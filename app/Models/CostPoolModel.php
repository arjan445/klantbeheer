<?php

namespace App\Models;

use CodeIgniter\Model;

class CostPoolModel extends Model
{
    protected $table         = 'cost_pools';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'ledger_account_ids', 'lookback_months', 'allocation_pct', 'extra_yearly',
        'synced_amount', 'synced_from', 'synced_to', 'synced_at', 'breakdown', 'notes',
        'supplier_ids', 'keywords', 'include_lines', 'exclude_lines'];
}
