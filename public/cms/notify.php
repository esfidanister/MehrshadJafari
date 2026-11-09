<?php
declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'notify.php') {
    http_response_code(404);
    exit;
}

function cms_service_catalog(): array
{
    return [
        'unsure' => 'معاینه',
        'wisdom-tooth-surgery' => 'دندان عقل',
        'dental-implants' => 'ایمپلنت',
        'orthognathic-surgery' => 'ارتوگناتیک',
        'oral-pathology' => 'پاتولوژی دهان',
        'bone-graft-sinus-lift' => 'پیوند استخوان',
        'all-on-4' => 'ایمپلنت تمام‌فک',
        'facial-fractures' => 'شکستگی',
        'tmj' => 'مفصل فک',
        'cosmetic-jaw' => 'فرم فک و چانه',
    ];
}

function cms_service_label(string $key, string $locale = 'fa'): string
{
    return cms_topic_label($key, $locale);
}

function cms_visit_statuses(): array
{
    return [
        'new' => 'درخواست تازه',
        'confirmed' => 'هماهنگ شد',
        'arrived' => 'حاضر شد',
        'done' => 'انجام شد',
        'cancelled' => 'لغو شد',
        'noshow' => 'نیامد',
    ];
}

function cms_status_label(string $status, string $locale = 'fa'): string
{
    $locale = cms_locale($locale);
    $packs = [
        'en' => ['new' => 'Request', 'confirmed' => 'Confirmed', 'arrived' => 'Arrived', 'done' => 'Done', 'cancelled' => 'Cancelled', 'noshow' => 'Missed'],
        'ar' => ['new' => 'طلب', 'confirmed' => 'تم التنسيق', 'arrived' => 'حضر', 'done' => 'تم', 'cancelled' => 'أُلغي', 'noshow' => 'لم يحضر'],
        'zh' => ['new' => '申请', 'confirmed' => '已安排', 'arrived' => '已到', 'done' => '已完成', 'cancelled' => '已取消', 'noshow' => '未到'],
    ];
    if ($locale !== 'fa' && isset($packs[$locale][$status])) {
        return $packs[$locale][$status];
    }
    return cms_visit_statuses()[$status] ?? $status;
}

function cms_notify_events(): array
{
    return [
        'created' => 'ثبت درخواست',
        'confirmed' => 'هماهنگ شد',
        'rescheduled' => 'زمان عوض شد',
        'cancelled' => 'لغو شد',
        'reminder' => 'یادآوری فردا',
    ];
}

function cms_visit_kinds(bool $activeOnly = false): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        foreach (cms_db()->query('SELECT * FROM visit_kinds ORDER BY sort_order, kind_key') as $row) {
            $rows[] = [
                'key' => (string) $row['kind_key'],
                'fa' => (string) $row['label_fa'],
                'en' => (string) $row['label_en'],
                'ar' => (string) $row['label_ar'],
                'zh' => (string) $row['label_zh'],
                'minutes' => max(10, min(180, (int) $row['minutes'])),
                'active' => (int) $row['active'] === 1,
                'sort' => (int) $row['sort_order'],
            ];
        }
    }
    if (!$activeOnly) {
        return $rows;
    }
    return array_values(array_filter($rows, static fn (array $kind): bool => $kind['active']));
}

function cms_kind_row(string $key): ?array
{
    foreach (cms_visit_kinds() as $kind) {
        if ($kind['key'] === $key) {
            return $kind;
        }
    }
    return null;
}

function cms_kind_label(string $key, string $locale = 'fa'): string
{
    $kind = cms_kind_row($key);
    if (!$kind) {
        return $key;
    }
    $locale = cms_locale($locale);
    $text = trim($kind[$locale] ?? '');
    if ($text !== '') {
        return $text;
    }
    return $kind['fa'] !== '' ? $kind['fa'] : $key;
}

function cms_kind_minutes(string $key): int
{
    $kind = cms_kind_row($key);
    return $kind ? $kind['minutes'] : 30;
}

function cms_visit_topics(bool $activeOnly = false): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        foreach (cms_db()->query('SELECT * FROM visit_topics ORDER BY sort_order, topic_key') as $row) {
            $rows[] = [
                'key' => (string) $row['topic_key'],
                'fa' => (string) $row['label_fa'],
                'en' => (string) $row['label_en'],
                'ar' => (string) $row['label_ar'],
                'zh' => (string) $row['label_zh'],
                'active' => (int) $row['active'] === 1,
                'sort' => (int) $row['sort_order'],
                'code' => (string) ($row['service_code'] ?? ''),
            ];
        }
    }
    if (!$activeOnly) {
        return $rows;
    }
    return array_values(array_filter($rows, static fn (array $topic): bool => $topic['active']));
}

function cms_topic_row(string $key): ?array
{
    foreach (cms_visit_topics() as $topic) {
        if ($topic['key'] === $key) {
            return $topic;
        }
    }
    return null;
}

function cms_topic_code(string $key): string
{
    if (!preg_match('/^[a-z0-9-]{2,40}$/', $key)) {
        return '';
    }
    $stmt = cms_db()->prepare('SELECT service_code FROM visit_topics WHERE topic_key = ?');
    $stmt->execute([$key]);
    return cms_plain((string) $stmt->fetchColumn(), 24);
}

function cms_topic_label(string $key, string $locale = 'fa'): string
{
    $topic = cms_topic_row($key);
    $locale = cms_locale($locale);
    if ($topic) {
        $text = trim($topic[$locale] ?? '');
        if ($text !== '') {
            return $text;
        }
        if ($topic['fa'] !== '') {
            return $topic['fa'];
        }
    }
    return cms_service_catalog()[$key] ?? $key;
}

function cms_book_capacity(): int
{
    $value = (int) cms_setting('book_capacity', '1');
    if ($value < 1) {
        return 1;
    }
    return min(8, $value);
}

function cms_book_horizon(): int
{
    $value = (int) cms_setting('book_horizon', '90');
    if ($value < 7) {
        return 7;
    }
    return min(365, $value);
}

function cms_book_lead(): int
{
    $value = (int) cms_setting('book_lead', '0');
    if ($value < 0) {
        return 0;
    }
    return min(60, $value);
}

function cms_book_public(): bool
{
    return cms_setting('book_public', '1') !== '0';
}

function cms_book_cutoff(): int
{
    $value = (int) cms_setting('book_cutoff', '24');
    if ($value < 0 || $value > 23) {
        return 24;
    }
    return $value;
}

function cms_book_phone_cap(): int
{
    $value = (int) cms_setting('book_phone_cap', '0');
    if ($value < 0) {
        return 0;
    }
    return min(5, $value);
}

function cms_book_hold_new(): bool
{
    return cms_setting('book_hold_new', '1') !== '0';
}

function cms_book_field_mode(string $key): string
{
    $value = cms_setting($key, 'optional');
    return in_array($value, ['off', 'optional', 'required'], true) ? $value : 'optional';
}

function cms_remind_days(): int
{
    $value = (int) cms_setting('remind_days', '1');
    if ($value < 1) {
        return 1;
    }
    return min(3, $value);
}

function cms_remind_grace(): int
{
    $value = (int) cms_setting('remind_grace', '2');
    if ($value < 1) {
        return 2;
    }
    return min(7, $value);
}

function cms_quiet_now(?DateTimeImmutable $now = null): bool
{
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran'));
    $hour = (int) $now->format('G');
    $quietFrom = (int) cms_setting('sms_quiet_from', '21');
    $quietUntil = (int) cms_setting('sms_quiet_until', '8');
    return $hour >= $quietFrom || $hour < $quietUntil;
}

