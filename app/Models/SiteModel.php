<?php

namespace App\Models;

use CodeIgniter\Model;

class SiteModel extends Model
{
    protected $table         = 'sites';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['customer_id', 'name', 'address', 'zipcode', 'city', 'notes'];

}
