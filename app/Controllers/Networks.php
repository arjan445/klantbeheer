<?php

namespace App\Controllers;

use App\Libraries\Network;
use App\Libraries\Network\Uisp;
use App\Libraries\Network\Unifi;
use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\NetworkDeviceModel;
use App\Models\NetworkSiteModel;
use App\Models\SiteModel;
use Throwable;

/**
 * Netwerk: UniFi- en UISP-apparaten, offline-overzicht, sites koppelen aan
 * klantlocaties en apparaten koppelen aan de kluis.
 */
class Networks extends BaseController
{
    public function index()
    {
        $tab = (string) ($this->request->getGet('tab') ?? '');

        $sites = model(NetworkSiteModel::class)
            ->select('network_sites.*, sites.name AS loc_name, sites.customer_id, customers.name AS customer_name')
            ->join('sites', 'sites.id = network_sites.site_id', 'left')
            ->join('customers', 'customers.id = sites.customer_id', 'left')
            ->orderBy('network_sites.site_id IS NOT NULL', '', false)->orderBy('network_sites.ignored')->orderBy('network_sites.name')->findAll();

        $inbox = model(NetworkDeviceModel::class)
            ->where('device_id', null)->where('ignored', 0)->where('gone', 0)->whereIn('platform', Network::MONITORED)
            ->orderBy('network_site_id')->orderBy('name')->findAll();

        $offline = Network::offline();
        $unlinkedSites = count(array_filter($sites, static fn ($s) => ! $s['site_id'] && ! $s['ignored']));

        if (! in_array($tab, ['offline', 'inbox', 'sites'], true)) {
            $tab = $offline['count'] > 0 ? 'offline' : ($unlinkedSites > 0 ? 'sites' : ($inbox !== [] ? 'inbox' : 'offline'));
        }

        // Inbox per netwerksite, met kandidaten uit de kluis van de gekoppelde klant
        $sitesById = array_column($sites, null, 'id');
        $groups    = [];
        foreach ($inbox as $nd) {
            $sid = (int) $nd['network_site_id'];
            $site = $sitesById[$sid] ?? null;
            if ($site && $site['ignored']) {
                continue;
            }
            $groups[$sid] ??= ['site' => $site, 'devices' => []];
            $groups[$sid]['devices'][] = $nd;
        }
        foreach ($groups as &$g) {
            $used = [];
            foreach ($g['devices'] as &$nd) {
                $nd['candidates'] = [];
                $nd['suggest']    = 'new';
                if ($g['site'] && $g['site']['customer_id']) {
                    $nd['candidates'] = array_slice(Network::candidates($nd, (int) $g['site']['customer_id'], (int) $g['site']['site_id']), 0, 40);
                    foreach ($nd['candidates'] as $c) {
                        if ($c['score'] >= 9 && ! isset($used[$c['id']])) {
                            $nd['suggest'] = (string) $c['id'];
                            $used[$c['id']] = true;
                            break;
                        }
                    }
                }
            }
            unset($nd);
        }
        unset($g);

        return $this->page('network/index', [
            'tab'           => $tab,
            'status'        => Network::status(),
            'offline'       => $offline,
            'sites'         => $sites,
            'groups'        => $groups,
            'inboxCount'    => array_sum(array_map(static fn ($g) => count($g['devices']), $groups)),
            'unlinkedSites' => $unlinkedSites,
            'locations'     => $this->locationOptions(),
            'suggestions'   => $this->siteSuggestions($sites),
            'manage'        => user_can('vault.manage'),
        ], 'Netwerk');
    }

