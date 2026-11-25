<?php $d = $device; ?>
<a href="<?= site_url('klanten/' . $d['customer_id']) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> <?= esc($customer['name'] ?? '') ?></a>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <h3 class="mb-0"><?= device_type_icon($d['type']) ?> <?= esc($d['name']) ?></h3>
    <?php if (user_can('vault.manage')) : ?>
    <div class="d-flex gap-2">
        <a href="<?= site_url('apparaten/nieuw?kopie=' . $d['id']) ?>" class="btn btn-outline-secondary"><i class="bi bi-copy"></i> Kopie maken</a>
        <a href="<?= site_url('apparaten/' . $d['id'] . '/bewerken') ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Bewerken</a>
    </div>
    <?php endif ?>
</div>
<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Soort</dt><dd class="col-sm-8"><?= esc(device_type_label($d['type'])) ?></dd>
            <dt class="col-sm-4">Klant</dt><dd class="col-sm-8"><a href="<?= site_url('klanten/' . $d['customer_id']) ?>"><?= esc($customer['name'] ?? '') ?></a></dd>
            <dt class="col-sm-4">Locatie</dt><dd class="col-sm-8"><?= esc($site['name'] ?? '—') ?><?= ! empty($d['room']) ? ' · ' . esc($d['room']) : '' ?></dd>
            <?php foreach (['brand' => 'Merk', 'model' => 'Model', 'serial' => device_is_sensor($d['type']) ? 'Device-ID' : 'Serienummer', 'firmware' => 'Firmware', 'mac' => 'MAC-adres'] as $f => $label) : ?>
                <?php if ($d[$f]) : ?><dt class="col-sm-4"><?= $label ?></dt><dd class="col-sm-8 <?= in_array($f, ['serial', 'mac'], true) ? 'mono' : '' ?>"><?= esc($d[$f]) ?></dd><?php endif ?>
            <?php endforeach ?>
            <?php if ($d['ip']) : ?><dt class="col-sm-4">IP-adres</dt><dd class="col-sm-8 mono"><?= esc($d['ip']) ?><?= $d['port'] ? ':' . esc($d['port']) : '' ?> <button class="btn btn-link btn-sm p-0" data-copy data-copy-value="<?= esc($d['ip'], 'attr') ?>"><i class="bi bi-clipboard"></i></button></dd><?php endif ?>
            <?php if ($d['url']) : ?><dt class="col-sm-4">URL</dt><dd class="col-sm-8"><a href="<?= esc($d['url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= esc($d['url']) ?></a></dd><?php endif ?>
            <?php if ($d['installed_at']) : ?><dt class="col-sm-4">Geïnstalleerd</dt><dd class="col-sm-8"><?= datum($d['installed_at']) ?></dd><?php endif ?>
            <dt class="col-sm-4">Gebruikersnaam</dt><dd class="col-sm-8 mono"><?= esc($d['username'] ?: '—') ?>
                <?php if ($d['username']) : ?><button class="btn btn-link btn-sm p-0" data-copy data-copy-value="<?= esc($d['username'], 'attr') ?>"><i class="bi bi-clipboard"></i></button><?php endif ?></dd>
            <dt class="col-sm-4">Wachtwoord</dt><dd class="col-sm-8">
                <?php if ($d['password_enc']) : ?>
                    <span id="pw" class="secret">••••••••</span>
                    <button class="btn btn-link btn-sm p-0 ms-1" data-reveal="<?= site_url('apparaten/' . $d['id'] . '/geheim') ?>" data-target="#pw"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-link btn-sm p-0" data-copy="<?= site_url('apparaten/' . $d['id'] . '/geheim') ?>"><i class="bi bi-clipboard"></i></button>
                <?php else : ?>—<?php endif ?>
            </dd>
            <?php if ($d['secret_notes_enc']) : ?>
            <dt class="col-sm-4">Geheime notitie</dt><dd class="col-sm-8">
                <pre id="sn" class="secret mb-0 d-inline" style="white-space: pre-wrap">••••••••</pre>
                <button class="btn btn-link btn-sm p-0 ms-1" data-reveal="<?= site_url('apparaten/' . $d['id'] . '/geheim') ?>" data-field="secret_notes" data-target="#sn"><i class="bi bi-eye"></i></button>
            </dd>
            <?php endif ?>
        </dl>
        <?php if ($d['notes']) : ?><hr><div class="small"><?= nl2br(esc($d['notes'])) ?></div><?php endif ?>
    </div></div></div>
    <div class="col-lg-5">
    <?php if ($network) : ?>
    <?php $nd = $network; $loc = current_url(); ?>
    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">
        <span><?= \App\Libraries\Network::dot($nd) ?> <?= esc(\App\Libraries\Network::platformLabel($nd['platform'])) ?></span>
        <span class="small fw-normal text-muted"><?= esc($nd['nsite_name'] ?? '') ?></span>
    </div><div class="card-body">
        <dl class="row mb-0 small">
            <dt class="col-5">Status</dt><dd class="col-7">
                <?php if ($nd['gone']) : ?><span class="text-muted">Niet meer gevonden</span>
                <?php elseif ($nd['status'] === 'online') : ?><span class="text-success fw-semibold">Online</span>
                <?php elseif ($nd['status'] === 'offline') : ?><span class="text-danger fw-semibold">Offline</span><?= $nd['offline_since'] ? ' sinds ' . esc(\App\Libraries\Network::ago($nd['offline_since'])) : '' ?>
                <?php else : ?><span class="text-muted"><?= esc($nd['raw_status'] ?: 'Onbekend') ?></span><?php endif ?>
            </dd>
            <?php if ($nd['last_seen_at']) : ?><dt class="col-5">Laatst gezien</dt><dd class="col-7"><?= date('d-m-Y H:i', strtotime($nd['last_seen_at'])) ?></dd><?php endif ?>
            <?php if ($nd['name']) : ?><dt class="col-5">Naam in <?= esc(\App\Libraries\Network::platformLabel($nd['platform'])) ?></dt><dd class="col-7"><?= esc($nd['name']) ?></dd><?php endif ?>
            <?php if ($nd['ip']) : ?><dt class="col-5">IP</dt><dd class="col-7 mono"><?= esc($nd['ip']) ?></dd><?php endif ?>
        </dl>
        <?php if (user_can('vault.manage')) : ?>
        <div class="d-flex gap-3 mt-2 flex-wrap small">
            <form method="post" action="<?= site_url('netwerk/apparaat/' . $nd['id'] . '/bewaken') ?>" class="d-inline"><?= csrf_field() ?>
                <input type="hidden" name="return" value="<?= esc($loc, 'attr') ?>"><input type="hidden" name="monitor" value="<?= $nd['monitor'] ? '0' : '1' ?>">
                <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" id="mon" <?= $nd['monitor'] ? 'checked' : '' ?> onchange="this.form.submit()">
                <label class="form-check-label" for="mon">Hoort online te zijn</label></div>
            </form>
            <?= post_link(site_url('netwerk/apparaat/' . $nd['id'] . '/ontkoppelen'), '<i class="bi bi-x-lg"></i> Ontkoppelen', 'Dit apparaat loskoppelen van ' . \App\Libraries\Network::platformLabel($nd['platform']) . '?', 'text-muted small') ?>
        </div>
        <?php endif ?>
    </div></div>
    <?php endif ?>

    <?php if ($d['sold_at'] || $invoice || $quote) : ?>
    <div class="card mb-3"><div class="card-header">Verkoop &amp; inkoop</div><div class="card-body">
        <dl class="row mb-0 small">
            <?php if ($d['sold_at']) : ?><dt class="col-5">Verkocht op</dt><dd class="col-7"><?= datum($d['sold_at']) ?></dd><?php endif ?>
            <?php if ($quote) : ?><dt class="col-5">Offerte</dt><dd class="col-7"><?php if (user_can('billing.view')) : ?><a href="<?= site_url('offertes/' . $quote['id']) ?>">#<?= (int) $quote['id'] ?><?= $quote['moneybird_number'] ? ' · ' . esc($quote['moneybird_number']) : '' ?></a><?php else : ?>#<?= (int) $quote['id'] ?><?php endif ?><?= $quote['title'] ? '<div class="text-muted">' . esc($quote['title']) . '</div>' : '' ?></dd><?php endif ?>
            <?php if ($invoice) : ?><dt class="col-5">Inkoopfactuur</dt><dd class="col-7"><a href="<?= site_url('inkoopfacturen/' . $invoice['id']) ?>"><?= esc(\App\Models\PurchaseInvoiceModel::label($invoice)) ?></a></dd><?php endif ?>
        </dl>
    </div></div>
    <?php endif ?>

    <div class="card"><div class="card-header">Laatste activiteit</div><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <?php foreach ($log as $l) : ?>
                <tr><td class="small text-nowrap"><?= date('d-m-Y H:i', strtotime($l['created_at'])) ?></td><td class="small"><?= esc($l['username'] ?: $l['email']) ?></td><td class="small"><?= esc($l['action']) ?></td></tr>
            <?php endforeach ?>
            <?php if ($log === []) : ?><tr><td class="text-muted small p-3">Nog geen activiteit.</td></tr><?php endif ?>
        </table>
    </div></div>
    </div>
</div>
