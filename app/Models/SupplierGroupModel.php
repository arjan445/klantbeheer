<?php

namespace App\Models;

use CodeIgniter\Model;

class SupplierGroupModel extends Model
{
    protected $table         = 'supplier_groups';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['supplier_id', 'group_name', 'discount', 'markup'];
}
