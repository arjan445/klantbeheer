<?php
/**
 * Tabel met kluisitems.
 * @var list<array> $devices
 * @var bool        $showCustomer
 * @var array       $siteNames  site_id => naam
 */
$showCustomer ??= false;
$siteNames ??= [];
?>
<div class="dev-table">
<table class="table table-sm table-hover align-middle <?= count($devices) > 15 ? 'datatable' : '' ?>">
    <thead><tr>
        <th>Soort</th><th>Naam</th>
        <?php if ($showCustomer) : ?><th>Klant</th><?php endif ?>
        <th>Locatie</th><th>IP / URL</th><th>Merk / model</th><th>Gebruiker</th><th>Wachtwoord</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($devices as $d) : ?>
        <tr>
            <td class="text-nowrap"><?= device_type_icon($d['type']) ?> <span class="small text-muted"><?= esc(device_type_label($d['type'])) ?></span></td>
            <td><a href="<?= site_url('apparaten/' . $d['id']) ?>" class="fw-semibold"><?= esc($d['name']) ?></a><?php if (! empty($d['room'])) : ?><div class="small text-muted"><?= esc($d['room']) ?></div><?php endif ?></td>
            <?php if ($showCustomer) : ?><td><a href="<?= site_url('klanten/' . $d['customer_id']) ?>"><?= esc($d['customer_name'] ?? '') ?></a></td><?php endif ?>
            <td><?= esc($d['site_name'] ?? ($siteNames[$d['site_id']] ?? '')) ?></td>
            <td class="mono small">
                <?php if ($d['ip']) : ?><?= esc($d['ip']) ?><?= $d['port'] ? ':' . esc($d['port']) : '' ?>
                    <button class="btn btn-link btn-sm p-0 ms-1" data-copy data-copy-value="<?= esc($d['ip'], 'attr') ?>" title="Kopieer IP"><i class="bi bi-clipboard"></i></button><?php endif ?>
                <?php if ($d['url']) : ?><div><a href="<?= esc($d['url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc(mb_strimwidth($d['url'], 0, 40, '…')) ?></a></div><?php endif ?>
            </td>
            <td class="small"><?= esc(trim(($d['brand'] ?? '') . ' ' . ($d['model'] ?? ''))) ?></td>
            <td class="mono small"><?= esc($d['username']) ?>
                <?php if ($d['username']) : ?><button class="btn btn-link btn-sm p-0 ms-1" data-copy data-copy-value="<?= esc($d['username'], 'attr') ?>" title="Kopieer gebruikersnaam"><i class="bi bi-clipboard"></i></button><?php endif ?>
            </td>
            <td class="text-nowrap">
                <?php if ($d['password_enc']) : ?>
                    <span id="pw<?= $d['id'] ?>" class="secret">••••••••</span>
                    <button class="btn btn-link btn-sm p-0 ms-1" data-reveal="<?= site_url('apparaten/' . $d['id'] . '/geheim') ?>" data-target="#pw<?= $d['id'] ?>" title="Tonen"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-link btn-sm p-0" data-copy="<?= site_url('apparaten/' . $d['id'] . '/geheim') ?>" title="Kopiëren"><i class="bi bi-clipboard"></i></button>
                <?php else : ?><span class="text-muted small">—</span><?php endif ?>
            </td>
            <td class="text-end text-nowrap">
                <?php if (user_can('vault.manage')) : ?><a href="<?= site_url('apparaten/' . $d['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary" title="Bewerken"><i class="bi bi-pencil"></i></a><?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div>

<!-- Mobiel: kaartjes -->
<div class="dev-cards">
<?php foreach ($devices as $d) : ?>
    <div class="dev-card">
        <div class="d-flex justify-content-between align-items-start">
            <a href="<?= site_url('apparaten/' . $d['id']) ?>" class="dev-title text-decoration-none"><?= device_type_icon($d['type']) ?> <?= esc($d['name']) ?></a>
            <?php if (user_can('vault.manage')) : ?><a href="<?= site_url('apparaten/' . $d['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-pencil"></i></a><?php endif ?>
        </div>
        <div class="small text-muted"><?= esc(implode(' · ', array_filter([$showCustomer ? ($d['customer_name'] ?? '') : null, $d['room'] ?? null, trim(($d['brand'] ?? '') . ' ' . ($d['model'] ?? ''))]))) ?></div>
        <?php if ($d['ip']) : ?>
            <div class="dev-row"><span class="label">IP</span><span class="mono"><?= esc($d['ip']) ?><?= $d['port'] ? ':' . esc($d['port']) : '' ?></span>
                <button class="btn btn-link btn-sm p-0" data-copy data-copy-value="<?= esc($d['ip'], 'attr') ?>"><i class="bi bi-clipboard"></i></button></div>
        <?php endif ?>
        <?php if ($d['username']) : ?>
            <div class="dev-row"><span class="label">Gebruiker</span><span class="mono"><?= esc($d['username']) ?></span>
                <button class="btn btn-link btn-sm p-0" data-copy data-copy-value="<?= esc($d['username'], 'attr') ?>"><i class="bi bi-clipboard"></i></button></div>
        <?php endif ?>
        <?php if ($d['password_enc']) : ?>
            <div class="dev-row"><span class="label">Wachtwoord</span><span id="pwm<?= $d['id'] ?>" class="secret">••••••••</span>
                <button class="btn btn-link btn-sm p-0" data-reveal="<?= site_url('apparaten/' . $d['id'] . '/geheim') ?>" data-target="#pwm<?= $d['id'] ?>"><i class="bi bi-eye"></i></button>
                <button class="btn btn-link btn-sm p-0" data-copy="<?= site_url('apparaten/' . $d['id'] . '/geheim') ?>"><i class="bi bi-clipboard"></i></button></div>
        <?php endif ?>
        <?php if (! empty($d['serial']) && device_is_sensor($d['type'])) : ?>
            <div class="dev-row"><span class="label">ID</span><span class="mono"><?= esc($d['serial']) ?></span></div>
        <?php endif ?>
    </div>
<?php endforeach ?>
</div>
