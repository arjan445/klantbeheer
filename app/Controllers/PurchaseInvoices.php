<?php

namespace App\Controllers;

use App\Models\DeviceModel;
use App\Models\PurchaseInvoiceModel;
use App\Models\SupplierModel;

/**
 * Inkoopfacturen: per apparaat terugzien op welke factuur het is geleverd.
 */
class PurchaseInvoices extends BaseController
{
    private const FIELDS = ['supplier_id', 'supplier', 'number', 'date', 'amount', 'moneybird_id', 'notes'];

    public function index()
    {
        $q     = trim((string) $this->request->getGet('q'));
        $model = model(PurchaseInvoiceModel::class)->withSupplier()
            ->select('(SELECT COUNT(*) FROM ' . db_connect()->prefixTable('devices') . ' d WHERE d.purchase_invoice_id = purchase_invoices.id) AS device_count', false)
            ->orderBy('purchase_invoices.date', 'DESC')->orderBy('purchase_invoices.id', 'DESC');
        if ($q !== '') {
            $model->groupStart()->like('purchase_invoices.number', $q)->orLike('purchase_invoices.supplier', $q)->orLike('suppliers.name', $q)->groupEnd();
        }

        return $this->page('purchase/index', ['invoices' => $model->findAll(500), 'q' => $q], 'Inkoopfacturen');
    }

    public function show($id)
    {
        $inv = model(PurchaseInvoiceModel::class)->withSupplier()->where('purchase_invoices.id', $id)->first();
        if ($inv === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $devices = model(DeviceModel::class)
            ->select('devices.*, customers.name AS customer_name, sites.name AS site_name')
            ->join('customers', 'customers.id = devices.customer_id')
            ->join('sites', 'sites.id = devices.site_id', 'left')
            ->where('purchase_invoice_id', $id)->orderBy('customers.name')->orderBy('devices.name')->findAll();

        return $this->page('purchase/show', ['inv' => $inv, 'devices' => $devices], 'Inkoopfactuur ' . $inv['number']);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit($id)
    {
        return $this->form($this->findOr404(PurchaseInvoiceModel::class, $id));
    }

    private function form(?array $existing)
    {
        $data = [
            'inv'       => $existing ?? ['date' => date('Y-m-d'), 'supplier_id' => $this->request->getGet('leverancier')],
            'isNew'     => $existing === null,
            'suppliers' => model(SupplierModel::class)->orderBy('name')->findAll(),
        ];
        $title = $existing ? 'Inkoopfactuur bewerken' : 'Inkoopfactuur toevoegen';

        if (! $this->request->is('post')) {
            return $this->page('purchase/form', $data, $title);
        }

        $post = $this->clean($this->request->getPost(self::FIELDS));
        if (! user_can('billing.view')) {
            unset($post['amount']); // monteurs zien geen bedragen
        }
        if (! $this->validateData($post, $this->rules())) {
            $data['inv']        = $post + ['id' => $existing['id'] ?? null];
            $data['validation'] = true;

            return $this->page('purchase/form', $data, $title);
        }

        if ($existing) {
            model(PurchaseInvoiceModel::class)->update($existing['id'], $post);
            $id = $existing['id'];
        } else {
            $id = model(PurchaseInvoiceModel::class)->insert($post);
        }

        return $this->back('Opgeslagen.', 'message', site_url('inkoopfacturen/' . $id));
    }

    /** Snel aanmaken vanuit de doosscan of het apparaatformulier (JSON) */
    public function quick()
    {
        $post = $this->clean($this->request->getPost(['supplier_id', 'supplier', 'number', 'date']));
        if (! $this->validateData($post, $this->rules())) {
            return $this->response->setStatusCode(422)->setJSON(['error' => implode(' ', $this->validator->getErrors()), 'csrf' => csrf_hash()]);
        }
        // Bestaat al? Dan die teruggeven
        $model = model(PurchaseInvoiceModel::class);
        $found = $model->withSupplier()->where('purchase_invoices.number', $post['number'])
            ->where('purchase_invoices.supplier_id', $post['supplier_id'])->where('purchase_invoices.supplier', $post['supplier'])->first();
        $id = $found['id'] ?? $model->insert($post);
        $inv = model(PurchaseInvoiceModel::class)->withSupplier()->where('purchase_invoices.id', $id)->first();

        return $this->response->setJSON(['id' => (int) $id, 'label' => PurchaseInvoiceModel::label($inv), 'csrf' => csrf_hash()]);
    }

    public function delete($id)
    {
        $inv = $this->findOr404(PurchaseInvoiceModel::class, $id);
        db_connect()->table('devices')->where('purchase_invoice_id', $inv['id'])->update(['purchase_invoice_id' => null]);
        model(PurchaseInvoiceModel::class)->delete($inv['id']);

        return $this->back('Inkoopfactuur verwijderd. De apparaten zelf staan nog in de kluis.', 'message', site_url('inkoopfacturen'));
    }

    private function rules(): array
    {
        return [
            'supplier_id' => 'permit_empty|is_natural_no_zero|is_not_unique[suppliers.id]',
            'supplier'    => 'permit_empty|max_length[100]|required_without[supplier_id]',
            'number'      => 'required|max_length[100]',
            'date'        => 'permit_empty|valid_date[Y-m-d]',
            'amount'      => 'permit_empty|decimal',
            'moneybird_id' => 'permit_empty|max_length[32]',
        ];
    }

    private function clean(array $post): array
    {
        $post = $this->nullify($post);
        if (isset($post['amount'])) {
            $post['amount'] = str_replace(',', '.', $post['amount']);
        }
        // Moneybird-link geplakt? Alleen het ID bewaren
        if (! empty($post['moneybird_id']) && preg_match('#/documents/(\d+)#', $post['moneybird_id'], $m)) {
            $post['moneybird_id'] = $m[1];
        }
        if (! empty($post['supplier_id'])) {
            $post['supplier'] = null;
        }

        return $post;
    }
}
