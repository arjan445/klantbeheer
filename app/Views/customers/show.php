<?php
use App\Libraries\Network;

$c         = $customer;
$siteNames = array_column($sites, 'name', 'id');
$network ??= null;
$netSites  = Network::topoSites($network['sites'] ?? []);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('klanten') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Klanten</a>
        <h3 class="mb-0"><?= esc($c['name']) ?> <?= $c['active'] ? '' : '<span class="badge text-bg-secondary fs-6 align-middle">inactief</span>' ?><?= ! empty($c['internal']) ? ' <span class="badge text-bg-info fs-6 align-middle">eigen gebruik</span>' : '' ?></h3>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (count($netSites) === 1) : ?>
            <a href="<?= site_url('netwerk/topologie/' . $netSites[0]['id']) ?>" class="btn btn-primary"><i class="bi bi-diagram-3"></i> Topologie</a>
        <?php elseif ($netSites !== []) : ?>
            <div class="dropdown"><button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-diagram-3"></i> Topologie</button>
                <ul class="dropdown-menu"><?php foreach ($netSites as $ns) : ?><li><a class="dropdown-item" href="<?= site_url('netwerk/topologie/' . $ns['id']) ?>"><?= esc($ns['label'] . ' · ' . $ns['name']) ?></a></li><?php endforeach ?></ul></div>
        <?php endif ?>
        <?php if (user_can('hours.manage')) : ?>
            <a href="<?= site_url('uren/nieuw?klant=' . $c['id']) ?>" class="btn btn-outline-secondary"><i class="bi bi-clock"></i> Uren</a>
        <?php endif ?>
        <?php if (user_can('billing.manage') && empty($c['internal'])) : ?>
            <a href="<?= site_url('offertes/nieuw?klant=' . $c['id']) ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-text"></i> Offerte</a>
        <?php endif ?>
        <?php if (user_can('customers.manage')) : ?>
            <a href="<?= site_url('klanten/' . $c['id'] . '/bewerken') ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Bewerken</a>
        <?php endif ?>
        <?php if ($c['moneybird_contact_id'] && $mb->configured()) : ?>
            <a href="<?= esc($mb->contactUrl($c['moneybird_contact_id']), 'attr') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Moneybird</a>
        <?php endif ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body small">
        <?php if ($c['contact_name']) : ?><div><i class="bi bi-person"></i> <?= esc($c['contact_name']) ?></div><?php endif ?>
        <?php if ($c['email']) : ?><div><i class="bi bi-envelope"></i> <a href="mailto:<?= esc($c['email'], 'attr') ?>"><?= esc($c['email']) ?></a></div><?php endif ?>
        <?php if ($c['phone']) : ?><div><i class="bi bi-telephone"></i> <a href="tel:<?= esc($c['phone'], 'attr') ?>"><?= esc($c['phone']) ?></a></div><?php endif ?>
        <?php if ($c['address'] || $c['city']) : ?><div><i class="bi bi-geo-alt"></i> <?= esc(trim($c['address'] . ', ' . $c['zipcode'] . ' ' . $c['city'], ', ')) ?></div><?php endif ?>
        <?php if (! empty($c['internal'])) : ?>
        <div class="mt-2 text-info"><i class="bi bi-house"></i> Eigen gebruik: wordt niet gefactureerd</div>
        <?php else : ?>
        <div class="mt-2"><?= $c['moneybird_contact_id'] ? '<span class="text-success"><i class="bi bi-link-45deg"></i> Gekoppeld aan Moneybird</span>' : '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> Niet gekoppeld aan Moneybird</span>' ?></div>
        <?php endif ?>
    </div></div></div>
    <?php if (isset($yearly)) : ?>
    <div class="col-md-4"><div class="card h-100 kpi"><div class="card-body">
        <div class="label"><?= ! empty($c['internal']) ? 'Waarde per jaar (eigen gebruik, geen omzet)' : 'Abonnementen per jaar (excl. btw)' ?></div><div class="value"><?= euro($yearly) ?></div>
    </div></div></div>
    <?php endif ?>
    <?php if ($c['notes']) : ?>
    <div class="col-md-4"><div class="card h-100"><div class="card-body small"><div class="text-muted mb-1">Notities</div><?= nl2br(esc($c['notes'])) ?></div></div></div>
    <?php endif ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 gap-2">
    <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Locaties<?= $network !== null ? ' &amp; netwerk' : '' ?>
        <?php if ($network && $network['offline']) : ?><span class="badge text-bg-danger align-middle"><?= (int) $network['offline'] ?> offline</span>
        <?php elseif ($network && $network['total']) : ?><span class="badge text-bg-success align-middle">alles online</span><?php endif ?></h5>
    <?php if (user_can('customers.manage')) : ?><a href="<?= site_url('klanten/' . $c['id'] . '/locaties/nieuw') ?>" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-plus-lg"></i> Locatie</a><?php endif ?>
