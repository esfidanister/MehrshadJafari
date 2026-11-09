<?php
declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'board.php') {
    http_response_code(404);
    exit;
}

function care_h(string $key, string $fallback): string
{
    return e(cms_care_heading($key, $fallback, cms_care_locale()));
}

function care_t(string $key): string
{
    return e(cms_care_ui($key));
}

function care_digits(string $value): string
{
    $locale = cms_care_locale();
    if ($locale === 'fa') {
        return cms_fa_digits($value);
    }
    if ($locale === 'ar') {
        return strtr($value, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }
    return $value;
}

function care_langs(): string
{
    $current = cms_care_locale();
    $html = '<form method="post" class="langs no-print"><input type="hidden" name="csrf" value="' . e(cms_care_csrf()) . '"><input type="hidden" name="action" value="lang">';
    foreach (['fa' => 'فارسی', 'en' => 'English', 'ar' => 'العربية', 'zh' => '中文'] as $code => $label) {
        $html .= '<button class="btn ghost' . ($current === $code ? ' on' : '') . '" name="locale" value="' . e($code) . '" type="submit">' . e($label) . '</button>';
    }
    return $html . '</form>';
}

function care_place(array $visit): string
{
    $row = cms_place_row((string) ($visit['place_key'] ?? ''));
    if (!$row) {
        return '';
    }
    $label = cms_place_label($row, cms_care_locale());
    $address = trim((string) ($row['address'] ?? ''));
    return $address === '' ? $label : $label . ' — ' . $address;
}

function care_day(string $iso): string
{
    return cms_day_label($iso, cms_care_locale());
}

function care_status(string $status): string
{
    return cms_status_label($status, cms_care_locale());
}

function care_board(string $phone, string $error, string $sent): string
{
    if (cms_care_role() === 'companion') {
        return care_companion_board($phone, $error);
    }
    $next = cms_care_next($phone);
    $visits = cms_care_visits($phone);
    $shownName = 'بیمار';
    foreach ($visits as $visit) {
        if (cms_chars((string) $visit['name']) >= 2) {
            $shownName = (string) $visit['name'];
            break;
        }
    }
    cms_care_mark_read($phone);
    $posts = cms_care_thread($phone, false);
    $facts = cms_care_facts($phone);
    $stages = cms_care_stages(true);
    $meds = cms_care_meds($phone);
    $needs = cms_care_needs($phone);
    $reminders = cms_care_reminders($phone);
    $comments = cms_care_comments($phone, false);
    $byVisit = [];
    foreach ($comments as $comment) {
        $byVisit[(int) $comment['visit_id']][] = $comment;
    }
    $csrf = e(cms_care_csrf());
    $html = '<header class="top"><div><p class="eyebrow">' . e($shownName) . '</p><h1>' . e(cms_care_title(cms_care_locale())) . '</h1></div>';
    $html .= '<div class="row no-print"><form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="sheet-pdf"><button class="btn" type="submit">' . care_t('pdf-mine') . '</button></form>';
    $html .= '<button type="button" class="btn ghost" onclick="window.print()">' . care_t('print-mine') . '</button>';
    $html .= '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="logout"><button class="btn ghost" type="submit">' . care_t('logout') . '</button></form></div></header>';
    $html .= care_langs();
    $html .= '<p class="help">' . e(cms_care_notice(cms_care_locale())) . ' ' . care_emergency_link() . '</p>';
    $quiet = cms_sms_stopped($phone);
    $html .= '<section class="card ticket no-print"><h2>' . care_t('sms-title') . '</h2>';
    $html .= '<p class="help">' . care_t($quiet ? 'sms-off-help' : 'sms-on-help') . '</p>';
    $html .= '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="' . ($quiet ? 'sms-resume' : 'sms-stop') . '">';
    $html .= '<button class="btn ghost" type="submit">' . care_t($quiet ? 'sms-resume' : 'sms-stop') . '</button></form></section>';
    $identity = cms_patient_row($phone);
    $birth = (string) ($identity['birth'] ?? '');
    $national = (string) ($identity['national_id'] ?? '');
    if ($birth !== '' || $national !== '') {
        $html .= '<section class="card ticket"><h2>' . care_t('identity') . '</h2>';
        if ($birth !== '') {
            $html .= '<p>' . care_t('birth') . ': ' . e(care_day($birth)) . '</p>';
        }
        if ($national !== '') {
            $html .= '<p class="idline" dir="ltr">' . e(cms_fa_digits($national)) . '</p>';
        }
        $html .= '<p class="help">' . care_t('identity-help') . '</p></section>';
    }
    $freshCode = cms_care_take_fresh_code();
    if ($freshCode !== '') {
        $html .= '<section class="card no-print"><h2>' . care_t('code-title') . '</h2><p class="when" dir="ltr">' . e($freshCode) . '</p>';
        $html .= '<p class="help">' . care_t('code-help') . '</p></section>';
    } elseif ($sent === 'code') {
        $html .= '<p class="ok no-print">' . care_t('code-changed') . '</p>';
    } elseif ($sent === 'sms') {
        $html .= '<p class="ok no-print">' . care_t(cms_sms_stopped($phone) ? 'sms-stopped' : 'sms-resumed') . '</p>';
    }
    if (cms_care_on('care_patient_rotate')) {
        $html .= '<form method="post" class="no-print"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="code-rotate"><button class="btn ghost" type="submit">' . care_t('rotate') . '</button></form>';
    }
    if (cms_care_on('care_companion') && cms_care_on('care_patient_companion')) {
        $freshMate = cms_care_companion_flash_name();
        $mateCode = cms_care_take_companion_code();
        $mates = cms_care_companions($phone);
        $html .= '<section class="card ticket no-print"><h2>' . care_t('companion-title') . '</h2>';
        $html .= '<p class="help">' . care_t('companion-add') . '</p>';
        if ($mateCode !== '') {
            if ($freshMate !== '') {
                $html .= '<p class="mate">' . e($freshMate) . '</p>';
            }
            $html .= '<p class="when" dir="ltr">' . e($mateCode) . '</p>';
            $html .= '<p class="help">' . care_t('companion-help') . '</p>';
        } elseif ($sent === 'mate') {
            $html .= '<p class="ok">' . care_t('companion-made') . '</p>';
        } elseif ($sent === 'mate-off') {
            $html .= '<p class="ok">' . care_t('companion-closed') . '</p>';
        }
        if ($mates) {
            $html .= '<div class="mates">';
            foreach ($mates as $mate) {
                $until = (string) $mate['expires_at'] === '' ? cms_care_ui('companion-until-open') : str_replace('{day}', care_day((string) $mate['expires_at']), cms_care_ui('companion-until'));
                $mateName = (string) ($mate['name'] ?? '');
                $html .= '<article><b>' . e($mateName !== '' ? $mateName : cms_care_ui('companion-unnamed')) . '</b>';
                $html .= '<small>' . e($until . ' ' . cms_care_ui('companion-stays')) . '</small>';
                $html .= '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="companion-revoke"><input type="hidden" name="id" value="' . (int) $mate['id'] . '"><button class="btn ghost" type="submit">' . care_t('companion-revoke') . '</button></form></article>';
            }
            $html .= '</div>';
        }
        if (count($mates) >= 4) {
            $html .= '<p class="help">' . care_t('companion-full') . '</p>';
        } else {
            $html .= '<form method="post" class="line"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="companion-issue">';
            $html .= '<input name="name" placeholder="' . care_t('companion-name') . '"><button class="btn ghost" type="submit">' . care_t('companion-make') . '</button></form>';
        }
        $html .= '</section>';
    }
    if ($sent === 'facts') {
        $html .= '<p class="ok">' . care_t('facts-saved') . '</p>';
    } elseif ($sent === 'med') {
        $html .= '<p class="ok">' . care_t('med-saved') . '</p>';
    } elseif ($sent === 'dose') {
        $html .= '<p class="ok">' . care_t('dose-saved') . '</p>';
    } elseif ($sent === 'medsame') {
        $html .= '<p class="ok">' . care_t('dose-same') . '</p>';
    } elseif ($sent === 'need') {
        $html .= '<p class="ok">' . care_t('need-saved') . '</p>';
    } elseif ($sent === 'remind') {
        $html .= '<p class="ok">' . care_t('remind-saved') . '</p>';
    } elseif ($sent === 'comment') {
        $html .= '<p class="ok">' . care_t('comment-saved') . '</p>';
    } elseif ($sent === 'ask') {
        $html .= '<p class="ok">' . care_t('ask-saved') . '</p>';
    } elseif ($sent !== '' && $sent !== 'code' && $sent !== 'sms' && $sent !== 'mate' && $sent !== 'mate-off') {
        $html .= '<p class="ok">' . care_t('message-saved') . '</p>';
    }
    if ($error !== '') {
        $html .= '<p class="bad">' . e(cms_care_say($error)) . '</p>';
    }

    $html .= '<section class="hero ticket"><p class="eyebrow">' . care_h('care_h_next', 'نوبت بعدی') . '</p>';
    if (!$next) {
        $html .= '<p>' . care_t('no-next') . '</p>';
    } else {
        $status = (string) $next['status'];
        $locale = cms_care_locale();
        $html .= '<p class="when">' . e(care_day((string) $next['day'])) . '</p>';
        if ($locale === 'fa') {
            $html .= '<p class="help" dir="ltr">' . e(cms_fa_digits(str_replace('-', '/', cms_shamsi_iso((string) $next['day'])))) . '</p>';
        }
        $html .= '<p class="clock" dir="ltr">' . e(care_digits((string) $next['time_slot'])) . '</p>';
        $placeLine = care_place($next);
        if ($placeLine !== '') {
            $html .= '<p>' . e($placeLine) . '</p>';
        }
        $html .= '<p><span class="pill">' . e(care_status($status)) . '</span> ';
        $html .= e((string) $next['source'] === 'site' ? cms_care_ui('request-site') : cms_care_ui('time-clinic')) . '</p>';
        $serviceCode = cms_topic_code((string) $next['service']);
        $roomName = cms_room_label((string) ($next['place_key'] ?? ''), (string) ($next['room_key'] ?? ''));
        $html .= '<p>' . e(cms_kind_label((string) $next['kind'], $locale)) . ' · ' . e(cms_service_label((string) $next['service'], $locale));
        if ($serviceCode !== '') {
            $html .= ' · <span class="chip" dir="ltr">' . e($serviceCode) . '</span>';
        }
        if ($roomName !== '') {
            $html .= ' · <span class="chip">' . e($roomName) . '</span>';
        }
        $html .= '</p>';
        if ($status === 'new') {
            $html .= '<p class="help">' . care_t('not-final') . '</p>';
        } else {
            $remind = cms_care_reminder_line($next, $locale);
            if ($remind !== '') {
                $html .= '<p class="help">' . e($remind) . '</p>';
            }
        }
        if (trim((string) ($next['prep'] ?? '')) !== '') {
            $html .= '<p class="prep">' . care_t('prep') . ': ' . e((string) $next['prep']) . '</p>';
        }
    }
    $html .= '</section>';

    if ($next) {
        $checks = cms_checks((int) $next['id']);
        if ($checks) {
            $html .= '<section class="card"><h2>' . care_t('check-title') . '</h2><p class="help">' . care_t('check-help') . '</p><div class="checks">';
            foreach ($checks as $check) {
                $done = (int) $check['done'] === 1;
                $html .= '<p class="tickrow' . ($done ? ' done' : '') . '"><span>' . ($done ? '✓' : '·') . '</span> ' . e((string) $check['label']) . '</p>';
            }
            $html .= '</div></section>';
        }
    }
    if (cms_setting('care_teeth', '0') === '1' && cms_teeth_marks($phone)) {
        $html .= '<section class="card"><h2>' . care_t('tooth-title') . '</h2><p class="help">' . care_t('tooth-help') . '</p>';
        $html .= cms_teeth_markup(cms_teeth_marks($phone), false) . '</section>';
    }

    $html .= '<section class="card"><h2>' . care_h('care_h_stage', 'روند درمان') . '</h2><div class="steps">';
    if (!$stages) {
        $html .= '<p class="help">' . care_t('no-stages') . '</p>';
    } else {
        $current = (string) ($facts['stage_key'] ?? '');
        $state = 'before';
        foreach ($stages as $stage) {
            if ($stage['key'] === $current) {
                $class = 'on';
                $state = 'after';
            } elseif ($state === 'before' && $current !== '') {
                $class = 'done';
            } else {
                $class = '';
            }
            $label = trim((string) ($stage[cms_care_locale()] ?? ''));
            $html .= '<span class="step ' . $class . '">' . e($label !== '' ? $label : $stage['fa']) . '</span>';
        }
        if ($current === '' || cms_care_stage_label($current, cms_care_locale()) === '') {
            $html .= '<p class="help">' . care_t('stage-empty') . '</p>';
        }
    }
    $html .= '</div></section>';

    $html .= '<div class="split">';
    $html .= '<section class="card"><h2>' . care_h('care_h_need', 'چه چیز لازم است') . '</h2>';
    if (!$needs) {
        $html .= '<p class="help">' . care_t('no-needs') . '</p>';
    }
    foreach ($needs as $need) {
        $done = (int) $need['done'] === 1;
        $html .= '<form class="line" method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="need-toggle"><input type="hidden" name="id" value="' . (int) $need['id'] . '">';
        $html .= '<button class="tick' . ($done ? ' done' : '') . '" type="submit">' . ($done ? '✓' : '') . '</button>';
        $html .= '<span' . ($done ? ' class="muted"' : '') . '>' . e((string) $need['body']) . '</span>';
        $html .= (string) $need['author'] === 'patient' ? '<small>' . care_t('your-task') . '</small>' : '<small>' . care_t('clinic-task') . '</small>';
        $html .= '</form>';
        if ((string) $need['author'] === 'patient') {
            $html .= '<form method="post" class="tiny"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="need-delete"><input type="hidden" name="id" value="' . (int) $need['id'] . '"><button class="btn ghost" type="submit">' . care_t('remove') . '</button></form>';
        }
    }
    if (cms_care_on('care_patient_need')) {
        $html .= '<form method="post" class="stack"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="need-add">';
        $html .= '<input name="body" placeholder="' . care_t('need-placeholder') . '">';
        $html .= '<button class="btn" type="submit">' . care_t('add-need') . '</button></form>';
    }
    $html .= '</section>';

    $html .= '<section class="card"><h2>' . care_h('care_h_med', 'داروها') . '</h2>';
    $allergy = cms_care_allergy_text($phone);
    if ($allergy !== '') {
        $html .= '<p class="watch"><strong>' . care_t('allergy-watch') . '</strong> ' . e($allergy) . '<br><span class="help">' . care_t('allergy-not-check') . '</span></p>';
    }
    $html .= '<p class="help">' . care_t('meds-help') . '</p>';
    if (!$meds) {
        $html .= '<p class="help">' . care_t('no-meds') . '</p>';
    }
    foreach ($meds as $med) {
        $off = (int) $med['active'] !== 1;
        $mine = (string) $med['author'] === 'patient';
        $html .= '<article class="visit"><strong' . ($off ? ' class="muted"' : '') . '>' . e((string) $med['name']) . '</strong>';
        $html .= '<br><span class="help">' . e($mine ? cms_care_ui('yours') : cms_care_ui('clinic-wrote')) . ($off ? ' · ' . cms_care_ui('stopped') : '') . '</span>';
        $html .= '<p class="dose">' . e((string) $med['dose'] !== '' ? (string) $med['dose'] : cms_care_ui('empty')) . '</p>';
        if ((string) ($med['clock'] ?? '') !== '') {
            $html .= '<p>' . e((string) $med['clock']) . '</p>';
        }
        if ((string) ($med['caution'] ?? '') !== '') {
            $html .= '<p class="help">' . e((string) $med['caution']) . '</p>';
        }
        if ((string) ($med['route'] ?? '') !== '' || (string) ($med['span'] ?? '') !== '') {
            $html .= '<p class="chip">' . e(trim((string) ($med['route'] ?? '') . ' ' . (string) ($med['span'] ?? ''))) . '</p>';
            $html .= '<p class="help">' . care_t('line-help') . '</p>';
        }
        if ($off && (string) ($med['stop_reason'] ?? '') !== '') {
            $html .= '<p class="help">' . care_t('stop-reason') . ': ' . e((string) $med['stop_reason']) . '</p>';
        }
        if (!$off && $mine && cms_care_on('care_patient_med')) {
            $html .= '<form method="post" class="line"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="med-dose"><input type="hidden" name="id" value="' . (int) $med['id'] . '">';
            $html .= '<input name="dose" value="' . e((string) $med['dose']) . '" placeholder="' . care_t('dose-placeholder') . '"><button class="btn ghost" type="submit">' . care_t('save-dose') . '</button></form>';
            $html .= '<form method="post" class="line"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="med-plan"><input type="hidden" name="id" value="' . (int) $med['id'] . '">';
            $html .= '<input name="clock" value="' . e((string) ($med['clock'] ?? '')) . '" placeholder="' . care_t('clock-placeholder') . '"><input name="caution" value="' . e((string) ($med['caution'] ?? '')) . '" placeholder="' . care_t('caution-placeholder') . '"><button class="btn ghost" type="submit">' . care_t('save-clock') . '</button></form>';
            $html .= '<form method="post" class="line"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="med-stop"><input type="hidden" name="id" value="' . (int) $med['id'] . '">';
            $html .= '<input name="reason" placeholder="' . care_t('stop-placeholder') . '"><button class="btn ghost" type="submit">' . care_t('stopped-btn') . '</button></form>';
        }
        $html .= '</article>';
    }
    $medLog = cms_care_med_log($phone);
    if ($medLog) {
        $html .= '<div class="rail">';
        foreach ($medLog as $change) {
            $who = (string) $change['author'] === 'patient' ? cms_care_ui('you') : cms_care_ui('clinic');
            if ((string) $change['staff'] !== '') {
                $who .= ' · ' . (string) $change['staff'];
            }
            $title = (string) $change['name'] !== '' ? (string) $change['name'] : 'دارو';
            $html .= '<p><strong>' . e($title) . '</strong> · ' . e($who) . ' · ' . e(cms_when((string) $change['created_at'], true));
            if ((string) $change['kind'] === 'stop') {
                $html .= '<br><span class="help">' . care_t('stopped-line') . '</span> ' . e((string) $change['after_text'] !== '' ? (string) $change['after_text'] : '');
            } elseif ((string) $change['kind'] === 'clock') {
                $html .= '<br><span class="help">' . care_t('save-clock') . '</span> ' . e((string) $change['before_text'] !== '' ? (string) $change['before_text'] : cms_care_ui('empty'));
                $html .= ' · ' . e((string) $change['after_text'] !== '' ? (string) $change['after_text'] : cms_care_ui('empty'));
            } elseif ((string) $change['kind'] === 'caution') {
                $html .= '<br><span class="help">' . care_t('caution-placeholder') . '</span> ' . e((string) $change['before_text'] !== '' ? (string) $change['before_text'] : cms_care_ui('empty'));
                $html .= ' · ' . e((string) $change['after_text'] !== '' ? (string) $change['after_text'] : cms_care_ui('empty'));
            } else {
                $html .= '<br><span class="help">' . care_t('before') . '</span> ' . e((string) $change['before_text'] !== '' ? (string) $change['before_text'] : cms_care_ui('empty'));
                $html .= ' · <span class="help">' . care_t('after') . '</span> ' . e((string) $change['after_text'] !== '' ? (string) $change['after_text'] : cms_care_ui('empty'));
            }
            $html .= '</p>';
        }
        $html .= '</div>';
    }
    if (cms_care_on('care_patient_med')) {
        $html .= '<form method="post" class="stack"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="med-add">';
        $html .= '<input name="name" placeholder="' . care_t('add-med-placeholder') . '"><input name="dose" placeholder="' . care_t('dose-short') . '"><input name="clock" placeholder="' . care_t('clock-placeholder') . '">';
        $html .= '<button class="btn" type="submit">' . care_t('add-med') . '</button></form>';
    }
    $html .= '</section></div>';

    $html .= '<section class="card"><h2>' . care_h('care_h_remind', 'یادآوری شخصی') . '</h2>';
    if (cms_care_on('care_self_sms', false)) {
        $html .= '<p class="help">' . care_t('remind-self') . '</p>';
    } else {
        $html .= '<p class="help">این یادآوری همین صفحه می‌ماند. پیامک شخصی خاموش است.</p>';
    }
    if (!$reminders) {
        $html .= '<p class="help">یادآوری شخصی ندارید.</p>';
    }
    foreach ($reminders as $item) {
        $html .= '<form class="line" method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="remind-delete"><input type="hidden" name="id" value="' . (int) $item['id'] . '">';
        $sentMark = (string) $item['sent_at'] === '' ? '' : ((string) $item['sent_at'] === 'expired' ? ' · بازه گذشت' : ' · پیامک رفت');
        $html .= '<strong>' . e(cms_day_label((string) $item['day'])) . '</strong><span>' . e((string) $item['note']) . e($sentMark) . '</span>';
        $html .= '<button class="btn ghost" type="submit">' . care_t('remove') . '</button></form>';
    }
    if (cms_care_on('care_self_remind')) {
        $html .= '<form method="post" class="stack"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="remind-add">';
        $html .= '<input name="day" dir="ltr" placeholder="' . e(cms_fa_digits('1405-08-27')) . '"><input name="note" placeholder="جمله یادآوری">';
        $html .= '<button class="btn" type="submit">گذاشتن یادآوری</button></form>';
    }
    $html .= '</section>';

    $mine = [];
    $clinicVisits = [];
    foreach ($visits as $visit) {
        if ((string) $visit['source'] === 'site') {
            $mine[] = $visit;
        } else {
            $clinicVisits[] = $visit;
        }
    }
    $html .= '<section class="card"><h2>' . care_t('your-requests') . '</h2>';
    $asks = cms_care_ask_map($phone);
    $html .= care_visit_group($mine, $byVisit, $csrf, $asks, cms_care_ui('no-site'));
    $html .= '<h2>وقت‌های مطب</h2>';
    $html .= care_visit_group($clinicVisits, $byVisit, $csrf, $asks, 'مطب هنوز وقتی جدا ثبت نکرده است.');
    $html .= '</section>';

    $visibleFacts = array_values(array_filter(array_keys(cms_care_fields()), 'cms_care_field_on'));
    if ($visibleFacts) {
        $html .= '<section class="card"><h2>برگه پزشکی</h2>';
        foreach ($visibleFacts as $key) {
            $value = trim((string) ($facts[$key] ?? ''));
            $html .= '<p><strong>' . e(cms_care_field_label($key, cms_care_locale())) . '</strong><br>' . ($value !== '' ? nl2br(e($value)) : '<span class="help">' . care_t('empty') . '</span>') . '</p>';
        }
        $html .= '</section>';
        $factLog = cms_care_fact_log($phone, 15);
        $factShown = false;
        $factHtml = '';
        foreach ($factLog as $change) {
            $field = (string) $change['field'];
            if ($field !== 'stage' && !cms_care_field_on($field)) {
                continue;
            }
            $factShown = true;
            $label = $field === 'stage' ? cms_care_ui('stage-word') : cms_care_field_label($field, cms_care_locale());
            $who = (string) $change['author'] === 'patient' ? cms_care_ui('you') : cms_care_ui('clinic');
            $factHtml .= '<p><strong>' . e($label !== '' ? $label : $field) . '</strong> · ' . e($who) . ' · ' . e(cms_when((string) $change['created_at'], true)) . '<br>';
            $factHtml .= '<span class="help">' . care_t('before') . '</span> ' . nl2br(e(cms_care_fact_text($field, (string) $change['before_text'], cms_care_locale()))) . '<br>';
            $factHtml .= '<span class="help">' . care_t('after') . '</span> ' . nl2br(e(cms_care_fact_text($field, (string) $change['after_text'], cms_care_locale()))) . '</p>';
        }
        if ($factShown) {
            $html .= '<section class="card"><h2>' . care_t('sheet-changes') . '</h2><p class="help">' . care_t('sheet-help') . '</p>' . $factHtml . '</section>';
        }
        $html .= '<form class="card no-print" method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="facts">';
        $html .= '<p class="hp"><label>شرکت<input name="company" tabindex="-1" autocomplete="off"></label></p><h2>به‌روزرسانی برگه</h2>';
        foreach ($visibleFacts as $key) {
            $html .= '<label>' . e(cms_care_field_label($key, cms_care_locale())) . '<textarea name="' . e($key) . '">' . e((string) ($facts[$key] ?? '')) . '</textarea></label>';
        }
        $html .= '<label class="check"><input type="checkbox" name="consent" value="1" required><span>' . e(cms_care_consent(cms_care_locale())) . '</span></label>';
        $html .= '<button class="btn" type="submit">ذخیره برگه</button></form>';
    }

    $html .= '<section class="card talk"><h2>' . care_h('care_h_talk', 'پیام با مطب') . '</h2>';
    if (!$posts) {
        $html .= '<p class="help">' . care_t('no-message') . '</p>';
    }
    foreach ($posts as $post) {
        $fromPatient = (string) $post['author'] === 'patient';
        $html .= '<article class="bubble' . ($fromPatient ? ' mine' : '') . '"><strong>' . ($fromPatient ? care_t('you') : care_t('clinic')) . '</strong> · ' . e(cms_when((string) $post['created_at'], cms_care_locale()));
        $via = cms_chat_names()[(string) ($post['via'] ?? '')] ?? '';
        if ($via !== '') {
            $html .= ' · <span class="chip">' . e(care_t('via-' . (string) $post['via'])) . '</span>';
        }
        if ($fromPatient && (string) ($post['seen_at'] ?? '') !== '') {
            $html .= ' · <span class="help">' . care_t('seen-clinic') . '</span>';
        }
        if ((string) $post['body'] !== '') {
            $html .= '<p>' . nl2br(e((string) $post['body'])) . '</p>';
        }
        foreach ($post['files'] as $file) {
            $html .= '<span class="shot"><a href="/care/file.php?id=' . (int) $file['id'] . '"><img class="photo" alt="' . care_t('photo-alt') . '" src="/care/file.php?id=' . (int) $file['id'] . '"></a>';
            if ($fromPatient) {
                $html .= '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="file-take"><input type="hidden" name="id" value="' . (int) $file['id'] . '"><button class="btn ghost" type="submit">' . care_t('take-photo') . '</button></form>';
            }
            $html .= '</span>';
        }
        $html .= '</article>';
    }
    if (cms_care_open()) {
        $html .= '<form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="message">';
        $html .= '<p class="hp"><label>شرکت<input name="company" tabindex="-1" autocomplete="off"></label></p>';
        $html .= '<textarea name="body" placeholder="' . care_t('message-placeholder') . '"></textarea>';
        $html .= '<input type="file" name="photo[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif" multiple>';
        $html .= '<p class="help">' . e(str_replace('{n}', care_digits((string) cms_care_photo_max()), cms_care_ui('photo-count'))) . ' ' . care_t('photo-help') . '</p>';
        $html .= '<label class="check"><input type="checkbox" name="consent" value="1" required><span>' . e(cms_care_consent(cms_care_locale())) . '</span></label>';
        $html .= '<button class="btn" type="submit">' . care_t('send') . '</button></form>';
    } else {
        $html .= '<p class="help">' . care_t('closed-send') . '</p>';
    }
    $html .= '</section>';
    return $html;
}

function care_companion_board(string $phone, string $error): string
{
    $next = cms_care_next($phone);
    $visits = cms_care_visits($phone);
    $shownName = '';
    foreach ($visits as $visit) {
        if (cms_chars((string) $visit['name']) >= 2) {
            $shownName = (string) $visit['name'];
            break;
        }
    }
    $csrf = e(cms_care_csrf());
    cms_care_session();
    $mate = cms_care_companion_stamp($phone, (string) ($_SESSION['care_stamp'] ?? ''));
    $mateName = trim((string) ($mate['name'] ?? ''));
    $html = '<header class="top"><div><p class="eyebrow">' . e($mateName !== '' ? $mateName : cms_care_ui('companion-entry')) . '</p><h1>' . e(cms_care_title(cms_care_locale())) . '</h1></div>';
    $html .= '<form method="post" class="no-print"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="logout"><button class="btn ghost" type="submit">' . care_t('logout') . '</button></form></header>';
    $html .= care_langs();
    $html .= '<p class="help">' . care_t('companion-limit') . '</p>';
    if ($shownName !== '') {
        $html .= '<p class="help">' . e($shownName) . '</p>';
    }
    if ($error !== '') {
        $html .= '<p class="bad">' . e(cms_care_say($error)) . '</p>';
    }
    $html .= '<section class="hero ticket"><p class="eyebrow">' . care_h('care_h_next', 'نوبت بعدی') . '</p>';
    if (!$next) {
        $html .= '<p>' . care_t('no-next') . '</p>';
    } else {
        $status = (string) $next['status'];
        $locale = cms_care_locale();
        $html .= '<p class="when">' . e(care_day((string) $next['day'])) . '</p>';
        $html .= '<p class="clock" dir="ltr">' . e(care_digits((string) $next['time_slot'])) . '</p>';
        $placeLine = care_place($next);
        if ($placeLine !== '') {
            $html .= '<p>' . e($placeLine) . '</p>';
        }
        $html .= '<p><span class="pill">' . care_t('companion-entry') . '</span> <span class="pill">' . e(care_status($status)) . '</span></p>';
        $html .= '<p>' . e(cms_kind_label((string) $next['kind'], $locale)) . ' · ' . e(cms_service_label((string) $next['service'], $locale)) . '</p>';
        if (trim((string) ($next['prep'] ?? '')) !== '') {
            $html .= '<div class="prep"><strong>' . care_t('prep') . '</strong><br>' . nl2br(e((string) $next['prep'])) . '</div>';
        }
    }
    $html .= '</section><section class="card"><h2>' . care_t('times') . '</h2>';
    if (!$visits) {
        $html .= '<p class="help">' . care_t('no-visits') . '</p>';
    }
    foreach ($visits as $visit) {
        $locale = cms_care_locale();
        $html .= '<article class="visit"><strong>' . e(care_day((string) $visit['day'])) . '</strong> · <span dir="ltr">' . e(care_digits((string) $visit['time_slot'])) . '</span><br>';
        $placeLine = care_place($visit);
        if ($placeLine !== '') {
            $html .= e($placeLine) . '<br>';
        }
        $html .= e(cms_kind_label((string) $visit['kind'], $locale)) . ' · ' . e(care_status((string) $visit['status']));
        if (trim((string) ($visit['prep'] ?? '')) !== '') {
            $html .= '<br><span class="help">' . care_t('prep') . ': ' . e((string) $visit['prep']) . '</span>';
        }
        $html .= '</article>';
    }
    $html .= '</section>';
    return $html;
}

function care_visit_group(array $visits, array $byVisit, string $csrf, array $asks, string $empty): string
{
    if (!$visits) {
        return '<p class="help">' . e($empty) . '</p>';
    }
    $html = '';
    $commentsOn = cms_care_on('care_comments');
    $askOn = cms_care_on('care_patient_ask');
    $slots = cms_book_slots();
    foreach ($visits as $visit) {
        $id = (int) $visit['id'];
        $locale = cms_care_locale();
        $html .= '<article class="visit"><strong>' . e(care_day((string) $visit['day'])) . '</strong> · <span dir="ltr">' . e(care_digits((string) $visit['time_slot'])) . '</span><br>';
        $placeLine = care_place($visit);
        if ($placeLine !== '') {
            $html .= e($placeLine) . '<br>';
        }
        $html .= e(cms_kind_label((string) $visit['kind'], $locale)) . ' · ' . e(care_status((string) $visit['status']));
        if (trim((string) ($visit['prep'] ?? '')) !== '') {
            $html .= '<br><span class="help">' . care_t('prep') . ': ' . e((string) $visit['prep']) . '</span>';
        }
        $ask = $asks[$id] ?? null;
        if (is_array($ask)) {
            $html .= '<p class="help">' . care_t('ask-pending') . ' ' . e((string) $ask['kind'] === 'cancel' ? cms_care_ui('cancel-word') : cms_care_ui('move-word'));
            if ((string) $ask['day'] !== '') {
                $html .= ' ' . e(cms_care_ui('suggestion')) . ': ' . e(care_day((string) $ask['day'])) . ' · <span dir="ltr">' . e(care_digits((string) $ask['time_slot'])) . '</span>';
            }
            $html .= '</p><form method="post" class="no-print"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="visit-unask"><input type="hidden" name="id" value="' . (int) $ask['id'] . '"><button class="btn ghost" type="submit">' . care_t('withdraw') . '</button></form>';
        } elseif ($askOn && cms_care_ask_open($visit)) {
            $html .= '<form method="post" class="stack no-print"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="visit-ask"><input type="hidden" name="visit" value="' . $id . '"><input type="hidden" name="kind" value="cancel">';
            $html .= '<input name="note" placeholder="' . care_t('reason-placeholder') . '"><button class="btn ghost" type="submit">' . care_t('ask-cancel') . '</button></form>';
            $html .= '<form method="post" class="stack no-print"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="visit-ask"><input type="hidden" name="visit" value="' . $id . '"><input type="hidden" name="kind" value="move">';
            $html .= '<input name="day" dir="ltr" placeholder="' . e(care_digits('1405-08-27')) . '"><select name="time">';
            foreach ($slots as $slot) {
                $html .= '<option value="' . e($slot) . '">' . e(care_digits($slot)) . '</option>';
            }
            $html .= '</select><button class="btn ghost" type="submit">' . care_t('ask-move') . '</button></form>';
            $html .= '<p class="help">' . care_t('ask-help') . '</p>';
        }
        foreach ($byVisit[$id] ?? [] as $comment) {
            $mineComment = (string) $comment['author'] === 'patient';
            $html .= '<p class="bubble' . ($mineComment ? ' mine' : '') . '"><strong>' . ($mineComment ? care_t('you') : care_t('clinic')) . '</strong><br>' . nl2br(e((string) $comment['body']));
            if ($mineComment) {
                $html .= '<form method="post" class="tiny"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="comment-drop"><input type="hidden" name="id" value="' . (int) $comment['id'] . '"><button class="btn ghost" type="submit">' . care_t('drop-comment') . '</button></form>';
            }
            $html .= '</p>';
        }
        if ($commentsOn) {
            $html .= '<form method="post" class="stack"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="comment"><input type="hidden" name="visit" value="' . $id . '">';
            $html .= '<input name="body" placeholder="' . care_t('comment-placeholder') . '"><button class="btn ghost" type="submit">' . care_t('save-comment') . '</button></form>';
        }
        $html .= '</article>';
    }
    return $html;
}
