<?php

namespace App\Controllers;

use App\Libraries\Network;
use App\Libraries\Network\Connector;
use App\Libraries\ScanCode;
use App\Libraries\Vault;
use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\NetworkDeviceModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\QuoteLineModel;
use App\Models\QuoteModel;
use App\Models\SiteModel;
use App\Models\SupplierModel;
use Throwable;

/**
 * Doosscan: met de camera van de telefoon een serienummer of MAC-adres scannen
 * en dat aan een apparaat in de kluis hangen (UniFi, UISP, Hikvision, Ajax).
 */
class Scan extends BaseController
{
    public function index()
    {
        $quotes = model(QuoteModel::class)->select('quotes.id, quotes.title, quotes.customer_id, quotes.moneybird_number, customers.name AS customer_name')
            ->join('customers', 'customers.id = quotes.customer_id')
            ->where('quotes.status', 'geaccepteerd')->orderBy('quotes.updated_at', 'DESC')->findAll(60);

        return $this->page('scan/index', [
            'customers' => model(CustomerModel::class)->select('id, name')->where('active', 1)->orderBy('name')->findAll(),
            'sites'     => model(SiteModel::class)->select('id, customer_id, name')->orderBy('name')->findAll(),
            'quotes'    => $quotes,
            'invoices'  => model(PurchaseInvoiceModel::class)->options(100),
            'suppliers' => model(SupplierModel::class)->orderBy('name')->findAll(),
            'pre'       => [
                'invoice'  => (int) $this->request->getGet('factuur') ?: null,
                'customer' => (int) $this->request->getGet('klant') ?: null,
                'site'     => (int) $this->request->getGet('locatie') ?: null,
                'quote'    => (int) $this->request->getGet('offerte') ?: null,
            ],
            'types' => device_type_groups(),
        ], 'Doosscan');
    }

