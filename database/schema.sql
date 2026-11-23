-- Bazopardaz Project Requests Database Schema
-- Import this file via phpMyAdmin
-- 
-- After importing, run /admin/setup.php to set your admin password.
-- Then DELETE /admin/setup.php for security.

CREATE TABLE IF NOT EXISTS `project_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `contact_method` ENUM('telegram','bale','whatsapp','email','phone','other') NOT NULL,
  `contact_value` VARCHAR(500) NOT NULL,
  `project_type` ENUM('telegram_bot','discord_bot','bale_bot','rubika_bot','website','other') NOT NULL,
  `budget` VARCHAR(200) DEFAULT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `error_message` VARCHAR(1000) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user with placeholder password
-- IMPORTANT: After importing, navigate to /admin/setup.php to set your real password
-- Then DELETE /admin/setup.php
INSERT INTO `admin_users` (`username`, `password_hash`) VALUES
('admin', '$2y$10$placeholder_hash_will_be_replaced_by_setup_script')
ON DUPLICATE KEY UPDATE `username` = `username`;
