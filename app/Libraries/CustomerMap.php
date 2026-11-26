<?php

namespace App\Libraries;

use App\Models\CustomerModel;
use App\Models\NetworkDeviceModel;
use App\Models\NetworkSiteModel;
use App\Models\SiteModel;
use Throwable;

/**
 * Klantenkaart: elke locatie van een klant (of het klantadres, als er geen
 * locaties zijn) als stip, gekleurd naar de netwerkstatus:
 *  - down: er is een bewaakt apparaat offline
 *  - warn: SD-WAN/VPN deels of niet verbonden
 *  - ok:   bewaakt, alles online
 *  - none: niets bewaakt
 *
 * Adressen worden omgezet met de PDOK Locatieserver (gratis, geen sleutel, NL) en
 * anders met OpenRouteService (als er voor de kilometers een sleutel is). Het
 * resultaat blijft bewaard zolang het adres gelijk is; een adres dat niet te
 * vinden is, wordt pas opnieuw geprobeerd als het adres verandert.
 */
class CustomerMap
{
    public const LEVELS = ['down' => 3, 'warn' => 2, 'ok' => 1, 'none' => 0];

    // ------------------------------------------------------------------ Geocoderen

    public static function address(array $row): string
    {
        return trim(implode(', ', array_filter([
            trim((string) ($row['address'] ?? '')),
            trim(trim((string) ($row['zipcode'] ?? '')) . ' ' . trim((string) ($row['city'] ?? ''))),
        ])), ', ');
    }

    /** @return array{0:float,1:float}|null [lat, lng] */
    public static function geocode(string $q): ?array
    {
        try {
            if ($c = self::pdok($q)) {
                return $c;
            }
        } catch (Throwable $e) {
            log_message('info', 'Klantenkaart PDOK: ' . $e->getMessage());
        }
        if (Mileage::apiKey() !== '') {
            try {
                [$lng, $lat] = Mileage::geocode($q);

                return [$lat, $lng];
            } catch (Throwable $e) {
                log_message('info', 'Klantenkaart ORS: ' . $e->getMessage());
            }
        }

        return null;
    }

    /** PDOK Locatieserver: adressen en postcodes in Nederland */
    private static function pdok(string $q): ?array
    {
        $base = rtrim((string) env('pdok.baseURL', 'https://api.pdok.nl'), '/');
        $res  = \Config\Services::curlrequest([], null, null, false)->get($base . '/bzk/locatieserver/search/v3_1/free', [
            'query'       => ['q' => $q, 'rows' => 1, 'fl' => 'centroide_ll,weergavenaam,type'],
            'headers'     => ['Accept' => 'application/json'],
            'http_errors' => false,
            'timeout'     => 10,
        ]);
        if ($res->getStatusCode() >= 400) {
            throw new \RuntimeException('HTTP ' . $res->getStatusCode());
        }
        $doc = json_decode((string) $res->getBody(), true)['response']['docs'][0] ?? null;
        if (! $doc || ! preg_match('/POINT\(([-\d.]+) ([-\d.]+)\)/', (string) ($doc['centroide_ll'] ?? ''), $m)) {
            return null;
        }

        return [(float) $m[2], (float) $m[1]];
    }

    /**
     * Ontbrekende coördinaten aanvullen (locaties en klanten zonder locatie).
     *
     * @return array{done:int, failed:int, left:int}
     */
    public static function fill(int $max = 15, float $seconds = 20): array
    {
        $deadline = microtime(true) + $seconds;
        $done     = $failed = 0;
        $todo     = self::missing();
        foreach ($todo as $i => [$table, $row, $q]) {
            if ($i >= $max || microtime(true) > $deadline) {
                break;
            }
            $c   = self::geocode($q);
            $set = $c ? ['lat' => round($c[0], 7), 'lng' => round($c[1], 7), 'geo_query' => mb_substr($q, 0, 255)]
                      : ['lat' => null, 'lng' => null, 'geo_query' => mb_substr('!' . $q, 0, 255)];
            db_connect()->table($table)->where('id', $row['id'])->update($set);
            $c ? $done++ : $failed++;
        }

        return ['done' => $done, 'failed' => $failed, 'left' => max(0, count($todo) - $done - $failed)];
    }

