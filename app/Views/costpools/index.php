<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Kostprijzen</h3>
        <div class="text-muted small">Je vaste kosten per leverancier, verdeeld over je abonnementen.</div>
    </div>
    <?php if (user_can('billing.manage')) : ?>
        <a href="<?= site_url('kostprijzen/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe kostenpool</a>
    <?php endif ?>
</div>

<?php if ($pools === []) : ?>
<div class="card"><div class="card-body">
    <h5>Hoe werkt het?</h5>
    <ol class="mb-0">
        <li>Maak een kostenpool aan, bijvoorbeeld <strong>Hostingplatform</strong>.</li>
        <li>Vul de kosten in per leverancier, bijvoorbeeld <em>Hetzner – VPS – € 40 per maand</em>, <em>Plesk – licentie – € 15 per maand</em> en <em>CloudLinux – Imunify – € 100 per jaar</em>.</li>
        <li>Vink de diensten aan die op dit platform draaien, bijvoorbeeld <em>Webhosting Basis</em> en <em>Webhosting Pro</em> (met gewicht 2).</li>
        <li>De jaarkosten worden verdeeld over het aantal actieve abonnementen. De uitkomst is de kostprijs per stuk, en die wordt automatisch de inkoopprijs van de dienst.</li>
    </ol>
</div></div>
<?php endif ?>

<?php foreach ($pools as $p) : ?>
    <?php $c = $calc[$p['id']] ?? ['yearly' => 0, 'units' => 0, 'services' => []]; ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-diagram-3"></i> <?= esc($p['name']) ?></span>
            <span>
                <span class="fs-5"><?= euro($c['yearly']) ?></span> <span class="small text-muted fw-normal">per jaar · <?= euro($c['yearly'] / 12) ?> per maand</span>
                <?php if (user_can('billing.manage')) : ?><a href="<?= site_url('kostprijzen/' . $p['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary ms-2"><i class="bi bi-pencil"></i></a><?php endif ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h6 class="text-muted">Kosten</h6>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Leverancier</th><th class="text-end">Bedrag</th><th class="text-end">Per jaar</th></tr></thead>
                        <?php foreach ($items[$p['id']] as $i) : ?>
                            <tr>
                                <td><?= esc($i['supplier']) ?><?php if ($i['description']) : ?> <span class="small text-muted">· <?= esc($i['description']) ?></span><?php endif ?></td>
                                <td class="text-end text-nowrap"><?= euro($i['amount']) ?> <span class="small text-muted">/ <?= $i['period'] === 'year' ? 'jaar' : 'maand' ?></span></td>
                                <td class="text-end"><?= euro(\App\Libraries\CostPools::itemYearly($i)) ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($items[$p['id']] === []) : ?><tr><td colspan="3" class="text-muted small">Nog geen kosten ingevuld.</td></tr><?php endif ?>
                        <tr class="fw-semibold"><td>Totaal</td><td></td><td class="text-end"><?= euro($c['yearly']) ?></td></tr>
                    </table>
                    <?php if ($p['notes']) : ?><div class="small text-muted mt-2"><?= nl2br(esc($p['notes'])) ?></div><?php endif ?>
                </div>
                <div class="col-lg-7">
                    <h6 class="text-muted">Kostprijs per dienst</h6>
                    <?php if ($c['services'] === []) : ?>
                        <p class="text-muted small">Nog geen diensten gekoppeld.</p>
                    <?php else : ?>
                    <table class="table table-sm">
                        <thead><tr><th>Dienst</th><th class="text-end">Actief</th><th class="text-end">Gewicht</th><th class="text-end">Kostprijs</th><th class="text-end">Verkoop</th><th class="text-end">Marge</th></tr></thead>
                        <?php foreach ($c['services'] as $s) : ?>
                            <?php $per = interval_label((int) $s['interval_months']); ?>
                            <tr>
                                <td><?= esc($s['name']) ?></td>
                                <td class="text-end"><?= aantal($s['units']) ?><?php if ((float) $s['own_units'] > 0) : ?><div class="small text-muted">waarvan <?= aantal($s['own_units']) ?> eigen</div><?php endif ?></td>
                                <td class="text-end"><?= aantal($s['cost_weight']) ?></td>
                                <td class="text-end text-nowrap"><?= $s['purchase'] !== null ? euro($s['purchase']) . '<span class="small text-muted"> / ' . esc($per) . '</span>' : '<span class="text-muted small">geen abonnementen</span>' ?></td>
                                <td class="text-end"><?= euro($s['price']) ?></td>
                                <td class="text-end <?= $s['purchase'] !== null && $s['price'] < $s['purchase'] ? 'text-danger fw-semibold' : '' ?>">
                                    <?php if ($s['purchase'] !== null) : ?><?= euro($s['price'] - $s['purchase']) ?>
                                        <?php if ($s['price'] > 0) : ?><span class="small text-muted"><?= round(($s['price'] - $s['purchase']) / $s['price'] * 100) ?>%</span><?php endif ?>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </table>
                    <?php if ($c['units'] > 0) : ?>
                        <?php $sell = array_sum(array_map(static fn ($s) => $s['price'] * 12 / max(1, (int) $s['interval_months']) * ($s['units'] - $s['own_units']), $c['services'])); ?>
                        <div class="small">Omzet uit deze diensten: <strong><?= euro($sell) ?></strong> per jaar · kosten <strong><?= euro($c['yearly']) ?></strong> · resultaat <strong class="<?= $sell < $c['yearly'] ? 'text-danger' : 'text-success' ?>"><?= euro($sell - $c['yearly']) ?></strong></div>
                    <?php endif ?>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach ?>
