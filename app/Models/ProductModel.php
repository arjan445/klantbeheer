<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table         = 'products';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['supplier_id', 'supplier_sku', 'sku', 'name', 'brand', 'group_name', 'list_price', 'cost_price', 'markup', 'sell_price', 'source', 'active', 'info', 'last_seen_at'];
}
