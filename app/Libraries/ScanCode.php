<?php

namespace App\Libraries;

use App\Libraries\Network\Connector;

/**
 * Leest een gescande barcode/QR-code van een doos of apparaatlabel uit:
 * MAC-adres, serienummer, Device-ID, typenummer en het waarschijnlijke merk.
 *
 *  - UniFi / UISP: MAC-adres (= serienummer bij UniFi), met of zonder dubbele punten
 *  - Hikvision:    lang serienummer "DS-2CD2347G2-LU20230115AAWRL12345678" (typenummer +
 *                  datum + 9 tekens), of de Hik-Connect-QR met serienummer en verificatiecode
 *  - Ajax:         Device-ID van 6 of 8 hexadecimale tekens
 *  - EAN/UPC:      productcode, geen serienummer
 */
class ScanCode
{
    /**
     * @return array{raw:string, mac:?string, serial:?string, serial_full:?string, device_id:?string,
     *               model:?string, verification:?string, ean:?string, brand:?string, tokens:list<string>}
     */
    public static function parse(string $raw): array
    {
        $raw = trim(str_replace("\0", '', $raw));
        $out = ['raw' => mb_substr($raw, 0, 500), 'mac' => null, 'serial' => null, 'serial_full' => null, 'device_id' => null,
            'model' => null, 'verification' => null, 'ean' => null, 'brand' => null, 'tokens' => []];

        // Losse stukken: regels, spaties, ; , |  — en voorvoegsels als "S/N:" of "MAC:" eraf
        $tokens = [];
        foreach (preg_split('/[\r\n\t;,|]+|\s{2,}|\s(?=[A-Z\/]{2,5}\s*[:=])/u', $raw) as $part) {
            $part = trim($part);
            $part = preg_replace('/^(s\/?n|serial(\s*no\.?)?|sn|mac(\s*address)?|id|device\s*id|vc|verification\s*code|model|p\/?n)\s*[:=#]?\s*/i', '', $part);
            foreach (preg_split('/\s+/', $part) as $t) {
                $t = trim($t, " \"'()[]{}<>");
                if ($t !== '') {
                    $tokens[] = $t;
                }
            }
        }
        // URL in een QR (bv. een link met ?sn=…): parameters meenemen
        if (preg_match('#^https?://#i', $raw)) {
            parse_str((string) parse_url($raw, PHP_URL_QUERY), $q);
            foreach ($q as $v) {
                if (is_string($v) && $v !== '') {
                    $tokens[] = $v;
                }
            }
        }
        $tokens        = array_values(array_unique($tokens));
        $out['tokens'] = array_slice($tokens, 0, 12);

        foreach ($tokens as $t) {
            $u = strtoupper($t);

            // MAC-adres: met scheidingstekens, of 12 hex met minstens één letter A-F
            if ($out['mac'] === null && (preg_match('/^([0-9A-F]{2}[:\-]){5}[0-9A-F]{2}$/', $u) || preg_match('/^[0-9A-F]{4}\.[0-9A-F]{4}\.[0-9A-F]{4}$/', $u)
                || (preg_match('/^[0-9A-F]{12}$/', $u) && preg_match('/[A-F]/', $u) && preg_match('/\d/', $u)))) {
                $out['mac'] = Connector::normalizeMac($u);
                continue;
            }

            // EAN/UPC: alleen cijfers, 8, 12, 13 of 14 lang
            if ($out['ean'] === null && preg_match('/^\d{8}$|^\d{12,14}$/', $u)) {
                $out['ean'] = $u;
                continue;
            }

            // Hikvision lang serienummer: typenummer + jjjjmmdd + 4 letters + 9 tekens
            if (preg_match('/^((?:I?DS|HWI|HWP|HWK|HWN)-[A-Z0-9][A-Z0-9\-\/()]*?)(20\d{6})([A-Z]{4})([A-Z0-9]{9})$/', $u, $m)) {
                $out['model']       ??= rtrim($m[1], '-');
                $out['serial_full'] = $u;
                $out['serial']      = $m[4];
                $out['brand']       = 'Hikvision';
                continue;
            }

            // Hikvision typenummer
            if ($out['model'] === null && preg_match('/^(I?DS|HWI|HWP|HWK|HWN)-[A-Z0-9][A-Z0-9\-\/()]{3,}$/', $u)) {
                $out['model'] = $u;
                $out['brand'] ??= 'Hikvision';
                continue;
            }
        }

        // Tweede ronde: korte serienummers en ID's (pas na het typenummer, zodat dat niet als serienummer telt)
        $hik = $out['brand'] === 'Hikvision';
        foreach ($tokens as $t) {
            $u = strtoupper($t);
            if ($u === $out['model'] || $u === $out['serial_full'] || ($out['mac'] && Connector::normalizeMac($u) === $out['mac']) || $u === $out['ean']) {
                continue;
            }
            // Hik-Connect: 9 tekens (letter + 8 cijfers, of 9 cijfers) en een verificatiecode van 6 hoofdletters
            if ($out['serial'] === null && preg_match('/^[A-Z]?\d{8,9}$/', $u) && strlen($u) === 9) {
                $out['serial'] = $u;
                continue;
            }
            if ($out['verification'] === null && ($hik || $out['serial'] !== null) && preg_match('/^[A-Z]{6}$/', $t)) {
                $out['verification'] = $t;
                $out['brand'] ??= 'Hikvision';
                continue;
            }
            // Ajax Device-ID: 6 of 8 hex-tekens met cijfers én letters
            if ($out['device_id'] === null && $out['mac'] === null && preg_match('/^[0-9A-F]{6}$|^[0-9A-F]{8}$/', $u) && preg_match('/\d/', $u) && preg_match('/[A-F]/', $u)) {
                $out['device_id'] = $u;
                continue;
            }
            // Iets dat op een serienummer lijkt
            if ($out['serial'] === null && preg_match('/^[A-Z0-9][A-Z0-9\-\/]{4,39}$/', $u) && preg_match('/\d/', $u)) {
                $out['serial'] = $u;
            }
        }

        if ($out['brand'] === null) {
            if ($out['device_id'] !== null && $out['serial'] === null) {
                $out['brand'] = 'Ajax';
            }
        }

        return $out;
    }

    /**
     * Alle waarden waarop in de kluis gezocht kan worden (kleine letters).
     *
     * @return list<string>
     */
    public static function needles(array $p): array
    {
        $n = array_filter([$p['serial'], $p['serial_full'], $p['device_id'], $p['mac'] ? strtoupper($p['mac']) : null]);
        if ($n === [] && $p['ean'] === null && $p['model'] === null && preg_match('/^[A-Z0-9\-\/]{5,60}$/i', $p['raw'])) {
            $n[] = $p['raw'];
        }

        return array_values(array_unique(array_map('strtolower', $n)));
    }
}
