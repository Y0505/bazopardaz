<?php
/**
 * Blog engine for the admin article editor.
 *
 * This is a PHP port of src/blog.py + the blog parts of src/build.py. HTML snippets come from the very same
 * templates (includes/blog-config.json, exported by build.py), so PHP and Python produce identical pages.
 * The page frame (head, header, footer) is includes/blog-shell.html, also exported by build.py.
 *
 * Posts are stored in blog-data/<slug>.post.php (first line is a PHP guard, so the file can never be shown
 * in a browser). Publishing writes plain static HTML files, exactly like the Python build does.
 *
 * Written for PHP 7.2+ (needs the mbstring and json extensions).
 */

// ------------------------------------------------------------------------------------------------ paths & config

function bpRoot()
{
    return dirname(__DIR__);
}

function bpDataDir()
{
    return bpRoot() . '/blog-data';
}

function bpConfig()
{
    static $cfg = null;
    if ($cfg === null) {
        $raw = @file_get_contents(__DIR__ . '/blog-config.json');
        $cfg = $raw === false ? null : json_decode($raw, true);
        if (!is_array($cfg) || !isset($cfg['templates'])) {
            throw new RuntimeException('includes/blog-config.json is missing or invalid (run build.py and upload it).');
        }
    }
    return $cfg;
}

function bpShell()
{
    $s = @file_get_contents(__DIR__ . '/blog-shell.html');
    if ($s === false || $s === '') {
        throw new RuntimeException('includes/blog-shell.html is missing (run build.py and upload it).');
    }
    return $s;
}

/** Single-pass {{token}} substitution using the shared templates. */
function bpTpl($name, array $vals)
{
    $cfg = bpConfig();
    if (!isset($cfg['templates'][$name])) {
        throw new RuntimeException('Unknown template: ' . $name);
    }
    $map = array();
    foreach ($vals as $k => $v) {
        $map['{{' . $k . '}}'] = (string)$v;
    }
    return strtr($cfg['templates'][$name], $map);
}

function bpIcon($w, $h, $name)
{
    return bpTpl('icon', array('w' => $w, 'h' => $h, 'name' => $name));
}

function bpEsc($s)
{
    return str_replace(array('&', '<', '>', '"', "'"), array('&amp;', '&lt;', '&gt;', '&quot;', '&#x27;'), (string)$s);
}

function bpEscText($s)
{
    return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), (string)$s);
}

function bpTrim($s)
{
    return trim($s, " \t\n\r");
}

// ------------------------------------------------------------------------------------------------ Persian dates

function bpFa($n)
{
    return strtr((string)$n, array('0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'));
}

function bpLeap($y)
{
    return in_array($y % 33, array(1, 5, 9, 13, 17, 22, 26, 30), true);
}

/** ISO date (YYYY-MM-DD) -> array(year, month, day) in the Solar Hijri calendar. */
function bpToJalali($iso)
{
    $p = explode('-', $iso);
    $ts = gmmktime(0, 0, 0, (int)$p[1], (int)$p[2], (int)$p[0]);
    $delta = (int)round(($ts - gmmktime(0, 0, 0, 3, 21, 2026)) / 86400);
    $y = 1405;
    while ($delta < 0) {
        $y--;
        $delta += bpLeap($y) ? 366 : 365;
    }
    while ($delta >= (bpLeap($y) ? 366 : 365)) {
        $delta -= bpLeap($y) ? 366 : 365;
        $y++;
    }
    $m = 0;
    while (true) {
        $ml = $m < 6 ? 31 : ($m < 11 ? 30 : (bpLeap($y) ? 30 : 29));
        if ($delta >= $ml) {
            $delta -= $ml;
            $m++;
        } else {
            break;
        }
    }
    return array($y, $m + 1, $delta + 1);
}

function bpFaDate($iso)
{
    $months = array('فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند');
    $j = bpToJalali($iso);
    return bpFa($j[2]) . ' ' . $months[$j[1] - 1] . ' ' . bpFa($j[0]);
}

// ------------------------------------------------------------------------------------------------ Markdown

function bpSafeUrl($url)
{
    return (bool)preg_match('/^(?:\/|#|https?:\/\/|mailto:)[^"\'<>]*$/', $url);
}

function bpInline($text, $base)
{
    $t = bpEscText($text);
    $codes = array();
    $t = preg_replace_callback('/`([^`]+)`/u', function ($m) use (&$codes) {
        $codes[] = $m[1];
        return "\x00" . (count($codes) - 1) . "\x00";
    }, $t);
    $t = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/u', function ($m) {
        if (!bpSafeUrl($m[2])) {
            return $m[1];
        }
        return '<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy" decoding="async">';
    }, $t);
    $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) use ($base) {
        $url = $m[2];
        if (!bpSafeUrl($url)) {
            return $m[1];
        }
        $external = strpos($url, 'http') === 0 && strpos($url, $base) !== 0;
        return '<a href="' . $url . '"' . ($external ? ' rel="noopener" target="_blank"' : '') . '>' . $m[1] . '</a>';
    }, $t);
    $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
    $t = preg_replace('/(?<![*\p{L}\p{N}_])\*(?!\s)(.+?)(?<!\s)\*(?![*\p{L}\p{N}_])/u', '<em>$1</em>', $t);
    $t = preg_replace_callback('/\x00(\d+)\x00/', function ($m) use ($codes) {
        return '<code>' . $codes[(int)$m[1]] . '</code>';
    }, $t);
    return $t;
}

