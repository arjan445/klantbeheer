<?php $i = $inv; ?>
<a href="<?= site_url($isNew ? 'inkoopfacturen' : 'inkoopfacturen/' . $i['id']) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Terug</a>
<h3 class="mb-3"><?= $isNew ? 'Inkoopfactuur toevoegen' : 'Inkoopfactuur bewerken' ?></h3>
<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small mb-1">Leverancier</label>
            <select name="supplier_id" class="form-select" onchange="document.getElementById('sup-free').classList.toggle('d-none', this.value !== '')">
                <option value="">Andere leverancier…</option>
                <?php foreach ($suppliers as $s) : ?><option value="<?= $s['id'] ?>" <?= (string) ($i['supplier_id'] ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6 <?= ! empty($i['supplier_id']) ? 'd-none' : '' ?>" id="sup-free">
            <label class="form-label small mb-1">Naam leverancier</label>
            <input name="supplier" class="form-control" value="<?= esc($i['supplier'] ?? '') ?>" placeholder="Bijv. Alarmtech, Coolblue">
        </div>
        <div class="col-7 col-md-4">
            <label class="form-label small mb-1">Factuurnummer *</label>
            <input name="number" class="form-control mono" required value="<?= esc($i['number'] ?? '') ?>">
        </div>
        <div class="col-5 col-md-4">
            <label class="form-label small mb-1">Datum</label>
            <input type="date" name="date" class="form-control" value="<?= esc($i['date'] ?? '') ?>">
        </div>
        <?php if (user_can('billing.view')) : ?>
        <div class="col-6 col-md-4 km-hide">
            <label class="form-label small mb-1">Bedrag (excl. btw)</label>
            <input name="amount" class="form-control" inputmode="decimal" value="<?= esc(isset($i['amount']) ? str_replace('.', ',', (string) $i['amount']) : '') ?>">
        </div>
        <?php endif ?>
        <div class="col-md-8">
            <label class="form-label small mb-1">Moneybird-link of -ID <span class="text-muted">(optioneel)</span></label>
            <input name="moneybird_id" class="form-control mono" value="<?= esc($i['moneybird_id'] ?? '') ?>" placeholder="Plak de link van de inkoopfactuur in Moneybird">
        </div>
        <div class="col-12">
            <label class="form-label small mb-1">Notities</label>
            <textarea name="notes" rows="2" class="form-control"><?= esc($i['notes'] ?? '') ?></textarea>
        </div>
    </div>
    <div class="mt-3"><button class="btn btn-primary"><i class="bi bi-check2"></i> Opslaan</button></div>
</div></form>
<?php if (! $isNew) : ?>
<div class="mt-4"><?= post_link(site_url('inkoopfacturen/' . $i['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Verwijderen', 'Deze inkoopfactuur verwijderen? De apparaten blijven bestaan.') ?></div>
<?php endif ?>
