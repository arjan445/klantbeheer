<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\InvoiceLineModel;
use App\Models\ServiceModel;
use App\Models\SiteModel;
use App\Models\SubscriptionModel;

class Subscriptions extends BaseController
{
    private const FIELDS = ['customer_id', 'service_id', 'site_id', 'label', 'quantity', 'price', 'purchase_price', 'interval_months', 'start_date', 'next_invoice_date', 'end_date', 'status', 'notes'];

    private const RULES = [
        'customer_id'       => 'required|is_natural_no_zero|is_not_unique[customers.id]',
        'service_id'        => 'required|is_natural_no_zero|is_not_unique[services.id]',
        'site_id'           => 'permit_empty|is_natural_no_zero',
        'quantity'          => 'required|decimal|greater_than[0]',
        'price'             => 'permit_empty|decimal',
        'purchase_price'    => 'permit_empty|decimal',
        'interval_months'   => 'required|is_natural_no_zero|less_than_equal_to[60]',
        'start_date'        => 'permit_empty|valid_date[Y-m-d]',
        'next_invoice_date' => 'required|valid_date[Y-m-d]',
        'end_date'          => 'permit_empty|valid_date[Y-m-d]',
        'status'            => 'required|in_list[active,cancelled]',
    ];

    public function index()
    {
        $status = $this->request->getGet('status') ?? 'active';
        $type   = $this->request->getGet('soort');

        $model = model(SubscriptionModel::class)->withDetails()
            ->orderBy('customers.name')->orderBy('services.type')->orderBy('subscriptions.label');
        if ($status !== 'all') {
            $model->where('subscriptions.status', $status);
        }
        if ($type) {
            $model->where('services.type', $type);
        }

        return $this->page('subscriptions/index', [
            'subscriptions' => $model->findAll(),
            'status'        => $status,
            'type'          => $type,
        ], 'Abonnementen');
    }

    public function create()
    {
        $defaults = [
            'customer_id'       => $this->request->getGet('klant'),
            'quantity'          => 1,
            'interval_months'   => 12,
            'start_date'        => date('Y-m-d'),
            'next_invoice_date' => date('Y-m-d'),
            'status'            => 'active',
        ];

        return $this->form(null, $defaults);
    }

    public function edit($id)
    {
        $sub = $this->findOr404(SubscriptionModel::class, $id);

        return $this->form($sub, $sub);
    }

    private function form(?array $existing, array $values)
    {
        $title = $existing ? 'Abonnement bewerken' : 'Nieuw abonnement';
        $data  = [
            'sub'       => $values,
            'isNew'     => $existing === null,
            'customers' => model(CustomerModel::class)->where('active', 1)->orderBy('name')->findAll(),
            'services'  => model(ServiceModel::class)->where('active', 1)->orWhere('id', $values['service_id'] ?? 0)->orderBy('type')->orderBy('name')->findAll(),
            'sites'     => model(SiteModel::class)->orderBy('name')->findAll(),
            'history'   => $existing ? model(InvoiceLineModel::class)->where('subscription_id', $existing['id'])->orderBy('period_start', 'DESC')->findAll() : [],
        ];

        if (! $this->request->is('post')) {
            return $this->page('subscriptions/form', $data, $title);
        }

        $post = $this->nullify($this->request->getPost(self::FIELDS));
        foreach (['quantity', 'price', 'purchase_price'] as $f) {
            if ($post[$f] !== null) {
                $post[$f] = str_replace(',', '.', $post[$f]);
            }
        }

        $valid = $this->validateData($post, self::RULES);

        // Locatie moet bij de klant horen
        if ($valid && $post['site_id'] !== null) {
            $site = model(SiteModel::class)->find($post['site_id']);
            if ($site === null || (int) $site['customer_id'] !== (int) $post['customer_id']) {
                $post['site_id'] = null;
            }
        }

        if (! $valid) {
            $data['sub'] = $post + ['id' => $existing['id'] ?? null];
            $data['validation'] = true;

            return $this->page('subscriptions/form', $data, $title);
        }

        if ($post['status'] === 'cancelled' && $post['end_date'] === null) {
            $post['end_date'] = date('Y-m-d', strtotime($post['next_invoice_date'] . ' -1 day'));
        }

        if ($existing) {
            model(SubscriptionModel::class)->update($existing['id'], $post);
        } else {
            model(SubscriptionModel::class)->insert($post);
        }

        $to = $this->request->getPost('return') === 'customer'
            ? site_url('klanten/' . $post['customer_id'])
            : site_url('abonnementen');

        return $this->back('Abonnement opgeslagen.', 'message', $to);
    }

    public function delete($id)
    {
        $sub = $this->findOr404(SubscriptionModel::class, $id);
        if (model(InvoiceLineModel::class)->where('subscription_id', $id)->countAllResults() > 0) {
            return $this->back('Er is al gefactureerd op dit abonnement. Zet het op "opgezegd" in plaats van te verwijderen.', 'error');
        }
        model(SubscriptionModel::class)->delete($id);

        return $this->back('Abonnement verwijderd.', 'message', site_url('klanten/' . $sub['customer_id']));
    }
}
