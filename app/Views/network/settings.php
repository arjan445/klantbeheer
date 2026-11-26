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

            <hr>
            <div class="fw-semibold small mb-1">Fabrics <span class="text-muted fw-normal">(sites van klanten in een eigen Fabric)</span></div>
            <div class="form-text mt-0 mb-2">Een API-sleutel ziet alleen de sites van één Fabric. Zit een klant in een eigen Fabric, open die Fabric dan op unifi.ui.com, maak daar onder <strong>API</strong> een sleutel aan (als eigenaar van die consoles) en vul hem hier in. De sites en het SD-WAN van die Fabric komen dan mee.</div>
            <?php foreach ($fabrics as $f) : ?>
            <div class="row g-2 align-items-center mb-2">
                <input type="hidden" name="fabric_id[]" value="<?= esc($f['id'], 'attr') ?>">
                <div class="col-5"><input name="fabric_label[]" class="form-control form-control-sm" value="<?= esc($f['label'], 'attr') ?>" placeholder="Naam" aria-label="Naam van de Fabric"></div>
                <div class="col-5"><input name="fabric_key[]" type="password" class="form-control form-control-sm mono" autocomplete="new-password" placeholder="•••••••• (leeg = ongewijzigd)" aria-label="Nieuwe sleutel"></div>
                <div class="col-2"><label class="small text-nowrap"><input type="checkbox" class="form-check-input" name="fabric_del[]" value="<?= esc($f['id'], 'attr') ?>"> wis</label></div>
            </div>
            <?php endforeach ?>
            <div class="row g-2">
                <div class="col-5"><input name="fabric_new_label" class="form-control form-control-sm" placeholder="Naam, bijv. klant" aria-label="Naam nieuwe Fabric"></div>
                <div class="col-7"><input name="fabric_new_key" type="password" class="form-control form-control-sm mono" autocomplete="new-password" placeholder="API-sleutel van die Fabric" aria-label="API-sleutel nieuwe Fabric"></div>
            </div>

            <hr>
            <div class="fw-semibold small mb-1">Eigen controller <span class="text-muted fw-normal">(zelf gehost, met meerdere sites)</span></div>
            <div class="form-text mt-0 mb-2">De UniFi-cloud ziet de apparaten van een zelf gehoste controller wel, maar weet niet bij welke site ze horen. Met een lokaal account op die controller worden ze automatisch per site ingedeeld. Maak hiervoor een apart account met alleen-lezen-rechten, zonder tweestapsverificatie.</div>
            <label class="form-label small mb-1">Adres van de controller</label>
            <input name="ctrl_url" class="form-control" inputmode="url" value="<?= esc($ctrlUrl, 'attr') ?>" placeholder="https://unifi.jouwdomein.nl:8443">
            <div class="row g-2 mt-1">
                <div class="col-6"><label class="form-label small mb-1">Gebruikersnaam</label>
                    <input name="ctrl_user" class="form-control" autocomplete="off" value="<?= esc($ctrlUser, 'attr') ?>"></div>
                <div class="col-6"><label class="form-label small mb-1">Wachtwoord <?= $ctrlSet ? '<span class="badge text-bg-success">ingesteld</span>' : '' ?></label>
                    <input name="ctrl_pass" type="password" class="form-control" autocomplete="new-password" placeholder="<?= $ctrlSet ? '•••••••• (leeg = ongewijzigd)' : '' ?>"></div>
            </div>
            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="ctrl_insecure" value="1" id="ci" <?= $ctrlInsecure ? 'checked' : '' ?>><label class="form-check-label small" for="ci">Certificaat niet controleren (alleen als de controller een eigen, niet-officieel certificaat heeft)</label></div>
            <?php if ($ctrlSet) : ?><div class="form-check"><input class="form-check-input" type="checkbox" name="ctrl_clear" value="1" id="cc"><label class="form-check-label small" for="cc">Wachtwoord wissen</label></div><?php endif ?>
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
<div class="card mt-3" id="meldingen"><div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-envelope-exclamation"></i> Storingsmeldingen aan klanten</span>
    <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" name="alerts_enabled" value="1" id="al-on" <?= $alerts['enabled'] ? 'checked' : '' ?>><label class="form-check-label small" for="al-on">Aan</label></div>
