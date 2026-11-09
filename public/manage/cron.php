<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cms/bootstrap.php';

if (!cms_installed()) {
    http_response_code(404);
    exit;
}
$token = (string) ($_GET['token'] ?? '');
$expected = cms_setting('cron_token');
if ($expected === '' || !hash_equals($expected, $token)) {
    http_response_code(404);
    exit;
}
$result = cms_import_feeds(cms_setting('openai_key') !== '', 3);
$reminded = cms_remind_due();
$selfReminded = cms_care_self_due();
header('Content-Type: text/plain; charset=UTF-8');
echo 'drafts=' . $result['created'] . "\n";
echo 'reminded=' . $reminded['sent'] . "\n";
echo 'late=' . $reminded['late'] . "\n";
echo 'self=' . $selfReminded['sent'] . "\n";
echo 'selflate=' . $selfReminded['late'] . "\n";
echo 'expired=' . $selfReminded['expired'] . "\n";
foreach ($result['errors'] as $error) {
    echo $error . "\n";
}
