<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;

/**
 * Eerste admin-gebruiker aanmaken:  php spark klantbeheer:admin
 */
class CreateAdmin extends BaseCommand
{
    protected $group       = 'Klantbeheer';
    protected $name        = 'klantbeheer:admin';
    protected $description = 'Maak een admin-gebruiker aan.';
    protected $usage       = 'klantbeheer:admin [email] [password]';

    public function run(array $params)
    {
        $email    = $params[0] ?? CLI::prompt('E-mailadres', null, 'required|valid_email');
        $password = $params[1] ?? CLI::prompt('Wachtwoord (min. 10 tekens)', null, 'required|min_length[10]');

        $provider = auth()->getProvider();
        if ($provider->findByCredentials(['email' => $email]) !== null) {
            CLI::error('Er bestaat al een gebruiker met dit e-mailadres.');

            return EXIT_ERROR;
        }

        $user = new User(['email' => $email, 'password' => $password, 'active' => 1]);
        $provider->save($user);
        $user = $provider->findById($provider->getInsertID());
        $user->syncGroups('admin');

        CLI::write('Admin aangemaakt: ' . $email, 'green');

        return EXIT_SUCCESS;
    }
}
