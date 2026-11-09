<?php
declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'bootstrap.php') {
    http_response_code(404);
    exit;
}

const CMS_SCHEMA = 3;

require_once __DIR__ . '/calendar.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/carelib.php';
require_once __DIR__ . '/pdfsheet.php';

function cms_sqlite_ready(): bool
{
    return in_array('sqlite', PDO::getAvailableDrivers(), true);
}

function cms_chars(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function cms_clip(string $value, int $max): string
{
    if (cms_chars($value) <= $max) {
        return $value;
    }
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function cms_dir_writable(string $dir): bool
{
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        return false;
    }
    $probe = $dir . DIRECTORY_SEPARATOR . '.write-test';
    if (@file_put_contents($probe, 'ok') === false) {
        return false;
    }
    @unlink($probe);
    return true;
}

function cms_path_outside_web(string $file): bool
{
    $doc = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $dir = realpath(dirname($file));
    if ($doc === false || $dir === false) {
        return false;
    }
    $doc = rtrim(str_replace('\\', '/', $doc), '/') . '/';
    $dir = str_replace('\\', '/', $dir) . '/';
    return !str_starts_with($dir, $doc);
}

function cms_private_sqlite(): ?string
{
    if (PHP_SAPI === 'cli') {
        return null;
    }
    $doc = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($doc === false) {
        return null;
    }
    $parent = dirname($doc);
    if (in_array(strtolower(basename($parent)), ['public_html', 'www', 'httpdocs', 'htdocs', 'public'], true)) {
        return null;
    }
    $dir = $parent . DIRECTORY_SEPARATOR . 'jafari-private';
    if (!cms_dir_writable($dir) || !cms_path_outside_web($dir . DIRECTORY_SEPARATOR . 'clinic.sqlite')) {
        return null;
    }
    return $dir . DIRECTORY_SEPARATOR . 'clinic.sqlite';
}

function cms_pragma(PDO $pdo, string $sql): void
{
    $pdo->query($sql)->fetchAll();
}

function cms_sqlite_copy(string $from, string $to): bool
{
    if (!cms_sqlite_ready() || !is_file($from)) {
        return false;
    }
    try {
        $src = new PDO('sqlite:' . $from, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        cms_pragma($src, 'PRAGMA wal_checkpoint(TRUNCATE)');
        $src = null;
    } catch (Throwable) {
        return false;
    }
    clearstatcache(true, $from);
    return @copy($from, $to) && is_file($to) && filesize($from) === filesize($to);
}

function cms_remember_db(string $file): void
{
    $pointer = __DIR__ . '/data/location.txt';
    if (!cms_dir_writable(__DIR__ . '/data')) {
        return;
    }
    if (is_file($pointer) && trim((string) file_get_contents($pointer)) === $file) {
        return;
    }
    @file_put_contents($pointer, $file);
}

function cms_db_path(): string
{
    static $path = null;
    if (is_string($path)) {
        return $path;
    }
    $override = getenv('CMS_TEST_DB');
    if ((PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') && is_string($override) && $override !== '') {
        $temp = realpath(sys_get_temp_dir());
        $real = realpath($override);
        $root = $temp === false ? '' : strtolower($temp . DIRECTORY_SEPARATOR);
        if ($temp !== false && $real !== false && is_file($real) && $root !== '' && str_starts_with(strtolower($real), $root) && basename($real) !== 'clinic.sqlite') {
            $path = $real;
            return $path;
        }
    }
    $local = __DIR__ . '/data/clinic.sqlite';
    $previous = $local . '.previous';
    $pointer = __DIR__ . '/data/location.txt';
    if (is_file($pointer)) {
        $saved = trim((string) file_get_contents($pointer));
        if ($saved !== '' && basename($saved) === 'clinic.sqlite' && is_file($saved) && cms_path_outside_web($saved)) {
            $path = $saved;
            return $path;
        }
    }
    $private = cms_private_sqlite();
    if ($private !== null && is_file($private)) {
        cms_remember_db($private);
        $path = $private;
        return $path;
    }
    if (!is_file($local) && is_file($previous)) {
        $local = $previous;
    }
    if (is_file($local) && $private !== null && cms_sqlite_copy($local, $private)) {
        cms_remember_db($private);
        if ($local !== $previous) {
            @rename($local, $previous);
        }
        $path = $private;
        return $path;
    }
    if ($private !== null && !is_file($local)) {
        cms_remember_db($private);
        $path = $private;
        return $path;
    }
    $path = $local;
    return $path;
}

function cms_db_place(): string
{
    return cms_path_outside_web(cms_db_path())
        ? 'بیرون از پوشه سایت، در jafari-private'
        : 'داخل cms/data و از وب بسته‌شده';
}

function cms_host_report(): array
{
    $report = [
        ['ok' => PHP_VERSION_ID >= 80100, 'label' => 'نسخه PHP', 'detail' => PHP_VERSION],
        ['ok' => function_exists('mb_strlen'), 'label' => 'متن فارسی', 'detail' => 'افزونه mbstring را در Select PHP Version روشن کنید.'],
        ['ok' => cms_sqlite_ready(), 'label' => 'پایگاه SQLite', 'detail' => 'در Select PHP Version گزینه‌های pdo_sqlite و sqlite3 را روشن کنید. ساخت دیتابیس MySQL لازم نیست.'],
        ['ok' => extension_loaded('gd') && function_exists('imagewebp'), 'label' => 'تبدیل عکس به WebP', 'detail' => 'گزینه gd را روشن کنید. اگر خاموش بماند سایت باز می‌شود، ولی عکس کم‌حجم نمی‌شود.'],
        ['ok' => class_exists('Imagick'), 'label' => 'عکس HEIC آیفون', 'detail' => 'اگر Imagick با پشتیبانی HEIC روشن باشد، عکس آیفون در پرونده بیمار به JPG تبدیل می‌شود. بدون آن، JPG و PNG و WebP پذیرفته می‌شوند و موقعیت‌شان پاک می‌شود.'],
        ['ok' => function_exists('simplexml_load_string'), 'label' => 'خواندن منابع PubMed', 'detail' => 'افزونه simplexml را روشن کنید. بدون آن میز باز می‌شود، ولی ساخت مقاله از منبع ممکن نیست.'],
    ];
    $writable = cms_sqlite_ready() && cms_dir_writable(dirname(cms_db_path()));
    $report[] = [
        'ok' => $writable,
        'label' => 'محل پایگاه',
        'detail' => $writable ? cms_db_place() . '. آپلود بعدی خودش این فایل را عوض نمی‌کند.' : 'پوشه پایگاه قابل نوشتن نیست. در File Manager مجوز cms/data را ۷۵۵ کنید.',
    ];
    return $report;
}

function cms_host_blocked(): string
{
    foreach (cms_host_report() as $item) {
        if ($item['ok'] || $item['label'] === 'تبدیل عکس به WebP' || $item['label'] === 'خواندن منابع PubMed' || $item['label'] === 'عکس HEIC آیفون') {
            continue;
        }
        return $item['label'] . ': ' . $item['detail'];
    }
    return '';
}

function cms_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!cms_sqlite_ready()) {
        throw new RuntimeException('پایگاه میز به SQLite نیاز دارد. در cPanel بخش Select PHP Version را باز کنید و pdo_sqlite و sqlite3 را روشن کنید. ساخت دیتابیس MySQL لازم نیست.');
    }
    $file = cms_db_path();
    $dir = dirname($file);
    if (!cms_dir_writable($dir)) {
        throw new RuntimeException('پوشه پایگاه قابل نوشتن نیست. در File Manager روی cms/data مجوز ۷۵۵ بگذارید. اگر پوشه jafari-private کنار public_html ساخته شده، همان را پاک نکنید.');
    }
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    cms_pragma($pdo, 'PRAGMA foreign_keys = ON');
    cms_pragma($pdo, 'PRAGMA busy_timeout = 5000');
    try {
        cms_pragma($pdo, 'PRAGMA journal_mode = WAL');
    } catch (Throwable) {
    }
    cms_migrate($pdo);
    return $pdo;
}

function cms_snapshot(PDO $pdo): string
{
    $src = cms_db_path();
    if (!is_file($src) || filesize($src) < 1) {
        return '';
    }
    try {
        cms_pragma($pdo, 'PRAGMA wal_checkpoint(TRUNCATE)');
    } catch (Throwable) {
    }
    $dir = dirname($src) . DIRECTORY_SEPARATOR . 'backups';
    if (!cms_dir_writable($dir)) {
        return '';
    }
    $dest = $dir . DIRECTORY_SEPARATOR . 'clinic-' . gmdate('Ymd-His') . '.sqlite';
    if (is_file($dest)) {
        $dest = $dir . DIRECTORY_SEPARATOR . 'clinic-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.sqlite';
    }
    $ready = static function (string $file): bool {
        if (!is_file($file) || filesize($file) < 1) {
            return false;
        }
        try {
            $probe = new PDO('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $found = $probe->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'visits'")->fetchColumn();
            $probe = null;
            return (int) $found === 1;
        } catch (Throwable) {
            return false;
        }
    };
    if (class_exists('SQLite3')) {
        try {
            $origin = new SQLite3($src, SQLITE3_OPEN_READONLY);
            $target = new SQLite3($dest);
            $copied = $origin->backup($target);
            $origin->close();
            $target->close();
            if ($copied !== true || !$ready($dest)) {
                @unlink($dest);
            }
        } catch (Throwable) {
            @unlink($dest);
        }
    }
    if (!$ready($dest)) {
        if (is_file($dest)) {
            @unlink($dest);
        }
        if (!@copy($src, $dest)) {
            return '';
        }
        foreach (['-wal', '-shm'] as $suffix) {
            if (is_file($src . $suffix)) {
                @copy($src . $suffix, $dest . $suffix);
            }
        }
        if (!$ready($dest)) {
            return '';
        }
    }
    $files = glob($dir . DIRECTORY_SEPARATOR . 'clinic-*.sqlite') ?: [];
    sort($files);
    while (count($files) > 7) {
        $old = array_shift($files);
        if (is_string($old) && is_file($old)) {
            @unlink($old);
        }
    }
    return is_file($dest) ? $dest : '';
}

function cms_companions_widen(PDO $pdo): void
{
    $pk = '';
    $info = $pdo->query("PRAGMA table_info(care_companions)");
    foreach ($info ? $info->fetchAll() : [] as $col) {
        if ((int) ($col['pk'] ?? 0) === 1) {
            $pk = (string) $col['name'];
        }
    }
    if ($pk === 'phone') {
        $pdo->exec('ALTER TABLE care_companions RENAME TO care_companions_old');
        $pdo->exec("CREATE TABLE care_companions (id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT NOT NULL, code_hash TEXT NOT NULL, stamp TEXT NOT NULL, expires_at TEXT NOT NULL DEFAULT '', name TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL)");
        $pdo->exec('INSERT INTO care_companions (phone, code_hash, stamp, expires_at, name, updated_at) SELECT phone, code_hash, stamp, expires_at, name, updated_at FROM care_companions_old');
        $pdo->exec('DROP TABLE care_companions_old');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS care_companions_phone ON care_companions (phone, id)');
}

function cms_backup_database(): array
{
    $file = cms_snapshot(cms_db());
    if ($file === '') {
        return ['ok' => false, 'file' => '', 'error' => 'نسخه پشتیبان ساخته نشد. پوشه پایگاه را قابل نوشتن کنید.'];
    }
    return ['ok' => true, 'file' => $file, 'error' => ''];
}

function cms_backup_list(): array
{
    $dir = dirname(cms_db_path()) . DIRECTORY_SEPARATOR . 'backups';
    $files = glob($dir . DIRECTORY_SEPARATOR . 'clinic-*.sqlite') ?: [];
    rsort($files);
    $rows = [];
    foreach ($files as $file) {
        $name = basename($file);
        if (!preg_match('/^clinic-(\d{8})-(\d{6})(?:-[a-f0-9]{4})?\.sqlite$/', $name, $match)) {
            continue;
        }
        $rows[] = ['name' => $name, 'bytes' => (int) filesize($file), 'made' => $match[1] . $match[2]];
    }
    return $rows;
}

function cms_wait_rows(int $limit = 12): array
{
    $limit = max(1, min(40, $limit));
    $stmt = cms_db()->query('SELECT id, name, phone, day, time_slot, kind, place_key, booked_by, created_at FROM visits WHERE status = \'new\' ORDER BY created_at, id LIMIT ' . $limit);
    return $stmt ? $stmt->fetchAll() : [];
}

function cms_book_sheet(): string
{
    $rows = cms_db()->query('SELECT day, time_slot, status, kind, place_key, code, name, phone, booked_by FROM visits ORDER BY day, time_slot, id')->fetchAll();
    $out = "day,time,status,kind,place,code,name,phone,booked_by\n";
    foreach ($rows as $row) {
        $place = '';
        $found = cms_place_row((string) ($row['place_key'] ?? ''));
        if ($found) {
            $place = cms_place_label($found, 'fa');
        }
        $cells = [
            cms_shamsi_iso((string) $row['day']),
            (string) $row['time_slot'],
            cms_visit_statuses()[(string) $row['status']] ?? (string) $row['status'],
            cms_kind_label((string) $row['kind']),
            $place,
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['phone'],
            (string) ($row['booked_by'] ?? ''),
        ];
        $quoted = [];
        foreach ($cells as $cell) {
            $quoted[] = '"' . str_replace('"', '""', $cell) . '"';
        }
        $out .= implode(',', $quoted) . "\n";
    }
    return $out;
}

function cms_migrate(PDO $pdo): void
{
    $before = 0;
    $settings = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'settings'")->fetchColumn();
    if ($settings === 'settings') {
        $stored = $pdo->query("SELECT value FROM settings WHERE key = 'schema_version'")->fetchColumn();
        $before = is_string($stored) && $stored !== '' ? (int) $stored : 1;
    }
    if ($before > 0 && $before < CMS_SCHEMA) {
        cms_snapshot($pdo);
    }
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT NOT NULL CHECK (kind IN ('doctor', 'briefing')),
  slug TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('draft', 'published')),
  title_fa TEXT NOT NULL DEFAULT '',
  title_en TEXT NOT NULL DEFAULT '',
  excerpt_fa TEXT NOT NULL DEFAULT '',
  excerpt_en TEXT NOT NULL DEFAULT '',
  body_fa TEXT NOT NULL DEFAULT '',
  body_en TEXT NOT NULL DEFAULT '',
  seo_title_fa TEXT NOT NULL DEFAULT '',
  seo_title_en TEXT NOT NULL DEFAULT '',
  seo_description_fa TEXT NOT NULL DEFAULT '',
  seo_description_en TEXT NOT NULL DEFAULT '',
  source_name TEXT NOT NULL DEFAULT '',
  source_url TEXT NOT NULL DEFAULT '',
  source_date TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  published_at TEXT,
  UNIQUE (kind, slug)
);
CREATE TABLE IF NOT EXISTS slots (
  slot_key TEXT NOT NULL,
  locale TEXT NOT NULL,
  value TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  PRIMARY KEY (slot_key, locale)
);
CREATE TABLE IF NOT EXISTS media (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  path TEXT NOT NULL UNIQUE,
  original_name TEXT NOT NULL,
  alt_fa TEXT NOT NULL DEFAULT '',
  alt_en TEXT NOT NULL DEFAULT '',
  bytes INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS leads (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kind TEXT NOT NULL CHECK (kind IN ('appointment', 'contact')),
  status TEXT NOT NULL,
  name TEXT NOT NULL,
  phone TEXT NOT NULL,
  service TEXT NOT NULL DEFAULT '',
  preferred_at TEXT NOT NULL DEFAULT '',
  message TEXT NOT NULL DEFAULT '',
  note TEXT NOT NULL DEFAULT '',
  locale TEXT NOT NULL DEFAULT 'fa',
  ip_hash TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS visits (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL,
  name TEXT NOT NULL,
  phone TEXT NOT NULL,
  email TEXT NOT NULL DEFAULT '',
  service TEXT NOT NULL DEFAULT '',
  day TEXT NOT NULL,
  time_slot TEXT NOT NULL,
  message TEXT NOT NULL DEFAULT '',
  note TEXT NOT NULL DEFAULT '',
  locale TEXT NOT NULL DEFAULT 'fa',
  ip_hash TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS consult_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip_hash TEXT NOT NULL,
  locale TEXT NOT NULL,
  chars INTEGER NOT NULL,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS audit (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  admin_id INTEGER,
  username TEXT NOT NULL DEFAULT '',
  action TEXT NOT NULL,
  target TEXT NOT NULL DEFAULT '',
  detail TEXT NOT NULL DEFAULT '',
  ip_hash TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS seo (
  path TEXT NOT NULL,
  locale TEXT NOT NULL,
  title TEXT NOT NULL DEFAULT '',
  description TEXT NOT NULL DEFAULT '',
  robots TEXT NOT NULL DEFAULT '',
  og_image TEXT NOT NULL DEFAULT '',
  PRIMARY KEY (path, locale)
);
SQL);
    cms_ensure_column($pdo, 'admins', 'role', "TEXT NOT NULL DEFAULT 'owner'");
    cms_ensure_column($pdo, 'admins', 'display_name', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'admins', 'active', 'INTEGER NOT NULL DEFAULT 1');
    cms_ensure_column($pdo, 'admins', 'last_login', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'admins', 'place_key', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'cover', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'category', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'featured', 'INTEGER NOT NULL DEFAULT 0');
    cms_ensure_column($pdo, 'posts', 'sources_json', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'image_prompt', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'image_alt', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'studio_note', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'search_query', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'posts', 'ai_draft', 'INTEGER NOT NULL DEFAULT 0');
    cms_ensure_column($pdo, 'posts', 'physician_review', 'INTEGER NOT NULL DEFAULT 0');
    cms_ensure_column($pdo, 'posts', 'reviewed_at', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'media', 'width', 'INTEGER NOT NULL DEFAULT 0');
    cms_ensure_column($pdo, 'media', 'height', 'INTEGER NOT NULL DEFAULT 0');
    cms_ensure_column($pdo, 'media', 'source_bytes', 'INTEGER NOT NULL DEFAULT 0');
    cms_ensure_column($pdo, 'media', 'show_gallery', 'INTEGER NOT NULL DEFAULT 0');
    cms_ensure_column($pdo, 'media', 'caption_fa', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'media', 'caption_en', "TEXT NOT NULL DEFAULT ''");
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS system_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  level TEXT NOT NULL,
  area TEXT NOT NULL,
  event TEXT NOT NULL,
  message TEXT NOT NULL,
  context TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS login_guard (
  ip_hash TEXT PRIMARY KEY,
  tries INTEGER NOT NULL DEFAULT 0,
  lock_until INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS consult_log_recent ON consult_log (created_at);
SQL);
    cms_ensure_column($pdo, 'visits', 'kind', "TEXT NOT NULL DEFAULT 'exam'");
    cms_ensure_column($pdo, 'visits', 'source', "TEXT NOT NULL DEFAULT 'site'");
    cms_ensure_column($pdo, 'visits', 'reminded_at', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'visits', 'prep', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'visits', 'place_key', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'visits', 'booked_by', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'visits', 'room_key', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'visits', 'anesthesia', "TEXT NOT NULL DEFAULT ''");
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS visit_kinds (
  kind_key TEXT PRIMARY KEY,
  label_fa TEXT NOT NULL,
  label_en TEXT NOT NULL DEFAULT '',
  label_ar TEXT NOT NULL DEFAULT '',
  label_zh TEXT NOT NULL DEFAULT '',
  minutes INTEGER NOT NULL DEFAULT 30,
  active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS visit_topics (
  topic_key TEXT PRIMARY KEY,
  label_fa TEXT NOT NULL,
  label_en TEXT NOT NULL DEFAULT '',
  label_ar TEXT NOT NULL DEFAULT '',
  label_zh TEXT NOT NULL DEFAULT '',
  active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS patients (
  phone TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  note TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS book_places (
  place_key TEXT PRIMARY KEY,
  label_fa TEXT NOT NULL,
  label_en TEXT NOT NULL DEFAULT '',
  label_ar TEXT NOT NULL DEFAULT '',
  label_zh TEXT NOT NULL DEFAULT '',
  kind TEXT NOT NULL DEFAULT 'clinic',
  address TEXT NOT NULL DEFAULT '',
  weekdays TEXT NOT NULL DEFAULT '',
  start_time TEXT NOT NULL DEFAULT '09:00',
  end_time TEXT NOT NULL DEFAULT '20:00',
  active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS book_rooms (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  place_key TEXT NOT NULL,
  room_key TEXT NOT NULL,
  label TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  UNIQUE(place_key, room_key)
);
CREATE TABLE IF NOT EXISTS visit_blocks (
  day TEXT PRIMARY KEY,
  note TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS messages_out (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  visit_id INTEGER NOT NULL DEFAULT 0,
  channel TEXT NOT NULL,
  audience TEXT NOT NULL,
  event TEXT NOT NULL,
  target TEXT NOT NULL DEFAULT '',
  body TEXT NOT NULL DEFAULT '',
  status TEXT NOT NULL,
  provider_ref TEXT NOT NULL DEFAULT '',
  error TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS messages_out_recent ON messages_out (id);
CREATE INDEX IF NOT EXISTS visits_day ON visits (day, time_slot);
SQL);
    cms_ensure_column($pdo, 'patients', 'birth', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'patients', 'national_id', "TEXT NOT NULL DEFAULT ''");
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS care_posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  author TEXT NOT NULL,
  body TEXT NOT NULL DEFAULT '',
  hidden INTEGER NOT NULL DEFAULT 0,
  seen_at TEXT NOT NULL DEFAULT '',
  staff TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_inbox (
  channel TEXT NOT NULL,
  update_id INTEGER NOT NULL,
  created_at TEXT NOT NULL,
  PRIMARY KEY (channel, update_id)
);
CREATE TABLE IF NOT EXISTS care_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  post_id INTEGER NOT NULL,
  phone TEXT NOT NULL,
  stored TEXT NOT NULL,
  mime TEXT NOT NULL,
  bytes INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_consents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  kind TEXT NOT NULL,
  sentence_sha TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS care_consents_phone ON care_consents (phone, id);
CREATE INDEX IF NOT EXISTS care_posts_phone ON care_posts (phone, id);
CREATE INDEX IF NOT EXISTS care_files_post ON care_files (post_id);
CREATE TABLE IF NOT EXISTS care_facts (
  phone TEXT PRIMARY KEY,
  problem TEXT NOT NULL DEFAULT '',
  medicines TEXT NOT NULL DEFAULT '',
  allergy TEXT NOT NULL DEFAULT '',
  extra TEXT NOT NULL DEFAULT '',
  stage_key TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_fact_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  field TEXT NOT NULL,
  author TEXT NOT NULL,
  staff TEXT NOT NULL DEFAULT '',
  before_text TEXT NOT NULL DEFAULT '',
  after_text TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS care_fact_log_phone ON care_fact_log (phone, id);
CREATE TABLE IF NOT EXISTS care_asks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  visit_id INTEGER NOT NULL,
  kind TEXT NOT NULL,
  day TEXT NOT NULL DEFAULT '',
  time_slot TEXT NOT NULL DEFAULT '',
  note TEXT NOT NULL DEFAULT '',
  state TEXT NOT NULL DEFAULT 'open',
  created_at TEXT NOT NULL,
  handled_at TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS care_asks_open ON care_asks (phone, state, id);
CREATE TABLE IF NOT EXISTS care_companions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  code_hash TEXT NOT NULL,
  stamp TEXT NOT NULL,
  expires_at TEXT NOT NULL DEFAULT '',
  name TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS care_companions_phone ON care_companions (phone, id);
CREATE TABLE IF NOT EXISTS care_stages (
  stage_key TEXT PRIMARY KEY,
  label_fa TEXT NOT NULL,
  label_en TEXT NOT NULL DEFAULT '',
  label_ar TEXT NOT NULL DEFAULT '',
  label_zh TEXT NOT NULL DEFAULT '',
  active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS care_meds (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  author TEXT NOT NULL,
  name TEXT NOT NULL,
  dose TEXT NOT NULL DEFAULT '',
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_needs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  author TEXT NOT NULL,
  body TEXT NOT NULL,
  done INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_reminders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  day TEXT NOT NULL,
  note TEXT NOT NULL DEFAULT '',
  sent_at TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  visit_id INTEGER NOT NULL DEFAULT 0,
  author TEXT NOT NULL,
  body TEXT NOT NULL,
  hidden INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS care_meds_phone ON care_meds (phone, id);
CREATE TABLE IF NOT EXISTS care_med_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  med_id INTEGER NOT NULL,
  phone TEXT NOT NULL,
  kind TEXT NOT NULL,
  author TEXT NOT NULL,
  staff TEXT NOT NULL DEFAULT '',
  before_text TEXT NOT NULL DEFAULT '',
  after_text TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS care_med_log_phone ON care_med_log (phone, id);
CREATE INDEX IF NOT EXISTS care_needs_phone ON care_needs (phone, id);
CREATE INDEX IF NOT EXISTS care_reminders_day ON care_reminders (day, sent_at);
CREATE INDEX IF NOT EXISTS care_comments_phone ON care_comments (phone, visit_id, id);
CREATE TABLE IF NOT EXISTS sms_stops (
  phone TEXT PRIMARY KEY,
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_teeth (
  phone TEXT PRIMARY KEY,
  marks TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS care_checks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  visit_id INTEGER NOT NULL,
  label TEXT NOT NULL,
  done INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS care_checks_visit ON care_checks (visit_id, id);
CREATE TABLE IF NOT EXISTS care_calls (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  phone TEXT NOT NULL,
  visit_id INTEGER NOT NULL DEFAULT 0,
  staff TEXT NOT NULL DEFAULT '',
  result TEXT NOT NULL,
  note TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS care_calls_phone ON care_calls (phone, id);
CREATE TABLE IF NOT EXISTS care_access (
  phone TEXT PRIMARY KEY,
  code_hash TEXT NOT NULL,
  stamp TEXT NOT NULL,
  expires_at TEXT NOT NULL DEFAULT '',
  updated_at TEXT NOT NULL
);
SQL);
    cms_ensure_column($pdo, 'care_companions', 'name', "TEXT NOT NULL DEFAULT ''");
    cms_companions_widen($pdo);
    cms_ensure_column($pdo, 'care_meds', 'clock', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'care_meds', 'caution', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'care_meds', 'stop_reason', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'care_posts', 'via', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'care_needs', 'staff', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'care_meds', 'route', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'care_meds', 'span', "TEXT NOT NULL DEFAULT ''");
    cms_ensure_column($pdo, 'visit_topics', 'service_code', "TEXT NOT NULL DEFAULT ''");
    if ((int) $pdo->query('SELECT COUNT(*) FROM care_stages')->fetchColumn() === 0) {
        $seedStage = $pdo->prepare('INSERT INTO care_stages (stage_key, label_fa, label_en, label_ar, label_zh, active, sort_order) VALUES (?,?,?,?,?,1,?)');
        foreach ([
            ['review', 'بررسی', 'Review', 'مراجعة', '复查', 1],
            ['plan', 'برنامه', 'Plan', 'خطة', '计划', 2],
            ['care', 'مراقبت', 'Care', 'رعاية', '护理', 3],
            ['follow', 'پیگیری', 'Follow-up', 'متابعة', '随访', 4],
        ] as $stageRow) {
            $seedStage->execute($stageRow);
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM visit_kinds')->fetchColumn() === 0) {
        $seedKind = $pdo->prepare('INSERT INTO visit_kinds (kind_key, label_fa, label_en, label_ar, label_zh, minutes, active, sort_order) VALUES (?,?,?,?,?,?,1,?)');
        foreach ([
            ['exam', 'معاینه و طرح درمان', 'Examination and treatment plan', 'فحص وخطة علاج', '检查和治疗计划', 30, 1],
            ['postop', 'ویزیت بعد از عمل', 'Visit after surgery', 'زيارة بعد الجراحة', '术后复诊', 20, 2],
            ['consult', 'مشاوره', 'Consultation', 'استشارة', '咨询', 30, 3],
            ['surgery', 'وقت جراحی', 'Surgery time', 'وقت جراحة', '手术时间', 60, 4],
        ] as $kindRow) {
            $seedKind->execute($kindRow);
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM visit_topics')->fetchColumn() === 0) {
        $seedTopic = $pdo->prepare('INSERT INTO visit_topics (topic_key, label_fa, label_en, label_ar, label_zh, active, sort_order) VALUES (?,?,?,?,?,1,?)');
        foreach ([
            ['unsure', 'مطمئن نیستم / معاینه', 'Not sure / examination', 'لست متأكداً / فحص', '还不确定 / 检查', 1],
            ['wisdom-tooth-surgery', 'جراحی دندان عقل', 'Wisdom Tooth Surgery', 'جراحة ضرس العقل', '智齿手术', 2],
            ['dental-implants', 'ایمپلنت دندانی', 'Dental Implants', 'زراعة الأسنان', '种植牙', 3],
            ['orthognathic-surgery', 'جراحی ارتوگناتیک (اصلاح ناهنجاری فک)', 'Orthognathic (Jaw) Surgery', 'جراحة تقويم الفكين', '正颌手术', 4],
            ['oral-pathology', 'پاتولوژی دهان و ضایعات فکی', 'Oral Pathology & Jaw Lesions', 'أمراض الفم وآفات الفك', '口腔病理与颌骨病变', 5],
            ['bone-graft-sinus-lift', 'پیوند استخوان و سینوس لیفت', 'Bone graft and sinus lift', 'طعم العظم ورفع الجيب', '植骨与上颌窦提升', 6],
            ['all-on-4', 'ایمپلنت تمام فک', 'Full-arch implants', 'زراعة الفك الكامل', '全颌种植', 7],
            ['facial-fractures', 'شکستگی فک و صورت', 'Facial fractures', 'كسور الفك والوجه', '颌面骨折', 8],
            ['tmj', 'اختلال مفصل فک', 'Jaw-joint disorders', 'اضطرابات مفصل الفك', '颞下颌关节紊乱', 9],
            ['cosmetic-jaw', 'جراحی فرم فک و چانه', 'Jaw and chin form', 'شكل الفك والذقن', '颌骨与下巴外形', 10],
        ] as $topicRow) {
            $seedTopic->execute($topicRow);
        }
    }
    $closedDays = $pdo->query("SELECT value FROM settings WHERE key = 'book_closed'")->fetchColumn();
    if ($closedDays === false) {
        $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute(['book_closed', '5']);
    }
    $capacity = $pdo->query("SELECT value FROM settings WHERE key = 'book_capacity'")->fetchColumn();
    if ($capacity === false) {
        $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute(['book_capacity', '1']);
    }
    $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['schema_version', (string) CMS_SCHEMA]);
    if ($before < 3) {
        $savedBase = $pdo->query("SELECT value FROM settings WHERE key = 'openai_base'")->fetchColumn();
        $savedKey = $pdo->query("SELECT value FROM settings WHERE key = 'openai_key'")->fetchColumn();
        $savedModel = $pdo->query("SELECT value FROM settings WHERE key = 'openai_model'")->fetchColumn();
        $baseEmpty = !is_string($savedBase) || $savedBase === '' || $savedBase === 'https://api.openai.com/v1';
        $keyEmpty = !is_string($savedKey) || $savedKey === '';
        if ($baseEmpty && $keyEmpty) {
            $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['openai_base', 'https://openrouter.ai/api/v1']);
            if (!is_string($savedModel) || $savedModel === '' || $savedModel === 'gpt-4o-mini') {
                $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')->execute(['openai_model', 'google/gemma-4-26b-a4b-it:free']);
            }
        }
    }
}

function cms_ensure_column(PDO $pdo, string $table, string $column, string $ddl): void
{
    if (!in_array($table, ['admins', 'posts', 'media', 'visits', 'patients', 'care_companions', 'care_meds', 'care_needs', 'care_posts', 'visit_topics'], true) || !preg_match('/^[a-z_]+$/', $column)) {
        return;
    }
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $ddl);
}

function cms_installed(): bool
{
    try {
        if (!cms_sqlite_ready() || !is_file(cms_db_path())) {
            return false;
        }
        return (int) cms_db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function cms_install_secret_paths(): array
{
    $paths = [];
    $private = cms_private_sqlite();
    if (is_string($private)) {
        $paths[] = dirname($private) . DIRECTORY_SEPARATOR . 'install.token';
    }
    $paths[] = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'install.token';
    return $paths;
}

function cms_install_secret_path(): ?string
{
    foreach (cms_install_secret_paths() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function cms_install_secret_value(): string
{
    $path = cms_install_secret_path();
    if ($path === null) {
        return '';
    }
    $raw = file_get_contents($path, false, null, 0, 300);
    if (!is_string($raw)) {
        return '';
    }
    return trim($raw);
}

function cms_install_secret_ready(): bool
{
    $value = cms_install_secret_value();
    if (strlen($value) < 20 || strlen($value) > 200) {
        return false;
    }
    return preg_match('/[A-Za-z]/', $value) === 1 && preg_match('/[0-9]/', $value) === 1;
}

function cms_install_secret_ensure(): bool
{
    if (cms_install_secret_ready()) {
        return true;
    }
    $token = bin2hex(random_bytes(16));
    foreach (cms_install_secret_paths() as $path) {
        if (!cms_dir_writable(dirname($path))) {
            continue;
        }
        if (@file_put_contents($path, $token) === false) {
            continue;
        }
        @chmod($path, 0600);
        return cms_install_secret_ready();
    }
    return false;
}

function cms_install_secret_ok(string $given): bool
{
    $expected = cms_install_secret_value();
    $given = trim($given);
    if (!cms_install_secret_ready() || $given === '') {
        return false;
    }
    return hash_equals(hash('sha256', $expected), hash('sha256', $given));
}

function cms_install_secret_place(): string
{
    if (cms_private_sqlite() !== null) {
        return 'jafari-private/install.token کنار public_html. اگر آن پوشه ساخته نمی‌شود، cms/data/install.token';
    }
    return 'cms/data/install.token';
}

function cms_install_secret_remove(): bool
{
    $path = cms_install_secret_path();
    return $path !== null && is_file($path) && @unlink($path);
}

function cms_setting(string $key, string $default = ''): string
{
    $stmt = cms_db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : $default;
}

function cms_set_setting(string $key, string $value): void
{
    $stmt = cms_db()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    $stmt->execute([$key, $value]);
}

function cms_look(): array
{
    $sections = ['hero', 'intro', 'treatments', 'implant', 'surgeon', 'reviews', 'journal', 'story', 'anatomy', 'contact'];
    $motion = 'quiet';
    $hero = 'portrait';
    $story = 'sticky';
    $articles = 'cards';
    $contact = 'band';
    $home = $sections;
    $hide = [];
    if (cms_installed()) {
        $motion = cms_setting('look_motion', 'quiet');
        $hero = cms_setting('look_hero', 'portrait');
        $story = cms_setting('look_story', 'sticky');
        $articles = cms_setting('look_articles', 'cards');
        $contact = cms_setting('look_contact', 'band');
        $saved = array_values(array_filter(explode('.', cms_setting('look_home', '')), static fn (string $id): bool => in_array($id, $sections, true)));
        if ($saved) {
            foreach ($sections as $id) {
                if (!in_array($id, $saved, true)) {
                    $saved[] = $id;
                }
            }
            $home = $saved;
        }
        $hide = array_values(array_filter(explode('.', cms_setting('look_hide', '')), static fn (string $id): bool => $id !== 'hero' && in_array($id, $sections, true)));
    }
    if (!in_array($motion, ['quiet', 'gentle', 'off'], true)) {
        $motion = 'quiet';
    }
    if (!in_array($hero, ['portrait', 'studio'], true)) {
        $hero = 'portrait';
    }
    if (!in_array($story, ['stacked', 'sticky'], true)) {
        $story = 'sticky';
    }
    if (!in_array($articles, ['cards', 'rows'], true)) {
        $articles = 'cards';
    }
    if (!in_array($contact, ['band', 'full'], true)) {
        $contact = 'band';
    }
    $token = 'motion=' . $motion . ',hero=' . $hero . ',story=' . $story . ',articles=' . $articles . ',contact=' . $contact . ',home=' . implode('.', $home) . ',hide=' . implode('.', $hide);
    return compact('motion', 'hero', 'story', 'articles', 'contact', 'home', 'hide', 'token');
}

function cms_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
}

function cms_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = bin2hex(random_bytes(16));
    }
    return $nonce;
}

function cms_csp_policy(): string
{
    $nonce = cms_csp_nonce();
    return "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; worker-src 'self' blob:";
}

function cms_csp_stamp(string $html): string
{
    $nonce = cms_csp_nonce();
    $stamped = preg_replace_callback(
        '#<script\b([^>]*)>#i',
        static function (array $match) use ($nonce): string {
            $attrs = $match[1];
            if (preg_match('#\bsrc\s*=#i', $attrs) || preg_match('#\bnonce\s*=#i', $attrs)) {
                return $match[0];
            }
            if (preg_match('#\btype\s*=\s*([\'"])application/(?:json|ld\+json)\1#i', $attrs)) {
                return $match[0];
            }
            return '<script nonce="' . $nonce . '"' . $attrs . '>';
        },
        $html
    );
    return is_string($stamped) ? $stamped : $html;
}

function cms_csp_send(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    header('Content-Security-Policy: ' . cms_csp_policy());
}

function cms_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('CLINICSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => cms_https(),
    ]);
    session_start();
}

function cms_user(): ?array
{
    cms_session();
    $id = $_SESSION['admin_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT id, username, role, display_name, active, place_key FROM admins WHERE id = ?');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    if (!$row || (int) ($row['active'] ?? 1) !== 1) {
        return null;
    }
    $row['role'] = cms_desk_role((string) ($row['role'] ?? ''));
    return $row;
}

function cms_desk_role(string $role): string
{
    return in_array($role, ['editor', 'nurse'], true) ? $role : 'owner';
}

function cms_nurse(): bool
{
    $user = cms_user();
    return $user !== null && $user['role'] === 'nurse';
}

function cms_owner(): bool
{
    $user = cms_user();
    return $user !== null && $user['role'] === 'owner';
}

function cms_csrf(): string
{
    cms_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function cms_check_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(cms_csrf(), $sent)) {
        http_response_code(419);
        exit('نشست نامعتبر است. صفحه را تازه کنید.');
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cms_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function cms_paragraphs(string $text): string
{
    $blocks = preg_split("/\n\s*\n/", trim($text)) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        $html .= '<p>' . nl2br(e(trim($block)), false) . '</p>';
    }
    return $html;
}

function cms_origin(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (cms_https() ? 'https' : 'http') . '://' . $host;
}

function cms_locale(string $value): string
{
    return in_array($value, ['fa', 'en', 'ar', 'zh'], true) ? $value : 'fa';
}

function cms_line(string $locale, string $fa, string $en, string $ar = '', string $zh = ''): string
{
    return match ($locale) {
        'fa' => $fa,
        'ar' => $ar !== '' ? $ar : $en,
        'zh' => $zh !== '' ? $zh : $en,
        default => $en,
    };
}

function cms_book_slots(): array
{
    $raw = cms_setting('book_slots', '16:00,16:30,17:00,17:30,18:00,18:30,19:00,19:30,20:00');
    $slots = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $part)) {
            $slots[] = $part;
        }
    }
    return $slots !== [] ? $slots : ['16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00'];
}

function cms_field(array $row, string $base, string $locale): string
{
    $key = $base . '_' . $locale;
    $value = trim((string) ($row[$key] ?? ''));
    if ($value !== '') {
        return $value;
    }
    $fallback = $base . '_' . ($locale === 'fa' ? 'en' : 'fa');
    return trim((string) ($row[$fallback] ?? ''));
}

function cms_published(string $kind, ?string $slug = null): array
{
    if ($slug === null) {
        $stmt = cms_db()->prepare("SELECT * FROM posts WHERE kind = ? AND status = 'published' ORDER BY COALESCE(published_at, updated_at) DESC");
        $stmt->execute([$kind]);
        return $stmt->fetchAll();
    }
    $stmt = cms_db()->prepare("SELECT * FROM posts WHERE kind = ? AND slug = ? AND status = 'published'");
    $stmt->execute([$kind, $slug]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function cms_feeds(): array
{
    return [
        ['name' => 'WHO', 'url' => 'https://www.who.int/rss-feeds/news-english.xml'],
        ['name' => 'NIH', 'url' => 'https://www.nih.gov/news-events/news-releases/rss.xml'],
    ];
}

function cms_http(string $url, ?string $body = null, array $headers = [], int $timeout = 20): array
{
    $timeout = max(5, min(60, $timeout));
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'JAFARI-clinic-desk/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $retryAfter = 0;
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $header) use (&$retryAfter): int {
            if (stripos($header, 'Retry-After:') === 0) {
                $retryAfter = (int) trim(substr($header, 12));
            }
            return strlen($header);
        });
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['ok' => $raw !== false && $code > 0 && $code < 400, 'code' => $code, 'body' => is_string($raw) ? $raw : '', 'error' => $error, 'retry_after' => $retryAfter];
    }
    $context = stream_context_create([
        'http' => [
            'method' => $body === null ? 'GET' : 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => $timeout,
            'user_agent' => 'JAFARI-clinic-desk/1.0',
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    return ['ok' => is_string($raw), 'code' => is_string($raw) ? 200 : 0, 'body' => is_string($raw) ? $raw : '', 'error' => is_string($raw) ? '' : 'HTTP request failed', 'retry_after' => 0];
}

function cms_ai_base(): string
{
    $base = rtrim(cms_setting('openai_base', 'https://openrouter.ai/api/v1'), '/');
    return $base !== '' ? $base : 'https://openrouter.ai/api/v1';
}

function cms_ai_catalog(): array
{
    return [
        'google/gemma-4-26b-a4b-it:free' => 'Gemma 4 26B، رایگان',
        'google/gemma-4-31b-it:free' => 'Gemma 4 31B، رایگان',
        'nvidia/nemotron-3-ultra-550b-a55b:free' => 'Nemotron 3 Ultra، رایگان',
        'nvidia/nemotron-3.5-super-vl:free' => 'Nemotron 3.5، رایگان',
    ];
}

function cms_log_scrub(string $text): string
{
    $text = preg_replace('/sk-[A-Za-z0-9_\-.]{8,}/', '[key]', $text) ?? $text;
    $text = preg_replace('/Bearer\s+\S+/i', 'Bearer [key]', $text) ?? $text;
    return $text;
}

function cms_log(string $level, string $area, string $event, string $message, array $context = []): void
{
    static $writing = false;
    if ($writing || !in_array($level, ['info', 'warn', 'error'], true)) {
        return;
    }
    $area = preg_replace('/[^a-z0-9_-]/', '', strtolower($area)) ?: 'app';
    $event = cms_clip(preg_replace('/[^A-Za-z0-9_.:-]/', '', $event) ?: 'event', 40);
    $message = cms_clip(cms_log_scrub($message), 400);
    $safe = [];
    foreach ($context as $key => $value) {
        $name = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key));
        if ($name === '' || in_array($name, ['key', 'apikey', 'authorization', 'password', 'openai_key', 'token'], true)) {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $safe[$name] = cms_log_scrub(cms_clip((string) $value, 180));
        }
    }
    $json = json_encode($safe, JSON_UNESCAPED_UNICODE);
    $writing = true;
    try {
        $pdo = cms_db();
        $pdo->prepare('INSERT INTO system_log (level, area, event, message, context, created_at) VALUES (?,?,?,?,?,?)')->execute([
            $level,
            $area,
            $event,
            $message,
            is_string($json) ? $json : '{}',
            cms_now(),
        ]);
        $pdo->exec('DELETE FROM system_log WHERE id < (SELECT MAX(id) - 800 FROM system_log)');
    } catch (Throwable) {
    } finally {
        $writing = false;
    }
}

function cms_ai_health(): array
{
    $data = json_decode(cms_setting('ai_health'), true);
    return is_array($data) ? $data : [];
}

function cms_ai_mark(string $model, int $code, int $ms, int $cooldown): void
{
    try {
        $health = cms_ai_health();
        $row = is_array($health[$model] ?? null) ? $health[$model] : [];
        $row['code'] = $code;
        $row['ms'] = max(0, $ms);
        $row['at'] = time();
        if ($code >= 200 && $code < 300) {
            $row['fails'] = 0;
            $row['until'] = 0;
            $row['ok'] = time();
        } else {
            $row['fails'] = (int) ($row['fails'] ?? 0) + 1;
            $row['until'] = time() + max(0, $cooldown);
        }
        $health[$model] = $row;
        $json = json_encode($health, JSON_UNESCAPED_UNICODE);
        if (is_string($json)) {
            cms_set_setting('ai_health', $json);
        }
    } catch (Throwable) {
    }
}

function cms_ai_models(): array
{
    $catalog = array_keys(cms_ai_catalog());
    if (!str_contains(cms_ai_base(), 'openrouter.ai')) {
        $chosen = trim(cms_setting('openai_model', ''));
        return preg_match('#^[A-Za-z0-9_.:/-]{3,120}$#', $chosen) ? [$chosen] : $catalog;
    }
    $now = time();
    $health = cms_ai_health();
    $ready = [];
    $cooling = [];
    foreach ($catalog as $model) {
        $row = is_array($health[$model] ?? null) ? $health[$model] : [];
        $until = (int) ($row['until'] ?? 0);
        if ($until > $now) {
            $cooling[] = [$until, $model];
            continue;
        }
        $ready[] = [-(int) ($row['ok'] ?? 0), (int) ($row['fails'] ?? 0), $model];
    }
    usort($ready, static fn (array $a, array $b): int => $a <=> $b);
    usort($cooling, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
    $models = [];
    foreach ($ready as $row) {
        $models[] = $row[2];
    }
    foreach ($cooling as $row) {
        $models[] = $row[1];
    }
    return $models ?: $catalog;
}

function cms_ai_status(): array
{
    $now = time();
    $health = cms_ai_health();
    $rows = [];
    foreach (cms_ai_catalog() as $id => $label) {
        $row = is_array($health[$id] ?? null) ? $health[$id] : [];
        $until = (int) ($row['until'] ?? 0);
        if ($until > $now) {
            $minutes = max(1, (int) ceil(($until - $now) / 60));
            $state = 'محدود؛ حدود ' . $minutes . ' دقیقه دیگر دوباره امتحان می‌شود';
            $tone = 'warn';
        } elseif ((int) ($row['ok'] ?? 0) > 0) {
            $state = 'آماده؛ آخرین بار جواب داد';
            $tone = 'ok';
        } else {
            $state = 'آماده';
            $tone = 'ok';
        }
        $rows[] = ['label' => $label, 'state' => $state, 'tone' => $tone];
    }
    return $rows;
}

function cms_ai_cooldown(int $code, int $retryAfter): int
{
    if ($code === 429) {
        return min(180, max(45, $retryAfter));
    }
    if ($code === 402 || $code === 403 || $code === 404) {
        return 600;
    }
    if ($code === 400) {
        return 180;
    }
    if ($code === 502 || $code === 503 || $code === 0) {
        return 45;
    }
    return 60;
}

function cms_ai_error(int $code, string $body): string
{
    $decoded = json_decode($body, true);
    $remote = '';
    if (is_array($decoded)) {
        $remote = trim((string) ($decoded['error']['message'] ?? ''));
        $remote = trim(preg_replace('/sk-[A-Za-z0-9_-]+/', '', $remote) ?? '');
        $remote = cms_clip($remote, 160);
    }
    if ($code === 401) {
        return 'کلید OpenRouter پذیرفته نشد. در تنظیمات کلید تازه را ذخیره کنید.';
    }
    if ($code === 402) {
        return 'اعتبار این مدل تمام شده است.';
    }
    if ($code === 403) {
        return 'دسترسی این کلید به مدل بسته است.';
    }
    if ($code === 404) {
        return 'این مدل الان در دسترس نیست.';
    }
    if ($code === 429) {
        return 'محدودیت تعداد درخواست بود.';
    }
    if ($code === 502 || $code === 503 || $code === 0) {
        return 'سرویس مدل موقتاً جواب نداد.';
    }
    return $remote !== '' ? $remote : 'پاسخ مدل دریافت نشد.';
}

function cms_ai_json_text(string $content): ?array
{
    $content = trim($content);
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/su', $content, $match)) {
        $content = $match[1];
    }
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }
    $data = json_decode(substr($content, $start, $end - $start + 1), true);
    return is_array($data) ? $data : null;
}

function cms_ai_message_text(array $message): string
{
    $content = $message['content'] ?? '';
    if (is_string($content) && trim($content) !== '') {
        return $content;
    }
    if (is_array($content)) {
        $parts = [];
        foreach ($content as $part) {
            if (is_array($part) && is_string($part['text'] ?? null) && trim($part['text']) !== '') {
                $parts[] = $part['text'];
            }
        }
        if ($parts) {
            return implode("\n", $parts);
        }
    }
    foreach (['reasoning', 'reasoning_content'] as $key) {
        if (is_string($message[$key] ?? null) && trim($message[$key]) !== '') {
            return $message[$key];
        }
    }
    $details = $message['reasoning_details'] ?? null;
    if (is_string($details) && trim($details) !== '') {
        return $details;
    }
    if (is_array($details)) {
        $bits = [];
        foreach ($details as $detail) {
            if (is_string($detail) && trim($detail) !== '') {
                $bits[] = $detail;
            } elseif (is_array($detail)) {
                foreach (['text', 'content', 'summary'] as $key) {
                    if (is_string($detail[$key] ?? null) && trim($detail[$key]) !== '') {
                        $bits[] = $detail[$key];
                    }
                }
            }
        }
        if ($bits) {
            return implode("\n", $bits);
        }
    }
    return '';
}

function cms_ai_complete(array $messages, float $temperature, bool $json, int $timeout = 35, int $maxTokens = 1200): array
{
    $key = trim(cms_setting('openai_key'));
    if ($key === '') {
        cms_log('warn', 'ai', 'missing-key', 'کلید OpenRouter ذخیره نشده است', []);
        return ['ok' => false, 'data' => [], 'text' => '', 'model' => '', 'error' => 'کلید OpenRouter در تنظیمات ذخیره نشده است.'];
    }
    $url = cms_ai_base() . '/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
        'HTTP-Referer: ' . cms_origin(),
        'X-OpenRouter-Title: JAFARI',
    ];
    $models = cms_ai_models();
    $lastError = 'هیچ مدلی پاسخ نداد.';
    $skipped = [];
    foreach ($models as $index => $model) {
        $attempts = 0;
        $useJson = $json;
        $limit = $maxTokens;
        $started = microtime(true);
        while ($attempts < 3) {
            $attempts++;
            $body = [
                'model' => $model,
                'temperature' => $temperature,
                'messages' => $messages,
            ];
            if ($limit > 0) {
                $body['max_tokens'] = $limit;
            }
            if ($useJson) {
                $body['response_format'] = ['type' => 'json_object'];
            }
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
            if (!is_string($payload)) {
                $lastError = 'درخواست مدل ساخته نشد.';
                break;
            }
            $response = cms_http($url, $payload, $headers, $timeout);
            $code = (int) $response['code'];
            $ms = (int) round((microtime(true) - $started) * 1000);
            if ($code === 401) {
                cms_log('error', 'ai', 'auth', cms_ai_error(401, (string) $response['body']), ['model' => $model, 'code' => '401', 'ms' => (string) $ms]);
                return ['ok' => false, 'data' => [], 'text' => '', 'model' => $model, 'error' => cms_ai_error(401, (string) $response['body']) . ' متن شما نگه داشته شد.'];
            }
            if ($code === 400 && ($useJson || $limit > 0)) {
                if ($useJson) {
                    $useJson = false;
                } else {
                    $limit = 0;
                }
                continue;
            }
            if ($code === 429 && $attempts < 2 && $index === count($models) - 1) {
                sleep(1);
                continue;
            }
            if (!$response['ok']) {
                $lastError = cms_ai_error($code, (string) $response['body']);
                $cooldown = cms_ai_cooldown($code, (int) ($response['retry_after'] ?? 0));
                cms_ai_mark($model, $code, $ms, $cooldown);
                cms_log('warn', 'ai', 'skip', $lastError, ['model' => $model, 'code' => (string) $code, 'ms' => (string) $ms, 'cooldown' => (string) $cooldown]);
                $skipped[] = $model;
                break;
            }
            $decoded = json_decode($response['body'], true);
            $message = is_array($decoded) ? ($decoded['choices'][0]['message'] ?? null) : null;
            if (!is_array($message)) {
                $lastError = 'پاسخ مدل قابل خواندن نبود.';
                cms_ai_mark($model, 502, $ms, 45);
                cms_log('warn', 'ai', 'skip', $lastError, ['model' => $model, 'code' => (string) $code, 'ms' => (string) $ms]);
                $skipped[] = $model;
                break;
            }
            $text = cms_ai_message_text($message);
            if ($json) {
                $data = cms_ai_json_text($text);
                if (!is_array($data)) {
                    if ($useJson) {
                        $useJson = false;
                        continue;
                    }
                    $lastError = 'پاسخ این مدل ساختار لازم را نداشت.';
                    cms_ai_mark($model, 422, $ms, 60);
                    cms_log('warn', 'ai', 'skip', $lastError, ['model' => $model, 'code' => (string) $code, 'ms' => (string) $ms]);
                    $skipped[] = $model;
                    break;
                }
                cms_ai_mark($model, 200, $ms, 0);
                cms_log('info', 'ai', 'reply', 'مدل جواب داد', ['model' => $model, 'ms' => (string) $ms, 'skipped' => implode(',', $skipped)]);
                return ['ok' => true, 'data' => $data, 'text' => $text, 'model' => $model, 'error' => ''];
            }
            if (trim($text) === '') {
                $lastError = 'مدل متن خالی برگرداند.';
                cms_ai_mark($model, 422, $ms, 60);
                cms_log('warn', 'ai', 'skip', $lastError, ['model' => $model, 'ms' => (string) $ms]);
                $skipped[] = $model;
                break;
            }
            cms_ai_mark($model, 200, $ms, 0);
            cms_log('info', 'ai', 'reply', 'مدل جواب داد', ['model' => $model, 'ms' => (string) $ms]);
            return ['ok' => true, 'data' => [], 'text' => $text, 'model' => $model, 'error' => ''];
        }
    }
    cms_log('error', 'ai', 'exhausted', $lastError, ['skipped' => implode(',', $skipped)]);
    return ['ok' => false, 'data' => [], 'text' => '', 'model' => '', 'error' => $lastError . ' مدل‌های بعدی هم جواب ندادند. چند دقیقه بعد دوباره بزنید. متن شما نگه داشته شد.'];
}

function cms_read_feed(array $feed): array
{
    $response = cms_http($feed['url']);
    if (!$response['ok']) {
        return ['name' => $feed['name'], 'error' => $response['error'] ?: 'HTTP ' . $response['code'], 'items' => []];
    }
    $xml = @simplexml_load_string($response['body']);
    if ($xml === false) {
        return ['name' => $feed['name'], 'error' => 'RSS unreadable', 'items' => []];
    }
    $items = [];
    $nodes = $xml->channel->item ?? $xml->item ?? [];
    foreach ($nodes as $item) {
        $title = trim((string) $item->title);
        $link = trim((string) $item->link);
        if ($title === '' || $link === '') {
            continue;
        }
        $desc = trim(html_entity_decode(strip_tags((string) $item->description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (strlen($desc) > 600) {
            $desc = substr($desc, 0, 600);
        }
        $items[] = [
            'name' => $feed['name'],
            'title' => $title,
            'url' => $link,
            'date' => trim((string) $item->pubDate),
            'summary' => $desc,
        ];
        if (count($items) >= 8) {
            break;
        }
    }
    return ['name' => $feed['name'], 'error' => '', 'items' => $items];
}

function cms_ai_draft(array $source): array
{
    $result = cms_ai_complete([
        [
            'role' => 'system',
            'content' => 'You write original bilingual patient-education briefs for an oral and maxillofacial surgery website. Use only the supplied headline and short note. Do not invent statistics, doses, cure rates, or quotes. Do not claim the surgeon wrote the study. Say this is an educational summary of a public source and not a diagnosis or treatment plan. JSON keys: title_fa, title_en, excerpt_fa, excerpt_en, body_fa, body_en, seo_title_fa, seo_title_en, seo_description_fa, seo_description_en. Bodies are 3 or 4 short paragraphs separated by a blank line.',
        ],
        [
            'role' => 'user',
            'content' => "Source: {$source['name']}\nDate: {$source['date']}\nTitle: {$source['title']}\nNote: {$source['summary']}\nURL: {$source['url']}",
        ],
    ], 0.2, true, 30, 1600);
    return ['ok' => $result['ok'], 'error' => $result['error'], 'data' => $result['data']];
}

function cms_store_source_draft(array $source, bool $withAi): array
{
    $exists = cms_db()->prepare('SELECT id FROM posts WHERE kind = ? AND source_url = ?');
    $exists->execute(['briefing', $source['url']]);
    if ($exists->fetch()) {
        return ['created' => false, 'error' => ''];
    }
    $ai = $withAi ? cms_ai_draft($source) : ['ok' => false, 'error' => '', 'data' => []];
    $data = $ai['data'];
    $slug = cms_slug($data['title_en'] ?? '') ?: cms_slug(substr(md5($source['url']), 0, 10));
    if ($slug === '') {
        $slug = 'note-' . date('YmdHis');
    }
    $base = $slug;
    $n = 2;
    $check = cms_db()->prepare('SELECT id FROM posts WHERE kind = ? AND slug = ?');
    while (true) {
        $check->execute(['briefing', $slug]);
        if (!$check->fetch()) {
            break;
        }
        $slug = $base . '-' . $n;
        $n++;
    }
    $now = gmdate('c');
    $stmt = cms_db()->prepare('INSERT INTO posts (kind, slug, status, title_fa, title_en, excerpt_fa, excerpt_en, body_fa, body_en, seo_title_fa, seo_title_en, seo_description_fa, seo_description_en, source_name, source_url, source_date, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        'briefing',
        $slug,
        'draft',
        (string) ($data['title_fa'] ?? $source['title']),
        (string) ($data['title_en'] ?? $source['title']),
        (string) ($data['excerpt_fa'] ?? ''),
        (string) ($data['excerpt_en'] ?? ''),
        (string) ($data['body_fa'] ?? ''),
        (string) ($data['body_en'] ?? ''),
        (string) ($data['seo_title_fa'] ?? ''),
        (string) ($data['seo_title_en'] ?? ''),
        (string) ($data['seo_description_fa'] ?? ''),
        (string) ($data['seo_description_en'] ?? ''),
        $source['name'],
        $source['url'],
        $source['date'],
        $now,
        $now,
    ]);
    return ['created' => true, 'error' => $ai['error']];
}

function cms_import_feeds(bool $withAi, int $limit = 3): array
{
    $created = 0;
    $errors = [];
    foreach (cms_feeds() as $feed) {
        $parsed = cms_read_feed($feed);
        if ($parsed['error'] !== '') {
            $errors[] = $feed['name'] . ': ' . $parsed['error'];
        }
        foreach ($parsed['items'] as $item) {
            if ($created >= $limit) {
                break 2;
            }
            $result = cms_store_source_draft($item, $withAi);
            if ($result['created']) {
                $created++;
                if ($result['error'] !== '') {
                    $errors[] = $result['error'];
                }
            }
        }
    }
    return ['created' => $created, 'errors' => $errors];
}

function cms_now(): string
{
    return gmdate('c');
}

function cms_audit(string $action, string $target = '', string $detail = ''): void
{
    $user = cms_user();
    $stmt = cms_db()->prepare('INSERT INTO audit (admin_id, username, action, target, detail, ip_hash, created_at) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([
        $user['id'] ?? null,
        (string) ($user['username'] ?? ''),
        $action,
        cms_clip($target, 180),
        cms_clip($detail, 500),
        cms_ip_hash(),
        cms_now(),
    ]);
}

function cms_flash(string $tone, string $message): void
{
    cms_session();
    $_SESSION['flash'] = [$tone, $message];
}

function cms_take_flash(): array
{
    cms_session();
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : [];
}

function cms_ip_hash(): string
{
    $salt = cms_setting('ip_salt');
    if ($salt === '') {
        $salt = bin2hex(random_bytes(16));
        cms_set_setting('ip_salt', $salt);
    }
    return hash('sha256', $salt . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function cms_admin_attempt(string $username, string $password, string $guardHash): array
{
    $until = cms_login_locked_until($guardHash);
    if ($until > time()) {
        return ['ok' => false, 'locked' => true, 'error' => 'closed', 'until' => $until, 'id' => 0];
    }
    $stmt = cms_db()->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || (int) ($row['active'] ?? 1) !== 1 || !password_verify($password, (string) $row['password_hash'])) {
        $guard = cms_login_fail($guardHash);
        return ['ok' => false, 'locked' => (bool) $guard['locked'], 'error' => $guard['locked'] ? 'locked' : 'denied', 'until' => (int) $guard['until'], 'id' => 0];
    }
    cms_login_clear($guardHash);
    return ['ok' => true, 'locked' => false, 'error' => '', 'until' => 0, 'id' => (int) $row['id']];
}

function cms_login_locked_until(string $hash): int
{
    $stmt = cms_db()->prepare('SELECT lock_until FROM login_guard WHERE ip_hash = ?');
    $stmt->execute([$hash]);
    $until = (int) $stmt->fetchColumn();
    return $until > time() ? $until : 0;
}

function cms_login_fail(string $hash): array
{
    $pdo = cms_db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare('SELECT tries, lock_until FROM login_guard WHERE ip_hash = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        $now = time();
        $tries = $row ? (int) $row['tries'] : 0;
        $until = $row ? (int) $row['lock_until'] : 0;
        if ($until > $now) {
            $pdo->exec('COMMIT');
            return ['locked' => true, 'until' => $until];
        }
        $tries++;
        $locked = false;
        if ($tries >= 8) {
            $until = $now + 900;
            $tries = 0;
            $locked = true;
        }
        if ($row) {
            $pdo->prepare('UPDATE login_guard SET tries = ?, lock_until = ?, updated_at = ? WHERE ip_hash = ?')->execute([$tries, $until, cms_now(), $hash]);
        } else {
            $pdo->prepare('INSERT INTO login_guard (ip_hash, tries, lock_until, updated_at) VALUES (?,?,?,?)')->execute([$hash, $tries, $until, cms_now()]);
        }
        $pdo->exec('COMMIT');
        return ['locked' => $locked, 'until' => $until];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        throw $error;
    }
}

function cms_login_clear(string $hash): void
{
    cms_db()->prepare('DELETE FROM login_guard WHERE ip_hash = ?')->execute([$hash]);
}

function cms_plain(string $value, int $max = 4000): string
{
    $value = trim(preg_replace('/<\/?[A-Za-z][^>]*>/', '', $value) ?? $value);
    return cms_clip($value, $max);
}

function cms_https_url(string $value): string
{
    $value = trim($value);
    if (!preg_match('#^https://#i', $value)) {
        return '';
    }
    return filter_var($value, FILTER_VALIDATE_URL) ? $value : '';
}

function cms_media_path(string $value): string
{
    $value = trim($value);
    if (!preg_match('#^/(?:visuals|cms/media)/[A-Za-z0-9._/-]+$#', $value) || str_contains($value, '..')) {
        return '';
    }
    return $value;
}

function cms_local_path(string $value): string
{
    $value = trim($value);
    if (!preg_match('#^/(fa|en|ar|zh)/[a-z0-9/_-]*$#', $value)) {
        return '';
    }
    return str_ends_with($value, '/') ? $value : $value . '/';
}

function cms_slot_map(string $locale): array
{
    $stmt = cms_db()->prepare('SELECT slot_key, value FROM slots WHERE locale = ? AND value <> \'\'');
    $stmt->execute([cms_locale($locale)]);
    $map = [];
    foreach ($stmt as $row) {
        $map[$row['slot_key']] = $row['value'];
    }
    return $map;
}

function cms_seo_map(string $locale): array
{
    $locale = cms_locale($locale);
    $stmt = cms_db()->prepare('SELECT path, title, description, robots, og_image FROM seo WHERE locale = ?');
    $stmt->execute([$locale]);
    $map = [];
    foreach ($stmt as $row) {
        $path = '/' . $locale . ($row['path'] === '/' ? '/' : $row['path']);
        $map[$path] = [
            'title' => $row['title'],
            'description' => $row['description'],
            'robots' => $row['robots'],
            'og' => $row['og_image'],
        ];
    }
    return $map;
}

function cms_own(array $row, string $base, string $locale): string
{
    return trim((string) ($row[$base . '_' . $locale] ?? ''));
}

function cms_rich(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return '';
    }
    $html = '';
    $para = [];
    $list = [];
    $flushPara = static function () use (&$para, &$html): void {
        if (!$para) {
            return;
        }
        $html .= '<p>' . cms_inline(implode(' ', $para)) . '</p>';
        $para = [];
    };
    $flushList = static function () use (&$list, &$html): void {
        if (!$list) {
            return;
        }
        $html .= '<ul>';
        foreach ($list as $item) {
            $html .= '<li>' . cms_inline($item) . '</li>';
        }
        $html .= '</ul>';
        $list = [];
    };
    foreach (explode("\n", $text) as $line) {
        $trim = trim($line);
        if ($trim === '') {
            $flushPara();
            $flushList();
            continue;
        }
        if (str_starts_with($trim, '### ')) {
            $flushPara();
            $flushList();
            $html .= '<h3>' . cms_inline(substr($trim, 4)) . '</h3>';
            continue;
        }
        if (str_starts_with($trim, '## ')) {
            $flushPara();
            $flushList();
            $html .= '<h2>' . cms_inline(substr($trim, 3)) . '</h2>';
            continue;
        }
        if (str_starts_with($trim, '> ')) {
            $flushPara();
            $flushList();
            $html .= '<blockquote><p>' . cms_inline(substr($trim, 2)) . '</p></blockquote>';
            continue;
        }
        if (str_starts_with($trim, '- ')) {
            $flushPara();
            $list[] = substr($trim, 2);
            continue;
        }
        $flushList();
        $para[] = $trim;
    }
    $flushPara();
    $flushList();
    return $html;
}

function cms_inline(string $text): string
{
    $safe = e($text);
    $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe) ?? $safe;
    $safe = preg_replace_callback(
        '/\[([^\]<]+)\]\(((?:https?:\/\/[^\s)<]+)|(?:\/(?:fa|en|ar|zh)\/[a-z0-9\/-]+))\)/',
        static function (array $match): string {
            $raw = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
            if (preg_match('#^/(?:fa|en|ar|zh)/[a-z0-9/-]+$#', $raw)) {
                return '<a href="' . e($raw) . '">' . $match[1] . '</a>';
            }
            $url = filter_var($raw, FILTER_VALIDATE_URL);
            if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
                return $match[0];
            }
            return '<a href="' . e($url) . '" rel="noopener noreferrer">' . $match[1] . '</a>';
        },
        $safe
    ) ?? $safe;
    return $safe;
}

function cms_minutes(string $text): int
{
    $parts = preg_split('/\s+/u', trim($text)) ?: [];
    $count = count(array_filter($parts, static fn ($part) => $part !== ''));
    return max(1, (int) ceil($count / 180));
}

function cms_feeds_view(bool $refresh): array
{
    if (!$refresh) {
        $cached = json_decode(cms_setting('feed_cache'), true);
        if (is_array($cached) && (int) ($cached['at'] ?? 0) > time() - 900 && is_array($cached['feeds'] ?? null)) {
            return $cached['feeds'];
        }
    }
    $feeds = [];
    foreach (cms_feeds() as $feed) {
        $feeds[] = cms_read_feed($feed);
    }
    cms_set_setting('feed_cache', json_encode(['at' => time(), 'feeds' => $feeds], JSON_UNESCAPED_UNICODE));
    return $feeds;
}

function cms_apply_slots(string $html, array $slots): string
{
    $offset = 0;
    while (preg_match('/\sdata-cms="([a-z0-9._-]+)"/', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $key = $match[1][0];
        $attrAt = $match[0][1];
        if (!isset($slots[$key]) || !is_string($slots[$key])) {
            $offset = $attrAt + strlen($match[0][0]);
            continue;
        }
        $tagStart = strrpos(substr($html, 0, $attrAt), '<');
        $openEnd = strpos($html, '>', $attrAt);
        if ($tagStart === false || $openEnd === false) {
            break;
        }
        $open = substr($html, $tagStart, $openEnd - $tagStart + 1);
        if (!preg_match('/^<([a-z0-9]+)/i', $open, $name) || preg_match('/<(?:img|input|br|hr|meta|link|source)\b/i', $open)) {
            $offset = $openEnd + 1;
            continue;
        }
        $close = '</' . strtolower($name[1]) . '>';
        $closeAt = stripos($html, $close, $openEnd);
        if ($closeAt === false) {
            break;
        }
        $inner = e($slots[$key]);
        $html = substr($html, 0, $openEnd + 1) . $inner . substr($html, $closeAt);
        $offset = $openEnd + 1 + strlen($inner);
    }
    return $html;
}

function cms_safe_href(string $value): string
{
    $value = trim($value);
    if (preg_match('#^http://#i', $value)) {
        $value = 'https://' . substr($value, 7);
    }
    if (preg_match('#^https://[^\s"\'<>]+$#u', $value)) {
        return $value;
    }
    return '';
}

function cms_apply_hrefs(string $html, array $slots): string
{
    return preg_replace_callback('/<a\b[^>]*>/i', static function (array $match) use ($slots): string {
        $tag = $match[0];
        if (!preg_match('/data-cms-href="([a-z0-9._-]+)"/', $tag, $key)) {
            return $tag;
        }
        $href = cms_safe_href((string) ($slots[$key[1]] ?? ''));
        if ($href === '' || !preg_match('/\shref="/', $tag)) {
            return $tag;
        }
        $tag = preg_replace('/\shref="[^"]*"/', ' href="' . e($href) . '"', $tag, 1) ?? $tag;
        return preg_replace('/\s+hidden(?:=(?:"[^"]*"|\'[^\']*\'))?/i', '', $tag, 1) ?? $tag;
    }, $html) ?? $html;
}

function cms_apply_mails(string $html, array $slots): string
{
    return preg_replace_callback('/<a\b[^>]*>/i', static function (array $match) use ($slots): string {
        $tag = $match[0];
        if (!preg_match('/data-cms-mail="([a-z0-9._-]+)"/', $tag, $key)) {
            return $tag;
        }
        $mail = trim((string) ($slots[$key[1]] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $mail) || !preg_match('/\shref="/', $tag)) {
            return $tag;
        }
        return preg_replace('/\shref="[^"]*"/', ' href="mailto:' . e($mail) . '"', $tag, 1) ?? $tag;
    }, $html) ?? $html;
}

function cms_apply_images(string $html, array $slots): string
{
    return preg_replace_callback('/<img\b[^>]*>/i', static function (array $match) use ($slots): string {
        $tag = $match[0];
        if (!preg_match('/data-cms-src="([a-z0-9._-]+)"/', $tag, $key)) {
            return $tag;
        }
        $path = cms_media_path($slots[$key[1]] ?? '');
        if ($path === '') {
            return $tag;
        }
        $safe = e($path);
        if (preg_match('/\ssrc="/', $tag)) {
            $tag = preg_replace('/\ssrc="[^"]*"/', ' src="' . $safe . '"', $tag, 1) ?? $tag;
        }
        $tag = preg_replace('/\ssrcset="[^"]*"/', '', $tag) ?? $tag;
        return $tag;
    }, $html) ?? $html;
}

function cms_clinic_slots(string $locale): array
{
    $packed = ['text' => [], 'images' => [], 'links' => []];
    if (!cms_installed()) {
        return $packed;
    }
    try {
        $map = cms_slot_map($locale);
    } catch (Throwable) {
        return $packed;
    }
    foreach (['doctor.name', 'common.specialty', 'common.medicalCodeLabel', 'clinic.address', 'clinic.phone', 'clinic.phoneAlt', 'clinic.medicalCode'] as $key) {
        $value = trim((string) ($map[$key] ?? ''));
        if ($value !== '') {
            $packed['text'][$key] = $value;
        }
    }
    $instagram = cms_safe_href((string) ($map['clinic.instagramUrl'] ?? ''));
    if ($instagram !== '') {
        $packed['links']['clinic.instagramUrl'] = $instagram;
    }
    $portrait = cms_media_path((string) ($map['visual.doctor'] ?? ''));
    if ($portrait !== '') {
        $packed['images']['visual.doctor'] = $portrait;
    }
    return $packed;
}

function cms_clinic_graph(string $locale, array $text, array $images, array $links): array
{
    $locale = cms_locale($locale);
    $pick = static function (string $key, string $fallback) use ($text): string {
        $value = trim((string) ($text[$key] ?? ''));
        return $value !== '' ? $value : $fallback;
    };
    $doctorName = $pick('doctor.name', cms_line($locale, 'دکتر سید مهرشاد جعفری', 'Dr. Seyed Mehrshad Jafari', 'الدكتور سيد مهرشاد جعفري', '赛义德·梅尔沙德·贾法里医生'));
    $clinicName = $doctorName;
    $specialty = $pick('common.specialty', cms_line($locale, 'متخصص جراحی دهان، فک و صورت', 'Oral & Maxillofacial Surgeon', 'اختصاصي جراحة الفم والفك والوجه', '口腔颌面外科医师'));
    $street = $pick('clinic.address', cms_line(
        $locale,
        'تهران، پاسداران، بوستان ششم، جنب مدرسه خدیجه کبری، پلاک ۱۳۵، طبقه ۲، واحد ۸',
        'Tehran, Pasdaran, Boostan 6th, next to Khadijah School, No. 135, 2nd floor, Unit 8',
        'طهران، باسداران، بستان السادس، جوار مدرسة خديجة الكبرى، رقم ۱۳۵، الطابق ۲، وحدة ۸',
        '德黑兰，帕斯达兰，第六花园，哈蒂彻学校旁，135号，2层，8单元'
    ));
    $city = cms_line($locale, 'تهران', 'Tehran', 'طهران', '德黑兰');
    $region = '';
    $postal = '';
    $country = 'IR';
    $hours = '';
    $lat = '';
    $lng = '';
    $telephones = [];
    foreach ([$pick('clinic.phone', '021-22579316'), $pick('clinic.phoneAlt', '021-22579315')] as $number) {
        if ($number !== '' && !in_array($number, $telephones, true)) {
            $telephones[] = $number;
        }
    }
    if (cms_installed() && cms_setting('geo_confirmed') === '1') {
        $geoStreet = trim(cms_setting('geo_street'));
        $geoCity = trim(cms_setting('geo_city'));
        $geoPhone = trim(cms_setting('geo_phone'));
        if ($geoStreet !== '' && $geoCity !== '' && $geoPhone !== '') {
            $street = $geoStreet;
            $city = $geoCity;
            $region = trim(cms_setting('geo_region'));
            $postal = trim(cms_setting('geo_postal'));
            $countrySetting = trim(cms_setting('geo_country', 'IR'));
            if ($countrySetting !== '') {
                $country = $countrySetting;
            }
            $geoName = trim(cms_setting('geo_name'));
            if ($geoName !== '') {
                $clinicName = $geoName;
            }
            $hours = trim(cms_setting('geo_hours'));
            $lat = trim(cms_setting('geo_lat'));
            $lng = trim(cms_setting('geo_lng'));
            if (!in_array($geoPhone, $telephones, true)) {
                array_unshift($telephones, $geoPhone);
            }
        }
    }
    $imagePath = cms_media_path((string) ($images['visual.doctor'] ?? ''));
    if ($imagePath === '') {
        $imagePath = '/visuals/doctor-portrait.webp';
    }
    $instagram = cms_safe_href((string) ($links['clinic.instagramUrl'] ?? ''));
    if ($instagram === '') {
        $instagram = 'https://www.instagram.com/dr.s.m.jafari/';
    }
    $home = cms_origin() . '/' . $locale . '/';
    $physician = $home . '#physician';
    $practice = $home . '#clinic';
    $image = cms_origin() . $imagePath;
    $address = [
        '@type' => 'PostalAddress',
        'streetAddress' => $street,
        'addressLocality' => $city,
        'addressCountry' => $country,
    ];
    if ($region !== '') {
        $address['addressRegion'] = $region;
    }
    if ($postal !== '') {
        $address['postalCode'] = $postal;
    }
    $clinic = [
        '@type' => 'MedicalClinic',
        '@id' => $practice,
        'name' => $clinicName,
        'url' => $home,
        'image' => $image,
        'medicalSpecialty' => 'https://schema.org/OralSurgery',
        'address' => $address,
        'telephone' => $telephones,
        'employee' => ['@id' => $physician],
    ];
    if ($hours !== '') {
        $clinic['openingHours'] = $hours;
    }
    if (is_numeric($lat) && is_numeric($lng) && (float) $lat >= -90 && (float) $lat <= 90 && (float) $lng >= -180 && (float) $lng <= 180) {
        $clinic['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
        ];
    }
    return [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Physician',
                '@id' => $physician,
                'name' => $doctorName,
                'description' => $specialty,
                'medicalSpecialty' => ['https://schema.org/OralSurgery', $specialty],
                'identifier' => [
                    '@type' => 'PropertyValue',
                    'name' => $pick('common.medicalCodeLabel', cms_line($locale, 'شماره نظام پزشکی', 'Medical council ID', 'رقم النقابة', '医师注册号')),
                    'value' => $pick('clinic.medicalCode', '141130'),
                ],
                'image' => $image,
                'url' => $home,
                'sameAs' => [$instagram],
                'worksFor' => ['@id' => $practice],
            ],
            $clinic,
        ],
    ];
}

function cms_clinic_script(string $locale, ?array $text = null, ?array $images = null, ?array $links = null): string
{
    if ($text === null || $images === null || $links === null) {
        $slots = cms_clinic_slots($locale);
        $text = $slots['text'];
        $images = $slots['images'];
        $links = $slots['links'];
    }
    $json = json_encode(
        cms_clinic_graph($locale, $text, $images, $links),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    return '<script id="jafari-clinic" type="application/ld+json">' . $json . '</script>';
}

function cms_put_clinic_schema(string $html, string $locale, array $text, array $images, array $links): string
{
    $script = cms_clinic_script($locale, $text, $images, $links);
    $replaced = preg_replace('/<script id="jafari-clinic" type="application\/ld\+json">.*?<\/script>/s', $script, $html, 1, $count);
    if (is_string($replaced) && $count > 0) {
        return $replaced;
    }
    return str_replace('</head>', $script . '</head>', $html);
}

function cms_geo_block(): string
{
    if (!cms_installed() || cms_setting('geo_confirmed') !== '1') {
        return '';
    }
    $street = cms_setting('geo_street');
    $city = cms_setting('geo_city');
    $phone = cms_setting('geo_phone');
    if ($street === '' || $city === '' || $phone === '') {
        return '';
    }
    $html = '';
    $region = cms_setting('geo_region');
    if ($region !== '') {
        $html .= '<meta name="geo.region" content="' . e($region) . '">';
    }
    $html .= '<meta name="geo.placename" content="' . e($city) . '">';
    $lat = cms_setting('geo_lat');
    $lng = cms_setting('geo_lng');
    if (is_numeric($lat) && is_numeric($lng)) {
        $html .= '<meta name="geo.position" content="' . e($lat . ';' . $lng) . '">';
        $html .= '<meta name="ICBM" content="' . e($lat . ', ' . $lng) . '">';
    }
    return $html;
}

function cms_webp_ready(): bool
{
    return function_exists('imagewebp') && function_exists('imagecreatefromstring') && function_exists('imagecreatetruecolor');
}

function cms_kb(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return (string) max(1, (int) round($bytes / 1024)) . ' KB';
    }
    return number_format($bytes / 1048576, 1) . ' MB';
}

function cms_uploaded_list(string $key): array
{
    $file = $_FILES[$key] ?? null;
    if (!is_array($file) || !isset($file['name'])) {
        return [];
    }
    if (!is_array($file['name'])) {
        return [[
            'name' => (string) $file['name'],
            'tmp_name' => (string) $file['tmp_name'],
            'error' => (int) $file['error'],
            'size' => (int) $file['size'],
        ]];
    }
    $list = [];
    foreach ($file['name'] as $index => $name) {
        $list[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($file['tmp_name'][$index] ?? ''),
            'error' => (int) ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($file['size'][$index] ?? 0),
        ];
    }
    return $list;
}

function cms_make_webp(string $source, string $dest): array
{
    if (!cms_webp_ready()) {
        throw new RuntimeException('افزونه gd با پشتیبانی WebP روی این PHP روشن نیست. در cPanel بخش Select PHP Version گزینه gd را فعال کنید.');
    }
    $info = @getimagesize($source);
    if (!is_array($info)) {
        throw new RuntimeException('این فایل تصویر قابل خواندن نیست.');
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    $mime = (string) ($info['mime'] ?? '');
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('فقط JPG، PNG و WebP پذیرفته می‌شود. عکس HEIC گوشی را اول به JPG تبدیل کنید.');
    }
    if ($width < 2 || $height < 2 || $width * $height > 24000000) {
        throw new RuntimeException('اندازه تصویر خارج از حد مجاز است.');
    }
    $raw = file_get_contents($source);
    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException('خواندن فایل ممکن نشد.');
    }
    $src = @imagecreatefromstring($raw);
    if ($src === false) {
        throw new RuntimeException('تبدیل تصویر ممکن نشد.');
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data') && function_exists('imagerotate')) {
        $exif = @exif_read_data($source);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($src, 180, 0),
            6 => imagerotate($src, -90, 0),
            8 => imagerotate($src, 90, 0),
            default => $src,
        };
        if ($rotated !== false && $rotated !== $src) {
            imagedestroy($src);
            $src = $rotated;
        }
    }
    if (function_exists('imagepalettetotruecolor')) {
        imagepalettetotruecolor($src);
    }
    $width = imagesx($src);
    $height = imagesy($src);
    $edge = max($width, $height);
    $limit = 2000;
    $scale = $edge > $limit ? $limit / $edge : 1;
    $targetW = max(1, (int) round($width * $scale));
    $targetH = max(1, (int) round($height * $scale));
    $dst = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $clear);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
    imagedestroy($src);
    $dir = dirname($dest);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($dst);
        throw new RuntimeException('پوشه تصویر ساخته نشد.');
    }
    if (!imagewebp($dst, $dest, 78)) {
        imagedestroy($dst);
        throw new RuntimeException('ذخیره WebP ممکن نشد.');
    }
    clearstatcache(true, $dest);
    $bytes = (int) filesize($dest);
    if ($bytes > 700 * 1024) {
        imagewebp($dst, $dest, 64);
        clearstatcache(true, $dest);
        $bytes = (int) filesize($dest);
    }
    imagedestroy($dst);
    if ($bytes < 1) {
        throw new RuntimeException('فایل WebP خالی ماند.');
    }
    return ['width' => $targetW, 'height' => $targetH, 'bytes' => $bytes];
}

function cms_media_used(string $path): bool
{
    $slots = cms_db()->prepare('SELECT COUNT(*) FROM slots WHERE value = ?');
    $slots->execute([$path]);
    $covers = cms_db()->prepare('SELECT COUNT(*) FROM posts WHERE cover = ?');
    $covers->execute([$path]);
    $seo = cms_db()->prepare('SELECT COUNT(*) FROM seo WHERE og_image = ?');
    $seo->execute([$path]);
    return (int) $slots->fetchColumn() > 0 || (int) $covers->fetchColumn() > 0 || (int) $seo->fetchColumn() > 0;
}

function cms_retarget_media(string $from, string $to, int $bytes, int $width, int $height): void
{
    cms_db()->prepare('UPDATE slots SET value = ? WHERE value = ?')->execute([$to, $from]);
    cms_db()->prepare('UPDATE posts SET cover = ? WHERE cover = ?')->execute([$to, $from]);
    cms_db()->prepare('UPDATE seo SET og_image = ? WHERE og_image = ?')->execute([$to, $from]);
    cms_db()->prepare('UPDATE media SET path = ?, bytes = ?, width = ?, height = ? WHERE path = ?')->execute([$to, $bytes, $width, $height, $from]);
}

function cms_google_token(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/content\s*=\s*["\']([A-Za-z0-9_-]{8,100})["\']/', $raw, $match)) {
        return $match[1];
    }
    if (preg_match('/^[A-Za-z0-9_-]{8,100}$/', $raw)) {
        return $raw;
    }
    return '';
}