function bpSlugify($text, array &$used)
{
    $s = preg_replace('/<[^>]+>/', '', $text);
    $s = preg_replace('/[^\p{L}\p{N}_-]+/u', '-', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'section';
    }
    $base = $s;
    $i = 2;
    while (isset($used[$s])) {
        $s = $base . '-' . $i;
        $i++;
    }
    $used[$s] = true;
    return $s;
}

/** Returns array('html' => string, 'toc' => array(array(id, text), ...)) */
function bpMarkdown($src, $base)
{
    $lines = explode("\n", str_replace("\r\n", "\n", $src));
    $n = count($lines);
    $out = array();
    $toc = array();
    $used = array();
    $para = array();
    $i = 0;

    $flush = function () use (&$out, &$para, $base) {
        if ($para) {
            $out[] = '<p>' . bpInline(implode(' ', array_map('bpTrim', $para)), $base) . '</p>';
            $para = array();
        }
    };

    while ($i < $n) {
        $ln = $lines[$i];
        if (strpos(bpTrim($ln), '```') === 0) {
            $flush();
            $code = array();
            $i++;
            while ($i < $n && strpos(bpTrim($lines[$i]), '```') !== 0) {
                $code[] = $lines[$i];
                $i++;
            }
            $out[] = '<pre dir="ltr"><code>' . bpEscText(implode("\n", $code)) . '</code></pre>';
        } elseif (preg_match('/^#{2,3} /', $ln)) {
            $flush();
            $level = substr($ln, 0, 3) === '## ' ? 2 : 3;
            $text = bpTrim(substr($ln, $level + 1));
            $hid = bpSlugify($text, $used);
            if ($level === 2) {
                $toc[] = array($hid, preg_replace('/<[^>]+>/', '', bpInline($text, $base)));
            }
            $out[] = '<h' . $level . ' id="' . $hid . '">' . bpInline($text, $base) . '</h' . $level . '>';
        } elseif (preg_match('/^(-{3,}|\*{3,})[ \t]*$/', $ln)) {
            $flush();
            $out[] = '<hr>';
        } elseif (strpos($ln, '>') === 0) {
            $flush();
            $q = array();
            while ($i < $n && strpos($lines[$i], '>') === 0) {
                $q[] = bpTrim(substr($lines[$i], 1));
                $i++;
            }
            $i--;
            $out[] = '<blockquote><p>' . bpInline(implode(' ', $q), $base) . '</p></blockquote>';
        } elseif (preg_match('/^[ \t]*[-*] /', $ln)) {
            $flush();
            $items = array();
            while ($i < $n && preg_match('/^[ \t]*[-*] /', $lines[$i])) {
                $items[] = preg_replace('/^[ \t]*[-*] /', '', $lines[$i]);
                $i++;
            }
            $i--;
            $li = '';
            foreach ($items as $it) {
                $li .= '<li>' . bpInline($it, $base) . '</li>';
            }
            $out[] = '<ul>' . $li . '</ul>';
        } elseif (preg_match('/^[ \t]*[0-9۰-۹]+[.)] /u', $ln)) {
            $flush();
            $items = array();
            while ($i < $n && preg_match('/^[ \t]*[0-9۰-۹]+[.)] /u', $lines[$i])) {
                $items[] = preg_replace('/^[ \t]*[0-9۰-۹]+[.)] /u', '', $lines[$i]);
                $i++;
            }
            $i--;
            $li = '';
            foreach ($items as $it) {
                $li .= '<li>' . bpInline($it, $base) . '</li>';
            }
            $out[] = '<ol>' . $li . '</ol>';
        } elseif (strpos(bpTrim($ln), '|') === 0 && $i + 1 < $n
            && preg_match('/^[ \t]*\|?[ \t:|-]+\|[ \t:|-]*$/', $lines[$i + 1])) {
            $flush();
            $rows = array();
            while ($i < $n && strpos(bpTrim($lines[$i]), '|') === 0) {
                $rows[] = array_map('bpTrim', explode('|', trim(bpTrim($lines[$i]), '|')));
                $i++;
            }
            $i--;
            $head = $rows[0];
            $body = array_slice($rows, 2);
            $t = '<thead><tr>';
            foreach ($head as $c) {
                $t .= '<th>' . bpInline($c, $base) . '</th>';
            }
            $t .= '</tr></thead><tbody>';
            foreach ($body as $r) {
                $t .= '<tr>';
                foreach ($r as $c) {
                    $t .= '<td>' . bpInline($c, $base) . '</td>';
                }
                $t .= '</tr>';
            }
            $t .= '</tbody>';
            $out[] = '<div class="table-wrap"><table>' . $t . '</table></div>';
        } elseif (bpTrim($ln) === '') {
            $flush();
        } else {
            $para[] = $ln;
        }
        $i++;
    }
    $flush();
    return array('html' => implode("\n", $out), 'toc' => $toc);
}

