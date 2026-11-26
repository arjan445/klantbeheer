<?php

namespace App\Libraries\Network;

use App\Libraries\Network;
use Throwable;

/**
 * UniFi via de Site Manager API (https://api.ui.com, API-sleutel uit unifi.ui.com › API).
 *
 *  GET /v1/hosts    consoles (naam)
 *  GET /v1/sites    sites per console
 *  GET /v1/devices  apparaten per console, met MAC, IP, model, firmware en status
 *
 * De Site Manager zegt per apparaat alleen bij welke console het hoort. Heeft een
 * console één site (een UCG/UDM bij de klant), dan horen alle apparaten bij die site.
 * Heeft een console meerdere sites (een gehoste controller), dan wordt per site de
 * apparatenlijst opgehaald via de Cloud Connector: eerst de Network Integration API,
 * anders de oudere Network API. Dat gaat via de cloud naar de console en kan traag
 * zijn, dus:
 *  - elke aanvraag wordt bij een timeout nog twee keer geprobeerd;
 *  - een site die niet lukt, houdt de andere sites niet tegen;
 *  - eenmaal bekend blijft een apparaat bij zijn site, ook als het ophalen later mislukt;
 *  - de indeling wordt alleen opnieuw opgehaald als er onbekende apparaten zijn,
 *    of eens per 6 uur.
 */
class Unifi extends Connector
{
    /** Foutmelding van de eigen controller, voor in de meldingen */
    public ?string $directError = null;

