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
    if (
        in_array($parsedHost, ['localhost', '127.0.0.1']) ||
        preg_match('/(^|\.)meridianicecreams\.com$/i', $parsedHost)
    ) {
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
        'success' => true,
        'entry_id' => '999999',
        'entry_code' => 'M-2026-999999',
        'message' => 'Registration received.'
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
        'error' => 'Too Many Requests',
        'message' => 'Submission rate limit reached. Please wait a few minutes before submitting again.'
    ]);
    exit;
}

// 3. Extract and Sanitize Inputs
$fullName = mb_substr(trim($_POST['full_name'] ?? $jsonInput['full_name'] ?? ''), 0, 150);
$mobileNumber = trim($_POST['mobile_number'] ?? $jsonInput['mobile_number'] ?? '');
$emailAddress = mb_substr(trim($_POST['email_address'] ?? $jsonInput['email_address'] ?? ''), 0, 150);
$category = strtolower(trim($_POST['category'] ?? $jsonInput['category'] ?? 'male'));
$videoUrl = mb_substr(trim($_POST['video_url'] ?? $jsonInput['video_url'] ?? ''), 0, 500);
$city = mb_substr(trim($_POST['city'] ?? $jsonInput['city'] ?? 'pune'), 0, 80);
$storeCode = mb_substr(trim($_POST['store_code'] ?? $jsonInput['store_code'] ?? 'GENERAL'), 0, 50);
$instagram = mb_substr(trim($_POST['instagram_handle'] ?? $jsonInput['instagram_handle'] ?? ''), 0, 100);
$termsAgreed = isset($_POST['terms_agreed']) || !empty($jsonInput['terms_agreed']) ? 1 : 0;

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


if (empty($instagram)) {
    $errors[] = 'Instagram handle is required.';
}

