<?php

namespace App\Commands;

use App\Libraries\Catalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Producten en inkoopprijzen uit je Wimood-bestellingen ophalen.
 * Cron, bijv. elke nacht:  20 3 * * *  cd /pad && php spark klantbeheer:wimood
 */
class SyncWimood extends BaseCommand
{
    protected $group       = 'Klantbeheer';
    protected $name        = 'klantbeheer:wimood';
    protected $description = 'Haal producten en inkoopprijzen uit je Wimood-bestellingen op.';

    public function run(array $params)
    {
        try {
            $r = Catalog::syncWimood();
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }
        CLI::write(sprintf('%d bestellingen, %d nieuw, %d bijgewerkt.', $r['orders'], $r['new'], $r['updated']), 'green');

        return EXIT_SUCCESS;
    }
}
