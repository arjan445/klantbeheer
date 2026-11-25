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
 * apparatenlijst opgehaald via de Cloud Connector (Network Integration API). Lukt dat
 * niet, dan komen de apparaten onder de console zelf en koppel je ze met de hand.
 */
class Unifi extends Connector
{
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

    private function get(string $path, array $query = [])
    {
        return $this->getJson($this->base . $path, $query, ['X-API-Key' => $this->apiKey]);
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
                try {
                    $siteForMac = $this->macsPerSite($hostId, $hostSites);
                } catch (Throwable $e) {
                    $notes[] = 'Console ' . ($hostNames[$hostId] ?? $hostId) . ' heeft ' . count($hostSites) . ' sites; apparaten per site ophalen lukte niet (' . $e->getMessage() . '). Koppel ze met de hand.';
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
     * Voor een console met meerdere sites: MAC -> site-ID (van de Site Manager),
     * via de Network Integration API achter de Cloud Connector.
     *
     * @param list<array> $hostSites
     * @return array<string,string>
     */
    private function macsPerSite(string $hostId, array $hostSites): array
    {
        $prefix = '/v1/connector/consoles/' . rawurlencode($hostId) . '/proxy/network/integration/v1';
        $local  = $this->get($prefix . '/sites', ['limit' => 200]);

        $map = [];
        foreach ($local['data'] ?? [] as $ls) {
            // Integration-site koppelen aan Site Manager-site: interne naam ("default") of weergavenaam
            $target = null;
            foreach ($hostSites as $hs) {
                if (($ls['internalReference'] ?? null) !== null && $ls['internalReference'] === $hs['description']) {
                    $target = $hs['external_id'];
                    break;
                }
            }
            if ($target === null) {
                foreach ($hostSites as $hs) {
                    if (mb_strtolower((string) ($ls['name'] ?? '')) === mb_strtolower($hs['name'])) {
                        $target = $hs['external_id'];
                        break;
                    }
                }
            }
            if ($target === null) {
                continue;
            }
            for ($offset = 0, $i = 0; $i < 20; $i++) {
                $page = $this->get($prefix . '/sites/' . rawurlencode((string) $ls['id']) . '/devices', ['offset' => $offset, 'limit' => 200]);
                foreach ($page['data'] ?? [] as $d) {
                    if ($mac = self::normalizeMac($d['macAddress'] ?? null)) {
                        $map[$mac] = $target;
                    }
                }
                $count  = count($page['data'] ?? []);
                $offset += $count;
                if ($count === 0 || $offset >= (int) ($page['totalCount'] ?? 0)) {
                    break;
                }
            }
        }

        return $map;
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
