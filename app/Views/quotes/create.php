<a href="<?= site_url('offertes') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Offertes</a>
<h3 class="mb-3">Nieuwe offerte</h3>
<form method="post" class="card col-lg-6"><div class="card-body">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Klant *</label>
        <select name="customer_id" class="form-select" required>
            <option value="">Kies een klant…</option>
            <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>" <?= (string) $customerId === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?><?= $c['moneybird_contact_id'] ? '' : ' (niet in Moneybird)' ?></option><?php endforeach ?>
        </select>
        <div class="form-text">Staat de klant er niet tussen? <a href="<?= site_url('klanten/nieuw') ?>">Maak hem eerst aan</a>.</div>
    </div>
    <div class="mb-3"><label class="form-label">Omschrijving *</label><input name="title" class="form-control" required placeholder="Bijv. Wifi-netwerk kantoor, Camerasysteem woning" value="<?= esc(old('title') ?? '') ?>"></div>
    <button class="btn btn-primary">Verder</button>
</div></form>
