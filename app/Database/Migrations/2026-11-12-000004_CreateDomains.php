<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Domeinnamen als eigen onderdeel. Abonnementen (registratie, hosting,
 * plugins) hangen aan een domein. Bestaande omschrijvingen die een
 * domeinnaam zijn, worden automatisch omgezet.
 */
class CreateDomains extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'registrar'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], // waar geregistreerd (bij extern domein)
            'notes'       => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('customer_id');
        $this->forge->createTable('domains');

        $this->forge->addColumn('subscriptions', [
            'domain_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);

        // Bestaande omschrijvingen die op een domeinnaam lijken omzetten
        $db   = $this->db;
        $now  = date('Y-m-d H:i:s');
        $subs = $db->table('subscriptions')->select('id, customer_id, label')->where('label IS NOT NULL')->get()->getResultArray();
        foreach ($subs as $s) {
            $name = strtolower(trim((string) $s['label']));
            $name = preg_replace('#^https?://#', '', $name);
            $name = preg_replace('#^www\.#', '', rtrim($name, '/'));
            if (! preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $name)) {
                continue;
            }
            $domain = $db->table('domains')->where('name', $name)->get()->getRowArray();
            if ($domain === null) {
                $db->table('domains')->insert(['customer_id' => $s['customer_id'], 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
                $domainId = $db->insertID();
            } else {
                $domainId = $domain['id'];
            }
            $db->table('subscriptions')->where('id', $s['id'])->update(['domain_id' => $domainId, 'label' => null]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('subscriptions', ['domain_id']);
        $this->forge->dropTable('domains', true);
    }
}
