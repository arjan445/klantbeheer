<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Klanten</h3>
    <?php if (user_can('customers.manage')) : ?>
        <a href="<?= site_url('klanten/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe klant</a>
    <?php endif ?>
</div>
<div class="card"><div class="card-body">
<table class="table table-hover datatable">
    <thead><tr><th>Naam</th><th>Contactpersoon</th><th>Plaats</th><th>Telefoon</th><th class="text-center">Locaties</th><th class="text-center">Abonnementen</th><th class="text-center">Apparaten</th><th>Moneybird</th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c) : ?>
        <tr class="<?= $c['active'] ? '' : 'text-muted' ?>">
            <td><a href="<?= site_url('klanten/' . $c['id']) ?>" class="fw-semibold"><?= esc($c['name']) ?></a><?= $c['active'] ? '' : ' <span class="badge text-bg-secondary">inactief</span>' ?><?= ! empty($c['internal']) ? ' <span class="badge text-bg-info">eigen gebruik</span>' : '' ?></td>
            <td><?= esc($c['contact_name']) ?></td>
            <td><?= esc($c['city']) ?></td>
            <td><?= esc($c['phone']) ?></td>
            <td class="text-center"><?= (int) $c['site_count'] ?></td>
            <td class="text-center"><?= (int) $c['sub_count'] ?></td>
            <td class="text-center"><?= (int) $c['device_count'] ?></td>
            <td><?= $c['moneybird_contact_id'] ? '<i class="bi bi-link-45deg text-success" title="Gekoppeld"></i>' : '<span class="text-muted small">niet gekoppeld</span>' ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div></div>
