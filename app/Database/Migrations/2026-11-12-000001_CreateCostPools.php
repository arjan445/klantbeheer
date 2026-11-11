<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kostenpools: inkoopkosten uit Moneybird (per categorie / grootboekrekening)
 * verdelen over diensten, zodat de kostprijs per eenheid automatisch klopt.
 */
class CreateCostPools extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'             => ['type' => 'VARCHAR', 'constraint' => 255],
            'ledger_account_ids' => ['type' => 'TEXT', 'null' => true],   // komma-gescheiden Moneybird-ID's
            'lookback_months'  => ['type' => 'INT', 'default' => 12],
            'allocation_pct'   => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 100],
            'extra_yearly'     => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true], // handmatige extra kosten per jaar
            'synced_amount'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true], // totaal uit Moneybird in de periode
            'synced_from'      => ['type' => 'DATE', 'null' => true],
            'synced_to'        => ['type' => 'DATE', 'null' => true],
            'synced_at'        => ['type' => 'DATETIME', 'null' => true],
            'breakdown'        => ['type' => 'TEXT', 'null' => true],      // JSON: per categorie en per leverancier
            'notes'            => ['type' => 'TEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('cost_pools');

        $this->forge->addColumn('services', [
            'cost_pool_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'cost_weight'  => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 1],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('services', ['cost_pool_id', 'cost_weight']);
        $this->forge->dropTable('cost_pools', true);
    }
}
