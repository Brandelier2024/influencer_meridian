<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Secure Contest Entries Export & Reporting Tool
 * 
 * Usage:
 * - CSV Export: api/export.php?key=<YOUR_ADMIN_KEY>&format=csv
 * - JSON Feed: api/export.php?key=<YOUR_ADMIN_KEY>&format=json
 * 
 * Supported Auth:
 * - Query Parameter: ?key=...
 * - Header: Authorization: Bearer <key>
 * - Header: X-Admin-Key: <key>
 */

require_once __DIR__ . '/config.php';

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');

// Extract admin key from multiple possible sources
$providedKey = '';

if (!empty($_GET['key'])) {
    $providedKey = trim((string)$_GET['key']);
} elseif (!empty($_SERVER['HTTP_X_ADMIN_KEY'])) {
    $providedKey = trim((string)$_SERVER['HTTP_X_ADMIN_KEY']);
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $providedKey = $matches[1];
    }
}

// Timing-safe secret key verification
if (empty($providedKey) || !hash_equals(ADMIN_SECRET_KEY, $providedKey)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized',
        'message' => 'Valid administrator credentials required.'
    ]);
    exit;
}

$format = strtolower(trim($_GET['format'] ?? 'csv'));
$categoryFilter = strtolower(trim($_GET['category'] ?? ''));
$statusFilter = strtolower(trim($_GET['status'] ?? ''));
$hasReel = strtolower(trim($_GET['has_reel'] ?? $_GET['reel'] ?? ''));

$pdo = getDbConnection();
$entries = [];

if ($pdo) {
    try {
        $sql = "SELECT id, entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, instagram_handle, status, created_at FROM meridian_contest_entries";
        $where = [];
        $params = [];
        if (!empty($categoryFilter)) {
            if ($categoryFilter === 'uploaded reels' || $categoryFilter === 'uploaded_reels' || $categoryFilter === 'reels') {
                $where[] = "(video_url IS NOT NULL AND TRIM(video_url) != '' AND video_url != '-')";
            } elseif ($categoryFilter === 'kids' || $categoryFilter === 'child') {
                $where[] = "(category = 'kids' OR category = 'child')";
            } elseif ($categoryFilter === 'group_family' || $categoryFilter === 'group' || $categoryFilter === 'family') {
                $where[] = "(category = 'group_family' OR category = 'group' OR category = 'family')";
            } elseif (in_array($categoryFilter, ['male', 'female', 'voice_of_meridian'], true)) {
                $where[] = "category = :category";
                $params[':category'] = $categoryFilter;
            }
        }
        if (!empty($statusFilter) && in_array($statusFilter, ['registered', 'under_review', 'shortlisted', 'winner', 'rejected', 'archived'], true)) {
            $where[] = "status = :status";
            $params[':status'] = $statusFilter;
        }
        if (!empty($hasReel)) {
            if ($hasReel === 'yes' || $hasReel === '1' || $hasReel === 'has_reel') {
                $where[] = "(video_url IS NOT NULL AND TRIM(video_url) != '' AND video_url != '-')";
            } elseif ($hasReel === 'no' || $hasReel === '0' || $hasReel === 'no_reel') {
                $where[] = "(video_url IS NULL OR TRIM(video_url) = '' OR video_url = '-')";
            }
        }
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Export Query Error: " . $e->getMessage());
        $entries = [];
    }
}

// JSON Output Format
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'total'   => count($entries),
        'entries' => $entries
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitize cell values against CSV / Formula Injection (DDE)
 * Prevents execution of malicious formulas in Excel/LibreOffice
 */
function sanitizeCsvCell($value)
{
    if ($value === null || $value === '') {
        return '-';
    }
    $str = (string)$value;
    $firstChar = substr($str, 0, 1);
    
    // If field starts with formula trigger characters, prefix with single quote
    if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r", "\n", '%'])) {
        return "'" . $str;
    }
    return $str;
}

// Default: CSV Export
$filename = 'meridian_contest_entries_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Microsoft Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header
fputcsv($output, [
    'ID',
    'Entry Code',
    'Full Name',
    'Mobile Number',
    'Email Address',
    'Category',
    'Video URL',
    'City',
    'Store Code',
    'Instagram Handle',
    'Status',
    'Submission Date'
]);

// Stream sanitized CSV rows
foreach ($entries as $row) {
    fputcsv($output, [
        sanitizeCsvCell($row['id'] ?? '-'),
        sanitizeCsvCell($row['entry_code'] ?? '-'),
        sanitizeCsvCell($row['full_name'] ?? '-'),
        sanitizeCsvCell($row['mobile_number'] ?? '-'),
        sanitizeCsvCell($row['email_address'] ?? '-'),
        sanitizeCsvCell($row['category'] ?? '-'),
        sanitizeCsvCell($row['video_url'] ?? '-'),
        sanitizeCsvCell($row['city'] ?? '-'),
        sanitizeCsvCell($row['store_code'] ?? '-'),
        sanitizeCsvCell($row['instagram_handle'] ?? '-'),
        sanitizeCsvCell($row['status'] ?? 'registered'),
        sanitizeCsvCell($row['created_at'] ?? '-')
    ]);
}

fclose($output);
exit;
