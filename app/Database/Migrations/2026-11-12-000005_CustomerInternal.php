<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Klant markeren als "eigen gebruik": wordt bijgehouden, maar nooit gefactureerd
 * en telt niet mee als omzet.
 */
class CustomerInternal extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('customers', [
            'internal' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', ['internal']);
    }
}
