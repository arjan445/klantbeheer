<?php $s = $service ?? []; $isNew = empty($s['id']); ?>
<a href="<?= site_url('diensten') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Diensten</a>
<h3 class="mb-3"><?= $isNew ? 'Nieuwe dienst' : 'Dienst bewerken' ?></h3>
<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Naam *</label><input name="name" class="form-control" required value="<?= esc($s['name'] ?? '') ?>" placeholder="Bijv. Microsoft 365 Business Standard"></div>
        <div class="col-md-3"><label class="form-label">Soort *</label>
            <select name="type" class="form-select" required>
                <?php foreach (service_types() as $key => [$label, $unit]) : ?>
                    <option value="<?= $key ?>" <?= ($s['type'] ?? '') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Eenheid</label><input name="unit" class="form-control" value="<?= esc($s['unit'] ?? '') ?>" placeholder="gebruiker, domein, licentie"></div>
        <div class="col-md-3"><label class="form-label">Verkoopprijs excl. btw *</label><div class="input-group"><span class="input-group-text">€</span><input name="price" class="form-control" required value="<?= esc($s['price'] ?? '') ?>"></div></div>
        <div class="col-md-3"><label class="form-label">Inkoopprijs</label>
            <?php if (! empty($pool)) : ?>
                <div class="input-group"><span class="input-group-text">€</span><input class="form-control" value="<?= esc($s['purchase_price'] ?? '') ?>" disabled></div>
                <div class="form-text"><i class="bi bi-diagram-3"></i> Automatisch uit kostenpool <a href="<?= site_url('kostprijzen') ?>"><?= esc($pool['name']) ?></a>.</div>
            <?php else : ?>
                <div class="input-group"><span class="input-group-text">€</span><input name="purchase_price" class="form-control" value="<?= esc($s['purchase_price'] ?? '') ?>"></div>
                <div class="form-text">Of laat hem uitrekenen via <a href="<?= site_url('kostprijzen') ?>">Kostprijzen</a>.</div>
            <?php endif ?>
        </div>
        <div class="col-md-3"><label class="form-label">Factureren per *</label>
            <select name="interval_months" class="form-select">
                <?php foreach ([1, 3, 6, 12, 24] as $m) : ?>
                    <option value="<?= $m ?>" <?= (int) ($s['interval_months'] ?? 12) === $m ? 'selected' : '' ?>><?= interval_label($m) ?></option>
                <?php endforeach ?>
            </select>
            <div class="form-text">De prijs geldt voor deze periode.</div>
        </div>
        <div class="col-md-3"><label class="form-label">Leverancier</label><input name="supplier" class="form-control" value="<?= esc($s['supplier'] ?? '') ?>" placeholder="Bijv. distributeur, hostingpartij"></div>
        <div class="col-md-3"><label class="form-label">Moneybird product-ID</label><input name="moneybird_product_id" class="form-control mono" value="<?= esc($s['moneybird_product_id'] ?? '') ?>"><div class="form-text">Optioneel, voor omzetrapportage per product.</div></div>
        <div class="col-md-3"><label class="form-label">Moneybird btw-tarief-ID</label><input name="tax_rate_id" class="form-control mono" value="<?= esc($s['tax_rate_id'] ?? '') ?>"><div class="form-text">Leeg = standaard uit .env.</div></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= ($s['active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="active">Actief (te kiezen bij nieuwe abonnementen)</label></div></div>
        <div class="col-12"><label class="form-label">Notities</label><textarea name="notes" rows="2" class="form-control"><?= esc($s['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Opslaan</button><a href="<?= site_url('diensten') ?>" class="btn btn-outline-secondary">Annuleren</a></div>
</div></form>
<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('diensten/' . $s['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Dienst verwijderen', 'Dienst verwijderen?') ?></div>
<?php endif ?>
