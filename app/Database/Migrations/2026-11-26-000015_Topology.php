<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Topologie: per netwerkapparaat aan welk apparaat (en welke poort) het hangt.
 *
 *  - auto_*  : wat UniFi/UISP zelf zegt (wordt bij elke synchronisatie bijgewerkt)
 *  - parent_*: wat getekend wordt. Gelijk aan auto_*, tenzij locked = 1: dan heb je
 *              het zelf ingesteld en blijft het zo, wat de sync ook zegt.
 *  - view_site_id: in welke topologie een apparaat zonder bovenliggend apparaat
 *              staat (bijv. een UISP-straalverbinding binnen een UniFi-site).
 */
class Topology extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'network_device_id' => ['type' => 'INT', 'unsigned' => true],
            'parent_id'         => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'parent_port'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'local_port'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'link_type'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'wired'], // wired | wireless | ptp | vpn
            'locked'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'auto_parent_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'auto_parent_port'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'auto_link_type'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'auto_seen_at'      => ['type' => 'DATETIME', 'null' => true],
            'view_site_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'note'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('network_device_id');
        $this->forge->addKey('parent_id');
        $this->forge->addKey('view_site_id');
        $this->forge->createTable('network_links');
    }

    public function down(): void
    {
        $this->forge->dropTable('network_links', true);
    }
}
