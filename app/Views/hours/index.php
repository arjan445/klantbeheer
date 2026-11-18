<?php

use App\Controllers\Hours;
use App\Libraries\Mileage;

$wd     = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
$months = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
$t      = strtotime($month . '-01');
$prev   = date('Y-m', strtotime('-1 month', $t));
$next   = date('Y-m', strtotime('+1 month', $t));
$dates  = array_unique(array_merge(array_keys($byDay), array_keys(array_filter($days, static fn ($d) => $d['journal_id']))));
rsort($dates);
$kmFmt  = static fn ($v) => number_format((float) $v, 1, ',', '.');
?>
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <h3 class="mb-0">Uren</h3>
    <a href="<?= site_url('uren/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Uren boeken</a>
</div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('uren?maand=' . $prev) ?>"><i class="bi bi-chevron-left"></i></a>
    <span class="fw-semibold" style="min-width: 9rem; text-align:center"><?= $months[(int) date('n', $t)] . ' ' . date('Y', $t) ?></span>
    <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('uren?maand=' . $next) ?>"><i class="bi bi-chevron-right"></i></a>
    <?php if ($month !== date('Y-m')) : ?><a class="btn btn-link btn-sm" href="<?= site_url('uren') ?>">Deze maand</a><?php endif ?>
</div>

<?php if ($missing || ! $orsReady) : ?>
    <div class="alert alert-warning small">
        <i class="bi bi-gear"></i> Kilometers en memoriaal zijn nog niet compleet ingesteld:
        <?= esc(implode(', ', array_merge($orsReady ? [] : ['OpenRouteService-sleutel / thuisadres'], $missing))) ?>.
        <?php if (user_can('settings.manage')) : ?><a href="<?= site_url('beheer/uren') ?>">Instellen</a><?php endif ?>
    </div>
<?php endif ?>

<div class="row g-2 mb-3">
    <div class="col-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Uren</div><div class="fs-5 fw-semibold"><?= Hours::hm($totalMin) ?></div></div></div></div>
    <div class="col-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Kilometers</div><div class="fs-5 fw-semibold"><?= $kmFmt($km) ?></div></div></div></div>
    <div class="col-4"><div class="card"><div class="card-body py-2"><div class="small text-muted">Km-aftrek</div><div class="fs-5 fw-semibold"><?= euro($amount) ?></div></div></div></div>
</div>
<?php if ($open > 0 && ! $missing) : ?>
<form method="post" action="<?= site_url('uren/maand/boeken') ?>" class="mb-3"><?= csrf_field() ?><input type="hidden" name="maand" value="<?= esc($month) ?>">
    <button class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-arrow-up"></i> <?= $open ?> dag(en) nog boeken in Moneybird</button>
</form>
<?php endif ?>

<?php if ($dates === []) : ?>
    <div class="card"><div class="card-body text-muted">Nog geen uren in deze maand.</div></div>
<?php endif ?>

<?php foreach ($dates as $date) :
    $entries = $byDay[$date] ?? [];
    $day     = $days[$date] ?? null;
    $min     = array_sum(array_column($entries, 'minutes'));
    ?>
