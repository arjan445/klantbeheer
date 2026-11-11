<?php

namespace App\Controllers;

use App\Libraries\Billing as BillingLib;
use App\Libraries\Moneybird;
use App\Models\InvoiceLineModel;
use Throwable;

class Billing extends BaseController
{
    public function index()
    {
        $days = $this->request->getGet('dagen');
        $days = $days === null || $days === '' ? BillingLib::daysAhead() : max(0, min(366, (int) $days));

        $byCustomer = [];
        foreach (BillingLib::due($days) as $sub) {
            $byCustomer[$sub['customer_id']]['name']       = $sub['customer_name'];
            $byCustomer[$sub['customer_id']]['moneybird']  = $sub['moneybird_contact_id'];
            $byCustomer[$sub['customer_id']]['subs'][]     = $sub;
        }

        return $this->page('billing/index', [
            'byCustomer' => $byCustomer,
            'days'       => $days,
            'mb'         => new Moneybird(),
        ], 'Facturatie');
    }

    public function invoice()
    {
        $customerId = (int) $this->request->getPost('customer_id');
        $ids        = array_map('intval', (array) $this->request->getPost('subs'));
        $viaMb      = $this->request->getPost('method') !== 'manual';

        try {
            $result = BillingLib::invoice($customerId, $ids, $viaMb);
        } catch (Throwable $e) {
            log_message('error', 'Factureren mislukt: ' . $e->getMessage());

            return $this->back($e->getMessage(), 'error');
        }

        $msg = $result['count'] . ' regel(s), ' . euro($result['total']) . ' excl. btw. ';
        $msg .= $viaMb
            ? 'Conceptfactuur staat klaar in Moneybird. Controleer en verstuur hem daar.'
            : 'Gemarkeerd als gefactureerd (zonder Moneybird).';

        if ($viaMb && $result['invoice_id']) {
            $this->session->setFlashdata('invoice_url', (new Moneybird())->invoiceUrl($result['invoice_id']));
        }

        return $this->back($msg, 'message', site_url('facturatie'));
    }

    public function history()
    {
        $lines = model(InvoiceLineModel::class)
            ->select('invoice_lines.*, customers.name AS customer_name')
            ->join('customers', 'customers.id = invoice_lines.customer_id')
            ->orderBy('invoice_lines.created_at', 'DESC')->limit(1000)->findAll();

        return $this->page('billing/history', ['lines' => $lines, 'mb' => new Moneybird()], 'Gefactureerd');
    }
}
