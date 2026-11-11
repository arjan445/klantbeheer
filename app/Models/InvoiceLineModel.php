<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceLineModel extends Model
{
    protected $table         = 'invoice_lines';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['subscription_id', 'customer_id', 'description', 'period_start', 'period_end', 'quantity', 'price', 'total', 'method', 'moneybird_invoice_id', 'user_id'];

}
