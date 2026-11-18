<?php

namespace App\Models;

use CodeIgniter\Model;

class TimeEntryModel extends Model
{
    protected $table         = 'time_entries';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['customer_id', 'site_id', 'date', 'start_time', 'end_time', 'pause_minutes', 'description', 'billable', 'travel', 'moneybird_id', 'sync_error', 'user_id'];

    /** Netto minuten (stop - start - pauze) */
    public static function minutes(array $e): int
    {
        $s = strtotime('1970-01-01 ' . $e['start_time'] . ' UTC');
        $t = strtotime('1970-01-01 ' . $e['end_time'] . ' UTC');
        if ($t <= $s) {
            $t += 86400; // over middernacht
        }

        return max(0, (int) (($t - $s) / 60) - (int) $e['pause_minutes']);
    }
}