function cms_remind_bounds(string $today, int $lead, int $grace): array
{
    $lead = max(1, min(3, $lead));
    $grace = max(1, min(7, $grace));
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $today, new DateTimeZone('Asia/Tehran'));
    if (!$base) {
        return [$today, $today];
    }
    $latest = $base->modify('+' . $lead . ' days')->format('Y-m-d');
    $earliest = $base->modify('-' . ($grace - 1) . ' days')->modify('+' . $lead . ' days')->format('Y-m-d');
    if ($earliest < $today) {
        $earliest = $today;
    }
    return [$earliest, $latest];
}

function cms_self_bounds(string $today, int $grace): array
{
    $grace = max(1, min(7, $grace));
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $today, new DateTimeZone('Asia/Tehran'));
    if (!$base) {
        return [$today, $today];
    }
    return [$base->modify('-' . ($grace - 1) . ' days')->format('Y-m-d'), $today];
}

function cms_visit_when(string $day, string $today, string $locale): string
{
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $today, new DateTimeZone('Asia/Tehran'));
    $tomorrow = $base ? $base->modify('+1 day')->format('Y-m-d') : '';
    $words = [
        'fa' => ['امروز', 'فردا'],
        'en' => ['today', 'tomorrow'],
        'ar' => ['اليوم', 'غدًا'],
        'zh' => ['今天', '明天'],
    ];
    $pair = $words[$locale] ?? $words['en'];
    if ($day === $today) {
        return $pair[0];
    }
    if ($day !== '' && $day === $tomorrow) {
        return $pair[1];
    }
    return '';
}

function cms_slot_statuses(): array
{
    return cms_book_hold_new() ? ['new', 'confirmed', 'arrived'] : ['confirmed', 'arrived'];
}

function cms_book_closed_weekdays(): array
{
    $stmt = cms_db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute(['book_closed']);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        return [5];
    }
    $days = [];
    foreach (explode(',', (string) $value) as $part) {
        $part = trim($part);
        if ($part === '' || !preg_match('/^[0-6]$/', $part)) {
            continue;
        }
        $days[] = (int) $part;
    }
    return array_values(array_unique($days));
}

function cms_book_blocks(): array
{
    $rows = [];
    foreach (cms_db()->query('SELECT day, note FROM visit_blocks ORDER BY day') as $row) {
        $rows[] = ['day' => (string) $row['day'], 'note' => (string) $row['note']];
    }
    return $rows;
}

function cms_clock_minutes(string $time): int
{
    if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $match)) {
        return -1;
    }
    return ((int) $match[1]) * 60 + (int) $match[2];
}

function cms_time_covers(string $start, int $minutes, string $slot): bool
{
    $from = cms_clock_minutes($start);
    $point = cms_clock_minutes($slot);
    $span = max(10, $minutes);
    return $from >= 0 && $point >= $from && $point < $from + $span;
}

function cms_clinic_day(string $day): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('Asia/Tehran'));
    if (!$date || $date->format('Y-m-d') !== $day) {
        return false;
    }
    if (in_array((int) $date->format('w'), cms_book_closed_weekdays(), true)) {
        return false;
    }
    foreach (cms_book_blocks() as $block) {
        if ($block['day'] === $day) {
            return false;
        }
    }
    return true;
}

function cms_month_rate(?int $part, int $whole): ?int
{
    if ($part === null || $whole < 1) {
        return null;
    }
    return (int) round($part * 100 / $whole);
}

function cms_report_sheet(array $report, int $year, int $month): string
{
    if (empty($report['ok'])) {
        return '';
    }
    $line = static function (array $cells): string {
        return implode(',', array_map(static function ($cell): string {
            return '"' . str_replace('"', '""', (string) $cell) . '"';
        }, $cells));
    };
    $out = $line(['year', 'month', 'fill', 'miss', 'held', 'capacity', 'noshow', 'seen', 'cancelled', 'fresh']) . "\n";
    $out .= $line([$year, $month, $report['fill'] ?? '', $report['miss'] ?? '', $report['held'] ?? '', $report['capacity'] ?? '', $report['noshow'] ?? '', $report['seen'] ?? '', $report['cancelled'] ?? '', $report['fresh'] ?? '']) . "\n";
    return $out;
}

function cms_month_report(int $year, int $month, string $today): array
{
    $span = cms_jalali_span($year, $month);
    if ($span['start'] === '') {
        return ['ok' => false];
    }
    $slots = count(cms_book_slots());
    $stmt = cms_db()->prepare('SELECT day, status, COUNT(*) AS n FROM visits WHERE day >= ? AND day <= ? GROUP BY day, status');
    $stmt->execute([$span['start'], $span['end']]);
    $byDay = [];
    foreach ($stmt as $row) {
        $byDay[(string) $row['day']][(string) $row['status']] = (int) $row['n'];
    }
    $kindStmt = cms_db()->prepare('SELECT kind, status, COUNT(*) AS n FROM visits WHERE day >= ? AND day <= ? GROUP BY kind, status');
    $kindStmt->execute([$span['start'], $span['end']]);
    $byKind = [];
    foreach ($kindStmt as $row) {
        $byKind[(string) $row['kind']][(string) $row['status']] = (int) $row['n'];
    }
    $zone = new DateTimeZone('Asia/Tehran');
    $cursor = DateTimeImmutable::createFromFormat('!Y-m-d', $span['start'], $zone);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $span['end'], $zone);
    $days = [];
    $capacity = 0;
    $held = 0;
    $noshow = 0;
    $seen = 0;
    $cancelled = 0;
    $fresh = 0;
    $confirmedPast = 0;
    $confirmedAhead = 0;
    while ($cursor instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $cursor <= $end) {
        $iso = $cursor->format('Y-m-d');
        $open = cms_clinic_day($iso);
        $cap = $open ? $slots : 0;
        $counts = $byDay[$iso] ?? [];
        $rowHeld = 0;
        foreach ($counts as $status => $count) {
            if ($status !== 'cancelled') {
                $rowHeld += $count;
            }
        }
        $rowSeen = (int) ($counts['arrived'] ?? 0) + (int) ($counts['done'] ?? 0);
        $rowNoshow = (int) ($counts['noshow'] ?? 0);
        $rowConfirmed = (int) ($counts['confirmed'] ?? 0);
        $days[] = [
            'day' => $iso,
            'open' => $open,
            'capacity' => $cap,
            'held' => $rowHeld,
            'noshow' => $rowNoshow,
            'seen' => $rowSeen,
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
        $capacity += $cap;
        $held += $rowHeld;
        $noshow += $rowNoshow;
        $seen += $rowSeen;
        $cancelled += (int) ($counts['cancelled'] ?? 0);
        $fresh += (int) ($counts['new'] ?? 0);
        if ($iso < $today) {
            $confirmedPast += $rowConfirmed;
        } else {
            $confirmedAhead += $rowConfirmed;
        }
        $cursor = $cursor->modify('+1 day');
    }
    $kinds = [];
    foreach ($byKind as $key => $counts) {
        $kindHeld = 0;
        foreach ($counts as $status => $count) {
            if ($status !== 'cancelled') {
                $kindHeld += $count;
            }
        }
        $kinds[] = [
            'key' => $key,
            'held' => $kindHeld,
            'noshow' => (int) ($counts['noshow'] ?? 0),
            'seen' => (int) ($counts['arrived'] ?? 0) + (int) ($counts['done'] ?? 0),
        ];
    }
    return [
        'ok' => true,
        'start' => $span['start'],
        'end' => $span['end'],
        'capacity' => $capacity,
        'held' => $held,
        'noshow' => $noshow,
        'seen' => $seen,
        'cancelled' => $cancelled,
        'fresh' => $fresh,
        'confirmed_past' => $confirmedPast,
        'confirmed_ahead' => $confirmedAhead,
        'fill' => cms_month_rate($held, $capacity),
        'miss' => cms_month_rate($noshow, $noshow + $seen),
        'days' => $days,
        'kinds' => $kinds,
    ];
}

