<?php
/**
 * Database Connection Helper
 * 
 * Provides PDO connection to MySQL.
 * 
 * Configuration loading priority:
 *   1. OUTSIDE public_html (recommended for shared hosting)
 *      /home/jckhnwrm/secure/config.php
 *   2. Fallback: project-internal secure/ directory
 *      (only if .htaccess blocks direct HTTP access)
 */

function getDbConfig() {
    static $config = null;
    if ($config === null) {
        // Determine the public_html root (where includes/ lives)
        $publicRoot = dirname(__DIR__);
        // Go up one level from public_html to the account root
        $accountRoot = dirname($publicRoot);
        
        $paths = [
            // PRIMARY: Config OUTSIDE public_html (secure!)
            $accountRoot . '/secure/config.php',
            // FALLBACK: Config inside project (only if .htaccess blocks HTTP access)
            $publicRoot . '/secure/config.php',
            // FALLBACK: Local override (for development)
            $publicRoot . '/config.local.php',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $config = require $path;
                return $config;
            }
        }
        
        // Nothing found - provide clear error
        die(
            'Configuration file not found.\n' .
            'Please upload secure/config.php to: ' . $accountRoot . '/secure/config.php\n' .
            'Or see config.example.php for setup instructions.'
        );
    }
    return $config;
}

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $config = getDbConfig();
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}
