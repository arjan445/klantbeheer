<?php
/**
 * @var array  $s
 * @var bool   $smtpSet
 * @var bool   $graphSet
 * @var string $me
 * @var bool   $secure
 * @var string $fallback
 */
use App\Libraries\Mailer;

$t = $s['transport'];
?>
<h3 class="mb-3">E-mail</h3>
<p class="text-muted small">Hoe klantbeheer mail verstuurt: storingsmails naar klanten en jezelf, en de herinnering voor de facturatie.</p>
<?php if (! $secure) : ?>
    <div class="alert alert-warning small">Er is geen <code>encryption.key</code>: wachtwoorden en geheimen worden onversleuteld opgeslagen.</div>
<?php endif ?>

<div class="row g-3">
    <div class="col-lg-7">
<form method="post" autocomplete="off"><?= csrf_field() ?>
        <div class="card mb-3"><div class="card-body">
            <label class="form-label fw-semibold">Versturen via</label>
            <div class="mail-choice">
                <?php foreach (Mailer::TRANSPORTS as $k => $label) : ?>
                <label class="mail-opt">
                    <input type="radio" name="transport" value="<?= $k ?>" class="form-check-input" <?= $t === $k ? 'checked' : '' ?>>
                    <span><strong><?= esc($label) ?></strong><br><span class="small text-muted"><?= match ($k) {
                        'php'   => 'Zoals het nu gaat. Komt vaak in de spam of niet aan.',
                        'smtp'  => 'Een eigen mailserver, met gebruikersnaam en wachtwoord.',
                        'graph' => 'Aanbevolen. Verstuurt vanuit je Microsoft 365-mailbox; komt netjes aan en staat in Verzonden items.',
                    } ?></span></span>
                </label>
                <?php endforeach ?>
            </div>

            <div class="row g-2 mt-2">
                <div class="col-sm-7"><label class="form-label small mb-1">Afzenderadres</label>
                    <input name="from" type="email" class="form-control" value="<?= esc($s['from'], 'attr') ?>" placeholder="<?= esc($fallback ?: 'info@jouwdomein.nl', 'attr') ?>"></div>
                <div class="col-sm-5"><label class="form-label small mb-1">Afzendernaam</label>
                    <input name="fromName" class="form-control" value="<?= esc($s['fromName'], 'attr') ?>"></div>
            </div>
        </div></div>

        <div class="card mb-3 mail-pane" data-for="graph"><div class="card-header"><i class="bi bi-microsoft"></i> Office 365 (Microsoft Graph)</div><div class="card-body">
            <div class="row g-2">
                <div class="col-12"><label class="form-label small mb-1">Mailbox waaruit verstuurd wordt</label>
                    <input name="graphSender" type="email" class="form-control" value="<?= esc($s['graphSender'], 'attr') ?>" placeholder="noreply@jouwdomein.nl">
                    <div class="form-text">Een bestaande mailbox (of gedeelde mailbox) in je Microsoft 365. Laat het afzenderadres hierboven leeg of gelijk, tenzij deze mailbox "Verzenden als" mag voor dat adres.</div></div>
                <div class="col-md-6"><label class="form-label small mb-1">Tenant-ID <span class="text-muted">(of domein)</span></label>
                    <input name="graphTenant" class="form-control mono" value="<?= esc($s['graphTenant'], 'attr') ?>" placeholder="xxxxxxxx-xxxx-… of jouwdomein.nl"></div>
                <div class="col-md-6"><label class="form-label small mb-1">Toepassings-ID (client-ID)</label>
                    <input name="graphClient" class="form-control mono" value="<?= esc($s['graphClient'], 'attr') ?>" placeholder="xxxxxxxx-xxxx-…"></div>
                <div class="col-12"><label class="form-label small mb-1">Clientgeheim <span class="text-muted">(de Waarde)</span> <?= $graphSet ? '<span class="badge text-bg-success">ingesteld</span>' : '' ?></label>
                    <input name="graphSecret" type="password" class="form-control mono" autocomplete="new-password" placeholder="<?= $graphSet ? '•••••••• (leeg = ongewijzigd)' : '' ?>">
                    <?php if ($graphSet) : ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="graphSecret_clear" value="1" id="gc"><label class="form-check-label small" for="gc">Geheim wissen</label></div><?php endif ?></div>
            </div>
            <details class="mt-3 small">
                <summary class="fw-semibold">Zo maak je de koppeling in Microsoft 365 (5 minuten)</summary>
                <ol class="mt-2 mb-0 ps-3">
                    <li>Ga naar <strong>entra.microsoft.com</strong> › Identiteit › Toepassingen › <strong>App-registraties</strong> › <strong>Nieuwe registratie</strong>. Naam bijvoorbeeld "Klantbeheer mail", verder niets invullen, Registreren.</li>
                    <li>Op het overzicht staan de <strong>Toepassings-ID (client-ID)</strong> en <strong>Map-ID (tenant-ID)</strong>: kopieer ze hierheen.</li>
                    <li><strong>API-machtigingen</strong> › Een machtiging toevoegen › Microsoft Graph › <strong>Toepassingsmachtigingen</strong> › <code>Mail.Send</code>. Klik daarna op <strong>Beheerderstoestemming verlenen</strong>.</li>
                    <li><strong>Certificaten en geheimen</strong> › Nieuw clientgeheim (bijv. 24 maanden). Kopieer meteen de <strong>Waarde</strong> (niet de Geheim-ID) hierheen.</li>
                    <li>Opslaan en een testmail sturen.</li>
                </ol>
                <div class="text-muted mt-2">Tip: de app mag dan uit élke mailbox versturen. Wil je dat beperken tot één mailbox, stel dan in Exchange een <em>RBAC for Applications</em>- of toegangsbeleid in. Het geheim verloopt: zet een herinnering in je agenda.</div>
            </details>
        </div></div>

        <div class="card mb-3 mail-pane" data-for="smtp"><div class="card-header">SMTP-server</div><div class="card-body">
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label small mb-1">Server</label>
                    <input name="smtpHost" class="form-control" value="<?= esc($s['smtpHost'], 'attr') ?>" placeholder="mail.jouwdomein.nl"></div>
                <div class="col-6 col-md-3"><label class="form-label small mb-1">Poort</label>
                    <input name="smtpPort" type="number" class="form-control" value="<?= (int) $s['smtpPort'] ?>"></div>
                <div class="col-6 col-md-3"><label class="form-label small mb-1">Beveiliging</label>
                    <select name="smtpCrypto" class="form-select">
                        <?php foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'Geen'] as $k => $l) : ?><option value="<?= $k ?>" <?= ($s['smtpCrypto'] ?: 'none') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
                    </select></div>
                <div class="col-md-6"><label class="form-label small mb-1">Gebruikersnaam</label>
                    <input name="smtpUser" class="form-control" autocomplete="off" value="<?= esc($s['smtpUser'], 'attr') ?>"></div>
                <div class="col-md-6"><label class="form-label small mb-1">Wachtwoord <?= $smtpSet ? '<span class="badge text-bg-success">ingesteld</span>' : '' ?></label>
                    <input name="smtpPass" type="password" class="form-control" autocomplete="new-password" placeholder="<?= $smtpSet ? '•••••••• (leeg = ongewijzigd)' : '' ?>">
                    <?php if ($smtpSet) : ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="smtpPass_clear" value="1" id="sc"><label class="form-check-label small" for="sc">Wachtwoord wissen</label></div><?php endif ?></div>
            </div>
            <div class="form-text">Office 365 via SMTP (smtp.office365.com) met een wachtwoord werkt vanaf eind 2026 standaard niet meer; kies dan Office 365 hierboven.</div>
        </div></div>

        <button class="btn btn-primary">Opslaan</button>
</form>
    </div>

    <div class="col-lg-5">
        <div class="card"><div class="card-header">Testmail</div><div class="card-body">
            <form method="post" action="<?= site_url('beheer/mail/test') ?>" class="d-flex gap-2"><?= csrf_field() ?>
                <input name="to" type="email" class="form-control" value="<?= esc($me, 'attr') ?>" required aria-label="E-mailadres">
                <button class="btn btn-outline-primary text-nowrap"><i class="bi bi-send"></i> Versturen</button>
            </form>
            <div class="form-text">Sla eerst op; de test gebruikt de opgeslagen instellingen.</div>
        </div></div>
    </div>
</div>

<script>
(function () {
    const panes = document.querySelectorAll('.mail-pane');
    const sync = () => {
        const v = document.querySelector('input[name="transport"]:checked')?.value;
        panes.forEach(p => p.classList.toggle('d-none', p.dataset.for !== v));
    };
    document.querySelectorAll('input[name="transport"]').forEach(r => r.addEventListener('change', sync));
    sync();
})();
</script>
