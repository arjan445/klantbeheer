<?php $s = $sub; $back = service('request')->getGet('terug') === 'klant' || service('request')->getGet('klant'); ?>
<a href="<?= site_url($back && ! empty($s['customer_id']) ? 'klanten/' . $s['customer_id'] : 'abonnementen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Terug</a>
<h3 class="mb-3"><?= $isNew ? 'Nieuw abonnement' : 'Abonnement bewerken' ?></h3>
<?php if ($services === []) : ?>
    <div class="alert alert-warning">Maak eerst een <a href="<?= site_url('diensten/nieuw') ?>">dienst</a> aan (bijv. Microsoft 365, webhosting of domeinnaam).</div>
<?php endif ?>
<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <input type="hidden" name="return" value="<?= $back ? 'customer' : '' ?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Klant *</label>
            <select name="customer_id" id="customer_id" class="form-select" required>
                <option value="">Kies een klant…</option>
                <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>" <?= (string) ($s['customer_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Locatie</label>
            <select name="site_id" class="form-select" data-sites-for="#customer_id">
                <option value="">—</option>
                <?php foreach ($sites as $site) : ?><option value="<?= $site['id'] ?>" data-customer="<?= $site['customer_id'] ?>" <?= (string) ($s['site_id'] ?? '') === (string) $site['id'] ? 'selected' : '' ?>><?= esc($site['name']) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Dienst *</label>
            <select name="service_id" id="service_id" class="form-select" required>
                <option value="">Kies een dienst…</option>
                <?php foreach ($services as $sv) : ?>
                    <option value="<?= $sv['id'] ?>" data-price="<?= esc($sv['price']) ?>" data-purchase="<?= esc($sv['purchase_price'] ?? '') ?>" data-interval="<?= (int) $sv['interval_months'] ?>" data-type="<?= esc($sv['type']) ?>"
                        <?= (string) ($s['service_id'] ?? '') === (string) $sv['id'] ? 'selected' : '' ?>><?= esc(service_type_label($sv['type'])) ?> · <?= esc($sv['name']) ?> (<?= euro($sv['price']) ?>)</option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Domein</label>
            <input name="domain_name" id="domain_name" class="form-control mono" list="domain-list" autocomplete="off" value="<?= esc($s['domain_name'] ?? '') ?>" placeholder="klant.nl">
            <datalist id="domain-list">
                <?php foreach ($domains as $dm) : ?><option value="<?= esc($dm['name'], 'attr') ?>" data-customer="<?= $dm['customer_id'] ?>"></option><?php endforeach ?>
            </datalist>
            <div class="form-text">Bestaand domein kiezen of een nieuw intikken.</div>
        </div>
        <div class="col-md-3"><label class="form-label">Extra omschrijving</label>
            <input name="label" id="label" class="form-control" value="<?= esc($s['label'] ?? '') ?>" placeholder="Bijv. tenant, webshop">
            <div class="form-text">Optioneel; komt samen met het domein op de factuur.</div>
        </div>
        <div class="col-md-2"><label class="form-label">Aantal *</label><input name="quantity" class="form-control" required value="<?= esc(isset($s['quantity']) ? aantal($s['quantity']) : '1') ?>"><div class="form-text" id="unit-hint"></div></div>
        <div class="col-md-3"><label class="form-label">Afwijkende prijs</label><div class="input-group"><span class="input-group-text">€</span><input name="price" id="price" class="form-control" value="<?= esc($s['price'] ?? '') ?>"></div><div class="form-text">Leeg = prijs van de dienst.</div></div>
        <div class="col-md-3"><label class="form-label">Afwijkende inkoop</label><div class="input-group"><span class="input-group-text">€</span><input name="purchase_price" id="purchase_price" class="form-control" value="<?= esc($s['purchase_price'] ?? '') ?>"></div></div>
        <div class="col-md-4"><label class="form-label">Factureren per *</label>
            <select name="interval_months" id="interval_months" class="form-select">
                <?php foreach ([1, 3, 6, 12, 24] as $m) : ?><option value="<?= $m ?>" <?= (int) ($s['interval_months'] ?? 12) === $m ? 'selected' : '' ?>><?= interval_label($m) ?></option><?php endforeach ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Startdatum</label><input type="date" name="start_date" class="form-control" value="<?= esc($s['start_date'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Volgende factuurdatum *</label><input type="date" name="next_invoice_date" class="form-control" required value="<?= esc($s['next_invoice_date'] ?? '') ?>">
            <div class="form-text">Begin van de volgende periode die nog gefactureerd moet worden.</div></div>
        <div class="col-md-3"><label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" <?= ($s['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Actief</option>
                <option value="cancelled" <?= ($s['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Opgezegd</option>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Einddatum</label><input type="date" name="end_date" class="form-control" value="<?= esc($s['end_date'] ?? '') ?>"><div class="form-text">Na deze datum niet meer factureren.</div></div>
        <div class="col-12"><label class="form-label">Notities</label><textarea name="notes" rows="2" class="form-control"><?= esc($s['notes'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Opslaan</button></div>
</div></form>

<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('abonnementen/' . $s['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Abonnement verwijderen', 'Abonnement verwijderen?') ?></div>
<?php if ($history !== []) : ?>
<div class="card mt-3"><div class="card-header">Gefactureerde periodes</div><div class="card-body">
    <?= view('billing/_history', ['lines' => $history, 'showCustomer' => false, 'mb' => new \App\Libraries\Moneybird()]) ?>
</div></div>
<?php endif ?>
<?php endif ?>

<script>
(function () {
    const svc = document.getElementById('service_id');
    const units = <?= json_encode(array_map(static fn ($t) => $t[1], service_types())) ?>;
    const isNew = <?= $isNew ? 'true' : 'false' ?>;
    function update(initial) {
        const o = svc.selectedOptions[0];
        if (!o || !o.value) return;
        document.getElementById('price').placeholder = o.dataset.price;
        document.getElementById('purchase_price').placeholder = o.dataset.purchase;
        document.getElementById('unit-hint').textContent = 'aantal ' + (units[o.dataset.type] || 'stuks');
        if (isNew && !initial) document.getElementById('interval_months').value = o.dataset.interval;
    }
    svc.addEventListener('change', () => update(false));
    update(true);

    // Domeinsuggesties alleen van de gekozen klant
    const cust = document.getElementById('customer_id');
    const all = Array.from(document.querySelectorAll('#domain-list option')).map(o => o.cloneNode());
    function filterDomains() {
        const list = document.getElementById('domain-list');
        list.innerHTML = '';
        all.filter(o => !cust.value || o.dataset.customer === cust.value).forEach(o => list.appendChild(o.cloneNode()));
    }
    cust.addEventListener('change', filterDomains);
    filterDomains();
})();
</script>