function cms_day_open(string $day): bool
{
    $zone = new DateTimeZone('Asia/Tehran');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, $zone);
    $today = new DateTimeImmutable('today', $zone);
    $first = $today->modify('+' . cms_book_lead() . ' days');
    $last = $today->modify('+' . cms_book_horizon() . ' days');
    if (!$date || $date < $first || $date > $last) {
        return false;
    }
    $cutoff = cms_book_cutoff();
    if ($cutoff < 24 && $date->format('Y-m-d') === $today->format('Y-m-d')) {
        $hour = (int) (new DateTimeImmutable('now', $zone))->format('G');
        if ($hour >= $cutoff) {
            return false;
        }
    }
    if (in_array((int) $date->format('w'), cms_book_closed_weekdays(), true)) {
        return false;
    }
    foreach (cms_book_blocks() as $block) {
        if ($block['day'] === $day) {
            return false;
        }
    }
    return true;
}

function cms_places(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM book_places';
    if ($activeOnly) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY sort_order, place_key';
    $rows = [];
    foreach (cms_db()->query($sql) as $row) {
        $rows[] = $row;
    }
    return $rows;
}

function cms_place_row(string $key): ?array
{
    if (!preg_match('/^[a-z0-9-]{2,24}$/', $key)) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM book_places WHERE place_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cms_place_label(array $place, string $locale = 'fa'): string
{
    $column = ['fa' => 'label_fa', 'en' => 'label_en', 'ar' => 'label_ar', 'zh' => 'label_zh'][$locale] ?? 'label_fa';
    $label = trim((string) ($place[$column] ?? ''));
    return $label !== '' ? $label : (string) $place['label_fa'];
}

function cms_place_weekdays(string $raw): array
{
    $days = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if (preg_match('/^[0-6]$/', $part)) {
            $days[] = (int) $part;
        }
    }
    return array_values(array_unique($days));
}

function cms_book_window(string $day): bool
{
    $zone = new DateTimeZone('Asia/Tehran');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, $zone);
    $today = new DateTimeImmutable('today', $zone);
    $first = $today->modify('+' . cms_book_lead() . ' days');
    $last = $today->modify('+' . cms_book_horizon() . ' days');
    if (!$date || $date < $first || $date > $last) {
        return false;
    }
    $cutoff = cms_book_cutoff();
    if ($cutoff < 24 && $date->format('Y-m-d') === $today->format('Y-m-d')) {
        $hour = (int) (new DateTimeImmutable('now', $zone))->format('G');
        if ($hour >= $cutoff) {
            return false;
        }
    }
    foreach (cms_book_blocks() as $block) {
        if ($block['day'] === $day) {
            return false;
        }
    }
    return true;
}

function cms_place_offers(array $place, string $day, string $time): bool
{
    if ((int) ($place['active'] ?? 0) !== 1 || !in_array($time, cms_book_slots(), true)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('Asia/Tehran'));
    if (!$date || $date->format('Y-m-d') !== $day) {
        return false;
    }
    if (!in_array((int) $date->format('w'), cms_place_weekdays((string) ($place['weekdays'] ?? '')), true)) {
        return false;
    }
    $start = (string) ($place['start_time'] ?? '');
    $end = (string) ($place['end_time'] ?? '');
    if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $time < $start || $time >= $end) {
        return false;
    }
    return cms_book_window($day);
}

function cms_staff_places(string $raw): array
{
    $keys = [];
    foreach (explode(',', strtolower($raw)) as $part) {
        $key = preg_replace('/[^a-z0-9-]/', '', $part) ?? '';
        if (preg_match('/^[a-z0-9-]{2,24}$/', $key)) {
            $keys[] = $key;
        }
    }
    $keys = array_values(array_unique($keys));
    sort($keys);
    return $keys;
}

function cms_staff_may(string $raw, string $place): bool
{
    $keys = cms_staff_places($raw);
    return $keys === [] || in_array($place, $keys, true);
}

