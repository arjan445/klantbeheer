<?php $c = $customer ?? []; $isNew = empty($c['id']); ?>
<h3 class="mb-3"><?= $isNew ? 'Nieuwe klant' : 'Klant bewerken' ?></h3>
<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Naam (bedrijf of persoon) *</label><input name="name" class="form-control" required value="<?= esc($c['name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Contactpersoon</label><input name="contact_name" class="form-control" value="<?= esc($c['contact_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">E-mail</label><input name="email" type="email" class="form-control" value="<?= esc($c['email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Telefoon</label><input name="phone" class="form-control" value="<?= esc($c['phone'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Adres</label><input name="address" class="form-control" value="<?= esc($c['address'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Postcode</label><input name="zipcode" class="form-control" value="<?= esc($c['zipcode'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Plaats</label><input name="city" class="form-control" value="<?= esc($c['city'] ?? '') ?>"></div>
        <div class="col-md-6">
            <label class="form-label">Moneybird contact-ID</label>
            <input name="moneybird_contact_id" class="form-control mono" value="<?= esc($c['moneybird_contact_id'] ?? '') ?>">
            <div class="form-text">Het getal uit de URL van het contact in Moneybird. Of importeer alle contacten via Beheer &gt; Moneybird.</div>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= ($c['active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="active">Actieve klant</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="internal" value="1" id="internal" <?= ! empty($c['internal']) ? 'checked' : '' ?>><label class="form-check-label" for="internal">Eigen gebruik <span class="text-muted small">(bijhouden, maar nooit factureren en niet als omzet tellen)</span></label></div>
                <?php if ($isNew) : ?>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="create_site" value="1" id="cs" checked><label class="form-check-label" for="cs">Maak meteen een locatie "Hoofdlocatie" op dit adres</label></div>
                <?php endif ?>
            </div>
        </div>
        <div class="col-12"><label class="form-label">Notities</label><textarea name="notes" rows="3" class="form-control"><?= esc($c['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary">Opslaan</button>
        <a href="<?= site_url($isNew ? 'klanten' : 'klanten/' . $c['id']) ?>" class="btn btn-outline-secondary">Annuleren</a>
    </div>
</div></form>
