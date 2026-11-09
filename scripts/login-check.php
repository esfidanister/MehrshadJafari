<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jafari-login-' . bin2hex(random_bytes(4));
if (!mkdir($dir) && !is_dir($dir)) {
    fwrite(STDERR, "temp\n");
    exit(1);
}
$file = $dir . DIRECTORY_SEPARATOR . 'check.sqlite';
touch($file);
putenv('CMS_TEST_DB=' . $file);
ini_set('session.save_path', $dir);

require dirname(__DIR__) . '/public/cms/bootstrap.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage() . ' @ ' . basename($error->getFile()) . ':' . $error->getLine() . "\n");
    exit(1);
});

$opened = cms_db_path();
if ($opened !== realpath($file) || str_contains(strtolower($opened), 'clinic.sqlite')) {
    fwrite(STDERR, "refused\n");
    exit(1);
}

$failed = 0;
$check = static function (bool $ok, string $label) use (&$failed): void {
    if (!$failed && !$ok) {
        fwrite(STDERR, $label . "\n");
        $failed = 1;
    }
};

$now = cms_now();
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran')))->format('Y-m-d');
$pdo = cms_db();
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'TEST2345', 'confirmed', 'تست', '09121110001', '', 'exam', $today, '16:00', '', '', 'fa', '', $now, $now,
]);
$check(cms_care_login('09121110001', 'WRONG234') === false, 'wrong-code');
cms_set_setting('care_bootstrap_days', '14');
$check(cms_care_login('09121110001', 'TEST2345') === false, 'visit-closed');
$check(cms_care_take_fresh_code() === '' && cms_care_access('09121110001') === null, 'no-bootstrap');
$issuedPortal = cms_care_issue_code('09121110001');
$portal = $issuedPortal['code'];
$check(preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $portal) === 1 && $portal !== 'TEST2345', 'portal-code');
$check(cms_care_login('09121110001', 'TEST2345') === false, 'visit-still');
$check(cms_care_login('09121110001', $portal) === true && cms_care_role() === 'patient', 'portal-login');
$check(cms_care_issue_companion('09121110001', 'ا')['ok'] === false, 'mate-short');
$issued = cms_care_issue_companion('09121110001', 'خواهر');
$storedMate = cms_care_companion('09121110001');
$check($issued['ok'] === true && $issued['name'] === 'خواهر' && (string) ($storedMate['name'] ?? '') === 'خواهر', 'mate-name');
cms_care_logout();
$check(cms_care_login('09121110001', $issued['code']) === true && cms_care_role() === 'companion', 'companion');
$check(cms_care_logged_phone() === '09121110001', 'companion-phone');
cms_care_logout();
$pdo->prepare('UPDATE care_access SET expires_at = ? WHERE phone = ?')->execute(['2000-01-01', '09121110001']);
$check(cms_care_login('09121110001', $portal) === false, 'expired');
$pdo->prepare('UPDATE care_access SET expires_at = ? WHERE phone = ?')->execute(['', '09121110001']);
$check(cms_care_login('09121110001', $portal) === true && cms_care_role() === 'patient', 'portal-again');
cms_care_logout();

$hash = password_hash('correct-horse-1', PASSWORD_DEFAULT);
$pdo->prepare('INSERT INTO admins (username, password_hash, created_at, role, display_name, active) VALUES (?,?,?,?,?,1)')->execute(['owner1', $hash, $now, 'owner', 'مالک']);
$pdo->prepare('INSERT INTO admins (username, password_hash, created_at, role, display_name, active) VALUES (?,?,?,?,?,0)')->execute(['quiet1', $hash, $now, 'editor', 'ویراستار']);
$guard = hash('sha256', 'login-check');
$ok = cms_admin_attempt('owner1', 'correct-horse-1', $guard);
$check($ok['ok'] === true && $ok['id'] > 0, 'desk-ok');
$bad = cms_admin_attempt('owner1', 'nope-nope-no', $guard);
$check($bad['ok'] === false && $bad['error'] === 'denied', 'desk-bad');
$idle = cms_admin_attempt('quiet1', 'correct-horse-1', $guard . '-idle');
$check($idle['ok'] === false && $idle['error'] === 'denied', 'desk-inactive');
$lockHash = hash('sha256', 'login-lock');
$locked = ['locked' => false];
for ($i = 0; $i < 8; $i++) {
    $locked = cms_admin_attempt('owner1', 'nope-nope-no', $lockHash);
}
$check(($locked['error'] ?? '') === 'locked', 'desk-lock');
$closed = cms_admin_attempt('owner1', 'correct-horse-1', $lockHash);
$check($closed['error'] === 'closed', 'desk-closed');

