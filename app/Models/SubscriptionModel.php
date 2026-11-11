<?php

namespace App\Models;

use CodeIgniter\Model;

class SubscriptionModel extends Model
{
    protected $table         = 'subscriptions';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['customer_id', 'service_id', 'site_id', 'label', 'quantity', 'price', 'purchase_price', 'interval_months', 'start_date', 'next_invoice_date', 'end_date', 'status', 'notes', 'domain_id'];

    /**
     * Abonnementen met dienst- en klantgegevens en de effectieve prijzen.
     */
    public function withDetails(): self
    {
        return $this->select('subscriptions.*, services.name AS service_name, services.type AS service_type, services.unit AS service_unit,
                services.moneybird_product_id, services.tax_rate_id,
                COALESCE(subscriptions.price, services.price) AS eff_price,
                COALESCE(subscriptions.purchase_price, services.purchase_price) AS eff_purchase,
                customers.name AS customer_name, customers.moneybird_contact_id, sites.name AS site_name, domains.name AS domain_name')
            ->join('services', 'services.id = subscriptions.service_id')
            ->join('customers', 'customers.id = subscriptions.customer_id')
            ->join('sites', 'sites.id = subscriptions.site_id', 'left')
            ->join('domains', 'domains.id = subscriptions.domain_id', 'left');
    }
}
