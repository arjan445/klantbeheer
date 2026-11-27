<?php

namespace App\Libraries\Network;

use App\Libraries\Network;
use RuntimeException;

/**
 * UISP via de NMS API van je eigen controller (https://<uisp>/nms/api/v2.1).
 * API-token maken in UISP: Settings › Users › API tokens (alleen lezen is genoeg).
 *
 *  GET /sites    sites en endpoints (klantlocaties)
 *  GET /devices  apparaten met MAC, IP, model, firmware, site en status
 */
class Uisp extends Connector
{
    private string $url;
    private string $token;

    public function __construct(?string $url = null, ?string $token = null)
    {
        $url = trim((string) ($url ?? Network::setting('uisp.url')));
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        // "/nms" of "/nms/api/v2.1" achter het adres mag, maar is niet nodig
        $this->url   = rtrim(preg_replace('#/nms(/api(/v[\d.]+)?)?/?$#i', '', rtrim($url, '/')), '/');
        $this->token = (string) ($token ?? Network::secret('uisp.token'));
    }

    public function platform(): string
    {
        return 'uisp';
    }

    public function label(): string
    {
        return 'UISP';
    }

    public function configured(): bool
    {
        return $this->url !== '' && $this->token !== '';
    }

    private function get(string $path, array $query = [])
    {
        if (! $this->configured()) {
            throw new RuntimeException('UISP is niet ingesteld.');
        }

        return $this->getJson($this->url . '/nms/api/v2.1' . $path, $query, ['x-auth-token' => $this->token], 45);
    }

    public function fetch(): array
    {
        $sites = [];
        foreach ((array) $this->get('/sites') as $s) {
            $ident = $s['identification'] ?? [];
            $type  = (string) ($ident['type'] ?? '');
            $desc  = $s['description'] ?? [];
            $address = is_array($desc['address'] ?? null) ? ($desc['address']['formatted'] ?? null) : ($desc['address'] ?? null);
            $sites[] = [
                'external_id' => (string) ($s['id'] ?? $ident['id'] ?? ''),
                'name'        => self::str($ident['name'] ?? null) ?? 'Site',
                'description' => self::str(implode(' · ', array_filter([$type === 'endpoint' ? 'Klant-endpoint' : ($type === 'site' ? 'Site' : $type), self::str($ident['parent']['name'] ?? null), self::str($address, 150)])), 255),
                'host_id'     => null,
                'host_name'   => null,
            ];
        }
        $sites = array_values(array_filter($sites, static fn ($s) => $s['external_id'] !== ''));

        $devices = [];
        foreach ((array) $this->get('/devices') as $d) {
            $ident = $d['identification'] ?? [];
            $over  = $d['overview'] ?? [];
            $id    = (string) ($ident['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $raw    = self::str($over['status'] ?? null, 50);
            $status = self::mapStatus($raw);
            $mac    = self::normalizeMac($ident['mac'] ?? null);
            $devices[] = [
                'external_id'      => $id,
                'site_external_id' => isset($ident['site']['id']) ? (string) $ident['site']['id'] : null,
                'name'             => self::str($ident['name'] ?? $ident['hostname'] ?? null),
                'model'            => self::str($ident['modelName'] ?? $ident['model'] ?? null, 100),
                'kind'             => self::str($ident['category'] ?? $ident['type'] ?? null, 50),
                'mac'              => $mac,
                'serial'           => self::str($ident['serialNumber'] ?? null, 100),
                'ip'               => self::cleanIp($d['ipAddress'] ?? ($over['ipAddress'] ?? null)),
                'firmware'         => self::str($ident['firmwareVersion'] ?? null, 100),
                'raw_status'       => $raw,
                'status'           => $status,
                'last_seen_at'     => self::toDateTime($over['lastSeen'] ?? null) ?? ($status === 'online' ? date('Y-m-d H:i:s') : null),
                // Topologie: een station hangt draadloos aan zijn AP
                'uplink'           => self::uplink($d),
            ] + self::radio($d);
        }

        return ['sites' => $sites, 'devices' => $devices, 'notes' => []];
    }

    /**
     * Signaal van een draadloos station (of PTP-zijde): dBm en korte radio-info.
     * Alleen voor apparaten die draadloos aan een AP hangen; anders leeg.
     *
     * @return array{signal:?int, radio:?string}
     */
    public static function radio(array $d): array
    {
        $o      = is_array($d['overview'] ?? null) ? $d['overview'] : [];
        // Alleen actuele waarden: een losgevallen station geeft verouderde cijfers
        $isSta  = ! empty($d['attributes']['apDevice']['id'] ?? null) && strtolower((string) ($o['status'] ?? '')) === 'active';
        $signal = is_numeric($o['signal'] ?? null) ? (int) round((float) $o['signal']) : null;
        if (! $isSta || $signal === null || $signal >= 0 || $signal < -110) {
            return ['signal' => null, 'radio' => null];
        }
        $mbit  = static fn ($bps) => is_numeric($bps) && $bps > 0 ? (int) round($bps / 1e6) : null;
        $down  = $mbit($o['downlinkCapacity'] ?? null);
        $up    = $mbit($o['uplinkCapacity'] ?? null);
        $freq  = is_numeric($o['frequency'] ?? null) ? (float) $o['frequency'] : null; // MHz
        $parts = array_filter([
            $freq ? rtrim(rtrim(number_format($freq / 1000, 2, ',', ''), '0'), ',') . ' GHz' : null,
            is_numeric($o['channelWidth'] ?? null) ? (int) $o['channelWidth'] . ' MHz' : null,
            $down || $up ? '↓ ' . ($down ?? '?') . ' / ↑ ' . ($up ?? '?') . ' Mbit/s' : null,
        ]);

        return ['signal' => $signal, 'radio' => $parts ? mb_substr(implode(' · ', $parts), 0, 150) : null];
    }

    /** Bovenliggend apparaat volgens UISP (station -> AP), of null */
    private static function uplink(array $d): ?array
    {
        $attr = is_array($d['attributes'] ?? null) ? $d['attributes'] : [];
        $ap   = $attr['apDevice']['id'] ?? null;
        if (is_string($ap) && $ap !== '') {
            return ['parent_external_id' => $ap, 'parent_mac' => null, 'parent_port' => null, 'local_port' => null, 'type' => 'wireless'];
        }
        $parent = $attr['parentId'] ?? null;
        if (is_string($parent) && $parent !== '') {
            return ['parent_external_id' => $parent, 'parent_mac' => null, 'parent_port' => null, 'local_port' => null, 'type' => 'wired'];
        }

        return null;
    }

    public static function mapStatus(?string $raw): string
    {
        $raw = strtolower((string) $raw);

        return match (true) {
            $raw === 'active' => 'online',
            in_array($raw, ['disconnected', 'inactive', 'unreachable'], true) => 'offline',
            default => 'unknown', // unauthorized, proposed, disabled, connecting, discovered, ...
        };
    }
}
