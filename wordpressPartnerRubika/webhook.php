<?php
declare(strict_types=1);

/**
 * WordPressPartner Rubika adapter
 * Native PHP only. Independent from the Bale webhook.
 * Configure secrets in the private config.php outside public_html.
 */

$configFile = dirname(__DIR__, 2) . '/bot/wordpressPartnerRubika/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing private config.php');
}
$config = require $configFile;

header('Content-Type: text/plain; charset=utf-8');

function rubikaLog(string $message): void {
    $file = __DIR__ . '/webhook-log.php';
    if (!is_file($file)) {
        @file_put_contents($file, "<?php http_response_code(404); exit; ?>\n", LOCK_EX);
    }
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Rubika webhook is ready.');
}

$secret = (string)($config['webhook_secret'] ?? '');
if ($secret === '' || !hash_equals($secret, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid JSON');
}
http_response_code(200);
echo 'OK';

$token = trim((string)($config['bot_token'] ?? ''));
if ($token === '') {
    rubikaLog('ERROR: bot_token is empty');
    exit;
}
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
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) {
        rubikaLog('API transport error method=' . $method . ' error=' . $error);
        return [];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || $status < 200 || $status >= 300 || (($decoded['status'] ?? '') !== 'OK')) {
        rubikaLog('API error method=' . $method . ' http=' . $status . ' response=' . substr((string)$response, 0, 500));
        return is_array($decoded) ? $decoded : [];
    }
    return $decoded;
}

function sendText(string $chatId, string $text, ?array $keyboard = null): void {
    $args = ['chat_id' => $chatId, 'text' => $text];
    if ($keyboard !== null) $args['inline_keypad'] = ['rows' => $keyboard];
    rubikaApi('sendMessage', $args);
}

function button(string $text, string $id): array {
    return ['type' => 'Simple', 'button_text' => $text, 'button_id' => $id];
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

// Rubika sends normal messages under update.new_message and keypad clicks
// under inline_message. Keep extraction tolerant of the documented envelope.
$update = $payload['update'] ?? [];
$message = $update['new_message'] ?? [];
$inline = $payload['inline_message'] ?? [];
$chatId = (string)($payload['chat_id'] ?? $update['chat_id'] ?? $inline['chat_id'] ?? '');
$userId = (string)($message['sender_id'] ?? $inline['sender_id'] ?? '');
$text = trim((string)($message['text'] ?? $inline['text'] ?? ''));
$messageId = (string)($message['message_id'] ?? $inline['message_id'] ?? '');
$buttonId = (string)($inline['aux_data']['button_id'] ?? '');

if ($chatId === '') {
    rubikaLog('Ignored update without chat_id');
    exit;
}
if ($userId === '') $userId = $chatId;

if ($buttonId !== '') {
    if ($buttonId === 'restart') {
        unset($data['users'][$userId]);
        writeData($dataFile, $data);
        sendText($chatId, "سلام! به فروشگاه نمایشی بازوپرداز خوش آمدید.\n\nیکی از گزینه‌های زیر را انتخاب کنید.", menuKeyboard());
        exit;
    }
    if ($buttonId === 'show_product') {
        sendText($chatId,
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
            sendText($chatId, 'هنوز سفارشی برای شما ثبت نشده است.', menuKeyboard());
        } else {
            $out = "📦 سفارش‌های شما\n\n";
            foreach (array_slice($orders, -10) as $order) {
                $out .= $order['order_id'] . ' | ' . $order['product_name'] . ' | سایز ' . $order['size'] . ' | ' . $order['status'] . "\n";
            }
            sendText($chatId, $out, menuKeyboard());
        }
        exit;
    }
    if ($buttonId === 'support') {
        sendText($chatId, 'برای پشتیبانی با مدیر فروشگاه در ارتباط باشید.', menuKeyboard());
        exit;
    }
    if (preg_match('/^size_(\d+)_(\d+)$/', $buttonId, $m)) {
        $pid = (int)$m[1]; $size = $m[2];
        if (!isset($products[$pid]) || !in_array($size, $products[$pid]['sizes'], true)) {
            sendText($chatId, 'انتخاب نامعتبر است. دوباره محصول را انتخاب کنید.', menuKeyboard());
            exit;
        }
        $data['users'][$userId] = ['chat_id' => $chatId, 'product_id' => $pid, 'size' => $size, 'stage' => 'review'];
        writeData($dataFile, $data);
        sendText($chatId, summary($products[$pid], $size), [
            row([button('✅ تأیید سفارش', 'confirm_order')]),
            row([button('↩️ تغییر سایز', 'change_size'), button('لغو', 'restart')]),
        ]);
        exit;
    }
    $state = $data['users'][$userId] ?? [];
    if ($buttonId === 'change_size') {
        sendText($chatId, 'لطفاً سایز موردنظرتان را انتخاب کنید:', sizeKeyboard($products[4]));
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
        sendText($chatId,
            "سفارش شما با شماره {$orderId} آماده پرداخت است.\n\n"
            . 'مبلغ: ' . number_format($product['price']) . " {$product['currency']}\n"
            . "محصول: {$product['name']}\nسایز: {$state['size']}\n\n"
            . 'در این نسخه نمایشی پرداخت آنلاین متصل نیست. پس از پرداخت آزمایشی، تصویر رسید را همین‌جا ارسال کنید.'
        );
        exit;
    }
    sendText($chatId, 'برای ادامه از دکمه‌های گفتگو استفاده کنید یا /start را بفرستید.');
    exit;
}

// /start can optionally carry the same product4_size43 payload when the
// Rubika client forwards it as "/start product4_size43".
if (preg_match('/^\/?start(?:\s+(.+))?$/u', $text, $startMatch)) {
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
    $state = $data['users'][$userId] ?? [];
    if (($state['stage'] ?? '') === 'awaiting_receipt') {
        sendText($chatId, 'لطفاً تصویر رسید را به‌صورت عکس ارسال کنید.');
    } else {
        sendText($chatId, 'برای شروع، /start را بفرستید.');
    }
}
