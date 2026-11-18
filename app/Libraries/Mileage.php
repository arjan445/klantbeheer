<?php

namespace App\Libraries;

use App\Models\SiteModel;
use App\Models\TimeEntryModel;
use App\Models\WorkDayModel;
use RuntimeException;
use Throwable;

/**
 * Kilometers per werkdag: Thuis → klantlocatie(s) in volgorde van starttijd → Thuis,
 * berekend met OpenRouteService, en per dag één memoriaalboeking in Moneybird.
 */
class Mileage
{
    public const DEFAULT_RATE = 0.25; // fiscale kilometeraftrek ondernemer 2026

    public static function apiKey(): string
    {
        return (string) Setting::get('openroute.apiKey', '');
    }

    public static function home(): string
    {
        return trim((string) Setting::get('km.home', ''));
    }

    public static function rate(): float
    {
        return (float) str_replace(',', '.', (string) Setting::get('km.rate', (string) self::DEFAULT_RATE));
    }

    public static function autoBook(): bool
    {
        return (string) Setting::get('km.autoBook', '1') === '1';
    }

    /** Klaar om te boeken? Geeft een lijst met wat er nog mist. */
    public static function missing(): array
    {
        $m = [];
        if (! (new Moneybird())->configured()) {
            $m[] = 'Moneybird-koppeling';
        }
        if (! Setting::get('km.ledgerCost')) {
            $m[] = 'grootboekrekening voor de kilometerkosten';
        }
        if (! Setting::get('km.ledgerPrivate')) {
            $m[] = 'grootboekrekening privé';
        }

        return $m;
    }

    // ------------------------------------------------------------------ OpenRouteService

    private static function ors(string $method, string $url, ?array $body = null): array
    {
        if (self::apiKey() === '') {
            throw new RuntimeException('Geen OpenRouteService-sleutel ingesteld (Beheer → Uren & kilometers).');
        }
        $base = rtrim((string) env('openroute.baseURL', 'https://api.openrouteservice.org'), '/');
        $opts = ['headers' => ['Authorization' => self::apiKey(), 'Accept' => 'application/json'], 'http_errors' => false, 'timeout' => 20];
        if ($body !== null) {
            $opts['json'] = $body;
        }
        $res  = \Config\Services::curlrequest([], null, null, false)->request($method, $base . $url, $opts);
        $data = json_decode((string) $res->getBody(), true);
        if ($res->getStatusCode() >= 400 || ! is_array($data)) {
            $err = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? json_encode($data)) : (string) $res->getBody();

            throw new RuntimeException('OpenRouteService gaf een fout (' . $res->getStatusCode() . '): ' . (is_string($err) ? $err : json_encode($err)));
        }

