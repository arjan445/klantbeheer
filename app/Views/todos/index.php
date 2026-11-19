<?php $webcal = preg_replace('#^https?://#', 'webcal://', $feedUrl); ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Taken</h3>
    <a href="#agenda" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-plus"></i> In je agenda</a>
</div>

<div class="card mb-3"><div class="card-body">
    <?= view('todos/_quickadd', ['customerId' => null, 'customers' => $customers]) ?>
</div></div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Open <span class="badge text-bg-light border"><?= count($open) ?></span></span>
        <a href="<?= site_url('taken' . ($showDone ? '' : '?afgerond=1')) ?>" class="small"><?= $showDone ? 'Afgeronde verbergen' : 'Afgeronde tonen' ?></a>
    </div>
    <div class="card-body py-1 todo-hide-done"><?= view('todos/_list', ['todos' => $open]) ?></div>
</div>

<?php if ($showDone) : ?>
<div class="card mb-3">
    <div class="card-header">Afgerond (laatste 100)</div>
    <div class="card-body py-1"><?= view('todos/_list', ['todos' => $done, 'empty' => 'Nog niets afgerond.']) ?></div>
</div>
<?php endif ?>

<div class="card" id="agenda">
    <div class="card-header"><i class="bi bi-calendar-plus"></i> Taken in je agenda (iCal)</div>
    <div class="card-body small">
        <p>Abonneer je agenda op deze link. Taken met een datum verschijnen dan vanzelf in je agenda; afgevinkte taken verdwijnen weer. Taken die te laat zijn, schuiven mee naar vandaag (met ⚠).</p>
        <div class="input-group mb-2">
            <input class="form-control form-control-sm" id="feed" value="<?= esc($feedUrl, 'attr') ?>" readonly>
            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('feed').value); this.innerHTML='<i class=&quot;bi bi-check&quot;></i> Gekopieerd'"><i class="bi bi-clipboard"></i> Kopiëren</button>
        </div>
        <a href="<?= esc($webcal, 'attr') ?>" class="btn btn-sm btn-primary mb-3"><i class="bi bi-calendar-plus"></i> Toevoegen aan agenda (iPhone / Mac / Outlook)</a>
        <ul class="mb-3">
            <li><strong>iPhone:</strong> tik op de knop hierboven op je telefoon, of Instellingen → Agenda → Accounts → Voeg account toe → Andere → Voeg agenda-abonnement toe → link plakken.</li>
            <li><strong>Google Agenda:</strong> op de computer: Andere agenda's → + → Via URL → link plakken. Google ververst dit zelf, meestal een paar keer per dag.</li>
            <li><strong>Outlook / Microsoft 365:</strong> Agenda toevoegen → Abonneren vanaf internet → link plakken.</li>
        </ul>
        <p class="text-muted mb-2">De link werkt zonder inloggen. Deel hem dus niet; is hij uitgelekt, maak dan een nieuwe.</p>
        <form method="post" action="<?= site_url('taken/agenda-link') ?>" onsubmit="return confirm('Nieuwe link maken? De oude stopt dan met werken.')"><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-repeat"></i> Nieuwe link maken</button>
        </form>
    </div>
</div>
<?= view('todos/_script') ?>
