<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * UniFi Fabrics en SD-WAN (Site Magic):
 *  - fabric: met welke API-sleutel (Fabric) de site binnenkwam
 *  - vpn:    JSON met de SD-WAN-verbanden waar deze site in zit (naam, hub/spoke,
 *            tunnels verbonden/totaal, andere sites); null = geen
 *  - vpn_at: wanneer dat het laatst is opgehaald
 */
class NetworkFabric extends Migration
{
    public function up(): void
    {
        $add = [];
        if (! $this->db->fieldExists('fabric', 'network_sites')) {
            $add['fabric'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true];
        }
        if (! $this->db->fieldExists('vpn', 'network_sites')) {
            $add['vpn'] = ['type' => 'TEXT', 'null' => true];
        }
        if (! $this->db->fieldExists('vpn_at', 'network_sites')) {
            $add['vpn_at'] = ['type' => 'DATETIME', 'null' => true];
        }
        if ($add !== []) {
            $this->forge->addColumn('network_sites', $add);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('network_sites', ['fabric', 'vpn', 'vpn_at']);
    }
}