</div>
<div class="row g-3 mb-4">
    <?php
    $locCards = $sites;
    if (($network['locs'][0]['total'] ?? 0) > 0) { // bewaakte apparaten zonder locatie
        $locCards[] = ['id' => 0, 'name' => 'Zonder locatie', 'address' => null, 'zipcode' => null, 'city' => null, 'notes' => null];
    }
    ?>
    <?php foreach ($locCards as $s) :
        $ln   = $network['locs'][(int) $s['id']] ?? null;
        $addr = trim(trim((string) $s['address']) . ', ' . trim(trim((string) $s['zipcode']) . ' ' . trim((string) $s['city'])), ', ');
        $off  = $ln['offline'] ?? 0;
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 loc-card <?= $off ? 'loc-down' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="fw-semibold"><?= esc($s['name']) ?></div>
                    <?php if ($off) : ?><span class="badge text-bg-danger text-nowrap"><i class="bi bi-wifi-off"></i> <?= (int) $off ?> offline</span>
                    <?php elseif ($ln && $ln['total']) : ?><span class="badge text-bg-success text-nowrap"><i class="bi bi-wifi"></i> <?= (int) $ln['total'] ?> online</span>
                    <?php endif ?>
                </div>
                <?php if ($addr !== '') : ?><div class="small"><a class="text-muted" href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($addr) ?>" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i> <?= esc($addr) ?></a></div><?php endif ?>
                <?php if (! empty($s['notes'])) : ?><div class="small text-muted loc-notes mt-1"><?= nl2br(esc($s['notes'])) ?></div><?php endif ?>

                <?php if ($ln && $ln['down']) : ?>
                <ul class="list-unstyled small mb-0 mt-2">
                    <?php foreach (array_slice($ln['down'], 0, 4) as $d) : ?><li class="text-danger"><i class="bi bi-x-circle"></i> <?= esc($d['name']) ?><?= $d['since'] ? ' <span class="text-muted">· ' . esc(Network::ago($d['since'])) . '</span>' : '' ?></li><?php endforeach ?>
                    <?php if (count($ln['down']) > 4) : ?><li class="text-muted">en <?= count($ln['down']) - 4 ?> meer</li><?php endif ?>
                </ul>
                <?php endif ?>

                <?php foreach ($ln['sites'] ?? [] as $ns) : ?>
                    <?php if (! empty($ns['vpn'])) : ?><div class="mt-2"><?= view('network/_vpn', ['site' => $ns]) ?></div><?php endif ?>
                <?php endforeach ?>

                <?php if (! empty($ln['sites'])) : $topo = Network::topoSites($ln['sites']); ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php foreach ($topo as $ns) : ?>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= site_url('netwerk/topologie/' . $ns['id']) ?>" class="btn btn-primary"><i class="bi bi-diagram-3"></i> <?= count($topo) > 1 ? esc($ns['label'] . ' · ' . $ns['name']) : 'Topologie' . ($ns['combined'] ? ' <span class="opacity-75 small">' . esc($ns['label']) . '</span>' : '') ?></a>
                            <a href="<?= site_url('netwerk/topologie/' . $ns['id'] . '/pdf') ?>" target="_blank" class="btn btn-outline-primary" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                        </div>
                    <?php endforeach ?>
                </div>
                <?php elseif ($network !== null && $s['id']) : ?>
                    <div class="small text-muted mt-2">Geen UniFi- of UISP-site gekoppeld.<?php if (user_can('vault.manage')) : ?> <a href="<?= site_url('netwerk?tab=sites') ?>">Koppelen</a><?php endif ?></div>
                <?php endif ?>
            </div>
            <?php if ($s['id'] && (user_can('customers.manage') || user_can('vault.manage'))) : ?>
            <div class="card-footer bg-transparent small d-flex gap-3">
                <?php if (user_can('vault.manage')) : ?><a href="<?= site_url('apparaten/nieuw?klant=' . $c['id'] . '&locatie=' . $s['id']) ?>"><i class="bi bi-plus-lg"></i> Apparaat</a><?php endif ?>
                <?php if (user_can('customers.manage')) : ?><a href="<?= site_url('klanten/locaties/' . $s['id'] . '/bewerken') ?>" class="ms-auto text-muted"><i class="bi bi-pencil"></i> Bewerken</a><?php endif ?>
            </div>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>
    <?php if ($locCards === []) : ?><div class="col-12"><div class="card"><div class="card-body text-muted small">Nog geen locaties.</div></div></div><?php endif ?>
