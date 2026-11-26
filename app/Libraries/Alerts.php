<?php

namespace App\Libraries;

use App\Models\CustomerModel;
use App\Models\NetworkDeviceModel;
use Throwable;

/**
 * Storingsmeldingen per klant.
 *
 * Een bewaakt apparaat dat langer dan de wachttijd (standaard 30 minuten) offline
 * is, levert één mail per klant op. Daarin staan alle apparaten van die klant die
 * nu offline zijn, in de volgorde van de topologie (hoog in de boom = bovenaan).
 * Elke storing wordt één keer gemeld; blijft een apparaat offline, dan komt het in
 * een volgende mail onder "nog steeds niet bereikbaar".
 *
 * Jij (het eigen adres, of de beheerders) krijgt altijd een mail bij elke
 * storing. De klant alleen als "klanten mailen" aan staat én de klant zelf aan
 * staat (per klant, standaard uit).
 */
class Alerts
{
    public static function settings(): array
    {
        $s = static fn (string $k, string $d = '') => (string) (Setting::get($k, $d) ?? $d);

        return [
            'enabled' => $s('alerts.enabled', '1') === '1', // standaard aan, in testmodus (alleen naar jou)
            'mode'    => $s('alerts.mode', 'test') === 'live' ? 'live' : 'test',
            'delay'   => max(5, (int) $s('alerts.delay', '30')),
            'testTo'  => $s('alerts.testTo'),
            'phone'   => $s('alerts.phone'),
            'replyTo' => $s('alerts.replyTo'),
            'pdf'     => $s('alerts.pdf', '1') === '1',
        ];
    }

