<?php

namespace App\Libraries;

use App\Models\CostPoolModel;
use App\Models\PurchaseLineModel;
use App\Models\ServiceModel;

/**
 * Kostprijs per dienst uit inkoopkosten.
 *
 * Een kostenpool (bijv. "Hostingplatform") telt de inkoopfacturen en bonnetjes
 * uit Moneybird op van de gekozen categorieën (Plesk-licentie, VPS, Imunify, ...)
 * over de laatste N maanden en rekent dat om naar kosten per jaar.
 *
 * Die jaarkosten (x toerekeningspercentage, + handmatige extra kosten) worden
 * verdeeld over alle actieve abonnementen van de gekoppelde diensten, naar
 * aantal x gewicht. De uitkomst wordt als inkoopprijs bij de dienst opgeslagen,
 * zodat marges op het dashboard en bij klanten vanzelf kloppen.
 */
class CostPools
{
    /** Jaarkosten van een pool (vóór verdeling) */
    public static function yearlyCost(array $pool): float
    {
        $months = max(1, (int) $pool['lookback_months']);
        $synced = (float) ($pool['synced_amount'] ?? 0) * 12 / $months;

        return $synced * (float) $pool['allocation_pct'] / 100 + (float) ($pool['extra_yearly'] ?? 0);
    }

