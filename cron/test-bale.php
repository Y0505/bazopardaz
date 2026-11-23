<?php
/**
 * Bale notification self-test (@notifbazobot)
 *
 *   https://bazopardaz.ir/cron/test-bale.php?key=YOUR_CRON_SECRET_KEY
 *
 * Checks the token (getMe), then sends a test notification with buttons to the admin chat and prints the
 * exact error if something is wrong. Protected by the same secret as the cron URL. DELETE it when done.
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/bale.php';

$config = getDbConfig();
$secret = isset($config['cron_secret_key']) ? (string)$config['cron_secret_key'] : '';
$given = isset($_GET['key']) ? (string)$_GET['key'] : '';
if ($secret === '' || !hash_equals($secret, $given)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

echo "API base : " . baleApiBase($config) . "\n";

$me = baleApiRequest($config, 'getMe', array());
if ($me['success']) {
    $bot = isset($me['result']['result']) ? $me['result']['result'] : array();
    echo "Token    : OK  (bot username: @" . (isset($bot['username']) ? $bot['username'] : '?') . ")\n";
} else {
    echo "Token    : FAILED -> " . $me['error'] . "\n";
    echo "\nFix the bale_bot_token in secure/config.php (get it from @botfather in Bale), then reload.\n";
    exit;
}

$chat = isset($config['bale_admin_chat_id']) ? (string)$config['bale_admin_chat_id'] : '(missing)';
echo "Chat ID  : " . $chat . "\n";

$fake = array(
    'id' => 0, 'name' => 'تست سیستم', 'contact_method' => 'bale', 'contact_value' => '@botsdeveloper',
    'project_type' => 'bale_bot', 'budget' => '', 'description' => 'این یک پیام آزمایشی برای اطمینان از کار کردن اعلان درخواست‌هاست.',
    'created_at' => date('Y-m-d H:i:s'),
);
$res = baleSendMessage(formatBaleMessage($fake), baleRequestButtons($fake));
if ($res['success']) {
    echo "Send     : OK  -> check your Bale chat with the bot.\n";
} else {
    echo "Send     : FAILED -> " . $res['error'] . "\n\n";
    echo "Common causes:\n";
    echo " - You have never pressed START in the bot's chat (a bot cannot message someone who has not started it).\n";
    echo " - bale_admin_chat_id is wrong (it must be the numeric ID of YOUR account/chat).\n";
    echo " - The hosting blocks outgoing connections to tapi.bale.ai (ask the host to allow it).\n";
}
