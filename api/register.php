<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Secure Contestant Registration API Endpoint
 * 
 * Features:
 * - Anti-bot honeypot protection
 * - IP rate limiting (10 submissions / 5 mins)
 * - Cryptographically secure 6-digit ticket generator with collision retry
 * - Robust input validation and length limits
 * - PDO prepared statements (immune to SQL injection)
 * - Protected fallback storage without internal server error leaks
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Allowed origins for CORS (restrict to own domain or same-origin)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    $parsedHost = parse_url($origin, PHP_URL_HOST);
    // Allow localhost, 127.0.0.1, or meridian domains
    if (in_array($parsedHost, ['localhost', '127.0.0.1']) || 
        preg_match('/(^|\.)meridianicecreams\.com$/i', $parsedHost)) {
        header("Access-Control-Allow-Origin: " . $origin);
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
    }
}

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

// 1. Anti-Bot Honeypot Check
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

$honeypot = trim($_POST['contest_hp'] ?? $jsonInput['contest_hp'] ?? '');
if (!empty($honeypot)) {
    // Bot trapped in honeypot. Return success silently to prevent adaptation.
    echo json_encode([
        'success'    => true,
        'entry_id'   => '9999',
        'entry_code' => 'M-2026-9999',
        'message'    => 'Registration received.'
    ]);
    exit;
}

// 2. Client IP & Basic Rate Limiting (Max 10 submissions per 5 minutes per IP)
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

if (!checkRateLimit($clientIp)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error'   => 'Too Many Requests',
        'message' => 'Submission rate limit reached. Please wait a few minutes before submitting again.'
    ]);
    exit;
}

// 3. Extract and Sanitize Inputs
$fullName       = mb_substr(trim($_POST['full_name'] ?? $jsonInput['full_name'] ?? ''), 0, 150);
$mobileNumber   = trim($_POST['mobile_number'] ?? $jsonInput['mobile_number'] ?? '');
$emailAddress   = mb_substr(trim($_POST['email_address'] ?? $jsonInput['email_address'] ?? ''), 0, 150);
$category       = strtolower(trim($_POST['category'] ?? $jsonInput['category'] ?? 'male'));
$videoUrl       = mb_substr(trim($_POST['video_url'] ?? $jsonInput['video_url'] ?? ''), 0, 500);
$city           = mb_substr(trim($_POST['city'] ?? $jsonInput['city'] ?? 'pune'), 0, 80);
$storeCode      = mb_substr(trim($_POST['store_code'] ?? $jsonInput['store_code'] ?? 'GENERAL'), 0, 50);
$instagram      = mb_substr(trim($_POST['instagram_handle'] ?? $jsonInput['instagram_handle'] ?? ''), 0, 100);
$termsAgreed    = isset($_POST['terms_agreed']) || !empty($jsonInput['terms_agreed']) ? 1 : 0;

// 4. Strict Validation
$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required.';
}

// Digits-only mobile validation
$cleanMobile = preg_replace('/[^0-9]/', '', $mobileNumber);
if (strlen($cleanMobile) !== 10) {
    $errors[] = 'A valid 10-digit mobile number is required.';
}

if (empty($emailAddress) || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if (!in_array($category, ['male', 'female', 'child', 'group'], true)) {
    $category = 'male';
}

if (!empty($videoUrl) && !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Invalid video URL format.';
}

if (!$termsAgreed) {
    $errors[] = 'You must agree to contest rules and terms.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'errors'  => $errors,
        'message' => 'Validation failed: ' . implode(' ', $errors)
    ]);
    exit;
}

