<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Storingsmeldingen: apart bijhouden wanneer de klant gemaild is. Jij krijgt
 * meteen een mail, de klant alleen binnen de verzendtijden (bijv. 17:00-06:00).
 */
class AlertClientNotified extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('client_notified_at', 'network_alerts')) {
            $this->forge->addColumn('network_alerts', [
                'client_notified_at' => ['type' => 'DATETIME', 'null' => true],
                'client_recipients'  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            ]);
            // Wat al als "live" naar de klant ging, telt als gemaild
            $this->db->query('UPDATE ' . $this->db->prefixTable('network_alerts') . " SET client_notified_at = notified_at WHERE mode IN ('live', 'start')");
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('network_alerts', ['client_notified_at', 'client_recipients']);
    }
}
