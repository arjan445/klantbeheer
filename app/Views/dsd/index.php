<?php
use App\Controllers\DsdLicenses;
use App\Libraries\Dsd;
$manage = user_can('billing.manage');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Licenties (DSD Europe)</h3>
        <div class="text-muted small">Wat er bij DSD loopt, naast wat je factureert.<?php if ($lastSync) : ?> Laatst opgehaald: <?= date('d-m-Y H:i', strtotime($lastSync)) ?>.<?php endif ?></div>
    </div>
    <?php if ($manage) : ?>
    <form method="post" action="<?= site_url('dsd/ophalen') ?>"><?= csrf_field() ?>
        <button class="btn btn-primary" <?= $dsd->configured() ? '' : 'disabled' ?>><i class="bi bi-arrow-repeat"></i> Ophalen uit DSD</button>
    </form>
    <?php endif ?>
</div>

<?php if (! $dsd->configured()) : ?>
<div class="card"><div class="card-body">
    <h5>DSD koppelen</h5>
    <ol class="mb-0">
        <li>Log in bij DSD en ga naar <a href="https://www.dsdeurope.com/shop/users/settings#api-keys" target="_blank" rel="noopener">Instellingen › API Keys</a>. Maak daar een sleutel aan.</li>
        <li>Zet die in <code>.env</code> op de server: <code>dsd.apiKey = …</code></li>
        <li>Kom hier terug en klik op <strong>Ophalen uit DSD</strong>.</li>
    </ol>
</div></div>
<?php else : ?>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="<?= site_url('dsd') ?>" class="btn btn-sm <?= $filter ? 'btn-outline-secondary' : 'btn-secondary' ?>">Alle <span class="badge text-bg-light"><?= array_sum($counts) ?></span></a>
    <a href="<?= site_url('dsd?filter=issues') ?>" class="btn btn-sm <?= $filter === 'issues' ? 'btn-dark' : 'btn-outline-dark' ?>">Aandacht nodig <span class="badge text-bg-light"><?= ($counts['under'] ?? 0) + ($counts['over'] ?? 0) + ($counts['no_sub'] ?? 0) + ($counts['no_customer'] ?? 0) ?></span></a>
    <?php foreach (DsdLicenses::CHECKS as $key => [$label, $color]) : ?>
        <?php if (! empty($counts[$key])) : ?>
        <a href="<?= site_url('dsd?filter=' . $key) ?>" class="btn btn-sm <?= $filter === $key ? 'btn-' . $color : 'btn-outline-' . $color ?>"><?= esc($label) ?> <span class="badge text-bg-light"><?= (int) $counts[$key] ?></span></a>
        <?php endif ?>
    <?php endforeach ?>
</div>

<?php if ($groups === []) : ?>
    <div class="card"><div class="card-body text-muted"><?= $lastSync ? 'Niets te tonen met dit filter.' : 'Nog niets opgehaald. Klik op Ophalen uit DSD.' ?></div></div>
<?php endif ?>

