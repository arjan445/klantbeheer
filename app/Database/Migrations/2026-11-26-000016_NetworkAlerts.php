<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Storingsmeldingen: welk apparaat is wanneer gemeld (één keer per storing),
 * zodat dezelfde storing niet elke 5 minuten opnieuw gemaild wordt.
 */
class NetworkAlerts extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id'       => ['type' => 'INT', 'unsigned' => true],
            'network_device_id' => ['type' => 'INT', 'unsigned' => true],
            'offline_since'     => ['type' => 'DATETIME'],
            'notified_at'       => ['type' => 'DATETIME'],
            'recipients'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'mode'              => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'test'], // test | live
            'recovered_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['network_device_id', 'offline_since']);
        $this->forge->addKey('customer_id');
        $this->forge->createTable('network_alerts');
    }

    public function down(): void
    {
        $this->forge->dropTable('network_alerts', true);
    }
}
