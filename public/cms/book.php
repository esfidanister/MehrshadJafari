<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
    http_response_code(403);
    echo '{"ok":false}';
    exit;
}

$fail = static function (): void {
    http_response_code(422);
    echo '{"ok":false}';
    exit;
};

if (!cms_installed() || trim((string) ($_POST['company'] ?? '')) !== '' || (($_POST['consent'] ?? '') !== '1')) {
    $fail();
}

$result = cms_visit_create([
    'service' => (string) ($_POST['service'] ?? ''),
    'kind' => (string) ($_POST['kind'] ?? 'exam'),
    'place' => (string) ($_POST['place'] ?? ''),
    'day' => (string) ($_POST['day'] ?? ''),
    'time' => (string) ($_POST['time'] ?? ''),
    'name' => (string) ($_POST['name'] ?? ''),
    'phone' => (string) ($_POST['phone'] ?? ''),
    'email' => (string) ($_POST['email'] ?? ''),
    'message' => (string) ($_POST['message'] ?? ''),
    'locale' => (string) ($_POST['locale'] ?? 'fa'),
    'source' => 'site',
    'status' => 'new',
]);
if (!$result['ok']) {
    if ($result['error'] === 'limit') {
        http_response_code(429);
        echo '{"ok":false,"error":"limit"}';
        exit;
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $result['error']], JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode(['ok' => true, 'code' => $result['code']], JSON_UNESCAPED_UNICODE);
