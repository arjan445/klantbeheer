<?php
/**
 * @var bool        $hasKey
 * @var string|null $newKeyLine
 * @var list<array> $migrations
 * @var list<array> $pending
 * @var string      $token
 * @var array       $tasks
 * @var array       $lastRuns
 */
$step = static fn (bool $done) => $done
    ? '<span class="badge text-bg-success me-2"><i class="bi bi-check-lg"></i> Klaar</span>'
    : '<span class="badge text-bg-warning me-2">Te doen</span>';
$nsLabel = static fn (string $ns) => match (true) {
    str_starts_with($ns, 'App')                     => 'Klantbeheer',
    str_contains($ns, 'Shield')                     => 'Inloggen (Shield)',
    str_contains($ns, 'Settings')                   => 'Instellingen',
    default                                         => $ns,
};
?>
<h3 class="mb-1">Onderhoud</h3>
<p class="text-muted">Wat je normaal met <code>php spark</code> op de server doet, kan hier. Doe de stappen van boven naar beneden.</p>

<div class="card mb-3">
    <div class="card-header">1. Versleutelingssleutel</div>
    <div class="card-body">
        <p><?= $step($hasKey) ?>Hiermee worden de wachtwoorden in de kluis en de API-sleutels versleuteld opgeslagen.</p>
        <?php if (! $hasKey) : ?>
            <ol class="mb-2">
                <li>Open in Plesk <strong>Bestanden</strong> en ga naar de map van klantbeheer (waar <code>.env</code> staat, boven <code>public</code>).</li>
                <li>Open <code>.env</code> en zet deze regel er onderaan bij:</li>
            </ol>
            <div class="input-group mb-2">
                <input type="text" class="form-control mono" id="keyLine" value="<?= esc($newKeyLine, 'attr') ?>" readonly>
                <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('keyLine').value); this.textContent='Gekopieerd';">Kopieer</button>
            </div>
            <ol start="3" class="mb-0"><li>Sla <code>.env</code> op en ververs deze pagina.</li></ol>
            <p class="small mt-2 mb-0">Bewaar de sleutel ook in je wachtwoordkluis. Raak je hem kwijt of verander je hem, dan zijn de opgeslagen wachtwoorden niet meer te lezen. Deel hem met niemand.</p>
        <?php else : ?>
            <p class="mb-0 small">De sleutel staat in <code>.env</code>. Verander hem niet meer, en bewaar hem ook in je wachtwoordkluis.</p>
        <?php endif ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">2. Database bijwerken</div>
    <div class="card-body">
        <p><?= $step($pending === []) ?>Na een update van klantbeheer: voegt nieuwe tabellen en kolommen toe. Bestaande gegevens blijven staan.</p>
        <?php if ($pending !== []) : ?>
            <form action="<?= site_url('beheer/onderhoud/migreren') ?>" method="post" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<span class=\'spinner-border spinner-border-sm\'></span> Bezig…';">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary mb-3"><i class="bi bi-database-up"></i> Database bijwerken (<?= count($pending) ?>)</button>
            </form>
        <?php endif ?>
        <details <?= $pending !== [] ? 'open' : '' ?>>
            <summary class="small">Alle migraties (<?= count($migrations) ?>)</summary>
            <ul class="small list-unstyled mt-2 mb-0">
                <?php foreach (array_reverse($migrations) as $m) : ?>
                    <li class="mb-1"><?= $m['done'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-circle text-warning"></i>' ?>
                        <?= esc($m['name']) ?> <span class="text-muted">· <?= esc($nsLabel($m['namespace'])) ?> · <?= esc(substr($m['version'], 0, 10)) ?></span></li>
                <?php endforeach ?>
            </ul>
        </details>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">3. Geplande taken</div>
    <div class="card-body">
        <p class="mb-2">Zet in Plesk bij <strong>Geplande taken</strong> per taak een taak van het type <strong>URL ophalen</strong> met de link hieronder. Zo is er geen PHP op de commandoregel nodig.</p>
        <div class="alert alert-warning small py-2"><i class="bi bi-shield-lock"></i> Deze links bevatten een geheime sleutel: wie de link heeft, kan de taak starten (meer niet). Deel ze niet. Uitgelekt? Maak onderaan een nieuwe sleutel.</div>
        <div class="net-list">
        <?php foreach ($tasks as $key => [$command, $label, $cron, $when]) : ?>
            <?php $url = site_url('cron/' . $token . '/' . $key); $run = $lastRuns[$key] ?? null; ?>
            <div class="net-item">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div class="min-w-0">
                        <div class="fw-semibold"><?= esc($label) ?></div>
                        <div class="small text-muted">Plesk-planning: <code><?= esc($cron) ?></code> (<?= esc($when) ?>)</div>
                    </div>
                    <div class="small text-end">
                        <?php if ($run) : ?>
                            <span class="<?= $run['ok'] ? 'text-success' : 'text-danger' ?>"><i class="bi bi-<?= $run['ok'] ? 'check-circle' : 'x-circle' ?>"></i> <?= esc(\App\Libraries\Network::ago($run['at'])) ?> geleden</span>
                        <?php else : ?><span class="text-muted">nog niet via link gedraaid</span><?php endif ?>
                    </div>
                </div>
                <div class="input-group input-group-sm mt-2">
                    <input class="form-control mono" value="<?= esc($url, 'attr') ?>" readonly onclick="this.select()">
                    <button class="btn btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='Gekopieerd';">Kopieer</button>
                </div>
                <?php if ($run && $run['output'] !== '') : ?>
                    <details class="mt-1"><summary class="small text-muted">Laatste uitvoer</summary><pre class="small bg-light p-2 rounded mb-0 mt-1" style="white-space: pre-wrap"><?= esc($run['output']) ?></pre></details>
                <?php endif ?>
            </div>
        <?php endforeach ?>
        </div>
        <details class="mt-3 small">
            <summary>Liever met PHP op de commandoregel?</summary>
            <p class="mt-2 mb-1">Plesk installeert PHP per versie in een eigen map (deze site draait PHP <?= esc($php) ?>). Gebruik het volledige pad, bijvoorbeeld:</p>
            <pre class="bg-light p-2 rounded mb-0" style="white-space: pre-wrap">/opt/plesk/php/<?= esc(implode('.', array_slice(explode('.', $php), 0, 2))) ?>/bin/php <?= esc($root) ?>/spark klantbeheer:netwerk</pre>
        </details>
        <form action="<?= site_url('beheer/onderhoud/cron-sleutel') ?>" method="post" class="mt-3" onsubmit="return confirm('Nieuwe sleutel maken? De huidige links werken dan niet meer.');">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-repeat"></i> Nieuwe sleutel voor de links</button>
        </form>
    </div>
</div>
