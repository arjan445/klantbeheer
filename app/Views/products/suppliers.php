<a href="<?= site_url('producten') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Producten</a>
<h3 class="mb-1">Leveranciers &amp; marges</h3>
<p class="text-muted small">Kostprijs = bruto × (1 − korting%), of de netto inkoopprijs als die bekend is (Wimood). Verkoop = kostprijs × opslag. Een lege groepswaarde volgt de leverancier.</p>
<form method="post"><?= csrf_field() ?>
<?php foreach ($suppliers as $s) : ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?= esc($s['name']) ?></span>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($s['code'] !== 'wimood') : ?>
            <label class="small fw-normal">Korting op bruto</label>
            <div class="input-group input-group-sm" style="width: 110px"><input name="supplier[<?= $s['id'] ?>][discount]" class="form-control" value="<?= esc(aantal($s['default_discount'])) ?>"><span class="input-group-text">%</span></div>
            <?php endif ?>
            <label class="small fw-normal ms-2">Opslag</label>
            <div class="input-group input-group-sm" style="width: 110px"><span class="input-group-text">×</span><input name="supplier[<?= $s['id'] ?>][markup]" class="form-control" value="<?= esc(rtrim(rtrim(number_format((float) $s['default_markup'], 3, ',', ''), '0'), ',')) ?>"></div>
        </div>
    </div>
    <?php if (! empty($groups[$s['id']])) : ?>
    <div class="card-body p-0">
        <details class="p-2">
            <summary class="small">Per productgroep afwijken (<?= count($groups[$s['id']]) ?> groepen)</summary>
            <table class="table table-sm mt-2 mb-0">
                <thead><tr><th>Groep</th><th class="text-end">Producten</th><?php if ($s['code'] !== 'wimood') : ?><th style="width: 140px">Korting</th><?php endif ?><th style="width: 140px">Opslag</th></tr></thead>
                <?php foreach ($groups[$s['id']] as $g) : ?>
                    <tr>
                        <td><?= esc($g['group_name']) ?></td>
                        <td class="text-end small"><?= (int) $g['count'] ?></td>
                        <?php if ($s['code'] !== 'wimood') : ?>
                        <td><div class="input-group input-group-sm"><input name="group[<?= $g['id'] ?>][discount]" class="form-control" value="<?= $g['discount'] !== null ? esc(aantal($g['discount'])) : '' ?>" placeholder="<?= esc(aantal($s['default_discount'])) ?>"><span class="input-group-text">%</span></div></td>
                        <?php endif ?>
                        <td><div class="input-group input-group-sm"><span class="input-group-text">×</span><input name="group[<?= $g['id'] ?>][markup]" class="form-control" value="<?= $g['markup'] !== null ? esc(rtrim(rtrim(number_format((float) $g['markup'], 3, ',', ''), '0'), ',')) : '' ?>" placeholder="<?= esc(rtrim(rtrim(number_format((float) $s['default_markup'], 3, ',', ''), '0'), ',')) ?>"></div></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </details>
    </div>
    <?php endif ?>
</div>
<?php endforeach ?>
<div class="card mb-3"><div class="card-body d-flex gap-2 align-items-center">
    <label class="small text-nowrap">Nieuwe leverancier</label><input name="new_supplier" class="form-control form-control-sm" style="max-width: 280px" placeholder="Bijv. Technische Unie">
</div></div>
<button class="btn btn-primary">Opslaan</button>
</form>
