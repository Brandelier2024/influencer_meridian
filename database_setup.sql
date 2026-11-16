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
  `category` ENUM('male', 'female', 'child', 'group') NOT NULL DEFAULT 'male' COMMENT 'Contest bracket',
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
-- Seed Sample Data for Testing / Demonstration
-- ============================================================

INSERT INTO `meridian_contest_entries` 
(`entry_code`, `full_name`, `mobile_number`, `email_address`, `category`, `video_url`, `city`, `store_code`, `instagram_handle`, `status`) 
VALUES
('M-2026-1042', 'Aryan Deshmukh', '9822012345', 'aryan.d@example.com', 'male', 'https://instagram.com/reel/meridian_scoop_aryan', 'pune', 'PUN-FC-01', '@aryan_scoops', 'shortlisted'),
('M-2026-2109', 'Sneha Patil', '9819054321', 'sneha.patil@example.com', 'female', 'https://instagram.com/reel/sneha_strawberry_cone', 'mumbai', 'MUM-BANDRA-02', '@sneha_flavours', 'registered'),
('M-2026-3841', 'Aarav & Kavya Kulkarni', '9422098765', 'kulkarni.family@example.com', 'child', 'https://instagram.com/reel/aarav_sundae_fun', 'nashik', 'NSK-COLLEGE-01', '@kulkarni_family', 'registered'),
('M-2026-4552', 'The Fergusson Scoop Squad', '9765011223', 'fc.crewsquad@example.com', 'group', 'https://instagram.com/reel/fergusson_crew_meridian', 'pune', 'PUN-DECCAN-03', '@fc_meridian_crew', 'under_review');

