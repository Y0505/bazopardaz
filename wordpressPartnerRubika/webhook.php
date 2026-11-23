<?php
declare(strict_types=1);

/**
 * WordPressPartner Rubika adapter
 * Native PHP only. Independent from the Bale webhook.
 * Diagnostic logging is written to webhook-debug.log.
 */

define('RUBIKA_DEBUG_LOG', __DIR__ . '/webhook-debug.log');

function rubikaDebug(string $event, array $context = []): void {
    $record = [
        'time' => date('Y-m-d H:i:s'),
        'event' => $event,
        'context' => $context,
    ];
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) {
        @file_put_contents(RUBIKA_DEBUG_LOG, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// Keep compatibility with the existing order-storage error call.
function rubikaLog(string $message): void {
    rubikaDebug('legacy_log', ['message' => $message]);
}

rubikaDebug('01_request_received', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'uri_path' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
]);

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    rubikaDebug('php_warning_or_error', [
        'severity' => $severity,
        'message' => $message,
        'file' => basename($file),
        'line' => $line,
    ]);
    return false;
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        rubikaDebug('php_fatal_shutdown', [
            'type' => $error['type'],
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line'],
        ]);
    } else {
        rubikaDebug('request_shutdown', ['last_error' => $error['type'] ?? null]);
    }
});

header('Content-Type: text/plain; charset=utf-8');

$configFile = dirname(__DIR__, 2) . '/bot/wordpressPartnerRubika/config.php';
rubikaDebug('02_config_check', ['path_exists' => is_file($configFile)]);

if (!is_file($configFile)) {
    rubikaDebug('config_missing');
    http_response_code(500);
    exit('Missing private config.php');
}

try {
    $config = require $configFile;
} catch (Throwable $e) {
    rubikaDebug('config_load_exception', ['message' => $e->getMessage()]);
    http_response_code(500);
    exit('Could not load private config.php');
}

rubikaDebug('03_config_loaded', [
    'bot_token_present' => !empty($config['bot_token']),
    'admin_chat_id_present' => !empty($config['admin_chat_id']),
]);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rubikaDebug('request_not_post');
    http_response_code(405);
    header('Allow: POST');
    exit('Rubika webhook is ready.');
}

$raw = file_get_contents('php://input');
rubikaDebug('04_body_received', ['bytes' => strlen((string)$raw)]);

$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    rubikaDebug('json_decode_failed', ['json_error' => json_last_error_msg()]);
    http_response_code(400);
    exit('Invalid JSON');
}

rubikaDebug('05_json_decoded', [
    'root_keys' => array_keys($payload),
    'update_keys' => is_array($payload['update'] ?? null) ? array_keys($payload['update']) : [],
    'inline_keys' => is_array($payload['inline_message'] ?? null) ? array_keys($payload['inline_message']) : [],
]);

// Acknowledge the webhook after the payload has been safely received and decoded.
http_response_code(200);
echo 'OK';

$token = trim((string)($config['bot_token'] ?? ''));
if ($token === '') {
    rubikaDebug('06_missing_bot_token');
    exit;
}
rubikaDebug('06_bot_token_ready');
$apiBase = 'https://botapi.rubika.ir/v3/' . rawurlencode($token) . '/';
$adminChatId = (string)($config['admin_chat_id'] ?? '');
$dataFile = __DIR__ . '/data.json';

function rubikaApi(string $method, array $params = []): array {
    global $apiBase;
    $ch = curl_init($apiBase . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    rubikaDebug('api_request_started', ['method' => $method]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) {
        rubikaDebug('api_transport_error', ['method' => $method, 'error' => $error]);
        return [];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || $status < 200 || $status >= 300 || (($decoded['status'] ?? '') !== 'OK')) {
        rubikaDebug('api_response_error', ['method' => $method, 'http' => $status, 'response_excerpt' => substr((string)$response, 0, 300)]);
        return is_array($decoded) ? $decoded : [];
    }
    rubikaDebug('api_response_ok', ['method' => $method, 'http' => $status]);
    return $decoded;
}

function sendText(string $chatId, string $text, ?array $keyboard = null): void {
    rubikaDebug('send_message_attempt', ['chat_id_present' => $chatId !== '', 'text_length' => strlen($text), 'keyboard' => $keyboard !== null]);
    $args = ['chat_id' => $chatId, 'text' => $text];
    if ($keyboard !== null) $args['inline_keypad'] = ['rows' => $keyboard];
    rubikaApi('sendMessage', $args);
}

/**
 * Edit the bot message that contained the clicked inline button.
 * Rubika exposes text and inline-keypad editing as separate API methods.
 */
function editButtonMessage(string $chatId, string $messageId, string $text, ?array $keyboard = null): void {
    if ($messageId === '') {
        rubikaDebug('edit_message_skipped_missing_message_id', ['chat_id_present' => $chatId !== '']);
        return;
    }

    rubikaDebug('edit_message_attempt', [
        'chat_id_present' => $chatId !== '',
        'message_id' => $messageId,
        'text_length' => strlen($text),
        'keyboard' => $keyboard !== null,
    ]);

    rubikaApi('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
    ]);

    if ($keyboard !== null) {
        rubikaApi('editMessageKeypad', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'inline_keypad' => ['rows' => $keyboard],
        ]);
    }
}

