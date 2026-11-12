<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per offerteregel per stuk aangeven waar het komt (bv. 5 access points: Kantine, Kantoor, …).
 */
class QuoteLinePlacements extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('quote_lines', [
            'placements' => ['type' => 'TEXT', 'null' => true], // JSON-lijst, één plek per stuk
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('quote_lines', ['placements']);
    }
}
