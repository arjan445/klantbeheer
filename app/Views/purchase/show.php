<?php $i = $inv; ?>
<a href="<?= site_url('inkoopfacturen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Inkoopfacturen</a>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <h3 class="mb-0"><i class="bi bi-receipt"></i> <?= esc(trim(($i['supplier_name'] ?? '') . ' ' . $i['number'])) ?></h3>
    <div class="d-flex gap-2">
        <?php if (user_can('vault.manage')) : ?>
            <a href="<?= site_url('scan?factuur=' . $i['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-upc-scan"></i> Apparaten scannen</a>
            <a href="<?= site_url('inkoopfacturen/' . $i['id'] . '/bewerken') ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Bewerken</a>
        <?php endif ?>
    </div>
</div>
<div class="card mb-3"><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Leverancier</dt><dd class="col-sm-9"><?= esc($i['supplier_name'] ?? '—') ?></dd>
        <dt class="col-sm-3">Datum</dt><dd class="col-sm-9"><?= datum($i['date']) ?: '—' ?></dd>
        <?php if ($i['amount'] !== null && user_can('billing.view')) : ?><dt class="col-sm-3 km-hide">Bedrag</dt><dd class="col-sm-9 km-hide"><?= euro($i['amount']) ?></dd><?php endif ?>
        <?php if ($i['moneybird_id']) : ?><dt class="col-sm-3">Moneybird</dt><dd class="col-sm-9"><a href="https://moneybird.com/<?= esc((string) env('moneybird.administrationId'), 'url') ?>/documents/<?= esc($i['moneybird_id'], 'url') ?>" target="_blank" rel="noopener">Openen in Moneybird <i class="bi bi-box-arrow-up-right"></i></a></dd><?php endif ?>
        <?php if ($i['notes']) : ?><dt class="col-sm-3">Notities</dt><dd class="col-sm-9"><?= nl2br(esc($i['notes'])) ?></dd><?php endif ?>
    </dl>
</div></div>
<div class="card"><div class="card-header">Apparaten op deze factuur <span class="badge text-bg-light"><?= count($devices) ?></span></div><div class="card-body">
    <?php if ($devices === []) : ?>
        <p class="text-muted mb-0">Nog geen apparaten gekoppeld. Kies deze factuur in het apparaatformulier, of scan de dozen met <a href="<?= site_url('scan?factuur=' . $i['id']) ?>">de doosscan</a>.</p>
    <?php else : ?>
        <?= view('devices/_table', ['devices' => $devices, 'showCustomer' => true]) ?>
    <?php endif ?>
</div></div>
