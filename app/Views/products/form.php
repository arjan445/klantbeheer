<?php $p = $product; ?>
<a href="<?= site_url('producten') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Producten</a>
<h3 class="mb-3"><?= $isNew ? 'Los product toevoegen' : 'Product bewerken' ?></h3>
<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Omschrijving *</label><input name="name" class="form-control" required value="<?= esc($p['name'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Leverancier *</label>
            <select name="supplier_id" class="form-select"><?php foreach ($suppliers as $s) : ?><option value="<?= $s['id'] ?>" <?= (string) ($p['supplier_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?></select></div>
        <div class="col-md-3"><label class="form-label">Typenummer</label><input name="sku" class="form-control mono" value="<?= esc($p['sku'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Artikelnr leverancier</label><input name="supplier_sku" class="form-control mono" value="<?= esc($p['supplier_sku'] ?? '') ?>" <?= ! $isNew && ($p['source'] ?? '') !== 'manual' ? 'readonly' : '' ?>></div>
        <div class="col-md-3"><label class="form-label">Merk</label><input name="brand" class="form-control" value="<?= esc($p['brand'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Groep</label><input name="group_name" class="form-control" value="<?= esc($p['group_name'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Bruto prijs</label><div class="input-group"><span class="input-group-text">€</span><input name="list_price" class="form-control" value="<?= esc($p['list_price'] ?? '') ?>"></div><div class="form-text">Korting van leverancier/groep gaat hiervan af.</div></div>
        <div class="col-md-3"><label class="form-label">Inkoopprijs (netto)</label><div class="input-group"><span class="input-group-text">€</span><input name="cost_price" class="form-control" value="<?= esc($p['cost_price'] ?? '') ?>"></div><div class="form-text">Ingevuld = deze geldt i.p.v. bruto − korting.</div></div>
        <div class="col-md-3"><label class="form-label">Opslag</label><div class="input-group"><span class="input-group-text">×</span><input name="markup" class="form-control" value="<?= esc($p['markup'] ?? '') ?>" placeholder="standaard"></div><div class="form-text">Leeg = groep/leverancier (1,2).</div></div>
        <div class="col-md-3"><label class="form-label">Vaste verkoopprijs</label><div class="input-group"><span class="input-group-text">€</span><input name="sell_price" class="form-control" value="<?= esc($p['sell_price'] ?? '') ?>" placeholder="berekend"></div></div>
        <div class="col-md-8"><label class="form-label">Info</label><input name="info" class="form-control" value="<?= esc($p['info'] ?? '') ?>"></div>
        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= ($p['active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="active">Actief (vindbaar in offertes)</label></div></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Opslaan</button></div>
</div></form>
<?php if (! $isNew && ($p['source'] ?? '') !== 'manual') : ?>
<p class="small text-muted mt-2">Let op: bij een volgende import van <?= esc($p['source']) ?> worden naam en prijs van de leverancier weer bijgewerkt. Een eigen opslag of vaste verkoopprijs blijft staan.</p>
<?php endif ?>
