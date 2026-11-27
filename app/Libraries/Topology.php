<?php

namespace App\Libraries;

use App\Libraries\Network\Connector;
use App\Libraries\Network\Unifi;
use App\Libraries\Network\UnifiController;
use App\Models\NetworkDeviceModel;
use App\Models\NetworkLinkModel;
use App\Models\NetworkSiteModel;
use RuntimeException;
use Throwable;

/**
 * Topologie per netwerksite: welk apparaat hangt aan welk apparaat, via welke poort.
 *
 * Automatisch uit UniFi (uplink per apparaat, via je eigen controller of de Cloud
 * Connector) en UISP (station -> AP). Wat je zelf instelt, staat vast (locked) en
 * wordt door de sync niet meer veranderd; wat UniFi dan zegt, blijft zichtbaar als
 * "UniFi ziet".
 *
 * Een apparaat staat in de topologie van zijn bovenliggende apparaat. Zo komt een
 * UISP-straalverbinding vanzelf in de UniFi-site waar je hem onder hangt, en de
 * UniFi-switch aan de overkant daar weer onder.
 */
class Topology
{
    public const TYPES = [
        'wired'    => 'Kabel',
        'wireless' => 'Draadloos (wifi)',
        'ptp'      => 'Straalverbinding (PTP)',
        'vpn'      => 'VPN / internet',
    ];

    /** Zo vaak worden de UniFi-uplinks automatisch opnieuw opgehaald */
    private const REFRESH_HOURS = 6;

    // ------------------------------------------------------------------
    // Automatisch ophalen

    /**
     * UniFi-uplinks ophalen voor sites die aan de beurt zijn (of één site, geforceerd).
     *
     * @return list<string> meldingen
     */
    public static function refreshUnifi(?int $networkSiteId = null): array
    {
        $unifi = new Unifi();
        if (! $unifi->configured()) {
            return [];
        }
        $ctrl  = new UnifiController();
        $notes = [];
        $q     = model(NetworkSiteModel::class)->where('platform', 'unifi')->where('ignored', 0)->notLike('external_id', 'host:', 'after');
        if ($networkSiteId !== null) {
            $q->where('id', $networkSiteId);
        }
        $deadline = microtime(true) + 90;

        foreach ($q->findAll() as $site) {
            $key = 'topology.at.' . $site['id'];
            if ($networkSiteId === null && (int) Network::setting($key) > time() - self::REFRESH_HOURS * 3600) {
                continue;
            }
            if (microtime(true) > $deadline) {
                break; // de rest bij de volgende synchronisatie
            }
            $name = (string) ($site['description'] ?: 'default');
            $rows = null;
            $err  = null;
            // MAC-adressen van deze console: zo hoort wat we ophalen echt bij deze site
            // (elke UCG heeft ook een site "default", net als je eigen controller)
            $hostMacs = array_flip(array_filter(array_column(model(NetworkDeviceModel::class)->select('network_devices.mac')
                ->join('network_sites', 'network_sites.id = network_devices.network_site_id')
                ->where('network_sites.host_id', $site['host_id'])->where('network_devices.gone', 0)->findAll(), 'mac')));
            $ours = static fn (array $list) => array_values(array_filter($list, static fn ($d) => isset($hostMacs[Connector::normalizeMac($d['mac'] ?? null)])));
            if ($ctrl->configured()) {
                try {
                    $rows = $ours($ctrl->devices($name));
                    $rows = $rows === [] ? null : $rows; // niet van deze console
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
            }
            if ($rows === null && $site['host_id']) {
                try {
                    $rows = $ours($unifi->legacyDevices($site['host_id'], $name));
                } catch (Throwable $e) {
                    $err ??= $e->getMessage();
                }
            }
            if ($rows === null) {
                if ($networkSiteId !== null) {
                    $notes[] = 'Verbindingen van ' . $site['name'] . ' ophalen lukte niet' . ($err ? ': ' . $err : '') . '.';
                }
                Setting::set($key, (string) (time() - (self::REFRESH_HOURS - 1) * 3600)); // over een uur opnieuw
                continue;
            }

            $links = [];
            $seen  = [];
            foreach ($rows as $d) {
                $mac = Connector::normalizeMac($d['mac'] ?? null);
                if ($mac === null) {
                    continue;
                }
                $seen[]      = $mac;
                $links[$mac] = Unifi::uplinkOf($d);
            }
            self::applyAuto('unifi', $links, 'mac');
            self::saveSignals($links);
            $ghostNote = self::markGhosts($site, $seen);
            if ($ghostNote !== null && $networkSiteId !== null) {
                $notes[] = $ghostNote;
            }
            Setting::set($key, (string) time());
        }

        return $notes;
    }

    /**
     * Signaal van draadloze UniFi-uplinks (mesh) bewaren; bedraad of onbekend: leeg.
     *
     * @param array<string,?array> $links MAC => uplinkOf()
     */
    private static function saveSignals(array $links): void
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');
        foreach ($links as $mac => $u) {
            $signal = $u['signal'] ?? null;
            $db->table('network_devices')->where('platform', 'unifi')->where('mac', $mac)->update([
                'signal'    => $signal,
                'radio'     => $signal !== null ? ($u['radio'] ?? null) : null,
                'signal_at' => $signal !== null ? $now : null,
            ]);
        }
    }