function button(string $text, string $id): array {
    return ['id' => $id, 'type' => 'Simple', 'button_text' => $text];
}
function row(array $buttons): array {
    return ['buttons' => $buttons];
}
function menuKeyboard(): array {
    return [
        row([button('🛍 مشاهده محصول', 'show_product')]),
        row([button('📦 سفارش‌های من', 'my_orders'), button('💬 پشتیبانی', 'support')]),
    ];
}
function sizeKeyboard(array $product): array {
    $buttons = [];
    foreach ($product['sizes'] as $size) $buttons[] = button($size, 'size_' . $product['id'] . '_' . $size);
    return [row($buttons), row([button('لغو / شروع دوباره', 'restart')])];
}
function readData(string $file): array {
    if (!is_file($file)) return ['users' => [], 'orders' => []];
    $decoded = json_decode((string)file_get_contents($file), true);
    return is_array($decoded) ? array_merge(['users' => [], 'orders' => []], $decoded) : ['users' => [], 'orders' => []];
}
function writeData(string $file, array $data): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($file, $json, LOCK_EX) === false) rubikaLog('ERROR: cannot write data.json');
}
function summary(array $product, string $size): string {
    return "🧾 بررسی سفارش\n\n"
        . "محصول: {$product['name']}\n"
        . "کد محصول: {$product['id']}\n"
        . "سایز: {$size}\n"
        . "تعداد: ۱ عدد\n"
        . 'مبلغ: ' . number_format((int)$product['price']) . " {$product['currency']}\n\n"
        . 'لطفاً اطلاعات سفارش را بررسی کنید.';
}

$products = [
    4 => [
        'id' => 4,
        'name' => 'کتونی روزمره نوکتورن',
        'collection' => 'VEL OURA / PERFORMANCE SERIES',
        'price' => 3490000,
        'currency' => 'تومان',
        'description' => 'رویه مشکی با جزئیات فیروزه‌ای و فرم مدرن؛ محصول نمایشی فروشگاه بازوپرداز.',
        'image' => 'https://bazopardaz.ir/veloura-sneaker-concept-03.png',
        'sizes' => ['40', '41', '42', '43', '44'],
    ],
];

$data = readData($dataFile);
rubikaDebug('07_data_loaded', ['users_count' => count($data['users'] ?? []), 'orders_count' => count($data['orders'] ?? [])]);

// Rubika sends normal messages under update.new_message and keypad clicks
// under inline_message. Keep extraction tolerant of the documented envelope.
$update = $payload['update'] ?? [];
$message = $update['new_message'] ?? [];
$inline = $payload['inline_message'] ?? [];
$chatId = (string)($payload['chat_id'] ?? $update['chat_id'] ?? $inline['chat_id'] ?? '');
$userId = (string)($message['sender_id'] ?? $inline['sender_id'] ?? '');
$text = trim((string)($message['text'] ?? $inline['text'] ?? ''));
$messageId = (string)($message['message_id'] ?? $inline['message_id'] ?? '');
// button_id belongs to ReceiveInlineMessage events only.
 // Normal messages may contain aux_data.button_id="start"; that is not a keypad click.
$buttonId = (string)($inline['aux_data']['button_id'] ?? '');

rubikaDebug('08_update_parsed', [
    'update_type' => $update['type'] ?? null,
    'message_keys' => is_array($message) ? array_keys($message) : [],
    'inline_type' => $inline['type'] ?? null,
    'chat_id_present' => $chatId !== '',
    'user_id_present' => $userId !== '',
    'text' => substr($text, 0, 160),
    'button_id' => $buttonId,
]);

