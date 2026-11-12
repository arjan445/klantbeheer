<?php
$groups = device_type_groups();
$kinds  = array_map(static fn ($t) => $t[2], device_types());
?>
<a href="<?= site_url('offertes/' . $quote['id']) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Offerte #<?= (int) $quote['id'] ?></a>
<h3 class="mb-1 h4-sm">Apparaten aanmaken</h3>
<p class="text-muted mb-3">Uit offerte <strong><?= esc($quote['title']) ?></strong> voor <a href="<?= site_url('klanten/' . $customer['id']) ?>"><?= esc($customer['name']) ?></a>.
    Aangevinkte regels worden apparaten in de kluis; toebehoren zoals kabels en beugels staan standaard uit.</p>

<?php if ($lines === []) : ?>
    <div class="alert alert-info">Deze offerte heeft geen materiaalregels.</div>
<?php else : ?>
<form method="post" id="qd-form"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small">Locatie</label>
            <select name="site_id" class="form-select">
                <option value="">— geen / later —</option>
                <?php foreach ($sites as $s) : ?><option value="<?= (int) $s['id'] ?>" <?= count($sites) === 1 ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small">Installatiedatum</label>
            <input type="date" name="installed_at" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
</div></div>

<?php foreach ($lines as $l) :
    $done = $l['todo'] === 0 && $l['created'] > 0;
    $on   = $l['guess'] !== null && $l['todo'] > 0;
    $type = $l['guess'] ?? '';
    $k    = 'line[' . (int) $l['id'] . ']';
    ?>
<div class="card mb-2 qd-line <?= $on ? '' : 'qd-off' ?>">
    <div class="card-body py-2">
        <label class="d-flex gap-2 align-items-start mb-2">
            <input class="form-check-input mt-1 qd-on" type="checkbox" name="<?= $k ?>[on]" value="1" <?= $on ? 'checked' : '' ?> style="width:1.3em;height:1.3em">
            <span class="flex-grow-1">
                <span class="fw-semibold"><?= esc(mb_strimwidth(preg_split('/\R/', $l['description'])[0], 0, 120, '…')) ?></span>
                <span class="d-block small text-muted">
                    <?= aantal($l['quantity']) ?>× in offerte<?= $l['sku'] ? ' · ' . esc($l['sku']) : '' ?>
                    <?php if ($l['created'] > 0) : ?> · <span class="text-success"><i class="bi bi-check-circle"></i> <?= $l['created'] ?> al aangemaakt</span><?php endif ?>
                    <?php $pl = array_slice(\App\Controllers\Quotes::placements($l), $l['created']); if (array_filter($pl) !== []) : ?>
                        <span class="d-block"><i class="bi bi-geo-alt"></i> <?= esc(implode(', ', array_map(static fn ($v) => $v === '' ? '—' : $v, $pl))) ?></span>
                    <?php endif ?>
                    <?php if ($l['guess'] === null) : ?> · <span class="text-warning-emphasis">geen apparaat herkend</span><?php endif ?>
                </span>
            </span>
        </label>
        <div class="row g-2 qd-fields">
            <div class="col-12 col-md-4">
                <select name="<?= $k ?>[type]" class="form-select form-select-sm qd-type">
                    <option value="">Soort kiezen…</option>
                    <?php foreach ($groups as $g => $items) : ?>
                        <optgroup label="<?= esc($g, 'attr') ?>">
                            <?php foreach ($items as $key => $t) : ?><option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= esc($t[0]) ?></option><?php endforeach ?>
                        </optgroup>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-4 col-md-2">
                <div class="input-group input-group-sm"><span class="input-group-text">Aantal</span>
                <input type="number" min="0" max="200" inputmode="numeric" name="<?= $k ?>[n]" class="form-control qd-n" value="<?= $done ? 0 : $l['todo'] ?>"></div>
            </div>
            <div class="col-8 col-md-3">
                <input name="<?= $k ?>[room]" class="form-control form-control-sm" placeholder="<?= array_filter(\App\Controllers\Quotes::placements($l)) ? 'Ruimte voor stuks zonder plaatsing' : 'Ruimte (optioneel)' ?>" list="rooms">
            </div>
            <div class="col-12 col-md-3 qd-ip">
                <input name="<?= $k ?>[ip]" class="form-control form-control-sm" inputmode="decimal" placeholder="Start-IP, bv. 192.168.1.64 (telt op)">
            </div>
        </div>
    </div>
</div>
<?php endforeach ?>

<datalist id="rooms"><?php foreach (['Hal', 'Woonkamer', 'Keuken', 'Garage', 'Kantoor', 'Magazijn', 'Slaapkamer', 'Zolder', 'Kelder', 'Voorgevel', 'Achtergevel', 'Oprit', 'Tuin', 'Meterkast', 'Serverruimte'] as $r) : ?><option value="<?= $r ?>"><?php endforeach ?></datalist>

<div class="device-actions d-flex gap-2 align-items-center">
    <span class="text-muted small flex-grow-1" id="qd-count"></span>
    <a href="<?= site_url('offertes/' . $quote['id']) ?>" class="btn btn-outline-secondary">Overslaan</a>
    <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> Aanmaken</button>
</div>
</form>

<style>
.qd-off .qd-fields { opacity: .45; }
@media (max-width: 767.98px) { #qd-form input, #qd-form select { font-size: 16px; } }
</style>
<script>
(() => {
    const kinds = <?= json_encode($kinds) ?>;
    const form = document.getElementById('qd-form');
    const update = () => {
        let total = 0;
        form.querySelectorAll('.qd-line').forEach(card => {
            const on = card.querySelector('.qd-on').checked;
            const type = card.querySelector('.qd-type').value;
            card.classList.toggle('qd-off', !on);
            card.querySelector('.qd-ip').style.display = (kinds[type] || '') === 'net' ? '' : 'none';
            if (on && type) total += parseInt(card.querySelector('.qd-n').value || 0, 10);
        });
        document.getElementById('qd-count').textContent = total === 1 ? '1 apparaat' : total + ' apparaten';
    };
    form.addEventListener('input', update);
    form.addEventListener('change', e => {
        // soort kiezen bij een uitgevinkte regel vinkt hem aan
        if (e.target.classList.contains('qd-type') && e.target.value) {
            const card = e.target.closest('.qd-line');
            card.querySelector('.qd-on').checked = true;
            const n = card.querySelector('.qd-n'); if (n.value === '0') n.value = 1;
        }
        update();
    });
    update();
})();
</script>
<?php endif ?>
