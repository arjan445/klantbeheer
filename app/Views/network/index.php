<?php
use App\Libraries\Network;

$platformBadge = static fn (string $p) => '<span class="badge net-pf net-pf-' . esc($p, 'attr') . '">' . esc(Network::platformLabel($p)) . '</span>';
$locSelect = static function (string $name, ?int $current, array $locations, ?int $suggest = null, string $extra = '') {
    $html = '<select name="' . $name . '" class="form-select" ' . $extra . '><option value="">— geen klantlocatie —</option>';
    foreach ($locations as $customer => $list) {
        $html .= '<optgroup label="' . esc($customer, 'attr') . '">';
        foreach ($list as $l) {
            $sel  = ((int) $l['id'] === (int) ($current ?? $suggest)) ? ' selected' : '';
            $mark = (! $current && $suggest === (int) $l['id']) ? ' (voorstel)' : '';
            $html .= '<option value="' . (int) $l['id'] . '"' . $sel . '>' . esc($customer . ' · ' . $l['name'] . $mark) . '</option>';
        }
        $html .= '</optgroup>';
    }

    return $html . '</select>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Netwerk</h3>
    <div class="d-flex gap-2">
        <?php if ($manage) : ?><a href="<?= site_url('scan') ?>" class="btn btn-outline-primary"><i class="bi bi-upc-scan"></i> Doosscan</a><?php endif ?>
        <?php if ($status['configured']) : ?>
        <form method="post" action="<?= site_url('netwerk/synchroniseren') ?>"><?= csrf_field() ?>
            <button class="btn btn-primary" onclick="this.disabled=true;this.innerHTML='<span class=\'spinner-border spinner-border-sm\'></span> Bezig…';this.form.submit()"><i class="bi bi-arrow-repeat"></i> Nu ophalen</button>
        </form>
        <?php endif ?>
    </div>
</div>

<?php if (! $status['configured']) : ?>
    <div class="alert alert-info">UniFi en UISP zijn nog niet ingesteld.
        <?php if (user_can('settings.manage')) : ?><a href="<?= site_url('beheer/netwerk') ?>" class="alert-link">Instellen</a><?php else : ?>Vraag een beheerder om dit in te stellen.<?php endif ?>
    </div>
<?php endif ?>

<ul class="nav nav-pills net-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab === 'offline' ? 'active' : '' ?>" href="<?= site_url('netwerk?tab=offline') ?>">Offline <?php if ($offline['count']) : ?><span class="badge text-bg-danger"><?= (int) $offline['count'] ?></span><?php endif ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'inbox' ? 'active' : '' ?>" href="<?= site_url('netwerk?tab=inbox') ?>">Niet gekoppeld <?php if ($inboxCount) : ?><span class="badge text-bg-warning"><?= (int) $inboxCount ?></span><?php endif ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $tab === 'sites' ? 'active' : '' ?>" href="<?= site_url('netwerk?tab=sites') ?>">Sites <?php if ($unlinkedSites) : ?><span class="badge text-bg-warning"><?= (int) $unlinkedSites ?></span><?php endif ?></a></li>
</ul>

<?php if ($tab === 'offline') : ?>
    <?= view('network/_offline', ['offline' => $offline, 'status' => $status]) ?>

<?php elseif ($tab === 'sites') : ?>
    <p class="text-muted small">Elke UniFi- of UISP-site hoort bij één locatie van een klant. Apparaten op die site tellen dan mee voor die klant, ook als ze nog niet in de kluis staan.</p>
    <?php if ($sites === []) : ?>
        <div class="card"><div class="card-body text-muted">Nog geen sites. Klik op <strong>Nu ophalen</strong>.</div></div>
    <?php endif ?>
    <div class="net-list">
    <?php foreach ($sites as $s) : ?>
        <div class="net-item <?= $s['ignored'] ? 'opacity-50' : '' ?>">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="fw-semibold text-truncate"><?= $platformBadge($s['platform']) ?> <?= esc($s['name']) ?></div>
                    <div class="small text-muted text-truncate"><?= esc(implode(' · ', array_filter([$s['host_name'] && $s['host_name'] !== $s['name'] ? 'console ' . $s['host_name'] : null, $s['platform'] === 'uisp' ? $s['description'] : null]))) ?></div>
                </div>
                <div class="text-end small text-nowrap">
                    <?= (int) $s['device_count'] ?> apparaten
                    <?php if ($s['offline_count']) : ?><div class="text-danger"><?= (int) $s['offline_count'] ?> offline</div><?php endif ?>
                </div>
            </div>
            <?php if ($manage) : ?>
            <form method="post" action="<?= site_url('netwerk/site/' . $s['id']) ?>" class="d-flex gap-2 mt-2 flex-wrap flex-md-nowrap">
                <?= csrf_field() ?>
                <?= $locSelect('site_id', $s['site_id'] ? (int) $s['site_id'] : null, $locations, $suggestions[(int) $s['id']] ?? null) ?>
                <label class="btn btn-outline-secondary text-nowrap mb-0" title="Niet meetellen (bijv. testsite of eigen lab)">
                    <input type="checkbox" name="ignored" value="1" class="form-check-input me-1" <?= $s['ignored'] ? 'checked' : '' ?>> Negeren
                </label>
                <button class="btn btn-primary text-nowrap">Opslaan</button>
            </form>
            <?php elseif ($s['site_id']) : ?>
                <div class="small mt-1"><i class="bi bi-link-45deg"></i> <?= esc($s['customer_name'] . ' · ' . $s['loc_name']) ?></div>
            <?php endif ?>
        </div>
    <?php endforeach ?>
    </div>

<?php else : ?>
    <p class="text-muted small">Apparaten uit UniFi en UISP die nog niet aan een apparaat in de kluis hangen. Kies per apparaat of het een <strong>nieuw</strong> kluis-apparaat wordt, of hoort bij een apparaat dat er al staat (bijvoorbeeld uit een offerte). Apparaten met hetzelfde MAC- of serienummer worden automatisch gekoppeld.</p>
    <?php if ($groups === []) : ?>
        <div class="card"><div class="card-body"><div class="net-allgood"><i class="bi bi-check-circle-fill"></i> Alles is gekoppeld.</div></div></div>
    <?php endif ?>

    <?php if ($groups !== [] && $manage) : ?><form method="post" action="<?= site_url('netwerk/koppelen') ?>" id="assign-form"><?= csrf_field() ?><?php endif ?>
    <?php foreach ($groups as $sid => $g) : ?>
        <?php $site = $g['site']; $linked = $site && $site['customer_id']; ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span><?= $site ? $platformBadge($site['platform']) . ' ' . esc($site['name']) : 'Zonder site' ?></span>
                <?php if ($linked) : ?>
                    <span class="small fw-normal"><i class="bi bi-link-45deg"></i> <a href="<?= site_url('klanten/' . $site['customer_id']) ?>"><?= esc($site['customer_name']) ?></a> · <?= esc($site['loc_name']) ?></span>
                <?php else : ?>
                    <?php if ($site && str_starts_with($site['external_id'], 'host:')) : ?>
                        <span class="small fw-normal text-muted">Site nog niet bekend: kies per apparaat de klant, of wacht op de volgende synchronisatie</span>
                    <?php else : ?>
                    <a href="<?= site_url('netwerk?tab=sites') ?>" class="small fw-normal text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> Site nog niet aan een klant gekoppeld</a>
                    <?php endif ?>
                <?php endif ?>
            </div>
            <div class="card-body">
                <?php if ($manage && $linked && count($g['devices']) > 1) : ?>
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-set-all="#grp<?= (int) $sid ?>" data-value="new"><i class="bi bi-plus-lg"></i> Alles nieuw in kluis</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-set-all="#grp<?= (int) $sid ?>" data-value="suggest"><i class="bi bi-magic"></i> Voorstellen</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-set-all="#grp<?= (int) $sid ?>" data-value=""><i class="bi bi-x-lg"></i> Niets</button>
                    </div>
                <?php endif ?>
                <div class="net-list" id="grp<?= (int) $sid ?>">
                <?php foreach ($g['devices'] as $nd) : ?>
                    <div class="net-item">
                        <div class="d-flex align-items-start gap-2">
                            <span class="dot mt-2 <?= $nd['status'] === 'online' ? 'dot-on' : ($nd['status'] === 'offline' ? 'dot-off' : 'dot-unk') ?>" title="<?= esc($nd['raw_status'] ?? $nd['status'], 'attr') ?>"></span>
                            <div class="min-w-0 flex-fill">
                                <div class="fw-semibold text-truncate"><?= esc($nd['name'] ?: $nd['model'] ?: 'Onbekend') ?></div>
                                <div class="small text-muted"><?= esc(implode(' · ', array_filter([$nd['model'], device_type_label(Network::guessType($nd))]))) ?></div>
                                <div class="small mono text-muted"><?= esc(implode(' · ', array_filter([Network::formatMac($nd['mac']), $nd['ip']]))) ?></div>
                            </div>
                        </div>
                        <?php if ($manage) : ?>
                        <select name="choice[<?= (int) $nd['id'] ?>]" class="form-select mt-2" data-suggest="<?= esc($linked ? $nd['suggest'] : '', 'attr') ?>">
                            <option value="">— later —</option>
                            <?php if ($linked) : ?>
                                <option value="new" <?= $nd['suggest'] === 'new' ? 'selected' : '' ?>>+ Nieuw in de kluis</option>
                                <?php if ($nd['candidates'] !== []) : ?>
                                <optgroup label="Bestaand apparaat van <?= esc($site['customer_name'], 'attr') ?>">
                                    <?php foreach ($nd['candidates'] as $c) : ?>
                                        <option value="<?= (int) $c['id'] ?>" <?= $nd['suggest'] === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name'] . ($c['model'] ? ' · ' . $c['model'] : '') . ($c['room'] ? ' · ' . $c['room'] : '') . ($c['score'] >= 9 ? '  ★' : '')) ?></option>
                                    <?php endforeach ?>
                                </optgroup>
                                <?php endif ?>
                            <?php endif ?>
                            <?php if (! $linked) : ?>
                                <?php foreach ($locations as $customer => $list) : ?>
                                <optgroup label="Nieuw in de kluis bij <?= esc($customer, 'attr') ?>">
                                    <?php foreach ($list as $l) : ?><option value="loc:<?= (int) $l['id'] ?>"><?= esc($customer . ' · ' . $l['name']) ?></option><?php endforeach ?>
                                </optgroup>
                                <?php endforeach ?>
                            <?php endif ?>
                            <option value="ignore">Negeren (niet bewaken)</option>
                        </select>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
    <?php if ($groups !== [] && $manage) : ?>
        <div class="sticky-actions"><button class="btn btn-primary w-100"><i class="bi bi-check2"></i> Opslaan</button></div>
    </form>
    <script>
    document.querySelectorAll('[data-set-all]').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll(b.dataset.setAll + ' select').forEach(s => {
            const v = b.dataset.value === 'suggest' ? (s.dataset.suggest || '') : b.dataset.value;
            if ([...s.options].some(o => o.value === v)) s.value = v;
        });
    }));
    </script>
    <?php endif ?>
<?php endif ?>
