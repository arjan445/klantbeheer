<?php use App\Controllers\Domains; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Domeinen</h3>
        <div class="text-muted small">Per domein: is het bij jou geregistreerd, wordt het bij jou gehost, en wat hangt er nog meer aan?</div>
    </div>
    <?php if (user_can('billing.manage')) : ?>
        <div class="d-flex gap-2">
            <a href="<?= site_url('domeinen/nieuw') ?>" class="btn btn-outline-secondary"><i class="bi bi-plus-lg"></i> Nieuw domein</a>
            <a href="<?= site_url('domeinen/snel') ?>" class="btn btn-primary"><i class="bi bi-lightning-charge"></i> Snel invoeren</a>
        </div>
    <?php endif ?>
</div>
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="<?= site_url('domeinen') ?>" class="btn btn-sm <?= $status ? 'btn-outline-secondary' : 'btn-secondary' ?>">Alle <span class="badge text-bg-light"><?= array_sum($counts) ?></span></a>
    <?php foreach (Domains::STATUSES as $key => [$label, $color]) : ?>
        <a href="<?= site_url('domeinen?status=' . $key) ?>" class="btn btn-sm <?= $status === $key ? 'btn-' . $color : 'btn-outline-' . $color ?>"><?= esc($label) ?> <span class="badge text-bg-light"><?= (int) ($counts[$key] ?? 0) ?></span></a>
    <?php endforeach ?>
</div>
<div class="card"><div class="card-body">
    <?= view('domains/_table', ['domains' => $domains, 'showCustomer' => true]) ?>
</div></div>
