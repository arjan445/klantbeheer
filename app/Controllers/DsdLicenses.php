<?php

namespace App\Controllers;

use App\Libraries\CostPools;
use App\Libraries\Dsd;
use App\Models\CustomerModel;
use App\Models\DsdItemModel;
use App\Models\ServiceModel;
use App\Models\SubscriptionModel;
use Throwable;

/**
 * Licenties uit DSD Europe naast de abonnementen in Klantbeheer.
 */
class DsdLicenses extends BaseController
{
    public const CHECKS = [
        'under'       => ['Te weinig gefactureerd', 'danger'],
        'over'        => ['Meer gefactureerd dan bij DSD', 'warning'],
        'no_sub'      => ['Nog niet gekoppeld aan abonnement', 'secondary'],
        'no_customer' => ['Eindklant niet gekoppeld', 'secondary'],
        'ok'          => ['Klopt', 'success'],
        'own'         => ['Eigen gebruik', 'info'],
    ];

    public function index()
    {
        $filter = $this->request->getGet('filter');
        $items  = Dsd::overview();
        $counts = array_count_values(array_column($items, 'check'));
        if ($filter === 'issues') {
            $items = array_values(array_filter($items, static fn ($i) => in_array($i['check'], ['under', 'over', 'no_sub', 'no_customer'], true)));
        } elseif ($filter && isset(self::CHECKS[$filter])) {
            $items = array_values(array_filter($items, static fn ($i) => $i['check'] === $filter));
        }

        // Per DSD-eindklant groeperen
        $groups = [];
        foreach ($items as $i) {
            $key = $i['dsd_client_id'] ?? 'own';
            $groups[$key]['name']     = $i['client_name'] ?: ('Eindklant ' . $i['dsd_client_id']);
            $groups[$key]['client']   = $i['dsd_client_id'];
            $groups[$key]['customer'] = $i['customer'];
            $groups[$key]['items'][]  = $i;
        }

        $customers = model(CustomerModel::class)->orderBy('name')->findAll();
        $subsByCustomer = [];
        foreach (model(SubscriptionModel::class)->withDetails()->where('subscriptions.status', 'active')->orderBy('services.name')->findAll() as $s) {
            $subsByCustomer[$s['customer_id']][] = $s;
        }

        return $this->page('dsd/index', [
            'dsd'            => new Dsd(),
            'groups'         => $groups,
            'counts'         => $counts,
            'filter'         => $filter,
            'customers'      => $customers,
            'subsByCustomer' => $subsByCustomer,
            'services'       => model(ServiceModel::class)->where('active', 1)->orderBy('type')->orderBy('name')->findAll(),
            'lastSync'       => model(DsdItemModel::class)->selectMax('synced_at')->first()['synced_at'] ?? null,
        ], 'DSD-licenties');
    }

    public function sync()
    {
        try {
            $r = (new Dsd())->sync();
        } catch (Throwable $e) {
            return $this->back('Ophalen uit DSD mislukt: ' . $e->getMessage(), 'error');
        }
        CostPools::recalculate();

        return $this->back(sprintf('%d licentieregels opgehaald van %d eindklanten. %d eindklant(en) automatisch gekoppeld op naam, %d inkoopprijs/prijzen bijgewerkt.',
            $r['items'], $r['clients'], $r['matched'], $r['purchase']), 'message', site_url('dsd'));
    }

    /** DSD-eindklant koppelen aan een klant (of een nieuwe klant aanmaken) */
    public function linkClient()
    {
        $clientId   = preg_replace('/\D/', '', (string) $this->request->getPost('dsd_client_id'));
        $customerId = $this->request->getPost('customer_id');
        $customers  = model(CustomerModel::class);
        if ($clientId === '') {
            return $this->back('Geen DSD-eindklant opgegeven.', 'error');
        }

        // Eventuele oude koppeling van deze eindklant losmaken
        $customers->where('dsd_client_id', $clientId)->set(['dsd_client_id' => null])->update();

        if ($customerId === 'new') {
            $name = trim((string) $this->request->getPost('client_name')) ?: 'DSD-eindklant ' . $clientId;
            $customerId = $customers->insert(['name' => $name, 'active' => 1, 'dsd_client_id' => $clientId]);

            return $this->back('Klant ' . $name . ' aangemaakt en gekoppeld.', 'message', site_url('dsd'));
        }
        if ($customerId) {
            $customers->update((int) $customerId, ['dsd_client_id' => $clientId]);
        }

        return $this->back($customerId ? 'Eindklant gekoppeld.' : 'Koppeling verwijderd.', 'message', site_url('dsd'));
    }

