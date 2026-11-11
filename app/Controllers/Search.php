<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\SiteModel;
use App\Models\SubscriptionModel;

class Search extends BaseController
{
    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));
        $data = ['q' => $q, 'customers' => [], 'sites' => [], 'devices' => [], 'subscriptions' => [], 'domains' => []];

        if (mb_strlen($q) >= 2) {
            $data['customers'] = model(CustomerModel::class)
                ->groupStart()->like('name', $q)->orLike('contact_name', $q)->orLike('email', $q)->orLike('city', $q)->orLike('phone', $q)->groupEnd()
                ->orderBy('name')->findAll(50);

            $data['sites'] = model(SiteModel::class)->select('sites.*, customers.name AS customer_name')
                ->join('customers', 'customers.id = sites.customer_id')
                ->groupStart()->like('sites.name', $q)->orLike('sites.address', $q)->orLike('sites.city', $q)->groupEnd()
                ->findAll(50);

            if (user_can('vault.view')) {
                $data['devices'] = model(DeviceModel::class)
                    ->select('devices.*, customers.name AS customer_name, sites.name AS site_name')
                    ->join('customers', 'customers.id = devices.customer_id')
                    ->join('sites', 'sites.id = devices.site_id', 'left')
                    ->groupStart()->like('devices.name', $q)->orLike('devices.ip', $q)->orLike('devices.serial', $q)
                        ->orLike('devices.mac', $q)->orLike('devices.model', $q)->orLike('devices.room', $q)->orLike('devices.username', $q)->orLike('devices.url', $q)->groupEnd()
                    ->orderBy('customers.name')->findAll(100);
            }

            if (user_can('billing.view')) {
                $data['subscriptions'] = model(SubscriptionModel::class)->withDetails()
                    ->groupStart()->like('subscriptions.label', $q)->orLike('services.name', $q)->orLike('domains.name', $q)->groupEnd()
                    ->findAll(100);
                $data['domains'] = array_values(array_filter(Domains::overview(), static fn ($d) => str_contains($d['name'], mb_strtolower($q))));
            }
        }

        // Precies één klant gevonden en verder niets: meteen openen
        $hits = count($data['customers']) + count($data['sites']) + count($data['devices']) + count($data['subscriptions']) + count($data['domains']);
        if ($hits === 1 && count($data['customers']) === 1) {
            return redirect()->to(site_url('klanten/' . $data['customers'][0]['id']));
        }

        return $this->page('search/index', $data, 'Zoeken');
    }
}
