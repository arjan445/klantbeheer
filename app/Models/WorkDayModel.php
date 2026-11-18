<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkDayModel extends Model
{
    protected $table         = 'work_days';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['date', 'km', 'km_manual', 'route', 'customers', 'signature', 'rate', 'amount', 'journal_id', 'booked_sig', 'booked_at', 'error'];
}
