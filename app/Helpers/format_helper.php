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

if (! function_exists('device_types')) {
    /** Soorten apparaten/items in de kluis: sleutel => [label, bootstrap-icon] */
    function device_types(): array
    {
        return [
            'camera'   => ['Camera', 'camera-video'],
            'nvr'      => ['Recorder (NVR/DVR)', 'hdd-stack'],
            'switch'   => ['Switch', 'diagram-3'],
            'router'   => ['Router / gateway', 'router'],
            'ap'       => ['Access point', 'wifi'],
            'server'   => ['Server / NAS', 'server'],
            'intercom' => ['Intercom / deurstation', 'door-open'],
            'alarm'    => ['Alarm / toegang', 'shield-lock'],
            'pc'       => ['PC / laptop', 'laptop'],
            'wifi'     => ['Wifi-netwerk', 'wifi'],
            'account'  => ['Account / login', 'person-badge'],
            'other'    => ['Overig', 'box'],
        ];
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
