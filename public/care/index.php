<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cms/bootstrap.php';
require __DIR__ . '/board.php';

header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: same-origin');

function care_emergency_link(): string
{
    $number = cms_care_emergency();
    return '<a href="tel:' . e($number) . '">' . e(cms_fa_digits($number)) . '</a>';
}

function care_shell(string $title, string $body): void
{
    $locale = cms_care_locale();
    $dir = $locale === 'en' || $locale === 'zh' ? 'ltr' : 'rtl';
    echo '<!doctype html><html lang="' . e($locale) . '" dir="' . $dir . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . '</title><link rel="stylesheet" href="/care/care.css"></head><body><main class="room">';
    echo $body;
    echo '</main></body></html>';
}

if (!cms_installed()) {
    care_shell('پرونده درمان', '<h1>پرونده درمان</h1><p>این صفحه بعد از نصب میز مطب باز می‌شود.</p>');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = 'حجم عکس از حد مجاز سرور بیشتر است.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $honeypot = trim((string) ($_POST['company'] ?? ''));
    if (!cms_care_csrf_ok()) {
        $error = 'نشست نامعتبر است. صفحه را تازه کنید.';
    } elseif (($_POST['action'] ?? '') === 'logout') {
        cms_care_logout();
        header('Location: /care/', true, 303);
        exit;
    } elseif (($_POST['action'] ?? '') === 'lang') {
        cms_care_locale_set((string) ($_POST['locale'] ?? 'fa'));
        header('Location: /care/', true, 303);
        exit;
    } elseif (($_POST['action'] ?? '') === 'sheet-pdf') {
        $phone = cms_care_logged_phone();
        if ($phone === '' || cms_care_role() !== 'patient') {
            $error = cms_care_role() === 'companion' ? 'این ورود فقط وقت‌ها و آماده شدن را نشان می‌دهد.' : 'اول با رمز خود پرونده وارد شوید.';
        } else {
            $pdf = cms_care_sheet_pdf($phone, cms_care_locale());
            if ($pdf === '' || !str_starts_with($pdf, '%PDF')) {
                $error = 'فایل برگه ساخته نشد.';
            } else {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="care-sheet.pdf"');
                header('Content-Length: ' . (string) strlen($pdf));
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: no-store');
                echo $pdf;
                exit;
            }
        }
    } elseif (cms_care_role() === 'companion') {
        $error = 'این ورود فقط وقت‌ها و آماده شدن را نشان می‌دهد.';
    } elseif (($_POST['action'] ?? '') === 'code-rotate') {
        $phone = cms_care_logged_phone();
        if ($phone === '' || $honeypot !== '') {
            $error = 'اول وارد پرونده شوید.';
        } elseif (!cms_care_on('care_patient_rotate')) {
            $error = 'تعویض رمز از این صفحه بسته است. از مطب بخواهید.';
        } elseif (cms_care_limited('care-code', 6)) {
            $error = 'تعویض رمز چند بار پشت سر هم ممکن نیست. کمی بعد دوباره بزنید.';
        } else {
            cms_care_tick('care-code');
            $issued = cms_care_issue_code($phone);
            cms_care_open_session($phone, $issued['stamp'], $issued['code']);
            header('Location: /care/?sent=code', true, 303);
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'login') {
        if ($honeypot !== '' || cms_care_limited('care-login', 20)) {
            $error = 'با این شماره و کد، پرونده‌ای باز نشد.';
        } else {
            cms_care_tick('care-login');
            if (!cms_care_login((string) ($_POST['phone'] ?? ''), (string) ($_POST['code'] ?? ''))) {
                $error = 'با این شماره و کد، پرونده‌ای باز نشد.';
            } else {
                cms_care_session();
                if (!isset($_SESSION['care_locale'])) {
                    $pick = cms_db()->prepare('SELECT locale FROM visits WHERE phone = ? ORDER BY id DESC LIMIT 1');
                    $pick->execute([cms_care_logged_phone()]);
                    $visitLocale = $pick->fetchColumn();
                    if (is_string($visitLocale) && $visitLocale !== '') {
                        cms_care_locale_set($visitLocale);
                    }
                }
                header('Location: /care/', true, 303);
                exit;
            }
        }
    } elseif (in_array((string) ($_POST['action'] ?? ''), ['companion-issue', 'companion-revoke'], true)) {
        $phone = cms_care_logged_phone();
        if ($phone === '' || cms_care_role() !== 'patient') {
            $error = 'اول با رمز خود پرونده وارد شوید.';
        } elseif ($honeypot !== '') {
            header('Location: /care/', true, 303);
            exit;
        } elseif (!cms_care_on('care_companion') || !cms_care_on('care_patient_companion')) {
            $error = 'رمز همراه از این صفحه بسته است.';
        } elseif ((string) $_POST['action'] === 'companion-issue' && cms_care_limited('care-mate', 6)) {
            $error = 'ساخت رمز همراه چند بار پشت سر هم ممکن نیست. کمی بعد دوباره بزنید.';
        } elseif ((string) $_POST['action'] === 'companion-issue') {
            $issued = cms_care_issue_companion($phone, (string) ($_POST['name'] ?? ''));
            if (!$issued['ok']) {
                $error = $issued['error'];
            } else {
                cms_care_tick('care-mate');
                cms_care_hold_companion($issued['code'], $issued['name']);
                header('Location: /care/?sent=mate', true, 303);
                exit;
            }
        } else {
            cms_care_revoke_companion($phone, (int) ($_POST['id'] ?? 0));
            header('Location: /care/?sent=mate-off', true, 303);
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'message') {
        $phone = cms_care_logged_phone();
        if ($phone === '') {
            $error = 'اول وارد پرونده شوید.';
        } elseif (!cms_care_open()) {
            $error = 'فرستادن پیام از این صفحه موقتاً بسته است.';
        } elseif ($honeypot !== '') {
            header('Location: /care/', true, 303);
            exit;
        } elseif ((string) ($_POST['consent'] ?? '') !== '1') {
            $error = 'بدون تأیید، متن و عکس ذخیره نمی‌شود.';
        } else {
            $saved = cms_care_add($phone, 'patient', (string) ($_POST['body'] ?? ''), $_FILES['photo'] ?? null, '');
            if ($saved['ok']) {
                cms_care_consent_keep($phone, 'message');
                header('Location: /care/?sent=1', true, 303);
                exit;
            }
            $error = $saved['error'];
        }
    } elseif (($_POST['action'] ?? '') === 'facts') {
        $phone = cms_care_logged_phone();
        if ($phone === '') {
            $error = 'اول وارد پرونده شوید.';
        } elseif ($honeypot !== '') {
            header('Location: /care/', true, 303);
            exit;
        } elseif ((string) ($_POST['consent'] ?? '') !== '1') {
            $error = 'بدون تأیید، برگه پزشکی ذخیره نمی‌شود.';
        } else {
            cms_care_save_facts($phone, $_POST);
            cms_care_consent_keep($phone, 'sheet');
            if (!cms_care_limited('care-facts', 8)) {
                cms_care_tick('care-facts');
                cms_care_alert($phone);
            }
            header('Location: /care/?sent=facts', true, 303);
            exit;
        }
    } elseif (in_array((string) ($_POST['action'] ?? ''), ['sms-stop', 'sms-resume'], true)) {
        $phone = cms_care_logged_phone();
        if ($phone === '' || cms_care_role() !== 'patient') {
            $error = 'اول با رمز خود پرونده وارد شوید.';
        } elseif ($honeypot !== '') {
            header('Location: /care/', true, 303);
            exit;
        } elseif (cms_care_limited('care-sms', 6)) {
            $error = 'این درخواست چند بار پشت سر هم ممکن نیست. کمی بعد دوباره بزنید.';
        } else {
            cms_care_tick('care-sms');
            cms_sms_stop($phone, (string) $_POST['action'] === 'sms-stop');
            header('Location: /care/?sent=sms', true, 303);
            exit;
        }
    } elseif (in_array((string) ($_POST['action'] ?? ''), ['med-add', 'med-dose', 'med-plan', 'med-stop', 'need-add', 'need-toggle', 'need-delete', 'remind-add', 'remind-delete', 'comment', 'comment-drop', 'file-take', 'visit-ask', 'visit-unask'], true)) {
        $phone = cms_care_logged_phone();
        $panelAction = (string) $_POST['action'];
        if ($phone === '') {
            $error = 'اول وارد پرونده شوید.';
        } elseif ($honeypot !== '') {
            header('Location: /care/', true, 303);
            exit;
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $result = ['ok' => false, 'error' => 'این بخش بسته است.'];
            $mark = '';
            if ($panelAction === 'med-add' && cms_care_on('care_patient_med')) {
                $result = cms_care_add_med($phone, 'patient', (string) ($_POST['name'] ?? ''), (string) ($_POST['dose'] ?? ''), (string) ($_POST['clock'] ?? ''), (string) ($_POST['caution'] ?? ''));
                $mark = 'med';
            } elseif ($panelAction === 'med-plan' && cms_care_on('care_patient_med')) {
                $result = cms_care_med_plan($phone, $id, 'patient', (string) ($_POST['clock'] ?? ''), (string) ($_POST['caution'] ?? ''));
                $mark = !empty($result['changed']) ? 'dose' : 'medsame';
            } elseif ($panelAction === 'med-dose' && cms_care_on('care_patient_med')) {
                $result = cms_care_med_dose($phone, $id, 'patient', (string) ($_POST['dose'] ?? ''));
                $mark = !empty($result['changed']) ? 'dose' : 'medsame';
            } elseif ($panelAction === 'med-stop') {
                $result = ['ok' => cms_care_med_stop($phone, $id, 'patient', '', (string) ($_POST['reason'] ?? '')), 'error' => 'این دارو را فقط مطب می‌تواند متوقف کند.'];
                $mark = 'med';
            } elseif ($panelAction === 'need-add' && cms_care_on('care_patient_need')) {
                $result = cms_care_add_need($phone, 'patient', (string) ($_POST['body'] ?? ''));
                $mark = 'need';
            } elseif ($panelAction === 'need-toggle') {
                $result = ['ok' => cms_care_need_toggle($phone, $id), 'error' => 'این کار پیدا نشد.'];
                $mark = 'need';
            } elseif ($panelAction === 'need-delete') {
                $result = ['ok' => cms_care_need_delete($phone, $id, 'patient'), 'error' => 'فقط کار خودتان را می‌توانید بردارید.'];
                $mark = 'need';
            } elseif ($panelAction === 'remind-add' && cms_care_on('care_self_remind')) {
                $result = cms_care_add_reminder($phone, (string) ($_POST['day'] ?? ''), (string) ($_POST['note'] ?? ''));
                $mark = 'remind';
            } elseif ($panelAction === 'remind-delete') {
                $result = ['ok' => cms_care_reminder_delete($phone, $id), 'error' => 'این یادآوری پیدا نشد.'];
                $mark = 'remind';
            } elseif ($panelAction === 'comment' && cms_care_on('care_comments')) {
                $result = cms_care_add_comment($phone, (int) ($_POST['visit'] ?? 0), 'patient', (string) ($_POST['body'] ?? ''));
                $mark = 'comment';
            } elseif ($panelAction === 'comment-drop' && cms_care_on('care_comments')) {
                $result = ['ok' => cms_care_comment_drop($phone, $id), 'error' => 'فقط نظر خودتان را می‌توانید بردارید.'];
                $mark = 'comment';
            } elseif ($panelAction === 'file-take') {
                $result = ['ok' => cms_care_file_take($phone, $id), 'error' => 'فقط عکس خودتان را می‌توانید بردارید.'];
                $mark = 'photo';
            } elseif ($panelAction === 'visit-ask') {
                $result = cms_care_add_ask($phone, (int) ($_POST['visit'] ?? 0), (string) ($_POST['kind'] ?? ''), (string) ($_POST['day'] ?? ''), (string) ($_POST['time'] ?? ''), (string) ($_POST['note'] ?? ''));
                $mark = 'ask';
            } elseif ($panelAction === 'visit-unask') {
                $result = ['ok' => cms_care_withdraw_ask($phone, $id), 'error' => 'این درخواست پیدا نشد.'];
                $mark = 'ask';
            }
            if ($result['ok']) {
                if (in_array($panelAction, ['med-add', 'need-add', 'comment', 'visit-ask'], true) && !cms_care_limited('care-note', 8)) {
                    cms_care_tick('care-note');
                    cms_care_alert($phone);
                }
                header('Location: /care/?sent=' . $mark, true, 303);
                exit;
            }
            $error = $result['error'];
        }
    }
}

$phone = cms_care_logged_phone();
$title = cms_care_title(cms_care_locale());
if ($phone === '') {
    $locale = cms_care_locale();
    $html = care_langs();
    $html .= '<h1>' . e($title) . '</h1>';
    $html .= '<p class="help">' . e(cms_care_notice(cms_care_locale())) . ' ' . care_emergency_link() . '</p>';
    if (!cms_care_open()) {
        $html .= '<p>' . e(cms_care_ui('closed-login')) . '</p>';
    }
    if ($error !== '') {
        $html .= '<p class="bad">' . e(cms_care_say($error)) . '</p>';
    }
    $html .= '<form class="card ticket" method="post"><input type="hidden" name="csrf" value="' . e(cms_care_csrf()) . '"><input type="hidden" name="action" value="login">';
    $html .= '<p class="hp"><label>' . e(cms_care_ui('company')) . '<input name="company" tabindex="-1" autocomplete="off"></label></p>';
    $html .= '<label>' . e(cms_care_ui('mobile')) . '<input name="phone" dir="ltr" inputmode="tel" required></label>';
    $html .= '<label>' . e(cms_care_ui('portal-or-companion')) . '<input name="code" dir="ltr" autocomplete="off" required></label>';
    $html .= '<button type="submit">' . e(cms_care_ui('enter')) . '</button></form>';
    $html .= '<div class="split"><p class="prep">' . e(cms_care_ui('login-portal')) . '</p>';
    $html .= '<p class="prep">' . e(cms_care_ui('login-visit')) . '</p></div>';
    $html .= '<p class="help">' . e(cms_care_ui('not-exam')) . '</p>';
    $html .= '<p><a href="/' . e($locale) . '/appointment/track/">' . e(cms_care_ui('track')) . '</a> · <a href="/' . e($locale) . '/appointment/">' . e(cms_care_ui('back')) . '</a></p>';
    care_shell($title, $html);
    exit;
}

$html = care_board($phone, $error, (string) ($_GET['sent'] ?? ''));

care_shell($title, $html);