if ($chatId === '') {
    rubikaDebug('update_ignored_missing_chat_id');
    exit;
}
if ($userId === '') $userId = $chatId;

if ($buttonId !== '') {
    rubikaDebug('09_button_update', ['button_id' => $buttonId]);
    if ($buttonId === 'restart') {
        unset($data['users'][$userId]);
        writeData($dataFile, $data);
        editButtonMessage($chatId, $messageId, "سلام! به فروشگاه نمایشی بازوپرداز خوش آمدید.\n\nیکی از گزینه‌های زیر را انتخاب کنید.", menuKeyboard());
        exit;
    }
    if ($buttonId === 'show_product') {
        editButtonMessage($chatId, $messageId,
            "🛍 {$products[4]['name']}\n"
            . "{$products[4]['collection']}\n\n"
            . "{$products[4]['description']}\n\n"
            . 'قیمت: ' . number_format($products[4]['price']) . " {$products[4]['currency']}\n"
            . 'سایزهای موجود: ' . implode('، ', $products[4]['sizes']) . "\n\n"
            . 'برای انتخاب سایز، یکی از دکمه‌ها را بزنید.',
            sizeKeyboard($products[4])
        );
        exit;
    }
    if ($buttonId === 'my_orders') {
        $orders = [];
        foreach (($data['orders'] ?? []) as $order) {
            if ((string)($order['user_id'] ?? '') === $userId) $orders[] = $order;
        }
        if (!$orders) {
            editButtonMessage($chatId, $messageId, 'هنوز سفارشی برای شما ثبت نشده است.', menuKeyboard());
        } else {
            $out = "📦 سفارش‌های شما\n\n";
            foreach (array_slice($orders, -10) as $order) {
                $out .= $order['order_id'] . ' | ' . $order['product_name'] . ' | سایز ' . $order['size'] . ' | ' . $order['status'] . "\n";
            }
            editButtonMessage($chatId, $messageId, $out, menuKeyboard());
        }
        exit;
    }
    if ($buttonId === 'support') {
        editButtonMessage($chatId, $messageId, 'برای پشتیبانی و سفارش ربات مشابه، به @botsdeveloper پیام دهید.', menuKeyboard());
        exit;
    }
    if (preg_match('/^size_(\d+)_(\d+)$/', $buttonId, $m)) {
        $pid = (int)$m[1]; $size = $m[2];
        if (!isset($products[$pid]) || !in_array($size, $products[$pid]['sizes'], true)) {
            editButtonMessage($chatId, $messageId, 'انتخاب نامعتبر است. دوباره محصول را انتخاب کنید.', menuKeyboard());
            exit;
        }
        $data['users'][$userId] = ['chat_id' => $chatId, 'product_id' => $pid, 'size' => $size, 'stage' => 'review'];
        writeData($dataFile, $data);
        editButtonMessage($chatId, $messageId, summary($products[$pid], $size), [
            row([button('✅ تأیید سفارش', 'confirm_order')]),
            row([button('↩️ تغییر سایز', 'change_size'), button('لغو', 'restart')]),
        ]);
        exit;
    }
    $state = $data['users'][$userId] ?? [];
    if ($buttonId === 'change_size') {
        editButtonMessage($chatId, $messageId, 'لطفاً سایز موردنظرتان را انتخاب کنید:', sizeKeyboard($products[4]));
        exit;
    }
    if ($buttonId === 'confirm_order' && ($state['stage'] ?? '') === 'review') {
        $product = $products[(int)$state['product_id']];
        $orderId = 'WR-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $data['users'][$userId]['stage'] = 'awaiting_receipt';
        $data['users'][$userId]['order_id'] = $orderId;
        $data['orders'][$orderId] = [
            'order_id' => $orderId, 'user_id' => $userId, 'chat_id' => $chatId,
            'product_id' => $product['id'], 'product_name' => $product['name'],
            'size' => (string)$state['size'], 'amount' => (int)$product['price'],
            'currency' => $product['currency'], 'status' => 'awaiting_receipt',
            'created_at' => date(DATE_ATOM),
        ];
        writeData($dataFile, $data);
        editButtonMessage($chatId, $messageId,
            "سفارش شما با شماره {$orderId} آماده پرداخت است.\n\n"
            . 'مبلغ: ' . number_format($product['price']) . " {$product['currency']}\n"
            . "محصول: {$product['name']}\nسایز: {$state['size']}\n\n"
            . 'در این نسخه نمایشی پرداخت آنلاین متصل نیست. پس از پرداخت آزمایشی، تصویر رسید را همین‌جا ارسال کنید.'
        );
        exit;
    }
    editButtonMessage($chatId, $messageId, 'برای ادامه از دکمه‌های گفتگو استفاده کنید یا /start را بفرستید.');
    exit;
}

