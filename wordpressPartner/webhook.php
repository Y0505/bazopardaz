<?php
declare(strict_types=1);

// Write diagnostic messages beside this webhook. The log file is blocked by .htaccess.
function botLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $path = __DIR__ . '/webhook-errors.php';
    // Make direct web access execute a 404/exit guard before any stored log lines.
    if (!is_file($path)) {
        @file_put_contents($path, "<?php http_response_code(404); exit; ?>\\n", LOCK_EX);
    }
    if (@file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('WordPressPartnerBot: unable to write webhook.log; ' . $message);
    }
}

botLog('Webhook request received; method=' . (string)($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

/**
 * WordPressPartnerBot — standalone Bale webhook demo.
 * Native PHP only; no framework or third-party package.
 */
$configFile = dirname(__DIR__, 2) . '/bot/wordpressPartner/config.php';
if (!is_file($configFile)) {
    botLog('ERROR: config file missing at expected private path.');
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing config.php. Copy config.example.php to config.php and configure the bot.';
    exit;
}
$config = require $configFile;
header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Bale webhook is ready. Send updates with POST.';
    exit;
}
$secret = (string)($config['webhook_secret'] ?? '');
if ($secret !== '' && !hash_equals($secret, (string)($_GET['key'] ?? ''))) {
    botLog('ERROR: webhook secret mismatch; update rejected.');
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
$raw = file_get_contents('php://input');
$update = json_decode($raw ?: '', true);
if (!is_array($update)) {
    botLog('ERROR: request body is not valid JSON.');
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}
http_response_code(200);
echo 'OK';

$token = trim((string)($config['bot_token'] ?? ''));
if ($token === '' || $token === 'REPLACE_WITH_BALE_BOT_TOKEN') {
    botLog('ERROR: bot_token is missing or still a placeholder in private config.');
}
$updateType = isset($update['message']) ? 'message' : (isset($update['callback_query']) ? 'callback_query' : 'other');
$messageText = (string)($update['message']['text'] ?? '');
botLog('Update accepted; type=' . $updateType . ($messageText !== '' && preg_match('/^\\/start(?:@\\w+)?(?:\\s|$)/u', $messageText) ? '; command=start' : ''));

$apiBase = rtrim((string)($config['api_base'] ?? 'https://tapi.bale.ai'), '/');
// Normalize the old/incorrect host so an existing private config.php keeps working.
if (in_array(strtolower((string)parse_url($apiBase, PHP_URL_HOST)), ['api.ble.ir', 'ble.ir'], true)) {
    $apiBase = 'https://tapi.bale.ai';
}
$adminChatId = (string)($config['admin_chat_id'] ?? '');
$storagePath = __DIR__ . '/data.json';
$products = [
    4 => [
        'id' => 4,
        'name' => 'کتونی روزمره نوکتورن',
        'collection' => 'VEL OURA / PERFORMANCE SERIES',
        'price' => 3490000,
        'currency' => 'تومان',
        'description' => 'رویه مشکی با جزئیات فیروزه‌ای و فرم مدرن؛ محصول نمایشی برای تجربه خرید از سایت و ادامه سفارش در بازوی بله.',
        'image' => 'https://bazopardaz.ir/veloura-sneaker-concept-03.png',
        'sizes' => ['40', '41', '42', '43', '44'],
    ],
];

function apiCall(string $method, array $payload, string $token, string $apiBase): array
{
    if ($token === '' || $token === 'REPLACE_WITH_BALE_BOT_TOKEN') {
        return ['ok' => false, 'description' => 'Bot token is not configured'];
    }
    $url = $apiBase . '/bot' . $token . '/' . $method;
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 12,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        botLog('ERROR: API request failed for method=' . $method);
        return ['ok' => false, 'description' => 'Network request failed'];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        botLog('ERROR: invalid API response for method=' . $method);
        return ['ok' => false, 'description' => 'Invalid API response'];
    }
    if (($decoded['ok'] ?? false) !== true) {
        botLog('ERROR: API rejected method=' . $method . '; description=' . (string)($decoded['description'] ?? 'unknown error'));
    }
    return $decoded;
}

function sendMessage(int|string $chatId, string $text, string $token, string $apiBase, ?array $keyboard = null, string $parseMode = 'HTML'): array
{
    $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => $parseMode, 'disable_web_page_preview' => true];
    if ($keyboard !== null) {
        $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
    }
    return apiCall('sendMessage', $payload, $token, $apiBase);
}

