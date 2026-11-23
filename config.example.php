<?php

/**
 * Bazopardaz Configuration File
 * 
 * IMPORTANT:
 *   1. Copy this file to your secure location OUTSIDE public_html
 *   2. Replace ALL placeholder values with your actual credentials
 *   3. NEVER commit this file to Git
 *   4. NEVER place the real config inside public_html
 * 
 * Recommended location:
 *   /home/YOUR_HOSTING_USERNAME/secure/config.php
 * 
 * The directory structure should be:
 *   /home/YOUR_HOSTING_USERNAME/
 *   ├── secure/
 *   │   └── config.php  ← This file (real credentials)
 *   └── public_html/    ← Your website files
 *       ├── index.html
 *       ├── includes/
 *       │   └── db.php  ← Loads the config from above
 *       └── ...
 * 
 * Replace YOUR_HOSTING_USERNAME with your actual hosting account username.
 */

return [
    // ──────────────────────────────────────────────
    //  MySQL Database Credentials
    // ──────────────────────────────────────────────

    // Database host (usually 'localhost' on shared hosting)
    'db_host'     => 'localhost',

    // Database name you created in your hosting panel
    'db_name'     => 'YOUR_DB_NAME',

    // Database username you created
    'db_user'     => 'YOUR_DB_USER',

    // Database password
    'db_password' => 'YOUR_DB_PASSWORD',

    // Character set (usually utf8mb4 for full Unicode support)
    'db_charset'  => 'utf8mb4',


    // ──────────────────────────────────────────────
    //  Bale Bot Notification Settings
    // ──────────────────────────────────────────────

    // Your Bale bot token (from @BotFather in Bale)
    // Format: something like: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz
    'bale_bot_token' => 'YOUR_BALE_BOT_TOKEN',

    // Your Bale chat ID (where notifications are sent)
    // Send /start to your bot in Bale, then open: https://tapi.bale.ai/botYOUR_TOKEN/getUpdates
    'bale_admin_chat_id' => 'YOUR_BALE_CHAT_ID',

    // Bale Bot API host (leave as is; the old api.ble.ir host is auto-corrected)
    'bale_api_base' => 'https://tapi.bale.ai',

    // Button shown under every notification
    'admin_panel_url' => 'https://bazopardaz.ir/admin/',

    // ──────────────────────────────────────────────
    //  Admin Dashboard Settings
    // ──────────────────────────────────────────────

    // Admin username (for /admin/ dashboard)
    'admin_username' => 'admin',

    // Admin password hash
    // After importing the database schema, visit /admin/setup.php to set your password
    // Then DELETE /admin/setup.php
    'admin_password_hash' => '',

    // ──────────────────────────────────────────────
    //  Cron Job Settings
    // ──────────────────────────────────────────────

    // Secret key for URL-based cron access
    // Generate a random string: https://www.random.org/strings/
    // Or use: php -r "echo bin2hex(random_bytes(32));"
    'cron_secret_key' => 'GENERATE_A_LONG_RANDOM_STRING',

    // ──────────────────────────────────────────────
    //  Rate Limiting & Retry Settings
    // ──────────────────────────────────────────────

    // Minimum seconds between submissions from the same IP
    'rate_limit_seconds' => 60,

    // Maximum number of retry attempts for Bale delivery
    'max_retry_attempts' => 5,
];
