<?php
/**
 * Storingsmail (kort, huisstijl bongersonline). Tabellen en inline stijlen:
 * zo ziet hij er in Outlook, Gmail en op de telefoon goed uit.
 *
 * @var array       $customer
 * @var list<array> $new       nieuw gemelde apparaten (topologievolgorde)
 * @var list<array> $still     eerder gemeld, nog steeds offline
 * @var array       $settings
 * @var string      $logo      cid: of URL
 * @var bool        $internal  jouw versie
 * @var string      $clientNote of/wanneer de klant gemaild is (jouw versie)
 * @var list<string> $wouldTo   e-mailadres van de klant
 * @var bool        $clientOn  klant krijgt mails
 * @var bool        $preview
 */
$navy  = '#0b1150';
$red   = '#dc3545';
$font  = "font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
$all   = array_merge($new, $still);
$first = min(array_map(static fn ($d) => strtotime($d['offline_since'] ?: 'now'), $all));
$since = static fn (?string $t) => $t ? (date('Y-m-d', strtotime($t)) === date('Y-m-d') ? date('H:i', strtotime($t)) : date('d-m H:i', strtotime($t))) : '';
$greet = trim((string) ($customer['contact_name'] ?? '')) !== '' ? $customer['contact_name'] : $customer['name'];
$top   = $all[0];
$tip   = match ($top['topo']['type']) {
    'router'          => 'Heeft de router of het modem van de provider nog stroom?',
    'camera', 'nvr'   => 'Hebben de recorder en de switch nog stroom?',
    default           => 'Heeft ' . $top['display_name'] . ' nog stroom en zitten de kabels goed?',
};
$minDepth = min(array_map(static fn ($d) => $d['topo']['depth'], $all));
$row = static function (array $d, bool $isStill) use ($red, $since, $font, $minDepth) {
    $indent = min(3, max(0, $d['topo']['depth'] - $minDepth)) * 16;

    return '<tr><td style="padding:8px 0 8px ' . $indent . 'px;border-bottom:1px solid #eceef3;' . $font . 'font-size:14px;line-height:1.4;color:#1d2433;">'
        . '<span style="color:' . ($isStill ? '#e8a0a8' : $red) . ';">&#9679;</span>&nbsp; <strong>' . esc($d['display_name']) . '</strong>'
        . ' <span style="color:#8a90a0;">&middot; ' . esc(device_type_label($d['topo']['type'])) . '</span>'
        . ($d['topo']['cause'] ? '<br><span style="color:#8a90a0;font-size:13px;padding-left:18px;">via ' . esc($d['topo']['cause']) . '</span>' : '')
        . '</td><td align="right" valign="top" style="padding:8px 0;border-bottom:1px solid #eceef3;' . $font . 'font-size:13px;color:#8a90a0;white-space:nowrap;">sinds ' . esc($since($d['offline_since'])) . '</td></tr>';
};
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title>Storing</title>
</head>
<body style="margin:0;padding:0;background:#eef0f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f4;">
<tr><td align="center" style="padding:20px 12px;">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background:#ffffff;border-radius:10px;overflow:hidden;">

<?php if ($internal) : ?>
<tr><td style="background:#e8ebf7;color:<?= $navy ?>;padding:9px 24px;<?= $font ?>font-size:13px;">
    <strong>Intern</strong> &middot; <?= esc(ucfirst($clientNote)) ?>.
</td></tr>
<?php elseif (! empty($test)) : ?>
<tr><td style="background:#fff3cd;color:#664d03;padding:9px 24px;<?= $font ?>font-size:13px;">
    <strong>Voorbeeld</strong> &middot; zo krijgt de klant hem<?= $wouldTo !== [] ? ' (' . esc(implode(', ', $wouldTo)) . ')' : '' ?><?= $clientOn ? '' : ', als je mails voor deze klant aanzet' ?>.
</td></tr>
<?php endif ?>

<tr><td style="padding:18px 24px 14px;border-bottom:3px solid <?= $navy ?>;">
    <img src="<?= htmlspecialchars($logo, ENT_QUOTES) ?>" alt="bongersonline" width="150" style="display:block;width:150px;max-width:150px;height:auto;border:0;">
</td></tr>

<tr><td style="padding:20px 24px 4px;<?= $font ?>font-size:15px;line-height:1.5;color:#1d2433;">
    <div style="font-size:19px;font-weight:700;color:<?= $navy ?>;margin-bottom:8px;"><?= count($all) === 1 ? 'Eén apparaat niet bereikbaar' : count($all) . ' apparaten niet bereikbaar' ?></div>
    Beste <?= esc($greet) ?>, sinds <?= date('H:i', $first) ?> <?= count($all) === 1 ? 'is dit apparaat' : 'zijn deze apparaten' ?> niet bereikbaar:
</td></tr>

<tr><td style="padding:6px 24px 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <?php foreach ($new as $d) : ?><?= $row($d, false) ?><?php endforeach ?>
        <?php foreach ($still as $d) : ?><?= $row($d, true) ?><?php endforeach ?>
    </table>
</td></tr>

<tr><td style="padding:14px 24px 20px;<?= $font ?>font-size:14px;line-height:1.55;color:#1d2433;">
    <?= esc($tip) ?> Wij kijken mee.<?= $settings['pdf'] ? ' Een overzicht zit in de bijlage.' : '' ?><br>
    Vragen? Beantwoord deze mail<?= $settings['phone'] !== '' ? ' of bel <a href="tel:' . esc(preg_replace('/[^\d+]/', '', $settings['phone']), 'attr') . '" style="color:' . $navy . ';font-weight:600;text-decoration:none;">' . esc($settings['phone']) . '</a>' : '' ?>.
    <br><br>Groet,<br><strong style="color:<?= $navy ?>;">bongersonline</strong>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
