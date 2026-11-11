<?php

namespace App\Controllers;

use App\Libraries\CostPools as Pools;
use App\Models\CostItemModel;
use App\Models\CostPoolModel;
use App\Models\ServiceModel;

/**
 * Kostprijzen: kostenposten per leverancier (per maand of per jaar)
 * verdelen over diensten.
 */
class CostPools extends BaseController
{
    public function index()
    {
        $pools = model(CostPoolModel::class)->orderBy('name')->findAll();
        $items = [];
        foreach ($pools as $p) {
            $items[$p['id']] = Pools::items((int) $p['id']);
        }

        return $this->page('costpools/index', [
            'pools' => $pools,
            'items' => $items,
            'calc'  => Pools::recalculate(),
        ], 'Kostprijzen');
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit($id)
    {
        return $this->form($this->findOr404(CostPoolModel::class, $id));
    }

    private function form(?array $pool)
    {
        $title = $pool ? 'Kostenpool bewerken' : 'Nieuwe kostenpool';
        $data  = [
            'pool'     => $pool ?? [],
            'isNew'    => $pool === null,
            'items'    => $pool ? Pools::items((int) $pool['id']) : [],
            'services' => model(ServiceModel::class)->orderBy('type')->orderBy('name')->findAll(),
        ];

        if (! $this->request->is('post')) {
            return $this->page('costpools/form', $data, $title);
        }

        $post = $this->nullify($this->request->getPost(['name', 'notes']));
        $items = $this->postedItems();

        if (! $this->validateData($post, ['name' => 'required|max_length[255]'])) {
            $data['pool']       = $post + ['id' => $pool['id'] ?? null];
            $data['items']      = $items;
            $data['validation'] = true;

            return $this->page('costpools/form', $data, $title);
        }

        $model = model(CostPoolModel::class);
        if ($pool) {
            $model->update($pool['id'], $post);
            $id = (int) $pool['id'];
        } else {
            $id = (int) $model->insert($post);
        }

        // Kostenposten vervangen
        $itemModel = model(CostItemModel::class);
        $itemModel->where('cost_pool_id', $id)->delete();
        foreach ($items as $i => $item) {
            $itemModel->insert($item + ['cost_pool_id' => $id, 'sort' => $i]);
        }

        // Diensten koppelen met gewicht
        $services = model(ServiceModel::class);
        $chosen   = array_map('intval', (array) $this->request->getPost('services'));
        $weights  = (array) $this->request->getPost('weights');
        foreach ($services->findAll() as $s) {
            if (in_array((int) $s['id'], $chosen, true)) {
                $w = (float) str_replace(',', '.', (string) ($weights[$s['id']] ?? '1')) ?: 1.0;
                $services->update($s['id'], ['cost_pool_id' => $id, 'cost_weight' => $w]);
            } elseif ((int) $s['cost_pool_id'] === $id) {
                // Losgekoppeld: inkoopprijs blijft staan, maar is weer handmatig te wijzigen
                $services->update($s['id'], ['cost_pool_id' => null, 'cost_weight' => 1]);
            }
        }

        Pools::recalculate();

        return $this->back('Kostenpool opgeslagen.', 'message', site_url('kostprijzen'));
    }

    /** Kostenposten uit het formulier; lege regels worden overgeslagen */
    private function postedItems(): array
    {
        $supplier = (array) $this->request->getPost('item_supplier');
        $desc     = (array) $this->request->getPost('item_description');
        $amount   = (array) $this->request->getPost('item_amount');
        $period   = (array) $this->request->getPost('item_period');

        $items = [];
        foreach ($supplier as $i => $sup) {
            $sup = trim((string) $sup);
            $amt = trim(str_replace(',', '.', (string) ($amount[$i] ?? '')));
            if ($sup === '' && $amt === '') {
                continue;
            }
            $items[] = [
                'supplier'    => $sup !== '' ? mb_substr($sup, 0, 255) : 'Onbekend',
                'description' => trim((string) ($desc[$i] ?? '')) ?: null,
                'amount'      => is_numeric($amt) ? round((float) $amt, 2) : 0,
                'period'      => ($period[$i] ?? 'month') === 'year' ? 'year' : 'month',
            ];
        }

        return $items;
    }

    public function delete($id)
    {
        $this->findOr404(CostPoolModel::class, $id);
        model(ServiceModel::class)->where('cost_pool_id', $id)->set(['cost_pool_id' => null, 'cost_weight' => 1])->update();
        model(CostItemModel::class)->where('cost_pool_id', $id)->delete();
        model(CostPoolModel::class)->delete($id);

        return $this->back('Kostenpool verwijderd. De gekoppelde diensten houden hun laatste inkoopprijs.', 'message', site_url('kostprijzen'));
    }
}
