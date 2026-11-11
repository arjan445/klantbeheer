<?php

namespace App\Models;

use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table         = 'customers';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['name', 'contact_name', 'email', 'phone', 'address', 'zipcode', 'city', 'moneybird_contact_id', 'notes', 'active', 'internal'];

}