function sendProductCard(int|string $chatId, array $product, string $token, string $apiBase, ?array $keyboard = null): void
{
    $caption = '*' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . "*\n";
    $caption .= htmlspecialchars($product['collection'], ENT_QUOTES, 'UTF-8') . "\n\n";
    $caption .= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') . "\n\n";
    $caption .= '*قیمت:* ' . number_format((int)$product['price']) . ' ' . $product['currency'] . "\n";
    $caption .= '*سایزهای قابل انتخاب:* ' . implode('، ', $product['sizes']) . "\n\n";
    $caption .= 'این یک سفارش نمایشی است؛ پرداخت از طریق درگاه آنلاین انجام نمی‌شود.';
    $payload = ['chat_id' => $chatId, 'photo' => $product['image'], 'caption' => $caption, 'parse_mode' => 'Markdown'];
    if ($keyboard !== null) {
        $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
    }
    $result = apiCall('sendPhoto', $payload, $token, $apiBase);
    if (($result['ok'] ?? false) !== true) {
        sendMessage($chatId, $caption, $token, $apiBase, $keyboard, 'Markdown');
    }
}

function editCallbackMessage(array $callback, string $text, string $token, string $apiBase, ?array $keyboard = null, string $parseMode = 'Markdown'): array
{
    $message = $callback['message'] ?? [];
    $chatId = $message['chat']['id'] ?? null;
    $messageId = $message['message_id'] ?? null;
    if ($chatId === null || $messageId === null) {
        botLog('ERROR: callback message identifiers are missing; cannot edit message.');
        return ['ok' => false, 'description' => 'Callback message identifiers are missing'];
    }
    $payload = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => $parseMode, 'disable_web_page_preview' => true];
    if ($keyboard !== null) $payload['reply_markup'] = ['inline_keyboard' => $keyboard];
    return apiCall('editMessageText', $payload, $token, $apiBase);
}

function orderStatusLabel(string $status): string
{
    return [
        'awaiting_payment' => 'در انتظار پرداخت',
        'awaiting_receipt' => 'در انتظار ارسال رسید',
        'receipt_received_demo' => 'رسید دریافت شد (نمایشی)',
    ][$status] ?? 'در حال بررسی';
}

