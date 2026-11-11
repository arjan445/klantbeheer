<?php

namespace App\Controllers;

use App\Libraries\Billing as BillingLib;
use App\Libraries\Moneybird;
use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\InvoiceLineModel;
use App\Models\SiteModel;
use App\Models\SubscriptionModel;

class Customers extends BaseController
{
    private const FIELDS = ['name', 'contact_name', 'email', 'phone', 'address', 'zipcode', 'city', 'moneybird_contact_id', 'notes'];

    private const RULES = [
        'name'                 => 'required|max_length[255]',
        'email'                => 'permit_empty|valid_email',
        'moneybird_contact_id' => 'permit_empty|numeric|max_length[32]',
    ];

    public function index()
    {
        $db = db_connect();
        $customers = model(CustomerModel::class)
            ->select('customers.*,
                (SELECT COUNT(*) FROM sites WHERE sites.customer_id = customers.id) AS site_count,
                (SELECT COUNT(*) FROM subscriptions WHERE subscriptions.customer_id = customers.id AND subscriptions.status = \'active\') AS sub_count,
                (SELECT COUNT(*) FROM devices WHERE devices.customer_id = customers.id) AS device_count')
            ->orderBy('active', 'DESC')->orderBy('name')
            ->findAll();

        return $this->page('customers/index', ['customers' => $customers], 'Klanten');
    }

    public function show($id)
    {
        $customer = $this->findOr404(CustomerModel::class, $id);

        $data = [
            'customer' => $customer,
            'sites'    => model(SiteModel::class)->where('customer_id', $id)->orderBy('name')->findAll(),
            'mb'       => new Moneybird(),
        ];

        if (user_can('billing.view')) {
            $data['subscriptions'] = model(SubscriptionModel::class)->withDetails()
                ->where('subscriptions.customer_id', $id)
                ->orderBy('subscriptions.status')->orderBy('services.type')->orderBy('subscriptions.label')
                ->findAll();
            $data['yearly'] = array_sum(array_map(
                static fn ($s) => $s['status'] === 'active' ? BillingLib::yearly($s) : 0,
                $data['subscriptions']
            ));
            $data['domains']  = Domains::overview((int) $id);
            $data['invoices'] = model(InvoiceLineModel::class)->where('customer_id', $id)
                ->orderBy('created_at', 'DESC')->limit(100)->findAll();
        }

        if (user_can('vault.view')) {
            $data['devices'] = model(DeviceModel::class)->where('customer_id', $id)
                ->orderBy('site_id')->orderBy('type')->orderBy('name')->findAll();
        }

        return $this->page('customers/show', $data, $customer['name']);
    }

    public function create()
    {
        if (! $this->request->is('post')) {
            return $this->page('customers/form', ['customer' => null], 'Nieuwe klant');
        }

        $post = $this->nullify($this->request->getPost(self::FIELDS));
        if (! $this->validateData($post, self::RULES)) {
            return $this->page('customers/form', ['customer' => $post, 'validation' => true], 'Nieuwe klant');
        }

        $post['active'] = $this->request->getPost('active') ? 1 : 0;
        $id = model(CustomerModel::class)->insert($post);

        // Meteen een eerste locatie op het klantadres
        if ($this->request->getPost('create_site')) {
            model(SiteModel::class)->insert([
                'customer_id' => $id,
                'name'        => 'Hoofdlocatie',
                'address'     => $post['address'],
                'zipcode'     => $post['zipcode'],
                'city'        => $post['city'],
            ]);
        }

        return $this->back('Klant aangemaakt.', 'message', site_url('klanten/' . $id));
    }

    public function edit($id)
    {
        $customer = $this->findOr404(CustomerModel::class, $id);

        if (! $this->request->is('post')) {
            return $this->page('customers/form', ['customer' => $customer], 'Klant bewerken');
        }

        $post = $this->nullify($this->request->getPost(self::FIELDS));
        if (! $this->validateData($post, self::RULES)) {
            return $this->page('customers/form', ['customer' => $post + ['id' => $id], 'validation' => true], 'Klant bewerken');
        }

        $post['active'] = $this->request->getPost('active') ? 1 : 0;
        model(CustomerModel::class)->update($id, $post);

        return $this->back('Klant opgeslagen.', 'message', site_url('klanten/' . $id));
    }

    public function delete($id)
    {
        $customer = $this->findOr404(CustomerModel::class, $id);

        $used = model(SubscriptionModel::class)->where('customer_id', $id)->countAllResults()
            + model(DeviceModel::class)->where('customer_id', $id)->countAllResults()
            + model(InvoiceLineModel::class)->where('customer_id', $id)->countAllResults();

        if ($used > 0) {
            return $this->back('Deze klant heeft nog abonnementen, apparaten of factuurhistorie. Zet de klant op inactief in plaats van te verwijderen.', 'error');
        }

        model(SiteModel::class)->where('customer_id', $id)->delete();
        model(\App\Models\DomainModel::class)->where('customer_id', $id)->delete();
        model(CustomerModel::class)->delete($id);

        return $this->back('Klant ' . $customer['name'] . ' verwijderd.', 'message', site_url('klanten'));
    }
}