    /**
     * Spookcontrole: apparaten die de UniFi-cloud bij deze site noemt, maar die niet in
     * de apparatenlijst van de console zelf staan. Die gelden als verdwenen (en de
     * synchronisatie slaat ze voortaan over). Komt zo'n apparaat later wél weer op de
     * console, dan telt het gewoon weer mee.
     *
     * Veilig: alleen als alle apparaten die nu online zijn in de lijst van de console
     * staan (anders hebben we misschien de lijst van een andere console of site).
     *
     * @param list<string> $seen MAC-adressen op de console
     */
    private static function markGhosts(array $site, array $seen): ?string
    {
        $seenSet = array_flip($seen);
        $mine    = model(NetworkDeviceModel::class)->select('id, mac, status, name, model')
            ->where('network_site_id', $site['id'])->where('platform', 'unifi')->where('gone', 0)
            ->where('mac IS NOT NULL', null, false)->findAll();
        foreach ($mine as $d) {
            if ($d['status'] === 'online' && ! isset($seenSet[$d['mac']])) {
                return null; // lijst klopt niet met wat er online is: niets concluderen
            }
        }

        $ghosts = Network::ghosts();
        $before = $ghosts;
        // Wat weer op de console staat, is geen spook meer
        foreach ($ghosts as $mac => $g) {
            if ((int) ($g['site'] ?? 0) === (int) $site['id'] && isset($seenSet[$mac])) {
                unset($ghosts[$mac]);
            }
        }
        $new = [];
        foreach ($mine as $d) {
            if (! isset($seenSet[$d['mac']]) && $d['status'] !== 'online') {
                $ghosts[$d['mac']] = ['site' => (int) $site['id'], 'since' => date('Y-m-d H:i:s')];
                $new[]             = (int) $d['id'];
            }
        }
        if ($ghosts !== $before) {
            Network::saveGhosts($ghosts);
        }
        if ($new === []) {
            return null;
        }
        model(NetworkDeviceModel::class)->whereIn('id', $new)
            ->set(['gone' => 1, 'status' => 'unknown', 'offline_since' => null])->update();
        log_message('info', 'Topologie ' . $site['name'] . ': ' . count($new) . ' spookapparaten (niet op de console) weggezet');

        return count($new) . ' apparaten stonden wel in de UniFi-cloud maar niet meer op de console van ' . $site['name'] . '; die tellen niet meer mee.';
    }

