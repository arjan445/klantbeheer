<?php

namespace App\Controllers;

use App\Models\CustomerModel;
use App\Models\DomainModel;
use App\Models\ServiceModel;
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
        $domains = model(DomainModel::class)->select('domains.*, customers.name AS customer_name, customers.internal AS customer_internal')
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

    /**
     * Snel invoeren: per regel een domein met registratie, hosting en/of een
     * extra dienst. Maakt domeinen en abonnementen in één keer aan.
     */
    public function quick()
    {
        $services = model(ServiceModel::class)->where('active', 1)->orderBy('type')->orderBy('name')->findAll();
        $data = [
            'customers' => model(CustomerModel::class)->where('active', 1)->orderBy('name')->findAll(),
            'services'  => $services,
            'byType'    => static fn (string $type) => array_values(array_filter($services, static fn ($s) => $s['type'] === $type)),
            'defaults'  => [
                'customer_id' => $this->request->getGet('klant'),
                'reg'         => $this->session->get('quick_reg'),
                'host'        => $this->session->get('quick_host'),
                'extra'       => $this->session->get('quick_extra'),
            ],
            'result'    => null,
        ];

        if (! $this->request->is('post')) {
            return $this->page('domains/quick', $data, 'Snel invoeren');
        }

        $regService   = (int) $this->request->getPost('reg_service');
        $hostService  = (int) $this->request->getPost('host_service');
        $extraService = (int) $this->request->getPost('extra_service');
        $this->session->set(['quick_reg' => $regService, 'quick_host' => $hostService, 'quick_extra' => $extraService]);

        $serviceById = array_column($services, null, 'id');
        $rows        = $this->request->getPost('rows') ?? [];
        $domains     = model(DomainModel::class);
        $subs        = model(SubscriptionModel::class);
        $created     = ['domains' => 0, 'subs' => 0];
        $messages    = [];

        foreach ((array) $rows as $row) {
            $name = DomainModel::normalize($row['domain'] ?? '');
            if ($name === '') {
                continue;
            }
            $customerId = (int) ($row['customer_id'] ?? 0);
            if ($customerId === 0 || model(CustomerModel::class)->find($customerId) === null) {
                $messages[] = $name . ': geen klant gekozen, overgeslagen.';
                continue;
            }
            if (! DomainModel::isValid($name)) {
                $messages[] = $name . ': geen geldige domeinnaam, overgeslagen.';
                continue;
            }

            $isNew  = $domains->where('name', $name)->countAllResults() === 0;
            $domain = $domains->findOrCreate($name, $customerId);
            $created['domains'] += $isNew ? 1 : 0;
            if (! $isNew && (int) $domain['customer_id'] !== $customerId) {
                $messages[] = $name . ': bestond al bij een andere klant; abonnementen zijn aan die klant gekoppeld.';
                $customerId = (int) $domain['customer_id'];
            }
            if (! empty($row['registrar']) && empty($row['reg'])) {
                $domains->update($domain['id'], ['registrar' => mb_substr(trim($row['registrar']), 0, 100)]);
            }

            $next = ! empty($row['next']) && strtotime($row['next']) ? date('Y-m-d', strtotime($row['next'])) : date('Y-m-d');

            foreach (['reg' => $regService, 'host' => $hostService, 'extra' => $extraService] as $key => $serviceId) {
                if (empty($row[$key]) || ! isset($serviceById[$serviceId])) {
                    continue;
                }
                $exists = $subs->where('domain_id', $domain['id'])->where('service_id', $serviceId)
                    ->where('status', 'active')->countAllResults();
                if ($exists) {
                    $messages[] = $name . ': ' . $serviceById[$serviceId]['name'] . ' stond er al, niet dubbel aangemaakt.';
                    continue;
                }
                $subs->insert([
                    'customer_id'       => $customerId,
                    'service_id'        => $serviceId,
                    'domain_id'         => $domain['id'],
                    'quantity'          => 1,
                    'interval_months'   => (int) $serviceById[$serviceId]['interval_months'],
                    'start_date'        => $next,
                    'next_invoice_date' => $next,
                    'status'            => 'active',
                ]);
                $created['subs']++;
            }
        }

        \App\Libraries\CostPools::recalculate();

        $msg = sprintf('%d domein(en) en %d abonnement(en) aangemaakt.', $created['domains'], $created['subs']);
        if ($messages !== []) {
            $this->session->setFlashdata('quick_notes', $messages);
        }

        return $this->back($msg, 'message', site_url('domeinen/snel' . ($this->request->getPost('default_customer') ? '?klant=' . (int) $this->request->getPost('default_customer') : '')));
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
