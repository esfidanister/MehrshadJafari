<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    exit;
}

$kind = ($_POST['kind'] ?? '') === 'appointment' ? 'appointment' : 'contact';
$locale = cms_locale((string) ($_POST['locale'] ?? 'fa'));
$next = cms_local_path((string) ($_POST['next'] ?? ''));
if ($next === '') {
    $next = '/' . $locale . ($kind === 'appointment' ? '/appointment/' : '/contact/');
}

$fail = static function (string $path): void {
    header('Location: ' . $path . (str_contains($path, '?') ? '&' : '?') . 'error=1', true, 303);
    exit;
};

if (!cms_installed()) {
    $fail($next);
}
if (trim((string) ($_POST['company'] ?? '')) !== '') {
    header('Location: ' . $next . '?sent=' . $kind, true, 303);
    exit;
}
if (($_POST['consent'] ?? '') !== '1') {
    $fail($next);
}

$name = cms_plain((string) ($_POST['name'] ?? ''), 80);
$phone = preg_replace('/[^\d+]/', '', (string) ($_POST['phone'] ?? '')) ?? '';
$message = cms_plain((string) ($_POST['message'] ?? ''), 2000);
$service = (string) ($_POST['service'] ?? '');
$preferred = cms_plain((string) ($_POST['preferred'] ?? ''), 120);
$allowedServices = ['wisdom-tooth-surgery', 'dental-implants', 'orthognathic-surgery', 'oral-pathology', ''];

if (cms_chars($name) < 2 || strlen($phone) < 8 || strlen($phone) > 20) {
    $fail($next);
}
if ($kind === 'contact' && cms_chars($message) < 10) {
    $fail($next);
}
if ($kind === 'appointment' && ($preferred === '' || !in_array($service, $allowedServices, true) || $service === '')) {
    $fail($next);
}

$hash = cms_ip_hash();
$recent = cms_db()->prepare('SELECT COUNT(*) FROM leads WHERE ip_hash = ? AND created_at > ?');
$recent->execute([$hash, gmdate('c', time() - 3600)]);
if ((int) $recent->fetchColumn() >= 8) {
    $fail($next);
}

$now = cms_now();
$stmt = cms_db()->prepare('INSERT INTO leads (kind, status, name, phone, service, preferred_at, message, note, locale, ip_hash, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
$stmt->execute([$kind, 'new', $name, $phone, $service, $preferred, $message, '', $locale, $hash, $now, $now]);
header('Location: ' . $next . '?sent=' . $kind, true, 303);
