<?php
use App\Libraries\Network;

$hour     = (int) date('G');
$greet    = $hour < 6 ? 'Goedenacht' : ($hour < 12 ? 'Goedemorgen' : ($hour < 18 ? 'Goedemiddag' : 'Goedenavond'));
$me       = auth()->user();
$name     = $me && $me->username ? ucfirst((string) $me->username) : '';
$days     = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
$months   = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
$mon      = ['', 'jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
$net      = $netOffline ?? null;
$netCount = $net['count'] ?? 0;
$netCusts = $net ? count($net['groups']) : 0;
$today    = date('Y-m-d');
?>
<div class="dash">

<div class="dash-head">
    <div>
        <div class="dash-greet"><?= $greet ?><?= $name !== '' ? ', ' . esc($name) : '' ?></div>
        <div class="dash-date"><?= ucfirst($days[(int) date('w')]) ?> <?= (int) date('j') ?> <?= $months[(int) date('n')] ?></div>
    </div>
    <div class="dash-actions">
        <?php if (user_can('billing.manage')) : ?><a href="<?= site_url('offertes/nieuw') ?>" class="btn btn-primary"><i class="bi bi-file-earmark-plus"></i><span class="d-none d-sm-inline"> Offerte</span></a><?php endif ?>
        <?php if (user_can('vault.manage')) : ?><a href="<?= site_url('scan') ?>" class="btn btn-outline-primary"><i class="bi bi-upc-scan"></i><span class="d-none d-sm-inline"> Doosscan</span></a><?php endif ?>
        <?php if (user_can('customers.manage')) : ?><a href="<?= site_url('klanten/nieuw') ?>" class="btn btn-outline-primary"><i class="bi bi-person-plus"></i><span class="d-none d-sm-inline"> Klant</span></a><?php endif ?>
    </div>
</div>

<?php // ---- Statustegels ---- ?>
<div class="row g-3 mb-4 dash-tiles">
    <?php if ($net !== null) : ?>
    <div class="col-6 col-xl-3">
        <a href="<?= site_url($netCount ? 'netwerk?tab=offline' : 'netwerk') ?>" class="tile <?= $netCount ? 'tile-bad' : 'tile-good' ?>">
            <div class="tile-icon"><i class="bi <?= $netCount ? 'bi-wifi-off' : 'bi-wifi' ?>"></i></div>
            <div class="tile-label">Netwerk</div>
            <div class="tile-value"><?= $netCount ? (int) $netCount . ' offline' : 'Alles online' ?></div>
            <div class="tile-sub">
                <?= $netCount ? 'bij ' . $netCusts . ($netCusts === 1 ? ' klant' : ' klanten') : (int) $net['monitored'] . ' apparaten bewaakt' ?>
                <?php if (! empty($netStatus['lastSync'])) : ?> · <?= esc(Network::ago($netStatus['lastSync'])) ?><?= Network::ago($netStatus['lastSync']) === 'net' ? '' : ' geleden' ?><?php endif ?>
            </div>
            <?php if (! empty($netStatus['stale'])) : ?><div class="tile-warn"><i class="bi bi-clock-history"></i> niet actueel</div><?php endif ?>
        </a>
    </div>
    <?php elseif (user_can('settings.manage')) : ?>
    <div class="col-6 col-xl-3">
        <a href="<?= site_url('beheer/netwerk') ?>" class="tile tile-muted">
            <div class="tile-icon"><i class="bi bi-wifi"></i></div>
            <div class="tile-label">Netwerk</div>
            <div class="tile-value">Instellen</div>
            <div class="tile-sub">Koppel UniFi en UISP</div>
        </a>
    </div>
    <?php endif ?>

    <?php if (isset($dueNow)) : ?>
    <div class="col-6 col-xl-3">
        <a href="<?= site_url('facturatie') ?>" class="tile <?= $overdue ? 'tile-warnc' : '' ?>">
            <div class="tile-icon"><i class="bi bi-receipt"></i></div>
            <div class="tile-label">Te factureren</div>
            <div class="tile-value"><?= (int) $dueNow ?></div>
            <div class="tile-sub"><?= $overdue ? (int) $overdue . ' over datum' : ($dueNow ? 'klaar om te versturen' : 'alles bij') ?></div>
        </a>
    </div>
    <?php endif ?>

    <div class="col-6 col-xl-3">
        <a href="<?= site_url('taken') ?>" class="tile <?= $todoLate ? 'tile-warnc' : '' ?>">
            <div class="tile-icon"><i class="bi bi-check2-square"></i></div>
            <div class="tile-label">Open taken</div>
            <div class="tile-value"><?= (int) $todoCount ?></div>
            <div class="tile-sub"><?= implode(' · ', array_filter([$todoLate ? $todoLate . ' te laat' : null, $todoToday ? $todoToday . ' vandaag' : null])) ?: ($todoCount ? 'niets dringends' : 'lekker bezig') ?></div>
        </a>
    </div>

    <div class="col-6 col-xl-3">
        <a href="<?= site_url('klanten') ?>" class="tile">
            <div class="tile-icon"><i class="bi bi-people"></i></div>
            <div class="tile-label">Klanten</div>
            <div class="tile-value"><?= (int) $customers ?></div>
            <div class="tile-sub"><?= $devices !== null ? (int) $devices . ' apparaten in de kluis' : 'actief' ?></div>
        </a>
    </div>
</div>

<?php if (! empty($dsdUnder)) : ?>
<div class="alert alert-danger d-flex justify-content-between align-items-center gap-2">
    <span><i class="bi bi-exclamation-triangle"></i> Bij <?= (int) $dsdUnder ?> licentie(s) heb je bij DSD meer staan dan je factureert.</span>
    <a href="<?= site_url('dsd?filter=under') ?>" class="btn btn-sm btn-danger text-nowrap">Bekijken</a>
</div>
<?php endif ?>

<?php // ---- Kaart en storingen | taken en verlengingen ---- ?>
<div class="row g-3 mb-2">
    <div class="col-xl-8">
        <?php if (isset($customerMap)) : ?>
            <?= view('network/_map', ['map' => $customerMap]) ?>
        <?php endif ?>
        <?php if ($net !== null && ($netCount > 0 || ! empty($netStatus['stale']))) : ?>
            <?= view('network/_offline', ['offline' => $net, 'status' => $netStatus, 'dashboard' => true]) ?>
        <?php endif ?>
    </div>

    <div class="col-xl-4">
        <div class="card mb-4 dash-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-square"></i> Taken</span>
                <a href="<?= site_url('taken') ?>" class="small">Alle<?= $todoCount > count($todos) ? ' (' . (int) $todoCount . ')' : '' ?> →</a>
            </div>
            <div class="card-body">
                <?= view('todos/_quickadd', ['customerId' => null, 'customers' => $todoCustomers]) ?>
                <div class="todo-hide-done mt-2"><?= view('todos/_list', ['todos' => $todos]) ?></div>
            </div>
        </div>

        <?php if (isset($upcoming)) : ?>
        <div class="card mb-4 dash-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-repeat"></i> Verlengingen <span class="text-muted small">60 dagen</span></span>
                <a href="<?= site_url('facturatie') ?>" class="small">Facturatie →</a>
            </div>
            <?php if ($upcoming === []) : ?>
                <div class="card-body text-muted small">Niets te verlengen in de komende 60 dagen.</div>
            <?php else : ?>
            <ul class="renew-list">
                <?php foreach (array_slice($upcoming, 0, 8) as $s) : $d = strtotime($s['next_invoice_date']); ?>
                <li class="<?= $s['next_invoice_date'] < $today ? 'late' : '' ?>">
                    <span class="renew-date"><span><?= (int) date('j', $d) ?></span><?= $mon[(int) date('n', $d)] ?></span>
                    <span class="renew-main">
                        <a href="<?= site_url('klanten/' . $s['customer_id']) ?>" class="renew-cust"><?= esc($s['customer_name']) ?></a>
                        <span class="renew-svc"><?= esc($s['service_name']) ?><?= $s['label'] ? ' · ' . esc($s['label']) : '' ?><?= (float) $s['quantity'] != 1 ? ' · ' . aantal($s['quantity']) . '×' : '' ?></span>
                    </span>
                    <span class="renew-amt"><?= euro($s['eff_price'] * $s['quantity']) ?></span>
                </li>
                <?php endforeach ?>
            </ul>
            <?php if (count($upcoming) > 8) : ?><div class="card-footer small text-center"><a href="<?= site_url('facturatie') ?>">en nog <?= count($upcoming) - 8 ?> →</a></div><?php endif ?>
            <?php endif ?>
        </div>
        <?php endif ?>
    </div>
</div>
<?= view('todos/_script') ?>

<?php // ---- Cijfers ---- ?>
<?php if (isset($activeSubs)) : $maxRev = max(1, ...array_map(static fn ($t) => $t['revenue'], $byType ?: [['revenue' => 1]])); ?>
<div class="dash-section">Cijfers <span>per jaar, excl. btw</span></div>
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100 dash-card"><div class="card-body">
            <div class="row g-3">
                <div class="col-6"><div class="num-label">Omzet</div><div class="num"><?= euro($revenue) ?></div></div>
                <div class="col-6 km-hide"><div class="num-label">Marge</div><div class="num num-accent"><?= euro($revenue - $cost - $ownCost) ?></div>
                    <?php if ($revenue > 0) : ?><div class="num-sub"><?= round(($revenue - $cost - $ownCost) / $revenue * 100) ?>% van de omzet</div><?php endif ?></div>
                <div class="col-6"><div class="num-label">Abonnementen</div><div class="num"><?= (int) $activeSubs ?></div></div>
                <?php if ($ownSubs > 0) : ?>
                <div class="col-6 km-hide"><div class="num-label">Eigen gebruik</div><div class="num"><?= euro($ownCost) ?></div><div class="num-sub"><?= (int) $ownSubs ?> abonnementen</div></div>
                <?php endif ?>
            </div>
            <?php if ($revenue > 0) : $mPct = max(0, min(100, round(($revenue - $cost - $ownCost) / $revenue * 100))); ?>
            <div class="margin-bar km-hide mt-3" title="Marge <?= $mPct ?>%"><span style="width: <?= $mPct ?>%"></span></div>
            <div class="d-flex justify-content-between small text-muted mt-1 km-hide"><span>inkoop</span><span>marge <?= $mPct ?>%</span></div>
            <?php endif ?>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100 dash-card">
            <div class="card-header">Per soort dienst</div>
            <div class="card-body">
                <?php if ($byType === []) : ?><div class="text-muted small">Nog geen abonnementen.</div><?php endif ?>
                <?php uasort($byType, static fn ($a, $b) => $b['revenue'] <=> $a['revenue']); foreach ($byType as $type => $t) : ?>
                <div class="svc-row">
                    <div class="svc-top">
                        <span class="svc-name"><?= esc(service_type_label($type)) ?> <span class="text-muted"><?= aantal($t['qty']) ?>×</span></span>
                        <span class="svc-amt"><?= euro($t['revenue']) ?> <span class="text-muted km-hide">· marge <?= euro($t['revenue'] - $t['cost']) ?></span></span>
                    </div>
                    <div class="svc-bar"><span style="width: <?= round($t['revenue'] / $maxRev * 100) ?>%"></span><span class="svc-margin km-hide" style="width: <?= $t['revenue'] > 0 ? round(max(0, $t['revenue'] - $t['cost']) / $maxRev * 100) : 0 ?>%"></span></div>
                </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>
<?php endif ?>

</div>
