<?php $d = $device; ?>
<a href="<?= site_url(! empty($d['customer_id']) ? 'klanten/' . $d['customer_id'] : 'apparaten') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Terug</a>
<h3 class="mb-3"><?= $isNew ? 'Apparaat / wachtwoord toevoegen' : 'Bewerken: ' . esc($d['name']) ?></h3>
<?php if (! $ready) : ?>
    <div class="alert alert-danger">Geen <code>encryption.key</code> ingesteld: wachtwoorden kunnen niet worden opgeslagen.</div>
<?php endif ?>
<form method="post" class="card" autocomplete="off"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Klant *</label>
            <select name="customer_id" id="customer_id" class="form-select" required>
                <option value="">Kies een klant…</option>
                <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>" <?= (string) ($d['customer_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label">Locatie</label>
            <select name="site_id" class="form-select" data-sites-for="#customer_id">
                <option value="">—</option>
                <?php foreach ($sites as $site) : ?><option value="<?= $site['id'] ?>" data-customer="<?= $site['customer_id'] ?>" <?= (string) ($d['site_id'] ?? '') === (string) $site['id'] ? 'selected' : '' ?>><?= esc($site['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label">Soort *</label>
            <select name="type" class="form-select">
                <?php foreach (device_types() as $k => [$label]) : ?><option value="<?= $k ?>" <?= ($d['type'] ?? '') === $k ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
            </select>
        </div>

        <div class="col-md-4"><label class="form-label">Naam *</label><input name="name" class="form-control" required value="<?= esc($d['name'] ?? '') ?>" placeholder="Bijv. Camera oprit, NVR kantoor, Wifi gasten"></div>
        <div class="col-md-4"><label class="form-label">Merk</label><input name="brand" class="form-control" list="brands" value="<?= esc($d['brand'] ?? '') ?>">
            <datalist id="brands"><option>Hikvision</option><option>Ubiquiti</option><option>HiLook</option><option>Dahua</option><option>Synology</option><option>Microsoft</option></datalist></div>
        <div class="col-md-4"><label class="form-label">Model</label><input name="model" class="form-control" value="<?= esc($d['model'] ?? '') ?>" placeholder="Bijv. DS-2CD2347G2-LU, U6-Pro"></div>

        <div class="col-md-3"><label class="form-label">IP-adres</label><input name="ip" class="form-control mono" value="<?= esc($d['ip'] ?? '') ?>" placeholder="192.168.1.64"></div>
        <div class="col-md-2"><label class="form-label">Poort</label><input name="port" class="form-control mono" value="<?= esc($d['port'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">MAC-adres</label><input name="mac" class="form-control mono" value="<?= esc($d['mac'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">URL / beheeradres</label><input name="url" class="form-control" value="<?= esc($d['url'] ?? '') ?>" placeholder="https://… of Hik-Connect / UniFi-site"></div>

        <div class="col-md-3"><label class="form-label">Serienummer</label><input name="serial" class="form-control mono" value="<?= esc($d['serial'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Firmware</label><input name="firmware" class="form-control" value="<?= esc($d['firmware'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Geïnstalleerd op</label><input type="date" name="installed_at" class="form-control" value="<?= esc($d['installed_at'] ?? '') ?>"></div>

        <div class="col-12"><hr class="my-1"><div class="small text-muted"><i class="bi bi-shield-lock"></i> Inloggegevens: het wachtwoord en de geheime notitie worden versleuteld opgeslagen.</div></div>
        <div class="col-md-4"><label class="form-label">Gebruikersnaam</label><input name="username" class="form-control mono" autocomplete="off" value="<?= esc($d['username'] ?? '') ?>" placeholder="admin"></div>
        <div class="col-md-5"><label class="form-label">Wachtwoord</label>
            <div class="input-group">
                <input name="password" id="password" type="password" class="form-control mono" autocomplete="new-password" placeholder="<?= ! empty($d['password_enc']) ? '•••••••• (leeg laten = ongewijzigd)' : '' ?>">
                <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'" title="Tonen"><i class="bi bi-eye"></i></button>
                <button class="btn btn-outline-secondary" type="button" data-generate="#password" title="Genereer sterk wachtwoord"><i class="bi bi-magic"></i></button>
            </div>
            <?php if (! empty($d['password_enc']) && ! $isNew) : ?>
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_password" value="1" id="cp"><label class="form-check-label small" for="cp">Wachtwoord wissen</label></div>
            <?php endif ?>
        </div>
        <div class="col-12"><label class="form-label">Geheime notitie</label>
            <textarea name="secret_notes" rows="2" class="form-control mono" placeholder="<?= ! empty($d['secret_notes_enc']) ? '(versleutelde notitie aanwezig — leeg laten = ongewijzigd)' : 'Bijv. verificatiecode Hik-Connect, herstelcodes, wifi-sleutel, PUK' ?>"></textarea>
            <?php if (! empty($d['secret_notes_enc']) && ! $isNew) : ?>
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_secret" value="1" id="cs"><label class="form-check-label small" for="cs">Geheime notitie wissen</label></div>
            <?php endif ?>
        </div>
        <div class="col-12"><label class="form-label">Notities (niet versleuteld)</label><textarea name="notes" rows="2" class="form-control"><?= esc($d['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" name="next" value="">Opslaan</button>
        <button class="btn btn-outline-primary" name="next" value="copy" title="Opslaan en een nieuw apparaat met dezelfde gegevens en het volgende IP-adres beginnen">Opslaan &amp; volgende (kopie)</button>
    </div>
</div></form>
<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('apparaten/' . $d['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Verwijderen', 'Dit apparaat en het opgeslagen wachtwoord verwijderen?') ?></div>
<?php endif ?>
