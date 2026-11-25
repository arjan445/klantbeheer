<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Netwerkbewaking en herkomst van apparaten:
 *  - network_sites:     sites uit UniFi (Site Manager) en UISP, gekoppeld aan een klantlocatie
 *  - network_devices:   apparaten uit die platforms, met IP, status en laatst gezien,
 *                       gekoppeld aan een apparaat in de kluis (of nog in de inbox)
 *  - purchase_invoices: inkoopfacturen, zodat je per apparaat terugziet waarop het geleverd is
 *  - devices:           verkoopdatum en inkoopfactuur
 */
class Network extends Migration
{
    public function up(): void
    {
        $id = ['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true]];
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        $this->forge->addField($id + [
            'platform'       => ['type' => 'VARCHAR', 'constraint' => 20],             // unifi | uisp
            'external_id'    => ['type' => 'VARCHAR', 'constraint' => 100],            // site-id bij het platform
            'host_id'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true], // UniFi-console
            'host_name'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'site_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true], // klantlocatie
            'ignored'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'device_count'   => ['type' => 'INT', 'default' => 0],
            'offline_count'  => ['type' => 'INT', 'default' => 0],
            'last_synced_at' => ['type' => 'DATETIME', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['platform', 'external_id']);
        $this->forge->addKey('site_id');
        $this->forge->createTable('network_sites');

        $this->forge->addField($id + [
            'platform'        => ['type' => 'VARCHAR', 'constraint' => 20],
            'external_id'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'network_site_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'device_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true], // apparaat in de kluis
            'name'            => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'model'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'kind'            => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],  // productLine / categorie
            'mac'             => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],  // 12 hex, kleine letters
            'serial'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'ip'              => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'firmware'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'unknown'], // online | offline | unknown
            'raw_status'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'monitor'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],   // hoort online te zijn
            'ignored'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],   // uit de inbox
            'last_seen_at'    => ['type' => 'DATETIME', 'null' => true],
            'offline_since'   => ['type' => 'DATETIME', 'null' => true],
            'synced_at'       => ['type' => 'DATETIME', 'null' => true],
            'gone'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],   // niet meer in het platform
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['platform', 'external_id']);
        $this->forge->addKey('mac');
        $this->forge->addKey('device_id');
        $this->forge->addKey('network_site_id');
        $this->forge->addKey(['status', 'monitor']);
        $this->forge->createTable('network_devices');

        $this->forge->addField($id + [
            'supplier_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'supplier'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], // vrije naam als er geen leverancier is
            'number'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'date'         => ['type' => 'DATE', 'null' => true],
            'amount'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'moneybird_id' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'notes'        => ['type' => 'TEXT', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('date');
        $this->forge->addKey('number');
        $this->forge->createTable('purchase_invoices');

        $this->forge->addColumn('devices', [
            'sold_at'             => ['type' => 'DATE', 'null' => true],
            'purchase_invoice_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->db->query('CREATE INDEX devices_purchase_invoice_id ON ' . $this->db->prefixTable('devices') . ' (purchase_invoice_id)');

        // Apparaten die al uit een offerte zijn aangemaakt: verkocht op de dag dat ze zijn aangemaakt
        $this->db->query('UPDATE ' . $this->db->prefixTable('devices') . ' SET sold_at = DATE(created_at) WHERE quote_line_id IS NOT NULL AND sold_at IS NULL AND created_at IS NOT NULL');
    }

    public function down(): void
    {
        $this->forge->dropColumn('devices', ['sold_at', 'purchase_invoice_id']);
        foreach (['purchase_invoices', 'network_devices', 'network_sites'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