<?php foreach ($groups as $g) : ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-building"></i> <?= esc($g['name']) ?>
            <?php if ($g['client']) : ?><span class="small text-muted fw-normal">· DSD-eindklant <?= esc($g['client']) ?></span><?php endif ?></span>
        <?php if ($g['client'] && $manage) : ?>
        <form method="post" action="<?= site_url('dsd/klant') ?>" class="d-flex gap-2 align-items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="dsd_client_id" value="<?= esc($g['client'], 'attr') ?>">
            <input type="hidden" name="client_name" value="<?= esc($g['name'], 'attr') ?>">
            <span class="small fw-normal text-muted">Klant:</span>
            <select name="customer_id" class="form-select form-select-sm" style="min-width: 220px" onchange="this.form.submit()">
                <option value="">— niet gekoppeld —</option>
                <option value="new">+ Nieuwe klant "<?= esc($g['name']) ?>"</option>
                <?php foreach ($customers as $c) : ?><option value="<?= $c['id'] ?>" <?= $g['customer'] && (int) $g['customer']['id'] === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach ?>
            </select>
        </form>
        <?php elseif ($g['customer']) : ?>
            <a href="<?= site_url('klanten/' . $g['customer']['id']) ?>"><?= esc($g['customer']['name']) ?></a>
        <?php endif ?>
    </div>
    <div class="card-body p-0">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Product (DSD)</th><th class="text-end">Bij DSD</th><th class="text-end">Inkoop</th><th title="Verlengdatum bij DSD; staat los van jouw factuurdatum">Verlengt bij DSD</th><th>Gekoppeld abonnement</th><th class="text-end">Gefactureerd</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($g['items'] as $i) : ?>
            <?php [$label, $color] = DsdLicenses::CHECKS[$i['check']]; ?>
            <tr class="<?= $i['check'] === 'under' ? 'row-overdue' : '' ?>">
                <td><?= esc($i['product_name']) ?><div class="small text-muted mono"><?= esc($i['sku']) ?><?= $i['sale_reference'] ? ' · ' . esc($i['sale_reference']) : '' ?></div></td>
                <td class="text-end text-nowrap fw-semibold"><?= $i['quantity'] !== null ? (int) $i['quantity'] : '?' ?> <span class="small text-muted fw-normal"><?= esc($i['unit']) ?></span></td>
                <td class="text-end text-nowrap"><?= $i['price'] !== null ? euro($i['price']) . '<span class="small text-muted"> / ' . esc(Dsd::periodLabel($i['invoicing_period'])) . '</span>' : '—' ?></td>
                <td class="text-nowrap small"><?= datum($i['renewal_date']) ?><?php if ($i['commitment_term']) : ?><div class="text-muted">looptijd <?= esc(Dsd::periodLabel($i['commitment_term'])) ?></div><?php endif ?></td>
                <td>
                    <?php foreach ($i['subs'] as $s) : ?>
                        <div class="small"><a href="<?= site_url('abonnementen/' . $s['id'] . '/bewerken') ?>"><?= esc($s['service_name']) ?></a> <?= euro($s['eff_price']) ?>/<?= esc(interval_label((int) $s['interval_months'])) ?>
                            <?php if ($manage) : ?>
                            <form method="post" action="<?= site_url('dsd/ontkoppel') ?>" class="d-inline" onsubmit="return confirm('Koppeling losmaken?')"><?= csrf_field() ?><input type="hidden" name="subscription_id" value="<?= $s['id'] ?>"><button class="btn btn-link btn-sm p-0 text-muted" title="Ontkoppelen"><i class="bi bi-x"></i></button></form>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                    <?php if ($i['subs'] === [] && $i['customer'] && $manage) : ?>
                        <form method="post" action="<?= site_url('dsd/koppel') ?>" class="d-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_key" value="<?= esc($i['item_key'], 'attr') ?>">
                            <select name="target" class="form-select form-select-sm" style="min-width: 220px">
                                <option value="">Kies…</option>
                                <?php if (! empty($subsByCustomer[$i['customer']['id']])) : ?>
                                <optgroup label="Bestaand abonnement koppelen">
                                    <?php foreach ($subsByCustomer[$i['customer']['id']] as $s) : ?>
                                        <option value="sub:<?= $s['id'] ?>"><?= esc($s['service_name']) ?> (<?= aantal($s['quantity']) ?>×)</option>
                                    <?php endforeach ?>
                                </optgroup>
                                <?php endif ?>
                                <optgroup label="Nieuw abonnement aanmaken">
                                    <?php foreach ($services as $sv) : ?>
                                        <option value="service:<?= $sv['id'] ?>"><?= esc($sv['name']) ?> (<?= euro($sv['price']) ?>/<?= esc(interval_label((int) $sv['interval_months'])) ?>)</option>
                                    <?php endforeach ?>
                                </optgroup>
                            </select>
                            <button class="btn btn-sm btn-outline-primary">Koppel</button>
                        </form>
                    <?php elseif ($i['subs'] === []) : ?>
                        <span class="small text-muted"><?= $i['check'] === 'own' ? 'eigen gebruik' : 'koppel eerst de klant' ?></span>
                    <?php endif ?>
                </td>
                <td class="text-end"><?= $i['subs'] ? aantal($i['billed']) : '' ?></td>
                <td><span class="badge text-bg-<?= $color ?>"><?= esc($label) ?></span></td>
                <td class="text-end">
                    <?php if (in_array($i['check'], ['under', 'over'], true) && count($i['subs']) === 1 && $manage) : ?>
                        <form method="post" action="<?= site_url('dsd/aantal') ?>"><?= csrf_field() ?><input type="hidden" name="item_key" value="<?= esc($i['item_key'], 'attr') ?>">
                            <button class="btn btn-sm btn-outline-danger text-nowrap" title="Aantal in het abonnement gelijkzetten aan DSD">Zet op <?= (int) $i['quantity'] ?></button></form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
</div>
<?php endforeach ?>
<?php endif ?>
