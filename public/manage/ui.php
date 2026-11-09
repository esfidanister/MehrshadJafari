<?php
declare(strict_types=1);

function desk_redirect(string $query = ''): void
{
    header('Location: /manage/index.php' . ($query !== '' ? '?' . $query : ''), true, 303);
    exit;
}

function desk_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(cms_csrf()) . '">';
}

function desk_nav(string $active, bool $owner): void
{
    if (cms_nurse()) {
        $groups = [
            'امروز' => [
                'dashboard' => 'خانه میز',
                'appointments' => 'نوبت‌ها',
                'patients' => 'پرونده‌ها',
            ],
            'مطب' => [
                'account' => 'حساب من',
            ],
        ];
        echo '<nav class="nav">';
        foreach ($groups as $name => $items) {
            echo '<p class="nav-label">' . e($name) . '</p>';
            foreach ($items as $key => $label) {
                $class = $key === $active ? ' class="active"' : '';
                echo '<a' . $class . ' href="/manage/index.php?page=' . e($key) . '">' . e($label) . '</a>';
            }
        }
        echo '</nav>';
        return;
    }
    $groups = [
        'امروز' => [
            'dashboard' => 'خانه میز',
            'appointments' => 'نوبت‌ها',
            'report' => 'گزارش ماه',
            'patients' => 'پرونده‌ها',
            'schedule' => 'برنامه مطب',
            'messaging' => 'پیام‌رسانی',
            'messages' => 'پیام‌ها',
        ],
        'نوشتن' => [
            'studio' => 'استودیو مقاله',
            'doctor' => 'یادداشت پزشک',
            'briefing' => 'مرور جهان',
            'content' => 'متن صفحات',
            'look' => 'چیدمان و حرکت',
            'media' => 'گالری',
        ],
        'گوگل' => [
            'seo' => 'سئو و گوگل',
            'feeds' => 'منابع روز',
        ],
        'مطب' => [
            'log' => 'گزارش سیستم',
            'account' => 'حساب من',
        ],
    ];
    if ($owner) {
        $groups['مطب'] = [
            'geo' => 'موقعیت مطب',
            'users' => 'کاربران',
            'settings' => 'تنظیمات',
            'log' => 'گزارش سیستم',
            'account' => 'حساب من',
        ];
    }
    echo '<nav class="nav">';
    foreach ($groups as $name => $items) {
        echo '<p class="nav-label">' . e($name) . '</p>';
        foreach ($items as $key => $label) {
            if ($key === 'messaging' && !$owner) {
                continue;
            }
            $class = $key === $active ? ' class="active"' : '';
            $text = $label;
            if ($key === 'patients') {
                $careNew = (int) cms_db()->query("SELECT COUNT(*) FROM care_posts WHERE author = 'patient' AND hidden = 0 AND seen_at = ''")->fetchColumn();
                if ($careNew > 0) {
                    $text .= ' (' . cms_fa_digits((string) $careNew) . ')';
                }
            }
            echo '<a' . $class . ' href="/manage/index.php?page=' . e($key) . '">' . e($text) . '</a>';
        }
    }
    echo '</nav>';
}

function desk_clip(string $value, int $max = 48): string
{
    if (function_exists('mb_strlen') && mb_strlen($value) > $max) {
        return mb_substr($value, 0, $max) . '…';
    }
    return $value;
}

function desk_open(string $title, string $active, string $lede = ''): void
{
    $user = cms_user();
    $owner = $user && $user['role'] === 'owner';
    $flash = cms_take_flash();
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow"><title>' . e($title) . ' | میز مطب</title><link rel="stylesheet" href="/manage/admin.css"></head><body>';
    echo '<div class="app"><aside class="side"><div class="brand">JAFARI<small>میز مطب</small></div>';
    desk_nav($active, $owner);
    echo '<div class="who">' . e((string) ($user['display_name'] ?: $user['username'])) . '<br>' . ($owner ? 'مالک' : 'ویرایشگر');
    echo '<form method="post" action="/manage/index.php">' . desk_csrf() . '<input type="hidden" name="action" value="logout"><button type="submit">خروج</button></form></div></aside>';
    echo '<main class="main"><div class="top"><div><h1>' . e($title) . '</h1>';
    if ($lede !== '') {
        echo '<p class="lede">' . e($lede) . '</p>';
    }
    echo '</div><a class="btn ghost" href="/fa/">دیدن سایت</a></div>';
    if ($flash) {
        $tone = ($flash[0] ?? '') === 'ok' ? 'ok' : 'bad';
        echo '<div class="flash ' . $tone . '">' . e((string) ($flash[1] ?? '')) . '</div>';
    }
}

function desk_close(): void
{
    echo '<script src="/manage/desk.js"></script></main></div></body></html>';
}

function desk_auth(string $mode, string $error = ''): void
{
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    $install = $mode === 'install';
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . ($install ? 'ساخت میز' : 'ورود') . '</title><link rel="stylesheet" href="/manage/admin.css"></head><body>';
    echo '<form class="auth" method="post" action="/manage/index.php">';
    echo desk_csrf();
    echo '<p class="brand">JAFARI</p><h1>' . ($install ? 'ساخت میز مطب' : 'ورود به میز') . '</h1>';
    echo '<p class="help">' . ($install ? 'نام کاربری انگلیسی و دو بار رمز را بنویسید. پایگاه خودش ساخته می‌شود. در cPanel بخش MySQL و phpMyAdmin را باز نکنید. رمز حداقل ۱۰ نویسه است.' : 'نشست بعد از بستن مرورگر تمام می‌شود. هشت ورود ناموفق از همین شبکه، ۱۵ دقیقه ورود را می‌بندد.') . '</p>';
    if ($install) {
        echo '<ul class="check">';
        foreach (cms_host_report() as $item) {
            echo '<li class="' . ($item['ok'] ? 'done' : 'wait') . '"><strong>' . e($item['label']) . '.</strong> ' . e($item['detail']) . '</li>';
        }
        echo '</ul>';
    }
    $blocked = $install ? cms_host_blocked() : '';
    if ($error !== '') {
        echo '<div class="flash bad">' . e($error) . '</div>';
    }
    if ($blocked !== '') {
        echo '<p class="help">تا مورد بالا درست نشود میز ساخته نمی‌شود.</p></form></body></html>';
        return;
    }
    if ($install && !cms_install_secret_ensure()) {
        echo '<p class="help">پوشهٔ پایگاه قابل نوشتن نیست. در File Manager روی پوشه cms/data مجوز ۷۵۵ بگذارید و صفحه را تازه کنید.</p></form></body></html>';
        return;
    }
    if ($install) {
        $_SESSION['install_token'] = cms_install_secret_value();
    }
    echo '<input type="hidden" name="action" value="' . ($install ? 'install' : 'login') . '">';
    echo '<label>نام کاربری<input name="username" autocomplete="username" required></label>';
    echo '<label>رمز<input type="password" name="password" autocomplete="' . ($install ? 'new-password' : 'current-password') . '" required></label>';
    if ($install) {
        echo '<label>تکرار رمز<input type="password" name="password2" autocomplete="new-password" required></label>';
    }
    echo '<button class="btn" type="submit">' . ($install ? 'ساختن میز' : 'ورود') . '</button></form></body></html>';
}
