<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkLinkModel extends Model
{
    protected $table         = 'network_links';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['network_device_id', 'parent_id', 'parent_port', 'local_port', 'link_type', 'locked',
        'auto_parent_id', 'auto_parent_port', 'auto_link_type', 'auto_seen_at', 'view_site_id', 'note'];
}
