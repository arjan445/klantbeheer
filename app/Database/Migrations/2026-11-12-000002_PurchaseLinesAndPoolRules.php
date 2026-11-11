<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Inkoopregels uit Moneybird lokaal bewaren, en kostenpools laten matchen op
 * leverancier, zoekwoord of categorie, met handmatig aan/uitvinken per regel.
 */
class PurchaseLinesAndPoolRules extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'detail_id'     => ['type' => 'VARCHAR', 'constraint' => 32],
            'document_id'   => ['type' => 'VARCHAR', 'constraint' => 32],
            'kind'          => ['type' => 'VARCHAR', 'constraint' => 20],
            'date'          => ['type' => 'DATE'],
            'reference'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'supplier_id'   => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'supplier_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ledger_id'     => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'ledger_name'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'description'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'amount'        => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('detail_id');
        $this->forge->addKey('date');
        $this->forge->createTable('purchase_lines');

        $this->forge->addColumn('cost_pools', [
            'supplier_ids'  => ['type' => 'TEXT', 'null' => true],
            'keywords'      => ['type' => 'TEXT', 'null' => true],
            'include_lines' => ['type' => 'TEXT', 'null' => true],
            'exclude_lines' => ['type' => 'TEXT', 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('cost_pools', ['supplier_ids', 'keywords', 'include_lines', 'exclude_lines']);
        $this->forge->dropTable('purchase_lines', true);
    }
}
