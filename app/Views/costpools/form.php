<?php
$p         = $pool;
$selLedger = array_flip(\App\Libraries\CostPools::idList($p['ledger_account_ids'] ?? ''));
$selSupp   = array_flip(\App\Libraries\CostPools::idList($p['supplier_ids'] ?? ''));
?>
<a href="<?= site_url('kostprijzen') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Kostprijzen</a>
<h3 class="mb-3"><?= $isNew ? 'Nieuwe kostenpool' : 'Kostenpool bewerken' ?></h3>

<?php if (! $hasLines) : ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-info-circle"></i> Er zijn nog geen inkoopregels opgehaald, dus er zijn nog geen leveranciers en categorieën om uit te kiezen.</span>
    <form method="post" action="<?= site_url('kostprijzen/ophalen') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-primary" <?= $mb->configured() ? '' : 'disabled' ?>><i class="bi bi-arrow-repeat"></i> Nu ophalen uit Moneybird</button></form>
</div>
<?php endif ?>

<form method="post"><?= csrf_field() ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-body">
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
        </div></div>

        <div class="card"><div class="card-body">
            <h6>Diensten die deze kosten dragen</h6>
            <p class="small text-muted">Verdeeld naar aantal actieve abonnementen × gewicht. Gewicht 2 = dubbel zoveel per stuk.</p>
            <table class="table table-sm mb-0">
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
        <div class="card"><div class="card-body">
            <h6>Welke inkoop hoort hierbij?</h6>
            <p class="small text-muted mb-3">Een inkoopregel telt mee als hij aan <strong>één</strong> van deze regels voldoet. Daarna kun je per regel nog aan- of uitvinken via <em>Inkoopregels</em>.</p>

            <label class="form-label fw-semibold"><i class="bi bi-shop"></i> Leveranciers</label>
            <div class="form-text mt-0 mb-1">Alle inkoop van deze leverancier telt mee, ongeacht de categorie. Bijv. Hetzner, Plesk, CloudLinux.</div>
            <input type="search" class="form-control form-control-sm mb-1" placeholder="Filter leveranciers…" oninput="const q=this.value.toLowerCase();document.querySelectorAll('.supp-row').forEach(r=>r.hidden=!r.textContent.toLowerCase().includes(q))">
            <div class="border rounded p-2 mb-3" style="max-height: 220px; overflow-y: auto">
                <?php foreach ($suppliers as $s) : ?>
                    <div class="form-check supp-row d-flex justify-content-between">
                        <span><input class="form-check-input" type="checkbox" name="suppliers[]" value="<?= esc($s['supplier_id'], 'attr') ?>" id="sp<?= esc($s['supplier_id'], 'attr') ?>" <?= isset($selSupp[$s['supplier_id']]) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sp<?= esc($s['supplier_id'], 'attr') ?>"><?= esc($s['name']) ?></label></span>
                        <span class="small text-muted text-nowrap"><?= euro($s['total']) ?> · <?= (int) $s['n'] ?> regels</span>
                    </div>
                <?php endforeach ?>
                <?php if ($suppliers === []) : ?><span class="small text-muted">Nog geen inkoop opgehaald.</span><?php endif ?>
            </div>

            <label class="form-label fw-semibold"><i class="bi bi-search"></i> Zoekwoorden</label>
            <textarea name="keywords" rows="3" class="form-control mb-1" placeholder="Eén per regel, bijv.&#10;vps&#10;plesk&#10;imunify"><?= esc($p['keywords'] ?? '') ?></textarea>
            <div class="form-text mt-0 mb-3">Zoekt in de omschrijving, referentie en leveranciersnaam (hoofdletters maken niet uit).</div>

            <label class="form-label fw-semibold"><i class="bi bi-tags"></i> Categorieën</label>
            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto">
                <?php foreach ($ledgers as $l) : ?>
                    <div class="form-check d-flex justify-content-between">
                        <span><input class="form-check-input" type="checkbox" name="ledgers[]" value="<?= esc($l['ledger_id'], 'attr') ?>" id="la<?= esc($l['ledger_id'], 'attr') ?>" <?= isset($selLedger[$l['ledger_id']]) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="la<?= esc($l['ledger_id'], 'attr') ?>"><?= esc($l['name']) ?></label></span>
                        <span class="small text-muted text-nowrap"><?= euro($l['total']) ?></span>
                    </div>
                <?php endforeach ?>
                <?php if ($ledgers === []) : ?><span class="small text-muted">Nog geen inkoop opgehaald.</span><?php endif ?>
            </div>
        </div></div>
    </div>
</div>
<div class="mt-3 d-flex gap-2"><button class="btn btn-primary">Opslaan</button><a href="<?= site_url('kostprijzen') ?>" class="btn btn-outline-secondary">Annuleren</a></div>
</form>
<?php if (! $isNew) : ?>
<div class="mt-3"><?= post_link(site_url('kostprijzen/' . $p['id'] . '/verwijderen'), '<i class="bi bi-trash"></i> Kostenpool verwijderen', 'Kostenpool verwijderen? De diensten houden hun laatste inkoopprijs.') ?></div>
<?php endif ?>
