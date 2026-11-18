<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Urenregistratie per klant/locatie, kilometers per werkdag (memoriaal in Moneybird)
 * en een eenvoudige instellingentabel.
 */
class Hours extends Migration
{
    public function up(): void
    {
        $ts = ['created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]];

        $this->forge->addField([
            'key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'value' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->createTable('app_settings', true);

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id'   => ['type' => 'INT', 'unsigned' => true],
            'site_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'date'          => ['type' => 'DATE'],
            'start_time'    => ['type' => 'TIME'],
            'end_time'      => ['type' => 'TIME'],
            'pause_minutes' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'description'   => ['type' => 'VARCHAR', 'constraint' => 500],
            'billable'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'travel'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], // telt mee voor de kilometers
            'moneybird_id'  => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'sync_error'    => ['type' => 'TEXT', 'null' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('date');
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', '', 'CASCADE');
        $this->forge->createTable('time_entries');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'date'        => ['type' => 'DATE'],
            'km'          => ['type' => 'DECIMAL', 'constraint' => '8,1', 'null' => true],
            'km_manual'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'route'       => ['type' => 'TEXT', 'null' => true],
            'customers'   => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'signature'   => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],  // stops van de dag
            'rate'        => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true],
            'amount'      => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'journal_id'  => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'booked_sig'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],  // wat er geboekt is
            'booked_at'   => ['type' => 'DATETIME', 'null' => true],
            'error'       => ['type' => 'TEXT', 'null' => true],
        ] + $ts);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('date');
        $this->forge->createTable('work_days');

        $this->forge->addColumn('sites', [
            'lat'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'geo_query' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sites', ['lat', 'lng', 'geo_query']);
        $this->forge->dropTable('work_days', true);
        $this->forge->dropTable('time_entries', true);
        $this->forge->dropTable('app_settings', true);
    }
}
