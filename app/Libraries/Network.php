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

    // ------------------------------------------------------------------
    // Instellingen

    public static function setting(string $key): string
    {
        return (string) Setting::get($key, '');
    }

    /** API-sleutels staan versleuteld in app_settings ("enc:…"), of los in .env */
    public static function secret(string $key): string
    {
        $v = self::setting($key);
        if (str_starts_with($v, 'enc:')) {
            try {
                return Vault::decrypt(substr($v, 4));
            } catch (Throwable $e) {
                log_message('error', 'Netwerk: ' . $key . ' ontsleutelen mislukt: ' . $e->getMessage());

                return '';
            }
        }

        return $v;
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

    public static function platformLabel(?string $platform): string
    {
        return match ($platform) {
            'unifi' => 'UniFi',
            'uisp'  => 'UISP',
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
            } catch (Throwable $e) {
                $r['error'] = $e->getMessage();
                log_message('error', 'Netwerk ' . $c->label() . ': ' . $e->getMessage());
            }
            $results[] = $r;
        }

        if ($results !== []) {
            $linked = self::autoLink();
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
        if ($seen !== []) {
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
                'device_count'  => $devM->where('network_site_id', $s['id'])->where('gone', 0)->countAllResults(),
                'offline_count' => $devM->where('network_site_id', $s['id'])->where('gone', 0)->where('monitor', 1)->where('ignored', 0)->where('status', 'offline')->countAllResults(),
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
        $unlinked = model(NetworkDeviceModel::class)->where('device_id', null)->where('gone', 0)->findAll();
        if ($unlinked === []) {
            return 0;
        }

        $taken = array_flip(array_map('intval', array_column(
            model(NetworkDeviceModel::class)->select('device_id')->where('device_id IS NOT NULL', null, false)->findAll(), 'device_id')));

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
        $taken = array_map('intval', array_column(model(NetworkDeviceModel::class)->select('device_id')->where('device_id IS NOT NULL', null, false)->findAll(), 'device_id'));
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
                ->whereIn('device_id', $chunk)->findAll() as $r) {
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
