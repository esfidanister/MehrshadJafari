<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !cms_installed()) {
    http_response_code(422);
    echo '{"ok":false}';
    exit;
}

$phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? '')) ?? '';
if (str_starts_with($phone, '98')) {
    $phone = '0' . substr($phone, 2);
}
$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['code'] ?? '')) ?? '');
if (strlen($phone) < 10 || strlen($code) < 6) {
    http_response_code(422);
    echo '{"ok":false}';
    exit;
}

$hash = cms_ip_hash();
$recent = cms_db()->prepare('SELECT COUNT(*) FROM consult_log WHERE ip_hash = ? AND created_at > ?');
$recent->execute([$hash, gmdate('c', time() - 3600)]);
if ((int) $recent->fetchColumn() >= 30) {
    http_response_code(429);
    echo '{"ok":false}';
    exit;
}
cms_db()->prepare('INSERT INTO consult_log (ip_hash, locale, chars, created_at) VALUES (?,?,?,?)')->execute([$hash, 'track', strlen($code), cms_now()]);

$stmt = cms_db()->prepare('SELECT status, service, kind, day, time_slot FROM visits WHERE code = ? AND phone = ?');
$stmt->execute([$code, $phone]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo '{"ok":false}';
    exit;
}

$locale = cms_locale((string) ($_POST['locale'] ?? 'fa'));
echo json_encode([
    'ok' => true,
    'visit' => [
        'status' => (string) $row['status'],
        'service' => (string) $row['service'],
        'serviceLabel' => cms_service_label((string) $row['service'], $locale),
        'kind' => (string) ($row['kind'] ?? ''),
        'kindLabel' => cms_kind_label((string) ($row['kind'] ?? ''), $locale),
        'day' => (string) $row['day'],
        'time' => (string) $row['time_slot'],
    ],
], JSON_UNESCAPED_UNICODE);
