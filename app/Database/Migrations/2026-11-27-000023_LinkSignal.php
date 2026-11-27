<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Signaalsterkte van draadloze verbindingen (UISP-stations/PTP en UniFi-mesh):
 * per apparaat het signaal van zijn uplink, korte radio-info en wanneer gemeten.
 */
class LinkSignal extends Migration
{
    public function up(): void
    {
        $add = [];
        if (! $this->db->fieldExists('signal', 'network_devices')) {
            $add['signal'] = ['type' => 'SMALLINT', 'null' => true]; // dBm
        }
        if (! $this->db->fieldExists('radio', 'network_devices')) {
            $add['radio'] = ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true];
        }
        if (! $this->db->fieldExists('signal_at', 'network_devices')) {
            $add['signal_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        if ($add !== []) {
            $this->forge->addColumn('network_devices', $add);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('network_devices', ['signal', 'radio', 'signal_at']);
    }
}
