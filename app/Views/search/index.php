<h3 class="mb-3">Zoeken<?= $q !== '' ? ': <span class="text-muted">' . esc($q) . '</span>' : '' ?></h3>
<?php if (mb_strlen($q) < 2) : ?>
    <p class="text-muted">Typ minstens 2 tekens: klantnaam, plaats, IP-adres, serienummer, MAC-adres of domeinnaam.</p>
<?php elseif (! $customers && ! $sites && ! $devices && ! $subscriptions) : ?>
    <p class="text-muted">Niets gevonden.</p>
<?php endif ?>

<?php if ($customers) : ?>
<div class="card mb-3"><div class="card-header">Klanten</div><div class="card-body p-0"><table class="table table-sm mb-0">
    <?php foreach ($customers as $c) : ?>
        <tr><td><a href="<?= site_url('klanten/' . $c['id']) ?>" class="fw-semibold"><?= esc($c['name']) ?></a></td><td><?= esc($c['contact_name']) ?></td><td><?= esc($c['city']) ?></td><td><?= esc($c['email']) ?></td></tr>
    <?php endforeach ?>
</table></div></div>
<?php endif ?>

<?php if ($sites) : ?>
<div class="card mb-3"><div class="card-header">Locaties</div><div class="card-body p-0"><table class="table table-sm mb-0">
    <?php foreach ($sites as $s) : ?>
        <tr><td><a href="<?= site_url('klanten/' . $s['customer_id']) ?>"><?= esc($s['customer_name']) ?></a></td><td class="fw-semibold"><?= esc($s['name']) ?></td><td><?= esc(trim($s['address'] . ', ' . $s['city'], ', ')) ?></td></tr>
    <?php endforeach ?>
</table></div></div>
<?php endif ?>

<?php if ($devices) : ?>
<div class="card mb-3"><div class="card-header">Apparaten</div><div class="card-body">
    <?= view('devices/_table', ['devices' => $devices, 'showCustomer' => true]) ?>
</div></div>
<?php endif ?>

<?php if ($subscriptions) : ?>
<div class="card mb-3"><div class="card-header">Abonnementen</div><div class="card-body">
    <?= view('subscriptions/_table', ['subscriptions' => $subscriptions, 'showCustomer' => true]) ?>
</div></div>
<?php endif ?>
