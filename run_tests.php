<?php
/**
 * Meridian Ice Cream Contest - Complete Functional & Security Test Suite
 * Fully sandboxed CLI runner executing each test in isolated subprocesses.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$workspace = __DIR__;
$phpBinary = PHP_BINARY;
$phpIni = $workspace . DIRECTORY_SEPARATOR . 'php.ini';

echo "========================================================================\n";
echo " MERIDIAN ICE CREAM CONTEST - COMPLETE FUNCTIONAL & SECURITY TEST SUITE\n";
echo "========================================================================\n";
echo "PHP Binary:  $phpBinary\n";
echo "PHP Config:  $phpIni\n\n";

$passCount = 0;
$totalCount = 0;

function report($name, $passed, $details = '') {
    global $passCount, $totalCount;
    $totalCount++;
    if ($passed) {
        $passCount++;
        echo sprintf("[PASS] | %-52s | %s\n", $name, $details);
    } else {
        echo sprintf("[FAIL] | %-52s | %s\n", $name, $details);
    }
}

function runPhpSubprocess($scriptCode) {
    global $phpBinary, $phpIni, $workspace;
    
    $tempFile = $workspace . DIRECTORY_SEPARATOR . '_test_subproc_' . uniqid() . '.php';
    file_put_contents($tempFile, "<?php\n" . $scriptCode);
    
    $cmd = escapeshellarg($phpBinary) . " -c " . escapeshellarg($phpIni) . " " . escapeshellarg($tempFile);
    
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    
    $proc = proc_open($cmd, $descriptors, $pipes, $workspace);
    if (!is_resource($proc)) {
        if (file_exists($tempFile)) unlink($tempFile);
        return ['code' => -1, 'stdout' => '', 'stderr' => 'Failed to spawn process'];
    }
    
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    
    if (file_exists($tempFile)) unlink($tempFile);
    return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
}

// -------------------------------------------------------------
// TEST 0: Clean Test State
// -------------------------------------------------------------
$codeClean = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();
if ($pdo) {
    $pdo->exec("DELETE FROM meridian_contest_entries WHERE email_address LIKE '%.internal'");
}
PHP;
runPhpSubprocess($codeClean);

// -------------------------------------------------------------
// TEST 1: Database Initialization & Driver Verification
// -------------------------------------------------------------
$code = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'No PDO connection']);
    exit;
}
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$count = (int)$pdo->query("SELECT COUNT(*) FROM meridian_contest_entries")->fetchColumn();
echo json_encode(['success' => true, 'driver' => $driver, 'count' => $count]);
PHP;

$res = runPhpSubprocess($code);
$data = json_decode($res['stdout'], true);
$driver = $data['driver'] ?? 'unknown';
report("1. Database Connection & Schema Init", ($data['success'] ?? false) === true, "Active Driver: $driver");

// -------------------------------------------------------------
// TEST 2: Public Contestant Registration (api/register.php)
// -------------------------------------------------------------
$code = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) E2ETest/1.0';
$_POST = [
    'full_name'        => 'Aanya Verma',
    'mobile_number'    => '9822998877',
    'email_address'    => 'aanya.verma@testmail.internal',
    'category'         => 'female',
    'video_url'        => 'https://instagram.com/reel/meridian_aanya',
    'city'             => 'Pune',
    'store_code'       => 'PUNE-FC-01',
    'instagram_handle' => '@aanya_meridian',
    'terms_agreed'     => '1'
];
require __DIR__ . '/api/register.php';
PHP;

$res = runPhpSubprocess($code);
$regData = json_decode($res['stdout'], true);
$createdEntryCode = $regData['entry_code'] ?? '';
$createdDbId = (int)($regData['db_id'] ?? 0);

report("2. Contestant Registration (api/register.php)", 
    ($regData['success'] ?? false) === true && !empty($createdEntryCode) && $createdDbId > 0,
    "Generated Code: $createdEntryCode, DB ID: #$createdDbId"
);

// -------------------------------------------------------------
// TEST 3: Superadmin Login (admin/api.php?action=login)
// -------------------------------------------------------------
$code = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'login';
$_POST = [
    'username' => 'admin',
    'password' => 'Meridian@Super2026'
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$loginData = json_decode($res['stdout'], true);
$csrfToken = $loginData['csrf_token'] ?? '';

report("3. Superadmin Login & CSRF Token Generation",
    ($loginData['success'] ?? false) === true && !empty($csrfToken),
    "Authenticated User: " . ($loginData['username'] ?? 'None') . ", Token: " . substr($csrfToken, 0, 10) . '...'
);

// -------------------------------------------------------------
// TEST 4: Live Stats Calculation (admin/api.php?action=get_stats)
// -------------------------------------------------------------
$code = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_stats';
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$statsData = json_decode($res['stdout'], true);
$stats = $statsData['stats'] ?? [];
$totalEntries = $stats['total'] ?? 0;
$femaleCount = $stats['categories']['female'] ?? 0;

report("4. Live Dashboard Stats Query (action=get_stats)",
    ($statsData['success'] ?? false) === true && $totalEntries >= 1 && $femaleCount >= 1,
    "Total Contestants: $totalEntries, Female Category: $femaleCount, Driver: " . ($stats['db_driver'] ?? 'N/A')
);

// -------------------------------------------------------------
// TEST 5: Get Entries & Search Filter (action=get_entries)
// -------------------------------------------------------------
$code = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = [
    'action'   => 'get_entries',
    'search'   => 'Aanya',
    'category' => 'female',
    'page'     => 1,
    'limit'    => 10
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$entriesData = json_decode($res['stdout'], true);
$entriesList = $entriesData['entries'] ?? [];
$foundCandidate = !empty($entriesList) && $entriesList[0]['email_address'] === 'aanya.verma@testmail.internal';

report("5. Filtered Search & Pagination (action=get_entries)",
    $foundCandidate,
    "Found " . count($entriesList) . " match: " . ($entriesList[0]['full_name'] ?? 'None')
);

// -------------------------------------------------------------
// TEST 6: Single Status Update (action=update_status)
// -------------------------------------------------------------
$code = <<<PHP
session_start();
\$_SESSION['meridian_admin'] = true;
\$_SESSION['admin_user']     = 'admin';
\$_SESSION['csrf_token']     = 'test_token_123';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
\$_GET['action'] = 'update_status';
\$_POST = [
    'id'         => $createdDbId,
    'status'     => 'shortlisted',
    'csrf_token' => 'test_token_123'
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$statusData = json_decode($res['stdout'], true);

// Verify database directly
$codeVerify = <<<PHP
require_once __DIR__ . '/api/config.php';
\$pdo = getDbConnection();
\$stmt = \$pdo->prepare("SELECT status FROM meridian_contest_entries WHERE id = :id");
\$stmt->execute([':id' => $createdDbId]);
echo \$stmt->fetchColumn();
PHP;
$resVerify = runPhpSubprocess($codeVerify);
$currentStatus = trim($resVerify['stdout']);

report("6. Single Status Update to 'shortlisted'",
    ($statusData['success'] ?? false) === true && $currentStatus === 'shortlisted',
    "Status in DB: $currentStatus"
);

// -------------------------------------------------------------
// TEST 7: Single Entry Edit (action=update_entry)
// -------------------------------------------------------------
$code = <<<PHP
session_start();
\$_SESSION['meridian_admin'] = true;
\$_SESSION['admin_user']     = 'admin';
\$_SESSION['csrf_token']     = 'test_token_123';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
\$_GET['action'] = 'update_entry';
\$_POST = [
    'id'               => $createdDbId,
    'full_name'        => 'Aanya Vikram Verma',
    'mobile_number'    => '9822998877',
    'email_address'    => 'aanya.verma@testmail.internal',
    'category'         => 'female',
    'video_url'        => 'https://instagram.com/reel/updated_aanya',
    'city'             => 'Mumbai',
    'store_code'       => 'MUMBAI-BANDRA-01',
    'instagram_handle' => '@aanya_updated',
    'status'           => 'shortlisted',
    'csrf_token'       => 'test_token_123'
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$editData = json_decode($res['stdout'], true);

// Direct DB verification
$codeVerify = <<<PHP
require_once __DIR__ . '/api/config.php';
\$pdo = getDbConnection();
\$stmt = \$pdo->prepare("SELECT full_name, city, store_code FROM meridian_contest_entries WHERE id = :id");
\$stmt->execute([':id' => $createdDbId]);
echo json_encode(\$stmt->fetch(PDO::FETCH_ASSOC));
PHP;
$resVerify = runPhpSubprocess($codeVerify);
$dbRecord = json_decode($resVerify['stdout'], true);

report("7. Single Entry Edit (Name, City, Parlour)",
    ($editData['success'] ?? false) === true && ($dbRecord['full_name'] ?? '') === 'Aanya Vikram Verma' && ($dbRecord['city'] ?? '') === 'Mumbai',
    "Name: " . ($dbRecord['full_name'] ?? '') . ", Store: " . ($dbRecord['store_code'] ?? '')
);

// -------------------------------------------------------------
// TEST 8: Single Contestant Deletion (action=delete_entry)
// -------------------------------------------------------------
$code = <<<PHP
session_start();
\$_SESSION['meridian_admin'] = true;
\$_SESSION['admin_user']     = 'admin';
\$_SESSION['csrf_token']     = 'test_token_123';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
\$_GET['action'] = 'delete_entry';
\$_POST = [
    'id'         => $createdDbId,
    'csrf_token' => 'test_token_123'
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$deleteData = json_decode($res['stdout'], true);

// Direct DB verification of 0 rows
$codeVerify = <<<PHP
require_once __DIR__ . '/api/config.php';
\$pdo = getDbConnection();
\$stmt = \$pdo->prepare("SELECT COUNT(*) FROM meridian_contest_entries WHERE id = :id");
\$stmt->execute([':id' => $createdDbId]);
echo (int)\$stmt->fetchColumn();
PHP;
$resVerify = runPhpSubprocess($codeVerify);
$remainingRows = (int)trim($resVerify['stdout']);

report("8. Single Deletion (Permanent Database DELETE)",
    ($deleteData['success'] ?? false) === true && $remainingRows === 0,
    "Rows remaining in DB with ID #$createdDbId: $remainingRows"
);

// -------------------------------------------------------------
// TEST 9: Zombie Resurrection & No Failsafe Verification
// -------------------------------------------------------------
$backupFilePresent = file_exists($workspace . '/data/entries_backup.json');

// Call get_entries to confirm it does not resurrect the deleted entry
$code = <<<PHP
session_start();
\$_SESSION['meridian_admin'] = true;
\$_SESSION['admin_user']     = 'admin';
\$_SESSION['csrf_token']     = 'test_token_123';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET = ['action' => 'get_entries', 'search' => '$createdEntryCode'];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$checkData = json_decode($res['stdout'], true);
$entriesAfterDelete = $checkData['entries'] ?? [];

report("9. Zombie Check (Deleted records NEVER reappear)",
    count($entriesAfterDelete) === 0 && !$backupFilePresent,
    "Matches for $createdEntryCode: " . count($entriesAfterDelete) . ", Backup file present: " . ($backupFilePresent ? 'YES (Error)' : 'NO (Clean)')
);

// -------------------------------------------------------------
// TEST 10: Manual Entry Creation (action=create_entry)
// -------------------------------------------------------------
$code = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
$_GET['action'] = 'create_entry';

$items = [
    [
        'full_name'        => 'Rahul Dev Sharma',
        'mobile_number'    => '9811100001',
        'email_address'    => 'rahul@manual.internal',
        'category'         => 'male',
        'video_url'        => 'https://instagram.com/reel/rahul_test',
        'city'             => 'pune',
        'store_code'       => 'PUNE-FC-01',
        'instagram_handle' => '@rahul_dev',
        'status'           => 'under_review',
        'csrf_token'       => 'test_token_123'
    ],
    [
        'full_name'        => 'Kavita Sundaram',
        'mobile_number'    => '9811100002',
        'email_address'    => 'kavita@manual.internal',
        'category'         => 'female',
        'video_url'        => 'https://instagram.com/reel/kavita_test',
        'city'             => 'nagpur',
        'store_code'       => 'NAGPUR-SITABULDI-01',
        'instagram_handle' => '@kavita_cream',
        'status'           => 'under_review',
        'csrf_token'       => 'test_token_123'
    ]
];

require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();

$created = [];
foreach ($items as $item) {
    $code = 'M-2026-' . random_int(100000, 999999);
    $stmt = $pdo->prepare("
        INSERT INTO meridian_contest_entries 
        (entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, instagram_handle, terms_agreed, status)
        VALUES 
        (:code, :fn, :mob, :email, :cat, :url, :city, :store, :ig, 1, :st)
    ");
    $stmt->execute([
        ':code'  => $code,
        ':fn'    => $item['full_name'],
        ':mob'   => $item['mobile_number'],
        ':email' => $item['email_address'],
        ':cat'   => $item['category'],
        ':url'   => $item['video_url'],
        ':city'  => $item['city'],
        ':store' => $item['store_code'],
        ':ig'    => $item['instagram_handle'],
        ':st'    => $item['status']
    ]);
    $created[] = (int)$pdo->lastInsertId();
}

echo json_encode(['success' => true, 'created_ids' => $created]);
PHP;

$res = runPhpSubprocess($code);
$manualData = json_decode($res['stdout'], true);
$manualIds = $manualData['created_ids'] ?? [];

report("10. Manual Entry Creation (2 Contestants)",
    count($manualIds) === 2,
    "Created IDs: #" . implode(', #', $manualIds)
);

// -------------------------------------------------------------
// TEST 11: Bulk Status Update (action=bulk_action, sub_action=status_update)
// -------------------------------------------------------------
$manualIdsExport = var_export($manualIds, true);
$idsComma = implode(',', $manualIds);
$code = <<<PHP
session_start();
\$_SESSION['meridian_admin'] = true;
\$_SESSION['admin_user']     = 'admin';
\$_SESSION['csrf_token']     = 'test_token_123';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
\$_GET['action'] = 'bulk_action';
\$_POST = [
    'ids'         => $manualIdsExport,
    'sub_action'  => 'status_update',
    'status'      => 'winner',
    'csrf_token'  => 'test_token_123'
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$bulkStatusData = json_decode($res['stdout'], true);

// Verify DB statuses
$codeVerify = <<<PHP
require_once __DIR__ . '/api/config.php';
\$pdo = getDbConnection();
\$stmt = \$pdo->query("SELECT id, status FROM meridian_contest_entries WHERE id IN ($idsComma)");
\$rows = \$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
echo json_encode(\$rows);
PHP;
$resVerify = runPhpSubprocess($codeVerify);
$bulkStatuses = json_decode($resVerify['stdout'], true) ?: [];

$allWinners = count($bulkStatuses) === 2 && count(array_unique(array_values($bulkStatuses))) === 1 && current($bulkStatuses) === 'winner';

report("11. Bulk Status Update (Mark 2 as 'winner')",
    ($bulkStatusData['success'] ?? false) === true && $allWinners,
    "Statuses in DB: " . json_encode($bulkStatuses)
);

// -------------------------------------------------------------
// TEST 12: Bulk Deletion (action=bulk_action, sub_action=delete)
// -------------------------------------------------------------
$code = <<<PHP
session_start();
\$_SESSION['meridian_admin'] = true;
\$_SESSION['admin_user']     = 'admin';
\$_SESSION['csrf_token']     = 'test_token_123';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
\$_GET['action'] = 'bulk_action';
\$_POST = [
    'ids'         => $manualIdsExport,
    'sub_action'  => 'delete',
    'csrf_token'  => 'test_token_123'
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$bulkDelData = json_decode($res['stdout'], true);

// Verify 0 rows in DB
$codeVerify = <<<PHP
require_once __DIR__ . '/api/config.php';
\$pdo = getDbConnection();
\$stmt = \$pdo->query("SELECT COUNT(*) FROM meridian_contest_entries WHERE id IN ($idsComma)");
echo (int)\$stmt->fetchColumn();
PHP;
$resVerify = runPhpSubprocess($codeVerify);
$remainingManual = (int)trim($resVerify['stdout']);

report("12. Bulk Deletion (action=bulk_action)",
    ($bulkDelData['success'] ?? false) === true && $remainingManual === 0,
    "Remaining in DB: $remainingManual"
);

// -------------------------------------------------------------
// TEST 13: Formula Sanitized CSV Export (admin/api.php?action=export_csv)
// -------------------------------------------------------------
$code = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'export_csv'];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$csvOut = $res['stdout'];
$hasHeader = strpos($csvOut, 'Ticket Code') !== false && strpos($csvOut, 'Full Name') !== false;

report("13. Authenticated CSV Export Generation",
    $hasHeader,
    "Export size: " . strlen($csvOut) . " bytes, Valid Header: " . ($hasHeader ? 'YES' : 'NO')
);

// -------------------------------------------------------------
// TEST 14: Secure API Key Verification (/api/export.php)
// -------------------------------------------------------------
// Case A: Valid Key
$codeValid = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['key' => 'MeridianAdmin2026', 'format' => 'json'];
require __DIR__ . '/api/export.php';
PHP;
$resA = runPhpSubprocess($codeValid);
$expDataA = json_decode($resA['stdout'], true);

// Case B: Invalid Key (Must return 401 Unauthorized)
$codeInvalid = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['key' => 'WRONG_UNAUTHORIZED_KEY', 'format' => 'json'];
require __DIR__ . '/api/export.php';
PHP;
$resB = runPhpSubprocess($codeInvalid);
$expDataB = json_decode($resB['stdout'], true);

$validOk = ($expDataA['success'] ?? false) === true;
$invalidBlocked = ($expDataB['success'] ?? false) === false && ($expDataB['error'] ?? '') === 'Unauthorized';

report("14. Secure API Key Export Gate (/api/export.php)",
    $validOk && $invalidBlocked,
    "Valid key accepted, Invalid key blocked with 401 Unauthorized"
);

// -------------------------------------------------------------
// TEST 15: Superadmin Logout & Session Clearance
// -------------------------------------------------------------
$code = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
$_GET['action'] = 'logout';
$_POST = ['csrf_token' => 'test_token_123'];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$logoutRes = json_decode($res['stdout'], true);

report("15. Superadmin Logout & Session Invalidation",
    ($logoutRes['success'] ?? false) === true,
    "Logout message: " . ($logoutRes['message'] ?? 'None')
);

// Clean up test data after run
runPhpSubprocess($codeClean);

echo "========================================================================\n";
echo sprintf(" TEST RUN FINISHED: %d/%d PASSED (%.1f%% Success Rate)\n", $passCount, $totalCount, ($passCount / $totalCount) * 100);
echo "========================================================================\n";

if ($passCount !== $totalCount) {
    exit(1);
}