    /** Wat is deze code, en bij welk apparaat hoort hij? (JSON) */
    public function lookup()
    {
        $p          = ScanCode::parse((string) $this->request->getPost('code'));
        $customerId = (int) $this->request->getPost('customer_id') ?: null;
        $siteId     = (int) $this->request->getPost('site_id') ?: null;
        $quoteId    = (int) $this->request->getPost('quote_id') ?: null;

        if ($p['raw'] === '') {
            return $this->json(['error' => 'Geen code.'], 422);
        }

        // Al in de kluis?
        $existing = [];
        $needles  = ScanCode::needles($p);
        $db       = db_connect();
        $inList   = $needles === [] ? null : '(' . implode(',', array_map(static fn ($n) => $db->escape($n), $needles)) . ')';
        if ($inList !== null || $p['mac']) {
            $q = model(DeviceModel::class)->select('devices.id, devices.name, devices.type, devices.customer_id, devices.site_id, devices.serial, devices.mac, customers.name AS customer_name, sites.name AS site_name')
                ->join('customers', 'customers.id = devices.customer_id')->join('sites', 'sites.id = devices.site_id', 'left')
                ->groupStart()->where('1 = 0', null, false);
            if ($inList !== null) {
                $q->orWhere('LOWER(devices.serial) IN ' . $inList, null, false);
            }
            if ($p['mac']) {
                // MAC staat in allerlei notaties: grof zoeken op de laatste twee tekens, daarna precies vergelijken
                $q->orLike('devices.mac', substr($p['mac'], -2), 'before', null, true)->orLike('devices.serial', substr($p['mac'], -2), 'before', null, true);
            }
            foreach ($q->groupEnd()->findAll(500) as $d) {
                $byMac = $p['mac'] && (Connector::normalizeMac($d['mac']) === $p['mac'] || Connector::normalizeMac($d['serial']) === $p['mac']);
                $bySer = $d['serial'] !== null && in_array(strtolower($d['serial']), $needles, true);
                if ($byMac || $bySer) {
                    $existing[] = $this->deviceOut($d);
                }
            }
        }

        // In UniFi/UISP?
        $network = null;
        $nq = model(NetworkDeviceModel::class)->withContext()->where('network_devices.gone', 0)->groupStart();
        $nq->where('1 = 0', null, false);
        if ($p['mac']) {
            $nq->orWhere('network_devices.mac', $p['mac']);
        }
        if ($inList !== null) {
            $nq->orWhere('LOWER(network_devices.serial) IN ' . $inList, null, false);
        }
        if ($nd = $nq->groupEnd()->first()) {
            $network = [
                'id'          => (int) $nd['id'],
                'platform'    => Network::platformLabel($nd['platform']),
                'name'        => $nd['name'],
                'model'       => $nd['model'],
                'ip'          => $nd['ip'],
                'status'      => $nd['status'],
                'site'        => $nd['nsite_name'],
                'customer_id' => $nd['cust_id'] ? (int) $nd['cust_id'] : null,
                'site_id'     => $nd['loc_id'] ? (int) $nd['loc_id'] : null,
                'device_id'   => $nd['device_id'] ? (int) $nd['device_id'] : null,
                'type'        => Network::guessType($nd),
            ];
            // Geen klant gekozen? Dan die van de UniFi/UISP-site
            $customerId ??= $network['customer_id'];
            $siteId     ??= $network['site_id'];
            $p['brand'] ??= 'Ubiquiti';
            $p['model'] ??= $nd['model'];
        }
        if ($p['mac'] && $p['brand'] === null) {
            $p['brand'] = 'Ubiquiti';
        }

        // Kandidaten bij de gekozen klant (of offerte)
        $candidates = [];
        if ($quoteId && ! $customerId) {
            $customerId = (int) (model(QuoteModel::class)->find($quoteId)['customer_id'] ?? 0) ?: null;
        }
        if ($customerId) {
            $candidates = $this->candidates($p, $customerId, $siteId, $quoteId, $network);
        }

        return $this->json([
            'parsed'      => $p,
            'mac'         => Network::formatMac($p['mac']),
            'existing'    => $existing,
            'network'     => $network,
            'candidates'  => $candidates,
            'customer_id' => $customerId,
            'site_id'     => $siteId,
            'type_guess'  => $network['type'] ?? $this->typeGuess($p),
        ]);
    }