<div class="card mb-3" id="dag-<?= $date ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= $wd[(int) date('w', strtotime($date))] ?> <?= date('d-m-Y', strtotime($date)) ?></span>
        <span class="d-flex align-items-center gap-2">
            <span class="badge text-bg-light border"><?= Hours::hm($min) ?></span>
            <a href="<?= site_url('uren/nieuw?datum=' . $date) ?>" class="btn btn-sm btn-outline-secondary py-0" title="Uren toevoegen op deze dag"><i class="bi bi-plus"></i></a>
        </span>
    </div>
    <ul class="list-group list-group-flush">
        <?php foreach ($entries as $e) : ?>
        <li class="list-group-item">
            <div class="d-flex justify-content-between gap-2">
                <a href="<?= site_url('uren/' . $e['id']) ?>" class="text-reset text-decoration-none flex-grow-1">
                    <span class="fw-semibold"><?= esc($e['customer_name']) ?></span><?= $e['site_name'] ? ' <span class="text-muted">· ' . esc($e['site_name']) . '</span>' : '' ?>
                    <span class="d-block small"><?= esc($e['description']) ?></span>
                    <span class="d-block small text-muted">
                        <?= substr($e['start_time'], 0, 5) ?>–<?= substr($e['end_time'], 0, 5) ?><?= $e['pause_minutes'] ? ' · pauze ' . (int) $e['pause_minutes'] . ' min' : '' ?>
                        <?= $e['billable'] ? '' : ' · <span class="text-warning-emphasis">niet factureerbaar</span>' ?>
                        <?= $e['travel'] && $e['site_id'] ? '' : ' · <i class="bi bi-house"></i> niet gereden' ?>
                    </span>
                </a>
                <span class="text-end text-nowrap">
                    <span class="fw-semibold"><?= Hours::hm($e['minutes']) ?></span>
                    <span class="d-block small">
                    <?php if ($e['moneybird_id']) : ?>
                        <span class="text-success" title="Staat in Moneybird"><i class="bi bi-check-circle"></i> MB</span>
                    <?php elseif ($mb->configured()) : ?>
                        <form method="post" action="<?= site_url('uren/' . $e['id'] . '/moneybird') ?>" class="d-inline"><?= csrf_field() ?>
                            <button class="btn btn-link btn-sm p-0 text-danger" title="<?= esc($e['sync_error'] ?: 'Nog niet in Moneybird', 'attr') ?>"><i class="bi bi-arrow-repeat"></i> MB</button></form>
                    <?php endif ?>
                    </span>
                </span>
            </div>
        </li>
        <?php endforeach ?>
    </ul>
    <?php if ($day && ($day['route'] || $day['journal_id'])) : ?>
    <div class="card-footer small">
        <?php if ($day['route']) : ?>
            <div class="text-muted mb-1"><i class="bi bi-signpost-2"></i> <?= esc($day['route']) ?></div>
        <?php endif ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="post" action="<?= site_url('uren/dag/' . $date . '/km') ?>" class="d-flex align-items-center gap-1"><?= csrf_field() ?>
                <i class="bi bi-car-front"></i>
                <input name="km" inputmode="decimal" class="form-control form-control-sm" style="width: 5.5rem" value="<?= $day['km'] !== null ? $kmFmt($day['km']) : '' ?>" placeholder="km" title="Leeg maken = weer automatisch berekenen">
                <span>km<?= $day['km_manual'] ? ' <span class="badge text-bg-secondary">handmatig</span>' : '' ?></span>
                <button class="btn btn-sm btn-outline-secondary py-0" title="Kilometers opslaan"><i class="bi bi-check"></i></button>
            </form>
            <span>= <?= euro((float) $day['amount']) ?></span>
            <span class="ms-auto">
            <?php if ($day['journal_id'] && ! Mileage::needsBooking($day)) : ?>
                <a href="<?= esc($mb->journalUrl($day['journal_id']), 'attr') ?>" target="_blank" rel="noopener" class="text-success text-decoration-none"><i class="bi bi-journal-check"></i> <?= esc(Mileage::reference($date)) ?></a>
            <?php elseif ($date <= date('Y-m-d') && ! $missing) : ?>
                <form method="post" action="<?= site_url('uren/dag/' . $date . '/boeken') ?>" class="d-inline"><?= csrf_field() ?>
                    <button class="btn btn-sm <?= $day['journal_id'] ? 'btn-warning' : 'btn-outline-primary' ?> py-0"><i class="bi bi-journal-arrow-up"></i> <?= $day['journal_id'] ? 'Gewijzigd, opnieuw boeken' : 'Memoriaal boeken' ?></button></form>
            <?php endif ?>
            </span>
        </div>
        <?php if ($day['error']) : ?><div class="text-danger mt-1"><i class="bi bi-exclamation-triangle"></i> <?= esc($day['error']) ?></div><?php endif ?>
    </div>
    <?php endif ?>
</div>
<?php endforeach ?>
