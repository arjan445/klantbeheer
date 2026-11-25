<?php
/**
 * Offline klantapparaten, per klant en locatie (dashboard en netwerkpagina).
 *
 * @var array $offline  Network::offline()
 * @var array $status   Network::status()
 * @var bool  $dashboard
 */
use App\Libraries\Network;

$dashboard ??= false;
$count     = $offline['count'];
?>
<div class="card mb-4 net-offline <?= $count > 0 ? 'has-offline' : '' ?>">
    <div class="card-header d-flex justify-content-between align-items-center gap-2">
        <span class="text-nowrap">
            <?php if ($count > 0) : ?>
                <i class="bi bi-wifi-off text-danger"></i> Offline <span class="badge text-bg-danger"><?= (int) $count ?></span>
            <?php else : ?>
                <i class="bi bi-wifi text-success"></i> Netwerk
            <?php endif ?>
        </span>
        <span class="small text-muted text-truncate">
            <?php if ($status['lastSync']) : ?>bijgewerkt <?= esc(Network::ago($status['lastSync'])) ?><?= Network::ago($status['lastSync']) === 'net' ? '' : ' geleden' ?><?php endif ?>
            <?php if ($dashboard) : ?> · <a href="<?= site_url('netwerk') ?>">Netwerk →</a><?php endif ?>
        </span>
    </div>
    <div class="card-body">
        <?php if ($status['stale']) : ?>
            <div class="alert alert-warning py-2 small mb-3"><i class="bi bi-clock-history"></i>
                <?= $status['lastSync'] ? 'De laatste synchronisatie is van ' . esc(Network::ago($status['lastSync'])) . ' geleden.' : 'Er is nog niet gesynchroniseerd.' ?>
                De status hieronder is daardoor mogelijk niet actueel. Controleer de cronjob <code>klantbeheer:netwerk</code>.
                <?php if ($status['errors'] !== []) : ?><div class="mt-1 text-danger"><?= esc(implode(' · ', $status['errors'])) ?></div><?php endif ?>
            </div>
        <?php endif ?>

        <?php if ($count === 0) : ?>
            <div class="net-allgood"><i class="bi bi-check-circle-fill"></i>
                <?= $offline['monitored'] > 0 ? 'Alle ' . (int) $offline['monitored'] . ' bewaakte klantapparaten zijn online.' : 'Nog geen apparaten die aan een klant gekoppeld zijn.' ?>
            </div>
        <?php else : ?>
            <?php foreach ($offline['groups'] as $g) : ?>
                <div class="off-cust">
                    <a class="off-cust-name" href="<?= site_url('klanten/' . $g['customer_id']) ?>"><?= esc($g['customer']) ?></a>
                    <?php foreach ($g['locations'] as $loc) : ?>
                        <?php if (count($g['locations']) > 1 || $loc['site_id']) : ?><div class="off-loc"><i class="bi bi-geo-alt"></i> <?= esc($loc['site']) ?></div><?php endif ?>
                        <?php foreach ($loc['devices'] as $d) : ?>
                            <?php $url = $d['device_id'] ? site_url('apparaten/' . $d['device_id']) : site_url('netwerk?tab=inbox'); ?>
                            <a class="off-dev" href="<?= $url ?>">
                                <span class="dot dot-off"></span>
                                <span class="off-main">
                                    <span class="off-name"><?= esc($d['device_name'] ?: ($d['name'] ?: $d['model'] ?: 'Onbekend apparaat')) ?></span>
                                    <span class="off-sub"><?= esc(implode(' · ', array_filter([$d['model'], $d['ip'], Network::platformLabel($d['platform'])]))) ?></span>
                                </span>
                                <span class="off-since"><?= $d['offline_since'] ? esc(Network::ago($d['offline_since'])) : '' ?></span>
                            </a>
                        <?php endforeach ?>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        <?php endif ?>

        <?php if ($offline['unassigned'] > 0) : ?>
            <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Nog <?= (int) $offline['unassigned'] ?> offline apparaat/apparaten op sites die niet aan een klant gekoppeld zijn.
                <a href="<?= site_url('netwerk?tab=sites') ?>">Sites koppelen</a></div>
        <?php endif ?>
    </div>
</div>
