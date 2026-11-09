<?php
declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'calendar.php') {
    http_response_code(404);
    exit;
}

function cms_div(int $value, int $by): int
{
    return intdiv($value, $by);
}

function cms_jal_cal(int $year): array
{
    $breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    $gy = $year + 621;
    $leapJ = -14;
    $jp = $breaks[0];
    $jump = 0;
    $last = count($breaks) - 1;
    if ($year < $jp || $year >= $breaks[$last]) {
        return ['leap' => 0, 'gy' => $gy, 'march' => 20];
    }
    for ($i = 1; $i <= $last; $i++) {
        $jm = $breaks[$i];
        $jump = $jm - $jp;
        if ($year < $jm) {
            break;
        }
        $leapJ += cms_div($jump, 33) * 8 + cms_div($jump % 33, 4);
        $jp = $jm;
    }
    $n = $year - $jp;
    $leapJ += cms_div($n, 33) * 8 + cms_div(($n % 33) + 3, 4);
    if ($jump % 33 === 4 && $jump - $n === 4) {
        $leapJ++;
    }
    $leapG = cms_div($gy, 4) - cms_div((cms_div($gy, 100) + 1) * 3, 4) - 150;
    $march = 20 + $leapJ - $leapG;
    if ($jump - $n < 6) {
        $n = $n - $jump + cms_div($jump + 4, 33) * 33;
    }
    $leap = ((($n + 1) % 33) - 1) % 4;
    if ($leap === -1) {
        $leap = 4;
    }
    return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
}

function cms_g2d(int $gy, int $gm, int $gd): int
{
    $day = cms_div(($gy + cms_div($gm - 8, 6) + 100100) * 1461, 4) + cms_div(153 * (($gm + 9) % 12) + 2, 5) + $gd - 34840408;
    return $day - cms_div(cms_div($gy + 100100 + cms_div($gm - 8, 6), 100) * 3, 4) + 752;
}

function cms_d2g(int $jdn): array
{
    $j = 4 * $jdn + 139361631;
    $j += cms_div(cms_div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    $i = cms_div($j % 1461, 4) * 5 + 308;
    $gd = cms_div($i % 153, 5) + 1;
    $gm = (cms_div($i, 153) % 12) + 1;
    $gy = cms_div($j, 1461) - 100100 + cms_div(8 - $gm, 6);
    return ['gy' => $gy, 'gm' => $gm, 'gd' => $gd];
}

function cms_j2d(int $jy, int $jm, int $jd): int
{
    $cal = cms_jal_cal($jy);
    return cms_g2d($cal['gy'], 3, $cal['march']) + ($jm - 1) * 31 - cms_div($jm, 7) * ($jm - 7) + $jd - 1;
}

function cms_g2j(int $gy, int $gm, int $gd): array
{
    $jdn = cms_g2d($gy, $gm, $gd);
    $g = cms_d2g($jdn);
    $jy = $g['gy'] - 621;
    $cal = cms_jal_cal($jy);
    $k = $jdn - cms_g2d($g['gy'], 3, $cal['march']);
    if ($k >= 0) {
        if ($k <= 185) {
            return ['jy' => $jy, 'jm' => 1 + cms_div($k, 31), 'jd' => ($k % 31) + 1];
        }
        $k -= 186;
    } else {
        $jy--;
        $k += 179;
        if ($cal['leap'] === 1) {
            $k++;
        }
    }
    return ['jy' => $jy, 'jm' => 7 + cms_div($k, 30), 'jd' => ($k % 30) + 1];
}

function cms_j2g(int $jy, int $jm, int $jd): array
{
    return cms_d2g(cms_j2d($jy, $jm, $jd));
}

function cms_jalali_month_name(int $month): string
{
    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    return $months[$month - 1] ?? '';
}

function cms_jalali_span(int $year, int $month): array
{
    if ($year < 1200 || $year > 1600 || $month < 1 || $month > 12) {
        return ['start' => '', 'end' => '', 'days' => 0];
    }
    $days = cms_jalali_month_length($year, $month);
    $start = cms_j2g($year, $month, 1);
    $end = cms_j2g($year, $month, $days);
    return [
        'start' => sprintf('%04d-%02d-%02d', $start['gy'], $start['gm'], $start['gd']),
        'end' => sprintf('%04d-%02d-%02d', $end['gy'], $end['gm'], $end['gd']),
        'days' => $days,
    ];
}

function cms_jalali_month_length(int $year, int $month): int
{
    if ($month <= 6) {
        return 31;
    }
    if ($month <= 11) {
        return 30;
    }
    return (int) cms_jal_cal($year)['leap'] === 0 ? 30 : 29;
}

function cms_fa_digits(string $value): string
{
    return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function cms_when(string $raw, bool|string $persian = true): string
{
    $locale = $persian === true || $persian === 'fa' ? 'fa' : ($persian === false ? 'en' : $persian);
    $persian = $locale === 'fa';
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $stamp = null;
    try {
        if (preg_match('/[zZ]|[+\-]\d{2}:?\d{2}$/', $raw)) {
            $stamp = (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('Asia/Tehran'));
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2})?/', $raw) && (int) substr($raw, 0, 4) >= 1700) {
            $normalized = str_replace(' ', 'T', substr($raw, 0, 19));
            $stamp = (new DateTimeImmutable($normalized, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Tehran'));
        }
    } catch (Exception $ignored) {
        $stamp = null;
    }
    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $enMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $arMonths = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $zhMonths = ['1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月'];
    $otherMonths = $locale === 'ar' ? $arMonths : ($locale === 'zh' ? $zhMonths : $enMonths);
    if ($stamp instanceof DateTimeImmutable) {
        $gy = (int) $stamp->format('Y');
        $gm = (int) $stamp->format('n');
        $gd = (int) $stamp->format('j');
        $clock = str_contains($raw, ':') ? $stamp->format('H:i') : '';
        if ($persian) {
            $j = cms_g2j($gy, $gm, $gd);
            $text = $j['jd'] . ' ' . $months[$j['jm'] - 1] . ' ' . $j['jy'];
            if ($clock !== '') {
                $text .= '، ' . $clock;
            }
            return cms_fa_digits($text);
        }
        $text = $locale === 'zh'
            ? $gy . '年' . $zhMonths[$gm - 1] . $gd . '日'
            : $gd . ' ' . $otherMonths[$gm - 1] . ' ' . $gy;
        return $clock === '' ? $text : $text . ', ' . $clock;
    }
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $raw, $match) && (int) $match[1] < 1700) {
        $g = cms_j2g((int) $match[1], (int) $match[2], (int) $match[3]);
        if ($persian) {
            return cms_fa_digits((int) $match[3] . ' ' . $months[(int) $match[2] - 1] . ' ' . $match[1]);
        }
        return $locale === 'zh'
            ? $g['gy'] . '年' . $zhMonths[$g['gm'] - 1] . $g['gd'] . '日'
            : $g['gd'] . ' ' . $otherMonths[$g['gm'] - 1] . ' ' . $g['gy'];
    }
    return $raw;
}

