<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Per klant: storingsmails naar de klant aan of uit (standaard uit) */
class CustomerAlertMail extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('alert_mail', 'customers')) {
            $this->forge->addColumn('customers', [
                'alert_mail' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', 'alert_mail');
    }
}
