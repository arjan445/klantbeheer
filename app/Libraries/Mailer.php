<?php

namespace App\Libraries;

use RuntimeException;
use Throwable;

/**
 * Mail versturen, op één van drie manieren (Beheer › E-mail):
 *  - php:   de mailserver van de webhost (CodeIgniter, instellingen uit .env)
 *  - smtp:  een eigen SMTP-server
 *  - graph: Office 365 via Microsoft Graph (app-registratie in Entra, recht
 *           Mail.Send). Werkt ook als Microsoft SMTP met wachtwoord uitzet.
 *
 * Bijlagen: ['name' => ..., 'content' => bytes, 'type' => mime, 'inline' => bool, 'cid' => ...].
 * Een inline bijlage zet je in de HTML als <img src="cid:{cid}">.
 */
class Mailer
{
    public const TRANSPORTS = ['php' => 'Mailserver van de webhost', 'smtp' => 'SMTP-server', 'graph' => 'Office 365 (Microsoft Graph)'];

    public static function settings(): array
    {
        $g = static fn (string $k, string $d = '') => (string) Setting::get('mail.' . $k, $d);

        return [
            'transport'   => array_key_exists($g('transport'), self::TRANSPORTS) ? $g('transport') : 'php',
            'from'        => $g('from'),
            'fromName'    => $g('fromName', 'bongersonline'),
            'smtpHost'    => $g('smtpHost'),
            'smtpPort'    => (int) $g('smtpPort', '587') ?: 587,
            'smtpCrypto'  => match ($g('smtpCrypto', 'tls')) { 'ssl' => 'ssl', 'none' => '', default => 'tls' },
            'smtpUser'    => $g('smtpUser'),
            'graphTenant' => $g('graphTenant'),
            'graphClient' => $g('graphClient'),
            'graphSender' => $g('graphSender'),
        ];
    }

    /** Afzender zoals die in de mail staat */
    public static function fromAddress(array $s): string
    {
        $from = $s['from'] !== '' ? $s['from'] : ($s['transport'] === 'graph' ? $s['graphSender'] : (string) config('Email')->fromEmail);

        return $from;
    }

