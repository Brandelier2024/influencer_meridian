-- ============================================================
-- Meridian Ice Cream — Brand Ambassador Contest 2026-2027
-- Hostinger MySQL Database Schema & Initial Setup
-- 
-- Host: localhost:3306 (Hostinger Default)
-- Engine: InnoDB
-- Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS `meridian_contest_db` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `meridian_contest_db`;

-- Drop old table if needed for clean reinstall
-- DROP TABLE IF EXISTS `meridian_contest_entries`;

CREATE TABLE IF NOT EXISTS `meridian_contest_entries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `entry_code` VARCHAR(32) NOT NULL UNIQUE COMMENT 'Formatted contest ticket ID (e.g. M-2026-1042)',
  `full_name` VARCHAR(150) NOT NULL COMMENT 'Contestant or team lead name',
  `mobile_number` VARCHAR(20) NOT NULL COMMENT '10-digit Indian mobile number',
  `email_address` VARCHAR(150) NOT NULL COMMENT 'Notification email',
  `category` VARCHAR(50) NOT NULL DEFAULT 'male' COMMENT 'Contest category: male, female, kids, group_family, voice_of_meridian',
  `video_url` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Instagram Reel / YouTube Shorts URL',
  `city` VARCHAR(80) NOT NULL DEFAULT 'pune' COMMENT 'Nearest parlour city (Mumbai, Pune, Nashik, etc.)',
  `store_code` VARCHAR(50) NOT NULL DEFAULT 'GENERAL' COMMENT 'Parlour code for vendor attribution (e.g. PUN-FC-01)',
  `instagram_handle` VARCHAR(100) NULL DEFAULT NULL COMMENT '@username for social tagging',
  `terms_agreed` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = agreed to rules and media usage',
  `status` ENUM('registered', 'under_review', 'shortlisted', 'winner') NOT NULL DEFAULT 'registered' COMMENT 'Review progression',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Client IP for anti-fraud',
  `user_agent` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Browser/device info',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Submission timestamp',
  
  -- Optimized indexes for fast parlour reports and category filtering
  INDEX idx_category (`category`),
  INDEX idx_store_code (`store_code`),
  INDEX idx_status (`status`),
  INDEX idx_city (`city`),
  INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Meridian Brand Ambassador Contest Registrations';

-- ============================================================
-- Superadmin Users Table (Database-Driven Authentication)
-- ============================================================

CREATE TABLE IF NOT EXISTS `meridian_admin_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Superadmin login handle',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Bcrypt password hash',
  `full_name` VARCHAR(100) NOT NULL DEFAULT 'Super Administrator',
  `email` VARCHAR(150) NULL DEFAULT NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'superadmin',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_admin_username (`username`),
  INDEX idx_admin_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Meridian Administrative Users';

-- Seed default Superadmin (Password: Meridian@Super2026)
INSERT INTO `meridian_admin_users` 
(`username`, `password_hash`, `full_name`, `email`, `role`, `status`) 
VALUES 
('admin', '$2y$10$sD8GJqH7gBL4TkV3WfBwWew7CCTf5G5hwkx.rIjB2QkWAesTlgecC', 'Meridian Super Administrator', 'admin@meridianicecreams.com', 'superadmin', 'active')
ON DUPLICATE KEY UPDATE `username` = `username`;
