<?php
/**
 * Meridian Ice Cream Brand Ambassador Contest
 * Secure Superadmin Control API Endpoint
 * 
 * Security & Data Invariants:
 * - Direct database operations only (No JSON backup failsafe overwriting deleted entries)
 * - Single source of truth: SQL database table `meridian_contest_entries`
 * - Session-based authentication with HttpOnly and SameSite cookies
 * - Anti-brute-force rate limiting on login (5 failed attempts / 5 mins)
 * - Strict CSRF verification on all state-mutating POST requests
 * - Complete PDO prepared statements (immune to SQL injection)
 * - Safe CSV streaming with Formula Injection (DDE) sanitization
 * - Scheme validation on external URLs
 */

// Configure secure session cookies before starting
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

require_once __DIR__ . '/../api/config.php';

// Check if current user is an authenticated administrator
function isAdminAuthenticated()
{
    if (!empty($_SESSION['meridian_admin']) && $_SESSION['meridian_admin'] === true) {
        return true;
    }

    // Support Bearer Token authentication via ADMIN_SECRET_KEY for headless tools
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return hash_equals(ADMIN_SECRET_KEY, trim($matches[1]));
    }

    $xKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
    if (!empty($xKey)) {
        return hash_equals(ADMIN_SECRET_KEY, trim($xKey));
    }

    return false;
}

// Generate CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Extract requested action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// -------------------------------------------------------------
// PUBLIC ACTION: CHECK AUTHENTICATION
// -------------------------------------------------------------
if ($action === 'check_auth') {
    $isAuth = isAdminAuthenticated();
    echo json_encode([
        'authenticated' => $isAuth,
        'username'      => $isAuth ? ($_SESSION['admin_user'] ?? 'Superadmin') : null,
        'full_name'     => $isAuth ? ($_SESSION['admin_name'] ?? 'Super Administrator') : null,
        'role'          => $isAuth ? ($_SESSION['admin_role'] ?? 'superadmin') : null,
        'csrf_token'    => $isAuth ? $_SESSION['csrf_token'] : null
    ]);
    exit;
}

// -------------------------------------------------------------
// PUBLIC ACTION: LOGIN (Database-Driven with Rate Limiting)
// -------------------------------------------------------------
if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'POST method required']);
        exit;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Check Brute Force Throttle (Max 5 attempts / 5 mins)
    if (isLoginThrottled($clientIp)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed login attempts. Please wait 5 minutes before trying again.'
        ]);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);

    $username = trim($_POST['username'] ?? $json['username'] ?? '');
    $password = trim($_POST['password'] ?? $json['password'] ?? '');

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
        exit;
    }

    $pdo = getDbConnection();
    $authenticatedUser = null;

    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `meridian_admin_users` WHERE `username` = :u AND `status` = 'active' LIMIT 1");
            $stmt->execute([':u' => $username]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRow && password_verify($password, $userRow['password_hash'])) {
                // Rehash password if algorithm/cost parameters were updated
                if (password_needs_rehash($userRow['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upStmt = $pdo->prepare("UPDATE `meridian_admin_users` SET `password_hash` = :nh WHERE `id` = :id");
                    $upStmt->execute([':nh' => $newHash, ':id' => $userRow['id']]);
                }
                $authenticatedUser = $userRow;

                // Record login timestamp and IP in database
                $metaStmt = $pdo->prepare("UPDATE `meridian_admin_users` SET `last_login_at` = CURRENT_TIMESTAMP, `last_login_ip` = :ip WHERE `id` = :id");
                $metaStmt->execute([':ip' => $clientIp, ':id' => $userRow['id']]);
            }
        } catch (PDOException $e) {
            error_log("Database authentication query error: " . $e->getMessage());
        }
    }

    if ($authenticatedUser) {
        clearLoginThrottle($clientIp);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['meridian_admin'] = true;
        $_SESSION['admin_id']       = (int)$authenticatedUser['id'];
        $_SESSION['admin_user']     = $authenticatedUser['username'];
        $_SESSION['admin_name']     = $authenticatedUser['full_name'];
        $_SESSION['admin_role']     = $authenticatedUser['role'];
        $_SESSION['csrf_token']     = bin2hex(random_bytes(32));

        echo json_encode([
            'success'    => true,
            'message'    => 'Login successful. Welcome Superadmin!',
            'username'   => $authenticatedUser['username'],
            'full_name'  => $authenticatedUser['full_name'],
            'role'       => $authenticatedUser['role'],
            'csrf_token' => $_SESSION['csrf_token']
        ]);
        exit;
    }

    // Record failed attempt
    recordFailedLogin($clientIp);

    // Delay to mitigate timing attacks
    usleep(250000); // 250ms

    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid username or password. Please try again.'
    ]);
    exit;
}

