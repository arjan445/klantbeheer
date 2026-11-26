<?php
/**
 * Storingsmail aan de klant (huisstijl bongersonline).
 * Tabellen en inline stijlen: zo ziet hij er in Outlook, Gmail en op de telefoon goed uit.
 *
 * @var array       $customer
 * @var list<array> $new      nieuw gemelde apparaten (topologievolgorde)
 * @var list<array> $still    eerder gemeld, nog steeds offline
 * @var array       $settings
 * @var string      $logo     cid: of URL
 * @var bool        $test
 */
$navy   = '#0b1150';
$red    = '#dc3545';
$font   = "font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
$all    = array_merge($new, $still);
$first  = min(array_map(static fn ($d) => strtotime($d['offline_since'] ?: 'now'), $all));
$sinceT = static fn (?string $t) => $t ? (date('Y-m-d', strtotime($t)) === date('Y-m-d') ? 'sinds ' . date('H:i', strtotime($t)) : 'sinds ' . date('d-m H:i', strtotime($t))) : '';
$dur    = static function (?string $t) {
    if (! $t) {
        return '';
    }
    $m = max(1, (int) round((time() - strtotime($t)) / 60));

    return $m < 90 ? $m . ' min' : ($m < 2880 ? round($m / 60) . ' uur' : round($m / 1440) . ' dagen');
};
$greet = trim((string) ($customer['contact_name'] ?? '')) !== '' ? 'Beste ' . $customer['contact_name'] : 'Beste ' . $customer['name'];

// Wat kan de klant zelf doen? Afhankelijk van het bovenste apparaat dat plat ligt
$top  = $all[0];
$type = $top['topo']['type'];
$tip  = match (true) {
    $type === 'router'                     => 'Werkt het internet nog? Controleer of de router en het modem van uw provider stroom hebben (lampjes aan). Is er een stroomstoring geweest, dan kan het een paar minuten duren voordat alles weer verbinding heeft.',
    in_array($type, ['switch', 'other'], true) => 'Controleer of <strong>' . esc($top['display_name']) . '</strong> stroom heeft (lampjes aan) en of de netwerkkabels er goed in zitten. Is er in die ruimte de stroom uitgevallen of is een stekker per ongeluk uitgetrokken?',
    $type === 'ap'                         => 'Controleer of de stekker of PoE-adapter van <strong>' . esc($top['display_name']) . '</strong> goed zit en of er in die ruimte stroom is.',
    $type === 'camera' || $type === 'nvr'  => 'Controleer of de recorder en de switch waar de camera aan hangt stroom hebben.',
    default                                => 'Controleer of de apparaten stroom hebben en of er in die ruimte een stroomstoring is.',
};
$row = static function (array $d, bool $isStill) use ($red, $sinceT, $dur, $font, $all) {
    $depths = array_map(static fn ($x) => $x['topo']['depth'], $all);
    $indent = min(3, max(0, $d['topo']['depth'] - min($depths))) * 18;
    $what   = device_type_label($d['topo']['type']);
    $where  = $d['loc_id'] ? '' : '';

    return '<tr><td style="padding:10px 0;border-bottom:1px solid #eceef3;' . $font . '">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
        . '<td width="' . (14 + $indent) . '" valign="top" style="padding:5px 0 0 ' . $indent . 'px;"><span style="display:inline-block;width:10px;height:10px;border-radius:5px;background:' . ($isStill ? '#e8a0a8' : $red) . ';"></span></td>'
        . '<td valign="top" style="' . $font . 'font-size:15px;color:#1d2433;line-height:1.35;">'
        . '<strong>' . esc($d['display_name']) . '</strong>'
        . '<div style="font-size:13px;color:#6c757d;">' . esc($what) . ($d['nsite_name'] ? ' &middot; ' . esc($d['nsite_name']) : '') . '</div>'
        . ($d['topo']['cause'] ? '<div style="font-size:13px;color:#8a6d00;margin-top:2px;">Waarschijnlijk doordat <strong>' . esc($d['topo']['cause']) . '</strong> niet bereikbaar is.</div>' : '')
        . '</td>'
        . '<td valign="top" align="right" style="' . $font . 'font-size:13px;color:' . $red . ';white-space:nowrap;padding-left:10px;">' . esc($sinceT($d['offline_since'])) . '<div style="color:#8a90a0;">' . esc($dur($d['offline_since'])) . '</div></td>'
        . '</tr></table></td></tr>';
};
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light only">
<title>Storing in uw netwerk</title>
</head>
<body style="margin:0;padding:0;background:#eef0f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f4;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">

