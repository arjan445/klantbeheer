<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table         = 'services';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['name', 'type', 'unit', 'price', 'purchase_price', 'interval_months', 'supplier', 'moneybird_product_id', 'tax_rate_id', 'active', 'notes', 'cost_pool_id', 'cost_weight'];

}
