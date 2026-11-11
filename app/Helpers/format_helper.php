<?php

/**
 * Opmaak en vaste lijsten.
 */

if (! function_exists('euro')) {
    function euro($amount): string
    {
        return '€ ' . number_format((float) $amount, 2, ',', '.');
    }
}

if (! function_exists('datum')) {
    /** 2026-11-11 -> 11-11-2026 */
    function datum(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $ts = strtotime($date);

        return $ts === false ? $date : date('d-m-Y', $ts);
    }
}

if (! function_exists('aantal')) {
    /** 3.00 -> 3, 2.50 -> 2,5 */
    function aantal($qty): string
    {
        $s = rtrim(rtrim(number_format((float) $qty, 2, ',', ''), '0'), ',');

        return $s === '' ? '0' : $s;
    }
}

if (! function_exists('service_types')) {
    /** Soorten diensten: sleutel => [label, eenheid] */
    function service_types(): array
    {
        return [
            'o365'    => ['Microsoft 365', 'gebruiker'],
            'hosting' => ['Webhosting', 'domein'],
            'domain'  => ['Domeinnaam', 'domein'],
            'plugin'  => ['Plugin / licentie', 'licentie'],
            'other'   => ['Overig', 'stuk'],
        ];
    }
}

if (! function_exists('service_type_label')) {
    function service_type_label(?string $type): string
    {
        return service_types()[$type][0] ?? (string) $type;
    }
}

if (! function_exists('device_type_groups')) {
    /**
     * Soorten apparaten, per groep.
     * sleutel => [label, bootstrap-icon, soort velden (net|sensor|login), standaardmerk, modellen]
     */
    function device_type_groups(): array
    {
        $ajax = 'Ajax';

        return [
            'Camera & video' => [
                'camera'   => ['Camera', 'camera-video', 'net', 'Hikvision', []],
                'nvr'      => ['Recorder (NVR/DVR)', 'hdd-stack', 'net', 'Hikvision', []],
                'intercom' => ['Intercom / deurstation', 'door-open', 'net', 'Hikvision', []],
            ],
            'Netwerk' => [
                'router' => ['Router / gateway', 'router', 'net', 'Ubiquiti', ['UniFi Cloud Gateway Max', 'UniFi Cloud Gateway Ultra', 'UniFi Dream Machine Pro']],
                'switch' => ['Switch', 'diagram-3', 'net', 'Ubiquiti', []],
                'ap'     => ['Access point', 'wifi', 'net', 'Ubiquiti', ['U6 Lite', 'U6 Pro', 'U6 Mesh', 'U7 Pro']],
                'server' => ['Server / NAS', 'server', 'net', null, []],
                'wifi'   => ['Wifi-netwerk', 'wifi', 'login', null, []],
            ],
            'Alarm (Ajax e.d.)' => [
                'alarm_hub'      => ['Alarmcentrale / hub', 'shield-lock', 'net', $ajax, ['Hub 2', 'Hub 2 Plus', 'Hub 2 (4G)', 'Hub Hybrid', 'Hub BP']],
                'alarm_motion'   => ['Bewegingsmelder', 'person-walking', 'sensor', $ajax, ['MotionProtect', 'MotionProtect Plus', 'MotionProtect Outdoor', 'MotionCam', 'MotionCam (PhOD)', 'MotionCam Outdoor', 'CombiProtect', 'Curtain Outdoor']],
                'alarm_contact'  => ['Deur-/raamcontact', 'door-closed', 'sensor', $ajax, ['DoorProtect', 'DoorProtect Plus', 'DoorProtect G3', 'GlassProtect']],
                'alarm_keypad'   => ['Bediendeel / keypad', 'grid-3x3-gap', 'sensor', $ajax, ['KeyPad', 'KeyPad Plus', 'KeyPad TouchScreen', 'KeyPad Outdoor']],
                'alarm_siren'    => ['Sirene', 'megaphone', 'sensor', $ajax, ['HomeSiren', 'StreetSiren', 'StreetSiren DoubleDeck']],
                'alarm_fire'     => ['Rook-/hitte-/CO-melder', 'fire', 'sensor', $ajax, ['FireProtect 2 (Heat/Smoke)', 'FireProtect 2 (Heat/Smoke/CO)', 'FireProtect Plus', 'FireProtect']],
                'alarm_water'    => ['Waterdetectie', 'droplet', 'sensor', $ajax, ['LeaksProtect']],
                'alarm_button'   => ['Paniekknop / afstandsbediening', 'hand-index', 'sensor', $ajax, ['Button', 'DoubleButton', 'SpaceControl']],
                'alarm_repeater' => ['Repeater', 'broadcast', 'sensor', $ajax, ['ReX', 'ReX 2']],
                'alarm_relay'    => ['Relais / schakelaar / stekker', 'toggle-on', 'sensor', $ajax, ['Relay', 'WallSwitch', 'Socket', 'LightSwitch', 'MultiTransmitter']],
                'alarm'          => ['Alarm overig', 'shield-check', 'sensor', $ajax, []],
            ],
            'Overig' => [
                'access'  => ['Toegangscontrole', 'key', 'net', null, []],
                'pc'      => ['PC / laptop', 'laptop', 'net', null, []],
                'account' => ['Account / login', 'person-badge', 'login', null, []],
                'other'   => ['Overig', 'box', 'net', null, []],
            ],
        ];
    }
}

