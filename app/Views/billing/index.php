<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0">Facturatie</h3>
        <div class="text-muted small">Abonnementen waarvan de volgende periode binnen <?= (int) $days ?> dagen begint (of al begonnen is).</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <form method="get" class="d-flex gap-2 align-items-center">
            <label class="small text-muted text-nowrap">Dagen vooruit</label>
            <input type="number" name="dagen" value="<?= (int) $days ?>" min="0" max="366" class="form-control form-control-sm" style="width: 90px" onchange="this.form.submit()">
        </form>
        <a href="<?= site_url('facturatie/historie') ?>" class="btn btn-outline-secondary btn-sm text-nowrap"><i class="bi bi-clock-history"></i> Historie</a>
    </div>
</div>

<?php if ($url = session()->getFlashdata('invoice_url')) : ?>
    <div class="alert alert-info"><a href="<?= esc($url, 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open de conceptfactuur in Moneybird</a></div>
<?php endif ?>

<?php if (! $mb->configured()) : ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Moneybird is nog niet ingesteld (zie <code>.env</code>). Je kunt wel handmatig als gefactureerd markeren.</div>
<?php endif ?>

<?php if ($byCustomer === []) : ?>
    <div class="card"><div class="card-body text-muted"><i class="bi bi-check2-circle"></i> Er hoeft nu niets gefactureerd te worden.</div></div>
<?php endif ?>

<?php $today = date('Y-m-d'); ?>
<?php foreach ($byCustomer as $customerId => $group) : ?>
    <?php $total = array_sum(array_map(static fn ($s) => $s['eff_price'] * $s['quantity'], $group['subs'])); ?>
    <form method="post" action="<?= site_url('facturatie/factureren') ?>" class="card mb-3"
          onsubmit="return confirm(this.method_choice === 'manual' ? 'Markeren als gefactureerd zonder Moneybird-factuur?' : 'Conceptfactuur aanmaken in Moneybird?');">
        <?= csrf_field() ?>
        <input type="hidden" name="customer_id" value="<?= (int) $customerId ?>">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="<?= site_url('klanten/' . $customerId) ?>"><?= esc($group['name']) ?></a>
            <span><?= euro($total) ?> <span class="text-muted small fw-normal">excl. btw</span></span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr>
                    <th style="width: 30px"><input type="checkbox" class="form-check-input" checked data-check-all="#f<?= (int) $customerId ?> .sub-check"></th>
                    <th>Factuurregel</th><th class="text-end">Aantal</th><th class="text-end">Prijs</th><th class="text-end">Totaal</th>
                </tr></thead>
                <tbody id="f<?= (int) $customerId ?>">
                <?php foreach ($group['subs'] as $s) : ?>
                    <tr class="<?= $s['next_invoice_date'] < $today ? 'row-overdue' : '' ?>">
                        <td><input type="checkbox" class="form-check-input sub-check" name="subs[]" value="<?= (int) $s['id'] ?>" checked></td>
                        <td><?= esc(\App\Libraries\Billing::lineDescription($s)) ?>
                            <?php if ($s['next_invoice_date'] < $today) : ?><span class="badge text-bg-danger">over datum</span><?php endif ?>
                            <?php if (user_can('billing.manage')) : ?><a href="<?= site_url('abonnementen/' . $s['id'] . '/bewerken') ?>" class="small ms-1"><i class="bi bi-pencil"></i></a><?php endif ?>
                        </td>
                        <td class="text-end"><?= aantal($s['quantity']) ?></td>
                        <td class="text-end"><?= euro($s['eff_price']) ?></td>
                        <td class="text-end"><?= euro($s['eff_price'] * $s['quantity']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php if (user_can('billing.manage')) : ?>
        <div class="card-footer bg-white d-flex gap-2 justify-content-end flex-wrap">
            <?php if (! $group['moneybird']) : ?>
                <span class="text-warning small me-auto align-self-center"><i class="bi bi-exclamation-circle"></i> Klant niet gekoppeld aan Moneybird. <a href="<?= site_url('klanten/' . $customerId . '/bewerken') ?>">Koppelen</a></span>
            <?php endif ?>
            <button name="method" value="manual" class="btn btn-sm btn-outline-secondary" onclick="this.form.method_choice='manual'">Markeer als gefactureerd</button>
            <button name="method" value="moneybird" class="btn btn-sm btn-primary" onclick="this.form.method_choice='moneybird'" <?= $group['moneybird'] && $mb->configured() ? '' : 'disabled' ?>><i class="bi bi-receipt"></i> Conceptfactuur in Moneybird</button>
        </div>
        <?php endif ?>
    </form>
<?php endforeach ?>
