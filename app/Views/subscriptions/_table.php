<?php
$showCustomer ??= true;
$today = date('Y-m-d');
?>
<table class="table table-sm table-hover <?= count($subscriptions) > 15 ? 'datatable' : '' ?>">
    <thead><tr>
        <?php if ($showCustomer) : ?><th>Klant</th><?php endif ?>
        <th>Dienst</th><th>Omschrijving</th><th class="text-end">Aantal</th><th class="text-end">Prijs</th><th class="text-end">Per jaar</th><th>Volgende factuur</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($subscriptions as $s) : ?>
        <?php $overdue = $s['status'] === 'active' && $s['next_invoice_date'] < $today; ?>
        <tr class="<?= $s['status'] !== 'active' ? 'text-muted' : '' ?> <?= $overdue ? 'row-overdue' : '' ?>">
            <?php if ($showCustomer) : ?><td><a href="<?= site_url('klanten/' . $s['customer_id']) ?>"><?= esc($s['customer_name']) ?></a></td><?php endif ?>
            <td><span class="badge badge-type"><?= esc(service_type_label($s['service_type'])) ?></span> <?= esc($s['service_name']) ?></td>
            <td><?= esc($s['label']) ?><?php if ($s['site_name']) : ?> <span class="small text-muted">· <?= esc($s['site_name']) ?></span><?php endif ?></td>
            <td class="text-end"><?= aantal($s['quantity']) ?></td>
            <td class="text-end text-nowrap"><?= euro($s['eff_price']) ?><span class="small text-muted"> / <?= esc(interval_label((int) $s['interval_months'])) ?></span><?= $s['price'] !== null ? ' <i class="bi bi-pencil-square small text-muted" title="Afwijkende prijs"></i>' : '' ?></td>
            <td class="text-end"><?= euro(\App\Libraries\Billing::yearly($s)) ?></td>
            <td class="text-nowrap <?= $overdue ? 'text-danger fw-semibold' : '' ?>" data-order="<?= esc($s['next_invoice_date']) ?>"><?= $s['status'] === 'active' ? datum($s['next_invoice_date']) : '' ?></td>
            <td><?= $s['status'] === 'active' ? '<span class="badge text-bg-success">actief</span>' : '<span class="badge text-bg-secondary">opgezegd' . ($s['end_date'] ? ' per ' . datum($s['end_date']) : '') . '</span>' ?></td>
            <td class="text-end"><?php if (user_can('billing.manage')) : ?><a href="<?= site_url('abonnementen/' . $s['id'] . '/bewerken' . ($showCustomer ? '' : '?terug=klant')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a><?php endif ?></td>
        </tr>
    <?php endforeach ?>
    <?php if ($subscriptions === []) : ?><tr><td colspan="9" class="text-muted">Geen abonnementen.</td></tr><?php endif ?>
    </tbody>
</table>
