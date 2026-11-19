<?php

namespace App\Controllers;

use App\Libraries\Setting;
use App\Models\CustomerModel;
use App\Models\TodoModel;
use DateTime;
use DateTimeZone;

/**
 * Takenlijst: op het dashboard, per klant en als iCal-feed voor je agenda.
 */
class Todos extends BaseController
{
    private const TZ = 'Europe/Amsterdam';

    public function index()
    {
        $showDone = (bool) $this->request->getGet('afgerond');
        $model    = model(TodoModel::class);
        $data     = [
            'open'      => $model->open(),
            'done'      => $showDone ? $model->select('todos.*, customers.name AS customer_name')->join('customers', 'customers.id = todos.customer_id', 'left')
                ->where('done', 1)->orderBy('done_at', 'DESC')->findAll(100) : [],
            'showDone'  => $showDone,
            'feedUrl'   => site_url('agenda/' . self::token() . '.ics'),
            'customers' => self::customerOptions(),
        ];

        return $this->page('todos/index', $data, 'Taken');
    }

    /** Snel toevoegen (dashboard, klantpagina, takenpagina) */
    public function create()
    {
        $data = $this->input();
        if ($data['title'] === '') {
            return redirect()->back()->with('error', 'Geef de taak een omschrijving.');
        }
        $data['created_by'] = auth()->id();
        model(TodoModel::class)->insert($data);

        return redirect()->back()->with('message', 'Taak toegevoegd.');
    }

    public function edit($id)
    {
        $todo = $this->findOr404(TodoModel::class, $id);
        if ($this->request->is('post')) {
            $data = $this->input();
            if ($data['title'] === '') {
                return redirect()->back()->withInput()->with('error', 'Geef de taak een omschrijving.');
            }
            model(TodoModel::class)->update($id, $data);

            return $this->back('Taak opgeslagen.', 'message', $this->returnTo());
        }

        return $this->page('todos/form', ['todo' => $todo, 'customers' => self::customerOptions(), 'returnTo' => $this->returnTo()], 'Taak wijzigen');
    }