</div><div class="card-body">
    <p class="small text-muted">Is een bewaakt apparaat langer dan de wachttijd offline, dan krijg <strong>jij altijd</strong> een mail. De klant krijgt er ook een als "Klanten mailen" aan staat en die klant hieronder is aangevinkt. Kort, met logo en (optioneel) een netwerkoverzicht als PDF zonder IP/MAC. Per storing één mail.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label small mb-1">Mijn adres (krijgt altijd een mail)</label>
            <input name="alerts_testTo" class="form-control" value="<?= esc($alerts['testTo'], 'attr') ?>" placeholder="<?= esc(implode(', ', $alertAdmins) ?: 'jij@bedrijf.nl', 'attr') ?>">
            <div class="form-text">Leeg = de beheerders (<?= esc(implode(', ', $alertAdmins) ?: 'geen') ?>).</div>
            <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" role="switch" name="alerts_mode" value="live" id="al-live" <?= $alerts['mode'] === 'live' ? 'checked' : '' ?>><label class="form-check-label" for="al-live"><strong>Klanten mailen</strong> (de aangevinkte klanten)</label></div>
        </div>
        <div class="col-md-6">
            <div class="row g-2">
                <div class="col-6"><label class="form-label small mb-1">Wachttijd (minuten)</label><input name="alerts_delay" type="number" min="5" max="1440" class="form-control" value="<?= (int) $alerts['delay'] ?>"></div>
                <div class="col-6"><label class="form-label small mb-1">Telefoon in de mail</label><input name="alerts_phone" class="form-control" value="<?= esc($alerts['phone'], 'attr') ?>" placeholder="06 ..."></div>
                <div class="col-12"><label class="form-label small mb-1">Antwoorden naar</label><input name="alerts_replyTo" type="email" class="form-control" value="<?= esc($alerts['replyTo'], 'attr') ?>" placeholder="support@jouwdomein.nl"></div>
            </div>
            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="alerts_pdf" value="1" id="al-pdf" <?= $alerts['pdf'] ? 'checked' : '' ?>><label class="form-check-label" for="al-pdf">Netwerkoverzicht (PDF) meesturen</label></div>
        </div>
    </div>
    <div class="row g-2 mt-2 align-items-end">
        <div class="col-12 small fw-semibold">Wanneer mag de klant gemaild worden?</div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Vanaf</label><input type="time" name="alerts_sendFrom" class="form-control" value="<?= esc($alerts['sendFrom'], 'attr') ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Tot</label><input type="time" name="alerts_sendUntil" class="form-control" value="<?= esc($alerts['sendUntil'], 'attr') ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">'s Nachts vanaf</label><input type="time" name="alerts_nightFrom" class="form-control" value="<?= esc($alerts['nightFrom'], 'attr') ?>"></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">dan pas na (min)</label><input type="number" min="5" max="1440" name="alerts_nightDelay" class="form-control" value="<?= (int) $alerts['nightDelay'] ?>"></div>
        <div class="col-12 form-text mt-1">Buiten deze tijden krijgt de klant niets; speelt de storing bij het begin van de verzendtijd nog, dan gaat de mail alsnog. Overdag ben jij bereikbaar.</div>
        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="alerts_ownWindow" value="1" id="al-own" <?= $alerts['ownWindow'] ? 'checked' : '' ?>><label class="form-check-label" for="al-own">Deze tijden ook voor mijn eigen mails</label></div></div>
    </div>
    <label class="form-label small mb-1 mt-3">Welke klanten krijgen een mail?</label>
    <div class="row g-1 alert-customers">
        <?php foreach ($alertCustomers as $c) : ?>
            <input type="hidden" name="alerts_shown[]" value="<?= (int) $c['id'] ?>">
            <div class="col-sm-6 col-lg-4"><div class="form-check">
                <input class="form-check-input" type="checkbox" name="alerts_customers[]" value="<?= (int) $c['id'] ?>" id="alc<?= (int) $c['id'] ?>" <?= $c['alert_mail'] ? 'checked' : '' ?> <?= $c['email'] ? '' : 'disabled' ?>>
                <label class="form-check-label" for="alc<?= (int) $c['id'] ?>"><?= esc($c['name']) ?><?= $c['email'] ? '' : ' <span class="text-muted small">(geen e-mail)</span>' ?></label>
            </div></div>
        <?php endforeach ?>
        <?php if ($alertCustomers === []) : ?><div class="small text-muted">Nog geen klanten met een gekoppelde netwerksite.</div><?php endif ?>
    </div>
    <?php if ($alertLast) : ?>
        <div class="small text-muted mt-3"><strong>Laatst verstuurd</strong> (<?= esc(\App\Libraries\Network::ago($alertLast['at'])) ?> geleden): <?= esc(implode(' · ', $alertLast['notes'])) ?></div>
    <?php endif ?>
</div></div>

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

<div class="card mt-3"><div class="card-header">Storingsmail bekijken of testen</div><div class="card-body">
    <p class="small text-muted mb-2">De versie die de klant krijgt, met wat nu offline is (of anders een voorbeeld). De testmail gaat naar jou.</p>
    <form method="post" action="<?= site_url('beheer/netwerk/melding-test') ?>" class="d-flex gap-2 flex-wrap align-items-center"><?= csrf_field() ?>
        <select name="klant" id="al-klant" class="form-select" style="max-width: 320px" required>
            <?php foreach ($alertCustomers as $c) : ?><option value="<?= $c['id'] ?>"><?= esc($c['name']) ?><?= $c['email'] ? '' : ' (geen e-mail)' ?></option><?php endforeach ?>
        </select>
        <a class="btn btn-outline-secondary" target="_blank" href="#" onclick="this.href='<?= site_url('beheer/netwerk/melding-voorbeeld') ?>?klant=' + document.getElementById('al-klant').value"><i class="bi bi-eye"></i> Voorbeeld</a>
        <a class="btn btn-outline-secondary" target="_blank" href="#" onclick="this.href='<?= site_url('beheer/netwerk/melding-voorbeeld') ?>?pdf=1&klant=' + document.getElementById('al-klant').value"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <button class="btn btn-primary"><i class="bi bi-send"></i> Testmail versturen</button>
    </form>
</div></div>
