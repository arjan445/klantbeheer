<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Abonnementen</h3>
    <div class="d-flex gap-2">
        <form class="d-flex gap-2" method="get">
            <select name="soort" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Alle soorten</option>
                <?php foreach (service_types() as $k => [$label]) : ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach ?>
            </select>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actief</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Opgezegd</option>
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Alles</option>
            </select>
        </form>
        <?php if (user_can('billing.manage')) : ?>
            <a href="<?= site_url('abonnementen/nieuw') ?>" class="btn btn-primary text-nowrap"><i class="bi bi-plus-lg"></i> Nieuw abonnement</a>
        <?php endif ?>
    </div>
</div>
<div class="card"><div class="card-body">
    <?= view('subscriptions/_table', ['subscriptions' => $subscriptions, 'showCustomer' => true]) ?>
</div></div>
