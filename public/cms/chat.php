<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(422);
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

if (!cms_installed()) {
    http_response_code(422);
    echo '{"ok":false}';
    exit;
}

$locale = cms_locale((string) ($_POST['locale'] ?? 'fa'));
$message = cms_plain((string) ($_POST['message'] ?? ''), 800);
if (cms_chars($message) < 2 || preg_match('/کد\s*ملی|national id/ui', $message)) {
    echo json_encode(['ok' => true, 'reply' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = cms_db();
$pdo->exec('BEGIN IMMEDIATE');
try {
    $hash = cms_ip_hash();
    $since = gmdate('c', time() - 3600);
    $byIp = $pdo->prepare('SELECT COUNT(*) FROM consult_log WHERE ip_hash = ? AND created_at > ?');
    $byIp->execute([$hash, $since]);
    $all = $pdo->prepare('SELECT COUNT(*) FROM consult_log WHERE created_at > ?');
    $all->execute([$since]);
    if ((int) $byIp->fetchColumn() >= 6 || (int) $all->fetchColumn() >= 40) {
        $pdo->exec('COMMIT');
        http_response_code(429);
        echo '{"ok":false}';
        exit;
    }
    $pdo->prepare('INSERT INTO consult_log (ip_hash, locale, chars, created_at) VALUES (?,?,?,?)')->execute([$hash, $locale, cms_chars($message), cms_now()]);
    $pdo->exec('COMMIT');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->exec('ROLLBACK');
    }
    throw $error;
}

$language = ['fa' => 'Persian', 'en' => 'English', 'ar' => 'Arabic', 'zh' => 'Chinese'][$locale];
$result = cms_ai_complete([
    [
        'role' => 'system',
        'content' => 'You answer as the educational desk of an oral and maxillofacial clinic in Tehran. Reply in ' . $language . ' only. Use plain language. Do not diagnose, prescribe doses, promise an outcome, or invent the surgeon\'s credentials. If the question needs an exam, say so. For heavy bleeding, rapidly spreading swelling of the face or neck, trouble breathing, or trouble swallowing, tell the person to call 115 or go to an emergency department. Keep the reply under 120 words. No markdown headings.',
    ],
    ['role' => 'user', 'content' => $message],
], 0.2, false, 25, 400);

$text = trim((string) ($result['text'] ?? ''));
if (($result['ok'] ?? false) !== true || $text === '') {
    cms_log('warn', 'consult', 'fallback', 'پاسخ مشاوره ساخته نشد', ['locale' => $locale, 'error' => (string) ($result['error'] ?? '')]);
    echo '{"ok":false}';
    exit;
}
echo json_encode(['ok' => true, 'reply' => $text], JSON_UNESCAPED_UNICODE);