$pdo->prepare('INSERT INTO care_facts (phone, problem, medicines, allergy, extra, stage_key, updated_at) VALUES (?,?,?,?,?,?,?)')->execute(['09121110001', '', '', 'حساسیت الف', '', '', $now]);
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'MOVE2345', 'confirmed', 'دوم', '09121110002', '', 'exam', $today, '16:30', '', '', 'fa', '', $now, $now,
]);
$pdo->prepare('INSERT INTO care_facts (phone, problem, medicines, allergy, extra, stage_key, updated_at) VALUES (?,?,?,?,?,?,?)')->execute(['09121110002', '', '', 'حساسیت ب', '', '', $now]);
$moved = cms_patient_move('09121110001', '09121110002', 'تست');
$left = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE phone = ?');
$left->execute(['09121110001']);
$right = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE phone = ?');
$right->execute(['09121110002']);
$allergy = $pdo->prepare('SELECT allergy FROM care_facts WHERE phone = ?');
$allergy->execute(['09121110002']);
$text = (string) $allergy->fetchColumn();
$check($moved['ok'] === true && $moved['merged'] === true, 'merge-ok');
$check((int) $left->fetchColumn() === 0 && (int) $right->fetchColumn() === 2, 'merge-visits');
$check(str_contains($text, 'حساسیت الف') && str_contains($text, 'حساسیت ب'), 'merge-facts');
$check(cms_care_login('09121110001', $portal) === false, 'old-phone');
$check(cms_care_login('09121110002', $portal) === true && cms_care_role() === 'patient', 'new-phone');
cms_care_logout();

$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'SHOW2345', 'noshow', 'سوم', '09121110003', '', 'exam', $today, '17:00', '', '', 'fa', '', $now, $now,
]);
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'DONE2345', 'done', 'چهارم', '09121110004', '', 'exam', $today, '17:30', '', '', 'fa', '', $now, $now,
]);
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'DROP2345', 'cancelled', 'پنجم', '09121110005', '', 'exam', $today, '18:00', '', '', 'fa', '', $now, $now,
]);
$point = cms_g2j((int) substr($today, 0, 4), (int) substr($today, 5, 2), (int) substr($today, 8, 2));
$report = cms_month_report((int) $point['jy'], (int) $point['jm'], $today);
$check($report['ok'] === true && $report['noshow'] === 1 && $report['seen'] === 1 && $report['miss'] === 50, 'month-miss');
$check($report['cancelled'] === 1 && $report['held'] >= 3, 'month-fill');

do {
    $nine = '';
    for ($i = 0; $i < 9; $i++) {
        $nine .= (string) random_int(0, 9);
    }
} while (preg_match('/^(\d)\1{8}$/', $nine));
$sum = 0;
for ($i = 0; $i < 9; $i++) {
    $sum += (int) $nine[$i] * (10 - $i);
}
$rest = $sum % 11;
$nid = $nine . (string) ($rest < 2 ? $rest : 11 - $rest);
$broken = substr($nid, 0, 9) . ($nid[9] === '0' ? '1' : '0');
$birth = cms_patient_birth('1370-01-15');
$check(cms_national_id($nid) === $nid, 'nid-ok');
$check(cms_national_id($broken) === '', 'nid-bad');
$check($birth !== '' && cms_shamsi_iso($birth) === '1370-01-15', 'birth-ok');
$check(cms_patient_birth('1500-01-01') === '', 'birth-future');
$saved = cms_patient_save('09121110002', 'دوم', '', '1370-01-15', $nid);
$check($saved['ok'] === true, 'identity-save');
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'DUP23456', 'confirmed', 'ششم', '09121110006', '', 'exam', $today, '18:30', '', '', 'fa', '', $now, $now,
]);
$dup = cms_patient_save('09121110006', 'ششم', '', '', $nid);
$check($dup['ok'] === false, 'nid-unique');
$makeId = static function () use ($nid): string {
    do {
        $nine = '';
        for ($i = 0; $i < 9; $i++) {
            $nine .= (string) random_int(0, 9);
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $nine[$i] * (10 - $i);
        }
        $rest = $sum % 11;
        $made = $nine . (string) ($rest < 2 ? $rest : 11 - $rest);
    } while ($made === $nid || preg_match('/^(\d)\1{9}$/', $made));
    return $made;
};
$nid2 = $makeId();
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'TWO23456', 'confirmed', 'هفتم', '09121110007', '', 'exam', $today, '19:00', 'متن', '', 'fa', '', $now, $now,
]);
$check(cms_patient_save('09121110006', 'ششم', '', '1360-02-02', $nid2)['ok'] === true, 'identity-other');
do {
    $nid3 = $makeId();
} while ($nid3 === $nid2);
$check(cms_patient_save('09121110007', 'هفتم', '', '1361-03-03', $nid3)['ok'] === true, 'identity-seven');
$clash = cms_patient_move('09121110006', '09121110007', 'تست');
$check($clash['ok'] === false, 'merge-id');
$still = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE phone = ?');
$still->execute(['09121110006']);
$check((int) $still->fetchColumn() === 1, 'merge-kept');
$photo = bin2hex(random_bytes(16)) . '.jpg';
file_put_contents(cms_care_dir() . DIRECTORY_SEPARATOR . $photo, 'photo');
$pdo->prepare('INSERT INTO care_files (post_id, phone, stored, mime, bytes, created_at) VALUES (?,?,?,?,?,?)')->execute([0, '09121110002', $photo, 'image/jpeg', 5, $now]);
$pdo->prepare('UPDATE visits SET message = ? WHERE phone = ?')->execute(['متن پرونده', '09121110002']);
$erased = cms_patient_erase('09121110002');
$gone = $pdo->prepare('SELECT COUNT(*) FROM care_facts WHERE phone = ?');
$gone->execute(['09121110002']);
$name = $pdo->prepare('SELECT name, message FROM visits WHERE phone = ? LIMIT 1');
$name->execute(['09121110002']);
$visit = $name->fetch();
$check($erased['ok'] === true && !is_file(cms_care_dir() . DIRECTORY_SEPARATOR . $photo), 'erase-file');
$check((int) $gone->fetchColumn() === 0 && (string) ($visit['name'] ?? '') === 'حذف‌شده' && (string) ($visit['message'] ?? '') === '', 'erase-chart');
$check(cms_care_login('09121110002', $portal) === false && cms_care_login('09121110002', 'TEST2345') === false, 'erase-login');