    public function sync()
    {
        if (! Network::configured()) {
            return $this->back('Stel eerst UniFi of UISP in.', 'error', site_url(user_can('settings.manage') ? 'beheer/netwerk' : 'netwerk'));
        }
        @set_time_limit(180);
        $msg = $err = [];
        foreach (Network::sync() as $r) {
            if ($r['ok']) {
                $msg[] = sprintf('%s: %d apparaten, %d offline%s', $r['label'], $r['devices'], $r['offline'], $r['linked'] ? ', ' . $r['linked'] . ' automatisch gekoppeld' : '');
                array_push($err, ...$r['notes']);
            } else {
                $err[] = $r['label'] . ': ' . $r['error'];
            }
        }
        if ($err !== []) {
            $this->session->setFlashdata('error', implode(' ', $err));
        }

        return $this->back($msg === [] ? 'Niets opgehaald.' : implode(' · ', $msg) . '.', 'message', $this->returnUrl());
    }

    /** Netwerksite koppelen aan een klantlocatie (of negeren) */
    public function linkSite($id)
    {
        $site   = $this->findOr404(NetworkSiteModel::class, $id);
        $locId  = (int) $this->request->getPost('site_id') ?: null;
        if ($locId !== null && model(SiteModel::class)->find($locId) === null) {
            $locId = null;
        }
        $ignored = $this->request->getPost('ignored') ? 1 : 0;
        model(NetworkSiteModel::class)->update($site['id'], ['site_id' => $locId, 'ignored' => $ignored]);

        // Apparaten die al in de kluis staan zonder locatie: locatie overnemen als de klant klopt
        if ($locId !== null) {
            $loc = model(SiteModel::class)->find($locId);
            foreach (model(NetworkDeviceModel::class)->select('device_id')->where('network_site_id', $site['id'])->where('device_id IS NOT NULL', null, false)->findAll() as $r) {
                db_connect()->table('devices')->where('id', $r['device_id'])->where('site_id', null)->where('customer_id', $loc['customer_id'])->update(['site_id' => $locId]);
            }
        }

        $label = $ignored ? 'genegeerd' : ($locId ? 'gekoppeld' : 'ontkoppeld');

        return $this->back('"' . $site['name'] . '" ' . $label . '.', 'message', site_url('netwerk?tab=sites'));
    }

