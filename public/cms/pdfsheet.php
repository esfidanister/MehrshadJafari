<?php
declare(strict_types=1);

function cms_pdf_chars(string $text): array
{
    $out = [];
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $b = ord($text[$i]);
        if ($b < 0x80) {
            $out[] = $b;
        } elseif (($b & 0xE0) === 0xC0 && $i + 1 < $len) {
            $out[] = (($b & 0x1F) << 6) | (ord($text[++$i]) & 0x3F);
        } elseif (($b & 0xF0) === 0xE0 && $i + 2 < $len) {
            $out[] = (($b & 0x0F) << 12) | ((ord($text[++$i]) & 0x3F) << 6) | (ord($text[++$i]) & 0x3F);
        } elseif (($b & 0xF8) === 0xF0 && $i + 3 < $len) {
            $out[] = (($b & 0x07) << 18) | ((ord($text[++$i]) & 0x3F) << 12) | ((ord($text[++$i]) & 0x3F) << 6) | (ord($text[++$i]) & 0x3F);
        }
    }
    return $out;
}

function cms_pdf_forms(): array
{
    static $forms = null;
    if ($forms !== null) {
        return $forms;
    }
    $row = static function (int $iso, int $fin, int $ini = 0, int $med = 0): array {
        return [$iso, $fin, $ini, $med];
    };
    $forms = [
        0x0621 => $row(0xFE80, 0xFE80),
        0x0622 => $row(0xFE81, 0xFE82),
        0x0623 => $row(0xFE83, 0xFE84),
        0x0624 => $row(0xFE85, 0xFE86),
        0x0625 => $row(0xFE87, 0xFE88),
        0x0626 => $row(0xFE89, 0xFE8A, 0xFE8B, 0xFE8C),
        0x0627 => $row(0xFE8D, 0xFE8E),
        0x0628 => $row(0xFE8F, 0xFE90, 0xFE91, 0xFE92),
        0x0629 => $row(0xFE93, 0xFE94),
        0x062A => $row(0xFE95, 0xFE96, 0xFE97, 0xFE98),
        0x062B => $row(0xFE99, 0xFE9A, 0xFE9B, 0xFE9C),
        0x062C => $row(0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0),
        0x062D => $row(0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4),
        0x062E => $row(0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8),
        0x062F => $row(0xFEA9, 0xFEAA),
        0x0630 => $row(0xFEAB, 0xFEAC),
        0x0631 => $row(0xFEAD, 0xFEAE),
        0x0632 => $row(0xFEAF, 0xFEB0),
        0x0633 => $row(0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4),
        0x0634 => $row(0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8),
        0x0635 => $row(0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC),
        0x0636 => $row(0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0),
        0x0637 => $row(0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4),
        0x0638 => $row(0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8),
        0x0639 => $row(0xFEC9, 0xFECA, 0xFECB, 0xFECC),
        0x063A => $row(0xFECD, 0xFECE, 0xFECF, 0xFED0),
        0x0641 => $row(0xFED1, 0xFED2, 0xFED3, 0xFED4),
        0x0642 => $row(0xFED5, 0xFED6, 0xFED7, 0xFED8),
        0x0643 => $row(0xFED9, 0xFEDA, 0xFEDB, 0xFEDC),
        0x0644 => $row(0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0),
        0x0645 => $row(0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4),
        0x0646 => $row(0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8),
        0x0647 => $row(0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC),
        0x0648 => $row(0xFEED, 0xFEEE),
        0x064A => $row(0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4),
        0x067E => $row(0xFB56, 0xFB57, 0xFB58, 0xFB59),
        0x0686 => $row(0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D),
        0x0698 => $row(0xFB8A, 0xFB8B),
        0x06A9 => $row(0xFB8E, 0xFB8F, 0xFB90, 0xFB91),
        0x06AF => $row(0xFB92, 0xFB93, 0xFB94, 0xFB95),
        0x06CC => $row(0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF),
    ];
    return $forms;
}

function cms_pdf_join(int $cp): string
{
    $forms = cms_pdf_forms();
    if (!isset($forms[$cp])) {
        return '';
    }
    return $forms[$cp][2] === 0 ? 'R' : 'D';
}