function loadData(string $path): array
{
    if (!is_file($path)) {
        return ['users' => [], 'orders' => []];
    }
    $data = json_decode(file_get_contents($path) ?: '', true);
    return is_array($data) ? array_merge(['users' => [], 'orders' => []], $data) : ['users' => [], 'orders' => []];
}
function saveData(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
        botLog('ERROR: unable to persist order state to data.json; check folder permissions.');
    }
}
function sizeKeyboard(array $product): array
{
    $row = [];
    foreach ($product['sizes'] as $size) {
        $row[] = ['text' => $size, 'callback_data' => 'size_' . $product['id'] . '_' . $size];
    }
    return [$row, [['text' => 'لغو / شروع دوباره', 'callback_data' => 'restart']]];
}
function orderSummary(array $product, string $size): string
{
    return "🧾 *بررسی نهایی سفارش*\n\n"
        . '*محصول:* ' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . "\n"
        . '*کد محصول:* ' . (int)$product['id'] . "\n"
        . '*سایز انتخاب‌شده:* ' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . "\n"
        . '*تعداد:* ۱ عدد' . "\n"
        . '*مبلغ قابل پرداخت:* ' . number_format((int)$product['price']) . ' ' . $product['currency'] . "\n\n"
        . 'لطفاً پیش از ادامه، نام محصول، سایز و مبلغ را بررسی کنید.';
}
function newOrderId(): string
{
    return 'WP-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

$data = loadData($storagePath);

if (isset($update['callback_query']) && is_array($update['callback_query'])) {
    $callback = $update['callback_query'];
    $callbackId = (string)($callback['id'] ?? '');
    $chatId = $callback['message']['chat']['id'] ?? null;
    $userId = (string)($callback['from']['id'] ?? '');
    $callbackData = (string)($callback['data'] ?? '');
    if ($callbackId !== '') {
        apiCall('answerCallbackQuery', ['callback_query_id' => $callbackId], $token, $apiBase);
    }
    if ($chatId === null || $userId === '') {
        exit;
    }
    // Welcome-screen buttons run the demo flow; no real payment is processed.
    if ($callbackData === 'demo_catalog') {
        $product = $products[4];
        $catalogText = "🛍 *" . $product['name'] . "*\n"
            . $product['collection'] . "\n\n"
            . $product['description'] . "\n\n"
            . '*قیمت:* ' . number_format((int)$product['price']) . ' ' . $product['currency'] . "\n"
            . '*سایزهای قابل انتخاب:* ' . implode('، ', $product['sizes']) . "\n\n"
            . '[مشاهده تصویر محصول](' . $product['image'] . ')';
        editCallbackMessage($callback, $catalogText, $token, $apiBase, sizeKeyboard($product));
        exit;
    }
    if ($callbackData === 'demo_orders') {
        $userOrders = [];
        foreach (($data['orders'] ?? []) as $order) {
            if ((string)($order['user_id'] ?? '') === $userId) $userOrders[] = $order;
        }
        if (!$userOrders) {
            editCallbackMessage($callback, '📦 *سفارش‌های شما*\n\nهنوز سفارشی برای شما ثبت نشده است.', $token, $apiBase, [
                [['text' => '↩️ منوی اصلی', 'callback_data' => 'restart']],
            ]);
        } else {
            $rows = [];
            foreach (array_slice($userOrders, -5) as $order) {
                $orderId = (string)($order['order_id'] ?? '');
                $rows[] = [['text' => '🧾 ' . $orderId . ' | سایز ' . (string)($order['size'] ?? '-'), 'callback_data' => 'order_' . $orderId]];
            }
            $rows[] = [['text' => '↩️ منوی اصلی', 'callback_data' => 'restart']];
            editCallbackMessage($callback, "📦 *سفارش‌های شما*\n\n۵ سفارش اخیر شما نمایش داده شده است.\nبرای مشاهده جزئیات، یک سفارش را انتخاب کنید:", $token, $apiBase, $rows);
        }
        exit;
    }
    if (str_starts_with($callbackData, 'order_')) {
        $orderId = substr($callbackData, 6);
        $order = $data['orders'][$orderId] ?? null;
        if (!is_array($order) || (string)($order['user_id'] ?? '') !== $userId) {
            editCallbackMessage($callback, 'این سفارش پیدا نشد.', $token, $apiBase, [
                [['text' => '↩️ بازگشت به سفارش‌ها', 'callback_data' => 'demo_orders']],
            ]);
            exit;
        }
        $detail = "🧾 *جزئیات سفارش*\n\n"
            . '*شماره سفارش:* ' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . "\n"
            . '*محصول:* ' . htmlspecialchars((string)($order['product_name'] ?? '-'), ENT_QUOTES, 'UTF-8') . "\n"
            . '*سایز:* ' . htmlspecialchars((string)($order['size'] ?? '-'), ENT_QUOTES, 'UTF-8') . "\n"
            . '*مبلغ:* ' . number_format((int)($order['amount'] ?? 0)) . ' ' . htmlspecialchars((string)($order['currency'] ?? 'تومان'), ENT_QUOTES, 'UTF-8') . "\n"
            . '*وضعیت:* ' . orderStatusLabel((string)($order['status'] ?? '')) . "\n"
            . '*تاریخ ثبت:* ' . htmlspecialchars(substr((string)($order['created_at'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8');
        editCallbackMessage($callback, $detail, $token, $apiBase, [
            [['text' => '↩️ بازگشت به سفارش‌ها', 'callback_data' => 'demo_orders']],
            [['text' => '🏠 منوی اصلی', 'callback_data' => 'restart']],
        ]);
        exit;
    }
    if ($callbackData === 'demo_support') {
        $supportText = "💬 *پشتیبانی و ارتباط با بازوپرداز*\n\n"
            . "برای سفارش ربات اختصاصی یا دریافت راهنمایی، از راه‌های زیر با ما در ارتباط باشید:\n\n"
            . "👨🏻‍💻 *پشتیبانی و سفارش ربات:* @botsdeveloper\n"
            . "📢 *کانال بازوپرداز:* @vipbots\n"
            . "🌐 *وب‌سایت:* [bazopardaz.ir](https://bazopardaz.ir)\n\n"
            . "_طراحی‌شده توسط بازوپرداز_";
        editCallbackMessage($callback, $supportText, $token, $apiBase, [
            [['text' => '↩️ بازگشت به منوی اصلی', 'callback_data' => 'restart']],
        ]);
        exit;
    }
    if ($callbackData === 'restart') {
        unset($data['users'][$userId]);
        saveData($storagePath, $data);
        editCallbackMessage($callback, "سلام! به فروشگاه نمایشی بازوپرداز خوش آمدید.\n\nبرای آشنایی با امکانات فروشگاه، از گزینه‌های نمایشی زیر استفاده کنید.", $token, $apiBase, [
            [['text' => '🛍 مشاهده فروشگاه', 'callback_data' => 'demo_catalog'], ['text' => '📦 سفارش‌های من', 'callback_data' => 'demo_orders']],
            [['text' => '💬 پشتیبانی', 'callback_data' => 'demo_support']],
        ]);
        exit;
    }
    if (preg_match('/^size_(\d+)_(\d+)$/', $callbackData, $match)) {
        $productId = (int)$match[1];
        $size = $match[2];
        if (!isset($products[$productId]) || !in_array($size, $products[$productId]['sizes'], true)) {
            editCallbackMessage($callback, 'این انتخاب معتبر نیست. لطفاً دوباره محصول را انتخاب کنید.', $token, $apiBase);
            exit;
        }
        $product = $products[$productId];
        $data['users'][$userId] = ['chat_id' => $chatId, 'product_id' => $productId, 'size' => $size, 'stage' => 'review', 'updated_at' => date(DATE_ATOM)];
        saveData($storagePath, $data);
        editCallbackMessage($callback, orderSummary($product, $size), $token, $apiBase, [[
            ['text' => '✅ تأیید سفارش', 'callback_data' => 'confirm_order'],
            ['text' => '↩️ تغییر سایز', 'callback_data' => 'change_size'],
        ]], 'Markdown');
        exit;
    }
    $userState = $data['users'][$userId] ?? null;
    if ($callbackData === 'change_size') {
        editCallbackMessage($callback, 'لطفاً سایز موردنظرتان را انتخاب کنید:', $token, $apiBase, sizeKeyboard($products[4]));
        exit;
    }
    if ($callbackData === 'confirm_order' && is_array($userState) && ($userState['stage'] ?? '') === 'review') {
        $product = $products[(int)$userState['product_id']];
        $orderId = newOrderId();
        $data['users'][$userId]['stage'] = 'ready_to_pay';
        $data['users'][$userId]['order_id'] = $orderId;
        $data['users'][$userId]['updated_at'] = date(DATE_ATOM);
        $data['orders'][$orderId] = [
            'order_id' => $orderId, 'user_id' => $userId, 'chat_id' => $chatId,
            'product_id' => $product['id'], 'product_name' => $product['name'],
            'size' => (string)$userState['size'], 'amount' => (int)$product['price'],
            'currency' => $product['currency'], 'status' => 'awaiting_payment', 'created_at' => date(DATE_ATOM),
        ];
        saveData($storagePath, $data);
        $payText = "سفارش شما با شماره *{$orderId}* آماده پرداخت است.\n\n"
            . '*مبلغ:* ' . number_format((int)$product['price']) . ' ' . $product['currency'] . "\n"
            . '*محصول:* ' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . "\n"
            . '*سایز:* ' . htmlspecialchars((string)$userState['size'], ENT_QUOTES, 'UTF-8') . "\n\n"
            . 'برای ادامه، دکمه «پرداخت / ارسال رسید» را بزنید. در این نسخه نمایشی، پرداخت آنلاین متصل نیست و رسید به‌صورت دستی بررسی می‌شود.';
        editCallbackMessage($callback, $payText, $token, $apiBase, [
            [['text' => '💳 پرداخت / ارسال رسید', 'callback_data' => 'pay_' . $orderId]],
            [['text' => 'لغو / شروع دوباره', 'callback_data' => 'restart']],
        ], 'Markdown');
        exit;
    }
    if (str_starts_with($callbackData, 'pay_')) {
        $orderId = substr($callbackData, 4);
        $order = $data['orders'][$orderId] ?? null;
        if (!is_array($order) || (string)$order['user_id'] !== $userId) {
            editCallbackMessage($callback, 'سفارش فعالی برای این دکمه پیدا نشد. لطفاً از ابتدا شروع کنید.', $token, $apiBase);
            exit;
        }
        $data['users'][$userId]['stage'] = 'awaiting_receipt';
        $data['users'][$userId]['updated_at'] = date(DATE_ATOM);
        $data['orders'][$orderId]['status'] = 'awaiting_receipt';
        saveData($storagePath, $data);
        editCallbackMessage($callback, "لطفاً تصویر رسید پرداخت را ارسال بفرمایید.\n\n"
            . '*مبلغ رسید:* ' . number_format((int)$order['amount']) . ' ' . $order['currency'] . "\n"
            . '*شماره سفارش:* ' . $orderId . "\n\n"
            . 'پس از دریافت تصویر، سفارش در این نسخه نمایشی ثبت می‌شود.', $token, $apiBase, null, 'Markdown');
        exit;
    }
    editCallbackMessage($callback, 'برای ادامه، از دکمه‌های همین گفتگو استفاده کنید یا /start را بفرستید.', $token, $apiBase);
    exit;
}

$message = $update['message'] ?? null;
if (!is_array($message)) {
    exit;
}
$chatId = $message['chat']['id'] ?? null;
$user = $message['from'] ?? [];
$userId = isset($user['id']) ? (string)$user['id'] : '';
$text = trim((string)($message['text'] ?? ''));
if ($chatId === null || $userId === '') {
    exit;
}

if (preg_match('/^\\/start(?:@\\w+)?(?:\\s+(.+))?$/u', $text, $startMatch)) {
    $startPayload = trim((string)($startMatch[1] ?? ''));
    unset($data['users'][$userId]);

    // Only a valid product deep-link should show product details.
    $productId = 0;
    $selectedSize = '';
    $validProductLink = false;
    if ($startPayload !== '' && preg_match('/^product(\d+)_size(\d+)$/', $startPayload, $payloadMatch)) {
        $candidateId = (int)$payloadMatch[1];
        $candidateSize = $payloadMatch[2];
        if (isset($products[$candidateId]) && in_array($candidateSize, $products[$candidateId]['sizes'], true)) {
            $productId = $candidateId;
            $selectedSize = $candidateSize;
            $validProductLink = true;
        }
    }

    if ($validProductLink) {
        $product = $products[$productId];
        $data['users'][$userId] = [
            'chat_id' => $chatId,
            'product_id' => $productId,
            'size' => $selectedSize,
            'stage' => 'review',
            'updated_at' => date(DATE_ATOM),
        ];
        saveData($storagePath, $data);

        $details = "🛍 *" . $product['name'] . "*\n\n"
            . $product['description'] . "\n\n"
            . '*قیمت:* ' . number_format((int)$product['price']) . ' ' . $product['currency'] . "\n"
            . '*سایز انتخابی:* ' . $selectedSize;
        sendMessage($chatId, $details, $token, $apiBase, [[
            ['text' => '💳 پرداخت', 'callback_data' => 'confirm_order'],
        ]], 'Markdown');
        exit;
    }

    // Normal /start and invalid/unknown deep-links show only the welcome screen.
    saveData($storagePath, $data);
    sendMessage($chatId, "سلام! به فروشگاه نمایشی بازوپرداز خوش آمدید.\n\nبرای آشنایی با امکانات فروشگاه، از گزینه‌های نمایشی زیر استفاده کنید.", $token, $apiBase, [
        [['text' => '🛍 مشاهده فروشگاه', 'callback_data' => 'demo_catalog'], ['text' => '📦 سفارش‌های من', 'callback_data' => 'demo_orders']],
        [['text' => '💬 پشتیبانی', 'callback_data' => 'demo_support']],
    ]);
    exit;
}

$state = $data['users'][$userId] ?? null;
if (isset($message['photo']) && is_array($message['photo'])) {
    if (!is_array($state) || ($state['stage'] ?? '') !== 'awaiting_receipt' || empty($state['order_id'])) {
        sendMessage($chatId, 'برای ثبت رسید، ابتدا محصول را انتخاب کنید و مراحل تأیید سفارش را انجام دهید. برای شروع /start را بفرستید.', $token, $apiBase);
        exit;
    }
    $orderId = (string)$state['order_id'];
    $order = $data['orders'][$orderId] ?? null;
    if (!is_array($order)) {
        sendMessage($chatId, 'اطلاعات سفارش پیدا نشد. لطفاً /start را بفرستید و دوباره سفارش را ثبت کنید.', $token, $apiBase);
        exit;
    }
    $data['orders'][$orderId]['status'] = 'receipt_received_demo';
    $data['orders'][$orderId]['receipt_message_id'] = $message['message_id'] ?? null;
    $data['orders'][$orderId]['receipt_received_at'] = date(DATE_ATOM);
    $data['users'][$userId]['stage'] = 'registered';
    $data['users'][$userId]['updated_at'] = date(DATE_ATOM);
    saveData($storagePath, $data);
    sendMessage($chatId, "✅ *سفارش شما ثبت شد!*\n\n"
        . '*شماره سفارش:* ' . $orderId . "\n"
        . '*محصول:* ' . htmlspecialchars((string)$order['product_name'], ENT_QUOTES, 'UTF-8') . "\n"
        . '*سایز:* ' . htmlspecialchars((string)$order['size'], ENT_QUOTES, 'UTF-8') . "\n"
        . '*مبلغ:* ' . number_format((int)$order['amount']) . ' ' . htmlspecialchars((string)$order['currency'], ENT_QUOTES, 'UTF-8') . "\n\n"
        . 'این ثبت، نمایشی است و دریافت تصویر به‌معنای تأیید واقعی پرداخت نیست.', $token, $apiBase, null, 'Markdown');

    if ($adminChatId !== '') {
        $firstName = (string)($user['first_name'] ?? '');
        $username = isset($user['username']) ? '@' . (string)$user['username'] : 'ندارد';
        $adminText = "🛍 *رسید جدید — سفارش نمایشی*\n\n"
            . '*شماره سفارش:* ' . $orderId . "\n"
            . '*محصول:* ' . htmlspecialchars((string)$order['product_name'], ENT_QUOTES, 'UTF-8') . "\n"
            . '*سایز:* ' . htmlspecialchars((string)$order['size'], ENT_QUOTES, 'UTF-8') . "\n"
            . '*مبلغ اعلام‌شده:* ' . number_format((int)$order['amount']) . ' ' . $order['currency'] . "\n"
            . '*خریدار:* ' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . "\n"
            . '*نام کاربری:* ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "\n"
            . '*شناسه کاربر:* ' . htmlspecialchars($userId, ENT_QUOTES, 'UTF-8') . "\n\n"
            . 'توجه: تصویر رسید برای نمایش فرایند دریافت شده و پرداخت واقعی تأیید نشده است.';
        sendMessage($adminChatId, $adminText, $token, $apiBase, null, 'Markdown');
        if (isset($message['message_id'])) {
            apiCall('copyMessage', ['chat_id' => $adminChatId, 'from_chat_id' => $chatId, 'message_id' => $message['message_id']], $token, $apiBase);
        }
    }
    exit;
}
if ($text !== '') {
    if (is_array($state) && ($state['stage'] ?? '') === 'awaiting_receipt') {
        sendMessage($chatId, 'لطفاً تصویر رسید را به‌صورت عکس ارسال کنید؛ سپس سفارش نمایشی ثبت می‌شود.', $token, $apiBase);
    } else {
        sendMessage($chatId, 'برای شروع سفارش، /start را بفرستید.', $token, $apiBase);
    }
}