    /**
     * Inbox verwerken: per netwerkapparaat een keuze (nieuw in kluis, bestaand
     * apparaat, negeren of niets).
     */
    public function assign()
    {
        $choices = (array) $this->request->getPost('choice');
        $made = $linked = $ignored = 0;
        $errors = [];

        foreach ($choices as $ndId => $choice) {
            $choice = (string) $choice;
            if ($choice === '' || ! ctype_digit((string) $ndId)) {
                continue;
            }
            $nd = model(NetworkDeviceModel::class)->find($ndId);
            if ($nd === null || $nd['device_id']) {
                continue;
            }
            try {
                if ($choice === 'ignore') {
                    model(NetworkDeviceModel::class)->update($nd['id'], ['ignored' => 1]);
                    $ignored++;
                    continue;
                }
                $ns  = $nd['network_site_id'] ? model(NetworkSiteModel::class)->find($nd['network_site_id']) : null;
                $loc = $ns && $ns['site_id'] ? model(SiteModel::class)->find($ns['site_id']) : null;
                // Site onbekend of niet gekoppeld: zelf een klantlocatie gekozen
                if (str_starts_with($choice, 'loc:')) {
                    $loc = model(SiteModel::class)->find((int) substr($choice, 4));
                    if ($loc === null) {
                        continue;
                    }
                    $choice = 'new';
                }
                if ($choice === 'new') {
                    if ($loc === null) {
                        $errors[] = ($nd['name'] ?: $nd['mac']) . ': koppel eerst de site aan een klantlocatie';
                        continue;
                    }
                    Network::createDevice((int) $nd['id'], (int) $loc['customer_id'], (int) $loc['id']);
                    $made++;
                } elseif (ctype_digit($choice)) {
                    $device = model(DeviceModel::class)->find($choice);
                    if ($device === null || ($loc !== null && (int) $device['customer_id'] !== (int) $loc['customer_id'])) {
                        continue;
                    }
                    Network::link((int) $nd['id'], (int) $device['id']);
                    $linked++;
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors !== []) {
            $this->session->setFlashdata('error', implode(' · ', array_unique($errors)));
        }
        $parts = array_filter([$made ? $made . ' nieuw in de kluis' : null, $linked ? $linked . ' gekoppeld' : null, $ignored ? $ignored . ' genegeerd' : null]);

        return $this->back($parts === [] ? 'Niets gewijzigd.' : ucfirst(implode(', ', $parts)) . '.', 'message', site_url('netwerk?tab=inbox'));
    }

    /** Bewaken aan/uit (hoort dit apparaat online te zijn?) */
    public function monitor($id)
    {
        $nd = $this->findOr404(NetworkDeviceModel::class, $id);
        $on = $this->request->getPost('monitor') === '1' ? 1 : 0;
        model(NetworkDeviceModel::class)->update($nd['id'], ['monitor' => $on]);

        return $this->back($on ? 'Wordt bewaakt.' : 'Wordt niet meer bewaakt: telt niet mee als offline.', 'message', $this->returnUrl());
    }

    /** Netwerkapparaat los van het kluis-apparaat (komt terug in de inbox) */
    public function unlink($id)
    {
        $nd = $this->findOr404(NetworkDeviceModel::class, $id);
        model(NetworkDeviceModel::class)->update($nd['id'], ['device_id' => null]);

        return $this->back('Ontkoppeld. Het apparaat staat weer onder "Niet gekoppeld".', 'message', $this->returnUrl());
    }

    /** Genegeerd apparaat terug naar de inbox */
    public function restore($id)
    {
        $nd = $this->findOr404(NetworkDeviceModel::class, $id);
        model(NetworkDeviceModel::class)->update($nd['id'], ['ignored' => 0]);

        return $this->back('Teruggezet.', 'message', $this->returnUrl());
    }

    // ------------------------------------------------------------------
    // Instellingen

    public function settings()
    {
        if ($this->request->is('post')) {
            Network::saveSecretIf('unifi.apiKey', $this->request->getPost('unifi_apiKey'), (bool) $this->request->getPost('unifi_clear'));
            Network::saveSecretIf('uisp.token', $this->request->getPost('uisp_token'), (bool) $this->request->getPost('uisp_clear'));
            \App\Libraries\Setting::set('uisp.url', mb_substr(trim((string) $this->request->getPost('uisp_url')), 0, 255));
            // Storingsmeldingen
            \App\Libraries\Setting::set('alerts.enabled', $this->request->getPost('alerts_enabled') ? '1' : '0');
            \App\Libraries\Setting::set('alerts.mode', $this->request->getPost('alerts_mode') === 'live' ? 'live' : 'test');
            \App\Libraries\Setting::set('alerts.delay', (string) max(5, min(1440, (int) ($this->request->getPost('alerts_delay') ?: 30))));
            \App\Libraries\Setting::set('alerts.testTo', mb_substr(trim((string) $this->request->getPost('alerts_testTo')), 0, 255));
            \App\Libraries\Setting::set('alerts.replyTo', mb_substr(trim((string) $this->request->getPost('alerts_replyTo')), 0, 255));
            \App\Libraries\Setting::set('alerts.phone', mb_substr(trim((string) $this->request->getPost('alerts_phone')), 0, 50));
            \App\Libraries\Setting::set('alerts.pdf', $this->request->getPost('alerts_pdf') ? '1' : '0');
            foreach (['sendFrom' => '17:00', 'sendUntil' => '06:00', 'nightFrom' => '23:00'] as $k => $def) {
                $v = trim((string) $this->request->getPost('alerts_' . $k));
                \App\Libraries\Setting::set('alerts.' . $k, preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $v) ? $v : $def);
            }
            \App\Libraries\Setting::set('alerts.nightDelay', (string) max(5, min(1440, (int) ($this->request->getPost('alerts_nightDelay') ?: 60))));
            \App\Libraries\Setting::set('alerts.ownWindow', $this->request->getPost('alerts_ownWindow') ? '1' : '0');
            // Per klant: mails naar de klant aan/uit (alleen de klanten die op de pagina stonden)
            $shown = array_map('intval', (array) $this->request->getPost('alerts_shown'));
            $on    = array_map('intval', (array) $this->request->getPost('alerts_customers'));
            if ($shown !== []) {
                db_connect()->table('customers')->whereIn('id', $shown)->update(['alert_mail' => 0]);
                if ($on !== []) {
                    db_connect()->table('customers')->whereIn('id', array_intersect($on, $shown))->update(['alert_mail' => 1]);
                }
            }
            \App\Libraries\Setting::set('unifi.controllerUrl', mb_substr(trim((string) $this->request->getPost('ctrl_url')), 0, 255));
            \App\Libraries\Setting::set('unifi.controllerUser', mb_substr(trim((string) $this->request->getPost('ctrl_user')), 0, 100));
            \App\Libraries\Setting::set('unifi.controllerInsecure', $this->request->getPost('ctrl_insecure') ? '1' : '0');
            Network::saveSecretIf('unifi.controllerPass', $this->request->getPost('ctrl_pass'), (bool) $this->request->getPost('ctrl_clear'));
            // Indeling per site opnieuw ophalen bij de volgende synchronisatie
            db_connect()->table('app_settings')->like('key', 'unifi.sitemap.', 'after')->delete();

            if ($this->request->getPost('test')) {
                $out = [];
                $ctrl = new \App\Libraries\Network\UnifiController();
                if ($ctrl->configured()) {
                    try {
                        $list  = $ctrl->sites();
                        $out[] = 'Eigen controller: ingelogd, ' . count($list) . ' sites (' . implode(', ', array_slice(array_column($list, 'desc'), 0, 8)) . ').';
                    } catch (Throwable $e) {
                        $this->session->setFlashdata('error', $e->getMessage());
                    }
                }
                foreach ([new Unifi(), new Uisp()] as $c) {
                    if (! $c->configured()) {
                        continue;
                    }
                    try {
                        $d     = $c->fetch();
                        $out[] = $c->label() . ': verbinding gelukt, ' . count($d['sites']) . ' sites en ' . count($d['devices']) . ' apparaten gevonden.';
                    } catch (Throwable $e) {
                        $this->session->setFlashdata('error', $c->label() . ': ' . $e->getMessage());
                    }
                }

                return $this->back($out === [] ? 'Opgeslagen.' : implode(' ', $out), 'message', site_url('beheer/netwerk'));
            }

            return $this->back('Opgeslagen.', 'message', site_url('beheer/netwerk'));
        }

        return $this->page('network/settings', [
            'alerts'      => \App\Libraries\Alerts::settings(),
            'alertAdmins' => \App\Libraries\Alerts::admins(),
            'alertLast'   => json_decode((string) \App\Libraries\Setting::get('alerts.last', ''), true),
            'alertCustomers' => model(\App\Models\CustomerModel::class)->select('customers.id, customers.name, customers.email, customers.alert_mail')
                ->join('sites', 'sites.customer_id = customers.id')->join('network_sites', 'network_sites.site_id = sites.id')
                ->where('customers.active', 1)->groupBy('customers.id, customers.name, customers.email, customers.alert_mail')->orderBy('customers.name')->findAll(),
            'unifiSet' => (new Unifi())->configured(),
            'uispSet'  => Network::secret('uisp.token') !== '',
            'uispUrl'  => Network::setting('uisp.url'),
            'ctrlUrl'  => Network::setting('unifi.controllerUrl'),
            'ctrlUser' => Network::setting('unifi.controllerUser'),
            'ctrlSet'  => Network::secret('unifi.controllerPass') !== '',
            'ctrlInsecure' => Network::setting('unifi.controllerInsecure') === '1',
            'status'   => Network::status(),
            'secure'   => \App\Libraries\Vault::ready(),
        ], 'Netwerk (UniFi & UISP)');
    }

    // ------------------------------------------------------------------

    /** Voorbeeld van de storingsmail in de browser */
    public function alertPreview()
    {
        $id       = (int) $this->request->getGet('klant');
        $customer = model(\App\Models\CustomerModel::class)->find($id);
        if ($customer === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $devices = \App\Libraries\Alerts::offlineByCustomer($id)[$id] ?? \App\Libraries\Alerts::sample($id);
        if ($devices === []) {
            return $this->back('Deze klant heeft nog geen apparaten uit UniFi of UISP.', 'error', site_url('beheer/netwerk'));
        }
        if ($this->request->getGet('pdf')) {
            $pdf = \App\Libraries\TopologyPdf::render(array_values(array_unique(array_map(static fn ($d) => $d['topo']['site'], $devices))), array_map('intval', array_column($devices, 'id')));

            return $this->response->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="Netwerkoverzicht.pdf"')->setBody($pdf);
        }
        $mail = \App\Libraries\Alerts::build($customer, $devices, \App\Libraries\Alerts::settings(), true);

        return $this->response->setBody($mail['html']);
    }

    /** Testmail versturen (altijd naar het testadres/de beheerders) */
    public function alertTest()
    {
        try {
            $to = \App\Libraries\Alerts::test((int) $this->request->getPost('klant'));
        } catch (Throwable $e) {
            return $this->back('Testmail niet verstuurd: ' . $e->getMessage(), 'error', site_url('beheer/netwerk#meldingen'));
        }

        return $this->back('Testmail verstuurd naar ' . implode(', ', $to) . '.', 'message', site_url('beheer/netwerk#meldingen'));
    }

    /** Locaties gegroepeerd per klant, voor keuzelijsten */
    private function locationOptions(): array
    {
        $out = [];
        foreach (model(SiteModel::class)->select('sites.id, sites.name, sites.city, customers.name AS customer_name, customers.id AS customer_id')
            ->join('customers', 'customers.id = sites.customer_id')->where('customers.active', 1)
            ->orderBy('customers.name')->orderBy('sites.name')->findAll() as $s) {
            $out[$s['customer_name']][] = $s;
        }

        return $out;
    }

    /**
     * Voorstel per niet-gekoppelde netwerksite: een locatie waarvan de klant- of
     * locatienaam in de sitenaam voorkomt (of andersom).
     *
     * @return array<int,int> network_site_id => site_id
     */
    private function siteSuggestions(array $networkSites): array
    {
        $norm = static fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
        $locs = model(SiteModel::class)->select('sites.id, sites.name, sites.city, customers.name AS customer_name')
            ->join('customers', 'customers.id = sites.customer_id')->where('customers.active', 1)->findAll();
        $out  = [];
        foreach ($networkSites as $ns) {
            if ($ns['site_id'] || $ns['ignored']) {
                continue;
            }
            $n    = $norm($ns['name']);
            $best = null;
            $bestScore = 0;
            if (strlen($n) < 3) {
                continue;
            }
            foreach ($locs as $l) {
                $c = $norm($l['customer_name']);
                $s = $norm($l['name']);
                $score = 0;
                if (strlen($c) >= 3 && (str_contains($n, $c) || str_contains($c, $n))) {
                    $score += 2;
                }
                if (strlen($s) >= 3 && (str_contains($n, $s) || str_contains($s, $n))) {
                    $score += 1;
                }
                if ($score > $bestScore) {
                    [$best, $bestScore] = [(int) $l['id'], $score];
                }
            }
            if ($best !== null && $bestScore >= 2) {
                $out[(int) $ns['id']] = $best;
            }
        }

        return $out;
    }

    private function returnUrl(): string
    {
        $to   = (string) $this->request->getPost('return');
        $base = rtrim(base_url(), '/');

        // Alleen terug naar een pagina van deze app
        return ($to !== '' && ($to === $base || str_starts_with($to, $base . '/'))) ? $to : previous_url();
    }
}
