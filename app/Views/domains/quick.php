<?php
$regList  = $byType('domain');
$hostList = $byType('hosting');
$pick = static function (array $list, $selected) {
    $out = '';
    foreach ($list as $s) {
        $out .= '<option value="' . $s['id'] . '"' . ((string) $selected === (string) $s['id'] ? ' selected' : '') . '>'
            . esc($s['name']) . ' (' . euro($s['price']) . ' / ' . esc(interval_label((int) $s['interval_months'])) . ')</option>';
    }
    return $out;
};
?>
<a href="<?= site_url('domeinen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Domeinen</a>
<h3 class="mb-1">Snel invoeren</h3>
<p class="text-muted small">Eén regel per domein. Vink aan wat erbij hoort; de domeinen en abonnementen worden in één keer aangemaakt. Tip: druk op <kbd>Enter</kbd> in het domeinveld voor een nieuwe regel.</p>

<?php if ($notes = session()->getFlashdata('quick_notes')) : ?>
    <div class="alert alert-warning small"><?php foreach ($notes as $n) : ?><div><?= esc($n) ?></div><?php endforeach ?></div>
<?php endif ?>

<?php if ($regList === [] || $hostList === []) : ?>
    <div class="alert alert-warning">Maak eerst bij <a href="<?= site_url('diensten') ?>">Diensten</a> minstens één dienst van het soort <em>Domeinnaam</em> en één van het soort <em>Webhosting</em> aan.</div>
<?php endif ?>

<form method="post" id="quick"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Standaard klant</label>
            <select id="def-customer" name="default_customer" class="form-select">
                <option value="">Kies…</option>
                <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>" <?= (string) $defaults['customer_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?>
            </select>
            <div class="form-text">Voor nieuwe regels; per regel aan te passen.</div>
        </div>
        <div class="col-md-3"><label class="form-label">Registratie</label>
            <select name="reg_service" class="form-select"><?= $pick($regList, $defaults['reg']) ?></select></div>
        <div class="col-md-3"><label class="form-label">Hosting</label>
            <select name="host_service" class="form-select"><?= $pick($hostList, $defaults['host']) ?></select></div>
        <div class="col-md-3"><label class="form-label">Extra (optioneel)</label>
            <select name="extra_service" id="extra-service" class="form-select"><option value="">—</option><?= $pick(array_values(array_filter($services, static fn ($s) => ! in_array($s['type'], ['domain', 'hosting'], true))), $defaults['extra']) ?></select></div>
        <div class="col-md-3"><label class="form-label">Volgende factuurdatum</label>
            <input type="date" id="def-next" class="form-control" value="<?= date('Y-m-d') ?>">
            <div class="form-text">Eerste dag die nog niet gefactureerd is.</div></div>
        <div class="col-md-9 d-flex align-items-end gap-3 flex-wrap">
            <span class="small text-muted">Standaard aanvinken bij nieuwe regels:</span>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="def-reg" checked><label class="form-check-label" for="def-reg">Registratie</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="def-host" checked><label class="form-check-label" for="def-host">Hosting</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="def-extra"><label class="form-check-label" for="def-extra">Extra</label></div>
        </div>
    </div>
</div></div>

<div class="card"><div class="card-body">
    <table class="table table-sm align-middle" id="rows">
        <thead><tr>
            <th style="width: 24%">Domein</th><th style="width: 22%">Klant</th>
            <th class="text-center">Registratie</th><th class="text-center">Hosting</th><th class="text-center">Extra</th>
            <th>Registrar (als extern)</th><th style="width: 150px">Volgende factuur</th><th></th>
        </tr></thead>
        <tbody></tbody>
    </table>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="add"><i class="bi bi-plus-lg"></i> Regel</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#paste"><i class="bi bi-clipboard"></i> Lijst plakken</button>
    </div>
    <div class="collapse mt-2" id="paste">
        <textarea id="paste-text" rows="5" class="form-control mono" placeholder="Eén domein per regel, bijv.&#10;klant.nl&#10;klant-shop.nl&#10;www.anderdomein.be"></textarea>
        <button type="button" class="btn btn-sm btn-primary mt-2" id="paste-go">Toevoegen als regels</button>
    </div>
</div></div>

<div class="mt-3 d-flex align-items-center gap-3">
    <button class="btn btn-primary" <?= $regList === [] && $hostList === [] ? 'disabled' : '' ?>><i class="bi bi-check2"></i> Alles opslaan</button>
    <span class="small text-muted" id="summary"></span>
</div>
</form>

