<?php

namespace App\Controllers;

use App\Libraries\Mailer;
use App\Libraries\Network;
use App\Libraries\Setting;
use Throwable;

/** Beheer › E-mail: hoe klantbeheer mail verstuurt */
class MailSettings extends BaseController
{
    public function index()
    {
        if ($this->request->is('post')) {
            $p   = fn (string $k, int $max = 255) => mb_substr(trim((string) $this->request->getPost($k)), 0, $max);
            $tr  = $p('transport');
            Setting::set('mail.transport', array_key_exists($tr, Mailer::TRANSPORTS) ? $tr : 'php');
            Setting::set('mail.from', filter_var($p('from'), FILTER_VALIDATE_EMAIL) ? $p('from') : '');
            Setting::set('mail.fromName', $p('fromName', 100));
            Setting::set('mail.smtpHost', $p('smtpHost'));
            Setting::set('mail.smtpPort', (string) max(1, min(65535, (int) ($p('smtpPort') ?: 587))));
            Setting::set('mail.smtpCrypto', in_array($p('smtpCrypto'), ['tls', 'ssl', 'none'], true) ? $p('smtpCrypto') : 'tls');
            Setting::set('mail.smtpUser', $p('smtpUser'));
            Network::saveSecretIf('mail.smtpPass', $this->request->getPost('smtpPass'), (bool) $this->request->getPost('smtpPass_clear'));
            Setting::set('mail.graphTenant', $p('graphTenant', 100));
            Setting::set('mail.graphClient', $p('graphClient', 100));
            Setting::set('mail.graphSender', filter_var($p('graphSender'), FILTER_VALIDATE_EMAIL) ? $p('graphSender') : '');
            Network::saveSecretIf('mail.graphSecret', $this->request->getPost('graphSecret'), (bool) $this->request->getPost('graphSecret_clear'));

            return $this->back('Opgeslagen.', 'message', site_url('beheer/mail'));
        }

        return $this->page('settings/mail', [
            's'         => Mailer::settings(),
            'smtpSet'   => Network::secret('mail.smtpPass') !== '',
            'graphSet'  => Network::secret('mail.graphSecret') !== '',
            'me'        => (string) (auth()->user()->email ?? ''),
            'secure'    => \App\Libraries\Vault::ready(),
            'fallback'  => (string) config('Email')->fromEmail,
        ], 'E-mail');
    }

    public function test()
    {
        $to = trim((string) $this->request->getPost('to'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->back('Vul een geldig e-mailadres in.', 'error', site_url('beheer/mail'));
        }
        try {
            Mailer::test($to);
        } catch (Throwable $e) {
            return $this->back('Testmail niet verstuurd: ' . $e->getMessage(), 'error', site_url('beheer/mail'));
        }

        return $this->back('Testmail verstuurd naar ' . $to . '. Kijk ook even in de spammap.', 'message', site_url('beheer/mail'));
    }
}
