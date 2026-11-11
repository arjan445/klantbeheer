<?php

namespace App\Libraries;

use App\Models\ProductModel;
use App\Models\SupplierGroupModel;
use App\Models\SupplierModel;
use RuntimeException;

/**
 * Productcatalogus: prijzen berekenen en importeren (OSEC-prijslijst, Wimood-bestellingen).
 *
 * Kostprijs  = vaste inkoopprijs (Wimood/handmatig), anders bruto × (1 − korting%)
 * Verkoop    = vaste verkoopprijs, anders kostprijs × opslag (standaard 1,2)
 * Korting en opslag: product > productgroep > leverancier.
 */
class Catalog
{
    private static ?array $suppliers = null;
    private static ?array $groups    = null;

    public static function reset(): void
    {
        self::$suppliers = self::$groups = null;
    }

    private static function load(): void
    {
        if (self::$suppliers !== null) {
            return;
        }
        self::$suppliers = array_column(model(SupplierModel::class)->findAll(), null, 'id');
        self::$groups    = [];
        foreach (model(SupplierGroupModel::class)->findAll() as $g) {
            self::$groups[$g['supplier_id']][$g['group_name']] = $g;
        }
    }

    /** @return array{cost:?float, markup:float, sell:?float, discount:float} */
    public static function price(array $p): array
    {
        self::load();
        $supplier = self::$suppliers[$p['supplier_id']] ?? ['default_discount' => 0, 'default_markup' => 1.2];
        $group    = self::$groups[$p['supplier_id']][$p['group_name'] ?? ''] ?? null;

        $discount = (float) ($group['discount'] ?? $supplier['default_discount']);
        $markup   = (float) ($p['markup'] ?? $group['markup'] ?? $supplier['default_markup']) ?: 1.2;

        if ($p['cost_price'] !== null && $p['cost_price'] !== '') {
            $cost = (float) $p['cost_price'];
        } elseif ($p['list_price'] !== null && $p['list_price'] !== '') {
            $cost = round((float) $p['list_price'] * (1 - $discount / 100), 2);
        } else {
            $cost = null;
        }

        $sell = $p['sell_price'] !== null && $p['sell_price'] !== ''
            ? (float) $p['sell_price']
            : ($cost === null ? null : round($cost * $markup, 2));

        return ['cost' => $cost, 'markup' => $markup, 'sell' => $sell, 'discount' => $discount];
    }

    public static function supplierByCode(string $code): array
    {
        $s = model(SupplierModel::class)->where('code', $code)->first();
        if ($s === null) {
            throw new RuntimeException('Leverancier ' . $code . ' ontbreekt.');
        }

        return $s;
    }

    /**
     * OSEC-prijslijst (.xlsx, "Artikelnr | Artikel | Bruto prijs | Groep | Groepnaam | Info").
     *
     * @return array{new:int, updated:int, inactive:int, groups:int}
     */
    public static function importOsec(string $file): array
    {
        $rows = XlsxReader::rows($file);
        if ($rows === []) {
            throw new RuntimeException('Het bestand is leeg.');
        }

        // Kolommen zoeken op kopnaam
        $head = array_map('mb_strtolower', array_shift($rows));
        $used = [];
        $col  = static function (array $names) use ($head, &$used) {
            // Eerst exacte kopnaam, daarna "bevat"; een kolom wordt maar één keer gebruikt
            foreach ([true, false] as $exact) {
                foreach ($names as $n) {
                    foreach ($head as $letter => $title) {
                        if (! isset($used[$letter]) && ($exact ? trim($title) === $n : str_contains($title, $n))) {
                            $used[$letter] = true;

                            return $letter;
                        }
                    }
                }
            }

            return null;
        };
        $cSku   = $col(['artikelnr', 'artikelnummer']);
        $cGroup = $col(['groepnaam']);
        $cName  = $col(['artikel', 'omschrijving', 'artikelomschrijving']);
        $cPrice = $col(['bruto prijs', 'bruto', 'prijs']);
        $cInfo  = $col(['info']);
        if ($cSku === null || $cName === null || $cPrice === null) {
            throw new RuntimeException('Kolommen Artikelnr, Artikel en Bruto prijs niet gevonden. Is dit de OSEC-prijslijst?');
        }

        $supplier = self::supplierByCode('osec');

        return self::upsert($supplier, array_map(static fn ($r) => [
            'supplier_sku' => $r[$cSku] ?? '',
            'name'         => $r[$cName] ?? '',
            'list_price'   => is_numeric(str_replace(',', '.', $r[$cPrice] ?? '')) ? (float) str_replace(',', '.', $r[$cPrice]) : null,
            'group_name'   => $cGroup ? (($r[$cGroup] ?? '') ?: null) : null,
            'info'         => $cInfo ? (($r[$cInfo] ?? '') ?: null) : null,
            'brand'        => self::guessBrand($r[$cName] ?? ''),
        ], $rows), 'osec', true);
    }

