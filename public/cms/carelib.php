<?php
declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'carelib.php') {
    http_response_code(404);
    exit;
}

function cms_care_open(): bool
{
    return cms_setting('care_public', '1') !== '0';
}

function cms_care_upload_kb(): int
{
    $kb = (int) cms_setting('care_upload_kb', '4096');
    return max(256, min(8192, $kb > 0 ? $kb : 4096));
}

function cms_care_text(string $key, string $fallback): string
{
    $value = trim(cms_setting($key));
    return $value !== '' ? $value : $fallback;
}

function cms_care_local(string $key, string $fallback, string $locale = 'fa'): string
{
    $locale = cms_locale($locale);
    if ($locale !== 'fa') {
        $specific = trim(cms_setting($key . '_' . $locale));
        if ($specific !== '') {
            return $specific;
        }
        $builtin = cms_care_builtin($key, $locale);
        if ($builtin !== '') {
            return $builtin;
        }
    }
    return cms_care_text($key, $fallback);
}

function cms_care_title(string $locale = 'fa'): string
{
    return cms_care_local('care_title', 'پرونده درمان', $locale);
}

function cms_care_notice(string $locale = 'fa'): string
{
    return cms_care_local('care_notice', 'این صفحه معاینه نیست و برنامه درمان نمی‌سازد. عکس و توضیح را مطب در میز می‌بیند و خودش پاسخ می‌دهد. در فوریت با شماره اورژانس همین صفحه تماس بگیرید.', $locale);
}

function cms_care_photo_max(): int
{
    $count = (int) cms_setting('care_photos', '3');
    return max(1, min(4, $count > 0 ? $count : 3));
}

function cms_care_emergency(): string
{
    $raw = preg_replace('/\D+/', '', cms_setting('care_emergency', '115')) ?? '';
    $tail = substr($raw, -8);
    if (!preg_match('/^\d{3,15}$/', $raw) || in_array($tail, ['22579316', '22579315'], true)) {
        return '115';
    }
    return $raw;
}

function cms_care_fields(): array
{
    return [
        'problem' => 'مشکل فعلی',
        'medicines' => 'داروهای فعلی',
        'allergy' => 'حساسیت',
        'extra' => 'توضیح دیگر',
    ];
}

function cms_care_field_on(string $key): bool
{
    return isset(cms_care_fields()[$key]) && cms_setting('care_show_' . $key, '1') !== '0';
}

function cms_care_field_label(string $key, string $locale = 'fa'): string
{
    $fallback = cms_care_fields()[$key] ?? '';
    return $fallback === '' ? '' : cms_care_local('care_label_' . $key, $fallback, $locale);
}

function cms_care_consent(string $locale = 'fa'): string
{
    return cms_care_local('care_consent', 'می‌دانم مطب این متن و عکس را می‌بیند و روی سایت عمومی منتشر نمی‌شود.', $locale);
}

function cms_care_phone(string $raw): string
{
    $phone = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($phone, '98') && strlen($phone) >= 12) {
        $phone = '0' . substr($phone, 2);
    }
    return preg_match('/^0\d{9,10}$/', $phone) ? $phone : '';
}

function cms_care_dir(): string
{
    $dir = dirname(cms_db_path()) . DIRECTORY_SEPARATOR . 'care';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $guard = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "Require all denied\nDeny from all\n");
    }
    return $dir;
}

function cms_care_locale(): string
{
    cms_care_session();
    $value = $_SESSION['care_locale'] ?? 'fa';
    return cms_locale(is_string($value) ? $value : 'fa');
}

function cms_care_locale_set(string $locale): void
{
    cms_care_session();
    $_SESSION['care_locale'] = cms_locale($locale);
}

function cms_care_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'CARESESS') {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name('CARESESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => cms_https(),
    ]);
    session_start();
}

