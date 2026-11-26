<?php

namespace App\Libraries;

use App\Libraries\Network\Connector;
use App\Libraries\Network\Uisp;
use App\Libraries\Network\Unifi;
use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\NetworkDeviceModel;
use App\Models\NetworkSiteModel;
use App\Models\SiteModel;
use RuntimeException;
use Throwable;

/**
 * Netwerkbewaking: apparaten uit UniFi en UISP ophalen, koppelen aan klanten,
 * locaties en kluis-apparaten, en bijhouden wat offline is.
 *
 * Cron (elke 5 minuten):  * /5 * * * *  cd /pad && php spark klantbeheer:netwerk
 */
class Network
{
    /** Na zoveel minuten zonder geslaagde synchronisatie is het offline-overzicht niet meer te vertrouwen */
    public const STALE_MINUTES = 30;

    /**
     * Platforms die echt worden opgehaald en bewaakt. Daarnaast bestaan in
     * network_devices ook knopen die alleen in de topologie staan:
     *  - manual: zelf toegevoegd (unmanaged switch, PoE-injector, modem, ...)
     *  - vault:  een apparaat uit de kluis (Hikvision-camera, NVR, Ajax-hub, ...)
     */
    public const MONITORED = ['unifi', 'uisp'];

    // ------------------------------------------------------------------
    // Instellingen

    public static function setting(string $key): string
    {
        return (string) Setting::get($key, '');
    }

    /** API-sleutels staan versleuteld in app_settings ("enc:…"), of los in .env */
    public static function secret(string $key): string
    {
        return self::decryptValue(self::setting($key), $key);
    }

    /** Een waarde zoals opgeslagen ("enc:…" of los) ontsleutelen */
    public static function decryptValue(string $v, string $what = 'sleutel'): string
    {
        if (str_starts_with($v, 'enc:')) {
            try {
                return Vault::decrypt(substr($v, 4));
            } catch (Throwable $e) {
                log_message('error', 'Netwerk: ' . $what . ' ontsleutelen mislukt: ' . $e->getMessage());

                return '';
            }
        }

        return $v;
    }

    /** Waarde versleutelen voor opslag (als er een encryption.key is) */
    public static function encryptValue(string $v): string
    {
        return Vault::ready() ? 'enc:' . Vault::encrypt($v) : $v;
    }

    public static function saveSecret(string $key, ?string $value): void
    {
        $value = trim((string) $value);
        Setting::set($key, $value === '' ? null : (Vault::ready() ? 'enc:' . Vault::encrypt($value) : $value));
    }

    /** Leeg veld = sleutel houden, tenzij "wissen" is aangevinkt */
    public static function saveSecretIf(string $key, $value, bool $clear): void
    {
        if ($clear) {
            Setting::set($key, null);
        } elseif (trim((string) $value) !== '') {
            self::saveSecret($key, (string) $value);
        }
    }

    /** @return list<Connector> */
    public static function connectors(): array
    {
        return [new Unifi(), new Uisp()];
    }

    public static function configured(): bool
    {
        foreach (self::connectors() as $c) {
            if ($c->configured()) {
                return true;
            }
        }

        return false;
    }

    /**
     * SD-WAN-verbanden van een netwerksite, met de andere sites opgezocht
     * (naam en, als we die site kennen, een link naar zijn topologie).
     *
     * @return list<array{name:string, role:string, state:string, up:int, total:int, count:int, peers:list<array{name:string, id:?int}>}>
     */
    public static function vpn(array $site): array
    {
        $list = json_decode((string) ($site['vpn'] ?? ''), true);
        if (! is_array($list) || $list === []) {
            return [];
        }
        static $byExt = null;
        if ($byExt === null) {
            $byExt = [];
            foreach (model(NetworkSiteModel::class)->select('id, external_id, name')->where('platform', 'unifi')->findAll() as $r) {
                $byExt[$r['external_id']] = $r;
            }
        }
        $out = [];
        foreach ($list as $v) {
            $peers = [];
            foreach ($v['peers'] ?? [] as $p) {
                $known   = $byExt[$p['site'] ?? ''] ?? null;
                $peers[] = ['name' => $known['name'] ?? ($p['name'] ?? 'onbekende site'), 'id' => $known ? (int) $known['id'] : null];
            }
            $out[] = [
                'name'  => (string) ($v['name'] ?? 'SD-WAN'),
                'role'  => ($v['role'] ?? '') === 'hub' ? 'hub' : 'spoke',
                'state' => (string) ($v['state'] ?? 'unknown'),
                'up'    => (int) ($v['up'] ?? 0),
                'total' => (int) ($v['total'] ?? 0),
                'count' => (int) ($v['count'] ?? 0),
                'peers' => $peers,
            ];
        }

        return $out;
    }

