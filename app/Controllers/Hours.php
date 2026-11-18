<?php

namespace App\Controllers;

use App\Libraries\Mileage;
use App\Libraries\Moneybird;
use App\Libraries\Setting;
use App\Models\CustomerModel;
use App\Models\SiteModel;
use App\Models\TimeEntryModel;
use App\Models\WorkDayModel;
use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Urenregistratie per klant/locatie → Moneybird-uren,
 * en per werkdag de kilometers als memoriaalboeking.
 */
class Hours extends BaseController
{
    private const TZ = 'Europe/Amsterdam';

    public function index()
    {
        $month = (string) $this->request->getGet('maand');
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $from = $month . '-01';
        $to   = date('Y-m-t', strtotime($from));

        $entries = model(TimeEntryModel::class)
            ->select('time_entries.*, customers.name AS customer_name, sites.name AS site_name, sites.city AS site_city')
            ->join('customers', 'customers.id = time_entries.customer_id')
            ->join('sites', 'sites.id = time_entries.site_id', 'left')
            ->where('date >=', $from)->where('date <=', $to)
            ->orderBy('date', 'DESC')->orderBy('start_time')->findAll();

        $days = [];
        foreach (model(WorkDayModel::class)->where('date >=', $from)->where('date <=', $to)->findAll() as $d) {
            $days[$d['date']] = $d;
        }
        $byDay = [];
        $totalMin = 0;
        foreach ($entries as $e) {
            $e['minutes']         = TimeEntryModel::minutes($e);
            $totalMin            += $e['minutes'];
            $byDay[$e['date']][] = $e;
        }
        $km = array_sum(array_map(static fn ($d) => (float) $d['km'], $days));
        $amount = array_sum(array_map(static fn ($d) => (float) $d['amount'], $days));
        $open = count(array_filter($days, static fn ($d) => $d['date'] <= date('Y-m-d') && Mileage::needsBooking($d)));

        return $this->page('hours/index', [
            'month' => $month, 'byDay' => $byDay, 'days' => $days, 'totalMin' => $totalMin, 'km' => $km, 'amount' => $amount, 'open' => $open,
            'mb' => new Moneybird(), 'missing' => Mileage::missing(), 'orsReady' => Mileage::apiKey() !== '' && Mileage::home() !== '',
        ], 'Uren');
    }

    public function create()
    {
        $date  = (string) ($this->request->getGet('datum') ?: date('Y-m-d'));
        $last  = model(TimeEntryModel::class)->where('date', $date)->orderBy('end_time', 'DESC')->first();
        $entry = [
            'customer_id'   => (int) $this->request->getGet('klant') ?: null,
            'site_id'       => (int) $this->request->getGet('locatie') ?: null,
            'date'          => $date,
            'start_time'    => $last ? substr($last['end_time'], 0, 5) : '08:00',
            'end_time'      => $date === date('Y-m-d') ? date('H:i', (int) (round(time() / 900) * 900)) : '',
            'pause_minutes' => 0,
            'description'   => '',
            'billable'      => 1,
            'travel'        => 1,
        ];
        if ($last && ! $entry['customer_id']) {
            $entry['customer_id'] = (int) $last['customer_id'];
            $entry['site_id']     = $last['site_id'];
        }

        return $this->form($entry, true);
    }

    public function edit($id)
    {
        return $this->form($this->findOr404(TimeEntryModel::class, $id), false);
    }

    private function form(array $entry, bool $isNew)
    {
        if ($this->request->is('post')) {
            return $this->save($entry, $isNew);
        }
        $customers = model(CustomerModel::class)->select('id, name, internal')->where('active', 1)->orderBy('internal')->orderBy('name')->findAll();
        $sites     = [];
        foreach (model(SiteModel::class)->select('id, customer_id, name, address, zipcode, city')->orderBy('name')->findAll() as $s) {
            $sites[$s['customer_id']][] = ['id' => (int) $s['id'], 'name' => $s['name'], 'addr' => Mileage::siteAddress($s)];
        }

        return $this->page('hours/form', compact('entry', 'isNew', 'customers', 'sites'), $isNew ? 'Uren boeken' : 'Uren wijzigen');
    }