function cms_care_csrf(): string
{
    cms_care_session();
    if (empty($_SESSION['care_csrf']) || !is_string($_SESSION['care_csrf'])) {
        $_SESSION['care_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['care_csrf'];
}

function cms_care_csrf_ok(): bool
{
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && hash_equals(cms_care_csrf(), $sent);
}

function cms_care_span(string $key, int $default, int $max): int
{
    $raw = cms_setting($key, (string) $default);
    if ($raw === '') {
        return $default;
    }
    return max(0, min($max, (int) $raw));
}

function cms_care_access(string $phone): ?array
{
    $stmt = cms_db()->prepare('SELECT phone, code_hash, stamp, expires_at, updated_at FROM care_access WHERE phone = ?');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function cms_care_code_live(array $row): bool
{
    $until = (string) ($row['expires_at'] ?? '');
    if ($until === '') {
        return true;
    }
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    return $until >= $today;
}

function cms_care_issue_code(string $phone): array
{
    $code = cms_visit_code();
    $stamp = bin2hex(random_bytes(8));
    $days = cms_care_span('care_code_days', 180, 365);
    $expires = '';
    if ($days > 0) {
        $expires = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->modify('+' . $days . ' days')->format('Y-m-d');
    }
    cms_db()->prepare('INSERT INTO care_access (phone, code_hash, stamp, expires_at, updated_at) VALUES (?,?,?,?,?) ON CONFLICT(phone) DO UPDATE SET code_hash = excluded.code_hash, stamp = excluded.stamp, expires_at = excluded.expires_at, updated_at = excluded.updated_at')->execute([
        $phone,
        hash('sha256', $code),
        $stamp,
        $expires,
        cms_now(),
    ]);
    return ['code' => $code, 'stamp' => $stamp, 'expires_at' => $expires];
}

function cms_care_open_session(string $phone, string $stamp, string $showCode, string $role = 'patient'): void
{
    cms_care_session();
    $keptLocale = $_SESSION['care_locale'] ?? 'fa';
    session_regenerate_id(true);
    $_SESSION['care_locale'] = cms_locale(is_string($keptLocale) ? $keptLocale : 'fa');
    $_SESSION['care_phone'] = $phone;
    $_SESSION['care_stamp'] = $stamp;
    $_SESSION['care_role'] = $role === 'companion' ? 'companion' : 'patient';
    $_SESSION['care_csrf'] = bin2hex(random_bytes(16));
    unset($_SESSION['care_show_code'], $_SESSION['care_show_companion'], $_SESSION['care_show_companion_name']);
    if ($showCode !== '' && $role !== 'companion') {
        $_SESSION['care_show_code'] = $showCode;
    }
}

function cms_care_take_fresh_code(): string
{
    cms_care_session();
    $code = $_SESSION['care_show_code'] ?? '';
    unset($_SESSION['care_show_code']);
    return is_string($code) && preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $code) ? $code : '';
}

function cms_care_logged_phone(): string
{
    cms_care_session();
    $phone = $_SESSION['care_phone'] ?? '';
    $phone = is_string($phone) ? cms_care_phone($phone) : '';
    $stamp = $_SESSION['care_stamp'] ?? '';
    if ($phone === '' || !is_string($stamp)) {
        return '';
    }
    $companion = (($_SESSION['care_role'] ?? '') === 'companion');
    if ($companion && !cms_care_on('care_companion')) {
        $row = null;
    } else {
        $row = $companion ? cms_care_companion_stamp($phone, $stamp) : cms_care_access($phone);
    }
    if (!$row || !hash_equals((string) $row['stamp'], $stamp) || !cms_care_code_live($row)) {
        unset($_SESSION['care_phone'], $_SESSION['care_stamp'], $_SESSION['care_role'], $_SESSION['care_show_code'], $_SESSION['care_show_companion'], $_SESSION['care_show_companion_name']);
        return '';
    }
    return $phone;
}

function cms_care_role(): string
{
    if (cms_care_logged_phone() === '') {
        return '';
    }
    return (($_SESSION['care_role'] ?? '') === 'companion') ? 'companion' : 'patient';
}

function cms_care_companions(string $phone): array
{
    $stmt = cms_db()->prepare('SELECT id, phone, code_hash, stamp, expires_at, name, updated_at FROM care_companions WHERE phone = ? ORDER BY id');
    $stmt->execute([$phone]);
    return $stmt->fetchAll();
}

function cms_care_companion(string $phone): ?array
{
    $rows = cms_care_companions($phone);
    if (!$rows) {
        return null;
    }
    return $rows[count($rows) - 1];
}

function cms_care_companion_stamp(string $phone, string $stamp): ?array
{
    if ($phone === '' || $stamp === '') {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT id, phone, code_hash, stamp, expires_at, name, updated_at FROM care_companions WHERE phone = ? AND stamp = ?');
    $stmt->execute([$phone, $stamp]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function cms_care_issue_companion(string $phone, string $nameRaw): array
{
    $empty = ['ok' => false, 'error' => 'نام همراه را بنویسید.', 'code' => '', 'stamp' => '', 'expires_at' => '', 'name' => ''];
    $name = cms_plain($nameRaw, 40);
    $chars = function_exists('mb_strlen') ? mb_strlen($name) : (int) preg_match_all('/./u', $name);
    if ($phone === '' || $chars < 2) {
        return $empty;
    }
    $code = cms_visit_code();
    $stamp = bin2hex(random_bytes(8));
    $days = cms_care_span('care_code_days', 180, 365);
    $expires = '';
    if ($days > 0) {
        $expires = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->modify('+' . $days . ' days')->format('Y-m-d');
    }
    $now = cms_now();
    $hash = hash('sha256', $code);
    $found = cms_db()->prepare('SELECT id FROM care_companions WHERE phone = ? AND name = ?');
    $found->execute([$phone, $name]);
    $same = $found->fetchColumn();
    if ($same) {
        cms_db()->prepare('UPDATE care_companions SET code_hash = ?, stamp = ?, expires_at = ?, updated_at = ? WHERE id = ? AND phone = ?')->execute([$hash, $stamp, $expires, $now, (int) $same, $phone]);
    } else {
        $count = cms_db()->prepare('SELECT COUNT(*) FROM care_companions WHERE phone = ?');
        $count->execute([$phone]);
        if ((int) $count->fetchColumn() >= 4) {
            return ['ok' => false, 'error' => 'برای این شماره چهار همراه ثبت شده. یکی را ببندید.', 'code' => '', 'stamp' => '', 'expires_at' => '', 'name' => ''];
        }
        cms_db()->prepare('INSERT INTO care_companions (phone, code_hash, stamp, expires_at, name, updated_at) VALUES (?,?,?,?,?,?)')->execute([$phone, $hash, $stamp, $expires, $name, $now]);
    }
    return ['ok' => true, 'error' => '', 'code' => $code, 'stamp' => $stamp, 'expires_at' => $expires, 'name' => $name];
}

function cms_care_revoke_companion(string $phone, int $id = 0): void
{
    if ($id > 0) {
        cms_db()->prepare('DELETE FROM care_companions WHERE phone = ? AND id = ?')->execute([$phone, $id]);
        return;
    }
    cms_db()->prepare('DELETE FROM care_companions WHERE phone = ?')->execute([$phone]);
}

function cms_care_hold_companion(string $code, string $name = ''): void
{
    cms_care_session();
    $_SESSION['care_show_companion'] = $code;
    $_SESSION['care_show_companion_name'] = $name;
}

function cms_care_take_companion_code(): string
{
    cms_care_session();
    $code = $_SESSION['care_show_companion'] ?? '';
    unset($_SESSION['care_show_companion'], $_SESSION['care_show_companion_name']);
    return is_string($code) && preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $code) ? $code : '';
}

function cms_care_companion_flash_name(): string
{
    cms_care_session();
    $name = $_SESSION['care_show_companion_name'] ?? '';
    return is_string($name) ? cms_plain($name, 40) : '';
}

function cms_care_login(string $phone, string $code): bool
{
    $phone = cms_care_phone($phone);
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    if ($phone === '' || strlen($code) !== 8) {
        return false;
    }
    $row = cms_care_access($phone);
    if ($row && cms_care_code_live($row) && hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
        cms_care_open_session($phone, (string) $row['stamp'], '');
        return true;
    }
    if (cms_care_on('care_companion')) {
        foreach (cms_care_companions($phone) as $mate) {
            if (cms_care_code_live($mate) && hash_equals((string) $mate['code_hash'], hash('sha256', $code))) {
                cms_care_open_session($phone, (string) $mate['stamp'], '', 'companion');
                return true;
            }
        }
    }
    return false;
}

function cms_care_logout(): void
{
    cms_care_session();
    unset($_SESSION['care_phone'], $_SESSION['care_stamp'], $_SESSION['care_role'], $_SESSION['care_show_code'], $_SESSION['care_show_companion'], $_SESSION['care_show_companion_name']);
    session_regenerate_id(true);
}

function cms_care_limited(string $tag, int $max): bool
{
    $recent = cms_db()->prepare('SELECT COUNT(*) FROM consult_log WHERE ip_hash = ? AND locale = ? AND created_at > ?');
    $recent->execute([cms_ip_hash(), $tag, gmdate('c', time() - 3600)]);
    return (int) $recent->fetchColumn() >= $max;
}

function cms_care_tick(string $tag): void
{
    cms_db()->prepare('INSERT INTO consult_log (ip_hash, locale, chars, created_at) VALUES (?,?,?,?)')->execute([cms_ip_hash(), $tag, 0, cms_now()]);
}

function cms_care_visits(string $phone): array
{
    $stmt = cms_db()->prepare('SELECT * FROM visits WHERE phone = ? ORDER BY day DESC, time_slot DESC LIMIT 40');
    $stmt->execute([$phone]);
    return $stmt->fetchAll();
}

function cms_care_next(string $phone): ?array
{
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    $stmt = cms_db()->prepare("SELECT * FROM visits WHERE phone = ? AND day >= ? AND status IN ('new','confirmed','arrived') ORDER BY day, time_slot LIMIT 1");
    $stmt->execute([$phone, $today]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cms_care_thread(string $phone, bool $includeHidden): array
{
    $sql = 'SELECT * FROM care_posts WHERE phone = ?';
    if (!$includeHidden) {
        $sql .= ' AND hidden = 0';
    }
    $sql .= ' ORDER BY id ASC LIMIT 80';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute([$phone]);
    $posts = [];
    $ids = [];
    foreach ($stmt as $row) {
        $row['files'] = [];
        $posts[(int) $row['id']] = $row;
        $ids[] = (int) $row['id'];
    }
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $files = cms_db()->prepare("SELECT * FROM care_files WHERE post_id IN ($marks) ORDER BY id");
    $files->execute($ids);
    foreach ($files as $file) {
        $postId = (int) $file['post_id'];
        if (isset($posts[$postId])) {
            $posts[$postId]['files'][] = $file;
        }
    }
    return array_values($posts);
}

function cms_care_mark_seen(string $phone): void
{
    cms_db()->prepare("UPDATE care_posts SET seen_at = ? WHERE phone = ? AND author = 'patient' AND seen_at = ''")->execute([cms_now(), $phone]);
}

function cms_care_mark_read(string $phone): void
{
    cms_db()->prepare("UPDATE care_posts SET seen_at = ? WHERE phone = ? AND author = 'clinic' AND hidden = 0 AND seen_at = ''")->execute([cms_now(), $phone]);
}

function cms_care_consent_keep(string $phone, string $kind): void
{
    if ($phone === '' || !in_array($kind, ['sheet', 'message'], true)) {
        return;
    }
    cms_db()->prepare('INSERT INTO care_consents (phone, kind, sentence_sha, created_at) VALUES (?,?,?,?)')->execute([
        $phone,
        $kind,
        hash('sha256', cms_care_consent(cms_care_locale())),
        cms_now(),
    ]);
}

function cms_care_consent_last(string $phone, string $kind): ?array
{
    if (!in_array($kind, ['sheet', 'message'], true)) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT sentence_sha, created_at FROM care_consents WHERE phone = ? AND kind = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$phone, $kind]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function cms_care_file_take(string $phone, int $id): bool
{
    $stmt = cms_db()->prepare("SELECT f.stored FROM care_files f JOIN care_posts p ON p.id = f.post_id WHERE f.id = ? AND f.phone = ? AND p.author = 'patient'");
    $stmt->execute([$id, $phone]);
    $stored = $stmt->fetchColumn();
    if (!is_string($stored) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $stored)) {
        return false;
    }
    cms_db()->prepare('DELETE FROM care_files WHERE id = ? AND phone = ?')->execute([$id, $phone]);
    $path = cms_care_dir() . DIRECTORY_SEPARATOR . $stored;
    if (is_file($path)) {
        @unlink($path);
    }
    return true;
}

function cms_care_comment_drop(string $phone, int $id): bool
{
    $stmt = cms_db()->prepare("DELETE FROM care_comments WHERE id = ? AND phone = ? AND author = 'patient'");
    $stmt->execute([$id, $phone]);
    return $stmt->rowCount() > 0;
}

function cms_care_allergy_text(string $phone): string
{
    return trim((string) (cms_care_facts($phone)['allergy'] ?? ''));
}

function cms_care_unseen_phones(): array
{
    $phones = [];
    foreach (cms_db()->query("SELECT phone FROM care_posts WHERE author = 'patient' AND hidden = 0 AND seen_at = '' GROUP BY phone") as $row) {
        $phones[(string) $row['phone']] = true;
    }
    return $phones;
}

function cms_care_toggle_hidden(int $id, string $phone): bool
{
    $stmt = cms_db()->prepare('SELECT hidden FROM care_posts WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $hidden = $stmt->fetchColumn();
    if ($hidden === false) {
        return false;
    }
    cms_db()->prepare('UPDATE care_posts SET hidden = ? WHERE id = ? AND phone = ?')->execute([(int) $hidden === 1 ? 0 : 1, $id, $phone]);
    return true;
}

function cms_jpeg_strip(string $raw): string
{
    if (strlen($raw) < 4 || !str_starts_with($raw, "\xFF\xD8")) {
        return '';
    }
    $out = "\xFF\xD8";
    $i = 2;
    $len = strlen($raw);
    while ($i + 1 < $len) {
        if ($raw[$i] !== "\xFF") {
            return '';
        }
        while ($i < $len && $raw[$i] === "\xFF") {
            $i++;
        }
        if ($i >= $len) {
            return '';
        }
        $marker = ord($raw[$i]);
        $i++;
        if ($marker === 0xD9) {
            return $out . "\xFF\xD9";
        }
        if ($marker === 0xDA) {
            return $out . "\xFF\xDA" . substr($raw, $i);
        }
        if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD8)) {
            $out .= "\xFF" . chr($marker);
            continue;
        }
        if ($i + 2 > $len) {
            return '';
        }
        $seglen = (ord($raw[$i]) << 8) + ord($raw[$i + 1]);
        if ($seglen < 2 || $i + $seglen > $len) {
            return '';
        }
        $payload = substr($raw, $i + 2, $seglen - 2);
        $drop = $marker === 0xE1 || $marker === 0xED || $marker === 0xFE;
        if ($marker === 0xE2 && !str_starts_with($payload, 'ICC_PROFILE')) {
            $drop = true;
        }
        if ($marker >= 0xE3 && $marker <= 0xEF && $marker !== 0xEE) {
            $drop = true;
        }
        if (!$drop) {
            $out .= "\xFF" . chr($marker) . substr($raw, $i, $seglen);
        }
        $i += $seglen;
    }
    return '';
}

function cms_png_strip(string $raw): string
{
    if (!str_starts_with($raw, "\x89PNG\r\n\x1a\n")) {
        return '';
    }
    $keep = ['IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'sRGB', 'cHRM', 'bKGD', 'pHYs', 'iCCP', 'sBIT'];
    $out = substr($raw, 0, 8);
    $i = 8;
    $len = strlen($raw);
    while ($i + 12 <= $len) {
        $size = unpack('N', substr($raw, $i, 4));
        $bytes = is_array($size) ? (int) $size[1] : 0;
        $type = substr($raw, $i + 4, 4);
        if ($i + 12 + $bytes > $len) {
            return '';
        }
        if (in_array($type, $keep, true)) {
            $out .= substr($raw, $i, 12 + $bytes);
        }
        $i += 12 + $bytes;
        if ($type === 'IEND') {
            return $out;
        }
    }
    return '';
}

function cms_webp_strip(string $raw): string
{
    if (strlen($raw) < 12 || substr($raw, 0, 4) !== 'RIFF' || substr($raw, 8, 4) !== 'WEBP') {
        return '';
    }
    $i = 12;
    $len = strlen($raw);
    $chunks = '';
    while ($i + 8 <= $len) {
        $type = substr($raw, $i, 4);
        $sizeData = unpack('V', substr($raw, $i + 4, 4));
        $size = is_array($sizeData) ? (int) $sizeData[1] : 0;
        $pad = $size % 2;
        if ($size < 0 || $i + 8 + $size + $pad > $len) {
            return '';
        }
        $data = substr($raw, $i + 8, $size);
        if ($type !== 'EXIF' && $type !== 'XMP ') {
            if ($type === 'VP8X' && $data !== '') {
                $data[0] = chr(ord($data[0]) & ~0x0C);
            }
            $chunks .= $type . pack('V', strlen($data)) . $data . (strlen($data) % 2 === 1 ? "\x00" : '');
        }
        $i += 8 + $size + $pad;
    }
    if ($chunks === '') {
        return '';
    }
    return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
}

function cms_care_is_heic(string $path): bool
{
    $head = @file_get_contents($path, false, null, 0, 64);
    if (!is_string($head) || strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') {
        return false;
    }
    $brand = substr($head, 8, 4);
    if (in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis'], true)) {
        return true;
    }
    return str_contains(substr($head, 8), 'heic');
}

function cms_care_gd_orient(string $jpeg, int $orientation): string
{
    $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg') || !function_exists('imagerotate') || $angle === 0) {
        return '';
    }
    $img = @imagecreatefromstring($jpeg);
    if (!$img) {
        return '';
    }
    $turned = imagerotate($img, $angle, 0);
    imagedestroy($img);
    if (!$turned) {
        return '';
    }
    ob_start();
    $ok = imagejpeg($turned, null, 90);
    $out = ob_get_clean();
    imagedestroy($turned);
    return $ok && is_string($out) ? $out : '';
}

function cms_care_heic_jpeg(string $path): string
{
    if (!class_exists('Imagick')) {
        return '';
    }
    try {
        $image = new Imagick();
        $image->readImage($path);
        if (method_exists($image, 'autoOrient')) {
            $image->autoOrient();
        }
        $image->stripImage();
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(90);
        $body = $image->getImageBlob();
        $image->clear();
        return is_string($body) ? $body : '';
    } catch (Throwable $ignored) {
        return '';
    }
}

function cms_care_clean_upload(string $tmp): array
{
    $fail = static function (string $error): array {
        return ['ok' => false, 'error' => $error, 'ext' => '', 'mime' => '', 'body' => ''];
    };
    if (cms_care_is_heic($tmp)) {
        $jpeg = cms_jpeg_strip(cms_care_heic_jpeg($tmp));
        if ($jpeg === '' || @getimagesizefromstring($jpeg) === false) {
            return $fail('عکس HEIC آیفون روی این سرور به JPG تبدیل نمی‌شود. JPG بفرستید، یا Imagick را در هاست روشن کنید.');
        }
        return ['ok' => true, 'error' => '', 'ext' => 'jpg', 'mime' => 'image/jpeg', 'body' => $jpeg];
    }
    $info = @getimagesize($tmp);
    $kind = is_array($info) ? (int) ($info[2] ?? 0) : 0;
    $map = [
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];
    if (!isset($map[$kind])) {
        return $fail('فقط عکس JPG یا PNG یا WebP، یا HEIC آیفون پذیرفته می‌شود.');
    }
    $raw = file_get_contents($tmp);
    if (!is_string($raw) || $raw === '') {
        return $fail('این عکس خوانده نشد.');
    }
    if ($kind === IMAGETYPE_JPEG) {
        $orientation = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        }
        $body = cms_jpeg_strip($raw);
        if ($body !== '' && $orientation !== 1) {
            $turned = cms_care_gd_orient($body, $orientation);
            if ($turned !== '') {
                $body = cms_jpeg_strip($turned);
            }
        }
    } elseif ($kind === IMAGETYPE_PNG) {
        $body = cms_png_strip($raw);
    } else {
        $body = cms_webp_strip($raw);
    }
    if ($body === '' || @getimagesizefromstring($body) === false) {
        return $fail('این عکس خوانده نشد.');
    }
    return ['ok' => true, 'error' => '', 'ext' => $map[$kind][0], 'mime' => $map[$kind][1], 'body' => $body];
}

function cms_care_store_upload(int $postId, string $phone, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'error' => ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'عکس نرسید. حجم را کمتر کنید یا دوباره بفرستید.'];
    }
    $bytes = (int) ($file['size'] ?? 0);
    if ($bytes < 1 || $bytes > cms_care_upload_kb() * 1024) {
        return ['ok' => false, 'error' => 'حجم عکس باید تا ' . cms_fa_digits((string) cms_care_upload_kb()) . ' کیلوبایت باشد.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'فقط عکس JPG یا PNG یا WebP، یا HEIC آیفون پذیرفته می‌شود.'];
    }
    $clean = cms_care_clean_upload($tmp);
    if (!$clean['ok']) {
        return ['ok' => false, 'error' => $clean['error']];
    }
    if (strlen($clean['body']) > cms_care_upload_kb() * 1024) {
        return ['ok' => false, 'error' => 'حجم عکس باید تا ' . cms_fa_digits((string) cms_care_upload_kb()) . ' کیلوبایت باشد.'];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $clean['ext'];
    $dest = cms_care_dir() . DIRECTORY_SEPARATOR . $stored;
    if (@file_put_contents($dest, $clean['body']) === false) {
        return ['ok' => false, 'error' => 'پوشه پرونده قابل نوشتن نیست.'];
    }
    cms_db()->prepare('INSERT INTO care_files (post_id, phone, stored, mime, bytes, created_at) VALUES (?,?,?,?,?,?)')->execute([
        $postId,
        $phone,
        $stored,
        $clean['mime'],
        strlen($clean['body']),
        cms_now(),
    ]);
    return ['ok' => true, 'error' => ''];
}

function cms_care_upload_list(?array $file): array
{
    if (!is_array($file) || !isset($file['error'])) {
        return [];
    }
    if (!is_array($file['error'])) {
        return (int) $file['error'] === UPLOAD_ERR_NO_FILE ? [] : [$file];
    }
    $list = [];
    foreach ($file['error'] as $index => $error) {
        if ((int) $error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = [
            'name' => $file['name'][$index] ?? '',
            'tmp_name' => $file['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $file['size'][$index] ?? 0,
        ];
    }
    return $list;
}

function cms_care_drop_post(int $postId): void
{
    $stmt = cms_db()->prepare('SELECT stored FROM care_files WHERE post_id = ?');
    $stmt->execute([$postId]);
    foreach ($stmt as $row) {
        $stored = (string) $row['stored'];
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $stored)) {
            $path = cms_care_dir() . DIRECTORY_SEPARATOR . $stored;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    cms_db()->prepare('DELETE FROM care_files WHERE post_id = ?')->execute([$postId]);
    cms_db()->prepare('DELETE FROM care_posts WHERE id = ?')->execute([$postId]);
}

function cms_care_add(string $phone, string $author, string $body, ?array $file, string $staff): array
{
    if (!in_array($author, ['patient', 'clinic'], true) || cms_care_phone($phone) === '') {
        return ['ok' => false, 'error' => 'این پرونده پیدا نشد.'];
    }
    $body = cms_plain($body, 2000);
    $files = cms_care_upload_list($file);
    if (count($files) > cms_care_photo_max()) {
        return ['ok' => false, 'error' => 'در هر پیام تا ' . cms_fa_digits((string) cms_care_photo_max()) . ' عکس می‌توان فرستاد.'];
    }
    if (cms_chars($body) < 2 && !$files) {
        return ['ok' => false, 'error' => 'متن یا یک عکس لازم است.'];
    }
    $recent = cms_db()->prepare('SELECT COUNT(*) FROM care_posts WHERE phone = ? AND author = ? AND created_at > ?');
    $recent->execute([$phone, $author, gmdate('c', time() - 3600)]);
    if ((int) $recent->fetchColumn() >= ($author === 'patient' ? 12 : 40)) {
        return ['ok' => false, 'error' => 'در این ساعت چند پیام فرستاده شده. کمی بعد دوباره بنویسید.'];
    }
    cms_db()->prepare('INSERT INTO care_posts (phone, author, body, hidden, seen_at, staff, created_at) VALUES (?,?,?,0,?,?,?)')->execute([
        $phone,
        $author,
        $body,
        $author === 'clinic' ? cms_now() : '',
        $author === 'clinic' ? cms_clip($staff, 80) : '',
        cms_now(),
    ]);
    $postId = (int) cms_db()->lastInsertId();
    foreach ($files as $one) {
        $stored = cms_care_store_upload($postId, $phone, $one);
        if (!$stored['ok']) {
            cms_care_drop_post($postId);
            return $stored;
        }
    }
    if ($author === 'patient') {
        cms_care_alert($phone);
    }
    return ['ok' => true, 'error' => ''];
}

function cms_care_facts(string $phone): array
{
    $stmt = cms_db()->prepare('SELECT * FROM care_facts WHERE phone = ?');
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    return $row ?: ['phone' => $phone, 'problem' => '', 'medicines' => '', 'allergy' => '', 'extra' => '', 'stage_key' => '', 'updated_at' => ''];
}

function cms_care_fact_change(string $phone, string $field, string $author, string $staff, string $before, string $after): void
{
    if (!in_array($field, ['problem', 'medicines', 'allergy', 'extra', 'stage'], true) || $before === $after) {
        return;
    }
    cms_db()->prepare('INSERT INTO care_fact_log (phone, field, author, staff, before_text, after_text, created_at) VALUES (?,?,?,?,?,?,?)')->execute([
        $phone,
        $field,
        $author === 'patient' ? 'patient' : 'clinic',
        $author === 'patient' ? '' : cms_plain($staff, 80),
        $before,
        $after,
        cms_now(),
    ]);
}

function cms_care_fact_log(string $phone, int $limit = 40): array
{
    $limit = max(1, min(80, $limit));
    $stmt = cms_db()->prepare('SELECT * FROM care_fact_log WHERE phone = ? ORDER BY id DESC LIMIT ' . $limit);
    $stmt->execute([$phone]);
    return $stmt->fetchAll() ?: [];
}

function cms_care_fact_text(string $field, string $value, string $locale = 'fa'): string
{
    $locale = cms_locale($locale);
    if ($field === 'stage') {
        if ($value === '') {
            return $locale === 'fa' ? 'بدون مرحله' : cms_care_ui('no-stage', $locale);
        }
        $label = cms_care_stage_label($value, $locale);
        return $label !== '' ? $label : $value;
    }
    return $value === '' ? ($locale === 'fa' ? 'خالی' : cms_care_ui('empty', $locale)) : $value;
}

function cms_care_save_facts(string $phone, array $input, string $author = 'patient', string $staff = ''): void
{
    $current = cms_care_facts($phone);
    $values = [];
    $changed = false;
    foreach (cms_care_fields() as $key => $label) {
        $values[$key] = cms_care_field_on($key) ? cms_plain((string) ($input[$key] ?? ''), 500) : (string) ($current[$key] ?? '');
        if ($values[$key] !== (string) ($current[$key] ?? '')) {
            cms_care_fact_change($phone, $key, $author, $staff, (string) ($current[$key] ?? ''), $values[$key]);
            $changed = true;
        }
    }
    if (!$changed) {
        return;
    }
    cms_db()->prepare('INSERT INTO care_facts (phone, problem, medicines, allergy, extra, stage_key, updated_at) VALUES (?,?,?,?,?,?,?) ON CONFLICT(phone) DO UPDATE SET problem = excluded.problem, medicines = excluded.medicines, allergy = excluded.allergy, extra = excluded.extra, updated_at = excluded.updated_at')->execute([
        $phone,
        $values['problem'],
        $values['medicines'],
        $values['allergy'],
        $values['extra'],
        (string) ($current['stage_key'] ?? ''),
        cms_now(),
    ]);
}

function cms_care_stages(bool $activeOnly = false): array
{
    $rows = [];
    foreach (cms_db()->query('SELECT * FROM care_stages ORDER BY sort_order, stage_key') as $row) {
        $stage = [
            'key' => (string) $row['stage_key'],
            'fa' => (string) $row['label_fa'],
            'en' => (string) $row['label_en'],
            'ar' => (string) $row['label_ar'],
            'zh' => (string) $row['label_zh'],
            'active' => (int) $row['active'] === 1,
            'sort' => (int) $row['sort_order'],
        ];
        if (!$activeOnly || $stage['active']) {
            $rows[] = $stage;
        }
    }
    return $rows;
}

function cms_care_stage_label(string $key, string $locale = 'fa'): string
{
    $locale = cms_locale($locale);
    foreach (cms_care_stages() as $stage) {
        if ($stage['key'] !== $key) {
            continue;
        }
        $text = trim((string) ($stage[$locale] ?? ''));
        return $text !== '' ? $text : $stage['fa'];
    }
    return '';
}

function cms_care_set_stage(string $phone, string $key, string $staff = ''): void
{
    $current = cms_care_facts($phone);
    $before = (string) ($current['stage_key'] ?? '');
    if ($before === $key) {
        return;
    }
    cms_care_fact_change($phone, 'stage', 'clinic', $staff, $before, $key);
    cms_db()->prepare('INSERT INTO care_facts (phone, problem, medicines, allergy, extra, stage_key, updated_at) VALUES (?,?,?,?,?,?,?) ON CONFLICT(phone) DO UPDATE SET stage_key = excluded.stage_key, updated_at = excluded.updated_at')->execute([
        $phone,
        (string) ($current['problem'] ?? ''),
        (string) ($current['medicines'] ?? ''),
        (string) ($current['allergy'] ?? ''),
        (string) ($current['extra'] ?? ''),
        $key,
        cms_now(),
    ]);
}

function cms_care_reminder_line(array $visit, string $locale = 'fa'): string
{
    $locale = cms_locale($locale);
    if ((string) ($visit['status'] ?? '') !== 'confirmed') {
        return '';
    }
    if (cms_setting('sms_on') !== '1' || !cms_notify_on('patient', 'reminder')) {
        return $locale === 'fa' ? 'یادآوری پیامکی این نوبت خاموش است. مطب آن را در پیام‌رسانی روشن می‌کند.' : cms_care_ui('remind-off', $locale);
    }
    $zone = new DateTimeZone('Asia/Tehran');
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $visit['day'], $zone);
    if (!$day) {
        return '';
    }
    if ((string) ($visit['reminded_at'] ?? '') !== '') {
        return $locale === 'fa' ? 'یادآوری پیامکی این نوبت فرستاده شده است.' : cms_care_ui('remind-sent', $locale);
    }
    $remind = $day->modify('-' . cms_remind_days() . ' days');
    $last = $remind->modify('+' . (cms_remind_grace() - 1) . ' days');
    $today = new DateTimeImmutable('today', $zone);
    if ($day < $today) {
        return '';
    }
    if ($today < $remind) {
        $when = cms_day_label($remind->format('Y-m-d'), $locale);
        return $locale === 'fa' ? 'یادآوری پیامکی در ' . $when . ' می‌رود، اگر نوبت تا آن روز هماهنگ بماند.' : str_replace('{day}', $when, cms_care_ui('remind-future', $locale));
    }
    if ($today <= $last) {
        if ($today->format('Y-m-d') === $remind->format('Y-m-d')) {
            return $locale === 'fa' ? 'یادآوری پیامکی امروز، در ساعت بیداری مطب، می‌رود.' : cms_care_ui('remind-today', $locale);
        }
        $when = cms_day_label($last->format('Y-m-d'), $locale);
        return $locale === 'fa' ? 'یادآوری این نوبت جا مانده و تا ' . $when . ' در ساعت بیداری جبران می‌شود.' : str_replace('{day}', $when, cms_care_ui('remind-late', $locale));
    }
    return $locale === 'fa' ? 'بازه یادآوری این نوبت گذشته است.' : cms_care_ui('remind-past', $locale);
}

function cms_care_alert(string $phone): void
{
    if (cms_setting('care_alert') !== '1') {
        return;
    }
    $tail = cms_fa_digits(substr($phone, -4));
    $text = cms_care_text('tpl_care_alert', 'پیام تازه در پرونده درمان است. چهار رقم آخر: {tail}. متن بیمار در این پیام نیست.');
    $text = cms_clip(str_replace('{tail}', $tail, $text), 300);
    if (cms_setting('sms_on') === '1') {
        $mobile = cms_mobile(cms_setting('sms_doctor'));
        if ($mobile === '') {
            cms_message_log(0, 'sms', 'doctor', 'care', '', $text, 'skipped', '', 'موبایل پزشک در پیام‌رسانی ذخیره نشده است');
        } else {
            $sent = cms_sms_send($mobile, $text);
            cms_message_log(0, 'sms', 'doctor', 'care', $mobile, $text, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
        }
    }
    foreach (['bale' => 'بله', 'telegram' => 'تلگرام', 'eitaa' => 'ایتا'] as $channel => $label) {
        if (cms_setting($channel . '_on') !== '1') {
            continue;
        }
        $sent = cms_chat_send($channel, $text);
        cms_message_log(0, $channel, 'doctor', 'care', $label, $text, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
    }
}

function cms_care_file(int $id): ?array
{
    $stmt = cms_db()->prepare('SELECT f.id, f.phone, f.stored, f.mime, p.hidden FROM care_files f JOIN care_posts p ON p.id = f.post_id WHERE f.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string) $row['stored'])) {
        return null;
    }
    $path = cms_care_dir() . DIRECTORY_SEPARATOR . $row['stored'];
    if (!is_file($path)) {
        return null;
    }
    $row['path'] = $path;
    return $row;
}

function cms_care_on(string $key, bool $defaultOn = true): bool
{
    return cms_setting($key, $defaultOn ? '1' : '0') === '1';
}

function cms_care_heading(string $key, string $fallback, string $locale = 'fa'): string
{
    return cms_care_local($key, $fallback, $locale);
}

function cms_care_rows(string $sql, string $phone): array
{
    $stmt = cms_db()->prepare($sql);
    $stmt->execute([$phone]);
    return $stmt->fetchAll();
}

function cms_care_meds(string $phone): array
{
    return cms_care_rows('SELECT * FROM care_meds WHERE phone = ? ORDER BY active DESC, id DESC LIMIT 40', $phone);
}

function cms_care_needs(string $phone): array
{
    return cms_care_rows('SELECT * FROM care_needs WHERE phone = ? ORDER BY done, id DESC LIMIT 40', $phone);
}

function cms_care_reminders(string $phone): array
{
    return cms_care_rows('SELECT * FROM care_reminders WHERE phone = ? ORDER BY day, id LIMIT 12', $phone);
}

function cms_care_comments(string $phone, bool $includeHidden = false): array
{
    $sql = 'SELECT * FROM care_comments WHERE phone = ?';
    if (!$includeHidden) {
        $sql .= ' AND hidden = 0';
    }
    $sql .= ' ORDER BY id ASC LIMIT 80';
    return cms_care_rows($sql, $phone);
}

function cms_care_owns_visit(string $phone, int $id): bool
{
    if ($id < 1) {
        return false;
    }
    $stmt = cms_db()->prepare('SELECT 1 FROM visits WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    return (bool) $stmt->fetchColumn();
}

function cms_care_add_med(string $phone, string $author, string $name, string $dose, string $clock = '', string $caution = '', string $route = '', string $span = ''): array
{
    $name = cms_plain($name, 80);
    $dose = cms_plain($dose, 160);
    $clock = cms_plain($clock, 80);
    $caution = cms_plain($caution, 160);
    $route = $author === 'clinic' ? cms_plain($route, 40) : '';
    $span = $author === 'clinic' ? cms_plain($span, 40) : '';
    if (!in_array($author, ['patient', 'clinic'], true) || cms_chars($name) < 2) {
        return ['ok' => false, 'error' => 'نام دارو را بنویسید.'];
    }
    $count = cms_db()->prepare('SELECT COUNT(*) FROM care_meds WHERE phone = ? AND active = 1');
    $count->execute([$phone]);
    if ((int) $count->fetchColumn() >= 30) {
        return ['ok' => false, 'error' => 'فهرست دارو پر است. یکی را متوقف کنید.'];
    }
    cms_db()->prepare('INSERT INTO care_meds (phone, author, name, dose, clock, caution, route, span, active, created_at) VALUES (?,?,?,?,?,?,?,?,1,?)')->execute([$phone, $author, $name, $dose, $clock, $caution, $route, $span, cms_now()]);
    return ['ok' => true, 'error' => ''];
}

function cms_care_med_log(string $phone, int $limit = 40): array
{
    $limit = max(1, min(80, $limit));
    $stmt = cms_db()->prepare('SELECT l.med_id, l.kind, l.author, l.staff, l.before_text, l.after_text, l.created_at, COALESCE(m.name, \'\') AS name FROM care_med_log l LEFT JOIN care_meds m ON m.id = l.med_id AND m.phone = l.phone WHERE l.phone = ? ORDER BY l.id DESC LIMIT ' . $limit);
    $stmt->execute([$phone]);
    return $stmt->fetchAll() ?: [];
}

function cms_care_med_trace(string $phone, int $medId, string $kind, string $author, string $staff, string $before, string $after): void
{
    if ($medId < 1 || !in_array($kind, ['dose', 'clock', 'caution', 'stop', 'route', 'span'], true) || !in_array($author, ['patient', 'clinic'], true)) {
        return;
    }
    cms_db()->prepare('INSERT INTO care_med_log (med_id, phone, kind, author, staff, before_text, after_text, created_at) VALUES (?,?,?,?,?,?,?,?)')->execute([
        $medId,
        $phone,
        $kind,
        $author,
        $author === 'patient' ? '' : cms_plain($staff, 80),
        $before,
        $after,
        cms_now(),
    ]);
}

function cms_care_med_dose(string $phone, int $id, string $author, string $doseRaw, string $staff = ''): array
{
    if ($id < 1 || !in_array($author, ['patient', 'clinic'], true)) {
        return ['ok' => false, 'error' => 'این دارو پیدا نشد.', 'changed' => false];
    }
    $stmt = cms_db()->prepare('SELECT author, dose, active FROM care_meds WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'این دارو پیدا نشد.', 'changed' => false];
    }
    if ((int) $row['active'] !== 1) {
        return ['ok' => false, 'error' => 'داروی متوقف دوز تازه نمی‌گیرد.', 'changed' => false];
    }
    if ($author === 'patient' && (string) $row['author'] !== 'patient') {
        return ['ok' => false, 'error' => 'دوز داروی مطب را فقط مطب عوض می‌کند.', 'changed' => false];
    }
    $dose = cms_plain($doseRaw, 160);
    $before = (string) $row['dose'];
    if ($dose === $before) {
        return ['ok' => true, 'error' => '', 'changed' => false];
    }
    cms_db()->prepare('UPDATE care_meds SET dose = ? WHERE id = ? AND phone = ?')->execute([$dose, $id, $phone]);
    cms_care_med_trace($phone, $id, 'dose', $author, $staff, $before, $dose);
    return ['ok' => true, 'error' => '', 'changed' => true];
}

function cms_care_med_plan(string $phone, int $id, string $author, string $clockRaw, string $cautionRaw, string $staff = ''): array
{
    if ($id < 1 || !in_array($author, ['patient', 'clinic'], true)) {
        return ['ok' => false, 'error' => 'این دارو پیدا نشد.', 'changed' => false];
    }
    $stmt = cms_db()->prepare('SELECT author, active, clock, caution FROM care_meds WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'این دارو پیدا نشد.', 'changed' => false];
    }
    if ((int) $row['active'] !== 1) {
        return ['ok' => false, 'error' => 'داروی متوقف ساعت تازه نمی‌گیرد.', 'changed' => false];
    }
    if ($author === 'patient' && (string) $row['author'] !== 'patient') {
        return ['ok' => false, 'error' => 'ساعت داروی مطب را فقط مطب می‌نویسد.', 'changed' => false];
    }
    $clock = cms_plain($clockRaw, 80);
    $caution = cms_plain($cautionRaw, 160);
    $changed = false;
    if ($clock !== (string) $row['clock']) {
        cms_care_med_trace($phone, $id, 'clock', $author, $staff, (string) $row['clock'], $clock);
        $changed = true;
    }
    if ($caution !== (string) $row['caution']) {
        cms_care_med_trace($phone, $id, 'caution', $author, $staff, (string) $row['caution'], $caution);
        $changed = true;
    }
    if ($changed) {
        cms_db()->prepare('UPDATE care_meds SET clock = ?, caution = ? WHERE id = ? AND phone = ?')->execute([$clock, $caution, $id, $phone]);
    }
    return ['ok' => true, 'error' => '', 'changed' => $changed];
}

function cms_care_med_stop(string $phone, int $id, string $author, string $staff = '', string $reason = ''): bool
{
    $stmt = cms_db()->prepare('SELECT author, dose, active FROM care_meds WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $row = $stmt->fetch();
    if (!$row || ($author === 'patient' && (string) $row['author'] !== 'patient')) {
        return false;
    }
    if ((int) $row['active'] !== 1) {
        return true;
    }
    $reason = cms_plain($reason, 160);
    $after = $reason !== '' ? $reason : (string) $row['dose'];
    cms_db()->prepare('UPDATE care_meds SET active = 0, stop_reason = ? WHERE id = ? AND phone = ?')->execute([$reason, $id, $phone]);
    cms_care_med_trace($phone, $id, 'stop', $author, $staff, (string) $row['dose'], $after);
    return true;
}

function cms_care_med_line(string $phone, int $id, string $author, string $routeRaw, string $spanRaw, string $staff = ''): array
{
    if ($id < 1 || $author !== 'clinic') {
        return ['ok' => false, 'error' => 'راه و مدت را فقط مطب می‌نویسد.', 'changed' => false];
    }
    $stmt = cms_db()->prepare('SELECT active, route, span FROM care_meds WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['active'] !== 1) {
        return ['ok' => false, 'error' => 'این دارو پیدا نشد.', 'changed' => false];
    }
    $route = cms_plain($routeRaw, 40);
    $span = cms_plain($spanRaw, 40);
    $changed = false;
    if ($route !== (string) $row['route']) {
        cms_care_med_trace($phone, $id, 'route', 'clinic', $staff, (string) $row['route'], $route);
        $changed = true;
    }
    if ($span !== (string) $row['span']) {
        cms_care_med_trace($phone, $id, 'span', 'clinic', $staff, (string) $row['span'], $span);
        $changed = true;
    }
    if ($changed) {
        cms_db()->prepare('UPDATE care_meds SET route = ?, span = ? WHERE id = ? AND phone = ?')->execute([$route, $span, $id, $phone]);
    }
    return ['ok' => true, 'error' => '', 'changed' => $changed];
}

function cms_fdi_quads(): array
{
    return [
        'ur' => [18, 17, 16, 15, 14, 13, 12, 11],
        'ul' => [21, 22, 23, 24, 25, 26, 27, 28],
        'lr' => [48, 47, 46, 45, 44, 43, 42, 41],
        'll' => [31, 32, 33, 34, 35, 36, 37, 38],
    ];
}

function cms_fdi_ok(int $number): bool
{
    foreach (cms_fdi_quads() as $quad) {
        if (in_array($number, $quad, true)) {
            return true;
        }
    }
    return false;
}

function cms_teeth_marks(string $phone): array
{
    $stmt = cms_db()->prepare('SELECT marks FROM care_teeth WHERE phone = ?');
    $stmt->execute([$phone]);
    $marks = [];
    foreach (explode(',', (string) $stmt->fetchColumn()) as $bit) {
        $number = (int) $bit;
        if (cms_fdi_ok($number)) {
            $marks[$number] = $number;
        }
    }
    ksort($marks);
    return array_values($marks);
}

function cms_teeth_save(string $phone, array $picked): array
{
    if (cms_setting('care_teeth', '0') !== '1') {
        return ['ok' => false, 'error' => 'نمودار دندان از میز خاموش است.', 'marks' => []];
    }
    $marks = [];
    foreach ($picked as $bit) {
        $number = (int) $bit;
        if (cms_fdi_ok($number)) {
            $marks[$number] = $number;
        }
    }
    ksort($marks);
    $list = array_values($marks);
    cms_db()->prepare('INSERT INTO care_teeth (phone, marks, updated_at) VALUES (?,?,?) ON CONFLICT(phone) DO UPDATE SET marks = excluded.marks, updated_at = excluded.updated_at')->execute([$phone, implode(',', $list), cms_now()]);
    return ['ok' => true, 'error' => '', 'marks' => $list];
}

function cms_teeth_markup(array $marks, bool $inputs): string
{
    $on = array_fill_keys($marks, true);
    $html = '<div class="arch" dir="ltr">';
    foreach (cms_fdi_quads() as $name => $numbers) {
        $lower = $name === 'lr' || $name === 'll' ? ' lower' : '';
        $html .= '<div class="quad' . $lower . '">';
        foreach ($numbers as $number) {
            $class = isset($on[$number]) ? ' on' : '';
            if ($inputs) {
                $html .= '<label class="tooth' . $class . '"><input type="checkbox" name="tooth[]" value="' . $number . '"' . (isset($on[$number]) ? ' checked' : '') . '><span>' . $number . '</span></label>';
            } else {
                $html .= '<span class="tooth' . $class . '"><span>' . $number . '</span></span>';
            }
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

function cms_checks(int $visitId): array
{
    if ($visitId < 1) {
        return [];
    }
    $stmt = cms_db()->prepare('SELECT * FROM care_checks WHERE visit_id = ? ORDER BY id');
    $stmt->execute([$visitId]);
    return $stmt->fetchAll() ?: [];
}

function cms_check_apply(int $visitId, string $raw): array
{
    if ($visitId < 1) {
        return ['ok' => false, 'error' => 'این نوبت پیدا نشد.', 'count' => 0];
    }
    $labels = [];
    foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
        $label = cms_plain($line, 80);
        if (cms_chars($label) < 2 || isset($labels[$label])) {
            continue;
        }
        $labels[$label] = true;
        if (count($labels) >= 12) {
            break;
        }
    }
    if ($labels === []) {
        return ['ok' => false, 'error' => 'هر سطر یک کار است.', 'count' => 0];
    }
    $kept = [];
    foreach (cms_checks($visitId) as $row) {
        $kept[(string) $row['label']] = (int) $row['done'] === 1 ? 1 : 0;
    }
    $pdo = cms_db();
    $pdo->prepare('DELETE FROM care_checks WHERE visit_id = ?')->execute([$visitId]);
    $insert = $pdo->prepare('INSERT INTO care_checks (visit_id, label, done, updated_at) VALUES (?,?,?,?)');
    $now = cms_now();
    foreach (array_keys($labels) as $label) {
        $insert->execute([$visitId, $label, $kept[$label] ?? 0, $now]);
    }
    return ['ok' => true, 'error' => '', 'count' => count($labels)];
}

function cms_check_toggle(int $id, int $visitId): bool
{
    if ($id < 1 || $visitId < 1) {
        return false;
    }
    $stmt = cms_db()->prepare('SELECT done FROM care_checks WHERE id = ? AND visit_id = ?');
    $stmt->execute([$id, $visitId]);
    $done = $stmt->fetchColumn();
    if ($done === false) {
        return false;
    }
    cms_db()->prepare('UPDATE care_checks SET done = ?, updated_at = ? WHERE id = ? AND visit_id = ?')->execute([(int) $done === 1 ? 0 : 1, cms_now(), $id, $visitId]);
    return true;
}

function cms_visit_anesthesia(int $id, string $phone, string $text): bool
{
    if ($id < 1 || !cms_care_owns_visit($phone, $id)) {
        return false;
    }
    cms_db()->prepare('UPDATE visits SET anesthesia = ?, updated_at = ? WHERE id = ? AND phone = ?')->execute([cms_plain($text, 240), cms_now(), $id, $phone]);
    return true;
}

function cms_care_self_text(array $row): string
{
    $tpl = trim(cms_setting('tpl_care_self'));
    if ($tpl === '') {
        $tpl = 'یادآوری شما برای {day}: {note}';
    }
    return cms_clip(strtr($tpl, [
        '{day}' => cms_day_label((string) ($row['day'] ?? '')),
        '{note}' => cms_plain((string) ($row['note'] ?? ''), 120),
    ]), 600);
}

function cms_care_add_need(string $phone, string $author, string $body): array
{
    $body = cms_plain($body, 180);
    if (!in_array($author, ['patient', 'clinic'], true) || cms_chars($body) < 2) {
        return ['ok' => false, 'error' => 'کار را کوتاه بنویسید.'];
    }
    $count = cms_db()->prepare('SELECT COUNT(*) FROM care_needs WHERE phone = ? AND done = 0');
    $count->execute([$phone]);
    if ((int) $count->fetchColumn() >= 40) {
        return ['ok' => false, 'error' => 'فهرست کارها پر است.'];
    }
    cms_db()->prepare('INSERT INTO care_needs (phone, author, body, done, created_at) VALUES (?,?,?,0,?)')->execute([$phone, $author, $body, cms_now()]);
    return ['ok' => true, 'error' => ''];
}

function cms_care_need_toggle(string $phone, int $id): bool
{
    $stmt = cms_db()->prepare('SELECT done FROM care_needs WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $done = $stmt->fetchColumn();
    if ($done === false) {
        return false;
    }
    cms_db()->prepare('UPDATE care_needs SET done = ? WHERE id = ? AND phone = ?')->execute([(int) $done === 1 ? 0 : 1, $id, $phone]);
    return true;
}

function cms_care_need_delete(string $phone, int $id, string $author): bool
{
    $stmt = cms_db()->prepare('SELECT author FROM care_needs WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $owner = (string) $stmt->fetchColumn();
    if ($owner === '' || ($author === 'patient' && $owner !== 'patient')) {
        return false;
    }
    cms_db()->prepare('DELETE FROM care_needs WHERE id = ? AND phone = ?')->execute([$id, $phone]);
    return true;
}

function cms_care_need_assign(string $phone, int $id, string $staff): bool
{
    if ($id < 1) {
        return false;
    }
    $stmt = cms_db()->prepare('SELECT id FROM care_needs WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    if (!$stmt->fetchColumn()) {
        return false;
    }
    cms_db()->prepare('UPDATE care_needs SET staff = ? WHERE id = ? AND phone = ?')->execute([cms_plain($staff, 40), $id, $phone]);
    return true;
}

function cms_call_results(): array
{
    return ['reached' => 'جواب داد', 'missed' => 'جواب نداد', 'callback' => 'دوباره زنگ'];
}

function cms_call_add(string $phone, int $visitId, string $result, string $note, string $staff): array
{
    if (!isset(cms_call_results()[$result])) {
        return ['ok' => false, 'error' => 'نتیجهٔ تماس را انتخاب کنید.'];
    }
    if ($visitId > 0 && !cms_care_owns_visit($phone, $visitId)) {
        return ['ok' => false, 'error' => 'این نوبت برای همین شماره نیست.'];
    }
    cms_db()->prepare('INSERT INTO care_calls (phone, visit_id, staff, result, note, created_at) VALUES (?,?,?,?,?,?)')->execute([
        $phone,
        max(0, $visitId),
        cms_plain($staff, 80),
        $result,
        cms_plain($note, 160),
        cms_now(),
    ]);
    return ['ok' => true, 'error' => '', 'result' => $result];
}

function cms_calls(string $phone, int $limit = 20): array
{
    $limit = max(1, min(40, $limit));
    $stmt = cms_db()->prepare('SELECT * FROM care_calls WHERE phone = ? ORDER BY id DESC LIMIT ' . $limit);
    $stmt->execute([$phone]);
    return $stmt->fetchAll() ?: [];
}

function cms_calls_open(int $limit = 8): array
{
    $limit = max(1, min(20, $limit));
    $sql = 'SELECT c.phone, c.result, c.note, c.created_at, c.staff, (SELECT name FROM visits WHERE phone = c.phone ORDER BY id DESC LIMIT 1) AS name FROM care_calls c WHERE c.result IN (\'missed\', \'callback\') ORDER BY c.id DESC LIMIT ' . $limit;
    return cms_db()->query($sql)->fetchAll() ?: [];
}

function cms_care_add_reminder(string $phone, string $day, string $note): array
{
    $day = cms_parse_day($day);
    $note = cms_plain($note, 120);
    $zone = new DateTimeZone('Asia/Tehran');
    $today = new DateTimeImmutable('today', $zone);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, $zone);
    $last = $today->modify('+180 days');
    if (!$date || $date < $today || $date > $last || cms_chars($note) < 2) {
        return ['ok' => false, 'error' => 'روز را از امروز تا شش ماه بعد، شمسی یا میلادی، و یک جمله کوتاه بنویسید.'];
    }
    $max = max(1, min(12, (int) cms_setting('care_remind_max', '6')));
    $count = cms_db()->prepare('SELECT COUNT(*) FROM care_reminders WHERE phone = ?');
    $count->execute([$phone]);
    if ((int) $count->fetchColumn() >= $max) {
        return ['ok' => false, 'error' => 'سقف یادآوری شخصی پر است. یکی را بردارید.'];
    }
    cms_db()->prepare('INSERT INTO care_reminders (phone, day, note, sent_at, created_at) VALUES (?,?,?,?,?)')->execute([$phone, $day, $note, '', cms_now()]);
    return ['ok' => true, 'error' => ''];
}

function cms_care_reminder_delete(string $phone, int $id): bool
{
    $stmt = cms_db()->prepare('DELETE FROM care_reminders WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    return $stmt->rowCount() > 0;
}

function cms_care_add_comment(string $phone, int $visitId, string $author, string $body): array
{
    $body = cms_plain($body, 400);
    if (!in_array($author, ['patient', 'clinic'], true) || cms_chars($body) < 2 || !cms_care_owns_visit($phone, $visitId)) {
        return ['ok' => false, 'error' => 'نظر را برای یکی از نوبت‌های همین شماره بنویسید.'];
    }
    cms_db()->prepare('INSERT INTO care_comments (phone, visit_id, author, body, hidden, created_at) VALUES (?,?,?,?,0,?)')->execute([$phone, $visitId, $author, $body, cms_now()]);
    return ['ok' => true, 'error' => ''];
}

function cms_care_comment_hide(string $phone, int $id): bool
{
    $stmt = cms_db()->prepare('SELECT hidden FROM care_comments WHERE id = ? AND phone = ?');
    $stmt->execute([$id, $phone]);
    $hidden = $stmt->fetchColumn();
    if ($hidden === false) {
        return false;
    }
    cms_db()->prepare('UPDATE care_comments SET hidden = ? WHERE id = ? AND phone = ?')->execute([(int) $hidden === 1 ? 0 : 1, $id, $phone]);
    return true;
}

function cms_care_self_due(): array
{
    if (!cms_care_on('care_self_sms', false) || cms_setting('sms_on') !== '1' || cms_quiet_now()) {
        return ['sent' => 0, 'late' => 0, 'expired' => 0];
    }
    $zone = new DateTimeZone('Asia/Tehran');
    $now = new DateTimeImmutable('now', $zone);
    $today = $now->format('Y-m-d');
    [$earliest, $until] = cms_self_bounds($today, cms_remind_grace());
    $stmt = cms_db()->prepare("SELECT * FROM care_reminders WHERE sent_at = '' AND day >= ? AND day <= ? ORDER BY day, id LIMIT 80");
    $stmt->execute([$earliest, $until]);
    $mark = cms_db()->prepare('UPDATE care_reminders SET sent_at = ? WHERE id = ?');
    $count = 0;
    $late = 0;
    foreach ($stmt as $row) {
        $phone = cms_mobile((string) $row['phone']);
        $text = cms_care_self_text($row);
        if ($phone === '') {
            cms_message_log(0, 'sms', 'patient', 'care-self', (string) $row['phone'], $text, 'skipped', '', 'شماره بیمار موبایل نیست');
        } elseif (cms_sms_stopped($phone)) {
            cms_message_log(0, 'sms', 'patient', 'care-self', $phone, $text, 'skipped', '', 'این شماره از پیامک بیمار خارج است');
        } else {
            $sent = cms_sms_send($phone, $text);
            cms_message_log(0, 'sms', 'patient', 'care-self', $phone, $text, $sent['ok'] ? 'sent' : 'failed', $sent['ref'], $sent['error']);
        }
        $mark->execute([cms_now(), (int) $row['id']]);
        $count++;
        if ((string) $row['day'] < $today) {
            $late++;
        }
    }
    $old = cms_db()->prepare("SELECT id, phone, day, note FROM care_reminders WHERE sent_at = '' AND day < ? ORDER BY day, id LIMIT 80");
    $old->execute([$earliest]);
    $expired = 0;
    foreach ($old as $row) {
        $text = 'یادآوری شما برای ' . cms_day_label((string) $row['day']) . ' از بازه جبران گذشت.';
        cms_message_log(0, 'sms', 'patient', 'care-self', (string) $row['phone'], $text, 'skipped', '', 'بازه جبران گذشت');
        $mark->execute(['expired', (int) $row['id']]);
        $expired++;
    }
    return ['sent' => $count, 'late' => $late, 'expired' => $expired];
}

function cms_care_ask_open(array $visit): bool
{
    if (!in_array((string) ($visit['status'] ?? ''), ['new', 'confirmed', 'arrived'], true)) {
        return false;
    }
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    return (string) ($visit['day'] ?? '') >= $today;
}

function cms_care_ask_map(string $phone): array
{
    $stmt = cms_db()->prepare("SELECT * FROM care_asks WHERE phone = ? AND state = 'open' ORDER BY id DESC");
    $stmt->execute([$phone]);
    $map = [];
    foreach ($stmt as $row) {
        $visitId = (int) $row['visit_id'];
        if (!isset($map[$visitId])) {
            $map[$visitId] = $row;
        }
    }
    return $map;
}

function cms_care_add_ask(string $phone, int $visitId, string $kind, string $dayRaw, string $time, string $note): array
{
    if (!cms_care_on('care_patient_ask')) {
        return ['ok' => false, 'error' => 'درخواست زمان از این صفحه بسته است.'];
    }
    if (!cms_care_owns_visit($phone, $visitId)) {
        return ['ok' => false, 'error' => 'این نوبت برای همین پرونده نیست.'];
    }
    $visit = cms_visit_row($visitId);
    if (!$visit || !cms_care_ask_open($visit)) {
        return ['ok' => false, 'error' => 'برای این نوبت دیگر درخواستی ثبت نمی‌شود.'];
    }
    $open = cms_db()->prepare("SELECT 1 FROM care_asks WHERE visit_id = ? AND state = 'open'");
    $open->execute([$visitId]);
    if ($open->fetchColumn()) {
        return ['ok' => false, 'error' => 'یک درخواست باز برای این نوبت هست.'];
    }
    $kind = $kind === 'cancel' ? 'cancel' : 'move';
    $day = '';
    $slot = '';
    if ($kind === 'move') {
        $day = cms_parse_day($dayRaw);
        $slot = $time;
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
        if ($day === '' || $day < $today || !in_array($slot, cms_book_slots(), true)) {
            return ['ok' => false, 'error' => 'روز را شمسی بنویسید و ساعت را از فهرست انتخاب کنید. نوبت هنوز عوض نمی‌شود.'];
        }
    }
    cms_db()->prepare('INSERT INTO care_asks (phone, visit_id, kind, day, time_slot, note, state, created_at, handled_at) VALUES (?,?,?,?,?,?,\'open\',?,\'\')')->execute([
        $phone,
        $visitId,
        $kind,
        $day,
        $slot,
        cms_plain($note, 200),
        cms_now(),
    ]);
    return ['ok' => true, 'error' => ''];
}

function cms_care_withdraw_ask(string $phone, int $id): bool
{
    $stmt = cms_db()->prepare("UPDATE care_asks SET state = 'withdrawn', handled_at = ? WHERE id = ? AND phone = ? AND state = 'open'");
    $stmt->execute([cms_now(), $id, $phone]);
    return $stmt->rowCount() > 0;
}

function cms_care_finish_ask(string $phone, int $id, string $state): bool
{
    if (!in_array($state, ['kept', 'declined'], true)) {
        return false;
    }
    $stmt = cms_db()->prepare("UPDATE care_asks SET state = ?, handled_at = ? WHERE id = ? AND phone = ? AND state = 'open'");
    $stmt->execute([$state, cms_now(), $id, $phone]);
    return $stmt->rowCount() > 0;
}

function cms_patient_digits(string $raw): string
{
    $raw = strtr(trim($raw), [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
    $phone = preg_replace('/\D+/', '', $raw) ?? '';
    if (str_starts_with($phone, '98') && strlen($phone) >= 12) {
        $phone = '0' . substr($phone, 2);
    }
    return $phone;
}

function cms_national_id(string $raw): string
{
    $digits = cms_patient_digits($raw);
    if (!preg_match('/^\d{10}$/', $digits) || preg_match('/^(\d)\1{9}$/', $digits)) {
        return '';
    }
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int) $digits[$i] * (10 - $i);
    }
    $rest = $sum % 11;
    $check = $rest < 2 ? $rest : 11 - $rest;
    return $check === (int) $digits[9] ? $digits : '';
}

function cms_patient_birth(string $raw): string
{
    $day = cms_parse_day($raw);
    if ($day === '') {
        return '';
    }
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    $jalali = cms_shamsi_iso($day);
    $year = (int) substr($jalali, 0, 4);
    if ($day > $today || $year < 1300) {
        return '';
    }
    return $day;
}

function cms_patient_save(string $phone, string $name, string $note, string $birthRaw, string $nationalRaw): array
{
    $phone = cms_patient_digits($phone);
    $name = cms_plain($name, 80);
    $known = cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?');
    $known->execute([$phone]);
    if ($phone === '' || !$known->fetchColumn() || cms_chars($name) < 2) {
        return ['ok' => false, 'error' => 'پرونده فقط برای شماره‌ای ساخته می‌شود که نوبت دارد، و نامش باید نوشته شود.'];
    }
    $birth = '';
    if (trim($birthRaw) !== '') {
        $birth = cms_patient_birth($birthRaw);
        if ($birth === '') {
            return ['ok' => false, 'error' => 'تاریخ تولد را شمسی بنویسید، مثل 1370-05-12. روز آینده پذیرفته نمی‌شود.'];
        }
    }
    $national = '';
    if (trim($nationalRaw) !== '') {
        $national = cms_national_id($nationalRaw);
        if ($national === '') {
            return ['ok' => false, 'error' => 'کد ملی باید ده رقم با رقم کنترل درست باشد.'];
        }
        $other = cms_db()->prepare('SELECT phone FROM patients WHERE national_id = ? AND phone != ?');
        $other->execute([$national, $phone]);
        if ($other->fetchColumn()) {
            return ['ok' => false, 'error' => 'این کد ملی روی پرونده شماره دیگری است.'];
        }
    }
    $now = cms_now();
    cms_db()->prepare('INSERT INTO patients (phone, name, note, birth, national_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?) ON CONFLICT(phone) DO UPDATE SET name = excluded.name, note = excluded.note, birth = excluded.birth, national_id = excluded.national_id, updated_at = excluded.updated_at')->execute([
        $phone,
        $name,
        cms_plain($note, 2000),
        $birth,
        $national,
        $now,
        $now,
    ]);
    return ['ok' => true, 'error' => ''];
}

function cms_patient_erase(string $phone): array
{
    $phone = cms_patient_digits($phone);
    if ($phone === '') {
        return ['ok' => false, 'error' => 'این شماره نوبتی در میز ندارد.', 'files' => 0];
    }
    $pdo = cms_db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $has = $pdo->prepare('SELECT 1 FROM visits WHERE phone = ?');
        $has->execute([$phone]);
        if (!$has->fetchColumn()) {
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'error' => 'این شماره نوبتی در میز ندارد.', 'files' => 0];
        }
        $stored = $pdo->prepare('SELECT stored FROM care_files WHERE phone = ?');
        $stored->execute([$phone]);
        $files = [];
        foreach ($stored as $row) {
            $name = (string) $row['stored'];
            if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
                $files[] = $name;
            }
        }
        foreach (['care_files', 'care_posts', 'care_facts', 'care_fact_log', 'care_meds', 'care_med_log', 'care_needs', 'care_reminders', 'care_comments', 'care_asks', 'care_companions', 'care_consents', 'care_teeth', 'care_calls', 'patients'] as $table) {
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE phone = ?')->execute([$phone]);
        }
        $pdo->prepare('DELETE FROM care_checks WHERE visit_id IN (SELECT id FROM visits WHERE phone = ?)')->execute([$phone]);
        $stamp = bin2hex(random_bytes(8));
        $pdo->prepare('INSERT INTO care_access (phone, code_hash, stamp, expires_at, updated_at) VALUES (?,?,?,?,?) ON CONFLICT(phone) DO UPDATE SET code_hash = excluded.code_hash, stamp = excluded.stamp, expires_at = excluded.expires_at, updated_at = excluded.updated_at')->execute([
            $phone,
            hash('sha256', bin2hex(random_bytes(16))),
            $stamp,
            '2000-01-01',
            cms_now(),
        ]);
        $pdo->prepare('UPDATE visits SET name = ?, email = ?, message = ?, note = ?, prep = ?, anesthesia = ?, updated_at = ? WHERE phone = ?')->execute(['حذف‌شده', '', '', '', '', '', cms_now(), $phone]);
        $pdo->exec('COMMIT');
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        return ['ok' => false, 'error' => 'حذف انجام نشد و پرونده سر جایش ماند.', 'files' => 0];
    }
    $dir = cms_care_dir();
    foreach ($files as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    return ['ok' => true, 'error' => '', 'files' => count($files)];
}

function cms_patient_join(string $keep, string $other): string
{
    $keep = trim($keep);
    $other = trim($other);
    if ($other === '' || $other === $keep) {
        return $keep;
    }
    if ($keep === '') {
        return $other;
    }
    return $keep . "\n" . 'از شمارهٔ دیگر: ' . $other;
}

function cms_patient_move(string $fromRaw, string $toRaw, string $staff): array
{
    $from = cms_patient_digits($fromRaw);
    $to = cms_mobile(cms_patient_digits($toRaw));
    if ($from === '' || $to === '' || $from === $to) {
        return ['ok' => false, 'error' => 'شماره تازه باید موبایل ۱۱ رقمی باشد و با شماره فعلی فرق داشته باشد.', 'merged' => false, 'code' => 'none', 'clash' => false];
    }
    $pdo = cms_db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $has = $pdo->prepare('SELECT 1 FROM visits WHERE phone = ?');
        $has->execute([$from]);
        if (!$has->fetchColumn()) {
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'error' => 'شماره فعلی نوبتی در میز ندارد.', 'merged' => false, 'code' => 'none', 'clash' => false];
        }
        $merged = false;
        foreach (['visits', 'patients', 'care_facts', 'care_access', 'care_companions', 'care_posts'] as $table) {
            $seen = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE phone = ?');
            $seen->execute([$to]);
            if ($seen->fetchColumn()) {
                $merged = true;
                break;
            }
        }
        $srcFacts = $pdo->prepare('SELECT * FROM care_facts WHERE phone = ?');
        $srcFacts->execute([$from]);
        $srcFact = $srcFacts->fetch();
        $dstFacts = $pdo->prepare('SELECT * FROM care_facts WHERE phone = ?');
        $dstFacts->execute([$to]);
        $dstFact = $dstFacts->fetch();
        if ($srcFact && !$dstFact) {
            $pdo->prepare('UPDATE care_facts SET phone = ? WHERE phone = ?')->execute([$to, $from]);
        } elseif ($srcFact && $dstFact) {
            $full = [
                'problem' => cms_patient_join((string) $dstFact['problem'], (string) $srcFact['problem']),
                'medicines' => cms_patient_join((string) $dstFact['medicines'], (string) $srcFact['medicines']),
                'allergy' => cms_patient_join((string) $dstFact['allergy'], (string) $srcFact['allergy']),
                'extra' => cms_patient_join((string) $dstFact['extra'], (string) $srcFact['extra']),
            ];
            $problem = cms_plain($full['problem'], 500);
            $medicines = cms_plain($full['medicines'], 500);
            $allergy = cms_plain($full['allergy'], 500);
            $extra = cms_plain($full['extra'], 500);
            $beforeStage = (string) $dstFact['stage_key'];
            $stage = $beforeStage !== '' ? $beforeStage : (string) $srcFact['stage_key'];
            foreach (['problem' => $problem, 'medicines' => $medicines, 'allergy' => $allergy, 'extra' => $extra] as $key => $after) {
                $history = $full[$key];
                cms_care_fact_change($to, $key, 'clinic', $staff, (string) $dstFact[$key], $history !== '' ? $history : $after);
            }
            if ($stage !== $beforeStage) {
                cms_care_fact_change($to, 'stage', 'clinic', $staff, $beforeStage, $stage);
            } elseif ((string) $srcFact['stage_key'] !== '' && (string) $srcFact['stage_key'] !== $stage) {
                cms_care_fact_change($to, 'stage', 'clinic', $staff, (string) $srcFact['stage_key'], $stage);
            }
            $pdo->prepare('UPDATE care_facts SET problem = ?, medicines = ?, allergy = ?, extra = ?, stage_key = ?, updated_at = ? WHERE phone = ?')->execute([$problem, $medicines, $allergy, $extra, $stage, cms_now(), $to]);
            $pdo->prepare('DELETE FROM care_facts WHERE phone = ?')->execute([$from]);
        }
        $srcPatient = $pdo->prepare('SELECT * FROM patients WHERE phone = ?');
        $srcPatient->execute([$from]);
        $srcRow = $srcPatient->fetch();
        $dstPatient = $pdo->prepare('SELECT * FROM patients WHERE phone = ?');
        $dstPatient->execute([$to]);
        $dstRow = $dstPatient->fetch();
        $srcBirth = (string) ($srcRow['birth'] ?? '');
        $dstBirth = (string) ($dstRow['birth'] ?? '');
        $srcNational = (string) ($srcRow['national_id'] ?? '');
        $dstNational = (string) ($dstRow['national_id'] ?? '');
        if ($srcRow && $dstRow && $srcBirth !== '' && $dstBirth !== '' && $srcBirth !== $dstBirth) {
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'error' => 'تاریخ تولد دو پرونده یکی نیست. تا وقتی یکی را خالی نکنید ادغام نمی‌شود.', 'merged' => false, 'code' => 'none', 'clash' => false];
        }
        if ($srcRow && $dstRow && $srcNational !== '' && $dstNational !== '' && $srcNational !== $dstNational) {
            $pdo->exec('ROLLBACK');
            return ['ok' => false, 'error' => 'کد ملی دو پرونده یکی نیست. تا وقتی یکی را خالی نکنید ادغام نمی‌شود.', 'merged' => false, 'code' => 'none', 'clash' => false];
        }
        $birth = $dstBirth !== '' ? $dstBirth : $srcBirth;
        $national = $dstNational !== '' ? $dstNational : $srcNational;
        if ($national !== '') {
            $owner = $pdo->prepare('SELECT phone FROM patients WHERE national_id = ? AND phone != ? AND phone != ?');
            $owner->execute([$national, $from, $to]);
            if ($owner->fetchColumn()) {
                $pdo->exec('ROLLBACK');
                return ['ok' => false, 'error' => 'این کد ملی روی پرونده شماره دیگری است.', 'merged' => false, 'code' => 'none', 'clash' => false];
            }
        }
        if ($srcRow && !$dstRow) {
            $pdo->prepare('UPDATE patients SET phone = ?, birth = ?, national_id = ?, updated_at = ? WHERE phone = ?')->execute([$to, $birth, $national, cms_now(), $from]);
        } elseif ($srcRow && $dstRow) {
            $name = cms_chars((string) $dstRow['name']) >= 2 ? (string) $dstRow['name'] : (string) $srcRow['name'];
            $note = cms_plain(cms_patient_join((string) $dstRow['note'], (string) $srcRow['note']), 2000);
            $created = (string) $dstRow['created_at'] <= (string) $srcRow['created_at'] ? (string) $dstRow['created_at'] : (string) $srcRow['created_at'];
            $pdo->prepare('UPDATE patients SET name = ?, note = ?, birth = ?, national_id = ?, created_at = ?, updated_at = ? WHERE phone = ?')->execute([$name, $note, $birth, $national, $created, cms_now(), $to]);
            $pdo->prepare('DELETE FROM patients WHERE phone = ?')->execute([$from]);
        }
        $code = 'none';
        $srcAccess = $pdo->prepare('SELECT 1 FROM care_access WHERE phone = ?');
        $srcAccess->execute([$from]);
        $dstAccess = $pdo->prepare('SELECT 1 FROM care_access WHERE phone = ?');
        $dstAccess->execute([$to]);
        $srcHasAccess = (bool) $srcAccess->fetchColumn();
        $dstHasAccess = (bool) $dstAccess->fetchColumn();
        if ($srcHasAccess && !$dstHasAccess) {
            $pdo->prepare('UPDATE care_access SET phone = ? WHERE phone = ?')->execute([$to, $from]);
            $code = 'moved';
        } elseif ($srcHasAccess && $dstHasAccess) {
            $pdo->prepare('DELETE FROM care_access WHERE phone = ?')->execute([$from]);
            $code = 'kept';
        }
        $srcMate = $pdo->prepare('SELECT id, name FROM care_companions WHERE phone = ? ORDER BY id');
        $srcMate->execute([$from]);
        $srcMates = $srcMate->fetchAll();
        $dstMate = $pdo->prepare('SELECT name FROM care_companions WHERE phone = ?');
        $dstMate->execute([$to]);
        $dstNames = [];
        foreach ($dstMate->fetchAll() as $dstRowMate) {
            $dstNames[(string) $dstRowMate['name']] = true;
        }
        $room = 4 - count($dstNames);
        foreach ($srcMates as $srcRowMate) {
            $mateName = (string) $srcRowMate['name'];
            if ($room > 0 && !isset($dstNames[$mateName])) {
                $pdo->prepare('UPDATE care_companions SET phone = ? WHERE id = ?')->execute([$to, (int) $srcRowMate['id']]);
                $dstNames[$mateName] = true;
                $room--;
            }
        }
        $pdo->prepare('DELETE FROM care_companions WHERE phone = ?')->execute([$from]);
        $srcQuiet = $pdo->prepare('SELECT 1 FROM sms_stops WHERE phone = ?');
        $srcQuiet->execute([$from]);
        $dstQuiet = $pdo->prepare('SELECT 1 FROM sms_stops WHERE phone = ?');
        $dstQuiet->execute([$to]);
        $srcStopped = (bool) $srcQuiet->fetchColumn();
        $dstStopped = (bool) $dstQuiet->fetchColumn();
        if ($srcStopped && !$dstStopped) {
            $pdo->prepare('UPDATE sms_stops SET phone = ? WHERE phone = ?')->execute([$to, $from]);
        } elseif ($srcStopped && $dstStopped) {
            $pdo->prepare('DELETE FROM sms_stops WHERE phone = ?')->execute([$from]);
        }
        $srcTeeth = $pdo->prepare('SELECT marks FROM care_teeth WHERE phone = ?');
        $srcTeeth->execute([$from]);
        $srcMarks = (string) $srcTeeth->fetchColumn();
        $dstTeeth = $pdo->prepare('SELECT marks FROM care_teeth WHERE phone = ?');
        $dstTeeth->execute([$to]);
        $dstMarks = $dstTeeth->fetchColumn();
        if ($srcMarks !== '') {
            if ($dstMarks === false || $dstMarks === '') {
                if ($dstMarks === false) {
                    $pdo->prepare('UPDATE care_teeth SET phone = ? WHERE phone = ?')->execute([$to, $from]);
                } else {
                    $pdo->prepare('UPDATE care_teeth SET marks = ?, updated_at = ? WHERE phone = ?')->execute([$srcMarks, cms_now(), $to]);
                    $pdo->prepare('DELETE FROM care_teeth WHERE phone = ?')->execute([$from]);
                }
            } else {
                $pdo->prepare('DELETE FROM care_teeth WHERE phone = ?')->execute([$from]);
            }
        }
        foreach (['visits', 'care_posts', 'care_files', 'care_fact_log', 'care_asks', 'care_meds', 'care_med_log', 'care_needs', 'care_reminders', 'care_comments', 'care_consents', 'care_calls'] as $table) {
            $pdo->prepare('UPDATE ' . $table . ' SET phone = ? WHERE phone = ?')->execute([$to, $from]);
        }
        $open = cms_slot_statuses();
        $marks = implode(',', array_fill(0, count($open), '?'));
        $clashStmt = $pdo->prepare('SELECT COUNT(*) FROM (SELECT 1 FROM visits WHERE phone = ? AND status IN (' . $marks . ') GROUP BY day, time_slot, kind HAVING COUNT(*) > 1)');
        $clashStmt->execute(array_merge([$to], $open));
        $clash = (int) $clashStmt->fetchColumn() > 0;
        $pdo->exec('COMMIT');
        return ['ok' => true, 'error' => '', 'merged' => $merged, 'code' => $code, 'clash' => $clash];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        return ['ok' => false, 'error' => 'انتقال انجام نشد و هیچ پرونده‌ای عوض نشد.', 'merged' => false, 'code' => 'none', 'clash' => false];
    }
}

function cms_care_sheet_pdf(string $phone, string $locale = 'fa'): string
{
    $locale = cms_locale($locale);
    $rtl = $locale === 'fa' || $locale === 'ar';
    $blocks = [['note', cms_care_ui('pdf-note', $locale)]];
    $name = '';
    foreach (cms_care_visits($phone) as $visit) {
        if (cms_chars((string) $visit['name']) >= 2 && (string) $visit['name'] !== 'حذف‌شده') {
            $name = (string) $visit['name'];
            break;
        }
    }
    if ($name !== '') {
        $blocks[] = ['p', $name];
    }
    $blocks[] = ['h', cms_care_heading('care_h_next', 'نوبت بعدی', $locale)];
    $next = cms_care_next($phone);
    if (!$next) {
        $blocks[] = ['note', cms_care_ui('pdf-no-visit', $locale)];
    } else {
        $place = '';
        $found = cms_place_row((string) ($next['place_key'] ?? ''));
        if ($found) {
            $place = cms_place_label($found, $locale);
        }
        $room = cms_room_label((string) ($next['place_key'] ?? ''), (string) ($next['room_key'] ?? ''));
        $code = cms_topic_code((string) $next['service']);
        $blocks[] = ['p', trim(cms_day_label((string) $next['day'], $locale) . '   ' . (string) $next['time_slot'] . '   ' . cms_kind_label((string) $next['kind'], $locale) . ($place !== '' ? '   ' . $place : '') . ($room !== '' ? '   ' . $room : '') . ($code !== '' ? '   ' . $code : ''))];
        foreach (cms_checks((int) $next['id']) as $check) {
            $blocks[] = ['p', ((int) $check['done'] === 1 ? '✓ ' : '· ') . (string) $check['label']];
        }
    }
    $blocks[] = ['h', cms_care_heading('care_h_med', 'داروها', $locale)];
    $meds = cms_care_meds($phone);
    if (!$meds) {
        $blocks[] = ['note', cms_care_ui('no-meds', $locale)];
    }
    foreach ($meds as $med) {
        $bits = [(string) $med['name']];
        if ((string) $med['dose'] !== '') {
            $bits[] = (string) $med['dose'];
        }
        if ((string) ($med['clock'] ?? '') !== '') {
            $bits[] = (string) $med['clock'];
        }
        if ((string) ($med['caution'] ?? '') !== '') {
            $bits[] = (string) $med['caution'];
        }
        if ((string) ($med['route'] ?? '') !== '') {
            $bits[] = (string) $med['route'];
        }
        if ((string) ($med['span'] ?? '') !== '') {
            $bits[] = (string) $med['span'];
        }
        if ((int) $med['active'] !== 1) {
            $bits[] = cms_care_ui('stopped', $locale);
            if ((string) ($med['stop_reason'] ?? '') !== '') {
                $bits[] = (string) $med['stop_reason'];
            }
        }
        $blocks[] = ['p', implode('   ', $bits)];
    }
    $teeth = cms_setting('care_teeth', '0') === '1' ? cms_teeth_marks($phone) : [];
    if ($teeth) {
        $blocks[] = ['h', cms_care_ui('tooth-title', $locale)];
        $blocks[] = ['p', implode(' ', array_map('strval', $teeth))];
    }
    $blocks[] = ['h', cms_care_ui('pdf-sheet', $locale)];
    foreach (cms_care_fields() as $key => $fallback) {
        if (!cms_care_field_on($key)) {
            continue;
        }
        $value = trim((string) (cms_care_facts($phone)[$key] ?? ''));
        $blocks[] = ['p', cms_care_field_label($key, $locale) . '   ' . ($value !== '' ? $value : cms_care_ui('empty', $locale))];
    }
    $blocks[] = ['h', cms_care_heading('care_h_need', 'چه چیز لازم است', $locale)];
    $needs = cms_care_needs($phone);
    if (!$needs) {
        $blocks[] = ['note', cms_care_ui('pdf-no-need', $locale)];
    }
    foreach ($needs as $need) {
        $blocks[] = ['p', (string) ($need['body'] ?? '')];
    }
    return cms_pdf_file(cms_care_title($locale), $blocks, $rtl);
}

require_once __DIR__ . '/../care/phrases.php';