    /**
     * Wimood: producten en echte inkoopprijzen uit je bestellingen (API).
     *
     * @return array{new:int, updated:int, inactive:int, groups:int, orders:int}
     */
    public static function syncWimood(): array
    {
        $key = (string) env('wimood.apiKey', '');
        if ($key === '') {
            throw new RuntimeException('Wimood is niet ingesteld (wimood.apiKey in .env).');
        }
        $base = rtrim((string) env('wimood.url', 'https://api.wimood.nl/v1'), '/');
        $get  = static function (string $path, array $query = []) use ($base, $key) {
            $res = \Config\Services::curlrequest([], null, null, false)->request('GET', $base . $path, [
                'headers'     => ['X-AUTH-TOKEN' => $key, 'Accept' => 'application/json'],
                'query'       => $query,
                'http_errors' => false,
                'timeout'     => 30,
            ]);
            $data = json_decode((string) $res->getBody(), true);
            if ($res->getStatusCode() >= 400) {
                throw new RuntimeException('Wimood gaf een fout (' . $res->getStatusCode() . '): ' . ($data['message'] ?? implode(' ', (array) ($data['errors'] ?? []))));
            }

            return is_array($data) ? $data : [];
        };

        // Alle bestellingen (nieuwste prijs wint)
        $orders = [];
        for ($offset = 0; $offset < 10000; $offset += 100) {
            $page = $get('/orders', ['limit' => 100, 'offset' => $offset]);
            array_push($orders, ...($page['orders'] ?? []));
            if (count($page['orders'] ?? []) < 100 || $offset + 100 >= (int) ($page['total'] ?? 0)) {
                break;
            }
        }
        usort($orders, static fn ($a, $b) => strcmp($a['order_date'], $b['order_date']));

        $items = [];
        foreach ($orders as $o) {
            foreach ($get('/orders/' . rawurlencode((string) $o['order_number']))['products'] ?? [] as $p) {
                if (empty($p['product_id']) || (float) ($p['price'] ?? 0) <= 0 || empty($p['brand'])) {
                    continue; // kortingsregels e.d.
                }
                $items[(string) $p['product_id']] = [
                    'supplier_sku' => (string) $p['product_id'],
                    'sku'          => $p['sku'] ?? null,
                    'name'         => $p['product_name'] ?? $p['sku'],
                    'brand'        => $p['brand'],
                    'group_name'   => $p['brand'],
                    'cost_price'   => round((float) $p['price'], 2),
                    'info'         => 'Laatst besteld ' . substr($o['order_date'], 0, 10),
                ];
            }
        }

        $r = self::upsert(self::supplierByCode('wimood'), array_values($items), 'wimood', false);

        return $r + ['orders' => count($orders)];
    }

    /**
     * Producten bijwerken of toevoegen.
     *
     * @param bool $fullList true = complete prijslijst: wat er niet meer in staat, wordt inactief
     */
    private static function upsert(array $supplier, array $items, string $source, bool $fullList): array
    {
        $model    = model(ProductModel::class);
        $existing = [];
        foreach ($model->select('id, supplier_sku')->where('supplier_id', $supplier['id'])->findAll() as $p) {
            $existing[(string) $p['supplier_sku']] = (int) $p['id'];
        }

        $now = date('Y-m-d H:i:s');
        $new = $updated = 0;
        $seen = $groups = [];
        $db = db_connect();
        $db->transStart();
        foreach ($items as $it) {
            $sku = trim((string) $it['supplier_sku']);
            if ($sku === '' || trim((string) $it['name']) === '') {
                continue;
            }
            $row = array_filter($it, static fn ($v) => $v !== null) + ['source' => $source, 'active' => 1, 'last_seen_at' => $now];
            $row['name'] = mb_substr(trim($row['name']), 0, 500);
            if (! empty($it['group_name'])) {
                $groups[$it['group_name']] = true;
            }
            $seen[$sku] = true;
            if (isset($existing[$sku])) {
                $model->update($existing[$sku], $row);
                $updated++;
            } else {
                $model->insert($row + ['supplier_id' => $supplier['id'], 'supplier_sku' => $sku]);
                $new++;
            }
        }

        $inactive = 0;
        if ($fullList) {
            $gone = array_diff_key($existing, $seen);
            foreach (array_chunk(array_values($gone), 500) as $ids) {
                $model->whereIn('id', $ids)->where('source', $source)->set(['active' => 0])->update();
                $inactive += count($ids);
            }
        }

        // Productgroepen registreren zodat je er een korting/opslag op kunt zetten
        $groupModel = model(SupplierGroupModel::class);
        $known = array_flip(array_column($groupModel->where('supplier_id', $supplier['id'])->findAll(), 'group_name'));
        foreach (array_keys($groups) as $g) {
            if (! isset($known[$g])) {
                // Groepen met nettoprijzen krijgen geen korting
                $groupModel->insert(['supplier_id' => $supplier['id'], 'group_name' => mb_substr($g, 0, 150), 'discount' => stripos($g, 'netto') !== false ? 0 : null]);
            }
        }
        model(SupplierModel::class)->update($supplier['id'], ['last_import_at' => $now]);
        $db->transComplete();
        self::reset();

        return ['new' => $new, 'updated' => $updated, 'inactive' => $inactive, 'groups' => count($groups)];
    }

    /** Merk uit de artikelnaam halen (eerste woord als dat een bekend merk is) */
    public static function guessBrand(string $name): ?string
    {
        foreach (['Hikvision', 'Ajax', 'Dahua', 'Paxton', 'Honeywell', 'Ubiquiti', 'Zyxel', 'Axis', 'Bosch', 'Satel', 'Tedee', 'Morley', 'Notifier', 'Hilook', 'Uniview', 'Synology', 'Western Digital', 'Seagate', 'Akuvox', '2N'] as $b) {
            if (stripos($name, $b) !== false) {
                return $b;
            }
        }

        return null;
    }
}
