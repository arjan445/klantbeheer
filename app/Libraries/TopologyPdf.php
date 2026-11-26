<?php

namespace App\Libraries;

use App\Models\NetworkSiteModel;
use App\Models\SiteModel;

/**
 * Eenvoudige topologie-PDF voor klanten: alleen namen en soort apparaat, geen
 * IP- of MAC-adressen. Wat nu niet bereikbaar is, staat in rood.
 */
class TopologyPdf
{
    private const W  = 150; // breedte van een blok
    private const H  = 40;
    private const GX = 16;
    private const GY = 46;

    /**
     * @param list<int>   $siteIds     netwerksites (één pagina per site)
     * @param list<int>   $highlight   netwerkapparaten die nu offline zijn
     */
    public static function render(array $siteIds, array $highlight = [], string $title = 'Overzicht van uw netwerk'): string
    {
        $pdf  = new SimplePdf('A4', true);
        $logo = SimplePdf::pngToJpeg(FCPATH . 'img/logo.png');
        $hl   = array_flip($highlight);

        // Gecombineerde installatie: één pagina voor de hele installatie
        $siteIds = array_values(array_unique(array_map(static fn ($id) => Topology::primary((int) $id), $siteIds)));
        foreach ($siteIds as $i => $siteId) {
            if ($i > 0) {
                $pdf->addPage();
            }
            $site = model(NetworkSiteModel::class)->find($siteId);
            if ($site === null) {
                continue;
            }
            $loc = $site['site_id'] ? model(SiteModel::class)->select('sites.*, customers.name AS customer_name')
                ->join('customers', 'customers.id = sites.customer_id')->where('sites.id', $site['site_id'])->first() : null;
            self::page($pdf, $site, $loc, Topology::nodes((int) $siteId), $hl, $logo, $title);
        }

        return $pdf->output();
    }

