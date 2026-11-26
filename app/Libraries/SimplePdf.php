<?php

namespace App\Libraries;

/**
 * Heel kleine PDF-schrijver zonder extra pakketten (voor hosting zonder Composer):
 * één of meer pagina's met lijnen, (afgeronde) rechthoeken, tekst in Helvetica
 * en een JPEG-afbeelding. Genoeg voor een overzichtelijke topologie-bijlage.
 *
 * Coördinaten in punten (1/72 inch), oorsprong LINKSBOVEN (wordt intern omgezet).
 */
class SimplePdf
{
    private float $w;
    private float $h;
    /** @var list<string> inhoud per pagina */
    private array $pages = [];
    /** @var list<array{data:string,w:int,h:int}> */
    private array $images = [];

    public function __construct(string $size = 'A4', bool $landscape = true)
    {
        [$w, $h] = match ($size) {
            'A3'    => [841.89, 1190.55],
            default => [595.28, 841.89],
        };
        [$this->w, $this->h] = $landscape ? [$h, $w] : [$w, $h];
        $this->addPage();
    }

    public function width(): float
    {
        return $this->w;
    }

    public function height(): float
    {
        return $this->h;
    }

    public function addPage(): void
    {
        $this->pages[] = '';
    }

    private function out(string $s): void
    {
        $this->pages[count($this->pages) - 1] .= $s . "\n";
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** '#rrggbb' -> 'r g b' (0..1) */
    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return implode(' ', array_map(static fn ($c) => self::n(hexdec($c) / 255), str_split($hex, 2)));
    }

    // ------------------------------------------------------------------
    // Tekenen

    /** Transformatie voor alles daarna (schalen/verschuiven), tot restore() */
    public function save(float $scale = 1, float $dx = 0, float $dy = 0): void
    {
        // PDF y loopt omhoog: schaal rond de linkerbovenhoek van de pagina
        $this->out('q ' . self::n($scale) . ' 0 0 ' . self::n($scale) . ' ' . self::n($dx) . ' ' . self::n($this->h - $this->h * $scale - $dy) . ' cm');
    }

    public function restore(): void
    {
        $this->out('Q');
    }

    public function line(array $points, string $color = '#8f96a6', float $width = 1, ?array $dash = null): void
    {
        $s = self::rgb($color) . ' RG ' . self::n($width) . ' w ' . ($dash ? '[' . implode(' ', array_map([self::class, 'n'], $dash)) . '] 0 d ' : '[] 0 d ');
        foreach ($points as $i => [$x, $y]) {
            $s .= self::n($x) . ' ' . self::n($this->h - $y) . ($i === 0 ? ' m ' : ' l ');
        }
        $this->out($s . 'S');
    }

    public function rect(float $x, float $y, float $w, float $h, ?string $fill, ?string $stroke = null, float $lw = 1, float $r = 0, ?array $dash = null): void
    {
        $s = ($fill ? self::rgb($fill) . ' rg ' : '') . ($stroke ? self::rgb($stroke) . ' RG ' . self::n($lw) . ' w ' : '')
            . ($dash ? '[' . implode(' ', array_map([self::class, 'n'], $dash)) . '] 0 d ' : '[] 0 d ');
        $Y = $this->h - $y - $h; // onderkant in PDF-coördinaten
        if ($r <= 0) {
            $s .= self::n($x) . ' ' . self::n($Y) . ' ' . self::n($w) . ' ' . self::n($h) . ' re ';
        } else {
            $k = 0.5523 * $r;
            $x2 = $x + $w;
            $y2 = $Y + $h;
            $s .= self::n($x + $r) . ' ' . self::n($Y) . ' m '
                . self::n($x2 - $r) . ' ' . self::n($Y) . ' l '
                . self::n($x2 - $r + $k) . ' ' . self::n($Y) . ' ' . self::n($x2) . ' ' . self::n($Y + $r - $k) . ' ' . self::n($x2) . ' ' . self::n($Y + $r) . ' c '
                . self::n($x2) . ' ' . self::n($y2 - $r) . ' l '
                . self::n($x2) . ' ' . self::n($y2 - $r + $k) . ' ' . self::n($x2 - $r + $k) . ' ' . self::n($y2) . ' ' . self::n($x2 - $r) . ' ' . self::n($y2) . ' c '
                . self::n($x + $r) . ' ' . self::n($y2) . ' l '
                . self::n($x + $r - $k) . ' ' . self::n($y2) . ' ' . self::n($x) . ' ' . self::n($y2 - $r + $k) . ' ' . self::n($x) . ' ' . self::n($y2 - $r) . ' c '
                . self::n($x) . ' ' . self::n($Y + $r) . ' l '
                . self::n($x) . ' ' . self::n($Y + $r - $k) . ' ' . self::n($x + $r - $k) . ' ' . self::n($Y) . ' ' . self::n($x + $r) . ' ' . self::n($Y) . ' c h ';
        }
        $this->out($s . ($fill && $stroke ? 'B' : ($fill ? 'f' : 'S')));
    }

