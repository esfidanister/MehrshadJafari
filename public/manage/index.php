<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cms/bootstrap.php';
require dirname(__DIR__) . '/cms/registry.php';
require dirname(__DIR__) . '/cms/studio.php';
require __DIR__ . '/ui.php';
require __DIR__ . '/reserve.php';

cms_session();

function desk_owner_count(): int
{
    return (int) cms_db()->query("SELECT COUNT(*) FROM admins WHERE role = 'owner' AND active = 1")->fetchColumn();
}

function desk_require_user(): array
{
    $user = cms_user();
    if (!$user) {
        desk_auth('login');
        exit;
    }
    return $user;
}

function desk_services(): array
{
    return [
        'wisdom-tooth-surgery' => 'دندان عقل',
        'dental-implants' => 'ایمپلنت',
        'orthognathic-surgery' => 'ارتوگناتیک',
        'oral-pathology' => 'پاتولوژی دهان',
        'bone-graft-sinus-lift' => 'پیوند استخوان',
        'all-on-4' => 'ایمپلنت تمام فک',
        'facial-fractures' => 'شکستگی',
        'tmj' => 'مفصل فک',
        'cosmetic-jaw' => 'فرم فک و چانه',
        'unsure' => 'معاینه',
    ];
}

function desk_lead_label(string $status): string
{
    return [
        'new' => 'تازه',
        'read' => 'خوانده‌شده',
        'contacted' => 'تماس گرفته شد',
        'booked' => 'نوبت شد',
        'closed' => 'بسته',
    ][$status] ?? $status;
}

