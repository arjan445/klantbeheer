<?php

namespace App\Commands;

use App\Libraries\Billing;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Mailt de admins een overzicht van wat er gefactureerd moet worden.
 * Cron, bijv. elke maandag 07:00:
 *   0 7 * * 1  cd /pad/naar/klantbeheer && php spark klantbeheer:herinnering
 */
class RenewalReminder extends BaseCommand
{
    protected $group       = 'Klantbeheer';
    protected $name        = 'klantbeheer:herinnering';
    protected $description = 'Mail de admins welke abonnementen binnenkort gefactureerd moeten worden.';
    protected $usage       = 'klantbeheer:herinnering [dagen]';

    public function run(array $params)
    {
        $days = isset($params[0]) ? (int) $params[0] : Billing::daysAhead();
        $due  = Billing::due($days);

        if ($due === []) {
            CLI::write('Niets te factureren.');

            return EXIT_SUCCESS;
        }

        $rows = '';
        $total = 0.0;
        foreach ($due as $s) {
            $amount = $s['eff_price'] * $s['quantity'];
            $total += $amount;
            $rows .= '<tr><td>' . esc(datum($s['next_invoice_date'])) . '</td><td>' . esc($s['customer_name']) . '</td><td>'
                . esc(Billing::lineDescription($s)) . '</td><td style="text-align:right">' . euro($amount) . '</td></tr>';
        }

        $body = '<p>Deze abonnementen moeten binnen ' . $days . ' dagen gefactureerd worden:</p>'
            . '<table cellpadding="4" style="border-collapse:collapse">' . $rows . '</table>'
            . '<p><strong>Totaal: ' . euro($total) . ' excl. btw</strong></p>'
            . '<p><a href="' . site_url('facturatie') . '">Naar facturatie</a></p>';

        $recipients = [];
        foreach (auth()->getProvider()->withIdentities()->findAll() as $user) {
            if ($user->active && $user->inGroup('admin') && $user->email) {
                $recipients[] = $user->email;
            }
        }
        if ($recipients === []) {
            CLI::error('Geen actieve admins met e-mailadres gevonden.');

            return EXIT_ERROR;
        }

        $email = service('email');
        $email->setTo($recipients);
        $email->setSubject(count($due) . ' abonnement(en) te factureren');
        $email->setMessage($body);
        $email->setMailType('html');

        if (! $email->send(false)) {
            CLI::error('Versturen mislukt: ' . strip_tags($email->printDebugger(['headers'])));

            return EXIT_ERROR;
        }

        CLI::write('Herinnering verstuurd naar ' . implode(', ', $recipients), 'green');

        return EXIT_SUCCESS;
    }
}
