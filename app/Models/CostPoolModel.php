<?php

namespace App\Models;

use CodeIgniter\Model;

class CostPoolModel extends Model
{
    protected $table         = 'cost_pools';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name', 'notes'];
}
