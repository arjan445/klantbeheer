<?php
$d       = $device;
$type    = $d['type'] ?? 'camera';
$types   = device_types();
$back    = ! empty($d['customer_id']) ? 'klanten/' . $d['customer_id'] : 'apparaten';
$custMap = array_column($customers, 'name', 'id');
$prefill = $isNew && ! empty($d['customer_id']) && isset($custMap[$d['customer_id']]);
$brands  = array_values(array_unique(array_filter(array_merge(array_column($types, 3), ['Hikvision', 'HiLook', 'Dahua', 'Ubiquiti', 'Ajax', 'Synology', 'Paxton', 'Microsoft']))));
?>
<a href="<?= site_url($back) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Terug</a>
<h3 class="mb-3"><?= $isNew ? 'Apparaat toevoegen' : 'Bewerken: ' . esc($d['name']) ?></h3>
<?php if (! $ready) : ?>
    <div class="alert alert-danger">Geen <code>encryption.key</code> ingesteld: wachtwoorden kunnen niet worden opgeslagen.</div>
<?php endif ?>
<?php if (! empty($d['copied_from'])) : ?>
    <div class="alert alert-info py-2 small"><i class="bi bi-copy"></i> Gegevens overgenomen van het vorige apparaat. Naam en IP zijn opgehoogd; vul serienummer en ruimte in.</div>
<?php endif ?>

<form method="post" autocomplete="off" id="device-form">
<?= csrf_field() ?>
<input type="hidden" name="type" id="type" value="<?= esc($type, 'attr') ?>">

<!-- Klant en locatie -->
<div class="card mb-3"><div class="card-body">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small mb-1">Klant *</label>
            <select name="customer_id" id="customer_id" class="form-select" required>
                <option value="">Kies een klant…</option>
                <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>" <?= (string) ($d['customer_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Locatie</label>
            <select name="site_id" class="form-select" data-sites-for="#customer_id">
                <option value="">—</option>
                <?php foreach ($sites as $site) : ?><option value="<?= $site['id'] ?>" data-customer="<?= $site['customer_id'] ?>" <?= (string) ($d['site_id'] ?? '') === (string) $site['id'] ? 'selected' : '' ?>><?= esc($site['name']) ?></option><?php endforeach ?>
            </select>
        </div>
    </div>
</div></div>

<!-- Soort: knoppen per groep -->
<div class="card mb-3"><div class="card-body">
    <label class="form-label small mb-2">Soort</label>
    <?php foreach (device_type_groups() as $group => $list) : ?>
        <div class="small text-muted mt-2 mb-1"><?= esc($group) ?></div>
        <div class="type-grid">
            <?php foreach ($list as $key => [$label, $icon, $kind, $brand, $models]) : ?>
                <button type="button" class="type-btn <?= $type === $key ? 'active' : '' ?>" data-type="<?= esc($key, 'attr') ?>"
                        data-kind="<?= esc($kind, 'attr') ?>" data-brand="<?= esc((string) $brand, 'attr') ?>" data-models="<?= esc(json_encode($models), 'attr') ?>">
                    <i class="bi bi-<?= esc($icon, 'attr') ?>"></i><span><?= esc($label) ?></span>
                </button>
            <?php endforeach ?>
        </div>
    <?php endforeach ?>
</div></div>