    /** Beheerders met e-mailadres (zelfde als de factuurherinnering) */
    public static function admins(): array
    {
        $out = [];
        foreach (auth()->getProvider()->withIdentities()->findAll() as $user) {
            if ($user->active && $user->inGroup('admin') && $user->email) {
                $out[] = $user->email;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function splitEmails(?string $list): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;,\s]+/', (string) $list)), static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    /** Wie krijgt altijd een mail: het eigen adres, anders de beheerders */
    public static function internal(?array $settings = null): array
    {
        $own = self::splitEmails(($settings ?? self::settings())['testTo']);

        return $own !== [] ? $own : self::admins();
    }

    /** Mailadressen van de klant, als die gemaild mag worden; anders [] */
    public static function customerTo(array $customer, ?array $settings = null): array
    {
        $s = $settings ?? self::settings();

        return $s['mode'] === 'live' && ! empty($customer['alert_mail']) ? self::splitEmails($customer['email'] ?? '') : [];
    }

    // ------------------------------------------------------------------

    /**
     * Na elke synchronisatie: nieuwe storingen melden, herstelde afsluiten.
     *
     * @return list<string> meldingen voor het log
     */
    public static function run(): array
    {
        $s = self::settings();
        if (! $s['enabled']) {
            return [];
        }
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        // Weer online (of niet meer bewaakt): storing afsluiten
        $open = $db->table('network_alerts')->select('network_alerts.id, network_alerts.offline_since AS a_since, d.status, d.offline_since, d.gone, d.monitor')
            ->join('network_devices d', 'd.id = network_alerts.network_device_id', 'left')
            ->where('network_alerts.recovered_at', null)->get()->getResultArray();
        $closed = [];
        foreach ($open as $a) {
            if ($a['status'] !== 'offline' || $a['offline_since'] !== $a['a_since'] || (int) $a['gone'] === 1 || (int) $a['monitor'] === 0) {
                $closed[] = (int) $a['id'];
            }
        }
        if ($closed !== []) {
            $db->table('network_alerts')->whereIn('id', $closed)->update(['recovered_at' => $now]);
        }

        // De eerste keer: wat nu al offline is, geldt als bekend (geen stapel mails
        // over oude storingen). Daarna worden alleen nieuwe storingen gemeld.
        if ((string) Setting::get('alerts.baselineAt', '') === '') {
            $n = 0;
            foreach (self::offlineByCustomer() as $customerId => $devices) {
                foreach ($devices as $d) {
                    if (! $d['alerted'] && $d['offline_since']) {
                        $db->table('network_alerts')->ignore(true)->insert([
                            'customer_id' => $customerId, 'network_device_id' => $d['id'], 'offline_since' => $d['offline_since'],
                            'notified_at' => $now, 'recipients' => null, 'mode' => 'start',
                        ]);
                        $n++;
                    }
                }
            }
            Setting::set('alerts.baselineAt', $now);

            return [$n . ' apparaten waren al offline bij het aanzetten; die worden niet gemeld, nieuwe storingen wel.'];
        }

        $cutoff = date('Y-m-d H:i:s', time() - $s['delay'] * 60);
        $out    = [];
        foreach (self::offlineByCustomer() as $customerId => $devices) {
            $new = array_filter($devices, static fn ($d) => ! $d['alerted'] && $d['offline_since'] && $d['offline_since'] <= $cutoff);
            if ($new === []) {
                continue;
            }
            $customer = model(CustomerModel::class)->find($customerId);
            if ($customer === null) {
                continue;
            }
            // Alleen wat al lang genoeg offline is in de mail; de rest komt later
            $list     = array_values(array_filter($devices, static fn ($d) => $d['offline_since'] && $d['offline_since'] <= $cutoff));
            $toClient = self::customerTo($customer, $s);
            $toMe     = self::internal($s);
            $sent     = [];
            try {
                if ($toClient !== []) {
                    self::send($customer, $list, $toClient, $s);
                    $sent = array_merge($sent, $toClient);
                }
                if ($toMe !== []) {
                    self::send($customer, $list, $toMe, $s, ['internal' => true, 'clientTo' => $toClient]);
                    $sent = array_merge($sent, $toMe);
                }
            } catch (Throwable $e) {
                $out[] = $customer['name'] . ': versturen mislukt: ' . $e->getMessage();
                log_message('error', 'Storingsmail ' . $customer['name'] . ': ' . $e->getMessage());
                if ($sent === []) {
                    continue; // niets weg: volgende keer opnieuw proberen
                }
            }
            foreach ($new as $d) {
                $db->table('network_alerts')->ignore(true)->insert([
                    'customer_id' => $customerId, 'network_device_id' => $d['id'], 'offline_since' => $d['offline_since'],
                    'notified_at' => $now, 'recipients' => mb_substr(implode(', ', $sent), 0, 500), 'mode' => $toClient !== [] ? 'live' : 'intern',
                ]);
            }
            $out[] = $customer['name'] . ': ' . count($new) . ' offline' . ($toClient !== [] ? ', klant gemaild (' . implode(', ', $toClient) . ')' : ', alleen intern');
        }

        return $out;
    }

    /**
     * Bewaakte apparaten die nu offline zijn, per klant, in topologievolgorde.
     *
     * @return array<int, list<array>>  customer_id => apparaten
     */
    public static function offlineByCustomer(?int $onlyCustomer = null): array
    {
        $q = model(NetworkDeviceModel::class)->withContext()
            ->whereIn('network_devices.platform', Network::MONITORED)
            ->where('network_devices.status', 'offline')->where('network_devices.monitor', 1)
            ->where('network_devices.ignored', 0)->where('network_devices.gone', 0)
            ->groupStart()->where('network_sites.ignored', 0)->orWhere('network_sites.id IS NULL', null, false)->groupEnd();
        $rows = $q->findAll();

        $alerted = [];
        foreach (db_connect()->table('network_alerts')->select('network_device_id, offline_since')->where('recovered_at', null)->get()->getResultArray() as $a) {
            $alerted[$a['network_device_id'] . '|' . $a['offline_since']] = true;
        }

        $by = [];
        foreach ($rows as $r) {
            $c = (int) $r['cust_id'];
            if (! $c || ($onlyCustomer !== null && $c !== $onlyCustomer)) {
                continue;
            }
            $r['alerted'] = isset($alerted[$r['id'] . '|' . $r['offline_since']]);
            $by[$c][]     = $r;
        }

        foreach ($by as $c => $list) {
            $by[$c] = self::sortByTopology($list);
        }

        return $by;
    }

    /**
     * Sorteer op topologie: per site in boomvolgorde (hoog = eerst), en geef aan
     * achter welk ander offline apparaat iets hangt ("waarschijnlijk door").
     */
    private static function sortByTopology(array $devices): array
    {
        $offline = array_flip(array_map('intval', array_column($devices, 'id')));
        $info    = [];
        foreach (array_unique(array_filter(array_map(static fn ($d) => (int) $d['network_site_id'], $devices))) as $siteId) {
            ['order' => $order, 'byId' => $byId] = Topology::tree(Topology::nodes($siteId));
            foreach ($order as $i => ['node' => $n, 'depth' => $depth]) {
                // Hoogste offline voorouder
                $cause = null;
                for ($p = $n['parent'], $k = 0; $p && $k < 100; $k++) {
                    if (isset($offline[$p])) {
                        $cause = $byId[$p]['name'] ?? null;
                    }
                    $p = $byId[$p]['parent'] ?? null;
                }
                $info[$n['id']] ??= ['site' => $siteId, 'index' => $i, 'depth' => $depth, 'cause' => $cause, 'type' => $n['type'], 'name' => $n['name']];
            }
        }
        foreach ($devices as &$d) {
            $x = $info[(int) $d['id']] ?? ['site' => (int) $d['network_site_id'], 'index' => 9999, 'depth' => 99, 'cause' => null, 'type' => Network::guessType($d), 'name' => null];
            $d['topo']         = $x;
            $d['display_name'] = $x['name'] ?: ($d['device_name'] ?: ($d['name'] ?: $d['model']));
        }
        unset($d);
        // Hoe hoger in de topologie, hoe hoger in de mail
        usort($devices, static fn ($a, $b) => [$a['topo']['site'], $a['topo']['depth'], $a['topo']['index']]
            <=> [$b['topo']['site'], $b['topo']['depth'], $b['topo']['index']]);

        return $devices;
    }

    // ------------------------------------------------------------------
    // Mail

    /** Onderwerp, HTML en bijlage opbouwen */
    public static function build(array $customer, array $devices, array $s, bool $preview = false, array $opt = []): array
    {
        $count   = count($devices);
        $new     = array_values(array_filter($devices, static fn ($d) => ! $d['alerted']));
        $still   = array_values(array_filter($devices, static fn ($d) => $d['alerted']));
        $subject = ($count === 1 ? '1 apparaat' : $count . ' apparaten') . ' niet bereikbaar bij ' . $customer['name'];
        if (! empty($opt['internal'])) {
            $subject = 'Offline: ' . $customer['name'] . ' (' . ($count === 1 ? '1 apparaat' : $count . ' apparaten') . ')';
        }

        $html = view('emails/offline', [
            'customer' => $customer,
            'new'      => $new === [] ? $devices : $new,
            'still'    => $new === [] ? [] : $still,
            'settings' => $s,
            'logo'     => $preview ? base_url('img/logo.png') : 'cid:__LOGO__',
            'internal' => (bool) ($opt['internal'] ?? false),
            'test'     => (bool) ($opt['test'] ?? $preview),
            'clientTo' => $opt['clientTo'] ?? [],
            'wouldTo'  => self::splitEmails($customer['email'] ?? ''),
            'clientOn' => $s['mode'] === 'live' && ! empty($customer['alert_mail']),
            'preview'  => $preview,
        ]);

        return ['subject' => $subject, 'html' => $html, 'sites' => array_values(array_unique(array_map(static fn ($d) => $d['topo']['site'], $devices)))];
    }

    public static function send(array $customer, array $devices, array $to, array $s, array $opt = []): void
    {
        $mail  = self::build($customer, $devices, $s, false, $opt);
        $email = service('email');
        $email->clear(true);
        $email->setTo($to);
        if ($s['replyTo'] !== '' && filter_var($s['replyTo'], FILTER_VALIDATE_EMAIL)) {
            $email->setReplyTo($s['replyTo']);
        }
        $email->setSubject(($opt['test'] ?? false ? '[TEST] ' : '') . $mail['subject']);
        $email->setMailType('html');

        // Logo in de mail zelf (niet als link, wordt dan niet geblokkeerd)
        $logo = FCPATH . 'img/logo.png';
        $html = $mail['html'];
        if (is_file($logo)) {
            $email->attach($logo, 'inline');
            $html = str_replace('cid:__LOGO__', 'cid:' . $email->setAttachmentCID($logo), $html);
        }
        $email->setMessage($html);
        $email->setAltMessage(self::plain($customer, $devices));

        if ($s['pdf'] && $mail['sites'] !== []) {
            $pdf = TopologyPdf::render($mail['sites'], array_map('intval', array_column($devices, 'id')));
            $email->attach($pdf, 'attachment', 'Netwerkoverzicht ' . preg_replace('/[^\w\- ]+/u', '', $customer['name']) . '.pdf', 'application/pdf');
        }

        if (! $email->send(false)) {
            throw new \RuntimeException(trim(strip_tags($email->printDebugger(['headers']))) ?: 'onbekende fout');
        }
    }

    private static function plain(array $customer, array $devices): string
    {
        $lines = ['Beste ' . ($customer['contact_name'] ?: $customer['name']) . ', deze apparaten zijn niet bereikbaar:', ''];
        foreach ($devices as $d) {
            $lines[] = '- ' . $d['display_name'] . ' (' . device_type_label($d['topo']['type']) . ')' . ($d['offline_since'] ? ', sinds ' . date('d-m H:i', strtotime($d['offline_since'])) : '')
                . ($d['topo']['cause'] ? ', via ' . $d['topo']['cause'] : '');
        }
        $lines[] = '';
        $lines[] = 'Wij kijken mee. Groet, bongersonline';

        return implode("\n", $lines);
    }

    /**
     * Testmail: met de echte offline apparaten van deze klant, of een voorbeeld
     * als er nu niets offline is. Gaat altijd naar het testadres/de beheerders.
     */
    public static function test(int $customerId): array
    {
        $s        = self::settings();
        $customer = model(CustomerModel::class)->find($customerId);
        if ($customer === null) {
            throw new \RuntimeException('Klant niet gevonden.');
        }
        $devices = self::offlineByCustomer($customerId)[$customerId] ?? self::sample($customerId);
        if ($devices === []) {
            throw new \RuntimeException('Deze klant heeft nog geen apparaten uit UniFi of UISP.');
        }
        $to = self::internal($s);
        if ($to === []) {
            throw new \RuntimeException('Geen ontvanger: vul je eigen adres in.');
        }
        // De versie die de klant zou krijgen, naar jou
        self::send($customer, $devices, $to, $s, ['test' => true]);

        return $to;
    }

    /** Voorbeeld: de bovenste apparaten van de klant doen alsof ze offline zijn */
    public static function sample(int $customerId): array
    {
        $rows = model(NetworkDeviceModel::class)->withContext()->whereIn('network_devices.platform', Network::MONITORED)
            ->where('network_devices.gone', 0)
            ->groupStart()->where('devices.customer_id', $customerId)->orWhere('sites.customer_id', $customerId)->groupEnd()->findAll(200);
        if ($rows === []) {
            return [];
        }
        $since = date('Y-m-d H:i:s', time() - 47 * 60);
        foreach ($rows as &$r) {
            $r['status']        = 'offline';
            $r['offline_since'] = $since;
            $r['alerted']       = false;
        }
        unset($r);
        $sorted = self::sortByTopology($rows);
        // Een switch met wat erachter hangt is het mooiste voorbeeld
        $pick = array_slice($sorted, 0, 1);
        foreach ($sorted as $d) {
            if ($d['topo']['depth'] >= 1 && in_array($d['topo']['type'], ['switch', 'ap'], true)) {
                $pick = [$d];
                foreach ($sorted as $c) {
                    if (count($pick) < 4 && $c['topo']['depth'] > $d['topo']['depth'] && $c['topo']['site'] === $d['topo']['site'] && $c['topo']['index'] > $d['topo']['index']) {
                        $pick[] = $c;
                    }
                }
                break;
            }
        }

        return self::sortByTopology($pick);
    }
}