    private static function page(SimplePdf $pdf, array $site, ?array $loc, array $nodes, array $hl, ?string $logo, string $title): void
    {
        $navy = '#0b1150';
        $pw   = $pdf->width();
        $ph   = $pdf->height();
        $m    = 32;

        // Kop
        if ($logo) {
            $pdf->jpeg($logo, $pw - $m - 120, $m - 6, 120);
        }
        $pdf->text($m, $m + 10, $title, 17, $navy, true);
        $sub = $loc ? $loc['customer_name'] . ' - ' . $loc['name'] : $site['name'];
        $pdf->text($m, $m + 28, $sub, 11, '#5b6272');
        $pdf->text($m, $m + 42, 'Stand van ' . date('d-m-Y H:i'), 9, '#8a90a0');
        $pdf->line([[$m, $m + 52], [$pw - $m, $m + 52]], $navy, 1.5);

        // Indeling (zelfde als op het scherm)
        ['order' => $order, 'byId' => $byId] = Topology::tree($nodes);
        $kids = [];
        foreach ($nodes as $n) {
            if ($n['parent'] && isset($byId[$n['parent']])) {
                $kids[$n['parent']][] = $n['id'];
            }
        }
        $pos = [];
        $x   = 0.0;
        $max = 0;
        $placed = [];
        // Volgorde van $order respecteren: kinderen staan daarin al gesorteerd
        $childrenSorted = [];
        foreach ($order as ['node' => $n]) {
            if ($n['parent'] && isset($byId[$n['parent']])) {
                $childrenSorted[$n['parent']][] = $n['id'];
            }
        }
        $place = static function (int $id, int $depth) use (&$place, &$pos, &$x, &$max, &$placed, $childrenSorted) {
            if (isset($placed[$id])) {
                return;
            }
            $placed[$id] = true;
            $max         = max($max, $depth);
            $xs          = [];
            foreach ($childrenSorted[$id] ?? [] as $c) {
                if (! isset($placed[$c])) {
                    $place($c, $depth + 1);
                    $xs[] = $pos[$c][0];
                }
            }
            if ($xs === []) {
                $pos[$id] = [$x, $depth * (self::H + self::GY)];
                $x += self::W + self::GX;
            } else {
                $pos[$id] = [(min($xs) + max($xs)) / 2, $depth * (self::H + self::GY)];
            }
        };
        foreach ($order as ['node' => $n, 'depth' => $d]) {
            if ($d === 0) {
                $place($n['id'], 0);
                $x += self::GX * 2;
            }
        }
        $bw = max($x - self::GX * 3, self::W);
        $bh = ($max + 1) * (self::H + self::GY) - self::GY;

        // Passend maken op de pagina
        $top   = $m + 72;
        $avail = [$pw - 2 * $m, $ph - $top - $m - 30];
        $scale = min(1.0, $avail[0] / $bw, $avail[1] / $bh);
        $ox    = $m + ($avail[0] - $bw * $scale) / 2;
        $pdf->save($scale, $ox, $top);

        // Lijnen
        foreach ($nodes as $n) {
            if (! $n['parent'] || ! isset($pos[$n['parent']], $pos[$n['id']])) {
                continue;
            }
            [$px, $py] = $pos[$n['parent']];
            [$cx, $cy] = $pos[$n['id']];
            $x1  = $px + self::W / 2;
            $y1  = $py + self::H;
            $x2  = $cx + self::W / 2;
            $mid = $y1 + self::GY / 2;
            $down = isset($hl[$n['id']]) && isset($hl[$n['parent']]) ? '#e3a1a9' : '#9aa1b1';
            [$color, $dash, $lw] = match ($n['link']) {
                'wireless' => ['#3b82f6', [4, 3], 1.4],
                'ptp'      => ['#e8590c', [7, 4], 2.2],
                'vpn'      => ['#7c3aed', [1.5, 3], 1.4],
                default    => [$down, null, 1.4],
            };
            $pdf->line([[$x1, $y1], [$x1, $mid], [$x2, $mid], [$x2, $cy]], $color, $lw, $dash);
        }

        // Blokken
        foreach ($nodes as $n) {
            if (! isset($pos[$n['id']])) {
                continue;
            }
            [$bx, $by] = $pos[$n['id']];
            $off   = isset($hl[$n['id']]);
            $fill  = $off ? '#fdecee' : ($n['kind'] === 'manual' ? '#f7f8fa' : '#ffffff');
            $edge  = $off ? '#dc3545' : '#c3c8d3';
            $pdf->rect($bx, $by, self::W, self::H, $fill, $edge, $off ? 1.8 : 0.9, 7, $n['kind'] === 'manual' ? [3, 2] : null);
            $pdf->text($bx + 10, $by + 17, SimplePdf::fit($n['name'], self::W - 22, 10.5, true), 10.5, '#1d2433', true);
            $what = device_type_label($n['type']) ?: 'Apparaat';
            if ($n['kind'] === 'manual' && $n['model']) {
                $what = $n['model'];
            }
            $pdf->text($bx + 10, $by + 31, SimplePdf::fit($off ? 'Niet bereikbaar' : $what, self::W - 22, 8.5), 8.5, $off ? '#b42335' : '#6c757d', $off);
            if ($n['kind'] === 'network') {
                $pdf->circle($bx + self::W - 10, $by + 10, 3.2, $off ? '#dc3545' : ($n['status'] === 'online' ? '#20a35a' : '#adb5bd'));
            }
        }
        $pdf->restore();

        // Voet
        $fy = $ph - $m;
        $pdf->rect($m, $fy - 10, 14, 9, '#fdecee', '#dc3545', 1.2, 2);
        $pdf->text($m + 20, $fy - 2, 'Rood: op dit moment niet bereikbaar', 8.5, '#5b6272');
        $pdf->line([[$m + 210, $fy - 5.5], [$m + 232, $fy - 5.5]], '#e8590c', 2.2, [7, 4]);
        $pdf->text($m + 238, $fy - 2, 'Straalverbinding', 8.5, '#5b6272');
        $pdf->line([[$m + 320, $fy - 5.5], [$m + 342, $fy - 5.5]], '#3b82f6', 1.4, [4, 3]);
        $pdf->text($m + 348, $fy - 2, 'Draadloos', 8.5, '#5b6272');
        $pdf->text($pw - $m, $fy - 2, 'bongersonline', 8.5, '#8a90a0', true, 'right');
    }
}
