<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Kostprijzen</h3>
        <div class="text-muted small">Inkoop uit Moneybird (per leverancier, zoekwoord of categorie) verdeeld over je abonnementen.</div>
    </div>
    <?php if (user_can('billing.manage')) : ?>
    <div class="d-flex gap-2">
        <?php if (user_can("billing.manage")) : ?>
        <form method="post" action="<?= site_url('kostprijzen/ophalen') ?>"><?= csrf_field() ?>
            <button class="btn btn-outline-secondary" <?= $mb->configured() ? '' : 'disabled' ?>><i class="bi bi-arrow-repeat"></i> Ophalen uit Moneybird</button>
        </form>
        <?php endif ?>
        <a href="<?= site_url('kostprijzen/nieuw') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nieuwe kostenpool</a>
    </div>
    <?php endif ?>
</div>

<?php if ($pools === []) : ?>
<div class="card"><div class="card-body">
    <h5>Hoe werkt het?</h5>
    <ol class="mb-0">
        <li>Maak een kostenpool, bijvoorbeeld <strong>Hostingplatform</strong>.</li>
        <li>Klik op <em>Ophalen uit Moneybird</em>: alle inkoopfacturen en bonnetjes van het afgelopen jaar worden opgehaald.</li>
        <li>Kies welke inkoop erbij hoort: per <strong>leverancier</strong> (bijv. Hetzner, Plesk), via <strong>zoekwoorden</strong> (bijv. "vps", "imunify") en/of per <strong>categorie</strong>. Per regel kun je nog aan- of uitvinken.</li>
        <li>Koppel de diensten die van dat platform gebruikmaken, bijvoorbeeld <em>Webhosting Basis</em> en <em>Webhosting Pro</em> (met gewicht 2).</li>
        <li>De app telt de inkoopfacturen en bonnetjes van de laatste 12 maanden op en deelt dat door het aantal actieve abonnementen. Dat bedrag wordt de inkoopprijs van de dienst, en de marges kloppen vanzelf.</li>
    </ol>
</div></div>
<?php endif ?>

<?php foreach ($pools as $p) : ?>
    <?php $c = $calc[$p['id']] ?? null; $bd = json_decode((string) $p['breakdown'], true) ?: []; ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-diagram-3"></i> <?= esc($p['name']) ?></span>
            <span>
                <span class="fs-5"><?= euro($c['yearly'] ?? 0) ?></span> <span class="small text-muted fw-normal">kosten per jaar</span>
                <?php if (user_can('billing.manage')) : ?><a href="<?= site_url('kostprijzen/' . $p['id'] . '/bewerken') ?>" class="btn btn-sm btn-outline-secondary ms-2"><i class="bi bi-pencil"></i></a><?php endif ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h6 class="text-muted">Opbouw</h6>
                    <table class="table table-sm mb-2">
                        <?php foreach ($bd['suppliers'] ?? [] as $name => $amount) : ?>
                            <tr><td><?= esc($name) ?></td><td class="text-end"><?= euro($amount) ?></td></tr>
                        <?php endforeach ?>
                        <tr class="fw-semibold"><td>Totaal Moneybird <span class="small text-muted fw-normal"><?= datum($p['synced_from']) ?> t/m <?= datum($p['synced_to']) ?></span></td><td class="text-end"><?= euro($p['synced_amount'] ?? 0) ?></td></tr>
                        <?php if ((int) $p['lookback_months'] !== 12) : ?>
                            <tr><td class="small text-muted">Omgerekend naar 12 maanden (×12/<?= (int) $p['lookback_months'] ?>)</td><td class="text-end small"><?= euro((float) $p['synced_amount'] * 12 / max(1, (int) $p['lookback_months'])) ?></td></tr>
                        <?php endif ?>
                        <?php if ((float) $p['allocation_pct'] < 100) : ?>
                            <tr><td class="small text-muted">Toegerekend aan deze diensten: <?= aantal($p['allocation_pct']) ?>%</td><td></td></tr>
                        <?php endif ?>
                        <?php if ((float) $p['extra_yearly'] > 0) : ?>
                            <tr><td>Extra kosten (handmatig)</td><td class="text-end"><?= euro($p['extra_yearly']) ?></td></tr>
                        <?php endif ?>
                    </table>
                    <?php if (user_can('billing.manage')) : ?>
                        <a href="<?= site_url('kostprijzen/' . $p['id'] . '/regels') ?>" class="btn btn-sm btn-outline-primary mb-2"><i class="bi bi-list-check"></i> Inkoopregels bekijken / aanpassen</a>
                    <?php endif ?>
                    <div class="small text-muted">
                        <?php if ($p['synced_at']) : ?>Laatst opgehaald: <?= date('d-m-Y H:i', strtotime($p['synced_at'])) ?><?php else : ?>Nog niet opgehaald uit Moneybird.<?php endif ?>
                    </div>
                </div>
                <div class="col-lg-7">
                    <h6 class="text-muted">Kostprijs per dienst</h6>
                    <?php if (($c['services'] ?? []) === []) : ?>
                        <p class="text-muted small">Nog geen diensten gekoppeld.</p>
                    <?php else : ?>
                    <table class="table table-sm">
                        <thead><tr><th>Dienst</th><th class="text-end">Actief</th><th class="text-end">Gewicht</th><th class="text-end">Kostprijs</th><th class="text-end">Verkoop</th><th class="text-end">Marge</th></tr></thead>
                        <?php foreach ($c['services'] as $s) : ?>
                            <?php $per = interval_label((int) $s['interval_months']); ?>
                            <tr>
                                <td><?= esc($s['name']) ?></td>
                                <td class="text-end"><?= aantal($s['units']) ?></td>
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
                        <?php $sell = array_sum(array_map(static fn ($s) => $s['price'] * 12 / max(1, (int) $s['interval_months']) * $s['units'], $c['services'])); ?>
                        <div class="small">Omzet uit deze diensten: <strong><?= euro($sell) ?></strong> per jaar · kosten <strong><?= euro($c['yearly']) ?></strong> · resultaat <strong class="<?= $sell < $c['yearly'] ? 'text-danger' : 'text-success' ?>"><?= euro($sell - $c['yearly']) ?></strong></div>
                    <?php endif ?>
                    <?php endif ?>
                </div>
            </div>

        </div>
    </div>
<?php endforeach ?>
