<?php
/** Logout: POST + CSRF only (a plain link cannot log you out). */
require_once dirname(__DIR__) . '/includes/admin-auth.php';

adminStartSession();
adminHeaders();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && adminCheckCsrf()) {
    adminLogout();
}
header('Location: /admin/');
exit;
