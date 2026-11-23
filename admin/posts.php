<?php
/** Article manager: list, delete, rebuild all pages, health check. */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/blog-engine.php';

adminStartSession();
adminHeaders();

if (isset($_GET['ping'])) {          // keep-alive from the editor while you are writing
    adminRequireLogin(true);
    http_response_code(204);
    exit;
}
adminRequireLogin();
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf();
    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $slug = isset($_POST['slug']) ? (string)$_POST['slug'] : '';
    try {
        if ($action === 'delete' && bpValidSlug($slug) && is_file(bpPostPath($slug))) {
            bpDeletePostFile($slug);
            bpRemovePage($slug);
            $notes = bpPublishAll();
            adminFlash('ok', 'مقاله حذف شد (یک نسخهٔ پشتیبان در blog-data/backup می‌ماند). ' . implode(' · ', $notes));
        } elseif ($action === 'rebuild') {
            adminFlash('ok', implode(' · ', bpPublishAll()));
        }
    } catch (Throwable $e) {
        adminFlash('error', 'خطا: ' . $e->getMessage());
    }
    header('Location: /admin/posts.php');
    exit;
}

$posts = array();
$loadError = '';
try {
    $posts = bpListPosts();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$checks = array(
    'قالب صفحه (includes/blog-shell.html)' => is_file(dirname(__DIR__) . '/includes/blog-shell.html'),
    'تنظیمات بلاگ (includes/blog-config.json)' => is_file(dirname(__DIR__) . '/includes/blog-config.json'),
    'پوشهٔ مقاله‌ها قابل نوشتن است (blog-data)' => is_dir(bpDataDir()) && is_writable(bpDataDir()),
    'پوشهٔ blog قابل نوشتن است' => is_dir(bpRoot() . '/blog') && is_writable(bpRoot() . '/blog'),
    'sitemap.xml قابل نوشتن است' => is_file(bpRoot() . '/sitemap.xml') && is_writable(bpRoot() . '/sitemap.xml'),
    'index.html صفحهٔ اصلی قابل نوشتن است' => is_file(bpRoot() . '/index.html') && is_writable(bpRoot() . '/index.html'),
);
$allOk = !in_array(false, $checks, true);

adminPageStart('مقالات', 'posts');
foreach (adminSecurityNotes($db) as $note) {
    echo '<div class="notice notice--error" role="alert">' . h($note) . '</div>';
}
?>
<div class="admin-head">
    <h1 class="admin-title">مقالات</h1>
    <a class="btn btn--primary" href="/admin/post-edit.php">+ مقالهٔ جدید</a>
</div>

<?php if ($loadError): ?><div class="notice notice--error"><?= h($loadError) ?></div><?php endif; ?>
<?php if (!$posts): ?><div class="empty">هنوز مقاله‌ای نیست.</div><?php endif; ?>

<div class="post-rows">
<?php foreach ($posts as $p): $live = $p['status'] !== 'draft'; ?>
    <article class="post-row">
        <div class="post-row-main">
            <h2><?= h($p['title']) ?></h2>
            <p class="post-row-meta">
                <span class="<?= $live ? 'tag tag--success' : 'tag tag--orange' ?>"><?= $live ? 'منتشرشده' : 'پیش‌نویس' ?></span>
                <span><?= h($p['category']) ?></span>
                <?php if ($p['published'] !== ''): ?><span><?= h(bpFaDate($p['published'])) ?></span><?php endif; ?>
                <span dir="ltr">/blog/<?= h($p['slug']) ?>/</span>
            </p>
        </div>
        <div class="post-row-actions">
            <a class="btn btn--outline" href="/admin/post-edit.php?slug=<?= h(urlencode($p['slug'])) ?>">ویرایش</a>
            <?php if ($live): ?><a class="btn btn--outline" href="/blog/<?= h($p['slug']) ?>/" target="_blank" rel="noopener">مشاهده</a><?php endif; ?>
            <form method="post" class="inline-form" onsubmit="return confirm('این مقاله حذف شود؟ (پشتیبان نگه داشته می‌شود)');">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="slug" value="<?= h($p['slug']) ?>">
                <button class="btn btn--outline btn--danger" type="submit">حذف</button>
            </form>
        </div>
    </article>
<?php endforeach; ?>
</div>

<section class="card admin-section">
    <h2>وضعیت سیستم مقالات</h2>
    <ul class="checklist">
        <?php foreach ($checks as $label => $ok): ?>
            <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✕' ?> <?= h($label) ?></li>
        <?php endforeach; ?>
    </ul>
    <div class="btn-row-start">
        <form method="post" class="inline-form">
            <?= adminCsrfField() ?><input type="hidden" name="action" value="rebuild">
            <button class="btn btn--outline" type="submit">بازسازی همهٔ صفحات مقالات</button>
        </form>
        <a class="btn btn--outline" href="/admin/blog-selftest.php">اجرای تست خودکار</a>
    </div>
    <?php if (!$allOk): ?><p class="hint">مورد ✕ را رفع کنید (معمولاً دسترسی پوشه 755 یا فایل 644 و مالکیت درست) وگرنه انتشار کار نمی‌کند.</p><?php endif; ?>
</section>
<?php adminPageEnd(); ?>