function cms_shamsi_iso(string $iso): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $match)) {
        return $iso;
    }
    $year = (int) $match[1];
    $month = (int) $match[2];
    $day = (int) $match[3];
    if ($year < 1700) {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    $jalali = cms_g2j($year, $month, $day);
    return sprintf('%04d-%02d-%02d', $jalali['jy'], $jalali['jm'], $jalali['jd']);
}

function cms_day_label(string $iso, string $locale = 'fa'): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $match)) {
        return $iso;
    }
    $gy = (int) $match[1];
    $gm = (int) $match[2];
    $gd = (int) $match[3];
    $locale = in_array($locale, ['fa', 'en', 'ar', 'zh'], true) ? $locale : 'fa';
    if ($locale === 'fa') {
        $jalali = cms_g2j($gy, $gm, $gd);
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        return cms_fa_digits($jalali['jd'] . ' ' . $months[$jalali['jm'] - 1] . ' ' . $jalali['jy']);
    }
    if ($locale === 'zh') {
        return $gy . '年' . $gm . '月' . $gd . '日';
    }
    $en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $ar = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $names = $locale === 'ar' ? $ar : $en;
    return $gd . ' ' . $names[$gm - 1] . ' ' . $gy;
}

function cms_week_start(string $iso): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $iso, new DateTimeZone('Asia/Tehran'));
    if (!$date || $date->format('Y-m-d') !== $iso) {
        return $iso;
    }
    $back = ((int) $date->format('w') + 1) % 7;
    return $back === 0 ? $iso : $date->modify('-' . $back . ' days')->format('Y-m-d');
}

function cms_week_days(string $iso): array
{
    $start = cms_week_start($iso);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $start, new DateTimeZone('Asia/Tehran'));
    if (!$date) {
        return [$iso];
    }
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $days[] = $date->modify('+' . $i . ' days')->format('Y-m-d');
    }
    return $days;
}

function cms_parse_day(string $raw): string
{
    $raw = strtr(trim($raw), [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
    $raw = str_replace(['/', '.'], '-', $raw);
    if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $match)) {
        return '';
    }
    $year = (int) $match[1];
    $month = (int) $match[2];
    $day = (int) $match[3];
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
        return '';
    }
    if ($year >= 1700) {
        if (!checkdate($month, $day, $year)) {
            return '';
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    $gregorian = cms_j2g($year, $month, $day);
    if (!checkdate((int) $gregorian['gm'], (int) $gregorian['gd'], (int) $gregorian['gy'])) {
        return '';
    }
    return sprintf('%04d-%02d-%02d', $gregorian['gy'], $gregorian['gm'], $gregorian['gd']);
}