function desk_action_label(string $action): string
{
    return [
        'install' => 'ساخت میز',
        'login' => 'ورود',
        'login-failed' => 'ورود ناموفق',
        'login-locked' => 'قفل ورود',
        'logout' => 'خروج',
        'content' => 'ذخیره متن صفحه',
        'media-upload' => 'افزودن عکس',
        'media-save' => 'ذخیره شرح عکس',
        'media-copy' => 'نسخه تازه عکس',
        'media-delete' => 'حذف عکس',
        'publish' => 'انتشار مطلب',
        'save-draft' => 'ذخیره پیش‌نویس',
        'delete-post' => 'حذف مطلب',
        'lead' => 'پیگیری درخواست',
        'seo' => 'ذخیره سئو',
        'geo' => 'موقعیت مطب',
        'user-create' => 'کاربر تازه',
        'user-update' => 'ویرایش کاربر',
        'account' => 'حساب',
        'settings' => 'تنظیمات',
        'google' => 'کد گوگل',
        'import' => 'ورود منابع',
        'import-one' => 'پیش‌نویس از منبع',
        'studio' => 'پیش‌نویس مقاله',
        'backup' => 'نسخه پشتیبان',
    ][$action] ?? $action;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'install') {
        if (cms_installed()) {
            desk_redirect();
        }
        cms_check_csrf();
        $blocked = cms_host_blocked();
        if ($blocked !== '') {
            desk_auth('install', $blocked);
            exit;
        }
        $installToken = trim((string) ($_POST['install_token'] ?? ''));
        if ($installToken === '') {
            $installToken = trim((string) ($_SESSION['install_token'] ?? ''));
        }
        if (!cms_install_secret_ok($installToken)) {
            desk_auth('install', 'صفحهٔ ساخت را یک بار تازه کنید و دوباره نام کاربری و رمز را بگذارید.');
            exit;
        }
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $again = (string) ($_POST['password2'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username) || strlen($password) < 10 || $password !== $again) {
            desk_auth('install', 'نام کاربری انگلیسی ۳ تا ۴۰ نویسه و رمز حداقل ۱۰ نویسه، با تکرار یکسان.');
            exit;
        }
        $pdo = cms_db();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) {
                $pdo->exec('ROLLBACK');
                desk_redirect();
            }
            $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, created_at, role, display_name, active) VALUES (?,?,?,?,?,1)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), cms_now(), 'owner', $username]);
            $id = (int) $pdo->lastInsertId();
            $pdo->exec('COMMIT');
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }
            desk_auth('install', 'ساخت میز انجام نشد. صفحه را یک بار تازه کنید و دوباره بسازید.');
            exit;
        }
        cms_set_setting('cron_token', bin2hex(random_bytes(16)));
        cms_set_setting('openai_base', 'https://openrouter.ai/api/v1');
        cms_set_setting('openai_model', 'google/gemma-4-26b-a4b-it:free');
        cms_set_setting('ip_salt', bin2hex(random_bytes(16)));
        $secretRemoved = cms_install_secret_remove();
        unset($_SESSION['install_token']);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $id;
        cms_audit('install', $username, '');
        cms_flash('ok', $secretRemoved ? 'میز ساخته شد و وارد شدید.' : 'میز ساخته شد و وارد شدید. فایل install.token را خودتان پاک کنید.');
        desk_redirect();
    }

    if ($action === 'login') {
        cms_check_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $result = cms_admin_attempt($username, (string) ($_POST['password'] ?? ''), cms_ip_hash());
        if (!$result['ok']) {
            if ($result['error'] === 'closed') {
                $mins = max(1, (int) ceil(((int) $result['until'] - time()) / 60));
                desk_auth('login', 'ورود از این شبکه تا ' . $mins . ' دقیقه دیگر بسته است.');
                exit;
            }
            if ($result['error'] === 'locked') {
                cms_audit('login-locked', $username, '');
                desk_auth('login', 'هشت تلاش ناموفق بود. ورود از این شبکه ۱۵ دقیقه بسته است.');
                exit;
            }
            cms_audit('login-failed', $username, '');
            desk_auth('login', 'نام کاربری یا رمز نادرست است.');
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $result['id'];
        unset($_SESSION['tries'], $_SESSION['lock_until']);
        cms_db()->prepare('UPDATE admins SET last_login = ? WHERE id = ?')->execute([cms_now(), (int) $result['id']]);
        cms_audit('login', $username, '');
        desk_redirect();
    }

    cms_check_csrf();
    $user = desk_require_user();
    $owner = $user['role'] === 'owner';
    if (desk_reserve_handle($action, $user, $owner)) {
        exit;
    }

    if ($action === 'logout') {
        cms_audit('logout', (string) $user['username'], '');
        $_SESSION = [];
        session_destroy();
        desk_redirect();
    }

    if ($action === 'save-content') {
        $group = (string) ($_POST['group'] ?? '');
        $known = [];
        foreach (cms_registry() as $row) {
            if ($row['group'] === $group) {
                $known[$row['key']] = $row;
            }
        }
        if (!$known) {
            cms_flash('bad', 'این بخش پیدا نشد.');
            desk_redirect('page=content');
        }
        $db = cms_db();
        $db->beginTransaction();
        $upsert = $db->prepare('INSERT OR REPLACE INTO slots (slot_key, locale, value, updated_at) VALUES (?,?,?,?)');
        $delete = $db->prepare('DELETE FROM slots WHERE slot_key = ? AND locale = ?');
        foreach ($known as $key => $row) {
            foreach (['fa', 'en'] as $locale) {
                $raw = (string) ($_POST[$locale][$key] ?? '');
                if ($row['type'] === 'image') {
                    if (trim($raw) === '') {
                        $delete->execute([$key, $locale]);
                        continue;
                    }
                    $value = cms_media_path($raw);
                    if ($value === '') {
                        $db->rollBack();
                        cms_flash('bad', 'مسیر تصویر معتبر نیست: ' . $key);
                        desk_redirect('page=content&group=' . rawurlencode($group));
                    }
                } elseif ($row['type'] === 'link') {
                    if (trim($raw) === '') {
                        $delete->execute([$key, $locale]);
                        continue;
                    }
                    $value = cms_safe_href($raw);
                    if ($value === '') {
                        $db->rollBack();
                        cms_flash('bad', 'لینک باید با https شروع شود: ' . $key);
                        desk_redirect('page=content&group=' . rawurlencode($group));
                    }
                } else {
                    $value = cms_plain($raw, 5000);
                    if ($value === '') {
                        $delete->execute([$key, $locale]);
                        continue;
                    }
                }
                $upsert->execute([$key, $locale, $value, cms_now()]);
            }
        }
        $db->commit();
        cms_audit('content', $group, '');
        cms_flash('ok', 'متن این بخش ذخیره شد. صفحه سایت را یک‌بار تازه کنید.');
        desk_redirect('page=content&group=' . rawurlencode($group));
    }

    if ($action === 'upload') {
        if (!cms_webp_ready()) {
            cms_flash('bad', 'افزونه gd با WebP روشن نیست. در cPanel بخش Select PHP Version گزینه gd را فعال کنید.');
            desk_redirect('page=media');
        }
        $files = array_slice(cms_uploaded_list('files'), 0, 12);
        if (!$files) {
            cms_flash('bad', 'عکسی انتخاب نشد.');
            desk_redirect('page=media');
        }
        $show = isset($_POST['show_gallery']) ? 1 : 0;
        $altFa = cms_plain((string) ($_POST['alt_fa'] ?? ''), 180);
        $altEn = cms_plain((string) ($_POST['alt_en'] ?? ''), 180);
        $saved = 0;
        $errors = [];
        $insert = cms_db()->prepare('INSERT INTO media (path, original_name, alt_fa, alt_en, bytes, created_at, width, height, source_bytes, show_gallery, caption_fa, caption_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($files as $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'یکی از فایل‌ها بزرگ‌تر از حد سرور بود.';
                continue;
            }
            if ($file['size'] > 12 * 1024 * 1024) {
                $errors[] = cms_plain($file['name'], 40) . ' بزرگ‌تر از ۱۲ مگابایت است.';
                continue;
            }
            $name = bin2hex(random_bytes(8)) . '.webp';
            $dest = dirname(__DIR__) . '/cms/media/' . $name;
            try {
                $made = cms_make_webp($file['tmp_name'], $dest);
            } catch (RuntimeException $exception) {
                if (is_file($dest)) {
                    unlink($dest);
                }
                $errors[] = cms_plain($file['name'], 40) . ': ' . $exception->getMessage();
                continue;
            }
            $path = '/cms/media/' . $name;
            $insert->execute([
                $path,
                cms_plain($file['name'], 120),
                $altFa,
                $altEn,
                $made['bytes'],
                cms_now(),
                $made['width'],
                $made['height'],
                $file['size'],
                $show,
                '',
                '',
            ]);
            $saved++;
            cms_audit('media-upload', $path, cms_kb($file['size']) . ' -> ' . cms_kb($made['bytes']));
        }
        if ($saved === 0) {
            cms_flash('bad', $errors ? implode(' ', array_slice($errors, 0, 3)) : 'عکسی ذخیره نشد.');
        } else {
            cms_flash('ok', $saved . ' عکس به WebP کم‌حجم تبدیل شد.' . ($errors ? ' ' . implode(' ', array_slice($errors, 0, 2)) : ''));
        }
        desk_redirect('page=media');
    }

    if ($action === 'save-media') {
        $id = (int) ($_POST['id'] ?? 0);
        cms_db()->prepare('UPDATE media SET alt_fa = ?, alt_en = ?, caption_fa = ?, caption_en = ?, show_gallery = ? WHERE id = ?')->execute([
            cms_plain((string) ($_POST['alt_fa'] ?? ''), 180),
            cms_plain((string) ($_POST['alt_en'] ?? ''), 180),
            cms_plain((string) ($_POST['caption_fa'] ?? ''), 240),
            cms_plain((string) ($_POST['caption_en'] ?? ''), 240),
            isset($_POST['show_gallery']) ? 1 : 0,
            $id,
        ]);
        cms_audit('media-save', '#' . $id, '');
        cms_flash('ok', 'شرح تصویر ذخیره شد.');
        desk_redirect('page=media');
    }

    if ($action === 'copy-media') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = cms_db()->prepare('SELECT * FROM media WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $safe = cms_media_path((string) ($row['path'] ?? ''));
        $full = $safe !== '' ? dirname(__DIR__) . $safe : '';
        if (!$row || !is_file($full)) {
            cms_flash('bad', 'این تصویر پیدا نشد.');
            desk_redirect('page=media');
        }
        $name = bin2hex(random_bytes(8)) . '.webp';
        $dest = dirname(__DIR__) . '/cms/media/' . $name;
        if (!str_ends_with(strtolower($safe), '.webp') || !copy($full, $dest)) {
            try {
                $made = cms_make_webp($full, $dest);
            } catch (RuntimeException $exception) {
                cms_flash('bad', $exception->getMessage());
                desk_redirect('page=media');
            }
        } else {
            $made = [
                'bytes' => (int) filesize($dest),
                'width' => (int) ($row['width'] ?? 0),
                'height' => (int) ($row['height'] ?? 0),
            ];
        }
        cms_db()->prepare('INSERT INTO media (path, original_name, alt_fa, alt_en, bytes, created_at, width, height, source_bytes, show_gallery, caption_fa, caption_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
            '/cms/media/' . $name,
            cms_plain((string) $row['original_name'], 120),
            (string) $row['alt_fa'],
            (string) $row['alt_en'],
            $made['bytes'],
            cms_now(),
            $made['width'],
            $made['height'],
            (int) ($row['source_bytes'] ?? $row['bytes']),
            0,
            (string) ($row['caption_fa'] ?? ''),
            (string) ($row['caption_en'] ?? ''),
        ]);
        cms_audit('media-copy', '/cms/media/' . $name, '');
        cms_flash('ok', 'یک نسخه تازه ساخته شد و هنوز در گالری سایت نیست.');
        desk_redirect('page=media');
    }

    if ($action === 'delete-media') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = cms_db()->prepare('SELECT path FROM media WHERE id = ?');
        $stmt->execute([$id]);
        $path = (string) ($stmt->fetchColumn() ?: '');
        $safe = cms_media_path($path);
        if ($safe === '' || !str_starts_with($safe, '/cms/media/')) {
            cms_flash('bad', 'این تصویر پیدا نشد.');
            desk_redirect('page=media');
        }
        if (cms_media_used($safe)) {
            cms_flash('bad', 'این تصویر هنوز در یک صفحه، مطلب یا سئو استفاده شده است.');
            desk_redirect('page=media');
        }
        $full = dirname(__DIR__) . $safe;
        if (is_file($full)) {
            unlink($full);
        }
        cms_db()->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
        cms_audit('media-delete', $safe, '');
        cms_flash('ok', 'تصویر حذف شد.');
        desk_redirect('page=media');
    }

    if ($action === 'save-post') {
        $kind = ($_POST['kind'] ?? '') === 'briefing' ? 'briefing' : 'doctor';
        $id = (int) ($_POST['id'] ?? 0);
        $titleFa = cms_plain((string) ($_POST['title_fa'] ?? ''), 180);
        $titleEn = cms_plain((string) ($_POST['title_en'] ?? ''), 180);
        $bodyFa = cms_plain((string) ($_POST['body_fa'] ?? ''), 20000);
        $bodyEn = cms_plain((string) ($_POST['body_en'] ?? ''), 20000);
        $status = ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft';
        $back = 'page=edit&kind=' . $kind . ($id ? '&id=' . $id : '');
        if ($titleFa === '' && $titleEn === '') {
            $_SESSION['old'] = $_POST;
            cms_flash('bad', 'حداقل عنوان فارسی یا انگلیسی لازم است. متن فرم نگه داشته شد.');
            desk_redirect($back);
        }
        $faReady = $titleFa !== '' && $bodyFa !== '';
        $enReady = $titleEn !== '' && $bodyEn !== '';
        if ($status === 'published' && !$faReady && !$enReady) {
            $_SESSION['old'] = $_POST;
            cms_flash('bad', 'برای انتشار، یک زبان باید هم عنوان داشته باشد هم متن.');
            desk_redirect($back);
        }
        $sourceRaw = trim((string) ($_POST['source_url'] ?? ''));
        $sourceUrl = cms_https_url($sourceRaw);
        if ($sourceRaw !== '' && $sourceUrl === '') {
            $_SESSION['old'] = $_POST;
            cms_flash('bad', 'نشانی منبع باید با https شروع شود.');
            desk_redirect($back);
        }
        $coverRaw = trim((string) ($_POST['cover'] ?? ''));
        $cover = $coverRaw === '' ? '' : cms_media_path($coverRaw);
        if ($coverRaw !== '' && $cover === '') {
            $_SESSION['old'] = $_POST;
            cms_flash('bad', 'مسیر جلد معتبر نیست.');
            desk_redirect($back);
        }
        $slug = cms_slug((string) ($_POST['slug'] ?? ''));
        if ($slug === '') {
            $slug = cms_slug($titleEn !== '' ? $titleEn : $titleFa);
        }
        if ($slug === '') {
            $slug = 'note-' . date('YmdHis');
        }
        $check = cms_db()->prepare('SELECT id FROM posts WHERE kind = ? AND slug = ? AND id <> ?');
        $base = $slug;
        $n = 2;
        while (true) {
            $check->execute([$kind, $slug, $id]);
            if (!$check->fetch()) {
                break;
            }
            $slug = $base . '-' . $n;
            $n++;
        }
        $featured = isset($_POST['featured']) ? 1 : 0;
        if ($featured === 1) {
            cms_db()->prepare('UPDATE posts SET featured = 0 WHERE kind = ?')->execute([$kind]);
        }
        $now = cms_now();
        $fields = [
            $slug,
            $status,
            $titleFa,
            $titleEn,
            cms_plain((string) ($_POST['excerpt_fa'] ?? ''), 400),
            cms_plain((string) ($_POST['excerpt_en'] ?? ''), 400),
            $bodyFa,
            $bodyEn,
            cms_plain((string) ($_POST['seo_title_fa'] ?? ''), 180),
            cms_plain((string) ($_POST['seo_title_en'] ?? ''), 180),
            cms_plain((string) ($_POST['seo_description_fa'] ?? ''), 300),
            cms_plain((string) ($_POST['seo_description_en'] ?? ''), 300),
            cms_plain((string) ($_POST['source_name'] ?? ''), 120),
            $sourceUrl,
            cms_plain((string) ($_POST['source_date'] ?? ''), 80),
            $cover,
            cms_plain((string) ($_POST['category'] ?? ''), 80),
            $featured,
            $now,
        ];
        if ($id > 0) {
            $existing = cms_db()->prepare('SELECT published_at, ai_draft, reviewed_at FROM posts WHERE id = ? AND kind = ?');
            $existing->execute([$id, $kind]);
            $current = $existing->fetch();
            if (!$current) {
                cms_flash('bad', 'مطلب پیدا نشد.');
                desk_redirect('page=' . $kind);
            }
            if ((int) ($current['ai_draft'] ?? 0) === 1 && $status === 'published' && !isset($_POST['physician_review'])) {
                $_SESSION['old'] = $_POST;
                cms_flash('bad', 'این پیش‌نویس پزشکی تا وقتی تأیید بازبینی پزشک را نزنید منتشر نمی‌شود. متن فرم نگه داشته شد.');
                desk_redirect($back);
            }
            $publishedAt = $status === 'published' ? ((string) ($current['published_at'] ?? '') !== '' ? $current['published_at'] : $now) : null;
            $fields[] = $publishedAt;
            $fields[] = $id;
            $fields[] = $kind;
            $pdo = cms_db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE posts SET slug=?, status=?, title_fa=?, title_en=?, excerpt_fa=?, excerpt_en=?, body_fa=?, body_en=?, seo_title_fa=?, seo_title_en=?, seo_description_fa=?, seo_description_en=?, source_name=?, source_url=?, source_date=?, cover=?, category=?, featured=?, updated_at=?, published_at=? WHERE id=? AND kind=?')->execute($fields);
                if ((int) ($current['ai_draft'] ?? 0) === 1) {
                    $reviewed = isset($_POST['physician_review']) ? 1 : 0;
                    $reviewedAt = '';
                    if ($reviewed === 1 && $status === 'published') {
                        $reviewedAt = (string) ($current['reviewed_at'] ?? '') !== '' ? (string) $current['reviewed_at'] : $now;
                    }
                    $pdo->prepare('UPDATE posts SET image_prompt = ?, physician_review = ?, reviewed_at = ? WHERE id = ?')->execute([
                        cms_plain((string) ($_POST['image_prompt'] ?? ''), 4000),
                        $reviewed,
                        $reviewedAt,
                        $id,
                    ]);
                }
                $pdo->commit();
            } catch (Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['old'] = $_POST;
                cms_flash('bad', 'ذخیره انجام نشد. متن فرم نگه داشته شد.');
                desk_redirect($back);
            }
        } else {
            $publishedAt = $status === 'published' ? $now : null;
            cms_db()->prepare('INSERT INTO posts (kind, slug, status, title_fa, title_en, excerpt_fa, excerpt_en, body_fa, body_en, seo_title_fa, seo_title_en, seo_description_fa, seo_description_en, source_name, source_url, source_date, cover, category, featured, created_at, updated_at, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
                $kind, $slug, $status, $titleFa, $titleEn,
                $fields[4], $fields[5], $bodyFa, $bodyEn,
                $fields[8], $fields[9], $fields[10], $fields[11],
                $fields[12], $sourceUrl, $fields[14], $cover, $fields[16], $featured,
                $now, $now, $publishedAt,
            ]);
            $id = (int) cms_db()->lastInsertId();
        }
        cms_audit($status === 'published' ? 'publish' : 'save-draft', $kind . '/' . $slug, '');
        cms_flash('ok', $status === 'published' ? 'منتشر شد. هر زبانی که عنوان و متن دارد روی سایت دیده می‌شود.' : 'پیش‌نویس ذخیره شد و روی سایت دیده نمی‌شود.');
        desk_redirect('page=edit&kind=' . $kind . '&id=' . $id);
    }

    if ($action === 'delete-post') {
        $kind = ($_POST['kind'] ?? '') === 'briefing' ? 'briefing' : 'doctor';
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = cms_db()->prepare('DELETE FROM posts WHERE id = ? AND kind = ?');
        $stmt->execute([$id, $kind]);
        if ($stmt->rowCount() < 1) {
            cms_flash('bad', 'مطلب پیدا نشد.');
        } else {
            cms_audit('delete-post', $kind . '#' . $id, '');
            cms_flash('ok', 'مطلب حذف شد.');
        }
        desk_redirect('page=' . $kind);
    }

    if ($action === 'save-lead') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'new');
        $allowed = ['new', 'read', 'contacted', 'booked', 'closed'];
        if (!in_array($status, $allowed, true)) {
            $status = 'new';
        }
        cms_db()->prepare('UPDATE leads SET status = ?, note = ?, updated_at = ? WHERE id = ?')->execute([
            $status,
            cms_plain((string) ($_POST['note'] ?? ''), 1000),
            cms_now(),
            $id,
        ]);
        cms_audit('lead', '#' . $id, $status);
        cms_flash('ok', 'وضعیت درخواست ذخیره شد.');
        desk_redirect('page=' . (($_POST['back'] ?? '') === 'messages' ? 'messages' : 'appointments'));
    }

    if ($action === 'save-seo') {
        $db = cms_db();
        $db->beginTransaction();
        $upsert = $db->prepare('INSERT OR REPLACE INTO seo (path, locale, title, description, robots, og_image) VALUES (?,?,?,?,?,?)');
        $delete = $db->prepare('DELETE FROM seo WHERE path = ? AND locale = ?');
        foreach (cms_seo_catalog() as $row) {
            $path = $row[0];
            foreach (['fa', 'en', 'ar', 'zh'] as $locale) {
                $title = cms_plain((string) ($_POST['title'][$path][$locale] ?? ''), 180);
                $description = cms_plain((string) ($_POST['description'][$path][$locale] ?? ''), 300);
                $robots = ($_POST['robots'][$path][$locale] ?? '') === 'noindex' ? 'noindex' : '';
                $ogRaw = trim((string) ($_POST['og'][$path][$locale] ?? ''));
                $og = $ogRaw === '' ? '' : cms_media_path($ogRaw);
                if ($ogRaw !== '' && $og === '') {
                    $db->rollBack();
                    cms_flash('bad', 'تصویر اشتراک‌گذاری معتبر نیست: ' . $path);
                    desk_redirect('page=seo');
                }
                if ($title === '' && $description === '' && $robots === '' && $og === '') {
                    $delete->execute([$path, $locale]);
                } else {
                    $upsert->execute([$path, $locale, $title, $description, $robots, $og]);
                }
            }
        }
        $db->commit();
        cms_audit('seo', '', '');
        cms_flash('ok', 'سئو ذخیره شد.');
        desk_redirect('page=seo');
    }

    if ($action === 'save-geo') {
        if (!$owner) {
            cms_flash('bad', 'فقط مالک می‌تواند موقعیت را منتشر کند.');
            desk_redirect();
        }
        $fields = [
            'geo_name' => 120,
            'geo_street' => 180,
            'geo_city' => 80,
            'geo_region' => 40,
            'geo_postal' => 20,
            'geo_country' => 8,
            'geo_phone' => 40,
            'geo_hours' => 180,
            'geo_lat' => 20,
            'geo_lng' => 20,
        ];
        foreach ($fields as $key => $max) {
            cms_set_setting($key, cms_plain((string) ($_POST[$key] ?? ''), $max));
        }
        $confirm = isset($_POST['geo_confirmed']);
        $lat = cms_setting('geo_lat');
        $lng = cms_setting('geo_lng');
        $latOk = $lat === '' || (is_numeric($lat) && (float) $lat >= -90 && (float) $lat <= 90);
        $lngOk = $lng === '' || (is_numeric($lng) && (float) $lng >= -180 && (float) $lng <= 180);
        if (!$latOk || !$lngOk) {
            cms_set_setting('geo_confirmed', '0');
            cms_flash('bad', 'عرض یا طول جغرافیایی خارج از محدوده است. منتشر نشد.');
            desk_redirect('page=geo');
        }
        if ($confirm && (cms_setting('geo_street') === '' || cms_setting('geo_city') === '' || cms_setting('geo_phone') === '')) {
            cms_set_setting('geo_confirmed', '0');
            cms_flash('bad', 'برای انتشار در گوگل، خیابان، شهر و تلفن تأییدشده لازم است.');
            desk_redirect('page=geo');
        }
        cms_set_setting('geo_confirmed', $confirm ? '1' : '0');
        cms_audit('geo', '', $confirm ? 'published' : 'hidden');
        cms_flash('ok', $confirm ? 'داده مکانی روی صفحات سایت منتشر شد.' : 'داده مکانی ذخیره شد و روی سایت منتشر نیست.');
        desk_redirect('page=geo');
    }

    if ($action === 'save-user') {
        if (!$owner) {
            cms_flash('bad', 'فقط مالک کاربر را مدیریت می‌کند.');
            desk_redirect();
        }
        $id = (int) ($_POST['id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = cms_desk_role((string) ($_POST['role'] ?? ''));
        if (!in_array((string) ($_POST['role'] ?? ''), ['owner', 'editor', 'nurse'], true)) {
            $role = 'editor';
        }
        $active = isset($_POST['active']) ? 1 : 0;
        $name = cms_plain((string) ($_POST['display_name'] ?? ''), 80);
        $password = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) {
            cms_flash('bad', 'نام کاربری انگلیسی ۳ تا ۴۰ نویسه است.');
            desk_redirect('page=users');
        }
        if ($id === 0) {
            if (strlen($password) < 10) {
                cms_flash('bad', 'رمز کاربر تازه حداقل ۱۰ نویسه است.');
                desk_redirect('page=users');
            }
            try {
                cms_db()->prepare('INSERT INTO admins (username, password_hash, created_at, role, display_name, active) VALUES (?,?,?,?,?,?)')->execute([
                    $username, password_hash($password, PASSWORD_DEFAULT), cms_now(), $role, $name, $active,
                ]);
            } catch (PDOException) {
                cms_flash('bad', 'این نام کاربری وجود دارد.');
                desk_redirect('page=users');
            }
            cms_audit('user-create', $username, $role);
            cms_flash('ok', 'کاربر ساخته شد.');
            desk_redirect('page=users');
        }
        $current = cms_db()->prepare('SELECT * FROM admins WHERE id = ?');
        $current->execute([$id]);
        $row = $current->fetch();
        if (!$row) {
            cms_flash('bad', 'کاربر پیدا نشد.');
            desk_redirect('page=users');
        }
        $stillOwner = $role === 'owner' && $active === 1;
        if ((string) $row['role'] === 'owner' && (int) $row['active'] === 1 && !$stillOwner && desk_owner_count() <= 1) {
            cms_flash('bad', 'آخرین مالک فعال را نمی‌توان ویرایشگر یا غیرفعال کرد.');
            desk_redirect('page=users');
        }
        if ((int) $user['id'] === $id && $active === 0) {
            cms_flash('bad', 'نمی‌توانید حساب خودتان را غیرفعال کنید.');
            desk_redirect('page=users');
        }
        $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : (string) $row['password_hash'];
        if ($password !== '' && strlen($password) < 10) {
            cms_flash('bad', 'رمز تازه حداقل ۱۰ نویسه است.');
            desk_redirect('page=users');
        }
        try {
            cms_db()->prepare('UPDATE admins SET username=?, password_hash=?, role=?, display_name=?, active=? WHERE id=?')->execute([
                $username, $hash, $role, $name, $active, $id,
            ]);
        } catch (PDOException) {
            cms_flash('bad', 'این نام کاربری وجود دارد.');
            desk_redirect('page=users');
        }
        cms_audit('user-update', $username, $role);
        cms_flash('ok', 'کاربر ذخیره شد.');
        desk_redirect('page=users');
    }

    if ($action === 'save-account') {
        $name = cms_plain((string) ($_POST['display_name'] ?? ''), 80);
        $current = (string) ($_POST['current_password'] ?? '');
        $next = (string) ($_POST['new_password'] ?? '');
        $row = cms_db()->prepare('SELECT * FROM admins WHERE id = ?');
        $row->execute([(int) $user['id']]);
        $account = $row->fetch();
        if (!$account || !password_verify($current, (string) $account['password_hash'])) {
            cms_flash('bad', 'رمز فعلی نادرست است.');
            desk_redirect('page=account');
        }
        $hash = (string) $account['password_hash'];
        if ($next !== '') {
            if (strlen($next) < 10) {
                cms_flash('bad', 'رمز تازه حداقل ۱۰ نویسه است.');
                desk_redirect('page=account');
            }
            $hash = password_hash($next, PASSWORD_DEFAULT);
        }
        cms_db()->prepare('UPDATE admins SET display_name=?, password_hash=? WHERE id=?')->execute([$name, $hash, (int) $user['id']]);
        cms_audit('account', (string) $user['username'], $next !== '' ? 'password' : 'name');
        cms_flash('ok', 'حساب ذخیره شد.');
        desk_redirect('page=account');
    }

    if ($action === 'save-settings') {
        if (!$owner) {
            cms_flash('bad', 'فقط مالک تنظیمات را عوض می‌کند.');
            desk_redirect();
        }
        $base = trim((string) ($_POST['openai_base'] ?? ''));
        if ($base !== '' && !preg_match('#^https://#', $base)) {
            cms_flash('bad', 'آدرس سرویس باید با https شروع شود.');
            desk_redirect('page=settings');
        }
        cms_set_setting('openai_base', $base !== '' ? rtrim($base, '/') : 'https://openrouter.ai/api/v1');
        $freshKey = trim((string) ($_POST['openai_key'] ?? ''));
        if (isset($_POST['clear_key'])) {
            cms_set_setting('openai_key', '');
        } elseif ($freshKey !== '') {
            if (!preg_match('/^[A-Za-z0-9_\-.]{20,250}$/', $freshKey)) {
                cms_flash('bad', 'شکل کلید پذیرفته نیست. کلید OpenRouter را کامل بچسبانید.');
                desk_redirect('page=settings');
            }
            cms_set_setting('openai_key', $freshKey);
        }
        cms_audit('settings', '', isset($_POST['clear_key']) ? 'key-cleared' : 'saved');
        cms_flash('ok', 'تنظیمات ذخیره شد.');
        desk_redirect('page=settings');
    }

    if ($action === 'save-look') {
        $motion = (string) ($_POST['look_motion'] ?? 'quiet');
        $hero = (string) ($_POST['look_hero'] ?? 'portrait');
        $story = (string) ($_POST['look_story'] ?? 'sticky');
        $articles = (string) ($_POST['look_articles'] ?? 'cards');
        $contact = (string) ($_POST['look_contact'] ?? 'band');
        $sections = ['hero', 'intro', 'treatments', 'implant', 'surgeon', 'reviews', 'journal', 'story', 'anatomy', 'contact'];
        if (!in_array($motion, ['quiet', 'gentle', 'off'], true) || !in_array($hero, ['portrait', 'studio'], true) || !in_array($story, ['stacked', 'sticky'], true) || !in_array($articles, ['cards', 'rows'], true) || !in_array($contact, ['band', 'full'], true)) {
            cms_flash('bad', 'یکی از انتخاب‌های چیدمان معتبر نیست.');
            desk_redirect('page=look');
        }
        $ranked = [];
        foreach ($sections as $id) {
            $rank = (int) ($_POST['order'][$id] ?? 0);
            $ranked[] = [$rank > 0 ? $rank : 99, $id];
        }
        usort($ranked, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $home = [];
        foreach ($ranked as $item) {
            if (!in_array($item[1], $home, true)) {
                $home[] = $item[1];
            }
        }
        $hide = [];
        foreach ($sections as $id) {
            if ($id !== 'hero' && !isset($_POST['show'][$id])) {
                $hide[] = $id;
            }
        }
        cms_set_setting('look_motion', $motion);
        cms_set_setting('look_hero', $hero);
        cms_set_setting('look_story', $story);
        cms_set_setting('look_articles', $articles);
        cms_set_setting('look_contact', $contact);
        cms_set_setting('look_home', implode('.', $home));
        cms_set_setting('look_hide', implode('.', $hide));
        cms_audit('look', '', $motion . '/' . $hero);
        cms_flash('ok', 'چیدمان ذخیره شد. صفحه سایت را یک‌بار تازه کنید.');
        desk_redirect('page=look');
    }

    if ($action === 'save-google') {
        if (!$owner) {
            cms_flash('bad', 'فقط مالک می‌تواند سایت را به گوگل وصل کند.');
            desk_redirect('page=seo');
        }
        $raw = trim((string) ($_POST['google_verify'] ?? ''));
        if ($raw === '') {
            cms_set_setting('google_verify', '');
            cms_audit('google', '', 'cleared');
            cms_flash('ok', 'کد تأیید گوگل پاک شد.');
            desk_redirect('page=seo');
        }
        $token = cms_google_token($raw);
        if ($token === '') {
            cms_flash('bad', 'کد را از برچسب HTML سرچ کنسول بردارید. فقط حروف، عدد، خط تیره و زیرخط قبول است.');
            desk_redirect('page=seo');
        }
        cms_set_setting('google_verify', $token);
        cms_audit('google', '', 'saved');
        cms_flash('ok', 'کد روی همه صفحات سایت گذاشته شد. در سرچ کنسول دکمه Verify را بزنید.');
        desk_redirect('page=seo');
    }

    if ($action === 'import-one' || $action === 'import') {
        $withAi = isset($_POST['with_ai']) && cms_setting('openai_key') !== '';
        if ($action === 'import') {
            $result = cms_import_feeds($withAi, 3);
            cms_audit('import', 'feeds', 'created=' . $result['created']);
            cms_flash($result['created'] > 0 ? 'ok' : 'bad', $result['created'] > 0 ? ($result['created'] . ' پیش‌نویس ساخته شد.') : 'پیش‌نویسی ساخته نشد. منبع ممکن است از این سرور باز نشود.');
            desk_redirect('page=feeds');
        }
        $item = [
            'name' => cms_plain((string) ($_POST['source_name'] ?? 'منبع'), 80),
            'title' => cms_plain((string) ($_POST['title'] ?? ''), 180),
            'url' => cms_https_url((string) ($_POST['url'] ?? '')),
            'date' => cms_plain((string) ($_POST['date'] ?? ''), 80),
            'summary' => cms_plain((string) ($_POST['summary'] ?? ''), 600),
        ];
        if ($item['title'] === '' || $item['url'] === '') {
            cms_flash('bad', 'عنوان و نشانی https لازم است.');
            desk_redirect('page=feeds');
        }
        $result = cms_store_source_draft($item, $withAi);
        cms_audit('import-one', $item['url'], '');
        cms_flash($result['created'] ? 'ok' : 'bad', $result['created'] ? 'پیش‌نویس ساخته شد و منتشر نشده است.' : 'این منبع قبلاً وارد شده است.');
        desk_redirect('page=briefing');
    }

    if ($action === 'studio-build') {
        $topic = cms_plain((string) ($_POST['topic'] ?? ''), 180);
        $title = cms_plain((string) ($_POST['suggested_title'] ?? ''), 180);
        $lang = (string) ($_POST['lang'] ?? 'fa');
        if (!in_array($lang, ['fa', 'en', 'both'], true)) {
            $lang = 'fa';
        }
        $audience = cms_plain((string) ($_POST['audience'] ?? 'عموم'), 80);
        $length = (string) ($_POST['length'] ?? 'medium');
        if (!in_array($length, ['short', 'medium', 'long'], true)) {
            $length = 'medium';
        }
        $category = cms_plain((string) ($_POST['category'] ?? ''), 80);
        $lines = preg_split('/\R/u', (string) ($_POST['more_topics'] ?? '')) ?: [];
        $topics = [];
        if ($topic !== '') {
            $topics[] = $topic;
        }
        foreach ($lines as $line) {
            $line = cms_plain($line, 180);
            if ($line !== '' && !in_array($line, $topics, true)) {
                $topics[] = $line;
            }
            if (count($topics) >= 3) {
                break;
            }
        }
        if (!$topics) {
            $_SESSION['studio_old'] = $_POST;
            cms_flash('bad', 'موضوع خالی است. چیزی ذخیره نشد.');
            desk_redirect('page=studio');
        }
        if (!isset($_POST['allow_similar'])) {
            foreach ($topics as $itemTopic) {
                $similar = cms_similar_posts($itemTopic);
                if ($similar) {
                    $_SESSION['studio_old'] = $_POST;
                    $_SESSION['studio_similar'] = $similar;
                    cms_flash('bad', 'برای «' . $itemTopic . '» مطلب مشابه در سایت هست. لینک به‌روزرسانی پایین فرم است. اگر پیش‌نویس تازه می‌خواهید، تأیید تکرار را بزنید.');
                    desk_redirect('page=studio');
                }
            }
        }
        $manualQuery = cms_plain((string) ($_POST['search_query'] ?? ''), 160);
        @set_time_limit(170);
        $deadline = time() + 145;
        $created = [];
        $errors = [];
        foreach ($topics as $itemTopic) {
            if ($created !== [] && time() >= $deadline) {
                $errors[] = 'وقت سرور برای موضوع‌های بعدی کافی نبود. آن‌ها را جداگانه بسازید.';
                break;
            }
            $resolved = cms_studio_query($itemTopic, $itemTopic === $topic ? $manualQuery : '');
            if (!$resolved['ok']) {
                $errors[] = $itemTopic . ': ' . $resolved['error'];
                cms_log('warn', 'studio', 'query', $itemTopic . ': ' . $resolved['error'], []);
                continue;
            }
            $found = cms_pubmed_sources($resolved['query']);
            if (!$found['ok']) {
                $errors[] = $itemTopic . ': ' . $found['error'];
                cms_log('warn', 'pubmed', 'search', $itemTopic . ': ' . $found['error'], ['query' => $resolved['query']]);
                continue;
            }
            $written = cms_studio_write($itemTopic, $itemTopic === $topic ? $title : '', $lang, $audience, $length, $found['items'], $found['note']);
            if (!$written['ok']) {
                $_SESSION['studio_sources'] = $found['items'];
                $errors[] = $itemTopic . ': ' . $written['error'];
                cms_log('warn', 'studio', 'write', $itemTopic . ': ' . $written['error'], []);
                continue;
            }
            $stored = cms_studio_store($itemTopic, [
                'category' => $category,
                'lang' => $lang,
                'query' => $resolved['query'],
            ], $found['items'], $found['note'] . ' عبارت PubMed: ' . $resolved['query'] . (($written['model'] ?? '') !== '' ? ' مدل: ' . $written['model'] : ''), $written['data']);
            if ($stored < 1) {
                $_SESSION['studio_sources'] = $found['items'];
                $errors[] = $itemTopic . ': پیش‌نویس زبان انتخاب‌شده کامل نبود و ذخیره نشد.';
                cms_log('warn', 'studio', 'store', 'پیش‌نویس زبان انتخاب‌شده کامل نبود', ['topic' => $itemTopic]);
                continue;
            }
            $created[] = $stored;
            cms_log('info', 'studio', 'draft', 'پیش‌نویس ذخیره شد و منتشر نشد', ['post' => (string) $stored, 'model' => (string) ($written['model'] ?? '')]);
        }
        if (!$created) {
            $_SESSION['studio_old'] = $_POST;
            cms_flash('bad', $errors ? implode(' ', array_slice($errors, 0, 3)) : 'پیش‌نویسی ساخته نشد.');
            desk_redirect('page=studio');
        }
        cms_audit('studio', implode(',', $created), '');
        if (count($created) === 1) {
            cms_flash('ok', 'پیش‌نویس ساخته شد و منتشر نشده است. منابع، پرامپت تصویر و متن را ببینید.' . ($errors ? ' ' . implode(' ', array_slice($errors, 0, 2)) : ''));
            desk_redirect('page=edit&kind=doctor&id=' . $created[0]);
        }
        $_SESSION['studio_created'] = $created;
        cms_flash('ok', count($created) . ' پیش‌نویس جدا ساخته شد و هیچ‌کدام منتشر نشده است.');
        desk_redirect('page=studio');
    }

    if ($action === 'backup-database' || $action === 'download-database') {
        if (!$owner) {
            cms_flash('bad', 'نسخه پشتیبان فقط برای مالک میز است.');
            desk_redirect('page=settings');
        }
        $saved = cms_backup_database();
        if (!$saved['ok']) {
            cms_flash('bad', $saved['error']);
            desk_redirect('page=settings');
        }
        $file = (string) $saved['file'];
        $folder = realpath(dirname(cms_db_path()) . DIRECTORY_SEPARATOR . 'backups');
        $real = realpath($file);
        $inside = $folder !== false && $real !== false && str_starts_with(str_replace('\\', '/', $real), rtrim(str_replace('\\', '/', $folder), '/') . '/');
        if (!$inside || !preg_match('/^clinic-\d{8}-\d{6}(?:-[a-f0-9]{4})?\.sqlite$/', basename($file))) {
            cms_flash('bad', 'فایل پشتیبان در جای امن نیست.');
            desk_redirect('page=settings');
        }
        if ($action === 'download-database') {
            cms_audit('backup', basename($file), 'download');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . (string) filesize($real));
            header('X-Content-Type-Options: nosniff');
            readfile($real);
            exit;
        }
        cms_audit('backup', basename($file), '');
        cms_flash('ok', 'یک نسخه پشتیبان کنار پایگاه ذخیره شد. هفت نسخه آخر نگه داشته می‌شود.');
        desk_redirect('page=settings');
    }

    if ($action === 'export-sheet') {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $known = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($known) {
            $known->execute([$phone]);
        }
        if (!$known || !$known->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $pdf = cms_care_sheet_pdf($phone, 'fa');
        if ($pdf === '' || !str_starts_with($pdf, '%PDF')) {
            cms_flash('bad', 'فایل برگه ساخته نشد.');
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        cms_audit('care', $phone, 'pdf');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="care-sheet.pdf"');
        header('Content-Length: ' . (string) strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    }

    if ($action === 'export-report') {
        $jy = (int) ($_POST['jy'] ?? 0);
        $jm = (int) ($_POST['jm'] ?? 0);
        $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
        $report = cms_month_report($jy, $jm, $today);
        $sheet = cms_report_sheet($report, $jy, $jm);
        if ($sheet === '') {
            cms_flash('bad', 'این ماه خوانده نشد.');
            desk_redirect('page=report');
        }
        cms_audit('backup', 'month', $jy . '-' . $jm);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="month-' . $jy . '-' . $jm . '.csv"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF" . $sheet;
        exit;
    }

    if ($action === 'export-book') {
        if (!$owner) {
            cms_flash('bad', 'فهرست نوبت فقط برای مالک میز است.');
            desk_redirect('page=appointments');
        }
        $sheet = cms_book_sheet();
        $lines = substr_count($sheet, "\n");
        cms_audit('backup', 'sheet', (string) max(0, $lines - 1));
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="visits-' . gmdate('Ymd-His') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF" . $sheet;
        exit;
    }

    cms_flash('bad', 'این کار شناخته نشد.');
    desk_redirect();
}

if (!cms_installed()) {
    desk_auth('install');
    exit;
}

$page = (string) ($_GET['page'] ?? 'dashboard');
$user = desk_require_user();
$owner = $user['role'] === 'owner';
if (in_array($page, ['users', 'settings', 'geo', 'messaging'], true) && !$owner) {
    cms_flash('bad', 'این بخش فقط برای مالک میز است.');
    desk_redirect();
}
if (($user['role'] ?? '') === 'nurse' && !in_array($page, ['dashboard', 'appointments', 'patients', 'account'], true)) {
    cms_flash('bad', 'این بخش برای نقش پرستار نیست. دفتر تماس داخل پرونده است.');
    desk_redirect();
}

if ($page === 'dashboard') {
    $freshVisits = (int) cms_db()->query("SELECT COUNT(*) FROM visits WHERE status = 'new'")->fetchColumn();
    $freshVisits += (int) cms_db()->query("SELECT COUNT(*) FROM leads WHERE kind = 'appointment' AND status = 'new'")->fetchColumn();
    $freshNotes = (int) cms_db()->query("SELECT COUNT(*) FROM leads WHERE kind = 'contact' AND status = 'new'")->fetchColumn();
    $drafts = (int) cms_db()->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn();
    $live = (int) cms_db()->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn();
    $thin = (int) cms_db()->query("SELECT COUNT(*) FROM posts WHERE status = 'published' AND seo_description_fa = '' AND excerpt_fa = ''")->fetchColumn();
    $google = cms_google_token(cms_setting('google_verify')) !== '';
    $geoOn = cms_setting('geo_confirmed') === '1' && cms_setting('geo_street') !== '' && cms_setting('geo_city') !== '';
    $hello = trim((string) ($user['display_name'] ?? ''));
    if ($hello === '') {
        $hello = 'دکتر';
    }
    $sitemap = cms_origin() . '/sitemap.xml';
    $liveMap = cms_origin() . '/cms/sitemap.php';
    if (($user['role'] ?? '') === 'nurse') {
        desk_open('خانه میز', 'dashboard', 'زنگ‌های بی‌جواب و دوباره همین‌جا می‌ماند.');
        echo '<section class="welcome"><p class="kicker">دفتر تماس</p><h2>سلام، ' . e($hello) . '</h2>';
        echo '<p>نقش شما پیگیری است. دفتر تماس، مسئول کار و تیک پیش از ورود باز است. برگه، دارو، رمز پرونده و حذف برای این نقش نیست.</p>';
        echo '<p class="row"><a class="btn" href="/manage/index.php?page=patients">پرونده‌ها</a><a class="btn ghost" href="/manage/index.php?page=appointments">نوبت‌ها</a></p></section>';
        echo '<section class="card calls"><h2>زنگ‌های باز</h2>';
        $openCalls = cms_calls_open();
        if (!$openCalls) {
            echo '<p class="help">جواب‌نداده یا دوباره زنگی در دفتر نیست.</p>';
        }
        foreach ($openCalls as $call) {
            $tone = (string) $call['result'] === 'missed' ? 'miss' : 'back';
            $label = cms_call_results()[(string) $call['result']] ?? (string) $call['result'];
            echo '<a class="call" href="/manage/index.php?page=patients&phone=' . e(rawurlencode((string) $call['phone'])) . '">';
            echo '<span class="chip ' . e($tone) . '">' . e($label) . '</span><div><b>' . e((string) ($call['name'] !== '' ? $call['name'] : 'بیمار')) . '</b> <small>' . e(cms_when((string) $call['created_at'], true)) . '</small>';
            if ((string) $call['note'] !== '') {
                echo '<p>' . e((string) $call['note']) . '</p>';
            }
            echo '</div></a>';
        }
        echo '</section>';
        desk_close();
        exit;
    }
    desk_open('خانه میز', 'dashboard', 'نوبت تازه، یادداشت و دیده‌شدن در گوگل.');
    echo '<section class="welcome"><p class="kicker">میز مطب</p><h2>سلام، ' . e($hello) . '</h2>';
    echo '<p>هر متنی که اینجا منتشر شود روی سایت می‌آید. پیش‌نویس را بیمار نمی‌بیند. کتاب، کنگره و نقل‌قول نظر بیماران اینجا نیست تا بدون بازبینی منتشر نشود.</p>';
    echo '<p class="row"><a class="btn" href="/manage/index.php?page=edit&kind=doctor">یادداشت تازه</a><a class="btn ghost" href="/manage/index.php?page=appointments">نوبت‌ها</a><a class="btn ghost" href="/fa/column/">دیدن یادداشت‌ها</a></p></section>';
    echo '<div class="stats">';
    echo '<a class="stat" href="/manage/index.php?page=appointments"><span class="muted">نوبت تازه</span><b>' . $freshVisits . '</b></a>';
    echo '<a class="stat" href="/manage/index.php?page=messages"><span class="muted">پیام تازه</span><b>' . $freshNotes . '</b></a>';
    echo '<a class="stat" href="/manage/index.php?page=doctor"><span class="muted">پیش‌نویس</span><b>' . $drafts . '</b></a>';
    echo '<a class="stat" href="/manage/index.php?page=doctor"><span class="muted">منتشرشده</span><b>' . $live . '</b></a>';
    echo '</div>';
    echo '<div class="grid-2"><div class="card"><h2>کار امروز</h2>';
    $anyLead = false;
    foreach (cms_db()->query("SELECT kind, name, status, created_at FROM leads ORDER BY id DESC LIMIT 6") as $lead) {
        $anyLead = true;
        $target = $lead['kind'] === 'appointment' ? 'appointments' : 'messages';
        $kindLabel = $lead['kind'] === 'appointment' ? 'نوبت' : 'پیام';
        echo '<p><a href="/manage/index.php?page=' . e($target) . '">' . e($kindLabel) . ' · ' . e($lead['name']) . '</a> <span class="pill ' . e($lead['status']) . '">' . e(desk_lead_label((string) $lead['status'])) . '</span> <span class="muted">' . e(cms_when((string) $lead['created_at'], true)) . '</span></p>';
    }
    if (!$anyLead) {
        echo '<p class="help">هنوز درخواستی نیامده. فرم تماس و نوبت سایت همین‌جا ذخیره می‌شود.</p>';
    }
    echo '</div><div class="card"><h2>دیده‌شدن در گوگل</h2><ul class="check">';
    echo '<li class="' . ($google ? 'done' : 'wait') . '">' . ($google ? 'کد تأیید گوگل روی سایت است.' : 'کد سرچ کنسول هنوز ذخیره نشده.') . '</li>';
    echo '<li class="' . ($geoOn ? 'done' : 'wait') . '">' . ($geoOn ? 'نشانی مطب برای نقشه منتشر شده.' : 'نشانی مطب هنوز تأیید و منتشر نشده، پس در نقشه گوگل نمی‌آید.') . '</li>';
    echo '<li class="' . ($thin === 0 ? 'done' : 'wait') . '">' . ($thin === 0 ? 'یادداشت‌های منتشرشده شرح جستجو دارند یا خلاصه دارند.' : $thin . ' یادداشت منتشرشده شرح جستجو و خلاصه ندارد.') . '</li>';
    echo '</ul><p class="row"><button type="button" class="btn ghost" data-copy="' . e($sitemap) . '">کپی نقشه سایت</button><a class="btn ghost" href="/manage/index.php?page=seo">سئو و گوگل</a></p>';
    echo '<p class="help" dir="ltr">' . e($sitemap) . '<br>' . e($liveMap) . '</p></div></div>';
    if ($owner) {
        echo '<div class="card"><h2>پایگاه و به‌روزرسانی</h2>';
        echo '<p>پایگاه نسخه ' . (int) cms_setting('schema_version', '0') . ' است و ' . e(cms_db_place()) . '.</p>';
        echo '<p class="help" dir="ltr">' . e(cms_db_path()) . '</p>';
        echo '<p class="help">فایل jafari-cpanel.zip را در public_html از حالت فشرده خارج کنید. پوشه‌های cms/data و cms/media و jafari-private را پاک نکنید. ستون یا جدول را در MySQL نسازید؛ با اولین باز شدن میز، ساختار خودش به‌روز می‌شود.</p>';
        echo '<p><a class="btn ghost" href="/manage/index.php?page=settings">نسخه پشتیبان</a></p></div>';
    }
    desk_close();
    exit;
}

if ($page === 'content') {
    $groups = cms_registry_groups();
    $group = (string) ($_GET['group'] ?? $groups[0]);
    if (!in_array($group, $groups, true)) {
        $group = $groups[0];
    }
    $saved = [];
    $stmt = cms_db()->query('SELECT slot_key, locale, value FROM slots');
    foreach ($stmt as $row) {
        $saved[$row['slot_key']][$row['locale']] = $row['value'];
    }
    $media = cms_db()->query('SELECT path FROM media ORDER BY id DESC')->fetchAll();
    desk_open('متن و تصویر صفحات', 'content');
    echo '<div class="groups">';
    foreach ($groups as $name) {
        echo '<a class="' . ($name === $group ? 'active' : '') . '" href="/manage/index.php?page=content&group=' . e(rawurlencode($name)) . '">' . e($name) . '</a>';
    }
    echo '</div><p class="help">جای خالی یعنی متن فعلی سایت بماند. عنوان هر کارت، متن فعلی فارسی است. عکس را اول در گالری بگذارید و مسیر WebP را اینجا انتخاب کنید. برای فیلم کنگره یا کتاب، لینک https را در فیلد فیلم بگذارید. لینک فقط با https ذخیره می‌شود. ایمیل را در بخش مطب، فیلد clinic.email بنویسید.</p>';
    echo '<form method="post"><input type="hidden" name="action" value="save-content">' . desk_csrf() . '<input type="hidden" name="group" value="' . e($group) . '">';
    echo '<datalist id="paths">';
    foreach ($media as $item) {
        echo '<option value="' . e($item['path']) . '">';
    }
    echo '</datalist>';
    foreach (cms_registry() as $row) {
        if ($row['group'] !== $group) {
            continue;
        }
        $title = trim((string) $row['fa']) !== '' ? (string) $row['fa'] : $row['key'];
        echo '<div class="card"><strong>' . e(desk_clip($title)) . '</strong> <span class="muted" dir="ltr">' . e($row['key']) . '</span><div class="grid-2">';
        foreach (['fa' => 'فارسی', 'en' => 'English'] as $locale => $label) {
            $value = $saved[$row['key']][$locale] ?? '';
            $short = function_exists('mb_strlen') && mb_strlen((string) $row['fa']) <= 80;
            echo '<label>' . e($label);
            if ($row['type'] === 'image' || $row['type'] === 'link') {
                echo '<input name="' . e($locale) . '[' . e($row['key']) . ']" ' . ($row['type'] === 'image' ? 'list="paths" ' : '') . 'value="' . e($value) . '" placeholder="' . e($row[$locale]) . '" dir="ltr">';
            } elseif ($short) {
                echo '<input name="' . e($locale) . '[' . e($row['key']) . ']" value="' . e($value) . '" placeholder="' . e($row[$locale]) . '">';
            } else {
                echo '<textarea name="' . e($locale) . '[' . e($row['key']) . ']" placeholder="' . e($row[$locale]) . '">' . e($value) . '</textarea>';
            }
            echo '</label>';
        }
        echo '</div></div>';
    }
    echo '<button class="btn" type="submit">ذخیره این بخش</button></form>';
    desk_close();
    exit;
}

if ($page === 'media') {
    $rows = cms_db()->query('SELECT * FROM media ORDER BY id DESC')->fetchAll();
    $shown = 0;
    $weight = 0;
    foreach ($rows as $item) {
        $weight += (int) $item['bytes'];
        if ((int) ($item['show_gallery'] ?? 0) === 1) {
            $shown++;
        }
    }
    desk_open('گالری', 'media');
    if (!cms_webp_ready()) {
        echo '<div class="flash bad">تبدیل WebP روی این PHP خاموش است. در cPanel بخش Select PHP Version افزونه gd را روشن کنید.</div>';
    }
    echo '<form class="drop" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="upload">' . desk_csrf();
    echo '<input id="gallery-files" type="file" name="files[]" accept="image/jpeg,image/png,image/webp" multiple required>';
    echo '<p id="drop-names" class="drop-names"></p>';
    echo '<p class="drop-title">عکس را اینجا رها کنید یا انتخاب کنید</p>';
    echo '<p class="help">هر عکس پیش از ذخیره به WebP تبدیل می‌شود، ضلع بزرگش حداکثر ۲۰۰۰ پیکسل می‌ماند و داده دوربین از فایل پاک می‌شود. سقف هر فایل ۱۲ مگابایت است. عکس قابل شناسایی بیمار را بدون رضایت نگذارید.</p>';
    echo '<div class="grid-2"><label>شرح فارسی برای این نوبت<input name="alt_fa"></label><label>English description<input name="alt_en" dir="ltr"></label></div>';
    echo '<label class="checkline"><span><input type="checkbox" name="show_gallery"> همین عکس‌ها در گالری عمومی سایت دیده شوند</span></label>';
    echo '<button class="btn" type="submit">افزودن به گالری</button></form>';
    echo '<div class="stats"><div class="stat"><span class="muted">همه عکس‌ها</span><b>' . count($rows) . '</b></div>';
    echo '<div class="stat"><span class="muted">روی سایت</span><b>' . $shown . '</b></div>';
    echo '<div class="stat"><span class="muted">حجم فعلی</span><b>' . e(cms_kb($weight)) . '</b></div>';
    echo '<div class="stat"><span class="muted">گالری عمومی</span><b><a href="/fa/gallery/">دیدن</a></b></div></div>';
    if (!$rows) {
        echo '<div class="card"><p>هنوز عکسی نیست. اولین تصویر، گالری را می‌سازد.</p></div>';
    }
    echo '<div class="gallery">';
    foreach ($rows as $item) {
        $path = (string) $item['path'];
        $wide = (int) ($item['width'] ?? 0) > (int) ($item['height'] ?? 0) ? ' wide' : '';
        $saved = (int) ($item['source_bytes'] ?? 0) > (int) $item['bytes']
            ? cms_kb((int) $item['source_bytes']) . ' ← ' . cms_kb((int) $item['bytes'])
            : cms_kb((int) $item['bytes']);
        echo '<article class="shot' . $wide . '">';
        echo '<button type="button" class="shot-open" data-full="' . e($path) . '" data-alt="' . e((string) $item['alt_fa']) . '">';
        echo '<img src="' . e($path) . '" alt="' . e((string) $item['alt_fa']) . '" width="' . (int) $item['width'] . '" height="' . (int) $item['height'] . '" loading="lazy" decoding="async">';
        echo '</button><div class="shot-body">';
        echo '<p class="shot-meta">' . e($saved);
        if ((int) $item['width'] > 0) {
            echo ' · ' . (int) $item['width'] . '×' . (int) $item['height'];
        }
        if ((int) ($item['show_gallery'] ?? 0) === 1) {
            echo ' · <span class="pill published">روی سایت</span>';
        }
        echo '</p><div class="shot-actions">';
        echo '<button type="button" class="btn ghost" data-copy="' . e($path) . '">کپی مسیر</button>';
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="copy-media"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button class="btn ghost" type="submit">نسخه تازه</button></form>';
        echo '<form method="post" data-confirm="این عکس حذف شود؟">' . desk_csrf() . '<input type="hidden" name="action" value="delete-media"><input type="hidden" name="id" value="' . (int) $item['id'] . '"><button class="btn bad" type="submit">حذف</button></form>';
        echo '</div><details><summary>شرح و نمایش</summary>';
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-media"><input type="hidden" name="id" value="' . (int) $item['id'] . '">';
        echo '<label>شرح فارسی<input name="alt_fa" value="' . e((string) $item['alt_fa']) . '"></label>';
        echo '<label>English alt<input name="alt_en" dir="ltr" value="' . e((string) $item['alt_en']) . '"></label>';
        echo '<label>زیرنویس گالری<input name="caption_fa" value="' . e((string) ($item['caption_fa'] ?? '')) . '"></label>';
        echo '<label>English caption<input name="caption_en" dir="ltr" value="' . e((string) ($item['caption_en'] ?? '')) . '"></label>';
        echo '<label class="checkline"><span><input type="checkbox" name="show_gallery" ' . ((int) ($item['show_gallery'] ?? 0) === 1 ? 'checked' : '') . '> در گالری عمومی سایت</span></label>';
        echo '<button class="btn" type="submit">ذخیره</button></form></details></div></article>';
    }
    echo '</div><dialog id="light"><div class="stage"><button type="button" id="light-close">بستن</button><img alt=""></div></dialog>';
    echo '<script nonce="' . e(cms_csp_nonce()) . '">';
    echo <<<'HTML'
(function () {
  var dialog = document.getElementById("light");
  var image = dialog.querySelector("img");
  var zone = document.querySelector(".drop");
  var picker = document.getElementById("gallery-files");
  var names = document.getElementById("drop-names");
  function showNames() {
    if (!picker || !names) return;
    var list = Array.prototype.map.call(picker.files, function (file) { return file.name; });
    names.textContent = list.length ? list.length + " عکس آماده افزودن است: " + list.join("، ") : "";
  }
  if (zone && picker) {
    zone.addEventListener("dragover", function (event) { event.preventDefault(); zone.classList.add("over"); });
    zone.addEventListener("dragleave", function () { zone.classList.remove("over"); });
    zone.addEventListener("drop", function (event) {
      event.preventDefault();
      zone.classList.remove("over");
      if (event.dataTransfer && event.dataTransfer.files.length) {
        picker.files = event.dataTransfer.files;
        showNames();
      }
    });
    picker.addEventListener("change", showNames);
  }
  function copyText(value, button) {
    var done = function () { button.textContent = "کپی شد"; };
    var fallback = function () {
      var input = document.createElement("textarea");
      input.value = value;
      input.setAttribute("readonly", "");
      input.style.position = "fixed";
      input.style.insetInlineStart = "0";
      input.style.top = "0";
      document.body.appendChild(input);
      input.select();
      var ok = false;
      try { ok = document.execCommand("copy"); } catch (error) { ok = false; }
      input.remove();
      if (ok) done(); else button.textContent = value;
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(value).then(done).catch(fallback);
    } else {
      fallback();
    }
  }
  document.querySelectorAll("[data-copy]").forEach(function (button) {
    button.addEventListener("click", function () { copyText(button.getAttribute("data-copy") || "", button); });
  });
  document.querySelectorAll(".shot-open").forEach(function (button) {
    button.addEventListener("click", function () {
      image.src = button.getAttribute("data-full");
      image.alt = button.getAttribute("data-alt") || "";
      dialog.showModal();
    });
  });
  document.getElementById("light-close").addEventListener("click", function () { dialog.close(); });
  dialog.addEventListener("click", function (event) { if (event.target === dialog) dialog.close(); });
})();
</script>
HTML;
    desk_close();
    exit;
}

if ($page === 'studio') {
    $old = $_SESSION['studio_old'] ?? [];
    $similar = $_SESSION['studio_similar'] ?? [];
    $made = $_SESSION['studio_created'] ?? [];
    $shownSources = $_SESSION['studio_sources'] ?? [];
    unset($_SESSION['studio_old'], $_SESSION['studio_similar'], $_SESSION['studio_created'], $_SESSION['studio_sources']);
    if (!is_array($old)) {
        $old = [];
    }
    $prefill = cms_plain((string) ($_GET['topic'] ?? ''), 180);
    if ($prefill !== '' && trim((string) ($old['topic'] ?? '')) === '') {
        $old['topic'] = $prefill;
    }
    $waiting = cms_db()->query("SELECT id, title_fa, title_en, updated_at FROM posts WHERE ai_draft = 1 AND status = 'draft' ORDER BY id DESC LIMIT 8")->fetchAll();
    $stale = [];
    $cutoff = gmdate('Y-m-d', strtotime('-18 months'));
    foreach (cms_db()->query("SELECT id, kind, title_fa, updated_at FROM posts WHERE status = 'published' ORDER BY updated_at ASC LIMIT 40") as $row) {
        if (substr((string) $row['updated_at'], 0, 10) < $cutoff) {
            $stale[] = $row;
        }
        if (count($stale) >= 5) {
            break;
        }
    }
    desk_open('استودیو مقاله', 'studio', 'موضوع را می‌نویسید. منابع PubMed پیدا می‌شود و یک پیش‌نویس آموزشی ساخته می‌شود. تا بازبینی پزشک، روی سایت نمی‌آید. تعداد مقاله‌ها به‌تنهایی رتبه یا معروف‌شدن نمی‌سازد.');
    if ($made) {
        echo '<div class="card"><h2>پیش‌نویس‌های این نوبت</h2>';
        foreach ($made as $madeId) {
            echo '<p><a href="/manage/index.php?page=edit&kind=doctor&id=' . (int) $madeId . '">باز کردن پیش‌نویس ' . (int) $madeId . '</a></p>';
        }
        echo '</div>';
    }
    if ($similar) {
        echo '<div class="card"><h2>مطلب مشابه در سایت</h2><p class="help">به‌روزرسانی معمولاً بهتر از یک مقاله تکراری است.</p>';
        foreach ($similar as $row) {
            echo '<p><a href="/manage/index.php?page=edit&kind=' . e((string) $row['kind']) . '&id=' . (int) $row['id'] . '">' . e($row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en']) . '</a> <span class="pill ' . e($row['status']) . '">' . ($row['status'] === 'published' ? 'منتشرشده' : 'پیش‌نویس') . '</span></p>';
        }
        echo '</div>';
    }
    if ($shownSources) {
        echo '<div class="card"><h2>منابعی که پیدا شد</h2>';
        foreach ($shownSources as $source) {
            echo '<p><a href="' . e((string) $source['url']) . '" rel="noopener noreferrer">' . e((string) $source['title']) . '</a> <span class="muted">' . e((string) $source['type']) . ' · ' . e((string) $source['date']) . '</span></p>';
        }
        echo '</div>';
    }
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="studio-build">';
    echo '<div class="grid-2"><label>موضوع پزشکی<input name="topic" required value="' . e((string) ($old['topic'] ?? '')) . '" placeholder="مثلاً مراقبت بعد از جراحی دندان عقل"></label>';
    echo '<label>عنوان پیشنهادی، اختیاری<input name="suggested_title" value="' . e((string) ($old['suggested_title'] ?? '')) . '"></label></div>';
    echo '<label>عبارت جست‌وجوی PubMed، انگلیسی و اختیاری<input name="search_query" dir="ltr" value="' . e((string) ($old['search_query'] ?? '')) . '" placeholder="impacted wisdom tooth recovery"></label>';
    echo '<p class="help">اگر خالی بماند و کلید هوش مصنوعی ذخیره شده باشد، از موضوع فارسی یک عبارت انگلیسی ساخته می‌شود. خود مقاله فقط از چکیده‌های پیدا شده نوشته می‌شود.</p>';
    echo '<div class="grid-2"><label>زبان<select name="lang"><option value="fa"' . (($old['lang'] ?? 'fa') === 'fa' ? ' selected' : '') . '>فارسی</option><option value="en"' . (($old['lang'] ?? '') === 'en' ? ' selected' : '') . '>English</option><option value="both"' . (($old['lang'] ?? '') === 'both' ? ' selected' : '') . '>هر دو</option></select></label>';
    echo '<label>مخاطب<input name="audience" value="' . e((string) ($old['audience'] ?? 'بیمار و همراه، پیش از تصمیم درمانی')) . '"></label></div>';
    echo '<div class="grid-2"><label>طول<select name="length"><option value="short"' . (($old['length'] ?? '') === 'short' ? ' selected' : '') . '>کوتاه</option><option value="medium"' . (($old['length'] ?? 'medium') === 'medium' ? ' selected' : '') . '>متوسط</option><option value="long"' . (($old['length'] ?? '') === 'long' ? ' selected' : '') . '>بلند</option></select></label>';
    echo '<label>دسته<input name="category" value="' . e((string) ($old['category'] ?? '')) . '" placeholder="دندان عقل"></label></div>';
    echo '<label>موضوع‌های دیگر برای همین نوبت، هر خط یکی، حداکثر دو خط اضافه<textarea name="more_topics">' . e((string) ($old['more_topics'] ?? '')) . '</textarea></label>';
    echo '<label class="checkline"><span><input type="checkbox" name="allow_similar" ' . (isset($old['allow_similar']) ? 'checked' : '') . '> اگر مطلب مشابه هست، باز هم پیش‌نویس تازه بساز</span></label>';
    echo '<button class="btn" type="submit">پیدا کردن منابع و ساخت پیش‌نویس</button></form>';
    echo '<div class="card"><h2>تقویم موضوع‌های سایت</h2><p class="help">این‌ها عنوان آماده نیستند. هر کدام را جدا باز کنید تا منبع و پیش‌نویس خودش ساخته شود.</p><div class="row">';
    foreach (cms_studio_topics() as $idea) {
        echo '<a class="btn ghost" href="/manage/index.php?page=studio&topic=' . e(rawurlencode($idea[1])) . '">' . e($idea[0]) . ' · ' . e($idea[1]) . '</a>';
    }
    echo '</div></div>';
    echo '<div class="grid-2"><div class="card"><h2>منتظر بازبینی پزشک</h2>';
    if (!$waiting) {
        echo '<p class="help">پیش‌نویس معلقی نیست.</p>';
    }
    foreach ($waiting as $row) {
        $label = $row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en'];
        echo '<p><a href="/manage/index.php?page=edit&kind=doctor&id=' . (int) $row['id'] . '">' . e($label) . '</a></p>';
    }
    echo '</div><div class="card"><h2>مطالب قدیمی برای بازبینی</h2>';
    if (!$stale) {
        echo '<p class="help">مطلب منتشرشده‌ای که بیش از هجده ماه به‌روز نشده باشد اینجا می‌آید.</p>';
    }
    foreach ($stale as $row) {
        echo '<p><a href="/manage/index.php?page=edit&kind=' . e((string) $row['kind']) . '&id=' . (int) $row['id'] . '">' . e($row['title_fa']) . '</a> <span class="muted">' . e(cms_when((string) $row['updated_at'], true)) . '</span></p>';
    }
    echo '</div></div>';
    desk_close();
    exit;
}

if ($page === 'doctor' || $page === 'briefing') {
    $stmt = cms_db()->prepare('SELECT id, title_fa, title_en, excerpt_fa, status, updated_at, slug FROM posts WHERE kind = ? ORDER BY id DESC');
    $stmt->execute([$page]);
    $rows = $stmt->fetchAll();
    $writing = $page === 'doctor';
    desk_open(
        $writing ? 'یادداشت پزشک' : 'مرور جهان',
        $page,
        $writing ? 'متنی که خودتان می‌نویسید. پیش‌نویس روی سایت نیست.' : 'خلاصه آموزشی از منبع عمومی. تا منتشر نکنید بیمار آن را نمی‌بیند.'
    );
    echo '<p class="row"><a class="btn" href="/manage/index.php?page=edit&kind=' . e($page) . '">مطلب تازه</a>';
    if ($writing) {
        echo '<a class="btn ghost" href="/manage/index.php?page=studio">ساخت پیش‌نویس از منبع</a>';
    }
    echo '</p>';
    if (!$rows) {
        echo '<div class="card welcome"><h2>' . ($writing ? 'اولین یادداشت را بنویسید' : 'هنوز مروری ساخته نشده') . '</h2>';
        echo '<p>' . ($writing ? 'یک موضوع آموزشی، مثل مراقبت بعد از دندان عقل، کافی است. عنوان و متن فارسی را پر کنید و وقتی آماده بود منتشر کنید.' : 'از منابع روز یک عنوان را به پیش‌نویس تبدیل کنید، بعد متن را خودتان ویرایش و منتشر کنید.') . '</p></div>';
    } else {
        echo '<div class="table-wrap"><table><tr><th>عنوان</th><th>وضعیت</th><th>تاریخ</th><th></th></tr>';
        foreach ($rows as $row) {
            $label = $row['title_fa'] !== '' ? $row['title_fa'] : $row['title_en'];
            echo '<tr><td><a href="/manage/index.php?page=edit&kind=' . e($page) . '&id=' . (int) $row['id'] . '">' . e($label) . '</a>';
            if ((string) $row['excerpt_fa'] !== '') {
                echo '<br><span class="muted">' . e(desk_clip((string) $row['excerpt_fa'], 90)) . '</span>';
            }
            echo '</td><td><span class="pill ' . e($row['status']) . '">' . ($row['status'] === 'published' ? 'منتشرشده' : 'پیش‌نویس') . '</span></td><td>' . e(cms_when((string) $row['updated_at'], true)) . '</td><td class="row">';
            echo '<a href="/manage/index.php?page=edit&kind=' . e($page) . '&id=' . (int) $row['id'] . '">ویرایش</a>';
            if ($row['status'] === 'published' && $row['slug'] !== '') {
                $public = $writing ? 'column' : 'briefing';
                if ($row['title_fa'] !== '') {
                    echo '<a href="/fa/' . e($public) . '/' . e($row['slug']) . '/">فارسی</a>';
                }
                if ($row['title_en'] !== '') {
                    echo '<a href="/en/' . e($public) . '/' . e($row['slug']) . '/">English</a>';
                }
            }
            echo '<form method="post" data-confirm="حذف شود؟">' . desk_csrf() . '<input type="hidden" name="action" value="delete-post"><input type="hidden" name="kind" value="' . e($page) . '"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="btn bad" type="submit">حذف</button></form>';
            echo '</td></tr>';
        }
        echo '</table></div>';
    }
    desk_close();
    exit;
}

if ($page === 'edit') {
    $kind = ($_GET['kind'] ?? '') === 'briefing' ? 'briefing' : 'doctor';
    $id = (int) ($_GET['id'] ?? 0);
    $row = [
        'slug' => '', 'status' => 'draft', 'title_fa' => '', 'title_en' => '', 'excerpt_fa' => '', 'excerpt_en' => '',
        'body_fa' => '', 'body_en' => '', 'seo_title_fa' => '', 'seo_title_en' => '', 'seo_description_fa' => '', 'seo_description_en' => '',
        'source_name' => '', 'source_url' => '', 'source_date' => '', 'cover' => '', 'category' => '', 'featured' => 0,
    ];
    if ($id > 0) {
        $stmt = cms_db()->prepare('SELECT * FROM posts WHERE id = ? AND kind = ?');
        $stmt->execute([$id, $kind]);
        $found = $stmt->fetch();
        if (!$found) {
            cms_flash('bad', 'این مطلب پیدا نشد.');
            desk_redirect('page=' . $kind);
        }
        $row = $found;
    }
    if (isset($_SESSION['old']) && is_array($_SESSION['old'])) {
        $row = array_merge($row, $_SESSION['old']);
        unset($_SESSION['old']);
    }
    $media = cms_db()->query('SELECT path, alt_fa FROM media ORDER BY id DESC LIMIT 24')->fetchAll();
    $coverNow = (string) ($row['cover'] ?? '');
    $coverSeen = array_column($media, 'path');
    if ($coverNow !== '' && !in_array($coverNow, $coverSeen, true)) {
        array_unshift($media, ['path' => $coverNow, 'alt_fa' => 'جلد فعلی']);
    }
    desk_open($id ? 'ویرایش مطلب' : 'مطلب تازه', $kind, 'پیش‌نویس را بیمار نمی‌بیند. برای گوگل، عنوان حدود ۵۰ تا ۶۰ حرف و شرح حدود ۱۲۰ تا ۱۶۰ حرف مناسب است.');
    $studioSources = json_decode((string) ($row['sources_json'] ?? ''), true);
    if ((int) ($row['ai_draft'] ?? 0) === 1) {
        echo '<section class="card"><h2>منابع و بازبینی</h2>';
        echo '<p class="help">' . e((string) ($row['studio_note'] ?? '')) . '</p>';
        if (is_array($studioSources)) {
            echo '<ul class="source-list">';
            foreach ($studioSources as $source) {
                if (!is_array($source) || empty($source['url'])) {
                    continue;
                }
                echo '<li><a href="' . e((string) $source['url']) . '" rel="noopener noreferrer">' . e((string) ($source['title'] ?? $source['url'])) . '</a>';
                echo '<span class="muted"> ' . e((string) ($source['publisher'] ?? '')) . ' · ' . e((string) ($source['date'] ?? '')) . ' · ' . e((string) ($source['type'] ?? '')) . '</span></li>';
            }
            echo '</ul>';
        }
        echo '<p class="help">جلد عوض نشده است. اگر تصویری مناسب بود، بعداً از گالری انتخابش کنید. پرامپت زیر فقط برای ساخت تصویر بعدی است.</p>';
        echo '</section>';
    }
    echo '<form method="post" class="composer">' . desk_csrf() . '<input type="hidden" name="action" value="save-post"><input type="hidden" name="kind" value="' . e($kind) . '"><input type="hidden" name="id" value="' . (int) $id . '">';
    echo '<div class="write">';
    echo '<div class="tools"><button type="button" data-insert="## ">عنوان بخش</button><button type="button" data-insert="### ">عنوان کوچک</button><button type="button" data-wrap="**">ضخیم</button><button type="button" data-insert="- ">فهرست</button><button type="button" data-preview="body_fa">پیش‌نمایش فارسی</button></div>';
    echo '<p class="help">خط خالی پاراگراف می‌سازد. پیوند بیرونی: [متن](https://...) و پیوند داخلی: [جراحی دندان عقل](/fa/services/wisdom-tooth-surgery/)</p>';
    echo '<div class="grid-2"><label>عنوان فارسی<input name="title_fa" value="' . e((string) $row['title_fa']) . '"></label><label>English title<input name="title_en" dir="ltr" data-slug-from value="' . e((string) $row['title_en']) . '"></label></div>';
    echo '<div class="grid-2"><label>خلاصه فارسی، همان چیزی که در فهرست دیده می‌شود<textarea name="excerpt_fa">' . e((string) $row['excerpt_fa']) . '</textarea></label><label>English excerpt<textarea name="excerpt_en">' . e((string) $row['excerpt_en']) . '</textarea></label></div>';
    echo '<label>متن فارسی<textarea class="paper" name="body_fa" id="body_fa">' . e((string) $row['body_fa']) . '</textarea></label>';
    echo '<div id="preview" class="preview" hidden></div>';
    echo '<label>English body<textarea class="paper" name="body_en" id="body_en">' . e((string) $row['body_en']) . '</textarea></label>';
    echo '</div><aside class="publish card">';
    echo '<h2>انتشار</h2>';
    echo '<label>وضعیت<select name="status"><option value="draft"' . ($row['status'] === 'draft' ? ' selected' : '') . '>پیش‌نویس، روی سایت نیست</option><option value="published"' . ($row['status'] === 'published' ? ' selected' : '') . '>منتشر شود</option></select></label>';
    echo '<label><span><input type="checkbox" name="featured" ' . ((int) ($row['featured'] ?? 0) === 1 ? 'checked' : '') . '> مطلب شاخص فهرست</span></label>';
    echo '<label>نامک انگلیسی، در آدرس صفحه<input name="slug" id="slug" dir="ltr" value="' . e((string) $row['slug']) . '" placeholder="wisdom-tooth-care"></label>';
    echo '<label>دسته<input name="category" value="' . e((string) ($row['category'] ?? '')) . '" placeholder="دندان عقل"></label>';
    echo '<p class="help">جلد</p><div class="covers"><label class="cover"><input type="radio" name="cover" value="" ' . (($row['cover'] ?? '') === '' ? 'checked' : '') . '><span>بدون تصویر</span></label>';
    foreach ($media as $item) {
        $checked = ($row['cover'] ?? '') === $item['path'] ? ' checked' : '';
        echo '<label class="cover"><input type="radio" name="cover" value="' . e($item['path']) . '"' . $checked . '><img src="' . e($item['path']) . '" alt="' . e((string) $item['alt_fa']) . '"></label>';
    }
    if (!$media) {
        echo '<p class="help">اول در گالری یک عکس بگذارید.</p>';
    }
    echo '</div>';
    echo '<h2>جستجو</h2>';
    echo '<label>عنوان گوگل، فارسی<input data-meter="title" data-empty="اگر خالی بماند، عنوان مطلب استفاده می‌شود." name="seo_title_fa" value="' . e((string) $row['seo_title_fa']) . '"></label><p class="meter"></p>';
    echo '<label>شرح گوگل، فارسی<textarea data-meter="description" data-empty="اگر خالی بماند، خلاصه مطلب استفاده می‌شود." name="seo_description_fa">' . e((string) $row['seo_description_fa']) . '</textarea></label><p class="meter"></p>';
    echo '<label>Google title, English<input data-meter="title" data-empty="If empty, the article title is used." name="seo_title_en" dir="ltr" value="' . e((string) $row['seo_title_en']) . '"></label><p class="meter"></p>';
    echo '<label>Google description<textarea data-meter="description" data-empty="If empty, the excerpt is used." name="seo_description_en">' . e((string) $row['seo_description_en']) . '</textarea></label><p class="meter"></p>';
    if ($kind === 'briefing') {
        echo '<h2>منبع</h2><label>نام منبع<input name="source_name" value="' . e((string) $row['source_name']) . '"></label>';
        echo '<label>نشانی https<input name="source_url" dir="ltr" value="' . e((string) $row['source_url']) . '"></label>';
        echo '<label>تاریخ منبع<input name="source_date" value="' . e((string) $row['source_date']) . '"></label>';
    }
    if ((int) ($row['ai_draft'] ?? 0) === 1) {
        echo '<label>پرامپت تصویر این سایت<textarea id="image-prompt" name="image_prompt">' . e((string) ($row['image_prompt'] ?? '')) . '</textarea></label>';
        echo '<p><button type="button" class="btn ghost" data-copy-from="image-prompt">کپی پرامپت تصویر</button></p>';
        $reviewedOn = (int) ($row['physician_review'] ?? 0) === 1 || (string) ($row['physician_review'] ?? '') === 'on';
        echo '<label class="checkline"><span><input type="checkbox" name="physician_review" ' . ($reviewedOn ? 'checked' : '') . '> این پیش‌نویس را به‌عنوان پزشک بازبینی کردم. بدون این تأیید منتشر نمی‌شود.</span></label>';
    }
    echo '<p class="row"><button class="btn" type="submit">ذخیره</button>';
    if ($id && $row['status'] === 'published' && $row['slug'] !== '') {
        $public = $kind === 'doctor' ? 'column' : 'briefing';
        if ((string) $row['title_fa'] !== '') {
            echo '<a class="btn ghost" href="/fa/' . e($public) . '/' . e((string) $row['slug']) . '/">دیدن فارسی</a>';
        }
        if ((string) $row['title_en'] !== '') {
            echo '<a class="btn ghost" href="/en/' . e($public) . '/' . e((string) $row['slug']) . '/">English</a>';
        }
    }
    echo '</p></aside></form>';
    desk_close();
    exit;
}

if ($page === 'appointments') {
    desk_page_appointments($owner);
    exit;
}

if ($page === 'report') {
    desk_page_report();
    exit;
}

if ($page === 'patients') {
    desk_page_patients();
    exit;
}

if ($page === 'schedule') {
    desk_page_schedule();
    exit;
}

if ($page === 'messaging') {
    desk_page_messaging();
    exit;
}

if ($page === 'messages') {
    $kind = 'contact';
    $q = cms_plain((string) ($_GET['q'] ?? ''), 80);
    $sql = 'SELECT * FROM leads WHERE kind = ?';
    $args = [$kind];
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $sql .= " AND (name LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\')";
        $args[] = $like;
        $args[] = $like;
    }
    $sql .= ' ORDER BY id DESC LIMIT 100';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute($args);
    desk_open('پیام‌ها', 'messages', 'پیام فرم تماس اینجاست. نوبت‌های کددار در بخش نوبت‌ها هستند.');
    echo '<form class="row" method="get"><input type="hidden" name="page" value="messages"><input name="q" value="' . e($q) . '" placeholder="نام یا تلفن"><button class="btn ghost" type="submit">جستجو</button></form>';
    echo '<div class="table-wrap"><table><tr><th>زمان</th><th>نام</th><th>تلفن</th><th>جزئیات</th><th>پیگیری</th></tr>';
    $found = false;
    foreach ($stmt as $lead) {
        $found = true;
        echo '<tr><td>' . e(cms_when((string) $lead['created_at'], true)) . '</td><td>' . e($lead['name']) . '</td><td dir="ltr">' . e($lead['phone']) . '</td><td>';
        if ($lead['service'] !== '') {
            echo e(desk_services()[$lead['service']] ?? $lead['service']) . '<br>';
        }
        if ($lead['preferred_at'] !== '') {
            echo e($lead['preferred_at']) . '<br>';
        }
        echo e($lead['message']) . '</td><td><form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-lead"><input type="hidden" name="id" value="' . (int) $lead['id'] . '"><input type="hidden" name="back" value="' . e($page) . '">';
        echo '<select name="status">';
        foreach (['new' => 'تازه', 'read' => 'خوانده‌شده', 'contacted' => 'تماس گرفته شد', 'booked' => 'نوبت شد', 'closed' => 'بسته'] as $value => $label) {
            echo '<option value="' . e($value) . '"' . ($lead['status'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        echo '</select><textarea class="note" name="note" placeholder="یادداشت داخلی، بیمار این را نمی‌بیند">' . e($lead['note']) . '</textarea><button class="btn" type="submit">ذخیره</button></form></td></tr>';
    }
    if (!$found) {
        echo '<tr><td colspan="5">درخواستی نیست.</td></tr>';
    }
    echo '</table></div>';
    desk_close();
    exit;
}

if ($page === 'seo') {
    $saved = [];
    foreach (cms_db()->query('SELECT * FROM seo') as $row) {
        $saved[$row['path']][$row['locale']] = $row;
    }
    $paths = cms_db()->query('SELECT path FROM media ORDER BY id DESC')->fetchAll();
    $token = cms_google_token(cms_setting('google_verify'));
    $sitemap = cms_origin() . '/sitemap.xml';
    $liveMap = cms_origin() . '/cms/sitemap.php';
    desk_open('سئو و گوگل', 'seo', 'جای خالی عنوان فعلی سایت را نگه می‌دارد. نتیجه درمان را در عنوان جستجو وعده ندهید.');
    echo '<section class="card welcome"><h2>وصل شدن به گوگل</h2><ol class="steps"><li>در <a href="https://search.google.com/search-console" rel="noopener noreferrer">Google Search Console</a> دامنه را با روش HTML tag اضافه کنید.</li><li>کد داخل content را اینجا بگذارید و ذخیره کنید. همان کد در صفحات فارسی، انگلیسی، یادداشت و گالری قرار می‌گیرد.</li><li>بعد از تأیید، هر دو نقشه را در بخش Sitemaps ثبت کنید.</li></ol>';
    echo '<p class="row"><button type="button" class="btn ghost" data-copy="' . e($sitemap) . '">کپی نقشه اصلی</button><button type="button" class="btn ghost" data-copy="' . e($liveMap) . '">کپی نقشه یادداشت‌ها</button></p>';
    echo '<p class="help" dir="ltr">' . e($sitemap) . '<br>' . e($liveMap) . '</p>';
    if ($owner) {
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-google">';
        echo '<label>کد تأیید گوگل<input name="google_verify" dir="ltr" value="' . e($token) . '" placeholder="content داخل برچسب HTML"></label>';
        echo '<button class="btn" type="submit">' . ($token !== '' ? 'به‌روزرسانی کد' : 'ذخیره کد گوگل') . '</button></form>';
        echo '<p class="help">' . ($token !== '' ? 'کد ذخیره شده و روی صفحات عمومی هست.' : 'هنوز کدی ذخیره نشده. تا این کد نباشد گوگل مالکیت دامنه را از این سایت تأیید نمی‌کند.') . '</p>';
    } else {
        echo '<p class="help">ذخیره کد گوگل فقط برای مالک میز است. وضعیت فعلی: ' . ($token !== '' ? 'وصل شده' : 'هنوز وصل نشده') . '.</p>';
    }
    echo '</section>';
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-seo"><datalist id="paths">';
    foreach ($paths as $item) {
        echo '<option value="' . e($item['path']) . '">';
    }
    echo '</datalist>';
    foreach (cms_seo_catalog() as $row) {
        $path = $row[0];
        echo '<div class="card"><strong>' . e($row[1]) . '</strong> <span class="muted" dir="ltr">' . e($path) . '</span>';
        echo '<p class="help">تصویر اشتراک خالی بماند تا پرترهٔ پزشک، /visuals/doctor-portrait.webp، استفاده شود. مسیر تازه باید با /visuals/ یا /cms/media/ شروع شود.</p>';
        if ($path === '/appointment/') {
            echo '<p class="help">شرح این صفحه باید هم فرم همین سایت را بگوید و هم لینک نوبت‌ایر و پلتفرم‌های دیگر را. نوبت بعد از تماس مطب قطعی می‌شود. فیلد خالی شرح پیش‌فرض را نگه می‌دارد.</p>';
        }
        echo '<div class="grid-2">';
        foreach (['fa' => [$row[2], $row[3]], 'en' => [$row[4], $row[5]], 'ar' => [$row[6] ?? '', $row[7] ?? ''], 'zh' => [$row[8] ?? '', $row[9] ?? '']] as $locale => $defaults) {
            $item = $saved[$path][$locale] ?? ['title' => '', 'description' => '', 'robots' => '', 'og_image' => ''];
            echo '<div><label>عنوان ' . e($locale) . '<input data-meter="title" name="title[' . e($path) . '][' . e($locale) . ']" value="' . e($item['title']) . '" placeholder="' . e($defaults[0]) . '"></label><p class="meter"></p>';
            echo '<label>شرح<textarea data-meter="description" name="description[' . e($path) . '][' . e($locale) . ']" placeholder="' . e($defaults[1]) . '">' . e($item['description']) . '</textarea></label><p class="meter"></p>';
            echo '<label>ربات<select name="robots[' . e($path) . '][' . e($locale) . ']"><option value="">در گوگل دیده شود</option><option value="noindex"' . ($item['robots'] === 'noindex' ? ' selected' : '') . '>از گوگل پنهان شود</option></select></label>';
            echo '<label>تصویر اشتراک<input dir="ltr" list="paths" name="og[' . e($path) . '][' . e($locale) . ']" value="' . e($item['og_image']) . '" placeholder="/visuals/doctor-portrait.webp"></label></div>';
        }
        echo '</div></div>';
    }
    echo '<button class="btn" type="submit">ذخیره سئو صفحات</button></form>';
    desk_close();
    exit;
}

if ($page === 'geo') {
    $keys = ['geo_name', 'geo_street', 'geo_city', 'geo_region', 'geo_postal', 'geo_country', 'geo_phone', 'geo_hours', 'geo_lat', 'geo_lng'];
    desk_open('موقعیت مطب', 'geo', 'فقط نشانی و تلفنی را بنویسید که خود مطب تأیید کرده است. تا تأیید نکنید، روی سایت و در نقشه گوگل منتشر نمی‌شود.');
    echo '<div class="card"><p>این نشانی فقط وقتی روی سایت و در نقشه گوگل می‌آید که خودتان تأیید کنید. تلفن و آدرسی را بنویسید که مطب همان را قبول دارد. نام پزشک، تخصص، دو تلفن و نشانی عمومی از متن‌های سایت می‌آیند. با تأیید، خیابان، شهر، ساعت ساخت‌یافته و مختصات به همان داده اضافه می‌شود.</p></div>';
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-geo">';
    $labels = [
        'geo_name' => 'نام مکان',
        'geo_street' => 'خیابان و پلاک',
        'geo_city' => 'شهر',
        'geo_region' => 'استان، مثل IR-23',
        'geo_postal' => 'کد پستی',
        'geo_country' => 'کشور',
        'geo_phone' => 'تلفن تأییدشده',
        'geo_hours' => 'ساعت، مثل Sa-We 16:00-20:00',
        'geo_lat' => 'عرض جغرافیایی',
        'geo_lng' => 'طول جغرافیایی',
    ];
    foreach ($labels as $key => $label) {
        echo '<label>' . e($label) . '<input name="' . e($key) . '" value="' . e(cms_setting($key)) . '"></label>';
    }
    echo '<label><span><input type="checkbox" name="geo_confirmed" ' . (cms_setting('geo_confirmed') === '1' ? 'checked' : '') . '> این نشانی را روی صفحات سایت برای موتور جستجو منتشر کن</span></label>';
    echo '<button class="btn" type="submit">ذخیره</button></form>';
    desk_close();
    exit;
}

if ($page === 'users') {
    desk_open('کاربران', 'users', 'پرستار دفتر تماس و تیک پیش از ورود را دارد و برگه، دارو، رمز و حذف را ندارد. ویرایشگر متن و پرونده را می‌بیند. تنظیمات فقط برای مالک است.');
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-user"><input type="hidden" name="id" value="0">';
    echo '<div class="grid-2"><label>نام کاربری<input name="username" dir="ltr" required></label><label>نام نمایشی<input name="display_name"></label></div>';
    echo '<div class="grid-2"><label>رمز<input type="password" name="password" required></label><label>نقش<select name="role"><option value="editor">ویرایشگر</option><option value="nurse">پرستار</option><option value="owner">مالک</option></select></label></div>';
    echo '<label><span><input type="checkbox" name="active" checked> فعال</span></label><button class="btn" type="submit">کاربر تازه</button></form>';
    echo '<div class="table-wrap"><table><tr><th>کاربر</th><th>آخرین ورود</th><th>ویرایش</th></tr>';
    foreach (cms_db()->query('SELECT * FROM admins ORDER BY id') as $account) {
        $roleLabel = ['owner' => 'مالک', 'editor' => 'ویرایشگر', 'nurse' => 'پرستار'][(string) $account['role']] ?? 'ویرایشگر';
        echo '<tr><td>' . e($account['username']) . '<br><span class="pill ' . ($account['role'] === 'owner' ? 'published' : 'draft') . '">' . e($roleLabel) . '</span>';
        if ((int) $account['active'] !== 1) {
            echo ' <span class="pill">غیرفعال</span>';
        }
        echo '</td><td>' . e(cms_when((string) $account['last_login'], true)) . '</td><td><form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-user"><input type="hidden" name="id" value="' . (int) $account['id'] . '">';
        echo '<input name="username" dir="ltr" value="' . e($account['username']) . '">';
        echo '<input name="display_name" value="' . e((string) $account['display_name']) . '" placeholder="نام نمایشی">';
        echo '<select name="role"><option value="owner"' . ($account['role'] === 'owner' ? ' selected' : '') . '>مالک</option><option value="editor"' . ($account['role'] === 'editor' ? ' selected' : '') . '>ویرایشگر</option><option value="nurse"' . ($account['role'] === 'nurse' ? ' selected' : '') . '>پرستار</option></select>';
        echo '<label><span><input type="checkbox" name="active" ' . ((int) $account['active'] === 1 ? 'checked' : '') . '> فعال</span></label>';
        echo '<input type="password" name="password" placeholder="رمز تازه، اگر لازم است">';
        echo '<button class="btn" type="submit">ذخیره</button></form></td></tr>';
    }
    echo '</table></div>';
    desk_close();
    exit;
}

if ($page === 'log') {
    $level = (string) ($_GET['level'] ?? '');
    $area = (string) ($_GET['area'] ?? '');
    if (!in_array($level, ['', 'info', 'warn', 'error'], true)) {
        $level = '';
    }
    if (!preg_match('/^[a-z0-9_-]{0,20}$/', $area)) {
        $area = '';
    }
    $levelLabel = ['info' => 'اطلاع', 'warn' => 'هشدار', 'error' => 'خطا'];
    $areaLabel = ['ai' => 'مدل', 'pubmed' => 'جست‌وجو', 'studio' => 'مقاله', 'php' => 'برنامه'];
    desk_open('گزارش سیستم', 'log', 'بخش بالا برای برنامه‌نویس است: کدام مدل جواب داد، چرا رد شد، و خطای PHP در کدام فایل بود. کلید API و متن مقاله اینجا ذخیره نمی‌شود.');
    echo '<div class="groups">';
    echo '<a class="' . ($level === '' && $area === '' ? 'active' : '') . '" href="/manage/index.php?page=log">همه</a>';
    foreach ($levelLabel as $key => $label) {
        echo '<a class="' . ($level === $key ? 'active' : '') . '" href="/manage/index.php?page=log&level=' . e($key) . '">' . e($label) . '</a>';
    }
    foreach ($areaLabel as $key => $label) {
        echo '<a class="' . ($area === $key ? 'active' : '') . '" href="/manage/index.php?page=log&area=' . e($key) . '">' . e($label) . '</a>';
    }
    echo '</div>';
    $sql = 'SELECT * FROM system_log WHERE 1 = 1';
    $params = [];
    if ($level !== '') {
        $sql .= ' AND level = ?';
        $params[] = $level;
    }
    if ($area !== '') {
        $sql .= ' AND area = ?';
        $params[] = $area;
    }
    $sql .= ' ORDER BY id DESC LIMIT 200';
    $technical = cms_db()->prepare($sql);
    $technical->execute($params);
    echo '<div class="table-wrap"><table><tr><th>زمان</th><th>سطح</th><th>بخش</th><th>چه شد</th></tr>';
    $shown = 0;
    foreach ($technical as $row) {
        $shown++;
        $context = json_decode((string) $row['context'], true);
        $bits = [];
        if (is_array($context)) {
            foreach (['model', 'code', 'ms', 'cooldown', 'file', 'line', 'type', 'trace', 'post', 'query', 'skipped'] as $key) {
                if (($context[$key] ?? '') !== '') {
                    $bits[] = $key . '=' . $context[$key];
                }
            }
        }
        echo '<tr><td>' . e(cms_when((string) $row['created_at'], true)) . '</td>';
        echo '<td><span class="pill ' . e((string) $row['level']) . '">' . e($levelLabel[$row['level']] ?? (string) $row['level']) . '</span></td>';
        echo '<td>' . e($areaLabel[$row['area']] ?? (string) $row['area']) . '<br><span class="muted">' . e((string) $row['event']) . '</span></td>';
        echo '<td>' . e((string) $row['message']);
        if ($bits) {
            echo '<div class="log-context">' . e(implode('  ', $bits)) . '</div>';
        }
        echo '</td></tr>';
    }
    if ($shown === 0) {
        echo '<tr><td colspan="4">هنوز رویداد فنی ثبت نشده است.</td></tr>';
    }
    echo '</table></div>';
    echo '<h2>کارهای کاربران</h2><p class="help">ورود، ذخیره، انتشار و حذف. دویست رویداد آخر.</p>';
    echo '<div class="table-wrap"><table><tr><th>زمان</th><th>کاربر</th><th>کار</th><th>موضوع</th></tr>';
    foreach (cms_db()->query('SELECT * FROM audit ORDER BY id DESC LIMIT 200') as $log) {
        echo '<tr><td>' . e(cms_when((string) $log['created_at'], true)) . '</td><td>' . e($log['username']) . '</td><td>' . e(desk_action_label((string) $log['action'])) . '</td><td>' . e($log['target']) . ' ' . e($log['detail']) . '</td></tr>';
    }
    echo '</table></div>';
    desk_close();
    exit;
}

if ($page === 'feeds') {
    $feeds = cms_feeds_view(isset($_GET['refresh']));
    desk_open('منابع روز', 'feeds');
    echo '<p class="help">WHO و NIH از بعضی سرورها باز نمی‌شوند. می‌توانید عنوان و نشانی را خودتان بچسبانید. نتیجه همیشه پیش‌نویس است.</p>';
    echo '<p class="row"><a class="btn ghost" href="/manage/index.php?page=feeds&refresh=1">تازه‌کردن منابع</a></p>';
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="import-one">';
    echo '<div class="grid-2"><label>عنوان<input name="title" required></label><label>نشانی https<input name="url" dir="ltr" required></label></div>';
    echo '<div class="grid-2"><label>نام منبع<input name="source_name" value="منبع عمومی"></label><label>تاریخ<input name="date"></label></div>';
    echo '<label>یادداشت کوتاه<textarea name="summary"></textarea></label>';
    echo '<label><span><input type="checkbox" name="with_ai"> اگر کلید هوش مصنوعی ذخیره شده، پیش‌نویس دوزبانه بساز</span></label>';
    echo '<button class="btn" type="submit">ساخت پیش‌نویس</button></form>';
    foreach ($feeds as $feed) {
        echo '<div class="card"><h2>' . e((string) ($feed['name'] ?? '')) . '</h2>';
        if (($feed['error'] ?? '') !== '') {
            echo '<p class="flash bad">این منبع الان باز نشد.</p>';
        }
        foreach ($feed['items'] ?? [] as $item) {
            echo '<form method="post" class="row">' . desk_csrf() . '<input type="hidden" name="action" value="import-one">';
            echo '<input type="hidden" name="title" value="' . e($item['title']) . '"><input type="hidden" name="url" value="' . e($item['url']) . '">';
            echo '<input type="hidden" name="source_name" value="' . e($item['name']) . '"><input type="hidden" name="date" value="' . e($item['date']) . '">';
            echo '<input type="hidden" name="summary" value="' . e($item['summary']) . '">';
            echo '<span>' . e($item['title']) . '</span><button class="btn ghost" type="submit">پیش‌نویس</button></form>';
        }
        echo '</div>';
    }
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="import"><label><span><input type="checkbox" name="with_ai"> با هوش مصنوعی، حداکثر سه پیش‌نویس</span></label><button class="btn" type="submit">ورود گروهی</button></form>';
    desk_close();
    exit;
}

if ($page === 'look') {
    $look = cms_look();
    $labels = [
        'hero' => 'شروع صفحه: نام و پرتره',
        'intro' => 'رویکرد درمان',
        'treatments' => 'درمان‌ها',
        'surgeon' => 'سوابق کوتاه',
        'reviews' => 'نظرات',
        'journal' => 'مجله',
        'implant' => 'ایمپلنت دیجیتال',
        'story' => 'آموزش تصویری',
        'anatomy' => 'آناتومی',
        'contact' => 'تماس',
    ];
    desk_open('چیدمان و حرکت', 'look', 'این انتخاب‌ها روی همه صفحه‌های عمومی اعمال می‌شود. صفحه اول را تازه کنید تا نتیجه را ببینید.');
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-look">';
    echo '<div class="card"><h2>حرکت</h2>';
    echo '<label>میزان حرکت<select name="look_motion">';
    foreach (['quiet' => 'آرام، پیشنهادی', 'gentle' => 'نرم‌تر و بلندتر', 'off' => 'بدون حرکت'] as $value => $label) {
        echo '<option value="' . e($value) . '"' . ($look['motion'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label>';
    echo '<p class="help">آرام، چرخ ماوس را کوتاه نرم می‌کند. نرم‌تر، همان اسکرول را کمی بلندتر می‌کند. بدون حرکت، پرش صفحه فوری است. لمس انگشت در هر سه حالت اسکرول خود گوشی است.</p>';
    echo '<label>روایت تصویری<select name="look_story">';
    foreach (['sticky' => 'تصویر می‌ماند و با متن عوض می‌شود، پیشنهادی', 'stacked' => 'هر فصل با عکس خودش، زیر هم'] as $value => $label) {
        echo '<option value="' . e($value) . '"' . ($look['story'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label>';
    echo '<p class="help">در حالت پیشنهادی، تصویر فک کنار متن می‌ماند و با رسیدن به هر فصل نرم عوض می‌شود. صفحه قفل نمی‌شود. «بدون حرکت» همان چیدمان را بدون محو شدن تصویر نشان می‌دهد. فهرست زیر هم برای کسی است که هر فصل را جدا می‌خواهد.</p></div>';
    echo '<div class="card"><h2>صفحه اول</h2>';
    echo '<label>تصویر شروع<select name="look_hero">';
    foreach (['portrait' => 'پرتره پزشک، پیشنهادی', 'studio' => 'تصویر آموزشی فک'] as $value => $label) {
        echo '<option value="' . e($value) . '"' . ($look['hero'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label>';
    echo '<p class="help">پرتره همان اول اعتماد می‌سازد. تصویر فک آموزشی است و اگر بخواهید می‌تواند جای پرتره را بگیرد.</p>';
    echo '<label>پایان صفحه اول<select name="look_contact">';
    foreach (['band' => 'نوار کوتاه آدرس و دکمه‌ها، پیشنهادی', 'full' => 'فرم کامل پیام، همان صفحهٔ تماس'] as $value => $label) {
        echo '<option value="' . e($value) . '"' . ($look['contact'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label>';
    echo '<p class="help">نوار کوتاه آدرس، ساعت و دو دکمه را نشان می‌دهد. فرم کامل همان فرم صفحهٔ تماس است و صفحهٔ اول را بلند می‌کند.</p></div>';
    echo '<div class="card"><h2>مطالب</h2>';
    echo '<label>نحوه نمایش<select name="look_articles">';
    foreach (['cards' => 'کارت', 'rows' => 'ردیف'] as $value => $label) {
        echo '<option value="' . e($value) . '"' . ($look['articles'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label>';
    echo '<p class="help">کارت برای مرور سریع است. ردیف برای خواندن عنوان‌ها زیر هم.</p></div>';
    echo '<div class="card"><h2>ترتیب بخش‌های صفحه اول</h2><div class="table-wrap"><table><tr><th>بخش</th><th>ترتیب</th><th>نمایش</th></tr>';
    foreach ($look['home'] as $index => $id) {
        echo '<tr><td>' . e($labels[$id]) . '</td><td><select name="order[' . e($id) . ']">';
        for ($n = 1; $n <= count($labels); $n++) {
            echo '<option value="' . $n . '"' . ($n === $index + 1 ? ' selected' : '') . '>' . cms_fa_digits((string) $n) . '</option>';
        }
        echo '</select></td><td>';
        if ($id === 'hero') {
            echo 'همیشه';
        } else {
            $shown = !in_array($id, $look['hide'], true);
            echo '<label><span><input type="checkbox" name="show[' . e($id) . ']"' . ($shown ? ' checked' : '') . '> دیده شود</span></label>';
        }
        echo '</td></tr>';
    }
    echo '</table></div></div>';
    echo '<button class="btn" type="submit">ذخیره چیدمان</button></form>';
    desk_close();
    exit;
}

if ($page === 'settings') {
    desk_open('تنظیمات', 'settings');
    $cron = cms_origin() . '/manage/cron.php?token=' . cms_setting('cron_token');
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-settings">';
    echo '<label>آدرس OpenRouter<input name="openai_base" dir="ltr" value="' . e(cms_setting('openai_base', 'https://openrouter.ai/api/v1')) . '"></label>';
    echo '<label>کلید OpenRouter<input type="password" name="openai_key" autocomplete="off" placeholder="sk-or-v1-..."></label>';
    echo '<p class="help">' . (cms_setting('openai_key') !== '' ? 'یک کلید ذخیره شده است و اینجا دوباره نشان داده نمی‌شود.' : 'کلید را از openrouter.ai/settings/keys بگیرید و اینجا ذخیره کنید.') . ' بعد از ذخیره کلید، میز خودش بین مدل‌های رایگان سالم جابه‌جا می‌شود. اگر مدلی محدود شود، تا تمام شدن محدودیت کنار گذاشته می‌شود و مدل بعدی همان لحظه کار را ادامه می‌دهد. نتیجه پیش‌نویس است و تا بازبینی پزشک منتشر نمی‌شود.</p>';
    echo '<label><span><input type="checkbox" name="clear_key"> حذف کلید ذخیره‌شده</span></label>';
    echo '<button class="btn" type="submit">ذخیره</button></form>';
    echo '<div class="card"><h2>انتخاب خودکار مدل</h2><div class="table-wrap"><table><tr><th>مدل</th><th>وضعیت</th></tr>';
    foreach (cms_ai_status() as $model) {
        echo '<tr><td>' . e($model['label']) . '</td><td><span class="pill ' . e($model['tone']) . '">' . e($model['state']) . '</span></td></tr>';
    }
    echo '</table></div><p class="help">این فهرست را دستی انتخاب نمی‌کنید. میز مدلی را اول می‌زند که آخرین بار جواب داده و الان محدود نیست.</p></div>';
    echo '<div class="card slip"><h2>نسخه پشتیبان پایگاه</h2>';
    echo '<p class="help">قبل از هر آپدیت یک نسخه بگیرید. فایل کنار خود پایگاه می‌ماند و از وب باز نمی‌شود. هفت نسخه آخر می‌ماند. فهرست نوبت جداست و یادداشت و علت مراجعه را ندارد.</p>';
    echo '<div class="row">';
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="backup-database"><button class="btn" type="submit">ساختن نسخه</button></form>';
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="download-database"><button class="btn ghost" type="submit">دانلود نسخه تازه</button></form>';
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="export-book"><button class="btn ghost" type="submit">فهرست نوبت، بدون یادداشت</button></form>';
    echo '</div>';
    $copies = cms_backup_list();
    if ($copies) {
        echo '<div class="rail">';
        foreach ($copies as $copy) {
            echo '<p><span dir="ltr">' . e($copy['name']) . '</span> · ' . e(cms_fa_digits((string) max(1, (int) round($copy['bytes'] / 1024)))) . ' کیلوبایت</p>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '<div class="card"><p>نشانی زمان‌بندی، فقط پیش‌نویس می‌سازد و رمز میز نیست. آن را جایی عمومی نگذارید.</p><input dir="ltr" readonly value="' . e($cron) . '"><p class="row"><button type="button" class="btn ghost" data-copy="' . e($cron) . '">کپی نشانی</button></p></div>';
    desk_close();
    exit;
}

if ($page === 'account') {
    desk_open('حساب من', 'account', 'نام نمایشی روی خانه میز دیده می‌شود. برای هر تغییر، رمز فعلی لازم است.');
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-account">';
    echo '<label>نام نمایشی<input name="display_name" value="' . e((string) $user['display_name']) . '"></label>';
    echo '<label>رمز فعلی<input type="password" name="current_password" required></label>';
    echo '<label>رمز تازه، اگر می‌خواهید عوض شود<input type="password" name="new_password"></label>';
    echo '<button class="btn" type="submit">ذخیره</button></form>';
    desk_close();
    exit;
}

desk_open('این صفحه نیست', 'dashboard');
echo '<div class="card"><p>این بخش در میز مطب وجود ندارد.</p><p><a class="btn" href="/manage/index.php">برگشت به خانه میز</a></p></div>';
desk_close();