<template id="row-tpl">
    <tr>
        <td><input class="form-control form-control-sm mono f-domain" placeholder="klant.nl" autocomplete="off"></td>
        <td><select class="form-select form-select-sm f-customer"><option value="">Kies…</option><?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach ?></select></td>
        <td class="text-center"><input type="checkbox" class="form-check-input f-reg" value="1"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input f-host" value="1"></td>
        <td class="text-center"><input type="checkbox" class="form-check-input f-extra" value="1"></td>
        <td><input class="form-control form-control-sm f-registrar" placeholder="bijv. TransIP"></td>
        <td><input type="date" class="form-control form-control-sm f-next"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger p-0 f-remove" title="Weg"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<script>
(function () {
    const body = document.querySelector('#rows tbody'), tpl = document.getElementById('row-tpl');
    let n = 0;
    const $ = id => document.getElementById(id);

    function addRow(domain = '') {
        const row = tpl.content.firstElementChild.cloneNode(true), i = n++;
        const set = (cls, name) => row.querySelector(cls).name = 'rows[' + i + '][' + name + ']';
        set('.f-domain', 'domain'); set('.f-customer', 'customer_id'); set('.f-reg', 'reg'); set('.f-host', 'host');
        set('.f-extra', 'extra'); set('.f-registrar', 'registrar'); set('.f-next', 'next');
        row.querySelector('.f-domain').value = domain;
        row.querySelector('.f-customer').value = $('def-customer').value;
        row.querySelector('.f-reg').checked = $('def-reg').checked;
        row.querySelector('.f-host').checked = $('def-host').checked;
        row.querySelector('.f-extra').checked = $('def-extra').checked;
        row.querySelector('.f-extra').disabled = !$('extra-service').value;
        row.querySelector('.f-next').value = $('def-next').value;
        toggleRegistrar(row);
        body.appendChild(row);
        summary();
        return row;
    }
    function toggleRegistrar(row) {
        // Registrar alleen nodig als we het domein niet zelf registreren
        row.querySelector('.f-registrar').disabled = row.querySelector('.f-reg').checked;
    }
    function summary() {
        const rows = Array.from(body.querySelectorAll('tr')).filter(r => r.querySelector('.f-domain').value.trim());
        const count = cls => rows.filter(r => r.querySelector(cls).checked).length;
        $('summary').textContent = rows.length ? rows.length + ' domein(en): ' + count('.f-reg') + ' registratie, ' + count('.f-host') + ' hosting, ' + count('.f-extra') + ' extra' : '';
    }

    $('add').addEventListener('click', () => addRow().querySelector('.f-domain').focus());
    body.addEventListener('click', e => {
        if (e.target.closest('.f-remove')) { e.target.closest('tr').remove(); if (!body.children.length) addRow(); summary(); }
    });
    body.addEventListener('change', e => { if (e.target.classList.contains('f-reg')) toggleRegistrar(e.target.closest('tr')); summary(); });
    body.addEventListener('input', summary);
    body.addEventListener('keydown', e => {
        if (e.key !== 'Enter' || !e.target.classList.contains('f-domain')) return;
        e.preventDefault();
        const rows = Array.from(body.children), idx = rows.indexOf(e.target.closest('tr'));
        (rows[idx + 1] || addRow()).querySelector('.f-domain').focus();
    });
    $('def-customer').addEventListener('change', () => {
        // Lege klantvelden meenemen
        body.querySelectorAll('.f-customer').forEach(s => { if (!s.value) s.value = $('def-customer').value; });
    });
    $('paste-go').addEventListener('click', () => {
        const names = $('paste-text').value.split(/[\s,;]+/).map(s => s.trim()).filter(Boolean);
        // Lege laatste regel hergebruiken
        const last = body.lastElementChild;
        if (last && !last.querySelector('.f-domain').value.trim()) last.remove();
        names.forEach(name => addRow(name));
        $('paste-text').value = '';
        summary();
    });
    document.getElementById('quick').addEventListener('submit', e => {
        const missing = Array.from(body.querySelectorAll('tr')).filter(r => r.querySelector('.f-domain').value.trim() && !r.querySelector('.f-customer').value);
        if (missing.length) { e.preventDefault(); alert('Kies bij elke regel een klant.'); missing[0].querySelector('.f-customer').focus(); }
    });

    // Extra-vinkjes alleen bruikbaar als er een extra dienst gekozen is
    function toggleExtra() {
        const off = !$('extra-service').value;
        document.querySelectorAll('.f-extra, #def-extra').forEach(c => { c.disabled = off; if (off) c.checked = false; });
        summary();
    }
    $('extra-service').addEventListener('change', toggleExtra);

    for (let i = 0; i < 3; i++) addRow();
    toggleExtra();
    body.querySelector('.f-domain').focus();
})();
</script>
