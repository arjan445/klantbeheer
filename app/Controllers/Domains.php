<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\DomainModel;
use App\Models\SubscriptionModel;

/**
 * Domeinnamen en wat eraan hangt: registratie, hosting en overige diensten.
 */
class Domains extends BaseController
{
    /** Statussen: sleutel => [label, kleur] */
    public const STATUSES = [
        'both'      => ['Registratie + hosting', 'success'],
        'reg_only'  => ['Alleen registratie', 'warning'],
        'host_only' => ['Hosting, domein extern', 'info'],
        'none'      => ['Niets actief', 'secondary'],
    ];

    /**
     * Domeinen met hun actieve abonnementen, ingedeeld naar soort.
     *
     * @return list<array>
     */
    public static function overview(?int $customerId = null): array
    {
        $domains = model(DomainModel::class)->select('domains.*, customers.name AS customer_name')
            ->join('customers', 'customers.id = domains.customer_id');
        if ($customerId !== null) {
            $domains->where('domains.customer_id', $customerId);
        }
        $domains = $domains->orderBy('domains.name')->findAll();

        $subs = model(SubscriptionModel::class)->withDetails()
            ->where('subscriptions.domain_id IS NOT NULL')
            ->where('subscriptions.status', 'active');
        if ($customerId !== null) {
            $subs->where('subscriptions.customer_id', $customerId);
        }
        $byDomain = [];
        foreach ($subs->findAll() as $s) {
            $byDomain[$s['domain_id']][] = $s;
        }

        foreach ($domains as &$d) {
            $list = $byDomain[$d['id']] ?? [];
            $d['registration'] = array_values(array_filter($list, static fn ($s) => $s['service_type'] === 'domain'));
            $d['hosting']      = array_values(array_filter($list, static fn ($s) => $s['service_type'] === 'hosting'));
            $d['other']        = array_values(array_filter($list, static fn ($s) => ! in_array($s['service_type'], ['domain', 'hosting'], true)));
            $d['yearly']       = array_sum(array_map(static fn ($s) => \App\Libraries\Billing::yearly($s), $list));
            $d['status']       = match (true) {
                $d['registration'] !== [] && $d['hosting'] !== [] => 'both',
                $d['registration'] !== []                         => 'reg_only',
                $d['hosting'] !== []                              => 'host_only',
                default                                           => 'none',
            };
        }

        return $domains;
    }

    public function index()
    {
        $status  = $this->request->getGet('status');
        $domains = self::overview();
        $counts  = array_count_values(array_column($domains, 'status'));
        if ($status && isset(self::STATUSES[$status])) {
            $domains = array_values(array_filter($domains, static fn ($d) => $d['status'] === $status));
        }

        return $this->page('domains/index', ['domains' => $domains, 'status' => $status, 'counts' => $counts], 'Domeinen');
    }

    public function create()
    {
        return $this->form(null, ['customer_id' => $this->request->getGet('klant')]);
    }

    public function edit($id)
    {
        $domain = $this->findOr404(DomainModel::class, $id);

        return $this->form($domain, $domain);
    }

    private function form(?array $existing, array $values)
    {
        $title = $existing ? $existing['name'] : 'Nieuw domein';
        $data  = [
            'domain'    => $values,
            'isNew'     => $existing === null,
            'customers' => model(CustomerModel::class)->orderBy('name')->findAll(),
            'subs'      => $existing ? model(SubscriptionModel::class)->withDetails()
                ->where('subscriptions.domain_id', $existing['id'])
                ->orderBy('subscriptions.status')->orderBy('services.type')->findAll() : [],
        ];

        if (! $this->request->is('post')) {
            return $this->page('domains/form', $data, $title);
        }

        $post         = $this->nullify($this->request->getPost(['customer_id', 'name', 'registrar', 'notes']));
        $post['name'] = DomainModel::normalize($post['name']);

        $unique = 'is_unique[domains.name' . ($existing ? ',id,' . $existing['id'] : '') . ']';
        $valid  = $this->validateData($post, [
            'customer_id' => 'required|is_natural_no_zero|is_not_unique[customers.id]',
            'name'        => 'required|max_length[255]|' . $unique,
        ], ['name' => ['is_unique' => 'Dit domein staat er al in.']]);

        if ($valid && ! DomainModel::isValid($post['name'])) {
            $valid = false;
            $this->session->setFlashdata('error', 'Dat lijkt geen geldige domeinnaam (bijv. klant.nl).');
        }

        if (! $valid) {
            $data['domain']     = $post + ['id' => $existing['id'] ?? null];
            $data['validation'] = true;

            return $this->page('domains/form', $data, $title);
        }

        $model = model(DomainModel::class);
        if ($existing) {
            $model->update($existing['id'], $post);
            // Verhuisd naar een andere klant: abonnementen niet stilletjes meeverhuizen, wel melden
        } else {
            $model->insert($post);
        }

        return $this->back('Domein opgeslagen.', 'message', $this->request->getGet('klant') || $existing === null
            ? site_url('klanten/' . $post['customer_id'])
            : site_url('domeinen'));
    }

    public function delete($id)
    {
        $domain = $this->findOr404(DomainModel::class, $id);
        if (model(SubscriptionModel::class)->where('domain_id', $id)->countAllResults() > 0) {
            return $this->back('Er hangen nog abonnementen aan dit domein. Koppel of verwijder die eerst.', 'error');
        }
        model(DomainModel::class)->delete($id);

        return $this->back('Domein ' . $domain['name'] . ' verwijderd.', 'message', site_url('domeinen'));
    }
}
