<?php
/**
 * Compacte SD-WAN-badge(s) voor een netwerksite: alleen dát er een VPN is, de
 * status en met welke sites. Niet de hele mesh.
 *
 * @var array $site  network_sites-rij
 * @var bool  $full  ook de andere sites tonen (topologiepagina)
 */
use App\Libraries\Network;

$full ??= false;
foreach (Network::vpn($site) as $v) :
    [$color, $label] = Network::vpnState($v['state']);
    $tunnels = $v['total'] > 0 ? $v['up'] . '/' . $v['total'] . ' tunnels' : '';
    $peerNames = array_column($v['peers'], 'name');
    $title = 'SD-WAN ' . $v['name'] . ' · ' . ($v['role'] === 'hub' ? 'hub' : 'spoke') . ' · ' . $label
        . ($tunnels ? ' (' . $tunnels . ')' : '') . ($peerNames ? "\nMet: " . implode(', ', $peerNames) : '');
?>
<span class="vpn-badge vpn-<?= esc($color, 'attr') ?>" title="<?= esc($title, 'attr') ?>">
    <i class="bi bi-shield-lock"></i> SD-WAN<span class="d-none d-sm-inline"> · <?= esc($v['name']) ?></span>
    · <?= $v['role'] === 'hub' ? 'hub' : 'spoke' ?><?= $tunnels ? ' · ' . esc($tunnels) : '' ?>
</span>
<?php if ($full) : ?>
<div class="small text-muted mt-1">
    <span class="text-<?= esc($color, 'attr') ?>"><?= esc(ucfirst($label)) ?></span>
    <?php if ($v['peers'] !== []) : ?>
        · <?= $v['role'] === 'hub' ? 'spokes' : 'via hub' ?>:
        <?php foreach ($v['peers'] as $i => $p) : ?><?= $i ? ', ' : '' ?><?= $p['id'] ? '<a href="' . site_url('netwerk/topologie/' . $p['id']) . '">' . esc($p['name']) . '</a>' : esc($p['name']) ?><?php endforeach ?>
    <?php endif ?>
    <?php if ($v['count'] > count($v['peers']) + 1) : ?> · <?= (int) $v['count'] ?> sites in totaal<?php endif ?>
</div>
<?php endif ?>
<?php endforeach ?>
