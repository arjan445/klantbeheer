<?php use App\Controllers\Quotes; ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Offertes</h3>
    <div class="d-flex gap-2">
        <a href="<?= site_url('producten') ?>" class="btn btn-outline-secondary"><i class="bi bi-box-seam"></i> Producten</a>
        <?php if (user_can('billing.manage')) : ?><a href="<?= site_url('offertes/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe offerte</a><?php endif ?>
    </div>
</div>
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="<?= site_url('offertes') ?>" class="btn btn-sm <?= $status ? 'btn-outline-secondary' : 'btn-secondary' ?>">Alle</a>
    <?php foreach (Quotes::STATUSES as $k => [$label, $color]) : ?>
        <a href="<?= site_url('offertes?status=' . $k) ?>" class="btn btn-sm <?= $status === $k ? 'btn-' . $color : 'btn-outline-' . $color ?>"><?= esc($label) ?></a>
    <?php endforeach ?>
</div>
<div class="card"><div class="card-body">
<table class="table table-hover <?= count($quotes) > 15 ? 'datatable' : '' ?>">
    <thead><tr><th>#</th><th>Datum</th><th>Klant</th><th>Omschrijving</th><th class="text-end">Excl. btw</th><th class="text-end">Marge materiaal</th><th class="text-end">Arbeid</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($quotes as $q) : ?>
        <?php [$label, $color] = Quotes::STATUSES[$q['status']] ?? [$q['status'], 'secondary']; ?>
        <tr>
            <td><a href="<?= site_url('offertes/' . $q['id']) ?>"><?= (int) $q['id'] ?></a></td>
            <td data-order="<?= esc($q['created_at']) ?>"><?= datum($q['created_at']) ?></td>
            <td><a href="<?= site_url('klanten/' . $q['customer_id']) ?>"><?= esc($q['customer_name']) ?></a></td>
            <td><a href="<?= site_url('offertes/' . $q['id']) ?>" class="fw-semibold"><?= esc($q['title']) ?></a></td>
            <td class="text-end"><?= euro($q['totals']['sell']) ?></td>
            <td class="text-end"><?= euro($q['totals']['margin']) ?><?= $q['totals']['pct'] !== null ? ' <span class="small text-muted">' . $q['totals']['pct'] . '%</span>' : '' ?></td>
            <td class="text-end"><?= $q['totals']['labor'] > 0 ? euro($q['totals']['labor']) : '' ?></td>
            <td><span class="badge text-bg-<?= $color ?>"><?= esc($label) ?></span></td>
        </tr>
    <?php endforeach ?>
    <?php if ($quotes === []) : ?><tr><td colspan="8" class="text-muted">Nog geen offertes.</td></tr><?php endif ?>
    </tbody>
</table>
</div></div>