    /**
     * Netwerk per klant (en per locatie): gekoppelde UniFi/UISP-sites (voor de
     * topologie) en hoeveel bewaakte apparaten er zijn en offline staan.
     *
     * @return array<int,array{sites:list<array>, total:int, offline:int, locs:array<int,array{sites:list<array>, total:int, offline:int, down:list<array>}>}>
     */
    public static function byCustomer(?int $customerId = null): array
    {
        $out  = [];
        $init = static fn () => ['sites' => [], 'total' => 0, 'offline' => 0, 'locs' => []];
        $loc  = static fn () => ['sites' => [], 'total' => 0, 'offline' => 0, 'down' => []];

        try {
            $q = model(NetworkSiteModel::class)->select('network_sites.id, network_sites.name, network_sites.platform, network_sites.site_id, network_sites.vpn, network_sites.device_count, network_sites.offline_count, sites.customer_id')
                ->join('sites', 'sites.id = network_sites.site_id')->where('network_sites.ignored', 0)->orderBy('network_sites.platform')->orderBy('network_sites.name');
            if ($customerId !== null) {
                $q->where('sites.customer_id', $customerId);
            }
            foreach ($q->findAll() as $n) {
                $cid = (int) $n['customer_id'];
                $out[$cid] ??= $init();
                $out[$cid]['sites'][] = $n;
                $out[$cid]['locs'][(int) $n['site_id']] ??= $loc();
                $out[$cid]['locs'][(int) $n['site_id']]['sites'][] = $n;
            }

            $q = model(NetworkDeviceModel::class)->withContext()
                ->whereIn('network_devices.platform', self::MONITORED)
                ->where('network_devices.monitor', 1)->where('network_devices.ignored', 0)->where('network_devices.gone', 0)
                ->groupStart()->where('network_sites.ignored', 0)->orWhere('network_sites.id IS NULL', null, false)->groupEnd();
            if ($customerId !== null) {
                $q->groupStart()->where('devices.customer_id', $customerId)->orGroupStart()->where('devices.customer_id IS NULL', null, false)->where('sites.customer_id', $customerId)->groupEnd()->groupEnd();
            }
            foreach ($q->findAll() as $d) {
                if (! $d['cust_id']) {
                    continue;
                }
                $cid = (int) $d['cust_id'];
                $lid = (int) ($d['loc_id'] ?? 0);
                $out[$cid] ??= $init();
                $out[$cid]['locs'][$lid] ??= $loc();
                $off = $d['status'] === 'offline';
                $out[$cid]['total']++;
                $out[$cid]['locs'][$lid]['total']++;
                if ($off) {
                    $out[$cid]['offline']++;
                    $out[$cid]['locs'][$lid]['offline']++;
                    $out[$cid]['locs'][$lid]['down'][] = [
                        'name'  => $d['device_name'] ?: ($d['name'] ?: ($d['model'] ?: self::formatMac($d['mac']))),
                        'since' => $d['offline_since'],
                    ];
                }
            }
        } catch (Throwable $e) {
            log_message('error', 'Netwerk per klant: ' . $e->getMessage());
        }

        return $out;
    }

    /** "UniFi", "UISP" of "UniFi + UISP" voor de sites van één installatie */
    public static function groupLabel(array $parts): string
    {
        $labels = array_values(array_unique(array_map(static fn ($p) => self::platformLabel($p['platform']), $parts)));
        sort($labels);

        return implode(' + ', array_reverse($labels)) ?: 'Netwerk';
    }

