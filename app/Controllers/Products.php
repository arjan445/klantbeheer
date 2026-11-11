<?php

namespace App\Controllers;

use App\Libraries\Catalog;
use App\Models\ProductModel;
use App\Models\SupplierGroupModel;
use App\Models\SupplierModel;
use Throwable;

/**
 * Productcatalogus voor offertes.
 */
class Products extends BaseController
{
    private const PER_PAGE = 50;

    public function index()
    {
        $q        = trim((string) $this->request->getGet('q'));
        $supplier = (int) $this->request->getGet('leverancier');
        $page     = max(1, (int) $this->request->getGet('pagina'));

        $model = $this->search($q, $supplier, (bool) $this->request->getGet('inactief'));
        $total = $model->countAllResults(false);
        $rows  = $model->orderBy('products.name')->findAll(self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->page('products/index', [
            'products'  => $rows,
            'q'         => $q,
            'supplier'  => $supplier,
            'page'      => $page,
            'pages'     => (int) ceil($total / self::PER_PAGE),
            'total'     => $total,
            'suppliers' => model(SupplierModel::class)->orderBy('id')->findAll(),
            'counts'    => array_column(db_connect()->table('products')->select('supplier_id, COUNT(*) AS n')->where('active', 1)->groupBy('supplier_id')->get()->getResultArray(), 'n', 'supplier_id'),
        ], 'Producten');
    }

    /** Zoeken op meerdere woorden in naam, artikelnr, typenummer, merk en groep */
    private function search(string $q, int $supplier = 0, bool $inactive = false): ProductModel
    {
        $model = model(ProductModel::class)->select('products.*, suppliers.name AS supplier_name')
            ->join('suppliers', 'suppliers.id = products.supplier_id');
        if (! $inactive) {
            $model->where('products.active', 1);
        }
        if ($supplier > 0) {
            $model->where('products.supplier_id', $supplier);
        }
        foreach (preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $model->groupStart()
                ->like('products.name', $word)->orLike('products.supplier_sku', $word)->orLike('products.sku', $word)
                ->orLike('products.brand', $word)->orLike('products.group_name', $word)
                ->groupEnd();
        }

        return $model;
    }

    /** JSON voor de offerte-editor */
    public function lookup()
    {
        $q = trim((string) $this->request->getGet('q'));
        if (mb_strlen($q) < 2) {
            return $this->response->setJSON([]);
        }
        $out = [];
        foreach ($this->search($q)->orderBy('products.name')->findAll(25) as $p) {
            $price = Catalog::price($p);
            $out[] = [
                'id'       => (int) $p['id'],
                'name'     => $p['name'],
                'sku'      => $p['sku'] ?: $p['supplier_sku'],
                'supplier' => $p['supplier_name'],
                'group'    => $p['group_name'],
                'cost'     => $price['cost'],
                'sell'     => $price['sell'],
                'info'     => $p['info'],
            ];
        }

        return $this->response->setJSON($out);
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit($id)
    {
        return $this->form($this->findOr404(ProductModel::class, $id));
    }

    private function form(?array $product)
    {
        $suppliers = model(SupplierModel::class)->orderBy('id')->findAll();
        $data = ['product' => $product ?? ['supplier_id' => end($suppliers)['id'] ?? null], 'suppliers' => $suppliers, 'isNew' => $product === null];

        if (! $this->request->is('post')) {
            return $this->page('products/form', $data, $product ? 'Product bewerken' : 'Los product toevoegen');
        }

        $post = $this->nullify($this->request->getPost(['supplier_id', 'supplier_sku', 'sku', 'name', 'brand', 'group_name', 'list_price', 'cost_price', 'markup', 'sell_price', 'info']));
        foreach (['list_price', 'cost_price', 'markup', 'sell_price'] as $f) {
            if ($post[$f] !== null) {
                $post[$f] = str_replace(',', '.', $post[$f]);
            }
        }
        $rules = [
            'supplier_id' => 'required|is_not_unique[suppliers.id]',
            'name'        => 'required|max_length[500]',
            'list_price'  => 'permit_empty|decimal',
            'cost_price'  => 'permit_empty|decimal',
            'markup'      => 'permit_empty|decimal|greater_than[0]',
            'sell_price'  => 'permit_empty|decimal',
        ];
        if (! $this->validateData($post, $rules)) {
            $data['product'] = $post + ['id' => $product['id'] ?? null];
            $data['validation'] = true;

            return $this->page('products/form', $data, 'Product');
        }
        $post['active'] = $this->request->getPost('active') ? 1 : 0;

        $model = model(ProductModel::class);
        if ($product) {
            $model->update($product['id'], $post);
        } else {
            $post['supplier_sku'] ??= 'M' . date('ymdHis') . random_int(10, 99);
            $post['source'] = 'manual';
            $model->insert($post);
        }

        return $this->back('Product opgeslagen.', 'message', site_url('producten?q=' . rawurlencode($post['name'])));
    }

    /** Leveranciers: standaard korting en opslag, en per productgroep */
    public function suppliers()
    {
        $suppliers = model(SupplierModel::class)->orderBy('id')->findAll();
        $groups    = model(SupplierGroupModel::class);

        if ($this->request->is('post')) {
            $num = static fn ($v) => ($v = trim(str_replace(',', '.', (string) $v))) === '' ? null : (float) $v;
            foreach ((array) $this->request->getPost('supplier') as $id => $s) {
                model(SupplierModel::class)->update((int) $id, [
                    'default_discount' => $num($s['discount'] ?? 0) ?? 0,
                    'default_markup'   => $num($s['markup'] ?? 1.2) ?: 1.2,
                ]);
            }
            foreach ((array) $this->request->getPost('group') as $id => $g) {
                $groups->update((int) $id, ['discount' => $num($g['discount'] ?? ''), 'markup' => $num($g['markup'] ?? '')]);
            }
            if ($name = trim((string) $this->request->getPost('new_supplier'))) {
                model(SupplierModel::class)->insert(['name' => mb_substr($name, 0, 100), 'default_discount' => 0, 'default_markup' => 1.2]);
            }

            return $this->back('Leveranciers opgeslagen.', 'message', site_url('producten/leveranciers'));
        }

        $bySupplier = [];
        $counts = [];
        foreach (db_connect()->table('products')->select('supplier_id, group_name, COUNT(*) AS n')->where('active', 1)
            ->groupBy('supplier_id, group_name')->get()->getResultArray() as $c) {
            $counts[$c['supplier_id'] . '|' . $c['group_name']] = $c['n'];
        }
        foreach ($groups->orderBy('group_name')->findAll() as $g) {
            $g['count'] = (int) ($counts[$g['supplier_id'] . '|' . $g['group_name']] ?? 0);
            $bySupplier[$g['supplier_id']][] = $g;
        }

        return $this->page('products/suppliers', ['suppliers' => $suppliers, 'groups' => $bySupplier], 'Leveranciers & marges');
    }

    public function importOsec()
    {
        $file = $this->request->getFile('file');
        if ($file === null || ! $file->isValid()) {
            return $this->back('Kies het OSEC-prijslijstbestand (.xlsx).', 'error');
        }
        try {
            $r = Catalog::importOsec($file->getTempName());
        } catch (Throwable $e) {
            return $this->back('Import mislukt: ' . $e->getMessage(), 'error');
        }

        return $this->back(sprintf('OSEC: %d nieuw, %d bijgewerkt, %d niet meer in de prijslijst (inactief).', $r['new'], $r['updated'], $r['inactive']), 'message', site_url('producten?leverancier=' . Catalog::supplierByCode('osec')['id']));
    }

    public function syncWimood()
    {
        try {
            $r = Catalog::syncWimood();
        } catch (Throwable $e) {
            return $this->back('Wimood ophalen mislukt: ' . $e->getMessage(), 'error');
        }

        return $this->back(sprintf('Wimood: %d bestellingen doorgelopen, %d nieuwe producten, %d bijgewerkt.', $r['orders'], $r['new'], $r['updated']), 'message', site_url('producten?leverancier=' . Catalog::supplierByCode('wimood')['id']));
    }
}
