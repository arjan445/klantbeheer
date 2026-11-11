<?php $d = $domain; ?>
<a href="<?= site_url(! empty($d['customer_id']) && $isNew ? 'klanten/' . $d['customer_id'] : 'domeinen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Terug</a>
<h3 class="mb-3"><?= $isNew ? 'Nieuw domein' : esc($d['name']) ?></h3>
<div class="row g-3">
<div class="col-lg-6">
<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label">Domeinnaam *</label><input name="name" class="form-control mono" required value="<?= esc($d['name'] ?? '') ?>" placeholder="klant.nl"></div>
    <div class="mb-3"><label class="form-label">Klant *</label>
        <select name="customer_id" class="form-select" required>
            <option value="">Kies een klant…</option>
            <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>" <?= (string) ($d['customer_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?>
        </select>
    </div>
    <div class="mb-3"><label class="form-label">Registrar (als het domein niet bij jou staat)</label><input name="registrar" class="form-control" value="<?= esc($d['registrar'] ?? '') ?>" placeholder="Bijv. TransIP (klant zelf), Hostnet"></div>
    <div class="mb-3"><label class="form-label">Notities</label><textarea name="notes" rows="3" class="form-control" placeholder="Bijv. DNS bij Cloudflare, mail via Microsoft 365"><?= esc($d['notes'] ?? '') ?></textarea></div>
    <button class="btn btn-primary">Opslaan</button>
</div></form>
<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('domeinen/' . $d['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Domein verwijderen', 'Domein verwijderen?') ?></div>
<?php endif ?>
</div>
<?php if (! $isNew) : ?>
<div class="col-lg-6">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
        <span>Abonnementen op dit domein</span>
        <?php if (user_can('billing.manage')) : ?><a href="<?= site_url('abonnementen/nieuw?klant=' . $d['customer_id'] . '&domein=' . $d['id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Toevoegen</a><?php endif ?>
    </div><div class="card-body">
        <?php if ($subs === []) : ?>
            <p class="text-muted mb-0">Er hangt nog niets aan dit domein.</p>
        <?php else : ?>
            <?= view('subscriptions/_table', ['subscriptions' => $subs, 'showCustomer' => false]) ?>
        <?php endif ?>
    </div></div>
</div>
<?php endif ?>
</div>
