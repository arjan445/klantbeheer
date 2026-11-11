<?php

namespace App\Models;

use CodeIgniter\Model;

class DsdItemModel extends Model
{
    protected $table         = 'dsd_items';
    protected $returnType    = 'array';
    protected $allowedFields = ['item_key', 'contract_id', 'dsd_client_id', 'client_name', 'sku', 'product_name', 'brand',
        'quantity', 'unit', 'price', 'advisory_price', 'invoicing_period', 'commitment_term', 'renewal_date', 'status',
        'sale_reference', 'synced_at'];
}
