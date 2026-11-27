<?php
/**
 * @var ?string     $error
 * @var list<array> $rows      Vimexx-regels
 * @var list<array> $services
 * @var array       $suggest   dienst-ID => [key, months, why]
 * @var array       $subs      dienst-ID => aantal abonnementen met eigen inkoopprijs (gelijk aan de dienst)
 */
use App\Libraries\Vimexx;

$byKey = array_column($rows, null, 'key');
$cats  = [];
foreach ($rows as $r) {
    $cats[$r['category']][] = $r;
}
$per = static fn (int $m) => match ($m) { 1 => 'maand', 3 => 'kwartaal', 12 => 'jaar', default => $m . ' mnd' };
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('diensten') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Diensten</a>
        <h3 class="mb-0">Prijzen Vimexx</h3>
        <div class="text-muted small">Inkoopprijzen van <a href="<?= Vimexx::URL ?>" target="_blank" rel="noopener">vimexx.nl/prijzen</a> (verlengprijs, excl. btw) overnemen in je diensten.<?= ! empty($last) ? ' Laatst bijgewerkt ' . esc(date('d-m-Y H:i', strtotime($last))) . '.' : '' ?></div>
    </div>
</div>

<?php if ($error) : ?>
    <div class="alert alert-danger"><?= esc($error) ?></div>
<?php else : ?>
<form method="post"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body p-0">
<div class="table-responsive">
<table class="table align-middle mb-0 vx-table">
    <thead><tr>
        <th>Dienst</th><th class="text-end">Verkoop</th><th class="text-end">Inkoop nu</th>
        <th style="min-width:260px">Vimexx-product</th><th>Prijs per</th>
        <th class="text-end">Nieuwe inkoop</th><th class="text-end">Marge nieuw</th><th class="text-center">Bijwerken</th>
    </tr></thead>
    <tbody>
    <?php foreach ($services as $s) :
        $id  = (int) $s['id'];
        $sg  = $suggest[$id] ?? ['key' => null, 'months' => null, 'why' => ''];
        $sel = $sg['key'];
        $pool = (bool) $s['cost_pool_id'];
    ?>
    <tr data-auto="<?= $sg['why'] !== '' && $sg['why'] !== 'voorstel op naam' ? '1' : '0' ?>" data-interval="<?= (int) ($s['interval_months'] ?: 12) ?>" data-price="<?= (float) $s['price'] ?>" data-old="<?= $s['purchase_price'] !== null ? (float) $s['purchase_price'] : '' ?>" class="<?= $sel ? '' : 'vx-none' ?>">
        <td>
            <div class="fw-semibold"><?= esc($s['name']) ?></div>
            <div class="small text-muted"><?= esc(service_type_label($s['type'])) ?> · per <?= $per((int) ($s['interval_months'] ?: 12)) ?><?= $s['supplier'] ? ' · ' . esc($s['supplier']) : '' ?></div>
            <?php if ($sg['why']) : ?><div class="small <?= $sg['why'] === 'voorstel op naam' ? 'text-warning-emphasis' : 'text-primary' ?>"><?= esc($sg['why']) ?><?= $sg['why'] === 'voorstel op naam' ? ' · controleer' : '' ?></div><?php endif ?>
        </td>
        <td class="text-end text-nowrap"><?= euro($s['price']) ?></td>
        <td class="text-end text-nowrap"><?= $s['purchase_price'] !== null ? euro($s['purchase_price']) : '—' ?></td>
        <td>
            <?php if ($pool) : ?>
                <span class="small text-muted">Inkoop komt uit een kostenpool</span>
            <?php else : ?>
            <select name="key[<?= $id ?>]" class="form-select form-select-sm vx-key">
                <option value="">— niet van Vimexx —</option>
                <?php foreach ($cats as $cat => $list) : ?>
                <optgroup label="<?= esc($cat, 'attr') ?>">
                    <?php foreach ($list as $r) : ?>
                    <option value="<?= esc($r['key'], 'attr') ?>" data-new="<?= $r['new'] ?>" data-months="<?= $r['months'] ?>" <?= $sel === $r['key'] ? 'selected' : '' ?>><?= esc($r['name']) ?> — <?= euro($r['new']) ?><?= $r['old'] !== null ? ' (was ' . euro($r['old']) . ')' : '' ?></option>
                    <?php endforeach ?>
                </optgroup>
                <?php endforeach ?>
            </select>
            <?php endif ?>
        </td>
        <td>
            <?php if (! $pool) : ?>
            <select name="months[<?= $id ?>]" class="form-select form-select-sm vx-months" aria-label="Vimexx-prijs per">
                <?php foreach ([1 => 'maand', 3 => 'kwartaal', 12 => 'jaar'] as $m => $l) : ?><option value="<?= $m ?>" <?= ($sg['months'] ?? ($sel ? $byKey[$sel]['months'] : 12)) == $m ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
            </select>
            <?php endif ?>
        </td>
        <td class="text-end text-nowrap vx-new">—</td>
        <td class="text-end text-nowrap vx-margin">—</td>
        <td class="text-center"><?php if (! $pool) : ?><input type="checkbox" class="form-check-input vx-apply" name="apply[]" value="<?= $id ?>" aria-label="Inkoopprijs bijwerken"><?php endif ?></td>
    </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div>
