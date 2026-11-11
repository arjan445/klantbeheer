<?php

namespace App\Libraries;

use App\Models\CostPoolModel;
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

    public static function ledgerIds(array $pool): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $pool['ledger_account_ids']))));
    }

    /**
     * Haalt de inkoopkosten op uit Moneybird voor alle pools (of één pool)
     * en rekent daarna de kostprijzen opnieuw uit.
     *
     * @return array{pools:int, documents:int}
     */
    public static function sync(?int $poolId = null): array
    {
        $model = model(CostPoolModel::class);
        $pools = $poolId ? array_filter([$model->find($poolId)]) : $model->findAll();
        if ($pools === []) {
            return ['pools' => 0, 'documents' => 0];
        }

        // Eén keer ophalen over de langste periode die een pool nodig heeft
        $maxMonths = max(array_map(static fn ($p) => max(1, (int) $p['lookback_months']), $pools));
        $to        = date('Y-m-d');
        $from      = date('Y-m-d', strtotime('-' . $maxMonths . ' months +1 day'));

        $mb        = new Moneybird();
        $documents = $mb->purchaseDocuments($from, $to);

        $ledgerNames = [];
        foreach ($mb->ledgerAccounts() as $la) {
            $ledgerNames[(string) $la['id']] = $la['name'];
        }

        foreach ($pools as $pool) {
            $ids      = array_flip(self::ledgerIds($pool));
            $poolFrom = date('Y-m-d', strtotime('-' . max(1, (int) $pool['lookback_months']) . ' months +1 day'));
            $total    = 0.0;
            $byLedger = $bySupplier = $lines = [];

            foreach ($documents as $doc) {
                $date = $doc['date'] ?? substr((string) ($doc['created_at'] ?? ''), 0, 10);
                if ($date < $poolFrom || $date > $to) {
                    continue;
                }
                $supplier = $doc['contact']['company_name'] ?? trim(($doc['contact']['firstname'] ?? '') . ' ' . ($doc['contact']['lastname'] ?? ''));
                $supplier = $supplier ?: 'Onbekend';

                foreach ($doc['details'] ?? [] as $d) {
                    $ledger = (string) ($d['ledger_account_id'] ?? '');
                    if (! isset($ids[$ledger])) {
                        continue;
                    }
                    $amount = self::detailExclTax($doc, $d);
                    $total += $amount;
                    $name = $ledgerNames[$ledger] ?? $ledger;
                    $byLedger[$name]     = ($byLedger[$name] ?? 0) + $amount;
                    $bySupplier[$supplier] = ($bySupplier[$supplier] ?? 0) + $amount;
                    $lines[] = [
                        'date'     => $date,
                        'supplier' => $supplier,
                        'ledger'   => $name,
                        'text'     => mb_substr((string) ($d['description'] ?? ''), 0, 80),
                        'amount'   => round($amount, 2),
                        'kind'     => $doc['_kind'],
                        'id'       => (string) $doc['id'],
                    ];
                }
            }

            arsort($byLedger);
            arsort($bySupplier);
            usort($lines, static fn ($a, $b) => strcmp($b['date'], $a['date']));

            $model->update($pool['id'], [
                'synced_amount' => round($total, 2),
                'synced_from'   => $poolFrom,
                'synced_to'     => $to,
                'synced_at'     => date('Y-m-d H:i:s'),
                'breakdown'     => json_encode([
                    'ledgers'   => $byLedger,
                    'suppliers' => $bySupplier,
                    'lines'     => array_slice($lines, 0, 200),
                ], JSON_UNESCAPED_UNICODE),
            ]);
        }

        self::recalculate();

        return ['pools' => count($pools), 'documents' => count($documents)];
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
