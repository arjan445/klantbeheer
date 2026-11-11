<?php
$p        = $pool;
$selected = array_flip(array_filter(explode(',', (string) ($p['ledger_account_ids'] ?? ''))));
$typeLabels = ['expenses' => 'Kosten', 'direct_costs' => 'Inkoopkosten', 'non_current_assets' => 'Vaste activa', 'current_assets' => 'Vlottende activa', 'revenue' => 'Omzet', 'other_income_expenses' => 'Overig', 'equity' => 'Eigen vermogen', 'current_liabilities' => 'Kortlopende schulden', 'provisions' => 'Voorzieningen', 'non_current_liabilities' => 'Langlopende schulden', 'temporary' => 'Tijdelijk'];
// Kostencategorieën bovenaan
usort($ledgers, static function ($a, $b) {
    $rank = static fn ($t) => in_array($t, ['direct_costs', 'expenses'], true) ? 0 : 1;
    return [$rank($a['account_type'] ?? ''), $a['name']] <=> [$rank($b['account_type'] ?? ''), $b['name']];
});
?>
<a href="<?= site_url('kostprijzen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Kostprijzen</a>
<h3 class="mb-3"><?= $isNew ? 'Nieuwe kostenpool' : 'Kostenpool bewerken' ?></h3>
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="mb-3"><label class="form-label">Naam *</label><input name="name" class="form-control" required value="<?= esc($p['name'] ?? '') ?>" placeholder="Bijv. Hostingplatform"></div>
            <div class="row g-3">
                <div class="col-sm-4"><label class="form-label">Periode</label>
                    <div class="input-group"><input name="lookback_months" type="number" min="1" max="36" class="form-control" value="<?= (int) ($p['lookback_months'] ?? 12) ?>"><span class="input-group-text">mnd</span></div>
                    <div class="form-text">Laatste X maanden, omgerekend naar een jaar.</div></div>
                <div class="col-sm-4"><label class="form-label">Toerekenen</label>
                    <div class="input-group"><input name="allocation_pct" class="form-control" value="<?= esc(aantal($p['allocation_pct'] ?? 100)) ?>"><span class="input-group-text">%</span></div>
                    <div class="form-text">Bijv. 80% als de VPS ook voor eigen dingen is.</div></div>
                <div class="col-sm-4"><label class="form-label">Extra per jaar</label>
                    <div class="input-group"><span class="input-group-text">€</span><input name="extra_yearly" class="form-control" value="<?= esc($p['extra_yearly'] ?? '') ?>"></div>
                    <div class="form-text">Kosten die niet in Moneybird staan.</div></div>
            </div>
            <div class="mt-3"><label class="form-label">Notities</label><textarea name="notes" rows="2" class="form-control"><?= esc($p['notes'] ?? '') ?></textarea></div>

            <h6 class="mt-4">Diensten die deze kosten dragen</h6>
            <p class="small text-muted">De kosten worden verdeeld naar het aantal actieve abonnementen × gewicht. Een dienst met gewicht 2 draagt dubbel zoveel per stuk.</p>
            <table class="table table-sm">
                <?php foreach ($services as $s) : ?>
                    <?php $linked = ! $isNew && (int) $s['cost_pool_id'] === (int) $p['id']; $otherPool = $s['cost_pool_id'] && ! $linked; ?>
                    <tr class="<?= $otherPool ? 'text-muted' : '' ?>">
                        <td style="width: 30px"><input type="checkbox" class="form-check-input" name="services[]" value="<?= $s['id'] ?>" id="sv<?= $s['id'] ?>" <?= $linked ? 'checked' : '' ?>></td>
                        <td><label for="sv<?= $s['id'] ?>"><?= esc($s['name']) ?></label> <span class="badge badge-type"><?= esc(service_type_label($s['type'])) ?></span>
                            <?php if ($otherPool) : ?><span class="small">(zit in een andere pool)</span><?php endif ?></td>
                        <td style="width: 110px"><div class="input-group input-group-sm"><span class="input-group-text">×</span><input name="weights[<?= $s['id'] ?>]" class="form-control" value="<?= esc(aantal($s['cost_weight'] ?? 1)) ?>"></div></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <h6>Moneybird-categorieën</h6>
            <p class="small text-muted">Vink de categorieën aan waarop je de inkoopfacturen en bonnetjes voor dit platform boekt.</p>
            <?php if ($mbError) : ?>
                <div class="alert alert-warning small">Kan de categorieën niet ophalen: <?= esc($mbError) ?><br>Controleer of het API-token de rechten <em>Documenten</em> en <em>Instellingen</em> heeft.</div>
            <?php endif ?>
            <input type="search" class="form-control form-control-sm mb-2" placeholder="Filter categorieën…" oninput="const q=this.value.toLowerCase();document.querySelectorAll('.ledger-row').forEach(r=>r.hidden=!r.textContent.toLowerCase().includes(q))">
            <div style="max-height: 480px; overflow-y: auto">
                <?php foreach ($ledgers as $l) : ?>
                    <div class="form-check ledger-row">
                        <input class="form-check-input" type="checkbox" name="ledgers[]" value="<?= esc($l['id'], 'attr') ?>" id="la<?= esc($l['id'], 'attr') ?>" <?= isset($selected[(string) $l['id']]) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="la<?= esc($l['id'], 'attr') ?>"><?= esc($l['name']) ?>
                            <span class="small text-muted"><?= esc($typeLabels[$l['account_type'] ?? ''] ?? ($l['account_type'] ?? '')) ?><?= ! empty($l['account_id']) ? ' · ' . esc($l['account_id']) : '' ?></span></label>
                    </div>
                <?php endforeach ?>
                <?php foreach (array_diff_key($selected, array_flip(array_map('strval', array_column($ledgers, 'id')))) as $id => $_) : ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="ledgers[]" value="<?= esc($id, 'attr') ?>" checked><label class="form-check-label small text-muted">Categorie <?= esc($id) ?> (niet geladen)</label></div>
                <?php endforeach ?>
            </div>
        </div></div>
    </div>
</div>
<div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Opslaan &amp; ophalen</button><a href="<?= site_url('kostprijzen') ?>" class="btn btn-outline-secondary">Annuleren</a></div>
</form>
<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('kostprijzen/' . $p['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Kostenpool verwijderen', 'Kostenpool verwijderen? De diensten houden hun laatste inkoopprijs.') ?></div>
<?php endif ?>
