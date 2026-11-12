<?php use App\Controllers\Quotes;

$manage = user_can('billing.manage');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Offertes</h3>
    <div class="d-flex gap-2">
        <a href="<?= site_url('producten') ?>" class="btn btn-outline-secondary"><i class="bi bi-box-seam"></i> Producten</a>
        <?php if ($manage) : ?><a href="<?= site_url('offertes/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe offerte</a><?php endif ?>
    </div>
</div>
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="<?= site_url('offertes') ?>" class="btn btn-sm <?= $status ? 'btn-outline-secondary' : 'btn-secondary' ?>">Alle</a>
    <?php foreach (Quotes::STATUSES as $k => [$label, $color]) : ?>
        <a href="<?= site_url('offertes?status=' . $k) ?>" class="btn btn-sm <?= $status === $k ? 'btn-' . $color : 'btn-outline-' . $color ?>"><?= esc($label) ?></a>
    <?php endforeach ?>
</div>

<div class="card"><div class="card-body">
<div class="dev-table">
<table class="table table-hover align-middle <?= count($quotes) > 15 ? 'datatable' : '' ?>">
    <thead><tr><?php if ($manage) : ?><th style="width:1%" data-orderable="false"><input type="checkbox" class="form-check-input" id="selAll" title="Alles selecteren"></th><?php endif ?><th>#</th><th>Datum</th><th>Klant</th><th>Omschrijving</th><th class="text-end">Excl. btw</th><th class="text-end km-hide">Marge materiaal</th><th class="text-end">Arbeid</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($quotes as $q) : ?>
        <?php [$label, $color] = Quotes::STATUSES[$q['status']] ?? [$q['status'], 'secondary']; ?>
        <tr>
            <?php if ($manage) : ?><td><input type="checkbox" class="form-check-input q-sel" value="<?= (int) $q['id'] ?>" data-mb="<?= $q['moneybird_estimate_id'] ? 1 : 0 ?>"></td><?php endif ?>
            <td><a href="<?= site_url('offertes/' . $q['id']) ?>"><?= (int) $q['id'] ?></a></td>
            <td data-order="<?= esc($q['created_at']) ?>"><?= datum($q['created_at']) ?></td>
            <td><a href="<?= site_url('klanten/' . $q['customer_id']) ?>"><?= esc($q['customer_name']) ?></a></td>
            <td><a href="<?= site_url('offertes/' . $q['id']) ?>" class="fw-semibold"><?= esc($q['title']) ?></a></td>
            <td class="text-end"><?= euro($q['totals']['sell']) ?></td>
            <td class="text-end km-hide"><?= euro($q['totals']['margin']) ?><?= $q['totals']['pct'] !== null ? ' <span class="small text-muted">' . $q['totals']['pct'] . '%</span>' : '' ?></td>
            <td class="text-end"><?= $q['totals']['labor'] > 0 ? euro($q['totals']['labor']) : '' ?></td>
            <td><span class="badge text-bg-<?= $color ?>"><?= esc($label) ?></span></td>
        </tr>
    <?php endforeach ?>
    <?php if ($quotes === []) : ?><tr><td colspan="<?= $manage ? 9 : 8 ?>" class="text-muted">Nog geen offertes.</td></tr><?php endif ?>
    </tbody>
</table>
</div>

<!-- Mobiel: kaartjes -->
<div class="dev-cards">
    <?php foreach ($quotes as $q) : ?>
        <?php [$label, $color] = Quotes::STATUSES[$q['status']] ?? [$q['status'], 'secondary']; ?>
        <div class="dev-card d-flex gap-2 align-items-start">
            <?php if ($manage) : ?><input type="checkbox" class="form-check-input q-sel mt-1" value="<?= (int) $q['id'] ?>" data-mb="<?= $q['moneybird_estimate_id'] ? 1 : 0 ?>" style="width:1.3em;height:1.3em"><?php endif ?>
            <a href="<?= site_url('offertes/' . $q['id']) ?>" class="flex-grow-1 text-reset text-decoration-none">
                <div class="d-flex justify-content-between gap-2"><span class="dev-title"><?= esc($q['title']) ?></span><span class="fw-semibold text-nowrap"><?= euro($q['totals']['sell']) ?></span></div>
                <div class="small text-muted">#<?= (int) $q['id'] ?> · <?= esc($q['customer_name']) ?> · <?= datum($q['created_at']) ?></div>
                <span class="badge text-bg-<?= $color ?>"><?= esc($label) ?></span>
            </a>
        </div>
    <?php endforeach ?>
    <?php if ($quotes === []) : ?><p class="text-muted mb-0">Nog geen offertes.</p><?php endif ?>
</div>
</div></div>

<?php if ($manage && $quotes !== []) : ?>
<div class="device-actions sel-bar align-items-center gap-2" id="selBar" style="display:none">
    <span class="flex-grow-1" id="selCount"></span>
    <button type="button" class="btn btn-outline-secondary" id="selClear">Wissen</button>
    <button type="button" class="btn btn-danger" id="selDelete"><i class="bi bi-trash"></i> Verwijderen</button>
</div>
<?= view('quotes/_delete_modal', ['action' => site_url('offertes/verwijderen'), 'linked' => false, 'intro' => '']) ?>
<script>
(() => {
    const sel = new Map(); // id -> gekoppeld aan Moneybird
    const bar = document.getElementById('selBar');
    const sync = () => {
        document.querySelectorAll('.q-sel').forEach(c => { c.checked = sel.has(c.value); });
        bar.style.display = sel.size ? 'flex' : 'none';
        document.getElementById('selCount').textContent = sel.size === 1 ? '1 offerte geselecteerd' : sel.size + ' offertes geselecteerd';
    };
    document.addEventListener('change', e => {
        if (e.target.classList.contains('q-sel')) {
            e.target.checked ? sel.set(e.target.value, e.target.dataset.mb === '1') : sel.delete(e.target.value);
            sync();
        } else if (e.target.id === 'selAll') {
            document.querySelectorAll('.dev-table .q-sel').forEach(c => {
                e.target.checked ? sel.set(c.value, c.dataset.mb === '1') : sel.delete(c.value);
            });
            sync();
        }
    });
    document.getElementById('selClear').onclick = () => { sel.clear(); const a = document.getElementById('selAll'); if (a) a.checked = false; sync(); };
    document.getElementById('selDelete').onclick = () => {
        const box = document.getElementById('deleteQuoteIds');
        box.innerHTML = '';
        sel.forEach((mb, id) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; box.appendChild(i); });
        document.getElementById('deleteQuoteTitle').textContent = sel.size === 1 ? 'Offerte verwijderen' : sel.size + ' offertes verwijderen';
        document.getElementById('deleteQuoteIntro').textContent = (sel.size === 1 ? 'De geselecteerde offerte' : 'De ' + sel.size + ' geselecteerde offertes') + ' met alle regels verwijderen?';
        document.getElementById('deleteQuoteMb').hidden = ![...sel.values()].some(Boolean);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteQuote')).show();
    };
})();
</script>
<?php endif ?>
