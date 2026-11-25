<?php
/**
 * Snel een inkoopfactuur toevoegen (zonder de pagina te verlaten).
 * @var list<array> $suppliers
 * @var string      $target  selector van de <select> waarin de nieuwe factuur komt
 */
$uid = 'qi' . substr(md5($target), 0, 6);
?>
<div class="quick-inv border rounded p-2 bg-light" id="<?= $uid ?>">
    <div class="row g-2">
        <div class="col-12 col-md-4">
            <select class="form-select" data-f="supplier_id">
                <?php foreach ($suppliers as $s) : ?><option value="<?= $s['id'] ?>"><?= esc($s['name']) ?></option><?php endforeach ?>
                <option value="">Andere leverancier…</option>
            </select>
            <input class="form-control mt-1 d-none" data-f="supplier" placeholder="Naam leverancier">
        </div>
        <div class="col-7 col-md-4"><input class="form-control mono" data-f="number" placeholder="Factuurnummer" autocapitalize="characters"></div>
        <div class="col-5 col-md-4"><input type="date" class="form-control" data-f="date" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <button type="button" class="btn btn-sm btn-primary mt-2" data-add><i class="bi bi-plus-lg"></i> Factuur toevoegen</button>
    <span class="small text-danger ms-2" data-err></span>
</div>
<script>
(function () {
    const box = document.getElementById(<?= json_encode($uid) ?>);
    const f = n => box.querySelector('[data-f="' + n + '"]');
    f('supplier_id').addEventListener('change', e => f('supplier').classList.toggle('d-none', e.target.value !== ''));
    box.querySelector('[data-add]').addEventListener('click', async () => {
        const meta = document.querySelector('meta[name="csrf-hash"]'), name = document.querySelector('meta[name="csrf-name"]').content;
        const body = new FormData();
        body.append(name, (document.querySelector('input[name="' + name + '"]') || meta).value || meta.content);
        ['supplier_id', 'supplier', 'number', 'date'].forEach(k => body.append(k, f(k).value));
        const res = await fetch(<?= json_encode(site_url('inkoopfacturen/snel')) ?>, { method: 'POST', body, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json().catch(() => ({}));
        if (data.csrf) { meta.content = data.csrf; document.querySelectorAll('input[name="' + name + '"]').forEach(i => i.value = data.csrf); }
        if (!res.ok) { box.querySelector('[data-err]').textContent = data.error || 'Opslaan mislukt'; return; }
        box.querySelector('[data-err]').textContent = '';
        const sel = document.querySelector(<?= json_encode($target) ?>);
        let opt = [...sel.options].find(o => o.value === String(data.id));
        if (!opt) { opt = new Option(data.label, data.id); sel.add(opt, 1); }
        sel.value = String(data.id);
        sel.dispatchEvent(new Event('change'));
        f('number').value = '';
        const c = box.closest('.collapse'); if (c && window.bootstrap) bootstrap.Collapse.getOrCreateInstance(c).hide();
    });
})();
</script>