    /** Code opslaan bij een apparaat (bestaand of nieuw) (JSON) */
    public function save()
    {
        $post = $this->nullify($this->request->getPost(['device_id', 'type', 'name', 'customer_id', 'site_id', 'serial', 'mac', 'model', 'brand',
            'purchase_invoice_id', 'network_device_id', 'verification', 'quote_line_id', 'room']));

        $customerId = (int) $post['customer_id'];
        if (! $customerId || model(CustomerModel::class)->find($customerId) === null) {
            return $this->json(['error' => 'Kies eerst een klant.'], 422);
        }
        $siteId = (int) $post['site_id'] ?: null;
        if ($siteId !== null) {
            $site = model(SiteModel::class)->find($siteId);
            if ($site === null || (int) $site['customer_id'] !== $customerId) {
                $siteId = null;
            }
        }
        $invoiceId = (int) $post['purchase_invoice_id'] ?: null;
        if ($invoiceId !== null && model(PurchaseInvoiceModel::class)->find($invoiceId) === null) {
            $invoiceId = null;
        }
        $mac = $post['mac'] ? Network::formatMac($post['mac']) : null;

        $model = model(DeviceModel::class);
        try {
            if ($post['device_id'] && $post['device_id'] !== 'new') {
                $device = $model->find($post['device_id']);
                if ($device === null || (int) $device['customer_id'] !== $customerId) {
                    return $this->json(['error' => 'Apparaat hoort niet bij deze klant.'], 422);
                }
                $set = array_filter([
                    'serial'              => $post['serial'] ? mb_substr($post['serial'], 0, 100) : null,
                    'mac'                 => $mac,
                    'model'               => ! $device['model'] && $post['model'] ? mb_substr($post['model'], 0, 100) : null,
                    'brand'               => ! $device['brand'] && $post['brand'] ? mb_substr($post['brand'], 0, 100) : null,
                    'site_id'             => ! $device['site_id'] ? $siteId : null,
                    'purchase_invoice_id' => $invoiceId,
                    'room'                => ! $device['room'] && $post['room'] ? mb_substr($post['room'], 0, 100) : null,
                ], static fn ($v) => $v !== null);
                if ($set !== []) {
                    $model->update($device['id'], $set);
                }
                $id = (int) $device['id'];
            } else {
                $type = (string) $post['type'];
                if (! isset(device_types()[$type])) {
                    return $this->json(['error' => 'Kies een soort apparaat.'], 422);
                }
                $id = (int) $model->insert([
                    'customer_id'         => $customerId,
                    'site_id'             => $siteId,
                    'type'                => $type,
                    'name'                => mb_substr($post['name'] ?: $this->nextName($customerId, self::shortLabel($type)), 0, 255),
                    'brand'               => $post['brand'] ? mb_substr($post['brand'], 0, 100) : (device_types()[$type][3] ?? null),
                    'model'               => $post['model'] ? mb_substr($post['model'], 0, 100) : null,
                    'serial'              => $post['serial'] ? mb_substr($post['serial'], 0, 100) : null,
                    'mac'                 => $mac,
                    'room'                => $post['room'] ? mb_substr($post['room'], 0, 100) : null,
                    'purchase_invoice_id' => $invoiceId,
                    'notes'               => 'Via doosscan',
                ]);
            }

            // Hik-Connect-verificatiecode versleuteld in de geheime notitie zetten
            if ($post['verification'] && Vault::ready()) {
                $device = $model->find($id);
                $old    = $device['secret_notes_enc'] ? Vault::decrypt($device['secret_notes_enc']) : '';
                if (! str_contains($old, $post['verification'])) {
                    $model->update($id, ['secret_notes_enc' => Vault::encrypt(trim($old . "\nHik-Connect verificatiecode: " . $post['verification']))]);
                    Vault::log('gewijzigd (scan)', ['id' => $id, 'customer_id' => $customerId]);
                }
            }

            if ($post['network_device_id']) {
                Network::link((int) $post['network_device_id'], $id);
            } else {
                Network::autoLink(); // misschien staat hij al in UniFi/UISP
            }
        } catch (Throwable $e) {
            log_message('error', 'Doosscan: ' . $e->getMessage());

            return $this->json(['error' => 'Opslaan mislukt: ' . $e->getMessage()], 500);
        }

        $d = $model->select('devices.*, customers.name AS customer_name, sites.name AS site_name')
            ->join('customers', 'customers.id = devices.customer_id')->join('sites', 'sites.id = devices.site_id', 'left')->where('devices.id', $id)->first();

        return $this->json(['ok' => true, 'device' => $this->deviceOut($d)]);
    }

    // ------------------------------------------------------------------

