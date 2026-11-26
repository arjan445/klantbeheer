<?php

namespace App\Libraries\Network;

use App\Libraries\Network;
use RuntimeException;

/**
 * Directe verbinding met je eigen UniFi Network-controller (zelf gehost, bv. op een
 * VPS, of een UniFi OS-console), met een lokaal account.
 *
 * Nodig voor een zelf gehoste controller met meerdere sites: de UniFi-cloud
 * (Site Manager) toont de apparaten wel, maar kan zo'n controller niet per site
 * uitvragen ("device is offline"). Deze verbinding wordt alleen gebruikt om te
 * zien welk apparaat bij welke site hoort.
 *
 * Gebruik een apart lokaal account met alleen-lezen-rechten en zonder
 * tweestapsverificatie.
 */
class UnifiController
{
    private string $url;
    private string $user;
    private string $pass;
    private bool $verify;

    /** @var \CurlHandle|null */
    private $ch = null;
    private string $prefix = '';
    private ?string $csrf  = null;

    public function __construct(?string $url = null, ?string $user = null, ?string $pass = null, ?bool $verify = null)
    {
        $url = trim((string) ($url ?? Network::setting('unifi.controllerUrl')));
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        // "/manage/…" of "/network/…" uit de adresbalk mag erachter staan
        $this->url    = rtrim(preg_replace('#/(manage|network)(/.*)?$#i', '', rtrim($url, '/')), '/');
        $this->user   = (string) ($user ?? Network::setting('unifi.controllerUser'));
        $this->pass   = (string) ($pass ?? Network::secret('unifi.controllerPass'));
        $this->verify = $verify ?? (Network::setting('unifi.controllerInsecure') !== '1');
    }

    public function configured(): bool
    {
        return $this->url !== '' && $this->user !== '' && $this->pass !== '';
    }

    /**
     * MAC-adressen per interne sitenaam ("default", "abc123de").
     *
     * @param list<string> $siteNames
     * @return array{0: array<string, list<string>>, 1: list<string>}  [naam => macs, mislukte namen]
     */
    public function macsBySite(array $siteNames): array
    {
        $this->login();
        $out    = [];
        $failed = [];
        foreach ($siteNames as $name) {
            try {
                $data = $this->request('GET', '/api/s/' . rawurlencode($name) . '/stat/device-basic');
                $macs = [];
                foreach ($data['data'] ?? [] as $d) {
                    if ($m = Connector::normalizeMac($d['mac'] ?? null)) {
                        $macs[] = $m;
                    }
                }
                $out[$name] = $macs;
            } catch (RuntimeException $e) {
                $failed[] = $name;
            }
        }

        return [$out, $failed];
    }

    /**
     * Volledige apparatenlijst van één site (met uplink-gegevens voor de topologie).
     *
     * @return list<array>
     */
    public function devices(string $siteName): array
    {
        $this->login();

        return $this->request('GET', '/api/s/' . rawurlencode($siteName) . '/stat/device')['data'] ?? [];
    }

    /**
     * Test: inloggen en de sites tellen.
     *
     * @return list<array{name:string, desc:string}>
     */
    public function sites(): array
    {
        $this->login();
        $out = [];
        foreach ($this->request('GET', '/api/self/sites')['data'] ?? [] as $s) {
            $out[] = ['name' => (string) ($s['name'] ?? ''), 'desc' => (string) ($s['desc'] ?? $s['name'] ?? '')];
        }

        return $out;
    }

    private function login(): void
    {
        if ($this->ch !== null) {
            return;
        }
        if (! $this->configured()) {
            throw new RuntimeException('Eigen controller is niet ingesteld.');
        }
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_COOKIEFILE     => '', // cookies in het geheugen, alleen voor deze verbinding
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->verify,
            CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
        ]);
        $creds = ['username' => $this->user, 'password' => $this->pass, 'remember' => false];

        // Zelf gehoste Network-applicatie (meestal poort 8443)
        [$status] = $this->raw('POST', '/api/login', $creds);
        if ($status === 200) {
            $this->prefix = '';

            return;
        }
        // UniFi OS (Cloud Key Gen2, UDM, UniFi OS Server)
        [$status, , $headers] = $this->raw('POST', '/api/auth/login', $creds);
        if ($status === 200) {
            $this->prefix = '/proxy/network';
            $this->csrf   = $headers['x-csrf-token'] ?? $headers['x-updated-csrf-token'] ?? null;

            return;
        }
        $this->close();

        throw new RuntimeException(match (true) {
            $status === 0               => 'Eigen controller niet bereikbaar op ' . $this->url . '. Klopt het adres (met poort, bv. :8443)?',
            in_array($status, [400, 401, 403], true) => 'Inloggen op de eigen controller mislukt: gebruikersnaam of wachtwoord klopt niet, of tweestapsverificatie staat aan.',
            $status === 429             => 'Eigen controller: te vaak geprobeerd in te loggen, probeer het later.',
            default                     => 'Inloggen op de eigen controller mislukt (' . $status . ').',
        });
    }

    private function request(string $method, string $path): array
    {
        [$status, $body] = $this->raw($method, $this->prefix . $path);
        if ($status !== 200) {
            throw new RuntimeException('Eigen controller gaf ' . $status . ' op ' . $path);
        }
        $data = json_decode($body, true);
        if (! is_array($data)) {
            throw new RuntimeException('Eigen controller gaf geen geldige JSON terug.');
        }

        return $data;
    }

    /** @return array{0:int, 1:string, 2:array<string,string>} */
    private function raw(string $method, string $path, ?array $json = null): array
    {
        $headers = ['Accept: application/json'];
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        if ($this->csrf) {
            $headers[] = 'X-CSRF-Token: ' . $this->csrf;
        }
        curl_setopt($this->ch, CURLOPT_URL, $this->url . $path);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        if ($json !== null) {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($json));
        } else {
            curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        }
        $resp = curl_exec($this->ch);
        if ($resp === false) {
            return [0, curl_error($this->ch), []];
        }
        $status = (int) curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        $size   = (int) curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        $hdrs   = [];
        foreach (explode("\r\n", substr($resp, 0, $size)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v]               = explode(':', $line, 2);
                $hdrs[strtolower(trim($k))] = trim($v);
            }
        }

        return [$status, substr($resp, $size), $hdrs];
    }

    public function close(): void
    {
        if ($this->ch !== null) {
            curl_close($this->ch);
            $this->ch = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
