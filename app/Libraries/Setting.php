<?php

namespace App\Libraries;

/**
 * Instellingen die je in de app zelf beheert (tabel app_settings), met .env als terugval.
 */
class Setting
{
    private static ?array $cache = null;

    public static function get(string $key, $default = null)
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (db_connect()->table('app_settings')->get()->getResultArray() as $r) {
                    self::$cache[$r['key']] = $r['value'];
                }
            } catch (\Throwable $e) {
                // tabel bestaat nog niet (migratie niet gedraaid)
            }
        }
        if (isset(self::$cache[$key]) && self::$cache[$key] !== '') {
            return self::$cache[$key];
        }
        $env = env($key);

        return ($env !== null && $env !== '') ? $env : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        $t = db_connect()->table('app_settings');
        $t->where('key', $key)->delete();
        if ($value !== null && $value !== '') {
            $t->insert(['key' => $key, 'value' => $value]);
        }
        self::$cache = null;
    }
}