// ------------------------------------------------------------------------------------------------ posts: files

function bpValidSlug($slug)
{
    return is_string($slug) && strlen($slug) >= 3 && strlen($slug) <= 80
        && (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function bpPostPath($slug)
{
    return bpDataDir() . '/' . $slug . '.post.php';
}

/** @return array(meta array, body string) */
function bpReadPostFile($path)
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read ' . basename($path));
    }
    if (strpos($raw, '<?php') === 0) {
        $nl = strpos($raw, "\n");
        $raw = $nl === false ? '' : substr($raw, $nl + 1);
    }
    $pos = strpos($raw, '<!--/meta-->');
    if ($pos === false) {
        throw new RuntimeException('Bad post file (no meta marker): ' . basename($path));
    }
    $meta = json_decode(substr($raw, 0, $pos), true);
    if (!is_array($meta)) {
        throw new RuntimeException('Bad post file (invalid JSON header): ' . basename($path));
    }
    return array($meta, substr($raw, $pos + strlen('<!--/meta-->')));
}

function bpWriteFile($path, $content)
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Cannot create folder: ' . $dir);
    }
    $tmp = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write: ' . basename($path) . ' (check folder permissions)');
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot replace: ' . basename($path));
    }
}

function bpWritePostFile($slug, array $meta, $body)
{
    $path = bpPostPath($slug);
    if (is_file($path)) {  // keep the previous version so a bad edit can be undone
        @mkdir(bpDataDir() . '/backup', 0755, true);
        @copy($path, bpDataDir() . '/backup/' . $slug . '.post.php');
    }
    $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    bpWriteFile($path, "<?php http_response_code(404); exit; ?>\n" . $json . "\n<!--/meta-->\n" . trim($body) . "\n");
}

function bpDeletePostFile($slug)
{
    $path = bpPostPath($slug);
    if (is_file($path)) {
        @mkdir(bpDataDir() . '/backup', 0755, true);
        @rename($path, bpDataDir() . '/backup/' . $slug . '.deleted.post.php');
    }
}

/** Light-weight list for the admin table (no Markdown rendering). */
function bpListPosts()
{
    $rows = array();
    foreach (glob(bpDataDir() . '/*.post.php') ?: array() as $f) {
        try {
            list($meta, $body) = bpReadPostFile($f);
        } catch (Exception $e) {
            continue;
        }
        $slug = isset($meta['slug']) ? $meta['slug'] : explode('.', basename($f))[0];
        $rows[] = array(
            'slug' => $slug, 'title' => isset($meta['title']) ? $meta['title'] : $slug,
            'status' => isset($meta['status']) ? $meta['status'] : 'published',
            'published' => isset($meta['published']) ? $meta['published'] : '',
            'updated' => isset($meta['updated']) ? $meta['updated'] : '',
            'category' => isset($meta['category']) ? $meta['category'] : '',
            'category_slug' => isset($meta['category_slug']) ? $meta['category_slug'] : '',
        );
    }
    usort($rows, function ($a, $b) {
        return strcmp($b['published'] . $b['slug'], $a['published'] . $a['slug']);
    });
    return $rows;
}

// ------------------------------------------------------------------------------------------------ posts: preparing

function bpPrepare(array $meta, $body, $slugFallback)
{
    $cfg = bpConfig();
    $base = $cfg['base'];
    foreach (array('title', 'description', 'excerpt', 'category', 'category_slug', 'published') as $req) {
        if (!isset($meta[$req]) || $meta[$req] === '') {
            throw new RuntimeException('Missing field "' . $req . '" in ' . $slugFallback);
        }
    }
    $p = $meta;
    if (!array_key_exists('slug', $p)) {
        $p['slug'] = $slugFallback;
    }
    if (!array_key_exists('updated', $p)) {
        $p['updated'] = $p['published'];
    }
    if (!array_key_exists('icon', $p)) {
        $p['icon'] = 'doc';
    }
    if (!isset($p['faq']) || !is_array($p['faq'])) {
        $p['faq'] = array();
    }
    $md = bpMarkdown(trim($body), $base);
    $html = $md['html'];
    $toc = $md['toc'];
    if ($p['faq']) {
        $toc[] = array('faq', 'سوالات متداول');
        $items = '';
        foreach ($p['faq'] as $qa) {
            $items .= bpTpl('faq_item', array('q' => bpEsc($qa[0]), 'icon' => bpIcon(20, 20, 'chevron-down'),
                'a' => bpInline($qa[1], $base)));
        }
        $html .= bpTpl('faq_section', array('items' => $items));
    }
    preg_match_all('/[\p{L}\p{N}_]+/u', preg_replace('/<[^>]+>/', ' ', $html), $m);
    $words = count($m[0]);
    $p['body_html'] = $html;
    $p['toc'] = $toc;
    $p['words'] = $words;
    $p['minutes'] = max(1, (int)ceil($words / 190));
    $p['url'] = '/blog/' . $p['slug'] . '/';
    return $p;
}

