<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Offertes: leveranciers met kortingen, productcatalogus (OSEC-prijslijst,
 * Wimood-bestellingen, losse producten) en offertes met regels.
 */
class Quotes extends Migration
{
    public function up(): void
    {
        $id = ['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true]];
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        $this->forge->addField($id + [
            'name'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'code'             => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true], // wimood | osec
            'default_discount' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],  // % op bruto
            'default_markup'   => ['type' => 'DECIMAL', 'constraint' => '6,3', 'default' => 1.2],
            'last_import_at'   => ['type' => 'DATETIME', 'null' => true],
            'notes'            => ['type' => 'TEXT', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('suppliers');

        $this->forge->addField($id + [
            'supplier_id' => ['type' => 'INT', 'unsigned' => true],
            'group_name'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'discount'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true], // leeg = standaard
            'markup'      => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true], // leeg = standaard
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['supplier_id', 'group_name']);
        $this->forge->createTable('supplier_groups');

        $this->forge->addField($id + [
            'supplier_id'  => ['type' => 'INT', 'unsigned' => true],
            'supplier_sku' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], // artikelnr bij leverancier
            'sku'          => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true], // typenummer fabrikant
            'name'         => ['type' => 'VARCHAR', 'constraint' => 500],
            'brand'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'group_name'   => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'list_price'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true], // bruto
            'cost_price'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true], // vaste inkoop (Wimood/handmatig)
            'markup'       => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true],  // leeg = groep/leverancier
            'sell_price'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true], // vaste verkoopprijs (optioneel)
            'source'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'manual'], // osec | wimood | manual
            'active'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'info'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['supplier_id', 'supplier_sku']);
        $this->forge->addKey('name');
        $this->forge->createTable('products');

        $this->forge->addField($id + [
            'customer_id'           => ['type' => 'INT', 'unsigned' => true],
            'title'                 => ['type' => 'VARCHAR', 'constraint' => 255],
            'notes'                 => ['type' => 'TEXT', 'null' => true],
            'status'                => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'concept'], // concept | moneybird | geaccepteerd | afgewezen
            'moneybird_estimate_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'user_id'               => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->createTable('quotes');

        $this->forge->addField($id + [
            'quote_id'    => ['type' => 'INT', 'unsigned' => true],
            'product_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'material'], // material | labor | text
            'description' => ['type' => 'TEXT'],
            'quantity'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1],
            'cost_price'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'sell_price'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
            'sort'        => ['type' => 'INT', 'default' => 0],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('quote_id');
        $this->forge->createTable('quote_lines');

        $now = date('Y-m-d H:i:s');
        $this->db->table('suppliers')->insertBatch([
            ['name' => 'Wimood', 'code' => 'wimood', 'default_discount' => 0, 'default_markup' => 1.2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'OSEC', 'code' => 'osec', 'default_discount' => 0, 'default_markup' => 1.2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Overig', 'code' => null, 'default_discount' => 0, 'default_markup' => 1.2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        foreach (['quote_lines', 'quotes', 'products', 'supplier_groups', 'suppliers'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
