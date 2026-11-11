<?php use App\Libraries\Catalog; $manage = user_can('billing.manage'); ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Producten</h3>
        <div class="text-muted small">Catalogus voor offertes. Kostprijs × opslag = verkoopprijs.</div>
    </div>
    <?php if ($manage) : ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('producten/leveranciers') ?>" class="btn btn-outline-secondary"><i class="bi bi-sliders"></i> Leveranciers &amp; marges</a>
        <a href="<?= site_url('producten/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Los product</a>
    </div>
    <?php endif ?>
</div>

<?php if ($manage) : ?>
<div class="row g-3 mb-3">
    <?php foreach ($suppliers as $s) : ?>
        <?php if (! in_array($s['code'], ['osec', 'wimood'], true)) continue; ?>
        <div class="col-md-6"><div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="fw-semibold"><?= esc($s['name']) ?> <span class="badge text-bg-light"><?= (int) ($counts[$s['id']] ?? 0) ?> producten</span></div>
                <div class="small text-muted">
                    <?= $s['code'] === 'osec' ? 'Bruto prijslijst, korting standaard ' . aantal($s['default_discount']) . '%' : 'Echte inkoopprijzen uit je bestellingen' ?>
                    · opslag ×<?= rtrim(rtrim(number_format((float) $s['default_markup'], 3, ',', ''), '0'), ',') ?>
                    <?= $s['last_import_at'] ? '· bijgewerkt ' . date('d-m-Y H:i', strtotime($s['last_import_at'])) : '' ?>
                </div>
            </div>
            <?php if ($s['code'] === 'osec') : ?>
                <form method="post" action="<?= site_url('producten/import/osec') ?>" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                    <?= csrf_field() ?>
                    <a href="https://www.osec.nl/products/products_xls" target="_blank" rel="noopener" class="small text-nowrap" title="Downloaden (ingelogd bij OSEC)">1. Download</a>
                    <input type="file" name="file" accept=".xlsx" class="form-control form-control-sm" style="max-width: 210px" required onchange="this.form.submit()">
                </form>
            <?php else : ?>
                <form method="post" action="<?= site_url('producten/import/wimood') ?>"><?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Ophalen</button></form>
            <?php endif ?>
        </div></div></div>
    <?php endforeach ?>
</div>
<?php endif ?>

<form method="get" class="d-flex gap-2 mb-3 flex-wrap">
    <input type="search" name="q" value="<?= esc($q) ?>" class="form-control" style="max-width: 420px" placeholder="Zoek op naam, artikel-/typenummer, merk of groep, bijv. 'u6 pro' of 'ajax hub'" autofocus>
    <select name="leverancier" class="form-select" style="max-width: 200px" onchange="this.form.submit()">
        <option value="">Alle leveranciers</option>
        <?php foreach ($suppliers as $s) : ?><option value="<?= $s['id'] ?>" <?= $supplier === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
    </select>
    <button class="btn btn-outline-secondary">Zoeken</button>
</form>

<div class="card"><div class="card-body p-0">
<table class="table table-sm table-hover mb-0 align-middle">
    <thead><tr><th>Product</th><th>Leverancier</th><th>Groep</th><th class="text-end">Bruto</th><th class="text-end">Kostprijs</th><th class="text-end">Opslag</th><th class="text-end">Verkoop</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p) : ?>
        <?php $pr = Catalog::price($p); ?>
        <tr class="<?= $p['active'] ? '' : 'text-muted' ?>">
            <td><?= esc($p['name']) ?>
                <div class="small text-muted mono"><?= esc($p['sku'] ?: '') ?><?= $p['sku'] && $p['supplier_sku'] ? ' · ' : '' ?><?= esc($p['source'] === 'manual' ? '' : '#' . $p['supplier_sku']) ?><?= $p['info'] ? ' · <span class="text-warning">' . esc($p['info']) . '</span>' : '' ?></div></td>
            <td class="small"><?= esc($p['supplier_name']) ?></td>
            <td class="small"><?= esc($p['group_name']) ?></td>
            <td class="text-end small"><?= $p['list_price'] !== null ? euro($p['list_price']) : '' ?></td>
            <td class="text-end"><?= $pr['cost'] !== null ? euro($pr['cost']) : '—' ?></td>
            <td class="text-end small">×<?= rtrim(rtrim(number_format($pr['markup'], 3, ',', ''), '0'), ',') ?></td>
            <td class="text-end fw-semibold"><?= $pr['sell'] !== null ? euro($pr['sell']) : '—' ?></td>
            <td class="text-end"><?php if ($manage) : ?><a href="<?= site_url('producten/' . $p['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    <?php if ($products === []) : ?><tr><td colspan="8" class="text-muted p-3"><?= $q !== '' ? 'Niets gevonden.' : 'Nog geen producten. Importeer de OSEC-prijslijst of haal Wimood op.' ?></td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
<?php if ($pages > 1) : ?>
<nav class="mt-3 d-flex justify-content-between align-items-center">
    <span class="small text-muted"><?= (int) $total ?> producten</span>
    <ul class="pagination pagination-sm mb-0">
        <?php $qs = static fn ($pg) => site_url('producten') . '?' . http_build_query(['q' => $q, 'leverancier' => $supplier ?: null, 'pagina' => $pg]); ?>
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $qs($page - 1) ?>">Vorige</a></li>
        <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $pages ?></span></li>
        <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $qs($page + 1) ?>">Volgende</a></li>
    </ul>
</nav>
<?php endif ?>
