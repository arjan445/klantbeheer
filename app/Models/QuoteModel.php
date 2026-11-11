<?php

namespace App\Models;

use CodeIgniter\Model;

class QuoteModel extends Model
{
    protected $table         = 'quotes';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['customer_id', 'title', 'notes', 'status', 'moneybird_estimate_id', 'moneybird_number', 'user_id'];
}
