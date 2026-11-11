<a href="<?= site_url('facturatie') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Facturatie</a>
<h3 class="mb-3">Gefactureerd</h3>
<div class="card"><div class="card-body">
    <?= view('billing/_history', ['lines' => $lines, 'showCustomer' => true, 'mb' => $mb]) ?>
</div></div>