    /** Komma-lijst naar array */
    public static function idList(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn ($v) => $v !== ''));
    }

    /** Zoekwoorden: één per regel of komma-gescheiden */
    public static function keywords(array $pool): array
    {
        return array_values(array_filter(array_map(
            static fn ($k) => mb_strtolower(trim($k)),
            preg_split('/[\r\n,]+/', (string) ($pool['keywords'] ?? ''))
        ), static fn ($k) => $k !== ''));
    }

    /** Begindatum van de periode van een pool */
    public static function from(array $pool): string
    {
        return date('Y-m-d', strtotime('-' . max(1, (int) $pool['lookback_months']) . ' months +1 day'));
    }

    /**
     * Valt deze inkoopregel onder de regels van de pool (leverancier, zoekwoord of categorie)?
     */
    public static function ruleMatch(array $pool, array $line): bool
    {
        static $cache = [];
        $key = $pool['id'] . '|' . ($pool['updated_at'] ?? '');
        $cache[$key] ??= [
            'ledgers'   => array_flip(self::idList($pool['ledger_account_ids'] ?? '')),
            'suppliers' => array_flip(self::idList($pool['supplier_ids'] ?? '')),
            'keywords'  => self::keywords($pool),
        ];
        $r = $cache[$key];

        if ($line['ledger_id'] !== null && isset($r['ledgers'][$line['ledger_id']])) {
            return true;
        }
        if ($line['supplier_id'] !== null && isset($r['suppliers'][$line['supplier_id']])) {
            return true;
        }
        if ($r['keywords'] !== []) {
            $haystack = mb_strtolower($line['description'] . ' ' . $line['reference'] . ' ' . $line['supplier_name']);
            foreach ($r['keywords'] as $k) {
                if (str_contains($haystack, $k)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Telt deze regel mee: regel-match, tenzij uitgevinkt; of handmatig aangevinkt */
    public static function counts(array $pool, array $line): bool
    {
        $id = (string) $line['detail_id'];
        if (in_array($id, self::idList($pool['exclude_lines'] ?? ''), true)) {
            return false;
        }
        if (in_array($id, self::idList($pool['include_lines'] ?? ''), true)) {
            return true;
        }

        return self::ruleMatch($pool, $line);
    }

    /** Alle lokaal bewaarde inkoopregels in de periode van de pool */
    public static function linesInPeriod(array $pool): array
    {
        return model(PurchaseLineModel::class)
            ->where('date >=', self::from($pool))
            ->orderBy('date', 'DESC')->orderBy('supplier_name')
            ->findAll();
    }

    /**
     * Haalt alle inkoopfacturen en bonnetjes uit Moneybird op en bewaart de
     * regels lokaal. Daarna worden alle pools en kostprijzen herberekend.
     *
     * @return array{pools:int, documents:int, lines:int}
     */
    public static function sync(): array
    {
        $pools     = model(CostPoolModel::class)->findAll();
        $maxMonths = max([12, ...array_map(static fn ($p) => max(1, (int) $p['lookback_months']), $pools)]);
        $to        = date('Y-m-d');
        $from      = date('Y-m-d', strtotime('-' . $maxMonths . ' months +1 day'));

        $mb          = new Moneybird();
        $documents   = $mb->purchaseDocuments($from, $to);
        $ledgerNames = [];
        foreach ($mb->ledgerAccounts() as $la) {
            $ledgerNames[(string) $la['id']] = $la['name'];
        }

        $rows = [];
        foreach ($documents as $doc) {
            $date = $doc['date'] ?? substr((string) ($doc['created_at'] ?? ''), 0, 10);
            if ($date === '' || $date < $from || $date > $to) {
                continue;
            }
            $contact  = $doc['contact'] ?? [];
            $supplier = trim((string) ($contact['company_name'] ?? '')) ?: trim(($contact['firstname'] ?? '') . ' ' . ($contact['lastname'] ?? ''));
            $supplierId = (string) ($doc['contact_id'] ?? ($contact['id'] ?? ''));

            foreach ($doc['details'] ?? [] as $i => $d) {
                $ledger = (string) ($d['ledger_account_id'] ?? '');
                $rows[] = [
                    'detail_id'     => (string) ($d['id'] ?? ($doc['id'] . '-' . $i)),
                    'document_id'   => (string) $doc['id'],
                    'kind'          => $doc['_kind'],
                    'date'          => $date,
                    'reference'     => mb_substr((string) ($doc['reference'] ?? ''), 0, 255) ?: null,
                    'supplier_id'   => $supplierId !== '' ? $supplierId : null,
                    'supplier_name' => $supplier !== '' ? mb_substr($supplier, 0, 255) : 'Onbekend',
                    'ledger_id'     => $ledger !== '' ? $ledger : null,
                    'ledger_name'   => $ledger !== '' ? ($ledgerNames[$ledger] ?? $ledger) : null,
                    'description'   => mb_substr((string) ($d['description'] ?? ''), 0, 500),
                    'amount'        => round(self::detailExclTax($doc, $d), 2),
                ];
            }
        }

        // Periode vervangen door de verse gegevens
        $db = db_connect();
        $db->transStart();
        $db->table('purchase_lines')->where('date >=', $from)->delete();
        foreach (array_chunk($rows, 200) as $chunk) {
            $db->table('purchase_lines')->insertBatch($chunk);
        }
        $db->transComplete();

        model(CostPoolModel::class)->where('id >', 0)->set(['synced_at' => date('Y-m-d H:i:s')])->update();
        self::refreshPools();

        return ['pools' => count($pools), 'documents' => count($documents), 'lines' => count($rows)];
    }

    /**
     * Bedrag excl. btw van een factuurregel, in euro's.
     */
    private static function detailExclTax(array $doc, array $d): float
    {
        foreach (['total_price_excl_tax_with_discount_base', 'total_price_excl_tax_with_discount'] as $key) {
            if (isset($d[$key]) && $d[$key] !== null && $d[$key] !== '') {
                return (float) $d[$key];
            }
        }

        // Terugval: prijs x aantal, en btw eraf als prijzen incl. btw zijn
        $amount = (float) ($d['amount_decimal'] ?? 1) ?: 1.0;
        $total  = (float) ($d['price'] ?? 0) * $amount;
        if (! empty($doc['prices_are_incl_tax'])) {
            $total /= 1.21;
        }

        return $total;
    }

    /**
     * Rekent per pool het totaal en de opbouw uit op basis van de lokale
     * inkoopregels en de regels van de pool, en daarna de kostprijzen.
     */
    public static function refreshPools(): void
    {
        $model = model(CostPoolModel::class);
        foreach ($model->findAll() as $pool) {
            $total = 0.0;
            $bySupplier = $byLedger = [];
            foreach (self::linesInPeriod($pool) as $line) {
                if (! self::counts($pool, $line)) {
                    continue;
                }
                $total += (float) $line['amount'];
                $bySupplier[$line['supplier_name']] = ($bySupplier[$line['supplier_name']] ?? 0) + (float) $line['amount'];
                $cat = $line['ledger_name'] ?? 'Geen categorie';
                $byLedger[$cat] = ($byLedger[$cat] ?? 0) + (float) $line['amount'];
            }
            arsort($bySupplier);
            arsort($byLedger);

            $lastSync = db_connect()->table('purchase_lines')->selectMax('date')->get()->getRow('date');

            $model->update($pool['id'], [
                'synced_amount' => round($total, 2),
                'synced_from'   => self::from($pool),
                'synced_to'     => $lastSync ? date('Y-m-d') : null,
                'breakdown'     => json_encode(['suppliers' => $bySupplier, 'ledgers' => $byLedger], JSON_UNESCAPED_UNICODE),
            ]);
        }

        self::recalculate();
    }

    /**
     * Verdeelt de jaarkosten van elke pool over de gekoppelde diensten en
     * slaat de inkoopprijs (per eenheid, per factuurperiode) op bij de dienst.
     *
     * @return array<int, array> per pool: units, per_unit_year, services
     */
    public static function recalculate(): array
    {
        $db       = db_connect();
        $result   = [];
        $services = model(ServiceModel::class);

        foreach (model(CostPoolModel::class)->findAll() as $pool) {
            $linked = $db->table('services')
                ->select('services.id, services.name, services.interval_months, services.cost_weight, services.price, services.unit,
                    COALESCE(SUM(CASE WHEN subscriptions.status = \'active\' THEN subscriptions.quantity ELSE 0 END), 0) AS units')
                ->join('subscriptions', 'subscriptions.service_id = services.id', 'left')
                ->where('services.cost_pool_id', $pool['id'])
                ->groupBy('services.id, services.name, services.interval_months, services.cost_weight, services.price, services.unit')
                ->get()->getResultArray();

            $weighted = 0.0;
            foreach ($linked as $s) {
                $weighted += (float) $s['units'] * max(0.01, (float) $s['cost_weight']);
            }

            $yearly   = self::yearlyCost($pool);
            $perUnit  = $weighted > 0 ? $yearly / $weighted : null; // per gewicht-eenheid per jaar
            $out      = [];

            foreach ($linked as $s) {
                $unitYear = $perUnit === null ? null : $perUnit * max(0.01, (float) $s['cost_weight']);
                $purchase = $unitYear === null ? null : round($unitYear * max(1, (int) $s['interval_months']) / 12, 2);
                $services->update($s['id'], ['purchase_price' => $purchase]);
                $out[] = $s + ['unit_year' => $unitYear, 'purchase' => $purchase];
            }

            $result[$pool['id']] = ['yearly' => $yearly, 'units' => $weighted, 'per_unit_year' => $perUnit, 'services' => $out];
        }

        return $result;
    }
}
