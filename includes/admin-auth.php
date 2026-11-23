<?php
/**
 * Shared helpers for the admin area (requests dashboard + article editor).
 *
 *  - hardened session cookie (HttpOnly, SameSite, Secure on https), own cookie name, strict mode
 *  - CSRF tokens for every POST
 *  - login throttling (5 failures / 15 minutes per IP), session id regenerated on login
 *  - idle timeout (30 min, refreshed while you work) and an absolute limit (8 h)
 *  - security headers and the shared page chrome
 *
 * Written for PHP 7.2+.
 */

const ADMIN_IDLE_LIMIT = 1800;       // 30 minutes without any request
const ADMIN_ABSOLUTE_LIMIT = 28800;  // 8 hours since login
const ADMIN_MAX_FAILURES = 5;
const ADMIN_LOCK_SECONDS = 900;

function adminIsHttps()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function adminStartSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bp_admin');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(array('lifetime' => 0, 'path' => '/admin/', 'secure' => adminIsHttps(),
            'httponly' => true, 'samesite' => 'Lax'));
    } else {
        session_set_cookie_params(0, '/admin/; samesite=Lax', '', adminIsHttps(), true);
    }
    session_start();
}

function adminHeaders()
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Values saved by the public form are already HTML-escaped; decode once, then escape for output. */
function hd($s)
{
    return h(html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8'));
}

// ------------------------------------------------------------------------------------------------ CSRF

function adminCsrfToken()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function adminCsrfField()
{
    return '<input type="hidden" name="csrf" value="' . h(adminCsrfToken()) . '">';
}

function adminCheckCsrf()
{
    $given = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $given);
}

function adminRequireCsrf($json = false)
{
    if (adminCheckCsrf()) {
        return;
    }
    http_response_code(400);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'نشست منقضی شده؛ صفحه را دوباره باز کنید.'), JSON_UNESCAPED_UNICODE);
    } else {
        echo 'درخواست نامعتبر است (CSRF). صفحه را دوباره باز کنید.';
    }
    exit;
}

// ------------------------------------------------------------------------------------------------ throttling

function adminPrivateDir()
{
    $candidates = array(dirname(__DIR__, 2) . '/secure', sys_get_temp_dir());
    foreach ($candidates as $d) {
        if (is_dir($d) && is_writable($d)) {
            return $d;
        }
    }
    return null;
}

function adminThrottleLoad()
{
    $dir = adminPrivateDir();
    if ($dir === null) {
        return array(null, array());
    }
    $file = $dir . '/bp-login-attempts.json';
    $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : array();
    return array($file, is_array($data) ? $data : array());
}