// -------------------------------------------------------------
// ACTION: LOGOUT
// -------------------------------------------------------------
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    exit;
}

// =========================================================================
// ALL ACTIONS BELOW REQUIRE SUPERADMIN AUTHENTICATION
// =========================================================================

if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized',
        'message' => 'Active Superadmin session required.'
    ]);
    exit;
}

// CSRF check on mutating requests (POST)
// If authenticated via session, CSRF token is strictly mandatory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['meridian_admin'])) {
    $providedCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($providedCsrf)) {
        $raw = file_get_contents('php://input');
        $parsed = json_decode($raw, true);
        $providedCsrf = $parsed['csrf_token'] ?? '';
    }

    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$providedCsrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token. Please refresh the page.']);
        exit;
    }
}

$pdo = getDbConnection();

// -------------------------------------------------------------
// ACTION: GET STATS (From Live Database)
// -------------------------------------------------------------
if ($action === 'get_stats') {
    $stats = [
        'total'           => 0,
        'today'           => 0,
        'shortlisted'     => 0,
        'winners'         => 0,
        'with_reel'       => 0,
        'without_reel'    => 0,
        'categories'      => [
            'male'              => 0,
            'female'            => 0,
            'kids'              => 0,
            'group_family'      => 0,
            'voice_of_meridian' => 0,
            'child'             => 0,
            'group'             => 0
        ],
        'statuses'        => ['registered' => 0, 'under_review' => 0, 'shortlisted' => 0, 'winner' => 0, 'rejected' => 0],
        'cities'          => [],
        'active_parlours' => 0,
        'db_driver'       => ($pdo ? $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : 'disconnected')
    ];

    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM meridian_contest_entries GROUP BY status");
            while ($row = $stmt->fetch()) {
                $st = $row['status'];
                $cnt = (int)$row['cnt'];
                $stats['total'] += $cnt;
                if (isset($stats['statuses'][$st])) {
                    $stats['statuses'][$st] = $cnt;
                }
            }

            $stats['shortlisted'] = $stats['statuses']['shortlisted'] ?? 0;
            $stats['winners'] = $stats['statuses']['winner'] ?? 0;

            $stmt = $pdo->query("SELECT category, COUNT(*) as cnt FROM meridian_contest_entries GROUP BY category");
            while ($row = $stmt->fetch()) {
                $cat = $row['category'];
                $cCnt = (int)$row['cnt'];
                if (isset($stats['categories'][$cat])) {
                    $stats['categories'][$cat] += $cCnt;
                }
                // Group legacy counts into canonical keys
                if ($cat === 'child') {
                    $stats['categories']['kids'] += $cCnt;
                } elseif ($cat === 'group' || $cat === 'family') {
                    $stats['categories']['group_family'] += $cCnt;
                }
            }

            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM meridian_contest_entries WHERE DATE(created_at) = CURRENT_DATE");
            $stats['today'] = (int)($stmt->fetch()['cnt'] ?? 0);

            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM meridian_contest_entries WHERE video_url IS NOT NULL AND TRIM(video_url) != '' AND video_url != '-'");
            $stats['with_reel'] = (int)($stmt->fetch()['cnt'] ?? 0);
            $stats['without_reel'] = max(0, $stats['total'] - $stats['with_reel']);

            $stmt = $pdo->query("SELECT city, COUNT(*) as cnt FROM meridian_contest_entries GROUP BY city ORDER BY cnt DESC LIMIT 10");
            $stats['cities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->query("SELECT COUNT(DISTINCT store_code) as cnt FROM meridian_contest_entries WHERE store_code != 'GENERAL'");
            $stats['active_parlours'] = (int)($stmt->fetch()['cnt'] ?? 0);

        } catch (PDOException $e) {
            error_log("Stats query error: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'stats' => $stats]);
    exit;
}

// -------------------------------------------------------------
// ACTION: GET ENTRIES (Filterable & Paginated, Database Only)
// -------------------------------------------------------------
if ($action === 'get_entries') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status   = trim($_GET['status'] ?? '');
    $city     = trim($_GET['city'] ?? '');
    $sort     = trim($_GET['sort'] ?? 'newest');
    $hasReel  = trim($_GET['has_reel'] ?? $_GET['reel'] ?? '');

    $entries = [];
    $total = 0;

    if ($pdo) {
        try {
            $whereClauses = [];
            $params = [];

            if (!empty($search)) {
                $whereClauses[] = "(full_name LIKE :s OR entry_code LIKE :s OR mobile_number LIKE :s OR email_address LIKE :s OR store_code LIKE :s OR instagram_handle LIKE :s)";
                $params[':s'] = '%' . $search . '%';
            }

            if (!empty($category)) {
                if ($category === 'Uploaded Reels' || $category === 'uploaded_reels' || $category === 'reels') {
                    $whereClauses[] = "(video_url IS NOT NULL AND TRIM(video_url) != '' AND video_url != '-')";
                } elseif ($category === 'kids' || $category === 'child') {
                    $whereClauses[] = "(category = 'kids' OR category = 'child')";
                } elseif ($category === 'group_family' || $category === 'group' || $category === 'family') {
                    $whereClauses[] = "(category = 'group_family' OR category = 'group' OR category = 'family')";
                } elseif (in_array($category, ['male', 'female', 'voice_of_meridian'], true)) {
                    $whereClauses[] = "category = :cat";
                    $params[':cat'] = $category;
                }
            }

            if (!empty($hasReel)) {
                if ($hasReel === 'yes' || $hasReel === '1' || $hasReel === 'has_reel') {
                    $whereClauses[] = "(video_url IS NOT NULL AND TRIM(video_url) != '' AND video_url != '-')";
                } elseif ($hasReel === 'no' || $hasReel === '0' || $hasReel === 'no_reel') {
                    $whereClauses[] = "(video_url IS NULL OR TRIM(video_url) = '' OR video_url = '-')";
                }
            }

            if (!empty($status) && in_array($status, ['registered', 'under_review', 'shortlisted', 'winner', 'rejected'], true)) {
                $whereClauses[] = "status = :st";
                $params[':st'] = $status;
            }

            if (!empty($city)) {
                $whereClauses[] = "city = :city";
                $params[':city'] = $city;
            }

            $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

            $orderSql = ' ORDER BY id DESC ';
            if ($sort === 'oldest') $orderSql = ' ORDER BY id ASC ';
            if ($sort === 'name_asc') $orderSql = ' ORDER BY full_name ASC ';
            if ($sort === 'name_desc') $orderSql = ' ORDER BY full_name DESC ';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM meridian_contest_entries" . $whereSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $fetchSql = "SELECT * FROM meridian_contest_entries" . $whereSql . $orderSql . "LIMIT :limit OFFSET :offset";
            $fetchStmt = $pdo->prepare($fetchSql);
            foreach ($params as $k => $v) {
                $fetchStmt->bindValue($k, $v);
            }
            $fetchStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $fetchStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $fetchStmt->execute();

            $entries = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get entries error: " . $e->getMessage());
        }
    }

    echo json_encode([
        'success'     => true,
        'entries'     => $entries,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => ($total > 0 ? ceil($total / $limit) : 1)
    ]);
    exit;
}

// -------------------------------------------------------------
// ACTION: GET SINGLE ENTRY
// -------------------------------------------------------------
if ($action === 'get_entry') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$pdo || $id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM meridian_contest_entries WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($entry) {
        echo json_encode(['success' => true, 'entry' => $entry]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Entry not found']);
    }
    exit;
}

// -------------------------------------------------------------
// ACTION: UPDATE STATUS
// -------------------------------------------------------------
if ($action === 'update_status') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    $status = strtolower(trim($data['status'] ?? ''));

    $validStatuses = ['registered', 'under_review', 'shortlisted', 'winner', 'rejected'];
    if (!$pdo || $id <= 0 || !in_array($status, $validStatuses, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters for status update']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE meridian_contest_entries SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $id]);

    echo json_encode([
        'success' => true,
        'message' => "Entry #{$id} status updated to " . ucfirst(str_replace('_', ' ', $status)),
        'status'  => $status
    ]);
    exit;
}

// -------------------------------------------------------------
// ACTION: UPDATE ENTRY DETAILS
// -------------------------------------------------------------
if ($action === 'update_entry') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    if (!$pdo || $id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        exit;
    }

    $fullName    = mb_substr(trim($data['full_name'] ?? ''), 0, 150);
    $mobile      = preg_replace('/[^0-9]/', '', $data['mobile_number'] ?? '');
    $email       = mb_substr(trim($data['email_address'] ?? ''), 0, 150);
    $category    = strtolower(trim($data['category'] ?? 'male'));
    if ($category === 'child') $category = 'kids';
    if ($category === 'group' || $category === 'family' || $category === 'group/family') $category = 'group_family';
    if ($category === 'voice' || $category === 'voice-of-meridian') $category = 'voice_of_meridian';
    if (!in_array($category, ['male', 'female', 'kids', 'group_family', 'voice_of_meridian'], true)) {
        $category = 'male';
    }
    $videoUrl    = mb_substr(trim($data['video_url'] ?? ''), 0, 500);
    $city        = mb_substr(trim($data['city'] ?? 'pune'), 0, 80);
    $storeCode   = mb_substr(trim($data['store_code'] ?? 'GENERAL'), 0, 50);
    $instagram   = mb_substr(trim($data['instagram_handle'] ?? ''), 0, 100);
    $status      = strtolower(trim($data['status'] ?? 'registered'));

    if (empty($fullName) || strlen($mobile) !== 10 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please fill in a valid name, 10-digit mobile, and email.']);
        exit;
    }

    if (!empty($videoUrl)) {
        $scheme = parse_url($videoUrl, PHP_URL_SCHEME);
        if (!in_array(strtolower((string)$scheme), ['http', 'https'], true) || !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Video URL must be a valid http:// or https:// address.']);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        UPDATE meridian_contest_entries 
        SET full_name = :full_name, mobile_number = :mobile, email_address = :email,
            category = :category, video_url = :video_url, city = :city,
            store_code = :store_code, instagram_handle = :instagram, status = :status
        WHERE id = :id
    ");

    $stmt->execute([
        ':full_name'  => $fullName,
        ':mobile'     => $mobile,
        ':email'      => $email,
        ':category'   => $category,
        ':video_url'  => $videoUrl,
        ':city'       => $city,
        ':store_code' => $storeCode,
        ':instagram'  => $instagram,
        ':status'     => $status,
        ':id'         => $id
    ]);

    echo json_encode(['success' => true, 'message' => 'Contestant details updated successfully.']);
    exit;
}

// -------------------------------------------------------------
// ACTION: DELETE ENTRY (Direct Database Deletion)
// -------------------------------------------------------------
if ($action === 'delete_entry') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $id = (int)($data['id'] ?? 0);
    if (!$pdo || $id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM meridian_contest_entries WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => "Entry #{$id} permanently deleted from database."]);
    exit;
}

// -------------------------------------------------------------
// ACTION: BULK ACTIONS (Bulk Status Change or Bulk Delete)
// -------------------------------------------------------------
if ($action === 'bulk_action') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $ids = $data['ids'] ?? [];
    $subAction = $data['sub_action'] ?? '';

    if (!$pdo || empty($ids) || !is_array($ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please select at least one record.']);
        exit;
    }

    $intIds = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($intIds), '?'));

    if ($subAction === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM meridian_contest_entries WHERE id IN ($placeholders)");
        $stmt->execute($intIds);
        echo json_encode(['success' => true, 'message' => count($intIds) . ' entries permanently deleted from database.']);
        exit;
    }

    if ($subAction === 'status_update') {
        $status = strtolower(trim($data['status'] ?? ''));
        if (!in_array($status, ['registered', 'under_review', 'shortlisted', 'winner', 'rejected'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status selected.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE meridian_contest_entries SET status = ? WHERE id IN ($placeholders)");
        $params = array_merge([$status], $intIds);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => count($intIds) . ' entries marked as ' . ucfirst(str_replace('_', ' ', $status)) . '.']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown bulk action.']);
    exit;
}

// -------------------------------------------------------------
// ACTION: CREATE MANUAL ENTRY
// -------------------------------------------------------------
if ($action === 'create_entry') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $fullName    = mb_substr(trim($data['full_name'] ?? ''), 0, 150);
    $mobile      = preg_replace('/[^0-9]/', '', $data['mobile_number'] ?? '');
    $email       = mb_substr(trim($data['email_address'] ?? ''), 0, 150);
    $category    = strtolower(trim($data['category'] ?? 'male'));
    if ($category === 'child') $category = 'kids';
    if ($category === 'group' || $category === 'family' || $category === 'group/family') $category = 'group_family';
    if ($category === 'voice' || $category === 'voice-of-meridian') $category = 'voice_of_meridian';
    if (!in_array($category, ['male', 'female', 'kids', 'group_family', 'voice_of_meridian'], true)) {
        $category = 'male';
    }
    $videoUrl    = mb_substr(trim($data['video_url'] ?? ''), 0, 500);
    $city        = mb_substr(trim($data['city'] ?? 'pune'), 0, 80);
    $storeCode   = mb_substr(trim($data['store_code'] ?? 'GENERAL'), 0, 50);
    $instagram   = mb_substr(trim($data['instagram_handle'] ?? ''), 0, 100);
    $status      = strtolower(trim($data['status'] ?? 'registered'));

    if (empty($fullName) || strlen($mobile) !== 10 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please provide full name, 10-digit mobile, and valid email.']);
        exit;
    }

    if (!empty($videoUrl)) {
        $scheme = parse_url($videoUrl, PHP_URL_SCHEME);
        if (!in_array(strtolower((string)$scheme), ['http', 'https'], true) || !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Video URL must be a valid http:// or https:// address.']);
            exit;
        }
    }

    $entryCode = 'M-2026-' . random_int(100000, 999999);

    if ($pdo) {
        $stmt = $pdo->prepare("
            INSERT INTO meridian_contest_entries 
            (entry_code, full_name, mobile_number, email_address, category, video_url, city, store_code, instagram_handle, status, terms_agreed, ip_address, user_agent)
            VALUES 
            (:code, :name, :mobile, :email, :category, :url, :city, :store, :insta, :status, 1, 'SUPERADMIN', 'Admin Panel')
        ");
        $stmt->execute([
            ':code'     => $entryCode,
            ':name'     => $fullName,
            ':mobile'   => $mobile,
            ':email'    => $email,
            ':category' => $category,
            ':url'      => $videoUrl,
            ':city'     => $city,
            ':store'    => $storeCode,
            ':insta'    => $instagram,
            ':status'   => $status
        ]);
        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success'    => true,
            'message'    => "Contestant {$fullName} registered manually with code {$entryCode}.",
            'entry_id'   => $newId,
            'entry_code' => $entryCode
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit;
}

// -------------------------------------------------------------
// ACTION: EXPORT CSV (Authenticated via Session)
// -------------------------------------------------------------
if ($action === 'export_csv') {
    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status   = trim($_GET['status'] ?? '');
    $city     = trim($_GET['city'] ?? '');
    $hasReel  = trim($_GET['has_reel'] ?? $_GET['reel'] ?? '');

    $entries = [];

    if ($pdo) {
        try {
            $whereClauses = [];
            $params = [];

            if (!empty($search)) {
                $whereClauses[] = "(full_name LIKE :s OR entry_code LIKE :s OR mobile_number LIKE :s OR email_address LIKE :s OR store_code LIKE :s)";
                $params[':s'] = '%' . $search . '%';
            }
            if (!empty($category)) {
                if ($category === 'Uploaded Reels' || $category === 'uploaded_reels' || $category === 'reels') {
                    $whereClauses[] = "(video_url IS NOT NULL AND TRIM(video_url) != '' AND video_url != '-')";
                } elseif ($category === 'kids' || $category === 'child') {
                    $whereClauses[] = "(category = 'kids' OR category = 'child')";
                } elseif ($category === 'group_family' || $category === 'group' || $category === 'family') {
                    $whereClauses[] = "(category = 'group_family' OR category = 'group' OR category = 'family')";
                } elseif (in_array($category, ['male', 'female', 'voice_of_meridian'], true)) {
                    $whereClauses[] = "category = :cat";
                    $params[':cat'] = $category;
                }
            }
            if (!empty($hasReel)) {
                if ($hasReel === 'yes' || $hasReel === '1' || $hasReel === 'has_reel') {
                    $whereClauses[] = "(video_url IS NOT NULL AND TRIM(video_url) != '' AND video_url != '-')";
                } elseif ($hasReel === 'no' || $hasReel === '0' || $hasReel === 'no_reel') {
                    $whereClauses[] = "(video_url IS NULL OR TRIM(video_url) = '' OR video_url = '-')";
                }
            }
            if (!empty($status) && in_array($status, ['registered', 'under_review', 'shortlisted', 'winner', 'rejected'], true)) {
                $whereClauses[] = "status = :st";
                $params[':st'] = $status;
            }
            if (!empty($city)) {
                $whereClauses[] = "city = :city";
                $params[':city'] = $city;
            }

            $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';
            $stmt = $pdo->prepare("SELECT * FROM meridian_contest_entries" . $whereSql . " ORDER BY id DESC");
            $stmt->execute($params);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $entries = [];
        }
    }

    $filename = 'meridian_contest_export_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    fputcsv($output, ['ID', 'Ticket Code', 'Full Name', 'Mobile Number', 'Email Address', 'Category', 'Video URL', 'City', 'Store Code', 'Instagram Handle', 'Status', 'Registered At']);

    foreach ($entries as $r) {
        fputcsv($output, [
            sanitizeCsvCell($r['id'] ?? '-'),
            sanitizeCsvCell($r['entry_code'] ?? '-'),
            sanitizeCsvCell($r['full_name'] ?? '-'),
            sanitizeCsvCell($r['mobile_number'] ?? '-'),
            sanitizeCsvCell($r['email_address'] ?? '-'),
            sanitizeCsvCell($r['category'] ?? '-'),
            sanitizeCsvCell($r['video_url'] ?? '-'),
            sanitizeCsvCell($r['city'] ?? '-'),
            sanitizeCsvCell($r['store_code'] ?? '-'),
            sanitizeCsvCell($r['instagram_handle'] ?? '-'),
            sanitizeCsvCell($r['status'] ?? 'registered'),
            sanitizeCsvCell($r['created_at'] ?? '-')
        ]);
    }

    fclose($output);
    exit;
}

// -------------------------------------------------------------
// ACTION: GET CURRENT ADMIN PROFILE (From Database)
// -------------------------------------------------------------
if ($action === 'get_admin_profile') {
    $userId = (int)($_SESSION['admin_id'] ?? 0);
    $username = $_SESSION['admin_user'] ?? '';

    $profile = null;
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id, username, full_name, email, role, status, last_login_at, last_login_ip, created_at FROM meridian_admin_users WHERE id = :id OR username = :u LIMIT 1");
        $stmt->execute([':id' => $userId, ':u' => $username]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Admin profile not found in database.']);
        exit;
    }

    echo json_encode(['success' => true, 'profile' => $profile]);
    exit;
}

// -------------------------------------------------------------
// ACTION: CHANGE ADMIN PASSWORD (In Database)
// -------------------------------------------------------------
if ($action === 'change_password') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $currentPass = trim($data['current_password'] ?? '');
    $newPass     = trim($data['new_password'] ?? '');
    $confirmPass = trim($data['confirm_password'] ?? '');

    if (empty($currentPass) || empty($newPass)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password and new password are required.']);
        exit;
    }

    if (strlen($newPass) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
        exit;
    }

    if ($newPass !== $confirmPass) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
        exit;
    }

    $userId = (int)($_SESSION['admin_id'] ?? 0);
    $username = $_SESSION['admin_user'] ?? '';

    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, password_hash FROM meridian_admin_users WHERE id = :id OR username = :u LIMIT 1");
    $stmt->execute([':id' => $userId, ':u' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($currentPass, $admin['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $upStmt = $pdo->prepare("UPDATE meridian_admin_users SET password_hash = :h, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $upStmt->execute([':h' => $newHash, ':id' => $admin['id']]);

    echo json_encode(['success' => true, 'message' => 'Superadmin password successfully updated in database.']);
    exit;
}

// -------------------------------------------------------------
// ACTION: LIST ALL ADMIN USERS (In Database)
// -------------------------------------------------------------
if ($action === 'get_admin_users') {
    $admins = [];
    if ($pdo) {
        $stmt = $pdo->query("SELECT id, username, full_name, email, role, status, last_login_at, last_login_ip, created_at FROM meridian_admin_users ORDER BY id ASC");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success' => true, 'admins' => $admins]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
exit;

// =========================================================================
// HELPER FUNCTIONS
// =========================================================================

function sanitizeCsvCell($value)
{
    if ($value === null || $value === '') {
        return '-';
    }
    $str = (string)$value;
    $firstChar = substr($str, 0, 1);
    if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r", "\n", '%'], true)) {
        return "'" . $str;
    }
    return $str;
}

function getThrottleFile($ip)
{
    $dir = sys_get_temp_dir() . '/meridian_admin_throttle';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir . '/' . md5($ip) . '.json';
}

function isLoginThrottled($ip)
{
    $file = getThrottleFile($ip);
    if (!file_exists($file)) {
        return false;
    }
    $attempts = json_decode(file_get_contents($file), true) ?: [];
    $now = time();
    $window = 300; // 5 minutes
    $recent = array_filter($attempts, fn($t) => ($now - $t) < $window);
    return count($recent) >= 5;
}

function recordFailedLogin($ip)
{
    $file = getThrottleFile($ip);
    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(file_get_contents($file), true) ?: [];
    }
    $now = time();
    $attempts[] = $now;
    @file_put_contents($file, json_encode(array_values($attempts)));
}

function clearLoginThrottle($ip)
{
    $file = getThrottleFile($ip);
    if (file_exists($file)) {
        @unlink($file);
    }
}
