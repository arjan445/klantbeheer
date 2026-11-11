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
            <dt class="col-sm-4">Locatie</dt><dd class="col-sm-8"><?= esc($site['name'] ?? '—') ?></dd>
            <?php foreach (['brand' => 'Merk', 'model' => 'Model', 'serial' => 'Serienummer', 'firmware' => 'Firmware', 'mac' => 'MAC-adres'] as $f => $label) : ?>
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
    <div class="col-lg-5"><div class="card"><div class="card-header">Laatste activiteit</div><div class="card-body p-0">
        <table class="table table-sm mb-0">
            <?php foreach ($log as $l) : ?>
                <tr><td class="small text-nowrap"><?= date('d-m-Y H:i', strtotime($l['created_at'])) ?></td><td class="small"><?= esc($l['username'] ?: $l['email']) ?></td><td class="small"><?= esc($l['action']) ?></td></tr>
            <?php endforeach ?>
            <?php if ($log === []) : ?><tr><td class="text-muted small p-3">Nog geen activiteit.</td></tr><?php endif ?>
        </table>
    </div></div></div>
</div>