function cms_pdf_shape(string $text): array
{
    $chars = cms_pdf_chars($text);
    $items = [];
    $count = count($chars);
    for ($i = 0; $i < $count; $i++) {
        $cp = $chars[$i];
        $next = $chars[$i + 1] ?? 0;
        if ($cp === 0x0644 && in_array($next, [0x0622, 0x0623, 0x0625, 0x0627], true)) {
            $items[] = ['lam', $next];
            $i++;
            continue;
        }
        $items[] = ['cp', $cp];
    }
    $types = [];
    foreach ($items as $item) {
        $types[] = $item[0] === 'lam' ? 'R' : cms_pdf_join($item[1]);
    }
    $prevJoins = static function (int $index) use ($types): bool {
        if ($types[$index] === '') {
            return false;
        }
        for ($j = $index - 1; $j >= 0; $j--) {
            if ($types[$j] === '') {
                return false;
            }
            return $types[$j] === 'D';
        }
        return false;
    };
    $nextJoins = static function (int $index) use ($types): bool {
        if ($types[$index] !== 'D') {
            return false;
        }
        $count = count($types);
        for ($j = $index + 1; $j < $count; $j++) {
            if ($types[$j] === '') {
                return false;
            }
            return $types[$j] === 'D' || $types[$j] === 'R';
        }
        return false;
    };
    $lamAlef = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];
    $out = [];
    foreach ($items as $index => $item) {
        $prev = $prevJoins($index);
        $next = $nextJoins($index);
        if ($item[0] === 'lam') {
            $pair = $lamAlef[$item[1]];
            $out[] = $prev ? $pair[1] : $pair[0];
            continue;
        }
        $forms = cms_pdf_forms()[$item[1]] ?? null;
        if ($forms === null) {
            $out[] = $item[1];
            continue;
        }
        if ($prev && $next && $forms[3] !== 0) {
            $out[] = $forms[3];
        } elseif ($prev && $forms[1] !== 0) {
            $out[] = $forms[1];
        } elseif ($next && $forms[2] !== 0) {
            $out[] = $forms[2];
        } else {
            $out[] = $forms[0];
        }
    }
    return $out;
}

function cms_pdf_visual(array $chars, bool $rtl): array
{
    if (!$rtl) {
        return $chars;
    }
    $chars = array_reverse($chars);
    $out = [];
    $run = [];
    $flush = static function () use (&$run, &$out): void {
        if ($run) {
            array_push($out, ...array_reverse($run));
            $run = [];
        }
    };
    foreach ($chars as $cp) {
        $ltr = ($cp >= 0x30 && $cp <= 0x39) || ($cp >= 0x41 && $cp <= 0x5A) || ($cp >= 0x61 && $cp <= 0x7A) || ($cp >= 0x06F0 && $cp <= 0x06F9) || ($cp >= 0x0660 && $cp <= 0x0669) || in_array($cp, [0x2E, 0x2F, 0x2D, 0x2B, 0x3A], true);
        if ($ltr) {
            $run[] = $cp;
        } else {
            $flush();
            $out[] = $cp;
        }
    }
    $flush();
    return $out;
}

function cms_ttf_u16(string $b, int $o): int
{
    return unpack('n', substr($b, $o, 2))[1];
}

function cms_ttf_i16(string $b, int $o): int
{
    $n = cms_ttf_u16($b, $o);
    return $n >= 0x8000 ? $n - 0x10000 : $n;
}

function cms_ttf_u32(string $b, int $o): int
{
    return unpack('N', substr($b, $o, 4))[1];
}