function adminThrottleKey()
{
    return hash('sha256', isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
}

/** @return int seconds the visitor still has to wait, 0 when login is allowed */
function adminLockedFor()
{
    list($file, $data) = adminThrottleLoad();
    $k = adminThrottleKey();
    $now = time();
    $recent = array();
    if (isset($data[$k]) && is_array($data[$k])) {
        foreach ($data[$k] as $t) {
            if ($now - (int)$t < ADMIN_LOCK_SECONDS) {
                $recent[] = (int)$t;
            }
        }
    }
    if (count($recent) >= ADMIN_MAX_FAILURES) {
        return max(1, ADMIN_LOCK_SECONDS - ($now - max($recent)));
    }
    return 0;
}

function adminRecordFailure()
{
    list($file, $data) = adminThrottleLoad();
    if ($file === null) {
        return;
    }
    $k = adminThrottleKey();
    $now = time();
    foreach ($data as $key => $times) {  // forget old entries of everybody
        $data[$key] = array_values(array_filter((array)$times, function ($t) use ($now) {
            return $now - (int)$t < ADMIN_LOCK_SECONDS;
        }));
        if (!$data[$key]) {
            unset($data[$key]);
        }
    }
    $data[$k][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function adminClearFailures()
{
    list($file, $data) = adminThrottleLoad();
    if ($file === null) {
        return;
    }
    unset($data[adminThrottleKey()]);
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

// ------------------------------------------------------------------------------------------------ login / logout

/** @return array(bool ok, string|null error) */
function adminAttemptLogin(PDO $db, $username, $password)
{
    $wait = adminLockedFor();
    if ($wait > 0) {
        return array(false, 'تعداد تلاش‌های ناموفق زیاد بود. حدود ' . ceil($wait / 60) . ' دقیقهٔ دیگر دوباره امتحان کنید.');
    }
    $stmt = $db->prepare('SELECT * FROM admin_users WHERE username = :u');
    $stmt->execute(array(':u' => $username));
    $user = $stmt->fetch();
    // verify against a dummy hash when the user does not exist, so timing does not reveal valid usernames
    static $dummy = null;
    if ($dummy === null) {
        $dummy = password_hash('not-a-real-password', PASSWORD_DEFAULT);
    }
    $ok = password_verify($password, $user ? $user['password_hash'] : $dummy) && $user;
    if (!$ok) {
        adminRecordFailure();
        usleep(500000);
        return array(false, 'نام کاربری یا رمز عبور اشتباه است.');
    }
    adminClearFailures();
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $user['username'];
    $_SESSION['admin_login_time'] = time();
    $_SESSION['admin_last'] = time();
    unset($_SESSION['csrf']);
    return array(true, null);
}

function adminLogout()
{
    $_SESSION = array();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** True when a valid, non-expired admin session exists (and refreshes the idle timer). */
function adminIsLoggedIn()
{
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }
    $now = time();
    if ($now - (isset($_SESSION['admin_last']) ? (int)$_SESSION['admin_last'] : 0) > ADMIN_IDLE_LIMIT
        || $now - (isset($_SESSION['admin_login_time']) ? (int)$_SESSION['admin_login_time'] : 0) > ADMIN_ABSOLUTE_LIMIT) {
        adminLogout();
        return false;
    }
    $_SESSION['admin_last'] = $now;
    return true;
}

function adminRequireLogin($json = false)
{
    if (adminIsLoggedIn()) {
        return;
    }
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'نشست شما تمام شده؛ دوباره وارد شوید.'), JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: /admin/?expired=1');
    exit;
}

function adminFlash($type, $message)
{
    $_SESSION['flash'][] = array($type, $message);
}

function adminTakeFlash()
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : array();
    unset($_SESSION['flash']);
    return $f;
}

/** Warnings shown on every admin page (default password, leftover setup file...). */
function adminSecurityNotes(PDO $db)
{
    $notes = array();
    if (is_file(dirname(__DIR__) . '/admin/setup.php')) {
        $notes[] = 'فایل admin/setup.php هنوز روی سرور است؛ فوراً حذفش کنید.';
    }
    try {
        $stmt = $db->prepare('SELECT password_hash FROM admin_users WHERE username = :u');
        $stmt->execute(array(':u' => isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : ''));
        $row = $stmt->fetch();
        if ($row && strpos((string)$row['password_hash'], 'placeholder') !== false) {
            $notes[] = 'رمز عبور هنوز تنظیم نشده است؛ از «رمز عبور» آن را عوض کنید.';
        }
    } catch (Exception $e) {
        // ignore
    }
    return $notes;
}

// ------------------------------------------------------------------------------------------------ page chrome

function adminPageStart($title, $active = '', array $opts = array())
{
    $nav = array('requests' => array('/admin/', 'درخواست‌ها'), 'posts' => array('/admin/posts.php', 'مقالات'),
        'password' => array('/admin/password.php', 'رمز عبور'));
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<meta name="robots" content="noindex, nofollow"><meta name="color-scheme" content="dark light"><meta name="theme-color" content="#0b0b14">'
        . '<script src="/js/theme-init.js"></script>'
        . '<title>' . h($title) . ' | مدیریت بازوپرداز</title>'
        . '<link rel="stylesheet" href="/css/style.css"><link rel="stylesheet" href="/css/pages/admin.css">';
    if (!empty($opts['blog_css'])) {
        echo '<link rel="stylesheet" href="/css/pages/blog.css">';
    }
    echo '</head><body class="admin no-cta-bar">';
    if (empty($opts['bare'])) {
        echo '<header class="admin-bar"><div class="admin-bar-inner">'
            . '<a class="admin-brand" href="/admin/">مدیریت بازوپرداز</a><nav class="admin-nav" aria-label="منوی مدیریت">';
        foreach ($nav as $key => $item) {
            echo '<a href="' . h($item[0]) . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . h($item[1]) . '</a>';
        }
        echo '<button type="button" class="theme-toggle" id="themeToggle" aria-pressed="false" aria-label="تغییر به حالت روشن" title="تغییر به حالت روشن">'
            . '<svg class="icon icon-sun" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4"/></svg>'
            . '<svg class="icon icon-moon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 14.2A8 8 0 0 1 9.8 4 8.2 8.2 0 1 0 20 14.2Z"/></svg></button>';
        echo '<a href="/" target="_blank" rel="noopener">مشاهدهٔ سایت</a>'
            . '<form method="post" action="/admin/logout.php" class="inline-form">' . adminCsrfField()
            . '<button type="submit" class="link-btn">خروج</button></form></nav></div></header>';
    }
    echo '<main class="admin-main">';
    foreach (adminTakeFlash() as $f) {
        echo '<div class="notice notice--' . h($f[0]) . '" role="status">' . h($f[1]) . '</div>';
    }
}

function adminPageEnd($scripts = array())
{
    echo '</main><script src="/js/site.js" defer></script>';
    foreach ($scripts as $s) {
        echo '<script src="' . h($s) . '" defer></script>';
    }
    echo '</body></html>';
}
