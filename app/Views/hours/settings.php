<?php
$val = static fn (string $k) => $values[$k] ?? '';
$ledgerSelect = static function (string $name, string $current, array $ledgers, string $hint) {
    $html = '<select name="' . $name . '" class="form-select"><option value="">— kiezen —</option>';
    $groups = [];
    foreach ($ledgers as $l) {
        $groups[$l['account_type'] ?? 'overig'][] = $l;
    }
    foreach ($groups as $type => $list) {
        $html .= '<optgroup label="' . esc($type, 'attr') . '">';
        foreach ($list as $l) {
            $label = trim(($l['account_id'] ?? '') . ' ' . $l['name']);
            $html .= '<option value="' . esc((string) $l['id'], 'attr') . '"' . ((string) $l['id'] === $current ? ' selected' : '') . '>' . esc($label) . '</option>';
        }
        $html .= '</optgroup>';
    }

    return $html . '</select><div class="form-text">' . $hint . '</div>';
};
?>
<h3 class="mb-3">Uren &amp; kilometers</h3>
<?php if (! $mb->configured()) : ?>
    <div class="alert alert-warning">Moneybird is nog niet gekoppeld (zie Beheer → Moneybird).</div>
<?php elseif ($error) : ?>
    <div class="alert alert-danger">Moneybird: <?= esc($error) ?><br><span class="small">Controleer of je API-token rechten heeft op <strong>Uren</strong>, <strong>Boekhouding</strong> en <strong>Instellingen</strong>.</span></div>
<?php endif ?>

<form method="post"><?= csrf_field() ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Moneybird</div><div class="card-body">
            <label class="form-label">Moneybird-gebruiker voor de uren</label>
            <select name="moneybird_userId" class="form-select">
                <option value="">— kiezen —</option>
                <?php foreach ($users as $u) : ?><option value="<?= esc($u['id'], 'attr') ?>" <?= $val('moneybird.userId') === $u['id'] ? 'selected' : '' ?>><?= esc($u['name']) ?></option><?php endforeach ?>
            </select>
            <div class="form-text mb-3">Elke urenregel gaat naar de urenregistratie van deze gebruiker, met "Uren dd-mm-jjjj" in de omschrijving.</div>

            <label class="form-label">Kilometerkosten (debet)</label>
            <?= $ledgerSelect('km_ledgerCost', $val('km.ledgerCost'), $ledgers, 'Kostenrekening, bv. "Reiskosten" of "Autokosten (kilometervergoeding)".') ?>

            <label class="form-label mt-3">Privé (credit)</label>
            <?= $ledgerSelect('km_ledgerPrivate', $val('km.ledgerPrivate'), $ledgers, 'De privérekening, bv. "Onttrekkingen privé". Het bedrag komt hier aan de creditkant, omdat je de zakelijke rit privé hebt betaald.') ?>

            <div class="form-check form-switch mt-3">
                <input type="hidden" name="km_autoBook" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="km_autoBook" value="1" id="ab" <?= $val('km.autoBook') !== '0' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ab">Memoriaal automatisch boeken/bijwerken bij het opslaan van uren</label>
            </div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">Kilometers</div><div class="card-body">
            <label class="form-label">Thuisadres (vertrek en terug)</label>
            <input name="km_home" class="form-control" value="<?= esc($val('km.home'), 'attr') ?>" placeholder="Straat 1, 1234 AB Plaats">

            <label class="form-label mt-3">Tarief per km</label>
            <div class="input-group" style="max-width: 12rem"><span class="input-group-text">€</span><input name="km_rate" inputmode="decimal" class="form-control" value="<?= esc(str_replace('.', ',', $val('km.rate')), 'attr') ?>"></div>
            <div class="form-text">Fiscale kilometeraftrek voor ondernemers in 2026: € 0,25.</div>

            <label class="form-label mt-3">OpenRouteService API-sleutel</label>
            <input name="openroute_apiKey" class="form-control" type="password" autocomplete="off" placeholder="<?= $val('openroute.apiKey') !== '' ? '•••••••• (ingesteld, leeg laten om te houden)' : 'plak hier je sleutel' ?>">
            <div class="form-text">Gratis aan te maken op openrouteservice.org (Dashboard → Tokens). Wordt gebruikt om adressen op te zoeken en de rijafstand te berekenen.</div>
            <?php if ($val('openroute.apiKey') !== '') : ?>
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="openroute_clear" value="1" id="oc"><label class="form-check-label small" for="oc">Sleutel verwijderen</label></div>
            <?php endif ?>
        </div></div>
    </div>
</div>
<div class="mt-3"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> Opslaan</button></div>
</form>
