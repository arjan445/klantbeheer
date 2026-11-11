<?php
use App\Controllers\Quotes;
[$stLabel, $stColor] = Quotes::STATUSES[$quote['status']] ?? [$quote['status'], 'secondary'];
$manage = user_can('billing.manage');
?>
<a href="<?= site_url('offertes') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Offertes</a>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Offerte #<?= (int) $quote['id'] ?> <span class="badge text-bg-<?= $stColor ?> fs-6 align-middle"><?= esc($stLabel) ?></span></h3>
        <div class="text-muted"><a href="<?= site_url('klanten/' . $customer['id']) ?>"><?= esc($customer['name']) ?></a> · <?= datum($quote['created_at']) ?>
            <?php if ($quote['moneybird_estimate_id'] && $mb->configured()) : ?>· <a href="<?= esc($mb->estimateUrl($quote['moneybird_estimate_id']), 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> In Moneybird</a><?php endif ?></div>
    </div>
    <?php if ($manage) : ?>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" action="<?= site_url('offertes/' . $quote['id'] . '/status') ?>" class="d-flex gap-1"><?= csrf_field() ?>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach (Quotes::STATUSES as $k => [$label]) : ?><option value="<?= $k ?>" <?= $quote['status'] === $k ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
            </select></form>
        <form method="post" action="<?= site_url('offertes/' . $quote['id'] . '/prijzen') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" title="Prijzen van catalogusproducten bijwerken naar de actuele catalogus"><i class="bi bi-arrow-repeat"></i> Prijzen bijwerken</button></form>
        <form method="post" action="<?= site_url('offertes/' . $quote['id'] . '/kopie') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-copy"></i> Kopie</button></form>
    </div>
    <?php endif ?>
</div>

<form method="post" id="quote-form"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body">
    <div class="row g-2">
        <div class="col-md-8"><label class="form-label small">Omschrijving</label><input name="title" class="form-control" value="<?= esc($quote['title']) ?>"></div>
        <div class="col-md-4"><label class="form-label small">Interne notitie</label><input name="notes" class="form-control" value="<?= esc($quote['notes'] ?? '') ?>" placeholder="Niet zichtbaar voor de klant"></div>
    </div>
</div></div>

<div class="card mb-3"><div class="card-body">
    <?php if ($manage) : ?>
    <div class="position-relative mb-3">
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="search" class="form-control" placeholder="Product zoeken en toevoegen, bijv. 'u6 pro', 'ajax hub 2', 'DS-2CD2347'…" autocomplete="off">
        </div>
        <div id="results" class="list-group position-absolute w-100 shadow" style="z-index: 1000; max-height: 420px; overflow-y: auto"></div>
    </div>
    <?php endif ?>

    <table class="table table-sm align-middle" id="lines">
        <thead><tr>
            <th style="width: 24px"></th><th>Omschrijving</th><th style="width: 90px" class="text-end">Aantal</th>
            <th style="width: 120px" class="text-end">Kostprijs</th><th style="width: 120px" class="text-end">Verkoop</th>
            <th style="width: 110px" class="text-end">Totaal</th><th style="width: 90px" class="text-end">Marge</th><th style="width: 60px"></th>
        </tr></thead>
        <tbody></tbody>
    </table>

    <?php if ($manage) : ?>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add="material"><i class="bi bi-plus-lg"></i> Los product</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add="labor"><i class="bi bi-tools"></i> Arbeid (<?= euro($rate) ?>/uur)</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-add="text"><i class="bi bi-type"></i> Tekst / kopje</button>
    </div>
    <?php endif ?>
</div></div>

<div class="row g-3">
    <div class="col-lg-6 order-lg-2">
        <div class="card"><div class="card-body">
            <table class="table table-sm mb-0">
                <tr><td>Totaal excl. btw</td><td class="text-end fw-semibold fs-5" id="t-sell"></td></tr>
                <tr><td>Btw 21%</td><td class="text-end" id="t-vat"></td></tr>
                <tr><td>Totaal incl. btw</td><td class="text-end" id="t-incl"></td></tr>
                <tr class="text-muted"><td>Kostprijs</td><td class="text-end" id="t-cost"></td></tr>
                <tr><td>Marge materiaal</td><td class="text-end fw-semibold" id="t-margin"></td></tr>
                <tr><td>Arbeid</td><td class="text-end" id="t-labor"></td></tr>
            </table>
        </div></div>
    </div>
    <div class="col-lg-6 order-lg-1">
        <?php if ($manage) : ?>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" name="after" value=""><i class="bi bi-check2"></i> Opslaan</button>
            <button class="btn btn-outline-primary" name="after" value="moneybird" <?= $customer['moneybird_contact_id'] && $mb->configured() ? '' : 'disabled' ?>
                onclick="return confirm('Opslaan en als conceptofferte aanmaken in Moneybird?')"><i class="bi bi-send"></i> Opslaan &amp; naar Moneybird</button>
        </div>
        <?php if (! $customer['moneybird_contact_id']) : ?><div class="small text-warning mt-2"><i class="bi bi-exclamation-circle"></i> Klant is niet gekoppeld aan Moneybird. <a href="<?= site_url('klanten/' . $customer['id'] . '/bewerken') ?>">Koppelen</a></div><?php endif ?>
        <?php if ($quote['moneybird_estimate_id']) : ?><div class="small text-muted mt-2">Let op: er staat al een offerte in Moneybird. Nogmaals versturen maakt een nieuwe aan.</div><?php endif ?>
        <?php endif ?>
    </div>
