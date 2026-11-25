<?php

namespace App\Commands;

use App\Libraries\Network;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Apparaten en online status ophalen uit UniFi en UISP.
 * Cron, elke 5 minuten:  * /5 * * * *  cd /pad && php spark klantbeheer:netwerk
 * (zonder de spatie tussen * en /5)
 */
class SyncNetwork extends BaseCommand
{
    protected $group       = 'Klantbeheer';
    protected $name        = 'klantbeheer:netwerk';
    protected $description = 'Haal apparaten en online status op uit UniFi en UISP.';
    protected $usage       = 'klantbeheer:netwerk [unifi|uisp]';

    public function run(array $params)
    {
        if (! Network::configured()) {
            CLI::write('UniFi en UISP zijn nog niet ingesteld (Beheer › Netwerk).', 'yellow');

            return EXIT_SUCCESS;
        }

        $failed = false;
        foreach (Network::sync($params[0] ?? null) as $r) {
            if (! $r['ok']) {
                $failed = true;
                CLI::error($r['label'] . ': ' . $r['error']);
                continue;
            }
            CLI::write(sprintf('%s: %d sites, %d apparaten (%d online, %d offline), %d automatisch gekoppeld.',
                $r['label'], $r['sites'], $r['devices'], $r['online'], $r['offline'], $r['linked']), 'green');
            foreach ($r['notes'] as $n) {
                CLI::write('  ' . $n, 'yellow');
            }
        }

        return $failed ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
