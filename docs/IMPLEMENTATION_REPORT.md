# گزارش بازطراحی بازوپرداز

## ۱. تغییرات انجام شده

### معماری سایت
- تبدیل سایت از یک صفحه‌ای (SPA-like) به ساختار چند صفحه‌ای با URL‌های تمیز
- ایجاد صفحات جداگانه برای هر خدمت (ربات تلگرام، ربات بله، ربات روبیکا، طراحی وبسایت، مینی‌اپ)
- ایجاد صفحه نمونه‌کارها با پروفایل جداگانه برای هر پروژه
- حفظ محتوای اصلی و بهبود ساختار آن

### طراحی UI/UX
- طراحی مجدد سیستم CSS با استفاده از CSS Custom Properties
- طراحی ناوبری جدید با CTA "شروع پروژه"
- طراحی مجدد هدر با پیام واضح و تبدیل بالا
- طراحی کارت‌های خدمات با آیکون‌های اختصاصی SVG
- بخش "چرا بازوپرداز" برای ایجاد اعتماد
- طراحی مجدد بخش نمونه‌کارها با کارت‌های حرفه‌ای
- بخش مسیر همکاری با مراحل مشخص
- فرم تماس با قابلیت انتقال به بله
- فوتر کامل با لینک‌های مفید
- دکمه بازگشت به بالا

### SEO
- متا تگ‌های اختصاصی برای هر صفحه (title, description, canonical)
- Open Graph metadata برای اشتراک‌گذاری در شبکه‌های اجتماعی
- Schema.org structured data (Person, WebSite, Organization, Service)
- ساختار heading صحیح (H1, H2, H3)
- HTML معنایی (nav, header, section, footer)
- breadcrumb navigation
- لینک‌سازی داخلی بین صفحات
- sitemap.xml با اولویت‌بندی
- robots.txt
- URL‌های تمیز و خوانا

### عملکرد
- حذف Font Awesome CDN (صرفه‌جویی ~100KB)
- استفاده از آیکون‌های SVG اختصاصی (بدون وابستگی)
- فایل CSS مشترک برای تمام صفحات
- Lazy loading برای تصاویر
- حذف انیمیشن‌های غیرضروری
- حذف backdrop-filterهای سنگین
- کد JavaScript حداقلی و بدون وابستگی

### دسترسی‌پذیری
- aria-label برای ناوبری و دکمه‌ها
- aria-expanded برای منوی موبایل و FAQ
- focus-visible برای ناوبری با کیبورد
- label برای فیلدهای فرم
- alt text برای تصاویر
- نقش‌های معنایی (role="navigation", role="contentinfo")

### طراحی ریسپانسیو
- Mobile-first approach
- ناوبری موبایل با منوی slide-out
- گریدهای تطبیقی (1-2-3-4 ستونه)
- فرم تماس ریسپانسیو

---

## ۲. صفحات ایجاد شده

| صفحه | URL | توضیح |
|------|-----|-------|
| صفحه اصلی | `/` | هدر، خدمات، چرا بازوپرداز، نمونه‌کارها، مسیر همکاری، FAQ، CTA |
| خدمات | `/services/` | فهرست تمام خدمات |
| ربات تلگرام | `/services/telegram-bot/` | جزئیات خدمت ربات تلگرام |
| ربات بله | `/services/bale-bot/` | جزئیات خدمت ربات بله |
| ربات روبیکا | `/services/rubika-bot/` | جزئیات خدمت ربات روبیکا |
| طراحی وبسایت | `/services/web-development/` | جزئیات خدمت طراحی وبسایت |
| مینی‌اپ | `/services/mini-app/` | جزئیات خدمت مینی‌اپ و لندینگ پیج |
| نمونه‌کارها | `/portfolio/` | فهرست پروژه‌ها |
| فروش یار | `/portfolio/froshyar/` | جزئیات پروژه فروش یار |
| شطرنج | `/portfolio/chess/` | جزئیات پروژه بازی شطرنج |
| یارن گیم | `/portfolio/yaren/` | جزئیات پروژه یارن گیم |
| درباره ما | `/about/` | معرفی توسعه‌دهنده |
| تماس با ما | `/contact/` | فرم ارسال درخواست پروژه |
| صفحه ۴۰۴ | `/404.html` | صفحه خطا |

---

## ۳. بهبودهای SEO

- ✅ Title و meta description اختصاصی برای هر صفحه
- ✅ Canonical URL برای هر صفحه
- ✅ Open Graph metadata
- ✅ Schema.org structured data (Person, WebSite, Organization, Service)
- ✅ ساختار heading صحیح
- ✅ HTML معنایی
- ✅ Breadcrumb navigation
- ✅ لینک‌سازی داخلی
- ✅ sitemap.xml
- ✅ robots.txt
- ✅ URL‌های تمیز
- ✅ Alt text برای تصاویر
- ✅ hreflang-ready architecture (آماده برای نسخه انگلیسی)

---

## ۴. بهبودهای عملکرد