        return $data;
    }

    /** @return array{0:float,1:float} [lng, lat] */
    public static function geocode(string $address): array
    {
        $data = self::ors('GET', '/geocode/search?' . http_build_query(['api_key' => self::apiKey(), 'text' => $address, 'boundary.country' => 'NL,BE,DE', 'size' => 1]));
        $c    = $data['features'][0]['geometry']['coordinates'] ?? null;
        if (! is_array($c) || count($c) < 2) {
            throw new RuntimeException('Adres niet gevonden: ' . $address);
        }

        return [(float) $c[0], (float) $c[1]];
    }

    /** Totale rijafstand in km langs alle punten ([lng, lat]) */
    public static function distance(array $coords): float
    {
        $data = self::ors('POST', '/v2/directions/driving-car', ['coordinates' => $coords, 'units' => 'km']);
        $d    = $data['routes'][0]['summary']['distance'] ?? null;
        if ($d === null) {
            throw new RuntimeException('Geen route gevonden.');
        }

        return round((float) $d, 1);
    }

    public static function siteAddress(array $site): string
    {
        return trim(implode(', ', array_filter([trim((string) $site['address']), trim(trim((string) $site['zipcode']) . ' ' . trim((string) $site['city']))])), ', ');
    }

    /** Coördinaten van een locatie (gecachet zolang het adres gelijk blijft) */
    private static function siteCoords(array $site): array
    {
        $q = self::siteAddress($site);
        if ($q === '') {
            throw new RuntimeException('Locatie "' . $site['name'] . '" heeft geen adres.');
        }
        if ($site['lat'] !== null && $site['geo_query'] === $q) {
            return [(float) $site['lng'], (float) $site['lat']];
        }
        [$lng, $lat] = self::geocode($q);
        model(SiteModel::class)->update($site['id'], ['lat' => $lat, 'lng' => $lng, 'geo_query' => $q]);

        return [$lng, $lat];
    }

    private static function homeCoords(): array
    {
        $home = self::home();
        if ($home === '') {
            throw new RuntimeException('Geen thuisadres ingesteld (Beheer → Uren & kilometers).');
        }
        if (Setting::get('km.homeQuery') === $home && Setting::get('km.homeLng') !== null) {
            return [(float) Setting::get('km.homeLng'), (float) Setting::get('km.homeLat')];
        }
        [$lng, $lat] = self::geocode($home);
        Setting::set('km.homeQuery', $home);
        Setting::set('km.homeLng', (string) $lng);
        Setting::set('km.homeLat', (string) $lat);

        return [$lng, $lat];
    }

    // ------------------------------------------------------------------ Werkdag

    /**
     * Stops van een dag: locaties met "gereden" aan, in volgorde van starttijd,
     * opeenvolgend dezelfde locatie telt één keer.
     *
     * @return list<array> sites + customer_name
     */
    public static function stops(string $date): array
    {
        $rows = model(TimeEntryModel::class)
            ->select('sites.*, customers.name AS customer_name')
            ->join('sites', 'sites.id = time_entries.site_id')
            ->join('customers', 'customers.id = time_entries.customer_id')
            ->where('time_entries.date', $date)->where('time_entries.travel', 1)
            ->orderBy('time_entries.start_time')->orderBy('time_entries.id')->findAll();
        $stops = [];
        foreach ($rows as $r) {
            if ($stops === [] || end($stops)['id'] !== $r['id']) {
                $stops[] = $r;
            }
        }

        return $stops;
    }

    /** Kilometers van een dag opnieuw bepalen (handmatige km blijven staan) */
    public static function recalc(string $date): array
    {
        $model = model(WorkDayModel::class);
        $day   = $model->where('date', $date)->first();
        $stops = self::stops($date);
        $sig   = hash('sha256', implode('|', array_map(static fn ($s) => $s['id'] . ':' . self::siteAddress($s), $stops)));

        $customers = implode(', ', array_values(array_unique(array_column($stops, 'customer_name'))));
        $routeText = $stops === [] ? null
            : 'Thuis (' . self::home() . ') → ' . implode(' → ', array_map(static fn ($s) => $s['customer_name'] . ' – ' . ($s['name'] !== '' ? $s['name'] . ', ' : '') . self::siteAddress($s), $stops)) . ' → Thuis';

        $data = ['date' => $date, 'route' => $routeText, 'customers' => $customers ?: null, 'signature' => $stops === [] ? null : $sig, 'rate' => self::rate(), 'error' => null];

        if ($stops === []) {
            $data += ['km' => null, 'km_manual' => 0];
        } elseif (! $day || ! $day['km_manual'] || $day['signature'] !== $sig) {
            try {
                $coords = array_merge([self::homeCoords()], array_map([self::class, 'siteCoords'], $stops), [self::homeCoords()]);
                $data['km']        = self::distance($coords);
                $data['km_manual'] = 0;
            } catch (Throwable $e) {
                $data['error'] = $e->getMessage();
                $data['km']    = $day['km'] ?? null;
            }
        }
        $km             = $data['km'] ?? $day['km'] ?? null;
        $data['amount'] = $km !== null ? round((float) $km * self::rate(), 2) : null;

        if ($day) {
            $model->update($day['id'], $data);
        } else {
            $model->insert($data);
        }

        return $model->where('date', $date)->first();
    }

    /** Tekst voor de memoriaalboeking */
    public static function description(array $day): string
    {
        return 'Reistijd naar klantlocatie: ' . $day['customers'] . ', ' . date('d-m-Y', strtotime($day['date']))
            . ' – ' . number_format((float) $day['km'], 1, ',', '.') . ' km × € ' . number_format((float) $day['rate'], 2, ',', '.')
            . ' – route: ' . $day['route'];
    }

    public static function reference(string $date): string
    {
        return 'Uren ' . date('d-m-Y', strtotime($date));
    }

    /** Boeking moet (opnieuw)? */
    public static function needsBooking(array $day): bool
    {
        $want = ((float) $day['km'] > 0 && $day['amount'] > 0) ? self::bookedSig($day) : null;

        return $want !== $day['booked_sig'] || ($want !== null && ! $day['journal_id']);
    }

    private static function bookedSig(array $day): string
    {
        return hash('sha256', $day['km'] . '|' . $day['amount'] . '|' . self::description($day));
    }

    /**
     * Memoriaalboeking van een dag maken / bijwerken / weghalen (geen kilometers meer).
     * Gooit een uitzondering bij een fout (die wordt ook op de dag bewaard).
     */
    public static function book(array $day): array
    {
        $model = model(WorkDayModel::class);
        $mb    = new Moneybird();
        try {
            if ((float) $day['km'] <= 0 || (float) $day['amount'] <= 0) {
                if ($day['journal_id']) {
                    $mb->deleteJournal($day['journal_id']);
                }
                $model->update($day['id'], ['journal_id' => null, 'booked_sig' => null, 'booked_at' => null, 'error' => null]);

                return $model->find($day['id']);
            }
            if ($m = self::missing()) {
                throw new RuntimeException('Nog niet ingesteld: ' . implode(', ', $m) . ' (Beheer → Uren & kilometers).');
            }
            $amount = number_format((float) $day['amount'], 2, '.', '');
            $desc   = mb_substr(self::description($day), 0, 1000);
            $id     = $mb->saveJournal($day['journal_id'] ?: null, $day['date'], self::reference($day['date']), [
                ['ledger_account_id' => (string) Setting::get('km.ledgerCost'), 'debit' => $amount, 'credit' => '0', 'description' => $desc],
                ['ledger_account_id' => (string) Setting::get('km.ledgerPrivate'), 'debit' => '0', 'credit' => $amount, 'description' => $desc],
            ]);
            $model->update($day['id'], ['journal_id' => $id, 'booked_sig' => self::bookedSig($day), 'booked_at' => date('Y-m-d H:i:s'), 'error' => null]);
        } catch (Throwable $e) {
            $model->update($day['id'], ['error' => $e->getMessage()]);

            throw $e;
        }

        return $model->find($day['id']);
    }
}
