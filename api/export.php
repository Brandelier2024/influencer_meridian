<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Contest Entries Export & Reporting Tool
 * 
 * Usage:
 * - CSV Export: api/export.php?key=MeridianAdmin2026&format=csv
 * - JSON Feed: api/export.php?key=MeridianAdmin2026&format=json
 */

require_once __DIR__ . '/config.php';

$key = $_GET['key'] ?? '';
$format = $_GET['format'] ?? 'csv';
$categoryFilter = $_GET['category'] ?? '';

// Check secret admin key
if ($key !== ADMIN_SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized. Please supply the valid admin key: ?key=' . ADMIN_SECRET_KEY]);
    exit;
}

$pdo = getDbConnection();
$entries = [];

if ($pdo) {
    try {
        $sql = "SELECT id, entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, instagram_handle, status, created_at FROM meridian_contest_entries";
        $params = [];
        if (!empty($categoryFilter) && in_array($categoryFilter, ['male', 'female', 'child', 'group'])) {
            $sql .= " WHERE category = :category";
            $params[':category'] = $categoryFilter;
        }
        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $entries = [];
    }
}

// Check backup file if DB returned empty or DB unavailable
if (empty($entries)) {
    $backupFile = __DIR__ . '/../data/entries_backup.json';
    if (file_exists($backupFile)) {
        $entries = json_decode(file_get_contents($backupFile), true) ?: [];
    }
}

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total' => count($entries),
        'entries' => $entries
    ], JSON_PRETTY_PRINT);
    exit;
}

// Default: CSV Export
$filename = 'meridian_contest_entries_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header
fputcsv($output, ['ID', 'Entry Code', 'Full Name', 'Mobile Number', 'Email Address', 'Category', 'Video URL', 'City', 'Store Code', 'Instagram Handle', 'Status', 'Submission Date']);

foreach ($entries as $row) {
    fputcsv($output, [
        $row['id'] ?? '-',
        $row['entry_code'] ?? '-',
        $row['full_name'] ?? '-',
        $row['mobile_number'] ?? '-',
        $row['email_address'] ?? '-',
        $row['category'] ?? '-',
        $row['video_url'] ?? '-',
        $row['city'] ?? '-',
        $row['store_code'] ?? '-',
        $row['instagram_handle'] ?? '-',
        $row['status'] ?? 'registered',
        $row['created_at'] ?? '-'
    ]);
}

fclose($output);
exit;