    /**
     * Netwerksites omzetten naar de topologieën om te openen: een site die bij een
     * andere is gevoegd, wordt de hoofdsite (één knop per installatie).
     *
     * @param list<array> $rows network_sites-rijen (id, name, platform)
     * @return list<array{id:int, name:string, label:string}>
     */
    public static function topoSites(array $rows): array
    {
        static $all = null;
        if ($all === null) {
            $all = [];
            foreach (model(NetworkSiteModel::class)->select('id, name, platform')->findAll() as $r) {
                $all[(int) $r['id']] = $r;
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $main = Topology::primary((int) $r['id']);
            if (isset($out[$main])) {
                continue;
            }
            $parts      = array_values(array_filter(array_map(static fn ($id) => $all[$id] ?? null, Topology::group($main))));
            $m          = $all[$main] ?? $r;
            $out[$main] = ['id' => $main, 'name' => $m['name'], 'label' => self::groupLabel($parts ?: [$m]), 'combined' => count($parts) > 1];
        }

        return array_values($out);
    }

    /** Kleur en tekst voor de status van een SD-WAN-verband */
    public static function vpnState(string $state): array
    {
        return match ($state) {
            'ok'      => ['success', 'verbonden'],
            'partial' => ['warning', 'deels verbonden'],
            'down'    => ['danger', 'niet verbonden'],
            'error'   => ['danger', 'fout bij toepassen'],
            'pending' => ['secondary', 'wordt opgezet'],
            default   => ['secondary', 'status onbekend'],
        };
    }

    public static function platformLabel(?string $platform): string
    {
        return match ($platform) {
            'unifi'  => 'UniFi',
            'uisp'   => 'UISP',
            'manual' => 'Eigen',
            'vault'  => 'Kluis',
            default => (string) $platform,
        };
    }

    // ------------------------------------------------------------------
    // Synchroniseren

    /**
     * Haalt alle ingestelde platforms op.
     *
     * @return list<array{platform:string, label:string, ok:bool, error:?string, sites:int, devices:int, online:int, offline:int, linked:int, notes:list<string>}>
     */
    public static function sync(?string $only = null): array
    {
        // Niet twee keer tegelijk (cron en de knop, of een trage vorige run)
        $lock = @fopen(WRITEPATH . 'network-sync.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return [['platform' => 'all', 'label' => 'Netwerk', 'ok' => false, 'error' => 'Er loopt al een synchronisatie, probeer het zo nog eens.',
                'sites' => 0, 'devices' => 0, 'online' => 0, 'offline' => 0, 'linked' => 0, 'notes' => []]];
        }

