<?php
/**
 * Bale Bot Notification Helper  (@notifbazobot)
 *
 * Sends "new project request" notifications to the admin through the Bale Bot API.
 * Plain PHP + cURL only. Written to run on PHP 7.2+ as well as 8.x.
 *
 * Optional keys in secure/config.php:
 *   'bale_api_base'    => 'https://tapi.bale.ai'          (default)
 *   'admin_panel_url'  => 'https://bazopardaz.ir/admin/'   (default; shown as a button)
 */

/**
 * Bale's Bot API lives on tapi.bale.ai. The old host api.ble.ir does not answer Bot API calls,
 * so it is silently replaced even if an old value is still stored in the private config.
 */
function baleApiBase($config)
{
    $base = rtrim((string)(isset($config['bale_api_base']) ? $config['bale_api_base'] : 'https://tapi.bale.ai'), '/');
    $host = strtolower((string)parse_url($base, PHP_URL_HOST));
    if ($host === '' || $host === 'api.ble.ir' || $host === 'ble.ir') {
        $base = 'https://tapi.bale.ai';
    }
    return $base;
}

/**
 * Low-level Bale API call (JSON in / JSON out).
 *
 * @return array ['success' => bool, 'error' => string|null, 'result' => array|null]
 */
function baleApiRequest($config, $method, array $payload)
{
    $token = trim((string)(isset($config['bale_bot_token']) ? $config['bale_bot_token'] : ''));
    if ($token === '' || $token === 'YOUR_BALE_BOT_TOKEN') {
        return array('success' => false, 'error' => 'Bale bot token not configured', 'result' => null);
    }

    $url = baleApiBase($config) . '/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
    ));
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // never let the token leak into logs / the database
    $clean = function ($s) use ($token) {
        return str_replace($token, '[token]', (string)$s);
    };

    if ($response === false || $curlError !== '') {
        return array('success' => false, 'error' => 'cURL error: ' . $clean($curlError), 'result' => null);
    }

    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['ok']) && $decoded['ok'] === true) {
        return array('success' => true, 'error' => null, 'result' => $decoded);
    }

    $description = is_array($decoded) && isset($decoded['description'])
        ? (string)$decoded['description']
        : mb_substr(trim((string)$response), 0, 200);
    return array('success' => false, 'error' => 'HTTP ' . $httpCode . ': ' . $clean($description), 'result' => is_array($decoded) ? $decoded : null);
}

/**
 * Send a message to the admin chat.
 *
 * @param string     $text    Plain text (no parse_mode on purpose: customer text containing * _ ` would break Markdown)
 * @param array|null $buttons Inline keyboard rows: array(array(array('text' => '..', 'url' => '..')))
 * @return array ['success' => bool, 'error' => string|null]
 */
function baleSendMessage($text, $buttons = null)
{
    $config = getDbConfig();
    $chatId = trim((string)(isset($config['bale_admin_chat_id']) ? $config['bale_admin_chat_id'] : ''));
    if ($chatId === '' || $chatId === 'YOUR_BALE_CHAT_ID') {
        return array('success' => false, 'error' => 'Bale admin chat ID not configured');
    }

    $payload = array(
        'chat_id' => ctype_digit($chatId) ? (int)$chatId : $chatId,
        'text'    => mb_substr((string)$text, 0, 4000),
        'disable_web_page_preview' => true,
    );
    if (is_array($buttons) && count($buttons) > 0) {
        $payload['reply_markup'] = array('inline_keyboard' => $buttons);
    }

    $res = baleApiRequest($config, 'sendMessage', $payload);

    // If only the buttons were the problem, the notification itself must still arrive.
    if (!$res['success'] && isset($payload['reply_markup'])) {
        unset($payload['reply_markup']);
        $res = baleApiRequest($config, 'sendMessage', $payload);
    }
    return array('success' => $res['success'], 'error' => $res['error']);
}

