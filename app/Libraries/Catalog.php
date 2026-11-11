<?php

namespace App\Libraries;

use App\Models\ProductModel;
use App\Models\SupplierGroupModel;
use App\Models\SupplierModel;
use RuntimeException;

/**
 * Productcatalogus: prijzen berekenen en importeren (OSEC-prijslijst, Wimood-productfeed).
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

        // Kopregel zoeken in de eerste 20 regels (er kunnen titelregels boven staan)
        $norm = static fn ($v) => trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $v)));
        $headIndex = null;
        foreach (array_slice($rows, 0, 20, true) as $i => $r) {
            $titles = array_map($norm, $r);
            $hasSku  = (bool) array_filter($titles, static fn ($t) => preg_match('/artikel ?n(r|ummer)|art\.? ?nr|^sku$|productcode/', $t));
            $hasName = (bool) array_filter($titles, static fn ($t) => preg_match('/artikel|omschrijving|product ?naam|naam/', $t));
            if ($hasSku && $hasName) {
                $headIndex = $i;
                break;
            }
        }
        if ($headIndex === null) {
            $first = implode(' | ', array_filter(array_map('strval', $rows[0] ?? [])));
            throw new RuntimeException('Geen kopregel met artikelnummer en omschrijving gevonden. Eerste regel: "' . mb_substr($first, 0, 200) . '"');
        }
        $head = array_map($norm, $rows[$headIndex]);
        $rows = array_slice($rows, $headIndex + 1);

        $used = [];
        $col  = static function (string $pattern) use ($head, &$used) {
            foreach ($head as $letter => $title) {
                if (! isset($used[$letter]) && preg_match($pattern, $title)) {
                    $used[$letter] = true;

                    return $letter;
                }
            }

            return null;
        };
        $cSku   = $col('/artikel ?n(r|ummer)|art\.? ?nr|^sku$|productcode/');
        $cNet   = $col('/netto|uw prijs|jouw prijs|inkoop|dealer|prijs na korting/');
        $cPrice = $col('/bruto|advies|catalogus|lijst ?prijs/') ?? $col('/prijs/');
        $cGroup = $col('/groepnaam|productgroep|kortingsgroep/');
        $cName  = $col('/^artikel$|omschrijving|product ?naam|^naam$|artikel/');
        $cInfo  = $col('/^info$|opmerking|status/');
        if ($cSku === null || $cName === null || ($cPrice === null && $cNet === null)) {
            throw new RuntimeException('Kolommen niet herkend. Gevonden: "' . implode(' | ', array_filter($head)) . '"');
        }

        $toNum = static function ($v) {
            $v = trim(str_replace(['€', ' '], '', (string) $v));
            if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $v)) { // 1.234,56
                $v = str_replace(['.', ','], ['', '.'], $v);
            } else {
                $v = str_replace(',', '.', $v);
            }

            return is_numeric($v) ? round((float) $v, 2) : null;
        };

        $supplier = self::supplierByCode('osec');

        $items = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r[$cSku] ?? ''));
            if ($sku === '' || ! preg_match('/\d/', $sku)) {
                continue; // lege regels, tussenkopjes
            }
            $items[] = [
                'supplier_sku' => $sku,
                'name'         => $r[$cName] ?? '',
                'list_price'   => $cPrice ? $toNum($r[$cPrice] ?? '') : null,
                'cost_price'   => $cNet ? $toNum($r[$cNet] ?? '') : null, // nettoprijs: korting zit er al in
                'group_name'   => $cGroup ? (($r[$cGroup] ?? '') ?: null) : null,
                'info'         => $cInfo ? (($r[$cInfo] ?? '') ?: null) : null,
                'brand'        => self::guessBrand($r[$cName] ?? ''),
            ];
        }
        if ($items === []) {
            throw new RuntimeException('Geen artikelen gevonden onder de kopregel "' . implode(' | ', array_filter($head)) . '".');
        }

        $supplier = self::supplierByCode('osec');
        $result   = self::upsert($supplier, $items, 'osec', true);

        // Nettoprijs ontbreekt in deze lijst: oude nettoprijzen niet laten staan
        if ($cNet === null) {
            model(ProductModel::class)->where('supplier_id', $supplier['id'])->where('source', 'osec')->set(['cost_price' => null])->update();
        }

        return $result + ['net' => $cNet !== null, 'total' => count($items)];
    }

    /**
     * Wimood: complete productfeed met jouw inkoopprijs (XML).
     *
     * https://www.wimoodshop.nl/api/index.php?api_key=…&klantnummer=…
     * Per product: product_id, brand, product_code, ean, product_name, stock, msrp (advies), prijs (jouw prijs).
     *
     * @return array{new:int, updated:int, inactive:int, groups:int, total:int}
     */
    public static function syncWimood(): array
    {
        $key      = (string) env('wimood.apiKey', '');
        $customer = (string) env('wimood.klantnummer', '');
        if ($key === '' || $customer === '') {
            throw new RuntimeException('Wimood is niet ingesteld (wimood.apiKey en wimood.klantnummer in .env).');
        }
        $url = (string) env('wimood.feedUrl', 'https://www.wimoodshop.nl/api/index.php');

        $res = \Config\Services::curlrequest([], null, null, false)->request('GET', $url, [
            'query'       => ['api_key' => $key, 'klantnummer' => $customer],
            'http_errors' => false,
            'timeout'     => 120,
        ]);
        $body = (string) $res->getBody();
        if ($res->getStatusCode() >= 400 || ! str_contains($body, '<products')) {
            throw new RuntimeException('Wimood gaf geen productlijst terug (' . $res->getStatusCode() . '): ' . mb_substr(strip_tags($body), 0, 200));
        }

        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($body);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            throw new RuntimeException('De productlijst van Wimood kon niet worden gelezen.');
        }

        $items = [];
        foreach ($xml->product as $p) {
            $price = (string) $p->prijs;
            $msrp  = (string) $p->msrp;
            $brand = trim((string) $p->brand);
            $stock = (string) $p->stock;
            $items[] = [
                'supplier_sku' => trim((string) $p->product_id),
                'sku'          => trim((string) $p->product_code) ?: null,
                'name'         => trim((string) $p->product_name),
                'brand'        => $brand ?: null,
                'group_name'   => $brand ?: 'Overig',
                'cost_price'   => is_numeric($price) ? round((float) $price, 2) : null,
                'list_price'   => is_numeric($msrp) ? round((float) $msrp, 2) : null,
                'info'         => $stock !== '' ? 'Voorraad ' . (int) $stock : null,
            ];
        }
        if ($items === []) {
            throw new RuntimeException('De productlijst van Wimood is leeg.');
        }

        return self::upsert(self::supplierByCode('wimood'), $items, 'wimood', true) + ['total' => count($items)];
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
            $row = array_filter($it, static fn ($v, $k) => $v !== null || in_array($k, ['cost_price', 'list_price'], true), ARRAY_FILTER_USE_BOTH)
                + ['source' => $source, 'active' => 1, 'last_seen_at' => $now];
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