</div>

<?php if (isset($devices) || isset($subscriptions)) : ?>
<ul class="nav nav-tabs" role="tablist">
    <?php if (isset($devices)) : ?>
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-devices">Apparaten &amp; wachtwoorden <span class="badge text-bg-light"><?= count($devices) ?></span></button></li>
    <?php endif ?>
    <?php if (isset($subscriptions)) : ?>
    <li class="nav-item"><button class="nav-link <?= isset($devices) ? '' : 'active' ?>" data-bs-toggle="tab" data-bs-target="#t-subs">Abonnementen <span class="badge text-bg-light"><?= count($subscriptions) ?></span></button></li>
    <?php endif ?>
    <?php if (isset($domains)) : ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-domains">Domeinen <span class="badge text-bg-light"><?= count($domains) ?></span></button></li>
    <?php endif ?>
    <?php if (isset($invoices)) : ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-inv">Gefactureerd</button></li>
    <?php endif ?>
</ul>

<div class="tab-content card border-top-0 rounded-top-0"><div class="card-body">

<?php if (isset($devices)) : ?>
<div class="tab-pane fade show active" id="t-devices">
    <?php if (user_can('vault.manage')) : ?>
    <div class="mb-3 d-flex gap-2 flex-wrap"><a href="<?= site_url('apparaten/nieuw?klant=' . $c['id'] . (count($sites) === 1 ? '&locatie=' . $sites[0]['id'] : '')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Apparaat toevoegen</a>
        <a href="<?= site_url('scan?klant=' . $c['id'] . (count($sites) === 1 ? '&locatie=' . $sites[0]['id'] : '')) ?>" class="btn btn-outline-primary"><i class="bi bi-upc-scan"></i> Doosscan</a></div>
    <?php endif ?>
    <?php if ($devices === []) : ?>
        <p class="text-muted mb-0">Nog geen apparaten of wachtwoorden bij deze klant.</p>
    <?php else : ?>
        <?php
        // Groeperen per locatie
        $groups = [];
        foreach ($devices as $d) { $groups[$d['site_id'] ?? 0][] = $d; }
        ?>
        <?php foreach ($groups as $siteId => $list) : ?>
            <h6 class="mt-3 d-flex justify-content-between align-items-center"><span><i class="bi bi-geo-alt"></i> <?= esc($siteId ? ($siteNames[$siteId] ?? 'Onbekende locatie') : 'Geen locatie') ?> <span class="badge text-bg-light"><?= count($list) ?></span></span>
                <?php if (user_can('vault.manage')) : ?><a href="<?= site_url('apparaten/nieuw?klant=' . $c['id'] . ($siteId ? '&locatie=' . $siteId : '')) ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-plus-lg"></i> Hier toevoegen</a><?php endif ?></h6>
            <?= view('devices/_table', ['devices' => $list, 'siteNames' => $siteNames]) ?>
        <?php endforeach ?>
    <?php endif ?>
