<?php

namespace App\Commands;

use App\Libraries\CostPools;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Inkoopkosten uit Moneybird ophalen en kostprijzen bijwerken.
 * Cron, bijv. elke nacht:  15 3 * * *  cd /pad && php spark klantbeheer:kosten
 */
class SyncCosts extends BaseCommand
{
    protected $group       = 'Klantbeheer';
    protected $name        = 'klantbeheer:kosten';
    protected $description = 'Haal inkoopkosten uit Moneybird op en werk de kostprijzen bij.';

    public function run(array $params)
    {
        try {
            $r = CostPools::sync();
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }
        CLI::write(sprintf('%d documenten, %d kostenpool(s) bijgewerkt.', $r['documents'], $r['pools']), 'green');

        return EXIT_SUCCESS;
    }
}