/** Published posts, newest first, with cover tones assigned (same rules as blog.py). */
function bpLoadPublished()
{
    $posts = array();
    foreach (glob(bpDataDir() . '/*.post.php') ?: array() as $f) {
        list($meta, $body) = bpReadPostFile($f);
        if ((isset($meta['status']) ? $meta['status'] : 'published') === 'draft') {
            continue;
        }
        $posts[] = bpPrepare($meta, $body, explode('.', basename($f))[0]);
    }
    usort($posts, function ($a, $b) {
        return strcmp($b['published'] . "\x01" . $b['slug'], $a['published'] . "\x01" . $a['slug']);
    });
    $tones = array('tone-a', 'tone-b', 'tone-c', 'tone-d');
    $cats = array();
    foreach ($posts as $p) {
        if (!in_array($p['category_slug'], $cats, true)) {
            $cats[] = $p['category_slug'];
        }
    }
    foreach ($posts as $k => $p) {
        $posts[$k]['tone'] = $tones[array_search($p['category_slug'], $cats, true) % 4];
    }
    return $posts;
}

// ------------------------------------------------------------------------------------------------ rendering

function bpCard(array $p, $featured = false)
{
    return bpTpl('card', array(
        'featured' => $featured ? ' post-card--featured' : '', 'cat' => bpEsc($p['category_slug']), 'tone' => $p['tone'],
        'icon' => bpIcon(64, 64, $p['icon']), 'category' => bpEsc($p['category']), 'url' => $p['url'],
        'title' => bpEsc($p['title']), 'excerpt' => bpEsc($p['excerpt']), 'published' => $p['published'],
        'date' => bpFaDate($p['published']), 'minutes' => bpFa($p['minutes'])));
}

function bpIndexHtml(array $posts)
{
    $seen = array();
    $chips = bpTpl('chip', array('key' => 'all', 'pressed' => 'true', 'label' => 'همه'));
    foreach ($posts as $p) {
        if (!isset($seen[$p['category_slug']])) {
            $seen[$p['category_slug']] = true;
            $chips .= bpTpl('chip', array('key' => bpEsc($p['category_slug']), 'pressed' => 'false', 'label' => bpEsc($p['category'])));
        }
    }
    $cards = '';
    foreach ($posts as $i => $p) {
        $cards .= bpCard($p, $i === 0);
    }
    return bpTpl('index', array('chips' => $chips, 'cards' => $cards));
}

function bpLatestHtml(array $posts, $n = 3)
{
    $cards = '';
    foreach (array_slice($posts, 0, $n) as $p) {
        $cards .= bpCard($p);
    }
    return bpTpl('latest', array('cards' => $cards));
}

function bpRelated(array $post, array $posts, $n = 3)
{
    $others = array();
    foreach ($posts as $i => $p) {
        if ($p['slug'] !== $post['slug']) {
            $others[] = array('p' => $p, 'i' => $i, 'same' => $p['category_slug'] === $post['category_slug']);
        }
    }
    usort($others, function ($a, $b) {
        if ($a['same'] !== $b['same']) {
            return $a['same'] ? -1 : 1;
        }
        $c = strcmp($b['p']['published'], $a['p']['published']);
        return $c !== 0 ? $c : $a['i'] - $b['i'];
    });
    $res = array();
    foreach (array_slice($others, 0, $n) as $o) {
        $res[] = $o['p'];
    }
    return $res;
}

