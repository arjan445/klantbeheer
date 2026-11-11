<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Diensten</h3>
        <div class="text-muted small">Wat je verkoopt, met standaardprijs. Per abonnement kun je de prijs nog aanpassen.</div>
    </div>
    <?php if (user_can('billing.manage')) : ?>
        <a href="<?= site_url('diensten/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe dienst</a>
    <?php endif ?>
</div>
<div class="card"><div class="card-body">
<table class="table table-hover">
    <thead><tr><th>Dienst</th><th>Soort</th><th class="text-end">Verkoop</th><th class="text-end">Inkoop</th><th class="text-end">Marge</th><th>Per</th><th>Leverancier</th><th class="text-center">In gebruik</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($services as $s) : ?>
        <tr class="<?= $s['active'] ? '' : 'text-muted' ?>">
            <td class="fw-semibold"><?= esc($s['name']) ?><?= $s['active'] ? '' : ' <span class="badge text-bg-secondary">inactief</span>' ?></td>
            <td><span class="badge badge-type"><?= esc(service_type_label($s['type'])) ?></span></td>
            <td class="text-end"><?= euro($s['price']) ?></td>
            <td class="text-end"><?= $s['purchase_price'] !== null ? euro($s['purchase_price']) : '—' ?><?php if ($s['cost_pool_id']) : ?><div class="small text-muted" title="Automatisch uit kostenpool"><i class="bi bi-diagram-3"></i> <?= esc($pools[$s['cost_pool_id']] ?? '') ?></div><?php endif ?></td>
            <td class="text-end"><?= $s['purchase_price'] !== null ? euro($s['price'] - $s['purchase_price']) : '—' ?></td>
            <td class="small"><?= esc($s['unit']) ?> / <?= esc(interval_label((int) $s['interval_months'])) ?></td>
            <td class="small"><?= esc($s['supplier']) ?></td>
            <td class="text-center"><?= (int) $s['sub_count'] ?> <span class="small text-muted">(<?= aantal($s['qty']) ?> st.)</span></td>
            <td class="text-end"><?php if (user_can('billing.manage')) : ?><a href="<?= site_url('diensten/' . $s['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    <?php if ($services === []) : ?>
        <tr><td colspan="9" class="text-muted">Nog geen diensten. Begin bijvoorbeeld met "Microsoft 365 Business Basic", "Webhosting" en "Domeinnaam .nl".</td></tr>
    <?php endif ?>
    </tbody>
</table>
</div></div>
