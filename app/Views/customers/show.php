<?php $c = $customer; $siteNames = array_column($sites, 'name', 'id'); ?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('klanten') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Klanten</a>
        <h3 class="mb-0"><?= esc($c['name']) ?> <?= $c['active'] ? '' : '<span class="badge text-bg-secondary fs-6 align-middle">inactief</span>' ?></h3>
    </div>
    <div class="d-flex gap-2">
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
        <div class="mt-2"><?= $c['moneybird_contact_id'] ? '<span class="text-success"><i class="bi bi-link-45deg"></i> Gekoppeld aan Moneybird</span>' : '<span class="text-warning"><i class="bi bi-exclamation-circle"></i> Niet gekoppeld aan Moneybird</span>' ?></div>
    </div></div></div>
    <?php if (isset($yearly)) : ?>
    <div class="col-md-4"><div class="card h-100 kpi"><div class="card-body">
        <div class="label">Abonnementen per jaar (excl. btw)</div><div class="value"><?= euro($yearly) ?></div>
    </div></div></div>
    <?php endif ?>
    <?php if ($c['notes']) : ?>
    <div class="col-md-4"><div class="card h-100"><div class="card-body small"><div class="text-muted mb-1">Notities</div><?= nl2br(esc($c['notes'])) ?></div></div></div>
    <?php endif ?>
</div>

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
    <li class="nav-item"><button class="nav-link <?= isset($devices) || isset($subscriptions) ? '' : 'active' ?>" data-bs-toggle="tab" data-bs-target="#t-sites">Locaties <span class="badge text-bg-light"><?= count($sites) ?></span></button></li>
    <?php if (isset($invoices)) : ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-inv">Gefactureerd</button></li>
    <?php endif ?>
</ul>

<div class="tab-content card border-top-0 rounded-top-0"><div class="card-body">

<?php if (isset($devices)) : ?>
<div class="tab-pane fade show active" id="t-devices">
    <?php if (user_can('vault.manage')) : ?>
    <div class="mb-3"><a href="<?= site_url('apparaten/nieuw?klant=' . $c['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Apparaat / wachtwoord toevoegen</a></div>
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
            <h6 class="mt-2"><i class="bi bi-geo-alt"></i> <?= esc($siteId ? ($siteNames[$siteId] ?? 'Onbekende locatie') : 'Geen locatie') ?></h6>
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
    <div class="mb-3"><a href="<?= site_url('domeinen/nieuw?klant=' . $c['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Domein toevoegen</a></div>
    <?php endif ?>
    <?= view('domains/_table', ['domains' => $domains, 'showCustomer' => false]) ?>
</div>
<?php endif ?>

<div class="tab-pane fade <?= isset($devices) || isset($subscriptions) ? '' : 'show active' ?>" id="t-sites">
    <?php if (user_can('customers.manage')) : ?>
    <div class="mb-3"><a href="<?= site_url('klanten/' . $c['id'] . '/locaties/nieuw') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Locatie toevoegen</a></div>
    <?php endif ?>
    <table class="table table-sm">
        <thead><tr><th>Naam</th><th>Adres</th><th>Notities</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($sites as $s) : ?>
            <tr>
                <td class="fw-semibold"><?= esc($s['name']) ?></td>
                <td><?= esc(trim($s['address'] . ', ' . $s['zipcode'] . ' ' . $s['city'], ', ')) ?></td>
                <td class="small"><?= nl2br(esc($s['notes'] ?? '')) ?></td>
                <td class="text-end text-nowrap">
                    <?php if (user_can('customers.manage')) : ?>
                        <a href="<?= site_url('klanten/locaties/' . $s['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        <?php if ($sites === []) : ?><tr><td colspan="4" class="text-muted">Nog geen locaties.</td></tr><?php endif ?>
        </tbody>
    </table>
</div>

<?php if (isset($invoices)) : ?>
<div class="tab-pane fade" id="t-inv">
    <?= view('billing/_history', ['lines' => $invoices, 'showCustomer' => false, 'mb' => $mb]) ?>
</div>
<?php endif ?>

</div></div>
