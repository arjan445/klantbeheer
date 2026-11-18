<?php
$v = static fn (string $k) => old($k, $entry[$k] ?? '');
$cust = (int) $v('customer_id');
?>
<a href="<?= site_url('uren?maand=' . substr((string) $entry['date'], 0, 7)) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Uren</a>
<h3 class="mb-3"><?= $isNew ? 'Uren boeken' : 'Uren wijzigen' ?></h3>

<form method="post" id="hours-form" autocomplete="off"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body">
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small mb-1">Klant</label>
            <select name="customer_id" id="customer" class="form-select" required>
                <option value="">Kies een klant…</option>
                <?php $int = null; foreach ($customers as $c) : ?>
                    <?php if ($c['internal'] && $int === null) : $int = true; ?><optgroup label="Eigen gebruik"><?php endif ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $cust === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                <?php endforeach ?>
                <?= $int ? '</optgroup>' : '' ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Werklocatie</label>
            <select name="site_id" id="site" class="form-select"></select>
            <div class="form-text" id="site-addr"></div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small mb-1">Datum</label>
            <input type="date" name="date" class="form-control" value="<?= esc($v('date'), 'attr') ?>" required>
        </div>
        <div class="col-4 col-md-2">
            <label class="form-label small mb-1">Start</label>
            <input type="time" name="start_time" id="start" class="form-control" value="<?= esc(substr((string) $v('start_time'), 0, 5), 'attr') ?>" required>
        </div>
        <div class="col-4 col-md-2">
            <label class="form-label small mb-1">Stop</label>
            <input type="time" name="end_time" id="end" class="form-control" value="<?= esc(substr((string) $v('end_time'), 0, 5), 'attr') ?>" required>
        </div>
        <div class="col-4 col-md-2">
            <label class="form-label small mb-1">Pauze (min)</label>
            <input type="number" name="pause_minutes" id="pause" min="0" max="600" step="5" inputmode="numeric" class="form-control" value="<?= (int) $v('pause_minutes') ?>">
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end">
            <div class="fs-5 fw-semibold" id="total">–</div>
        </div>
        <div class="col-12">
            <div class="d-flex gap-1 flex-wrap" id="pause-chips">
                <span class="small text-muted me-1 align-self-center">Pauze:</span>
                <?php foreach ([0, 15, 30, 45, 60] as $m) : ?><button type="button" class="btn btn-sm btn-outline-secondary" data-pause="<?= $m ?>"><?= $m ?></button><?php endforeach ?>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label small mb-1">Omschrijving</label>
            <textarea name="description" class="form-control" rows="2" maxlength="500" required placeholder="Bv. camera's opgehangen en NVR ingericht"><?= esc($v('description')) ?></textarea>
        </div>
        <div class="col-12 d-flex flex-wrap gap-3">
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="billable" value="1" id="billable" <?= $v('billable') ? 'checked' : '' ?>><label class="form-check-label" for="billable">Factureerbaar</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="travel" value="1" id="travel" <?= $v('travel') ? 'checked' : '' ?>><label class="form-check-label" for="travel">Naartoe gereden (kilometers)</label></div>
        </div>
    </div>
</div></div>

<div class="device-actions d-flex gap-2">
    <?php if (! $isNew) : ?>
        <button type="button" class="btn btn-outline-danger" onclick="if (confirm('Deze uren verwijderen? Ook uit Moneybird.')) document.getElementById('del-form').submit()"><i class="bi bi-trash"></i></button>
    <?php endif ?>
    <button class="btn btn-outline-primary flex-grow-1" name="after" value="next"><i class="bi bi-plus-lg"></i> Opslaan &amp; nog een</button>
    <button class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg"></i> Opslaan</button>
</div>
</form>
<?php if (! $isNew) : ?>
<form method="post" action="<?= site_url('uren/' . $entry['id'] . '/verwijderen') ?>" id="del-form"><?= csrf_field() ?></form>
<?php endif ?>

<style>@media (max-width: 767.98px) { #hours-form input, #hours-form select, #hours-form textarea { font-size: 16px; } }</style>
<script>
(() => {
    const sites = <?= json_encode($sites, JSON_UNESCAPED_UNICODE) ?>;
    const cust = document.getElementById('customer'), site = document.getElementById('site'), addr = document.getElementById('site-addr');
    let current = <?= json_encode((string) ($v('site_id') ?? '')) ?>;
    const fill = () => {
        const list = sites[cust.value] || [];
        site.innerHTML = '';
        site.add(new Option(list.length ? '— geen / op afstand —' : '— klant heeft geen locaties —', ''));
        list.forEach(s => site.add(new Option(s.name + (s.addr ? ' · ' + s.addr : ''), s.id)));
        if (list.some(s => String(s.id) === String(current))) site.value = current;
        else if (list.length === 1) site.value = list[0].id;
        showAddr();
    };
    const showAddr = () => {
        const s = (sites[cust.value] || []).find(x => String(x.id) === site.value);
        addr.textContent = s ? (s.addr || 'Let op: deze locatie heeft geen adres, dan kunnen er geen kilometers berekend worden.') : '';
        addr.classList.toggle('text-danger', !!s && !s.addr);
    };
    cust.addEventListener('change', () => { current = ''; fill(); });
    site.addEventListener('change', showAddr);
    fill();

    const start = document.getElementById('start'), end = document.getElementById('end'), pause = document.getElementById('pause'), total = document.getElementById('total');
    const calc = () => {
        const p = s => { const m = /^(\d{1,2}):(\d{2})/.exec(s || ''); return m ? (+m[1]) * 60 + (+m[2]) : null; };
        let a = p(start.value), b = p(end.value);
        if (a === null || b === null) { total.textContent = '–'; return; }
        if (b <= a) b += 1440;
        const n = b - a - (parseInt(pause.value || 0, 10));
        total.textContent = n > 0 ? Math.floor(n / 60) + ':' + String(n % 60).padStart(2, '0') + ' uur' : '–';
        document.querySelectorAll('[data-pause]').forEach(x => x.classList.toggle('active', x.dataset.pause === String(parseInt(pause.value || 0, 10))));
    };
    [start, end, pause].forEach(x => x.addEventListener('input', calc));
    document.querySelectorAll('[data-pause]').forEach(b => b.addEventListener('click', () => { pause.value = b.dataset.pause; calc(); }));
    calc();
})();
</script>
