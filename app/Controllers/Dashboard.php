<?php

namespace App\Controllers;

use App\Libraries\Billing as BillingLib;
use App\Models\CustomerModel;
use App\Models\DeviceModel;
use App\Models\SubscriptionModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $data = [
            'customers' => model(CustomerModel::class)->where('active', 1)->countAllResults(),
            'devices'   => user_can('vault.view') ? model(DeviceModel::class)->countAllResults() : null,
        ];

        if (user_can('billing.view')) {
            $subs = model(SubscriptionModel::class)->withDetails()->where('subscriptions.status', 'active')->findAll();

            $revenue = $cost = $ownCost = 0.0;
            $byType  = [];
            $own     = 0;
            foreach ($subs as $s) {
                if (! empty($s['customer_internal'])) {
                    // Eigen gebruik: geen omzet, wel kosten
                    $own++;
                    $ownCost += $s['eff_purchase'] === null ? 0.0 : BillingLib::yearly($s, 'eff_purchase');
                    continue;
                }
                $r = BillingLib::yearly($s);
                $c = $s['eff_purchase'] === null ? 0.0 : BillingLib::yearly($s, 'eff_purchase');
                $revenue += $r;
                $cost += $c;
                $byType[$s['service_type']] ??= ['count' => 0, 'qty' => 0, 'revenue' => 0.0, 'cost' => 0.0];
                $byType[$s['service_type']]['count']++;
                $byType[$s['service_type']]['qty'] += (float) $s['quantity'];
                $byType[$s['service_type']]['revenue'] += $r;
                $byType[$s['service_type']]['cost'] += $c;
            }

            $today = date('Y-m-d');
            $due   = BillingLib::due(60);

            $data += [
                'activeSubs' => count($subs) - $own,
                'ownSubs'    => $own,
                'ownCost'    => $ownCost,
                'revenue'    => $revenue,
                'cost'       => $cost,
                'byType'     => $byType,
                'upcoming'   => $due,
                'overdue'    => count(array_filter($due, static fn ($s) => $s['next_invoice_date'] <= $today)),
                'dueNow'     => count(BillingLib::due()),
            ];
        }

        return $this->page('dashboard/index', $data, 'Dashboard');
    }

    public function norights()
    {
        return $this->page('templates/norights', [], 'Geen toegang');
    }
}
