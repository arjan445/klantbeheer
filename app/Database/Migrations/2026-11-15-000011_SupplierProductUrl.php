<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Link naar de productpagina bij de leverancier; {sku} wordt vervangen door het artikelnummer.
 */
class SupplierProductUrl extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('suppliers', [
            'product_url' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->db->table('suppliers')->where('code', 'osec')->update(['product_url' => 'https://www.osec.nl/products/view/{sku}']);
        $this->db->table('suppliers')->where('code', 'wimood')->update(['product_url' => 'https://www.wimoodshop.nl/nl/products/{sku}']);
    }

    public function down(): void
    {
        $this->forge->dropColumn('suppliers', ['product_url']);
    }
}