$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'MED23456', 'confirmed', 'هشتم', '09121110008', '', 'exam', $today, '19:30', '', '', 'fa', '', $now, $now,
]);
$check(cms_care_add_med('09121110008', 'clinic', 'آموکسی', 'هر ۸ ساعت')['ok'] === true, 'med-add');
$medId = (int) $pdo->query('SELECT last_insert_rowid()')->fetchColumn();
$sameDose = cms_care_med_dose('09121110008', $medId, 'clinic', 'هر ۸ ساعت', 'دکتر');
$doseCount = $pdo->prepare('SELECT COUNT(*) FROM care_med_log WHERE phone = ?');
$doseCount->execute(['09121110008']);
$check($sameDose['changed'] === false && (int) $doseCount->fetchColumn() === 0, 'dose-same');
$changedDose = cms_care_med_dose('09121110008', $medId, 'clinic', 'هر ۱۲ ساعت', 'دکتر');
$doseRow = $pdo->prepare('SELECT kind, before_text, after_text, staff FROM care_med_log WHERE phone = ? ORDER BY id DESC LIMIT 1');
$doseRow->execute(['09121110008']);
$dose = $doseRow->fetch();
$check($changedDose['changed'] === true && (string) ($dose['kind'] ?? '') === 'dose' && (string) ($dose['before_text'] ?? '') === 'هر ۸ ساعت' && (string) ($dose['after_text'] ?? '') === 'هر ۱۲ ساعت' && (string) ($dose['staff'] ?? '') === 'دکتر', 'dose-log');
$denied = cms_care_med_dose('09121110008', $medId, 'patient', 'هر ۶ ساعت');
$keptDose = $pdo->prepare('SELECT dose FROM care_meds WHERE id = ?');
$keptDose->execute([$medId]);
$check($denied['ok'] === false && (string) $keptDose->fetchColumn() === 'هر ۱۲ ساعت', 'dose-clinic');
$check(cms_care_med_stop('09121110008', $medId, 'clinic', 'دکتر') === true, 'med-stop');
$stopRow = $pdo->prepare('SELECT kind, before_text FROM care_med_log WHERE med_id = ? AND kind = ?');
$stopRow->execute([$medId, 'stop']);
$stopped = $stopRow->fetch();
$check((string) ($stopped['kind'] ?? '') === 'stop' && (string) ($stopped['before_text'] ?? '') === 'هر ۱۲ ساعت' && cms_care_med_dose('09121110008', $medId, 'clinic', 'هر ۶ ساعت', 'دکتر')['ok'] === false, 'dose-stopped');
$check(cms_care_issue_companion('09121110008', 'پدر')['ok'] === true, 'mate-move-ready');
$check(cms_patient_move('09121110008', '09121110009', 'تست')['ok'] === true, 'dose-move');
$movedMate = cms_care_companion('09121110009');
$check((string) ($movedMate['name'] ?? '') === 'پدر' && cms_care_companion('09121110008') === null, 'mate-moved');
$movedLog = $pdo->prepare('SELECT COUNT(*) FROM care_med_log WHERE phone = ?');
$movedLog->execute(['09121110009']);
$check((int) $movedLog->fetchColumn() === 2, 'dose-moved');
$check(cms_patient_erase('09121110009')['ok'] === true, 'dose-erase');
$erasedLog = $pdo->prepare('SELECT COUNT(*) FROM care_med_log WHERE phone = ?');
$erasedLog->execute(['09121110009']);
$check((int) $erasedLog->fetchColumn() === 0 && cms_care_companion('09121110009') === null, 'dose-gone');

