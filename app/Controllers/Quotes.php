<?php

namespace App\Controllers;

use App\Libraries\Catalog;
use App\Libraries\Moneybird;
use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\ProductModel;
use App\Models\QuoteLineModel;
use App\Models\QuoteModel;
use App\Models\SiteModel;
use App\Models\SupplierModel;
use Throwable;

/**
 * Offertes samenstellen uit de productcatalogus en als conceptofferte naar Moneybird sturen.
 */
class Quotes extends BaseController
{
    public const STATUSES = [
        'concept'      => ['Concept', 'secondary'],
        'moneybird'    => ['In Moneybird', 'primary'],
        'geaccepteerd' => ['Geaccepteerd', 'success'],
        'afgewezen'    => ['Afgewezen', 'danger'],
    ];

    public static function hourlyRate(): float
    {
        return (float) env('quote.hourlyRate', 65);
    }

    /** Totalen van een offerte */
    public static function totals(array $lines): array
    {
        $sell = $cost = $costKnown = $labor = 0.0;
        foreach ($lines as $l) {
            if ($l['type'] === 'text') {
                continue;
            }
            $sell += (float) $l['sell_price'] * (float) $l['quantity'];
            if ($l['type'] === 'labor') {
                $labor += (float) $l['sell_price'] * (float) $l['quantity'];
            }
            if ($l['cost_price'] !== null && $l['cost_price'] !== '') {
                $cost += (float) $l['cost_price'] * (float) $l['quantity'];
                $costKnown += (float) $l['sell_price'] * (float) $l['quantity'];
            }
        }

        return [
            'sell'   => round($sell, 2),
            'cost'   => round($cost, 2),
            'margin' => round($costKnown - $cost, 2),
            'pct'    => $costKnown > 0 ? round(($costKnown - $cost) / $costKnown * 100, 1) : null,
            'vat'    => round($sell * 0.21, 2),
            'labor'  => round($labor, 2),
        ];
    }

    public function index()
    {
        $status = $this->request->getGet('status');
        $model  = model(QuoteModel::class)->select('quotes.*, customers.name AS customer_name')
            ->join('customers', 'customers.id = quotes.customer_id')->orderBy('quotes.id', 'DESC');
        if ($status && isset(self::STATUSES[$status])) {
            $model->where('quotes.status', $status);
        }
        $quotes = $model->findAll(300);

        $lines = [];
        if ($quotes !== []) {
            foreach (model(QuoteLineModel::class)->whereIn('quote_id', array_column($quotes, 'id'))->findAll() as $l) {
                $lines[$l['quote_id']][] = $l;
            }
        }
        foreach ($quotes as &$q) {
            $q['totals'] = self::totals($lines[$q['id']] ?? []);
        }

        return $this->page('quotes/index', ['quotes' => $quotes, 'status' => $status, 'mb' => new Moneybird()], 'Offertes');
    }

    public function create()
    {
        $customers = model(CustomerModel::class)->where('active', 1)->where('internal', 0)->orderBy('name')->findAll();

        if (! $this->request->is('post')) {
            return $this->page('quotes/create', ['customers' => $customers, 'customerId' => $this->request->getGet('klant')], 'Nieuwe offerte');
        }

        $post = $this->request->getPost(['customer_id', 'title']);
        if (! $this->validateData($post, ['customer_id' => 'required|is_not_unique[customers.id]', 'title' => 'required|max_length[255]'])) {
            return $this->page('quotes/create', ['customers' => $customers, 'customerId' => $post['customer_id'], 'validation' => true], 'Nieuwe offerte');
        }
        $id = model(QuoteModel::class)->insert($post + ['status' => 'concept', 'user_id' => auth()->id()]);

        return redirect()->to(site_url('offertes/' . $id));
    }

    public function edit($id)
    {
        $quote = $this->findOr404(QuoteModel::class, $id);

        if ($this->request->is('post')) {
            return $this->save($quote);
        }

        $lines = model(QuoteLineModel::class)->where('quote_id', $id)->orderBy('sort')->orderBy('id')->findAll();

        return $this->page('quotes/edit', [
            'quote'     => $quote,
            'customer'  => model(CustomerModel::class)->find($quote['customer_id']),
            'lines'     => $lines,
            'sites'     => model(SiteModel::class)->where('customer_id', $quote['customer_id'])->orderBy('name')->findAll(),
            'totals'    => self::totals($lines),
            'rate'      => self::hourlyRate(),
            'markup'    => (float) (model(SupplierModel::class)->where('code', null)->first()['default_markup'] ?? 1.2),
            'mb'        => new Moneybird(),
        ], $quote['title']);
    }

