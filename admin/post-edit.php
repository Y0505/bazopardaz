<?php
/** Article editor: create / edit / publish. Saving a published article regenerates the static pages. */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/blog-engine.php';

adminStartSession();
adminHeaders();
adminRequireLogin();
$db = getDb();

try {
    $cfg = bpConfig();
} catch (Throwable $e) {
    adminPageStart('ویرایشگر', 'posts');
    echo '<div class="notice notice--error">' . h($e->getMessage()) . '</div>';
    adminPageEnd();
    exit;
}

// ---------- load the article being edited ----------
$slugParam = isset($_GET['slug']) ? (string)$_GET['slug'] : '';
$existing = null;
$existingBody = '';
if ($slugParam !== '') {
    if (!bpValidSlug($slugParam) || !is_file(bpPostPath($slugParam))) {
        adminFlash('error', 'مقاله پیدا نشد.');
        header('Location: /admin/posts.php');
        exit;
    }
    try {
        list($existing, $existingBody) = bpReadPostFile(bpPostPath($slugParam));
    } catch (Throwable $e) {
        adminFlash('error', $e->getMessage());
        header('Location: /admin/posts.php');
        exit;
    }
    $existing['slug'] = $slugParam;
}

// ---------- form values (defaults -> existing -> posted) ----------
$v = array('slug' => '', 'title' => '', 'description' => '', 'excerpt' => '', 'category' => '', 'category_slug' => '',
    'icon' => 'doc', 'published' => date('Y-m-d'), 'cta_type' => 'other', 'service' => '', 'keywords' => '', 'image' => '',
    'body' => '', 'faq' => array(), 'status' => 'published');
if ($existing) {
    foreach (array('slug', 'title', 'description', 'excerpt', 'category', 'category_slug', 'icon', 'published', 'cta_type', 'image', 'status') as $k) {
        if (isset($existing[$k])) {
            $v[$k] = $existing[$k];
        }
    }
    $v['service'] = isset($existing['service']['href']) ? $existing['service']['href'] : '';
    $v['keywords'] = isset($existing['keywords']) && is_array($existing['keywords']) ? implode('، ', $existing['keywords']) : '';
    $v['faq'] = isset($existing['faq']) && is_array($existing['faq']) ? $existing['faq'] : array();
    $v['body'] = trim($existingBody);
}

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequireCsrf();
    list($meta, $bodyIn, $errors) = bpFromInput($_POST, $existing);
    if (!$errors) {
        $slug = $meta['slug'];
        $prevExisted = is_file(bpPostPath($slug));
        try {
            bpWritePostFile($slug, $meta, $bodyIn);
            $wasLive = $existing && ((isset($existing['status']) ? $existing['status'] : 'published') !== 'draft');
            if ($meta['status'] === 'published' || $wasLive) {
                $notes = bpPublishAll();
                adminFlash('ok', ($meta['status'] === 'published' ? 'مقاله منتشر شد. ' : 'مقاله به پیش‌نویس برگشت و از سایت برداشته شد. ') . implode(' · ', $notes));
            } else {
                adminFlash('ok', 'پیش‌نویس ذخیره شد.');
            }
            header('Location: /admin/post-edit.php?slug=' . urlencode($slug));
            exit;
        } catch (Throwable $e) {
            $backup = bpDataDir() . '/backup/' . $slug . '.post.php';
            if ($prevExisted && is_file($backup)) {
                @copy($backup, bpPostPath($slug));
            } elseif (!$prevExisted) {
                @unlink(bpPostPath($slug));
            }
            $errors[] = 'ذخیره/انتشار ناموفق بود و تغییرات برگردانده شد: ' . $e->getMessage();
        }
    }
    // keep what the person typed
    foreach (array('slug', 'title', 'description', 'excerpt', 'category', 'category_slug', 'icon', 'published', 'cta_type', 'service', 'keywords', 'image', 'body') as $k) {
        if (isset($_POST[$k]) && is_string($_POST[$k])) {
            $v[$k] = $_POST[$k];
        }
    }
    $v['faq'] = array();
    $qs = isset($_POST['faq_q']) && is_array($_POST['faq_q']) ? $_POST['faq_q'] : array();
    $as = isset($_POST['faq_a']) && is_array($_POST['faq_a']) ? $_POST['faq_a'] : array();
    foreach ($qs as $i => $q) {
        $v['faq'][] = array(is_string($q) ? $q : '', isset($as[$i]) && is_string($as[$i]) ? $as[$i] : '');
    }
    if ($existing) {
        $v['slug'] = $existing['slug'];
    }
}

// categories already used (to pick from)
$categories = array();
foreach (bpListPosts() as $row) {
    if ($row['category_slug'] !== '' && !isset($categories[$row['category_slug']])) {
        $categories[$row['category_slug']] = $row['category'];
    }
}
$isLive = $existing && ((isset($existing['status']) ? $existing['status'] : 'published') !== 'draft');