/** Values are stored HTML-escaped by the API; turn them back into plain text for a chat message. */
function baleClean($value)
{
    return trim(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
}

/**
 * Format a project request into a plain-text Bale notification.
 *
 * @param array $request Row from project_requests
 */
function formatBaleMessage($request)
{
    $contactMethods = array(
        'telegram' => 'تلگرام', 'bale' => 'بله', 'whatsapp' => 'واتساپ',
        'email' => 'ایمیل', 'phone' => 'تلفن', 'other' => 'سایر',
    );
    $projectTypes = array(
        'telegram_bot' => 'ربات تلگرام', 'bale_bot' => 'ربات بله', 'rubika_bot' => 'ربات روبیکا',
        'discord_bot' => 'ربات دیسکورد', 'mini_app' => 'مینی‌اپ تلگرام', 'website' => 'طراحی وبسایت',
        'automation' => 'اتوماسیون / اتصال API', 'other' => 'سایر',
    );

    $method = isset($request['contact_method']) ? $request['contact_method'] : '';
    $type = isset($request['project_type']) ? $request['project_type'] : '';
    $budget = baleClean(isset($request['budget']) ? $request['budget'] : '');

    $text  = "🚀 درخواست پروژه جدید  #" . $request['id'] . "\n";
    $text .= "━━━━━━━━━━━━━━\n";
    $text .= "👤 نام: " . baleClean($request['name']) . "\n";
    $text .= "💻 نوع پروژه: " . (isset($projectTypes[$type]) ? $projectTypes[$type] : $type) . "\n";
    $text .= "💰 بودجه: " . ($budget !== '' ? $budget : 'مشخص نشده') . "\n\n";
    $text .= "📞 راه تماس (" . (isset($contactMethods[$method]) ? $contactMethods[$method] : $method) . "):\n";
    $text .= baleClean($request['contact_value']) . "\n\n";
    $text .= "📝 توضیحات:\n" . mb_substr(baleClean($request['description']), 0, 3000) . "\n\n";
    $text .= "🕐 " . (isset($request['created_at']) ? $request['created_at'] : date('Y-m-d H:i:s')) . "\n";
    $text .= "🌐 bazopardaz.ir";
    return $text;
}

/**
 * Quick-action buttons under the notification:
 *  - open a chat with the customer (when the contact is a Bale / Telegram username or a WhatsApp number)
 *  - open the admin panel
 */
function baleRequestButtons($request)
{
    $config = getDbConfig();
    $method = isset($request['contact_method']) ? $request['contact_method'] : '';
    $value = baleClean(isset($request['contact_value']) ? $request['contact_value'] : '');
    $rows = array();

    if (($method === 'bale' || $method === 'telegram') && preg_match('/^(?:https?:\/\/(?:t\.me|ble\.ir)\/|@)?([A-Za-z][A-Za-z0-9_]{3,31})$/', $value, $m)) {
        $rows[] = array(array(
            'text' => $method === 'bale' ? '💬 گفتگو با مشتری در بله' : '💬 گفتگو با مشتری در تلگرام',
            'url'  => ($method === 'bale' ? 'https://ble.ir/' : 'https://t.me/') . $m[1],
        ));
    } elseif ($method === 'whatsapp') {
        $digits = preg_replace('/\D+/', '', $value);
        if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
            $digits = '98' . substr($digits, 1);
        }
        if (strlen($digits) >= 10) {
            $rows[] = array(array('text' => '💬 گفتگو با مشتری در واتساپ', 'url' => 'https://wa.me/' . $digits));
        }
    }

    $panel = isset($config['admin_panel_url']) && $config['admin_panel_url'] !== ''
        ? $config['admin_panel_url'] : 'https://bazopardaz.ir/admin/';
    $rows[] = array(array('text' => '🗂 پنل مدیریت درخواست‌ها', 'url' => $panel));
    return $rows;
}
