<?php
declare(strict_types=1);

function desk_weekdays(): array
{
    return [
        6 => 'شنبه',
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنجشنبه',
        5 => 'جمعه',
    ];
}

function desk_visit_tone(string $status): string
{
    return [
        'new' => 'new',
        'confirmed' => 'ok',
        'arrived' => 'ok',
        'cancelled' => 'error',
        'noshow' => 'error',
    ][$status] ?? '';
}

function desk_message_tone(string $status): string
{
    return ['sent' => 'ok', 'failed' => 'error', 'skipped' => 'warn'][$status] ?? '';
}

function desk_message_label(string $status): string
{
    return ['sent' => 'رفت', 'failed' => 'نرفت', 'skipped' => 'رد شد'][$status] ?? $status;
}

function desk_reserve_back(string $fallback): void
{
    $back = (string) ($_POST['back'] ?? '');
    if (!preg_match('/^page=[a-z]+(?:&[a-z0-9_%=.-]+)?$/', $back)) {
        $back = $fallback;
    }
    desk_redirect($back);
}

function desk_reserve_handle(string $action, array $user, bool $owner): bool
{
    $known = ['save-visit', 'save-visit-new', 'move-visit', 'status-visit', 'notify-visit', 'save-patient', 'move-patient', 'erase-patient', 'sms-stop', 'save-care', 'hide-care', 'save-facts', 'save-stage', 'save-prep', 'save-med', 'dose-med', 'plan-med', 'stop-med', 'line-med', 'save-need', 'delete-need', 'assign-need', 'save-comment', 'hide-comment', 'delete-reminder', 'save-care-code', 'save-companion-code', 'revoke-companion-code', 'accept-ask', 'decline-ask', 'close-ask', 'save-stages', 'save-care-settings', 'save-schedule', 'save-place', 'save-room', 'save-kinds', 'save-topics', 'save-messaging', 'send-test', 'send-reminders', 'save-teeth', 'save-checks', 'toggle-check', 'save-anesthesia', 'save-call', 'pull-chat'];
    if (!in_array($action, $known, true)) {
        return false;
    }
    if ((string) ($user['role'] ?? '') === 'nurse' && !in_array($action, ['save-call', 'assign-need', 'toggle-check'], true)) {
        cms_flash('bad', 'پرستار دفتر تماس، مسئول کار و تیک پیش از ورود را دارد. برگه، دارو، رمز و حذف برای این نقش نیست.');
        desk_redirect('page=patients');
    }
    if (in_array($action, ['save-messaging', 'send-test', 'send-reminders', 'save-care-settings'], true) && !$owner) {
        cms_flash('bad', $action === 'save-care-settings' ? 'تنظیم پرونده درمان فقط برای مالک میز است.' : 'پیام‌رسانی فقط برای مالک میز است.');
        desk_redirect($action === 'save-care-settings' ? 'page=patients' : 'page=appointments');
    }

    if ($action === 'save-visit') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = cms_visit_row($id);
        if (!$row) {
            cms_flash('bad', 'این نوبت پیدا نشد.');
            desk_redirect('page=appointments');
        }
        $status = (string) ($_POST['status'] ?? 'new');
        if (!isset(cms_visit_statuses()[$status])) {
            $status = 'new';
        }
        $kind = (string) ($_POST['kind'] ?? $row['kind']);
        $day = cms_parse_day((string) ($_POST['day'] ?? ''));
        $time = (string) ($_POST['time'] ?? $row['time_slot']);
        $name = cms_plain((string) ($_POST['name'] ?? ''), 80);
        $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')) ?? '';
        if (str_starts_with($phone, '98')) {
            $phone = '0' . substr($phone, 2);
        }
        $email = trim((string) ($_POST['email'] ?? ''));
        $service = (string) ($_POST['service'] ?? $row['service']);
        $locale = cms_locale((string) ($_POST['locale'] ?? $row['locale']));
        $topic = cms_topic_row($service);
        $kindRow = cms_kind_row($kind);
        $serviceOk = $service === (string) $row['service'] || ($topic && $topic['active']);
        $timeOk = in_array($time, cms_book_slots(), true) || $time === (string) $row['time_slot'];
        if (!$kindRow || $day === '' || !$timeOk || cms_chars($name) < 2 || strlen($phone) < 10 || strlen($phone) > 11 || !$serviceOk || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            cms_flash('bad', 'نام، موبایل، موضوع، نوع نوبت، روز یا ساعت معتبر نیست. روز را شمسی بنویسید، مثل 1405-08-27.');
            desk_redirect('page=appointments');
        }
        $occupies = in_array($status, ['new', 'confirmed', 'arrived'], true);
        $moved = $row['day'] !== $day || $row['time_slot'] !== $time || (string) $row['kind'] !== $kind;
        $force = isset($_POST['force']);
        $placeKey = (string) ($row['place_key'] ?? '');
        $room = cms_room_key((string) ($_POST['room'] ?? ''));
        if ($room !== '' && !cms_room_row($placeKey, $room)) {
            cms_flash('bad', 'این اتاق برای مکان همین نوبت ساخته نشده است.');
            desk_redirect('page=appointments');
        }
        if ($occupies && $moved && !$force) {
            if ($row['day'] !== $day && !cms_day_open($day)) {
                cms_flash('bad', 'این روز در برنامه مطب بسته است. اول از برنامه مطب بازش کنید، یا گزینه ساعت پر را بزنید.');
                desk_redirect('page=appointments');
            }
            if (!cms_slot_free($day, $time, $kind, $id)) {
                cms_flash('bad', 'این ساعت با نوبت دیگری پر شده است. اگر عمداً می‌خواهید روی هم بنشیند، گزینه مربوط را بزنید.');
                desk_redirect('page=appointments');
            }
        }
        if ($occupies && $room !== '' && !$force && !cms_room_free($day, $time, $kind, $placeKey, $room, $id)) {
            cms_flash('bad', cms_hold_sentence(cms_room_holders($day, $time, $kind, $placeKey, $room, $id), $day) . ' اتاق هم همان ساعت پر است.');
            desk_redirect('page=appointments');
        }
        cms_db()->prepare('UPDATE visits SET status = ?, kind = ?, day = ?, time_slot = ?, note = ?, name = ?, phone = ?, email = ?, service = ?, message = ?, locale = ?, prep = ?, room_key = ?, updated_at = ? WHERE id = ?')->execute([
            $status,
            $kind,
            $day,
            $time,
            cms_plain((string) ($_POST['note'] ?? ''), 1000),
            $name,
            $phone,
            $email,
            $service,
            cms_plain((string) ($_POST['message'] ?? ''), 400),
            $locale,
            cms_plain((string) ($_POST['prep'] ?? ''), 300),
            $room,
            cms_now(),
            $id,
        ]);
        cms_patient_ensure($phone, $name);
        $fresh = cms_visit_row($id);
        $event = '';
        if ($fresh && $row['status'] !== $status && $status === 'cancelled') {
            $event = 'cancelled';
        } elseif ($fresh && $row['status'] !== $status && $status === 'confirmed') {
            $event = 'confirmed';
        } elseif ($fresh && $moved && $status === 'confirmed') {
            $event = 'rescheduled';
        }
        if ($fresh && $event !== '') {
            cms_notify_visit($fresh, $event);
        }
        cms_audit('visit', '#' . $id, $status);
        cms_flash('ok', $event !== '' ? 'نوبت ذخیره شد و پیام همان وضعیت فرستاده شد.' : 'نوبت ذخیره شد. اصلاح نام، شماره یا موضوع خودش پیام تازه نمی‌فرستد.');
        desk_redirect('page=appointments');
    }

    if ($action === 'move-visit') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = cms_visit_row($id);
        if (!$row) {
            cms_flash('bad', 'این نوبت پیدا نشد.');
            desk_redirect('page=appointments');
        }
        $back = (string) $row['day'];
        $day = cms_parse_day((string) ($_POST['day'] ?? ''));
        $time = (string) ($_POST['time'] ?? '');
        if ($day === '' || !in_array($time, cms_book_slots(), true)) {
            cms_flash('bad', 'روز را شمسی بنویسید، مثل 1405-08-27، و ساعت را از فهرست برنامه انتخاب کنید.');
            desk_redirect('page=appointments&day=' . $back);
        }
        if ($day === $back && $time === (string) $row['time_slot']) {
            cms_flash('ok', 'زمان همین بود و عوض نشد.');
            desk_redirect('page=appointments&day=' . $day);
        }
        $status = (string) $row['status'];
        $occupies = in_array($status, ['new', 'confirmed', 'arrived'], true);
        $pdo = cms_db();
        $pdo->exec('BEGIN IMMEDIATE');
        if ($occupies && !isset($_POST['force'])) {
            if (!cms_day_open($day) || !cms_slot_free($day, $time, (string) $row['kind'], $id)) {
                $pdo->exec('ROLLBACK');
                cms_flash('bad', !cms_day_open($day) ? 'این روز در برنامه مطب بسته است. برای نشاندن روی روز بسته، گزینه مربوط را بزنید.' : 'این ساعت پر است. اگر عمداً می‌خواهید روی هم بنشیند، گزینه مربوط را بزنید.');
                desk_redirect('page=appointments&day=' . $back);
            }
        }
        $pdo->prepare('UPDATE visits SET day = ?, time_slot = ?, updated_at = ? WHERE id = ?')->execute([$day, $time, cms_now(), $id]);
        $pdo->exec('COMMIT');
        $fresh = cms_visit_row($id);
        if ($fresh && $status === 'confirmed') {
            cms_notify_visit($fresh, 'rescheduled');
        }
        cms_audit('visit', '#' . $id, 'move');
        cms_flash('ok', $status === 'confirmed' ? 'نوبت جابه‌جا شد و پیام تغییر زمان فرستاده شد.' : 'نوبت جابه‌جا شد. چون هنوز هماهنگ نشده، پیامی برای بیمار نرفت.');
        desk_redirect('page=appointments&day=' . $day);
    }

    if ($action === 'status-visit') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = cms_visit_row($id);
        if (!$row) {
            cms_flash('bad', 'این نوبت پیدا نشد.');
            desk_redirect('page=appointments');
        }
        $back = (string) $row['day'];
        $status = (string) ($_POST['status'] ?? '');
        if (!isset(cms_visit_statuses()[$status])) {
            cms_flash('bad', 'این وضعیت در میز نیست.');
            desk_redirect('page=appointments&day=' . $back);
        }
        if ($status === (string) $row['status']) {
            cms_flash('ok', 'وضعیت همین بود و عوض نشد.');
            desk_redirect('page=appointments&day=' . $back);
        }
        $holds = in_array($status, cms_slot_statuses(), true);
        $held = in_array((string) $row['status'], cms_slot_statuses(), true);
        $pdo = cms_db();
        $pdo->exec('BEGIN IMMEDIATE');
        if ($holds && !$held && !cms_slot_free($back, (string) $row['time_slot'], (string) $row['kind'], $id)) {
            $pdo->exec('ROLLBACK');
            cms_flash('bad', 'این ساعت الان پر است. برگشت به وضعیت فعال را از کارت همان نوبت، با گزینه ساعت پر، انجام دهید.');
            desk_redirect('page=appointments&day=' . $back);
        }
        $pdo->prepare('UPDATE visits SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, cms_now(), $id]);
        $pdo->exec('COMMIT');
        $fresh = cms_visit_row($id);
        $event = '';
        if ($fresh && $status === 'cancelled') {
            $event = 'cancelled';
        } elseif ($fresh && $status === 'confirmed') {
            $event = 'confirmed';
        }
        if ($fresh && $event !== '') {
            cms_notify_visit($fresh, $event);
        }
        cms_audit('visit', '#' . $id, $status);
        $label = cms_visit_statuses()[$status];
        cms_flash('ok', $event !== '' ? 'وضعیت شد «' . $label . '» و پیام همان وضعیت فرستاده شد.' : 'وضعیت شد «' . $label . '». برای حاضر شدن، انجام شدن و نیامدن پیامی نمی‌رود.');
        desk_redirect('page=appointments&day=' . $back);
    }

    if ($action === 'save-visit-new') {
        $day = cms_parse_day((string) ($_POST['day'] ?? ''));
        if ($day === '') {
            cms_flash('bad', 'روز را شمسی بنویسید، مثل 1405-08-27.');
            desk_redirect('page=appointments');
        }
        $result = cms_visit_create([
            'name' => (string) ($_POST['name'] ?? ''),
            'phone' => (string) ($_POST['phone'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
            'service' => (string) ($_POST['service'] ?? 'unsure'),
            'kind' => (string) ($_POST['kind'] ?? 'exam'),
            'place' => (string) ($_POST['place'] ?? ''),
            'staff_place' => (string) ($user['place_key'] ?? ''),
            'booked_by' => cms_plain((string) ($user['display_name'] ?? $user['username'] ?? ''), 80),
            'day' => $day,
            'time' => (string) ($_POST['time'] ?? ''),
            'message' => (string) ($_POST['message'] ?? ''),
            'note' => (string) ($_POST['note'] ?? ''),
            'locale' => cms_locale((string) ($_POST['locale'] ?? 'fa')),
            'source' => 'desk',
            'status' => (($_POST['status'] ?? '') === 'confirmed') ? 'confirmed' : 'new',
        ]);
        if (!$result['ok']) {
            $why = [
                'closed' => 'این روز یا ساعت در برنامهٔ این مکان باز نیست.',
                'full' => cms_hold_sentence((array) ($result['holders'] ?? []), $day),
                'place' => 'این منشی فقط برای مکان خودش نوبت می‌گذارد.',
                'limit' => 'تعداد درخواست از این شبکه زیاد شده است.',
            ][$result['error']] ?? 'نام، موبایل، نوع نوبت، مکان، روز و ساعت را کامل کنید.';
            cms_flash('bad', $why);
            desk_redirect('page=appointments');
        }
        $prep = cms_plain((string) ($_POST['prep'] ?? ''), 300);
        if ($prep !== '') {
            cms_db()->prepare('UPDATE visits SET prep = ? WHERE id = ?')->execute([$prep, (int) $result['id']]);
        }
        cms_audit('visit', (string) $result['code'], 'desk');
        cms_flash('ok', 'نوبت تلفنی ثبت شد. کد پیگیری ' . $result['code'] . ' است.');
        desk_redirect('page=appointments');
    }

    if ($action === 'save-patient') {
        $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')) ?? '';
        if (str_starts_with($phone, '98')) {
            $phone = '0' . substr($phone, 2);
        }
        $saved = cms_patient_save($phone, (string) ($_POST['name'] ?? ''), (string) ($_POST['note'] ?? ''), (string) ($_POST['birth'] ?? ''), (string) ($_POST['national_id'] ?? ''));
        if (!$saved['ok']) {
            cms_flash('bad', $saved['error']);
            desk_redirect($phone !== '' ? 'page=patients&phone=' . rawurlencode($phone) : 'page=patients');
        }
        cms_audit('patient', $phone, 'file');
        cms_flash('ok', 'پرونده ذخیره شد. این یادداشت برای بیمار فرستاده نمی‌شود و نوبت‌های قبلی را عوض نمی‌کند.');
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'sms-stop') {
        $phone = cms_mobile((string) ($_POST['phone'] ?? ''));
        $known = cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?');
        $known->execute([$phone]);
        if ($phone === '' || !$known->fetchColumn() || !cms_sms_stop($phone, isset($_POST['sms_off']))) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $off = isset($_POST['sms_off']);
        cms_audit('patient', $phone, $off ? 'sms-off' : 'sms-on');
        cms_flash('ok', $off ? 'پیامک بیمار برای این شماره قطع شد. پیام پزشک همچنان می‌رود.' : 'پیامک بیمار برای این شماره دوباره آزاد شد.');
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'move-patient') {
        $from = cms_patient_digits((string) ($_POST['phone'] ?? ''));
        $knownPhone = $from !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$from]);
        }
        $next = cms_mobile(cms_patient_digits((string) ($_POST['next'] ?? '')));
        $again = cms_mobile(cms_patient_digits((string) ($_POST['again'] ?? '')));
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        if ((string) ($_POST['confirm'] ?? '') !== '1' || $next === '' || $next !== $again) {
            cms_flash('bad', 'شماره تازه و تکرارش باید یک موبایل ۱۱ رقمی باشد و تأیید را بزنید. بدون این، پرونده عوض نمی‌شود.');
            desk_redirect('page=patients&phone=' . rawurlencode($from));
        }
        $staff = (string) ($user['display_name'] ?? '');
        if ($staff === '') {
            $staff = (string) ($user['username'] ?? '');
        }
        $moved = cms_patient_move($from, $next, $staff);
        if (!$moved['ok']) {
            cms_flash('bad', $moved['error']);
            desk_redirect('page=patients&phone=' . rawurlencode($from));
        }
        cms_audit('patient', $next, $moved['merged'] ? 'merge' : 'phone');
        $message = $moved['merged']
            ? 'دو پرونده یکی شد. نوبت، برگه، عکس، دارو و پیام روی شماره تازه است. جاهای خالی از شماره قبلی پر شد و اگر هر دو متن داشتند هر دو ماند.'
            : 'شماره پرونده عوض شد. نوبت، برگه، عکس، دارو و پیام با شماره تازه است.';
        if ($moved['code'] === 'moved') {
            $message .= ' همان رمز پرونده با شماره تازه کار می‌کند.';
        } elseif ($moved['code'] === 'kept') {
            $message .= ' رمز شماره تازه سر جایش ماند و رمز شماره قبلی دیگر باز نمی‌کند.';
        }
        $message .= ' پیامکی نرفت و ورودهای شماره قبلی بسته شد.';
        if ($moved['clash']) {
            $message .= ' بعضی ساعت‌های فعال روی هم افتاده است. از دفتر روز جدا کنید.';
        }
        cms_flash('ok', $message);
        desk_redirect('page=patients&phone=' . rawurlencode($next));
    }

    if ($action === 'erase-patient') {
        $phone = cms_patient_digits((string) ($_POST['phone'] ?? ''));
        $again = cms_patient_digits((string) ($_POST['again'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        if ((string) ($_POST['confirm'] ?? '') !== '1' || $again !== $phone) {
            cms_flash('bad', 'برای حذف، همان موبایل را دوباره بنویسید و تأیید را بزنید. بدون این، پرونده سر جایش می‌ماند.');
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        $erased = cms_patient_erase($phone);
        if (!$erased['ok']) {
            cms_flash('bad', $erased['error']);
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        cms_audit('patient', $phone, 'erase');
        cms_flash('ok', 'برگه، عکس، دارو، پیام، رمز و کد ملی پاک شد. نوبت در دفتر روز ماند، نامش «حذف‌شده» شد و موبایل روی همان نوبت ماند تا ساعت اشغال بماند. ورود با رمز بسته است تا میز رمز تازه بسازد. کد نوبت پرونده را باز نمی‌کند.');
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'save-care' || $action === 'hide-care') {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'پرونده درمان فقط برای شماره‌ای است که نوبت دارد.');
            desk_redirect('page=patients');
        }
        if ($action === 'hide-care') {
            if (!cms_care_toggle_hidden((int) ($_POST['id'] ?? 0), $phone)) {
                cms_flash('bad', 'این پیام در پرونده پیدا نشد.');
            } else {
                cms_audit('care', $phone, 'hide');
                cms_flash('ok', 'دیده شدن این پیام برای بیمار عوض شد. متن در گزارش سیستم نوشته نمی‌شود.');
            }
        } else {
            $staff = (string) ($user['display_name'] ?? '');
            if ($staff === '') {
                $staff = (string) ($user['username'] ?? '');
            }
            $saved = cms_care_add($phone, 'clinic', (string) ($_POST['body'] ?? ''), $_FILES['photo'] ?? null, $staff);
            if (!$saved['ok']) {
                cms_flash('bad', $saved['error']);
            } else {
                cms_audit('care', $phone, 'reply');
                cms_flash('ok', 'در روند درمان گذاشته شد. بیمار آن را می‌بیند. یادداشت داخلی پرونده عوض نشد.');
            }
        }
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'save-care-settings') {
        cms_set_setting('care_public', isset($_POST['care_public']) ? '1' : '0');
        cms_set_setting('care_alert', isset($_POST['care_alert']) ? '1' : '0');
        $kb = (int) ($_POST['care_upload_kb'] ?? 4096);
        cms_set_setting('care_upload_kb', (string) max(256, min(8192, $kb > 0 ? $kb : 4096)));
        $careTexts = ['care_title' => 80, 'care_notice' => 500, 'care_consent' => 300, 'tpl_care_alert' => 300, 'care_label_problem' => 80, 'care_label_medicines' => 80, 'care_label_allergy' => 80, 'care_label_extra' => 80, 'care_h_next' => 40, 'care_h_stage' => 40, 'care_h_need' => 40, 'care_h_med' => 40, 'care_h_remind' => 40, 'care_h_talk' => 40];
        foreach ($careTexts as $key => $max) {
            $value = cms_plain((string) ($_POST[$key] ?? ''), $max);
            if ($value === '') {
                cms_db()->prepare('DELETE FROM settings WHERE key = ?')->execute([$key]);
            } else {
                cms_set_setting($key, $value);
            }
            if ($key === 'tpl_care_alert') {
                continue;
            }
            foreach (['en', 'ar', 'zh'] as $locale) {
                $field = $key . '_' . $locale;
                $translated = cms_plain((string) ($_POST[$field] ?? ''), $max);
                if ($translated === '') {
                    cms_db()->prepare('DELETE FROM settings WHERE key = ?')->execute([$field]);
                } else {
                    cms_set_setting($field, $translated);
                }
            }
        }
        $photos = (int) ($_POST['care_photos'] ?? 3);
        cms_set_setting('care_photos', (string) max(1, min(4, $photos > 0 ? $photos : 3)));
        foreach (cms_care_fields() as $key => $label) {
            cms_set_setting('care_show_' . $key, isset($_POST['care_show_' . $key]) ? '1' : '0');
        }
        foreach (['care_patient_med', 'care_patient_need', 'care_comments', 'care_self_remind', 'care_self_sms'] as $flag) {
            cms_set_setting($flag, isset($_POST[$flag]) ? '1' : '0');
        }
        cms_set_setting('care_remind_max', (string) max(1, min(12, (int) ($_POST['care_remind_max'] ?? 6))));
        cms_set_setting('care_code_days', (string) max(0, min(365, (int) ($_POST['care_code_days'] ?? 180))));
        cms_db()->prepare('DELETE FROM settings WHERE key = ?')->execute(['care_bootstrap_days']);
        cms_set_setting('care_patient_rotate', isset($_POST['care_patient_rotate']) ? '1' : '0');
        cms_set_setting('care_patient_ask', isset($_POST['care_patient_ask']) ? '1' : '0');
        cms_set_setting('care_companion', isset($_POST['care_companion']) ? '1' : '0');
        cms_set_setting('care_patient_companion', isset($_POST['care_patient_companion']) ? '1' : '0');
        cms_set_setting('care_teeth', isset($_POST['care_teeth']) ? '1' : '0');
        $checkTpl = cms_plain((string) ($_POST['care_check_tpl'] ?? ''), 800);
        if ($checkTpl === '') {
            cms_db()->prepare('DELETE FROM settings WHERE key = ?')->execute(['care_check_tpl']);
        } else {
            cms_set_setting('care_check_tpl', $checkTpl);
        }
        $emergency = preg_replace('/\D+/', '', (string) ($_POST['care_emergency'] ?? '')) ?? '';
        $emergencyNote = '';
        if ($emergency === '') {
            cms_db()->prepare('DELETE FROM settings WHERE key = ?')->execute(['care_emergency']);
        } elseif (!preg_match('/^\d{3,15}$/', $emergency) || in_array(substr($emergency, -8), ['22579316', '22579315'], true)) {
            $emergencyNote = ' شماره فوریت پذیرفته نشد و همان ' . cms_fa_digits(cms_care_emergency()) . ' ماند.';
        } else {
            cms_set_setting('care_emergency', $emergency);
        }
        cms_audit('care', 'settings', isset($_POST['care_public']) ? 'open' : 'closed');
        cms_flash('ok', 'تنظیم پرونده درمان ذخیره شد. فیلد خالی، جمله پیش‌فرض را برمی‌گرداند.' . $emergencyNote);
        desk_redirect('page=patients');
    }

    if ($action === 'save-facts' || $action === 'save-stage' || $action === 'save-prep') {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $staff = cms_plain((string) ($user['display_name'] ?? $user['username'] ?? ''), 80);
        if ($action === 'save-facts') {
            cms_care_save_facts($phone, $_POST, 'clinic', $staff);
            cms_audit('care', $phone, 'facts');
            cms_flash('ok', 'برگه پزشکی ذخیره شد. متن قبلی در تاریخچه ماند و در پیامک نمی‌رود.');
        } elseif ($action === 'save-stage') {
            $stage = (string) ($_POST['stage'] ?? '');
            $allowed = $stage === '';
            foreach (cms_care_stages(true) as $row) {
                if ($row['key'] === $stage) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                cms_flash('bad', 'این مرحله فعال نیست.');
                desk_redirect('page=patients&phone=' . rawurlencode($phone));
            }
            cms_care_set_stage($phone, $stage, $staff);
            cms_audit('care', $phone, 'stage');
            cms_flash('ok', 'مرحله درمان ذخیره شد. این برچسب نتیجه پزشکی نیست.');
        } else {
            $row = cms_visit_row((int) ($_POST['id'] ?? 0));
            if (!$row || (string) $row['phone'] !== $phone) {
                cms_flash('bad', 'این نوبت برای همین پرونده نیست.');
                desk_redirect('page=patients&phone=' . rawurlencode($phone));
            }
            cms_db()->prepare('UPDATE visits SET prep = ?, updated_at = ? WHERE id = ?')->execute([
                cms_plain((string) ($_POST['prep'] ?? ''), 300),
                cms_now(),
                (int) $row['id'],
            ]);
            cms_audit('visit', '#' . (int) $row['id'], 'prep');
            cms_flash('ok', 'آماده شدن نوبت ذخیره شد و پیامی فرستاده نشد.');
        }
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'save-care-code') {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $issued = cms_care_issue_code($phone);
        cms_audit('care', $phone, 'code');
        $until = $issued['expires_at'] === '' ? 'تا وقتی عوضش کنید معتبر است.' : 'تا ' . cms_day_label($issued['expires_at']) . ' معتبر است.';
        cms_flash('ok', 'رمز تازه پرونده: ' . $issued['code'] . '. ' . $until . ' آن را فقط به خود بیمار بگویید. در پیامک نمی‌رود و ورود قبلی این شماره بسته شد.');
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'save-companion-code' || $action === 'revoke-companion-code') {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        if ($action === 'revoke-companion-code') {
            $mateId = (int) ($_POST['id'] ?? 0);
            cms_care_revoke_companion($phone, $mateId);
            cms_audit('care', $phone, 'mate');
            cms_flash('ok', $mateId > 0 ? 'ورود این همراه بسته شد. همراهان دیگر و رمز خود پرونده سر جایشان ماندند.' : 'ورود همهٔ همراهان بسته شد. رمز خود پرونده و نوبت‌ها سر جایشان ماندند.');
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        if (!cms_care_on('care_companion')) {
            cms_flash('bad', 'ورود همراه از تنظیم پرونده بسته است.');
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        $issued = cms_care_issue_companion($phone, (string) ($_POST['name'] ?? ''));
        if (!$issued['ok']) {
            cms_flash('bad', $issued['error']);
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        cms_audit('care', $phone, 'mate');
        $until = $issued['expires_at'] === '' ? 'تا وقتی عوضش کنید معتبر است.' : 'تا ' . cms_day_label($issued['expires_at']) . ' معتبر است.';
        cms_flash('ok', 'رمز همراه برای ' . $issued['name'] . ': ' . $issued['code'] . '. ' . $until . ' فقط وقت و آماده شدن را باز می‌کند. همراهان دیگر این شماره بسته نشدند. برگه، عکس و دارو را نشان نمی‌دهد و نام و رمز در پیامک نمی‌روند.');
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if (in_array($action, ['save-med', 'dose-med', 'plan-med', 'stop-med', 'line-med', 'save-need', 'delete-need', 'save-comment', 'hide-comment', 'delete-reminder'], true)) {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $ok = true;
        $bad = 'ذخیره نشد.';
        $sameDose = false;
        $staff = cms_plain((string) ($user['display_name'] ?? $user['username'] ?? ''), 80);
        if ($action === 'save-med') {
            $saved = cms_care_add_med($phone, 'clinic', (string) ($_POST['name'] ?? ''), (string) ($_POST['dose'] ?? ''), (string) ($_POST['clock'] ?? ''), (string) ($_POST['caution'] ?? ''), (string) ($_POST['route'] ?? ''), (string) ($_POST['span'] ?? ''));
            $ok = $saved['ok'];
            $bad = $saved['error'];
        } elseif ($action === 'dose-med') {
            $saved = cms_care_med_dose($phone, $id, 'clinic', (string) ($_POST['dose'] ?? ''), $staff);
            $ok = $saved['ok'];
            $bad = $saved['error'];
            $sameDose = $ok && !$saved['changed'];
        } elseif ($action === 'plan-med') {
            $saved = cms_care_med_plan($phone, $id, 'clinic', (string) ($_POST['clock'] ?? ''), (string) ($_POST['caution'] ?? ''), $staff);
            $ok = $saved['ok'];
            $bad = $saved['error'];
            $sameDose = $ok && !$saved['changed'];
        } elseif ($action === 'line-med') {
            $saved = cms_care_med_line($phone, $id, 'clinic', (string) ($_POST['route'] ?? ''), (string) ($_POST['span'] ?? ''), $staff);
            $ok = $saved['ok'];
            $bad = $saved['error'];
            $sameDose = $ok && !$saved['changed'];
        } elseif ($action === 'stop-med') {
            $ok = cms_care_med_stop($phone, $id, 'clinic', $staff, (string) ($_POST['reason'] ?? ''));
            $bad = 'این دارو پیدا نشد.';
        } elseif ($action === 'save-need') {
            $saved = cms_care_add_need($phone, 'clinic', (string) ($_POST['body'] ?? ''));
            $ok = $saved['ok'];
            $bad = $saved['error'];
        } elseif ($action === 'delete-need') {
            $ok = cms_care_need_delete($phone, $id, 'clinic');
            $bad = 'این کار پیدا نشد.';
        } elseif ($action === 'save-comment') {
            $saved = cms_care_add_comment($phone, (int) ($_POST['visit'] ?? 0), 'clinic', (string) ($_POST['body'] ?? ''));
            $ok = $saved['ok'];
            $bad = $saved['error'];
        } elseif ($action === 'hide-comment') {
            $ok = cms_care_comment_hide($phone, $id);
            $bad = 'این نظر پیدا نشد.';
        } else {
            $ok = cms_care_reminder_delete($phone, $id);
            $bad = 'این یادآوری پیدا نشد.';
        }
        if (!$ok) {
            cms_flash('bad', $bad);
        } elseif ($sameDose) {
            cms_flash('ok', 'دوز همان بود. ردپا نوشته نشد و پیامک نرفت.');
        } else {
            cms_audit('care', $phone, $action === 'dose-med' ? 'dose' : ($action === 'plan-med' ? 'clock' : ($action === 'line-med' ? 'line' : $action)));
            cms_flash('ok', 'پرونده بیمار به‌روز شد. متن دارو و دوز در پیامک نوبت نمی‌رود.');
        }
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if (in_array($action, ['accept-ask', 'decline-ask', 'close-ask'], true)) {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $askId = (int) ($_POST['id'] ?? 0);
        $askStmt = cms_db()->prepare("SELECT * FROM care_asks WHERE id = ? AND phone = ? AND state = 'open'");
        $askStmt->execute([$askId, $phone]);
        $ask = $askStmt->fetch();
        if (!$ask) {
            cms_flash('bad', 'این درخواست باز پیدا نشد.');
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        if ($action === 'accept-ask') {
            if ((string) $ask['kind'] !== 'cancel') {
                cms_flash('bad', 'قبول این دکمه فقط درخواست لغو را قطعی می‌کند. برای زمان دیگر، اول از فرم جابه‌جایی استفاده کنید.');
                desk_redirect('page=patients&phone=' . rawurlencode($phone));
            }
            $visit = cms_visit_row((int) $ask['visit_id']);
            $event = false;
            if ($visit && (string) $visit['status'] !== 'cancelled') {
                $pdo = cms_db();
                $pdo->exec('BEGIN IMMEDIATE');
                $pdo->prepare('UPDATE visits SET status = ?, updated_at = ? WHERE id = ?')->execute(['cancelled', cms_now(), (int) $visit['id']]);
                $pdo->exec('COMMIT');
                $fresh = cms_visit_row((int) $visit['id']);
                if ($fresh) {
                    cms_notify_visit($fresh, 'cancelled');
                    $event = true;
                }
            }
            cms_care_finish_ask($phone, $askId, 'kept');
            cms_audit('care', $phone, 'ask');
            cms_flash('ok', $event ? 'نوبت لغو شد و پیام لغو فرستاده شد. تا این دکمه، درخواست بیمار نوبت را عوض نکرده بود.' : 'درخواست بسته شد. نوبت از قبل لغو شده بود.');
            desk_redirect('page=patients&phone=' . rawurlencode($phone));
        }
        cms_care_finish_ask($phone, $askId, $action === 'decline-ask' ? 'declined' : 'kept');
        cms_audit('care', $phone, 'ask');
        cms_flash('ok', $action === 'decline-ask' ? 'درخواست رد شد و نوبت سر جایش ماند.' : 'درخواست بسته شد. اگر ساعت را جدا جابه‌جا نکرده باشید، نوبت همان است.');
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'save-stages') {
        $posted = is_array($_POST['stage'] ?? null) ? $_POST['stage'] : [];
        $pending = [];
        foreach (cms_care_stages() as $stage) {
            $key = $stage['key'];
            $row = is_array($posted[$key] ?? null) ? $posted[$key] : [];
            $fa = cms_plain((string) ($row['fa'] ?? ''), 80);
            if (cms_chars($fa) < 2) {
                cms_flash('bad', 'نام فارسی هر مرحله لازم است.');
                desk_redirect('page=patients');
            }
            $pending[] = [
                $fa,
                cms_plain((string) ($row['en'] ?? ''), 80),
                cms_plain((string) ($row['ar'] ?? ''), 80),
                cms_plain((string) ($row['zh'] ?? ''), 80),
                isset($row['active']) ? 1 : 0,
                max(0, min(999, (int) ($row['sort'] ?? 0))),
                $key,
            ];
        }
        $newKey = strtolower(trim((string) ($_POST['new_stage_key'] ?? '')));
        $newFa = cms_plain((string) ($_POST['new_stage_fa'] ?? ''), 80);
        if ($newKey !== '' || $newFa !== '') {
            if (!preg_match('/^[a-z][a-z0-9-]{1,32}$/', $newKey) || cms_chars($newFa) < 2) {
                cms_flash('bad', 'شناسه مرحله با حرف انگلیسی شروع می‌شود و نام فارسی لازم است.');
                desk_redirect('page=patients');
            }
            $exists = cms_db()->prepare('SELECT 1 FROM care_stages WHERE stage_key = ?');
            $exists->execute([$newKey]);
            if ($exists->fetchColumn()) {
                cms_flash('bad', 'این شناسه مرحله قبلاً هست.');
                desk_redirect('page=patients');
            }
        }
        $deleteKeys = [];
        $used = cms_db()->prepare('SELECT COUNT(*) FROM care_facts WHERE stage_key = ?');
        foreach ((array) ($_POST['delete_stage'] ?? []) as $key) {
            $key = (string) $key;
            if (!preg_match('/^[a-z][a-z0-9-]{1,32}$/', $key)) {
                continue;
            }
            $used->execute([$key]);
            if ((int) $used->fetchColumn() > 0) {
                cms_flash('bad', 'مرحله‌ای که روی پرونده بیماری است حذف نمی‌شود. اول مرحله آن پرونده را بردارید.');
                desk_redirect('page=patients');
            }
            $deleteKeys[] = $key;
        }
        $update = cms_db()->prepare('UPDATE care_stages SET label_fa = ?, label_en = ?, label_ar = ?, label_zh = ?, active = ?, sort_order = ? WHERE stage_key = ?');
        foreach ($pending as $item) {
            $update->execute($item);
        }
        foreach ($deleteKeys as $key) {
            cms_db()->prepare('DELETE FROM care_stages WHERE stage_key = ?')->execute([$key]);
        }
        if ($newKey !== '') {
            cms_db()->prepare('INSERT INTO care_stages (stage_key, label_fa, label_en, label_ar, label_zh, active, sort_order) VALUES (?,?,?,?,?,1,?)')->execute([
                $newKey,
                $newFa,
                cms_plain((string) ($_POST['new_stage_en'] ?? ''), 80),
                cms_plain((string) ($_POST['new_stage_ar'] ?? ''), 80),
                cms_plain((string) ($_POST['new_stage_zh'] ?? ''), 80),
                max(0, min(999, (int) ($_POST['new_stage_sort'] ?? 0))),
            ]);
        }
        if ((int) cms_db()->query('SELECT COUNT(*) FROM care_stages')->fetchColumn() > 0 && (int) cms_db()->query('SELECT COUNT(*) FROM care_stages WHERE active = 1')->fetchColumn() === 0) {
            $first = (string) cms_db()->query('SELECT stage_key FROM care_stages ORDER BY sort_order, stage_key LIMIT 1')->fetchColumn();
            if ($first !== '') {
                cms_db()->prepare('UPDATE care_stages SET active = 1 WHERE stage_key = ?')->execute([$first]);
            }
        }
        cms_audit('care', 'stages', 'save');
        cms_flash('ok', 'مرحله‌ها ذخیره شد. این برچسب‌ها نتیجه درمان نیستند و نامشان را برای هر مطب می‌توان عوض کرد.');
        desk_redirect('page=patients');
    }

    if ($action === 'notify-visit') {
        $row = cms_visit_row((int) ($_POST['id'] ?? 0));
        $event = (string) ($_POST['event'] ?? '');
        if (!$row || !isset(cms_notify_events()[$event]) || $event === 'test') {
            cms_flash('bad', 'پیام دوباره فرستاده نشد.');
            desk_redirect('page=appointments');
        }
        cms_notify_visit($row, $event);
        cms_audit('visit-message', (string) $row['code'], $event);
        cms_flash('ok', 'پیام «' . cms_notify_events()[$event] . '» برای بیمار و پزشک، روی مسیرهای روشن، فرستاده شد.');
        desk_redirect('page=appointments');
    }

    if ($action === 'save-place') {
        if (!$owner) {
            cms_flash('bad', 'مکان‌ها را فقط مالک میز می‌سازد.');
            desk_redirect('page=schedule');
        }
        $saved = cms_place_save([
            'key' => (string) ($_POST['key'] ?? ''),
            'label_fa' => (string) ($_POST['label_fa'] ?? ''),
            'kind' => (string) ($_POST['kind'] ?? 'clinic'),
            'address' => (string) ($_POST['address'] ?? ''),
            'weekdays' => (array) ($_POST['days'] ?? []),
            'start' => (string) ($_POST['start'] ?? ''),
            'end' => (string) ($_POST['end'] ?? ''),
            'active' => isset($_POST['active']),
            'secretary' => (string) ($_POST['secretary'] ?? ''),
        ]);
        cms_flash($saved['ok'] ? 'ok' : 'bad', $saved['ok'] ? 'مکان ذخیره شد. بیمار مکان، روز و نزدیک‌ترین ساعت آزاد را می‌بیند. آدرس فقط همان متنی است که نوشتید.' : $saved['error']);
        if ($saved['ok']) {
            cms_audit('schedule', (string) $saved['key'], 'place');
        }
        desk_redirect('page=schedule');
    }

    if ($action === 'save-room') {
        if (!$owner) {
            cms_flash('bad', 'اتاق را فقط مالک میز می‌سازد.');
            desk_redirect('page=schedule');
        }
        $saved = cms_room_save((string) ($_POST['place'] ?? ''), (string) ($_POST['key'] ?? ''), (string) ($_POST['label'] ?? ''));
        cms_flash($saved['ok'] ? 'ok' : 'bad', $saved['ok'] ? 'اتاق ذخیره شد. نامش را خودتان نوشتید و آدرس تازه‌ای ساخته نشد.' : $saved['error']);
        if ($saved['ok']) {
            cms_audit('schedule', (string) ($_POST['place'] ?? ''), 'room');
        }
        desk_redirect('page=schedule');
    }

    if ($action === 'save-call') {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $staff = cms_plain((string) ($user['display_name'] ?? $user['username'] ?? ''), 80);
        $saved = cms_call_add($phone, (int) ($_POST['id'] ?? 0), (string) ($_POST['result'] ?? ''), (string) ($_POST['note'] ?? ''), $staff);
        cms_flash($saved['ok'] ? 'ok' : 'bad', $saved['ok'] ? 'تماس در دفتر ماند. بیمار این دفتر را نمی‌بیند و پیامک نمی‌رود.' : $saved['error']);
        if ($saved['ok']) {
            cms_audit('care', $phone, (string) $saved['result']);
        }
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'assign-need') {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $ok = cms_care_need_assign($phone, (int) ($_POST['id'] ?? 0), (string) ($_POST['staff'] ?? ''));
        cms_flash($ok ? 'ok' : 'bad', $ok ? 'مسئول این کار روی میز ماند. بیمار نام مسئول را نمی‌بیند.' : 'این کار پیدا نشد.');
        if ($ok) {
            cms_audit('care', $phone, 'assign');
        }
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if (in_array($action, ['save-teeth', 'save-checks', 'toggle-check', 'save-anesthesia'], true)) {
        $phone = cms_care_phone((string) ($_POST['phone'] ?? ''));
        $knownPhone = $phone !== '' ? cms_db()->prepare('SELECT 1 FROM visits WHERE phone = ?') : null;
        if ($knownPhone) {
            $knownPhone->execute([$phone]);
        }
        if (!$knownPhone || !$knownPhone->fetchColumn()) {
            cms_flash('bad', 'این شماره نوبتی در میز ندارد.');
            desk_redirect('page=patients');
        }
        $visitId = (int) ($_POST['id'] ?? 0);
        if ($action === 'save-teeth') {
            $saved = cms_teeth_save($phone, (array) ($_POST['tooth'] ?? []));
            cms_flash($saved['ok'] ? 'ok' : 'bad', $saved['ok'] ? 'نمودار دندان ذخیره شد. این شمارهٔ FDI است، نه تشخیص و نه کد خدمت. در پیامک نمی‌رود.' : $saved['error']);
            if ($saved['ok']) {
                cms_audit('care', $phone, 'tooth');
            }
        } elseif ($action === 'save-checks') {
            if (!cms_care_owns_visit($phone, $visitId)) {
                cms_flash('bad', 'این نوبت برای همین شماره نیست.');
            } else {
                $saved = cms_check_apply($visitId, (string) ($_POST['lines'] ?? ''));
                cms_flash($saved['ok'] ? 'ok' : 'bad', $saved['ok'] ? 'فهرست پیش از ورود ذخیره شد. پروتکل بیهوشی نیست و در پیامک نمی‌رود.' : $saved['error']);
                if ($saved['ok']) {
                    cms_audit('care', $phone, 'check');
                }
            }
        } elseif ($action === 'toggle-check') {
            $ok = cms_care_owns_visit($phone, $visitId) && cms_check_toggle((int) ($_POST['check'] ?? 0), $visitId);
            cms_flash($ok ? 'ok' : 'bad', $ok ? 'وضعیت همان سطر عوض شد.' : 'این سطر پیدا نشد.');
        } else {
            $ok = cms_visit_anesthesia($visitId, $phone, (string) ($_POST['anesthesia'] ?? ''));
            cms_flash($ok ? 'ok' : 'bad', $ok ? 'یادداشت بیهوشی فقط روی میز ماند. بیمار و پیامک آن را نمی‌بینند.' : 'این نوبت برای همین شماره نیست.');
            if ($ok) {
                cms_audit('care', $phone, 'sleep');
            }
        }
        desk_redirect('page=patients&phone=' . rawurlencode($phone));
    }

    if ($action === 'save-schedule') {
        $from = trim((string) ($_POST['slot_from'] ?? ''));
        $until = trim((string) ($_POST['slot_until'] ?? ''));
        $step = (int) ($_POST['slot_step'] ?? 0);
        $slots = [];
        if ($from !== '' || $until !== '' || $step !== 0) {
            $startMin = cms_clock_minutes($from);
            $endMin = cms_clock_minutes($until);
            if ($startMin < 0 || $endMin < $startMin || $step < 5 || $step > 180) {
                cms_flash('bad', 'ساخت ساعت‌ها از، تا و فاصله می‌خواهد. فاصله از ۵ تا ۱۸۰ دقیقه است.');
                desk_redirect('page=schedule');
            }
            for ($minute = $startMin; $minute <= $endMin && count($slots) < 48; $minute += $step) {
                $slots[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            }
        } else {
            foreach (explode(',', (string) ($_POST['book_slots'] ?? '')) as $part) {
                $part = trim($part);
                if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $part)) {
                    $slots[] = $part;
                }
            }
        }
        $slots = array_values(array_unique($slots));
        sort($slots);
        if (!$slots) {
            cms_flash('bad', 'دست‌کم یک ساعت مثل 16:00 لازم است.');
            desk_redirect('page=schedule');
        }
        $closed = [];
        foreach ((array) ($_POST['closed'] ?? []) as $day) {
            $day = (string) $day;
            if (preg_match('/^[0-6]$/', $day)) {
                $closed[] = $day;
            }
        }
        $capacity = (int) ($_POST['capacity'] ?? 1);
        if ($capacity < 1 || $capacity > 8) {
            $capacity = 1;
        }
        $horizon = (int) ($_POST['horizon'] ?? 90);
        if ($horizon < 7 || $horizon > 365) {
            $horizon = 90;
        }
        $lead = (int) ($_POST['lead'] ?? 0);
        if ($lead < 0 || $lead > 60) {
            $lead = 0;
        }
        if ($lead > $horizon) {
            $lead = $horizon;
        }
        $blockRaw = trim((string) ($_POST['block_day'] ?? ''));
        $block = $blockRaw === '' ? '' : cms_parse_day($blockRaw);
        if ($blockRaw !== '' && $block === '') {
            cms_flash('bad', 'تاریخ بسته شدن خوانده نشد. شمسی بنویسید، مثل 1405-08-29.');
            desk_redirect('page=schedule');
        }
        cms_set_setting('book_slots', implode(',', $slots));
        cms_set_setting('book_closed', implode(',', array_unique($closed)));
        cms_set_setting('book_capacity', (string) $capacity);
        $cutoff = (int) ($_POST['cutoff'] ?? 24);
        if ($cutoff < 0 || $cutoff > 23) {
            $cutoff = 24;
        }
        $phoneCap = (int) ($_POST['phone_cap'] ?? 0);
        if ($phoneCap < 0 || $phoneCap > 5) {
            $phoneCap = 0;
        }
        $emailMode = (string) ($_POST['email_mode'] ?? 'optional');
        $reasonMode = (string) ($_POST['reason_mode'] ?? 'optional');
        if (!in_array($emailMode, ['off', 'optional', 'required'], true)) {
            $emailMode = 'optional';
        }
        if (!in_array($reasonMode, ['off', 'optional', 'required'], true)) {
            $reasonMode = 'optional';
        }
        cms_set_setting('book_horizon', (string) $horizon);
        cms_set_setting('book_lead', (string) $lead);
        cms_set_setting('book_public', isset($_POST['book_public']) ? '1' : '0');
        cms_set_setting('book_cutoff', (string) $cutoff);
        cms_set_setting('book_phone_cap', (string) $phoneCap);
        cms_set_setting('book_hold_new', isset($_POST['hold_new']) ? '1' : '0');
        cms_set_setting('book_email', $emailMode);
        cms_set_setting('book_reason', $reasonMode);
        if ($block !== '') {
            cms_db()->prepare('INSERT OR REPLACE INTO visit_blocks (day, note) VALUES (?, ?)')->execute([$block, cms_plain((string) ($_POST['block_note'] ?? ''), 80)]);
        }
        $remove = (string) ($_POST['block_remove'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $remove)) {
            cms_db()->prepare('DELETE FROM visit_blocks WHERE day = ?')->execute([$remove]);
        }
        cms_audit('schedule', 'book', (string) $capacity);
        cms_flash('ok', 'برنامه مطب ذخیره شد. تقویم سایت همین ساعت‌ها، روزهای بسته و ظرفیت را نشان می‌دهد.');
        desk_redirect('page=schedule');
    }

    if ($action === 'save-kinds') {
        $keys = $_POST['kind_key'] ?? [];
        if (!is_array($keys)) {
            cms_flash('bad', 'نوع نوبت ذخیره نشد.');
            desk_redirect('page=schedule');
        }
        $dropping = [];
        foreach ((array) ($_POST['drop_kind'] ?? []) as $drop) {
            $drop = (string) $drop;
            if (preg_match('/^[a-z0-9-]{2,40}$/', $drop)) {
                $dropping[$drop] = true;
            }
        }
        $update = cms_db()->prepare('UPDATE visit_kinds SET label_fa = ?, label_en = ?, label_ar = ?, label_zh = ?, minutes = ?, active = ?, sort_order = ? WHERE kind_key = ?');
        $activeCount = 0;
        foreach ($keys as $key) {
            $key = (string) $key;
            if (!preg_match('/^[a-z0-9-]{2,40}$/', $key) || !cms_kind_row($key) || isset($dropping[$key])) {
                continue;
            }
            $minutes = (int) ($_POST['minutes'][$key] ?? 30);
            $minutes = max(10, min(180, $minutes));
            $sort = max(0, min(999, (int) ($_POST['sort'][$key] ?? 0)));
            $active = isset($_POST['active'][$key]) ? 1 : 0;
            $label = cms_plain((string) ($_POST['label_fa'][$key] ?? ''), 80);
            if ($label === '') {
                cms_flash('bad', 'نام فارسی نوع نوبت خالی نمی‌ماند.');
                desk_redirect('page=schedule');
            }
            $update->execute([
                $label,
                cms_plain((string) ($_POST['label_en'][$key] ?? ''), 80),
                cms_plain((string) ($_POST['label_ar'][$key] ?? ''), 80),
                cms_plain((string) ($_POST['label_zh'][$key] ?? ''), 80),
                $minutes,
                $active,
                $sort,
                $key,
            ]);
            $activeCount += $active;
        }
        $newKey = strtolower(trim((string) ($_POST['new_key'] ?? '')));
        $newLabel = cms_plain((string) ($_POST['new_label'] ?? ''), 80);
        $adding = $newKey !== '' || $newLabel !== '';
        $deletable = [];
        $blockedDrop = false;
        $usedCount = cms_db()->prepare('SELECT COUNT(*) FROM visits WHERE kind = ?');
        foreach (array_keys($dropping) as $drop) {
            $row = cms_kind_row($drop);
            if (!$row) {
                continue;
            }
            $usedCount->execute([$drop]);
            if ((int) $usedCount->fetchColumn() > 0) {
                $blockedDrop = true;
                $activeCount += $row['active'] ? 1 : 0;
                continue;
            }
            $deletable[] = $drop;
        }
        if ($activeCount < 1 && !$adding) {
            cms_db()->prepare("UPDATE visit_kinds SET active = 1 WHERE kind_key = 'exam'")->execute();
            cms_flash('bad', 'دست‌کم یک نوع نوبت باید روشن بماند. معاینه دوباره روشن شد.');
            desk_redirect('page=schedule');
        }
        if ($adding) {
            $exists = cms_db()->prepare('SELECT 1 FROM visit_kinds WHERE kind_key = ?');
            $exists->execute([$newKey]);
            $taken = (bool) $exists->fetchColumn() && !in_array($newKey, $deletable, true);
            if (!preg_match('/^[a-z0-9-]{2,40}$/', $newKey) || $newLabel === '' || $taken) {
                cms_flash('bad', 'نوع تازه یک شناسه انگلیسی و یک نام فارسی می‌خواهد و نباید تکراری باشد.');
                desk_redirect('page=schedule');
            }
        }
        if ($deletable) {
            $delete = cms_db()->prepare('DELETE FROM visit_kinds WHERE kind_key = ?');
            foreach ($deletable as $drop) {
                $delete->execute([$drop]);
            }
        }
        if ($adding) {
            $exists = cms_db()->prepare('SELECT 1 FROM visit_kinds WHERE kind_key = ?');
            $exists->execute([$newKey]);
            if ($exists->fetchColumn()) {
                cms_flash('bad', 'نوع تازه یک شناسه انگلیسی و یک نام فارسی می‌خواهد و نباید تکراری باشد.');
                desk_redirect('page=schedule');
            }
            $minutes = max(10, min(180, (int) ($_POST['new_minutes'] ?? 30)));
            $sort = (int) cms_db()->query('SELECT COUNT(*) FROM visit_kinds')->fetchColumn() + 1;
            cms_db()->prepare('INSERT INTO visit_kinds (kind_key, label_fa, label_en, label_ar, label_zh, minutes, active, sort_order) VALUES (?,?,?,?,?,?,1,?)')->execute([$newKey, $newLabel, '', '', '', $minutes, $sort]);
            $activeCount++;
        }
        if ($activeCount < 1) {
            cms_db()->prepare("UPDATE visit_kinds SET active = 1 WHERE kind_key = 'exam'")->execute();
            cms_flash('bad', 'دست‌کم یک نوع نوبت باید روشن بماند. معاینه دوباره روشن شد.');
            desk_redirect('page=schedule');
        }
        cms_audit('schedule', 'kinds', (string) $activeCount);
        cms_flash($blockedDrop ? 'bad' : 'ok', $blockedDrop ? 'نوعی که روی نوبت مانده حذف نشد. بقیه ذخیره شد.' : 'انواع نوبت ذخیره شد.');
        desk_redirect('page=schedule');
    }

    if ($action === 'save-topics') {
        $keys = $_POST['topic_key'] ?? [];
        if (!is_array($keys)) {
            cms_flash('bad', 'موضوع مراجعه ذخیره نشد.');
            desk_redirect('page=schedule');
        }
        $dropping = [];
        foreach ((array) ($_POST['drop_topic'] ?? []) as $drop) {
            $drop = (string) $drop;
            if (preg_match('/^[a-z0-9-]{2,40}$/', $drop)) {
                $dropping[$drop] = true;
            }
        }
        $update = cms_db()->prepare('UPDATE visit_topics SET label_fa = ?, label_en = ?, label_ar = ?, label_zh = ?, active = ?, sort_order = ?, service_code = ? WHERE topic_key = ?');
        $activeCount = 0;
        foreach ($keys as $key) {
            $key = (string) $key;
            if (!preg_match('/^[a-z0-9-]{2,40}$/', $key) || !cms_topic_row($key) || isset($dropping[$key])) {
                continue;
            }
            $active = isset($_POST['topic_active'][$key]) ? 1 : 0;
            $sort = max(0, min(999, (int) ($_POST['topic_sort'][$key] ?? 0)));
            $label = cms_plain((string) ($_POST['topic_fa'][$key] ?? ''), 80);
            if ($label === '') {
                cms_flash('bad', 'نام فارسی موضوع مراجعه خالی نمی‌ماند.');
                desk_redirect('page=schedule');
            }
            $update->execute([
                $label,
                cms_plain((string) ($_POST['topic_en'][$key] ?? ''), 80),
                cms_plain((string) ($_POST['topic_ar'][$key] ?? ''), 80),
                cms_plain((string) ($_POST['topic_zh'][$key] ?? ''), 80),
                $active,
                $sort,
                cms_plain((string) ($_POST['topic_code'][$key] ?? ''), 24),
                $key,
            ]);
            $activeCount += $active;
        }
        $newKey = strtolower(trim((string) ($_POST['new_topic_key'] ?? '')));
        $newLabel = cms_plain((string) ($_POST['new_topic_label'] ?? ''), 80);
        $adding = $newKey !== '' || $newLabel !== '';
        $deletable = [];
        $blockedDrop = false;
        $usedCount = cms_db()->prepare('SELECT COUNT(*) FROM visits WHERE service = ?');
        foreach (array_keys($dropping) as $drop) {
            $row = cms_topic_row($drop);
            if (!$row) {
                continue;
            }
            $usedCount->execute([$drop]);
            if ((int) $usedCount->fetchColumn() > 0) {
                $blockedDrop = true;
                $activeCount += $row['active'] ? 1 : 0;
                continue;
            }
            $deletable[] = $drop;
        }
        if ($activeCount < 1 && !$adding) {
            cms_db()->prepare("UPDATE visit_topics SET active = 1 WHERE topic_key = 'unsure'")->execute();
            cms_flash('bad', 'دست‌کم یک موضوع باید روشن بماند.');
            desk_redirect('page=schedule');
        }
        if ($adding) {
            $exists = cms_db()->prepare('SELECT 1 FROM visit_topics WHERE topic_key = ?');
            $exists->execute([$newKey]);
            $taken = (bool) $exists->fetchColumn() && !in_array($newKey, $deletable, true);
            if (!preg_match('/^[a-z0-9-]{2,40}$/', $newKey) || $newLabel === '' || $taken) {
                cms_flash('bad', 'موضوع تازه یک شناسه انگلیسی و یک نام فارسی می‌خواهد و نباید تکراری باشد.');
                desk_redirect('page=schedule');
            }
        }
        if ($deletable) {
            $delete = cms_db()->prepare('DELETE FROM visit_topics WHERE topic_key = ?');
            foreach ($deletable as $drop) {
                $delete->execute([$drop]);
            }
        }
        if ($adding) {
            $exists = cms_db()->prepare('SELECT 1 FROM visit_topics WHERE topic_key = ?');
            $exists->execute([$newKey]);
            if ($exists->fetchColumn()) {
                cms_flash('bad', 'موضوع تازه یک شناسه انگلیسی و یک نام فارسی می‌خواهد و نباید تکراری باشد.');
                desk_redirect('page=schedule');
            }
            $sort = (int) cms_db()->query('SELECT COUNT(*) FROM visit_topics')->fetchColumn() + 1;
            cms_db()->prepare('INSERT INTO visit_topics (topic_key, label_fa, label_en, label_ar, label_zh, active, sort_order) VALUES (?,?,?,?,?,1,?)')->execute([$newKey, $newLabel, '', '', '', $sort]);
            $activeCount++;
        }
        if ($activeCount < 1) {
            cms_db()->prepare("UPDATE visit_topics SET active = 1 WHERE topic_key = 'unsure'")->execute();
            cms_flash('bad', 'دست‌کم یک موضوع باید روشن بماند.');
            desk_redirect('page=schedule');
        }
        cms_audit('schedule', 'topics', (string) $activeCount);
        cms_flash($blockedDrop ? 'bad' : 'ok', $blockedDrop ? 'موضوعی که روی نوبت مانده حذف نشد. بقیه ذخیره شد.' : 'موضوع‌های مراجعه ذخیره شد. صفحه درمان‌ها عوض نمی‌شود.');
        desk_redirect('page=schedule');
    }

    if ($action === 'save-messaging') {
        cms_set_setting('sms_on', isset($_POST['sms_on']) ? '1' : '0');
        cms_set_setting('sms_mode', (($_POST['sms_mode'] ?? '') === 'console') ? 'console' : 'rest');
        $userName = trim((string) ($_POST['sms_user'] ?? ''));
        if ($userName === '' || preg_match('/^[A-Za-z0-9._@-]{3,64}$/', $userName)) {
            cms_set_setting('sms_user', $userName);
        }
        $from = trim((string) ($_POST['sms_from'] ?? ''));
        if ($from === '' || preg_match('/^[0-9]{4,20}$/', $from)) {
            cms_set_setting('sms_from', $from);
        }
        $doctor = preg_replace('/\D+/', '', (string) ($_POST['sms_doctor'] ?? '')) ?? '';
        if (str_starts_with($doctor, '98')) {
            $doctor = '0' . substr($doctor, 2);
        }
        if ($doctor === '' || preg_match('/^09\d{9}$/', $doctor)) {
            cms_set_setting('sms_doctor', $doctor);
        } else {
            cms_flash('bad', 'موبایل پزشک باید با ۰۹ شروع شود.');
            desk_redirect('page=messaging');
        }
        if (isset($_POST['clear_sms_pass'])) {
            cms_setting_forget('sms_pass');
        } elseif (trim((string) ($_POST['sms_pass'] ?? '')) !== '') {
            cms_set_setting('sms_pass', (string) $_POST['sms_pass']);
        }
        if (isset($_POST['clear_sms_key'])) {
            cms_setting_forget('sms_key');
        } elseif (trim((string) ($_POST['sms_key'] ?? '')) !== '') {
            $key = trim((string) $_POST['sms_key']);
            if (preg_match('/^[A-Za-z0-9_-]{8,80}$/', $key)) {
                cms_set_setting('sms_key', $key);
            }
        }
        $quietFrom = (int) ($_POST['sms_quiet_from'] ?? 21);
        $quietUntil = (int) ($_POST['sms_quiet_until'] ?? 8);
        cms_set_setting('sms_quiet_from', (string) max(0, min(23, $quietFrom)));
        cms_set_setting('sms_quiet_until', (string) max(0, min(23, $quietUntil)));
        $remindDays = (int) ($_POST['remind_days'] ?? 1);
        if ($remindDays < 1 || $remindDays > 3) {
            $remindDays = 1;
        }
        cms_set_setting('remind_days', (string) $remindDays);
        $remindGrace = (int) ($_POST['remind_grace'] ?? 2);
        if ($remindGrace < 1 || $remindGrace > 7) {
            $remindGrace = 2;
        }
        cms_set_setting('remind_grace', (string) $remindGrace);
        foreach (cms_notify_events() as $event => $label) {
            cms_set_setting('notify_patient_' . $event, isset($_POST['patient'][$event]) ? '1' : '0');
            cms_set_setting('notify_doctor_' . $event, isset($_POST['doctor'][$event]) ? '1' : '0');
            foreach (['fa' => '', 'en' => '_en', 'ar' => '_ar', 'zh' => '_zh'] as $locale => $suffix) {
                $field = 'tpl_patient_' . $event . ($locale === 'fa' ? '' : '_' . $locale);
                $text = cms_plain((string) ($_POST[$field] ?? ''), 600);
                if ($text === '') {
                    cms_setting_forget('tpl_patient_' . $event . $suffix);
                } else {
                    cms_set_setting('tpl_patient_' . $event . $suffix, $text);
                }
            }
            $doctorText = cms_plain((string) ($_POST['tpl_doctor_' . $event] ?? ''), 600);
            if ($doctorText === '') {
                cms_setting_forget('tpl_doctor_' . $event);
            } else {
                cms_set_setting('tpl_doctor_' . $event, $doctorText);
            }
        }
        $testText = cms_plain((string) ($_POST['tpl_test'] ?? ''), 600);
        if ($testText === '') {
            cms_setting_forget('tpl_test');
        } else {
            cms_set_setting('tpl_test', $testText);
        }
        cms_set_setting('care_alert', isset($_POST['care_alert']) ? '1' : '0');
        cms_set_setting('care_self_sms', isset($_POST['care_self_sms']) ? '1' : '0');
        $selfTpl = cms_plain((string) ($_POST['tpl_care_self'] ?? ''), 600);
        if ($selfTpl === '') {
            cms_setting_forget('tpl_care_self');
        } else {
            cms_set_setting('tpl_care_self', $selfTpl);
        }
        $alertTpl = cms_plain((string) ($_POST['tpl_care_alert'] ?? ''), 300);
        if ($alertTpl === '') {
            cms_setting_forget('tpl_care_alert');
        } else {
            cms_set_setting('tpl_care_alert', $alertTpl);
        }
        foreach (['bale', 'telegram', 'eitaa'] as $channel) {
            cms_set_setting($channel . '_on', isset($_POST[$channel . '_on']) ? '1' : '0');
            $chat = trim((string) ($_POST[$channel . '_chat'] ?? ''));
            if ($chat === '' || preg_match('/^[A-Za-z0-9_@:-]{1,40}$/', $chat)) {
                cms_set_setting($channel . '_chat', $chat);
            }
            if (isset($_POST['clear_' . $channel])) {
                cms_setting_forget($channel . '_token');
            } elseif (trim((string) ($_POST[$channel . '_token'] ?? '')) !== '') {
                $token = trim((string) $_POST[$channel . '_token']);
                if (preg_match('/^[A-Za-z0-9:_-]{20,160}$/', $token)) {
                    cms_set_setting($channel . '_token', $token);
                } else {
                    cms_flash('bad', 'توکن پیام‌رسان شکل درستی ندارد و ذخیره نشد.');
                    desk_redirect('page=messaging');
                }
            }
        }
        cms_audit('messaging', 'save', '');
        cms_flash('ok', 'پیام‌رسانی ذخیره شد. رمز و توکن دوباره نشان داده نمی‌شود.');
        desk_redirect('page=messaging');
    }

    if ($action === 'pull-chat') {
        if (!$owner) {
            cms_flash('bad', 'گرفتن پاسخ پیام‌رسان فقط برای مالک میز است.');
            desk_redirect('page=appointments');
        }
        $channel = (string) ($_POST['channel'] ?? '');
        $result = cms_chat_pull($channel);
        if ($result['ok']) {
            cms_audit('care', $channel, 'reply');
            cms_flash('ok', (int) $result['kept'] > 0 ? 'پاسخ پزشک به همان پیام، در پروندهٔ بیمار نشست. پیام تازه‌ای فرستاده نشد.' : 'پاسخی که به پیام میز باشد نیامد. پیام تازه‌ای فرستاده نشد.');
        } else {
            cms_flash('bad', $result['error']);
        }
        desk_redirect('page=messaging');
    }

    if ($action === 'send-test') {
        $channel = (string) ($_POST['channel'] ?? '');
        $result = cms_notify_test($channel);
        cms_flash($result['ok'] ? 'ok' : 'bad', $result['ok'] ? 'پیام آزمایشی فرستاده شد.' : $result['error']);
        desk_redirect('page=messaging');
    }

    if ($action === 'send-reminders') {
        if (cms_quiet_now()) {
            $until = cms_fa_digits((string) (int) cms_setting('sms_quiet_until', '8'));
            $from = cms_fa_digits((string) (int) cms_setting('sms_quiet_from', '21'));
            cms_flash('bad', 'الان ساعت خاموشی است. یادآوری بین ' . $until . ' و ' . $from . ' به وقت تهران می‌رود و این اجرا چیزی را علامت نمی‌زند.');
            desk_redirect('page=messaging');
        }
        $visit = cms_remind_due();
        $self = cms_care_self_due();
        $sent = $visit['sent'] + $self['sent'];
        cms_audit('messaging', 'remind', (string) $sent);
        $late = $visit['late'] + $self['late'];
        if ($sent < 1 && $self['expired'] < 1) {
            cms_flash('ok', 'در این بازه یادآوری تازه‌ای نبود.');
        } else {
            $text = cms_fa_digits((string) $sent) . ' یادآوری بررسی شد';
            if ($late > 0) {
                $text .= '، که ' . cms_fa_digits((string) $late) . ' تا جبران روز قبل بود';
            }
            if ($self['expired'] > 0) {
                $text .= '. ' . cms_fa_digits((string) $self['expired']) . ' یادآوری شخصی از بازه گذشت و پیامک نشد';
            }
            cms_flash('ok', $text . '.');
        }
        desk_redirect('page=messaging');
    }

    return true;
}

function desk_iso_from_jalali(int $year, int $month, int $day): string
{
    $gregorian = cms_j2g($year, $month, $day);
    return sprintf('%04d-%02d-%02d', $gregorian['gy'], $gregorian['gm'], $gregorian['gd']);
}

function desk_book_href(array $parts): string
{
    $query = ['page' => 'appointments'];
    foreach ($parts as $key => $value) {
        if ($value !== '') {
            $query[$key] = $value;
        }
    }
    return '/manage/index.php?' . http_build_query($query);
}

function desk_render_month(int $year, int $month, string $today, string $selected, string $status, string $q): void
{
    $names = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $heads = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
    $length = cms_jalali_month_length($year, $month);
    $first = desk_iso_from_jalali($year, $month, 1);
    $last = desk_iso_from_jalali($year, $month, $length);
    $zone = new DateTimeZone('Asia/Tehran');
    $firstDate = DateTimeImmutable::createFromFormat('!Y-m-d', $first, $zone);
    $lead = $firstDate ? ((int) $firstDate->format('w') + 1) % 7 : 0;
    $counts = [];
    $fresh = [];
    $stmt = cms_db()->prepare('SELECT day, status, COUNT(*) AS total FROM visits WHERE day >= ? AND day <= ? GROUP BY day, status');
    $stmt->execute([$first, $last]);
    foreach ($stmt as $row) {
        $iso = (string) $row['day'];
        $total = (int) $row['total'];
        if (in_array((string) $row['status'], ['new', 'confirmed', 'arrived'], true)) {
            $counts[$iso] = ($counts[$iso] ?? 0) + $total;
        }
        if ((string) $row['status'] === 'new') {
            $fresh[$iso] = true;
        }
    }
    $blocks = [];
    foreach (cms_book_blocks() as $block) {
        $blocks[$block['day']] = true;
    }
    $closed = cms_book_closed_weekdays();
    $prevMonth = $month === 1 ? 12 : $month - 1;
    $prevYear = $month === 1 ? $year - 1 : $year;
    $nextMonth = $month === 12 ? 1 : $month + 1;
    $nextYear = $month === 12 ? $year + 1 : $year;
    echo '<section class="card"><div class="row"><a class="btn ghost" href="' . e(desk_book_href(['jy' => (string) $prevYear, 'jm' => (string) $prevMonth, 'status' => $status, 'q' => $q, 'day' => $selected])) . '">ماه قبل</a>';
    echo '<h2>' . e($names[$month - 1] . ' ' . cms_fa_digits((string) $year)) . '</h2>';
    echo '<a class="btn ghost" href="' . e(desk_book_href(['jy' => (string) $nextYear, 'jm' => (string) $nextMonth, 'status' => $status, 'q' => $q, 'day' => $selected])) . '">ماه بعد</a></div>';
    echo '<div class="month">';
    foreach ($heads as $head) {
        echo '<b>' . e($head) . '</b>';
    }
    for ($i = 0; $i < $lead; $i++) {
        echo '<span class="blank"></span>';
    }
    for ($dayNumber = 1; $dayNumber <= $length; $dayNumber++) {
        $iso = desk_iso_from_jalali($year, $month, $dayNumber);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $iso, $zone);
        $shut = $date && (in_array((int) $date->format('w'), $closed, true) || isset($blocks[$iso]));
        $class = trim(($iso === $selected ? 'on' : '') . ' ' . ($iso === $today ? 'today' : '') . ' ' . ($shut ? 'shut' : ''));
        $count = $counts[$iso] ?? 0;
        echo '<a class="' . e($class) . '" href="' . e(desk_book_href(['day' => $iso, 'jy' => (string) $year, 'jm' => (string) $month, 'status' => $status, 'q' => $q])) . '">';
        echo '<strong>' . e(cms_fa_digits((string) $dayNumber)) . '</strong>';
        if ($count > 0) {
            echo '<small>' . e(cms_fa_digits((string) $count)) . ' نوبت' . (isset($fresh[$iso]) ? ' · تازه' : '') . '</small>';
        } elseif ($shut) {
            echo '<small>بسته</small>';
        }
        echo '</a>';
    }
    echo '</div><p class="help">عدد هر روز، درخواست تازه و نوبت هماهنگ‌شده و حاضر شده است. روز را بزنید تا دفتر همان روز و کارت‌ها پایین بیاید.</p></section>';
}

function desk_jalali_iso(string $iso): string
{
    return cms_shamsi_iso($iso);
}

function desk_move_form(array $visit): string
{
    $html = '<form class="row" method="post">' . desk_csrf();
    $html .= '<input type="hidden" name="action" value="move-visit"><input type="hidden" name="id" value="' . (int) $visit['id'] . '">';
    $html .= '<input name="day" dir="ltr" value="' . e(desk_jalali_iso((string) $visit['day'])) . '">';
    $html .= '<select name="time">';
    $listed = cms_book_slots();
    if (!in_array((string) $visit['time_slot'], $listed, true)) {
        $html .= '<option value="" selected>ساعت تازه</option>';
    }
    foreach ($listed as $slot) {
        $html .= '<option value="' . e($slot) . '"' . ((string) $visit['time_slot'] === $slot ? ' selected' : '') . '>' . e(cms_fa_digits($slot)) . '</option>';
    }
    $html .= '</select><label><span><input type="checkbox" name="force"> روی ساعت پر یا روز بسته</span></label>';
    $html .= '<button class="btn" type="submit">جابه‌جایی</button></form>';
    return $html;
}

function desk_status_form(array $visit): string
{
    $html = '<form class="row" method="post">' . desk_csrf();
    $html .= '<input type="hidden" name="action" value="status-visit"><input type="hidden" name="id" value="' . (int) $visit['id'] . '">';
    $html .= '<select name="status">';
    foreach (cms_visit_statuses() as $key => $label) {
        $html .= '<option value="' . e($key) . '"' . ((string) $visit['status'] === $key ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $html .= '</select><button class="btn" type="submit">ثبت وضعیت</button></form>';
    return $html;
}

function desk_render_queue(): void
{
    $rows = cms_wait_rows(8);
    echo '<section class="card slip queue"><div class="row"><h2>صف انتظار</h2>';
    echo '<a class="btn ghost" href="/manage/index.php?page=appointments&status=new">همهٔ درخواست‌ها</a></div>';
    if (!$rows) {
        echo '<p class="help">درخواست تازه‌ای در صف نیست.</p></section>';
        return;
    }
    echo '<div class="queue-row">';
    foreach ($rows as $visit) {
        $place = '';
        $found = cms_place_row((string) ($visit['place_key'] ?? ''));
        if ($found) {
            $place = cms_place_label($found, 'fa');
        }
        echo '<a class="ticket" href="/manage/index.php?page=patients&phone=' . e(rawurlencode((string) $visit['phone'])) . '">';
        echo '<b>' . e((string) $visit['name']) . '</b>';
        echo '<small>' . e(cms_day_label((string) $visit['day'])) . ' · <span dir="ltr">' . e(cms_fa_digits((string) $visit['time_slot'])) . '</span></small>';
        echo '<small>' . e(cms_kind_label((string) $visit['kind'])) . ($place !== '' ? ' · ' . e($place) : '') . '</small>';
        if ((string) ($visit['booked_by'] ?? '') !== '') {
            echo '<small>' . e((string) $visit['booked_by']) . '</small>';
        }
        echo '</a>';
    }
    echo '</div></section>';
}

function desk_render_week(string $focus): void
{
    $days = cms_week_days($focus);
    $start = $days[0];
    $end = $days[6];
    $prev = (new DateTimeImmutable($start, new DateTimeZone('Asia/Tehran')))->modify('-7 days')->format('Y-m-d');
    $next = (new DateTimeImmutable($start, new DateTimeZone('Asia/Tehran')))->modify('+7 days')->format('Y-m-d');
    $today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    $deskUser = cms_user();
    $staffPlace = is_array($deskUser) ? (string) ($deskUser['place_key'] ?? '') : '';
    $places = [];
    foreach (cms_places(true) as $place) {
        if (cms_staff_may($staffPlace, (string) $place['place_key'])) {
            $places[] = $place;
        }
    }
    $stmt = cms_db()->prepare('SELECT * FROM visits WHERE day >= ? AND day <= ? ORDER BY time_slot, id');
    $stmt->execute([$start, $end]);
    $visits = $stmt->fetchAll();
    $blocked = [];
    foreach (cms_book_blocks() as $block) {
        $blocked[(string) $block['day']] = (string) $block['note'];
    }
    $names = ['clinic' => 'مطب', 'hospital' => 'بیمارستان', 'other' => 'جای دیگر'];
    echo '<section class="card week slip"><div class="row"><h2>هفتهٔ ' . e(cms_day_label($start)) . '</h2>';
    echo '<a class="btn ghost no-print" href="/manage/index.php?page=appointments&day=' . e($prev) . '">هفته قبل</a>';
    echo '<a class="btn ghost no-print" href="/manage/index.php?page=appointments&day=' . e($today) . '">این هفته</a>';
    echo '<a class="btn ghost no-print" href="/manage/index.php?page=appointments&day=' . e($next) . '">هفته بعد</a></div>';
    echo '<p class="help">شنبه اول هفته است. هر ردیف یک مکان است. اگر پزشک همان ساعت جای دیگری باشد، نام، مکان و ثبت‌کننده اینجا دیده می‌شود و ساعت برای رزرو تازه پر است.</p>';
    $hasBlank = false;
    if ($places !== []) {
        foreach ($visits as $visit) {
            if ((string) ($visit['place_key'] ?? '') === '') {
                $hasBlank = true;
                break;
            }
        }
    }
    echo '<div class="weekboard-wrap"><div class="weekboard">';
    echo '<div class="head"></div>';
    foreach ($days as $day) {
        $date = new DateTimeImmutable($day, new DateTimeZone('Asia/Tehran'));
        $weekName = desk_weekdays()[(int) $date->format('w')] ?? '';
        $class = $day === $today ? ' head today' : ' head';
        echo '<a class="' . $class . '" href="/manage/index.php?page=appointments&day=' . e($day) . '"><b>' . e($weekName) . '</b><small dir="ltr">' . e(cms_fa_digits(str_replace('-', '/', cms_shamsi_iso($day)))) . '</small></a>';
    }
    $rows = $places !== [] ? $places : [null];
    if ($hasBlank) {
        $rows[] = ['place_key' => '*', 'kind' => 'other', 'label_fa' => 'بدون مکان', 'weekdays' => '0,1,2,3,4,5,6', 'start_time' => '', 'end_time' => ''];
    }
    foreach ($rows as $place) {
        $label = 'برنامهٔ عمومی';
        $key = '';
        $openDays = [];
        $startTime = '';
        $endTime = '';
        if (is_array($place)) {
            $key = (string) $place['place_key'];
            $label = ($names[(string) $place['kind']] ?? 'مطب') . ' · ' . (string) $place['label_fa'];
            $openDays = cms_place_weekdays((string) $place['weekdays']);
            $startTime = (string) $place['start_time'];
            $endTime = (string) $place['end_time'];
        }
        echo '<div class="place">' . e($label) . '</div>';
        foreach ($days as $day) {
            $date = new DateTimeImmutable($day, new DateTimeZone('Asia/Tehran'));
            $weekday = (int) $date->format('w');
            $shut = isset($blocked[$day]) || ($openDays !== [] && !in_array($weekday, $openDays, true));
            echo '<div class="cell' . ($shut ? ' shut' : '') . ($day === $today ? ' today' : '') . '">';
            if (isset($blocked[$day])) {
                echo '<small>' . e($blocked[$day] !== '' ? $blocked[$day] : 'تعطیل') . '</small>';
            } elseif ($shut) {
                echo '<small>این روز اینجا نیست</small>';
            }
            foreach ($visits as $visit) {
                if ((string) $visit['day'] !== $day) {
                    continue;
                }
                $visitPlace = (string) ($visit['place_key'] ?? '');
                if ($key === '*' && $visitPlace !== '') {
                    continue;
                }
                if ($key !== '' && $key !== '*' && $visitPlace !== $key) {
                    continue;
                }
                $by = (string) ($visit['booked_by'] ?? '');
                echo '<a class="bit" href="/manage/index.php?page=patients&phone=' . e(rawurlencode((string) $visit['phone'])) . '">';
                $roomName = cms_room_label((string) ($visit['place_key'] ?? ''), (string) ($visit['room_key'] ?? ''));
                echo '<b dir="ltr">' . e(cms_fa_digits((string) $visit['time_slot'])) . '</b> ' . e((string) $visit['name']);
                echo '<small>' . e(cms_kind_label((string) $visit['kind'])) . ($roomName !== '' ? ' · ' . $roomName : '') . ($by !== '' ? ' · ' . $by : '') . '</small></a>';
            }
            if ($key !== '' && $key !== '*' && !$shut) {
                $openStatuses = cms_slot_statuses();
                foreach ($visits as $visit) {
                    if ((string) $visit['day'] !== $day || (string) ($visit['place_key'] ?? '') === $key || !in_array((string) $visit['status'], $openStatuses, true)) {
                        continue;
                    }
                    $time = (string) $visit['time_slot'];
                    $inside = ($startTime !== '' && $endTime !== '' && $time >= $startTime && $time < $endTime) || cms_time_covers($time, cms_kind_minutes((string) $visit['kind']), $startTime);
                    if (!$inside) {
                        continue;
                    }
                    $other = cms_place_row((string) ($visit['place_key'] ?? ''));
                    $otherName = $other ? cms_place_label($other, 'fa') : 'جای دیگر';
                    $by = (string) ($visit['booked_by'] ?? '');
                    echo '<p class="away"><b dir="ltr">' . e(cms_fa_digits($time)) . '</b> پر در ' . e($otherName) . ' · ' . e((string) $visit['name']) . ($by !== '' ? ' · ' . e($by) : '') . '</p>';
                }
            }
            echo '</div>';
        }
    }
    echo '</div></div></section>';
}

function desk_render_diary(string $day): void
{
    echo '<section class="card diary"><div class="row"><h2>دفتر ' . e(cms_day_label($day)) . '</h2>';
    echo '<button type="button" class="btn ghost no-print" onclick="window.print()">چاپ دفتر این روز</button></div>';
    echo '<p class="help no-print">روز را شمسی بنویسید، مثل 1405-08-27. جابه‌جایی نوبت هماهنگ‌شده پیام تغییر زمان می‌فرستد. «هماهنگ شد» و «لغو شد» پیام همان وضعیت را می‌فرستند. «حاضر شد»، «انجام شد» و «نیامد» پیامی نمی‌فرستند. «انجام شد»، «لغو شد» و «نیامد» از عدد تقویم کنار می‌روند و «حاضر شد» در عدد همان روز می‌ماند. چاپ، یادداشت داخلی و علت مراجعه را نمی‌آورد.</p>';
    if (!cms_day_open($day) && $day >= (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d')) {
        echo '<p class="help">این روز در برنامه مطب بسته است. نوبت‌های ثبت‌شده همچنان اینجا دیده می‌شوند.</p>';
    }
    $stmt = cms_db()->prepare('SELECT * FROM visits WHERE day = ? ORDER BY time_slot, id');
    $stmt->execute([$day]);
    $visits = $stmt->fetchAll();
    $slots = cms_book_slots();
    if (!$slots && !$visits) {
        echo '<p>برای این روز ساعتی در برنامه نیست.</p></section>';
        return;
    }
    $shown = [];
    foreach ($slots as $slot) {
        echo '<div class="slot"><b dir="ltr">' . e(cms_fa_digits($slot)) . '</b><span>';
        $lines = [];
        foreach ($visits as $visit) {
            if (!cms_time_covers((string) $visit['time_slot'], cms_kind_minutes((string) $visit['kind']), $slot)) {
                continue;
            }
            $shown[(int) $visit['id']] = true;
            $start = (string) $visit['time_slot'] === $slot;
            $label = $start ? (string) $visit['name'] : 'ادامه ' . (string) $visit['name'];
            $placeName = '';
            $placeRow = cms_place_row((string) ($visit['place_key'] ?? ''));
            if ($placeRow) {
                $placeName = cms_place_label($placeRow, 'fa');
            }
            $line = e($label) . ' · ' . e(cms_kind_label((string) $visit['kind'])) . ' · ' . e(cms_visit_statuses()[$visit['status']] ?? (string) $visit['status']);
            if ($placeName !== '') {
                $line .= ' · ' . e($placeName);
            }
            $roomName = cms_room_label((string) ($visit['place_key'] ?? ''), (string) ($visit['room_key'] ?? ''));
            if ($roomName !== '') {
                $line .= ' · <span class="chip">' . e($roomName) . '</span>';
            }
            if ((string) ($visit['booked_by'] ?? '') !== '') {
                $line .= ' · ' . e((string) $visit['booked_by']);
            }
            $line .= ' · <span dir="ltr">' . e((string) $visit['phone']) . '</span>';
            $line .= ' · <a class="no-print" href="/manage/index.php?page=patients&phone=' . e(rawurlencode((string) $visit['phone'])) . '">پرونده</a>';
            if ($start && !cms_nurse()) {
                $line .= desk_status_form($visit) . desk_move_form($visit);
            }
            $lines[] = $line;
        }
        echo $lines ? implode('<br>', $lines) : 'خالی';
        echo '</span></div>';
    }
    foreach ($visits as $visit) {
        if (isset($shown[(int) $visit['id']])) {
            continue;
        }
        $extra = cms_nurse() ? '' : desk_status_form($visit) . desk_move_form($visit);
        echo '<div class="slot"><b dir="ltr">' . e(cms_fa_digits((string) $visit['time_slot'])) . '</b><span>' . e((string) $visit['name']) . ' · ' . e(cms_kind_label((string) $visit['kind'])) . ' · ' . e(cms_visit_statuses()[$visit['status']] ?? (string) $visit['status']) . ' · <span dir="ltr">' . e((string) $visit['phone']) . '</span> · این ساعت دیگر در برنامه نیست' . $extra . '</span></div>';
    }
    echo '</section>';
}

function desk_render_calls(string $phone, int $visitId): void
{
    echo '<section class="card calls"><h2>دفتر تماس</h2>';
    echo '<p class="help">این دفتر روی میز است. بیمار، همراه، فایل برگه و پیامک آن را نمی‌بینند و متنش در گزارش سیستم نوشته نمی‌شود.</p>';
    echo '<form method="post" class="call-form">' . desk_csrf() . '<input type="hidden" name="action" value="save-call"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . $visitId . '">';
    echo '<div class="outcomes">';
    foreach (cms_call_results() as $key => $label) {
        echo '<label class="outcome"><input type="radio" name="result" value="' . e($key) . '"' . ($key === 'reached' ? ' checked' : '') . '><span>' . e($label) . '</span></label>';
    }
    echo '</div><label>یادداشت کوتاه<input name="note" placeholder="ساعت پیشنهادی برای زنگ بعد"></label>';
    echo '<button class="btn" type="submit">ثبت تماس</button></form>';
    $rows = cms_calls($phone);
    if (!$rows) {
        echo '<p class="help">تماسی ثبت نشده.</p>';
    }
    foreach ($rows as $call) {
        $tone = ['reached' => 'yes', 'missed' => 'miss', 'callback' => 'back'][(string) $call['result']] ?? '';
        $label = cms_call_results()[(string) $call['result']] ?? (string) $call['result'];
        echo '<article class="call"><span class="chip ' . e($tone) . '">' . e($label) . '</span><div>';
        echo '<b>' . e((string) $call['staff'] !== '' ? (string) $call['staff'] : 'میز') . '</b> <small>' . e(cms_when((string) $call['created_at'], true)) . '</small>';
        if ((string) $call['note'] !== '') {
            echo '<p>' . e((string) $call['note']) . '</p>';
        }
        echo '</div></article>';
    }
    echo '</section>';
}

function desk_page_patients(): void
{
    $phone = preg_replace('/\D+/', '', (string) ($_GET['phone'] ?? '')) ?? '';
    if (str_starts_with($phone, '98') && strlen($phone) === 12) {
        $phone = '0' . substr($phone, 2);
    }
    if ($phone !== '') {
        $file = cms_patient_row($phone);
        $visits = cms_db()->prepare('SELECT * FROM visits WHERE phone = ? ORDER BY day DESC, time_slot DESC LIMIT 40');
        $visits->execute([$phone]);
        $rows = $visits->fetchAll();
        if (!$rows) {
            desk_open('پرونده', 'patients', 'این شماره نوبتی در میز ندارد.');
            echo '<p><a class="btn ghost" href="/manage/index.php?page=patients">بازگشت به فهرست</a></p>';
            desk_close();
            return;
        }
        $name = $file['name'] ?? (string) $rows[0]['name'];
        desk_open('پرونده ' . $name, 'patients', 'یادداشت ماندگار برای بیمار فرستاده نمی‌شود. متن روند درمان را بیمار در صفحه پرونده درمان می‌بیند.');
        if (cms_sms_stopped($phone)) {
            echo '<p class="pill warn">پیامک این بیمار قطع است</p>';
        }
        echo '<div class="row"><a class="btn ghost" href="/manage/index.php?page=patients">فهرست پرونده‌ها</a>';
        echo '<a class="btn ghost" href="/manage/index.php?page=appointments&q=' . e(rawurlencode($phone)) . '">کارت‌های این شماره</a>';
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="export-sheet"><input type="hidden" name="phone" value="' . e($phone) . '"><button class="btn" type="submit">PDF برگه</button></form></div>';
        cms_care_mark_seen($phone);
        $next = cms_care_next($phone);
        echo '<section class="card"><h2>نوبت بعدی</h2>';
        if (!$next) {
            echo '<p>نوبت بازی از امروز به بعد برای این شماره نیست.</p>';
        } else {
            echo '<p>' . e(cms_day_label((string) $next['day'])) . ' · <span dir="ltr">' . e(cms_fa_digits((string) $next['time_slot'])) . '</span> · ' . e(cms_visit_statuses()[(string) $next['status']] ?? (string) $next['status']) . ' · ' . e(cms_kind_label((string) $next['kind'])) . '</p>';
            echo '<p class="help">' . e((string) $next['source'] === 'site' ? 'درخواست بیمار از سایت. تا هماهنگ نشود قطعی نیست.' : 'این وقت را مطب ثبت کرده است.') . '</p>';
            $remind = cms_care_reminder_line($next);
            if ($remind !== '') {
                echo '<p class="help">' . e($remind) . '</p>';
            }
        }
        echo '</section>';
        desk_render_calls($phone, $next ? (int) $next['id'] : 0);
        $asks = cms_care_ask_map($phone);
        echo '<section class="card"><h2>درخواست زمان</h2>';
        echo '<p class="help">درخواست بیمار نوبت را عوض نمی‌کند و پیامک نوبت نمی‌فرستد. قبول لغو، وضعیت را لغو می‌کند. بستن درخواست جابه‌جایی، ساعت را عوض نمی‌کند.</p>';
        if (!$asks) {
            echo '<p>درخواست بازی نیست.</p>';
        }
        foreach ($asks as $ask) {
            $kind = (string) $ask['kind'] === 'cancel' ? 'لغو' : 'زمان دیگر';
            echo '<p><strong>' . e($kind) . '</strong> · ' . e(cms_when((string) $ask['created_at'], true));
            if ((string) $ask['day'] !== '') {
                echo '<br>' . e(cms_day_label((string) $ask['day'])) . ' · <span dir="ltr">' . e(cms_fa_digits((string) $ask['time_slot'])) . '</span>';
            }
            if ((string) $ask['note'] !== '') {
                echo '<br><span class="help">' . e((string) $ask['note']) . '</span>';
            }
            echo '</p><div class="row">';
            if ((string) $ask['kind'] === 'cancel') {
                echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="accept-ask"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $ask['id'] . '"><button class="btn" type="submit">قبول لغو</button></form>';
            }
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="decline-ask"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $ask['id'] . '"><button class="btn ghost" type="submit">رد</button></form>';
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="close-ask"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $ask['id'] . '"><button class="btn ghost" type="submit">بستن بدون تغییر نوبت</button></form></div>';
        }
        echo '</section>';
        $access = cms_care_access($phone);
        echo '<section class="card"><h2>رمز پرونده</h2>';
        if (!$access) {
            echo '<p>هنوز رمز جدا ساخته نشده. تا زدن «رمز تازه»، این در برای بیمار بسته است. کد نوبت پرونده را باز نمی‌کند.</p>';
        } elseif (!cms_care_code_live($access)) {
            echo '<p>رمز منقضی شده است. تا ساختن رمز تازه، این صفحه برای بیمار باز نمی‌شود.</p>';
        } else {
            $until = (string) $access['expires_at'] === '' ? 'تا تعویض معتبر است.' : 'تا ' . cms_day_label((string) $access['expires_at']) . ' معتبر است.';
            echo '<p>رمز فعال است و ' . e($until) . ' خود رمز اینجا نگه داشته نمی‌شود.</p>';
        }
        echo '<p class="help">با رمز تازه، ورودهای قبلی این شماره بسته می‌شود. کد پیگیری نوبت برای دیدن وضعیت نوبت می‌ماند و دیگر رمز همیشگی پرونده نیست.</p>';
        if (!cms_nurse()) {
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-care-code"><input type="hidden" name="phone" value="' . e($phone) . '">';
            echo '<button class="btn" type="submit">رمز تازه</button></form>';
        }
        echo '</section>';
        $mates = cms_care_companions($phone);
        echo '<section class="card slip"><h2>رمز همراه</h2>';
        echo '<p class="help">هر شماره تا چهار همراه دارد و هر نام رمز جدا می‌گیرد. همان نام را دوباره بنویسید تا فقط رمز همان نفر عوض شود. او وقت و آماده شدن را می‌بیند و برگه، عکس، دارو و پیام را نمی‌بیند. ورود بیمار بسته نمی‌شود. نام و رمز در پیامک نمی‌روند.</p>';
        if (!$mates) {
            echo '<p>هنوز رمز همراه نیست.</p>';
        } else {
            echo '<div class="mates">';
            foreach ($mates as $mate) {
                $mateName = (string) $mate['name'] !== '' ? (string) $mate['name'] : 'نام ثبت نشده';
                $live = cms_care_code_live($mate);
                $until = !$live ? 'رمز منقضی شده' : ((string) $mate['expires_at'] === '' ? 'تا تعویض معتبر است' : 'تا ' . cms_day_label((string) $mate['expires_at']));
                echo '<article><b>' . e($mateName) . '</b><small>' . e($until) . '</small>';
                echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="revoke-companion-code"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $mate['id'] . '"><button class="btn ghost" type="submit">بستن این نفر</button></form></article>';
            }
            echo '</div>';
        }
        echo '<div class="row">';
        if (cms_care_on('care_companion') && count($mates) < 4) {
            echo '<form method="post" class="line">' . desk_csrf() . '<input type="hidden" name="action" value="save-companion-code"><input type="hidden" name="phone" value="' . e($phone) . '">';
            echo '<input name="name" placeholder="نام همراه تازه"><button class="btn" type="submit">رمز این همراه</button></form>';
        } elseif (cms_care_on('care_companion')) {
            echo '<p class="help">چهار همراه ثبت شده. برای نفر تازه، یکی را ببندید.</p>';
        }
        if ($mates) {
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="revoke-companion-code"><input type="hidden" name="phone" value="' . e($phone) . '"><button class="btn ghost" type="submit">بستن همهٔ همراهان</button></form>';
        }
        echo '</div></section>';
        $facts = cms_care_facts($phone);
        echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-stage"><input type="hidden" name="phone" value="' . e($phone) . '">';
        echo '<h2>مرحله درمان</h2><p class="help">بیمار این مرحله را می‌بیند. برچسب نتیجه پزشکی نیست.</p><label>مرحله<select name="stage"><option value="">بدون مرحله</option>';
        $stageSeen = (string) ($facts['stage_key'] ?? '') === '';
        foreach (cms_care_stages(true) as $stage) {
            if ((string) ($facts['stage_key'] ?? '') === $stage['key']) {
                $stageSeen = true;
            }
            echo '<option value="' . e($stage['key']) . '"' . ((string) ($facts['stage_key'] ?? '') === $stage['key'] ? ' selected' : '') . '>' . e($stage['fa']) . '</option>';
        }
        if (!$stageSeen) {
            $stageLabel = cms_care_stage_label((string) $facts['stage_key']);
            if ($stageLabel === '') {
                $stageLabel = (string) $facts['stage_key'];
            }
            echo '<option value="' . e((string) $facts['stage_key']) . '" selected>' . e($stageLabel) . '</option>';
        }
        echo '</select></label><button class="btn" type="submit">ذخیره مرحله</button></form>';
        echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-facts"><input type="hidden" name="phone" value="' . e($phone) . '"><h2>برگه پزشکی</h2>';
        echo '<p class="help">فقط خانه‌های روشن در تنظیم پرونده درمان اینجا و پیش بیمار دیده می‌شوند. متن در پیامک نمی‌رود.</p>';
        $anyFact = false;
        foreach (cms_care_fields() as $key => $label) {
            if (!cms_care_field_on($key)) {
                continue;
            }
            $anyFact = true;
            echo '<label>' . e(cms_care_field_label($key)) . '<textarea name="' . e($key) . '">' . e((string) ($facts[$key] ?? '')) . '</textarea></label>';
        }
        echo $anyFact ? '<button class="btn" type="submit">ذخیره برگه پزشکی</button>' : '<p>همه خانه‌های برگه پزشکی در تنظیمات خاموش است.</p>';
        echo '</form>';
        $factLog = cms_care_fact_log($phone, 40);
        echo '<section class="card"><h2>تاریخچه برگه</h2>';
        echo '<p class="help">متن قبلی حساسیت، دارو، توضیح و مرحله پاک نمی‌شود. این فهرست نتیجه درمان نیست و در پیامک و گزارش میز نوشته نمی‌شود.</p>';
        if (!$factLog) {
            echo '<p>هنوز تغییری ثبت نشده است.</p>';
        }
        foreach ($factLog as $change) {
            $field = (string) $change['field'];
            $label = $field === 'stage' ? 'مرحله' : cms_care_field_label($field);
            $who = (string) $change['author'] === 'patient' ? 'بیمار' : 'مطب';
            if ((string) $change['staff'] !== '') {
                $who .= ' · ' . (string) $change['staff'];
            }
            echo '<p><strong>' . e($label !== '' ? $label : $field) . '</strong> · ' . e($who) . ' · ' . e(cms_when((string) $change['created_at'], true)) . '<br>';
            echo '<span class="help">قبل</span> ' . nl2br(e(cms_care_fact_text($field, (string) $change['before_text']))) . '<br>';
            echo '<span class="help">بعد</span> ' . nl2br(e(cms_care_fact_text($field, (string) $change['after_text']))) . '</p>';
        }
        echo '</section>';
        if ($next) {
            echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-prep"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $next['id'] . '">';
            echo '<h2>آماده شدن برای نوبت بعدی</h2><p class="help">بیمار این جمله را بالای نوبت بعدی می‌بیند. ذخیره آن پیام نمی‌فرستد.</p>';
            echo '<label>جمله<input name="prep" value="' . e((string) ($next['prep'] ?? '')) . '" placeholder="عکس قبلی را همراه بیاورید"></label>';
            echo '<button class="btn" type="submit">ذخیره آماده شدن</button></form>';
            echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-checks"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $next['id'] . '">';
            echo '<h2>پیش از ورود</h2><p class="help">هر سطر یک کار است. این فهرست پروتکل بیهوشی و نتیجهٔ عمل نیست و در پیامک نمی‌رود. سطر تکراری یکی می‌شود و حداکثر دوازده سطر می‌ماند.</p>';
            $checkLines = [];
            foreach (cms_checks((int) $next['id']) as $check) {
                $checkLines[] = (string) $check['label'];
                echo '<p class="tickrow' . ((int) $check['done'] === 1 ? ' done' : '') . '">';
                echo '<button class="btn ghost" type="submit" form="check-' . (int) $check['id'] . '">' . ((int) $check['done'] === 1 ? 'انجام شد' : 'نشده') . '</button> ' . e((string) $check['label']) . '</p>';
            }
            $checkValue = $checkLines ? implode("\n", $checkLines) : (string) cms_setting('care_check_tpl');
            echo '<label>سطرها<textarea name="lines" placeholder="ناشتا از نیمه‌شب">' . e($checkValue) . '</textarea></label>';
            echo '<button class="btn" type="submit">ذخیره فهرست</button></form>';
            foreach (cms_checks((int) $next['id']) as $check) {
                echo '<form id="check-' . (int) $check['id'] . '" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="toggle-check"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $next['id'] . '"><input type="hidden" name="check" value="' . (int) $check['id'] . '"></form>';
            }
            if (!cms_nurse()) {
                echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-anesthesia"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $next['id'] . '">';
                echo '<h2>یادداشت بیهوشی</h2><p class="help">فقط میز این متن را می‌بیند. بیمار، همراه، فایل برگه و پیامک آن را نمی‌گیرند. تشخیص و دستور بیهوشی نیست.</p>';
                echo '<label>متن میز<textarea name="anesthesia">' . e((string) ($next['anesthesia'] ?? '')) . '</textarea></label>';
                echo '<button class="btn" type="submit">ذخیره یادداشت</button></form>';
            }
        }
        if (cms_setting('care_teeth', '0') === '1' && !cms_nurse()) {
            echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-teeth"><input type="hidden" name="phone" value="' . e($phone) . '">';
            echo '<h2>نمودار دندان</h2><p class="help">شمارهٔ FDI بزرگسال است. چپِ تصویر، راستِ بیمار است. تشخیص، نمودار کودک و کد خدمت نیست و در پیامک نمی‌رود.</p>';
            echo cms_teeth_markup(cms_teeth_marks($phone), true);
            echo '<button class="btn" type="submit">ذخیره دندان‌ها</button></form>';
        }
        echo '<section class="card"><h2>داروها و کارها</h2><p class="help">بیمار این‌ها را می‌بیند. راه و مدت، خط ساختاری است نه نسخهٔ بیمارستان و تداخل را تشخیص نمی‌دهد. در پیامک نمی‌رود. عوض شدن دوز پاک نمی‌شود.</p>';
        $allergy = cms_care_allergy_text($phone);
        if ($allergy !== '') {
            echo '<p class="watch"><strong>حساسیت ثبت‌شده</strong> ' . e($allergy) . '<br><span class="help">این فقط همان متن برگه است و تداخل دارو را تشخیص نمی‌دهد.</span></p>';
        }
        foreach (cms_care_meds($phone) as $med) {
            $activeMed = (int) $med['active'] === 1;
            echo '<article class="rail"><strong>' . e((string) $med['name']) . '</strong> · ' . e((string) $med['author'] === 'clinic' ? 'مطب' : 'بیمار') . ($activeMed ? '' : ' · متوقف');
            echo '<p class="dose">' . e((string) $med['dose'] !== '' ? (string) $med['dose'] : 'خالی') . '</p>';
            if ((string) ($med['clock'] ?? '') !== '') {
                echo '<p>' . e((string) $med['clock']) . '</p>';
            }
            if ((string) ($med['caution'] ?? '') !== '') {
                echo '<p class="help">' . e((string) $med['caution']) . '</p>';
            }
            if ((string) ($med['route'] ?? '') !== '' || (string) ($med['span'] ?? '') !== '') {
                echo '<p class="chip">' . e(trim((string) ($med['route'] ?? '') . ' · ' . (string) ($med['span'] ?? ''), ' ·')) . '</p>';
            }
            if (!$activeMed && (string) ($med['stop_reason'] ?? '') !== '') {
                echo '<p class="help">دلیل توقف: ' . e((string) $med['stop_reason']) . '</p>';
            }
            if ($activeMed) {
                echo '<form method="post" class="line">' . desk_csrf() . '<input type="hidden" name="action" value="dose-med"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $med['id'] . '">';
                echo '<input name="dose" value="' . e((string) $med['dose']) . '" placeholder="دوز کوتاه"><button class="btn ghost" type="submit">ثبت دوز</button></form>';
                echo '<form method="post" class="line">' . desk_csrf() . '<input type="hidden" name="action" value="plan-med"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $med['id'] . '">';
                echo '<input name="clock" value="' . e((string) ($med['clock'] ?? '')) . '" placeholder="ساعت، مثل صبح و شب"><input name="caution" value="' . e((string) ($med['caution'] ?? '')) . '" placeholder="احتیاط کوتاه"><button class="btn ghost" type="submit">ثبت ساعت</button></form>';
                echo '<form method="post" class="line">' . desk_csrf() . '<input type="hidden" name="action" value="stop-med"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $med['id'] . '">';
                echo '<input name="reason" placeholder="دلیل توقف، اگر هست"><button class="btn ghost" type="submit">متوقف</button></form>';
                echo '<form method="post" class="line">' . desk_csrf() . '<input type="hidden" name="action" value="line-med"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $med['id'] . '">';
                echo '<input name="route" value="' . e((string) ($med['route'] ?? '')) . '" placeholder="راه، مثل خوراکی"><input name="span" value="' . e((string) ($med['span'] ?? '')) . '" placeholder="مدت، مثل پنج روز"><button class="btn ghost" type="submit">ثبت خط</button></form>';
            }
            echo '</article>';
        }
        $medLog = cms_care_med_log($phone);
        if ($medLog) {
            echo '<h3>ردپای دوز</h3>';
            foreach ($medLog as $change) {
                $who = (string) $change['author'] === 'patient' ? 'بیمار' : 'مطب';
                if ((string) $change['staff'] !== '') {
                    $who .= ' · ' . (string) $change['staff'];
                }
                $title = (string) $change['name'] !== '' ? (string) $change['name'] : 'دارو';
                echo '<p><strong>' . e($title) . '</strong> · ' . e($who) . ' · ' . e(cms_when((string) $change['created_at'], true));
                if ((string) $change['kind'] === 'stop') {
                    echo '<br><span class="help">متوقف شد. دوز همان موقع: ' . e((string) $change['before_text'] !== '' ? (string) $change['before_text'] : 'خالی') . '</span>';
                    if ((string) $change['after_text'] !== '' && (string) $change['after_text'] !== (string) $change['before_text']) {
                        echo '<br><span class="help">دلیل: ' . e((string) $change['after_text']) . '</span>';
                    }
                } elseif ((string) $change['kind'] === 'clock') {
                    echo '<br><span class="help">ساعت</span> ' . e((string) $change['before_text'] !== '' ? (string) $change['before_text'] : 'خالی');
                    echo ' · <span class="help">بعد</span> ' . e((string) $change['after_text'] !== '' ? (string) $change['after_text'] : 'خالی');
                } elseif ((string) $change['kind'] === 'caution') {
                    echo '<br><span class="help">احتیاط</span> ' . e((string) $change['before_text'] !== '' ? (string) $change['before_text'] : 'خالی');
                    echo ' · <span class="help">بعد</span> ' . e((string) $change['after_text'] !== '' ? (string) $change['after_text'] : 'خالی');
                } else {
                    echo '<br><span class="help">قبل</span> ' . e((string) $change['before_text'] !== '' ? (string) $change['before_text'] : 'خالی');
                    echo ' · <span class="help">بعد</span> ' . e((string) $change['after_text'] !== '' ? (string) $change['after_text'] : 'خالی');
                }
                echo '</p>';
            }
        }
        if (!cms_nurse()) {
            echo '<form method="post" class="grid-2">' . desk_csrf() . '<input type="hidden" name="action" value="save-med"><input type="hidden" name="phone" value="' . e($phone) . '">';
            echo '<label>داروی مطب<input name="name"></label><label>دوز کوتاه<input name="dose"></label><label>ساعت مصرف<input name="clock" placeholder="صبح و شب"></label><label>احتیاط<input name="caution"></label><label>راه<input name="route" placeholder="خوراکی"></label><label>مدت<input name="span" placeholder="پنج روز"></label><button class="btn" type="submit">افزودن دارو</button></form>';
        }
        foreach (cms_care_needs($phone) as $need) {
            $who = trim((string) ($need['staff'] ?? ''));
            echo '<article class="call"><div><p>' . ((int) $need['done'] === 1 ? '✓ ' : '') . e((string) $need['body']) . ' · ' . e((string) $need['author'] === 'clinic' ? 'مطب' : 'بیمار');
            echo $who !== '' ? ' · <span class="chip">' . e($who) . '</span>' : ' · <span class="help">مسئول ندارد</span>';
            echo '</p>';
            echo '<form method="post" class="line">' . desk_csrf() . '<input type="hidden" name="action" value="assign-need"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $need['id'] . '">';
            echo '<input name="staff" value="' . e($who) . '" placeholder="نام مسئول روی میز"><button class="btn ghost" type="submit">مسئول</button></form>';
            if (!cms_nurse()) {
                echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="delete-need"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $need['id'] . '"><button class="btn ghost" type="submit">حذف</button></form>';
            }
            echo '</div></article>';
        }
        if (!cms_nurse()) {
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-need"><input type="hidden" name="phone" value="' . e($phone) . '">';
            echo '<label>کاری که بیمار باید بداند<input name="body"></label><button class="btn" type="submit">افزودن کار</button></form>';
        }
        echo '<h2>یادآوری شخصی بیمار</h2>';
        $reminders = cms_care_reminders($phone);
        if (!$reminders) {
            echo '<p class="help">بیمار یادآوری شخصی نگذاشته است.</p>';
        }
        foreach ($reminders as $item) {
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="delete-reminder"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $item['id'] . '">';
            echo e(cms_day_label((string) $item['day'])) . ' · ' . e((string) $item['note']) . ' <button class="btn ghost" type="submit">برداشتن</button></form>';
        }
        echo '<h2>نظر روی نوبت</h2>';
        foreach (cms_care_comments($phone, true) as $comment) {
            echo '<p><strong>' . e((string) $comment['author'] === 'clinic' ? 'مطب' : 'بیمار') . '</strong> · ' . e((string) $comment['body']);
            if ((int) $comment['hidden'] === 1) {
                echo ' · پنهان';
            }
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="hide-comment"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $comment['id'] . '"><button class="btn ghost" type="submit">' . ((int) $comment['hidden'] === 1 ? 'نشان بده' : 'پنهان کن') . '</button></form></p>';
        }
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-comment"><input type="hidden" name="phone" value="' . e($phone) . '"><label>نوبت<select name="visit">';
        foreach ($rows as $visit) {
            echo '<option value="' . (int) $visit['id'] . '">' . e(cms_day_label((string) $visit['day'])) . ' ' . e(cms_fa_digits((string) $visit['time_slot'])) . '</option>';
        }
        echo '</select></label><label>پاسخ مطب<textarea name="body"></textarea></label><button class="btn" type="submit">ثبت نظر</button></form></section>';
        echo '<section class="card"><h2>روند درمان</h2><p class="help">بیمار با موبایل و رمز پرونده از مسیر /care/ وارد می‌شود. کد نوبت این در را باز نمی‌کند. عکس آنجا روی سایت عمومی نیست. پنهان کردن، متن را از بیمار برمی‌دارد و در میز نگه می‌دارد.</p>';
        $thread = cms_care_thread($phone, true);
        if (!$thread) {
            echo '<p>هنوز پیامی در روند درمان نیست.</p>';
        }
        foreach ($thread as $post) {
            $hidden = (int) $post['hidden'] === 1;
            $fromPatient = (string) $post['author'] === 'patient';
            echo '<article class="post"><strong>' . e($fromPatient ? 'بیمار' : 'مطب') . '</strong> · ' . e(cms_when((string) $post['created_at'], true));
            $via = cms_chat_names()[(string) ($post['via'] ?? '')] ?? '';
            if ($via !== '') {
                echo ' · <span class="chip">' . e('از ' . $via) . '</span>';
            }
            if ((string) ($post['seen_at'] ?? '') !== '') {
                echo ' · <span class="pill">' . e($fromPatient ? 'میز دید' : 'بیمار دید') . '</span>';
            }
            if ($hidden) {
                echo ' · <span class="pill warn">از بیمار پنهان</span>';
            }
            if ((string) $post['body'] !== '') {
                echo '<p>' . nl2br(e((string) $post['body'])) . '</p>';
            }
            foreach ($post['files'] as $file) {
                echo '<a href="/care/file.php?id=' . (int) $file['id'] . '"><img class="photo" alt="عکس پرونده" src="/care/file.php?id=' . (int) $file['id'] . '"></a>';
            }
            echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="hide-care"><input type="hidden" name="phone" value="' . e($phone) . '"><input type="hidden" name="id" value="' . (int) $post['id'] . '">';
            echo '<button class="btn ghost" type="submit">' . ($hidden ? 'دوباره به بیمار نشان بده' : 'بیمار این را نبیند') . '</button></form></article>';
        }
        echo '<form method="post" enctype="multipart/form-data">' . desk_csrf() . '<input type="hidden" name="action" value="save-care"><input type="hidden" name="phone" value="' . e($phone) . '">';
        echo '<label>متن برای بیمار<textarea name="body" placeholder="این متن در پرونده درمان بیمار دیده می‌شود."></textarea></label>';
        echo '<label>عکس برای بیمار، تا ' . e(cms_fa_digits((string) cms_care_photo_max())) . '<input type="file" name="photo[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple></label>';
        echo '<p class="help">موقعیت و داده دوربین پاک می‌شود. چرخش گوشی وقتی GD روشن باشد درست می‌شود. HEIC فقط با Imagick به JPG تبدیل می‌شود.</p>';
        echo '<button class="btn" type="submit">گذاشتن در روند درمان</button></form>';
        echo '<div class="mates">';
        foreach (['sheet' => 'برگه', 'message' => 'پیام'] as $kind => $label) {
            $kept = cms_care_consent_last($phone, $kind);
            echo '<article><b>رضایت ' . e($label) . '</b>';
            if (!$kept) {
                echo '<small>هنوز تیک نخورده</small>';
            } else {
                $same = hash_equals((string) $kept['sentence_sha'], hash('sha256', cms_care_consent('fa')));
                echo '<small>' . e(cms_when((string) $kept['created_at'], true)) . ' · ' . e($same ? 'همان جملهٔ فعلی' : 'جمله از آن روز عوض شده') . '</small>';
            }
            echo '</article>';
        }
        echo '</div></section>';
        if (!cms_nurse()) {
        echo '<form class="card slip" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="move-patient"><input type="hidden" name="phone" value="' . e($phone) . '">';
        echo '<h2>تعویض شماره یا ادغام</h2>';
        echo '<p class="help">شماره تازه نوبت، برگه، عکس، دارو، پیام و یادآوری را با خودش می‌برد. اگر آن موبایل از قبل نوبت داشته باشد دو پرونده یکی می‌شوند. متن شماره مقصد می‌ماند، جاهای خالی از این شماره پر می‌شود و متن متفاوت هر دو در برگه و تاریخچه می‌ماند. پیامک نمی‌رود. ورود شماره قبلی بسته می‌شود.</p>';
        echo '<div class="grid-2"><label>موبایل تازه<input dir="ltr" name="next" inputmode="numeric" placeholder="0912..."></label>';
        echo '<label>تکرار همان موبایل<input dir="ltr" name="again" inputmode="numeric"></label></div>';
        echo '<label class="checkline"><input type="checkbox" name="confirm" value="1"> می‌دانم این کار نوبت و برگه را به شماره تازه می‌برد</label>';
        echo '<button class="btn" type="submit">انتقال یا ادغام</button></form>';
        echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-patient"><input type="hidden" name="phone" value="' . e($phone) . '">';
        $birthShown = (string) ($file['birth'] ?? '');
        $birthShown = $birthShown !== '' ? cms_shamsi_iso($birthShown) : '';
        echo '<div class="grid-2"><label>نام در پرونده<input name="name" value="' . e($name) . '"></label><label>موبایل<input dir="ltr" value="' . e($phone) . '" readonly></label>';
        echo '<label>تاریخ تولد شمسی<input dir="ltr" name="birth" value="' . e($birthShown) . '" placeholder="1370-05-12"></label>';
        echo '<label>کد ملی<input dir="ltr" name="national_id" inputmode="numeric" value="' . e((string) ($file['national_id'] ?? '')) . '"></label></div>';
        echo '<p class="help">کد ملی و تاریخ تولد در پیامک نمی‌روند و همراه آن‌ها را نمی‌بیند. کد ملی باید رقم کنترل درست داشته باشد و روی دو پرونده تکرار نمی‌شود.</p>';
        echo '<label>یادداشت ماندگار<textarea class="note" name="note">' . e((string) ($file['note'] ?? '')) . '</textarea></label>';
        echo '<button class="btn" type="submit">ذخیره پرونده</button></form>';
        echo '<form class="card slip" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="sms-stop"><input type="hidden" name="phone" value="' . e($phone) . '">';
        echo '<h2>پیامک این بیمار</h2>';
        echo '<p class="help">این کلید فقط پیام نوبت و یادآوری شخصی همین شماره را قطع می‌کند. پیام پزشک و پیام‌رسان پزشک می‌ماند. حذف پرونده این قطع را برنمی‌دارد، چون موبایل روی نوبت دفتر روز می‌ماند.</p>';
        echo '<label class="checkline"><input type="checkbox" name="sms_off" value="1"' . (cms_sms_stopped($phone) ? ' checked' : '') . '> پیامک بیمار برای این شماره نرود</label>';
        echo '<button class="btn" type="submit">ذخیره پیامک</button></form>';
        echo '<form class="card slip" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="erase-patient"><input type="hidden" name="phone" value="' . e($phone) . '">';
        echo '<h2>حذف داده پرونده</h2>';
        echo '<p class="help">برگه، عکس، دارو، پیام، یادآوری، رمز پرونده، رمز همراه، تاریخ تولد و کد ملی پاک می‌شود. نوبت در دفتر روز می‌ماند، نامش «حذف‌شده» می‌شود و موبایل روی همان نوبت می‌ماند تا ساعت اشغال بماند. اگر پیامک این شماره قطع شده باشد، همان قطع می‌ماند. ورود با رمز بسته می‌شود تا میز رمز تازه بسازد. کد نوبت پرونده را باز نمی‌کند. پیامک نمی‌رود.</p>';
        echo '<label>تکرار همین موبایل<input dir="ltr" name="again" inputmode="numeric"></label>';
        echo '<label class="checkline"><input type="checkbox" name="confirm" value="1"> می‌دانم برگه و عکس این شماره پاک می‌شود</label>';
        echo '<button class="btn bad" type="submit">حذف داده پرونده</button></form>';
        }
        echo '<div class="table-wrap"><table><tr><th>زمان</th><th>وضعیت</th><th>نوع</th><th>موضوع</th><th>کد</th></tr>';
        foreach ($rows as $visit) {
            echo '<tr><td>' . e(cms_day_label((string) $visit['day'])) . ' <span dir="ltr">' . e(cms_fa_digits((string) $visit['time_slot'])) . '</span></td>';
            echo '<td>' . e(cms_visit_statuses()[$visit['status']] ?? (string) $visit['status']) . '</td>';
            echo '<td>' . e(cms_kind_label((string) $visit['kind'])) . '</td>';
            echo '<td>' . e(cms_service_label((string) $visit['service'])) . '</td>';
            echo '<td dir="ltr">' . e((string) $visit['code']) . '</td></tr>';
        }
        echo '</table></div>';
        desk_close();
        return;
    }
    $q = cms_plain((string) ($_GET['q'] ?? ''), 80);
    $unseen = cms_care_unseen_phones();
    desk_open('پرونده‌ها', 'patients', 'هر شماره یک پرونده است. پیام تازه بیمار با باز کردن همان پرونده دیده می‌شود.');
    if ($unseen) {
        echo '<p class="help">' . e(cms_fa_digits((string) count($unseen))) . ' پرونده پیام تازه‌ای دارد که هنوز باز نشده است.</p>';
    }
    if (cms_owner()) {
        echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-care-settings">';
        echo '<h2>پرونده درمان بیمار</h2>';
        echo '<label><span><input type="checkbox" name="care_public"' . (cms_care_open() ? ' checked' : '') . '> بیمار بتواند پیام و عکس تازه بفرستد</span></label>';
        echo '<label><span><input type="checkbox" name="care_alert"' . (cms_setting('care_alert') === '1' ? ' checked' : '') . '> با پیام بیمار، به پزشک خبر بده</span></label>';
        echo '<div class="grid-2"><label>عنوان صفحه<input name="care_title" value="' . e(cms_setting('care_title')) . '" placeholder="' . e(cms_care_title()) . '"></label>';
        echo '<label>حداکثر حجم عکس، کیلوبایت<input dir="ltr" name="care_upload_kb" value="' . e((string) cms_care_upload_kb()) . '"></label></div>';
        echo '<label>توضیح بالای صفحه<textarea name="care_notice" placeholder="' . e(cms_care_notice()) . '">' . e(cms_setting('care_notice')) . '</textarea></label>';
        echo '<label>جمله تأیید بیمار<textarea name="care_consent" placeholder="' . e(cms_care_consent()) . '">' . e(cms_setting('care_consent')) . '</textarea></label>';
        echo '<label>متن خبر به پزشک<textarea name="tpl_care_alert" placeholder="پیام تازه در پرونده درمان است. چهار رقم آخر: {tail}. متن بیمار در این پیام نیست.">' . e(cms_setting('tpl_care_alert')) . '</textarea></label>';
        echo '<div class="grid-2"><label>شماره فوریت<input dir="ltr" name="care_emergency" value="' . e(cms_setting('care_emergency')) . '" placeholder="115"></label>';
        echo '<label>تعداد عکس در هر پیام<input dir="ltr" name="care_photos" value="' . e((string) cms_care_photo_max()) . '"></label></div>';
        echo '<p class="help">شماره فوریت پیش‌فرض ۱۱۵ است. شماره مطب اینجا پذیرفته نمی‌شود. خالی بماند تا ۱۱۵ برگردد. عکس پرونده موقعیت دوربین را نگه نمی‌دارد.</p>';
        echo '<label><span><input type="checkbox" name="care_patient_med"' . (cms_care_on('care_patient_med') ? ' checked' : '') . '> بیمار بتواند داروی خودش را بنویسد</span></label>';
        echo '<label><span><input type="checkbox" name="care_teeth"' . (cms_setting('care_teeth', '0') === '1' ? ' checked' : '') . '> نمودار دندان FDI روی پرونده باشد</span></label>';
        echo '<label>سطرهای آمادهٔ پیش از ورود<textarea name="care_check_tpl" placeholder="ناشتا از نیمه‌شب">' . e(cms_setting('care_check_tpl')) . '</textarea></label>';
        echo '<p class="help">نمودار پیش‌فرض خاموش است تا مطب غیر دندان‌پزشکی آن را نبیند. سطرهای آماده فقط وقتی فهرست نوبت خالی است در خانه می‌آیند و خودبه‌خود روی نوبت نمی‌نشینند.</p>';
        echo '<label><span><input type="checkbox" name="care_patient_need"' . (cms_care_on('care_patient_need') ? ' checked' : '') . '> بیمار بتواند کار شخصی اضافه کند</span></label>';
        echo '<label><span><input type="checkbox" name="care_comments"' . (cms_care_on('care_comments') ? ' checked' : '') . '> نظر روی هر نوبت باز باشد</span></label>';
        echo '<label><span><input type="checkbox" name="care_patient_ask"' . (cms_care_on('care_patient_ask') ? ' checked' : '') . '> بیمار بتواند لغو یا زمان دیگر را درخواست کند</span></label>';
        echo '<label><span><input type="checkbox" name="care_self_remind"' . (cms_care_on('care_self_remind') ? ' checked' : '') . '> بیمار بتواند یادآوری شخصی بگذارد</span></label>';
        echo '<label><span><input type="checkbox" name="care_self_sms"' . (cms_care_on('care_self_sms', false) ? ' checked' : '') . '> همان یادآوری، در روزش به موبایل خودش پیامک شود</span></label>';
        echo '<label><span><input type="checkbox" name="care_patient_rotate"' . (cms_care_on('care_patient_rotate') ? ' checked' : '') . '> بیمار بتواند رمز پرونده را خودش عوض کند</span></label>';
        echo '<label><span><input type="checkbox" name="care_companion"' . (cms_care_on('care_companion') ? ' checked' : '') . '> ورود همراه با رمز جدا باز باشد</span></label>';
        echo '<label><span><input type="checkbox" name="care_patient_companion"' . (cms_care_on('care_patient_companion') ? ' checked' : '') . '> بیمار بتواند رمز همراه را خودش بسازد</span></label>';
        echo '<label>اعتبار رمز پرونده، روز<input dir="ltr" name="care_code_days" value="' . e((string) cms_care_span('care_code_days', 180, 365)) . '"></label>';
        echo '<p class="help">صفر یعنی رمز تا تعویض می‌ماند. رمز را فقط میز می‌سازد و در پیامک نوبت نمی‌رود. کد نوبت فقط وضعیت را در پیگیری نشان می‌دهد و پرونده را باز نمی‌کند.</p>';
        echo '<label>سقف یادآوری شخصی<input dir="ltr" name="care_remind_max" value="' . e((string) max(1, min(12, (int) cms_setting('care_remind_max', '6')))) . '"></label>';
        echo '<div class="grid-2"><label>عنوان نوبت بعدی<input name="care_h_next" value="' . e(cms_setting('care_h_next')) . '" placeholder="نوبت بعدی"></label>';
        echo '<label>عنوان روند<input name="care_h_stage" value="' . e(cms_setting('care_h_stage')) . '" placeholder="روند درمان"></label>';
        echo '<label>عنوان کارها<input name="care_h_need" value="' . e(cms_setting('care_h_need')) . '" placeholder="چه چیز لازم است"></label>';
        echo '<label>عنوان داروها<input name="care_h_med" value="' . e(cms_setting('care_h_med')) . '" placeholder="داروها"></label>';
        echo '<label>عنوان یادآوری<input name="care_h_remind" value="' . e(cms_setting('care_h_remind')) . '" placeholder="یادآوری شخصی"></label>';
        echo '<label>عنوان گفتگو<input name="care_h_talk" value="' . e(cms_setting('care_h_talk')) . '" placeholder="پیام با مطب"></label></div>';
        echo '<p class="help">پیامک یادآوری شخصی فقط جمله خود بیمار را به موبایل خودش می‌فرستد، در همان ساعت مجاز پیام‌رسانی، و فقط اگر پیامک روشن باشد. عنوان خالی، جمله پیش‌فرض همان زبان را برمی‌گرداند و فارسی را به زبان دیگر کپی نمی‌کند.</p>';
        echo '<details><summary>عنوان‌ها به انگلیسی، عربی و چینی</summary>';
        $translatedLabels = [
            'care_title' => 'عنوان صفحه',
            'care_h_next' => 'نوبت بعدی',
            'care_h_stage' => 'روند',
            'care_h_need' => 'کارها',
            'care_h_med' => 'داروها',
            'care_h_remind' => 'یادآوری',
            'care_h_talk' => 'گفتگو',
            'care_label_problem' => 'مشکل',
            'care_label_medicines' => 'داروهای برگه',
            'care_label_allergy' => 'حساسیت',
            'care_label_extra' => 'توضیح دیگر',
        ];
        foreach ($translatedLabels as $key => $label) {
            echo '<p class="dose">' . e($label) . '</p><div class="grid-2">';
            foreach (['en' => 'English', 'ar' => 'العربية', 'zh' => '中文'] as $locale => $localeName) {
                $dir = $locale === 'ar' ? 'rtl' : 'ltr';
                echo '<label>' . e($localeName) . '<input dir="' . $dir . '" name="' . e($key . '_' . $locale) . '" value="' . e(cms_setting($key . '_' . $locale)) . '" placeholder="' . e(cms_care_builtin($key, $locale)) . '"></label>';
            }
            echo '</div>';
        }
        foreach (['care_notice' => 'توضیح بالای صفحه', 'care_consent' => 'جمله تأیید'] as $key => $label) {
            echo '<p class="dose">' . e($label) . '</p>';
            foreach (['en' => 'English', 'ar' => 'العربية', 'zh' => '中文'] as $locale => $localeName) {
                $dir = $locale === 'ar' ? 'rtl' : 'ltr';
                echo '<label>' . e($localeName) . '<textarea dir="' . $dir . '" name="' . e($key . '_' . $locale) . '" placeholder="' . e(cms_care_builtin($key, $locale)) . '">' . e(cms_setting($key . '_' . $locale)) . '</textarea></label>';
            }
        }
        echo '</details>';
        foreach (cms_care_fields() as $key => $label) {
            echo '<div class="grid-2"><label><span><input type="checkbox" name="care_show_' . e($key) . '"' . (cms_care_field_on($key) ? ' checked' : '') . '> ' . e($label) . ' روشن باشد</span></label>';
            echo '<label>نام این خانه<input name="care_label_' . e($key) . '" value="' . e(cms_setting('care_label_' . $key)) . '" placeholder="' . e($label) . '"></label></div>';
        }
        echo '<p class="help">فیلد خالی جمله پیش‌فرض را نگه می‌دارد. خبر پزشک متن بیمار و عکس را ندارد و فقط وقتی پیامک یا پیام‌رسان پزشک روشن باشد می‌رود. یادآوری نوبت جداست و همچنان فقط برای نوبت هماهنگ‌شده است. تأیید بیمار را نمی‌شود بردارد.</p>';
        echo '<button class="btn" type="submit">ذخیره پرونده درمان</button></form>';
    }
    echo '<details class="card"><summary>مرحله‌های درمان</summary><p class="help">این‌ها برچسب مطب‌اند، نه نتیجه پزشکی. برای دندان‌پزشکی یا هر کار دیگر نامشان را عوض کنید. مرحله‌ای که روی پرونده بیماری باشد حذف نمی‌شود.</p>';
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-stages">';
    foreach (cms_care_stages() as $stage) {
        echo '<div class="grid-2"><label>نام فارسی<input name="stage[' . e($stage['key']) . '][fa]" value="' . e($stage['fa']) . '"></label>';
        echo '<label>English<input dir="ltr" name="stage[' . e($stage['key']) . '][en]" value="' . e($stage['en']) . '"></label>';
        echo '<label>العربية<input dir="rtl" name="stage[' . e($stage['key']) . '][ar]" value="' . e($stage['ar']) . '"></label>';
        echo '<label>中文<input dir="ltr" name="stage[' . e($stage['key']) . '][zh]" value="' . e($stage['zh']) . '"></label>';
        echo '<label>ترتیب<input dir="ltr" name="stage[' . e($stage['key']) . '][sort]" value="' . e((string) $stage['sort']) . '"></label>';
        echo '<label><span><input type="checkbox" name="stage[' . e($stage['key']) . '][active]"' . ($stage['active'] ? ' checked' : '') . '> فعال</span></label></div>';
        echo '<label><span><input type="checkbox" name="delete_stage[]" value="' . e($stage['key']) . '"> حذف اگر روی پرونده‌ای نیست</span></label>';
    }
    echo '<h2>مرحله تازه</h2><div class="grid-2"><label>شناسه انگلیسی<input dir="ltr" name="new_stage_key" placeholder="healing"></label><label>نام فارسی<input name="new_stage_fa"></label>';
    echo '<label>ترتیب<input dir="ltr" name="new_stage_sort" value="10"></label></div>';
    echo '<button class="btn" type="submit">ذخیره مرحله‌ها</button></form></details>';
    echo '<form class="row" method="get"><input type="hidden" name="page" value="patients"><input name="q" value="' . e($q) . '" placeholder="نام، موبایل یا یادداشت"><button class="btn ghost" type="submit">جستجو</button></form>';
    $sql = "SELECT v.phone AS phone, COALESCE(NULLIF(p.name, ''), MAX(v.name)) AS name, COUNT(*) AS visits, MAX(v.day) AS last_day, COALESCE(p.note, '') AS note FROM visits v LEFT JOIN patients p ON p.phone = v.phone";
    $args = [];
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $sql .= " WHERE (v.name LIKE ? ESCAPE '\\' OR v.phone LIKE ? ESCAPE '\\' OR p.name LIKE ? ESCAPE '\\' OR p.note LIKE ? ESCAPE '\\')";
        array_push($args, $like, $like, $like, $like);
    }
    $sql .= ' GROUP BY v.phone ORDER BY last_day DESC LIMIT 80';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute($args);
    echo '<div class="table-wrap"><table><tr><th>نام</th><th>موبایل</th><th>نوبت‌ها</th><th>آخرین روز</th><th>پیام</th><th>یادداشت</th></tr>';
    $found = false;
    foreach ($stmt as $row) {
        $found = true;
        echo '<tr><td><a href="/manage/index.php?page=patients&phone=' . e(rawurlencode((string) $row['phone'])) . '">' . e((string) $row['name']) . '</a></td>';
        echo '<td dir="ltr">' . e((string) $row['phone']) . '</td><td>' . e(cms_fa_digits((string) $row['visits'])) . '</td>';
        echo '<td>' . e(cms_day_label((string) $row['last_day'])) . '</td><td>' . (isset($unseen[(string) $row['phone']]) ? 'تازه' : '') . '</td><td>' . e(desk_clip((string) $row['note'], 60)) . '</td></tr>';
    }
    if (!$found) {
        echo '<tr><td colspan="6">پرونده‌ای با این جستجو نیست. با اولین نوبت، پرونده ساخته می‌شود.</td></tr>';
    }
    echo '</table></div>';
    desk_close();
}

function desk_page_appointments(bool $owner): void
{
    $zone = new DateTimeZone('Asia/Tehran');
    $today = (new DateTimeImmutable('today', $zone))->format('Y-m-d');
    $week = (new DateTimeImmutable('today', $zone))->modify('+7 days')->format('Y-m-d');
    $status = (string) ($_GET['status'] ?? '');
    if (!isset(cms_visit_statuses()[$status])) {
        $status = '';
    }
    $dayRaw = trim((string) ($_GET['day'] ?? ''));
    $day = $dayRaw === '' ? '' : cms_parse_day($dayRaw);
    $q = cms_plain((string) ($_GET['q'] ?? ''), 80);
    $waiting = (int) cms_db()->query("SELECT COUNT(*) FROM visits WHERE status = 'new'")->fetchColumn();
    $todayCount = (int) cms_db()->query("SELECT COUNT(*) FROM visits WHERE day = " . cms_db()->quote($today) . " AND status IN ('new','confirmed','arrived')")->fetchColumn();
    $weekCount = (int) cms_db()->query("SELECT COUNT(*) FROM visits WHERE status = 'confirmed' AND day >= " . cms_db()->quote($today) . " AND day <= " . cms_db()->quote($week))->fetchColumn();
    $cancelled = (int) cms_db()->query("SELECT COUNT(*) FROM visits WHERE status = 'cancelled' AND day >= " . cms_db()->quote($today))->fetchColumn();
    desk_open('نوبت‌ها', 'appointments', 'درخواست سایت تا وقتی اینجا هماهنگ نشود قطعی نیست. با هر هماهنگی، لغو یا جابه‌جایی، پیام مسیرهای روشن می‌رود.');
    echo '<p class="row"><a class="btn ghost" href="/manage/index.php?page=report">گزارش ماه</a><a class="btn ghost" href="/manage/index.php?page=patients">پرونده‌ها</a><a class="btn ghost" href="/manage/index.php?page=schedule">برنامه مطب</a>';
    if ($owner) {
        echo '<a class="btn ghost" href="/manage/index.php?page=messaging">پیام‌رسانی</a>';
    }
    echo '</p>';
    if (cms_setting('sms_on') !== '1') {
        echo '<div class="flash bad">پیامک هنوز خاموش است. بیمار و پزشک پیامی نمی‌گیرند تا مالک میز در پیام‌رسانی ملی‌پیامک را روشن کند.</div>';
    }
    if (!cms_book_public()) {
        echo '<div class="flash bad">فرم سایت بسته است. درخواست تازه فقط از نوبت تلفنی همین صفحه ثبت می‌شود.</div>';
    }
    echo '<div class="stats">';
    echo '<a class="stat" href="/manage/index.php?page=appointments&day=' . e($today) . '"><span class="muted">امروز</span><b>' . e(cms_fa_digits((string) $todayCount)) . '</b></a>';
    echo '<a class="stat" href="/manage/index.php?page=appointments&status=new"><span class="muted">منتظر هماهنگی</span><b>' . e(cms_fa_digits((string) $waiting)) . '</b></a>';
    echo '<a class="stat" href="/manage/index.php?page=appointments&status=confirmed"><span class="muted">هماهنگ، هفت روز</span><b>' . e(cms_fa_digits((string) $weekCount)) . '</b></a>';
    echo '<a class="stat" href="/manage/index.php?page=appointments&status=cancelled"><span class="muted">لغو از امروز به بعد</span><b>' . e(cms_fa_digits((string) $cancelled)) . '</b></a>';
    echo '</div>';
    desk_render_queue();
    echo '<form class="row" method="get"><input type="hidden" name="page" value="appointments">';
    echo '<select name="status"><option value="">همه وضعیت‌ها</option>';
    foreach (cms_visit_statuses() as $value => $label) {
        echo '<option value="' . e($value) . '"' . ($status === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $dayShown = $day !== '' ? desk_jalali_iso($day) : $dayRaw;
    echo '</select><input name="day" value="' . e($dayShown) . '" placeholder="1405-08-27" dir="ltr">';
    echo '<input name="q" value="' . e($q) . '" placeholder="نام، موبایل یا کد"><button class="btn ghost" type="submit">صافی</button>';
    echo '<a class="btn ghost" href="/manage/index.php?page=appointments">پاک کردن صافی</a></form>';
    if ($dayRaw !== '' && $day === '') {
        echo '<p class="help">این تاریخ خوانده نشد. شمسی بنویسید، مثل 1405-08-27.</p>';
    }
    $focus = $day !== '' ? $day : $today;
    $point = cms_g2j((int) substr($focus, 0, 4), (int) substr($focus, 5, 2), (int) substr($focus, 8, 2));
    $jy = (int) ($_GET['jy'] ?? 0);
    $jm = (int) ($_GET['jm'] ?? 0);
    if ($jy < 1200 || $jy > 1600 || $jm < 1 || $jm > 12) {
        $jy = (int) $point['jy'];
        $jm = (int) $point['jm'];
    }
    desk_render_month($jy, $jm, $today, $day, $status, $q);
    desk_render_week($focus);
    desk_render_diary($day !== '' ? $day : $today);

    $sql = 'SELECT * FROM visits WHERE 1 = 1';
    $args = [];
    if ($status !== '') {
        $sql .= ' AND status = ?';
        $args[] = $status;
    }
    if ($day !== '') {
        $sql .= ' AND day = ?';
        $args[] = $day;
    }
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $sql .= " AND (name LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\' OR code LIKE ? ESCAPE '\\')";
        array_push($args, $like, $like, $like);
    }
    $sql .= ' ORDER BY day DESC, time_slot DESC LIMIT 40';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute($args);
    $visits = $stmt->fetchAll();
    if (!$visits) {
        echo '<div class="card"><p>با این صافی نوبتی نیست.</p></div>';
    }
    foreach ($visits as $visit) {
        $tone = desk_visit_tone((string) $visit['status']);
        echo '<article class="card"><div class="row"><strong>' . e((string) $visit['name']) . '</strong>';
        echo '<span class="pill ' . e($tone) . '">' . e(cms_visit_statuses()[$visit['status']] ?? $visit['status']) . '</span>';
        echo '<span class="muted" dir="ltr">' . e((string) $visit['code']) . '</span>';
        echo '<span class="muted">' . e((string) $visit['source'] === 'desk' ? 'ثبت میز' : 'ثبت سایت') . '</span>';
        echo '<a class="btn ghost" href="/manage/index.php?page=patients&phone=' . e(rawurlencode((string) $visit['phone'])) . '">پرونده</a></div>';
        echo '<p>' . e(cms_day_label((string) $visit['day'])) . ' <span dir="ltr">' . e(cms_fa_digits((string) $visit['time_slot'])) . '</span>';
        $serviceCode = cms_topic_code((string) $visit['service']);
        echo ' · ' . e(cms_kind_label((string) $visit['kind'])) . ' · ' . e(cms_service_label((string) $visit['service']));
        if ($serviceCode !== '') {
            echo ' · <span class="chip" dir="ltr">' . e($serviceCode) . '</span>';
        }
        echo ' · <span dir="ltr">' . e((string) $visit['phone']) . '</span></p>';
        if (!cms_nurse()) {
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-visit"><input type="hidden" name="id" value="' . (int) $visit['id'] . '"><div class="grid-2">';
        echo '<label>نام<input name="name" value="' . e((string) $visit['name']) . '"></label>';
        echo '<label>موبایل<input name="phone" dir="ltr" value="' . e((string) $visit['phone']) . '"></label>';
        echo '<label>ایمیل<input name="email" dir="ltr" value="' . e((string) $visit['email']) . '"></label>';
        echo '<label>وضعیت<select name="status">';
        foreach (cms_visit_statuses() as $value => $label) {
            echo '<option value="' . e($value) . '"' . ($visit['status'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        echo '</select></label><label>نوع نوبت<select name="kind">';
        foreach (cms_visit_kinds() as $kind) {
            echo '<option value="' . e($kind['key']) . '"' . ($visit['kind'] === $kind['key'] ? ' selected' : '') . '>' . e($kind['fa']) . '</option>';
        }
        echo '</select></label><label>موضوع<select name="service">';
        $serviceKnown = false;
        foreach (cms_visit_topics() as $topic) {
            if ($visit['service'] === $topic['key']) {
                $serviceKnown = true;
            }
            if (!$topic['active'] && $topic['key'] !== (string) $visit['service']) {
                continue;
            }
            echo '<option value="' . e($topic['key']) . '"' . ($visit['service'] === $topic['key'] ? ' selected' : '') . '>' . e($topic['fa']) . '</option>';
        }
        if (!$serviceKnown && (string) $visit['service'] !== '') {
            echo '<option value="' . e((string) $visit['service']) . '" selected>' . e(cms_service_label((string) $visit['service'])) . '</option>';
        }
        echo '</select></label><label>زبان پیام<select name="locale">';
        foreach (['fa' => 'فارسی', 'en' => 'English', 'ar' => 'العربية', 'zh' => '中文'] as $value => $label) {
            echo '<option value="' . e($value) . '"' . (($visit['locale'] ?? 'fa') === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        echo '</select></label><label>روز<input name="day" dir="ltr" value="' . e(desk_jalali_iso((string) $visit['day'])) . '" placeholder="1405-08-27"></label>';
        echo '<label>ساعت<select name="time">';
        $times = cms_book_slots();
        if (!in_array((string) $visit['time_slot'], $times, true) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $visit['time_slot'])) {
            $times[] = (string) $visit['time_slot'];
        }
        foreach ($times as $slot) {
            echo '<option value="' . e($slot) . '"' . ($visit['time_slot'] === $slot ? ' selected' : '') . '>' . e(cms_fa_digits($slot)) . '</option>';
        }
        echo '</select></label>';
        $rooms = cms_rooms((string) ($visit['place_key'] ?? ''));
        if ($rooms) {
            echo '<label>اتاق<select name="room"><option value="">بدون اتاق</option>';
            foreach ($rooms as $room) {
                echo '<option value="' . e((string) $room['room_key']) . '"' . ((string) ($visit['room_key'] ?? '') === (string) $room['room_key'] ? ' selected' : '') . '>' . e((string) $room['label']) . '</option>';
            }
            echo '</select></label>';
        }
        echo '</div>';
        echo '<label>علت مراجعه، بیمار این را نوشته<textarea class="note" name="message">' . e((string) $visit['message']) . '</textarea></label>';
        echo '<label>یادداشت داخلی، بیمار این را نمی‌بیند<textarea class="note" name="note">' . e((string) $visit['note']) . '</textarea></label>';
        echo '<label>آماده شدن برای بیمار، پیام نمی‌فرستد<input name="prep" value="' . e((string) ($visit['prep'] ?? '')) . '"></label>';
        echo '<label><span><input type="checkbox" name="force"> حتی اگر این ساعت پر یا روز بسته باشد ذخیره شود</span></label>';
        echo '<button class="btn" type="submit">ذخیره نوبت</button></form>';
        echo '<form class="row" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="notify-visit"><input type="hidden" name="id" value="' . (int) $visit['id'] . '">';
        echo '<select name="event">';
        foreach (cms_notify_events() as $value => $label) {
            echo '<option value="' . e($value) . '">' . e($label) . '</option>';
        }
        echo '</select><button class="btn ghost" type="submit">فرستادن دوباره پیام</button></form>';
        }
        echo '</article>';
    }

    if (!cms_nurse()) {
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-visit-new"><h2>نوبت تلفنی</h2>';
    echo '<p class="help">اگر بیمار زنگ زد، همین‌جا ثبت کنید. وضعیت «هماهنگ شد» پیام قطعی می‌فرستد. «درخواست تازه» پیام ثبت، با جمله هنوز قطعی نیست، می‌فرستد.</p><div class="grid-2">';
    echo '<label>نام<input name="name" required></label><label>موبایل<input name="phone" dir="ltr" required placeholder="0912..."></label>';
    echo '<label>ایمیل<input name="email" dir="ltr"></label><label>زبان پیام<select name="locale"><option value="fa">فارسی</option><option value="en">English</option><option value="ar">العربية</option><option value="zh">中文</option></select></label>';
    $deskUser = cms_user();
    $staffPlace = (string) ($deskUser['place_key'] ?? '');
    $placeRows = cms_places(true);
    if ($placeRows) {
        echo '<label>مکان<select name="place">';
        foreach ($placeRows as $place) {
            if (!cms_staff_may($staffPlace, (string) $place['place_key'])) {
                continue;
            }
            $kindName = ['clinic' => 'مطب', 'hospital' => 'بیمارستان', 'other' => 'جای دیگر'][(string) $place['kind']] ?? 'مطب';
            echo '<option value="' . e((string) $place['place_key']) . '">' . e($kindName . ' · ' . (string) $place['label_fa']) . '</option>';
        }
        echo '</select></label>';
        if (cms_staff_places($staffPlace) !== []) {
            echo '<p class="help">رزرو شما به مکان‌های بسته‌شده محدود است. اگر ساعت پر باشد، نام و مکان و ثبت‌کننده را می‌بینید، حتی اگر آن نوبت جای دیگری باشد.</p>';
        }
    }
    echo '<label>نوع نوبت<select name="kind">';
    foreach (cms_visit_kinds(true) as $kind) {
        echo '<option value="' . e($kind['key']) . '">' . e($kind['fa']) . '، ' . e(cms_fa_digits((string) $kind['minutes'])) . ' دقیقه</option>';
    }
    echo '</select></label><label>موضوع<select name="service">';
    foreach (cms_visit_topics(true) as $topic) {
        echo '<option value="' . e($topic['key']) . '">' . e($topic['fa']) . '</option>';
    }
    echo '</select></label><label>روز<input name="day" dir="ltr" required placeholder="1405-08-27"></label><label>ساعت<select name="time">';
    foreach (cms_book_slots() as $slot) {
        echo '<option value="' . e($slot) . '">' . e(cms_fa_digits($slot)) . '</option>';
    }
    echo '</select></label><label>وضعیت<select name="status"><option value="confirmed">هماهنگ شد</option><option value="new">درخواست تازه</option></select></label>';
    echo '<label>علت مراجعه<input name="message"></label><label>آماده شدن برای بیمار<input name="prep"></label></div><label>یادداشت داخلی<textarea class="note" name="note"></textarea></label>';
    echo '<button class="btn" type="submit">ثبت نوبت تلفنی</button></form>';
    }

    echo '<h2>درخواست‌های قدیمی فرم تماس</h2><p class="help">این‌ها نوبت کددار نیستند. نوبت‌های تازه سایت در کارت‌های بالا می‌آیند.</p>';
    echo '<div class="table-wrap"><table><tr><th>زمان</th><th>نام</th><th>تلفن</th><th>جزئیات</th><th>پیگیری</th></tr>';
    $leads = cms_db()->query("SELECT * FROM leads WHERE kind = 'appointment' ORDER BY id DESC LIMIT 30");
    $found = false;
    foreach ($leads as $lead) {
        $found = true;
        echo '<tr><td>' . e(cms_when((string) $lead['created_at'], true)) . '</td><td>' . e((string) $lead['name']) . '</td><td dir="ltr">' . e((string) $lead['phone']) . '</td><td>' . e((string) $lead['message']) . '</td><td><form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-lead"><input type="hidden" name="id" value="' . (int) $lead['id'] . '"><input type="hidden" name="back" value="appointments"><select name="status">';
        foreach (['new' => 'تازه', 'read' => 'خوانده‌شده', 'contacted' => 'تماس گرفته شد', 'booked' => 'نوبت شد', 'closed' => 'بسته'] as $value => $label) {
            echo '<option value="' . e($value) . '"' . ($lead['status'] === $value ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        echo '</select><textarea class="note" name="note">' . e((string) $lead['note']) . '</textarea><button class="btn" type="submit">ذخیره</button></form></td></tr>';
    }
    if (!$found) {
        echo '<tr><td colspan="5">درخواست قدیمی نیست.</td></tr>';
    }
    echo '</table></div>';
    desk_close();
}

function desk_schedule_keep(): string
{
    $html = '<input type="hidden" name="book_slots" value="' . e(implode(', ', cms_book_slots())) . '">';
    $html .= '<input type="hidden" name="capacity" value="' . e((string) cms_book_capacity()) . '">';
    $html .= '<input type="hidden" name="horizon" value="' . e((string) cms_book_horizon()) . '">';
    $html .= '<input type="hidden" name="lead" value="' . e((string) cms_book_lead()) . '">';
    $html .= '<input type="hidden" name="cutoff" value="' . e((string) cms_book_cutoff()) . '">';
    $html .= '<input type="hidden" name="phone_cap" value="' . e((string) cms_book_phone_cap()) . '">';
    $html .= '<input type="hidden" name="email_mode" value="' . e(cms_book_field_mode('book_email')) . '">';
    $html .= '<input type="hidden" name="reason_mode" value="' . e(cms_book_field_mode('book_reason')) . '">';
    if (cms_book_public()) {
        $html .= '<input type="hidden" name="book_public" value="1">';
    }
    if (cms_book_hold_new()) {
        $html .= '<input type="hidden" name="hold_new" value="1">';
    }
    foreach (cms_book_closed_weekdays() as $number) {
        $html .= '<input type="hidden" name="closed[]" value="' . (int) $number . '">';
    }
    return $html;
}

function desk_report_rate(?int $rate): string
{
    return $rate === null ? 'مخرج صفر است' : cms_fa_digits((string) $rate) . '٪';
}

function desk_report_gauge(?int $rate): string
{
    $width = $rate === null ? 0 : max(0, min(100, $rate));
    return '<div class="gauge" aria-hidden="true"><span style="width:' . $width . '%"></span></div>';
}

function desk_page_report(): void
{
    $zone = new DateTimeZone('Asia/Tehran');
    $today = (new DateTimeImmutable('today', $zone))->format('Y-m-d');
    $parts = explode('-', $today);
    $point = cms_g2j((int) $parts[0], (int) $parts[1], (int) $parts[2]);
    $jy = (int) ($_GET['jy'] ?? 0);
    $jm = (int) ($_GET['jm'] ?? 0);
    if ($jy < 1200 || $jy > 1600 || $jm < 1 || $jm > 12) {
        $jy = (int) $point['jy'];
        $jm = (int) $point['jm'];
    }
    $prevMonth = $jm === 1 ? 12 : $jm - 1;
    $prevYear = $jm === 1 ? $jy - 1 : $jy;
    $nextMonth = $jm === 12 ? 1 : $jm + 1;
    $nextYear = $jm === 12 ? $jy + 1 : $jy;
    $report = cms_month_report($jy, $jm, $today);
    $title = cms_jalali_month_name($jm) . ' ' . cms_fa_digits((string) $jy);
    desk_open('گزارش ' . $title, 'report', 'پر بودن از نوبت لغو‌نشده روی ساعت‌های روز باز است. نیامدن فقط میان حاضر، انجام‌شده و نیامده حساب می‌شود. نام بیمار در این صفحه نیست.');
    echo '<p class="row no-print">';
    if ($prevYear >= 1200) {
        echo '<a class="btn ghost" href="/manage/index.php?page=report&jy=' . $prevYear . '&jm=' . $prevMonth . '">ماه قبل</a>';
    }
    echo '<a class="btn ghost" href="/manage/index.php?page=report&jy=' . (int) $point['jy'] . '&jm=' . (int) $point['jm'] . '">این ماه</a>';
    if ($nextYear <= 1600) {
        echo '<a class="btn ghost" href="/manage/index.php?page=report&jy=' . $nextYear . '&jm=' . $nextMonth . '">ماه بعد</a>';
    }
    echo '<a class="btn ghost" href="/manage/index.php?page=appointments">نوبت‌ها</a>';
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="export-report"><input type="hidden" name="jy" value="' . $jy . '"><input type="hidden" name="jm" value="' . $jm . '"><button class="btn" type="submit">فایل همین ماه، بدون نام</button></form></p>';
    if (!$report['ok']) {
        echo '<section class="card"><p>این ماه خوانده نشد.</p></section>';
        desk_close();
        return;
    }
    echo '<section class="card sheet"><h2>' . e($title) . '</h2><div class="stats">';
    echo '<div class="stat"><span class="muted">پر بودن</span><b>' . e(desk_report_rate($report['fill'])) . '</b>' . desk_report_gauge($report['fill']) . '<span class="help">' . e(cms_fa_digits((string) $report['held'])) . ' از ' . e(cms_fa_digits((string) $report['capacity'])) . ' ساعت</span></div>';
    echo '<div class="stat"><span class="muted">نیامدن</span><b>' . e(desk_report_rate($report['miss'])) . '</b>' . desk_report_gauge($report['miss']) . '<span class="help">' . e(cms_fa_digits((string) $report['noshow'])) . ' نیامده، ' . e(cms_fa_digits((string) $report['seen'])) . ' آمده</span></div>';
    echo '<div class="stat"><span class="muted">لغو</span><b>' . e(cms_fa_digits((string) $report['cancelled'])) . '</b><span class="help">در پر بودن و نیامدن نیست</span></div>';
    echo '<div class="stat"><span class="muted">درخواست تازه</span><b>' . e(cms_fa_digits((string) $report['fresh'])) . '</b><span class="help">هنوز هماهنگ نشده</span></div>';
    echo '</div>';
    $lastYear = cms_month_report($jy - 1, $jm, $today);
    if (!empty($lastYear['ok'])) {
        echo '<p class="help">پارسال همین ماه: پر بودن ' . e(desk_report_rate($lastYear['fill'])) . ' و نیامدن ' . e(desk_report_rate($lastYear['miss'])) . '. نام بیمار در این مقایسه نیست.</p>';
    }
    echo '<p class="help">هماهنگ گذشته بدون حاضر یا نیامدن: ' . e(cms_fa_digits((string) $report['confirmed_past'])) . '. هماهنگ امروز و بعد: ' . e(cms_fa_digits((string) $report['confirmed_ahead'])) . '. این دو در نرخ نیامدن نیستند. روز بسته و جمعه ظرفیت صفر دارند، حتی اگر نوبتی رویشان نشسته باشد.</p>';
    echo '<div class="table-wrap"><table><tr><th>روز</th><th>ظرفیت</th><th>پر</th><th>آمده</th><th>نیامده</th><th>لغو</th></tr>';
    foreach ($report['days'] as $day) {
        echo '<tr' . ($day['open'] ? '' : ' class="shut"') . '><td>' . e(cms_day_label((string) $day['day'])) . '</td>';
        echo '<td>' . e($day['open'] ? cms_fa_digits((string) $day['capacity']) : 'بسته') . '</td>';
        echo '<td>' . e(cms_fa_digits((string) $day['held'])) . '</td>';
        echo '<td>' . e(cms_fa_digits((string) $day['seen'])) . '</td>';
        echo '<td>' . e(cms_fa_digits((string) $day['noshow'])) . '</td>';
        echo '<td>' . e(cms_fa_digits((string) $day['cancelled'])) . '</td></tr>';
    }
    echo '</table></div>';
    echo '<h2>به تفکیک نوع نوبت</h2>';
    if (!$report['kinds']) {
        echo '<p>نوبتی در این ماه نیست.</p>';
    } else {
        echo '<div class="table-wrap"><table><tr><th>نوع</th><th>لغو‌نشده</th><th>آمده</th><th>نیامده</th></tr>';
        foreach ($report['kinds'] as $kind) {
            echo '<tr><td>' . e(cms_kind_label((string) $kind['key'])) . '</td>';
            echo '<td>' . e(cms_fa_digits((string) $kind['held'])) . '</td>';
            echo '<td>' . e(cms_fa_digits((string) $kind['seen'])) . '</td>';
            echo '<td>' . e(cms_fa_digits((string) $kind['noshow'])) . '</td></tr>';
        }
        echo '</table></div>';
    }
    echo '</section>';
    desk_close();
}

function desk_page_schedule(): void
{
    desk_open('برنامه مطب', 'schedule', 'ساعت‌ها، روزهای بسته، افق تقویم، موضوع مراجعه و مدت هر نوع نوبت از اینجا به فرم سایت می‌رود.');
    $closed = cms_book_closed_weekdays();
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-schedule">';
    echo '<label>ساعت‌های قابل انتخاب، با ویرگول<textarea name="book_slots" dir="ltr">' . e(implode(', ', cms_book_slots())) . '</textarea></label>';
    echo '<p class="help">نمونه: 16:00, 16:30, 17:00. ساعت کاری عمومی سایت همچنان «شنبه تا پنجشنبه، با هماهنگی قبلی» است. این فهرست فقط خانه‌های فرم نوبت است.</p>';
    echo '<div class="grid-2"><label>ساخت از ساعت<input name="slot_from" dir="ltr" placeholder="16:00"></label><label>تا ساعت<input name="slot_until" dir="ltr" placeholder="20:00"></label><label>فاصله به دقیقه<input name="slot_step" dir="ltr" placeholder="30"></label></div>';
    echo '<p class="help">اگر این سه خانه را پر کنید، فهرست بالا با ساعت‌های ساخته‌شده عوض می‌شود. خالی بگذارید تا همان فهرست ویرگولی بماند.</p>';
    echo '<p>روزهای بسته</p><div class="row">';
    foreach (desk_weekdays() as $number => $label) {
        echo '<label><span><input type="checkbox" name="closed[]" value="' . $number . '"' . (in_array($number, $closed, true) ? ' checked' : '') . '> ' . e($label) . '</span></label>';
    }
    echo '</div><div class="grid-2"><label>ظرفیت هر خانه<input name="capacity" dir="ltr" value="' . e((string) cms_book_capacity()) . '"></label>';
    echo '<label>افق رزرو، روز<input name="horizon" dir="ltr" value="' . e((string) cms_book_horizon()) . '"></label>';
    echo '<label>شروع از چند روز بعد<input name="lead" dir="ltr" value="' . e((string) cms_book_lead()) . '"></label>';
    echo '<label>بستن امروز از ساعت<input name="cutoff" dir="ltr" value="' . e((string) cms_book_cutoff()) . '"></label>';
    echo '<label>سقف درخواست باز برای هر موبایل<input name="phone_cap" dir="ltr" value="' . e((string) cms_book_phone_cap()) . '"></label></div>';
    echo '<label><span><input type="checkbox" name="book_public"' . (cms_book_public() ? ' checked' : '') . '> فرم سایت درخواست تازه بپذیرد</span></label>';
    echo '<label><span><input type="checkbox" name="hold_new"' . (cms_book_hold_new() ? ' checked' : '') . '> درخواست تازه هم خانه ساعت را پر کند</span></label>';
    echo '<div class="grid-2"><label>ایمیل بیمار<select name="email_mode">';
    foreach (['optional' => 'اختیاری', 'required' => 'الزامی', 'off' => 'پنهان'] as $value => $label) {
        echo '<option value="' . e($value) . '"' . (cms_book_field_mode('book_email') === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label><label>علت مراجعه<select name="reason_mode">';
    foreach (['optional' => 'اختیاری', 'required' => 'الزامی', 'off' => 'پنهان'] as $value => $label) {
        echo '<option value="' . e($value) . '"' . (cms_book_field_mode('book_reason') === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></label></div>';
    echo '<p class="help">اگر ظرفیت ۱ باشد، یک نوبت فعال همان خانه را پر می‌کند. وقت جراحی ۶۰ دقیقه‌ای دو خانه ۳۰ دقیقه‌ای را می‌گیرد. افق از ۷ تا ۳۶۵ روز است. صفر در «شروع از چند روز بعد» یعنی بیمار از امروز می‌تواند وقت بگذارد. ساعت بستن امروز را ۲۴ بگذارید تا امروز تا آخر وقت باز بماند؛ ۱۴ یعنی از ساعت ۱۴ امروز بسته است. سقف موبایل صفر یعنی محدودیتی روی شماره نیست و ۱ تا ۵ یعنی همان تعداد درخواست باز.</p>';
    echo '<div class="grid-2"><label>بستن یک تاریخ خاص<input name="block_day" dir="ltr" placeholder="1405-08-29"></label><label>یادداشت همان روز<input name="block_note" placeholder="تعطیل، کنگره"></label></div>';
    echo '<button class="btn" type="submit">ذخیره برنامه</button></form>';
    echo '<section class="card slip"><h2>مطب‌ها و بیمارستان‌ها</h2>';
    echo '<p class="help">هر مکان روز و ساعت خودش را دارد. پزشک یکی است: اگر ساعتی در یک مکان پر شود، مکان دیگر همان ساعت را پر می‌بیند و نام، مکان و ثبت‌کننده را نشان می‌دهد. آدرس را خودتان بنویسید. تا وقتی مکانی نسازید، فرم سایت مثل قبل با برنامهٔ عمومی کار می‌کند.</p>';
    $placeNames = ['clinic' => 'مطب', 'hospital' => 'بیمارستان', 'other' => 'جای دیگر'];
    foreach (cms_places() as $place) {
        $days = [];
        foreach (cms_place_weekdays((string) $place['weekdays']) as $number) {
            $days[] = desk_weekdays()[$number] ?? (string) $number;
        }
        echo '<article class="rail"><strong>' . e($placeNames[(string) $place['kind']] ?? 'مطب') . ' · ' . e((string) $place['label_fa']) . '</strong>';
        echo '<p class="dose">' . e(implode('، ', $days)) . ' · <span dir="ltr">' . e((string) $place['start_time'] . '–' . (string) $place['end_time']) . '</span></p>';
        if ((string) $place['address'] !== '') {
            echo '<p>' . e((string) $place['address']) . '</p>';
        }
        $secretaries = cms_place_secretaries((string) $place['place_key']);
        echo '<p class="help">' . ((int) $place['active'] === 1 ? 'فعال' : 'خاموش');
        echo $secretaries !== [] ? ' · منشی: ' . e(implode('، ', $secretaries)) : ' · منشی جدا بسته نشده';
        $rooms = cms_rooms((string) $place['place_key'], false);
        if ($rooms) {
            $roomLabels = [];
            foreach ($rooms as $room) {
                $roomLabels[] = (string) $room['label'] . ((int) $room['active'] === 1 ? '' : ' (خاموش)');
            }
            echo ' · اتاق: ' . e(implode('، ', $roomLabels));
        }
        echo '</p></article>';
    }
    if (cms_owner()) {
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-place">';
        echo '<div class="grid-2"><label>نام مکان<input name="label_fa" placeholder="نامی که بیمار ببیند"></label>';
        echo '<label>شناسهٔ لاتین<input dir="ltr" name="key" placeholder="clinic-main"></label>';
        echo '<label>نوع<select name="kind"><option value="clinic">مطب</option><option value="hospital">بیمارستان</option><option value="other">جای دیگر</option></select></label>';
        echo '<label>از ساعت<input dir="ltr" name="start" placeholder="09:00"></label><label>تا ساعت<input dir="ltr" name="end" placeholder="13:00"></label></div>';
        echo '<label>آدرس، اگر می‌خواهید بیمار ببیند<textarea name="address" placeholder="فقط متنی که خودتان تأیید می‌کنید"></textarea></label>';
        echo '<p>روزهای حضور در این مکان</p><div class="row">';
        foreach (desk_weekdays() as $number => $label) {
            echo '<label><span><input type="checkbox" name="days[]" value="' . $number . '"> ' . e($label) . '</span></label>';
        }
        echo '</div><div class="grid-2"><label>افزودن منشی، چند نام با ویرگول<input dir="ltr" name="secretary" placeholder="staff-a, staff-b"></label>';
        echo '<label>برداشتن این مکان از منشی<input dir="ltr" name="release" placeholder="خالی یعنی کسی حذف نشود"></label>';
        echo '<label class="checkline"><input type="checkbox" name="active" value="1" checked> فعال باشد</label></div>';
        echo '<p class="help">هر ذخیره، این مکان را به فهرست همان منشی اضافه می‌کند و مکان‌های دیگرش را پاک نمی‌کند. نام خالی یعنی فهرست منشی‌ها دست نمی‌خورد. مالک، با فهرست خالی، همهٔ مکان‌ها را می‌بیند.</p>';
        echo '<button class="btn" type="submit">ذخیره مکان</button></form>';
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-room">';
        echo '<div class="grid-2"><label>مکان اتاق<select name="place">';
        foreach (cms_places() as $place) {
            echo '<option value="' . e((string) $place['place_key']) . '">' . e((string) $place['label_fa']) . '</option>';
        }
        echo '</select></label><label>شناسهٔ لاتین<input dir="ltr" name="key" placeholder="or-1"></label><label>نام اتاق<input name="label" placeholder="اتاق عمل ۱"></label></div>';
        echo '<p class="help">اتاق فقط یک نام روی همان مکان است. ساعت پرِ اتاق، نام و ثبت‌کننده را نشان می‌دهد. آدرس ساخته نمی‌شود.</p>';
        echo '<button class="btn" type="submit">ذخیره اتاق</button></form>';
    }
    echo '</section>';
    $blocks = cms_book_blocks();
    if ($blocks) {
        echo '<div class="card"><h2>تاریخ‌های بسته</h2>';
        foreach ($blocks as $block) {
            echo '<form class="row" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-schedule">' . desk_schedule_keep();
            echo '<input type="hidden" name="block_remove" value="' . e($block['day']) . '"><span>' . e(cms_day_label($block['day'])) . ' <span dir="ltr">' . e(cms_fa_digits(str_replace('-', '/', cms_shamsi_iso($block['day'])))) . '</span> ' . e($block['note']) . '</span><button class="btn ghost" type="submit">باز کردن این روز</button></form>';
        }
        echo '</div>';
    }
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-kinds"><h2>انواع نوبت</h2>';
    echo '<p class="help">نام‌ها روی فرم سایت دیده می‌شود. عدد ترتیب کوچک‌تر بالاتر می‌آید. مدت، فقط چیدن ساعت است و به بیمار وعده پزشکی نمی‌دهد. نوع را خاموش کنید اگر دیگر ارائه نمی‌شود. نوبت‌های قبلی همان نام را نگه می‌دارند.</p>';
    foreach (cms_visit_kinds() as $kind) {
        $key = $kind['key'];
        echo '<div class="card"><div class="row"><strong>' . e($kind['fa']) . '</strong><span class="muted" dir="ltr">' . e($key) . '</span></div>';
        echo '<input type="hidden" name="kind_key[]" value="' . e($key) . '"><div class="grid-2">';
        echo '<label>نام فارسی<input name="label_fa[' . e($key) . ']" value="' . e($kind['fa']) . '"></label>';
        echo '<label>English<input dir="ltr" name="label_en[' . e($key) . ']" value="' . e($kind['en']) . '"></label>';
        echo '<label>العربية<input dir="rtl" name="label_ar[' . e($key) . ']" value="' . e($kind['ar']) . '"></label>';
        echo '<label>中文<input dir="ltr" name="label_zh[' . e($key) . ']" value="' . e($kind['zh']) . '"></label>';
        echo '<label>مدت به دقیقه<input dir="ltr" name="minutes[' . e($key) . ']" value="' . e((string) $kind['minutes']) . '"></label>';
        echo '<label>ترتیب<input dir="ltr" name="sort[' . e($key) . ']" value="' . e((string) $kind['sort']) . '"></label>';
        echo '<label><span><input type="checkbox" name="active[' . e($key) . ']"' . ($kind['active'] ? ' checked' : '') . '> روی فرم سایت باشد</span></label>';
        echo '<label><span><input type="checkbox" name="drop_kind[]" value="' . e($key) . '"> حذف، اگر هیچ نوبتی این نوع را نداشته باشد</span></label></div></div>';
    }
    echo '<h2>نوع تازه</h2><div class="grid-2"><label>شناسه انگلیسی<input dir="ltr" name="new_key" placeholder="suture"></label><label>نام فارسی<input name="new_label" placeholder="کشیدن بخیه"></label><label>مدت<input dir="ltr" name="new_minutes" value="20"></label></div>';
    echo '<button class="btn" type="submit">ذخیره انواع نوبت</button></form>';
    echo '<form class="card" method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-topics"><h2>موضوع مراجعه</h2>';
    echo '<p class="help">این نام‌ها فقط روی فرم نوبت دیده می‌شوند و عدد ترتیب کوچک‌تر بالاتر می‌آید. عوض کردنشان صفحه درمان‌ها را عوض نمی‌کند. موضوع خاموش از فرم سایت کنار می‌رود و نوبت‌های قبلی همان نام را نگه می‌دارند.</p>';
    foreach (cms_visit_topics() as $topic) {
        $key = $topic['key'];
        echo '<div class="card"><div class="row"><strong>' . e($topic['fa']) . '</strong><span class="muted" dir="ltr">' . e($key) . '</span></div>';
        echo '<input type="hidden" name="topic_key[]" value="' . e($key) . '"><div class="grid-2">';
        echo '<label>نام فارسی<input name="topic_fa[' . e($key) . ']" value="' . e($topic['fa']) . '"></label>';
        echo '<label>English<input dir="ltr" name="topic_en[' . e($key) . ']" value="' . e($topic['en']) . '"></label>';
        echo '<label>العربية<input dir="rtl" name="topic_ar[' . e($key) . ']" value="' . e($topic['ar']) . '"></label>';
        echo '<label>中文<input dir="ltr" name="topic_zh[' . e($key) . ']" value="' . e($topic['zh']) . '"></label>';
        echo '<label>ترتیب<input dir="ltr" name="topic_sort[' . e($key) . ']" value="' . e((string) $topic['sort']) . '"></label>';
        echo '<label>کد خدمت، اگر دارید<input dir="ltr" name="topic_code[' . e($key) . ']" value="' . e((string) ($topic['code'] ?? '')) . '" placeholder="خالی یعنی کدی نیست"></label>';
        echo '<label><span><input type="checkbox" name="topic_active[' . e($key) . ']"' . ($topic['active'] ? ' checked' : '') . '> روی فرم سایت باشد</span></label>';
        echo '<label><span><input type="checkbox" name="drop_topic[]" value="' . e($key) . '"> حذف، اگر هیچ نوبتی این موضوع را نداشته باشد</span></label></div></div>';
    }
    echo '<h2>موضوع تازه</h2><div class="grid-2"><label>شناسه انگلیسی<input dir="ltr" name="new_topic_key" placeholder="follow-up"></label><label>نام فارسی<input name="new_topic_label" placeholder="پیگیری"></label></div>';
    echo '<button class="btn" type="submit">ذخیره موضوع‌ها</button></form>';
    desk_close();
}

function desk_page_messaging(): void
{
    desk_open('پیام‌رسانی', 'messaging', 'پیامک بیمار به موبایل خودش می‌رود. پیامک پزشک و بله و تلگرام و ایتا به خود پزشک می‌رود، چون ربات با شماره موبایل بیمار حرف نمی‌زند.');
    $cron = cms_origin() . '/manage/cron.php?token=' . cms_setting('cron_token');
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="save-messaging">';
    echo '<section class="card"><h2>ملی‌پیامک</h2>';
    echo '<label><span><input type="checkbox" name="sms_on"' . (cms_setting('sms_on') === '1' ? ' checked' : '') . '> پیامک روشن باشد</span></label>';
    echo '<label>روش<select name="sms_mode"><option value="rest"' . (cms_setting('sms_mode', 'rest') !== 'console' ? ' selected' : '') . '>نام کاربری و رمز پنل</option><option value="console"' . (cms_setting('sms_mode') === 'console' ? ' selected' : '') . '>کلید کنسول</option></select></label>';
    echo '<div class="grid-2"><label>نام کاربری<input dir="ltr" name="sms_user" value="' . e(cms_setting('sms_user')) . '"></label>';
    echo '<label>رمز پنل<input type="password" name="sms_pass" autocomplete="new-password" placeholder="' . (cms_setting('sms_pass') !== '' ? 'ذخیره شده، برای عوض کردن بنویسید' : '') . '"></label>';
    echo '<label>شماره خط<input dir="ltr" name="sms_from" value="' . e(cms_setting('sms_from')) . '" placeholder="5000..."></label>';
    echo '<label>موبایل پزشک<input dir="ltr" name="sms_doctor" value="' . e(cms_setting('sms_doctor')) . '" placeholder="0912..."></label>';
    echo '<label>کلید کنسول<input type="password" name="sms_key" autocomplete="new-password" placeholder="' . (cms_setting('sms_key') !== '' ? 'ذخیره شده' : 'فقط برای روش کنسول') . '"></label></div>';
    echo '<label><span><input type="checkbox" name="clear_sms_pass"> حذف رمز ذخیره‌شده</span></label>';
    echo '<label><span><input type="checkbox" name="clear_sms_key"> حذف کلید کنسول</span></label>';
    echo '<p class="help">رمز و کلید در صفحه دیده نمی‌شود و در گزارش سیستم هم نوشته نمی‌شود. روش معمولی همان پنل payamak-panel است. روش کنسول کلید را از console.melipayamak.com می‌گیرد.</p>';
    echo '<div class="grid-2"><label>یادآوری از ساعت<input dir="ltr" name="sms_quiet_until" value="' . e(cms_setting('sms_quiet_until', '8')) . '"></label>';
    echo '<label>یادآوری تا ساعت<input dir="ltr" name="sms_quiet_from" value="' . e(cms_setting('sms_quiet_from', '21')) . '"></label>';
    echo '<label>یادآوری چند روز قبل<input dir="ltr" name="remind_days" value="' . e((string) cms_remind_days()) . '"></label>';
    echo '<label>جبران اگر جا ماند، روز<input dir="ltr" name="remind_grace" value="' . e((string) cms_remind_grace()) . '"></label></div>';
    echo '<p class="help">کلید «پیامک روشن باشد» اگر خاموش بماند، هیچ پیامکی نمی‌رود؛ حتی اگر ردیف‌های پایین تیک داشته باشند. قطع یک شماره از داخل پرونده همان بیمار است و پیام پزشک را قطع نمی‌کند. ثبت و هماهنگی هر ساعت می‌تواند برود. یادآوری فقط در ساعت بیداری می‌رود و اگر کار زمان‌بندی شب باشد، علامت نمی‌خورد. عدد ۱ یعنی یک روز قبل. جبران ۲ یعنی اگر آن شب نرفت، فردا صبح هنوز فرستاده می‌شود. بعد از این بازه دیگر نمی‌رود. در متن، {when} امروز یا فردا را درست می‌گذارد و {place} نام مکانی است که خودتان ساخته‌اید.</p></section>';

    echo '<section class="card slip"><h2>چه پیامی برود</h2><p class="help">هر ردیف را جدا خاموش کنید. متن پایه همان جمله‌ای است که با خانهٔ خالی فرستاده می‌شود. نشانه‌ها: {name} {code} {day} {when} {time} {kind} {service} {phone} {place} {note}. یادداشت داخلی فقط اگر خودتان {note} را در متن بگذارید می‌رود. جملهٔ قدیمی که فقط «فردا» داشت فرستاده نمی‌شود.</p>';
    foreach (cms_notify_events() as $event => $label) {
        echo '<article class="rail"><strong>' . e($label) . '</strong><div class="line">';
        echo '<label class="checkline"><input type="checkbox" name="patient[' . e($event) . ']"' . (cms_notify_on('patient', $event) ? ' checked' : '') . '> بیمار</label>';
        echo '<label class="checkline"><input type="checkbox" name="doctor[' . e($event) . ']"' . (cms_notify_on('doctor', $event) ? ' checked' : '') . '> پزشک</label></div>';
        echo '<p class="base">متن پایهٔ بیمار: ' . e(cms_tpl_default('patient', $event, 'fa')) . '</p></article>';
    }
    echo '<article class="rail"><strong>خبر پرونده به پزشک</strong><div class="line">';
    echo '<label class="checkline"><input type="checkbox" name="care_alert"' . (cms_setting('care_alert') === '1' ? ' checked' : '') . '> پزشک، بدون متن بیمار</label></div>';
    echo '<p class="base">متن پایه: پیام تازه در پرونده درمان است. چهار رقم آخر: {tail}. متن بیمار در این پیام نیست.</p></article>';
    echo '<article class="rail"><strong>یادآوری شخصی بیمار</strong><div class="line">';
    echo '<label class="checkline"><input type="checkbox" name="care_self_sms"' . (cms_care_on('care_self_sms', false) ? ' checked' : '') . '> فقط جملهٔ خود بیمار، در ساعت بیداری</label></div>';
    echo '<p class="base">متن پایه: یادآوری شما برای {day}: {note}</p></article>';
    echo '</section>';

    echo '<section class="card"><h2>متن پیام بیمار</h2>';
    foreach (cms_notify_events() as $event => $label) {
        $patientTpl = cms_setting('tpl_patient_' . $event);
        echo '<label>' . e($label) . '<textarea name="tpl_patient_' . e($event) . '" placeholder="' . e(cms_tpl_default('patient', $event, 'fa')) . '">' . e(cms_tpl_stale($patientTpl) ? '' : $patientTpl) . '</textarea></label>';
        echo '<p class="base">اگر خالی بماند: ' . e(cms_tpl_default('patient', $event, 'fa')) . '</p>';
    }
    echo '</section><details class="card"><summary>متن انگلیسی، عربی و چینی بیمار</summary><p class="help">اگر خالی بماند، همان متن فارسی می‌رود.</p>';
    foreach (['en' => 'English', 'ar' => 'العربية', 'zh' => '中文'] as $locale => $title) {
        echo '<h2>' . e($title) . '</h2>';
        foreach (cms_notify_events() as $event => $label) {
            $localeTpl = cms_setting('tpl_patient_' . $event . '_' . $locale);
            echo '<label>' . e($label) . '<textarea dir="ltr" name="tpl_patient_' . e($event) . '_' . e($locale) . '">' . e(cms_tpl_stale($localeTpl) ? '' : $localeTpl) . '</textarea></label>';
        }
    }
    echo '</details><section class="card"><h2>متن پیام پزشک</h2><p class="help">پیام پزشک همیشه فارسی است، حتی اگر بیمار سایت را انگلیسی دیده باشد.</p>';
    foreach (cms_notify_events() as $event => $label) {
        $doctorTpl = cms_setting('tpl_doctor_' . $event);
        echo '<label>' . e($label) . '<textarea name="tpl_doctor_' . e($event) . '" placeholder="' . e(cms_tpl_default('doctor', $event, 'fa')) . '">' . e(cms_tpl_stale($doctorTpl) ? '' : $doctorTpl) . '</textarea></label>';
        echo '<p class="base">اگر خالی بماند: ' . e(cms_tpl_default('doctor', $event, 'fa')) . '</p>';
    }
    echo '</section>';
    echo '<section class="card"><h2>متن پیام آزمایشی</h2>';
    echo '<label>خبر پرونده<textarea name="tpl_care_alert" placeholder="پیام تازه در پرونده درمان است. چهار رقم آخر: {tail}. متن بیمار در این پیام نیست.">' . e(cms_setting('tpl_care_alert')) . '</textarea></label>';
    echo '<label>یادآوری شخصی<textarea name="tpl_care_self" placeholder="یادآوری شما برای {day}: {note}">' . e(cms_setting('tpl_care_self')) . '</textarea></label>';
    echo '<label>متن<textarea name="tpl_test" placeholder="پیام آزمایشی میز مطب دکتر مهرشاد جعفری. اگر این متن را دیدید، این مسیر وصل است.">' . e(cms_setting('tpl_test')) . '</textarea></label>';
    echo '<p class="help">خالی بماند تا همان جمله پیش‌فرض برود. این متن فقط با دکمه‌های آزمایش فرستاده می‌شود.</p></section>';

    echo '<section class="card"><h2>بله، تلگرام، ایتا</h2><p class="help">ربات را در گفتگوی خود پزشک استارت کنید و شناسه عددی همان گفتگو را بگذارید. این مسیرها به بیمار پیام نمی‌دهند.</p>';
    foreach (['bale' => 'بله', 'telegram' => 'تلگرام', 'eitaa' => 'ایتا'] as $channel => $label) {
        echo '<h2>' . e($label) . '</h2><label><span><input type="checkbox" name="' . e($channel) . '_on"' . (cms_setting($channel . '_on') === '1' ? ' checked' : '') . '> روشن</span></label><div class="grid-2">';
        echo '<label>توکن ربات<input type="password" dir="ltr" name="' . e($channel) . '_token" autocomplete="new-password" placeholder="' . (cms_setting($channel . '_token') !== '' ? 'ذخیره شده' : '') . '"></label>';
        echo '<label>شناسه گفتگوی پزشک<input dir="ltr" name="' . e($channel) . '_chat" value="' . e(cms_setting($channel . '_chat')) . '"></label></div>';
        echo '<label><span><input type="checkbox" name="clear_' . e($channel) . '"> حذف توکن ' . e($label) . '</span></label>';
    }
    echo '</section><button class="btn" type="submit">ذخیره پیام‌رسانی</button></form>';

    echo '<section class="card"><h2>آزمایش و یادآوری</h2><div class="row">';
    foreach (['sms' => 'آزمایش پیامک پزشک', 'bale' => 'آزمایش بله', 'telegram' => 'آزمایش تلگرام', 'eitaa' => 'آزمایش ایتا'] as $channel => $label) {
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="send-test"><input type="hidden" name="channel" value="' . e($channel) . '"><button class="btn ghost" type="submit">' . e($label) . '</button></form>';
    }
    echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="send-reminders"><button class="btn" type="submit">یادآوری نوبت‌های پیش‌رو</button></form></div>';
    echo '<p class="help">همان نشانی زمان‌بندی، یادآوری نوبت و یادآوری شخصی را هم می‌فرستد. آن را جایی عمومی نگذارید. اگر فقط شب اجرا شود چیزی علامت نمی‌خورد و صبح، تا روزهای جبران، فرستاده می‌شود. پیشنهاد: هر روز ساعت ۹ صبح.</p><input dir="ltr" readonly value="' . e($cron) . '"></section>';
    echo '<section class="card"><h2>پاسخ پزشک</h2><p class="help">فقط جوابی گرفته می‌شود که پزشک روی همان پیام میز زده باشد. گفتگوی دیگر وارد پرونده نمی‌شود. متن بیمار در پیام پزشک نیست و این دکمه‌ها پیام تازه نمی‌فرستند. تا وقتی همان پیام‌رسان روشن و ذخیره نشده باشد، این دکمه چیزی نمی‌گیرد.</p><div class="row">';
    foreach (['bale' => 'گرفتن پاسخ بله', 'telegram' => 'گرفتن پاسخ تلگرام', 'eitaa' => 'گرفتن پاسخ ایتا'] as $channel => $label) {
        echo '<form method="post">' . desk_csrf() . '<input type="hidden" name="action" value="pull-chat"><input type="hidden" name="channel" value="' . e($channel) . '"><button class="btn ghost" type="submit">' . e($label) . '</button></form>';
    }
    echo '</div></section>';

    echo '<section class="card"><h2>گزارش پیام‌ها</h2><div class="table-wrap"><table><tr><th>زمان</th><th>مسیر</th><th>گیرنده</th><th>رویداد</th><th>متن</th><th>نتیجه</th><th>خطا</th></tr>';
    $any = false;
    foreach (cms_db()->query('SELECT * FROM messages_out ORDER BY id DESC LIMIT 40') as $message) {
        $any = true;
        echo '<tr><td>' . e(cms_when((string) $message['created_at'], true)) . '</td><td>' . e((string) $message['channel']) . '</td><td>' . e((string) $message['audience']) . '<br><span dir="ltr">' . e((string) $message['target']) . '</span></td><td>' . e(cms_notify_events()[(string) $message['event']] ?? (['care' => 'پیام پرونده', 'care-self' => 'یادآوری شخصی'][(string) $message['event']] ?? (string) $message['event'])) . '</td>';
        echo '<td>' . e(desk_clip((string) $message['body'], 90)) . '</td>';
        echo '<td><span class="pill ' . e(desk_message_tone((string) $message['status'])) . '">' . e(desk_message_label((string) $message['status'])) . '</span></td><td>' . e((string) $message['error']) . '</td></tr>';
    }
    if (!$any) {
        echo '<tr><td colspan="7">هنوز پیامی ثبت نشده است.</td></tr>';
    }
    echo '</table></div></section>';
    desk_close();
}
