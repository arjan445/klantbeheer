<?php

namespace App\Libraries;

use App\Models\ServiceModel;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Inkoopprijzen van Vimexx (www.vimexx.nl/prijzen) inlezen en aan diensten koppelen.
 *
 * De pagina is een overzicht per soort (Domeinnamen, Hosting, VPS, Licenties) met
 * per product de oude en nieuwe verlengprijs, excl. btw. Vimexx heeft hier geen
 * API voor, dus we lezen de pagina zelf: eerst als HTML-tabellen, en lukt dat niet
 * als tekst (naam, dan oude en nieuwe prijs).
 *
 * Periode: domeinen per jaar, de rest per maand (zo rekent Vimexx). Per regel aan
 * te passen; de nieuwe inkoopprijs wordt omgerekend naar de periode van de dienst.
 */
class Vimexx
{
    public const URL = 'https://www.vimexx.nl/prijzen';

    /**
     * @return list<array{key:string, category:string, name:string, old:?float, new:float, months:int}>
     */
    public static function fetch(?string $url = null): array
    {
        $url = $url ?? (string) env('vimexx.priceURL', self::URL);
        $res = \Config\Services::curlrequest([], null, null, false)->get($url, [
            'headers'     => ['Accept' => 'text/html', 'User-Agent' => 'Mozilla/5.0 (klantbeheer prijsimport)'],
            'http_errors' => false,
            'timeout'     => 20,
        ]);
        if ($res->getStatusCode() >= 400) {
            throw new RuntimeException('Vimexx-prijzenpagina niet op te halen (HTTP ' . $res->getStatusCode() . ').');
        }
        $rows = self::parse((string) $res->getBody());
        if ($rows === []) {
            throw new RuntimeException('Geen prijzen gevonden op ' . $url . '. Is de pagina veranderd?');
        }

        return $rows;
    }

    /** @return list<array> */
    public static function parse(string $html): array
    {
        $rows = self::parseTables($html);

        return $rows !== [] ? $rows : self::parseText($html);
    }

    /** "€ 6,57" of "6.57" naar 6.57; "+ 10%" en leeg naar null */
    public static function price(string $s): ?float
    {
        $s = trim(str_replace(["\u{a0}", ' '], ' ', $s));
        if ($s === '' || str_contains($s, '%') || ! preg_match('/(\d{1,3}(?:\.\d{3})*|\d+)(?:,(\d{1,2}))?(?!\d)/', str_replace('€', '', $s), $m)) {
            return null;
        }
        if (! str_contains($s, '€') && ! str_contains($s, ',')) {
            return null; // geen bedrag
        }

        return (float) (str_replace('.', '', $m[1]) . '.' . ($m[2] ?? '0'));
    }

    private static function months(string $category): int
    {
        return preg_match('/domein/i', $category) ? 12 : 1;
    }

    private static function row(string $category, string $name, ?float $old, float $new): array
    {
        $category = trim(preg_replace('/\s+/', ' ', $category)) ?: 'Overig';
        $name     = trim(preg_replace('/\s+/', ' ', $name));

        return [
            'key'      => mb_strtolower($category . '|' . $name),
            'category' => $category,
            'name'     => $name,
            'old'      => $old,
            'new'      => $new,
            'months'   => self::months($category),
        ];
    }

