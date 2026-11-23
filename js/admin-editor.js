/* Article editor behaviour: Markdown toolbar, live preview, counters, SEO checklist, FAQ rows,
   local draft backup, keep-alive. No dependencies. */
(function () {
  'use strict';

  var form = document.getElementById('postForm');
  if (!form) return;
  var $ = function (id) { return document.getElementById(id); };
  var body = $('f-body'), title = $('f-title'), desc = $('f-desc');
  var dirty = false, submitting = false;

  /* ---------- Markdown toolbar ---------- */
  function wrap(before, after, placeholder) {
    var s = body.selectionStart, e = body.selectionEnd, sel = body.value.slice(s, e) || placeholder;
    body.setRangeText(before + sel + after, s, e, 'end');
    body.focus();
    body.setSelectionRange(s + before.length, s + before.length + sel.length);
  }
  function linePrefix(prefix, placeholder) {
    var s = body.selectionStart, e = body.selectionEnd, v = body.value;
    var start = v.lastIndexOf('\n', s - 1) + 1;
    var chunk = v.slice(start, e) || placeholder;
    var lines = chunk.split('\n').map(function (l, i) { return (typeof prefix === 'function' ? prefix(i) : prefix) + l; });
    var lead = start > 0 && v.charAt(start - 1) !== '\n' ? '\n' : '';
    body.setRangeText(lead + lines.join('\n'), start, e, 'end');
    body.focus();
  }
  var actions = {
    h2: function () { linePrefix('## ', 'عنوان بخش'); },
    h3: function () { linePrefix('### ', 'زیرعنوان'); },
    bold: function () { wrap('**', '**', 'متن ضخیم'); },
    italic: function () { wrap('*', '*', 'متن کج'); },
    link: function () {
      var url = window.prompt('آدرس لینک (برای صفحهٔ داخلی مثل /services/telegram-bot/):', '/');
      if (url) wrap('[', '](' + url + ')', 'متن لینک');
    },
    ul: function () { linePrefix('- ', 'مورد'); },
    ol: function () { linePrefix(function (i) { return (i + 1) + '. '; }, 'مورد'); },
    quote: function () { linePrefix('> ', 'نکتهٔ مهم'); },
    table: function () {
      body.setRangeText('\n| ستون ۱ | ستون ۲ |\n|---|---|\n| مقدار | مقدار |\n', body.selectionStart, body.selectionEnd, 'end');
      body.focus();
    },
    code: function () { wrap('\n```\n', '\n```\n', 'کد'); }
  };
  $('toolbar').addEventListener('click', function (e) {
    var b = e.target.closest('button[data-md]');
    if (b && actions[b.getAttribute('data-md')]) { actions[b.getAttribute('data-md')](); update(); }
  });

  /* ---------- counters + SEO checklist ---------- */
  function len(s) { return Array.from(s.trim()).length; }
  function words(s) { return (s.match(/[\p{L}\p{N}_]+/gu) || []).length; }
  function count(id, n, lo, hi, max) {
    var el = $(id);
    el.textContent = n + ' / ' + max;
    el.style.color = n === 0 ? '' : (n >= lo && n <= hi ? '#4ade80' : '#fb923c');
  }
  function update() {
    count('c-title', len(title.value), 30, 60, 110);
    count('c-desc', len(desc.value), 110, 155, 200);
    var text = body.value, w = words(text);
    $('stats').textContent = w + ' کلمه · حدود ' + Math.max(1, Math.ceil(w / 190)) + ' دقیقه مطالعه';
    var internal = (text.match(/\]\(\//g) || []).length;
    var h2 = (text.match(/^## /gm) || []).length;
    var checks = [
      [len(title.value) >= 30 && len(title.value) <= 60, 'عنوان بین ۳۰ تا ۶۰ کاراکتر باشد'],
      [len(desc.value) >= 110 && len(desc.value) <= 155, 'توضیح متا بین ۱۱۰ تا ۱۵۵ کاراکتر باشد'],
      [w >= 500, 'حداقل ۵۰۰ کلمه (مقاله‌های کامل‌تر معمولاً بهتر رتبه می‌گیرند)'],
      [h2 >= 3, 'حداقل ۳ عنوان بخش (##) برای ساختار و فهرست مطالب'],
      [internal >= 2, 'حداقل ۲ لینک داخلی (به خدمات یا مقاله‌های دیگر)'],
      [/^[a-z0-9]+(-[a-z0-9]+)*$/.test($('f-slug').value), 'آدرس انگلیسی و با خط تیره باشد'],
      [$('f-cat').value.trim() !== '' && $('f-catslug').value.trim() !== '', 'دسته‌بندی کامل باشد']
    ];
    $('seoList').innerHTML = checks.map(function (c) {
      return '<li class="' + (c[0] ? 'ok' : 'warn') + '">' + (c[0] ? '✓ ' : '• ') + c[1] + '</li>';
    }).join('');
  }
  form.addEventListener('input', function () { dirty = true; update(); scheduleSave(); });
  update();

  /* ---------- category picker ---------- */
  $('f-catpick').addEventListener('change', function () {
    var o = this.options[this.selectedIndex];
    if (o.value) { $('f-catslug').value = o.value; $('f-cat').value = o.getAttribute('data-name'); update(); dirty = true; }
  });

  /* ---------- FAQ rows ---------- */
  $('addFaq').addEventListener('click', function () {
    var node = $('faqTpl').content.cloneNode(true);
    $('faqList').appendChild(node);
    var rows = $('faqList').querySelectorAll('.faq-edit');
    rows[rows.length - 1].querySelector('input').focus();
  });
  $('faqList').addEventListener('click', function (e) {
    if (e.target.classList.contains('faq-remove')) { e.target.closest('.faq-edit').remove(); dirty = true; scheduleSave(); }
  });

  /* ---------- write / preview tabs ---------- */
  var tabW = $('tab-write'), tabP = $('tab-preview'), prev = $('preview');
  function showTab(preview) {
    tabW.setAttribute('aria-pressed', preview ? 'false' : 'true'); tabW.setAttribute('aria-selected', preview ? 'false' : 'true');
    tabP.setAttribute('aria-pressed', preview ? 'true' : 'false'); tabP.setAttribute('aria-selected', preview ? 'true' : 'false');
    body.hidden = preview; prev.hidden = !preview; $('toolbar').hidden = preview;
  }
  tabW.addEventListener('click', function () { showTab(false); });
  tabP.addEventListener('click', function () {
    showTab(true);
    prev.innerHTML = '<p>در حال ساخت پیش‌نمایش…</p>';
    var fd = new FormData();
    fd.append('csrf', form.getAttribute('data-csrf'));
    fd.append('body', body.value);
    form.querySelectorAll('[name="faq_q[]"]').forEach(function (q) { fd.append('faq_q[]', q.value); });
    form.querySelectorAll('[name="faq_a[]"]').forEach(function (a) { fd.append('faq_a[]', a.value); });
    fetch('/admin/post-preview.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { prev.textContent = d.error || 'خطا در پیش‌نمایش'; return; }
        prev.innerHTML = d.html || '<p>(متن خالی است)</p>';   // HTML comes from the server-side renderer, which escapes all input
      })
      .catch(function () { prev.textContent = 'ارتباط با سرور برقرار نشد.'; });
  });

  /* ---------- local backup of unsaved work ---------- */
  var key = 'bp-draft:' + (form.getAttribute('data-slug') || 'new');
  var saveTimer = null;
  function snapshot() {
    var data = {};
    form.querySelectorAll('input[name], textarea[name], select[name]').forEach(function (el) {
      if (el.name === 'csrf' || el.name.indexOf('faq_') === 0) return;
      data[el.name] = el.value;
    });
    return data;
  }
  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () {
      try { localStorage.setItem(key, JSON.stringify({ t: Date.now(), d: snapshot() })); } catch (e) { /* storage full / blocked */ }
    }, 1500);
  }
  try {
    var saved = JSON.parse(localStorage.getItem(key) || 'null');
    if (saved && saved.d && saved.d.body && saved.d.body !== body.value) {
      $('draftBanner').hidden = false;
      $('restoreDraft').addEventListener('click', function () {
        Object.keys(saved.d).forEach(function (n) { var el = form.elements[n]; if (el && n !== 'slug' || (el && !form.getAttribute('data-slug'))) el.value = saved.d[n]; });
        $('draftBanner').hidden = true; dirty = true; update();
      });
      $('dropDraft').addEventListener('click', function () { localStorage.removeItem(key); $('draftBanner').hidden = true; });
    }
  } catch (e) { /* ignore */ }
  form.addEventListener('submit', function () {
    submitting = true;
    try { localStorage.removeItem(key); } catch (e) { /* ignore */ }
  });

  /* ---------- keep the login alive while writing, warn before leaving ---------- */
  setInterval(function () { fetch('/admin/posts.php?ping=1', { credentials: 'same-origin' }).catch(function () {}); }, 4 * 60 * 1000);
  window.addEventListener('beforeunload', function (e) {
    if (dirty && !submitting) { e.preventDefault(); e.returnValue = ''; }
  });
})();
