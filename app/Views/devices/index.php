<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Apparaten &amp; wachtwoorden</h3>
    <div class="d-flex gap-2">
        <form method="get">
            <select name="soort" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Alle soorten</option>
                <?php foreach (device_types() as $k => [$label]) : ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
            </select>
        </form>
        <?php if (user_can('vault.manage')) : ?>
            <a href="<?= site_url('apparaten/nieuw') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Toevoegen</a>
        <?php endif ?>
    </div>
</div>
<?php if (! $ready) : ?>
    <div class="alert alert-danger"><i class="bi bi-shield-exclamation"></i> Er is geen <code>encryption.key</code> ingesteld. Wachtwoorden kunnen niet worden opgeslagen of getoond. Draai <code>php spark key:generate</code>.</div>
<?php endif ?>
<div class="card"><div class="card-body">
    <?= view('devices/_table', ['devices' => $devices, 'showCustomer' => true]) ?>
</div></div>