    /** HTML-tabellen, met de kop erboven als soort */
    private static function parseTables(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        $xp   = new DOMXPath($doc);
        $out  = [];
        $head = '';
        foreach ($xp->query('//h1|//h2|//h3|//h4|//table') as $el) {
            /** @var DOMElement $el */
            if ($el->nodeName !== 'table') {
                $head = trim($el->textContent);
                continue;
            }
            foreach ($xp->query('.//tr', $el) as $tr) {
                $cells = [];
                foreach ($xp->query('./td|./th', $tr) as $td) {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $td->textContent));
                }
                if (count($cells) < 2 || self::price($cells[0]) !== null) {
                    continue;
                }
                $prices = array_values(array_filter(array_map([self::class, 'price'], array_slice($cells, 1)), static fn ($p) => $p !== null));
                if ($prices === []) {
                    continue; // kopregel of "+ 10%"
                }
                $out[] = self::row($head, $cells[0], count($prices) > 1 ? $prices[0] : null, end($prices));
            }
        }

        return self::unique($out);
    }

    /** Terugval: tekst per regel. Kop = bekende soort; daarna naam, oude prijs, nieuwe prijs */
    private static function parseText(string $html): array
    {
        $html  = preg_replace('#<(script|style|noscript)\b.*?</\1>#is', '', $html);
        $text  = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/div|/li|/tr|/td|/th|/h\d|/span)\b[^>]*>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_values(array_filter(array_map(static fn ($l) => trim(preg_replace('/\s+/', ' ', $l)), explode("\n", $text)), static fn ($l) => $l !== ''));
        $out   = [];
        $head  = '';
        $n     = count($lines);
        for ($i = 0; $i < $n; $i++) {
            $l = $lines[$i];
            if (preg_match('/^(Domeinnamen|Hosting|VPS|Dedicated server|Licenties|Staffel)$/i', $l)) {
                $head = $l;
                continue;
            }
            if (self::price($l) !== null || $head === '') {
                continue;
            }
            $p1 = self::price($lines[$i + 1] ?? '');
            $p2 = self::price($lines[$i + 2] ?? '');
            if ($p1 !== null) {
                $out[] = self::row($head, $l, $p2 !== null ? $p1 : null, $p2 ?? $p1);
                $i += $p2 !== null ? 2 : 1;
            }
        }

        return self::unique($out);
    }

    private static function unique(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] ??= $r;
        }

        return array_values($out);
    }

    // ------------------------------------------------------------------ Koppelen

    /** Opgeslagen koppelingen: dienst-ID => [key, months] */
    public static function mapping(): array
    {
        $m = json_decode((string) Setting::get('vimexx.map', ''), true);

        return is_array($m) ? $m : [];
    }

    /**
     * Per dienst een voorstel: eerder gekoppeld, anders domeinextensie, anders de
     * naam die het meest lijkt (alleen als het echt lijkt).
     *
     * @return array<int,array{key:?string, months:?int, why:string}>
     */
    public static function suggest(array $services, array $rows): array
    {
        $map   = self::mapping();
        $byKey = array_column($rows, null, 'key');
        $out   = [];
        foreach ($services as $s) {
            $id = (int) $s['id'];
            if (isset($map[$id]['key'], $byKey[$map[$id]['key']])) {
                $out[$id] = ['key' => $map[$id]['key'], 'months' => (int) ($map[$id]['months'] ?? $byKey[$map[$id]['key']]['months']), 'why' => 'eerder gekoppeld'];
                continue;
            }
            // Domeinextensie: ".nl" in de naam
            if (preg_match('/(?:^|\s)\.([a-z0-9-]{2,24})\b/i', $s['name'], $m) || ($s['type'] === 'domain' && preg_match('/\b([a-z]{2,6})$/i', trim($s['name']), $m))) {
                $tld = '.' . strtolower($m[1]);
                foreach ($rows as $r) {
                    if (preg_match('/domein/i', $r['category']) && strtolower($r['name']) === $tld) {
                        $out[$id] = ['key' => $r['key'], 'months' => $r['months'], 'why' => 'extensie ' . $tld];
                        continue 2;
                    }
                }
            }
            // Naam lijkt erop (bijv. "SpamExperts" <-> "Spamexperts licentie 1 domeinnaam")
            $norm = static fn (string $x) => trim(preg_replace('/[^a-z0-9 ]+/', ' ', mb_strtolower($x)));
            $best = null;
            $bestScore = 0;
            foreach ($rows as $r) {
                if (preg_match('/domein/i', $r['category'])) {
                    continue;
                }
                similar_text($norm($s['name']), $norm($r['name']), $pct);
                $words = array_intersect(explode(' ', $norm($s['name'])), explode(' ', $norm($r['name'])));
                $score = $pct + 10 * count(array_filter($words, static fn ($w) => strlen($w) > 3));
                if ($score > $bestScore) {
                    [$best, $bestScore] = [$r, $score];
                }
            }
            $out[$id] = $best !== null && $bestScore >= 70
                ? ['key' => $best['key'], 'months' => $best['months'], 'why' => 'voorstel op naam']
                : ['key' => null, 'months' => null, 'why' => ''];
        }

        return $out;
    }

    /** Nieuwe inkoopprijs voor de periode van de dienst */
    public static function convert(float $price, int $vimexxMonths, int $serviceMonths): float
    {
        return round($price * max(1, $serviceMonths) / max(1, $vimexxMonths), 2);
    }

    /**
     * Toepassen. $choices: dienst-ID => [key, months, apply(bool)].
     *
     * @return array{services:int, subscriptions:int}
     */
    public static function apply(array $choices, array $rows, bool $alsoSubs): array
    {
        $byKey = array_column($rows, null, 'key');
        $map   = self::mapping();
        $db    = db_connect();
        $done  = ['services' => 0, 'subscriptions' => 0];
        foreach ($choices as $id => $c) {
            $id  = (int) $id;
            $key = (string) ($c['key'] ?? '');
            if ($key === '' || ! isset($byKey[$key])) {
                unset($map[$id]);
                continue;
            }
            $months   = in_array((int) ($c['months'] ?? 0), [1, 3, 12], true) ? (int) $c['months'] : $byKey[$key]['months'];
            $map[$id] = ['key' => $key, 'months' => $months];
            if (empty($c['apply'])) {
                continue;
            }
            $s = model(ServiceModel::class)->find($id);
            if ($s === null || $s['cost_pool_id']) {
                continue; // kostprijs komt uit een kostenpool
            }
            $new = self::convert($byKey[$key]['new'], $months, (int) ($s['interval_months'] ?: 12));
            $old = $s['purchase_price'];
            model(ServiceModel::class)->update($id, ['purchase_price' => $new, 'supplier' => $s['supplier'] ?: 'Vimexx']);
            $done['services']++;
            if ($alsoSubs && $old !== null) {
                // Abonnementen met een eigen inkoopprijs die gelijk was aan de oude
                $db->table('subscriptions')->where('service_id', $id)->where('purchase_price', $old)->update(['purchase_price' => $new]);
                $done['subscriptions'] += $db->affectedRows();
            }
        }
        Setting::set('vimexx.map', $map === [] ? null : json_encode($map));
        Setting::set('vimexx.importedAt', date('Y-m-d H:i:s'));

        return $done;
    }
}
