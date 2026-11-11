<?php

namespace App\Controllers;

use App\Libraries\CostPools as Pools;
use App\Libraries\Moneybird;
use App\Models\CostPoolModel;
use App\Models\ServiceModel;
use Throwable;

/**
 * Kostprijzen: inkoopcategorieën uit Moneybird verdelen over diensten.
 */
class CostPools extends BaseController
{
    public function index()
    {
        $pools = model(CostPoolModel::class)->orderBy('name')->findAll();

        return $this->page('costpools/index', [
            'pools'  => $pools,
            'calc'   => Pools::recalculate(),
            'mb'     => new Moneybird(),
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
        $db   = db_connect();
        $from = date('Y-m-d', strtotime('-12 months +1 day'));
        $data = [
            'pool'      => $pool ?? ['lookback_months' => 12, 'allocation_pct' => 100],
            'isNew'     => $pool === null,
            'services'  => model(ServiceModel::class)->orderBy('type')->orderBy('name')->findAll(),
            'suppliers' => $db->table('purchase_lines')->select('supplier_id, MAX(supplier_name) AS name, SUM(amount) AS total, COUNT(*) AS n')
                ->where('supplier_id IS NOT NULL')->where('date >=', $from)->groupBy('supplier_id')->orderBy('name')->get()->getResultArray(),
            'ledgers'   => $db->table('purchase_lines')->select('ledger_id, MAX(ledger_name) AS name, SUM(amount) AS total, COUNT(*) AS n')
                ->where('ledger_id IS NOT NULL')->where('date >=', $from)->groupBy('ledger_id')->orderBy('name')->get()->getResultArray(),
            'hasLines'  => $db->table('purchase_lines')->countAllResults() > 0,
            'mb'        => new Moneybird(),
        ];

        if (! $this->request->is('post')) {
            return $this->page('costpools/form', $data, $title);
        }

        $post = $this->nullify($this->request->getPost(['name', 'lookback_months', 'allocation_pct', 'extra_yearly', 'keywords', 'notes']));
        foreach (['allocation_pct', 'extra_yearly'] as $f) {
            if ($post[$f] !== null) {
                $post[$f] = str_replace(',', '.', $post[$f]);
            }
        }
        $rules = [
            'name'            => 'required|max_length[255]',
            'lookback_months' => 'required|is_natural_no_zero|less_than_equal_to[36]',
            'allocation_pct'  => 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
            'extra_yearly'    => 'permit_empty|decimal',
        ];
        if (! $this->validateData($post, $rules)) {
            $data['pool']       = $post + ['id' => $pool['id'] ?? null, 'ledger_account_ids' => implode(',', (array) $this->request->getPost('ledgers'))];
            $data['validation'] = true;

            return $this->page('costpools/form', $data, $title);
        }

        $digits = static fn ($list) => implode(',', array_filter(array_map(static fn ($v) => preg_replace('/\D/', '', (string) $v), (array) $list)));
        $post['ledger_account_ids'] = $digits($this->request->getPost('ledgers'));
        $post['supplier_ids']       = $digits($this->request->getPost('suppliers'));

        $model = model(CostPoolModel::class);
        if ($pool) {
            $model->update($pool['id'], $post);
            $id = $pool['id'];
        } else {
            $id = $model->insert($post);
        }

        // Diensten koppelen met gewicht
        $services = model(ServiceModel::class);
        $chosen   = array_map('intval', (array) $this->request->getPost('services'));
        $weights  = (array) $this->request->getPost('weights');
        foreach ($services->findAll() as $s) {
            $isChosen = in_array((int) $s['id'], $chosen, true);
            if ($isChosen) {
                $w = (float) str_replace(',', '.', (string) ($weights[$s['id']] ?? '1')) ?: 1.0;
                $services->update($s['id'], ['cost_pool_id' => $id, 'cost_weight' => $w]);
            } elseif ((int) $s['cost_pool_id'] === (int) $id) {
                // Losgekoppeld: inkoopprijs blijft staan, maar is weer handmatig te wijzigen
                $services->update($s['id'], ['cost_pool_id' => null, 'cost_weight' => 1]);
            }
        }

        Pools::refreshPools();
        $msg = 'Kostenpool opgeslagen.';

        return $this->back($msg, 'message', site_url('kostprijzen'));
    }

    public function sync()
    {
        try {
            $r = Pools::sync();
        } catch (Throwable $e) {
            return $this->back('Ophalen uit Moneybird mislukt: ' . $e->getMessage(), 'error');
        }

        return $this->back(sprintf('%d inkoopdocumenten (%d regels) opgehaald, %d kostenpool(s) bijgewerkt.', $r['documents'], $r['lines'], $r['pools']), 'message', site_url('kostprijzen'));
    }

    /**
     * Alle inkoopregels in de periode van de pool, met per regel aan/uit.
     */
    public function lines($id)
    {
        $pool  = $this->findOr404(CostPoolModel::class, $id);
        $lines = Pools::linesInPeriod($pool);

        if ($this->request->is('post')) {
            $checked = array_flip(array_map('strval', (array) $this->request->getPost('count')));
            $include = $exclude = [];
            foreach ($lines as $l) {
                $rule = Pools::ruleMatch($pool, $l);
                $on   = isset($checked[(string) $l['detail_id']]);
                if ($on && ! $rule) {
                    $include[] = $l['detail_id'];
                } elseif (! $on && $rule) {
                    $exclude[] = $l['detail_id'];
                }
            }
            // Keuzes van regels buiten de huidige periode bewaren
            $inPeriod = array_flip(array_column($lines, 'detail_id'));
            $keep     = static fn ($list) => array_filter(Pools::idList($list), static fn ($d) => ! isset($inPeriod[$d]));

            model(CostPoolModel::class)->update($id, [
                'include_lines' => implode(',', array_merge($keep($pool['include_lines']), $include)),
                'exclude_lines' => implode(',', array_merge($keep($pool['exclude_lines']), $exclude)),
            ]);
            Pools::refreshPools();

            return $this->back('Inkoopregels opgeslagen.', 'message', site_url('kostprijzen'));
        }

        return $this->page('costpools/lines', ['pool' => $pool, 'lines' => $lines], 'Inkoopregels: ' . $pool['name']);
    }

    public function delete($id)
    {
        $this->findOr404(CostPoolModel::class, $id);
        model(ServiceModel::class)->where('cost_pool_id', $id)->set(['cost_pool_id' => null, 'cost_weight' => 1])->update();
        model(CostPoolModel::class)->delete($id);

        return $this->back('Kostenpool verwijderd. De gekoppelde diensten houden hun laatste inkoopprijs.', 'message', site_url('kostprijzen'));
    }
}
