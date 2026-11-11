<?php use App\Libraries\CostPools as P; ?>
<a href="<?= site_url('kostprijzen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Kostprijzen</a>
<h3 class="mb-1">Inkoopregels: <?= esc($pool['name']) ?></h3>
<p class="text-muted small">Alle inkoopregels van <?= datum(P::from($pool)) ?> t/m vandaag. Aangevinkt = telt mee voor deze kostenpool.
    <span class="badge text-bg-light border">regel</span> komt via leverancier, zoekwoord of categorie, <span class="badge text-bg-warning">handmatig</span> heb je zelf aan- of uitgezet.</p>

<form method="post"><?= csrf_field() ?>
<div class="card"><div class="card-body">
    <div class="d-flex gap-2 mb-2 flex-wrap align-items-center">
        <input type="search" id="q" class="form-control form-control-sm" style="max-width: 300px" placeholder="Zoek leverancier, omschrijving, categorie…">
        <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="onlyOn"><label class="form-check-label small" for="onlyOn">Alleen meetellende regels</label></div>
        <div class="ms-auto">Totaal aangevinkt: <strong id="sum">€ 0,00</strong></div>
    </div>
    <table class="table table-sm table-hover small" id="lines">
        <thead><tr><th style="width: 30px"></th><th>Datum</th><th>Leverancier</th><th>Omschrijving</th><th>Categorie</th><th class="text-end">Excl. btw</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l) : ?>
            <?php $rule = P::ruleMatch($pool, $l); $on = P::counts($pool, $l); $manual = $on !== $rule; ?>
            <tr data-amount="<?= esc($l['amount']) ?>">
                <td><input type="checkbox" class="form-check-input line-check" name="count[]" value="<?= esc($l['detail_id'], 'attr') ?>" <?= $on ? 'checked' : '' ?>></td>
                <td class="text-nowrap"><?= datum($l['date']) ?></td>
                <td><?= esc($l['supplier_name']) ?></td>
                <td><?= esc($l['description']) ?><?php if ($l['reference']) : ?> <span class="text-muted">· <?= esc($l['reference']) ?></span><?php endif ?> <span class="text-muted">(<?= esc($l['kind']) ?>)</span></td>
                <td><?= esc($l['ledger_name'] ?? '—') ?></td>
                <td class="text-end text-nowrap"><?= euro($l['amount']) ?></td>
                <td><?php if ($manual) : ?><span class="badge text-bg-warning">handmatig</span><?php elseif ($rule) : ?><span class="badge text-bg-light border">regel</span><?php endif ?></td>
            </tr>
        <?php endforeach ?>
        <?php if ($lines === []) : ?><tr><td colspan="7" class="text-muted">Geen inkoopregels in deze periode. Haal eerst de inkoop op uit Moneybird.</td></tr><?php endif ?>
        </tbody>
    </table>
</div></div>
<div class="mt-3"><button class="btn btn-primary">Opslaan</button></div>
</form>
<script>
(function () {
    const rows = Array.from(document.querySelectorAll('#lines tbody tr[data-amount]'));
    const q = document.getElementById('q'), onlyOn = document.getElementById('onlyOn'), sum = document.getElementById('sum');
    const fmt = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' });
    function update() {
        const term = q.value.toLowerCase();
        let total = 0;
        rows.forEach(r => {
            const checked = r.querySelector('.line-check').checked;
            if (checked) total += parseFloat(r.dataset.amount);
            r.hidden = (term && !r.textContent.toLowerCase().includes(term)) || (onlyOn.checked && !checked);
        });
        sum.textContent = fmt.format(total);
    }
    q.addEventListener('input', update); onlyOn.addEventListener('change', update);
    rows.forEach(r => r.querySelector('.line-check').addEventListener('change', update));
    update();
})();
</script>
