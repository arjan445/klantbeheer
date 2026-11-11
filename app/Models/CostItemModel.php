<?php

namespace App\Models;

use CodeIgniter\Model;

class CostItemModel extends Model
{
    protected $table         = 'cost_items';
    protected $returnType    = 'array';
    protected $allowedFields = ['cost_pool_id', 'supplier', 'description', 'amount', 'period', 'sort'];
}
