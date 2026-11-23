<?php
/** Change the admin password. */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

adminStartSession();
adminHeaders();
adminRequireLogin();
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf();
    $current = isset($_POST['current']) ? (string)$_POST['current'] : '';
    $new = isset($_POST['new']) ? (string)$_POST['new'] : '';
    $again = isset($_POST['again']) ? (string)$_POST['again'] : '';
    $stmt = $db->prepare('SELECT * FROM admin_users WHERE username = :u');
    $stmt->execute(array(':u' => isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : ''));
    $user = $stmt->fetch();
    if (!$user || !password_verify($current, $user['password_hash'])) {
        adminFlash('error', 'رمز فعلی درست نیست.');
    } elseif (mb_strlen($new, 'UTF-8') < 12) {
        adminFlash('error', 'رمز جدید باید حداقل ۱۲ کاراکتر باشد.');
    } elseif ($new !== $again) {
        adminFlash('error', 'تکرار رمز جدید با رمز جدید یکی نیست.');
    } elseif ($new === $current) {
        adminFlash('error', 'رمز جدید باید با رمز فعلی فرق داشته باشد.');
    } else {
        $db->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id')
            ->execute(array(':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user['id']));
        session_regenerate_id(true);
        adminFlash('ok', 'رمز عبور تغییر کرد.');
    }
    header('Location: /admin/password.php');
    exit;
}

adminPageStart('رمز عبور', 'password');
?>
<h1 class="admin-title">تغییر رمز عبور</h1>
<form method="post" class="card admin-form" autocomplete="off">
    <?= adminCsrfField() ?>
    <div class="form-group"><label class="form-label" for="c">رمز فعلی</label>
        <input class="form-input" id="c" name="current" type="password" dir="ltr" autocomplete="current-password" required></div>
    <div class="form-group"><label class="form-label" for="n">رمز جدید (حداقل ۱۲ کاراکتر)</label>
        <input class="form-input" id="n" name="new" type="password" dir="ltr" autocomplete="new-password" minlength="12" required></div>
    <div class="form-group"><label class="form-label" for="a">تکرار رمز جدید</label>
        <input class="form-input" id="a" name="again" type="password" dir="ltr" autocomplete="new-password" minlength="12" required></div>
    <button class="btn btn--primary" type="submit">ذخیرهٔ رمز جدید</button>
</form>
<?php adminPageEnd(); ?>
