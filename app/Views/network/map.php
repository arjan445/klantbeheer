<?php use App\Libraries\Network; ?>
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('/') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <h3 class="mb-0"><i class="bi bi-geo-alt"></i> Klantenkaart</h3>
    </div>
    <?php if ($status && $status['lastSync']) : ?><span class="small text-muted">netwerk bijgewerkt <?= esc(Network::ago($status['lastSync'])) ?><?= Network::ago($status['lastSync']) === 'net' ? '' : ' geleden' ?></span><?php endif ?>
</div>
<?php if ($status && $status['stale']) : ?>
    <div class="alert alert-warning py-2 small"><i class="bi bi-clock-history"></i> De netwerkstatus is al een tijd niet bijgewerkt: de kleuren kunnen achterlopen.</div>
<?php endif ?>
<?= view('network/_map', ['map' => $map, 'full' => true]) ?>
<?php if ($map['notFound'] !== []) : ?>
<div class="card" id="cmap-notfound"><div class="card-body small">
    <div class="fw-semibold mb-1"><i class="bi bi-geo"></i> Adres niet gevonden</div>
    <div class="text-muted mb-2">Controleer straat, huisnummer, postcode en plaats. Na een wijziging wordt het adres vanzelf opnieuw opgezocht.</div>
    <ul class="mb-0"><?php foreach ($map['notFound'] as $n) : ?><li><a href="<?= site_url('klanten/' . $n['cid']) ?>"><?= esc($n['name']) ?></a></li><?php endforeach ?></ul>
</div></div>
<?php endif ?>
