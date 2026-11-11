<h3 class="mb-3">Kluislogboek</h3>
<p class="text-muted small">Wie heeft wanneer welk wachtwoord bekeken, aangemaakt of gewijzigd (laatste 1000).</p>
<div class="card"><div class="card-body">
<table class="table table-sm datatable">
    <thead><tr><th>Tijdstip</th><th>Gebruiker</th><th>Actie</th><th>Klant</th><th>Apparaat</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($log as $l) : ?>
        <tr>
            <td class="text-nowrap" data-order="<?= esc($l['created_at']) ?>"><?= date('d-m-Y H:i', strtotime($l['created_at'])) ?></td>
            <td><?= esc($l['username'] ?: $l['email']) ?></td>
            <td><?= esc($l['action']) ?></td>
            <td><?php if ($l['customer_id']) : ?><a href="<?= site_url('klanten/' . $l['customer_id']) ?>"><?= esc($l['customer_name']) ?></a><?php endif ?></td>
            <td><?php if ($l['device_name']) : ?><a href="<?= site_url('apparaten/' . $l['device_id']) ?>"><?= esc($l['device_name']) ?></a><?php endif ?></td>
            <td class="mono small"><?= esc($l['ip']) ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
</div></div>
