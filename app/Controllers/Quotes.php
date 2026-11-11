<?php

namespace App\Controllers;

use App\Libraries\Catalog;
use App\Libraries\Moneybird;
use App\Models\CustomerModel;
use App\Models\ProductModel;
use App\Models\QuoteLineModel;
use App\Models\QuoteModel;
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
            $line = [
                'quote_id'    => $quote['id'],
                'product_id'  => ! empty($l['product_id']) ? (int) $l['product_id'] : null,
                'type'        => $type,
                'description' => $desc,
                'quantity'    => $type === 'text' ? 1 : ($num($l['quantity'] ?? 1) ?? 1),
                'cost_price'  => $type === 'text' ? null : $num($l['cost_price'] ?? ''),
                'sell_price'  => $type === 'text' ? 0 : ($num($l['sell_price'] ?? '') ?? 0),
                'sort'        => count($lines),
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
        model(QuoteLineModel::class)->where('quote_id', $quote['id'])->delete();
        foreach ($lines as $line) {
            model(QuoteLineModel::class)->insert($line);
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
            : ['description' => $l['description'], 'price' => $l['sell_price'], 'amount' => $l['quantity'], 'tax_rate_id' => $tax], $lines);

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
        }

        return $this->back('Status bijgewerkt.', 'message', site_url('offertes/' . $id));
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

    public function delete($id)
    {
        $this->findOr404(QuoteModel::class, $id);
        model(QuoteLineModel::class)->where('quote_id', $id)->delete();
        model(QuoteModel::class)->delete($id);

        return $this->back('Offerte verwijderd.', 'message', site_url('offertes'));
    }
}
