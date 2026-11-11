<?php

namespace App\Libraries;

use App\Models\CustomerModel;
use App\Models\DsdItemModel;
use App\Models\SubscriptionModel;
use RuntimeException;

/**
 * Koppeling met de DSD Europe API (https://developer.dsdeurope.com).
 *
 * Gebruikt:
 *  - End Client API      GET /v1/end-clients
 *  - Contract Management GET /v1/contracts  (per contract: eindklant, producten,
 *                                           aantal, inkoopprijs, verlengdatum)
 *
 * Instelling in .env:  dsd.apiKey = <API-sleutel uit je DSD-account>
 */
class Dsd
{
    private string $apiKey;
    private string $contractUrl;
    private string $clientUrl;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey      = (string) ($apiKey ?? env('dsd.apiKey', ''));
        $this->contractUrl = rtrim((string) env('dsd.contractUrl', 'https://contract-management-api.dsdeurope.com'), '/');
        $this->clientUrl   = rtrim((string) env('dsd.clientUrl', 'https://end-client-api.dsdeurope.com'), '/');
    }

    public function configured(): bool
    {
        return $this->apiKey !== '';
    }

    /** Maanden per factuur-/termijnperiode van DSD */
    public static function periodMonths(?string $period): ?int
    {
        return match ($period) {
            'one_month'   => 1,
            'one_year'    => 12,
            'three_years' => 36,
            default       => null,
        };
    }

    public static function periodLabel(?string $period): string
    {
        return match ($period) {
            'one_month'   => 'maand',
            'one_year'    => 'jaar',
            'three_years' => '3 jaar',
            'one_time'    => 'eenmalig',
            default       => (string) $period,
        };
    }

    /** @return list<array> alle eindklanten */
    public function endClients(): array
    {
        return $this->paginate($this->clientUrl . '/v1/end-clients', []);
    }

    /** @return list<array> alle contracten (standaard alleen actieve) */
    public function contracts(?string $status = 'active'): array
    {
        return $this->paginate($this->contractUrl . '/v1/contracts', $status ? ['status' => $status] : []);
    }

    private function paginate(string $url, array $query): array
    {
        $all = [];
        for ($offset = 0, $i = 0; $i < 200; $i++) {
            $data  = $this->get($url, $query + ['offset' => $offset, 'limit' => 100]);
            $batch = $data['result'] ?? [];
            array_push($all, ...$batch);
            $total = (int) ($data['pagination']['totalCount'] ?? count($all));
            $offset += count($batch);
            if ($batch === [] || $offset >= $total) {
                break;
            }
        }

        return $all;
    }

    private function get(string $url, array $query): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('DSD is niet ingesteld (dsd.apiKey in .env).');
        }

        $response = \Config\Services::curlrequest([], null, null, false)->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept'        => 'application/json',
            ],
            'query'       => $query,
            'http_errors' => false,
            'timeout'     => 30,
        ]);

        $status = $response->getStatusCode();
        $data   = json_decode((string) $response->getBody(), true);

        if ($status >= 400) {
            $msg = is_array($data) ? trim(($data['title'] ?? '') . ' ' . ($data['detail'] ?? '')) : '';

            throw new RuntimeException('DSD gaf een fout (' . $status . ')' . ($msg !== '' ? ': ' . $msg : '') . '.');
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Haalt eindklanten en actieve contracten op, bewaart de licenties lokaal,
     * koppelt eindklanten op naam aan klanten en werkt de inkoopprijs van
     * gekoppelde abonnementen bij.
     *
     * @return array{items:int, clients:int, matched:int, purchase:int}
     */
    public function sync(): array
    {
        $clients   = $this->endClients();
        $contracts = $this->contracts('active');

        $clientNames = [];
        foreach ($clients as $c) {
            $clientNames[(string) $c['id']] = trim((string) ($c['company'] ?: ($c['name'] ?? '') ?: trim(($c['firstName'] ?? '') . ' ' . ($c['lastName'] ?? ''))));
        }

        $rows = [];
        $now  = date('Y-m-d H:i:s');
        foreach ($contracts as $contract) {
            $clientId = isset($contract['client']['id']) ? (string) $contract['client']['id'] : null;
            foreach ($contract['items'] ?? [] as $i => $item) {
                $product = $item['product'] ?? [];
                $price   = $product['prices'][0] ?? [];
                $key     = $item['subscriptionId'] ?: ($contract['id'] . '-' . ($product['sku'] ?? $i));
                $rows[]  = [
                    'item_key'         => mb_substr((string) $key, 0, 100),
                    'contract_id'      => (string) $contract['id'],
                    'dsd_client_id'    => $clientId,
                    'client_name'      => $clientId ? ($clientNames[$clientId] ?? ($contract['client']['company'] ?? null)) : 'Eigen gebruik',
                    'sku'              => $product['sku'] ?? null,
                    'product_name'     => mb_substr((string) ($product['name'] ?? 'Onbekend product'), 0, 255),
                    'brand'            => $product['brand'] ?? null,
                    'quantity'         => isset($price['quantity']) ? (int) $price['quantity'] : null,
                    'unit'             => $price['unit'] ?? null,
                    'price'            => $price['price'] ?? null,
                    'advisory_price'   => $price['advisoryPrice'] ?? null,
                    'invoicing_period' => $contract['invoicingPeriod'] ?? null,
                    'commitment_term'  => $contract['commitmentTerm'] ?? null,
                    'renewal_date'     => ! empty($contract['renewalDate']) ? substr($contract['renewalDate'], 0, 10) : null,
                    'status'           => (string) ($item['status'] ?? $contract['status'] ?? 'active'),
                    'sale_reference'   => $contract['saleReference'] ?? null,
                    'synced_at'        => $now,
                ];
            }
        }

        $db = db_connect();
        $db->transStart();
        $db->table('dsd_items')->truncate();
        foreach (array_chunk($rows, 200) as $chunk) {
            $db->table('dsd_items')->insertBatch($chunk);
        }
        $db->transComplete();

        // Eindklanten op naam koppelen aan klanten die nog niet gekoppeld zijn
        $matched   = 0;
        $customers = model(CustomerModel::class);
        $byName    = [];
        foreach ($customers->where('dsd_client_id', null)->findAll() as $c) {
            $byName[mb_strtolower(trim($c['name']))] = $c['id'];
        }
        foreach ($clientNames as $id => $name) {
            $k = mb_strtolower($name);
            if ($name !== '' && isset($byName[$k]) && $customers->where('dsd_client_id', $id)->countAllResults() === 0) {
                $customers->update($byName[$k], ['dsd_client_id' => $id]);
                unset($byName[$k]);
                $matched++;
            }
        }

        return ['items' => count($rows), 'clients' => count($clients), 'matched' => $matched, 'purchase' => self::applyPurchasePrices()];
    }

    /**
     * Inkoopprijs per factuurperiode van het abonnement, uit de DSD-prijs
     * (DSD geeft de prijs per eenheid per factuurperiode van het contract).
     */
    public static function purchaseFor(array $item, int $intervalMonths): ?float
    {
        $months = self::periodMonths($item['invoicing_period']);
        if ($item['price'] === null || $months === null) {
            return null;
        }

        return round((float) $item['price'] / $months * max(1, $intervalMonths), 2);
    }

    /** Zet de DSD-inkoopprijs op alle gekoppelde abonnementen */
    public static function applyPurchasePrices(): int
    {
        $items = array_column(model(DsdItemModel::class)->findAll(), null, 'item_key');
        $subs  = model(SubscriptionModel::class);
        $n     = 0;
        foreach ($subs->where('dsd_item_key IS NOT NULL')->findAll() as $s) {
            if (! isset($items[$s['dsd_item_key']])) {
                continue;
            }
            $purchase = self::purchaseFor($items[$s['dsd_item_key']], (int) $s['interval_months']);
            if ($purchase !== null && (string) $purchase !== (string) $s['purchase_price']) {
                $subs->update($s['id'], ['purchase_price' => $purchase]);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Vergelijking per DSD-licentie: aan welk abonnement gekoppeld, en klopt het aantal?
     *
     * @return list<array>
     */
    public static function overview(): array
    {
        $items     = model(DsdItemModel::class)->orderBy('client_name')->orderBy('product_name')->findAll();
        $customers = [];
        foreach (model(CustomerModel::class)->where('dsd_client_id IS NOT NULL')->findAll() as $c) {
            $customers[$c['dsd_client_id']] = $c;
        }
        $linked = [];
        foreach (model(SubscriptionModel::class)->withDetails()->where('subscriptions.dsd_item_key IS NOT NULL')->findAll() as $s) {
            $linked[$s['dsd_item_key']][] = $s;
        }

        foreach ($items as &$item) {
            $item['customer'] = $item['dsd_client_id'] ? ($customers[$item['dsd_client_id']] ?? null) : null;
            $item['subs']     = $linked[$item['item_key']] ?? [];
            $billed           = array_sum(array_map(static fn ($s) => $s['status'] === 'active' ? (float) $s['quantity'] : 0, $item['subs']));
            $item['billed']   = $billed;
            $item['check']    = match (true) {
                $item['dsd_client_id'] === null => 'own',
                $item['customer'] === null      => 'no_customer',
                $item['subs'] === []            => 'no_sub',
                (int) $item['quantity'] === (int) round($billed) => 'ok',
                $item['quantity'] > $billed     => 'under',
                default                         => 'over',
            };
        }

        return $items;
    }
}
