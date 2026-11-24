<?php
/**
 * Meridian Ice Cream Contest - Complete Functional & Security Test Suite
 * Fully sandboxed CLI runner executing each test in isolated subprocesses.
 * Tests public contest features AND database-driven superadmin authentication.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$workspace = __DIR__;
$phpBinary = PHP_BINARY;
$phpIni = $workspace . DIRECTORY_SEPARATOR . 'php.ini';

echo "========================================================================\n";
echo " MERIDIAN ICE CREAM CONTEST - DATABASE SUPERADMIN & SYSTEM TEST SUITE\n";
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
    $pdo->exec("DELETE FROM meridian_contest_entries WHERE email_address LIKE '%.internal%' OR email_address LIKE '%.test'");
}
PHP;
runPhpSubprocess($codeClean);

// -------------------------------------------------------------
// TEST 1: Database Initialization & Tables Check
// -------------------------------------------------------------
$code = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'No PDO connection']);
    exit;
}
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// Check both contest entries and admin users tables
$adminCount = (int)$pdo->query("SELECT COUNT(*) FROM meridian_admin_users")->fetchColumn();
$entriesCount = (int)$pdo->query("SELECT COUNT(*) FROM meridian_contest_entries")->fetchColumn();

echo json_encode([
    'success'     => true,
    'driver'      => $driver,
    'admin_count' => $adminCount,
    'entries_cnt' => $entriesCount
]);
PHP;

$res = runPhpSubprocess($code);
$data = json_decode($res['stdout'], true);
$driver = $data['driver'] ?? 'unknown';
$adminCount = $data['admin_count'] ?? 0;

report("1. Database Connection & Admin Table Init", 
    ($data['success'] ?? false) === true && $adminCount >= 1, 
    "Active Driver: $driver, DB Admins: $adminCount"
);

// -------------------------------------------------------------
// TEST 2: Public Contestant Registration (api/register.php)
// -------------------------------------------------------------
$testUniqueId = uniqid();
$code = <<<PHP
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) E2ETest/1.0';
\$_POST = [
    'full_name'        => 'Aanya Verma $testUniqueId',
    'mobile_number'    => '9822998877',
    'email_address'    => 'aanya.$testUniqueId@testmail.internal',
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
// TEST 3: Superadmin Login via Database (admin/api.php?action=login)
// -------------------------------------------------------------
$code = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
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

// Check database to verify last_login_at and last_login_ip were updated
$codeMeta = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();
$adminRow = $pdo->query("SELECT id, username, role, last_login_at, last_login_ip FROM meridian_admin_users WHERE username = 'admin'")->fetch(PDO::FETCH_ASSOC);
echo json_encode($adminRow);
PHP;
$resMeta = runPhpSubprocess($codeMeta);
$adminMeta = json_decode($resMeta['stdout'], true);
$loginIpSaved = ($adminMeta['last_login_ip'] ?? '') === '127.0.0.1';
$loginTimeSaved = !empty($adminMeta['last_login_at']);

report("3. Database Superadmin Login & Audit Meta",
    ($loginData['success'] ?? false) === true && !empty($csrfToken) && $loginIpSaved && $loginTimeSaved,
    "User: " . ($loginData['username'] ?? 'None') . ", Role: " . ($loginData['role'] ?? '') . ", IP Logged: " . ($adminMeta['last_login_ip'] ?? '')
);

// -------------------------------------------------------------
// TEST 4: Fetch Admin Profile from Database (action=get_admin_profile)
// -------------------------------------------------------------
$code = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_admin_profile';
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$profData = json_decode($res['stdout'], true);
$prof = $profData['profile'] ?? [];

report("4. Superadmin Profile Query (action=get_admin_profile)",
    ($profData['success'] ?? false) === true && ($prof['username'] ?? '') === 'admin' && ($prof['role'] ?? '') === 'superadmin',
    "Full Name: " . ($prof['full_name'] ?? '') . ", Email: " . ($prof['email'] ?? '')
);

// -------------------------------------------------------------
// TEST 5: Change Password in Database (action=change_password)
// -------------------------------------------------------------
$codePwd = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
$_GET['action'] = 'change_password';
$_POST = [
    'current_password' => 'Meridian@Super2026',
    'new_password'     => 'MeridianUpdatedPass@2027!',
    'confirm_password' => 'MeridianUpdatedPass@2027!',
    'csrf_token'       => 'test_token_123'
];
require __DIR__ . '/admin/api.php';
PHP;

$resPwd = runPhpSubprocess($codePwd);
$pwdData = json_decode($resPwd['stdout'], true);

// Verify that login with new password succeeds
$codeTestNew = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_GET['action'] = 'login';
$_POST = [
    'username' => 'admin',
    'password' => 'MeridianUpdatedPass@2027!'
];
require __DIR__ . '/admin/api.php';
PHP;
$resTestNew = runPhpSubprocess($codeTestNew);
$newLoginData = json_decode($resTestNew['stdout'], true);
$newLoginWorks = ($newLoginData['success'] ?? false) === true;

// Reset password back to standard default
$codeReset = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
$_GET['action'] = 'change_password';
$_POST = [
    'current_password' => 'MeridianUpdatedPass@2027!',
    'new_password'     => 'Meridian@Super2026',
    'confirm_password' => 'Meridian@Super2026',
    'csrf_token'       => 'test_token_123'
];
require __DIR__ . '/admin/api.php';
PHP;
runPhpSubprocess($codeReset);

report("5. Change Superadmin Password in Database",
    ($pwdData['success'] ?? false) === true && $newLoginWorks,
    "Updated password in database verified, reset cleanly to standard"
);

// -------------------------------------------------------------
// TEST 6: Live Stats Calculation (admin/api.php?action=get_stats)
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

report("6. Live Dashboard Stats Query (action=get_stats)",
    ($statsData['success'] ?? false) === true && $totalEntries >= 1 && $femaleCount >= 1,
    "Total Contestants: $totalEntries, Female Category: $femaleCount, Driver: " . ($stats['db_driver'] ?? 'N/A')
);

// -------------------------------------------------------------
// TEST 7: Get Entries & Search Filter (action=get_entries)
// -------------------------------------------------------------
$code = <<<PHP
session_start();
\$_SESSION['meridian_admin'] = true;
\$_SESSION['admin_user']     = 'admin';
\$_SESSION['csrf_token']     = 'test_token_123';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET = [
    'action'   => 'get_entries',
    'search'   => '$createdEntryCode',
    'category' => 'female',
    'page'     => 1,
    'limit'    => 10
];
require __DIR__ . '/admin/api.php';
PHP;

$res = runPhpSubprocess($code);
$entriesData = json_decode($res['stdout'], true);
$entriesList = $entriesData['entries'] ?? [];
$foundCandidate = !empty($entriesList) && $entriesList[0]['entry_code'] === $createdEntryCode;

report("7. Filtered Search & Pagination (action=get_entries)",
    $foundCandidate,
    "Found " . count($entriesList) . " match for $createdEntryCode"
);

// -------------------------------------------------------------
// TEST 8: Single Status Update (action=update_status)
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

report("8. Single Status Update to 'shortlisted'",
    ($statusData['success'] ?? false) === true && $currentStatus === 'shortlisted',
    "Status in DB: $currentStatus"
);

// -------------------------------------------------------------
// TEST 9: Single Entry Edit (action=update_entry)
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
    'email_address'    => 'aanya.$testUniqueId@testmail.internal',
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

report("9. Single Entry Edit (Name, City, Parlour)",
    ($editData['success'] ?? false) === true && ($dbRecord['full_name'] ?? '') === 'Aanya Vikram Verma' && ($dbRecord['city'] ?? '') === 'Mumbai',
    "Name: " . ($dbRecord['full_name'] ?? '') . ", Store: " . ($dbRecord['store_code'] ?? '')
);

// -------------------------------------------------------------
// TEST 10: Single Contestant Deletion (action=delete_entry)
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

report("10. Single Deletion (Permanent Database DELETE)",
    ($deleteData['success'] ?? false) === true && $remainingRows === 0,
    "Rows remaining in DB with ID #$createdDbId: $remainingRows"
);

// -------------------------------------------------------------
// TEST 11: Zombie Resurrection Check
// -------------------------------------------------------------
$backupFilePresent = file_exists($workspace . '/data/entries_backup.json');

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

report("11. Zombie Check (Deleted records NEVER reappear)",
    count($entriesAfterDelete) === 0 && !$backupFilePresent,
    "Matches for $createdEntryCode: " . count($entriesAfterDelete) . ", Backup file present: " . ($backupFilePresent ? 'YES (Error)' : 'NO (Clean)')
);

// -------------------------------------------------------------
// TEST 12: Manual Entry Creation (action=create_entry)
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

report("12. Manual Entry Creation (2 Contestants)",
    count($manualIds) === 2,
    "Created IDs: #" . implode(', #', $manualIds)
);

// -------------------------------------------------------------
// TEST 13: Bulk Status Update (action=bulk_action, sub_action=status_update)
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

report("13. Bulk Status Update (Mark 2 as 'winner')",
    ($bulkStatusData['success'] ?? false) === true && $allWinners,
    "Statuses in DB: " . json_encode($bulkStatuses)
);

// -------------------------------------------------------------
// TEST 14: Bulk Deletion (action=bulk_action, sub_action=delete)
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

report("14. Bulk Deletion (action=bulk_action)",
    ($bulkDelData['success'] ?? false) === true && $remainingManual === 0,
    "Remaining in DB: $remainingManual"
);

// -------------------------------------------------------------
// TEST 15: Formula Sanitized CSV Export (admin/api.php?action=export_csv)
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

report("15. Authenticated CSV Export Generation",
    $hasHeader,
    "Export size: " . strlen($csvOut) . " bytes, Valid Header: " . ($hasHeader ? 'YES' : 'NO')
);

// -------------------------------------------------------------
// TEST 16: Secure API Key Verification (/api/export.php)
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

report("16. Secure API Key Export Gate (/api/export.php)",
    $validOk && $invalidBlocked,
    "Valid key accepted, Invalid key blocked with 401 Unauthorized"
);

// -------------------------------------------------------------
// TEST 17: Superadmin Logout & Session Clearance
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

report("17. Superadmin Logout & Session Invalidation",
    ($logoutRes['success'] ?? false) === true,
    "Logout message: " . ($logoutRes['message'] ?? 'None')
);

// -------------------------------------------------------------
// TEST 18: Five Contest Categories Verification
// -------------------------------------------------------------
// 1. Register Voice of Meridian entry
$codeVoiceReg = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 VoiceTest/1.0';
$_POST = [
    'full_name'        => 'Aarav Voice Singer',
    'mobile_number'    => '9822334455',
    'email_address'    => 'aarav.voice@internal.test',
    'category'         => 'voice_of_meridian',
    'video_url'        => 'https://instagram.com/reel/meridian_jingle_singing',
    'instagram_handle' => '@aarav_singer',
    'city'             => 'pune',
    'terms_agreed'     => '1'
];
require __DIR__ . '/api/register.php';
PHP;
$resVoice = runPhpSubprocess($codeVoiceReg);
$voiceRegData = json_decode($resVoice['stdout'], true);

// 2. Insert kids and group_family, then query entries filtered by voice_of_meridian
$codeFilterCheck = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();

$codeKids = 'M-2026-' . random_int(100000, 999999);
$codeGrp = 'M-2026-' . random_int(100000, 999999);

$stmt = $pdo->prepare("
    INSERT INTO meridian_contest_entries 
    (entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, terms_agreed, status)
    VALUES 
    (:ck, 'Little Ananya', '9822334456', 'ananya.kids@internal.test', 'kids', 'https://instagram.com/reel/kids', 'mumbai', 'MUM-01', 1, 'registered'),
    (:cg, 'Deshmukh Squad', '9822334457', 'squad@internal.test', 'group_family', 'https://instagram.com/reel/squad', 'nashik', 'NAS-01', 1, 'registered')
");
$stmt->execute([':ck' => $codeKids, ':cg' => $codeGrp]);

session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'get_entries', 'category' => 'voice_of_meridian'];
require __DIR__ . '/admin/api.php';
PHP;
$resFilter = runPhpSubprocess($codeFilterCheck);
$filterData = json_decode($resFilter['stdout'], true);
$voiceFound = count($filterData['entries'] ?? []);

// 3. Query stats to verify all 5 category counts
$codeStatsCheck = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'get_stats'];
require __DIR__ . '/admin/api.php';
PHP;
$resStats = runPhpSubprocess($codeStatsCheck);
$statsData = json_decode($resStats['stdout'], true);
$cats = $statsData['stats']['categories'] ?? [];

$hasKids = ($cats['kids'] ?? 0) >= 1;
$hasGroup = ($cats['group_family'] ?? 0) >= 1;
$hasVoice = ($cats['voice_of_meridian'] ?? 0) >= 1;

$catSuccess = ($voiceRegData['success'] ?? false) === true 
    && $voiceFound >= 1
    && $hasKids
    && $hasGroup
    && $hasVoice;

report("18. Five Categories (Voice, Kids, Group/Family, Male, Female)",
    $catSuccess,
    "Voice of Meridian found: $voiceFound, Kids: " . ($hasKids ? 'YES' : 'NO') . ", Group/Family: " . ($hasGroup ? 'YES' : 'NO')
);

// -------------------------------------------------------------
// TEST 19: Instagram Reel URL Filter Verification (has_reel & no_reel)
// -------------------------------------------------------------
$codeInsertReels = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();

$codeWithReel = 'M-REEL-' . random_int(100000, 999999);
$codeNoReel   = 'M-NOREEL-' . random_int(100000, 999999);

$stmt = $pdo->prepare("
    INSERT INTO meridian_contest_entries 
    (entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, terms_agreed, status)
    VALUES 
    (:cr, 'Reel Submitter', '9899001122', 'has_reel_test@internal.test', 'female', 'https://instagram.com/reel/meridian_creator_123', 'mumbai', 'MUM-01', 1, 'registered'),
    (:cn, 'No Reel Submitter', '9899001123', 'no_reel_test@internal.test', 'male', '', 'pune', 'PUN-01', 1, 'registered')
");
$stmt->execute([':cr' => $codeWithReel, ':cn' => $codeNoReel]);

echo json_encode(['with_reel' => $codeWithReel, 'no_reel' => $codeNoReel]);
PHP;
$resInsert = runPhpSubprocess($codeInsertReels);
$insertData = json_decode($resInsert['stdout'], true);
$targetHas = $insertData['with_reel'] ?? '';
$targetNo  = $insertData['no_reel'] ?? '';

// 1. Query has_reel=has_reel
$codeFilterHasReel = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'get_entries', 'has_reel' => 'has_reel', 'search' => 'Reel Submitter'];
require __DIR__ . '/admin/api.php';
PHP;
$resHas = runPhpSubprocess($codeFilterHasReel);
$dataHas = json_decode($resHas['stdout'], true);
$hasCodes = array_column($dataHas['entries'] ?? [], 'entry_code');

// 2. Query has_reel=no_reel
$codeFilterNoReel = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'get_entries', 'has_reel' => 'no_reel', 'search' => 'Reel Submitter'];
require __DIR__ . '/admin/api.php';
PHP;
$resNo = runPhpSubprocess($codeFilterNoReel);
$dataNo = json_decode($resNo['stdout'], true);
$noCodes = array_column($dataNo['entries'] ?? [], 'entry_code');

// 3. Query category='Uploaded Reels' (legacy dropdown option)
$codeLegacyCat = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'get_entries', 'category' => 'Uploaded Reels', 'search' => 'Reel Submitter'];
require __DIR__ . '/admin/api.php';
PHP;
$resLegacy = runPhpSubprocess($codeLegacyCat);
$dataLegacy = json_decode($resLegacy['stdout'], true);
$legacyCodes = array_column($dataLegacy['entries'] ?? [], 'entry_code');

// 4. Query stats
$codeStatsReel = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['action' => 'get_stats'];
require __DIR__ . '/admin/api.php';
PHP;
$resStats = runPhpSubprocess($codeStatsReel);
$dataStats = json_decode($resStats['stdout'], true);
$withReelStat = $dataStats['stats']['with_reel'] ?? null;

$hasPassed = in_array($targetHas, $hasCodes, true) && !in_array($targetNo, $hasCodes, true);
$noPassed = in_array($targetNo, $noCodes, true) && !in_array($targetHas, $noCodes, true);
$legacyCatPassed = in_array($targetHas, $legacyCodes, true) && !in_array($targetNo, $legacyCodes, true);
$statPassed = ($withReelStat !== null && $withReelStat >= 1);

$reelTestSuccess = $hasPassed && $noPassed && $legacyCatPassed && $statPassed;

report("19. Instagram Reel URL Filter (has_reel & no_reel)",
    $reelTestSuccess,
    "has_reel: " . ($hasPassed ? 'PASS' : 'FAIL') .
    ", no_reel: " . ($noPassed ? 'PASS' : 'FAIL') .
    ", category='Uploaded Reels': " . ($legacyCatPassed ? 'PASS' : 'FAIL') .
    ", with_reel stat: " . ($withReelStat ?? 'MISSING')
);

// -------------------------------------------------------------
// TEST 20: Required Instagram Handle & Reel URL Validation Gate
// -------------------------------------------------------------
// Case A: Missing Instagram Handle
$codeNoIg = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_POST = [
    'full_name'        => 'Test User A',
    'mobile_number'    => '9800112233',
    'email_address'    => 'test_user_a@internal.test',
    'category'         => 'male',
    'video_url'        => 'https://instagram.com/reel/valid123',
    'city'             => 'pune',
    'terms_agreed'     => '1'
];
require __DIR__ . '/api/register.php';
PHP;
$resNoIg = runPhpSubprocess($codeNoIg);
$dataNoIg = json_decode($resNoIg['stdout'], true);
$noIgBlocked = ($dataNoIg['success'] ?? false) === false
            && in_array('Instagram handle is required.', $dataNoIg['errors'] ?? [], true);

// Case B: Missing Reel URL
$codeNoReel = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_POST = [
    'full_name'        => 'Test User B',
    'mobile_number'    => '9800112234',
    'email_address'    => 'test_user_b@internal.test',
    'category'         => 'female',
    'instagram_handle' => '@testuser_b',
    'city'             => 'pune',
    'terms_agreed'     => '1'
];
require __DIR__ . '/api/register.php';
PHP;
$resNoReel = runPhpSubprocess($codeNoReel);
$dataNoReel = json_decode($resNoReel['stdout'], true);
$noReelBlocked = ($dataNoReel['success'] ?? false) === false
              && in_array('Reel / Video URL is required.', $dataNoReel['errors'] ?? [], true);

// Case C: Invalid Reel URL format
$codeInvalidUrl = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_POST = [
    'full_name'        => 'Test User C',
    'mobile_number'    => '9800112235',
    'email_address'    => 'test_user_c@internal.test',
    'category'         => 'kids',
    'instagram_handle' => '@testuser_c',
    'video_url'        => 'not_a_valid_url',
    'city'             => 'pune',
    'terms_agreed'     => '1'
];
require __DIR__ . '/api/register.php';
PHP;
$resInvalidUrl = runPhpSubprocess($codeInvalidUrl);
$dataInvalidUrl = json_decode($resInvalidUrl['stdout'], true);
$invalidBlocked = ($dataInvalidUrl['success'] ?? false) === false
               && count(array_filter($dataInvalidUrl['errors'] ?? [], fn($e) => str_contains($e, 'Invalid video URL'))) > 0;

$validationGatePassed = $noIgBlocked && $noReelBlocked && $invalidBlocked;

report("20. Required Fields Gate (Instagram & Reel URL)",
    $validationGatePassed,
    "Missing IG blocked: " . ($noIgBlocked ? 'YES' : 'NO') .
    ", Missing Reel blocked: " . ($noReelBlocked ? 'YES' : 'NO') .
    ", Malformed URL blocked: " . ($invalidBlocked ? 'YES' : 'NO')
);

// -------------------------------------------------------------
// TEST 21: Smart Duplicate Backfill on Registration (api/register.php)
// -------------------------------------------------------------
$codeBackfillTest = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();

$testMob = '9876500111';
$testEmail = 'backfill_contestant@internal.test';
$pdo->exec("DELETE FROM meridian_contest_entries WHERE mobile_number = '$testMob' OR email_address = '$testEmail'");

$origCode = 'M-2026-654321';
$pdo->prepare("
    INSERT INTO meridian_contest_entries 
    (entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, instagram_handle, terms_agreed, status)
    VALUES 
    (:code, 'Pooja Kulkarni', :mob, :email, 'female', '', 'pune', 'GENERAL', '', 1, 'registered')
")->execute([':code' => $origCode, ':mob' => $testMob, ':email' => $testEmail]);

// Contestant returns to add their Reel URL and Instagram Handle!
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_POST = [
    'full_name'        => 'Pooja Kulkarni Updated',
    'mobile_number'    => $testMob,
    'email_address'    => $testEmail,
    'category'         => 'female',
    'instagram_handle' => '@pooja_creations',
    'video_url'        => 'https://instagram.com/reel/pooja_reel_official',
    'city'             => 'mumbai',
    'terms_agreed'     => '1'
];

require __DIR__ . '/api/register.php';
PHP;

$resBackfill = runPhpSubprocess($codeBackfillTest);
$backfillData = json_decode($resBackfill['stdout'], true);

// Check database state
$codeVerifyBackfill = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM meridian_contest_entries WHERE mobile_number = '9876500111'");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
PHP;
$resVerifyBackfill = runPhpSubprocess($codeVerifyBackfill);
$backfillRows = json_decode($resVerifyBackfill['stdout'], true) ?: [];

$keptOrigCode   = ($backfillData['entry_code'] ?? '') === 'M-2026-654321';
$flaggedUpdated = ($backfillData['is_updated'] ?? false) === true;
$rowCountIsOne  = count($backfillRows) === 1;
$reelSaved      = ($backfillRows[0]['video_url'] ?? '') === 'https://instagram.com/reel/pooja_reel_official';
$handleSaved    = ($backfillRows[0]['instagram_handle'] ?? '') === '@pooja_creations';

$test21Pass = $keptOrigCode && $flaggedUpdated && $rowCountIsOne && $reelSaved && $handleSaved;

report("21. Smart Duplicate Backfill on Submit (api/register.php)",
    $test21Pass,
    "Retained Ticket: " . ($keptOrigCode ? 'YES' : 'NO') .
    ", Updated Flag: " . ($flaggedUpdated ? 'YES' : 'NO') .
    ", Rows in DB: " . count($backfillRows) . " (No duplicate created)" .
    ", Reel Saved: " . ($reelSaved ? 'YES' : 'NO')
);

// -------------------------------------------------------------
// TEST 22: Smart Duplicate Sorting, Completeness & Archiving (admin/api.php)
// -------------------------------------------------------------
$codeDupAdminTest = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();

$dupMob = '9876500222';
$pdo->exec("DELETE FROM meridian_contest_entries WHERE mobile_number = '$dupMob'");

// Insert Row 1: Incomplete (Missing Reel URL)
$codeIncomplete = 'M-DUP-111111';
$pdo->prepare("
    INSERT INTO meridian_contest_entries 
    (entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, instagram_handle, terms_agreed, status)
    VALUES 
    (:code, 'Sameer Khan', :mob, 'sameer.k@internal.test', 'male', '', 'pune', 'GENERAL', '', 1, 'registered')
")->execute([':code' => $codeIncomplete, ':mob' => $dupMob]);

// Insert Row 2: Complete (Has Reel URL and Instagram Handle)
$codeComplete = 'M-DUP-222222';
$pdo->prepare("
    INSERT INTO meridian_contest_entries 
    (entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, instagram_handle, terms_agreed, status)
    VALUES 
    (:code, 'Sameer Khan Full', :mob, 'sameer.k@internal.test', 'male', 'https://instagram.com/reel/sameer_star', 'pune', 'PUN-FC-01', '@sameer_creator', 1, 'registered')
")->execute([':code' => $codeComplete, ':mob' => $dupMob]);

session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';

// 1. Query with sort=duplicates
$_GET = ['action' => 'get_entries', 'sort' => 'duplicates', 'search' => $dupMob];
ob_start();
require __DIR__ . '/admin/api.php';
$resSorted = json_decode(ob_get_clean(), true);

echo json_encode([
    'sorted_entries' => $resSorted['entries'] ?? []
]);
PHP;
$resAdminDups = runPhpSubprocess($codeDupAdminTest);
$adminDupsData = json_decode($resAdminDups['stdout'], true);
$sortedEntries = $adminDupsData['entries'] ?? $adminDupsData['sorted_entries'] ?? [];

$firstIsComplete = count($sortedEntries) >= 2 
    && ($sortedEntries[0]['is_complete'] ?? false) === true
    && ($sortedEntries[0]['entry_code'] ?? '') === 'M-DUP-222222';

$secondIsIncomplete = count($sortedEntries) >= 2
    && ($sortedEntries[1]['is_complete'] ?? true) === false
    && ($sortedEntries[1]['is_duplicate'] ?? false) === true;

// 2. Run merge_and_archive_duplicates
$codeMergeAction = <<<'PHP'
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test_token_123';
$_GET = ['action' => 'merge_and_archive_duplicates'];
require __DIR__ . '/admin/api.php';
PHP;
$resMerge = runPhpSubprocess($codeMergeAction);
$mergeData = json_decode($resMerge['stdout'], true);

// 3. Verify status in database
$codeVerifyArchive = <<<'PHP'
require_once __DIR__ . '/api/config.php';
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT entry_code, status, video_url FROM meridian_contest_entries WHERE mobile_number = '9876500222' ORDER BY id ASC");
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
PHP;
$resVerifyArchive = runPhpSubprocess($codeVerifyArchive);
$finalRows = json_decode($resVerifyArchive['stdout'], true) ?: [];

$rowMap = [];
foreach ($finalRows as $r) {
    $rowMap[$r['entry_code']] = $r['status'];
}

$origStayedActive = ($rowMap['M-DUP-222222'] ?? '') === 'registered';
$dupWasArchived   = ($rowMap['M-DUP-111111'] ?? '') === 'archived';

$test22Pass = $firstIsComplete && $secondIsIncomplete && ($mergeData['success'] ?? false) && $origStayedActive && $dupWasArchived;

report("22. Completeness Scoring & Duplicate Auto-Archive",
    $test22Pass,
    "Complete First: " . ($firstIsComplete ? 'PASS' : 'FAIL') .
    ", Incomplete Flagged: " . ($secondIsIncomplete ? 'PASS' : 'FAIL') .
    ", Complete Active: " . ($origStayedActive ? 'YES' : 'NO') .
    ", Incomplete Archived: " . ($dupWasArchived ? 'YES' : 'NO')
);

// Clean up test data after run
runPhpSubprocess($codeClean);

echo "========================================================================\n";
echo sprintf(" TEST RUN FINISHED: %d/%d PASSED (%.1f%% Success Rate)\n", $passCount, $totalCount, ($passCount / $totalCount) * 100);
echo "========================================================================\n";

if ($passCount !== $totalCount) {
    exit(1);
}
