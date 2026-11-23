<?php
/**
 * Cron Job: Send Pending Project Requests to Bale
 * 
 * This script should be run every ~5 minutes via hosting Cron.
 * 
 * Usage from hosting control panel:
 *   php /path/to/project/cron/send-pending-requests.php
 * 
 * Or via URL (with secret key protection):
 *   https://yourdomain.com/cron/send-pending-requests.php?key=YOUR_SECRET_KEY
 * 
 * Architecture: MySQL (pending) → Cron → Bale Bot → status = sent
 */

// Security: Only allow CLI or secret key access
$isCli = (php_sapi_name() === 'cli');
$secretKey = $_GET['key'] ?? '';

if (!$isCli) {
    // Web access requires secret key
    require_once dirname(__DIR__) . '/includes/db.php';
    $config = getDbConfig();
    
    if ($secretKey !== $config['cron_secret_key']) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    
    // Prevent indexing
    header('X-Robots-Tag: noindex');
}

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/bale.php';

$config = getDbConfig();
$db = getDb();

// Process up to 10 requests per execution
$batchSize = 10;

// Find pending requests (oldest first)
$stmt = $db->prepare("
    SELECT * FROM project_requests 
    WHERE status = 'pending' 
    AND attempts < :max_attempts
    AND created_at < (NOW() - INTERVAL 2 MINUTE)
    ORDER BY created_at ASC 
    LIMIT :batch_size
");
$stmt->bindValue(':max_attempts', $config['max_retry_attempts'], PDO::PARAM_INT);
$stmt->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll();

if (empty($requests)) {
    if ($isCli) {
        echo "No pending requests to process.\n";
    }
    exit;
}

$successCount = 0;
$failCount = 0;

foreach ($requests as $request) {
    // Format the Bale message
    $message = formatBaleMessage($request);
    
    // Try to send via Bale
    $result = baleSendMessage($message, baleRequestButtons($request));
    
    if ($result['success']) {
        // Mark as sent
        $update = $db->prepare("
            UPDATE project_requests 
            SET status = 'sent', 
                sent_at = NOW(), 
                attempts = attempts + 1,
                last_attempt_at = NOW()
            WHERE id = :id
        ");
        $update->execute([':id' => $request['id']]);
        $successCount++;
    } else {
        // Increment attempts and store error
        $newAttempts = $request['attempts'] + 1;
        $errorMsg = $result['error'];
        
        if ($newAttempts >= $config['max_retry_attempts']) {
            // Max retries reached - mark as failed
            $update = $db->prepare("
                UPDATE project_requests 
                SET status = 'failed', 
                    attempts = :attempts,
                    last_attempt_at = NOW(),
                    error_message = :error
                WHERE id = :id
            ");
            $update->execute([
                ':attempts' => $newAttempts,
                ':error'    => $errorMsg,
                ':id'       => $request['id'],
            ]);
        } else {
            // Will retry on next cron run
            $update = $db->prepare("
                UPDATE project_requests 
                SET attempts = :attempts,
                    last_attempt_at = NOW(),
                    error_message = :error
                WHERE id = :id
            ");
            $update->execute([
                ':attempts' => $newAttempts,
                ':error'    => $errorMsg,
                ':id'       => $request['id'],
            ]);
        }
        $failCount++;
    }
}

if ($isCli) {
    echo "Processed " . count($requests) . " requests: {$successCount} sent, {$failCount} failed.\n";
}
