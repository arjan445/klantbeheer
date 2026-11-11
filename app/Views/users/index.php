<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Gebruikers</h3>
    <a href="<?= site_url('beheer/gebruikers/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe gebruiker</a>
</div>
<div class="card"><div class="card-body">
<table class="table table-hover">
    <thead><tr><th>E-mail</th><th>Gebruikersnaam</th><th>Rol</th><th>Status</th><th>Laatst actief</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u) : ?>
        <tr class="<?= $u->active ? '' : 'text-muted' ?>">
            <td><?= esc($u->email) ?></td>
            <td><?= esc($u->username) ?></td>
            <td><?= esc(implode(', ', array_map(static fn ($g) => $groups[$g]['title'] ?? $g, $u->getGroups()))) ?></td>
            <td><?= $u->active ? '<span class="badge text-bg-success">actief</span>' : '<span class="badge text-bg-secondary">inactief</span>' ?></td>
            <td class="small"><?= $u->last_active ? $u->last_active->format('d-m-Y H:i') : '—' ?></td>
            <td class="text-end"><a href="<?= site_url('beheer/gebruikers/' . $u->id) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div></div>
<div class="card mt-3"><div class="card-body small">
    <?php foreach ($groups as $g) : ?><div><strong><?= esc($g['title']) ?>:</strong> <?= esc($g['description']) ?></div><?php endforeach ?>
</div></div>
