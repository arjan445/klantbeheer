<?php

namespace App\Controllers;

use App\Libraries\Setting;
use App\Libraries\Vimexx;
use App\Models\ServiceModel;
use Throwable;

/** Diensten › Prijzen Vimexx: inkoopprijzen van de Vimexx-prijzenpagina overnemen */
class VimexxPrices extends BaseController
{
    public function index()
    {
        try {
            $rows = Vimexx::fetch();
        } catch (Throwable $e) {
            return $this->page('services/vimexx', ['error' => $e->getMessage(), 'rows' => [], 'services' => [], 'suggest' => [], 'subs' => []], 'Prijzen Vimexx');
        }
        $services = model(ServiceModel::class)->where('active', 1)->orderBy('type')->orderBy('name')->findAll();
        $subs     = [];
        foreach (db_connect()->query("SELECT s.service_id, COUNT(*) AS n FROM subscriptions s JOIN services v ON v.id = s.service_id
            WHERE s.status = 'active' AND s.purchase_price IS NOT NULL AND s.purchase_price = v.purchase_price GROUP BY s.service_id")->getResultArray() as $r) {
            $subs[(int) $r['service_id']] = (int) $r['n'];
        }

        return $this->page('services/vimexx', [
            'error'    => null,
            'rows'     => $rows,
            'services' => $services,
            'suggest'  => Vimexx::suggest($services, $rows),
            'subs'     => $subs,
            'last'     => Setting::get('vimexx.importedAt'),
        ], 'Prijzen Vimexx');
    }

    public function apply()
    {
        try {
            $rows = Vimexx::fetch();
        } catch (Throwable $e) {
            return $this->back($e->getMessage(), 'error', site_url('diensten/vimexx'));
        }
        $keys    = (array) $this->request->getPost('key');
        $months  = (array) $this->request->getPost('months');
        $apply   = array_flip(array_map('strval', (array) $this->request->getPost('apply')));
        $choices = [];
        foreach ($keys as $id => $key) {
            $choices[(int) $id] = ['key' => (string) $key, 'months' => (int) ($months[$id] ?? 0), 'apply' => isset($apply[(string) $id])];
        }
        $r = Vimexx::apply($choices, $rows, (bool) $this->request->getPost('subs'));

        return $this->back($r['services'] . ' diensten bijgewerkt' . ($r['subscriptions'] ? ', en ' . $r['subscriptions'] . ' abonnementen met een eigen inkoopprijs' : '') . '. Koppelingen zijn onthouden voor de volgende keer.', 'message', site_url('diensten/vimexx'));
    }
}