// 5. Generate Cryptographically Secure 6-digit Code (e.g. M-2026-784291)
function generateSecureCode()
{
    return 'M-2026-' . random_int(100000, 999999);
}

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

        $insertSql = "
        INSERT INTO `meridian_contest_entries` 
        (`entry_code`, `full_name`, `mobile_number`, `email_address`, `category`, `video_url`, `city`, `store_code`, `instagram_handle`, `terms_agreed`, `ip_address`, `user_agent`)
        VALUES 
        (:entry_code, :full_name, :mobile_number, :email_address, :category, :video_url, :city, :store_code, :instagram_handle, :terms_agreed, :ip_address, :user_agent)
        ";

        $stmt = $pdo->prepare($insertSql);

        // Attempt insertion with up to 3 retries in case of rare ticket code collision
        $inserted = false;
        $entryCode = '';
        $dbInsertId = 0;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $entryCode = generateSecureCode();
            try {
                $stmt->execute([
                    ':entry_code'       => $entryCode,
                    ':full_name'        => $fullName,
                    ':mobile_number'    => $cleanMobile,
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
                $inserted = true;
                break;
            } catch (PDOException $ex) {
                // If collision on entry_code, retry with a fresh code
                if ($ex->getCode() == 23000 && strpos($ex->getMessage(), 'entry_code') !== false) {
                    continue;
                }
                throw $ex; // Re-throw other database errors to catch block
            }
        }

        if ($inserted) {
            echo json_encode([
                'success'    => true,
                'entry_id'   => substr($entryCode, -6),
                'entry_code' => $entryCode,
                'db_id'      => $dbInsertId,
                'message'    => 'Congratulations! You are officially registered for the Meridian Brand Ambassador Contest 2026-2027.',
                'category'   => $category,
                'storage'    => 'hostinger_mysql'
            ]);
            exit;
        }

    } catch (PDOException $ex) {
        error_log("Database Insert Error: " . $ex->getMessage());
        // Fall back to protected offline store without leaking SQL internals
        $entryCode = generateSecureCode();
        saveOfflineBackupSafe([
            'entry_code'    => $entryCode,
            'full_name'     => $fullName,
            'mobile_number' => $cleanMobile,
            'email_address' => $emailAddress,
            'category'      => $category,
            'city'          => $city,
            'store_code'    => $storeCode,
            'created_at'    => date('Y-m-d H:i:s')
        ]);

        echo json_encode([
            'success'    => true,
            'entry_id'   => substr($entryCode, -6),
            'entry_code' => $entryCode,
            'message'    => 'Registration confirmed and safely recorded in backup queue.',
            'storage'    => 'local_backup'
        ]);
        exit;
    }
}

// Fallback when MySQL is not connected
$entryCode = generateSecureCode();
saveOfflineBackupSafe([
    'entry_code'    => $entryCode,
    'full_name'     => $fullName,
    'mobile_number' => $cleanMobile,
    'email_address' => $emailAddress,
    'category'      => $category,
    'city'          => $city,
    'store_code'    => $storeCode,
    'created_at'    => date('Y-m-d H:i:s')
]);

echo json_encode([
    'success'    => true,
    'entry_id'   => substr($entryCode, -6),
    'entry_code' => $entryCode,
    'message'    => 'Registration saved successfully.',
    'storage'    => 'local_backup'
]);
exit;

/**
 * Protected offline backup storage
 */
function saveOfflineBackupSafe($data)
{
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    
    // Ensure .htaccess protection exists
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }

    $file = $dir . '/entries_backup.json';
    $entries = [];
    if (file_exists($file)) {
        $existing = file_get_contents($file);
        $entries = json_decode($existing, true) ?: [];
    }
    $entries[] = $data;
    @file_put_contents($file, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Lightweight IP rate-limiting (Max 10 requests per 300 seconds per IP)
 */
function checkRateLimit($ip)
{
    $cacheDir = sys_get_temp_dir() . '/meridian_rate_limits';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0750, true);
    }

    $hash = md5($ip);
    $limitFile = $cacheDir . '/' . $hash . '.json';
    $now = time();
    $window = 300; // 5 minutes
    $maxRequests = 10;

    $records = [];
    if (file_exists($limitFile)) {
        $records = json_decode(file_get_contents($limitFile), true) ?: [];
    }

    // Filter out timestamps older than the window
    $records = array_filter($records, function ($ts) use ($now, $window) {
        return ($now - $ts) < $window;
    });

    if (count($records) >= $maxRequests) {
        return false;
    }

    $records[] = $now;
    @file_put_contents($limitFile, json_encode(array_values($records)));
    return true;
}
