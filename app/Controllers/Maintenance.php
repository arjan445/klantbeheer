<?php

namespace App\Controllers;

use App\Libraries\Setting;
use App\Libraries\Vault;
use Throwable;

/**
 * Onderhoud (beheerders): wat je anders met `php spark` op de server doet,
 * voor hosting zonder commandoregel (Plesk).
 *
 *   GET  beheer/onderhoud                 status: sleutel, migraties, geplande taken
 *   POST beheer/onderhoud/migreren        openstaande migraties uitvoeren (alle namespaces)
 *   POST beheer/onderhoud/cron-sleutel    nieuwe geheime sleutel voor de cron-links
 *   GET  cron/{sleutel}/{taak}            taak uitvoeren via een URL (Plesk: "URL ophalen")
 */
class Maintenance extends BaseController
{
    /** Taken die via een cron-link mogen draaien: naam in de URL => spark-commando */
    public const TASKS = [
        'netwerk'     => ['klantbeheer:netwerk', 'UniFi en UISP ophalen', '*/5 * * * *', 'elke 5 minuten'],
        'herinnering' => ['klantbeheer:herinnering', 'Mail met abonnementen die gefactureerd moeten worden', '0 7 * * 1', 'maandag 07:00'],
        'wimood'      => ['klantbeheer:wimood', 'Wimood-producten en inkoopprijzen', '20 3 * * *', 'elke nacht 03:20'],
    ];

    public function index()
    {
        $migrations = $this->migrations();

        return $this->page('maintenance/index', [
            'hasKey'     => Vault::ready(),
            'newKeyLine' => Vault::ready() ? null : $this->suggestedKeyLine(),
            'migrations' => $migrations,
            'pending'    => array_values(array_filter($migrations, static fn ($m) => ! $m['done'])),
            'token'      => $this->token(),
            'tasks'      => self::TASKS,
            'lastRuns'   => json_decode((string) Setting::get('cron.lastRuns', ''), true) ?: [],
            'php'        => PHP_VERSION,
            'root'       => rtrim(ROOTPATH, '/'),
        ], 'Onderhoud');
    }

    /**
     * Alle openstaande migraties (Klantbeheer, Shield, Settings). Ze voegen alleen
     * tabellen en kolommen toe; bestaande gegevens blijven staan.
     */
    public function migrate()
    {
        $before = array_column(array_filter($this->migrations(), static fn ($m) => ! $m['done']), 'name');
        $runner = service('migrations');
        $runner->setNamespace(null);
        $runner->setSilent(true);
        @set_time_limit(300);

        try {
            $ok = $runner->latest();
        } catch (Throwable $e) {
            log_message('error', 'Migreren via Onderhoud mislukt: ' . $e->getMessage());

            return $this->back('Bijwerken is mislukt: ' . $e->getMessage(), 'error', site_url('beheer/onderhoud'));
        }

        if ($ok === false) {
            return $this->back('Bijwerken is mislukt: ' . implode(' ', array_map('strip_tags', $runner->getCliMessages())), 'error', site_url('beheer/onderhoud'));
        }

        return $this->back($before === [] ? 'De database was al bijgewerkt.' : 'Database bijgewerkt: ' . implode(', ', $before) . '.', 'message', site_url('beheer/onderhoud'));
    }

    /** Nieuwe cron-sleutel: de oude links werken dan niet meer */
    public function newToken()
    {
        Setting::set('cron.token', bin2hex(random_bytes(20)));

        return $this->back('Nieuwe sleutel gemaakt. Pas de geplande taken in Plesk aan met de nieuwe links.', 'message', site_url('beheer/onderhoud'));
    }

    /**
     * Taak uitvoeren via een URL, zonder in te loggen. Beveiligd met de geheime
     * sleutel in de link (lang en willekeurig; vergelijken in constante tijd).
     */
    public function cron($token, $task)
    {
        $expected = (string) Setting::get('cron.token', '');
        if ($expected === '' || ! is_string($token) || ! hash_equals($expected, $token) || ! isset(self::TASKS[$task])) {
            return $this->response->setStatusCode(404)->setBody('Niet gevonden');
        }

        // Niet twee keer tegelijk dezelfde taak
        $lock = @fopen(WRITEPATH . 'cron-' . $task . '.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return $this->response->setStatusCode(409)->setBody('Taak loopt al');
        }
        @set_time_limit(280);
        ignore_user_abort(true);

        $start = microtime(true);
        try {
            $out = (string) command(self::TASKS[$task][0]);
            $ok  = true;
        } catch (Throwable $e) {
            $out = $e->getMessage();
            $ok  = false;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $out = trim(preg_replace('/\e\[[\d;]*m/', '', $out)); // kleurcodes van de commandoregel eruit

        $runs        = json_decode((string) Setting::get('cron.lastRuns', ''), true) ?: [];
        $runs[$task] = ['at' => date('Y-m-d H:i:s'), 'ok' => $ok, 'seconds' => round(microtime(true) - $start, 1), 'output' => mb_substr($out, -600)];
        Setting::set('cron.lastRuns', json_encode($runs, JSON_UNESCAPED_UNICODE));

        return $this->response->setStatusCode($ok ? 200 : 500)->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setHeader('Cache-Control', 'no-store')->setBody(($ok ? 'OK' : 'FOUT') . "\n" . $out);
    }

    // ------------------------------------------------------------------

    /** @return list<array{name:string, version:string, namespace:string, done:bool}> */
    private function migrations(): array
    {
        $runner = service('migrations');
        $runner->setNamespace(null);

        try {
            $runner->ensureTable();
            $done = [];
            foreach ($runner->getHistory() as $h) {
                $done[$h->namespace . '|' . $h->version] = true;
            }
        } catch (Throwable $e) {
            $done = [];
        }

        $list = [];
        foreach ($runner->findMigrations() as $m) {
            $list[] = [
                'name'      => $m->name,
                'version'   => $m->version,
                'namespace' => $m->namespace,
                'done'      => isset($done[$m->namespace . '|' . $m->version]),
            ];
        }

        return $list;
    }

    private function token(): string
    {
        $t = (string) Setting::get('cron.token', '');
        if ($t === '') {
            $t = bin2hex(random_bytes(20));
            Setting::set('cron.token', $t);
        }

        return $t;
    }

    /** Willekeurige sleutelregel voor .env, dezelfde tot hij in gebruik is */
    private function suggestedKeyLine(): string
    {
        $line = $this->session->get('suggestedEncryptionKey');
        if (! is_string($line)) {
            $line = 'encryption.key = hex2bin:' . bin2hex(random_bytes(32));
            $this->session->set('suggestedEncryptionKey', $line);
        }

        return $line;
    }
}
