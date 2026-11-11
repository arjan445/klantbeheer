<?php

namespace App\Models;

use CodeIgniter\Model;

class VaultLogModel extends Model
{
    protected $table         = 'vault_log';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['device_id', 'customer_id', 'user_id', 'action', 'ip'];

}
