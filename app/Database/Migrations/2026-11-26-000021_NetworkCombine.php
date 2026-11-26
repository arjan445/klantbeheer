<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gecombineerde installatie (bijv. UniFi + UISP): een netwerksite kan bij een
 * andere site worden gevoegd; samen één topologie.
 */
class NetworkCombine extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('merged_into', 'network_sites')) {
            $this->forge->addColumn('network_sites', [
                'merged_into' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('network_sites', ['merged_into']);
    }
}
