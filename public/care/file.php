<?php
declare(strict_types=1);

require dirname(__DIR__) . '/cms/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$file = ($id > 0 && cms_installed()) ? cms_care_file($id) : null;
$allowed = false;
if ($file) {
    $viewer = '';
    $full = false;
    if (isset($_COOKIE['CARESESS'])) {
        $viewer = cms_care_logged_phone();
        $full = cms_care_role() === 'patient';
        session_write_close();
    }
    $own = $full && $viewer !== '' && $viewer === (string) $file['phone'] && (int) $file['hidden'] !== 1;
    $staff = false;
    if (!$own && isset($_COOKIE['CLINICSESS'])) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name('CLINICSESS');
        session_start();
        $staff = cms_user() !== null;
    }
    $allowed = $own || $staff;
}
if (!$file || !$allowed) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
    exit('پیدا نشد.');
}

header('Content-Type: ' . (string) $file['mime']);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="photo.' . (str_ends_with((string) $file['stored'], '.png') ? 'png' : (str_ends_with((string) $file['stored'], '.webp') ? 'webp' : 'jpg')) . '"');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Length: ' . (string) filesize((string) $file['path']));
readfile((string) $file['path']);
