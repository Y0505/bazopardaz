<?php
/**
 * Self-test for the article editor. Open it while logged in: /admin/blog-selftest.php
 * It proves that the PHP engine on THIS server produces exactly the same HTML as the Python build:
 *   1) environment, 2) Persian dates, 3) Markdown vectors, 4) every published article page, the /blog/ list
 *      and the RSS feed are re-rendered in memory and compared byte-for-byte with the files on disk.
 * Nothing is written.
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/blog-engine.php';

adminStartSession();
adminHeaders();
adminRequireLogin();

$VECTORS = json_decode(<<<'JSONEOF'
{"md": [{"in": "## عنوان *مهم* و `کد`\n\nمتن **ضخیم** و [لینک داخلی](/a/) و `x<y` و a & b.\n\n- یک\n- دو **ضخیم**\n\n۱. الف\n۲. ب\n\n1) one\n2) two", "html": "<h2 id=\"عنوان-مهم-و-کد\">عنوان <em>مهم</em> و <code>کد</code></h2>\n<p>متن <strong>ضخیم</strong> و <a href=\"/a/\">لینک داخلی</a> و <code>x&lt;y</code> و a &amp; b.</p>\n<ul><li>یک</li><li>دو <strong>ضخیم</strong></li></ul>\n<ol><li>الف</li><li>ب</li></ol>\n<ol><li>one</li><li>two</li></ol>", "toc": [["عنوان-مهم-و-کد", "عنوان مهم و کد"]]}, {"in": "### زیرعنوان\n\n| الف | ب |\n|---|---|\n| ۱ | ۲ |\n| *x* | [y](/y/) |\n\n> نقل **قول**\n> ادامه\n\n---\n\n```\nprint(1<2) && \"q\"\n```\n\nپایان.", "html": "<h3 id=\"زیرعنوان\">زیرعنوان</h3>\n<div class=\"table-wrap\"><table><thead><tr><th>الف</th><th>ب</th></tr></thead><tbody><tr><td>۱</td><td>۲</td></tr><tr><td><em>x</em></td><td><a href=\"/y/\">y</a></td></tr></tbody></table></div>\n<blockquote><p>نقل <strong>قول</strong> ادامه</p></blockquote>\n<hr>\n<pre dir=\"ltr\"><code>print(1&lt;2) &amp;&amp; \"q\"</code></pre>\n<p>پایان.</p>", "toc": []}, {"in": "بیرونی [x](https://example.com) و [bad](javascript:alert(1)) و ![i](/images/a.png) و ![b](http://x.y/\"z).\n\nدو خط\nپشت هم.\n\n## تکراری\n\n## تکراری\n\n## ۱. شماره", "html": "<p>بیرونی <a href=\"https://example.com\" rel=\"noopener\" target=\"_blank\">x</a> و bad) و <img src=\"/images/a.png\" alt=\"i\" loading=\"lazy\" decoding=\"async\"> و b.</p>\n<p>دو خط پشت هم.</p>\n<h2 id=\"تکراری\">تکراری</h2>\n<h2 id=\"تکراری-2\">تکراری</h2>\n<h2 id=\"۱-شماره\">۱. شماره</h2>", "toc": [["تکراری", "تکراری"], ["تکراری-2", "تکراری"], ["۱-شماره", "۱. شماره"]]}], "jalali": [["2026-11-05", "۱۴ آبان ۱۴۰۵"], ["2026-03-21", "۱ فروردین ۱۴۰۵"], ["2026-03-20", "۲۹ اسفند ۱۴۰۴"], ["2025-03-21", "۱ فروردین ۱۴۰۴"], ["2024-03-20", "۱ فروردین ۱۴۰۳"], ["2027-03-21", "۱ فروردین ۱۴۰۶"], ["2026-09-23", "۱ مهر ۱۴۰۵"], ["2030-01-01", "۱۲ دی ۱۴۰۸"]]}
JSONEOF
, true);

$results = array();
$add = function ($group, $name, $ok, $detail = '') use (&$results) {
    $results[] = array($group, $name, (bool)$ok, $detail);
};

// ---- 1. environment
$add('محیط', 'PHP 7.2 یا بالاتر (' . PHP_VERSION . ')', PHP_VERSION_ID >= 70200);
$add('محیط', 'افزونهٔ mbstring', function_exists('mb_strlen'));
$add('محیط', 'افزونهٔ json', function_exists('json_encode'));
$add('محیط', 'پشتیبانی یونیکد در regex', @preg_match('/\p{L}+/u', 'سلام') === 1);
foreach (array('blog-shell.html', 'blog-config.json', 'blog-index-body.html') as $f) {
    $add('فایل‌ها', 'includes/' . $f, is_file(dirname(__DIR__) . '/includes/' . $f));
}
$add('فایل‌ها', 'blog-data قابل نوشتن', is_dir(bpDataDir()) && is_writable(bpDataDir()));
$add('فایل‌ها', 'blog قابل نوشتن', is_dir(bpRoot() . '/blog') && is_writable(bpRoot() . '/blog'));

try {
    // ---- 2. dates
    foreach ($VECTORS['jalali'] as $j) {
        $got = bpFaDate($j[0]);
        $add('تاریخ شمسی', $j[0] . ' → ' . $j[1], $got === $j[1], $got === $j[1] ? '' : 'خروجی PHP: ' . $got);
    }
    // ---- 3. markdown
    $base = bpConfig()['base'];
    foreach ($VECTORS['md'] as $i => $v) {
        $r = bpMarkdown($v['in'], $base);
        $okHtml = $r['html'] === $v['html'];
        $okToc = json_encode($r['toc'], JSON_UNESCAPED_UNICODE) === json_encode($v['toc'], JSON_UNESCAPED_UNICODE);
        $detail = '';
        if (!$okHtml) {
            $n = min(strlen($r['html']), strlen($v['html']));
            $pos = 0;
            while ($pos < $n && $r['html'][$pos] === $v['html'][$pos]) {
                $pos++;
            }
            $detail = 'اولین تفاوت در بایت ' . $pos . ': PHP «' . substr($r['html'], max(0, $pos - 20), 60) . '» ≠ مرجع «' . substr($v['html'], max(0, $pos - 20), 60) . '»';
        }
        $add('مارک‌داون', 'نمونهٔ ' . ($i + 1), $okHtml && $okToc, $detail);
    }
    // ---- 4. full pages vs files on disk
    list($posts, $files) = bpRenderAll();
    foreach ($files as $rel => $content) {
        $disk = @file_get_contents(bpRoot() . '/' . $rel);
        if ($disk === false) {
            $add('صفحه‌ها', $rel, false, 'فایل روی سرور نیست (با «بازسازی همهٔ صفحات» ساخته می‌شود)');
            continue;
        }
        $same = $disk === $content;
        $detail = '';
        if (!$same) {
            $n = min(strlen($disk), strlen($content));
            $pos = 0;
            while ($pos < $n && $disk[$pos] === $content[$pos]) {
                $pos++;
            }
            $detail = 'اولین تفاوت در بایت ' . $pos . ': روی سرور «' . substr($disk, max(0, $pos - 30), 70) . '» ≠ PHP «' . substr($content, max(0, $pos - 30), 70) . '»';
        }
        $add('صفحه‌ها', $rel . ($same ? '' : ' (متفاوت؛ اگر مقاله را بعد از ساخت اولیه ویرایش کرده‌اید طبیعی است)'), $same, $detail);
    }
} catch (Throwable $e) {
    $add('خطا', 'اجرای تست', false, $e->getMessage());
}

$fail = 0;
foreach ($results as $r) {
    if (!$r[2]) {
        $fail++;
    }
}
adminPageStart('تست خودکار', 'posts');
?>
<h1 class="admin-title">تست خودکار سیستم مقالات</h1>
<div class="notice <?= $fail === 0 ? 'notice--ok' : 'notice--warn' ?>">
    <?= $fail === 0 ? 'همه‌چیز درست است: PHP همان HTML پایتون را می‌سازد.' : $fail . ' مورد نیاز به بررسی دارد (جزئیات پایین).' ?>
</div>
<section class="card">
    <ul class="checklist">
        <?php foreach ($results as $r): ?>
            <li class="<?= $r[2] ? 'ok' : 'bad' ?>"><?= $r[2] ? '✓' : '✕' ?> <strong><?= h($r[0]) ?></strong> · <?= h($r[1]) ?>
                <?php if ($r[3] !== ''): ?><br><small dir="auto"><?= h($r[3]) ?></small><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<p class="hint">اگر مورد «صفحه‌ها» قرمز بود ولی «مارک‌داون» سبز، معمولاً فقط نسخهٔ فایل روی سرور قدیمی است؛ دکمهٔ «بازسازی همهٔ صفحات مقالات» را در بخش مقالات بزنید و این تست را دوباره اجرا کنید.</p>
<?php adminPageEnd(); ?>
