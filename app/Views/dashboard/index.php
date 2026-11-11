<h3 class="mb-4">Dashboard</h3>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="card kpi"><div class="card-body">
        <div class="label">Actieve klanten</div><div class="value"><?= (int) $customers ?></div>
    </div></div></div>
    <?php if (isset($activeSubs)) : ?>
    <div class="col-6 col-lg"><div class="card kpi"><div class="card-body">
        <div class="label">Actieve abonnementen</div><div class="value"><?= (int) $activeSubs ?></div>
    </div></div></div>
    <div class="col-6 col-lg"><div class="card kpi"><div class="card-body">
        <div class="label">Omzet per jaar (excl. btw)</div><div class="value"><?= euro($revenue) ?></div>
    </div></div></div>
    <div class="col-6 col-lg"><div class="card kpi"><div class="card-body">
        <div class="label">Marge per jaar</div><div class="value"><?= euro($revenue - $cost) ?></div>
        <?php if ($revenue > 0) : ?><div class="small text-muted"><?= round(($revenue - $cost) / $revenue * 100) ?>% van de omzet</div><?php endif ?>
    </div></div></div>
    <div class="col-6 col-lg"><a class="text-decoration-none" href="<?= site_url('facturatie') ?>"><div class="card kpi"><div class="card-body">
        <div class="label">Te factureren</div>
        <div class="value <?= $overdue > 0 ? 'text-danger' : '' ?>"><?= (int) $dueNow ?></div>
        <?php if ($overdue > 0) : ?><div class="small text-danger"><?= (int) $overdue ?> over datum</div><?php endif ?>
    </div></div></a></div>
    <?php endif ?>
    <?php if ($devices !== null) : ?>
    <div class="col-6 col-lg"><div class="card kpi"><div class="card-body">
        <div class="label">Apparaten in de kluis</div><div class="value"><?= (int) $devices ?></div>
    </div></div></div>
    <?php endif ?>
</div>

<?php if (isset($activeSubs)) : ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Verlengingen komende 60 dagen</span>
                <a href="<?= site_url('facturatie') ?>" class="small">Naar facturatie <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if ($upcoming === []) : ?>
                    <p class="text-muted p-3 mb-0">Niets te verlengen in de komende 60 dagen.</p>
                <?php else : ?>
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Datum</th><th>Klant</th><th>Dienst</th><th class="text-end">Aantal</th><th class="text-end">Bedrag</th></tr></thead>
                    <tbody>
                    <?php foreach ($upcoming as $s) : ?>
                        <tr class="<?= $s['next_invoice_date'] < date('Y-m-d') ? 'row-overdue' : '' ?>">
                            <td><?= datum($s['next_invoice_date']) ?></td>
                            <td><a href="<?= site_url('klanten/' . $s['customer_id']) ?>"><?= esc($s['customer_name']) ?></a></td>
                            <td><?= esc($s['service_name']) ?><?php if ($s['label']) : ?> <span class="text-muted">· <?= esc($s['label']) ?></span><?php endif ?></td>
                            <td class="text-end"><?= aantal($s['quantity']) ?></td>
                            <td class="text-end"><?= euro($s['eff_price'] * $s['quantity']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Per soort dienst (per jaar)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Soort</th><th class="text-end">Aantal</th><th class="text-end">Omzet</th><th class="text-end">Marge</th></tr></thead>
                    <tbody>
                    <?php foreach ($byType as $type => $t) : ?>
                        <tr>
                            <td><?= esc(service_type_label($type)) ?></td>
                            <td class="text-end"><?= aantal($t['qty']) ?></td>
                            <td class="text-end"><?= euro($t['revenue']) ?></td>
                            <td class="text-end"><?= euro($t['revenue'] - $t['cost']) ?></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($byType === []) : ?><tr><td colspan="4" class="text-muted">Nog geen abonnementen.</td></tr><?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif ?>
