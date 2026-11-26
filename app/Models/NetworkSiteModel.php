<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkSiteModel extends Model
{
    protected $table         = 'network_sites';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['platform', 'external_id', 'host_id', 'host_name', 'name', 'description', 'site_id', 'ignored', 'device_count', 'offline_count', 'last_synced_at', 'fabric', 'vpn', 'vpn_at', 'merged_into'];
}