/** Page meta for one article, in the same shape build.py uses. */
function bpPostMeta(array $p, array $posts)
{
    $cfg = bpConfig();
    $base = $cfg['base'];
    $url = $base . $p['url'];
    $toc = '';
    foreach ($p['toc'] as $t) {
        $toc .= bpTpl('toc_item', array('id' => $t[0], 'text' => bpEsc($t[1])));
    }
    $updated = '';
    if ($p['updated'] !== $p['published']) {
        $updated = bpTpl('updated', array('updated' => $p['updated'], 'updatedfa' => bpFaDate($p['updated'])));
    }
    $serviceHtml = '';
    if (!empty($p['service'])) {
        $serviceHtml = bpTpl('service_btn', array('href' => $p['service']['href'], 'label' => bpEsc($p['service']['label'])));
    }
    $rel = '';
    foreach (bpRelated($p, $posts) as $r) {
        $rel .= bpCard($r);
    }
    $body = bpTpl('post_body', array(
        'cat' => bpEsc($p['category']), 'title' => bpEsc($p['title']), 'excerpt' => bpEsc($p['excerpt']),
        'pub' => $p['published'], 'pubfa' => bpFaDate($p['published']), 'updated' => $updated,
        'min' => bpFa($p['minutes']), 'toc' => $toc, 'body' => $p['body_html'],
        'cta' => isset($p['cta_type']) ? $p['cta_type'] : '', 'service' => $serviceHtml, 'rel' => $rel,
        'urlq' => bpEsc($url), 'titleq' => bpEsc($p['title']), 'arrow' => bpIcon(18, 18, 'arrow-left')));
    $image = isset($p['image']) ? $p['image'] : '';
    if ($image === '') {
        $cand = '/images/blog/' . $p['slug'] . '.jpg';
        $image = is_file(bpRoot() . $cand) ? $cand : $cfg['default_og'];
    }
    $title = $p['title'];
    if (mb_strlen($title, 'UTF-8') < 48) {
        $title .= ' | ' . $cfg['name'];
    }
    return array(
        'title' => $title, 'description' => $p['description'], 'path' => $p['url'],
        'crumbs' => array(array('خانه', '/'), array('مقالات', '/blog/'), array($p['title'], null)),
        'og_type' => 'article', 'og_image' => $image, 'og_image_alt' => $p['title'],
        'article' => array('published' => $p['published'], 'updated' => $p['updated'], 'section' => $p['category'],
            'keywords' => isset($p['keywords']) ? $p['keywords'] : array(), 'words' => $p['words'],
            'headline' => $p['title'], 'image' => $image),
        'lastmod' => $p['updated'], '_body' => $body,
    );
}

function bpBreadcrumb($crumbs)
{
    if (!$crumbs) {
        return '';
    }
    $cfg = bpConfig();
    $items = '';
    $last = count($crumbs) - 1;
    foreach ($crumbs as $i => $c) {
        $sep = $i === 0 ? '' : $cfg['templates']['crumb_sep'];
        $items .= $i === $last
            ? bpTpl('crumb_last', array('sep' => $sep, 'label' => $c[0]))
            : bpTpl('crumb_link', array('sep' => $sep, 'href' => $c[1], 'label' => $c[0]));
    }
    return bpTpl('breadcrumb', array('items' => $items));
}

function bpSocialTags(array $meta, $url)
{
    $cfg = bpConfig();
    $article = '';
    if (isset($meta['article'])) {
        $a = $meta['article'];
        $article = bpTpl('article_tags', array('published' => $a['published'], 'updated' => $a['updated'], 'section' => bpEsc($a['section'])));
    }
    return bpTpl('social', array(
        'type' => isset($meta['og_type']) ? $meta['og_type'] : 'website', 'site' => $cfg['name'],
        'title' => bpEsc($meta['title']), 'description' => bpEsc($meta['description']), 'url' => $url,
        'image' => $cfg['base'] . (isset($meta['og_image']) ? $meta['og_image'] : $cfg['default_og']),
        'w' => 1200, 'h' => 630,
        'alt' => bpEsc(isset($meta['og_image_alt']) ? $meta['og_image_alt'] : $cfg['default_og_alt']),
        'article' => $article));
}

