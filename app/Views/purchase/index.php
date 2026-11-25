<?php use App\Models\PurchaseInvoiceModel; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Inkoopfacturen</h3>
    <div class="d-flex gap-2">
        <form method="get" class="d-flex"><input type="search" name="q" class="form-control" placeholder="Zoek nummer of leverancier" value="<?= esc($q) ?>"></form>
        <?php if (user_can('vault.manage')) : ?><a href="<?= site_url('inkoopfacturen/nieuw') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Toevoegen</a><?php endif ?>
    </div>
</div>
<p class="text-muted small">Koppel apparaten aan de inkoopfactuur waarop ze geleverd zijn, zodat je bij garantie of een RMA meteen weet waar en wanneer je ze kocht. Dat kan in het apparaatformulier of met de doosscan.</p>
<div class="net-list">
<?php foreach ($invoices as $i) : ?>
    <a class="net-item d-flex justify-content-between align-items-center gap-2 text-reset text-decoration-none" href="<?= site_url('inkoopfacturen/' . $i['id']) ?>">
        <span class="min-w-0">
            <span class="fw-semibold d-block text-truncate"><?= esc(($i['supplier_name'] ?? '') . ' ' . $i['number']) ?></span>
            <span class="small text-muted"><?= datum($i['date']) ?><?php if ($i['amount'] !== null && user_can('billing.view')) : ?><span class="km-hide"> · <?= euro($i['amount']) ?></span><?php endif ?></span>
        </span>
        <span class="badge text-bg-light border text-nowrap"><?= (int) $i['device_count'] ?> apparaten</span>
    </a>
<?php endforeach ?>
<?php if ($invoices === []) : ?><div class="card"><div class="card-body text-muted">Nog geen inkoopfacturen.</div></div><?php endif ?>
</div>