function cms_staff_names(string $raw): array
{
    $names = preg_split('/[\s,،]+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
    return is_array($names) ? array_values(array_unique($names)) : [];
}

function cms_staff_place_ids(array $names): array
{
    $ids = [];
    $staff = cms_db()->prepare('SELECT id FROM admins WHERE username = ? AND role = ? AND active = 1');
    foreach ($names as $name) {
        $staff->execute([$name, 'editor']);
        $id = (int) $staff->fetchColumn();
        if ($id < 1) {
            return [];
        }
        $ids[] = $id;
    }
    return $ids;
}

function cms_staff_grant(int $id, string $place, bool $grant): void
{
    $stmt = cms_db()->prepare('SELECT place_key FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    $keys = cms_staff_places((string) $stmt->fetchColumn());
    if ($grant) {
        $keys[] = $place;
    } else {
        $keys = array_values(array_filter($keys, static fn (string $key): bool => $key !== $place));
    }
    $keys = array_values(array_unique($keys));
    sort($keys);
    cms_db()->prepare('UPDATE admins SET place_key = ? WHERE id = ?')->execute([implode(',', $keys), $id]);
}

function cms_place_secretaries(string $key): array
{
    $names = [];
    foreach (cms_db()->query("SELECT username, display_name, place_key FROM admins WHERE role = 'editor' AND active = 1") as $row) {
        if (!in_array($key, cms_staff_places((string) $row['place_key']), true)) {
            continue;
        }
        $label = cms_plain((string) $row['display_name'], 40);
        $names[] = $label !== '' ? $label : (string) $row['username'];
    }
    return $names;
}

function cms_place_save(array $fields): array
{
    $key = strtolower(preg_replace('/[^a-z0-9-]/', '', (string) ($fields['key'] ?? '')) ?? '');
    $label = cms_plain((string) ($fields['label_fa'] ?? ''), 80);
    if (!preg_match('/^[a-z0-9-]{2,24}$/', $key) || cms_chars($label) < 2) {
        return ['ok' => false, 'error' => 'نام مکان و شناسهٔ لاتین را بنویسید.'];
    }
    $kind = (string) ($fields['kind'] ?? 'clinic');
    if (!in_array($kind, ['clinic', 'hospital', 'other'], true)) {
        $kind = 'clinic';
    }
    $days = [];
    foreach ((array) ($fields['weekdays'] ?? []) as $day) {
        if (preg_match('/^[0-6]$/', (string) $day)) {
            $days[] = (string) $day;
        }
    }
    $days = array_values(array_unique($days));
    $start = (string) ($fields['start'] ?? '');
    $end = (string) ($fields['end'] ?? '');
    if (!$days || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
        return ['ok' => false, 'error' => 'روزهای کاری و بازهٔ ساعت را درست بنویسید.'];
    }
    $grantNames = cms_staff_names((string) ($fields['secretary'] ?? ''));
    $dropNames = cms_staff_names((string) ($fields['release'] ?? ''));
    if (array_intersect($grantNames, $dropNames) !== []) {
        return ['ok' => false, 'error' => 'یک نام را هم‌زمان اضافه و حذف نکنید.'];
    }
    $grantIds = cms_staff_place_ids($grantNames);
    $dropIds = cms_staff_place_ids($dropNames);
    if (count($grantIds) !== count($grantNames) || count($dropIds) !== count($dropNames)) {
        return ['ok' => false, 'error' => 'منشی باید یک کاربر فعال با نقش ویراستار باشد.'];
    }
    cms_db()->prepare('INSERT INTO book_places (place_key, label_fa, label_en, label_ar, label_zh, kind, address, weekdays, start_time, end_time, active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(place_key) DO UPDATE SET label_fa = excluded.label_fa, label_en = excluded.label_en, label_ar = excluded.label_ar, label_zh = excluded.label_zh, kind = excluded.kind, address = excluded.address, weekdays = excluded.weekdays, start_time = excluded.start_time, end_time = excluded.end_time, active = excluded.active, sort_order = excluded.sort_order')->execute([
        $key,
        $label,
        cms_plain((string) ($fields['label_en'] ?? ''), 80),
        cms_plain((string) ($fields['label_ar'] ?? ''), 80),
        cms_plain((string) ($fields['label_zh'] ?? ''), 80),
        $kind,
        cms_plain((string) ($fields['address'] ?? ''), 180),
        implode(',', $days),
        $start,
        $end,
        !empty($fields['active']) ? 1 : 0,
        max(0, min(99, (int) ($fields['sort'] ?? 0))),
    ]);
    foreach ($grantIds as $id) {
        cms_staff_grant($id, $key, true);
    }
    foreach ($dropIds as $id) {
        cms_staff_grant($id, $key, false);
    }
    return ['ok' => true, 'error' => '', 'key' => $key];
}

function cms_slot_holders(string $day, string $time, string $kind, int $exceptId = 0): array
{
    $openStatuses = "'" . implode("','", cms_slot_statuses()) . "'";
    $stmt = cms_db()->prepare("SELECT id, name, place_key, time_slot, kind, booked_by FROM visits WHERE day = ? AND status IN ($openStatuses)");
    $stmt->execute([$day]);
    $holders = [];
    foreach ($stmt as $row) {
        if ((int) $row['id'] === $exceptId) {
            continue;
        }
        $span = cms_kind_minutes((string) ($row['kind'] ?? 'exam'));
        $hit = cms_time_covers((string) $row['time_slot'], $span, $time) || cms_time_covers($time, cms_kind_minutes($kind), (string) $row['time_slot']);
        if (!$hit) {
            continue;
        }
        $holders[] = [
            'name' => (string) $row['name'],
            'place' => (string) $row['place_key'],
            'time' => (string) $row['time_slot'],
            'booked_by' => (string) $row['booked_by'],
        ];
    }
    return $holders;
}

function cms_hold_sentence(array $holders, string $day): string
{
    if (!$holders) {
        return 'این ساعت پر است.';
    }
    $parts = [];
    foreach (array_slice($holders, 0, 3) as $holder) {
        $place = cms_place_row((string) $holder['place']);
        $where = $place ? cms_place_label($place) : 'برنامهٔ عمومی';
        $who = (string) $holder['booked_by'] !== '' ? (string) $holder['booked_by'] : 'میز';
        $parts[] = (string) $holder['name'] . ' در ' . $where . '، ' . cms_day_label($day) . ' ' . cms_fa_digits((string) $holder['time']) . '، ثبت ' . $who;
    }
    return 'این ساعت پر است. ' . implode('؛ ', $parts) . '.';
}

function cms_room_key(string $key): string
{
    $key = strtolower(preg_replace('/[^a-z0-9-]/', '', $key) ?? '');
    return preg_match('/^[a-z0-9-]{2,24}$/', $key) ? $key : '';
}

function cms_rooms(string $place, bool $activeOnly = true): array
{
    $place = cms_room_key($place);
    if ($place === '') {
        return [];
    }
    $sql = 'SELECT * FROM book_rooms WHERE place_key = ?';
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY label, room_key';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute([$place]);
    return $stmt->fetchAll() ?: [];
}

function cms_room_row(string $place, string $room): ?array
{
    $place = cms_room_key($place);
    $room = cms_room_key($room);
    if ($place === '' || $room === '') {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM book_rooms WHERE place_key = ? AND room_key = ? AND active = 1');
    $stmt->execute([$place, $room]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cms_room_label(string $place, string $room): string
{
    $row = cms_room_row($place, $room);
    return $row ? (string) $row['label'] : '';
}

function cms_room_save(string $place, string $key, string $label): array
{
    $place = cms_room_key($place);
    $key = cms_room_key($key);
    $label = cms_plain($label, 40);
    if ($place === '' || !cms_place_row($place) || $key === '' || cms_chars($label) < 2) {
        return ['ok' => false, 'error' => 'اتاق به یک مکان ساخته‌شده، شناسهٔ لاتین و نام نیاز دارد.'];
    }
    cms_db()->prepare('INSERT INTO book_rooms (place_key, room_key, label, active) VALUES (?,?,?,1) ON CONFLICT(place_key, room_key) DO UPDATE SET label = excluded.label, active = 1')->execute([$place, $key, $label]);
    return ['ok' => true, 'error' => '', 'key' => $key];
}

function cms_ranges_overlap(string $aStart, int $aMinutes, string $bStart, int $bMinutes): bool
{
    $a = cms_clock_minutes($aStart);
    $b = cms_clock_minutes($bStart);
    if ($a < 0 || $b < 0 || $aMinutes < 1 || $bMinutes < 1) {
        return false;
    }
    return $a < $b + $bMinutes && $b < $a + $aMinutes;
}

function cms_room_holders(string $day, string $time, string $kind, string $place, string $room, int $exceptId = 0): array
{
    $place = cms_room_key($place);
    $room = cms_room_key($room);
    if ($place === '' || $room === '') {
        return [];
    }
    $open = "'" . implode("','", cms_slot_statuses()) . "'";
    $stmt = cms_db()->prepare("SELECT id, name, time_slot, kind, place_key, booked_by FROM visits WHERE day = ? AND place_key = ? AND room_key = ? AND status IN ($open)");
    $stmt->execute([$day, $place, $room]);
    $holders = [];
    $minutes = cms_kind_minutes($kind);
    foreach ($stmt as $row) {
        if ((int) $row['id'] === $exceptId) {
            continue;
        }
        if (!cms_ranges_overlap($time, $minutes, (string) $row['time_slot'], cms_kind_minutes((string) $row['kind']))) {
            continue;
        }
        $holders[] = [
            'name' => (string) $row['name'],
            'place' => (string) $row['place_key'],
            'time' => (string) $row['time_slot'],
            'booked_by' => (string) $row['booked_by'],
            'room' => $room,
        ];
    }
    return $holders;
}

function cms_room_free(string $day, string $time, string $kind, string $place, string $room, int $exceptId = 0): bool
{
    return $room === '' || cms_room_holders($day, $time, $kind, $place, $room, $exceptId) === [];
}

function cms_slot_free(string $day, string $time, string $kind, int $exceptId = 0): bool
{
    if (!in_array($time, cms_book_slots(), true)) {
        return false;
    }
    $wanted = [];
    foreach (cms_book_slots() as $slot) {
        if (cms_time_covers($time, cms_kind_minutes($kind), $slot)) {
            $wanted[] = $slot;
        }
    }
    if (!$wanted) {
        return false;
    }
    $openStatuses = "'" . implode("','", cms_slot_statuses()) . "'";
    $stmt = cms_db()->prepare("SELECT id, time_slot, kind FROM visits WHERE day = ? AND status IN ($openStatuses)");
    $stmt->execute([$day]);
    $load = array_fill_keys($wanted, 0);
    foreach ($stmt as $row) {
        if ((int) $row['id'] === $exceptId) {
            continue;
        }
        $span = cms_kind_minutes((string) ($row['kind'] ?? 'exam'));
        foreach ($wanted as $slot) {
            if (cms_time_covers((string) $row['time_slot'], $span, $slot)) {
                $load[$slot]++;
            }
        }
    }
    $cap = cms_book_capacity();
    foreach ($load as $count) {
        if ($count >= $cap) {
            return false;
        }
    }
    return true;
}

function cms_booking_public(string $locale): array
{
    $locale = cms_locale($locale);
    $zone = new DateTimeZone('Asia/Tehran');
    $today = new DateTimeImmutable('today', $zone);
    $first = $today->modify('+' . cms_book_lead() . ' days')->format('Y-m-d');
    $last = $today->modify('+' . cms_book_horizon() . ' days')->format('Y-m-d');
    $kinds = [];
    foreach (cms_visit_kinds(true) as $kind) {
        $label = trim($kind[$locale] ?? '');
        $kinds[] = [
            'key' => $kind['key'],
            'label' => $label !== '' ? $label : $kind['fa'],
            'minutes' => $kind['minutes'],
        ];
    }
    $topics = [];
    foreach (cms_visit_topics(true) as $topic) {
        $label = trim($topic[$locale] ?? '');
        $topics[] = [
            'key' => $topic['key'],
            'label' => $label !== '' ? $label : $topic['fa'],
        ];
    }
    $blocked = [];
    foreach (cms_book_blocks() as $block) {
        if ($block['day'] >= $today->format('Y-m-d') && $block['day'] <= $last) {
            $blocked[] = $block['day'];
        }
    }
    $openStatuses = "'" . implode("','", cms_slot_statuses()) . "'";
    $stmt = cms_db()->prepare("SELECT day, time_slot, kind FROM visits WHERE status IN ($openStatuses) AND day >= ? AND day <= ? ORDER BY day, time_slot LIMIT 400");
    $stmt->execute([$first, $last]);
    $busy = [];
    foreach ($stmt as $row) {
        $busy[] = [
            'day' => (string) $row['day'],
            'start' => (string) $row['time_slot'],
            'minutes' => cms_kind_minutes((string) ($row['kind'] ?? 'exam')),
        ];
    }
    return [
        'slots' => cms_book_slots(),
        'closedWeekdays' => cms_book_closed_weekdays(),
        'blocked' => $blocked,
        'capacity' => cms_book_capacity(),
        'horizon' => cms_book_horizon(),
        'leadDays' => cms_book_lead(),
        'open' => cms_book_public(),
        'cutoff' => cms_book_cutoff(),
        'email' => cms_book_field_mode('book_email'),
        'reason' => cms_book_field_mode('book_reason'),
        'busy' => $busy,
        'kinds' => $kinds,
        'topics' => $topics,
        'places' => array_map(static function (array $place) use ($locale): array {
            return [
                'key' => (string) $place['place_key'],
                'label' => cms_place_label($place, $locale),
                'kind' => (string) $place['kind'],
                'address' => (string) $place['address'],
                'weekdays' => cms_place_weekdays((string) $place['weekdays']),
                'start' => (string) $place['start_time'],
                'end' => (string) $place['end_time'],
            ];
        }, cms_places(true)),
    ];
}

function cms_mobile(string $phone): string
{
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($phone, '98') && strlen($phone) === 12) {
        $phone = '0' . substr($phone, 2);
    }
    return preg_match('/^09\d{9}$/', $phone) ? $phone : '';
}

function cms_visit_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function cms_visit_create(array $fields): array
{
    $service = (string) ($fields['service'] ?? '');
    $kind = (string) ($fields['kind'] ?? 'exam');
    $day = (string) ($fields['day'] ?? '');
    $time = (string) ($fields['time'] ?? '');
    $name = cms_plain((string) ($fields['name'] ?? ''), 80);
    $phone = preg_replace('/\D+/', '', (string) ($fields['phone'] ?? '')) ?? '';
    if (str_starts_with($phone, '98')) {
        $phone = '0' . substr($phone, 2);
    }
    $email = trim((string) ($fields['email'] ?? ''));
    $message = cms_plain((string) ($fields['message'] ?? ''), 400);
    $note = cms_plain((string) ($fields['note'] ?? ''), 1000);
    $locale = cms_locale((string) ($fields['locale'] ?? 'fa'));
    $source = ($fields['source'] ?? '') === 'desk' ? 'desk' : 'site';
    $status = (string) ($fields['status'] ?? 'new');
    if (!in_array($status, ['new', 'confirmed'], true)) {
        $status = 'new';
    }
    $place = strtolower(preg_replace('/[^a-z0-9-]/', '', (string) ($fields['place'] ?? '')) ?? '');
    $staffPlaces = cms_staff_places((string) ($fields['staff_place'] ?? ''));
    $bookedBy = cms_plain((string) ($fields['booked_by'] ?? ''), 80);
    if ($bookedBy === '') {
        $bookedBy = $source === 'desk' ? 'میز' : 'سایت';
    }
    $kindRow = cms_kind_row($kind);
    $topic = cms_topic_row($service);
    if (!$kindRow || !$kindRow['active'] || !$topic || !$topic['active']) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || cms_chars($name) < 2 || strlen($phone) < 10 || strlen($phone) > 11) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    if ($source === 'site' && !cms_book_public()) {
        return ['ok' => false, 'error' => 'paused'];
    }
    $emailMode = $source === 'site' ? cms_book_field_mode('book_email') : 'optional';
    $reasonMode = $source === 'site' ? cms_book_field_mode('book_reason') : 'optional';
    if ($emailMode === 'off') {
        $email = '';
    }
    if ($reasonMode === 'off') {
        $message = '';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    if ($emailMode === 'required' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    if ($reasonMode === 'required' && cms_chars($message) < 2) {
        return ['ok' => false, 'error' => 'invalid'];
    }
    $activePlaces = cms_places(true);
    if ($activePlaces) {
        if ($staffPlaces !== [] && !in_array($place, $staffPlaces, true)) {
            return ['ok' => false, 'error' => 'place'];
        }
        $placeRow = cms_place_row($place);
        if (!$placeRow || !cms_place_offers($placeRow, $day, $time)) {
            return ['ok' => false, 'error' => 'closed'];
        }
    } elseif (!cms_day_open($day)) {
        return ['ok' => false, 'error' => 'closed'];
    }
    if ($source === 'site') {
        $hash = cms_ip_hash();
        $recent = cms_db()->prepare('SELECT COUNT(*) FROM visits WHERE ip_hash = ? AND created_at > ?');
        $recent->execute([$hash, gmdate('c', time() - 3600)]);
        if ((int) $recent->fetchColumn() >= 6) {
            return ['ok' => false, 'error' => 'limit'];
        }
    } else {
        $hash = cms_ip_hash();
    }
    $pdo = cms_db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $phoneCap = $source === 'site' ? cms_book_phone_cap() : 0;
        if ($phoneCap > 0) {
            $openStatuses = "'" . implode("','", cms_slot_statuses()) . "'";
            $phoneCount = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE phone = ? AND status IN ($openStatuses)");
            $phoneCount->execute([$phone]);
            if ((int) $phoneCount->fetchColumn() >= $phoneCap) {
                $pdo->exec('ROLLBACK');
                return ['ok' => false, 'error' => 'phone'];
            }
        }
        if (!cms_slot_free($day, $time, $kind)) {
            $holders = cms_slot_holders($day, $time, $kind);
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'error' => 'full', 'holders' => $holders, 'day' => $day];
        }
        $now = cms_now();
        $stmt = $pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, kind, day, time_slot, message, note, locale, ip_hash, source, created_at, updated_at, reminded_at, place_key, booked_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $code = '';
        $saved = false;
        for ($try = 0; $try < 4; $try++) {
            $code = cms_visit_code();
            try {
                $stmt->execute([$code, $status, $name, $phone, $email, $service, $kind, $day, $time, $message, $note, $locale, $hash, $source, $now, $now, '', $place, $bookedBy]);
                $saved = true;
                break;
            } catch (PDOException) {
                $saved = false;
            }
        }
        if (!$saved) {
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'error' => 'invalid'];
        }
        $id = (int) $pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        return ['ok' => false, 'error' => 'invalid'];
    }
    cms_patient_ensure($phone, $name);
    $row = cms_visit_row($id);
    if ($row) {
        cms_notify_visit($row, $status === 'confirmed' ? 'confirmed' : 'created');
    }
    cms_log('info', 'visit', 'created', 'درخواست نوبت ثبت شد', ['code' => $code, 'locale' => $locale, 'source' => $source]);
    return ['ok' => true, 'error' => '', 'code' => $code, 'id' => $id];
}

function cms_patient_ensure(string $phone, string $name): void
{
    $phone = preg_replace('/\D+/', '', $phone) ?? '';
    if ($phone === '') {
        return;
    }
    $stmt = cms_db()->prepare('SELECT phone FROM patients WHERE phone = ?');
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn()) {
        return;
    }
    cms_db()->prepare('INSERT INTO patients (phone, name, note, created_at, updated_at) VALUES (?,?,?,?,?)')->execute([
        $phone,
        cms_plain($name, 80),
        '',
        cms_now(),
        cms_now(),
    ]);
}

function cms_patient_row(string $phone): ?array
{
    $stmt = cms_db()->prepare('SELECT * FROM patients WHERE phone = ?');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cms_visit_row(int $id): ?array
{
    $stmt = cms_db()->prepare('SELECT * FROM visits WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cms_setting_forget(string $key): void
{
    if (!preg_match('/^[a-z0-9_]{1,40}$/', $key)) {
        return;
    }
    cms_db()->prepare('DELETE FROM settings WHERE key = ?')->execute([$key]);
}

function cms_tpl_default(string $audience, string $event, string $locale): string
{
    $fa = [
        'patient' => [
            'created' => '{name} عزیز، درخواست نوبت شما در مطب دکتر مهرشاد جعفری ثبت شد. کد پیگیری: {code}. زمان پیشنهادی: {day} ساعت {time}. این نوبت هنوز قطعی نیست و مطب برای هماهنگی تماس می‌گیرد.',
            'confirmed' => '{name} عزیز، نوبت شما برای {day} ساعت {time} هماهنگ شد. موضوع: {kind}. کد پیگیری: {code}. اگر نمی‌توانید بیایید زودتر به مطب خبر بدهید.',
            'rescheduled' => '{name} عزیز، زمان نوبت شما عوض شد. زمان تازه: {day} ساعت {time}. کد پیگیری: {code}.',
            'cancelled' => '{name} عزیز، درخواست نوبت با کد {code} لغو شد. برای زمان دیگر، صفحه نوبت سایت را باز کنید.',
            'reminder' => 'یادآوری: {name} عزیز، {when} {day} ساعت {time} نوبت شما در مطب دکتر مهرشاد جعفری است. کد {code}.',
        ],
        'doctor' => [
            'created' => 'درخواست نوبت تازه. {name}، {phone}، {kind} / {service}، {day} {time}، کد {code}.',
            'confirmed' => 'نوبت هماهنگ شد. {name}، {phone}، {kind} / {service}، {day} {time}، کد {code}.',
            'rescheduled' => 'زمان نوبت عوض شد. {name}، {phone}، {day} {time}، کد {code}.',
            'cancelled' => 'نوبت لغو شد. {name}، {phone}، {day} {time}، کد {code}.',
            'reminder' => 'یادآوری نوبت. {name}، {phone}، {when} {day} {time}، کد {code}.',
        ],
    ];
    $en = [
        'patient' => [
            'created' => 'Hello {name}. Your visit request at Dr. Mehrshad Jafari clinic is saved. Code: {code}. Suggested time: {day} {time}. The clinic calls before this visit is confirmed.',
            'confirmed' => 'Hello {name}. Your visit is arranged for {day} at {time}. Code: {code}. Please tell the clinic early if you cannot come.',
            'rescheduled' => 'Hello {name}. Your visit time changed to {day} at {time}. Code: {code}.',
            'cancelled' => 'Hello {name}. The visit request {code} was cancelled. Use the appointment page to ask for another time.',
            'reminder' => 'Reminder: {name}, your visit at Dr. Mehrshad Jafari clinic is {when}, {day} at {time}. Code: {code}.',
        ],
    ];
    $pack = $fa[$audience][$event] ?? '';
    if ($audience === 'patient' && $locale === 'en') {
        $pack = $en['patient'][$event] ?? $pack;
    }
    return $pack;
}

function cms_tpl_stale(string $text): bool
{
    return in_array(trim($text), [
        'یادآوری: {name} عزیز، فردا {day} ساعت {time} نوبت شما در مطب دکتر مهرشاد جعفری است. کد {code}.',
        'یادآوری فردا. {name}، {phone}، {day} {time}، کد {code}.',
        'Reminder: {name}, your visit at Dr. Mehrshad Jafari clinic is tomorrow, {day} at {time}. Code: {code}.',
    ], true);
}

function cms_tpl(string $audience, string $event, string $locale): string
{
    $locale = $audience === 'doctor' ? 'fa' : cms_locale($locale);
    $specific = trim(cms_setting('tpl_' . $audience . '_' . $event . '_' . $locale));
    if ($specific !== '' && !cms_tpl_stale($specific)) {
        return $specific;
    }
    $shared = trim(cms_setting('tpl_' . $audience . '_' . $event));
    if ($shared !== '' && !cms_tpl_stale($shared)) {
        return $shared;
    }
    return cms_tpl_default($audience, $event, $locale);
}

function cms_tpl_place(array $visit, string $locale): string
{
    $key = (string) ($visit['place_key'] ?? '');
    if ($key === '') {
        return '';
    }
    $place = cms_place_row($key);
    return $place ? cms_place_label($place, $locale) : '';
}

function cms_tpl_fill(string $template, array $visit, string $locale): string
{
    $locale = cms_locale($locale);
    $dayIso = (string) ($visit['day'] ?? '');
    $day = cms_day_label($dayIso, $locale);
    $time = (string) ($visit['time_slot'] ?? '');
    if ($locale === 'fa') {
        $time = cms_fa_digits($time);
    }
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    $map = [
        '{name}' => (string) ($visit['name'] ?? ''),
        '{code}' => (string) ($visit['code'] ?? ''),
        '{day}' => $day,
        '{when}' => cms_visit_when($dayIso, $today, $locale),
        '{time}' => $time,
        '{service}' => cms_topic_label((string) ($visit['service'] ?? ''), $locale),
        '{kind}' => cms_kind_label((string) ($visit['kind'] ?? ''), $locale),
        '{phone}' => (string) ($visit['phone'] ?? ''),
        '{note}' => (string) ($visit['note'] ?? ''),
        '{place}' => cms_tpl_place($visit, $locale),
    ];
    return cms_clip(strtr($template, $map), 600);
}

function cms_sms_stopped(string $phone): bool
{
    $phone = cms_mobile($phone);
    if ($phone === '') {
        return false;
    }
    $stmt = cms_db()->prepare('SELECT 1 FROM sms_stops WHERE phone = ?');
    $stmt->execute([$phone]);
    return (bool) $stmt->fetchColumn();
}

function cms_sms_stop(string $phone, bool $stop): bool
{
    $phone = cms_mobile($phone);
    if ($phone === '') {
        return false;
    }
    if ($stop) {
        cms_db()->prepare('INSERT INTO sms_stops (phone, updated_at) VALUES (?,?) ON CONFLICT(phone) DO UPDATE SET updated_at = excluded.updated_at')->execute([$phone, cms_now()]);
    } else {
        cms_db()->prepare('DELETE FROM sms_stops WHERE phone = ?')->execute([$phone]);
    }
    return true;
}

function cms_notify_on(string $audience, string $event): bool
{
    $default = ($audience === 'doctor' && $event === 'reminder') ? '0' : '1';
    return cms_setting('notify_' . $audience . '_' . $event, $default) !== '0';
}

function cms_message_log(int $visitId, string $channel, string $audience, string $event, string $target, string $body, string $status, string $ref, string $error): void
{
    cms_db()->prepare('INSERT INTO messages_out (visit_id, channel, audience, event, target, body, status, provider_ref, error, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([
        $visitId,
        $channel,
        $audience,
        $event,
        cms_clip($target, 40),
        cms_clip($body, 600),
        $status,
        cms_clip($ref, 40),
        cms_plain($error, 180),
        cms_now(),
    ]);
}

function cms_sms_explain(string $value): string
{
    return [
        '0' => 'نام کاربری یا رمز ملی‌پیامک نادرست است',
        '2' => 'اعتبار پنل پیامک کافی نیست',
        '3' => 'محدودیت ارسال روزانه پر شده است',
        '4' => 'محدودیت حجم ارسال پر شده است',
        '5' => 'شماره خط فرستنده معتبر نیست',
        '6' => 'سامانه ملی‌پیامک در حال به‌روزرسانی است',
        '7' => 'متن پیام فیلتر شده است',
        '9' => 'ارسال از این خط با وب‌سرویس ممکن نیست',
        '10' => 'کاربر پیامک فعال نیست',
        '11' => 'پیام ارسال نشد',
        '12' => 'مدارک پنل کامل نیست',
        '35' => 'شماره گیرنده در فهرست سیاه است',
    ][$value] ?? ('ملی‌پیامک پاسخ داد: ' . $value);
}

function cms_sms_read(array $http): array
{
    if (!$http['ok'] && $http['body'] === '') {
        return ['ok' => false, 'ref' => '', 'error' => 'ارتباط با ملی‌پیامک برقرار نشد'];
    }
    $json = json_decode($http['body'], true);
    if (!is_array($json)) {
        $plain = trim($http['body']);
        if (preg_match('/^\d{8,}$/', $plain)) {
            return ['ok' => true, 'ref' => $plain, 'error' => ''];
        }
        return ['ok' => false, 'ref' => '', 'error' => 'پاسخ ملی‌پیامک خوانده نشد'];
    }
    $status = (int) ($json['RetStatus'] ?? $json['retStatus'] ?? 0);
    $value = trim((string) ($json['Value'] ?? $json['value'] ?? $json['recId'] ?? ''));
    if (($status === 1 || isset($json['recId'])) && preg_match('/^\d{4,}$/', $value)) {
        return ['ok' => true, 'ref' => $value, 'error' => ''];
    }
    $text = trim((string) ($json['StrRetStatus'] ?? $json['status'] ?? ''));
    return ['ok' => false, 'ref' => '', 'error' => $value !== '' ? cms_sms_explain($value) : ($text !== '' ? $text : 'پیامک فرستاده نشد')];
}

function cms_sms_send(string $to, string $text): array
{
    $from = trim(cms_setting('sms_from'));
    if (!preg_match('/^[0-9]{4,20}$/', $from)) {
        return ['ok' => false, 'ref' => '', 'error' => 'شماره خط فرستنده ذخیره نشده است'];
    }
    if (cms_setting('sms_mode', 'rest') === 'console') {
        $key = trim(cms_setting('sms_key'));
        if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $key)) {
            return ['ok' => false, 'ref' => '', 'error' => 'کلید کنسول ملی‌پیامک ذخیره نشده است'];
        }
        $http = cms_http(
            'https://console.melipayamak.com/api/send/simple/' . rawurlencode($key),
            json_encode(['from' => $from, 'to' => $to, 'text' => $text], JSON_UNESCAPED_UNICODE),
            ['Content-Type: application/json', 'Accept: application/json'],
            12
        );
        return cms_sms_read($http);
    }
    $user = trim(cms_setting('sms_user'));
    $pass = cms_setting('sms_pass');
    if ($user === '' || $pass === '') {
        return ['ok' => false, 'ref' => '', 'error' => 'نام کاربری یا رمز ملی‌پیامک ذخیره نشده است'];
    }
    $body = http_build_query([
        'username' => $user,
        'password' => $pass,
        'to' => $to,
        'from' => $from,
        'text' => $text,
        'isFlash' => 'false',
    ]);
    $http = cms_http('https://rest.payamak-panel.com/api/SendSMS/SendSMS', $body, ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], 12);
    return cms_sms_read($http);
}