    /**
     * @param list<string> $to
     */
    public static function send(array $to, string $subject, string $html, array $opt = []): void
    {
        $to = array_values(array_filter(array_map('trim', $to), static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        if ($to === []) {
            throw new RuntimeException('Geen geldig e-mailadres om naar te sturen.');
        }
        $s = self::settings();
        if ($s['transport'] === 'graph') {
            self::viaGraph($s, $to, $subject, $html, $opt);

            return;
        }
        self::viaCodeIgniter($s, $to, $subject, $html, $opt);
    }

    // ------------------------------------------------------------------ Webhost of SMTP

    private static function viaCodeIgniter(array $s, array $to, string $subject, string $html, array $opt): void
    {
        $cfg = config('Email');
        $c   = [];
        if ($s['transport'] === 'smtp') {
            if ($s['smtpHost'] === '') {
                throw new RuntimeException('Geen SMTP-server ingesteld (Beheer › E-mail).');
            }
            $c = [
                'protocol'   => 'smtp',
                'SMTPHost'   => $s['smtpHost'],
                'SMTPPort'   => $s['smtpPort'],
                'SMTPCrypto' => $s['smtpCrypto'],
                'SMTPUser'   => $s['smtpUser'],
                'SMTPPass'   => Network::secret('mail.smtpPass'),
                'SMTPTimeout' => 20,
            ];
        }
        // Basis: de instellingen uit .env/Config\Email, met SMTP eroverheen als dat gekozen is
        $email = \Config\Services::email(array_merge(get_object_vars($cfg), ['mailType' => 'html', 'charset' => 'UTF-8'], $c), false);
        $from  = self::fromAddress($s);
        if ($from !== '') {
            $email->setFrom($from, $s['fromName'] !== '' ? $s['fromName'] : $cfg->fromName);
        }
        $email->setTo($to);
        if (! empty($opt['replyTo']) && filter_var($opt['replyTo'], FILTER_VALIDATE_EMAIL)) {
            $email->setReplyTo($opt['replyTo']);
        }
        $email->setSubject($subject);
        foreach ($opt['attachments'] ?? [] as $a) {
            $email->attach($a['content'], ! empty($a['inline']) ? 'inline' : 'attachment', $a['name'], $a['type'] ?? 'application/octet-stream');
            if (! empty($a['inline']) && ! empty($a['cid'])) {
                $html = str_replace('cid:' . $a['cid'], 'cid:' . $email->setAttachmentCID($a['name']), $html);
            }
        }
        $email->setMessage($html);
        if (! empty($opt['text'])) {
            $email->setAltMessage($opt['text']);
        }
        if (! $email->send(false)) {
            throw new RuntimeException(trim(preg_replace('/\s+/', ' ', strip_tags($email->printDebugger(['headers'])))) ?: 'Versturen mislukt.');
        }
    }

    // ------------------------------------------------------------------ Office 365

    private static ?array $token = null;

    private static function graphToken(array $s): string
    {
        if (self::$token !== null && self::$token['exp'] > time() + 60) {
            return self::$token['value'];
        }
        $secret = Network::secret('mail.graphSecret');
        if ($s['graphTenant'] === '' || $s['graphClient'] === '' || $secret === '') {
            throw new RuntimeException('Office 365 is nog niet volledig ingesteld (tenant, client-ID en geheim bij Beheer › E-mail).');
        }
        $login = rtrim((string) env('graph.loginURL', 'https://login.microsoftonline.com'), '/');
        $res   = \Config\Services::curlrequest([], null, null, false)->post($login . '/' . rawurlencode($s['graphTenant']) . '/oauth2/v2.0/token', [
            'form_params' => [
                'client_id'     => $s['graphClient'],
                'client_secret' => $secret,
                'scope'         => 'https://graph.microsoft.com/.default',
                'grant_type'    => 'client_credentials',
            ],
            'http_errors' => false,
            'timeout'     => 20,
        ]);
        $data = json_decode((string) $res->getBody(), true);
        if ($res->getStatusCode() >= 400 || empty($data['access_token'])) {
            $msg = (string) ($data['error_description'] ?? $data['error'] ?? ('HTTP ' . $res->getStatusCode()));
            $msg = trim(strtok($msg, "\r\n")); // eerste regel, zonder trace-ID's
            throw new RuntimeException('Office 365 aanmelden mislukt: ' . $msg . self::hint($msg));
        }
        self::$token = ['value' => $data['access_token'], 'exp' => time() + (int) ($data['expires_in'] ?? 3000)];

        return self::$token['value'];
    }

    private static function hint(string $msg): string
    {
        return match (true) {
            str_contains($msg, 'AADSTS7000215') => ' (het geheim klopt niet; let op: de Waarde, niet de Geheim-ID)',
            str_contains($msg, 'AADSTS7000222') => ' (het geheim is verlopen; maak een nieuw geheim aan)',
            str_contains($msg, 'AADSTS700016')  => ' (deze client-ID bestaat niet in deze tenant)',
            str_contains($msg, 'AADSTS90002'), str_contains($msg, 'AADSTS900023') => ' (tenant-ID of domein klopt niet)',
            default => '',
        };
    }

    private static function viaGraph(array $s, array $to, string $subject, string $html, array $opt): void
    {
        $sender = $s['graphSender'] !== '' ? $s['graphSender'] : $s['from'];
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Vul bij Beheer › E-mail de mailbox in waaruit verstuurd wordt.');
        }
        $addr = static fn (string $a, ?string $name = null) => ['emailAddress' => array_filter(['address' => $a, 'name' => $name])];
        $msg  = [
            'subject'      => $subject,
            'body'         => ['contentType' => 'HTML', 'content' => $html],
            'toRecipients' => array_map($addr, $to),
        ];
        $from = self::fromAddress($s);
        if ($from !== '' && strcasecmp($from, $sender) !== 0) {
            $msg['from'] = $addr($from, $s['fromName'] ?: null); // vereist "Verzenden als" op die mailbox
        }
        if (! empty($opt['replyTo']) && filter_var($opt['replyTo'], FILTER_VALIDATE_EMAIL)) {
            $msg['replyTo'] = [$addr($opt['replyTo'])];
        }
        foreach ($opt['attachments'] ?? [] as $a) {
            $msg['attachments'][] = array_filter([
                '@odata.type'  => '#microsoft.graph.fileAttachment',
                'name'         => $a['name'],
                'contentType'  => $a['type'] ?? 'application/octet-stream',
                'contentBytes' => base64_encode($a['content']),
                'isInline'     => ! empty($a['inline']),
                'contentId'    => ! empty($a['inline']) ? ($a['cid'] ?? null) : null,
            ], static fn ($v) => $v !== null);
        }

        $base = rtrim((string) env('graph.baseURL', 'https://graph.microsoft.com'), '/');
        $res  = \Config\Services::curlrequest([], null, null, false)->post($base . '/v1.0/users/' . rawurlencode($sender) . '/sendMail', [
            'headers'     => ['Authorization' => 'Bearer ' . self::graphToken($s), 'Content-Type' => 'application/json'],
            'body'        => json_encode(['message' => $msg, 'saveToSentItems' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'http_errors' => false,
            'timeout'     => 30,
        ]);
        if ($res->getStatusCode() >= 300) {
            $data = json_decode((string) $res->getBody(), true);
            $msg  = (string) ($data['error']['message'] ?? ('HTTP ' . $res->getStatusCode()));
            $code = (string) ($data['error']['code'] ?? '');
            $hint = match (true) {
                $res->getStatusCode() === 403, $code === 'ErrorAccessDenied' => ' (geef de app het recht Mail.Send (Toepassing) en klik op "Beheerderstoestemming verlenen")',
                $code === 'ErrorInvalidUser', $code === 'MailboxNotEnabledForRESTAPI', $res->getStatusCode() === 404 => ' (mailbox ' . $sender . ' niet gevonden of heeft geen Exchange-licentie)',
                $code === 'ErrorSendAsDenied' => ' (geen "Verzenden als"-recht voor ' . $from . '; laat het afzenderadres leeg of geef dat recht)',
                default => '',
            };
            throw new RuntimeException('Office 365: ' . $msg . $hint);
        }
    }

    /** Testmail */
    public static function test(string $to): void
    {
        $s    = self::settings();
        $html = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:15px;color:#1d2433">'
            . '<p>Deze testmail komt uit klantbeheer.</p><p>Verzonden via: <strong>' . esc(self::TRANSPORTS[$s['transport']]) . '</strong>'
            . ($s['transport'] === 'graph' ? ' (mailbox ' . esc($s['graphSender'] ?: $s['from']) . ')' : '') . '</p>'
            . '<p style="color:#8a90a0;font-size:13px">' . date('d-m-Y H:i') . '</p></div>';
        try {
            self::send([$to], 'Testmail klantbeheer', $html, ['text' => 'Deze testmail komt uit klantbeheer.']);
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