        try {
            return self::doSync($only);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function doSync(?string $only): array
    {
        $results = [];
        foreach (self::connectors() as $c) {
            if (! $c->configured() || ($only !== null && $only !== $c->platform())) {
                continue;
            }
            $r = ['platform' => $c->platform(), 'label' => $c->label(), 'ok' => false, 'error' => null, 'sites' => 0, 'devices' => 0, 'online' => 0, 'offline' => 0, 'linked' => 0, 'notes' => []];
            try {
                $data   = $c->fetch();
                $stored = self::store($c->platform(), $data);
                $notes  = $data['notes'] ?? [];
                if ($stored['errors'] !== []) {
                    $notes[] = 'Niet alles opgeslagen (' . ($stored['devices'] - $stored['saved']) . ' apparaten): ' . implode(' · ', $stored['errors']);
                }
                unset($stored['errors']);
                $r = array_merge($r, $stored, ['ok' => true, 'notes' => $notes]);
                try {
                    Topology::applyFromDevices($c->platform(), $data['devices']);
                } catch (Throwable $e) {
                    log_message('error', 'Topologie ' . $c->label() . ': ' . $e->getMessage());
                }
            } catch (Throwable $e) {
                $r['error'] = $e->getMessage();
                log_message('error', 'Netwerk ' . $c->label() . ': ' . $e->getMessage());
            }
            $results[] = $r;
        }

        if ($results !== []) {
            $linked = self::autoLink();
            try {
                Topology::refreshUnifi(); // alleen sites die aan de beurt zijn (eens per 6 uur)
            } catch (Throwable $e) {
                log_message('error', 'Topologie UniFi: ' . $e->getMessage());
            }
            foreach ($results as &$res) {
                $res['linked'] = $res['ok'] ? $linked : 0;
                $linked        = 0; // één keer tellen
            }
            unset($res);
            self::updateDevicesFromNetwork();

            $okAll = array_filter($results, static fn ($x) => $x['ok']);
            if ($okAll !== []) {
                Setting::set('network.lastSync', date('Y-m-d H:i:s'));
            }
            Setting::set('network.lastResult', json_encode(['at' => date('Y-m-d H:i:s'), 'results' => $results], JSON_UNESCAPED_UNICODE));

            // Storingsmails (alleen als ze aan staan en alleen na een geslaagde sync)
            if ($okAll !== []) {
                try {
                    $sent = Alerts::run();
                    if ($sent !== []) {
                        Setting::set('alerts.last', json_encode(['at' => date('Y-m-d H:i:s'), 'notes' => $sent], JSON_UNESCAPED_UNICODE));
                        foreach ($sent as $n) {
                            log_message('info', 'Storingsmail: ' . $n);
                        }
                    }
                } catch (Throwable $e) {
                    log_message('error', 'Storingsmails: ' . $e->getMessage());
                }
            }

            // Klantenkaart: een paar nieuwe adressen opzoeken
            try {
                CustomerMap::fill(10, 10);
            } catch (Throwable $e) {
                log_message('info', 'Klantenkaart: ' . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Slaat sites en apparaten van één platform op.
     *
     * Bewust zonder één grote transactie: daarin draait CodeIgniter bij één
     * mislukte regel stilletjes alles terug. Nu wordt elke regel apart opgeslagen;
     * wat niet lukt, komt in 'errors' (en in het log), de rest staat er gewoon.
     *
     * @return array{sites:int, devices:int, online:int, offline:int, saved:int, errors:list<string>}
     */
    private static function store(string $platform, array $data): array
    {
        $now    = date('Y-m-d H:i:s');
        $db     = db_connect();
        $sitesM = model(NetworkSiteModel::class);
        $devM   = model(NetworkDeviceModel::class);
        $errors = [];
        $key    = static fn ($v) => mb_strtolower(trim((string) $v)); // MySQL vergelijkt zonder hoofdletters

        $write = static function (callable $fn, string $what) use ($db, &$errors) {
            try {
                $r = $fn();
                if ($r === false) {
                    $e = $db->error();
                    throw new RuntimeException(($e['message'] ?? '') !== '' ? $e['message'] : 'onbekende databasefout');
                }

                return $r;
            } catch (Throwable $e) {
                $msg = $what . ': ' . $e->getMessage();
                log_message('error', 'Netwerk opslaan: ' . $msg);
                if (count($errors) < 5) {
                    $errors[] = $msg;
                }

                return false;
            }
        };

        // Sites
        $existing = [];
        foreach ($sitesM->where('platform', $platform)->findAll() as $s) {
            $existing[$key($s['external_id'])] = $s;
        }
        $siteIds = []; // sleutel (kleine letters) => id
        foreach ($data['sites'] as $s) {
            $ext = mb_substr(trim((string) $s['external_id']), 0, 100);
            if ($ext === '' || isset($siteIds[$key($ext)])) {
                continue; // leeg of dubbel
            }
            $row = [
                'platform'       => $platform,
                'external_id'    => $ext,
                'name'           => mb_substr((string) ($s['name'] ?: 'Site'), 0, 150),
                'description'    => $s['description'] !== null ? mb_substr($s['description'], 0, 255) : null,
                'host_id'        => $s['host_id'] !== null ? mb_substr($s['host_id'], 0, 150) : null,
                'host_name'      => $s['host_name'] !== null ? mb_substr($s['host_name'], 0, 150) : null,
                'last_synced_at' => $now,
            ];
            if (array_key_exists('fabric', $s)) {
                $row['fabric'] = $s['fabric'] !== null ? mb_substr($s['fabric'], 0, 100) : null;
            }
            if (array_key_exists('vpn', $s)) { // alleen als SD-WAN echt is opgehaald
                $row['vpn']    = $s['vpn'] ? json_encode($s['vpn'], JSON_UNESCAPED_UNICODE) : null;
                $row['vpn_at'] = $now;
            }
            if (isset($existing[$key($ext)])) {
                $id = (int) $existing[$key($ext)]['id'];
                $write(static fn () => $sitesM->update($id, $row), 'site ' . $row['name']);
                $siteIds[$key($ext)] = $id;
            } else {
                $id = $write(static fn () => $sitesM->insert($row), 'site ' . $row['name']);
                if ($id) {
                    $siteIds[$key($ext)] = (int) $id;
                }
            }
        }

        // Apparaten
        $existing = [];
        foreach ($devM->where('platform', $platform)->findAll() as $d) {
            $existing[$key($d['external_id'])] = $d;
        }
        $counts = ['online' => 0, 'offline' => 0];
        $seen   = [];
        $saved  = 0;
        foreach ($data['devices'] as $d) {
            $ext = mb_substr(trim((string) $d['external_id']), 0, 150);
            if ($ext === '' || isset($seen[$key($ext)])) {
                continue;
            }
            $seen[$key($ext)] = true;
            $prev = $existing[$key($ext)] ?? null;

            $offlineSince = null;
            if ($d['status'] === 'offline') {
                $offlineSince = ($prev && $prev['status'] === 'offline' && $prev['offline_since'])
                    ? $prev['offline_since']
                    : ($d['last_seen_at'] ?? $prev['last_seen_at'] ?? $now);
            }
            if (isset($counts[$d['status']])) {
                $counts[$d['status']]++;
            }
            $cut = static fn ($v, int $n) => $v === null || $v === '' ? null : mb_substr((string) $v, 0, $n);

            $row = [
                'platform'        => $platform,
                'external_id'     => $ext,
                'network_site_id' => $d['site_external_id'] !== null ? ($siteIds[$key($d['site_external_id'])] ?? null) : null,
                'name'            => $cut($d['name'], 150),
                'model'           => $cut($d['model'], 100),
                'kind'            => $cut($d['kind'], 50),
                'mac'             => Connector::normalizeMac($d['mac']),
                'serial'          => $cut($d['serial'], 100),
                'ip'              => $cut($d['ip'] ?? $prev['ip'] ?? null, 64), // offline geeft soms geen IP: laatst bekende houden
                'firmware'        => $cut($d['firmware'] ?? $prev['firmware'] ?? null, 100),
                'status'          => in_array($d['status'], ['online', 'offline'], true) ? $d['status'] : 'unknown',
                'raw_status'      => $cut($d['raw_status'], 50),
                'last_seen_at'    => $d['last_seen_at'] ?? $prev['last_seen_at'] ?? null,
                'offline_since'   => $offlineSince,
                'synced_at'       => $now,
                'gone'            => 0,
            ];
            $label = 'apparaat ' . ($row['name'] ?? $row['mac'] ?? $ext);
            $ok    = $prev
                ? $write(static fn () => $devM->update($prev['id'], $row), $label)
                : $write(static fn () => $devM->insert($row), $label);
            if ($ok !== false) {
                $saved++;
            }
        }

        // Niet meer in het platform (verwijderd of vergeten): niet meer meetellen.
        // Alleen als het ophalen echt iets opleverde, anders zou alles "weg" zijn.
        // Lukte één Fabric niet ('partial'), dan ook niet: die apparaten zijn er nog.
        if ($seen !== [] && empty($data['partial'])) {
            $goneIds = array_column(array_filter($existing, static fn ($d) => ! isset($seen[$key($d['external_id'])]) && ! $d['gone']), 'id');
            if ($goneIds !== []) {
                $write(static fn () => $db->table('network_devices')->whereIn('id', $goneIds)
                    ->update(['gone' => 1, 'status' => 'unknown', 'offline_since' => null, 'updated_at' => $now]), 'verdwenen apparaten');
            }
        }

        // Sites die niet meer bestaan, nergens aan gekoppeld zijn en geen apparaten meer hebben: weg
        $keep = array_flip(array_values($siteIds));
        foreach ($sitesM->where('platform', $platform)->findAll() as $s) {
            if (! isset($keep[(int) $s['id']]) && ! $s['site_id']
                && $devM->where('network_site_id', $s['id'])->where('gone', 0)->countAllResults() === 0) {
                $write(static function () use ($db, $sitesM, $s) {
                    $db->table('network_devices')->where('network_site_id', $s['id'])->update(['network_site_id' => null]);

                    return $sitesM->delete($s['id']);
                }, 'oude site ' . $s['name']);
            }
        }

        // Tellers per site
        foreach ($sitesM->where('platform', $platform)->findAll() as $s) {
            $set = [
                'device_count'  => $devM->where('network_site_id', $s['id'])->where('gone', 0)->whereIn('platform', self::MONITORED)->countAllResults(),
                'offline_count' => $devM->where('network_site_id', $s['id'])->where('gone', 0)->whereIn('platform', self::MONITORED)->where('monitor', 1)->where('ignored', 0)->where('status', 'offline')->countAllResults(),
            ];
            $write(static fn () => $sitesM->update($s['id'], $set), 'tellers ' . $s['name']);
        }

        if ($seen !== [] && $saved === 0) {
            throw new RuntimeException('Er is niets opgeslagen. ' . ($errors[0] ?? ''));
        }

        return ['sites' => count($siteIds), 'devices' => count($seen), 'saved' => $saved, 'errors' => $errors] + $counts;
    }

    /**
     * Koppelt netwerkapparaten zonder kluis-apparaat aan een kluis-apparaat met
     * hetzelfde MAC-adres of serienummer.
     */
    public static function autoLink(): int
    {
        $unlinked = model(NetworkDeviceModel::class)->where('device_id', null)->where('gone', 0)->whereIn('platform', self::MONITORED)->findAll();
        if ($unlinked === []) {
            return 0;
        }

        $taken = array_flip(array_map('intval', array_column(
            model(NetworkDeviceModel::class)->select('device_id')->where('device_id IS NOT NULL', null, false)->whereIn('platform', self::MONITORED)->findAll(), 'device_id')));

        $byMac = $bySerial = [];
        foreach (model(DeviceModel::class)->select('id, mac, serial')->groupStart()->where('mac IS NOT NULL', null, false)->orWhere('serial IS NOT NULL', null, false)->groupEnd()->findAll() as $d) {
            if (isset($taken[(int) $d['id']])) {
                continue;
            }
            if ($m = Connector::normalizeMac($d['mac'])) {
                $byMac[$m] ??= (int) $d['id'];
            }
            if ($d['serial'] !== null && trim($d['serial']) !== '') {
                $bySerial[strtolower(trim($d['serial']))] ??= (int) $d['id'];
                if ($m = Connector::normalizeMac($d['serial'])) {
                    $byMac[$m] ??= (int) $d['id']; // UniFi: serienummer = MAC
                }
            }
        }

        $n = 0;
        foreach ($unlinked as $nd) {
            $deviceId = ($nd['mac'] ? ($byMac[$nd['mac']] ?? null) : null)
                ?? ($nd['serial'] ? ($bySerial[strtolower($nd['serial'])] ?? null) : null);
            if ($deviceId === null || isset($taken[$deviceId])) {
                continue;
            }
            model(NetworkDeviceModel::class)->update($nd['id'], ['device_id' => $deviceId]);
            $taken[$deviceId] = true;
            $n++;
        }

        return $n;
    }

    /** IP, firmware en MAC van gekoppelde kluis-apparaten bijwerken met wat het netwerk zegt */
    public static function updateDevicesFromNetwork(): void
    {
        $rows = model(NetworkDeviceModel::class)
            ->select('network_devices.ip AS n_ip, network_devices.firmware AS n_fw, network_devices.mac AS n_mac, devices.id, devices.ip, devices.firmware, devices.mac')
            ->join('devices', 'devices.id = network_devices.device_id')
            ->where('network_devices.gone', 0)->findAll();

        foreach ($rows as $r) {
            $set = [];
            if ($r['n_ip'] && $r['n_ip'] !== $r['ip']) {
                $set['ip'] = $r['n_ip'];
            }
            if ($r['n_fw'] && $r['n_fw'] !== $r['firmware']) {
                $set['firmware'] = mb_substr($r['n_fw'], 0, 100);
            }
            if ($r['n_mac'] && ! Connector::normalizeMac($r['mac'])) {
                $set['mac'] = self::formatMac($r['n_mac']);
            }
            if ($set !== []) {
                model(DeviceModel::class)->update($r['id'], $set);
            }
        }
    }

    /** 'aabbccddeeff' -> 'AA:BB:CC:DD:EE:FF' */
    public static function formatMac(?string $mac): ?string
    {
        $m = Connector::normalizeMac($mac);

        return $m === null ? $mac : strtoupper(implode(':', str_split($m, 2)));
    }

    // ------------------------------------------------------------------
    // Koppelen

    /** Netwerkapparaat aan een bestaand kluis-apparaat hangen (en zijn gegevens aanvullen) */
    public static function link(int $networkDeviceId, int $deviceId): void
    {
        $nd     = model(NetworkDeviceModel::class)->find($networkDeviceId);
        $device = model(DeviceModel::class)->find($deviceId);
        if ($nd === null || $device === null) {
            throw new RuntimeException('Apparaat niet gevonden.');
        }
        // Een kluis-apparaat hoort bij hooguit één netwerkapparaat
        db_connect()->table('network_devices')->where('device_id', $deviceId)->where('id !=', $networkDeviceId)->update(['device_id' => null]);
        model(NetworkDeviceModel::class)->update($networkDeviceId, ['device_id' => $deviceId, 'ignored' => 0]);

        $set = [];
        if ($nd['mac'] && ! Connector::normalizeMac($device['mac'])) {
            $set['mac'] = self::formatMac($nd['mac']);
        }
        if ($nd['serial'] && ! $device['serial']) {
            $set['serial'] = $nd['serial'];
        }
        if ($nd['ip']) {
            $set['ip'] = $nd['ip'];
        }
        if ($nd['firmware']) {
            $set['firmware'] = mb_substr($nd['firmware'], 0, 100);
        }
        if ($nd['model'] && ! $device['model']) {
            $set['model'] = $nd['model'];
        }
        if (! $device['site_id'] && $nd['network_site_id']) {
            $ns = model(NetworkSiteModel::class)->find($nd['network_site_id']);
            $loc = $ns && $ns['site_id'] ? model(SiteModel::class)->find($ns['site_id']) : null;
            if ($loc && (int) $loc['customer_id'] === (int) $device['customer_id']) {
                $set['site_id'] = $loc['id'];
            }
        }
        if ($set !== []) {
            model(DeviceModel::class)->update($deviceId, $set);
        }
    }

    /** Nieuw kluis-apparaat maken van een netwerkapparaat */
    public static function createDevice(int $networkDeviceId, int $customerId, ?int $siteId): int
    {
        $nd = model(NetworkDeviceModel::class)->find($networkDeviceId);
        if ($nd === null) {
            throw new RuntimeException('Apparaat niet gevonden.');
        }
        $type = self::guessType($nd);
        $id   = (int) model(DeviceModel::class)->insert([
            'customer_id' => $customerId,
            'site_id'     => $siteId,
            'type'        => $type,
            'name'        => mb_substr($nd['name'] ?: ($nd['model'] ?: device_type_label($type)), 0, 255),
            'brand'       => 'Ubiquiti',
            'model'       => $nd['model'],
            'serial'      => $nd['serial'],
            'mac'         => self::formatMac($nd['mac']),
            'ip'          => $nd['ip'],
            'firmware'    => $nd['firmware'] ? mb_substr($nd['firmware'], 0, 100) : null,
            'notes'       => 'Uit ' . self::platformLabel($nd['platform']),
        ]);
        model(NetworkDeviceModel::class)->update($networkDeviceId, ['device_id' => $id, 'ignored' => 0]);

        return $id;
    }

    /** Soort apparaat raden uit model, naam en productlijn */
    public static function guessType(array $nd): string
    {
        if (($nd['platform'] ?? '') === 'manual') {
            return $nd['kind'] && isset(device_types()[$nd['kind']]) ? $nd['kind'] : 'other'; // zelf gekozen soort
        }
        $kind = strtolower((string) $nd['kind']);
        if ($kind === 'protect') {
            $t = device_type_guess((string) $nd['model'] . ' ' . $nd['name']);

            return in_array($t, ['camera', 'nvr', 'intercom'], true) ? $t : 'camera';
        }
        if ($kind === 'access') {
            return 'access';
        }
        $t = device_type_guess(trim($nd['model'] . ' ' . $nd['name']));
        if ($t !== null && ! str_starts_with($t, 'alarm')) {
            return $t;
        }
        $m = strtolower((string) $nd['model']);

        return match (true) {
            (bool) preg_match('/^(udm|ucg|udr|uxg|usg|ux|er-|edgerouter|uisp-r)/', $m)                    => 'router',
            (bool) preg_match('/^(usw|us-|es-|edgeswitch|uisp-s|flex)/', $m)                               => 'switch',
            (bool) preg_match('/^(u6|u7|uap|ual|uk-|nanostation|litebeam|powerbeam|rocket|wave|airmax|ac-|nbe|lbe|loco|af|gbe)/', $m),
            in_array($kind, ['wireless', 'optical'], true)                                                 => 'ap',
            $kind === 'wired'                                                                               => 'switch',
            default                                                                                         => 'other',
        };
    }

    /**
     * Kluis-apparaten van een klant die nog aan geen netwerkapparaat hangen, met de
     * beste kandidaat voor dit netwerkapparaat bovenaan (zelfde soort, model, locatie).
     *
     * @return list<array>
     */
    public static function candidates(array $nd, int $customerId, ?int $siteId): array
    {
        $taken = array_map('intval', array_column(model(NetworkDeviceModel::class)->select('device_id')->where('device_id IS NOT NULL', null, false)->whereIn('platform', self::MONITORED)->findAll(), 'device_id'));
        $q     = model(DeviceModel::class)->where('customer_id', $customerId);
        if ($taken !== []) {
            $q->whereNotIn('id', $taken);
        }
        $list = $q->findAll();
        $type = self::guessType($nd);
        $norm = static fn ($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
        $nm   = $norm($nd['model']);

        foreach ($list as &$d) {
            $score = 0;
            if ($d['type'] === $type) {
                $score += 4;
            }
            $dm = $norm($d['model']);
            if ($nm !== '' && $dm !== '' && (str_contains($dm, $nm) || str_contains($nm, $dm))) {
                $score += 5;
            }
            if ($siteId !== null && (int) $d['site_id'] === $siteId) {
                $score += 2;
            } elseif ($d['site_id'] === null) {
                $score += 1;
            }
            if (! Connector::normalizeMac($d['mac']) && ! $d['serial']) {
                $score += 1; // nog leeg: waarschijnlijk net uit een offerte
            }
            $d['score'] = $score;
        }
        unset($d);
        usort($list, static fn ($a, $b) => [$b['score'], $a['name']] <=> [$a['score'], $b['name']]);

        return $list;
    }

    // ------------------------------------------------------------------
    // Overzichten

    /** Status van de synchronisatie: wanneer, en is het nog actueel? */
    public static function status(): array
    {
        $last   = self::setting('network.lastSync');
        $result = json_decode(self::setting('network.lastResult'), true) ?: null;
        $conf   = self::configured();

        return [
            'configured' => $conf,
            'lastSync'   => $last ?: null,
            'stale'      => $conf && ($last === '' || strtotime($last) < time() - self::STALE_MINUTES * 60),
            'errors'     => array_values(array_filter(array_map(static fn ($r) => $r['error'] ? $r['label'] . ': ' . $r['error'] : null, $result['results'] ?? []))),
        ];
    }

    /**
     * Bewaakte apparaten die offline zijn, per klant en locatie.
     *
     * @return array{groups: list<array{customer_id:int, customer:string, locations: list<array{site_id:?int, site:string, devices:list<array>}>}>, count:int, unassigned:int, monitored:int}
     */
    public static function offline(): array
    {
        $base = static fn () => model(NetworkDeviceModel::class)->withContext()
            ->whereIn('network_devices.platform', self::MONITORED)
            ->where('network_devices.monitor', 1)->where('network_devices.ignored', 0)->where('network_devices.gone', 0)
            ->groupStart()->where('network_sites.ignored', 0)->orWhere('network_sites.id IS NULL', null, false)->groupEnd();

        $rows = $base()->where('network_devices.status', 'offline')->orderBy('network_devices.offline_since', 'ASC')->findAll();

        $customers = array_column(model(CustomerModel::class)->select('id, name')->findAll(), 'name', 'id');
        $locations = array_column(model(SiteModel::class)->select('id, name')->findAll(), 'name', 'id');

        $groups     = [];
        $unassigned = 0;
        $count      = 0;
        foreach ($rows as $r) {
            if (! $r['cust_id']) {
                $unassigned++;
                continue;
            }
            $count++;
            $c = (int) $r['cust_id'];
            $l = $r['loc_id'] ? (int) $r['loc_id'] : 0;
            $groups[$c] ??= ['customer_id' => $c, 'customer' => $customers[$c] ?? 'Klant ' . $c, 'locations' => []];
            $groups[$c]['locations'][$l] ??= ['site_id' => $l ?: null, 'site' => $l ? ($locations[$l] ?? 'Locatie') : 'Geen locatie', 'devices' => []];
            $groups[$c]['locations'][$l]['devices'][] = $r;
        }
        foreach ($groups as &$g) {
            $g['locations'] = array_values($g['locations']);
        }
        unset($g);

        // Klanten met de meeste offline apparaten bovenaan
        $groups = array_values($groups);
        usort($groups, static function ($a, $b) {
            $n = static fn ($g) => array_sum(array_map(static fn ($l) => count($l['devices']), $g['locations']));

            return [$n($b), $a['customer']] <=> [$n($a), $b['customer']];
        });

        $monitored = $base()->groupStart()->where('devices.customer_id IS NOT NULL', null, false)->orWhere('sites.customer_id IS NOT NULL', null, false)->groupEnd()->countAllResults();

        return ['groups' => $groups, 'count' => $count, 'unassigned' => $unassigned, 'monitored' => $monitored];
    }

    /**
     * Netwerkstatus per kluis-apparaat (voor de stipjes in de apparatenlijsten).
     *
     * @param list<int|string> $deviceIds
     * @return array<int,array> device_id => network_devices-rij
     */
    public static function statusMap(array $deviceIds): array
    {
        if ($deviceIds === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($deviceIds, 500) as $chunk) {
            foreach (model(NetworkDeviceModel::class)->select('id, device_id, platform, status, monitor, last_seen_at, offline_since, gone')
                ->whereIn('device_id', $chunk)->whereIn('platform', self::MONITORED)->findAll() as $r) {
                $out[(int) $r['device_id']] = $r;
            }
        }

        return $out;
    }

    /** Stipje met tooltip voor een netwerkstatus */
    public static function dot(?array $nd): string
    {
        if ($nd === null) {
            return '';
        }
        if ($nd['gone']) {
            return '<span class="dot dot-unk" title="Niet meer gevonden in ' . esc(self::platformLabel($nd['platform']), 'attr') . '"></span>';
        }
        [$cls, $label] = match ($nd['status']) {
            'online'  => ['dot-on', 'Online'],
            'offline' => [$nd['monitor'] ? 'dot-off' : 'dot-unk', 'Offline' . ($nd['offline_since'] ? ' sinds ' . self::ago($nd['offline_since']) : '') . ($nd['monitor'] ? '' : ' (niet bewaakt)')],
            default   => ['dot-unk', 'Status onbekend'],
        };

        return '<span class="dot ' . $cls . '" title="' . esc($label . ' · ' . self::platformLabel($nd['platform']), 'attr') . '"></span>';
    }

    /** "5 min", "3 uur", "2 dagen" */
    public static function ago(?string $datetime): string
    {
        if (! $datetime) {
            return '';
        }
        $s = max(0, time() - strtotime($datetime));

        return match (true) {
            $s < 90     => 'net',
            $s < 3600   => round($s / 60) . ' min',
            $s < 86400 * 2 => round($s / 3600) . ' uur',
            default     => round($s / 86400) . ' dagen',
        };
    }
}
