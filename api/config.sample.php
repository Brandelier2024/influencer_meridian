<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Sample Configuration Template (Safe for Version Control)
 */

if (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Direct access to config is forbidden.');
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'your_database_name');
define('DB_USER', getenv('DB_USER') ?: 'your_database_user');
define('DB_PASS', getenv('DB_PASS') ?: 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

define('ADMIN_SECRET_KEY', getenv('ADMIN_SECRET_KEY') ?: 'ChangeThisAdminKey2026');
define('SUPERADMIN_USER', getenv('SUPERADMIN_USER') ?: 'admin');
define('SUPERADMIN_PASS', getenv('SUPERADMIN_PASS') ?: 'ChangeThisPassword2026');

function getDbConnection()
{
    if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
        return null;
    }
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}