function cms_chat_send(string $channel, string $text): array
{
    $routes = [
        'bale' => 'https://tapi.bale.ai/bot%s/sendMessage',
        'telegram' => 'https://api.telegram.org/bot%s/sendMessage',
        'eitaa' => 'https://eitaayar.ir/api/bot%s/sendMessage',
    ];
    if (!isset($routes[$channel])) {
        return ['ok' => false, 'ref' => '', 'error' => 'پیام‌رسان شناخته نشد'];
    }
    $token = trim(cms_setting($channel . '_token'));
    $chat = trim(cms_setting($channel . '_chat'));
    if (!preg_match('/^[A-Za-z0-9:_-]{20,160}$/', $token) || !preg_match('/^[A-Za-z0-9_@:-]{1,40}$/', $chat)) {
        return ['ok' => false, 'ref' => '', 'error' => 'توکن یا شناسه گفتگو کامل نیست'];
    }
    $http = cms_http(
        sprintf($routes[$channel], $token),
        json_encode(['chat_id' => $chat, 'text' => $text], JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json', 'Accept: application/json'],
        12
    );
    if (!$http['ok'] && $http['body'] === '') {
        return ['ok' => false, 'ref' => '', 'error' => 'ارتباط با پیام‌رسان برقرار نشد'];
    }
    $json = json_decode($http['body'], true);
    if (is_array($json) && (!empty($json['ok']) || isset($json['result']))) {
        $ref = '';
        if (is_array($json['result'] ?? null) && isset($json['result']['message_id'])) {
            $ref = (string) $json['result']['message_id'];
        }
        return ['ok' => true, 'ref' => $ref, 'error' => ''];
    }
    $desc = is_array($json) ? trim((string) ($json['description'] ?? '')) : '';
    return ['ok' => false, 'ref' => '', 'error' => $desc !== '' ? $desc : 'پیام‌رسان پیام را نپذیرفت'];
}

function cms_notify_visit(array $visit, string $event): void
{
    if (!isset(cms_notify_events()[$event])) {
        return;
    }
    $id = (int) ($visit['id'] ?? 0);
    if (cms_setting('sms_on') === '1' && cms_notify_on('patient', $event)) {
        $text = cms_tpl_fill(cms_tpl('patient', $event, (string) ($visit['locale'] ?? 'fa')), $visit, (string) ($visit['locale'] ?? 'fa'));
        $mobile = cms_mobile((string) ($visit['phone'] ?? ''));
        if ($mobile === '') {
            cms_message_log($id, 'sms', 'patient', $event, (string) ($visit['phone'] ?? ''), $text, 'skipped', '', 'شماره بیمار موبایل نیست');
        } elseif (cms_sms_stopped($mobile)) {
            cms_message_log($id, 'sms', 'patient', $event, $mobile, $text, 'skipped', '', 'این شماره از پیامک بیمار خارج است');
        } else {
            $sent = cms_sms_send($mobile, $text);
            cms_message_log($id, 'sms', 'patient', $event, $mobile, $text, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
        }
    }
    if (!cms_notify_on('doctor', $event)) {
        return;
    }
    $doctorText = cms_tpl_fill(cms_tpl('doctor', $event, 'fa'), $visit, 'fa');
    if (cms_setting('sms_on') === '1') {
        $doctorMobile = cms_mobile(cms_setting('sms_doctor'));
        if ($doctorMobile === '') {
            cms_message_log($id, 'sms', 'doctor', $event, '', $doctorText, 'skipped', '', 'موبایل پزشک در پیام‌رسانی ذخیره نشده است');
        } else {
            $sent = cms_sms_send($doctorMobile, $doctorText);
            cms_message_log($id, 'sms', 'doctor', $event, $doctorMobile, $doctorText, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
        }
    }
    foreach (['bale' => 'بله', 'telegram' => 'تلگرام', 'eitaa' => 'ایتا'] as $channel => $label) {
        if (cms_setting($channel . '_on') !== '1') {
            continue;
        }
        $sent = cms_chat_send($channel, $doctorText);
        cms_message_log($id, $channel, 'doctor', $event, $label, $doctorText, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
    }
}

function cms_chat_names(): array
{
    return ['bale' => 'بله', 'telegram' => 'تلگرام', 'eitaa' => 'ایتا'];
}

function cms_chat_read(array $payload): array
{
    $rows = $payload['result'] ?? [];
    if (!is_array($rows)) {
        return [];
    }
    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $message = is_array($row['message'] ?? null) ? $row['message'] : [];
        $reply = $message['reply_to_message']['message_id'] ?? null;
        $text = cms_plain((string) ($message['text'] ?? ''), 2000);
        $update = (int) ($row['update_id'] ?? 0);
        $chat = $message['chat']['id'] ?? '';
        if ($update < 1 || cms_chars($text) < 2 || !is_scalar($reply) || !is_scalar($chat)) {
            continue;
        }
        $items[] = ['update' => $update, 'reply' => (string) $reply, 'text' => $text, 'chat' => (string) $chat, 'offset' => $update];
    }
    return $items;
}

function cms_chat_keep(string $channel, array $items): array
{
    $names = cms_chat_names();
    if (!isset($names[$channel])) {
        return ['kept' => 0, 'offset' => 0];
    }
    $expect = trim(cms_setting($channel . '_chat'));
    $kept = 0;
    $offset = 0;
    $seen = cms_db()->prepare('INSERT INTO care_inbox (channel, update_id, created_at) VALUES (?,?,?)');
    $find = cms_db()->prepare("SELECT visit_id FROM messages_out WHERE channel = ? AND provider_ref = ? AND status = 'sent' AND visit_id > 0 ORDER BY id DESC LIMIT 1");
    $phoneOf = cms_db()->prepare('SELECT phone FROM visits WHERE id = ?');
    $write = cms_db()->prepare('INSERT INTO care_posts (phone, author, body, hidden, seen_at, staff, via, created_at) VALUES (?,?,?,0,?,?,?,?)');
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $update = (int) ($item['update'] ?? 0);
        $offset = max($offset, $update);
        if ($update < 1 || $expect === '' || (string) ($item['chat'] ?? '') !== $expect) {
            continue;
        }
        try {
            $seen->execute([$channel, $update, cms_now()]);
        } catch (PDOException) {
            continue;
        }
        $find->execute([$channel, (string) ($item['reply'] ?? '')]);
        $visitId = (int) $find->fetchColumn();
        if ($visitId < 1) {
            continue;
        }
        $phoneOf->execute([$visitId]);
        $phone = cms_care_phone((string) $phoneOf->fetchColumn());
        $text = cms_plain((string) ($item['text'] ?? ''), 2000);
        if ($phone === '' || cms_chars($text) < 2) {
            continue;
        }
        $write->execute([$phone, 'clinic', $text, '', $names[$channel], $channel, cms_now()]);
        $kept++;
    }
    return ['kept' => $kept, 'offset' => $offset];
}

function cms_chat_pull(string $channel): array
{
    $routes = [
        'bale' => 'https://tapi.bale.ai/bot%s/getUpdates',
        'telegram' => 'https://api.telegram.org/bot%s/getUpdates',
        'eitaa' => 'https://eitaayar.ir/api/bot%s/getUpdates',
    ];
    if (!isset($routes[$channel]) || cms_setting($channel . '_on') !== '1') {
        return ['ok' => false, 'error' => 'اول همان پیام‌رسان را روشن و ذخیره کنید.', 'kept' => 0];
    }
    $token = trim(cms_setting($channel . '_token'));
    if (!preg_match('/^[A-Za-z0-9:_-]{20,160}$/', $token)) {
        return ['ok' => false, 'error' => 'توکن ربات کامل نیست.', 'kept' => 0];
    }
    $offset = (int) cms_setting('chat_offset_' . $channel, '0');
    $url = sprintf($routes[$channel], $token);
    if ($offset > 0) {
        $url .= '?offset=' . ($offset + 1);
    }
    $http = cms_http($url, null, ['Accept: application/json'], 12);
    $json = json_decode($http['body'], true);
    if (!$http['ok'] || !is_array($json) || (isset($json['ok']) && $json['ok'] === false)) {
        return ['ok' => false, 'error' => 'پاسخ‌ها گرفته نشد.', 'kept' => 0];
    }
    $result = cms_chat_keep($channel, cms_chat_read($json));
    $cursor = 0;
    foreach (is_array($json['result'] ?? null) ? $json['result'] : [] as $row) {
        $cursor = max($cursor, is_array($row) ? (int) ($row['update_id'] ?? 0) : 0);
    }
    if ($cursor > 0) {
        cms_set_setting('chat_offset_' . $channel, (string) $cursor);
    }
    return ['ok' => true, 'error' => '', 'kept' => $result['kept']];
}

function cms_notify_test(string $channel): array
{
    $recent = cms_db()->prepare("SELECT COUNT(*) FROM messages_out WHERE event = 'test' AND created_at > ?");
    $recent->execute([gmdate('c', time() - 3600)]);
    if ((int) $recent->fetchColumn() >= 8) {
        return ['ok' => false, 'error' => 'در یک ساعت اخیر چند پیام آزمایشی رفته است. کمی بعد دوباره امتحان کنید.'];
    }
    $text = trim(cms_setting('tpl_test'));
    if ($text === '') {
        $text = 'پیام آزمایشی میز مطب دکتر مهرشاد جعفری. اگر این متن را دیدید، این مسیر وصل است.';
    }
    $text = cms_clip($text, 600);
    if ($channel === 'sms') {
        if (cms_setting('sms_on') !== '1') {
            return ['ok' => false, 'error' => 'اول کلید پیامک را روشن و ذخیره کنید.'];
        }
        $mobile = cms_mobile(cms_setting('sms_doctor'));
        if ($mobile === '') {
            return ['ok' => false, 'error' => 'موبایل پزشک را ذخیره کنید تا پیام آزمایشی به همان شماره برود.'];
        }
        $sent = cms_sms_send($mobile, $text);
        cms_message_log(0, 'sms', 'doctor', 'test', $mobile, $text, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
        return $sent['ok'] ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => $sent['error']];
    }
    if (!in_array($channel, ['bale', 'telegram', 'eitaa'], true) || cms_setting($channel . '_on') !== '1') {
        return ['ok' => false, 'error' => 'اول همان پیام‌رسان را روشن و ذخیره کنید.'];
    }
    $sent = cms_chat_send($channel, $text);
    cms_message_log(0, $channel, 'doctor', 'test', $channel, $text, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
    return $sent['ok'] ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => $sent['error']];
}

function cms_remind_due(): array
{
    if (cms_quiet_now()) {
        return ['sent' => 0, 'late' => 0];
    }
    $zone = new DateTimeZone('Asia/Tehran');
    $now = new DateTimeImmutable('now', $zone);
    $today = $now->format('Y-m-d');
    [$earliest, $latest] = cms_remind_bounds($today, cms_remind_days(), cms_remind_grace());
    $stmt = cms_db()->prepare("SELECT * FROM visits WHERE status = 'confirmed' AND reminded_at = '' AND day >= ? AND day <= ? ORDER BY day, time_slot LIMIT 80");
    $stmt->execute([$earliest, $latest]);
    $count = 0;
    $late = 0;
    $mark = cms_db()->prepare('UPDATE visits SET reminded_at = ? WHERE id = ?');
    foreach ($stmt as $visit) {
        cms_notify_visit($visit, 'reminder');
        $mark->execute([cms_now(), (int) $visit['id']]);
        $count++;
        if ((string) $visit['day'] < $latest) {
            $late++;
        }
    }
    return ['sent' => $count, 'late' => $late];
}