    /**
     * Uplinks uit de apparatenlijst van een platform verwerken (UISP levert ze bij
     * elke synchronisatie mee).
     */
    public static function applyFromDevices(string $platform, array $devices): void
    {
        $links = [];
        foreach ($devices as $d) {
            if (array_key_exists('uplink', $d)) {
                $links[(string) $d['external_id']] = $d['uplink'];
            }
        }
        if ($links !== []) {
            self::applyAuto($platform, $links, 'external_id');
        }
    }

    /**
     * Automatisch gevonden verbindingen opslaan. Bij vastgezette verbindingen
     * alleen het auto_-deel; anders ook wat getekend wordt.
     *
     * @param array<string, array|null> $links  sleutel (mac of external_id) => uplink of null (geen uplink: bovenaan)
     */
    private static function applyAuto(string $platform, array $links, string $keyField): void
    {
        $devM  = model(NetworkDeviceModel::class);
        $linkM = model(NetworkLinkModel::class);
        $now   = date('Y-m-d H:i:s');

        // Apparaten van dit platform op sleutel, en alle apparaten op MAC (ouder kan elk platform zijn)
        $byKey = $byMac = $byExt = [];
        foreach ($devM->select('id, platform, mac, external_id')->where('gone', 0)->findAll() as $nd) {
            if ($nd['mac']) {
                $byMac[$nd['mac']] ??= (int) $nd['id'];
            }
            if ($nd['platform'] === $platform) {
                $byExt[mb_strtolower($nd['external_id'])] = (int) $nd['id'];
                $k = $keyField === 'mac' ? $nd['mac'] : mb_strtolower($nd['external_id']);
                if ($k) {
                    $byKey[$k] = (int) $nd['id'];
                }
            }
        }
        $existing = array_column($linkM->whereIn('network_device_id', array_values($byKey) ?: [0])->findAll(), null, 'network_device_id');

        // Eerst alles verzamelen, dan pas schrijven: zo kunnen we rondjes weren
        $plan = [];
        foreach ($links as $key => $u) {
            $childId = $byKey[$keyField === 'mac' ? $key : mb_strtolower($key)] ?? null;
            if ($childId === null) {
                continue;
            }
            $parentId = null;
            if ($u !== null) {
                $parentId = $u['parent_mac'] ? ($byMac[$u['parent_mac']] ?? null)
                    : ($u['parent_external_id'] ? ($byExt[mb_strtolower($u['parent_external_id'])] ?? null) : null);
            }
            if ($parentId === $childId) {
                $parentId = null;
            }
            $plan[$childId] = [$parentId, $u];
        }

        // Ouders zoals ze na deze ronde zouden zijn: vastgezette blijven, de rest wordt automatisch
        $parents = [];
        foreach ($linkM->select('network_device_id, parent_id, locked')->findAll() as $l) {
            $parents[(int) $l['network_device_id']] = $l['parent_id'] !== null ? (int) $l['parent_id'] : null;
        }
        foreach ($plan as $childId => [$parentId]) {
            $row = $existing[$childId] ?? null;
            if (! ($row && $row['locked'])) {
                $parents[$childId] = $parentId;
            }
        }
        // Rondje (A aan B, B aan A, ook via via; komt voor bij mesh en verouderde uplinks)?
        // Dan krijgt het automatische apparaat in het rondje geen ouder.
        foreach (array_keys($plan) as $childId) {
            $row = $existing[$childId] ?? null;
            if ($row && $row['locked']) {
                continue;
            }
            for ($p = $parents[$childId] ?? null, $i = 0; $p !== null && $i < 1000; $i++) {
                if ($p === $childId) {
                    $plan[$childId][0] = null;
                    $parents[$childId] = null;
                    log_message('info', 'Topologie: rondje bij netwerkapparaat ' . $childId . ', automatische ouder genegeerd.');
                    break;
                }
                $p = $parents[$p] ?? null;
            }
        }

        foreach ($plan as $childId => [$parentId, $u]) {
            $auto = [
                'auto_parent_id'   => $parentId,
                'auto_parent_port' => $parentId ? $u['parent_port'] : null,
                'auto_link_type'   => $parentId ? $u['type'] : null,
                'auto_seen_at'     => $now,
            ];
            $row = $existing[$childId] ?? null;
            if ($row && $row['locked']) {
                $linkM->update($row['id'], $auto);
                continue;
            }
            $set = $auto + [
                'parent_id'   => $parentId,
                'parent_port' => $auto['auto_parent_port'],
                'local_port'  => $parentId ? $u['local_port'] : null,
                'link_type'   => $auto['auto_link_type'] ?? 'wired',
            ];
            if ($row) {
                $linkM->update($row['id'], $set);
            } else {
                $linkM->insert($set + ['network_device_id' => $childId]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Tekenen

    /**
     * Alle apparaten en verbindingen.
     *
     * @return array{devices: array<int,array>, links: array<int,array>}
     */
    private static function graph(): array
    {
        return [
            'devices' => array_column(model(NetworkDeviceModel::class)->withContext()->where('network_devices.gone', 0)->findAll(), null, 'id'),
            'links'   => array_column(model(NetworkLinkModel::class)->findAll(), null, 'network_device_id'),
        ];
    }

    /**
     * Welke apparaten staan in de topologie van deze netwerksite?
     *
     *  - altijd alle apparaten van de site zelf (er verdwijnt dus nooit iets);
     *  - apparaten die je er zelf aan hebt toegevoegd;
     *  - en de apparaten uit andere sites waar ze via lopen (bijv. de UISP-
     *    straalverbinding tussen twee gebouwen), zodat de lijn klopt.
     *
     * Een apparaat kan zo in meer topologieën staan; het blijft hetzelfde apparaat.
     *
     * @return array<int,true>
     */
    /** @var array<int,?int>|null network_sites.id => merged_into */
    private static ?array $merged = null;

    public static function resetGroups(): void
    {
        self::$merged = null;
    }

    private static function mergedMap(): array
    {
        if (self::$merged === null) {
            self::$merged = [];
            try {
                foreach (model(NetworkSiteModel::class)->select('id, merged_into')->findAll() as $r) {
                    self::$merged[(int) $r['id']] = $r['merged_into'] ? (int) $r['merged_into'] : null;
                }
            } catch (\Throwable $e) {
                // kolom nog niet aangemaakt (migratie): alles los
                foreach (model(NetworkSiteModel::class)->select('id')->findAll() as $r) {
                    self::$merged[(int) $r['id']] = null;
                }
            }
        }

        return self::$merged;
    }

    /** Hoofdsite van een gecombineerde installatie (of de site zelf) */
    public static function primary(int $networkSiteId): int
    {
        return self::mergedMap()[$networkSiteId] ?? $networkSiteId;
    }

    /**
     * Alle sites van de installatie: hoofdsite + wat erbij is gevoegd.
     *
     * @return list<int>
     */
    public static function group(int $networkSiteId): array
    {
        $main = self::primary($networkSiteId);
        $out  = [$main];
        foreach (self::mergedMap() as $id => $into) {
            if ($into === $main) {
                $out[] = $id;
            }
        }

        return $out;
    }

    private static function members(int $networkSiteId, array $devices, array $links): array
    {
        $set = array_flip(self::group($networkSiteId));
        $in  = [];
        foreach ($devices as $id => $d) {
            if (isset($set[(int) $d['network_site_id']]) || isset($set[(int) ($links[$id]['view_site_id'] ?? 0)])) {
                $in[(int) $id] = true;
            }
        }
        // Bovenliggende apparaten erbij (via via), ook uit andere sites
        foreach (array_keys($in) as $id) {
            for ($p = $links[$id]['parent_id'] ?? null, $i = 0; $p !== null && $i < 100; $i++) {
                $p = (int) $p;
                if (! isset($devices[$p]) || isset($in[$p])) {
                    break;
                }
                $in[$p] = true;
                $p = $links[$p]['parent_id'] ?? null;
            }
        }

        return $in;
    }

    /** Komt een apparaat via zijn ouders weer bij zichzelf uit? */
    private static function inCycle(int $id, array $links): bool
    {
        $seen = [];
        for ($p = $links[$id]['parent_id'] ?? null, $i = 0; $p !== null && $i < 1000; $i++) {
            $p = (int) $p;
            if ($p === $id) {
                return true;
            }
            if (isset($seen[$p])) {
                return false; // rondje verderop, niet via dit apparaat
            }
            $seen[$p] = true;
            $p = $links[$p]['parent_id'] ?? null;
        }

        return false;
    }

    /**
     * Knopen en verbindingen voor één topologie.
     *
     * @return list<array>
     */
    public static function nodes(int $networkSiteId): array
    {
        $networkSiteId = self::primary($networkSiteId);
        $set           = array_flip(self::group($networkSiteId));
        ['devices' => $devices, 'links' => $links] = self::graph();
        $in  = self::members($networkSiteId, $devices, $links);
        $out = [];
        foreach (array_keys($in) as $id) {
            $d = $devices[$id];
            $l = $links[$id] ?? null;
            $p = $l && $l['parent_id'] && isset($in[(int) $l['parent_id']]) ? (int) $l['parent_id'] : null;
            if ($p !== null && self::inCycle((int) $id, $links)) {
                $p = null; // rondje: dit apparaat bovenaan tekenen, zodat niets verdwijnt
            }
            $out[] = [
                'id'          => (int) $id,
                'name'        => $d['device_name'] ?: ($d['name'] ?: ($d['model'] ?: Network::formatMac($d['mac']))),
                'model'       => $d['model'],
                'ip'          => $d['ip'],
                'mac'         => Network::formatMac($d['mac']),
                'platform'    => $d['platform'],
                'type'        => $d['platform'] === 'manual' ? ($d['kind'] ?: 'other') : ($d['device_type'] ?: Network::guessType($d)),
                'status'      => $d['status'],
                'monitor'     => (bool) $d['monitor'],
                'device_id'   => $d['device_id'] ? (int) $d['device_id'] : null,
                'site'        => $d['nsite_name'],
                'parent'      => $p,
                'parent_port' => $p ? $l['parent_port'] : null,
                'local_port'  => $p ? $l['local_port'] : null,
                'link'        => $p ? $l['link_type'] : null,
                'signal'      => $p && isset($d['signal']) && $d['signal'] !== null ? (int) $d['signal'] : null,
                'radio'       => $p ? ($d['radio'] ?? null) : null,
                'signal_at'   => $p && ! empty($d['signal_at']) ? Network::ago($d['signal_at']) : null,
                'locked'      => (bool) ($l['locked'] ?? false),
                'note'        => $l['note'] ?? null,
                'auto'        => $l && $l['locked'] && ($l['auto_parent_id'] || $l['auto_seen_at']) ? [
                    'parent'      => $l['auto_parent_id'] ? (int) $l['auto_parent_id'] : null,
                    'parent_name' => $l['auto_parent_id'] && isset($devices[(int) $l['auto_parent_id']])
                        ? ($devices[(int) $l['auto_parent_id']]['device_name'] ?: $devices[(int) $l['auto_parent_id']]['name']) : null,
                    'port'        => $l['auto_parent_port'],
                    'type'        => $l['auto_link_type'],
                ] : null,
                'foreign'     => ! isset($set[(int) ($d['network_site_id'] ?? 0)]), // uit een site buiten deze installatie
                'kind'        => in_array($d['platform'], ['manual', 'vault'], true) ? $d['platform'] : 'network',
            ];
        }
        usort($out, static fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * Boomvolgorde zoals de tekening: bovenaan de router, dan per poort naar beneden.
     *
     * @param list<array> $nodes  uit nodes()
     * @return array{order: list<array{node:array, depth:int}>, byId: array<int,array>}
     */
    public static function tree(array $nodes): array
    {
        $byId = [];
        foreach ($nodes as $n) {
            $byId[$n['id']] = $n;
        }
        $kids = [];
        foreach ($nodes as $n) {
            if ($n['parent'] && isset($byId[$n['parent']])) {
                $kids[$n['parent']][] = $n;
            }
        }
        $port = static fn ($p) => preg_match('/\d+/', (string) $p, $m) ? (int) $m[0] : 9999;
        foreach ($kids as &$list) {
            usort($list, static fn ($a, $b) => [$port($a['parent_port']), $a['name']] <=> [$port($b['parent_port']), $b['name']]);
        }
        unset($list);
        $rank  = static fn ($n) => $n['type'] === 'router' ? 0 : ($n['type'] === 'switch' ? 1 : 2);
        $roots = array_values(array_filter($nodes, static fn ($n) => ! $n['parent'] || ! isset($byId[$n['parent']])));
        usort($roots, static fn ($a, $b) => [$rank($a), -count($kids[$a['id']] ?? []), $a['name']] <=> [$rank($b), -count($kids[$b['id']] ?? []), $b['name']]);

        $order = [];
        $seen  = [];
        $walk  = static function (array $n, int $depth) use (&$walk, &$order, &$seen, $kids) {
            if (isset($seen[$n['id']])) {
                return;
            }
            $seen[$n['id']] = true;
            $order[]        = ['node' => $n, 'depth' => $depth];
            foreach ($kids[$n['id']] ?? [] as $c) {
                $walk($c, $depth + 1);
            }
        };
        foreach ($roots as $r) {
            $walk($r, 0);
        }
        foreach ($nodes as $n) { // rondjes: alsnog meenemen
            $walk($n, 0);
        }

        return ['order' => $order, 'byId' => $byId];
    }

    /**
     * Apparaten die je aan deze topologie kunt toevoegen (uit andere sites), per site.
     *
     * @return array<string, list<array{id:int, name:string}>>
     */
    public static function addable(int $networkSiteId): array
    {
        ['devices' => $devices, 'links' => $links] = self::graph();
        $in  = self::members($networkSiteId, $devices, $links);
        $out = [];
        foreach ($devices as $id => $d) {
            if (isset($in[(int) $id])) {
                continue;
            }
            $group = Network::platformLabel($d['platform']) . ' · ' . ($d['nsite_name'] ?? 'zonder site');
            $out[$group][] = ['id' => (int) $id, 'name' => ($d['device_name'] ?: ($d['name'] ?: $d['model'] ?: Network::formatMac($d['mac']))) . ($d['model'] && $d['name'] ? ' · ' . $d['model'] : '')];
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    // ------------------------------------------------------------------
    // Handmatig

    /** Zelf instellen: vastgezet, de sync verandert dit niet meer */
    public static function setParent(int $childId, ?int $parentId, ?string $port, ?string $localPort, string $type, ?string $note, int $viewSiteId): void
    {
        if ($parentId === $childId) {
            throw new RuntimeException('Een apparaat kan niet aan zichzelf hangen.');
        }
        if ($parentId !== null) {
            // Geen rondje: de nieuwe ouder mag niet onder dit apparaat hangen
            $links = array_column(model(NetworkLinkModel::class)->select('network_device_id, parent_id')->findAll(), 'parent_id', 'network_device_id');
            for ($p = $parentId, $i = 0; $p !== null && $i < 500; $i++) {
                if ($p === $childId) {
                    throw new RuntimeException('Dat kan niet: dat apparaat hangt zelf (via via) aan dit apparaat.');
                }
                $p = isset($links[$p]) ? (int) $links[$p] ?: null : null;
            }
        }
        self::upsert($childId, [
            'parent_id'    => $parentId,
            'parent_port'  => $parentId ? self::cut($port, 30) : null,
            'local_port'   => $parentId ? self::cut($localPort, 30) : null,
            'link_type'    => isset(self::TYPES[$type]) ? $type : 'wired',
            'locked'       => 1,
            'note'         => self::cut($note, 255),
            'view_site_id' => $viewSiteId, // ook als je hem later loshaalt, blijft hij hier staan
        ]);
    }

    /** Terug naar wat UniFi/UISP zegt */
    public static function unlock(int $childId): void
    {
        $l = model(NetworkLinkModel::class)->where('network_device_id', $childId)->first();
        if ($l === null) {
            return;
        }
        model(NetworkLinkModel::class)->update($l['id'], [
            'locked'       => 0,
            'parent_id'    => $l['auto_parent_id'],
            'parent_port'  => $l['auto_parent_port'],
            'local_port'   => $l['auto_parent_id'] ? $l['local_port'] : null,
            'link_type'    => $l['auto_link_type'] ?? 'wired',
            'view_site_id' => null,
        ]);
    }

    /** Apparaat uit een andere site ook in deze topologie tonen (zijn eigen verbinding blijft) */
    public static function addToView(int $childId, int $viewSiteId): void
    {
        self::upsert($childId, ['view_site_id' => $viewSiteId]);
    }

    /** Soorten voor zelf toegevoegde apparaten */
    public const MANUAL_TYPES = [
        'switch' => 'Switch (unmanaged of ander merk)',
        'router' => 'Router / modem (provider)',
        'ap'     => 'Access point (ander merk)',
        'poe'    => 'PoE-injector / -splitter',
        'media'  => 'Mediaconverter / glas',
        'patch'  => 'Patchkast / wandcontactdoos',
        'server' => 'Server / NAS / pc',
        'other'  => 'Overig',
    ];

    /** Icoon per soort (zelf toegevoegd) */
    public const MANUAL_ICONS = [
        'switch' => 'diagram-3', 'router' => 'router', 'ap' => 'wifi', 'poe' => 'lightning-charge',
        'media'  => 'arrow-left-right', 'patch' => 'grid-3x3-gap', 'server' => 'pc-display', 'other' => 'box',
    ];

    /**
     * Zelf een apparaat toevoegen dat UniFi/UISP niet kent. Wordt niet bewaakt.
     *
     * @return int id van de nieuwe knoop
     */
    public static function addManual(int $viewSiteId, string $name, string $type, ?string $model, ?string $ip): int
    {
        $name = trim($name);
        if ($name === '') {
            $name = self::MANUAL_TYPES[$type] ?? 'Apparaat';
        }
        $id = (int) model(NetworkDeviceModel::class)->insert([
            'platform'        => 'manual',
            'external_id'     => 'manual:' . bin2hex(random_bytes(8)),
            'network_site_id' => $viewSiteId,
            'name'            => mb_substr($name, 0, 150),
            'model'           => self::cut($model, 100) ?? (self::MANUAL_TYPES[$type] ?? null),
            'kind'            => isset(self::MANUAL_TYPES[$type]) ? $type : 'other',
            'ip'              => self::cut($ip, 64),
            'status'          => 'unknown',
            'monitor'         => 0,
            'ignored'         => 1,
        ]);
        self::upsert($id, ['parent_id' => null, 'locked' => 1, 'view_site_id' => $viewSiteId]);

        return $id;
    }

    /** Naam/model/IP van een zelf toegevoegd apparaat aanpassen */
    public static function updateManual(int $id, ?string $name, ?string $model, ?string $ip): void
    {
        $nd = model(NetworkDeviceModel::class)->find($id);
        if ($nd === null || $nd['platform'] !== 'manual') {
            throw new RuntimeException('Alleen zelf toegevoegde apparaten kun je hier hernoemen.');
        }
        model(NetworkDeviceModel::class)->update($id, [
            'name'  => self::cut($name, 150) ?? $nd['name'],
            'model' => self::cut($model, 100),
            'ip'    => self::cut($ip, 64),
        ]);
    }

    /**
     * Apparaat uit de kluis als knoop (bv. Hikvision-camera of Ajax-hub).
     *
     * @return int id van de knoop
     */
    public static function addVault(int $viewSiteId, int $deviceId): int
    {
        $d = model(\App\Models\DeviceModel::class)->find($deviceId);
        if ($d === null) {
            throw new RuntimeException('Apparaat niet gevonden.');
        }
        $existing = model(NetworkDeviceModel::class)->where('device_id', $deviceId)->first();
        if ($existing !== null) {
            if ($existing['platform'] !== 'vault') {
                throw new RuntimeException('Dit apparaat staat al in ' . Network::platformLabel($existing['platform']) . ' en dus al in de topologie.');
            }
            self::addToView((int) $existing['id'], $viewSiteId);

            return (int) $existing['id'];
        }
        $id = (int) model(NetworkDeviceModel::class)->insert([
            'platform'        => 'vault',
            'external_id'     => 'device:' . $deviceId,
            'network_site_id' => $viewSiteId,
            'device_id'       => $deviceId,
            'name'            => mb_substr((string) $d['name'], 0, 150),
            'model'           => $d['model'],
            'kind'            => $d['type'],
            'ip'              => $d['ip'],
            'mac'             => Connector::normalizeMac($d['mac']),
            'status'          => 'unknown',
            'monitor'         => 0,
            'ignored'         => 1,
        ]);
        self::upsert($id, ['parent_id' => null, 'locked' => 1, 'view_site_id' => $viewSiteId]);

        return $id;
    }

    /**
     * Kluis-apparaten van de klant van deze site die nog niet in de topologie staan.
     *
     * @return list<array{id:int, name:string}>
     */
    public static function vaultOptions(int $viewSiteId): array
    {
        $ns  = model(NetworkSiteModel::class)->find($viewSiteId);
        $loc = $ns && $ns['site_id'] ? model(\App\Models\SiteModel::class)->find($ns['site_id']) : null;
        if ($loc === null) {
            return [];
        }
        $taken = array_flip(array_map('intval', array_column(model(NetworkDeviceModel::class)->select('device_id')
            ->where('device_id IS NOT NULL', null, false)->where('gone', 0)->findAll(), 'device_id')));
        $out = [];
        foreach (model(\App\Models\DeviceModel::class)->where('customer_id', $loc['customer_id'])
            ->whereNotIn('type', ['wifi', 'account'])->orderBy('name')->findAll() as $d) {
            if (isset($taken[(int) $d['id']])) {
                continue;
            }
            $out[] = ['id' => (int) $d['id'], 'name' => $d['name'] . ($d['model'] ? ' · ' . $d['model'] : '') . ($d['room'] ? ' · ' . $d['room'] : '')];
        }

        return $out;
    }

    /**
     * Zelf toegevoegd apparaat verwijderen, of een kluis-apparaat uit de topologie
     * halen. Wat eronder hing, komt bovenaan te staan (vast, in deze topologie).
     */
    public static function remove(int $id): void
    {
        $nd = model(NetworkDeviceModel::class)->find($id);
        if ($nd === null || ! in_array($nd['platform'], ['manual', 'vault'], true)) {
            throw new RuntimeException('Alleen zelf toegevoegde apparaten en apparaten uit de kluis kun je hier verwijderen.');
        }
        $view = $nd['network_site_id'];
        foreach (model(NetworkLinkModel::class)->where('parent_id', $id)->findAll() as $child) {
            model(NetworkLinkModel::class)->update($child['id'], ['parent_id' => null, 'parent_port' => null, 'locked' => 1, 'view_site_id' => $view]);
        }
        db_connect()->table('network_links')->where('network_device_id', $id)->delete();
        model(NetworkDeviceModel::class)->delete($id); // het kluis-apparaat zelf blijft gewoon bestaan
    }

    private static function upsert(int $childId, array $set): void
    {
        $m = model(NetworkLinkModel::class);
        $l = $m->where('network_device_id', $childId)->first();
        if ($l) {
            $m->update($l['id'], $set);
        } else {
            $m->insert($set + ['network_device_id' => $childId]);
        }
    }

    private static function cut(?string $v, int $n): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $n);
    }
}
