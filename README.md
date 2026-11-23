# بازوپرداز — ساختار پروژه

```
public_html/   خروجی نهایی (HTML استاتیک)؛ همین پوشه روی هاست آپلود می‌شود
src/
  build.py         اسکریپت بیلد (فقط Python 3، بدون نصب هیچ پکیجی)
  site.json        تنظیمات مشترک: منو، لینک‌های فوتر، لینک بله/تلگرام، سال کپی‌رایت
  layout.html      اسکلت <head> و بدنه، فقط یک‌بار
  partials/        header.html · footer.html · icons.html (آیدی آیکون‌ها)
  pages/           محتوای یکتای هر صفحه (*.src.html) + متا/سئو/breadcrumb در ابتدای فایل
docs/              مستندات قبلی + پیشنهاد .htaccess + گزارش
```

## کار روزمره
1. هدر/فوتر/منو: `src/partials/` یا `src/site.json` را ویرایش کنید.
2. متن یا سئوی یک صفحه: همان فایل در `src/pages/` (بالای فایل بخش JSON: title, description, crumbs).
3. استایل و جاوااسکریپت: مستقیم در `public_html/css/style.css` و `public_html/js/`.
4. اجرا: `python3 src/build.py`  ← همهٔ صفحات + sitemap.xml دوباره ساخته می‌شوند و نسخهٔ cache فایل‌های CSS/JS خودکار عوض می‌شود.
5. محتوای `public_html` را آپلود کنید.

نکته: فایل‌های HTML داخل `public_html` را دستی ویرایش نکنید؛ با بیلد بعدی بازنویسی می‌شوند.
