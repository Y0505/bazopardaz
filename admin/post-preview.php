<?php
/** Live preview for the editor: renders the Markdown with the same engine that publishes it. JSON in/out. */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/blog-engine.php';

adminStartSession();
adminHeaders();
adminRequireLogin(true);
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'error' => 'POST only'));
    exit;
}
adminRequireCsrf(true);

$body = isset($_POST['body']) && is_string($_POST['body']) ? $_POST['body'] : '';
if (mb_strlen($body, 'UTF-8') > 80000) {
    echo json_encode(array('ok' => false, 'error' => 'متن بیش از حد طولانی است.'), JSON_UNESCAPED_UNICODE);
    exit;
}
$faq = array();
$qs = isset($_POST['faq_q']) && is_array($_POST['faq_q']) ? $_POST['faq_q'] : array();
$as = isset($_POST['faq_a']) && is_array($_POST['faq_a']) ? $_POST['faq_a'] : array();
foreach ($qs as $i => $q) {
    $q = is_string($q) ? trim($q) : '';
    $a = isset($as[$i]) && is_string($as[$i]) ? trim($as[$i]) : '';
    if ($q !== '' && $a !== '' && count($faq) < 10) {
        $faq[] = array($q, $a);
    }
}
try {
    $p = bpPrepare(array('title' => 'x', 'description' => 'x', 'excerpt' => 'x', 'category' => 'x', 'category_slug' => 'x',
        'published' => date('Y-m-d'), 'faq' => $faq, 'slug' => 'preview'), $body, 'preview');
    echo json_encode(array('ok' => true, 'html' => $p['body_html'], 'toc' => $p['toc'], 'words' => $p['words'],
        'minutes' => $p['minutes']), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(array('ok' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
