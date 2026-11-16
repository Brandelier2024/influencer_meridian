<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Hostinger MySQL Database Configuration
 * 
 * Features:
 * - Production: Hostinger MySQL (localhost:3306)
 * - Local Testing: Seamless SQLite database (data/meridian_contest.db)
 * - Auto-initialization of tables and indexes
 * - Direct database operations only (No JSON failsafe overwriting deleted entries)
 */

// Prevent direct web access to configuration file
if (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Direct access to config is forbidden.');
}

// Hostinger Database Settings
// (Preserves configured credentials with environment variable fallback)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'u510276165_influencer_M');
define('DB_USER', getenv('DB_USER') ?: 'u510276165_influencer_M');
define('DB_PASS', getenv('DB_PASS') ?: 'RGBrandelier@2027');
define('DB_CHARSET', 'utf8mb4');

// Admin Passkey for Exporting Contestant CSV (api/export.php)
define('ADMIN_SECRET_KEY', getenv('ADMIN_SECRET_KEY') ?: 'MeridianAdmin2026');

// Superadmin Dashboard Credentials
define('SUPERADMIN_USER', getenv('SUPERADMIN_USER') ?: 'admin');
define('SUPERADMIN_PASS', getenv('SUPERADMIN_PASS') ?: 'Meridian@Super2026');

/**
 * Establish PDO connection
 * 1. Tries Hostinger MySQL first
 * 2. If MySQL is not reachable (e.g. testing locally without MySQL service),
 *    seamlessly connects to a real SQL database (data/meridian_contest.db)
 * 3. Initializes tables automatically
 */
function getDbConnection()
{
    static $pdoInstance = null;
    if ($pdoInstance !== null) {
        return $pdoInstance;
    }

    // Attempt MySQL connection first
    if (extension_loaded('pdo_mysql')) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false
            ];
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES " . DB_CHARSET;
            }

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            initDatabaseTable($pdo, 'mysql');
            $pdoInstance = $pdo;
            return $pdoInstance;
        } catch (PDOException $e) {
            // MySQL unavailable locally (e.g. local testing), fall through to local SQLite database
            error_log("MySQL Notice: " . $e->getMessage() . " - switching to local SQLite database for testing.");
        }
    }

    // Local Testing Database (SQLite)
    if (extension_loaded('pdo_sqlite')) {
        try {
            $dataDir = __DIR__ . '/../data';
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0750, true);
            }
            $dbPath = $dataDir . '/meridian_contest.db';
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            initDatabaseTable($pdo, 'sqlite');
            $pdoInstance = $pdo;
            return $pdoInstance;
        } catch (PDOException $e) {
            error_log("SQLite Connection Error: " . $e->getMessage());
            return null;
        }
    }

    return null;
}

/**
 * Initialize database schema and indexes
 */
function initDatabaseTable(PDO $pdo, string $driver)
{
    if ($driver === 'mysql') {
        $sql = "
        CREATE TABLE IF NOT EXISTS `meridian_contest_entries` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `entry_code` VARCHAR(32) NOT NULL UNIQUE,
            `full_name` VARCHAR(150) NOT NULL,
            `mobile_number` VARCHAR(20) NOT NULL,
            `email_address` VARCHAR(150) NOT NULL,
            `category` ENUM('male', 'female', 'child', 'group') NOT NULL DEFAULT 'male',
            `video_url` VARCHAR(500) NULL,
            `city` VARCHAR(80) NOT NULL DEFAULT 'pune',
            `store_code` VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
            `instagram_handle` VARCHAR(100) NULL,
            `terms_agreed` TINYINT(1) NOT NULL DEFAULT 1,
            `status` ENUM('registered', 'under_review', 'shortlisted', 'winner', 'rejected') NOT NULL DEFAULT 'registered',
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_category (`category`),
            INDEX idx_store_code (`store_code`),
            INDEX idx_status (`status`),
            INDEX idx_created_at (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);
    } else {
        $sql = "
        CREATE TABLE IF NOT EXISTS `meridian_contest_entries` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `entry_code` TEXT NOT NULL UNIQUE,
            `full_name` TEXT NOT NULL,
            `mobile_number` TEXT NOT NULL,
            `email_address` TEXT NOT NULL,
            `category` TEXT NOT NULL DEFAULT 'male',
            `video_url` TEXT,
            `city` TEXT NOT NULL DEFAULT 'pune',
            `store_code` TEXT NOT NULL DEFAULT 'GENERAL',
            `instagram_handle` TEXT,
            `terms_agreed` INTEGER NOT NULL DEFAULT 1,
            `status` TEXT NOT NULL DEFAULT 'registered',
            `ip_address` TEXT,
            `user_agent` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_category ON meridian_contest_entries (category);
        CREATE INDEX IF NOT EXISTS idx_store_code ON meridian_contest_entries (store_code);
        CREATE INDEX IF NOT EXISTS idx_status ON meridian_contest_entries (status);
        CREATE INDEX IF NOT EXISTS idx_created_at ON meridian_contest_entries (created_at);
        ";
        $pdo->exec($sql);
    }
}
