<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

/**
 * Rollen en rechten van Klantbeheer.
 *
 * Wijzig je hier iets aan de groepen van een bestaande gebruiker,
 * doe dat dan via Gebruikers in de app.
 */
class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'monteur';

    public array $groups = [
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Alles, inclusief gebruikers en instellingen.',
        ],
        'administratie' => [
            'title'       => 'Administratie',
            'description' => 'Klanten, abonnementen en facturatie. Geen toegang tot de kluis.',
        ],
        'monteur' => [
            'title'       => 'Monteur',
            'description' => 'Klanten en locaties inzien, apparaten en wachtwoorden beheren.',
        ],
    ];

    public array $permissions = [
        'customers.view'   => 'Klanten en locaties inzien',
        'customers.manage' => 'Klanten en locaties aanmaken/wijzigen',
        'billing.view'     => 'Abonnementen, diensten en omzet inzien',
        'billing.manage'   => 'Abonnementen en diensten beheren, factureren via Moneybird',
        'vault.view'       => 'Apparaten inzien en wachtwoorden tonen',
        'vault.manage'     => 'Apparaten en wachtwoorden toevoegen/wijzigen',
        'users.manage'     => 'Gebruikers beheren',
        'settings.manage'  => 'Instellingen (Moneybird) en het kluislogboek',
    ];

    public array $matrix = [
        'admin'         => ['customers.*', 'billing.*', 'vault.*', 'users.*', 'settings.*'],
        'administratie' => ['customers.view', 'customers.manage', 'billing.view', 'billing.manage'],
        'monteur'       => ['customers.view', 'vault.view', 'vault.manage'],
    ];
}
