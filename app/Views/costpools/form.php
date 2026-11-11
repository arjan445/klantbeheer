<?php $p = $pool; $rows = $items ?: [['supplier' => '', 'description' => '', 'amount' => '', 'period' => 'month']]; ?>
<a href="<?= site_url('kostprijzen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Kostprijzen</a>
<h3 class="mb-3"><?= $isNew ? 'Nieuwe kostenpool' : 'Kostenpool bewerken' ?></h3>

<form method="post"><?= csrf_field() ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <div class="mb-3"><label class="form-label">Naam *</label><input name="name" class="form-control" required value="<?= esc($p['name'] ?? '') ?>" placeholder="Bijv. Hostingplatform"></div>

            <h6>Kosten</h6>
            <table class="table table-sm align-middle" id="items">
                <thead><tr><th>Leverancier</th><th>Omschrijving</th><th style="width: 140px">Bedrag excl. btw</th><th style="width: 120px">Per</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i) : ?>
                    <tr>
                        <td><input name="item_supplier[]" class="form-control form-control-sm" list="supplier-list" value="<?= esc($i['supplier']) ?>" placeholder="Bijv. Hetzner"></td>
                        <td><input name="item_description[]" class="form-control form-control-sm" value="<?= esc($i['description'] ?? '') ?>" placeholder="Bijv. VPS CX42"></td>
                        <td><div class="input-group input-group-sm"><span class="input-group-text">€</span><input name="item_amount[]" class="form-control item-amount" value="<?= esc($i['amount'] === '' ? '' : number_format((float) $i['amount'], 2, ',', '')) ?>"></div></td>
                        <td><select name="item_period[]" class="form-select form-select-sm item-period">
                            <option value="month" <?= ($i['period'] ?? 'month') === 'month' ? 'selected' : '' ?>>maand</option>
                            <option value="year" <?= ($i['period'] ?? '') === 'year' ? 'selected' : '' ?>>jaar</option>
                        </select></td>
                        <td><button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row" title="Verwijderen"><i class="bi bi-x-lg"></i></button></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
                <tfoot><tr>
                    <td colspan="2"><button type="button" class="btn btn-sm btn-outline-secondary" id="add-row"><i class="bi bi-plus-lg"></i> Kostenpost toevoegen</button></td>
                    <td colspan="3" class="text-end small">Totaal: <strong id="total-year">€ 0,00</strong> per jaar</td>
                </tr></tfoot>
            </table>
            <datalist id="supplier-list"><option>Hetzner</option><option>Plesk</option><option>CloudLinux</option><option>TransIP</option><option>Microsoft</option></datalist>

            <div class="mt-3"><label class="form-label">Notities</label><textarea name="notes" rows="2" class="form-control"><?= esc($p['notes'] ?? '') ?></textarea></div>
        </div></div>
    </div>

    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h6>Diensten die deze kosten dragen</h6>
            <p class="small text-muted">Verdeeld naar het aantal actieve abonnementen × gewicht. Een dienst met gewicht 2 draagt per stuk dubbel zoveel.</p>
            <table class="table table-sm mb-0">
                <?php foreach ($services as $s) : ?>
                    <?php $linked = ! $isNew && (int) $s['cost_pool_id'] === (int) $p['id']; $otherPool = $s['cost_pool_id'] && ! $linked; ?>
                    <tr class="<?= $otherPool ? 'text-muted' : '' ?>">
                        <td style="width: 30px"><input type="checkbox" class="form-check-input" name="services[]" value="<?= $s['id'] ?>" id="sv<?= $s['id'] ?>" <?= $linked ? 'checked' : '' ?>></td>
                        <td><label for="sv<?= $s['id'] ?>"><?= esc($s['name']) ?></label> <span class="badge badge-type"><?= esc(service_type_label($s['type'])) ?></span>
                            <?php if ($otherPool) : ?><span class="small">(andere pool)</span><?php endif ?></td>
                        <td style="width: 100px"><div class="input-group input-group-sm"><span class="input-group-text">×</span><input name="weights[<?= $s['id'] ?>]" class="form-control" value="<?= esc(aantal($s['cost_weight'] ?? 1)) ?>"></div></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div></div>
    </div>
</div>
<div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Opslaan</button><a href="<?= site_url('kostprijzen') ?>" class="btn btn-outline-secondary">Annuleren</a></div>
</form>
<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('kostprijzen/' . $p['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Kostenpool verwijderen', 'Kostenpool verwijderen? De diensten houden hun laatste inkoopprijs.') ?></div>
<?php endif ?>

<script>
(function () {
    const body = document.querySelector('#items tbody');
    const fmt = new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' });
    function total() {
        let t = 0;
        body.querySelectorAll('tr').forEach(r => {
            const a = parseFloat((r.querySelector('.item-amount').value || '0').replace(',', '.')) || 0;
            t += a * (r.querySelector('.item-period').value === 'year' ? 1 : 12);
        });
        document.getElementById('total-year').textContent = fmt.format(t);
    }
    document.getElementById('add-row').addEventListener('click', () => {
        const row = body.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input').forEach(i => i.value = '');
        row.querySelector('select').value = 'month';
        body.appendChild(row);
        row.querySelector('input').focus();
    });
    body.addEventListener('click', e => {
        if (!e.target.closest('.remove-row')) return;
        const row = e.target.closest('tr');
        if (body.querySelectorAll('tr').length > 1) row.remove(); else row.querySelectorAll('input').forEach(i => i.value = '');
        total();
    });
    body.addEventListener('input', total);
    body.addEventListener('change', total);
    total();
})();
</script>
