# راهنمای استقرار بازوپرداز روی هاست اشتراکی

## خلاصه معماری

```
/home/jckhnwrm/          ← ریشه حساب هاست (خارج از دسترس وب)
├── secure/
│   └── config.php            ← رمزهای عبور و توکن‌ها (امن!)
└── public_html/              ← ریشه وبسایت (قابل دسترس از اینترنت)
    ├── index.html
    ├── css/
    ├── services/
    ├── contact/
    ├── api/
    ├── cron/
    ├── admin/
    ├── includes/
    │   ├── db.php            ← کانفیگ را از ../secure/config.php می‌خواند
    │   └── bale.php
    └── ...
```

**نکته امنیتی مهم:** فایل `secure/config.php` در **خارج از public_html** قرار می‌گیرد.
این یعنی هیچ‌کس از طریق مرورگر نمی‌تواند به توکن‌ها و رمزهای عبور دسترسی پیدا کند.

---

## مراحل استقرار

### مرحله ۱: آماده‌سازی دیتابیس

1. وارد پنل مدیریت هاست شوید
2. بخش **MySQL Databases** را باز کنید
3. یک دیتابیس جدید بسازید (مثلاً `bazopardaz_db`)
4. یک کاربر دیتابیس بسازید و به دیتابیس اختصاص دهید
5. نام دیتابیس، نام کاربری و رمز را یادداشت کنید

### مرحله ۲: ایجاد جدول‌ها

1. وارد **phpMyAdmin** شوید
2. دیتابیسی که ساختید را انتخاب کنید
3. روی تب **SQL** کلیک کنید
4. محتوای فایل `database/schema.sql` را کپی و پیست کنید
5. روی **Go** کلیک کنید تا جدول‌ها ایجاد شوند

### مرحله ۳: ایجاد پوشه secure خارج از public_html

**این مهم‌ترین مرحله امنیتی است.**

1. وارد **File Manager** هاست شوید
2. به ریشه حساب هاست بروید (معمولاً `/home/jckhnwrm/`)
3. یک پوشه جدید به نام `secure` بسازید
4. فایل `config.example.php` پروژه را دانلود کنید
5. آن را به `config.php` تغییر نام دهید
6. فایل `config.php` را در پوشه `secure` آپلود کنید

**نتیجه باید این باشد:**
```
/home/jckhnwrm/secure/config.php
```

**⚠️ مهم: jckhnwrm را با نام واقعی حساب هاست خودتان جایگزین کنید!**

نام حساب هاست معمولاً همان نام کاربری FTP یا نامی است که هنگام ثبت‌نام هاست دریافت کرده‌اید.
برای پیدا کردن آن:
- از File Manager استفاده کنید و به ریشه بروید
- یا از FTP استفاده کنید و ببینید در کدام پوشه هستید
- یا از بخش **File Manager** هاست، آدرس فعلی را بخوانید

### مرحله ۴: تنظیم رمز فایل پیکربندی

فایل `/home/jckhnwrm/secure/config.php` را باز کنید و مقادیر زیر را جایگزین کنید:

```php
// نام دیتابیس
'db_name'     => 'your_database_name',

// نام کاربری دیتابیس
'db_user'     => 'your_database_user',

// رمز دیتابیس
'db_password' => 'your_database_password',

// توکن ربات بله (از @BotFather)
'bale_bot_token' => 'ACTUAL_BALE_BOT_TOKEN',

// آیدی چت ادمین بله
'bale_admin_chat_id' => 'ACTUAL_BALE_CHAT_ID',

// کلید امنیتی Cron (یک رشته تصادفی)
'cron_secret_key' => 'RANDOM_SECRET_STRING',
```

### مرحله ۵: آپلود فایل‌های وبسایت

تمام فایل‌های پروژه (به جز `secure/` پوشه داخل پروژه و `config.example.php`) را
از طریق **File Manager** یا **FTP** در پوشه `public_html` آپلود کنید.

**ساختار فایل‌ها در public_html:**
```
public_html/
├── index.html
├── 404.html
├── robots.txt
├── sitemap.xml
├── .htaccess
├── .gitignore
├── css/
│   └── style.css
├── images/
├── services/
│   ├── index.html
│   ├── telegram-bot/
│   ├── bale-bot/
│   ├── rubika-bot/
│   ├── web-development/
│   └── mini-app/
├── portfolio/
│   ├── index.html
│   ├── froshyar/
│   ├── chess/
│   └── yaren/
├── about/
│   └── index.html
├── contact/
│   └── index.html
├── api/
│   └── submit-request.php
├── cron/
│   └── send-pending-requests.php
├── admin/
│   ├── index.php
│   └── setup.php
├── includes/
│   ├── db.php
│   ├── bale.php
│   └── .htaccess
├── secure/
│   └── .htaccess
└── database/
    └── schema.sql
```

**⚠️ فایل `secure/config.php` را در `public_html/secure/` آپلود نکنید!**
فقط فایل `.htaccess` داخل `public_html/secure/` باشد (برای بلاک کردن دسترسی).

### مرحله ۶: تنظیم .htaccess

مطمئن شوید فایل `.htaccess` در ریشه `public_html` وجود دارد:

```apache
RewriteEngine On

# Clean URLs for service pages
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)/$ index.html [L]

# Deny access to includes directory
RewriteRule ^includes/ - [F,L]

# Deny access to secure config inside public_html (defense in depth)
RewriteRule ^secure/config\.php$ - [F,L]
```

### مرحله ۷: تنظیم رمز عبور مدیر

1. در مرورگر به آدرس زیر بروید:
   ```
   https://yourdomain.com/admin/setup.php
   ```
