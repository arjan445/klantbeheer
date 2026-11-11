<?php

namespace App\Commands;

use App\Libraries\Catalog;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Producten en inkoopprijzen uit de Wimood-productfeed ophalen.
 * Cron, bijv. elke nacht:  20 3 * * *  cd /pad && php spark klantbeheer:wimood
 */
class SyncWimood extends BaseCommand
{
    protected $group       = 'Klantbeheer';
    protected $name        = 'klantbeheer:wimood';
    protected $description = 'Haal producten en inkoopprijzen uit de Wimood-productfeed op.';

    public function run(array $params)
    {
        try {
            $r = Catalog::syncWimood();
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }
        CLI::write(sprintf('%d producten, %d nieuw, %d bijgewerkt, %d inactief.', $r['total'], $r['new'], $r['updated'], $r['inactive']), 'green');

        return EXIT_SUCCESS;
    }
}