    public function circle(float $cx, float $cy, float $r, string $fill): void
    {
        $this->rect($cx - $r, $cy - $r, 2 * $r, 2 * $r, $fill, null, 0, $r);
    }

    /**
     * Tekst; $y is de basislijn. align: left | center | right
     */
    public function text(float $x, float $y, string $text, float $size = 10, string $color = '#1d2433', bool $bold = false, string $align = 'left'): void
    {
        $w = self::textWidth($text, $size, $bold);
        if ($align === 'center') {
            $x -= $w / 2;
        } elseif ($align === 'right') {
            $x -= $w;
        }
        $this->out('BT /' . ($bold ? 'F2' : 'F1') . ' ' . self::n($size) . ' Tf ' . self::rgb($color) . ' rg '
            . self::n($x) . ' ' . self::n($this->h - $y) . ' Td (' . self::escape($text) . ') Tj ET');
    }

    /** Ongeveer de breedte van Helvetica-tekst (genoeg om af te kappen en te centreren) */
    public static function textWidth(string $text, float $size, bool $bold = false): float
    {
        $narrow = 'iljtfrI.,:;!|\' ';
        $wide   = 'mwMW@%';
        $w      = 0.0;
        foreach (mb_str_split($text) as $c) {
            $w += str_contains($narrow, $c) ? 0.28 : (str_contains($wide, $c) ? 0.83 : (ctype_upper($c) || ctype_digit($c) ? 0.62 : 0.53));
        }

        return $w * $size * ($bold ? 1.06 : 1);
    }

    /** Afkappen met … tot de tekst in $max punten past */
    public static function fit(string $text, float $max, float $size, bool $bold = false): string
    {
        if (self::textWidth($text, $size, $bold) <= $max) {
            return $text;
        }
        while (mb_strlen($text) > 1 && self::textWidth($text . '…', $size, $bold) > $max) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . '…';
    }

    /** JPEG-afbeelding (bijv. het logo) */
    public function jpeg(string $data, float $x, float $y, float $w, ?float $h = null): void
    {
        $info = @getimagesizefromstring($data);
        if (! $info || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
            return;
        }
        $h ??= $w * $info[1] / $info[0];
        $this->images[] = ['data' => $data, 'w' => (int) $info[0], 'h' => (int) $info[1]];
        $name = 'Im' . count($this->images);
        $this->out('q ' . self::n($w) . ' 0 0 ' . self::n($h) . ' ' . self::n($x) . ' ' . self::n($this->h - $y - $h) . ' cm /' . $name . ' Do Q');
    }

    /** PNG (met transparantie) -> JPEG op witte achtergrond, via GD als dat er is */
    public static function pngToJpeg(string $path): ?string
    {
        if (! is_file($path) || ! function_exists('imagecreatefrompng')) {
            return null;
        }
        $src = @imagecreatefrompng($path);
        if (! $src) {
            return null;
        }
        $w   = imagesx($src);
        $h   = imagesy($src);
        $dst = imagecreatetruecolor($w, $h);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
        ob_start();
        imagejpeg($dst, null, 90);

        return (string) ob_get_clean();
    }

    private static function escape(string $s): string
    {
        $s = strtr($s, ['…' => '...', '–' => '-', '—' => '-', '·' => '-', '→' => '->', '“' => '"', '”' => '"', '‘' => "'", '’' => "'"]);
        $s = function_exists('iconv') ? (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) : mb_convert_encoding($s, 'Windows-1252', 'UTF-8');

        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    // ------------------------------------------------------------------

    public function output(): string
    {
        $objs = [];
        $add  = static function (string $body) use (&$objs): int {
            $objs[] = $body;

            return count($objs);
        };
        $catalog = $add(''); // later ingevuld
        $pagesId = $add('');
        $f1      = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $f2      = $add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $xobj    = '';
        foreach ($this->images as $i => $img) {
            $id = $add("<< /Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($img['data']) . " >>\nstream\n" . $img['data'] . "\nendstream");
            $xobj .= '/Im' . ($i + 1) . ' ' . $id . ' 0 R ';
        }
        $kids = [];
        foreach ($this->pages as $content) {
            $data = function_exists('gzcompress') ? gzcompress($content) : $content;
            $cid  = $add('<< /Length ' . strlen($data) . (function_exists('gzcompress') ? ' /Filter /FlateDecode' : '') . " >>\nstream\n" . $data . "\nendstream");
            $kids[] = $add('<< /Type /Page /Parent ' . $pagesId . ' 0 R /MediaBox [0 0 ' . self::n($this->w) . ' ' . self::n($this->h) . '] '
                . '/Resources << /Font << /F1 ' . $f1 . ' 0 R /F2 ' . $f2 . ' 0 R >>' . ($xobj !== '' ? ' /XObject << ' . $xobj . '>>' : '') . ' >> /Contents ' . $cid . ' 0 R >>');
        }
        $objs[$pagesId - 1] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn ($k) => $k . ' 0 R', $kids)) . '] /Count ' . count($kids) . ' >>';
        $objs[$catalog - 1] = '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>';

        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf      .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf . 'trailer << /Size ' . (count($objs) + 1) . ' /Root ' . $catalog . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }
}