adminPageStart($existing ? 'ویرایش مقاله' : 'مقالهٔ جدید', 'posts', array('blog_css' => true));
foreach ($errors as $e) {
    echo '<div class="notice notice--error" role="alert">' . h($e) . '</div>';
}
?>
<div class="admin-head">
    <h1 class="admin-title"><?= $existing ? 'ویرایش مقاله' : 'مقالهٔ جدید' ?></h1>
    <?php if ($isLive): ?><a class="btn btn--outline" href="/blog/<?= h($existing['slug']) ?>/" target="_blank" rel="noopener">مشاهده در سایت</a><?php endif; ?>
</div>
<div class="notice notice--warn" id="draftBanner" hidden>نسخهٔ ذخیره‌نشده‌ای از این مقاله در همین مرورگر پیدا شد. <button type="button" class="link-btn" id="restoreDraft">بازیابی</button> · <button type="button" class="link-btn" id="dropDraft">نادیده‌گرفتن</button></div>

<form method="post" id="postForm" class="editor" data-csrf="<?= h(adminCsrfToken()) ?>" data-slug="<?= h($v['slug']) ?>" novalidate>
    <?= adminCsrfField() ?>

    <section class="card">
        <div class="form-group">
            <label class="form-label" for="f-title">عنوان مقاله</label>
            <input class="form-input" id="f-title" name="title" type="text" maxlength="110" value="<?= h($v['title']) ?>" required>
            <div class="form-hint"><span>ایده‌آل: تا ۶۰ کاراکتر، کلیدواژهٔ اصلی در ابتدا</span><span id="c-title" dir="ltr"></span></div>
        </div>
        <div class="form-group">
            <label class="form-label" for="f-slug">آدرس مقاله (انگلیسی)</label>
            <input class="form-input" id="f-slug" name="slug" type="text" dir="ltr" maxlength="80" value="<?= h($v['slug']) ?>" placeholder="my-new-post"
                <?= $existing ? 'readonly' : 'required' ?>>
            <div class="form-hint"><span><?= $existing ? 'آدرس بعد از ساخت قابل تغییر نیست (برای حفظ سئو).' : 'فقط حروف کوچک انگلیسی، عدد و خط تیره؛ بعد از ساخت تغییر نمی‌کند.' ?></span>
                <span dir="ltr">/blog/<?= h($v['slug'] !== '' ? $v['slug'] : '…') ?>/</span></div>
        </div>
        <div class="form-group">
            <label class="form-label" for="f-desc">توضیح متا (در نتایج گوگل دیده می‌شود)</label>
            <textarea class="form-textarea short" id="f-desc" name="description" maxlength="200" rows="3"><?= h($v['description']) ?></textarea>
            <div class="form-hint"><span>ایده‌آل: ۱۱۰ تا ۱۵۵ کاراکتر</span><span id="c-desc" dir="ltr"></span></div>
        </div>
        <div class="form-group">
            <label class="form-label" for="f-excerpt">خلاصه (زیر عنوان و روی کارت‌ها)</label>
            <textarea class="form-textarea short" id="f-excerpt" name="excerpt" maxlength="300" rows="2"><?= h($v['excerpt']) ?></textarea>
        </div>
    </section>

    <section class="card">
        <div class="editor-tabs" role="tablist">
            <button type="button" role="tab" class="chip-btn" id="tab-write" aria-selected="true" aria-pressed="true">نوشتن</button>
            <button type="button" role="tab" class="chip-btn" id="tab-preview" aria-selected="false" aria-pressed="false">پیش‌نمایش</button>
            <span class="editor-stats" id="stats" dir="rtl"></span>
        </div>
        <div class="md-toolbar" id="toolbar" role="toolbar" aria-label="ابزار قالب‌بندی">
            <button type="button" data-md="h2" title="عنوان بخش">H2</button>
            <button type="button" data-md="h3" title="زیرعنوان">H3</button>
            <button type="button" data-md="bold" title="ضخیم"><b>B</b></button>
            <button type="button" data-md="italic" title="کج"><i>I</i></button>
            <button type="button" data-md="link" title="لینک">لینک</button>
            <button type="button" data-md="ul" title="لیست">• لیست</button>
            <button type="button" data-md="ol" title="لیست شماره‌دار">۱. لیست</button>
            <button type="button" data-md="quote" title="نقل‌قول">❝</button>
            <button type="button" data-md="table" title="جدول">جدول</button>
            <button type="button" data-md="code" title="کد">کد</button>
        </div>
        <textarea class="form-textarea editor-body" id="f-body" name="body" dir="auto" spellcheck="true" rows="22" required><?= h($v['body']) ?></textarea>
        <div class="post-body editor-preview" id="preview" hidden></div>
        <p class="hint">Markdown ساده: <code>## عنوان</code> · <code>**ضخیم**</code> · <code>[متن](/services/telegram-bot/)</code> · <code>- لیست</code>. فهرست مطالب خودکار ساخته می‌شود.</p>
    </section>

    <section class="card">
        <h2 class="card-title">سوالات متداول (اختیاری، برای سئو مفید است)</h2>
        <div id="faqList">
            <?php foreach ($v['faq'] as $qa): ?>
                <div class="faq-edit">
                    <input class="form-input" name="faq_q[]" type="text" placeholder="سوال" maxlength="200" value="<?= h($qa[0]) ?>">
                    <textarea class="form-textarea short" name="faq_a[]" rows="2" placeholder="پاسخ کوتاه" maxlength="1500"><?= h($qa[1]) ?></textarea>
                    <button type="button" class="link-btn faq-remove">حذف این سوال</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn--outline" id="addFaq">+ افزودن سوال</button>
    </section>

    <section class="card">
        <h2 class="card-title">تنظیمات</h2>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="f-catpick">دسته‌بندی</label>
                <select class="form-select" id="f-catpick">
                    <option value="">انتخاب دستهٔ موجود…</option>
                    <?php foreach ($categories as $slug => $name): ?>
                        <option value="<?= h($slug) ?>" data-name="<?= h($name) ?>"><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="f-icon">آیکون کارت</label>
                <select class="form-select" id="f-icon" name="icon">
                    <?php foreach ($cfg['icons'] as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= $v['icon'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="f-cat">نام دسته (فارسی)</label>
                <input class="form-input" id="f-cat" name="category" type="text" maxlength="40" value="<?= h($v['category']) ?>"></div>
            <div class="form-group"><label class="form-label" for="f-catslug">شناسهٔ دسته (انگلیسی)</label>
                <input class="form-input" id="f-catslug" name="category_slug" type="text" dir="ltr" maxlength="40" value="<?= h($v['category_slug']) ?>" placeholder="mini-app"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="f-date">تاریخ انتشار</label>
                <input class="form-input" id="f-date" name="published" type="date" dir="ltr" value="<?= h($v['published']) ?>"></div>
            <div class="form-group"><label class="form-label" for="f-cta">نوع پروژه برای دکمهٔ «شروع پروژه»</label>
                <select class="form-select" id="f-cta" name="cta_type">
                    <?php foreach ($cfg['cta_types'] as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= $v['cta_type'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
        <div class="form-group"><label class="form-label" for="f-service">خدمت مرتبط (دکمهٔ پایین مقاله)</label>
            <select class="form-select" id="f-service" name="service">
                <option value="">بدون دکمه</option>
                <?php foreach ($cfg['services'] as $s): ?>
                    <option value="<?= h($s['href']) ?>" <?= $v['service'] === $s['href'] ? 'selected' : '' ?>><?= h($s['label']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="form-group"><label class="form-label" for="f-kw">کلیدواژه‌ها (با ویرگول جدا کنید)</label>
            <input class="form-input" id="f-kw" name="keywords" type="text" value="<?= h($v['keywords']) ?>"></div>
        <div class="form-group"><label class="form-label" for="f-img">تصویر شاخص برای اشتراک‌گذاری (اختیاری)</label>
            <input class="form-input" id="f-img" name="image" type="text" dir="ltr" value="<?= h($v['image']) ?>" placeholder="/images/blog/my-image.jpg">
            <div class="form-hint"><span>ابتدا تصویر ۱۲۰۰×۶۳۰ را در پوشهٔ images آپلود کنید. خالی = تصویر پیش‌فرض سایت.</span></div></div>
    </section>

    <section class="card">
        <h2 class="card-title">چک‌لیست سئو</h2>
        <ul class="checklist" id="seoList"></ul>
    </section>

    <div class="action-bar">
        <button class="btn btn--outline" type="submit" name="status" value="draft">ذخیره به‌عنوان پیش‌نویس</button>
        <button class="btn btn--primary" type="submit" name="status" value="published"><?= $isLive ? 'ذخیره و به‌روزرسانی در سایت' : 'انتشار' ?></button>
    </div>
</form>

<template id="faqTpl">
    <div class="faq-edit">
        <input class="form-input" name="faq_q[]" type="text" placeholder="سوال" maxlength="200">
        <textarea class="form-textarea short" name="faq_a[]" rows="2" placeholder="پاسخ کوتاه" maxlength="1500"></textarea>
        <button type="button" class="link-btn faq-remove">حذف این سوال</button>
    </div>
</template>
<?php adminPageEnd(array('/js/admin-editor.js')); ?>