// /start can optionally carry the same product4_size43 payload when the
// Rubika client forwards it as "/start product4_size43".
if (preg_match('/^\/?start(?:\s+(.+))?$/u', $text, $startMatch)) {
    rubikaDebug('10_start_command_detected');
    $deepLink = trim((string)($startMatch[1] ?? ''));
    unset($data['users'][$userId]);
    if ($deepLink !== '' && preg_match('/^product(\d+)_size(\d+)$/', $deepLink, $m)) {
        $pid = (int)$m[1]; $size = $m[2];
        if (isset($products[$pid]) && in_array($size, $products[$pid]['sizes'], true)) {
            $data['users'][$userId] = ['chat_id' => $chatId, 'product_id' => $pid, 'size' => $size, 'stage' => 'review'];
            writeData($dataFile, $data);
            sendText($chatId, summary($products[$pid], $size), [
                row([button('✅ تأیید سفارش', 'confirm_order')]),
                row([button('↩️ تغییر سایز', 'change_size')]),
            ]);
            exit;
        }
    }
    writeData($dataFile, $data);
    sendText($chatId, "سلام! به فروشگاه نمایشی بازوپرداز خوش آمدید.\n\nیکی از گزینه‌های زیر را انتخاب کنید.", menuKeyboard());
    exit;
}

// Photo/file receipt: preserve metadata and forward the original message to admin.
$file = $message['file'] ?? null;
if (is_array($file) || str_contains(strtolower((string)($message['mime_type'] ?? '')), 'image')) {
    $state = $data['users'][$userId] ?? [];
    if (($state['stage'] ?? '') !== 'awaiting_receipt' || empty($state['order_id'])) {
        sendText($chatId, 'برای ارسال رسید، ابتدا سفارش را ثبت کنید و سپس تصویر رسید را بفرستید.');
        exit;
    }
    $orderId = (string)$state['order_id'];
    if (!isset($data['orders'][$orderId])) {
        sendText($chatId, 'اطلاعات سفارش پیدا نشد. لطفاً /start را بفرستید.');
        exit;
    }
    $data['orders'][$orderId]['status'] = 'receipt_received_demo';
    $data['orders'][$orderId]['receipt_message_id'] = $messageId;
    $data['orders'][$orderId]['receipt_received_at'] = date(DATE_ATOM);
    $data['users'][$userId]['stage'] = 'registered';
    writeData($dataFile, $data);
    sendText($chatId,
        "✅ سفارش شما ثبت شد!\n\nشماره سفارش: {$orderId}\n"
        . "محصول: {$data['orders'][$orderId]['product_name']}\n"
        . "سایز: {$data['orders'][$orderId]['size']}\n"
        . 'مبلغ: ' . number_format((int)$data['orders'][$orderId]['amount']) . " {$data['orders'][$orderId]['currency']}\n\n"
        . 'این ثبت نمایشی است و دریافت تصویر به‌معنای تأیید واقعی پرداخت نیست.'
    );
    if ($adminChatId !== '') {
        sendText($adminChatId,
            "🧾 رسید جدید (نمایشی)\nشماره سفارش: {$orderId}\n"
            . "محصول: {$data['orders'][$orderId]['product_name']}\n"
            . "سایز: {$data['orders'][$orderId]['size']}\n"
            . "شناسه کاربر: {$userId}\n"
            . 'مبلغ اعلام‌شده: ' . number_format((int)$data['orders'][$orderId]['amount']) . " {$data['orders'][$orderId]['currency']}"
        );
        if ($messageId !== '') {
            rubikaApi('forwardMessage', ['from_chat_id' => $chatId, 'message_id' => $messageId, 'to_chat_id' => $adminChatId]);
        }
    }
    exit;
}

if ($text !== '') {
    rubikaDebug('11_other_text_received', ['text' => substr($text, 0, 160)]);
    $state = $data['users'][$userId] ?? [];
    if (($state['stage'] ?? '') === 'awaiting_receipt') {
        sendText($chatId, 'لطفاً تصویر رسید را به‌صورت عکس ارسال کنید.');
    } else {
        sendText($chatId, 'برای شروع، /start را بفرستید.');
    }
}