function cms_ttf_open(): ?array
{
    static $font = null;
    static $failed = false;
    if ($font !== null || $failed) {
        return $font;
    }
    $file = __DIR__ . '/fonts/NotoNaskhArabic-Regular.ttf';
    $bytes = is_file($file) ? (string) file_get_contents($file) : '';
    if (strlen($bytes) < 12 || cms_ttf_u32($bytes, 0) !== 0x00010000) {
        $failed = true;
        return null;
    }
    $tables = [];
    $count = cms_ttf_u16($bytes, 4);
    for ($i = 0; $i < $count; $i++) {
        $at = 12 + $i * 16;
        $tables[substr($bytes, $at, 4)] = [cms_ttf_u32($bytes, $at + 8), cms_ttf_u32($bytes, $at + 12)];
    }
    foreach (['head', 'hhea', 'hmtx', 'cmap', 'maxp'] as $need) {
        if (!isset($tables[$need])) {
            $failed = true;
            return null;
        }
    }
    $head = $tables['head'][0];
    $hhea = $tables['hhea'][0];
    $cmapOff = $tables['cmap'][0];
    $maps = [];
    $subCount = cms_ttf_u16($bytes, $cmapOff + 2);
    for ($i = 0; $i < $subCount; $i++) {
        $at = $cmapOff + 4 + $i * 8;
        $maps[] = [cms_ttf_u16($bytes, $at), cms_ttf_u16($bytes, $at + 2), $cmapOff + cms_ttf_u32($bytes, $at + 4)];
    }
    $format4 = null;
    $groups = [];
    foreach ($maps as $map) {
        $format = cms_ttf_u16($bytes, $map[2]);
        if ($format === 12 && !$groups) {
            $n = cms_ttf_u32($bytes, $map[2] + 12);
            for ($g = 0; $g < $n; $g++) {
                $at = $map[2] + 16 + $g * 12;
                $groups[] = [cms_ttf_u32($bytes, $at), cms_ttf_u32($bytes, $at + 4), cms_ttf_u32($bytes, $at + 8)];
            }
        }
        if ($format === 4 && $format4 === null) {
            $format4 = $map[2];
        }
    }
    $font = [
        'bytes' => $bytes,
        'units' => cms_ttf_u16($bytes, $head + 18),
        'ascent' => cms_ttf_i16($bytes, $hhea + 4),
        'descent' => cms_ttf_i16($bytes, $hhea + 6),
        'hmetrics' => cms_ttf_u16($bytes, $hhea + 34),
        'hmtx' => $tables['hmtx'][0],
        'bbox' => [cms_ttf_i16($bytes, $head + 36), cms_ttf_i16($bytes, $head + 38), cms_ttf_i16($bytes, $head + 40), cms_ttf_i16($bytes, $head + 42)],
        'groups' => $groups,
        'format4' => $format4,
    ];
    return $font;
}

function cms_ttf_glyph(array $font, int $cp): int
{
    foreach ($font['groups'] as $group) {
        if ($cp >= $group[0] && $cp <= $group[1]) {
            return $group[2] + ($cp - $group[0]);
        }
    }
    $at = $font['format4'];
    if (!is_int($at)) {
        return 0;
    }
    $bytes = $font['bytes'];
    $segCount = intdiv(cms_ttf_u16($bytes, $at + 6), 2);
    $endAt = $at + 14;
    $startAt = $endAt + $segCount * 2 + 2;
    $deltaAt = $startAt + $segCount * 2;
    $offsetAt = $deltaAt + $segCount * 2;
    for ($i = 0; $i < $segCount; $i++) {
        $end = cms_ttf_u16($bytes, $endAt + $i * 2);
        if ($cp > $end) {
            continue;
        }
        $start = cms_ttf_u16($bytes, $startAt + $i * 2);
        if ($cp < $start) {
            return 0;
        }
        $delta = cms_ttf_i16($bytes, $deltaAt + $i * 2);
        $range = cms_ttf_u16($bytes, $offsetAt + $i * 2);
        if ($range === 0) {
            return ($cp + $delta) & 0xFFFF;
        }
        $glyphAt = $offsetAt + $i * 2 + $range + ($cp - $start) * 2;
        $glyph = cms_ttf_u16($bytes, $glyphAt);
        return $glyph === 0 ? 0 : ($glyph + $delta) & 0xFFFF;
    }
    return 0;
}

function cms_ttf_advance(array $font, int $gid): int
{
    if ($gid <= 0) {
        return $font['units'];
    }
    $index = min($gid, $font['hmetrics'] - 1);
    return cms_ttf_u16($font['bytes'], $font['hmtx'] + $index * 4);
}

