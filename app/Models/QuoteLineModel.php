<?php

namespace App\Models;

use CodeIgniter\Model;

class QuoteLineModel extends Model
{
    protected $table         = 'quote_lines';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['quote_id', 'product_id', 'type', 'description', 'quantity', 'cost_price', 'sell_price', 'sort'];
}