    /**
     * Wat nog omgezet moet worden: adres bekend, maar (nog) geen coördinaten voor dít adres.
     *
     * @return list<array{0:string,1:array,2:string}>
     */
    public static function missing(): array
    {
        $out  = [];
        $need = static function (array $r) {
            $q = self::address($r);

            return $q !== '' && ! (($r['lat'] !== null && $r['geo_query'] === $q) || $r['geo_query'] === mb_substr('!' . $q, 0, 255)) ? $q : null;
        };
        foreach (model(SiteModel::class)->select('sites.id, sites.customer_id, sites.address, sites.zipcode, sites.city, sites.lat, sites.geo_query')
            ->join('customers', 'customers.id = sites.customer_id')->where('customers.active', 1)->findAll() as $s) {
            if ($q = $need($s)) {
                $out[] = ['sites', $s, $q];
            }
        }
        // Klantadres ook: valt terug als geen locatie op de kaart staat
        foreach (model(CustomerModel::class)->select('id, address, zipcode, city, lat, geo_query')->where('active', 1)->findAll() as $c) {
            if ($q = $need($c)) {
                $out[] = ['customers', $c, $q];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ Punten

    /**
     * Alle punten voor de kaart.
     *
     * @return array{points:list<array>, noAddress:int, pending:int, notFound:list<array>, counts:array<string,int>}
     */
    public static function points(): array
    {
        $customers = [];
        foreach (model(CustomerModel::class)->select('id, name, address, zipcode, city, lat, lng, geo_query, internal')->where('active', 1)->findAll() as $c) {
            $customers[(int) $c['id']] = $c;
        }
        $sites = model(SiteModel::class)->select('id, customer_id, name, address, zipcode, city, lat, lng, geo_query')
            ->whereIn('customer_id', array_keys($customers) ?: [0])->orderBy('name')->findAll();

        // Netwerksites per locatie (voor topologie-links en VPN)
        $nsites = [];
        foreach (model(NetworkSiteModel::class)->select('id, name, platform, site_id, vpn')->where('ignored', 0)
            ->where('site_id IS NOT NULL', null, false)->findAll() as $n) {
            $nsites[(int) $n['site_id']][] = $n;
        }

        // Bewaakte apparaten met hun locatie (of alleen klant)
        $byLoc  = [];
        $byCust = [];
        if (Network::configured()) {
            foreach (model(NetworkDeviceModel::class)->withContext()
                ->whereIn('network_devices.platform', Network::MONITORED)
                ->where('network_devices.monitor', 1)->where('network_devices.ignored', 0)->where('network_devices.gone', 0)
                ->groupStart()->where('network_sites.ignored', 0)->orWhere('network_sites.id IS NULL', null, false)->groupEnd()
                ->findAll() as $d) {
                if (! $d['cust_id']) {
                    continue;
                }
                $row = [
                    'name'   => $d['device_name'] ?: ($d['name'] ?: ($d['model'] ?: Network::formatMac($d['mac']))),
                    'status' => $d['status'],
                    'since'  => $d['offline_since'],
                ];
                if ($d['loc_id']) {
                    $byLoc[(int) $d['loc_id']][] = $row;
                } else {
                    $byCust[(int) $d['cust_id']][] = $row;
                }
            }
        }

        $points    = [];
        $noAddress = 0;
        $pending   = 0;
        $notFound  = [];
        // geen adres / nog niet gezocht / niet gevonden
        $geo = static function (array $r): string {
            $q = self::address($r);
            if ($q === '') {
                return 'none';
            }
            if ($r['geo_query'] === mb_substr('!' . $q, 0, 255)) {
                return 'failed';
            }

            return $r['lat'] !== null && $r['geo_query'] === $q ? 'ok' : 'pending';
        };
        $placed    = [];
        $make      = static function (array $c, ?array $site, array $devs, array $ns) {
            $off = array_values(array_filter($devs, static fn ($d) => $d['status'] === 'offline'));
            usort($off, static fn ($a, $b) => strcmp((string) $a['since'], (string) $b['since']));
            $vpnBad = [];
            foreach ($ns as $n) {
                foreach (Network::vpn($n) as $v) {
                    if (in_array($v['state'], ['down', 'partial', 'error'], true)) {
                        $vpnBad[] = $v['name'] . ': ' . Network::vpnState($v['state'])[1] . ($v['total'] ? ' (' . $v['up'] . '/' . $v['total'] . ')' : '');
                    }
                }
            }
            $level = $off !== [] ? 'down' : ($vpnBad !== [] ? 'warn' : ($devs !== [] ? 'ok' : 'none'));
            $src   = $site ?? $c;

            return [
                'lat'      => (float) $src['lat'],
                'lng'      => (float) $src['lng'],
                'level'    => $level,
                'customer' => $c['name'],
                'cid'      => (int) $c['id'],
                'site'     => $site['name'] ?? null,
                'address'  => self::address($src),
                'devices'  => count($devs),
                'offline'  => array_map(static fn ($d) => ['name' => $d['name'], 'since' => $d['since'] ? Network::ago($d['since']) : null], array_slice($off, 0, 8)),
                'offCount' => count($off),
                'vpn'      => $vpnBad,
                'topo'     => array_map(static fn ($n) => ['id' => (int) $n['id'], 'name' => Network::platformLabel($n['platform']) . ' · ' . $n['name']], $ns),
            ];
        };

        $unplaced = []; // locaties die niet op de kaart staan, per klant
        foreach ($sites as $s) {
            $cid = (int) $s['customer_id'];
            $placed[$cid] ??= false;
            $st = $geo($s);
            if ($st !== 'ok') {
                $unplaced[$cid][] = $s;
                if ($st === 'pending') {
                    $pending++;
                } elseif ($st === 'failed') {
                    $notFound[] = ['cid' => $cid, 'name' => $customers[$cid]['name'] . ' · ' . $s['name']];
                }
                continue;
            }
            $devs = $byLoc[(int) $s['id']] ?? [];
            // Apparaten zonder locatie: bij de eerste locatie op de kaart
            if (! $placed[$cid]) {
                $devs = array_merge($devs, $byCust[$cid] ?? []);
            }
            $placed[$cid] = true;
            $points[]     = $make($customers[$cid], $s, $devs, $nsites[(int) $s['id']] ?? []);
        }
        // Klanten waarvan geen locatie op de kaart staat: het klantadres, met alles erop
        foreach ($customers as $cid => $c) {
            if (! empty($placed[$cid])) {
                continue;
            }
            $st = $geo($c);
            if ($st !== 'ok') {
                if (! isset($placed[$cid]) || ($unplaced[$cid] ?? []) !== []) {
                    if ($st === 'pending') {
                        $pending++;
                    } elseif ($st === 'failed') {
                        $notFound[] = ['cid' => $cid, 'name' => $c['name']];
                    } elseif (! isset($placed[$cid])) {
                        $noAddress++;
                    }
                }
                continue;
            }
            $devs = $byCust[$cid] ?? [];
            $ns   = [];
            foreach ($unplaced[$cid] ?? [] as $s) {
                $devs = array_merge($devs, $byLoc[(int) $s['id']] ?? []);
                $ns   = array_merge($ns, $nsites[(int) $s['id']] ?? []);
            }
            $points[] = $make($c, null, $devs, $ns);
        }

        // Ergste bovenop
        usort($points, static fn ($a, $b) => self::LEVELS[$a['level']] <=> self::LEVELS[$b['level']]);
        $counts = array_fill_keys(array_keys(self::LEVELS), 0);
        foreach ($points as $p) {
            $counts[$p['level']]++;
        }

        return ['points' => $points, 'noAddress' => $noAddress, 'pending' => $pending, 'notFound' => $notFound, 'counts' => $counts];
    }
}
