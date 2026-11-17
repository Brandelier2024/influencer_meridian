<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Contestant Registration API Endpoint
 * 
 * Inserts contestant registrations directly into the database.
 * No JSON failsafe files — the SQL database is the single source of truth.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Allowed origins for CORS (restrict to own domain or same-origin)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    $parsedHost = parse_url($origin, PHP_URL_HOST);
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
    // Bot trapped in honeypot. Return success silently.
    echo json_encode([
        'success'    => true,
        'entry_id'   => '999999',
        'entry_code' => 'M-2026-999999',
        'message'    => 'Registration received.'
    ]);
    exit;
}

// 2. Client IP & Basic Rate Limiting
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

$cleanMobile = preg_replace('/[^0-9]/', '', $mobileNumber);
if (strlen($cleanMobile) !== 10) {
    $errors[] = 'A valid 10-digit mobile number is required.';
}

if (empty($emailAddress) || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

// Normalize categories & aliases to canonical 5 categories
if ($category === 'child') {
    $category = 'kids';
} elseif ($category === 'group' || $category === 'family' || $category === 'group/family') {
    $category = 'group_family';
} elseif ($category === 'voice' || $category === 'voice-of-meridian') {
    $category = 'voice_of_meridian';
}

if (!in_array($category, ['male', 'female', 'kids', 'group_family', 'voice_of_meridian'], true)) {
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

// 5. Connect to Database (Single Source of Truth)
$pdo = getDbConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable. Please verify database configuration.'
    ]);
    exit;
}

// 6. Generate 6-digit Code & Insert into Database
$insertSql = "
INSERT INTO `meridian_contest_entries` 
(`entry_code`, `full_name`, `mobile_number`, `email_address`, `category`, `video_url`, `city`, `store_code`, `instagram_handle`, `terms_agreed`, `ip_address`, `user_agent`)
VALUES 
(:entry_code, :full_name, :mobile_number, :email_address, :category, :video_url, :city, :store_code, :instagram_handle, :terms_agreed, :ip_address, :user_agent)
";

$stmt = $pdo->prepare($insertSql);

$inserted = false;
$entryCode = '';
$dbInsertId = 0;

for ($attempt = 0; $attempt < 3; $attempt++) {
    $entryCode = 'M-2026-' . random_int(100000, 999999);
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
        if ($ex->getCode() == 23000 && strpos($ex->getMessage(), 'entry_code') !== false) {
            continue; // Retry on rare unique ticket collision
        }
        error_log("Database Insert Error: " . $ex->getMessage());
        break;
    }
}

if ($inserted) {
    echo json_encode([
        'success'    => true,
        'entry_id'   => substr($entryCode, -6),
        'entry_code' => $entryCode,
        'db_id'      => $dbInsertId,
        'message'    => 'Congratulations! You are officially registered for the Meridian Brand Ambassador Contest 2026-2027.',
        'category'   => $category
    ]);
    exit;
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save registration to database. Please try again.'
    ]);
    exit;
}

/**
 * Lightweight IP rate-limiting (Max 15 requests per 300 seconds per IP)
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
    $maxRequests = 15;

    $records = [];
    if (file_exists($limitFile)) {
        $records = json_decode(file_get_contents($limitFile), true) ?: [];
    }

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