</div>
</form>
<?php if ($manage) : ?>
<div class="mt-4"><?= post_link(site_url('offertes/' . $quote['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Offerte verwijderen', 'Offerte verwijderen?') ?></div>
<?php endif ?>

<template id="row-tpl">
    <tr>
        <td class="text-muted handle" style="cursor: grab" title="Slepen om te verplaatsen"><i class="bi bi-grip-vertical"></i></td>
        <td>
            <input type="hidden" class="f-type"><input type="hidden" class="f-product">
            <input class="form-control form-control-sm f-desc" placeholder="Omschrijving">
            <div class="small text-muted f-meta mt-1"></div>
        </td>
        <td><input class="form-control form-control-sm text-end f-qty" value="1"></td>
        <td><input class="form-control form-control-sm text-end f-cost" placeholder="—"></td>
        <td><input class="form-control form-control-sm text-end f-sell"></td>
        <td class="text-end f-total"></td>
        <td class="text-end small f-margin"></td>
        <td class="text-end text-nowrap">
            <button type="button" class="btn btn-link btn-sm p-0 text-muted f-up" title="Omhoog"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-link btn-sm p-0 text-danger f-del" title="Verwijderen"><i class="bi bi-x-lg"></i></button>
        </td>
    </tr>
</template>

<script>
(function () {
    const body = document.querySelector('#lines tbody'), tpl = document.getElementById('row-tpl');
    const fmt = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' });
    const num = v => { v = String(v ?? '').replace(/\./g, m => m).replace(',', '.').trim(); const n = parseFloat(v); return isNaN(n) ? null : n; };
    const show = v => v === null || v === undefined || v === '' ? '' : String(Number(v).toFixed(2)).replace('.', ',');
    const MARKUP = <?= json_encode($markup) ?>, RATE = <?= json_encode($rate) ?>;
    const editable = <?= $manage ? 'true' : 'false' ?>;
    let n = 0;

    function addLine(l, focus) {
        const row = tpl.content.firstElementChild.cloneNode(true), i = n++;
        const f = cls => row.querySelector(cls);
        const name = k => 'lines[' + i + '][' + k + ']';
        f('.f-type').name = name('type'); f('.f-product').name = name('product_id'); f('.f-desc').name = name('description');
        f('.f-qty').name = name('quantity'); f('.f-cost').name = name('cost_price'); f('.f-sell').name = name('sell_price');
        f('.f-type').value = l.type; f('.f-product').value = l.product_id || '';
        f('.f-desc').value = l.description || ''; f('.f-qty').value = show(l.quantity ?? 1).replace(',00', '');
        f('.f-cost').value = show(l.cost_price); f('.f-sell').value = show(l.sell_price);
        if (l.type === 'text') {
            ['.f-qty', '.f-cost', '.f-sell'].forEach(c => f(c).classList.add('d-none'));
            f('.f-desc').classList.add('fw-semibold');
            f('.f-desc').placeholder = 'Kopje of toelichting';
        }
        if (l.type === 'labor') { f('.f-meta').textContent = 'Arbeid · aantal = uren'; f('.f-cost').placeholder = ''; }
        if (l.type === 'material' && !l.product_id) {
            // Los product: verkoop volgt kostprijs × opslag tot je zelf een verkoopprijs typt; optie om te bewaren
            f('.f-meta').innerHTML = '<label class="form-check-label"><input type="checkbox" class="form-check-input me-1" name="' + name('save') + '" value="1"> bewaar in catalogus</label>';
            f('.f-cost').addEventListener('input', () => { if (!row.dataset.manualSell) { const c = num(f('.f-cost').value); f('.f-sell').value = c === null ? '' : show(Math.round(c * MARKUP * 100) / 100); } calc(); });
            f('.f-sell').addEventListener('input', () => { row.dataset.manualSell = '1'; });
        }
        if (l.meta) f('.f-meta').textContent = l.meta;
        if (!editable) row.querySelectorAll('input, button').forEach(e => e.disabled = true);
        body.appendChild(row);
        calc();
        if (focus) f('.f-desc').focus();
        return row;
    }

    function calc() {
        let sell = 0, cost = 0, costKnown = 0, labor = 0;
        body.querySelectorAll('tr').forEach(r => {
            const type = r.querySelector('.f-type').value;
            if (type === 'text') { r.querySelector('.f-total').textContent = ''; r.querySelector('.f-margin').textContent = ''; return; }
            const q = num(r.querySelector('.f-qty').value) ?? 0, s = num(r.querySelector('.f-sell').value) ?? 0, c = num(r.querySelector('.f-cost').value);
            sell += q * s;
            if (type === 'labor') labor += q * s;
            r.querySelector('.f-total').textContent = fmt.format(q * s);
            if (c !== null) {
                cost += q * c; costKnown += q * s;
                const m = s > 0 ? Math.round((s - c) / s * 100) : 0;
                r.querySelector('.f-margin').innerHTML = '<span class="' + (s < c ? 'text-danger fw-semibold' : 'text-muted') + '">' + m + '%</span>';
            } else r.querySelector('.f-margin').textContent = '';
        });
        document.getElementById('t-sell').textContent = fmt.format(sell);
        document.getElementById('t-vat').textContent = fmt.format(sell * 0.21);
        document.getElementById('t-incl').textContent = fmt.format(sell * 1.21);
        document.getElementById('t-cost').textContent = fmt.format(cost);
        document.getElementById('t-labor').textContent = fmt.format(labor);
        const margin = costKnown - cost;
        document.getElementById('t-margin').textContent = fmt.format(margin) + (costKnown > 0 ? ' (' + Math.round(margin / costKnown * 100) + '%)' : '');
    }

    body.addEventListener('input', calc);
    body.addEventListener('click', e => {
        const row = e.target.closest('tr');
        if (e.target.closest('.f-del')) { row.remove(); calc(); }
        if (e.target.closest('.f-up') && row.previousElementSibling) { body.insertBefore(row, row.previousElementSibling); }
    });

    // Slepen om te sorteren
    let dragged = null;
    body.addEventListener('mousedown', e => { const r = e.target.closest('tr'); if (r) r.draggable = !!e.target.closest('.handle'); });
    body.addEventListener('dragstart', e => { dragged = e.target.closest('tr'); });
    body.addEventListener('dragover', e => {
        e.preventDefault();
        const over = e.target.closest('tr');
        if (!dragged || !over || over === dragged) return;
        const after = e.clientY > over.getBoundingClientRect().top + over.offsetHeight / 2;
        body.insertBefore(dragged, after ? over.nextSibling : over);
    });

    document.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => {
        const t = b.dataset.add;
        addLine(t === 'labor' ? { type: 'labor', description: 'Montage en configuratie', quantity: 1, sell_price: RATE }
            : t === 'text' ? { type: 'text', description: '' } : { type: 'material', description: '', quantity: 1 }, true);
    }));

    // Product zoeken
    const search = document.getElementById('search'), results = document.getElementById('results');
    let timer, items = [], active = -1;
    function render() {
        results.innerHTML = items.map((p, i) => '<button type="button" class="list-group-item list-group-item-action py-1' + (i === active ? ' active' : '') + '" data-i="' + i + '">' +
            '<div class="d-flex justify-content-between"><span>' + esc(p.name) + '</span><span class="fw-semibold text-nowrap ms-3">' + (p.sell !== null ? fmt.format(p.sell) : '—') + '</span></div>' +
            '<div class="small ' + (i === active ? '' : 'text-muted') + '">' + esc([p.sku, p.supplier, p.group].filter(Boolean).join(' · ')) + (p.cost !== null ? ' · inkoop ' + fmt.format(p.cost) : '') + (p.info ? ' · ' + esc(p.info) : '') + '</div></button>').join('');
    }
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    function pick(p) {
        addLine({ type: 'material', product_id: p.id, description: p.name, quantity: 1, cost_price: p.cost, sell_price: p.sell, meta: [p.sku, p.supplier].filter(Boolean).join(' · ') });
        search.value = ''; items = []; results.innerHTML = ''; search.focus();
    }
    if (search) {
        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const q = search.value.trim();
                if (q.length < 2) { items = []; results.innerHTML = ''; return; }
                items = await fetch('<?= site_url('producten/zoek') ?>?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).catch(() => []);
                active = items.length ? 0 : -1;
                render();
            }, 200);
        });
        search.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown') { active = Math.min(items.length - 1, active + 1); render(); e.preventDefault(); }
            if (e.key === 'ArrowUp') { active = Math.max(0, active - 1); render(); e.preventDefault(); }
            if (e.key === 'Enter') { e.preventDefault(); if (items[active]) pick(items[active]); }
            if (e.key === 'Escape') { items = []; results.innerHTML = ''; }
        });
        results.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) pick(items[+b.dataset.i]); });
        document.addEventListener('click', e => { if (!e.target.closest('#results') && e.target !== search) { results.innerHTML = ''; } });
    }

    // Enter in een regel niet het formulier laten versturen
    document.getElementById('quote-form').addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target !== search) e.preventDefault(); });

    <?php foreach ($lines as $l) : ?>
    addLine(<?= json_encode([
        'type' => $l['type'], 'product_id' => $l['product_id'], 'description' => $l['description'],
        'quantity' => (float) $l['quantity'], 'cost_price' => $l['cost_price'] === null ? null : (float) $l['cost_price'], 'sell_price' => (float) $l['sell_price'],
    ]) ?>);
    <?php endforeach ?>
    calc();
})();
</script>
