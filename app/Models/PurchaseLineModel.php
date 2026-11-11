<?php

namespace App\Models;

use CodeIgniter\Model;

class PurchaseLineModel extends Model
{
    protected $table         = 'purchase_lines';
    protected $returnType    = 'array';
    protected $allowedFields = ['detail_id', 'document_id', 'kind', 'date', 'reference', 'supplier_id', 'supplier_name',
        'ledger_id', 'ledger_name', 'description', 'amount'];
}
