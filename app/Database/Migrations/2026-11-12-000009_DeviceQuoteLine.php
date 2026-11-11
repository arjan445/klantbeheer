<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Apparaten onthouden uit welke offerteregel ze zijn aangemaakt,
 * zodat je ze niet dubbel aanmaakt.
 */
class DeviceQuoteLine extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('devices', [
            'quote_line_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);
        $this->db->query('CREATE INDEX devices_quote_line_id ON ' . $this->db->prefixTable('devices') . ' (quote_line_id)');
    }

    public function down(): void
    {
        $this->forge->dropColumn('devices', ['quote_line_id']);
    }
}