- ✅ حذف Font Awesome (~100KB صرفه‌جویی)
- ✅ آیکون‌های SVG اختصاصی (0KB وابستگی)
- ✅ CSS مشترک (فایل واحد)
- ✅ Lazy loading برای تصاویر
- ✅ کد JavaScript حداقلی (~2KB)
- ✅ حذف backdrop-filter سنگین
- ✅ حذف انیمیشن‌های غیرضروری
- ✅ فونت Vazirmatn از Google Fonts (MIT license)

---

## ۵. سیستم درخواست پروژه

### معماری

```
                    ┌───────────────┐
                    │    مشتری     │
                    └───────┬───────┘
                            │
                            ▼
                    ┌───────────────┐
                    │  فرم درخواست  │
                    └───────┬───────┘
                            │
                            ▼
                    ┌───────────────┐
                    │      PHP      │
                    └───────┬───────┘
                            │
                            ▼
                    ┌───────────────┐
                    │     MySQL     │
                    │   PENDING     │
                    └───────┬───────┘
                            │
                    هر ۵ دقیقه
                            │
                            ▼
                    ┌───────────────┐
                    │   CRON JOB    │
                    └───────┬───────┘
                            │
                            ▼
                    ┌───────────────┐
                    │   BALE BOT    │
                    └───────┬───────┘
                            │
                            ▼
                    ┌───────────────┐
                    │   حساب بله    │
                    │   من          │
                    └───────────────┘
```

**قانون اساسی: MYSQL اول → BALE دوم**

### فایل‌های PHP ایجاد شده

| فایل | مسیر | هدف |
|------|------|------|
| پیکربندی | `/home/USER/secure/config.php` (خارج از public_html) | تنظیمات دیتابیس و Bale |
| نمونه پیکربندی | `config.example.php` (داخل پروژه) | الگوی پیکربندی با جایگزین‌ها |
| اتصال دیتابیس | `includes/db.php` | اتصال PDO به MySQL |
| Bale Helper | `includes/bale.php` | ارسال پیام از طریق Bale API |
| API ارسال فرم | `api/submit-request.php` | ذخیره درخواست در MySQL |
| Cron Job | `cron/send-pending-requests.php` | ارسال درخواست‌های pending به Bale |
| داشبورد مدیریت | `admin/index.php` | مشاهده و مدیریت درخواست‌ها |
| تنظیم رمز | `admin/setup.php` | تنظیم رمز عبور مدیر (حذف پس از استفاده) |

### Schema دیتابیس

```sql
project_requests
├── id (INT, PK, AUTO_INCREMENT)
├── name (VARCHAR 200)
├── contact_method (ENUM: telegram, bale, whatsapp, email, phone, other)
├── contact_value (VARCHAR 500)
├── project_type (ENUM: telegram_bot, discord_bot, bale_bot, rubika_bot, website, other)
├── budget (VARCHAR 200)
├── description (TEXT)
├── status (ENUM: pending, sent, failed)
├── attempts (TINYINT, default 0)
├── last_attempt_at (DATETIME)
├── sent_at (DATETIME)
├── error_message (VARCHAR 1000)
├── created_at (DATETIME)
└── ip_address (VARCHAR 45)

Indexes: status, created_at, status+created_at
```

### فیلدهای فرم

| فیلد | نوع | الزامی | توضیح |
|------|------|--------|-------|
| نام | text | ✅ | حداقل ۲ کاراکتر |
| روش تماس | select | ✅ | تلگرام/بله/واتساپ/ایمیل/تلفن/سایر |
| اطلاعات تماس | text | ✅ | آیدی، شماره یا ایمیل |
| نوع پروژه | select | ✅ | ربات تلگرام/دیسکورد/بله/روبیکا/وبسایت/سایر |
| بودجه | select | ❌ | کمتر از ۱.۵M تا ۷M تومان |
| توضیحات | textarea | ✅ | حداقل ۱۰ کاراکتر |

### سیستم امنیت

- **Honeypot field** برای جلوگیری از ارسال خودکار
- **Rate limiting** (حداقل ۶۰ ثانیه بین ارسال‌ها)
- **Server-side validation** (فقط اعتماد به اعتبارسنجی سمت سرور)
- **Input sanitization** (htmlspecialchars قبل از ذخیره)
- **Input length limits** (maxlength در HTML + بررسی سمت سرور)
- **Duplicate prevention** (غیرفعال کردن دکمه ارسال)
- **Session timeout** برای پنل مدیر (۳۰ دقیقه)

### امنیت فایل پیکربندی

فایل پیکربندی حساس (`config.php`) **خارج از public_html** ذخیره می‌شود:

```
/home/jckhnwrm/secure/config.php  ← خارج از دسترس وب!
/home/jckhnwrm/public_html/       ← وبسایت
```

- فایل PHP با `require` می‌تواند فایل خارج از public_html را بخواند
- بازدیدکنندگان از طریق مرورگر نمی‌توانند به توکن‌ها دسترسی پیدا کنند
- `.htaccess` دسترسی به `secure/config.php` داخل public_html را نیز مسدود می‌کند
- فایل `config.example.php` با جایگزین‌ها در پروژه موجود است
- `.gitignore` فایل‌های حساس را از Git حذف می‌کند

