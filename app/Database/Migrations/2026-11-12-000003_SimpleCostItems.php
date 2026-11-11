<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kostenpools vereenvoudigd: kostenposten met de hand (leverancier, bedrag,
 * per maand of per jaar) in plaats van ophalen uit Moneybird.
 */
class SimpleCostItems extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'cost_pool_id' => ['type' => 'INT', 'unsigned' => true],
            'supplier'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'amount'       => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'period'       => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'month'], // month | year
            'sort'         => ['type' => 'INT', 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('cost_pool_id');
        $this->forge->createTable('cost_items');

        $this->forge->dropTable('purchase_lines', true);
        $this->forge->dropColumn('cost_pools', [
            'ledger_account_ids', 'lookback_months', 'allocation_pct', 'extra_yearly', 'synced_amount', 'synced_from',
            'synced_to', 'synced_at', 'breakdown', 'supplier_ids', 'keywords', 'include_lines', 'exclude_lines',
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('cost_items', true);
    }
}
