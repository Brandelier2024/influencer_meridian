<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Hostinger MySQL Database Configuration
 * 
 * Instructions for Hostinger Deployment:
 * 1. Log into your Hostinger hPanel -> Databases -> MySQL Databases.
 * 2. Create a new Database & Username (e.g. u123456789_meridian).
 * 3. Update the credentials below, or set them via environment variables in hPanel.
 * 4. The system will automatically create the required database tables on first submission!
 */

// Allow CORS if needed for testing across subdomains
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Hostinger Database Settings
// (Replace with your actual Hostinger hPanel credentials)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'u510276165_influencer_M');
define('DB_USER', getenv('DB_USER') ?: 'u510276165_influencer_M');
define('DB_PASS', getenv('DB_PASS') ?: 'RGBrandelier@2027');
define('DB_CHARSET', 'utf8mb4');

// Admin Passkey for Exporting Contestant CSV (api/export.php?key=MeridianAdmin2026)
define('ADMIN_SECRET_KEY', getenv('ADMIN_SECRET_KEY') ?: 'MeridianAdmin2026');

/**
 * Establish PDO connection with Hostinger MySQL
 * Returns PDO instance or null on connection failure
 */
function getDbConnection()
{
    if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
        error_log("Notice: PDO or pdo_mysql extension is not loaded in this PHP environment.");
        return null;
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        // Optional init command if constant exists
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES " . DB_CHARSET;
        }

        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}
