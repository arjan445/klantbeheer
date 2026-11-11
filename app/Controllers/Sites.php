<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\SiteModel;
use App\Models\SubscriptionModel;

class Sites extends BaseController
{
    private const FIELDS = ['name', 'address', 'zipcode', 'city', 'notes'];

    public function create($customerId)
    {
        $customer = $this->findOr404(CustomerModel::class, $customerId);

        if (! $this->request->is('post')) {
            return $this->page('sites/form', ['site' => null, 'customer' => $customer], 'Nieuwe locatie');
        }

        $post = $this->nullify($this->request->getPost(self::FIELDS));
        if (! $this->validateData($post, ['name' => 'required|max_length[255]'])) {
            return $this->page('sites/form', ['site' => $post, 'customer' => $customer, 'validation' => true], 'Nieuwe locatie');
        }

        model(SiteModel::class)->insert($post + ['customer_id' => $customer['id']]);

        return $this->back('Locatie toegevoegd.', 'message', site_url('klanten/' . $customer['id']));
    }

    public function edit($id)
    {
        $site     = $this->findOr404(SiteModel::class, $id);
        $customer = $this->findOr404(CustomerModel::class, $site['customer_id']);

        if (! $this->request->is('post')) {
            return $this->page('sites/form', ['site' => $site, 'customer' => $customer], 'Locatie bewerken');
        }

        $post = $this->nullify($this->request->getPost(self::FIELDS));
        if (! $this->validateData($post, ['name' => 'required|max_length[255]'])) {
            return $this->page('sites/form', ['site' => $post + ['id' => $id], 'customer' => $customer, 'validation' => true], 'Locatie bewerken');
        }

        model(SiteModel::class)->update($id, $post);

        return $this->back('Locatie opgeslagen.', 'message', site_url('klanten/' . $customer['id']));
    }

    public function delete($id)
    {
        $site = $this->findOr404(SiteModel::class, $id);

        $used = model(DeviceModel::class)->where('site_id', $id)->countAllResults()
            + model(SubscriptionModel::class)->where('site_id', $id)->countAllResults();
        if ($used > 0) {
            return $this->back('Op deze locatie staan nog apparaten of abonnementen. Verplaats of verwijder die eerst.', 'error');
        }

        model(SiteModel::class)->delete($id);

        return $this->back('Locatie verwijderd.', 'message', site_url('klanten/' . $site['customer_id']));
    }
}
