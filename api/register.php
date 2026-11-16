<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Contestant Registration API Endpoint
 * 
 * Connects to Hostinger MySQL Database and stores contestant submissions.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Please send a POST request.'
    ]);
    exit;
}

// Extract inputs from either $_POST or JSON body
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

$fullName       = trim($_POST['full_name'] ?? $jsonInput['full_name'] ?? '');
$mobileNumber   = trim($_POST['mobile_number'] ?? $jsonInput['mobile_number'] ?? '');
$emailAddress   = trim($_POST['email_address'] ?? $jsonInput['email_address'] ?? '');
$category       = trim($_POST['category'] ?? $jsonInput['category'] ?? 'male');
$videoUrl       = trim($_POST['video_url'] ?? $jsonInput['video_url'] ?? '');
$city           = trim($_POST['city'] ?? $jsonInput['city'] ?? 'pune');
$storeCode      = trim($_POST['store_code'] ?? $jsonInput['store_code'] ?? 'GENERAL');
$instagram      = trim($_POST['instagram_handle'] ?? $jsonInput['instagram_handle'] ?? '');
$termsAgreed    = isset($_POST['terms_agreed']) || !empty($jsonInput['terms_agreed']) ? 1 : 0;

// Input Validation
$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required.';
}
if (empty($mobileNumber) || !preg_match('/^[0-9]{10}$/', preg_replace('/[^0-9]/', '', $mobileNumber))) {
    $errors[] = 'A valid 10-digit mobile number is required.';
}
if (empty($emailAddress) || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (!in_array($category, ['male', 'female', 'child', 'group'])) {
    $category = 'male';
}
if (!$termsAgreed) {
    $errors[] = 'You must agree to contest rules and terms.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'errors' => $errors,
        'message' => 'Validation failed: ' . implode(' ', $errors)
    ]);
    exit;
}

// Generate Unique Entry Code (e.g. M-2026-8492)
$randomDigits = str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
$entryCode = 'M-2026-' . $randomDigits;

// Get Hostinger MySQL Connection
$pdo = getDbConnection();

if ($pdo) {
    try {
        // Auto-create table if it doesn't already exist
        $createTableSql = "
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
            `status` ENUM('registered', 'under_review', 'shortlisted', 'winner') NOT NULL DEFAULT 'registered',
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_category (`category`),
            INDEX idx_store_code (`store_code`),
            INDEX idx_status (`status`),
            INDEX idx_created_at (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($createTableSql);

        // Insert Record using Prepared Statement
        $insertSql = "
        INSERT INTO `meridian_contest_entries` 
        (`entry_code`, `full_name`, `mobile_number`, `email_address`, `category`, `video_url`, `city`, `store_code`, `instagram_handle`, `terms_agreed`, `ip_address`, `user_agent`)
        VALUES 
        (:entry_code, :full_name, :mobile_number, :email_address, :category, :video_url, :city, :store_code, :instagram_handle, :terms_agreed, :ip_address, :user_agent)
        ";

        $stmt = $pdo->prepare($insertSql);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt->execute([
            ':entry_code'       => $entryCode,
            ':full_name'        => $fullName,
            ':mobile_number'    => $mobileNumber,
            ':email_address'    => $emailAddress,
            ':category'         => $category,
            ':video_url'        => $videoUrl,
            ':city'             => $city,
            ':store_code'       => $storeCode,
            ':instagram_handle' => $instagram,
            ':terms_agreed'     => $termsAgreed,
            ':ip_address'       => $clientIp,
            ':user_agent'       => $userAgent
        ]);

        $dbInsertId = $pdo->lastInsertId();

        echo json_encode([
            'success'   => true,
            'entry_id'  => $randomDigits,
            'entry_code'=> $entryCode,
            'db_id'     => $dbInsertId,
            'message'   => 'Congratulations! You are officially registered for the Meridian Brand Ambassador Contest 2026-2027.',
            'category'  => $category,
            'storage'   => 'hostinger_mysql'
        ]);
        exit;

    } catch (PDOException $ex) {
        error_log("Database Insert Error: " . $ex->getMessage());
        // Fall back to file logging so entries are never lost
        saveOfflineBackup([
            'entry_code' => $entryCode,
            'full_name' => $fullName,
            'mobile_number' => $mobileNumber,
            'email_address' => $emailAddress,
            'category' => $category,
            'city' => $city,
            'created_at' => date('Y-m-d H:i:s'),
            'db_error' => $ex->getMessage()
        ]);

        echo json_encode([
            'success'   => true,
            'entry_id'  => $randomDigits,
            'entry_code'=> $entryCode,
            'message'   => 'Entry recorded in backup queue while Hostinger database completes setup.',
            'storage'   => 'local_backup'
        ]);
        exit;
    }
} else {
    // Database connection failed (e.g. testing in local environment before deploying to Hostinger)
    saveOfflineBackup([
        'entry_code' => $entryCode,
        'full_name' => $fullName,
        'mobile_number' => $mobileNumber,
        'email_address' => $emailAddress,
        'category' => $category,
        'city' => $city,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    echo json_encode([
        'success'   => true,
        'entry_id'  => $randomDigits,
        'entry_code'=> $entryCode,
        'message'   => 'Entry saved successfully (Hostinger DB credentials in api/config.php will connect on live server).',
        'storage'   => 'local_backup'
    ]);
    exit;
}

/**
 * Backup helper function to ensure no contestant registrations are lost
 */
function saveOfflineBackup($data) {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/entries_backup.json';
    $entries = [];
    if (file_exists($file)) {
        $existing = file_get_contents($file);
        $entries = json_decode($existing, true) ?: [];
    }
    $entries[] = $data;
    @file_put_contents($file, json_encode($entries, JSON_PRETTY_PRINT));
}