function cms_pdf_width(array $font, array $chars, float $size): float
{
    $width = 0.0;
    foreach ($chars as $cp) {
        $width += cms_ttf_advance($font, cms_ttf_glyph($font, $cp)) * $size / $font['units'];
    }
    return $width;
}

function cms_pdf_wrap(array $font, string $text, bool $rtl, float $size, float $max): array
{
    $text = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', $text)) ?? '');
    if ($text === '') {
        return [];
    }
    $parts = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $current = '';
    $pack = static function (string $line) use ($font, $rtl, $size): array {
        return cms_pdf_visual(cms_pdf_shape($line), $rtl);
    };
    foreach ($parts as $part) {
        $trial = $current === '' ? $part : $current . ' ' . $part;
        if ($current !== '' && cms_pdf_width($font, $pack($trial), $size) > $max) {
            $lines[] = $pack($current);
            $current = $part;
        } else {
            $current = $trial;
        }
    }
    if ($current !== '') {
        $lines[] = $pack($current);
    }
    $out = [];
    foreach ($lines as $line) {
        if (cms_pdf_width($font, $line, $size) <= $max) {
            $out[] = $line;
            continue;
        }
        $piece = [];
        foreach ($line as $cp) {
            $trial = $piece;
            $trial[] = $cp;
            if ($piece && cms_pdf_width($font, $trial, $size) > $max) {
                $out[] = $piece;
                $piece = [$cp];
            } else {
                $piece = $trial;
            }
        }
        if ($piece) {
            $out[] = $piece;
        }
    }
    return $out;
}

function cms_pdf_file(string $title, array $blocks, bool $rtl): string
{
    $font = cms_ttf_open();
    if ($font === null) {
        return '';
    }
    $pageW = 595.0;
    $pageH = 842.0;
    $margin = 46.0;
    $max = $pageW - $margin * 2;
    $pages = [];
    $commands = [];
    $used = [];
    $y = 760.0;
    $open = static function () use (&$commands, &$y, $pageW, $pageH, $margin, $title, $font, $rtl, &$used): void {
        $commands = ['0.612 0.804 0.816 rg', '0 800 ' . $pageW . ' 42 re f', '0.094 0.157 0.196 rg'];
        $y = 760.0;
        cms_pdf_draw($commands, $font, cms_pdf_wrap($font, $title, $rtl, 16, $pageW - $margin * 2), $rtl, 16, $margin, 814, $pageW, $used);
    };
    $open();
    foreach ($blocks as $block) {
        $kind = (string) ($block[0] ?? 'p');
        $text = trim((string) ($block[1] ?? ''));
        if ($text === '') {
            continue;
        }
        $size = $kind === 'h' ? 13.0 : ($kind === 'note' ? 9.0 : 11.0);
        $gap = $size + 7;
        $lines = cms_pdf_wrap($font, $text, $rtl, $size, $max);
        if ($y - count($lines) * $gap < 52) {
            $pages[] = $commands;
            $open();
        }
        if ($kind === 'h') {
            $commands[] = '0.612 0.804 0.816 rg';
            $commands[] = $margin . ' ' . ($y - 2) . ' ' . $max . ' 1.2 re f';
            $commands[] = '0.094 0.157 0.196 rg';
        } elseif ($kind === 'note') {
            $commands[] = '0.365 0.420 0.451 rg';
        } else {
            $commands[] = '0.094 0.157 0.196 rg';
        }
        foreach ($lines as $line) {
            cms_pdf_draw($commands, $font, [$line], $rtl, $size, $margin, $y, $pageW, $used);
            $y -= $gap;
        }
        $y -= $kind === 'h' ? 2 : 1;
    }
    $pages[] = $commands;
    return cms_pdf_pack($font, $pages, $used, $title);
}

function cms_pdf_draw(array &$commands, array $font, array $lines, bool $rtl, float $size, float $margin, float $y, float $pageW, array &$used): void
{
    foreach ($lines as $line) {
        if (!$line) {
            continue;
        }
        $width = cms_pdf_width($font, $line, $size);
        $x = $rtl ? $pageW - $margin - $width : $margin;
        $hex = '';
        foreach ($line as $cp) {
            $gid = cms_ttf_glyph($font, $cp);
            if ($gid <= 0) {
                continue;
            }
            $used[$gid] = $cp;
            $hex .= sprintf('%04X', $gid);
        }
        if ($hex === '') {
            continue;
        }
        $commands[] = 'BT /F1 ' . $size . ' Tf 1 0 0 1 ' . round($x, 2) . ' ' . round($y, 2) . ' Tm <' . $hex . '> Tj ET';
    }
}

