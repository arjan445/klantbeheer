<?php

namespace App\Models;

use CodeIgniter\Model;

class DeviceModel extends Model
{
    protected $table         = 'devices';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['customer_id', 'site_id', 'type', 'name', 'brand', 'model', 'serial', 'firmware', 'ip', 'port', 'mac', 'url', 'username', 'password_enc', 'secret_notes_enc', 'notes', 'installed_at', 'room'];

}
