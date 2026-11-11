<h3 class="mb-3">Moneybird</h3>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">Verbinding</div><div class="card-body">
            <?php if (! $mb->configured()) : ?>
                <div class="alert alert-warning mb-0">Nog niet ingesteld. Zet in <code>.env</code>:<br><code>moneybird.token</code> en <code>moneybird.administrationId</code>.<br>
                <span class="small">Token aanmaken: Moneybird &gt; Instellingen &gt; Ontwikkelaars &gt; Persoonlijke API-tokens (rechten: Verkoopfacturen, Contacten, Instellingen).</span></div>
            <?php elseif ($error) : ?>
                <div class="alert alert-danger mb-0"><?= esc($error) ?></div>
            <?php else : ?>
                <div class="text-success"><i class="bi bi-check-circle"></i> Verbonden met Moneybird.</div>
            <?php endif ?>
        </div></div>

        <div class="card mb-3"><div class="card-header">Contacten koppelen</div><div class="card-body">
            <p class="small"><?= (int) $linked ?> klanten gekoppeld, <?= (int) $unlinked ?> nog niet.</p>
            <form method="post" action="<?= site_url('beheer/moneybird/contacten') ?>">
                <?= csrf_field() ?>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="create_new" value="1" id="cn"><label class="form-check-label" for="cn">Ook nieuwe klanten aanmaken voor contacten die hier nog niet staan</label></div>
                <button class="btn btn-primary" <?= $mb->configured() ? '' : 'disabled' ?>><i class="bi bi-arrow-repeat"></i> Contacten ophalen &amp; koppelen</button>
            </form>
            <p class="small text-muted mt-2 mb-0">Koppelt op contact-ID en anders op exact dezelfde naam. Bestaande gegevens worden niet overschreven, alleen lege velden aangevuld.</p>
        </div></div>

        <div class="card"><div class="card-header">Kluis</div><div class="card-body small">
            <?php if ($vaultReady) : ?>
                <span class="text-success"><i class="bi bi-shield-check"></i> Versleutelingssleutel is ingesteld.</span>
                <div class="text-muted mt-1">Bewaar een kopie van <code>encryption.key</code> uit <code>.env</code> op een veilige plek buiten de server. Zonder die sleutel zijn de wachtwoorden niet terug te halen.</div>
            <?php else : ?>
                <span class="text-danger"><i class="bi bi-shield-exclamation"></i> Geen <code>encryption.key</code>. Draai <code>php spark key:generate</code>.</span>
            <?php endif ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header">Btw-tarieven</div><div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Naam</th><th>%</th><th>ID</th></tr></thead>
                <?php foreach ($taxRates as $t) : ?>
                    <tr><td><?= esc($t['name']) ?></td><td><?= esc($t['percentage']) ?></td><td class="mono small"><?= esc($t['id']) ?>
                        <?= (string) env('moneybird.taxRateId') === (string) $t['id'] ? '<span class="badge text-bg-success">standaard</span>' : '' ?></td></tr>
                <?php endforeach ?>
                <?php if ($taxRates === []) : ?><tr><td colspan="3" class="text-muted small p-3">Geen gegevens.</td></tr><?php endif ?>
            </table>
            <div class="small text-muted p-3">Zet het ID van 21% in <code>moneybird.taxRateId</code>. Leeg = Moneybird kiest zelf.</div>
        </div></div>
        <div class="card"><div class="card-header">Producten in Moneybird</div><div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Omschrijving</th><th>Prijs</th><th>ID</th></tr></thead>
                <?php foreach ($products as $p) : ?>
                    <tr><td><?= esc($p['description'] ?? $p['title'] ?? '') ?></td><td><?= esc($p['price'] ?? '') ?></td><td class="mono small"><?= esc($p['id']) ?></td></tr>
                <?php endforeach ?>
                <?php if ($products === []) : ?><tr><td colspan="3" class="text-muted small p-3">Geen producten. Optioneel: koppel een product-ID aan een dienst voor rapportage per product.</td></tr><?php endif ?>
            </table>
        </div></div>
    </div>
</div>