function cms_pdf_pack(array $font, array $pages, array $used, string $title): string
{
    $units = max(1, (int) $font['units']);
    $scale = static fn (int $n): int => (int) round($n * 1000 / $units);
    $widths = [];
    $bf = [];
    foreach ($used as $gid => $cp) {
        $widths[] = $gid . ' [' . $scale(cms_ttf_advance($font, (int) $gid)) . ']';
        $token = $cp > 0xFFFF ? '' : sprintf('%04X', $cp);
        if ($token !== '') {
            $bf[] = '<' . sprintf('%04X', (int) $gid) . '> <' . $token . '>';
        }
    }
    $toUnicode = "12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def\n/CMapName /Care def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
    foreach (array_chunk($bf, 100) as $chunk) {
        $toUnicode .= count($chunk) . " beginbfchar\n" . implode("\n", $chunk) . "\nendbfchar\n";
    }
    $toUnicode .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend\n";
    $compressed = gzcompress($font['bytes'], 6);
    $bbox = $font['bbox'];
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type0 /BaseFont /NotoNaskhArabic /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 7 0 R >>';
    $objects[4] = '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /NotoNaskhArabic /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 5 0 R /DW 500 /CIDToGIDMap /Identity /W [' . implode(' ', $widths) . '] >>';
    $objects[5] = '<< /Type /FontDescriptor /FontName /NotoNaskhArabic /Flags 4 /Ascent ' . $scale((int) $font['ascent']) . ' /Descent ' . $scale((int) $font['descent']) . ' /StemV 80 /FontBBox [' . $scale((int) $bbox[0]) . ' ' . $scale((int) $bbox[1]) . ' ' . $scale((int) $bbox[2]) . ' ' . $scale((int) $bbox[3]) . '] /FontFile2 6 0 R >>';
    $objects[6] = '<< /Length ' . strlen($compressed) . ' /Length1 ' . strlen($font['bytes']) . ' /Filter /FlateDecode >> stream' . "\n" . $compressed . "\nendstream";
    $objects[7] = '<< /Length ' . strlen($toUnicode) . ' >> stream' . "\n" . $toUnicode . "\nendstream";
    $pageIds = [];
    $next = 8;
    foreach ($pages as $commands) {
        $stream = implode("\n", $commands);
        $pageId = $next++;
        $streamId = $next++;
        $pageIds[] = $pageId;
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $streamId . ' 0 R >>';
        $objects[$streamId] = '<< /Length ' . strlen($stream) . ' >> stream' . "\n" . $stream . "\nendstream";
    }
    $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageIds));
    $objects[2] = '<< /Type /Pages /Count ' . count($pageIds) . ' /Kids [' . $kids . '] >>';
    $titleHex = 'FEFF';
    foreach (cms_pdf_chars($title) as $cp) {
        if ($cp <= 0xFFFF) {
            $titleHex .= sprintf('%04X', $cp);
        }
    }
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    $maxId = max(array_keys($objects));
    for ($id = 1; $id <= $maxId; $id++) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . ($objects[$id] ?? '<< >>') . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $maxId; $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R /Info << /Title <" . $titleHex . "> >> >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

function cms_color_ratio(string $fore, string $back): float
{
    $lum = static function (string $hex): float {
        $hex = ltrim($hex, '#');
        $lin = static function (int $channel): float {
            $c = $channel / 255;
            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $r = $lin(hexdec(substr($hex, 0, 2)));
        $g = $lin(hexdec(substr($hex, 2, 2)));
        $b = $lin(hexdec(substr($hex, 4, 2)));
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };
    $light = max($lum($fore), $lum($back));
    $dark = min($lum($fore), $lum($back));
    return ($light + 0.05) / ($dark + 0.05);
}