    /**
     * Apparaten van de klant om deze code aan te hangen, beste eerst.
     * Apparaten zonder serienummer/MAC (net uit een offerte) en van het
     * juiste merk/soort komen bovenaan.
     */
    private function candidates(array $p, int $customerId, ?int $siteId, ?int $quoteId, ?array $network): array
    {
        $q = model(DeviceModel::class)->select('devices.*, sites.name AS site_name')
            ->join('sites', 'sites.id = devices.site_id', 'left')->where('devices.customer_id', $customerId);
        if ($quoteId) {
            $lineIds = array_column(model(QuoteLineModel::class)->select('id')->where('quote_id', $quoteId)->findAll(), 'id');
            $q->whereIn('devices.quote_line_id', $lineIds ?: [0]);
        }
        $list = $q->findAll(500);

        $taken = array_flip(array_map('intval', array_column(model(NetworkDeviceModel::class)->select('device_id')->where('device_id IS NOT NULL', null, false)->findAll(), 'device_id')));
        $types = match ($p['brand']) {
            'Hikvision' => ['camera', 'nvr', 'intercom', 'access'],
            'Ajax'      => array_keys(array_filter(device_types(), static fn ($t, $k) => str_starts_with($k, 'alarm'), ARRAY_FILTER_USE_BOTH)),
            'Ubiquiti'  => ['router', 'switch', 'ap', 'camera', 'nvr', 'access', 'server'],
            default     => [],
        };
        $guess = $network['type'] ?? $this->typeGuess($p);
        $norm  = static fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
        $pm    = $norm($p['model']);

        $out = [];
        foreach ($list as $d) {
            $empty = ! $d['serial'] && ! Connector::normalizeMac($d['mac']);
            $score = 0;
            if ($empty) {
                $score += 3;
            }
            if ($types !== [] && in_array($d['type'], $types, true)) {
                $score += 3;
            } elseif ($types !== []) {
                $score -= 3;
            }
            if ($guess && $d['type'] === $guess) {
                $score += 2;
            }
            $dm = $norm($d['model']);
            if ($pm !== '' && $dm !== '' && (str_contains($dm, $pm) || str_contains($pm, $dm))) {
                $score += 5;
            }
            if ($siteId && (int) $d['site_id'] === $siteId) {
                $score += 2;
            }
            if ($network && isset($taken[(int) $d['id']])) {
                $score -= 5; // hangt al aan een ander UniFi/UISP-apparaat
            }
            $out[] = [
                'id'      => (int) $d['id'],
                'name'    => $d['name'],
                'type'    => $d['type'],
                'icon'    => device_types()[$d['type']][1] ?? 'box',
                'model'   => $d['model'],
                'room'    => $d['room'],
                'site'    => $d['site_name'],
                'serial'  => $d['serial'] ?: Network::formatMac($d['mac']),
                'empty'   => $empty,
                'score'   => $score,
            ];
        }
        usort($out, static fn ($a, $b) => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

        return array_slice($out, 0, 60);
    }

    private function typeGuess(array $p): ?string
    {
        if ($p['model']) {
            $t = device_type_guess($p['model']);
            if ($t !== null) {
                return $t;
            }
        }

        return match ($p['brand']) {
            'Hikvision' => 'camera',
            'Ajax'      => 'alarm',
            default     => null,
        };
    }

    private function deviceOut(array $d): array
    {
        return [
            'id'       => (int) $d['id'],
            'name'     => $d['name'],
            'customer' => $d['customer_name'] ?? null,
            'site'     => $d['site_name'] ?? null,
            'url'      => site_url('apparaten/' . $d['id']),
            'icon'     => device_types()[$d['type']][1] ?? 'box',
        ];
    }

    /** "Recorder (NVR/DVR)" -> "Recorder", "Router / gateway" -> "Router" */
    private static function shortLabel(string $type): string
    {
        return trim(explode(' (', explode(' / ', device_type_label($type))[0])[0]);
    }

    /** "Camera 4" als er al Camera 1-3 zijn */
    private function nextName(int $customerId, string $label): string
    {
        $max = 0;
        foreach (model(DeviceModel::class)->select('name')->where('customer_id', $customerId)->like('name', $label, 'after')->findAll() as $d) {
            if ($d['name'] === $label) {
                $max = max($max, 1);
            } elseif (preg_match('/^' . preg_quote($label, '/') . ' (\d+)$/u', $d['name'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max === 0 ? $label : $label . ' ' . ($max + 1);
    }

    private function json(array $data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setHeader('Cache-Control', 'no-store')->setJSON($data + ['csrf' => csrf_hash()]);
    }
}