    /** Licentie koppelen aan een bestaand abonnement, of een nieuw abonnement aanmaken */
    public function linkItem()
    {
        $item = model(DsdItemModel::class)->where('item_key', (string) $this->request->getPost('item_key'))->first();
        if ($item === null) {
            return $this->back('Licentie niet gevonden. Haal de gegevens opnieuw op.', 'error');
        }
        $customer = model(CustomerModel::class)->where('dsd_client_id', $item['dsd_client_id'])->first();
        if ($customer === null) {
            return $this->back('Koppel eerst de eindklant aan een klant.', 'error');
        }

        $subs  = model(SubscriptionModel::class);
        $value = (string) $this->request->getPost('target');

        if (str_starts_with($value, 'service:')) {
            $service = model(ServiceModel::class)->find((int) substr($value, 8));
            if ($service === null) {
                return $this->back('Dienst niet gevonden.', 'error');
            }
            $interval = (int) $service['interval_months'];
            $subs->insert([
                'customer_id'       => $customer['id'],
                'service_id'        => $service['id'],
                'quantity'          => (int) $item['quantity'] ?: 1,
                'interval_months'   => $interval,
                'start_date'        => date('Y-m-d'),
                // Eigen factuurdatum, los van de verlengdatum bij DSD (die lopen bewust niet gelijk)
                'next_invoice_date' => date('Y-m-d'),
                'status'            => 'active',
                'dsd_item_key'      => $item['item_key'],
                'purchase_price'    => Dsd::purchaseFor($item, $interval),
            ]);
            $msg = 'Abonnement aangemaakt met ' . (int) $item['quantity'] . ' ' . ($item['unit'] ?: 'stuks') . '.';
        } elseif (str_starts_with($value, 'sub:')) {
            $sub = $subs->find((int) substr($value, 4));
            if ($sub === null || (int) $sub['customer_id'] !== (int) $customer['id']) {
                return $this->back('Abonnement hoort niet bij deze klant.', 'error');
            }
            $subs->update($sub['id'], [
                'dsd_item_key'   => $item['item_key'],
                'purchase_price' => Dsd::purchaseFor($item, (int) $sub['interval_months']) ?? $sub['purchase_price'],
            ]);
            $msg = 'Licentie gekoppeld aan abonnement.';
        } else {
            return $this->back('Kies een abonnement of dienst.', 'error');
        }
        CostPools::recalculate();

        return $this->back($msg, 'message', site_url('dsd'));
    }

    /** Aantal uit DSD overnemen in het gekoppelde abonnement */
    public function takeQuantity()
    {
        $item = model(DsdItemModel::class)->where('item_key', (string) $this->request->getPost('item_key'))->first();
        $subs = $item ? model(SubscriptionModel::class)->where('dsd_item_key', $item['item_key'])->where('status', 'active')->findAll() : [];
        if ($item === null || count($subs) !== 1) {
            return $this->back('Kan het aantal niet overnemen: er moet precies één actief abonnement aan deze licentie gekoppeld zijn.', 'error');
        }
        model(SubscriptionModel::class)->update($subs[0]['id'], ['quantity' => (int) $item['quantity']]);
        CostPools::recalculate();

        return $this->back('Aantal bijgewerkt naar ' . (int) $item['quantity'] . '. Geldt vanaf de volgende factuur.', 'message', site_url('dsd'));
    }

    public function unlink()
    {
        $sub = model(SubscriptionModel::class)->find((int) $this->request->getPost('subscription_id'));
        if ($sub) {
            model(SubscriptionModel::class)->update($sub['id'], ['dsd_item_key' => null]);
        }

        return $this->back('Koppeling met DSD-licentie verwijderd.', 'message', site_url('dsd'));
    }
}