    private function save(array $old, bool $isNew)
    {
        $p    = $this->request->getPost();
        $time = static fn ($v) => preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim((string) $v)) ? trim((string) $v) : null;
        $data = [
            'customer_id'   => (int) ($p['customer_id'] ?? 0),
            'site_id'       => (int) ($p['site_id'] ?? 0) ?: null,
            'date'          => (string) ($p['date'] ?? ''),
            'start_time'    => $time($p['start_time'] ?? ''),
            'end_time'      => $time($p['end_time'] ?? ''),
            'pause_minutes' => max(0, min(1440, (int) ($p['pause_minutes'] ?? 0))),
            'description'   => mb_substr(trim((string) ($p['description'] ?? '')), 0, 500),
            'billable'      => empty($p['billable']) ? 0 : 1,
            'travel'        => empty($p['travel']) ? 0 : 1,
        ];

        $errors = [];
        if (! model(CustomerModel::class)->find($data['customer_id'])) {
            $errors[] = 'Kies een klant.';
        }
        if ($data['site_id'] && ! model(SiteModel::class)->where('customer_id', $data['customer_id'])->find($data['site_id'])) {
            $data['site_id'] = null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['date'])) {
            $errors[] = 'Vul een geldige datum in.';
        }
        if (! $data['start_time'] || ! $data['end_time']) {
            $errors[] = 'Vul een start- en stoptijd in (uu:mm).';
        } elseif (TimeEntryModel::minutes($data) < 1) {
            $errors[] = 'De pauze is langer dan de gewerkte tijd.';
        }
        if ($data['description'] === '') {
            $errors[] = 'Vul een omschrijving in (komt ook in Moneybird).';
        }
        if ($errors) {
            return redirect()->back()->withInput()->with('error', implode(' ', $errors));
        }

        $model = model(TimeEntryModel::class);
        if ($isNew) {
            $data['user_id'] = auth()->id();
            $id = (int) $model->insert($data);
        } else {
            $id = (int) $old['id'];
            $model->update($id, $data);
        }

        $notes = [$this->syncEntry($id)];
        $dates = array_unique(array_filter([$data['date'], $isNew ? null : $old['date']]));
        foreach ($dates as $d) {
            $notes[] = $this->dayChanged($d);
        }
        $msg = 'Uren opgeslagen (' . self::hm(TimeEntryModel::minutes($data)) . ').';
        $warn = implode(' ', array_filter($notes));

        if (($p['after'] ?? '') === 'next') {
            $this->session->setFlashdata($warn ? 'error' : 'message', trim($msg . ' ' . $warn));

            return redirect()->to(site_url('uren/nieuw?datum=' . $data['date']));
        }

        return $this->back(trim($msg . ' ' . $warn), $warn ? 'error' : 'message', site_url('uren?maand=' . substr($data['date'], 0, 7) . '#dag-' . $data['date']));
    }

    public function delete($id)
    {
        $e = $this->findOr404(TimeEntryModel::class, $id);
        $warn = '';
        if ($e['moneybird_id']) {
            try {
                (new Moneybird())->deleteTimeEntry($e['moneybird_id']);
            } catch (Throwable $ex) {
                $warn = 'In Moneybird niet verwijderd: ' . $ex->getMessage();
            }
        }
        model(TimeEntryModel::class)->delete($id);
        $warn .= ' ' . $this->dayChanged($e['date']);

        return $this->back(trim('Uren verwijderd. ' . $warn), trim($warn) ? 'error' : 'message', site_url('uren?maand=' . substr($e['date'], 0, 7)));
    }

    /** Urenregel naar Moneybird; geeft een waarschuwing terug (of leeg) */
    private function syncEntry(int $id): string
    {
        $mb     = new Moneybird();
        $userId = (string) Setting::get('moneybird.userId', '');
        if (! $mb->configured() || $userId === '') {
            return $mb->configured() ? 'Niet naar Moneybird: kies eerst je Moneybird-gebruiker bij Beheer → Uren & kilometers.' : '';
        }
        $e = model(TimeEntryModel::class)
            ->select('time_entries.*, customers.moneybird_contact_id, sites.name AS site_name')
            ->join('customers', 'customers.id = time_entries.customer_id')
            ->join('sites', 'sites.id = time_entries.site_id', 'left')
            ->where('time_entries.id', $id)->first();

        $tz    = new DateTimeZone(self::TZ);
        $start = new DateTime($e['date'] . ' ' . $e['start_time'], $tz);
        $end   = new DateTime($e['date'] . ' ' . $e['end_time'], $tz);
        if ($end <= $start) {
            $end->modify('+1 day');
        }
        $entry = [
            'user_id'         => $userId,
            'started_at'      => $start->format(DATE_ATOM),
            'ended_at'        => $end->format(DATE_ATOM),
            'paused_duration' => (int) $e['pause_minutes'] * 60,
            'description'     => mb_substr($e['description'] . ($e['site_name'] ? ' – ' . $e['site_name'] : '') . ' – ' . Mileage::reference($e['date']), 0, 500),
            'billable'        => (bool) $e['billable'],
        ];
        if ($e['moneybird_contact_id']) {
            $entry['contact_id'] = $e['moneybird_contact_id'];
        }
        try {
            $mbId = $mb->saveTimeEntry($e['moneybird_id'] ?: null, $entry);
            model(TimeEntryModel::class)->update($id, ['moneybird_id' => $mbId, 'sync_error' => null]);

            return '';
        } catch (Throwable $ex) {
            model(TimeEntryModel::class)->update($id, ['sync_error' => $ex->getMessage()]);

            return 'Moneybird-uren: ' . $ex->getMessage();
        }
    }

    /** Dag herberekenen en (bij automatisch boeken) de memoriaalboeking bijwerken */
    private function dayChanged(string $date): string
    {
        try {
            $day = Mileage::recalc($date);
            if ($day['error']) {
                return 'Kilometers ' . date('d-m', strtotime($date)) . ': ' . $day['error'];
            }
            if (Mileage::autoBook() && Mileage::missing() === [] && Mileage::needsBooking($day)) {
                Mileage::book($day);
            }
        } catch (Throwable $e) {
            return 'Memoriaal ' . date('d-m', strtotime($date)) . ': ' . $e->getMessage();
        }

        return '';
    }

    /** Kilometers van een dag handmatig aanpassen (leeg = weer automatisch) */
    public function dayKm($date)
    {
        $this->validDate($date);
        $day = Mileage::recalc($date);
        $km  = trim((string) $this->request->getPost('km'));
        if ($km === '') {
            model(WorkDayModel::class)->update($day['id'], ['km_manual' => 0, 'signature' => null]);
        } else {
            $km = round(max(0, (float) str_replace(',', '.', $km)), 1);
            model(WorkDayModel::class)->update($day['id'], ['km' => $km, 'km_manual' => 1, 'amount' => round($km * (float) $day['rate'], 2)]);
        }
        $warn = $this->dayChanged($date);

        return $this->back($warn ?: 'Kilometers bijgewerkt.', $warn ? 'error' : 'message', site_url('uren?maand=' . substr($date, 0, 7) . '#dag-' . $date));
    }

    /** Dag opnieuw berekenen en boeken */
    public function dayBook($date)
    {
        $this->validDate($date);
        try {
            $day = Mileage::recalc($date);
            if ($day['error']) {
                throw new \RuntimeException($day['error']);
            }
            $day = Mileage::book($day);
            $msg = $day['journal_id'] ? 'Memoriaalboeking ' . Mileage::reference($date) . ' staat in Moneybird.' : 'Geen kilometers op deze dag; er staat geen boeking.';

            return $this->back($msg, 'message', site_url('uren?maand=' . substr($date, 0, 7) . '#dag-' . $date));
        } catch (Throwable $e) {
            return $this->back('Boeken mislukt: ' . $e->getMessage(), 'error', site_url('uren?maand=' . substr($date, 0, 7) . '#dag-' . $date));
        }
    }

    /** Alle openstaande dagen van een maand (t/m vandaag) boeken */
    public function monthBook()
    {
        $month = (string) $this->request->getPost('maand');
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $this->back('Ongeldige maand.', 'error', site_url('uren'));
        }
        $done = 0;
        $errors = [];
        $dates = array_column(model(TimeEntryModel::class)->select('date')->distinct()->where('date >=', $month . '-01')->where('date <=', min(date('Y-m-d'), date('Y-m-t', strtotime($month . '-01'))))->findAll(), 'date');
        foreach ($dates as $d) {
            try {
                $day = Mileage::recalc($d);
                if ($day['error']) {
                    throw new \RuntimeException($day['error']);
                }
                if (Mileage::needsBooking($day)) {
                    Mileage::book($day);
                    $done++;
                }
            } catch (Throwable $e) {
                $errors[] = date('d-m', strtotime($d)) . ': ' . $e->getMessage();
            }
        }
        $msg = $done . ' dag(en) geboekt in Moneybird.' . ($errors ? ' Fouten: ' . implode(' · ', $errors) : '');

        return $this->back($msg, $errors ? 'error' : 'message', site_url('uren?maand=' . $month));
    }

    /** Uren die niet in Moneybird staan alsnog versturen */
    public function resync($id)
    {
        $e    = $this->findOr404(TimeEntryModel::class, $id);
        $warn = $this->syncEntry((int) $id);

        return $this->back($warn ?: 'Uren staan in Moneybird.', $warn ? 'error' : 'message', site_url('uren?maand=' . substr($e['date'], 0, 7) . '#dag-' . $e['date']));
    }

    // ------------------------------------------------------------------ Instellingen

    public function settings()
    {
        $keys = ['moneybird.userId', 'km.ledgerCost', 'km.ledgerPrivate', 'km.home', 'km.rate', 'km.autoBook', 'openroute.apiKey'];
        if ($this->request->is('post')) {
            foreach ($keys as $k) {
                $field = str_replace('.', '_', $k);
                $v     = trim((string) $this->request->getPost($field));
                if ($k === 'openroute.apiKey' && $v === '' && ! $this->request->getPost('openroute_clear')) {
                    continue; // leeg laten = bestaande sleutel houden
                }
                if ($k === 'km.rate') {
                    $v = $v === '' ? '' : (string) round((float) str_replace(',', '.', $v), 3);
                }
                if ($k === 'km.autoBook') {
                    $v = $v === '1' ? '1' : '0';
                }
                Setting::set($k, mb_substr($v, 0, 500));
            }

            return $this->back('Instellingen opgeslagen.', 'message', site_url('beheer/uren'));
        }

        $mb   = new Moneybird();
        $data = ['mb' => $mb, 'users' => [], 'ledgers' => [], 'error' => null, 'values' => []];
        foreach ($keys as $k) {
            $data['values'][$k] = (string) Setting::get($k, '');
        }
        if ($data['values']['km.rate'] === '') {
            $data['values']['km.rate'] = (string) Mileage::DEFAULT_RATE;
        }
        if ($mb->configured()) {
            try {
                $data['users']   = $mb->users();
                $data['ledgers'] = $mb->ledgerAccounts();
            } catch (Throwable $e) {
                $data['error'] = $e->getMessage();
            }
        }

        return $this->page('hours/settings', $data, 'Uren & kilometers');
    }

    // ------------------------------------------------------------------

    private function validDate($date): void
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    public static function hm(int $minutes): string
    {
        return intdiv($minutes, 60) . ':' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . ' u';
    }
}
