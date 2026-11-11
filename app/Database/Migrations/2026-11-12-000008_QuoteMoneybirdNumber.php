<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Offertenummer uit Moneybird bewaren, en een "ruimte/plek" bij apparaten.
 */
class QuoteMoneybirdNumber extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('quotes', [
            'moneybird_number' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        ]);
        $this->forge->addColumn('devices', [
            'room' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('quotes', ['moneybird_number']);
        $this->forge->dropColumn('devices', ['room']);
    }
}