    private string $apiKey;
    private string $base;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = (string) ($apiKey ?? Network::secret('unifi.apiKey'));
        $this->base   = rtrim((string) env('unifi.baseUrl', 'https://api.ui.com'), '/');
    }

    public function platform(): string
    {
        return 'unifi';
    }

    public function label(): string
    {
        return 'UniFi';
    }

    public function configured(): bool
    {
        return $this->apiKey !== '';
    }

    /** Hoe vaak de indeling per site opnieuw wordt opgehaald als alles al bekend is */
    private const REMAP_HOURS = 6;

    private function get(string $path, array $query = [], int $tries = 1, int $timeout = 30)
    {
        for ($i = 1; ; $i++) {
            try {
                return $this->getJson($this->base . $path, $query, ['X-API-Key' => $this->apiKey], $timeout);
            } catch (\RuntimeException $e) {
                $retry = in_array($e->getCode(), [0, 28, 408, 429, 500, 502, 503, 504], true) || stripos($e->getMessage(), 'timed out') !== false;
                if (! $retry || $i >= $tries) {
                    throw $e;
                }
                usleep($i * 1_500_000);
            }
        }
    }

    /** Alle pagina's van een Site Manager-lijst */
    private function all(string $path): array
    {
        $out   = [];
        $token = null;
        for ($i = 0; $i < 50; $i++) {
            $data = $this->get($path, ['pageSize' => '200'] + ($token ? ['nextToken' => $token] : []));
            array_push($out, ...($data['data'] ?? []));
            $token = $data['nextToken'] ?? null;
            if (! $token) {
                break;
            }
        }

        return $out;
    }

    public function fetch(): array
    {
        $notes = [];
        $known = $this->knownSites();

        // Consoles: naam
        $hostNames = [];
        foreach ($this->all('/v1/hosts') as $h) {
            $state = $h['reportedState'] ?? [];
            $hostNames[(string) $h['id']] = self::str($state['name'] ?? $state['hostname'] ?? null)
                ?? self::str($h['userData']['name'] ?? null) ?? self::str($h['ipAddress'] ?? null);
        }

        // Sites
        $sites      = [];
        $sitesByHost = [];
        foreach ($this->all('/v1/sites') as $s) {
            $hostId = (string) ($s['hostId'] ?? '');
            $meta   = $s['meta'] ?? [];
            $name   = self::str($meta['desc'] ?? null) ?? self::str($meta['name'] ?? null) ?? 'Site';
            if ($name === 'Default' && isset($hostNames[$hostId])) {
                $name = $hostNames[$hostId]; // "Default" zegt niets: naam van de console
            }
            $site = [
                'external_id' => (string) $s['siteId'],
                'name'        => $name,
                'description' => self::str($meta['name'] ?? null, 255), // interne naam, bv. "default"
                'host_id'     => $hostId,
                'host_name'   => $hostNames[$hostId] ?? null,
            ];
            $sites[]                = $site;
            $sitesByHost[$hostId][] = $site;
        }

        // Apparaten, per console
        $devices = [];
        $now     = date('Y-m-d H:i:s');
        foreach ($this->all('/v1/devices') as $group) {
            $hostId   = (string) ($group['hostId'] ?? '');
            $hostSites = $sitesByHost[$hostId] ?? [];
            $hostNames[$hostId] ??= self::str($group['hostName'] ?? null);

            // Welke site per MAC?
            $siteForMac = [];
            $fallback   = null;
            if (count($hostSites) === 1) {
                $fallback = $hostSites[0]['external_id'];
            } elseif (count($hostSites) > 1) {
                $siteIds = array_column($hostSites, 'external_id');
                // Wat we al wisten (alleen sites die nog bestaan)
                $siteForMac = array_filter($known, static fn ($sid) => in_array($sid, $siteIds, true));
                $macs       = array_filter(array_map(static fn ($d) => self::normalizeMac($d['mac'] ?? null), $group['devices'] ?? []));
                $unknown    = array_diff($macs, array_keys($siteForMac));
                $lastMap    = (int) Network::setting('unifi.sitemap.' . $hostId);
                if ($unknown !== [] || $lastMap < time() - self::REMAP_HOURS * 3600) {
                    [$fresh, $failed, $selfHosted] = $this->macsPerSite($hostId, $hostSites);
                    $siteForMac       = $fresh + $siteForMac; // nieuw gaat voor oud
                    if ($failed === []) {
                        \App\Libraries\Setting::set('unifi.sitemap.' . $hostId, (string) time());
                    }
                    $still = array_diff($macs, array_keys($siteForMac));
                    if ($failed !== [] && $still !== []) {
                        $console = 'Console ' . ($hostNames[$hostId] ?? $hostId);
                        if ($this->directError !== null) {
                            $notes[] = $console . ': ' . $this->directError;
                        } elseif ($selfHosted) {
                            $notes[] = $console . ' is een zelf gehoste controller: de UniFi-cloud weet niet bij welke site de apparaten horen. '
                                . 'Vul bij Beheer › Netwerk het adres en een lokaal account van deze controller in, dan worden ' . count($still) . ' apparaten automatisch per site ingedeeld.';
                        } else {
                            $notes[] = $console . ': ' . count($still) . ' apparaat/apparaten nog zonder site, want ophalen lukte niet voor '
                                . implode(', ', $failed) . '. Wordt bij de volgende synchronisatie opnieuw geprobeerd.';
                        }
                    }
                }
            }
            // Pseudo-site voor de console, alleen als er apparaten zonder bekende site zijn
            $pseudo     = 'host:' . $hostId;
            $needPseudo = false;

            foreach ($group['devices'] ?? [] as $d) {
                $mac    = self::normalizeMac($d['mac'] ?? null);
                $site   = ($mac !== null ? ($siteForMac[$mac] ?? null) : null) ?? $fallback;
                if ($site === null) {
                    $site       = $pseudo;
                    $needPseudo = true;
                }
                $raw    = self::str($d['status'] ?? null, 50);
                $status = self::mapStatus($raw);
                $devices[] = [
                    'external_id'      => (string) ($d['id'] ?? $mac ?? md5(json_encode($d))),
                    'site_external_id' => $site,
                    'name'             => self::str($d['name'] ?? null),
                    'model'            => self::str($d['model'] ?? null, 100) ?? self::str($d['shortname'] ?? null, 100),
                    'kind'             => self::str($d['productLine'] ?? null, 50),
                    'mac'              => $mac,
                    'serial'           => $mac !== null ? strtoupper($mac) : null, // bij UniFi is het serienummer het MAC-adres
                    'ip'               => self::cleanIp($d['ip'] ?? null),
                    'firmware'         => self::str($d['version'] ?? null, 100),
                    'raw_status'       => $raw,
                    'status'           => $status,
                    'last_seen_at'     => $status === 'online' ? $now : null,
                ];
            }
            if ($needPseudo) {
                $sites[] = [
                    'external_id' => $pseudo,
                    'name'        => ($hostNames[$hostId] ?? 'Console') . ($hostSites === [] ? '' : ' (site onbekend)'),
                    'description' => 'Apparaten van deze console zonder bekende site',
                    'host_id'     => $hostId,
                    'host_name'   => $hostNames[$hostId] ?? null,
                ];
            }
        }

        // Namen van consoles op de sites invullen (ook voor consoles die na de sites kwamen)
        foreach ($sites as &$s) {
            $s['host_name'] ??= $hostNames[$s['host_id']] ?? null;
        }
        unset($s);

        return ['sites' => $sites, 'devices' => $devices, 'notes' => $notes];
    }

    /**
     * Bekende indeling uit de database: MAC -> site-ID, voor apparaten die al eens
     * aan een echte site (niet de console zelf) zijn toegewezen.
     *
     * @return array<string,string>
     */
    private function knownSites(): array
    {
        $map = [];
        try {
            foreach (model(\App\Models\NetworkDeviceModel::class)->select('network_devices.mac, network_sites.external_id')
                ->join('network_sites', 'network_sites.id = network_devices.network_site_id')
                ->where('network_devices.platform', 'unifi')->where('network_devices.mac IS NOT NULL', null, false)
                ->notLike('network_sites.external_id', 'host:', 'after')->findAll() as $r) {
                $map[$r['mac']] = $r['external_id'];
            }
        } catch (Throwable $e) {
            // Geen database (bv. bij het testen van de verbinding): dan zonder
        }

        return $map;
    }

    /**
     * Voor een console met meerdere sites: MAC -> site-ID (van de Site Manager), via de
     * Cloud Connector. Per site eerst de Network Integration API, anders de oudere
     * Network API. Een site die niet lukt, slaat hij over.
     *
     * @param list<array> $hostSites
     * @return array{0: array<string,string>, 1: list<string>, 2: bool}  [indeling, mislukte sites, cloud kan deze console niet uitvragen]
     */
    private function macsPerSite(string $hostId, array $hostSites): array
    {
        $proxy = '/v1/connector/consoles/' . rawurlencode($hostId) . '/proxy/network';
        $map   = [];

        // 0. Eigen controller (zelf gehost): direct, op de interne sitenaam
        $direct = new UnifiController();
        if ($direct->configured()) {
            $byName = [];
            foreach ($hostSites as $hs) {
                if ($hs['description']) {
                    $byName[$hs['description']] = $hs;
                }
            }
            try {
                [$found] = $direct->macsBySite(array_keys($byName));
                foreach ($found as $name => $macs) {
                    foreach ($macs as $mac) {
                        $map[$mac] = $byName[$name]['external_id'];
                    }
                }
                // Gelukt: deze sites niet meer via de cloud proberen
                $hostSites = array_values(array_filter($hostSites, static fn ($hs) => ! isset($found[$hs['description']])));
                if ($hostSites === []) {
                    return [$map, [], false];
                }
            } catch (Throwable $e) {
                $this->directError = $e->getMessage();
            }
        }

        // Integration API: lijst met sites (id + interne naam)
        $integration = [];
        try {
            foreach ($this->get($proxy . '/integration/v1/sites', ['limit' => 200], 3, 40)['data'] ?? [] as $ls) {
                $integration[] = $ls;
            }
        } catch (Throwable $e) {
            log_message('info', 'UniFi ' . $hostId . ': integration-sites niet bereikbaar: ' . $e->getMessage());
            // "device is offline": de cloud kan deze console niet uitvragen (zelf gehoste controller)
            if ($e->getCode() === 404 && stripos($e->getMessage(), 'offline') !== false) {
                return [$map, array_column($hostSites, 'name'), true];
            }
        }

        $failed   = [];
        $deadline = microtime(true) + 120; // niet eindeloos wachten: de rest de volgende keer
        foreach ($hostSites as $hs) {
            if (microtime(true) > $deadline) {
                $failed[] = $hs['name'];
                continue;
            }
            $macs = null;

            // 1. Integration API
            $ls = null;
            foreach ($integration as $x) {
                if ((($x['internalReference'] ?? null) !== null && $x['internalReference'] === $hs['description'])
                    || mb_strtolower((string) ($x['name'] ?? '')) === mb_strtolower($hs['name'])) {
                    $ls = $x;
                    break;
                }
            }
            if ($ls !== null) {
                try {
                    $macs = [];
                    for ($offset = 0, $i = 0; $i < 20; $i++) {
                        $page = $this->get($proxy . '/integration/v1/sites/' . rawurlencode((string) $ls['id']) . '/devices', ['offset' => $offset, 'limit' => 100], 2, 40);
                        foreach ($page['data'] ?? [] as $d) {
                            if ($mac = self::normalizeMac($d['macAddress'] ?? null)) {
                                $macs[] = $mac;
                            }
                        }
                        $count  = count($page['data'] ?? []);
                        $offset += $count;
                        if ($count === 0 || $offset >= (int) ($page['totalCount'] ?? 0)) {
                            break;
                        }
                    }
                } catch (Throwable $e) {
                    $macs = null;
                }
            }

            // 2. Oudere Network API (op de interne sitenaam, bv. "default")
            if ($macs === null && $hs['description'] && microtime(true) < $deadline) {
                try {
                    $macs = [];
                    foreach ($this->get($proxy . '/api/s/' . rawurlencode($hs['description']) . '/stat/device-basic', [], 2, 40)['data'] ?? [] as $d) {
                        if ($mac = self::normalizeMac($d['mac'] ?? null)) {
                            $macs[] = $mac;
                        }
                    }
                } catch (Throwable $e) {
                    $macs = null;
                }
            }

            if ($macs === null) {
                $failed[] = $hs['name'];
                continue;
            }
            foreach ($macs as $mac) {
                $map[$mac] = $hs['external_id'];
            }
        }

        return [$map, $failed, false];
    }

    /**
     * Apparatenlijst met uplinks van één site via de Cloud Connector (oudere Network API).
     * Werkt alleen bij consoles met UniFi OS; een zelf gehoste controller loopt via
     * UnifiController.
     *
     * @return list<array>
     */
    public function legacyDevices(string $hostId, string $siteName): array
    {
        $path = '/v1/connector/consoles/' . rawurlencode($hostId) . '/proxy/network/api/s/' . rawurlencode($siteName) . '/stat/device';

        return $this->get($path, [], 2, 40)['data'] ?? [];
    }

    /**
     * Uplink van één apparaat uit een stat/device-regel.
     *
     * @return array{parent_mac:?string, parent_port:?string, local_port:?string, type:string}|null
     */
    public static function uplinkOf(array $d): ?array
    {
        $u = is_array($d['uplink'] ?? null) ? $d['uplink'] : [];
        if (empty($u['uplink_mac']) && is_array($d['last_uplink'] ?? null)) {
            $u = $d['last_uplink'] + $u; // offline: laatst bekende uplink
        }
        $mac = self::normalizeMac($u['uplink_mac'] ?? null);
        if ($mac === null) {
            return null;
        }
        $wireless = ($u['type'] ?? '') === 'wireless' || ! empty($u['ap_mac']);
        $port     = $u['uplink_remote_port'] ?? null;

        return [
            'parent_mac'  => $mac,
            'parent_port' => $wireless || $port === null || $port === '' ? null : (string) $port,
            'local_port'  => isset($u['port_idx']) && ! $wireless ? (string) $u['port_idx'] : null,
            'type'        => $wireless ? 'wireless' : 'wired',
        ];
    }

    public static function mapStatus(?string $raw): string
    {
        $raw = strtolower((string) $raw);

        return match (true) {
            $raw === 'offline', $raw === 'disconnected', $raw === 'isolated' => 'offline',
            in_array($raw, ['online', 'connected', 'updating', 'upgrading', 'provisioning', 'gettingready', 'getting_ready', 'adopting'], true) => 'online',
            default => 'unknown',
        };
    }
}