if (! function_exists('device_types')) {
    /** Alle soorten plat: sleutel => [label, icon, velden, merk, modellen] */
    function device_types(): array
    {
        return array_merge(...array_values(device_type_groups()));
    }
}

if (! function_exists('device_is_sensor')) {
    function device_is_sensor(?string $type): bool
    {
        return (device_types()[$type][2] ?? 'net') === 'sensor';
    }
}

if (! function_exists('device_type_guess')) {
    /**
     * Raadt aan de hand van productnaam/typenummer welk soort apparaat het is.
     * Geeft null terug voor toebehoren (kabels, beugels, schijven, licenties…).
     */
    function device_type_guess(string $text): ?string
    {
        $t = mb_strtolower($text);

        // Toebehoren: geen apparaat
        if (preg_match('/\b(kabel|cable|patch|beugel|bracket|mount|montage|junction|lasdoos|adapter|voeding|psu|power supply|poe[- ]?injector|injector|harde ?schijf|hdd|ssd|sd[- ]?kaart|micro ?sd|licentie|license|lens|behuizing|housing|connector|rj45|keystone|rack|kast|batterij|battery|accu|sticker|bord|plug|stekker(?!doos))\b/u', $t)
            && ! preg_match('/\b(socket|nvr|dvr)\b/u', $t)) {
            return null;
        }

        $rules = [
            // Ajax / alarm eerst (woorden als "switch" en "hub" komen ook elders voor)
            'alarm_motion'   => '/motionprotect|motioncam|combiprotect|curtain|bewegingsmelder|\bpir\b/u',
            'alarm_contact'  => '/doorprotect|glassprotect|deurcontact|magneetcontact|raamcontact/u',
            'alarm_keypad'   => '/keypad|bediendeel/u',
            'alarm_siren'    => '/siren|sirene/u',
            'alarm_fire'     => '/fireprotect|rookmelder|hittemelder|co[- ]melder|smoke detector/u',
            'alarm_water'    => '/leaksprotect|waterdetect|lekdetect/u',
            'alarm_button'   => '/spacecontrol|doublebutton|paniekknop|\bajax\b.*\bbutton\b|^button\b/u',
            'alarm_repeater' => '/\brex\b|\brex ?2\b/u',
            'alarm_relay'    => '/\bwallswitch\b|\blightswitch\b|multitransmitter|\bajax\b.*\b(relay|socket)\b|^(relay|socket)\b/u',
            'alarm_hub'      => '/\bhub ?(2|hybrid|bp|plus)\b|\bajax\b.*\bhub\b|alarmcentrale|alarmpaneel/u',
            'alarm'          => '/\bajax\b/u',
            // Hikvision / video
            'intercom'       => '/ds-k[dvhbi]|intercom|deurstation|videofoon|monitor binnenpost/u',
            'nvr'            => '/\bnvr\b|\bdvr\b|recorder|ds-[79]\d{3}|\bunvr\b/u',
            'camera'         => '/ds-2c[deh]|ds-2de|ds-2xs|camera|bullet|dome|turret|\buvc\b|\bg[345] (bullet|dome|flex|instant|pro)\b/u',
            'access'         => '/ds-k1|toegangs|access control|kaartlezer|reader|\bua[- ]/u',
            // Ubiquiti / netwerk
            'router'         => '/\bucg\b|\budm\b|\budr\b|\buxg\b|cloud gateway|dream (machine|router)|\busg\b|gateway|router|firewall|edgerouter|\ber-\d/u',
            'ap'             => '/\bu[67][- ]?(lite|pro|mesh|lr|plus|enterprise|outdoor|iw|in-wall)\b|\bu[67]-|\buap\b|nanohd|access ?point|\bac[- ](lite|pro|lr|mesh)\b|\buk[- ]ultra\b|\bu6\b|\bu7\b/u',
            'switch'         => '/\busw\b|\busw-|\bus-\d|flex mini|\bswitch\b|lite \d+ poe|pro \d+ poe/u',
            'server'         => '/\bnas\b|synology|qnap|\bserver\b|\bunas\b/u',
        ];
        foreach ($rules as $type => $re) {
            if (preg_match($re, $t)) {
                return $type;
            }
        }

        return null;
    }
}

if (! function_exists('device_type_label')) {
    function device_type_label(?string $type): string
    {
        return device_types()[$type][0] ?? (string) $type;
    }
}

if (! function_exists('device_type_icon')) {
    function device_type_icon(?string $type): string
    {
        return '<i class="bi bi-' . esc(device_types()[$type][1] ?? 'box', 'attr') . '"></i>';
    }
}

if (! function_exists('interval_label')) {
    function interval_label(int $months): string
    {
        return match ($months) {
            1       => 'maand',
            3       => 'kwartaal',
            6       => 'halfjaar',
            12      => 'jaar',
            24      => '2 jaar',
            default => $months . ' maanden',
        };
    }
}
