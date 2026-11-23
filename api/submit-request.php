<?php
/**
 * Project Request Submission API
 * 
 * Architecture: Client → PHP → MySQL (pending) → Cron → Bale
 * 
 * This endpoint:
 * 1. Validates the incoming form data
 * 2. Saves to MySQL with status = 'pending'
 * 3. Returns success to the client immediately
 * 4. Notifies the admin on Bale right after responding (best effort)
 * 5. The Cron job retries any request that is still 'pending'
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Load configuration and database
require_once dirname(__DIR__) . '/includes/db.php';
$config = getDbConfig();

// --- Rate Limiting ---
session_start();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'last_submit_' . md5($ip);
$lastSubmit = $_SESSION[$rateKey] ?? 0;
$now = time();

if (($now - $lastSubmit) < $config['rate_limit_seconds']) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'لطفاً کمی صبر کنید و دوباره تلاش کنید.']);
    exit;
}

// --- Honeypot Check ---
if (!empty($_POST['website_url'])) {
    // Bot detected - pretend success
    echo json_encode(['success' => true, 'message' => 'درخواست شما ثبت شد.']);
    exit;
}

// --- Validate Required Fields ---
$name = trim($_POST['name'] ?? '');
$contactMethod = trim($_POST['contact_method'] ?? '');
$contactValue = trim($_POST['contact_value'] ?? '');
$projectType = trim($_POST['project_type'] ?? '');
$budget = trim($_POST['budget'] ?? '');
$description = trim($_POST['description'] ?? '');

$errors = [];

if (empty($name) || mb_strlen($name) < 2) {
    $errors[] = 'نام الزامی است.';
}

$validContactMethods = ['telegram', 'bale', 'whatsapp', 'email', 'phone', 'other'];
if (!in_array($contactMethod, $validContactMethods)) {
    $errors[] = 'روش تماس نامعتبر است.';
}

if (empty($contactValue) || mb_strlen($contactValue) < 3) {
    $errors[] = 'اطلاعات تماس الزامی است.';
}

$validProjectTypes = ['telegram_bot', 'bale_bot', 'rubika_bot', 'discord_bot', 'mini_app', 'website', 'automation', 'other'];
if (!in_array($projectType, $validProjectTypes)) {
    $errors[] = 'نوع پروژه نامعتبر است.';
}

if (empty($description) || mb_strlen($description) < 10) {
    $errors[] = 'توضیحات پروژه باید حداقل ۱۰ کاراکتر باشد.';
}

// Validate description length (max 5000 chars)
if (mb_strlen($description) > 5000) {
    $errors[] = 'توضیحات پروژه نمی‌تواند بیش از ۵۰۰۰ کاراکتر باشد.';
}

// Validate input sizes
if (mb_strlen($name) > 200) {
    $errors[] = 'نام نمی‌تواند بیش از ۲۰۰ کاراکتر باشد.';
}

if (mb_strlen($contactValue) > 500) {
    $errors[] = 'اطلاعات تماس نمی‌تواند بیش از ۵۰۰ کاراکتر باشد.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// --- Sanitize Data ---
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$contactValue = htmlspecialchars($contactValue, ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
$budget = htmlspecialchars($budget, ENT_QUOTES, 'UTF-8');

// --- Save to MySQL (FIRST!) ---
try {
    $db = getDb();
    
    $sql = "INSERT INTO project_requests 
            (name, contact_method, contact_value, project_type, budget, description, ip_address, status, created_at) 
            VALUES (:name, :contact_method, :contact_value, :project_type, :budget, :description, :ip_address, 'pending', NOW())";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':name'           => $name,
        ':contact_method' => $contactMethod,
        ':contact_value'  => $contactValue,
        ':project_type'   => $projectType,
        ':budget'         => $budget,
        ':description'    => $description,
        ':ip_address'     => $ip,
    ]);
    
    $requestId = $db->lastInsertId();
    
    // Update rate limit
    $_SESSION[$rateKey] = $now;
    
    // Return success to the visitor first (never make them wait for Bale)...
    $body = json_encode([
        'success'    => true,
        'message'    => 'درخواست شما با موفقیت ثبت شد. اطلاعات پروژه دریافت شد و پس از بررسی با شما تماس خواهم گرفت.',
        'request_id' => $requestId,
    ]);
    ignore_user_abort(true);
    session_write_close();
    header('Connection: close');
    header('Content-Length: ' . strlen($body));
    echo $body;
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        flush();
    }

    // ...then notify the admin on Bale right away. This is best effort: if it fails, the row stays
    // 'pending' and cron/send-pending-requests.php keeps retrying, exactly as before.
    try {
        require_once dirname(__DIR__) . '/includes/bale.php';
        $rowStmt = $db->prepare('SELECT * FROM project_requests WHERE id = :id');
        $rowStmt->execute([':id' => $requestId]);
        $row = $rowStmt->fetch();
        if ($row) {
            $res = baleSendMessage(formatBaleMessage($row), baleRequestButtons($row));
            if ($res['success']) {
                $db->prepare("UPDATE project_requests SET status = 'sent', sent_at = NOW(), attempts = attempts + 1, last_attempt_at = NOW(), error_message = NULL WHERE id = :id")
                   ->execute([':id' => $requestId]);
            } else {
                $db->prepare('UPDATE project_requests SET attempts = attempts + 1, last_attempt_at = NOW(), error_message = :err WHERE id = :id')
                   ->execute([':err' => mb_substr((string)$res['error'], 0, 900), ':id' => $requestId]);
                error_log('submit-request: Bale notification failed: ' . $res['error']);
            }
        }
    } catch (Throwable $e) {
        error_log('submit-request: Bale notification exception: ' . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log("Database error in submit-request.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ثبت درخواست. لطفاً دوباره تلاش کنید.',
    ]);
}
