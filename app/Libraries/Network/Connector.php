<?php

namespace App\Libraries\Network;

use RuntimeException;

/**
 * Eén netwerkplatform (UniFi, UISP, later bv. Hik-Connect of Ajax).
 * Elk platform levert sites en apparaten in hetzelfde formaat aan, zodat
 * de synchronisatie, de koppelingen en het offline-overzicht voor alle
 * platforms hetzelfde werken.
 */
abstract class Connector
{
    /** Sleutel: unifi | uisp */
    abstract public function platform(): string;

    /** Naam voor op het scherm */
    abstract public function label(): string;

    abstract public function configured(): bool;

    /**
     * @return array{
     *   sites: list<array{external_id:string, name:string, description:?string, host_id:?string, host_name:?string}>,
     *   devices: list<array{external_id:string, site_external_id:?string, name:?string, model:?string, kind:?string,
     *                       mac:?string, serial:?string, ip:?string, firmware:?string, raw_status:?string,
     *                       status:string, last_seen_at:?string}>,
     *   notes: list<string>
     * }
     */
    abstract public function fetch(): array;

    /**
     * GET-verzoek met JSON-antwoord.
     *
     * @param array<string,string> $headers
     */
    protected function getJson(string $url, array $query, array $headers, int $timeout = 30)
    {
        $response = \Config\Services::curlrequest([], null, null, false)->request('GET', $url, [
            'headers'     => $headers + ['Accept' => 'application/json'],
            'query'       => $query,
            'http_errors' => false,
            'timeout'     => $timeout,
        ]);

        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();
        $data   = json_decode($body, true);

        if ($status >= 400) {
            $msg = '';
            if (is_array($data)) {
                $msg = (string) ($data['message'] ?? $data['error'] ?? $data['detail'] ?? '');
            }
            if ($status === 408 || $status === 504) {
                $msg = 'de console reageerde niet op tijd';
            }
            if ($status === 401 || $status === 403) {
                $msg = 'sleutel ongeldig of zonder rechten' . ($msg !== '' ? ' (' . $msg . ')' : '');
            }

            throw new RuntimeException($this->label() . ' gaf een fout (' . $status . ')' . ($msg !== '' ? ': ' . $msg : '') . '.', $status);
        }
        if ($data === null && trim($body) !== '' && trim($body) !== 'null') {
            throw new RuntimeException($this->label() . ' gaf geen geldige JSON terug. Klopt het adres?');
        }

        return $data;
    }

    /** 'AA:BB:CC:DD:EE:FF' / 'aabb.ccdd.eeff' / 'AABBCCDDEEFF' -> 'aabbccddeeff' (of null) */
    public static function normalizeMac(?string $mac): ?string
    {
        $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', (string) $mac));

        return strlen($hex) === 12 ? $hex : null;
    }

    /** '192.168.1.20/24' -> '192.168.1.20' */
    protected static function cleanIp($ip): ?string
    {
        if (! is_string($ip) || trim($ip) === '') {
            return null;
        }
        $ip = trim(explode('/', $ip)[0]);

        return mb_substr($ip, 0, 64);
    }

    /** ISO-tijd -> 'Y-m-d H:i:s' in de tijdzone van de app */
    protected static function toDateTime($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    protected static function str($value, int $max = 150): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
