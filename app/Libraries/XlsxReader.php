<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Minimale .xlsx-lezer (eerste werkblad), zonder externe packages.
 * Geeft rijen terug als arrays met kolomletter => waarde.
 */
class XlsxReader
{
    /** @return list<array<string, string>> */
    public static function rows(string $path): array
    {
        $files  = self::unzip($path, ['xl/sharedStrings.xml', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels']);
        $shared = [];
        if (isset($files['xl/sharedStrings.xml'])) {
            preg_match_all('#<si>(.*?)</si>#s', $files['xl/sharedStrings.xml'], $m);
            foreach ($m[1] as $si) {
                preg_match_all('#<t[^>]*>(.*?)</t>#s', $si, $t);
                $shared[] = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        // Eerste werkblad volgens workbook.xml (meestal sheet1.xml)
        $sheetPath = 'xl/worksheets/sheet1.xml';
        if (isset($files['xl/workbook.xml'], $files['xl/_rels/workbook.xml.rels'])
            && preg_match('#<sheet\b[^>]*\br:id="([^"]+)"#', $files['xl/workbook.xml'], $sm)) {
            foreach (preg_split('#(?=<Relationship\b)#', $files['xl/_rels/workbook.xml.rels']) as $rel) {
                if (str_contains($rel, 'Id="' . $sm[1] . '"') && preg_match('#Target="([^"]+)"#', $rel, $tm)) {
                    $sheetPath = 'xl/' . ltrim(preg_replace('#^/?xl/#', '', $tm[1]), '/');
                    break;
                }
            }
        }
        $sheet = self::unzip($path, [$sheetPath])[$sheetPath] ?? null;
        if ($sheet === null) {
            throw new RuntimeException('Werkblad niet gevonden in het Excel-bestand.');
        }

        $rows = [];
        preg_match_all('#<row[^>]*>(.*?)</row>#s', $sheet, $rm2);
        foreach ($rm2[1] as $rowXml) {
            $row = [];
            preg_match_all('#<c r="([A-Z]+)\d+"([^>]*?)(?:/>|>(.*?)</c>)#s', $rowXml, $cells, PREG_SET_ORDER);
            foreach ($cells as $c) {
                $attrs = $c[2];
                $inner = $c[3] ?? '';
                $value = '';
                if (preg_match('#<v>(.*?)</v>#s', $inner, $v)) {
                    $value = $v[1];
                    if (str_contains($attrs, 't="s"')) {
                        $value = $shared[(int) $value] ?? '';
                    } else {
                        $value = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                } elseif (preg_match_all('#<t[^>]*>(.*?)</t>#s', $inner, $t)) {
                    $value = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                $row[$c[1]] = trim($value);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Leest de gevraagde bestanden uit een zip (deflate of stored).
     *
     * @return array<string, string>
     */
    private static function unzip(string $path, array $want): array
    {
        $data = file_get_contents($path);
        if ($data === false || ! str_starts_with($data, "PK")) {
            throw new RuntimeException('Dit is geen geldig .xlsx-bestand.');
        }
        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false) {
            throw new RuntimeException('Beschadigd .xlsx-bestand.');
        }
        $info  = unpack('vdisk/vcd_disk/ventries_disk/ventries/Vsize/Voffset', substr($data, $eocd + 4, 16));
        $p     = $info['offset'];
        $want  = array_flip($want);
        $out   = [];
        for ($i = 0; $i < $info['entries']; $i++) {
            $h    = unpack('Vsig/vver/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/vxlen/vclen/vdisk/vint/Vext/Voff', substr($data, $p, 46));
            $name = substr($data, $p + 46, $h['nlen']);
            $p   += 46 + $h['nlen'] + $h['xlen'] + $h['clen'];
            if (! isset($want[$name])) {
                continue;
            }
            $l    = unpack('Vsig/vver/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/vxlen', substr($data, $h['off'], 30));
            $raw  = substr($data, $h['off'] + 30 + $l['nlen'] + $l['xlen'], $h['csize']);
            $out[$name] = $h['method'] === 8 ? (string) gzinflate($raw) : $raw;
        }

        return $out;
    }
}
