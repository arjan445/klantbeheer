<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Takenlijst (dashboard + iCal-feed voor je agenda) */
class Todos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 200],
            'notes'       => ['type' => 'TEXT', 'null' => true],
            'customer_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'due_date'    => ['type' => 'DATE', 'null' => true],
            'due_time'    => ['type' => 'TIME', 'null' => true],
            'duration'    => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 30], // minuten, voor de agenda
            'done'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'done_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['done', 'due_date']);
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', '', 'SET NULL');
        $this->forge->createTable('todos');
    }

    public function down(): void
    {
        $this->forge->dropTable('todos', true);
    }
}
