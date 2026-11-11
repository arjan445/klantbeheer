<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Alle tabellen van Klantbeheer.
 * Draai eerst Shield:  php spark migrate --all
 */
class CreateKlantbeheer extends Migration
{
    public function up(): void
    {
        $id         = ['id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true]];
        $timestamps = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];
        $fk    = fn (bool $null = false) => ['type' => 'INT', 'unsigned' => true, 'null' => $null];
        $str   = fn (int $len = 255, bool $null = true) => ['type' => 'VARCHAR', 'constraint' => $len, 'null' => $null];
        $money = fn (bool $null = true) => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => $null];
        $text  = ['type' => 'TEXT', 'null' => true];

        // Klanten
        $this->forge->addField($id + [
            'name'                 => $str(255, false),
            'contact_name'         => $str(),
            'email'                => $str(),
            'phone'                => $str(50),
            'address'              => $str(),
            'zipcode'              => $str(20),
            'city'                 => $str(100),
            'moneybird_contact_id' => $str(32),
            'notes'                => $text,
            'active'               => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ] + $timestamps);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('moneybird_contact_id');
        $this->forge->createTable('customers');

        // Locaties per klant
        $this->forge->addField($id + [
            'customer_id' => $fk(),
            'name'        => $str(255, false),
            'address'     => $str(),
            'zipcode'     => $str(20),
            'city'        => $str(100),
            'notes'       => $text,
        ] + $timestamps);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', '', 'CASCADE');
        $this->forge->createTable('sites');

        // Diensten (catalogus): O365, hosting, domein, plugin, ...
        $this->forge->addField($id + [
            'name'                 => $str(255, false),
            'type'                 => $str(20, false),
            'unit'                 => $str(30),
            'price'                => $money(false),
            'purchase_price'       => $money(),
            'interval_months'      => ['type' => 'INT', 'default' => 12],
            'supplier'             => $str(100),
            'moneybird_product_id' => $str(32),
            'tax_rate_id'          => $str(32),
            'active'               => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'notes'                => $text,
        ] + $timestamps);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('services');

        // Abonnementen: welke klant neemt welke dienst af, hoeveel, tot wanneer gefactureerd
        $this->forge->addField($id + [
            'customer_id'       => $fk(),
            'service_id'        => $fk(),
            'site_id'           => $fk(true),
            'label'             => $str(),          // domein, tenant, website
            'quantity'          => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1],
            'price'             => $money(),        // leeg = prijs van de dienst
            'purchase_price'    => $money(),        // leeg = inkoop van de dienst
            'interval_months'   => ['type' => 'INT', 'default' => 12],
            'start_date'        => ['type' => 'DATE', 'null' => true],
            'next_invoice_date' => ['type' => 'DATE', 'null' => false],
            'end_date'          => ['type' => 'DATE', 'null' => true],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'notes'             => $text,
        ] + $timestamps);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey(['status', 'next_invoice_date']);
        $this->forge->addForeignKey('customer_id', 'customers', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('service_id', 'services', 'id', '', 'RESTRICT');
        $this->forge->createTable('subscriptions');

        // Wat er gefactureerd is (per abonnement per periode)
        $this->forge->addField($id + [
            'subscription_id'      => $fk(),
            'customer_id'          => $fk(),
            'description'          => $str(),
            'period_start'         => ['type' => 'DATE'],
            'period_end'           => ['type' => 'DATE'],
            'quantity'             => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'price'                => $money(false),
            'total'                => $money(false),
            'method'               => $str(20, false), // moneybird | manual
            'moneybird_invoice_id' => $str(32),
            'user_id'              => $fk(true),
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('subscription_id');
        $this->forge->addKey('customer_id');
        $this->forge->createTable('invoice_lines');

        // Kluis: apparaten, IP-adressen en (versleutelde) wachtwoorden
        $this->forge->addField($id + [
            'customer_id'      => $fk(),
            'site_id'          => $fk(true),
            'type'             => $str(20, false),
            'name'             => $str(255, false),
            'brand'            => $str(100),
            'model'            => $str(100),
            'serial'           => $str(100),
            'firmware'         => $str(100),
            'ip'               => $str(100),
            'port'             => $str(20),
            'mac'              => $str(50),
            'url'              => $str(),
            'username'         => $str(),
            'password_enc'     => $text,
            'secret_notes_enc' => $text,
            'notes'            => $text,
            'installed_at'     => ['type' => 'DATE', 'null' => true],
        ] + $timestamps);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('site_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', '', 'CASCADE');
        $this->forge->createTable('devices');

        // Logboek: wie heeft wanneer welk wachtwoord bekeken of gewijzigd
        $this->forge->addField($id + [
            'device_id'   => $fk(true),
            'customer_id' => $fk(true),
            'user_id'     => $fk(true),
            'action'      => $str(30, false),
            'ip'          => $str(45),
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('device_id');
        $this->forge->createTable('vault_log');
    }

    public function down(): void
    {
        foreach (['vault_log', 'devices', 'invoice_lines', 'subscriptions', 'services', 'sites', 'customers'] as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