function bpJsonLd(array $meta, $url)
{
    $cfg = bpConfig();
    $base = $cfg['base'];
    $orgId = $base . '/#organization';
    $siteId = $base . '/#website';
    $logo = array('@type' => 'ImageObject', 'url' => $base . '/images/logo-512.png', 'width' => 512, 'height' => 512);
    $nodes = array();
    $page = array('@type' => isset($meta['ptype']) ? $meta['ptype'] : 'WebPage', '@id' => $url . '#webpage', 'url' => $url,
        'name' => $meta['title'], 'description' => $meta['description'], 'inLanguage' => 'fa-IR',
        'isPartOf' => array('@id' => $siteId), 'publisher' => array('@id' => $orgId));
    $crumbs = isset($meta['crumbs']) ? $meta['crumbs'] : null;
    if (isset($meta['article'])) {
        $a = $meta['article'];
        $nodes[] = array(
            '@type' => 'BlogPosting', '@id' => $url . '#article', 'mainEntityOfPage' => array('@id' => $url . '#webpage'),
            'headline' => mb_substr($a['headline'], 0, 110, 'UTF-8'), 'description' => $meta['description'],
            'inLanguage' => 'fa-IR', 'datePublished' => $a['published'], 'dateModified' => $a['updated'],
            'image' => array($base . $a['image']), 'articleSection' => $a['section'], 'wordCount' => $a['words'],
            'keywords' => $a['keywords'],
            'author' => array('@type' => 'Person', 'name' => 'یاسر', 'url' => $base . '/about/'),
            'publisher' => array('@type' => 'Organization', 'name' => $cfg['name'], 'url' => $base . '/', 'logo' => $logo));
    }
    if (isset($meta['extra_nodes'])) {
        foreach ($meta['extra_nodes'] as $x) {
            $nodes[] = $x;
        }
    }
    if ($crumbs) {
        $page['breadcrumb'] = array('@id' => $url . '#breadcrumb');
    }
    $nodes[] = $page;
    if ($crumbs) {
        $items = array();
        foreach ($crumbs as $i => $c) {
            $items[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0],
                'item' => $c[1] ? $base . $c[1] : $url);
        }
        $nodes[] = array('@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $items);
    }
    // FAQPage built from the visible FAQ markup, exactly like build.py
    if (preg_match_all('/<summary class="faq-trigger"><span>(.*?)<\/span>.*?<\/summary><div class="faq-answer"><div class="faq-answer-inner">(.*?)<\/div><\/div><\/details>/s',
        $meta['_body'], $m, PREG_SET_ORDER)) {
        $strip = function ($t) {
            $t = preg_replace('/\s+/u', ' ', preg_replace('/<[^>]+>/', '', $t));
            return trim(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        };
        $qs = array();
        foreach ($m as $qa) {
            $qs[] = array('@type' => 'Question', 'name' => $strip($qa[1]),
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => $strip($qa[2])));
        }
        $nodes[] = array('@type' => 'FAQPage', '@id' => $url . '#faq', 'mainEntity' => $qs);
    }
    $json = json_encode(array('@context' => 'https://schema.org', '@graph' => $nodes),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('JSON-LD encoding failed');
    }
    return '<script type="application/ld+json">' . str_replace('<', '\u003c', $json) . "</script>\n";
}

/** Fills the page shell (head + header + footer) with the page-specific parts. */
function bpAssemble(array $meta)
{
    $cfg = bpConfig();
    $url = $cfg['base'] . $meta['path'];
    return strtr(bpShell(), array(
        '{{title}}' => bpEsc($meta['title']),
        '{{description}}' => bpEsc($meta['description']),
        '{{canonical_tag}}' => '<link rel="canonical" href="' . $url . '">' . "\n",
        '{{social_tags}}' => bpSocialTags($meta, $url),
        '{{json_ld}}' => bpJsonLd($meta, $url),
        '{{breadcrumb}}' => bpBreadcrumb(isset($meta['crumbs']) ? $meta['crumbs'] : null),
        '{{content}}' => $meta['_body'],
    ));
}

function bpIndexMeta(array $posts)
{
    $cfg = bpConfig();
    $base = $cfg['base'];
    $body = str_replace('<!--POST_LIST-->', bpIndexHtml($posts), (string)@file_get_contents(__DIR__ . '/blog-index-body.html'));
    $meta = array('title' => $cfg['index']['title'], 'description' => $cfg['index']['description'], 'path' => '/blog/',
        'ptype' => 'CollectionPage', 'crumbs' => $cfg['index']['crumbs'], '_body' => $body);
    if ($posts) {
        $items = array();
        foreach ($posts as $i => $p) {
            $items[] = array('@type' => 'ListItem', 'position' => $i + 1, 'url' => $base . $p['url'], 'name' => $p['title']);
        }
        $meta['extra_nodes'] = array(array('@type' => 'ItemList', 'itemListElement' => $items));
    }
    return $meta;
}

function bpFeedXml(array $posts)
{
    $cfg = bpConfig();
    $base = $cfg['base'];
    $items = '';
    $lastIso = '2026-01-01';
    foreach ($posts as $p) {
        $d = explode('-', $p['published']);
        $pub = gmdate('D, d M Y', gmmktime(0, 0, 0, (int)$d[1], (int)$d[2], (int)$d[0])) . ' 08:00:00 +0000';
        $items .= '<item><title>' . bpEsc($p['title']) . '</title><link>' . $base . $p['url'] . '</link><guid isPermaLink="true">'
            . $base . $p['url'] . '</guid><pubDate>' . $pub . '</pubDate><category>' . bpEsc($p['category'])
            . '</category><description>' . bpEsc($p['description']) . '</description></item>';
        if ($p['updated'] > $lastIso) {
            $lastIso = $p['updated'];
        }
    }
    $d = explode('-', $lastIso);
    $last = gmdate('D, d M Y', gmmktime(0, 0, 0, (int)$d[1], (int)$d[2], (int)$d[0])) . ' 08:00:00 +0000';
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
        . '<title>مقالات بازوپرداز</title><link>' . $base . '/blog/</link><description>' . bpEsc($cfg['blog_description'])
        . '</description><language>fa</language><lastBuildDate>' . $last . '</lastBuildDate><atom:link href="' . $base
        . '/blog/feed.xml" rel="self" type="application/rss+xml"/>' . $items . '</channel></rss>' . "\n";
}

