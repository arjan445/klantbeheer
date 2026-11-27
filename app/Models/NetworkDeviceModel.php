<?php

namespace App\Models;

use CodeIgniter\Model;

class NetworkDeviceModel extends Model
{
    protected $table         = 'network_devices';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['platform', 'external_id', 'network_site_id', 'device_id', 'name', 'model', 'kind', 'mac', 'serial', 'ip', 'firmware',
        'status', 'raw_status', 'monitor', 'ignored', 'last_seen_at', 'offline_since', 'synced_at', 'gone', 'signal', 'radio', 'signal_at'];

    /**
     * Met de netwerksite, de klantlocatie en de klant erbij. Klant en locatie komen
     * van het gekoppelde apparaat, anders van de netwerksite.
     */
    public function withContext(): self
    {
        return $this->select('network_devices.*, network_sites.name AS nsite_name, network_sites.host_name, network_sites.site_id AS nsite_site_id,
                COALESCE(devices.site_id, network_sites.site_id) AS loc_id, COALESCE(devices.customer_id, sites.customer_id) AS cust_id,
                devices.name AS device_name, devices.type AS device_type, devices.customer_id AS device_customer_id')
            ->join('network_sites', 'network_sites.id = network_devices.network_site_id', 'left')
            ->join('devices', 'devices.id = network_devices.device_id', 'left')
            ->join('sites', 'sites.id = network_sites.site_id', 'left');
    }
}
