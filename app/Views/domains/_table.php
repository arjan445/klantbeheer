<?php
use App\Controllers\Domains;
$showCustomer ??= true;
$subLink = static function (array $s): string {
    $txt = esc($s['service_name']) . ' <span class="text-muted small">· t/m ' . datum(date('Y-m-d', strtotime($s['next_invoice_date'] . ' -1 day'))) . '</span>';
    return user_can('billing.manage') ? '<a href="' . site_url('abonnementen/' . $s['id'] . '/bewerken') . '" class="text-decoration-none">' . $txt . '</a>' : $txt;
};
?>
<table class="table table-sm table-hover align-middle <?= count($domains) > 15 ? 'datatable' : '' ?>">
    <thead><tr>
        <th>Domein</th><?php if ($showCustomer) : ?><th>Klant</th><?php endif ?>
        <th>Registratie</th><th>Hosting</th><th>Overig</th><th class="text-end">Per jaar</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($domains as $d) : ?>
        <?php [$label, $color] = Domains::STATUSES[$d['status']]; ?>
        <tr>
            <td class="fw-semibold"><a href="https://<?= esc($d['name'], 'attr') ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none"><?= esc($d['name']) ?> <i class="bi bi-box-arrow-up-right small"></i></a></td>
            <?php if ($showCustomer) : ?><td><a href="<?= site_url('klanten/' . $d['customer_id']) ?>"><?= esc($d['customer_name']) ?></a></td><?php endif ?>
            <td><?php foreach ($d['registration'] as $s) : ?><div><?= $subLink($s) ?></div><?php endforeach ?>
                <?php if ($d['registration'] === []) : ?><span class="text-muted small"><?= $d['registrar'] ? 'extern: ' . esc($d['registrar']) : '—' ?></span><?php endif ?></td>
            <td><?php foreach ($d['hosting'] as $s) : ?><div><?= $subLink($s) ?></div><?php endforeach ?>
                <?php if ($d['hosting'] === []) : ?><span class="text-muted small">—</span><?php endif ?></td>
            <td class="small"><?php foreach ($d['other'] as $s) : ?><div><?= $subLink($s) ?></div><?php endforeach ?></td>
            <td class="text-end"><?= $d['yearly'] > 0 ? euro($d['yearly']) : '' ?></td>
            <td><span class="badge text-bg-<?= $color ?>"><?= esc($label) ?></span></td>
            <td class="text-end text-nowrap">
                <?php if (user_can('billing.manage')) : ?>
                    <a href="<?= site_url('abonnementen/nieuw?klant=' . $d['customer_id'] . '&domein=' . $d['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Abonnement toevoegen aan dit domein"><i class="bi bi-plus-lg"></i></a>
                    <a href="<?= site_url('domeinen/' . $d['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary" title="Bewerken"><i class="bi bi-pencil"></i></a>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    <?php if ($domains === []) : ?><tr><td colspan="8" class="text-muted">Geen domeinen.</td></tr><?php endif ?>
    </tbody>
</table>