    private function save(array $quote)
    {
        $post  = $this->request->getPost();
        $lines = [];
        $num   = static fn ($v) => ($v = trim(str_replace(',', '.', (string) $v))) === '' || ! is_numeric($v) ? null : (float) $v;
        $saveToCatalog = [];

        foreach ((array) ($post['lines'] ?? []) as $i => $l) {
            $desc = trim((string) ($l['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $type = in_array($l['type'] ?? '', ['material', 'labor', 'text'], true) ? $l['type'] : 'material';
            $qty  = $type === 'text' ? 1 : ($num($l['quantity'] ?? 1) ?? 1);
            $line = [
                'id'          => ! empty($l['id']) ? (int) $l['id'] : null,
                'quote_id'    => $quote['id'],
                'product_id'  => ! empty($l['product_id']) ? (int) $l['product_id'] : null,
                'type'        => $type,
                'description' => $desc,
                'quantity'    => $qty,
                'cost_price'  => $type === 'text' ? null : $num($l['cost_price'] ?? ''),
                'sell_price'  => $type === 'text' ? 0 : ($num($l['sell_price'] ?? '') ?? 0),
                'sort'        => count($lines),
                'placements'  => $type === 'material' ? self::cleanPlacements($l['placements'] ?? null, $qty) : null,
            ];
            $lines[] = $line;
            if ($type === 'material' && $line['product_id'] === null && ! empty($l['save'])) {
                $saveToCatalog[] = $line;
            }
        }

        $db = db_connect();
        $db->transStart();
        model(QuoteModel::class)->update($quote['id'], [
            'title' => trim((string) ($post['title'] ?? $quote['title'])) ?: $quote['title'],
            'notes' => trim((string) ($post['notes'] ?? '')) ?: null,
        ]);
        // Bestaande regels bijwerken (id blijft gelijk, zodat de koppeling met aangemaakte apparaten blijft),
        // nieuwe toevoegen en weggehaalde regels verwijderen.
        $existing = array_map('intval', array_column(model(QuoteLineModel::class)->select('id')->where('quote_id', $quote['id'])->findAll(), 'id'));
        $keep     = [];
        foreach ($lines as $line) {
            $id = $line['id'];
            unset($line['id']);
            if ($id !== null && in_array($id, $existing, true) && ! in_array($id, $keep, true)) {
                model(QuoteLineModel::class)->update($id, $line);
                $keep[] = $id;
            } else {
                model(QuoteLineModel::class)->insert($line);
            }
        }
        $gone = array_values(array_diff($existing, $keep));
        if ($gone !== []) {
            model(DeviceModel::class)->whereIn('quote_line_id', $gone)->set('quote_line_id', null)->update();
            model(QuoteLineModel::class)->whereIn('id', $gone)->delete();
        }

        // Losse producten bewaren in de catalogus (leverancier "Overig")
        $other = model(SupplierModel::class)->where('code', null)->orderBy('id')->first();
        foreach ($saveToCatalog as $line) {
            $exists = model(ProductModel::class)->where('supplier_id', $other['id'])->where('name', $line['description'])->first();
            $row = ['cost_price' => $line['cost_price'], 'sell_price' => $line['cost_price'] === null ? $line['sell_price'] : null, 'active' => 1];
            if ($exists) {
                model(ProductModel::class)->update($exists['id'], $row);
            } else {
                model(ProductModel::class)->insert($row + [
                    'supplier_id'  => $other['id'],
                    'supplier_sku' => 'M' . date('ymdHis') . random_int(100, 999),
                    'name'         => mb_substr($line['description'], 0, 500),
                    'source'       => 'manual',
                ]);
            }
        }
        $db->transComplete();

        $msg = 'Offerte opgeslagen.' . ($saveToCatalog ? ' ' . count($saveToCatalog) . ' los(se) product(en) bewaard in de catalogus.' : '');

        if (in_array($post['after'] ?? '', ['moneybird', 'moneybird_new'], true)) {
            return $this->sendToMoneybird((int) $quote['id'], $post['after'] === 'moneybird_new');
        }

        return $this->back($msg, 'message', site_url('offertes/' . $quote['id']));
    }

    public function toMoneybird($id)
    {
        return $this->sendToMoneybird((int) $id, (bool) $this->request->getPost('new_estimate'));
    }

    /**
     * Offerte naar Moneybird: bestaande gekoppelde offerte bijwerken, of een nieuwe aanmaken.
     */
    private function sendToMoneybird(int $id, bool $forceNew)
    {
        $quote    = $this->findOr404(QuoteModel::class, $id);
        $customer = model(CustomerModel::class)->find($quote['customer_id']);
        $lines    = model(QuoteLineModel::class)->where('quote_id', $id)->orderBy('sort')->findAll();

        if (empty($customer['moneybird_contact_id'])) {
            return $this->back('Deze klant is nog niet gekoppeld aan een Moneybird-contact.', 'error', site_url('offertes/' . $id));
        }
        if ($lines === []) {
            return $this->back('De offerte heeft nog geen regels.', 'error', site_url('offertes/' . $id));
        }

        $tax     = Moneybird::defaultTaxRateId();
        $details = array_map(static fn ($l) => $l['type'] === 'text'
            ? ['description' => $l['description'], 'text' => true]
            : ['description' => self::describeWithPlacements($l), 'price' => $l['sell_price'], 'amount' => $l['quantity'], 'tax_rate_id' => $tax], $lines);

        $mb = new Moneybird();

        try {
            $existing = ! $forceNew && $quote['moneybird_estimate_id'] ? $mb->getEstimate($quote['moneybird_estimate_id']) : null;
            if ($existing !== null) {
                $estimate = $mb->updateEstimate($existing, $customer['moneybird_contact_id'], $details, $quote['title']);
                $msg      = 'Offerte ' . ($estimate['estimate_id'] ?? '') . ' in Moneybird bijgewerkt (zelfde nummer, oude regels vervangen).';
                if (($existing['state'] ?? 'draft') !== 'draft') {
                    $msg .= ' Let op: deze offerte was al ' . self::mbState($existing['state']) . '; verstuur hem zo nodig opnieuw.';
                }
            } else {
                $estimate = $mb->createEstimate($customer['moneybird_contact_id'], $details, $quote['title']);
                $msg      = $quote['moneybird_estimate_id'] && ! $forceNew
                    ? 'De gekoppelde offerte bestond niet meer in Moneybird; er is een nieuwe conceptofferte aangemaakt.'
                    : 'Conceptofferte aangemaakt in Moneybird. Controleer en verstuur hem daar.';
            }
        } catch (Throwable $e) {
            return $this->back('Moneybird: ' . $e->getMessage(), 'error', site_url('offertes/' . $id));
        }

        model(QuoteModel::class)->update($id, [
            'status'                => in_array($quote['status'], ['geaccepteerd', 'afgewezen'], true) ? $quote['status'] : 'moneybird',
            'moneybird_estimate_id' => (string) ($estimate['id'] ?? $quote['moneybird_estimate_id']),
            'moneybird_number'      => $estimate['estimate_id'] ?? null,
        ]);

        return $this->back($msg, 'message', site_url('offertes/' . $id));
    }

    private static function mbState(string $state): string
    {
        return match ($state) {
            'open'    => 'verstuurd',
            'late'    => 'verlopen',
            'accepted' => 'geaccepteerd',
            'rejected' => 'afgewezen',
            'billed'  => 'gefactureerd',
            'archived' => 'gearchiveerd',
            default   => $state,
        };
    }

    public function status($id)
    {
        $this->findOr404(QuoteModel::class, $id);
        $status = (string) $this->request->getPost('status');
        if (isset(self::STATUSES[$status])) {
            model(QuoteModel::class)->update($id, ['status' => $status]);
            // Net geaccepteerd: meteen door naar apparaten aanmaken als er nog iets te doen is
            if ($status === 'geaccepteerd' && user_can('vault.manage') && $this->deviceCandidates((int) $id, true) !== []) {
                return $this->back('Offerte geaccepteerd. Controleer de apparaten en maak ze in één keer aan onder de klant.', 'message', site_url('offertes/' . $id . '/apparaten'));
            }
        }

        return $this->back('Status bijgewerkt.', 'message', site_url('offertes/' . $id));
    }

    /**
     * Materiaalregels van een offerte met het geraden apparaatsoort en hoeveel er nog aangemaakt moeten worden.
     *
     * @return list<array>
     */
    private function deviceCandidates(int $quoteId, bool $onlyGuessed = false): array
    {
        $lines = model(QuoteLineModel::class)
            ->select('quote_lines.*, products.sku, products.brand')
            ->join('products', 'products.id = quote_lines.product_id', 'left')
            ->where('quote_id', $quoteId)->where('quote_lines.type', 'material')
            ->orderBy('sort')->orderBy('quote_lines.id')->findAll();
        if ($lines === []) {
            return [];
        }

        $done = [];
        foreach (model(DeviceModel::class)->select('quote_line_id, COUNT(*) AS n')->whereIn('quote_line_id', array_column($lines, 'id'))->groupBy('quote_line_id')->findAll() as $r) {
            $done[$r['quote_line_id']] = (int) $r['n'];
        }

        $out = [];
        foreach ($lines as $l) {
            $guess   = device_type_guess(trim(($l['brand'] ?? '') . ' ' . $l['description'] . ' ' . ($l['sku'] ?? '')));
            $qty     = max(0, (int) round((float) $l['quantity']));
            $created = $done[$l['id']] ?? 0;
            $todo    = max(0, $qty - $created);
            if ($onlyGuessed && ($guess === null || $todo === 0)) {
                continue;
            }
            $out[] = $l + ['guess' => $guess, 'qty' => $qty, 'created' => $created, 'todo' => $todo];
        }

        return $out;
    }

    /** Apparaten aanmaken onder de klant vanuit een (geaccepteerde) offerte */
    public function devices($id)
    {
        $quote    = $this->findOr404(QuoteModel::class, $id);
        $customer = model(CustomerModel::class)->find($quote['customer_id']);
        $sites    = model(SiteModel::class)->where('customer_id', $quote['customer_id'])->orderBy('name')->findAll();
        $lines    = $this->deviceCandidates((int) $id);

        if (! $this->request->is('post')) {
            return $this->page('quotes/devices', compact('quote', 'customer', 'sites', 'lines'), 'Apparaten uit offerte');
        }

        $siteId = (int) $this->request->getPost('site_id') ?: null;
        if ($siteId !== null && ! in_array($siteId, array_map('intval', array_column($sites, 'id')), true)) {
            $siteId = null;
        }
        $installed = $this->request->getPost('installed_at') ?: null;
        if ($installed !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $installed)) {
            $installed = null;
        }
        $post  = (array) $this->request->getPost('line');
        $types = device_types();
        $model = model(DeviceModel::class);
        $made  = 0;

        $db = db_connect();
        $db->transStart();
        foreach ($lines as $l) {
            $p = $post[$l['id']] ?? null;
            if (! is_array($p) || empty($p['on'])) {
                continue;
            }
            $type = (string) ($p['type'] ?? '');
            $n    = min(200, max(0, (int) ($p['n'] ?? 0)));
            if (! isset($types[$type]) || $n === 0) {
                continue;
            }
            [$label, , $kind, $defBrand, $models] = $types[$type];

            // Model: bekend modelnaampje uit de lijst (langste treffer), anders typenummer, anders omschrijving
            $mdl = null;
            foreach ($models as $m) {
                if (stripos($l['description'], $m) !== false && ($mdl === null || strlen($m) > strlen($mdl))) {
                    $mdl = $m;
                }
            }
            if ($mdl === null && ! $l['sku'] && preg_match('/\b[A-Z]{1,6}-[A-Z0-9][A-Z0-9\/-]{3,}\b/i', $l['description'], $tm)) {
                $mdl = strtoupper($tm[0]); // typenummer uit de omschrijving, bv. DS-2CD2347G2-LU
            }
            $mdl ??= $l['sku'] ?: mb_substr(trim(preg_split('/\R/', $l['description'])[0]), 0, 100);

            $num   = $this->highestNumber((int) $quote['customer_id'], $label);
            $places = self::placements($l);
            $siteByName = [];
            foreach ($sites as $st) {
                $siteByName[mb_strtolower(trim($st['name']))] = (int) $st['id'];
            }
            $ip   = trim((string) ($p['ip'] ?? ''));
            $room = trim((string) ($p['room'] ?? '')) ?: null;
            for ($i = 0; $i < $n; $i++) {
                $num++;
                // Plaatsing uit de offerte: naam van een locatie van de klant -> die locatie, anders de ruimte
                $place    = $places[$l['created'] + $i] ?? '';
                $unitSite = $siteId;
                $unitRoom = $room;
                if ($place !== '') {
                    if (isset($siteByName[mb_strtolower($place)])) {
                        $unitSite = $siteByName[mb_strtolower($place)];
                    } else {
                        $unitRoom = $place;
                    }
                }
                $model->insert([
                    'customer_id'   => $quote['customer_id'],
                    'site_id'       => $unitSite,
                    'type'          => $type,
                    'name'          => ($n === 1 && $num === 1) ? $label : $label . ' ' . $num,
                    'room'          => $unitRoom ? mb_substr($unitRoom, 0, 100) : null,
                    'brand'         => $l['brand'] ?: $defBrand,
                    'model'         => mb_substr((string) $mdl, 0, 100),
                    'ip'            => ($kind === 'net' && $ip !== '') ? mb_substr($ip, 0, 100) : null,
                    'installed_at'  => $installed,
                    'notes'         => 'Uit offerte #' . $quote['id'],
                    'quote_line_id' => $l['id'],
                ]);
                $made++;
                if ($ip !== '') {
                    $ip = (string) self::nextIp($ip);
                }
            }
        }
        $db->transComplete();

        if ($made === 0) {
            return $this->back('Niets aangemaakt: vink minstens één regel aan met een soort en aantal.', 'error', site_url('offertes/' . $id . '/apparaten'));
        }

        return $this->back($made . ' apparaat/apparaten aangemaakt onder ' . $customer['name'] . '. Vul waar nodig IP, wachtwoord en Device-ID aan.', 'message', site_url('klanten/' . $customer['id']));
    }

    /** Hoogste volgnummer van "Label N" bij deze klant ("Label" zonder nummer telt als 1) */
    private function highestNumber(int $customerId, string $label): int
    {
        $max = 0;
        foreach (model(DeviceModel::class)->select('name')->where('customer_id', $customerId)->like('name', $label, 'after')->findAll() as $d) {
            if ($d['name'] === $label) {
                $max = max($max, 1);
            } elseif (preg_match('/^' . preg_quote($label, '/') . ' (\d+)$/u', $d['name'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    private static function nextIp(string $ip): string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
        $p = explode('.', $ip);
        if ((int) $p[3] < 254) {
            $p[3] = (string) ((int) $p[3] + 1);
        }

        return implode('.', $p);
    }

    public function copy($id)
    {
        $quote = $this->findOr404(QuoteModel::class, $id);
        $new   = model(QuoteModel::class)->insert([
            'customer_id' => $this->request->getPost('customer_id') ?: $quote['customer_id'],
            'title'       => $quote['title'] . ' (kopie)',
            'notes'       => $quote['notes'],
            'status'      => 'concept',
            'user_id'     => auth()->id(),
        ]);
        foreach (model(QuoteLineModel::class)->where('quote_id', $id)->orderBy('sort')->findAll() as $l) {
            unset($l['id']);
            model(QuoteLineModel::class)->insert(['quote_id' => $new] + $l);
        }

        return $this->back('Kopie gemaakt.', 'message', site_url('offertes/' . $new));
    }

    /** Prijzen van catalogusregels opnieuw ophalen uit de actuele catalogus */
    public function refreshPrices($id)
    {
        $this->findOr404(QuoteModel::class, $id);
        $n = 0;
        foreach (model(QuoteLineModel::class)->where('quote_id', $id)->where('product_id IS NOT NULL')->findAll() as $l) {
            $p = model(ProductModel::class)->find($l['product_id']);
            if ($p) {
                $pr = Catalog::price($p);
                model(QuoteLineModel::class)->update($l['id'], ['cost_price' => $pr['cost'], 'sell_price' => $pr['sell'] ?? $l['sell_price']]);
                $n++;
            }
        }

        return $this->back($n . ' regel(s) bijgewerkt naar de actuele prijzen.', 'message', site_url('offertes/' . $id));
    }

    /** Plaatsingen opschonen: één tekst per stuk, max. aantal stuks; null als alles leeg is */
    private static function cleanPlacements($raw, float $qty): ?string
    {
        if (! is_array($raw)) {
            return null;
        }
        $n   = min(200, max(0, (int) round($qty)));
        $out = array_map(static fn ($v) => mb_substr(trim((string) $v), 0, 100), array_slice(array_values($raw), 0, $n));
        while ($out !== [] && end($out) === '') {
            array_pop($out);
        }

        return array_filter($out, static fn ($v) => $v !== '') === [] ? null : json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /** @return list<string> */
    public static function placements(array $line): array
    {
        $p = json_decode((string) ($line['placements'] ?? ''), true);

        return is_array($p) ? array_map('strval', $p) : [];
    }

    /** Omschrijving voor Moneybird, met "Plaatsing: Kantine, Kantoor (2×)" eronder */
    private static function describeWithPlacements(array $line): string
    {
        $counts = [];
        foreach (self::placements($line) as $p) {
            if ($p !== '') {
                $counts[$p] = ($counts[$p] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return $line['description'];
        }
        $parts = [];
        foreach ($counts as $p => $c) {
            $parts[] = $c > 1 ? $p . ' (' . $c . '×)' : $p;
        }

        return $line['description'] . "\nPlaatsing: " . implode(', ', $parts);
    }

    /** Eén offerte verwijderen (vanuit de offerte zelf) */
    public function delete($id)
    {
        $this->findOr404(QuoteModel::class, $id);

        return $this->deleteMany([(int) $id]);
    }

    /** Meerdere offertes verwijderen (vanuit het overzicht) */
    public function deleteBulk()
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if ($ids === []) {
            return $this->back('Geen offertes geselecteerd.', 'error', site_url('offertes'));
        }

        return $this->deleteMany($ids);
    }

    /**
     * Offertes + regels verwijderen. Optioneel ook de gekoppelde offerte in Moneybird,
     * maar alleen als die daar nog concept is. Apparaten die uit de offerte zijn
     * aangemaakt blijven gewoon bestaan.
     */
    private function deleteMany(array $ids)
    {
        $alsoMb = (bool) $this->request->getPost('moneybird');
        $mb     = new Moneybird();
        $quotes = model(QuoteModel::class)->whereIn('id', $ids)->findAll();
        $kept   = [];
        $mbDone = 0;

        foreach ($quotes as $q) {
            if ($alsoMb && $q['moneybird_estimate_id'] && $mb->configured()) {
                try {
                    $est = $mb->getEstimate($q['moneybird_estimate_id']);
                    if ($est !== null && ($est['state'] ?? '') !== 'draft') {
                        $kept[] = ($q['moneybird_number'] ?: '#' . $q['id']) . ' (' . self::mbState((string) $est['state']) . ')';
                    } elseif ($est !== null) {
                        $mb->deleteEstimate($q['moneybird_estimate_id']);
                        $mbDone++;
                    }
                } catch (Throwable $e) {
                    $kept[] = ($q['moneybird_number'] ?: '#' . $q['id']) . ' (' . $e->getMessage() . ')';
                }
            }

            $lineIds = array_column(model(QuoteLineModel::class)->select('id')->where('quote_id', $q['id'])->findAll(), 'id');
            if ($lineIds !== []) {
                model(DeviceModel::class)->whereIn('quote_line_id', $lineIds)->set('quote_line_id', null)->update();
                model(QuoteLineModel::class)->whereIn('id', $lineIds)->delete();
            }
            model(QuoteModel::class)->delete($q['id']);
        }

        $n   = count($quotes);
        $msg = ($n === 1 ? 'Offerte' : $n . ' offertes') . ' verwijderd.';
        if ($mbDone > 0) {
            $msg .= ' ' . $mbDone . ' concept(en) ook in Moneybird verwijderd.';
        }
        if ($kept !== []) {
            $msg .= ' Niet verwijderd in Moneybird (niet meer concept, doe dat daar zelf): ' . implode(', ', $kept) . '.';
        }

        return $this->back($msg, $kept !== [] ? 'error' : 'message', site_url('offertes'));
    }
}