### احراز هویت مدیر

- رمز عبور با `password_hash()` هش می‌شود
- نشست مدیر با `session_start()` مدیریت می‌شود
- انقضای نشست: ۳۰ دقیقه
- فایل `.htaccess` دسترسی مستقیم HTTP به includes و secure را مسدود می‌کند

### Cron Job

- حداکثر ۱۰ درخواست در هر اجرا
- حداکثر ۵ تلاش برای هر درخواست
- پس از ۵ تلاش ناموفق: `status = failed`
- امنیت: نیاز به CLI یا secret key URL
- لاگ‌گیری از خطاها

### وضعیت درخواست

| وضعیت | توضیح |
|-------|-------|
| `pending` | ذخیره شده، هنوز به Bale ارسال نشده |
| `sent` | با موفقیت به Bale ارسال شده |
| `failed` | پس از ۵ تلاش ناموفق |

### پیام Bale

فرمت پیام نوتیفیکیون:

```
🚀 درخواست پروژه جدید

🆔 شماره درخواست:
#104

👤 نام:
[نام مشتری]

📱 روش تماس:
[تلگرام]

📞 اطلاعات تماس:
[@username]

💻 نوع پروژه:
[ربات تلگرام]

💰 بودجه تقریبی:
[۲,۰۰۰,۰۰۰ تومان]

📝 توضیحات پروژه:
[توضیحات مشتری]

🕐 زمان ثبت:
[2026-08-26 14:30:00]

---
🌐 bazopardaz.ir
```

---

## ۶. وابستگی‌ها

| نام | نسخه | مجوز | هدف |
|-----|------|------|-----|
| Vazirmatn | latest | MIT | فونت فارسی |

**توضیح:** تنها وابستگی خارجی، فونت Vazirmatn از Google Fonts است. تمامی کدهای PHP، CSS و JavaScript اختصاصی هستند.

---

## ۷. اصالت و کپی‌رایت

- ✅ کد PHP از صفر و به صورت اختصاصی نوشته شده
- ✅ کد CSS از صفر نوشته شده
- ✅ آیکون‌های SVG اختصاصی طراحی شده
- ✅ محتوای متنی اصیل
- ✅ طراحی UI اصیل
- ✅ هیچ کدی از منابع خارجی کپی نشده
- ✅ اطلاعات جعلی وجود ندارد

---

## ۸. راهنمای استقرار

### محل فایل پیکربندی

**⚠️ فایل `config.php` باید خارج از public_html باشد!**

```
/home/jckhnwrm/secure/config.php  ← اینجا!
```

jckhnwrm = نام حساب هاست شما (همان FTP username)

**مراحل:**
1. `config.example.php` را کپی کنید
2. نامش را به `config.php` تغییر دهید
3. مقادیر واقعی را جایگزین کنید
4. در `/home/jckhnwrm/secure/` آپلود کنید

### تنظیم توکن Bale

1. با ربات @BotFather در بله صحبت کنید
2. توکن ربات را بگیرید
3. در `/home/jckhnwrm/secure/config.php` قرار دهید:
   ```php
   'bale_bot_token' => 'YOUR_ACTUAL_TOKEN',
   ```

### تنظیم Chat ID Bale

1. ربات را در چت خصوصی اضافه کنید
2. به `https://api.ble.ir/botYOUR_TOKEN/getUpdates` بروید
3. `chat.id` را پیدا کنید
4. در `/home/jckhnwrm/secure/config.php` قرار دهید:
   ```php
   'bale_admin_chat_id' => 'YOUR_CHAT_ID',
   ```

### تنظیم Cron Job

از پنل مدیریت هاست:
```
* * * * * php /home/jckhnwrm/public_html/cron/send-pending-requests.php
```

یا با URL:
```
* * * * * curl -s "https://yourdomain.com/cron/send-pending-requests.php?key=YOUR_SECRET_KEY" > /dev/null 2>&1
```

### تنظیم رمز مدیر

1. `admin/setup.php` را باز کنید
2. رمز عبور تنظیم کنید
3. فایل `admin/setup.php` را **فوراً حذف کنید**

**راهنمای کامل استقرار:** فایل `DEPLOYMENT.md` را بخوانید.

---

## ۹. مشکلات باقیمانده

### قابل حل:
1. **تصاویر OG:** تصویر OG موجود باید با کیفیت و ابعاد مناسب (1200x630) به‌روز شود.
2. **بلاگ:** صفحه بلاگ ایجاد شده اما محتوایی ندارد.
3. **نسخه انگلیسی:** معماری آماده اضافه کردن نسخه انگلیسی است.

### نیاز به بررسی:
1. ساختار URL‌ها با سرور وب سازگار باشد (پیکربندی mod_rewrite)
2. صفحه 404 توسط سرور وب فعال شود
3. HTTPS فعال باشد
4. تست نهایی در مرورگرهای مختلف
5. تست فرم ارسال پروژه در محیط واقعی

---

*گزارش تولید شده در تاریخ ۲۶ آگوست ۲۰۲۶*