if (empty($videoUrl)) {
    $errors[] = 'Reel / Video URL is required.';
} elseif (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
    $errors[] = 'Invalid video URL format. Please provide a full link (e.g. https://instagram.com/reel/...).';
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
            ':entry_code' => $entryCode,
            ':full_name' => $fullName,
            ':mobile_number' => $cleanMobile,
            ':email_address' => $emailAddress,
            ':category' => $category,
            ':video_url' => $videoUrl,
            ':city' => $city,
            ':store_code' => $storeCode,
            ':instagram_handle' => $instagram,
            ':terms_agreed' => $termsAgreed,
            ':ip_address' => $clientIp,
            ':user_agent' => $userAgent
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
    // Send confirmation email to the contestant
    $emailSent = sendRegistrationConfirmationEmail(
        $emailAddress,
        $fullName,
        $entryCode,
        $category,
        $instagram,
        $videoUrl,
        $city,
        $storeCode
    );

    echo json_encode([
        'success'    => true,
        'entry_id'   => substr($entryCode, -6),
        'entry_code' => $entryCode,
        'db_id'      => $dbInsertId,
        'email_sent' => $emailSent,
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

/**
 * Dispatch HTML confirmation email to the contestant with instructions
 *
 * @param string $toEmail
 * @param string $fullName
 * @param string $entryCode
 * @param string $category
 * @param string $instagram
 * @param string $videoUrl
 * @param string $city
 * @param string $storeCode
 * @return bool True if dispatched, false otherwise
 */
function sendRegistrationConfirmationEmail($toEmail, $fullName, $entryCode, $category, $instagram, $videoUrl, $city, $storeCode)
{
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $categoryLabels = [
        'male'              => 'Male Creator',
        'female'            => 'Female Creator',
        'kids'              => 'Kids Ambassador',
        'group_family'      => 'Group / Family',
        'voice_of_meridian' => 'Voice of Meridian'
    ];
    $categoryName = $categoryLabels[$category] ?? ucfirst(str_replace('_', ' ', $category));

    $cityName = ucfirst($city);
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($entryCode, ENT_QUOTES, 'UTF-8');
    $safeCategory = htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8');
    $safeInstagram = htmlspecialchars($instagram ?: 'Not provided', ENT_QUOTES, 'UTF-8');
    $safeVideoUrl = htmlspecialchars($videoUrl ?: '#', ENT_QUOTES, 'UTF-8');
    $safeCity = htmlspecialchars($cityName, ENT_QUOTES, 'UTF-8');
    $safeStore = htmlspecialchars($storeCode ?: 'GENERAL', ENT_QUOTES, 'UTF-8');

    $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'contest@meridianicecreams.com';
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Meridian Brand Ambassador Contest';
    $replyTo = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'support@meridianicecreams.com';

    $subjectText = "Registration Confirmed: Meridian Brand Ambassador Contest [Pass #{$entryCode}]";
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subjectText) . "?=";
    $encodedFromName = "=?UTF-8?B?" . base64_encode($fromName) . "?=";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $encodedFromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $replyTo,
        'X-Mailer: PHP/' . phpversion()
    ];

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Confirmed - Meridian Ice Cream</title>
<style>
  body { margin: 0; padding: 0; background-color: #F8F9FA; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1F2937; line-height: 1.6; }
  .wrapper { width: 100%; max-width: 600px; margin: 30px auto; background: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #E5E7EB; }
  .header { background: linear-gradient(135deg, #D8232A 0%, #B91C1C 100%); padding: 32px 24px; text-align: center; color: #FFFFFF; }
  .header h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
  .header p { margin: 6px 0 0; font-size: 14px; opacity: 0.95; }
  .content { padding: 32px 28px; }
  .pass-card { background: #FEF3C7; border: 2px dashed #F59E0B; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
  .pass-title { font-size: 12px; font-weight: 800; text-transform: uppercase; color: #92400E; letter-spacing: 1px; }
  .pass-code { font-size: 30px; font-weight: 900; color: #D8232A; margin: 6px 0; letter-spacing: 1.5px; font-family: monospace, sans-serif; }
  .pass-meta { font-size: 13px; color: #78350F; }
  .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #F9FAFB; border-radius: 10px; overflow: hidden; border: 1px solid #E5E7EB; }
  .details-table td { padding: 12px 16px; font-size: 14px; border-bottom: 1px solid #E5E7EB; }
  .details-table td.label { font-weight: 600; color: #4B5563; width: 35%; }
  .details-table td.val { color: #111827; font-weight: 500; word-break: break-all; }
  .details-table tr:last-child td { border-bottom: none; }
  .notice-box { background: #EEF2FF; border-left: 4px solid #4F46E5; padding: 18px 20px; border-radius: 8px; margin: 24px 0; }
  .notice-title { font-weight: 700; color: #3730A3; font-size: 15px; margin-bottom: 8px; }
  .notice-list { margin: 0; padding-left: 18px; font-size: 13.5px; color: #312E81; }
  .notice-list li { margin-bottom: 8px; }
  .footer { background: #F9FAFB; padding: 24px; text-align: center; font-size: 12px; color: #6B7280; border-top: 1px solid #E5E7EB; }
  .footer a { color: #D8232A; text-decoration: none; font-weight: 600; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>Meridian Ice Cream</h1>
    <p>Official Brand Ambassador Contest 2026–2027</p>
  </div>
  <div class="content">
    <h2 style="font-size: 20px; font-weight: 700; color: #111827; margin-top: 0;">Registration Confirmed!</h2>
    <p style="font-size: 15px; color: #374151;">Dear <strong>{$safeName}</strong>,</p>
    <p style="font-size: 14.5px; color: #4B5563;">Thank you for entering the <strong>Meridian Ice Cream Brand Ambassador Contest</strong>! Your submission and creative entry have been successfully registered in our official contest database.</p>
    
    <div class="pass-card">
      <div class="pass-title">Official Contestant Ticket Pass</div>
      <div class="pass-code">{$safeCode}</div>
      <div class="pass-meta">Keep this entry code safe for your official records &amp; announcements</div>
    </div>

    <table class="details-table">
      <tr>
        <td class="label">Contestant Name</td>
        <td class="val">{$safeName}</td>
      </tr>
      <tr>
        <td class="label">Chosen Category</td>
        <td class="val">{$safeCategory}</td>
      </tr>
      <tr>
        <td class="label">Instagram Handle</td>
        <td class="val">{$safeInstagram}</td>
      </tr>
      <tr>
        <td class="label">Submitted Reel URL</td>
        <td class="val"><a href="{$safeVideoUrl}" target="_blank" style="color: #D8232A; text-decoration: underline;">View Reel Submission</a></td>
      </tr>
      <tr>
        <td class="label">Nearest City</td>
        <td class="val">{$safeCity}</td>
      </tr>
      <tr>
        <td class="label">Store / Standee Code</td>
        <td class="val">{$safeStore}</td>
      </tr>
    </table>

    <div class="notice-box">
      <div class="notice-title">What Happens Next?</div>
      <ul class="notice-list">
        <li><strong>Jury Evaluation in Progress:</strong> Our panel of creative judges is evaluating all submitted reels, engagement, and category presentations.</li>
        <li><strong>Keep Your Profile Public:</strong> Please ensure your Instagram profile remains public and keep your submission reel active with <strong>#FaceOfMeridian</strong>.</li>
        <li><strong>Shortlist &amp; Announcements:</strong> Shortlisted candidates will be contacted directly via this email and Instagram DM for the next stage.</li>
        <li><strong>Winner Rewards:</strong> Winning Brand Ambassadors will receive exclusive <strong>Free Ice Cream Vouchers</strong>, a professional studio portfolio photoshoot, and feature on parlour hoardings across Maharashtra!</li>
      </ul>
    </div>

    <p style="font-size: 13.5px; color: #6B7280; margin-bottom: 0;">If you have any questions regarding your submission, reply to this email or write to <a href="mailto:support@meridianicecreams.com" style="color: #D8232A;">support@meridianicecreams.com</a>.</p>
  </div>
  <div class="footer">
    <p style="margin: 0 0 6px;">&copy; 2026 Meridian Ice Cream. All rights reserved.</p>
    <p style="margin: 0;"><a href="https://www.meridianicecreams.com" target="_blank">www.meridianicecreams.com</a> &bull; 100% Natural Ice Creams Crafted with Love</p>
  </div>
</div>
</body>
</html>
HTML;

    try {
        $sent = @mail($toEmail, $encodedSubject, $htmlBody, implode("\r\n", $headers));
        return (bool)$sent;
    } catch (\Throwable $t) {
        error_log("Confirmation email dispatch exception: " . $t->getMessage());
        return false;
    }
}