// ------------------------------------------------------------------------------------------------ publishing

/** Renders every file in memory first (a failure leaves the live site untouched), then writes them. */
function bpRenderAll()
{
    $posts = bpLoadPublished();
    $files = array();
    $files['blog/index.html'] = bpAssemble(bpIndexMeta($posts));
    foreach ($posts as $p) {
        $files['blog/' . $p['slug'] . '/index.html'] = bpAssemble(bpPostMeta($p, $posts));
    }
    $files['blog/feed.xml'] = bpFeedXml($posts);
    return array($posts, $files);
}

function bpRemovePage($slug)
{
    if (!bpValidSlug($slug)) {
        return;
    }
    $dir = bpRoot() . '/blog/' . $slug;
    if (is_file($dir . '/index.html')) {
        @unlink($dir . '/index.html');
    }
    if (is_dir($dir)) {
        @rmdir($dir);  // only removes it when empty
    }
}

function bpUpdateHome(array $posts)
{
    $path = bpRoot() . '/index.html';
    $html = @file_get_contents($path);
    if ($html === false) {
        return false;
    }
    $a = strpos($html, '<!--LATEST_POSTS_START-->');
    $b = strpos($html, '<!--LATEST_POSTS_END-->');
    if ($a === false || $b === false || $b < $a) {
        return false;
    }
    $new = substr($html, 0, $a) . '<!--LATEST_POSTS_START-->' . bpLatestHtml($posts) . substr($html, $b);
    bpWriteFile($path, $new);
    return true;
}

function bpUpdateSitemap(array $posts)
{
    $cfg = bpConfig();
    $base = $cfg['base'];
    $path = bpRoot() . '/sitemap.xml';
    $xml = @file_get_contents($path);
    if ($xml === false || strpos($xml, '</urlset>') === false) {
        return false;
    }
    $xml = preg_replace('#[ \t]*<url><loc>' . preg_quote($base, '#') . '/blog/[^<]*</loc>.*?</url>\r?\n?#s', '', $xml);
    $lines = '';
    $latest = '';
    foreach ($posts as $p) {
        $lines .= '  <url><loc>' . $base . $p['url'] . '</loc><lastmod>' . $p['updated'] . '</lastmod></url>' . "\n";
        if ($p['updated'] > $latest) {
            $latest = $p['updated'];
        }
    }
    $index = '  <url><loc>' . $base . '/blog/</loc><lastmod>' . ($latest !== '' ? $latest : date('Y-m-d')) . '</lastmod></url>' . "\n";
    $xml = str_replace('</urlset>', $index . $lines . '</urlset>', $xml);
    bpWriteFile($path, $xml);
    return true;
}

/**
 * Publishes the current state of blog-data/: all article pages, the /blog/ list, the RSS feed,
 * the "latest articles" block on the home page and the sitemap.
 * @return array list of human readable notes about what was done
 */
function bpPublishAll()
{
    list($posts, $files) = bpRenderAll();
    foreach ($files as $rel => $content) {
        bpWriteFile(bpRoot() . '/' . $rel, $content);
    }
    $notes = array(count($posts) . ' مقاله منتشر/به‌روز شد');
    // drafts must not stay online
    foreach (bpListPosts() as $row) {
        if ($row['status'] === 'draft') {
            bpRemovePage($row['slug']);
        }
    }
    $notes[] = bpUpdateHome($posts) ? 'بخش «تازه‌ترین مقالات» صفحهٔ اصلی به‌روز شد' : 'صفحهٔ اصلی بدون تغییر ماند (نشانه‌گذاری پیدا نشد)';
    $notes[] = bpUpdateSitemap($posts) ? 'sitemap.xml به‌روز شد' : 'sitemap.xml بدون تغییر ماند';
    return $notes;
}

// ------------------------------------------------------------------------------------------------ editor input

/**
 * Validates the editor form and returns array(meta, body, errors).
 * $existing = current meta when editing (slug can not change), or null for a new article.
 */
