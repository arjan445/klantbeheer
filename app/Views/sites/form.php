<?php $s = $site ?? []; $isNew = empty($s['id']); ?>
<a href="<?= site_url('klanten/' . $customer['id']) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> <?= esc($customer['name']) ?></a>
<h3 class="mb-3"><?= $isNew ? 'Nieuwe locatie' : 'Locatie bewerken' ?></h3>
<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Naam *</label><input name="name" class="form-control" required value="<?= esc($s['name'] ?? '') ?>" placeholder="Bijv. Hoofdkantoor, Magazijn, Woonhuis"></div>
        <div class="col-md-6"><label class="form-label">Adres</label><input name="address" class="form-control" value="<?= esc($s['address'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Postcode</label><input name="zipcode" class="form-control" value="<?= esc($s['zipcode'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Plaats</label><input name="city" class="form-control" value="<?= esc($s['city'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Notities</label><textarea name="notes" rows="3" class="form-control" placeholder="Bijv. sleutelkluis, meterkast, IP-bereik, contactpersoon ter plaatse"><?= esc($s['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary">Opslaan</button>
        <a href="<?= site_url('klanten/' . $customer['id']) ?>" class="btn btn-outline-secondary">Annuleren</a>
    </div>
</div></form>
<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('klanten/locaties/' . $s['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Locatie verwijderen', 'Locatie verwijderen?') ?></div>
<?php endif ?>
