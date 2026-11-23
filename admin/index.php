<?php
/**
 * Admin dashboard: project requests (+ the login form).
 * Hardened: CSRF on every POST, login throttling, session regeneration, safe cookie flags, idle timeout.
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/bale.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

adminStartSession();
adminHeaders();
$db = getDb();

// ---------- login ----------
$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!adminCheckCsrf()) {
        $loginError = 'نشست منقضی شده؛ دوباره امتحان کنید.';
    } else {
        list($ok, $err) = adminAttemptLogin(
            $db,
            trim(isset($_POST['username']) ? (string)$_POST['username'] : ''),
            isset($_POST['password']) ? (string)$_POST['password'] : ''
        );
        if ($ok) {
            header('Location: /admin/');
            exit;
        }
        $loginError = $err;
    }
}

if (!adminIsLoggedIn()) {
    adminPageStart('ورود', '', array('bare' => true));
    ?>
    <div class="login-box">
        <h1>ورود به مدیریت</h1>
        <?php if (!empty($_GET['expired'])): ?><div class="notice notice--warn">نشست شما تمام شده؛ دوباره وارد شوید.</div><?php endif; ?>
        <?php if ($loginError): ?><div class="notice notice--error" role="alert"><?= h($loginError) ?></div><?php endif; ?>
        <form method="post" autocomplete="on">
            <?= adminCsrfField() ?>
            <div class="form-group"><label class="form-label" for="u">نام کاربری</label>
                <input class="form-input" id="u" name="username" type="text" autocomplete="username" dir="ltr" required autofocus></div>
            <div class="form-group"><label class="form-label" for="p">رمز عبور</label>
                <input class="form-input" id="p" name="password" type="password" autocomplete="current-password" dir="ltr" required></div>
            <button class="btn btn--primary btn--block" type="submit" name="login" value="1">ورود</button>
        </form>
    </div>
    <?php
    adminPageEnd();
    exit;
}

// ---------- manual retry of a failed Bale notification ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry'], $_POST['request_id'])) {
    adminRequireCsrf();
    $requestId = (int)$_POST['request_id'];
    $stmt = $db->prepare('SELECT * FROM project_requests WHERE id = :id');
    $stmt->execute(array(':id' => $requestId));
    $request = $stmt->fetch();
    if ($request) {
        $result = baleSendMessage(formatBaleMessage($request), baleRequestButtons($request));
        if ($result['success']) {
            $db->prepare("UPDATE project_requests SET status = 'sent', sent_at = NOW(), attempts = attempts + 1, last_attempt_at = NOW(), error_message = NULL WHERE id = :id")
                ->execute(array(':id' => $requestId));
            adminFlash('ok', 'درخواست #' . $requestId . ' با موفقیت در بله ارسال شد.');
        } else {
            $db->prepare('UPDATE project_requests SET attempts = attempts + 1, last_attempt_at = NOW(), error_message = :e WHERE id = :id')
                ->execute(array(':e' => mb_substr((string)$result['error'], 0, 900), ':id' => $requestId));
            adminFlash('error', 'خطا در ارسال: ' . $result['error']);
        }
    }
    header('Location: /admin/' . (isset($_GET['filter']) ? '?filter=' . urlencode((string)$_GET['filter']) : ''));
    exit;
}

// ---------- data ----------
$filter = isset($_GET['filter']) ? (string)$_GET['filter'] : 'all';
if (!in_array($filter, array('all', 'pending', 'sent', 'failed'), true)) {
    $filter = 'all';
}
if ($filter === 'all') {
    $requests = $db->query('SELECT * FROM project_requests ORDER BY created_at DESC LIMIT 100')->fetchAll();
} else {
    $st = $db->prepare('SELECT * FROM project_requests WHERE status = :s ORDER BY created_at DESC LIMIT 100');
    $st->execute(array(':s' => $filter));
    $requests = $st->fetchAll();
}
$counts = array('pending' => 0, 'sent' => 0, 'failed' => 0);
foreach ($db->query('SELECT status, COUNT(*) AS c FROM project_requests GROUP BY status')->fetchAll() as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['c'];
    }
}
$counts['all'] = $counts['pending'] + $counts['sent'] + $counts['failed'];

$contactMethods = array('telegram' => 'تلگرام', 'bale' => 'بله', 'whatsapp' => 'واتساپ', 'email' => 'ایمیل', 'phone' => 'تلفن', 'other' => 'سایر');
$projectTypes = array('telegram_bot' => 'ربات تلگرام', 'bale_bot' => 'ربات بله', 'rubika_bot' => 'ربات روبیکا', 'discord_bot' => 'ربات دیسکورد',
    'mini_app' => 'مینی‌اپ تلگرام', 'website' => 'طراحی وبسایت', 'automation' => 'اتوماسیون / API', 'other' => 'سایر');
$statusLabels = array('pending' => 'در انتظار', 'sent' => 'ارسال‌شده', 'failed' => 'ناموفق');
$statusClass = array('pending' => 'tag', 'sent' => 'tag tag--success', 'failed' => 'tag tag--orange');
$filterLabels = array('all' => 'همه', 'pending' => 'در انتظار', 'sent' => 'ارسال‌شده', 'failed' => 'ناموفق');

adminPageStart('درخواست‌ها', 'requests');
foreach (adminSecurityNotes($db) as $note) {
    echo '<div class="notice notice--error" role="alert">' . h($note) . '</div>';
}
?>
<h1 class="admin-title">درخواست‌های پروژه</h1>
<nav class="filter-row" aria-label="فیلتر وضعیت">
    <?php foreach ($filterLabels as $key => $label): ?>
        <a class="chip-btn" href="/admin/?filter=<?= h($key) ?>" <?= $filter === $key ? 'aria-pressed="true"' : 'aria-pressed="false"' ?>>
            <?= h($label) ?> <span class="count"><?= (int)$counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if (!$requests): ?>
    <div class="empty">درخواستی در این بخش نیست.</div>
<?php endif; ?>

<div class="request-list">
<?php foreach ($requests as $r):
    $method = (string)$r['contact_method'];
    $raw = html_entity_decode((string)$r['contact_value'], ENT_QUOTES, 'UTF-8');
    $chatUrl = '';
    if (($method === 'bale' || $method === 'telegram') && preg_match('/^(?:https?:\/\/(?:t\.me|ble\.ir)\/|@)?([A-Za-z][A-Za-z0-9_]{3,31})$/', $raw, $m)) {
        $chatUrl = ($method === 'bale' ? 'https://ble.ir/' : 'https://t.me/') . $m[1];
    } elseif ($method === 'whatsapp') {
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = '98' . substr($digits, 1);
        }
        if (strlen($digits) >= 10) {
            $chatUrl = 'https://wa.me/' . $digits;
        }
    }
    $st = isset($statusLabels[$r['status']]) ? $r['status'] : 'pending';
    ?>
    <article class="request-card">
        <header>
            <strong>#<?= (int)$r['id'] ?> · <?= hd($r['name']) ?></strong>
            <span class="<?= h($statusClass[$st]) ?>"><?= h($statusLabels[$st]) ?></span>
        </header>
        <dl>
            <div><dt>نوع پروژه</dt><dd><?= h(isset($projectTypes[$r['project_type']]) ? $projectTypes[$r['project_type']] : $r['project_type']) ?></dd></div>
            <div><dt>بودجه</dt><dd><?= $r['budget'] !== null && $r['budget'] !== '' ? hd($r['budget']) : 'مشخص نشده' ?></dd></div>
            <div><dt>راه تماس (<?= h(isset($contactMethods[$method]) ? $contactMethods[$method] : $method) ?>)</dt>
                <dd dir="auto"><?= hd($r['contact_value']) ?></dd></div>
            <div><dt>ثبت</dt><dd><?= h($r['created_at']) ?></dd></div>
        </dl>
        <p class="request-desc"><?= nl2br(hd($r['description'])) ?></p>
        <?php if (!empty($r['error_message']) && $st !== 'sent'): ?>
            <p class="request-error">خطای ارسال به بله: <?= h($r['error_message']) ?> (تلاش‌ها: <?= (int)$r['attempts'] ?>)</p>
        <?php endif; ?>
        <footer>
            <?php if ($chatUrl !== ''): ?>
                <a class="btn btn--outline" href="<?= h($chatUrl) ?>" target="_blank" rel="noopener noreferrer">گفتگو با مشتری</a>
            <?php endif; ?>
            <?php if ($st !== 'sent'): ?>
                <form method="post" action="/admin/?filter=<?= h($filter) ?>" class="inline-form">
                    <?= adminCsrfField() ?>
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn--primary" type="submit" name="retry" value="1">ارسال مجدد به بله</button>
                </form>
            <?php endif; ?>
        </footer>
    </article>
<?php endforeach; ?>
</div>
<?php adminPageEnd(); ?>
