<?php
/**
 * Herstelmail ("weer bereikbaar"), zelfde huisstijl als de storingsmail.
 *
 * @var array       $customer
 * @var list<array> $back      weer bereikbaar (topologievolgorde), met offline_since en recovered_at
 * @var list<array> $still     nog steeds offline
 * @var array       $settings
 * @var string      $logo
 * @var bool        $internal
 * @var bool        $test
 */
use App\Libraries\Alerts;

$navy  = '#0b1150';
$green = '#198754';
$red   = '#dc3545';
$font  = "font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
$greet = trim((string) ($customer['contact_name'] ?? '')) !== '' ? $customer['contact_name'] : $customer['name'];
$n     = count($back);
$row   = static function (array $d, string $color, string $right) use ($font) {
    return '<tr><td style="padding:8px 0;border-bottom:1px solid #eceef3;' . $font . 'font-size:14px;line-height:1.4;color:#1d2433;">'
        . '<span style="color:' . $color . ';">&#9679;</span>&nbsp; <strong>' . esc($d['display_name']) . '</strong>'
        . ' <span style="color:#8a90a0;">&middot; ' . esc(device_type_label($d['topo']['type'])) . '</span>'
        . '</td><td align="right" valign="top" style="padding:8px 0;border-bottom:1px solid #eceef3;' . $font . 'font-size:13px;color:#8a90a0;">' . $right . '</td></tr>';
};
$since = static fn (?string $t) => $t ? (date('Y-m-d', strtotime($t)) === date('Y-m-d') ? date('H:i', strtotime($t)) : date('d-m H:i', strtotime($t))) : '';
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title>Weer bereikbaar</title>
</head>
<body style="margin:0;padding:0;background:#eef0f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f4;">
<tr><td align="center" style="padding:20px 12px;">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden;">

<?php if ($internal) : ?>
<tr><td style="background:#e8ebf7;color:<?= $navy ?>;padding:9px 24px;<?= $font ?>font-size:13px;"><strong>Intern</strong> &middot; herstel na de storingsmail.</td></tr>
<?php elseif ($test) : ?>
<tr><td style="background:#fff3cd;color:#664d03;padding:9px 24px;<?= $font ?>font-size:13px;"><strong>Voorbeeld</strong> &middot; zo krijgt de klant hem.</td></tr>
<?php endif ?>

<tr><td style="padding:18px 24px 14px;border-bottom:3px solid <?= $navy ?>;">
    <img src="<?= htmlspecialchars($logo, ENT_QUOTES) ?>" alt="bongersonline" width="150" style="display:block;width:150px;max-width:150px;height:auto;border:0;">
</td></tr>

<tr><td style="padding:20px 24px 4px;<?= $font ?>font-size:15px;line-height:1.5;color:#1d2433;">
    <div style="font-size:19px;font-weight:700;color:<?= $green ?>;margin-bottom:8px;"><?= $still === [] ? 'Alles weer bereikbaar' : ($n === 1 ? 'Eén apparaat weer bereikbaar' : $n . ' apparaten weer bereikbaar') ?></div>
    Beste <?= esc($greet) ?>, <?= $n === 1 ? 'dit apparaat werkt' : 'deze apparaten werken' ?> weer:
</td></tr>

<tr><td style="padding:6px 24px 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <?php foreach ($back as $d) : ?><?= $row($d, $green, 'offline ' . esc(Alerts::span($d['offline_since'], $d['recovered_at']))) ?><?php endforeach ?>
    </table>
</td></tr>

<?php if ($still !== []) : ?>
<tr><td style="padding:16px 24px 0;<?= $font ?>font-size:14px;color:#1d2433;"><strong>Nog niet bereikbaar</strong></td></tr>
<tr><td style="padding:2px 24px 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <?php foreach ($still as $d) : ?><?= $row($d, $red, 'sinds ' . esc($since($d['offline_since']))) ?><?php endforeach ?>
    </table>
</td></tr>
<?php endif ?>

<tr><td style="padding:14px 24px 20px;<?= $font ?>font-size:14px;line-height:1.55;color:#1d2433;">
    <?= $still === [] ? 'Je hoeft niets te doen.' : 'Wij houden de rest in de gaten.' ?>
    Vragen? Beantwoord deze mail<?= ($settings['phone'] ?? '') !== '' ? ' of bel <a href="tel:' . esc(preg_replace('/[^\d+]/', '', $settings['phone']), 'attr') . '" style="color:' . $navy . ';font-weight:600;text-decoration:none;">' . esc($settings['phone']) . '</a>' : '' ?>.
    <br><br>Groet,<br><strong style="color:<?= $navy ?>;">bongersonline</strong>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