2. رمز عبور دلخواه را وارد کنید
3. روی «ذخیره رمز عبور» کلیک کنید
4. **⚠️ فوراً فایل `admin/setup.php` را حذف کنید!**

### مرحله ۸: تنظیم Cron Job

از پنل مدیریت هاست:

1. بخش **Cron Jobs** را باز کنید
2. یک Cron Job جدید با این تنظیمات بسازید:

**روش ۱: دسترسی مستقیم PHP (توصیه شده)**
```
* * * * * php /home/jckhnwrm/public_html/cron/send-pending-requests.php
```

**روش ۲: URL-based (اگر PHP CLI در دسترس نیست)**
```
* * * * * curl -s "https://yourdomain.com/cron/send-pending-requests.php?key=YOUR_SECRET_KEY" > /dev/null 2>&1
```

**روش ۳: wget**
```
* * * * * wget -q -O /dev/null "https://yourdomain.com/cron/send-pending-requests.php?key=YOUR_SECRET_KEY"
```

**⚠️ jckhnwrm و YOUR_SECRET_KEY را با مقادیر واقعی جایگزین کنید!**

فرکانس: **هر ۵ دقیقه** ( */5 * * * * ) یا **هر دقیقه** ( * * * * * )

### مرحله ۹: تست

1. به صفحه تماس بروید: `https://yourdomain.com/contact/`
2. یک درخواست پروژه تستی پر کنید
3. ارسال کنید
4. پیام موفقیت باید نمایش داده شود
5. وارد phpMyAdmin شوید و جدول `project_requests` را بررسی کنید
6. درخواست باید با `status = pending` ذخیره شده باشد
7. Cron Job را دستی اجرا کنید (یا ۵ دقیقه صبر کنید)
8. بررسی کنید که `status = sent` شده
9. در بله بات، پیام نوتیفیکیشن باید دریافت شده باشد

---

## پیدا کردن نام حساب هاست (jckhnwrm)

نام حساب هاست شما معمولاً:

- همان نام کاربری FTP شماست
- در File Manager هاست در نوار آدرس دیده می‌شود (مثلاً `/home/johndoe/`)
- در ایمیل خوش‌آمدگویی هاست ذکر شده

**例子:**
- اگر FTP username شما `bazopdev` باشد:
  - مسیر config: `/home/bazopdev/secure/config.php`
  - مسیر public_html: `/home/bazopdev/public_html/`
  - Cron command: `php /home/bazopdev/public_html/cron/send-pending-requests.php`

- اگر FTP username شما `myhost123` باشد:
  - مسیر config: `/home/myhost123/secure/config.php`
  - مسیر public_html: `/home/myhost123/public_html/`
  - Cron command: `php /home/myhost123/public_html/cron/send-pending-requests.php`

**⚠️ هرگز از `USERNAME` واقعی استفاده نکنید. حتماً نام واقعی حساب خود را بگذارید.**

---

## تنظیم Bale Bot Token

### توکن بله
1. با ربات @BotFather در بله صحبت کنید
2. یک ربات جدید بسازید یا توکن ربات موجود را بگیرید
3. توکن را در فایل `/home/jckhnwrm/secure/config.php` در فیلد `bale_bot_token` قرار دهید

### آیدی چت ادمین
1. ربات را در یک چت خصوصی با خودتان اضافه کنید
2. به آدرس زیر بروید (توکن خود را جایگزین کنید):
   ```
   https://api.ble.ir/botYOUR_TOKEN/getUpdates
   ```
3. در پاسخ JSON، `chat.id` را پیدا کنید
4. این عدد را در فایل `/home/jckhnwrm/secure/config.php` در فیلد `bale_admin_chat_id` قرار دهید

---

## چرا config خارج از public_html است؟

```
اگر config.php داخل public_html باشد:
  visitor → GET /secure/config.php → رمزها لو می‌رود ❌

اگر config.php خارج از public_html باشد:
  visitor → GET /home/USER/secure/config.php → 404 ❌ (غیرقابل دسترس)
  PHP code → require '/home/USER/secure/config.php' → کار می‌کند ✅
```

فایل PHP با `require` می‌تواند هر فایلی را از سیستم فایل سرور بخواند،
حتی اگر آن فایل خارج از پوشه وب باشد.

---

## عیب‌یابی

### خطای "Configuration file not found"
- مطمئن شوید فایل `config.php` در `/home/jckhnwrm/secure/` وجود دارد
- نام حساب هاست (jckhnwrm) را بررسی کنید
- فایل `config.php` باید شامل `return [...]` باشد

### فرم ارسال نمی‌شود
- بررسی کنید که فایل `api/submit-request.php` وجود دارد
- بررسی کنید که نام دیتابیس و رمز در `config.php` صحیح است
- خطای PHP را در لاگ هاست بررسی کنید

### Cron Job اجرا نمی‌شود
- مسیر PHP را در هاست بررسی کنید
- از روش URL-based استفاده کنید
- کلید امنیتی را بررسی کنید

### پیام به بله ارسال نمی‌شود
- توکن بله را بررسی کنید
- آیدی چت را بررسی کنید
- درخواست‌ها را در دیتابیس بررسی کنید (باید `status = pending` باشند)
- Cron Job را دستی اجرا کنید

### خطای 500
- فایل‌های مجوز را بررسی کنید
- نسخه PHP را بررسی کنید (حداقل 7.4)
- خطای دقیق را در لاگ هاست بیابید

### Cron نمی‌تواند config.php را بخواند
- مطمئن شوید مسیر در Cron command صحیح است
- مسیر باید شامل `/home/jckhnwrm/secure/config.php` باشد
- از File Manager نام پوشه حساب را بررسی کنید