    /** Afvinken / weer openzetten (ook via fetch, dan JSON terug) */
    public function toggle($id)
    {
        $todo = $this->findOr404(TodoModel::class, $id);
        $done = $this->request->getPost('done') !== null ? (bool) $this->request->getPost('done') : ! $todo['done'];
        model(TodoModel::class)->update($id, ['done' => $done ? 1 : 0, 'done_at' => $done ? date('Y-m-d H:i:s') : null]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true, 'done' => $done, 'csrf' => csrf_hash()]);
        }

        return redirect()->back()->with('message', $done ? 'Taak afgerond.' : 'Taak weer open.');
    }

    public function delete($id)
    {
        $this->findOr404(TodoModel::class, $id);
        model(TodoModel::class)->delete($id);

        return $this->back('Taak verwijderd.', 'message', $this->returnTo());
    }

    /** Nieuwe geheime agenda-link (de oude werkt dan niet meer) */
    public function newToken()
    {
        Setting::set('ical.token', bin2hex(random_bytes(20)));

        return $this->back('Nieuwe agenda-link gemaakt. Vervang de link in je agenda-app.', 'message', site_url('taken#agenda'));
    }

    // ------------------------------------------------------------------ iCal

    /** Openbare feed (zonder inloggen), beveiligd met de geheime sleutel in de link */
    public function ical($token)
    {
        $token = (string) preg_replace('/\.ics$/', '', (string) $token);
        if (! hash_equals(self::token(), $token)) {
            return $this->response->setStatusCode(404)->setBody('Niet gevonden');
        }

        $tz    = new DateTimeZone(self::TZ);
        $utc   = new DateTimeZone('UTC');
        $today = date('Y-m-d');
        $host  = parse_url(base_url(), PHP_URL_HOST) ?: 'klantbeheer';
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//bongersonline//Klantbeheer//NL', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:Taken (bongersonline)', 'X-WR-TIMEZONE:' . self::TZ,
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H',
        ];

        foreach (model(TodoModel::class)->open() as $t) {
            if (! $t['due_date']) {
                continue; // zonder datum hoort het niet in de agenda
            }
            // Te laat? Dan schuift hij mee naar vandaag, zodat je hem blijft zien
            $late = $t['due_date'] < $today;
            $date = $late ? $today : $t['due_date'];

            $summary = ($late ? '⚠ ' : '') . $t['title'] . ($t['customer_name'] ? ' – ' . $t['customer_name'] : '');
            $desc    = implode("\n\n", array_filter([
                $late ? 'Was gepland op ' . date('d-m-Y', strtotime($t['due_date'])) . '.' : '',
                trim((string) $t['notes']),
                site_url('taken/' . $t['id']),
            ]));
            $stamp   = (new DateTime($t['updated_at'] ?: $t['created_at'] ?: 'now', $tz))->setTimezone($utc)->format('Ymd\THis\Z');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:todo-' . $t['id'] . '@' . $host;
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'LAST-MODIFIED:' . $stamp;
            if ($t['due_time'] && ! $late) {
                $start   = new DateTime($date . ' ' . $t['due_time'], $tz);
                $end     = (clone $start)->modify('+' . max(5, (int) $t['duration']) . ' minutes');
                $lines[] = 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z');
                $lines[] = 'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z');
            } else {
                $lines[] = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($date));
                $lines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($date . ' +1 day'));
                $lines[] = 'TRANSP:TRANSPARENT'; // hele-dag-taak blokkeert je agenda niet
            }
            $lines[] = 'SUMMARY:' . self::esc($summary);
            $lines[] = 'DESCRIPTION:' . self::esc($desc);
            $lines[] = 'URL:' . site_url('taken/' . $t['id']);
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return $this->response
            ->setHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->setHeader('Content-Disposition', 'inline; filename="taken.ics"')
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setBody(implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n");
    }

    private static function esc(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', ''], $s);
    }

    /** Regels langer dan 75 bytes vouwen (RFC 5545), zonder UTF-8-tekens te breken */
    private static function fold(string $line): string
    {
        $out = '';
        $cur = '';
        foreach (mb_str_split($line) as $ch) {
            if (strlen($cur) + strlen($ch) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ") . $cur;
                $cur = '';
            }
            $cur .= $ch;
        }

        return $out === '' ? $cur : $out . "\r\n " . $cur;
    }

    // ------------------------------------------------------------------

    public static function token(): string
    {
        $t = (string) Setting::get('ical.token', '');
        if ($t === '') {
            $t = bin2hex(random_bytes(20));
            Setting::set('ical.token', $t);
        }

        return $t;
    }

    private function input(): array
    {
        $p    = $this->request->getPost();
        $date = (string) ($p['due_date'] ?? '');
        $time = trim((string) ($p['due_time'] ?? ''));
        $cust = (int) ($p['customer_id'] ?? 0);

        return [
            'title'       => mb_substr(trim((string) ($p['title'] ?? '')), 0, 200),
            'notes'       => array_key_exists('notes', $p) ? (trim((string) $p['notes']) ?: null) : null,
            'customer_id' => $cust && model(CustomerModel::class)->find($cust) ? $cust : null,
            'due_date'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            'due_time'    => preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $time : null,
            'duration'    => max(5, min(1440, (int) ($p['duration'] ?? 30) ?: 30)),
        ];
    }

    private function returnTo(): string
    {
        $r = (string) ($this->request->getGetPost('terug') ?? '');

        return str_starts_with($r, '/') && ! str_starts_with($r, '//') ? site_url(ltrim($r, '/')) : site_url('taken');
    }

    public static function customerOptions(): array
    {
        return model(CustomerModel::class)->select('id, name')->where('active', 1)->orderBy('name')->findAll();
    }

    /** Label + kleur voor de deadline */
    public static function dueLabel(?string $date, ?string $time = null): array
    {
        if (! $date) {
            return ['', 'secondary'];
        }
        $days = (int) round((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
        $wd   = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'][(int) date('w', strtotime($date))];
        $t    = $time ? ' ' . substr($time, 0, 5) : '';

        return match (true) {
            $days < 0   => [($days === -1 ? 'gisteren' : date('d-m', strtotime($date))) . $t, 'danger'],
            $days === 0 => ['vandaag' . $t, 'warning'],
            $days === 1 => ['morgen' . $t, 'primary'],
            $days < 7   => [$wd . ' ' . date('d-m', strtotime($date)) . $t, 'secondary'],
            default     => [date('d-m-Y', strtotime($date)) . $t, 'secondary'],
        };
    }
}