</div>
<?php endif ?>

<?php if (isset($subscriptions)) : ?>
<div class="tab-pane fade <?= isset($devices) ? '' : 'show active' ?>" id="t-subs">
    <?php if (user_can('billing.manage')) : ?>
    <div class="mb-3"><a href="<?= site_url('abonnementen/nieuw?klant=' . $c['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Abonnement toevoegen</a></div>
    <?php endif ?>
    <?= view('subscriptions/_table', ['subscriptions' => $subscriptions, 'showCustomer' => false]) ?>
</div>
<?php endif ?>

<?php if (isset($domains)) : ?>
<div class="tab-pane fade" id="t-domains">
    <?php if (user_can('billing.manage')) : ?>
    <div class="mb-3 d-flex gap-2">
        <a href="<?= site_url('domeinen/snel?klant=' . $c['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-lightning-charge"></i> Snel invoeren</a>
        <a href="<?= site_url('domeinen/nieuw?klant=' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg"></i> Los domein toevoegen</a>
    </div>
    <?php endif ?>
    <?= view('domains/_table', ['domains' => $domains, 'showCustomer' => false]) ?>
</div>
<?php endif ?>

<?php if (isset($invoices)) : ?>
<div class="tab-pane fade" id="t-inv">
    <?= view('billing/_history', ['lines' => $invoices, 'showCustomer' => false, 'mb' => $mb]) ?>
</div>
<?php endif ?>

</div></div>
<?php endif ?>

<?php if (isset($hours)) : ?>
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock"></i> Uren</span>
        <a href="<?= site_url('uren/nieuw?klant=' . $c['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Uren boeken</a>
    </div>
    <?php if ($hours === []) : ?>
        <div class="card-body text-muted small">Nog geen uren geboekt.</div>
    <?php else : ?>
    <ul class="list-group list-group-flush">
        <?php foreach ($hours as $h) : ?>
        <li class="list-group-item"><a href="<?= site_url('uren/' . $h['id']) ?>" class="d-flex justify-content-between gap-2 text-reset text-decoration-none">
            <span><span class="text-muted"><?= date('d-m-Y', strtotime($h['date'])) ?></span> · <?= esc($h['description']) ?><?= $h['site_name'] ? ' <span class="text-muted">· ' . esc($h['site_name']) . '</span>' : '' ?></span>
            <span class="fw-semibold text-nowrap"><?= \App\Controllers\Hours::hm(\App\Models\TimeEntryModel::minutes($h)) ?></span>
        </a></li>
        <?php endforeach ?>
    </ul>
    <?php endif ?>
</div>
<?php endif ?>

<div class="card mt-3">
    <div class="card-header"><i class="bi bi-check2-square"></i> Taken</div>
    <div class="card-body">
        <?= view('todos/_quickadd', ['customerId' => $c['id']]) ?>
        <div class="todo-hide-done mt-2"><?= view('todos/_list', ['todos' => $todos, 'showCustomer' => false, 'empty' => 'Geen open taken voor deze klant.']) ?></div>
    </div>
</div>
<?= view('todos/_script') ?>
<script>
// Laatst gekozen tabblad onthouden (ook na terugkomen van de topologie)
(function () {
    const key = 'klant.tab';
    let want = location.hash;
    try { want = want || sessionStorage.getItem(key); } catch (e) {}
    const btn = want && document.querySelector('[data-bs-target="' + CSS.escape(want) + '"]');
    if (btn && window.bootstrap) bootstrap.Tab.getOrCreateInstance(btn).show();
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(b => b.addEventListener('shown.bs.tab', () => {
        try { sessionStorage.setItem(key, b.dataset.bsTarget); } catch (e) {}
        history.replaceState(null, '', b.dataset.bsTarget);
    }));
})();
</script>
