<?php

namespace App\Libraries;

use App\Models\VaultLogModel;
use RuntimeException;

/**
 * Versleutelt en ontsleutelt de geheimen in de kluis (wachtwoorden, geheime notities).
 *
 * Gebruikt de CodeIgniter Encryption-service (OpenSSL AES-256-CTR + HMAC-SHA512)
 * met de sleutel uit .env (encryption.key). Zonder die sleutel is niets te lezen,
 * ook niet met een kopie van de database.
 */
class Vault
{
    public static function ready(): bool
    {
        return (string) config('Encryption')->key !== '';
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        self::assertReady();

        return base64_encode(service('encrypter')->encrypt($plain));
    }

    public static function decrypt(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }
        self::assertReady();

        $raw = base64_decode($stored, true);
        if ($raw === false) {
            throw new RuntimeException('Opgeslagen waarde is beschadigd.');
        }

        return service('encrypter')->decrypt($raw);
    }

    /**
     * Schrijft in het logboek wie wat met een kluisitem deed.
     */
    public static function log(string $action, ?array $device): void
    {
        model(VaultLogModel::class)->insert([
            'device_id'   => $device['id'] ?? null,
            'customer_id' => $device['customer_id'] ?? null,
            'user_id'     => auth()->id(),
            'action'      => $action,
            'ip'          => service('request')->getIPAddress(),
        ]);
    }

    private static function assertReady(): void
    {
        if (! self::ready()) {
            throw new RuntimeException('Er is geen encryption.key ingesteld in .env. Draai: php spark key:generate');
        }
    }
}
