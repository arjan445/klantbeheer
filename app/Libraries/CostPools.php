<?php

namespace App\Libraries;

use App\Models\CostItemModel;
use App\Models\CostPoolModel;
use App\Models\ServiceModel;

/**
 * Kostprijs per dienst.
 *
 * Een kostenpool (bijv. "Hostingplatform") bestaat uit kostenposten die je zelf
 * invult: leverancier, bedrag, per maand of per jaar (Plesk-licentie, VPS,
 * Imunify, ...). De jaarkosten worden verdeeld over alle actieve abonnementen
 * van de gekoppelde diensten, naar aantal x gewicht. De uitkomst wordt als
 * inkoopprijs bij de dienst opgeslagen, zodat de marges vanzelf kloppen.
 */
class CostPools
{
    public static function itemYearly(array $item): float
    {
        return (float) $item['amount'] * ($item['period'] === 'year' ? 1 : 12);
    }

    public static function items(int $poolId): array
    {
        return model(CostItemModel::class)->where('cost_pool_id', $poolId)->orderBy('sort')->orderBy('id')->findAll();
    }

    /** Jaarkosten van een pool */
    public static function yearlyCost(int $poolId): float
    {
        return array_sum(array_map([self::class, 'itemYearly'], self::items($poolId)));
    }

    /**
     * Verdeelt de jaarkosten van elke pool over de gekoppelde diensten en
     * slaat de inkoopprijs (per eenheid, per factuurperiode) op bij de dienst.
     *
     * @return array<int, array> per pool: yearly, units, per_unit_year, services
     */
    public static function recalculate(): array
    {
        $db       = db_connect();
        $result   = [];
        $services = model(ServiceModel::class);

        foreach (model(CostPoolModel::class)->findAll() as $pool) {
            $linked = $db->table('services')
                ->select('services.id, services.name, services.interval_months, services.cost_weight, services.price, services.unit,
                    COALESCE(SUM(CASE WHEN subscriptions.status = \'active\' THEN subscriptions.quantity ELSE 0 END), 0) AS units,
                    COALESCE(SUM(CASE WHEN subscriptions.status = \'active\' AND customers.internal = 1 THEN subscriptions.quantity ELSE 0 END), 0) AS own_units')
                ->join('subscriptions', 'subscriptions.service_id = services.id', 'left')
                ->join('customers', 'customers.id = subscriptions.customer_id', 'left')
                ->where('services.cost_pool_id', $pool['id'])
                ->groupBy('services.id, services.name, services.interval_months, services.cost_weight, services.price, services.unit')
                ->get()->getResultArray();

            $weighted = 0.0;
            foreach ($linked as $s) {
                $weighted += (float) $s['units'] * max(0.01, (float) $s['cost_weight']);
            }

            $yearly  = self::yearlyCost((int) $pool['id']);
            $perUnit = $weighted > 0 ? $yearly / $weighted : null; // per gewicht-eenheid per jaar
            $out     = [];

            foreach ($linked as $s) {
                $unitYear = $perUnit === null ? null : $perUnit * max(0.01, (float) $s['cost_weight']);
                $purchase = $unitYear === null ? null : round($unitYear * max(1, (int) $s['interval_months']) / 12, 2);
                $services->update($s['id'], ['purchase_price' => $purchase]);
                $out[] = $s + ['unit_year' => $unitYear, 'purchase' => $purchase];
            }

            $result[$pool['id']] = ['yearly' => $yearly, 'units' => $weighted, 'per_unit_year' => $perUnit, 'services' => $out];
        }

        return $result;
    }
}
