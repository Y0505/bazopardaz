/* Contact / project-request form. Posts to the existing /api/submit-request.php (same field names). */
(function () {
  'use strict';

  var form = document.getElementById('projectForm');
  if (!form) return;

  var $ = function (id) { return document.getElementById(id); };
  var fields = {
    name: { el: $('pf-name'), err: $('err-name') },
    method: { el: $('pf-contact-method'), err: $('err-method') },
    value: { el: $('pf-contact-value'), err: $('err-value') },
    type: { el: $('pf-project-type'), err: $('err-type') },
    desc: { el: $('pf-description'), err: $('err-desc') }
  };
  var budget = $('pf-budget');
  var honeypot = $('pf-website');
  var msg = $('formMessage');
  var btn = $('submitBtn');
  var btnText = $('submitText');
  var btnLoading = $('submitLoading');
  var success = $('formSuccess');
  var successText = $('successText');
  var counter = $('descCount');
  var submitting = false;

  var HINTS = {
    '': { ph: 'آیدی، شماره یا ایمیل', mode: 'text', ac: 'off' },
    telegram: { ph: 'آیدی تلگرام، مثلاً @username', mode: 'text', ac: 'off' },
    bale: { ph: 'آیدی بله یا شماره موبایل', mode: 'text', ac: 'off' },
    whatsapp: { ph: 'شماره واتساپ، مثلاً 0912…', mode: 'tel', ac: 'tel' },
    email: { ph: 'name@example.com', mode: 'email', ac: 'email' },
    phone: { ph: 'شماره موبایل یا تلفن', mode: 'tel', ac: 'tel' },
    other: { ph: 'راه تماس خود را بنویسید', mode: 'text', ac: 'off' }
  };

  /* Persian / Arabic-Indic digits -> ASCII, so numbers and e-mails are stored in a usable form */
  function toLatinDigits(s) {
    return s.replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 1776); })
            .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 1632); });
  }

  function applyHint() {
    var h = HINTS[fields.method.el.value] || HINTS[''];
    var v = fields.value.el;
    v.placeholder = h.ph;
    v.setAttribute('inputmode', h.mode);
    v.setAttribute('autocomplete', h.ac);
  }

  function setError(key, text) {
    var f = fields[key];
    f.err.textContent = text || '';
    if (text) f.el.setAttribute('aria-invalid', 'true');
    else f.el.removeAttribute('aria-invalid');
  }

  function clearAll() {
    Object.keys(fields).forEach(function (k) { setError(k, ''); });
    msg.className = 'form-message';
    msg.textContent = '';
  }

  function validate() {
    var first = null;
    function fail(key, text) { setError(key, text); if (!first) first = fields[key].el; }

    var name = fields.name.el.value.trim();
    var method = fields.method.el.value;
    var value = fields.value.el.value.trim();
    var type = fields.type.el.value;
    var desc = fields.desc.el.value.trim();

    if (name.length < 2) fail('name', 'لطفاً نام خود را وارد کنید.');
    if (!method) fail('method', 'لطفاً روش تماس را انتخاب کنید.');

    if (method === 'email' || method === 'phone' || method === 'whatsapp') value = toLatinDigits(value);
    else if (/^[\d۰-۹٠-٩+\s-]+$/.test(value)) value = toLatinDigits(value);

    if (value.length < 3) fail('value', 'لطفاً اطلاعات تماس را وارد کنید.');
    else if (method === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) fail('value', 'ایمیل واردشده معتبر نیست.');
    else if ((method === 'phone' || method === 'whatsapp') && value.replace(/\D/g, '').length < 7) fail('value', 'شماره واردشده کامل نیست.');

    if (!type) fail('type', 'لطفاً نوع پروژه را انتخاب کنید.');
    if (desc.length < 10) fail('desc', 'توضیحات پروژه باید حداقل ۱۰ کاراکتر باشد.');

    if (first) { first.focus(); return null; }
    return { name: name, method: method, value: value, type: type, desc: desc };
  }

  function setBusy(on) {
    submitting = on;
    btn.disabled = on;
    btnText.hidden = on;
    btnLoading.hidden = !on;
  }

  function showFormError(text) {
    msg.className = 'form-message is-visible form-message--error';
    msg.textContent = text;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (submitting) return;
    clearAll();
    var data = validate();
    if (!data) return;

    var fd = new FormData();
    fd.append('name', data.name);
    fd.append('contact_method', data.method);
    fd.append('contact_value', data.value);
    fd.append('project_type', data.type);
    fd.append('budget', budget.value);
    fd.append('description', data.desc);
    fd.append('website_url', honeypot ? honeypot.value : '');

    setBusy(true);
    fetch('/api/submit-request.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) {
        return r.text().then(function (t) {
          var j = null;
          try { j = JSON.parse(t); } catch (err) { /* non-JSON answer */ }
          return j || { success: false, message: '' };
        });
      })
      .then(function (d) {
        setBusy(false);
        if (d.success) {
          successText.textContent = d.message || successText.textContent;
          form.reset();
          applyHint();
          updateCount();
          form.hidden = true;
          success.hidden = false;
          success.focus();
          if (success.scrollIntoView) success.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          showFormError(d.message || 'خطا در ثبت درخواست. لطفاً دوباره تلاش کنید.');
        }
      })
      .catch(function () {
        setBusy(false);
        showFormError('خطا در ارتباط با سرور. اتصال اینترنت را بررسی کنید یا از راه‌های ارتباطی پایین صفحه استفاده کنید.');
      });
  });

  /* clear a field's error as soon as the person fixes it */
  Object.keys(fields).forEach(function (k) {
    var el = fields[k].el;
    el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', function () { setError(k, ''); });
  });
  fields.method.el.addEventListener('change', applyHint);

  function updateCount() { if (counter) counter.textContent = fields.desc.el.value.length + ' / 5000'; }
  fields.desc.el.addEventListener('input', updateCount);

  var again = $('anotherRequest');
  if (again) again.addEventListener('click', function () {
    success.hidden = true;
    form.hidden = false;
    fields.name.el.focus();
  });

  /* Deep links from service pages: /contact/#type=telegram_bot  (hash, so no duplicate URLs for search engines) */
  function prefillFromHash() {
    var m = /(?:^#|&)type=([a-z_]+)/.exec(window.location.hash);
    if (!m) return;
    var sel = fields.type.el;
    for (var i = 0; i < sel.options.length; i++) {
      if (sel.options[i].value === m[1]) { sel.value = m[1]; return; }
    }
  }
  prefillFromHash();
  window.addEventListener('hashchange', prefillFromHash);
  applyHint();
  updateCount();
})();
