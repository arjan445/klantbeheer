<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Koppeling met DSD Europe: eindklanten en licenties (contracten).
 */
class Dsd extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('customers', [
            'dsd_client_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
        ]);
        $this->forge->addColumn('subscriptions', [
            'dsd_item_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);

        // Laatst opgehaalde licenties uit DSD (één regel per product per contract)
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'item_key'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'contract_id'      => ['type' => 'VARCHAR', 'constraint' => 32],
            'dsd_client_id'    => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true], // leeg = eigen gebruik
            'client_name'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sku'              => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'product_name'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'brand'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'quantity'         => ['type' => 'INT', 'null' => true],
            'unit'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'price'            => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'advisory_price'   => ['type' => 'DECIMAL', 'constraint' => '10,4', 'null' => true],
            'invoicing_period' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'commitment_term'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'renewal_date'     => ['type' => 'DATE', 'null' => true],
            'status'           => ['type' => 'VARCHAR', 'constraint' => 20],
            'sale_reference'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'synced_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('item_key');
        $this->forge->addKey('dsd_client_id');
        $this->forge->createTable('dsd_items');
    }

    public function down(): void
    {
        $this->forge->dropTable('dsd_items', true);
        $this->forge->dropColumn('subscriptions', ['dsd_item_key']);
        $this->forge->dropColumn('customers', ['dsd_client_id']);
    }
}
