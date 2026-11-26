<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Coördinaten van het klantadres, voor de klantenkaart (klanten zonder locatie).
 * Locaties (sites) hebben al lat/lng/geo_query.
 */
class CustomerGeo extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('lat', 'customers')) {
            $this->forge->addColumn('customers', [
                'lat'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'lng'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'geo_query' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('customers', ['lat', 'lng', 'geo_query']);
    }
}