function bpFromInput(array $in, $existing)
{
    $cfg = bpConfig();
    $err = array();
    $get = function ($k) use ($in) {
        return isset($in[$k]) && is_string($in[$k]) ? trim($in[$k]) : '';
    };
    $len = function ($s) {
        return mb_strlen($s, 'UTF-8');
    };

    $slug = $existing ? $existing['slug'] : strtolower($get('slug'));
    if (!bpValidSlug($slug)) {
        $err[] = 'آدرس مقاله باید انگلیسی و با خط تیره باشد (مثل my-new-post) و ۳ تا ۸۰ کاراکتر.';
    } elseif (!$existing && is_file(bpPostPath($slug))) {
        $err[] = 'مقاله‌ای با این آدرس از قبل وجود دارد.';
    }
    $title = $get('title');
    if ($len($title) < 8 || $len($title) > 110) {
        $err[] = 'عنوان باید بین ۸ تا ۱۱۰ کاراکتر باشد.';
    }
    $desc = $get('description');
    if ($len($desc) < 60 || $len($desc) > 200) {
        $err[] = 'توضیح متا باید بین ۶۰ تا ۲۰۰ کاراکتر باشد (ایده‌آل ۱۱۰ تا ۱۵۵).';
    }
    $excerpt = $get('excerpt');
    if ($len($excerpt) < 20 || $len($excerpt) > 300) {
        $err[] = 'خلاصه باید بین ۲۰ تا ۳۰۰ کاراکتر باشد.';
    }
    $category = $get('category');
    $categorySlug = strtolower($get('category_slug'));
    if ($len($category) < 2 || $len($category) > 40) {
        $err[] = 'نام دسته باید بین ۲ تا ۴۰ کاراکتر باشد.';
    }
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $categorySlug) || strlen($categorySlug) > 40) {
        $err[] = 'شناسهٔ دسته باید انگلیسی و با خط تیره باشد (مثل mini-app).';
    }
    $icon = $get('icon');
    if (!isset($cfg['icons'][$icon])) {
        $err[] = 'آیکون نامعتبر است.';
    }
    $published = $get('published');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $published) || !checkdate((int)substr($published, 5, 2), (int)substr($published, 8, 2), (int)substr($published, 0, 4))) {
        $err[] = 'تاریخ انتشار معتبر نیست.';
    }
    $ctaType = $get('cta_type');
    if (!isset($cfg['cta_types'][$ctaType])) {
        $err[] = 'نوع پروژهٔ دکمهٔ فراخوان نامعتبر است.';
    }
    $service = null;
    $svc = $get('service');
    if ($svc !== '') {
        foreach ($cfg['services'] as $s) {
            if ($s['href'] === $svc) {
                $service = array('label' => $s['label'], 'href' => $s['href']);
            }
        }
        if (!$service) {
            $err[] = 'خدمت مرتبط نامعتبر است.';
        }
    }
    $keywords = array();
    foreach (explode(',', str_replace('،', ',', $get('keywords'))) as $k) {
        $k = trim($k);
        if ($k !== '' && count($keywords) < 12) {
            $keywords[] = mb_substr($k, 0, 60, 'UTF-8');
        }
    }
    $faq = array();
    $qs = isset($in['faq_q']) && is_array($in['faq_q']) ? $in['faq_q'] : array();
    $as = isset($in['faq_a']) && is_array($in['faq_a']) ? $in['faq_a'] : array();
    foreach ($qs as $i => $q) {
        $q = is_string($q) ? trim($q) : '';
        $a = isset($as[$i]) && is_string($as[$i]) ? trim($as[$i]) : '';
        if ($q === '' && $a === '') {
            continue;
        }
        if ($q === '' || $a === '') {
            $err[] = 'هر سوال متداول باید هم سوال و هم پاسخ داشته باشد.';
            break;
        }
        if (count($faq) < 10) {
            $faq[] = array(mb_substr($q, 0, 200, 'UTF-8'), mb_substr($a, 0, 1500, 'UTF-8'));
        }
    }
    $image = $get('image');
    if ($image !== '' && (!preg_match('#^/images/[A-Za-z0-9_./-]+$#', $image) || strpos($image, '..') !== false)) {
        $err[] = 'مسیر تصویر باید مثل /images/blog/name.jpg باشد.';
    }
    $body = isset($in['body']) && is_string($in['body']) ? str_replace("\r\n", "\n", trim($in['body'])) : '';
    if ($len($body) < 200) {
        $err[] = 'متن مقاله خیلی کوتاه است (حداقل ۲۰۰ کاراکتر).';
    }
    if ($len($body) > 80000) {
        $err[] = 'متن مقاله بیش از حد طولانی است.';
    }
    $status = $get('status') === 'draft' ? 'draft' : 'published';
    $today = date('Y-m-d');
    $updated = $existing && isset($existing['updated']) ? $existing['updated'] : $published;
    if ($existing && $status === 'published') {
        $updated = $today;  // editing a published article records the update date (Google sees freshness)
    }
    if ($updated < $published) {
        $updated = $published;
    }

    $meta = array(
        'title' => $title, 'description' => $desc, 'excerpt' => $excerpt, 'category' => $category,
        'category_slug' => $categorySlug, 'icon' => $icon, 'published' => $published, 'updated' => $updated,
        'cta_type' => $ctaType, 'keywords' => $keywords, 'faq' => $faq, 'status' => $status, 'slug' => $slug,
    );
    if ($service) {
        $meta['service'] = $service;
    }
    if ($image !== '') {
        $meta['image'] = $image;
    }
    return array($meta, $body, $err);
}
