<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Klanten</h3>
    <?php if (user_can('customers.manage')) : ?>
        <a href="<?= site_url('klanten/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe klant</a>
    <?php endif ?>
</div>
<div class="card"><div class="card-body">
<table class="table table-hover datatable">
    <thead><tr><th>Naam</th><th>Contactpersoon</th><th>Plaats</th><th>Telefoon</th><th class="text-center">Locaties</th><th class="text-center">Abonnementen</th><th class="text-center">Apparaten</th><?php if ($network !== null) : ?><th class="text-start">Netwerk</th><?php endif ?><th>Moneybird</th></tr></thead>
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
            <?php if ($network !== null) : $n = $network[(int) $c['id']] ?? null; ?>
            <td class="text-nowrap text-start" data-order="<?= $n ? ($n['offline'] ? 1000 + $n['offline'] : ($n['total'] ? 500 : ($n['sites'] ? 100 : 0))) : 0 ?>">
                <?php if ($n) : ?>
                    <?php if ($n['offline']) : ?><span class="badge text-bg-danger" title="offline"><i class="bi bi-wifi-off"></i> <?= (int) $n['offline'] ?></span>
                    <?php elseif ($n['total']) : ?><span class="badge text-bg-success" title="alle <?= (int) $n['total'] ?> apparaten online"><i class="bi bi-wifi"></i> <?= (int) $n['total'] ?></span><?php endif ?>
                    <?php if (count($n['sites']) === 1) : ?>
                        <a href="<?= site_url('netwerk/topologie/' . $n['sites'][0]['id']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Topologie <?= esc($n['sites'][0]['name'], 'attr') ?>"><i class="bi bi-diagram-3"></i></a>
                    <?php elseif ($n['sites']) : ?>
                        <span class="dropdown"><button class="btn btn-sm btn-outline-secondary py-0 px-1 dropdown-toggle" data-bs-toggle="dropdown" title="Topologie"><i class="bi bi-diagram-3"></i> <?= count($n['sites']) ?></button>
                        <ul class="dropdown-menu"><?php foreach ($n['sites'] as $ns) : ?><li><a class="dropdown-item" href="<?= site_url('netwerk/topologie/' . $ns['id']) ?>"><?= esc(\App\Libraries\Network::platformLabel($ns['platform'])) ?> · <?= esc($ns['name']) ?></a></li><?php endforeach ?></ul></span>
                    <?php endif ?>
                <?php endif ?>
            </td>
            <?php endif ?>
            <td><?= $c['moneybird_contact_id'] ? '<i class="bi bi-link-45deg text-success" title="Gekoppeld"></i>' : '<span class="text-muted small">niet gekoppeld</span>' ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div></div>
