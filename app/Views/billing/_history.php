<?php $showCustomer ??= false; ?>
<table class="table table-sm <?= count($lines) > 15 ? 'datatable' : '' ?>">
    <thead><tr>
        <th>Gefactureerd op</th>
        <?php if ($showCustomer) : ?><th>Klant</th><?php endif ?>
        <th>Omschrijving</th><th class="text-end">Aantal</th><th class="text-end">Totaal</th><th>Via</th>
    </tr></thead>
    <tbody>
    <?php foreach ($lines as $l) : ?>
        <tr>
            <td class="text-nowrap" data-order="<?= esc($l['created_at']) ?>"><?= datum($l['created_at']) ?></td>
            <?php if ($showCustomer) : ?><td><a href="<?= site_url('klanten/' . $l['customer_id']) ?>"><?= esc($l['customer_name']) ?></a></td><?php endif ?>
            <td><?= esc($l['description']) ?></td>
            <td class="text-end"><?= aantal($l['quantity']) ?></td>
            <td class="text-end"><?= euro($l['total']) ?></td>
            <td>
                <?php if ($l['method'] === 'moneybird' && $l['moneybird_invoice_id']) : ?>
                    <a href="<?= esc($mb->invoiceUrl($l['moneybird_invoice_id']), 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Moneybird</a>
                <?php else : ?><span class="text-muted small">handmatig</span><?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    <?php if ($lines === []) : ?><tr><td colspan="6" class="text-muted">Nog niets gefactureerd.</td></tr><?php endif ?>
    </tbody>
</table>
