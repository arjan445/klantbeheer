<?php use App\Libraries\Network; ?>
<h3 class="mb-3">Netwerk: UniFi &amp; UISP</h3>
<?php if (! $secure) : ?>
    <div class="alert alert-warning small">Er is geen <code>encryption.key</code>: de API-sleutels worden onversleuteld in de database opgeslagen.</div>
<?php endif ?>

<form method="post" autocomplete="off"><?= csrf_field() ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">UniFi (Site Manager)</div><div class="card-body">
            <label class="form-label">API-sleutel <?= $unifiSet ? '<span class="badge text-bg-success">ingesteld</span>' : '' ?></label>
            <input name="unifi_apiKey" type="password" class="form-control mono" autocomplete="new-password" placeholder="<?= $unifiSet ? '•••••••• (leeg = ongewijzigd)' : '' ?>">
            <?php if ($unifiSet) : ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="unifi_clear" value="1" id="uc"><label class="form-check-label small" for="uc">Sleutel wissen</label></div><?php endif ?>
            <div class="form-text">Maak de sleutel aan op <strong>unifi.ui.com › API</strong> (Site Manager). Je ziet dan alle consoles en sites van je UI-account, met per apparaat MAC, IP, model, firmware en online status. Bij UniFi is het serienummer gelijk aan het MAC-adres.</div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header">UISP</div><div class="card-body">
            <label class="form-label">Adres van je UISP-controller</label>
            <input name="uisp_url" class="form-control" inputmode="url" value="<?= esc($uispUrl, 'attr') ?>" placeholder="https://uisp.jouwdomein.nl">
            <label class="form-label mt-3">API-token <?= $uispSet ? '<span class="badge text-bg-success">ingesteld</span>' : '' ?></label>
            <input name="uisp_token" type="password" class="form-control mono" autocomplete="new-password" placeholder="<?= $uispSet ? '•••••••• (leeg = ongewijzigd)' : '' ?>">
            <?php if ($uispSet) : ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="uisp_clear" value="1" id="xc"><label class="form-check-label small" for="xc">Token wissen</label></div><?php endif ?>
            <div class="form-text">In UISP: <strong>Settings › Users › API tokens</strong>. Alleen-lezen is genoeg.</div>
        </div></div>
    </div>
</div>
<div class="d-flex gap-2 mt-3">
    <button class="btn btn-primary" name="test" value=""><i class="bi bi-check2"></i> Opslaan</button>
    <button class="btn btn-outline-primary" name="test" value="1"><i class="bi bi-plug"></i> Opslaan &amp; verbinding testen</button>
</div>
</form>

<div class="card mt-4"><div class="card-header">Automatisch ophalen</div><div class="card-body small">
    <p>Laat de server elke 5 minuten de status ophalen met een cronjob (Plesk › Geplande taken):</p>
    <pre class="bg-light p-2 rounded mb-2">*/5 * * * *  cd /pad/naar/klantbeheer &amp;&amp; php spark klantbeheer:netwerk</pre>
    <p class="mb-0">Laatste synchronisatie: <strong><?= $status['lastSync'] ? esc(datum($status['lastSync']) . ' ' . date('H:i', strtotime($status['lastSync']))) . ' (' . esc(Network::ago($status['lastSync'])) . ')' : 'nog nooit' ?></strong>.
    <?php if ($status['errors'] !== []) : ?><span class="text-danger"><?= esc(implode(' · ', $status['errors'])) ?></span><?php endif ?></p>
</div></div>
