<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Herstelmail ("weer bereikbaar"): wanneer jij en de klant die hebben gehad.
 * Oude, al afgesloten storingen tellen als afgehandeld (geen stapel herstelmails).
 */
class AlertRecovery extends Migration
{
    public function up(): void
    {
        $add = [];
        foreach (['recovery_notified_at', 'recovery_client_at'] as $f) {
            if (! $this->db->fieldExists($f, 'network_alerts')) {
                $add[$f] = ['type' => 'DATETIME', 'null' => true];
            }
        }
        if ($add !== []) {
            $this->forge->addColumn('network_alerts', $add);
            $this->db->query('UPDATE ' . $this->db->prefixTable('network_alerts') . ' SET recovery_notified_at = recovered_at, recovery_client_at = recovered_at WHERE recovered_at IS NOT NULL');
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('network_alerts', ['recovery_notified_at', 'recovery_client_at']);
    }
}