<?php if ($test) : ?>
<tr><td style="background:#fff3cd;color:#664d03;padding:10px 28px;<?= $font ?>font-size:13px;">
    <strong>TESTMAIL</strong> &middot; In de live-stand gaat deze mail naar <?= esc($customer['email'] ?: '(geen e-mailadres bij de klant)') ?>.
</td></tr>
<?php endif ?>

<tr><td style="padding:22px 28px 18px;border-bottom:4px solid <?= $navy ?>;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
        <td valign="middle"><img src="<?= htmlspecialchars($logo, ENT_QUOTES) ?>" alt="bongersonline" width="170" style="display:block;width:170px;max-width:170px;height:auto;border:0;"></td>
        <td valign="middle" align="right" style="<?= $font ?>font-size:12px;color:#8a90a0;"><?= date('d-m-Y H:i') ?></td>
    </tr></table>
</td></tr>

<tr><td style="padding:26px 28px 6px;<?= $font ?>">
    <div style="display:inline-block;background:#fdecee;color:<?= $red ?>;font-size:12px;font-weight:700;letter-spacing:.04em;padding:4px 10px;border-radius:20px;">STORING</div>
    <h1 style="margin:12px 0 8px;font-size:22px;line-height:1.25;color:<?= $navy ?>;"><?= count($all) === 1 ? 'Eén apparaat in uw netwerk is niet bereikbaar' : count($all) . ' apparaten in uw netwerk zijn niet bereikbaar' ?></h1>
    <p style="margin:0 0 6px;font-size:15px;line-height:1.55;color:#1d2433;"><?= esc($greet) ?>,</p>
    <p style="margin:0;font-size:15px;line-height:1.55;color:#1d2433;">
        Wij zien dat <?= count($all) === 1 ? 'het volgende apparaat' : 'de volgende apparaten' ?> van <?= esc($customer['name']) ?> sinds <?= date('H:i', $first) ?> geen verbinding <?= count($all) === 1 ? 'heeft' : 'hebben' ?>.
        Wij zijn hiervan op de hoogte.
    </p>
</td></tr>

<tr><td style="padding:12px 28px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <?php foreach ($new as $d) : ?><?= $row($d, false) ?><?php endforeach ?>
    </table>
</td></tr>

<?php if ($still !== []) : ?>
<tr><td style="padding:14px 28px 0;<?= $font ?>font-size:13px;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.04em;">Nog steeds niet bereikbaar</td></tr>
<tr><td style="padding:0 28px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <?php foreach ($still as $d) : ?><?= $row($d, true) ?><?php endforeach ?>
    </table>
</td></tr>
<?php endif ?>

<tr><td style="padding:18px 28px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6fb;border-left:4px solid <?= $navy ?>;border-radius:8px;">
        <tr><td style="padding:14px 16px;<?= $font ?>font-size:14px;line-height:1.55;color:#1d2433;">
            <strong style="color:<?= $navy ?>;">Wat kunt u zelf controleren?</strong><br>
            <?= $tip ?>
        </td></tr>
    </table>
</td></tr>

<tr><td style="padding:18px 28px 8px;<?= $font ?>font-size:15px;line-height:1.55;color:#1d2433;">
    <?php if ($settings['pdf']) : ?>In de bijlage vindt u een overzicht van uw netwerk; wat niet bereikbaar is, staat in rood.<br><br><?php endif ?>
    Is alles weer in orde of heeft u vragen? Beantwoord deze mail<?= $settings['phone'] !== '' ? ' of bel ons op <a href="tel:' . esc(preg_replace('/[^\d+]/', '', $settings['phone']), 'attr') . '" style="color:' . $navy . ';font-weight:600;text-decoration:none;">' . esc($settings['phone']) . '</a>' : '' ?>.
</td></tr>

<tr><td style="padding:8px 28px 26px;<?= $font ?>font-size:15px;color:#1d2433;">
    Met vriendelijke groet,<br><strong style="color:<?= $navy ?>;">bongersonline</strong>
</td></tr>

<tr><td style="background:<?= $navy ?>;padding:14px 28px;<?= $font ?>font-size:12px;line-height:1.5;color:#c9cdf0;">
    Deze melding is automatisch verstuurd omdat wij uw netwerk bewaken.
    U krijgt per storing één bericht.
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