function cms_google_meta(): string
{
    try {
        if (!cms_installed()) {
            return '';
        }
        $token = cms_google_token(cms_setting('google_verify'));
    } catch (Throwable) {
        return '';
    }
    if ($token === '') {
        return '';
    }
    return '<meta name="google-site-verification" content="' . e($token) . '">';
}

set_exception_handler(static function (Throwable $error): void {
    $frames = [];
    foreach (array_slice($error->getTrace(), 0, 4) as $frame) {
        $frames[] = basename((string) ($frame['file'] ?? '')) . ':' . (int) ($frame['line'] ?? 0);
    }
    cms_log('error', 'php', 'exception', $error->getMessage(), [
        'type' => $error::class,
        'file' => basename($error->getFile()),
        'line' => (string) $error->getLine(),
        'trace' => implode(' < ', $frames),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $message = $error instanceof RuntimeException ? $error->getMessage() : '';
    if (str_contains($error->getMessage(), 'mb_')) {
        $message = 'افزونه mbstring در PHP خاموش است. در Select PHP Version آن را روشن کنید.';
    } elseif (!preg_match('/\p{Arabic}/u', $message)) {
        $message = 'پایگاه میز باز نشد. در Select PHP Version گزینه‌های pdo_sqlite و sqlite3 را روشن کنید و پوشه cms/data را قابل نوشتن بگذارید. ساخت دیتابیس MySQL لازم نیست.';
    }
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>میز مطب</title><body style="font-family:Tahoma,sans-serif;line-height:1.9;padding:2rem"><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
});

cms_csp_send();