<!-- Belangrijkste gegevens -->
<div class="card mb-3"><div class="card-body">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small mb-1">Naam *</label>
            <input name="name" id="name" class="form-control" required value="<?= esc($d['name'] ?? '') ?>" placeholder="Bijv. Camera oprit, Bewegingsmelder hal">
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Ruimte / plek</label>
            <input name="room" class="form-control" list="rooms" value="<?= esc($d['room'] ?? '') ?>" placeholder="Bijv. Hal, Woonkamer, Garage, Gevel voor">
            <datalist id="rooms"><option>Hal</option><option>Woonkamer</option><option>Keuken</option><option>Garage</option><option>Kantoor</option><option>Slaapkamer</option><option>Zolder</option><option>Meterkast</option><option>Gevel voor</option><option>Gevel achter</option><option>Oprit</option><option>Tuin</option></datalist>
        </div>

        <!-- Sensor: model en device-ID -->
        <div class="col-6 col-md-6 kind-sensor">
            <label class="form-label small mb-1">Model</label>
            <input name="model" id="model" class="form-control" list="models" value="<?= esc($d['model'] ?? '') ?>">
        </div>
        <div class="col-6 col-md-6 kind-sensor">
            <label class="form-label small mb-1">Device-ID / serienr.</label>
            <input name="serial" id="serial" class="form-control mono" value="<?= esc($d['serial'] ?? '') ?>" autocapitalize="characters">
        </div>

        <!-- Netwerk: IP en inlog -->
        <div class="col-8 col-md-4 kind-net">
            <label class="form-label small mb-1">IP-adres</label>
            <input name="ip" class="form-control mono" inputmode="decimal" value="<?= esc($d['ip'] ?? '') ?>" placeholder="192.168.1.64">
        </div>
        <div class="col-4 col-md-2 kind-net">
            <label class="form-label small mb-1">Poort</label>
            <input name="port" class="form-control mono" inputmode="numeric" value="<?= esc($d['port'] ?? '') ?>">
        </div>
        <div class="col-md-6 kind-net kind-login">
            <label class="form-label small mb-1">Gebruikersnaam</label>
            <input name="username" class="form-control mono" autocomplete="off" autocapitalize="off" value="<?= esc($d['username'] ?? '') ?>" placeholder="admin">
        </div>
        <div class="col-12 kind-net kind-login">
            <label class="form-label small mb-1">Wachtwoord <span class="text-muted">(versleuteld)</span></label>
            <div class="input-group">
                <input name="password" id="password" type="password" class="form-control mono" autocomplete="new-password" autocapitalize="off"
                       placeholder="<?= ! empty($d['password_enc']) ? '•••••••• (leeg = ongewijzigd)' : '' ?>">
                <button class="btn btn-outline-secondary" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'" title="Tonen"><i class="bi bi-eye"></i></button>
                <button class="btn btn-outline-secondary" type="button" data-generate="#password" title="Genereer sterk wachtwoord"><i class="bi bi-magic"></i></button>
            </div>
            <?php if (! empty($d['password_enc']) && ! $isNew) : ?>
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_password" value="1" id="cp"><label class="form-check-label small" for="cp">Wachtwoord wissen</label></div>
            <?php endif ?>
        </div>
    </div>

    <!-- Meer details -->
    <details class="mt-3" <?= ! $isNew && ($d['mac'] ?? $d['firmware'] ?? $d['url'] ?? $d['notes'] ?? null) ? 'open' : '' ?>>
        <summary class="small fw-semibold">Meer details</summary>
        <div class="row g-2 mt-1">
            <div class="col-6 col-md-3"><label class="form-label small mb-1">Merk</label><input name="brand" id="brand" class="form-control" list="brands" value="<?= esc($d['brand'] ?? '') ?>">
                <datalist id="brands"><?php foreach ($brands as $b) : ?><option><?= esc($b) ?></option><?php endforeach ?></datalist></div>
            <div class="col-6 col-md-3 kind-net-only"><label class="form-label small mb-1">Model</label><input id="model-net" class="form-control" list="models" value="<?= esc($d['model'] ?? '') ?>"></div>
            <div class="col-6 col-md-3 kind-net-only"><label class="form-label small mb-1">Serienummer</label><input id="serial-net" class="form-control mono" value="<?= esc($d['serial'] ?? '') ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-1">MAC-adres</label><input name="mac" class="form-control mono" value="<?= esc($d['mac'] ?? '') ?>" autocapitalize="characters"></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-1">Firmware</label><input name="firmware" class="form-control" value="<?= esc($d['firmware'] ?? '') ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label small mb-1">Geïnstalleerd op</label><input type="date" name="installed_at" class="form-control" value="<?= esc($d['installed_at'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label small mb-1">URL / beheeradres</label><input name="url" class="form-control" inputmode="url" value="<?= esc($d['url'] ?? '') ?>" placeholder="https://… of Hik-Connect / UniFi-site / Ajax-account"></div>
            <div class="col-12"><label class="form-label small mb-1">Geheime notitie <span class="text-muted">(versleuteld)</span></label>
                <textarea name="secret_notes" rows="2" class="form-control mono" placeholder="<?= ! empty($d['secret_notes_enc']) ? '(versleutelde notitie aanwezig — leeg laten = ongewijzigd)' : 'Bijv. Hik-Connect verificatiecode, wifi-sleutel, installateurscode, PUK' ?>"></textarea>
                <?php if (! empty($d['secret_notes_enc']) && ! $isNew) : ?>
                    <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_secret" value="1" id="cs"><label class="form-check-label small" for="cs">Geheime notitie wissen</label></div>
                <?php endif ?>
            </div>
            <div class="col-12"><label class="form-label small mb-1">Notities</label><textarea name="notes" rows="2" class="form-control"><?= esc($d['notes'] ?? '') ?></textarea></div>
        </div>
    </details>
    <datalist id="models"></datalist>
</div></div>

<div class="device-actions d-flex gap-2">
    <button class="btn btn-primary flex-fill" name="next" value=""><i class="bi bi-check2"></i> Opslaan</button>
    <button class="btn btn-outline-primary flex-fill" name="next" value="copy" title="Opslaan en meteen het volgende apparaat invullen (naam en IP opgehoogd)"><i class="bi bi-plus-lg"></i> Opslaan &amp; volgende</button>
</div>
</form>

<?php if (! $isNew) : ?>
<div class="mt-4"><?= post_link(site_url('apparaten/' . $d['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Verwijderen', 'Dit apparaat en het opgeslagen wachtwoord verwijderen?') ?></div>
<?php endif ?>

<script>
(function () {
    const typeInput = document.getElementById('type');
    const form = document.getElementById('device-form');
    const models = document.getElementById('models');
    const brand = document.getElementById('brand');
    const name = document.getElementById('name');
    // Model en serienummer bestaan twee keer in het scherm (sensor bovenaan, netwerk onder "meer details"):
    // alleen het zichtbare veld wordt verstuurd.
    const pairs = [['model', 'model-net'], ['serial', 'serial-net']].map(([a, b]) => [document.getElementById(a), document.getElementById(b)]);

    let current = null;
    function apply(btn, userClick) {
        const prev = current; current = btn;
        document.querySelectorAll('.type-btn').forEach(b => b.classList.toggle('active', b === btn));
        typeInput.value = btn.dataset.type;
        const kind = btn.dataset.kind; // net | sensor | login
        form.querySelectorAll('.kind-sensor').forEach(e => e.classList.toggle('d-none', kind !== 'sensor'));
        form.querySelectorAll('.kind-net').forEach(e => e.classList.toggle('d-none', !(kind === 'net' || (kind === 'login' && e.classList.contains('kind-login')))));
        form.querySelectorAll('.kind-net-only').forEach(e => e.classList.toggle('d-none', kind === 'sensor'));
        pairs.forEach(([sensorField, netField]) => {
            const useSensor = kind === 'sensor';
            const from = useSensor ? netField : sensorField, to = useSensor ? sensorField : netField;
            if (userClick && !to.value && from.value) to.value = from.value;
            to.name = sensorField.id; from.removeAttribute('name');
        });
        models.innerHTML = JSON.parse(btn.dataset.models || '[]').map(m => '<option value="' + m.replace(/"/g, '&quot;') + '">').join('');
        if (userClick) {
            // Merk en model van de vorige soort niet laten "meeliften"
            const prevModels = prev ? JSON.parse(prev.dataset.models || '[]') : [];
            if (!brand.value || (prev && brand.value === prev.dataset.brand)) brand.value = btn.dataset.brand || '';
            pairs.forEach(([a, b]) => { if (a.id === 'model') [a, b].forEach(f => { if (prevModels.includes(f.value)) f.value = ''; }); });
            // Naam voorstellen als die nog leeg is of nog de vorige soort was
            const label = btn.querySelector('span').textContent.split(' / ')[0].split(' (')[0];
            if (!name.value || name.dataset.auto === '1') { name.value = label; name.dataset.auto = '1'; }
        }
    }
    name.addEventListener('input', () => { name.dataset.auto = '0'; });
    document.querySelectorAll('.type-btn').forEach(b => b.addEventListener('click', () => { apply(b, true); name.focus(); name.select(); }));
    apply(document.querySelector('.type-btn[data-type="' + typeInput.value + '"]') || document.querySelector('.type-btn'), false);
})();
</script>