</div></div>

<div class="d-flex flex-wrap gap-3 align-items-center">
    <button class="btn btn-primary"><i class="bi bi-check2"></i> Bijwerken</button>
    <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="subs" value="1" id="vx-subs" checked>
        <label class="form-check-label small" for="vx-subs">Ook abonnementen met een eigen inkoopprijs die gelijk is aan de oude<?= array_sum($subs) ? ' (' . array_sum($subs) . ')' : '' ?></label></div>
    <label class="small text-muted ms-auto"><input type="checkbox" class="form-check-input" id="vx-only"> alleen gekoppelde diensten tonen</label>
</div>
<div class="form-text">Alleen aangevinkte diensten worden bijgewerkt. Vinkjes staan vanzelf aan bij een zekere koppeling (domeinextensie of eerder gekozen) waar de prijs verschilt; een voorstel op naam vink je zelf aan. Je keuze per dienst wordt onthouden.</div>
</form>

<script>
(function () {
    const fmt = v => '€ ' + v.toFixed(2).replace('.', ',');
    function calc(tr) {
        const key = tr.querySelector('.vx-key'), mon = tr.querySelector('.vx-months');
        if (!key) return;
        const opt = key.selectedOptions[0], cb = tr.querySelector('.vx-apply');
        const newCell = tr.querySelector('.vx-new'), mCell = tr.querySelector('.vx-margin');
        tr.classList.toggle('vx-none', !opt || !opt.value);
        if (!opt || !opt.value) { newCell.textContent = '—'; mCell.textContent = '—'; mCell.className = 'text-end text-nowrap vx-margin'; cb.checked = false; cb.disabled = true; return; }
        cb.disabled = false;
        const val = Math.round(parseFloat(opt.dataset.new) * parseInt(tr.dataset.interval) / parseInt(mon.value) * 100) / 100;
        const old = tr.dataset.old === '' ? null : parseFloat(tr.dataset.old);
        const diff = old === null ? null : Math.round((val - old) * 100) / 100;
        newCell.innerHTML = fmt(val) + (diff ? ' <span class="small ' + (diff > 0 ? 'text-danger' : 'text-success') + '">(' + (diff > 0 ? '+' : '−') + fmt(Math.abs(diff)).slice(2) + ')</span>' : '');
        const margin = parseFloat(tr.dataset.price) - val;
        mCell.textContent = fmt(margin);
        mCell.className = 'text-end text-nowrap vx-margin ' + (margin < 0 ? 'text-danger fw-semibold' : '');
        // Vanzelf aan: alleen bij een zekere koppeling (extensie of eerder gekozen) of na een eigen keuze
        if (!tr.dataset.touched) cb.checked = (tr.dataset.auto === '1' || tr.dataset.chosen === '1') && (diff === null || Math.abs(diff) >= 0.01);
    }
    document.querySelectorAll('.vx-table tbody tr').forEach(tr => {
        calc(tr);
        tr.querySelectorAll('.vx-key, .vx-months').forEach(el => el.addEventListener('change', () => {
            const o = tr.querySelector('.vx-key').selectedOptions[0];
            if (el.classList.contains('vx-key') && o && o.dataset.months) tr.querySelector('.vx-months').value = o.dataset.months;
            tr.dataset.chosen = '1';
            calc(tr);
        }));
        tr.querySelector('.vx-apply')?.addEventListener('change', () => { tr.dataset.touched = '1'; });
    });
    const only = document.getElementById('vx-only');
    only.addEventListener('change', () => document.querySelector('.vx-table').classList.toggle('vx-only', only.checked));
})();
</script>
<?php endif ?>
