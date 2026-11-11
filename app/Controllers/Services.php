<?php

namespace App\Controllers;

use App\Models\ServiceModel;
use App\Models\CostPoolModel;
use App\Models\SubscriptionModel;

class Services extends BaseController
{
    private const FIELDS = ['name', 'type', 'unit', 'price', 'purchase_price', 'interval_months', 'supplier', 'moneybird_product_id', 'tax_rate_id', 'notes'];

    private function rules(): array
    {
        return [
            'name'            => 'required|max_length[255]',
            'type'            => 'required|in_list[' . implode(',', array_keys(service_types())) . ']',
            'price'           => 'required|decimal',
            'purchase_price'  => 'permit_empty|decimal',
            'interval_months' => 'required|is_natural_no_zero|less_than_equal_to[60]',
        ];
    }

    public function index()
    {
        $services = model(ServiceModel::class)
            ->select('services.*, (SELECT COUNT(*) FROM subscriptions WHERE subscriptions.service_id = services.id AND subscriptions.status = \'active\') AS sub_count,
                (SELECT COALESCE(SUM(quantity),0) FROM subscriptions WHERE subscriptions.service_id = services.id AND subscriptions.status = \'active\') AS qty')
            ->orderBy('active', 'DESC')->orderBy('type')->orderBy('name')->findAll();

        $pools = array_column(model(CostPoolModel::class)->findAll(), 'name', 'id');

        return $this->page('services/index', ['services' => $services, 'pools' => $pools], 'Diensten');
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit($id)
    {
        return $this->form($this->findOr404(ServiceModel::class, $id));
    }

    private function form(?array $service)
    {
        $title = $service ? 'Dienst bewerken' : 'Nieuwe dienst';

        $pool = ! empty($service['cost_pool_id']) ? model(CostPoolModel::class)->find($service['cost_pool_id']) : null;

        if (! $this->request->is('post')) {
            return $this->page('services/form', ['service' => $service, 'pool' => $pool], $title);
        }

        $post = $this->nullify($this->request->getPost(self::FIELDS));
        foreach (['price', 'purchase_price'] as $f) {
            if ($post[$f] !== null) {
                $post[$f] = str_replace(',', '.', $post[$f]);
            }
        }
        if (! $this->validateData($post, $this->rules())) {
            return $this->page('services/form', ['service' => $post + ['id' => $service['id'] ?? null, 'cost_pool_id' => $service['cost_pool_id'] ?? null], 'pool' => $pool, 'validation' => true], $title);
        }
        $post['active'] = $this->request->getPost('active') ? 1 : 0;
        $post['unit'] ??= service_types()[$post['type']][1];

        if ($service && ! empty($service['cost_pool_id'])) {
            unset($post['purchase_price']); // komt uit de kostenpool
        }

        if ($service) {
            model(ServiceModel::class)->update($service['id'], $post);
        } else {
            model(ServiceModel::class)->insert($post);
        }
        \App\Libraries\CostPools::recalculate();

        return $this->back('Dienst opgeslagen.', 'message', site_url('diensten'));
    }

    public function delete($id)
    {
        $this->findOr404(ServiceModel::class, $id);
        if (model(SubscriptionModel::class)->where('service_id', $id)->countAllResults() > 0) {
            return $this->back('Deze dienst wordt nog gebruikt in abonnementen. Zet hem op inactief in plaats van te verwijderen.', 'error');
        }
        model(ServiceModel::class)->delete($id);

        return $this->back('Dienst verwijderd.', 'message', site_url('diensten'));
    }
}