$thursday = new DateTimeImmutable('today', new DateTimeZone('Asia/Tehran'));
while ($thursday->format('w') !== '4') {
    $thursday = $thursday->modify('+1 day');
}
$placeDay = $thursday->format('Y-m-d');
$check(cms_place_save([
    'key' => 'hospital-a',
    'label_fa' => 'بخش آزمایش',
    'kind' => 'hospital',
    'address' => '',
    'weekdays' => ['4'],
    'start' => '16:00',
    'end' => '18:00',
    'active' => 1,
])['ok'] === true, 'place-save');
$hospital = cms_place_row('hospital-a');
$check(is_array($hospital) && cms_place_offers($hospital, $placeDay, '16:00') === true && cms_place_offers($hospital, $placeDay, '19:00') === false, 'place-hours');
$booked = cms_visit_create([
    'name' => 'بیمار مکان',
    'phone' => '09121110010',
    'service' => 'unsure',
    'kind' => 'exam',
    'place' => 'hospital-a',
    'day' => $placeDay,
    'time' => '16:00',
    'source' => 'desk',
    'status' => 'confirmed',
    'booked_by' => 'منشی الف',
]);
$check($booked['ok'] === true, 'place-book');
$check(cms_place_save([
    'key' => 'clinic-a',
    'label_fa' => 'مطب آزمایش',
    'kind' => 'clinic',
    'weekdays' => ['4'],
    'start' => '16:00',
    'end' => '18:00',
    'active' => 1,
])['ok'] === true, 'place-clinic');
$full = cms_visit_create([
    'name' => 'بیمار دوم',
    'phone' => '09121110011',
    'service' => 'unsure',
    'kind' => 'consult',
    'place' => 'clinic-a',
    'day' => $placeDay,
    'time' => '16:00',
    'source' => 'desk',
    'status' => 'confirmed',
    'booked_by' => 'منشی ب',
]);
$sentence = cms_hold_sentence((array) ($full['holders'] ?? []), $placeDay);
$check($full['ok'] === false && $full['error'] === 'full' && str_contains($sentence, 'بیمار مکان') && str_contains($sentence, 'بخش آزمایش') && str_contains($sentence, 'منشی الف'), 'place-who');
$pdo->prepare('INSERT INTO admins (username, password_hash, created_at, role, display_name, active) VALUES (?,?,?,?,?,1)')->execute(['staff1', $hash, $now, 'editor', 'منشی بخش']);
$check(cms_place_save([
    'key' => 'clinic-a',
    'label_fa' => 'مطب آزمایش',
    'kind' => 'clinic',
    'weekdays' => ['4'],
    'start' => '16:00',
    'end' => '18:00',
    'active' => 1,
    'secretary' => 'staff1',
])['ok'] === true, 'place-secretary');
$wrongDesk = cms_visit_create([
    'name' => 'بیمار سوم',
    'phone' => '09121110012',
    'service' => 'unsure',
    'kind' => 'exam',
    'place' => 'hospital-a',
    'staff_place' => 'clinic-a',
    'day' => $placeDay,
    'time' => '17:00',
    'source' => 'desk',
    'status' => 'new',
]);
$check($wrongDesk['ok'] === false && $wrongDesk['error'] === 'place', 'place-staff');
$check(cms_place_save([
    'key' => 'hospital-a',
    'label_fa' => 'بخش آزمایش',
    'kind' => 'hospital',
    'weekdays' => ['4'],
    'start' => '16:00',
    'end' => '18:00',
    'active' => 1,
    'secretary' => 'staff1',
])['ok'] === true, 'staff-grant');
$staffPlaces = (string) $pdo->query("SELECT place_key FROM admins WHERE username = 'staff1'")->fetchColumn();
$second = cms_visit_create([
    'name' => 'بیمار چهارم',
    'phone' => '09121110014',
    'service' => 'unsure',
    'kind' => 'exam',
    'place' => 'hospital-a',
    'staff_place' => $staffPlaces,
    'day' => $placeDay,
    'time' => '17:00',
    'source' => 'desk',
    'status' => 'confirmed',
    'booked_by' => 'منشی بخش',
]);
$check($second['ok'] === true && in_array('clinic-a', cms_staff_places($staffPlaces), true) && in_array('hospital-a', cms_staff_places($staffPlaces), true), 'staff-two');
$pdo->prepare('INSERT INTO admins (username, password_hash, created_at, role, display_name, active) VALUES (?,?,?,?,?,1)')->execute(['staff2', $hash, $now, 'editor', 'منشی دوم']);
$check(cms_place_save([
    'key' => 'other-a',
    'label_fa' => 'جای سوم',
    'kind' => 'other',
    'weekdays' => ['4'],
    'start' => '16:00',
    'end' => '18:00',
    'active' => 1,
    'secretary' => 'staff1, staff2',
])['ok'] === true, 'staff-pair');
$afterPair = cms_staff_places((string) $pdo->query("SELECT place_key FROM admins WHERE username = 'staff1'")->fetchColumn());
$secondDesk = cms_staff_places((string) $pdo->query("SELECT place_key FROM admins WHERE username = 'staff2'")->fetchColumn());
$check(in_array('other-a', $afterPair, true) && $secondDesk === ['other-a'], 'staff-pair-list');
$check(cms_place_save([
    'key' => 'hospital-a',
    'label_fa' => 'بخش آزمایش',
    'kind' => 'hospital',
    'weekdays' => ['4'],
    'start' => '16:00',
    'end' => '18:00',
    'active' => 1,
    'release' => 'staff1',
])['ok'] === true, 'staff-release');
$left = cms_staff_places((string) $pdo->query("SELECT place_key FROM admins WHERE username = 'staff1'")->fetchColumn());
$blocked = cms_visit_create([
    'name' => 'بیمار پنجم',
    'phone' => '09121110015',
    'service' => 'unsure',
    'kind' => 'consult',
    'place' => 'hospital-a',
    'staff_place' => implode(',', $left),
    'day' => $placeDay,
    'time' => '17:30',
    'source' => 'desk',
    'status' => 'new',
]);
$check(!in_array('hospital-a', $left, true) && in_array('clinic-a', $left, true) && $blocked['ok'] === false && $blocked['error'] === 'place', 'staff-kept');
$filled = cms_tpl_fill('نوبت در {place}', [
    'place_key' => 'hospital-a',
    'day' => $placeDay,
    'time_slot' => '16:00',
    'kind' => 'exam',
    'name' => 'الف',
    'code' => 'ABCD2345',
    'phone' => '',
    'service' => 'unsure',
    'note' => '',
], 'fa');
cms_set_setting('notify_patient_created', '0');
$check(str_contains($filled, 'بخش آزمایش') && cms_notify_on('patient', 'created') === false, 'sms-switch');
cms_set_setting('notify_patient_created', '1');
cms_set_setting('tpl_care_self', 'یادتان: {day} / {note}');
$selfText = cms_care_self_text(['day' => $placeDay, 'note' => 'عکس']);
$check(str_contains($selfText, 'یادتان') && str_contains($selfText, 'عکس'), 'sms-self');
$check(cms_care_add_med('09121110010', 'clinic', 'مسکن', 'یک عدد', 'صبح', '')['ok'] === true, 'clock-add');
$clockId = (int) $pdo->query('SELECT last_insert_rowid()')->fetchColumn();
$planned = cms_care_med_plan('09121110010', $clockId, 'clinic', 'شب', 'با غذا', 'دکتر');
$clockLog = $pdo->prepare('SELECT kind, before_text, after_text FROM care_med_log WHERE med_id = ? AND kind = ?');
$clockLog->execute([$clockId, 'clock']);
$clockRow = $clockLog->fetch();
$check($planned['changed'] === true && (string) ($clockRow['before_text'] ?? '') === 'صبح' && (string) ($clockRow['after_text'] ?? '') === 'شب', 'clock-log');
$check(cms_care_med_plan('09121110010', $clockId, 'clinic', 'شب', 'با غذا', 'دکتر')['changed'] === false, 'clock-same');
$check(cms_care_med_stop('09121110010', $clockId, 'clinic', 'دکتر', 'دوره تمام شد') === true, 'clock-stop');
$reason = $pdo->prepare('SELECT stop_reason FROM care_meds WHERE id = ?');
$reason->execute([$clockId]);
$check((string) $reason->fetchColumn() === 'دوره تمام شد', 'clock-reason');
cms_set_setting('sms_on', '1');
cms_set_setting('notify_patient_created', '1');
cms_set_setting('notify_doctor_created', '0');
$check(cms_sms_stop('09121110010', true) === true && cms_sms_stopped('09121110010') === true, 'sms-stop');
cms_notify_visit([
    'id' => 0,
    'phone' => '09121110010',
    'name' => 'بیمار مکان',
    'locale' => 'fa',
    'day' => $placeDay,
    'time_slot' => '16:00',
    'kind' => 'exam',
    'code' => 'ABCD2345',
    'service' => 'unsure',
    'note' => '',
    'place_key' => 'hospital-a',
], 'created');
$quietLog = $pdo->query("SELECT status, error FROM messages_out WHERE audience = 'patient' AND event = 'created' ORDER BY id DESC LIMIT 1")->fetch();
$doctorSent = (int) $pdo->query("SELECT COUNT(*) FROM messages_out WHERE audience = 'doctor' AND event = 'created'")->fetchColumn();
$check((string) ($quietLog['status'] ?? '') === 'skipped' && str_contains((string) ($quietLog['error'] ?? ''), 'خارج') && $doctorSent === 0, 'sms-skip');
$movedQuiet = cms_patient_move('09121110010', '09121110013', 'میز');
$check($movedQuiet['ok'] === true && cms_sms_stopped('09121110013') === true && cms_sms_stopped('09121110010') === false, 'sms-move');
$erasedQuiet = cms_patient_erase('09121110013');
$check($erasedQuiet['ok'] === true && cms_sms_stopped('09121110013') === true, 'sms-keep');
cms_care_locale_set('en');
$englishReminder = cms_care_reminder_line(['status' => 'confirmed', 'day' => $placeDay, 'reminded_at' => ''], 'en');
$check(cms_care_ui('logout') === 'Sign out' && cms_status_label('confirmed', 'en') === 'Confirmed' && cms_care_say('با این شماره و کد، پرونده‌ای باز نشد.') === 'That number and code did not open a chart.' && str_contains($englishReminder, 'text'), 'lang-en');
cms_care_locale_set('fa');
$check(cms_care_ui('logout') === 'خروج' && cms_status_label('confirmed', 'zz') === 'هماهنگ شد' && cms_care_stage_label('missing', 'en') === '', 'lang-fa');
$weekDays = cms_week_days('2026-11-09');
$check($weekDays[0] === '2026-11-07' && $weekDays[5] === '2026-11-12' && $weekDays[6] === '2026-11-13' && cms_week_start('2026-11-07') === '2026-11-07' && in_array($placeDay, cms_week_days($placeDay), true), 'week-board');
cms_set_setting('care_h_next', 'ویزیت من');
cms_set_setting('care_h_next_en', 'My visit');
$check(cms_care_heading('care_h_next', 'نوبت بعدی', 'fa') === 'ویزیت من' && cms_care_heading('care_h_next', 'نوبت بعدی', 'en') === 'My visit', 'head-set');
cms_setting_forget('care_h_next_en');
$check(cms_care_heading('care_h_next', 'نوبت بعدی', 'en') === 'Next visit' && cms_care_heading('care_h_next', 'نوبت بعدی', 'ar') === 'الموعد التالي' && cms_care_heading('care_h_next', 'نوبت بعدی', 'zh') === '下次就诊' && cms_care_field_label('allergy', 'en') === 'Allergy', 'head-base');
$queued = cms_visit_create([
    'name' => 'صف آزمایش',
    'phone' => '09121110016',
    'service' => 'unsure',
    'kind' => 'exam',
    'place' => 'hospital-a',
    'day' => $placeDay,
    'time' => '17:30',
    'source' => 'desk',
    'status' => 'new',
    'booked_by' => 'میز',
]);
$check($queued['ok'] === true, 'queue-add');
$pdo->exec("UPDATE visits SET note = 'راز بالینی', message = 'راز پیام' WHERE id > 0");
$sheet = cms_book_sheet();
$savedCopy = cms_backup_database();
$copyName = basename((string) ($savedCopy['file'] ?? ''));
$inside = str_starts_with(strtolower(str_replace('\\', '/', (string) ($savedCopy['file'] ?? ''))), strtolower(str_replace('\\', '/', dirname(cms_db_path()) . '/backups/')));
$openedCopy = false;
if ($savedCopy['ok'] === true && is_file((string) $savedCopy['file'])) {
    $copyDb = new PDO('sqlite:' . $savedCopy['file'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $openedCopy = (int) $copyDb->query("SELECT COUNT(*) FROM visits")->fetchColumn() > 0;
    $copyDb = null;
}
$waiting = cms_wait_rows(20);
$waitingNames = array_map(static fn (array $row): string => (string) $row['name'], $waiting);
$check(!str_contains($sheet, 'راز') && str_contains($sheet, 'صف آزمایش'), 'sheet-safe');
$check($savedCopy['ok'] === true && $inside && $openedCopy && preg_match('/^clinic-\d{8}-\d{6}(?:-[a-f0-9]{4})?\.sqlite$/', $copyName), 'backup-file');
$check(in_array('صف آزمایش', $waitingNames, true) && !in_array('حذف‌شده', $waitingNames, true), 'queue-list');
foreach (cms_backup_list() as $copy) {
    $path = dirname(cms_db_path()) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $copy['name'];
    if (is_file($path)) {
        unlink($path);
    }
}
$sister = cms_care_issue_companion('09121110001', 'خواهر');
$fatherCode = cms_care_issue_companion('09121110001', 'پدر');
$names = array_map(static fn (array $row): string => (string) $row['name'], cms_care_companions('09121110001'));
$check($sister['ok'] === true && $fatherCode['ok'] === true && $names === ['خواهر', 'پدر'], 'mates-two');
$check(cms_care_login('09121110001', $sister['code']) === true && cms_care_role() === 'companion', 'mate-sister');
cms_care_logout();
$check(cms_care_login('09121110001', $fatherCode['code']) === true, 'mate-father');
cms_care_logout();
$rotated = cms_care_issue_companion('09121110001', 'خواهر');
$check($rotated['code'] !== $sister['code'] && cms_care_login('09121110001', $sister['code']) === false && cms_care_login('09121110001', $fatherCode['code']) === true, 'mate-rotate');
cms_care_logout();
$fatherId = 0;
foreach (cms_care_companions('09121110001') as $mateRow) {
    if ((string) $mateRow['name'] === 'پدر') {
        $fatherId = (int) $mateRow['id'];
    }
}
cms_care_revoke_companion('09121110001', $fatherId);
$check($fatherId > 0 && cms_care_login('09121110001', $fatherCode['code']) === false && cms_care_login('09121110001', $rotated['code']) === true && count(cms_care_companions('09121110001')) === 1, 'mate-one-closed');
cms_care_logout();
$check(cms_care_issue_companion('09121110001', 'مادر')['ok'] === true && cms_care_issue_companion('09121110001', 'همسر')['ok'] === true && cms_care_issue_companion('09121110001', 'فرزند')['ok'] === true && cms_care_issue_companion('09121110001', 'عمو')['ok'] === false, 'mate-cap');
$widenFile = dirname(cms_db_path()) . DIRECTORY_SEPARATOR . 'widen.sqlite';
$widen = new PDO('sqlite:' . $widenFile);
$widen->exec("CREATE TABLE care_companions (phone TEXT PRIMARY KEY, code_hash TEXT NOT NULL, stamp TEXT NOT NULL, expires_at TEXT NOT NULL DEFAULT '', name TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL)");
$widen->exec("INSERT INTO care_companions (phone, code_hash, stamp, expires_at, name, updated_at) VALUES ('09120000000','abc','stamp1','','قدیمی','2026-01-01T00:00:00Z')");
cms_companions_widen($widen);
$widened = $widen->query('SELECT id, name FROM care_companions')->fetch();
$check(is_array($widened) && (string) $widened['name'] === 'قدیمی' && (int) $widened['id'] > 0, 'mate-widen');
$widen = null;
if (is_file($widenFile)) {
    unlink($widenFile);
}
$check(cms_pdf_shape('سلام')[0] === 0xFEB3, 'shape');
cms_care_save_facts('09121110001', ['allergy' => 'پنی‌سیلین']);
$pdf = cms_care_sheet_pdf('09121110001', 'fa');
$check(str_starts_with($pdf, '%PDF') && str_contains($pdf, '%%EOF') && str_contains($pdf, '<FB58>') && !str_contains($pdf, 'پنی') && cms_color_ratio('182832', 'F7F8F6') >= 4.5 && cms_color_ratio('5D6B73', 'FFFFFF') >= 4.5 && cms_color_ratio('FFFFFF', '397F91') >= 4.5 && cms_color_ratio('182832', '9CCDD0') >= 4.5, 'pdf-sheet');
cms_care_consent_keep('09121110001', 'sheet');
$keptConsent = cms_care_consent_last('09121110001', 'sheet');
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'NOTE1234', 'confirmed', 'تست', '09121110001', '', 'exam', $today, '18:30', '', '', 'fa', '', $now, $now,
]);
$visitId = (int) $pdo->query("SELECT id FROM visits WHERE phone = '09121110001' ORDER BY id DESC LIMIT 1")->fetchColumn();
$patientNote = cms_care_add_comment('09121110001', $visitId, 'patient', 'نظر خودم');
$clinicNote = cms_care_add_comment('09121110001', $visitId, 'clinic', 'نظر مطب');
$clinicId = (int) $pdo->query("SELECT id FROM care_comments WHERE phone = '09121110001' AND author = 'clinic' ORDER BY id DESC LIMIT 1")->fetchColumn();
$patientId = (int) $pdo->query("SELECT id FROM care_comments WHERE phone = '09121110001' AND author = 'patient' ORDER BY id DESC LIMIT 1")->fetchColumn();
$storedName = '0123456789abcdef0123456789abcdef.jpg';
file_put_contents(cms_care_dir() . DIRECTORY_SEPARATOR . $storedName, 'photo');
$pdo->prepare("INSERT INTO care_posts (phone, author, body, hidden, seen_at, staff, created_at) VALUES ('09121110001','patient','',0,'','',?)")->execute([$now]);
$postId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO care_files (post_id, phone, stored, mime, bytes, created_at) VALUES (?,?,?,?,1,?)')->execute([$postId, '09121110001', $storedName, 'image/jpeg', $now]);
$fileId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO care_posts (phone, author, body, hidden, seen_at, staff, created_at) VALUES ('09121110001','clinic','پاسخ',0,'','',?)")->execute([$now]);
cms_care_mark_read('09121110001');
$seenClinic = (string) $pdo->query("SELECT seen_at FROM care_posts WHERE phone = '09121110001' AND author = 'clinic' ORDER BY id DESC LIMIT 1")->fetchColumn();
$monthSheet = cms_report_sheet(cms_month_report(1405, 8, '2026-11-09'), 1405, 8);
$check(is_array($keptConsent) && hash_equals((string) $keptConsent['sentence_sha'], hash('sha256', cms_care_consent('fa'))), 'consent-hash');
$check($patientNote['ok'] === true && $clinicNote['ok'] === true && cms_care_comment_drop('09121110001', $clinicId) === false && cms_care_comment_drop('09121110001', $patientId) === true, 'comment-own');
$check(cms_care_file_take('09121110001', $fileId) === true && !is_file(cms_care_dir() . DIRECTORY_SEPARATOR . $storedName), 'photo-own');
$check($seenClinic !== '' && cms_care_allergy_text('09121110001') === 'پنی‌سیلین', 'seen-allergy');
$check(str_contains($monthSheet, '1405') && !str_contains($monthSheet, 'صف') && is_file(__DIR__ . '/../public/cms/fonts/vazirmatn-arabic-400-normal.woff2'), 'report-font');
cms_set_setting('care_teeth', '0');
$check(cms_teeth_save('09121110001', [11])['ok'] === false, 'teeth-off');
cms_set_setting('care_teeth', '1');
$teeth = cms_teeth_save('09121110001', [11, 18, 99, 51]);
$addedLine = cms_care_add_med('09121110001', 'clinic', 'آموکسی', '500', 'صبح', '', 'خوراکی', 'پنج روز');
$lineId = (int) $pdo->query("SELECT id FROM care_meds WHERE phone = '09121110001' ORDER BY id DESC LIMIT 1")->fetchColumn();
$lineRow = $pdo->query("SELECT route, span FROM care_meds WHERE id = $lineId")->fetch();
$appliedChecks = cms_check_apply($visitId, "ناشتا\nرضایت\nناشتا");
$checkId = (int) $pdo->query("SELECT id FROM care_checks WHERE visit_id = $visitId ORDER BY id LIMIT 1")->fetchColumn();
$slept = cms_visit_anesthesia($visitId, '09121110001', 'ANESTHNOTE');
$toothPdf = cms_care_sheet_pdf('09121110001', 'fa');
$roomSaved = cms_room_save('hospital-a', 'or-1', 'اتاق آزمایش');
$roomBad = cms_room_save('hospital-a', 'x', 'اتاق');
$pdo->prepare("UPDATE visits SET place_key = 'hospital-a', room_key = 'or-1' WHERE id = ?")->execute([$visitId]);
$pdo->prepare("UPDATE visit_topics SET service_code = 'D-TEST' WHERE topic_key = 'unsure'")->execute();
$check($teeth['ok'] === true && $teeth['marks'] === [11, 18], 'teeth-fdi');
$check($addedLine['ok'] === true && is_array($lineRow) && (string) $lineRow['route'] === 'خوراکی' && (string) $lineRow['span'] === 'پنج روز' && cms_care_med_line('09121110001', $lineId, 'patient', 'تزریقی', 'یک روز')['ok'] === false, 'med-line');
$check($appliedChecks['ok'] === true && $appliedChecks['count'] === 2 && cms_check_toggle($checkId, $visitId) === true, 'checks');
$check($slept === true && str_starts_with($toothPdf, '%PDF') && str_contains($toothPdf, '11') && !str_contains($toothPdf, 'ANESTHNOTE'), 'pdf-tooth');
$check($roomSaved['ok'] === true && $roomBad['ok'] === false && cms_room_free($today, '18:30', 'exam', 'hospital-a', 'or-1', $visitId) === true && cms_room_free($today, '18:30', 'exam', 'hospital-a', 'or-1', 0) === false, 'room');
$check(cms_topic_code('unsure') === 'D-TEST', 'service-code');
$check(cms_desk_role('nurse') === 'nurse' && cms_desk_role('editor') === 'editor' && cms_desk_role('owner') === 'owner' && cms_desk_role('nope') === 'owner', 'role-nurse');
$called = cms_call_add('09121110001', $visitId, 'callback', 'ساعت چهار', 'پرستار آزمایش');
$badCall = cms_call_add('09121110001', 0, 'nope', 'نه', 'پرستار');
$callRow = $pdo->query("SELECT result, note FROM care_calls WHERE phone = '09121110001' ORDER BY id DESC LIMIT 1")->fetch();
$needAdded = cms_care_add_need('09121110001', 'clinic', 'زنگ پیگیری');
$needId = (int) $pdo->query("SELECT id FROM care_needs WHERE phone = '09121110001' ORDER BY id DESC LIMIT 1")->fetchColumn();
$assigned = cms_care_need_assign('09121110001', $needId, 'پرستار آزمایش');
$needStaff = (string) $pdo->query("SELECT staff FROM care_needs WHERE id = $needId")->fetchColumn();
$pdo->prepare('INSERT INTO visits (code, status, name, phone, email, service, day, time_slot, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
    'CALL1234', 'confirmed', 'تماس', '09121110019', '', 'exam', $today, '19:00', '', '', 'fa', '', $now, $now,
]);
cms_call_add('09121110019', 0, 'missed', 'نبود', 'پرستار');
$erasedCall = cms_patient_erase('09121110019');
$callLeft = (int) $pdo->query("SELECT COUNT(*) FROM care_calls WHERE phone = '09121110019'")->fetchColumn();
$check($called['ok'] === true && $badCall['ok'] === false && is_array($callRow) && (string) $callRow['result'] === 'callback' && (string) $callRow['note'] === 'ساعت چهار', 'call-log');
$check($needAdded['ok'] === true && $assigned === true && $needStaff === 'پرستار آزمایش', 'need-owner');
$check($erasedCall['ok'] === true && $callLeft === 0, 'call-erase');
cms_set_setting('telegram_chat', '77');
cms_set_setting('telegram_on', '0');
cms_message_log($visitId, 'telegram', 'doctor', 'confirmed', 'تلگرام', 'نوبت ثبت شد', 'sent', '55', '');
$chatPayload = [
    'ok' => true,
    'result' => [
        ['update_id' => 9, 'message' => ['text' => 'فردا بیایید', 'chat' => ['id' => 77], 'reply_to_message' => ['message_id' => 55]]],
        ['update_id' => 10, 'message' => ['text' => 'گفتگوی دیگر', 'chat' => ['id' => 8], 'reply_to_message' => ['message_id' => 55]]],
        ['update_id' => 11, 'message' => ['text' => 'بی پاسخ', 'chat' => ['id' => 77]]],
    ],
];
$chatOnce = cms_chat_keep('telegram', cms_chat_read($chatPayload));
$chatTwice = cms_chat_keep('telegram', cms_chat_read($chatPayload));
$chatRow = $pdo->query("SELECT body, via, seen_at FROM care_posts WHERE phone = '09121110001' AND via = 'telegram'")->fetch();
$chatCount = (int) $pdo->query("SELECT COUNT(*) FROM care_posts WHERE via = 'telegram'")->fetchColumn();
$chatPdf = cms_care_sheet_pdf('09121110001', 'fa');
$chatPull = cms_chat_pull('telegram');
cms_set_setting('telegram_chat', '');
$check($chatOnce['kept'] === 1 && $chatTwice['kept'] === 0 && is_array($chatRow) && (string) $chatRow['body'] === 'فردا بیایید' && (string) $chatRow['via'] === 'telegram' && (string) $chatRow['seen_at'] === '' && $chatCount === 1 && $chatPull['ok'] === false && str_starts_with($chatPdf, '%PDF') && !str_contains($chatPdf, 'فردا بیایید'), 'chat-reply');
cms_set_setting('care_teeth', '0');
cms_set_setting('sms_on', '0');
cms_set_setting('notify_doctor_created', '1');

if ($failed !== 0) {
    exit(1);
}
echo "login-ok\n";
