<?php
// api.php
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

// API is same-origin by default. Configure an explicit allowlist only if a
// separate trusted frontend origin is required.
if ($allowedOrigins = getenv('WLC_ALLOWED_ORIGINS')) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_map('trim', explode(',', $allowedOrigins));
    if ($origin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, x-wlc-token');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }
}
header("Content-Type: application/json; charset=UTF-8");
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true, 'samesite' => 'Strict'
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Config
$envSecret = getenv('WLC_JWT_SECRET');
if (!$envSecret) {
    $secretFile = __DIR__ . '/.jwt_secret';
    if (file_exists($secretFile)) {
        $envSecret = trim(file_get_contents($secretFile));
    } else {
        $envSecret = bin2hex(random_bytes(32));
        file_put_contents($secretFile, $envSecret);
    }
}
define('JWT_SECRET', $envSecret);
$dbPath = __DIR__ . '/wlc.db';

// DB Connection
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode = WAL;');
    $db->exec('PRAGMA busy_timeout = 5000;');
    $db->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// JWT Helpers
function jwt_encode($payload, $secret, $expire_hours = 24) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload['exp'] = time() + ($expire_hours * 3600);
    $payload_json = json_encode($payload);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload_json));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function jwt_decode($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    
    list($header64, $payload64, $signature64) = $parts;
    
    $header = json_decode(base64_decode(strtr($header64, '-_', '+/')), true);
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') return null;
    $signature = base64_decode(strtr($signature64, '-_', '+/'));
    if ($signature === false) return null;
    $expected_signature = hash_hmac('sha256', $header64 . "." . $payload64, $secret, true);
    
    if (!hash_equals($signature, $expected_signature)) {
        return null;
    }
    
    $payload = json_decode(base64_decode(strtr($payload64, '-_', '+/')), true);
    if (!is_array($payload) || !isset($payload['exp']) || !is_numeric($payload['exp']) || (int)$payload['exp'] < time()) {
        return null;
    }
    
    return $payload;
}

function getBearerToken() {
    $authHeader = null;
    
    // Check multiple potential locations for the Authorization header
    if (isset($_SERVER['HTTP_X_WLC_TOKEN'])) {
        return trim($_SERVER['HTTP_X_WLC_TOKEN']);
    }
    if (isset($_SERVER['Authorization'])) {
        $authHeader = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $authHeader = trim($requestHeaders['Authorization']);
        }
    }
    
    // Fallback to getallheaders() if available
    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $authHeader = trim($value);
                break;
            }
        }
    }

    if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return null;
}

function authenticateToken() {
    $token = getBearerToken();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Access token required']);
        exit;
    }
    $decoded = jwt_decode($token, JWT_SECRET);
    if (!$decoded) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }
    global $db;
    if (($decoded['role'] ?? '') === 'siswa') {
        $studentId = (int)($decoded['reference_id'] ?? $decoded['id'] ?? 0);
        $stmt = $db->prepare('SELECT id, nama FROM siswa WHERE id = ?');
        $stmt->execute([$studentId]);
        $account = $stmt->fetch();
        if (!$account) { http_response_code(401); echo json_encode(['error' => 'Account is no longer active']); exit; }
        $decoded['reference_id'] = (int)$account['id'];
    } else {
        $stmt = $db->prepare('SELECT id, username, role, token_version FROM users WHERE id = ?');
        $stmt->execute([(int)($decoded['id'] ?? 0)]);
        $account = $stmt->fetch();
        if (!$account || $account['role'] !== ($decoded['role'] ?? '') || $account['username'] !== ($decoded['username'] ?? '') ||
            (int)($account['token_version'] ?? 0) !== (int)($decoded['token_version'] ?? -1)) {
            http_response_code(401); echo json_encode(['error' => 'Account is no longer active']); exit;
        }
    }
    return $decoded;
}

function enforceRateLimit(PDO $db, string $scope, int $limit, int $windowSeconds): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash_hmac('sha256', $scope . '|' . $ip, JWT_SECRET);
    $now = time();
    if ($now % 60 === 0) $db->exec('DELETE FROM api_rate_limits WHERE window_start < ' . (int)($now - 86400));
    $stmt = $db->prepare("INSERT INTO api_rate_limits (rate_key, window_start, request_count) VALUES (?, ?, 1)
        ON CONFLICT(rate_key) DO UPDATE SET
        request_count = CASE WHEN window_start < ? THEN 1 ELSE request_count + 1 END,
        window_start = CASE WHEN window_start < ? THEN excluded.window_start ELSE window_start END");
    $stmt->execute([$key, $now, $now - $windowSeconds, $now - $windowSeconds]);
    $stmt = $db->prepare('SELECT request_count FROM api_rate_limits WHERE rate_key = ?');
    $stmt->execute([$key]);
    if ((int)$stmt->fetchColumn() > $limit) {
        http_response_code(429); echo json_encode(['error' => 'Terlalu banyak permintaan. Coba lagi nanti.']); exit;
    }
}

function observerCanAccessStudent(PDO $db, array $user, int $studentId): bool {
    if (($user['role'] ?? '') === 'owner' || ($user['role'] ?? '') === 'evaluator') return true;
    if (($user['role'] ?? '') !== 'asisten') return false;
    $stmt = $db->prepare('SELECT siswaIds FROM grup WHERE asistenId = ? OR asistenId = ?');
    $stmt->execute([(string)($user['username'] ?? ''), (string)($user['id'] ?? '')]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $ids) {
        $assigned = json_decode((string)$ids, true);
        if (is_array($assigned) && in_array($studentId, array_map('intval', $assigned), true)) return true;
    }
    return false;
}

// Seeding/Init helper functions
function seedBankSoal($db) {
    $soalPath = __DIR__ . '/bank_soal_wlc1.json';
    if (file_exists($soalPath)) {
        $soalData = json_decode(file_get_contents($soalPath), true);
        if ($soalData) {
            $db->exec("DELETE FROM bank_soal");
            $stmt = $db->prepare("INSERT INTO bank_soal (wlc, type, komponen, indikator, pertanyaan) VALUES (?, ?, ?, ?, ?)");
            foreach ($soalData as $s) {
                $stmt->execute([
                    $s['wlc'],
                    $s['type'],
                    $s['komponen'],
                    $s['indikator'] ?? '',
                    $s['pertanyaan']
                ]);
            }
        }
    }
}

function initDatabase($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        role TEXT,
        username TEXT UNIQUE,
        password TEXT
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS jadwal (
        id INTEGER PRIMARY KEY,
        sekolahId INTEGER,
        tanggal DATE,
        status TEXT DEFAULT 'pending',
        catatan TEXT
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS grup (
        id INTEGER PRIMARY KEY,
        nama TEXT,
        jadwalId INTEGER,
        asistenId TEXT,
        siswaIds TEXT,
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    try { $db->exec("ALTER TABLE grup ADD COLUMN nama TEXT"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS sekolah (
        id INTEGER PRIMARY KEY,
        nama TEXT,
        alamat TEXT,
        kota TEXT,
        status TEXT DEFAULT 'pending'
    )");
    
    try { $db->exec("ALTER TABLE sekolah ADD COLUMN status TEXT DEFAULT 'pending'"); } catch (Exception $e) {}

    $db->exec("CREATE TABLE IF NOT EXISTS kelas (
        id INTEGER PRIMARY KEY,
        nama TEXT,
        tingkat TEXT,
        sekolahId INTEGER
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS siswa (
        id INTEGER PRIMARY KEY,
        nama TEXT,
        nisn TEXT UNIQUE,
        sekolahId INTEGER,
        kelasId INTEGER
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS bank_soal (
        id INTEGER PRIMARY KEY,
        wlc INTEGER,
        type TEXT,
        komponen TEXT,
        indikator TEXT,
        pertanyaan TEXT,
        contoh TEXT
    )");

    try { $db->exec("ALTER TABLE bank_soal ADD COLUMN contoh TEXT"); } catch (Exception $e) {}
    try { $db->exec("ALTER TABLE bank_soal ADD COLUMN indikator TEXT"); } catch (Exception $e) {}
    
    $db->exec("CREATE TABLE IF NOT EXISTS observasi (
        id INTEGER PRIMARY KEY,
        siswaId INTEGER,
        soalId INTEGER,
        skor INTEGER,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        asistenId TEXT
    )");
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $row = $stmt->fetch();
    if ($row['count'] == 0) {
        // Validate required environment variables for default users
        $ownerPass = getenv('WLC_OWNER_PASSWORD');
        $evaluatorPass = getenv('WLC_EVALUATOR_PASSWORD');
        $asistenPass = getenv('WLC_ASISTEN_PASSWORD');
        if (!$ownerPass || strlen($ownerPass) < 8) {
            http_response_code(500);
            echo json_encode(['error' => 'Environment variable WLC_OWNER_PASSWORD missing or too short (min 8)']);
            exit;
        }
        if (!$evaluatorPass || strlen($evaluatorPass) < 8) {
            http_response_code(500);
            echo json_encode(['error' => 'Environment variable WLC_EVALUATOR_PASSWORD missing or too short (min 8)']);
            exit;
        }
        if (!$asistenPass || strlen($asistenPass) < 8) {
            http_response_code(500);
            echo json_encode(['error' => 'Environment variable WLC_ASISTEN_PASSWORD missing or too short (min 8)']);
            exit;
        }
        $insertUser = $db->prepare("INSERT OR IGNORE INTO users (role, username, password) VALUES (?, ?, ?)");
        $insertUser->execute(['owner', 'owner', password_hash($ownerPass, PASSWORD_BCRYPT, ['cost' => 10])]);
        $insertUser->execute(['evaluator', 'evaluator', password_hash($evaluatorPass, PASSWORD_BCRYPT, ['cost' => 10])]);
        $insertUser->execute(['asisten', 'asisten', password_hash($asistenPass, PASSWORD_BCRYPT, ['cost' => 10])]);
    }
    
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        key TEXT UNIQUE,
        value TEXT
    )");

    $stmt = $db->query("SELECT COUNT(*) as count FROM settings");
    $row = $stmt->fetch();
    if ($row['count'] == 0) {
        $db->exec("INSERT INTO settings (key, value) VALUES ('app_name', 'WLC App')");
        $db->exec("INSERT INTO settings (key, value) VALUES ('app_logo', '')");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS parent_reflections (
        id INTEGER PRIMARY KEY,
        siswaId INTEGER,
        parentName TEXT,
        kesiapan TEXT,
        fokus TEXT,
        kemandirian TEXT,
        ketekunan TEXT,
        emosional TEXT,
        minat TEXT,
        catatan TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS kegiatan_wlc (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        siswaId INTEGER,
        wlc_tipe INTEGER,
        tanggal TEXT,
        org_name TEXT,
        kesiapan TEXT,
        fokus TEXT,
        respons TEXT,
        kemandirian TEXT,
        ketekunan TEXT,
        emosional TEXT,
        minat TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    try { $db->exec('ALTER TABLE users ADD COLUMN token_version INTEGER NOT NULL DEFAULT 0'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN email TEXT UNIQUE'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN otp_hash TEXT'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN otp_expires INTEGER'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN otp_attempts INTEGER DEFAULT 0'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE users ADD COLUMN otp_last_request INTEGER DEFAULT 0'); } catch (Exception $e) {}
    try { $db->exec('ALTER TABLE kegiatan_wlc ADD COLUMN share_token TEXT'); } catch (Exception $e) {}
    // Backfill unguessable share keys for certificates that predate this migration.
    $db->exec("UPDATE kegiatan_wlc SET share_token = lower(hex(randomblob(32))) WHERE share_token IS NULL OR share_token = ''");
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_kegiatan_share_token ON kegiatan_wlc(share_token)');
    $db->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (
        rate_key TEXT PRIMARY KEY, window_start INTEGER NOT NULL, request_count INTEGER NOT NULL
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_siswa_sekolah ON siswa(sekolahId)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_siswa_kelas ON siswa(kelasId)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_observasi_siswa ON observasi(siswaId)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_observasi_timestamp ON observasi(timestamp)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_parent_reflections_siswa ON parent_reflections(siswaId)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_kegiatan_wlc_siswa ON kegiatan_wlc(siswaId)");
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM sekolah");
    if ($stmt->fetch()['count'] == 0) {
        $db->exec("INSERT OR IGNORE INTO sekolah (id, nama, alamat, kota, status) VALUES 
          (1, 'SD Wahidin Sudirohusodo 1', 'Jl Merdeka No 10', 'Jakarta', 'acc'),
          (2, 'SMP Negeri 5 Jakarta', 'Jl Gatot Subroto', 'Jakarta', 'pending'),
          (3, 'SMA Tunas Bangsa', 'Jl Sudirman', 'Bandung', 'acc')");
    }
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM jadwal");
    if ($stmt->fetch()['count'] == 0) {
        $db->exec("INSERT OR IGNORE INTO jadwal (sekolahId, tanggal, status) VALUES 
          (1, '2024-10-20', 'confirmed'),
          (2, '2024-10-25', 'pending')");
    }
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM grup");
    if ($stmt->fetch()['count'] == 0) {
        $db->exec("INSERT OR IGNORE INTO grup (nama, jadwalId, asistenId, siswaIds) VALUES 
          ('Grup A - Kelas 4A', 1, 'asisten1', '[1,2,3,4,5,6,7]'),
          ('Grup B - Kelas 4B', 1, 'asisten2', '[8,9,10]')");
    }
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM bank_soal");
    if ($stmt->fetch()['count'] == 0) {
        seedBankSoal($db);
    }
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM siswa");
    if ($stmt->fetch()['count'] == 0) {
        $sampleSiswa = [
          ['nama' => 'Budi Santoso', 'nisn' => '12345', 'sekolahId' => 1, 'kelasId' => 1],
          ['nama' => 'Siti Aminah', 'nisn' => '12346', 'sekolahId' => 1, 'kelasId' => 1],
          ['nama' => 'Ahmad Fauzi', 'nisn' => '12347', 'sekolahId' => 1, 'kelasId' => 1],
          ['nama' => 'Dewi Sartika', 'nisn' => '12348', 'sekolahId' => 1, 'kelasId' => 1],
          ['nama' => 'Rudi Hartono', 'nisn' => '12349', 'sekolahId' => 1, 'kelasId' => 1],
          ['nama' => 'Anton Suryo', 'nisn' => '12350', 'sekolahId' => 1, 'kelasId' => 1],
          ['nama' => 'Lina Sari', 'nisn' => '12351', 'sekolahId' => 2, 'kelasId' => 2]
        ];
        $insert = $db->prepare("INSERT INTO siswa (nama, nisn, sekolahId, kelasId) VALUES (?, ?, ?, ?)");
        foreach ($sampleSiswa as $s) {
            $insert->execute([$s['nama'], $s['nisn'], $s['sekolahId'], $s['kelasId']]);
        }
    }
}

// Run DB Auto-init
initDatabase($db);

// Helper function to get all headers (for compatibility across environments)
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// Input Sanitization
function sanitizeString($str) {
    if (!is_string($str)) return $str;
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function sanitizeObject(&$obj) {
    if (!is_array($obj)) return;
    foreach ($obj as $key => &$value) {
        if ($key === 'app_logo' || $key === 'password' || $key === 'siswaIds') {
            continue;
        }
        if (is_string($value)) {
            $value = sanitizeString($value);
        } elseif (is_array($value)) {
            sanitizeObject($value);
        }
    }
}

// Request Data Parsers
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($uri, '/api.php') === 0) {
    $uri = substr($uri, 8);
    if ($uri === '') $uri = '/';
}
$method = $_SERVER['REQUEST_METHOD'];

$queryParams = [];
if (isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $queryParams);
}
sanitizeObject($queryParams);

$inputBody = json_decode(file_get_contents('php://input'), true) ?? [];
sanitizeObject($inputBody);

// ROUTING

if (strpos($uri, '/api/v2/student') === 0) {
    require_once 'api_v2_student.php';
    exit;
}
if (strpos($uri, '/api/v2/observer') === 0) {
    require_once 'api_v2_observer.php';
    exit;
}

// 1. Settings Endpoints
if ($uri === '/api/settings') {
    if ($method === 'GET') {
        $stmt = $db->query('SELECT * FROM settings');
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['key']] = $r['value'];
        }
        echo json_encode($settings);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
            foreach ($inputBody as $key => $val) {
                $stmt->execute([$key, $val]);
            }
            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
}
}

// 1.5 Restore Endpoint
if ($uri === '/api/restore' && $method === 'POST') {
    $user = authenticateToken();
    if ($user['role'] !== 'owner') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    if (!isset($_FILES['backup_file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No backup file provided']);
        exit;
    }
    
    $uploadedFile = $_FILES['backup_file']['tmp_name'];
    $dbFile = __DIR__ . '/wlc.db';
    
    if (is_uploaded_file($uploadedFile)) {
        // Optional backup
        copy($dbFile, $dbFile . '.bak_' . time());
        
        if (move_uploaded_file($uploadedFile, $dbFile)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to overwrite database file']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file upload']);
    }
    exit;
}

    
if ($uri === '/api/wlc-instruments' && $method === 'GET') {
    authenticateToken();
    $audience = $_GET['audience'] ?? 'KIDS'; // 'KIDS' or 'TEEN'
    $targetGrade = $_GET['target_grade'] ?? 'TK';
    
    // Resolve the current instrument for the selected WLC pathway and grade.
    if ($audience === 'KIDS') {
        $stmt = $db->prepare("SELECT id, version, status, audience, target_grade, methodology FROM wlc_instruments WHERE audience = ? AND target_grade = ? AND UPPER(status) IN ('ACTIVE', 'PROVISIONAL') ORDER BY version DESC, id DESC LIMIT 1");
        $stmt->execute([$audience, $targetGrade]);
    } else {
        $stmt = $db->prepare("SELECT id, version, status, audience, target_grade, methodology FROM wlc_instruments WHERE audience = ? AND (target_grade = ? OR target_grade IS NULL OR target_grade = '') AND UPPER(status) IN ('ACTIVE', 'PROVISIONAL') ORDER BY version DESC, id DESC LIMIT 1");
        $stmt->execute([$audience, $targetGrade]);
    }
    
    $inst = $stmt->fetch();
    if (!$inst) {
        echo json_encode([]);
        exit;
    }
    
    $instId = $inst['id'];
    
    // Return the selected instrument's constructs and points as one coherent tool.
    $stmt = $db->prepare("
        SELECT i.id, c.name as construct_name, i.text, i.is_reverse 
        FROM wlc_items i 
        JOIN wlc_constructs c ON i.construct_id = c.id 
        WHERE c.instrument_id = ?
        ORDER BY c.id, i.id
    ");
    $stmt->execute([$instId]);
    echo json_encode(['instrument' => $inst, 'items' => $stmt->fetchAll()]);
    exit;
}

if ($uri === '/api/wlc-items' && $method === 'POST') {
    $user = authenticateToken();
    if ($user['role'] !== 'owner') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $audience = $inputBody['audience'] ?? 'KIDS';
    $targetGrade = $inputBody['target_grade'] ?? null;
    $constructName = $inputBody['construct_name'] ?? '';
    $text = $inputBody['text'] ?? '';
    
    if (empty($constructName) || empty($text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing data']);
        exit;
    }
    
    // Find instrument
    if ($audience === 'KIDS') {
        $stmt = $db->prepare("SELECT id FROM wlc_instruments WHERE audience = ? AND target_grade = ?");
        $stmt->execute([$audience, $targetGrade]);
    } else {
        // Assume SMP/TEEN
        $stmt = $db->prepare("SELECT id FROM wlc_instruments WHERE audience = ? OR audience = 'SMP Self-Report'");
        $stmt->execute([$audience]);
    }
    
    $inst = $stmt->fetch();
    if (!$inst) {
        http_response_code(404);
        echo json_encode(['error' => 'Instrument not found']);
        exit;
    }
    $instId = $inst['id'];
    
    // Find or create construct
    $stmt = $db->prepare("SELECT id FROM wlc_constructs WHERE instrument_id = ? AND name = ?");
    $stmt->execute([$instId, $constructName]);
    $construct = $stmt->fetch();
    
    if ($construct) {
        $constructId = $construct['id'];
    } else {
        $stmt = $db->prepare("INSERT INTO wlc_constructs (instrument_id, name) VALUES (?, ?)");
        $stmt->execute([$instId, $constructName]);
        $constructId = $db->lastInsertId();
    }
    
    // Insert item
    $stmt = $db->prepare("INSERT INTO wlc_items (construct_id, text, is_reverse) VALUES (?, ?, 0)");
    $stmt->execute([$constructId, $text]);
    
    echo json_encode(['success' => true]);
    exit;
}

// 2. Bank Soal Endpoints
if ($uri === '/api/bank-soal') {
    if ($method === 'GET') {
        authenticateToken(); // require authenticated user to read
        $versi = $queryParams['versi'] ?? 1;
        $type = $queryParams['type'] ?? 'A';
        $stmt = $db->prepare('SELECT * FROM bank_soal WHERE wlc = ? AND type = ? ORDER BY id');
        $stmt->execute([$versi, $type]);
        echo json_encode($stmt->fetchAll());
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $wlc = $inputBody['wlc'] ?? null;
        $type = $inputBody['type'] ?? null;
        $komponen = $inputBody['komponen'] ?? null;
        $indikator = $inputBody['indikator'] ?? null;
        $pertanyaan = $inputBody['pertanyaan'] ?? null;
        $contoh = $inputBody['contoh'] ?? '';

        if (!$wlc || !$type || !$komponen || !$indikator || !$pertanyaan) {
            http_response_code(400);
            echo json_encode(['error' => 'WLC, Type, Komponen, Indikator, dan Pertanyaan wajib diisi']);
            exit;
        }
        $stmt = $db->prepare('INSERT INTO bank_soal (wlc, type, komponen, indikator, pertanyaan, contoh) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$wlc, $type, $komponen, $indikator, $pertanyaan, $contoh]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

if ($uri === '/api/bank-soal-all' && $method === 'GET') {
    authenticateToken();
    $stmt = $db->query('SELECT DISTINCT wlc, type FROM bank_soal ORDER BY wlc, type');
    echo json_encode($stmt->fetchAll() ?: []);
    exit;
}

// 3. Users Endpoints
if ($uri === '/api/users') {
    if ($method === 'GET') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $stmt = $db->query('SELECT id, role, username, email FROM users');
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $role = $inputBody['role'] ?? null;
        $username = $inputBody['username'] ?? null;
        $password = $inputBody['password'] ?? null;
        $email = $inputBody['email'] ?? null;

        if (!$role || !$username || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields required']);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        try {
            $stmt = $db->prepare('INSERT INTO users (role, username, password, email) VALUES (?, ?, ?, ?)');
            $stmt->execute([$role, $username, $hashed, $email]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Gagal menambah user']);
        }
        exit;
    }
}

// 4. Jadwal Endpoints
if ($uri === '/api/jadwal') {
    if ($method === 'GET') {
        authenticateToken();
        $stmt = $db->query('SELECT j.*, s.nama as namaSekolah FROM jadwal j LEFT JOIN sekolah s ON j.sekolahId = s.id ORDER BY j.tanggal DESC');
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $sekolahId = $inputBody['sekolahId'] ?? null;
        $tanggal = $inputBody['tanggal'] ?? null;
        $catatan = $inputBody['catatan'] ?? '';

        if (!$sekolahId || !$tanggal) {
            http_response_code(400);
            echo json_encode(['error' => 'Sekolah dan Tanggal wajib diisi']);
            exit;
        }
        $stmt = $db->prepare('INSERT INTO jadwal (sekolahId, tanggal, catatan, status) VALUES (?, ?, ?, "pending")');
        $stmt->execute([$sekolahId, $tanggal, $catatan]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

// 5. Login Endpoint
if ($uri === '/api/login' && $method === 'POST') {
    $username = $inputBody['username'] ?? '';
    $password = $inputBody['password'] ?? '';

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        exit;
    }

    $sanitizedUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$sanitizedUsername]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    $valid = false;
    if ($user['password'] && strpos($user['password'], '$2') === 0) {
        $valid = password_verify($password, $user['password']);
    }

    if (!$valid) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    $token = jwt_encode([
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'token_version' => (int)($user['token_version'] ?? 0)
    ], JWT_SECRET, 24);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => ['role' => $user['role'], 'username' => $user['username'], 'email' => $user['email'] ?? '']
    ]);
    exit;
}

// 5.5 Reset Password Endpoint
if ($uri === '/api/reset-password' && $method === 'POST') {
    // Password reset is an administrative operation until a verified recovery
    // flow (email/OTP) exists. Never allow an anonymous caller to take over an
    // account by supplying its username.
    $user = authenticateToken();
    if (($user['role'] ?? null) !== 'owner') {
        http_response_code(403);
        echo json_encode(['error' => 'Hanya owner yang dapat mereset password akun.']);
        exit;
    }

    $username = $inputBody['username'] ?? '';
    $new_password = $inputBody['new_password'] ?? '';

    if (!$username || !$new_password) {
        http_response_code(400);
        echo json_encode(['error' => 'Username dan password baru wajib diisi']);
        exit;
    }
    if (strlen($new_password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password baru minimal 8 karakter']);
        exit;
    }

    $sanitizedUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$sanitizedUsername]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Username tidak ditemukan']);
        exit;
    }

    $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmtUpgrade = $db->prepare('UPDATE users SET password = ?, token_version = token_version + 1 WHERE id = ?');
    $stmtUpgrade->execute([$hashed, $user['id']]);

    echo json_encode(['success' => true]);
    exit;
}

// 5.6 Request OTP Endpoint
if ($uri === '/api/request-otp' && $method === 'POST') {
    enforceRateLimit($db, 'request-otp', 5, 900);
    $email = $inputBody['email'] ?? '';
    if (!$email) {
        http_response_code(400); echo json_encode(['error' => 'Email diperlukan']); exit;
    }
    
    $sanitizedEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($sanitizedEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); echo json_encode(['error' => 'Format email tidak valid']); exit;
    }

    $stmt = $db->prepare('SELECT id, email, username, otp_attempts, otp_last_request FROM users WHERE email = ?');
    $stmt->execute([$sanitizedEmail]);
    $user = $stmt->fetch();

    if ($user) {
        // Rate limiting: max 3 requests per 15 minutes, cooldown 1 minute
        $lastRequest = $user['otp_last_request'] ?: 0;
        $attempts = $user['otp_attempts'] ?: 0;

        if (time() - $lastRequest < 60) {
            http_response_code(429); echo json_encode(['error' => 'Harap tunggu 1 menit sebelum meminta OTP lagi.']); exit;
        }

        if ($attempts >= 3 && time() - $lastRequest < 900) {
            http_response_code(429); echo json_encode(['error' => 'Terlalu banyak percobaan. Silakan coba lagi dalam 15 menit.']); exit;
        }

        $newAttempts = (time() - $lastRequest > 900) ? 1 : $attempts + 1;

        // Generate secure 6-digit OTP
        $otp = sprintf("%06d", random_int(100000, 999999));
        $otpHash = password_hash($otp, PASSWORD_BCRYPT);
        $expires = time() + (5 * 60); // 5 minutes expiry

        $db->prepare('UPDATE users SET otp_hash = ?, otp_expires = ?, otp_attempts = ?, otp_last_request = ? WHERE id = ?')
           ->execute([$otpHash, $expires, $newAttempts, time(), $user['id']]);

        // SMTP Config from ENV
        $smtpHost = getenv('SMTP_HOST') ?: 'wlc.kumonwahidincilacap.com';
        $smtpPort = getenv('SMTP_PORT') ?: 465;
        $smtpEnc = getenv('SMTP_ENCRYPTION') ?: 'ssl';
        $smtpUser = getenv('SMTP_USERNAME') ?: 'admin@wlc.kumonwahidincilacap.com';
        $smtpPass = getenv('SMTP_PASSWORD');
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'admin@wlc.kumonwahidincilacap.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'WLC Security';

        require_once __DIR__ . '/SmtpMailer.php';
        
        try {
            $mailer = new SmtpMailer($smtpHost, $smtpPort, $smtpEnc, $smtpUser, $smtpPass);
            $subject = "Kode OTP Reset Password WLC";
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <h2>Wahidin Learning Companion (WLC)</h2>
                <p>Halo,</p>
                <p>Anda telah meminta untuk mereset password akun Anda. Berikut adalah kode OTP Anda:</p>
                <h1 style='background: #f4f4f4; padding: 10px; border-radius: 5px; display: inline-block; letter-spacing: 2px;'>{$otp}</h1>
                <p>Kode ini hanya berlaku selama <strong>5 menit</strong>.</p>
                <p style='color: #d9534f;'><strong>PERINGATAN:</strong> Jangan berikan kode ini kepada siapa pun! Jika Anda tidak meminta reset password, abaikan email ini.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 0.8em; color: #999;'>Pesan ini dikirim secara otomatis, mohon tidak membalas.</p>
            </body>
            </html>";
            
            $mailer->send($sanitizedEmail, $subject, $message, $fromEmail, $fromName);
        } catch (Exception $e) {
            // Do not expose SMTP errors to client for security
            error_log("SMTP Error: " . $e->getMessage());
        }
    } else {
        // Dummy sleep to prevent timing attacks (user enumeration)
        usleep(random_int(100000, 300000)); 
    }

    // Always return success to prevent email enumeration
    echo json_encode(['success' => true, 'message' => 'Jika email Anda terdaftar, kode OTP telah dikirim.']);
    exit;
}

// 5.7 Validate OTP and Reset Password Endpoint
if ($uri === '/api/reset-with-otp' && $method === 'POST') {
    $email = $inputBody['email'] ?? '';
    $otp = $inputBody['otp'] ?? '';
    $new_password = $inputBody['new_password'] ?? '';

    if (!$email || !$otp || !$new_password) {
        http_response_code(400); echo json_encode(['error' => 'Data tidak lengkap']); exit;
    }
    if (strlen($new_password) < 8) {
        http_response_code(400); echo json_encode(['error' => 'Password baru minimal 8 karakter']); exit;
    }
    enforceRateLimit($db, 'reset-otp:' . strtolower((string)$email), 5, 600);

    $sanitizedEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
    $stmt = $db->prepare('SELECT id, otp_hash, otp_expires FROM users WHERE email = ?');
    $stmt->execute([$sanitizedEmail]);
    $user = $stmt->fetch();

    if (!$user || !$user['otp_hash']) {
        http_response_code(400); echo json_encode(['error' => 'OTP salah atau kedaluwarsa']); exit;
    }
    if (time() > $user['otp_expires']) {
        // Clear expired OTP
        $db->prepare('UPDATE users SET otp_hash = NULL, otp_expires = NULL WHERE id = ?')->execute([$user['id']]);
        http_response_code(400); echo json_encode(['error' => 'OTP sudah kedaluwarsa']); exit;
    }
    if (!password_verify($otp, $user['otp_hash'])) {
        // Invalidate after the per-IP limit is reached; this prevents repeated guessing.
        $attempts = (int)($_SESSION['otp_failures'] ?? 0) + 1;
        $_SESSION['otp_failures'] = $attempts;
        if ($attempts >= 5) {
            $db->prepare('UPDATE users SET otp_hash = NULL, otp_expires = NULL WHERE id = ?')->execute([$user['id']]);
            unset($_SESSION['otp_failures']);
        }
        http_response_code(400); echo json_encode(['error' => 'OTP salah']); exit;
    }

    // OTP Valid. Reset password.
    $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
    $db->prepare('UPDATE users SET password = ?, token_version = token_version + 1, otp_hash = NULL, otp_expires = NULL, otp_attempts = 0 WHERE id = ?')
       ->execute([$hashed, $user['id']]);
    unset($_SESSION['otp_failures']);

    echo json_encode(['success' => true]);
    exit;
}

// 6. Siswa Endpoints
// 6.X Mark Siswa Absent
if ($uri === '/api/siswa/absen' && $method === 'POST') {
    $user = authenticateToken();
    $siswaId = (int)($inputBody['siswa_id'] ?? 0);
    if (!in_array($user['role'] ?? '', ['owner', 'evaluator', 'asisten'], true) || !$siswaId || !observerCanAccessStudent($db, $user, $siswaId)) {
        http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
    }
    if ($siswaId) {
        $db->prepare("UPDATE siswa SET status = 'absent' WHERE id = ?")->execute([$siswaId]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400); echo json_encode(['error' => 'Missing siswa_id']);
    }
    exit;
}
if ($uri === '/api/siswa') {
    if ($method === 'GET') {
        $user = authenticateToken();
        if (!in_array($user['role'] ?? '', ['owner', 'evaluator', 'asisten', 'siswa'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
        }
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $limit = min(500, max(1, (int)($queryParams['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];
        if ($user['role'] === 'siswa') {
            $conditions[] = 's.id = ?'; $params[] = (int)$user['reference_id'];
        } elseif ($user['role'] === 'asisten') {
            $groupStmt = $db->prepare('SELECT siswaIds FROM grup WHERE asistenId = ?');
            $groupStmt->execute([$user['username']]);
            $assignedIds = [];
            foreach ($groupStmt->fetchAll(PDO::FETCH_COLUMN) as $jsonIds) {
                $ids = json_decode((string)$jsonIds, true);
                if (is_array($ids)) $assignedIds = array_merge($assignedIds, array_map('intval', $ids));
            }
            $assignedIds = array_values(array_unique(array_filter($assignedIds, static fn($id) => $id > 0)));
            if (!$assignedIds) {
                echo json_encode(['data' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0, 'totalPages' => 0]]); exit;
            }
            $conditions[] = 's.id IN (' . implode(',', array_fill(0, count($assignedIds), '?')) . ')';
            $params = array_merge($params, $assignedIds);
        }
        $sekolahId = $queryParams['sekolahId'] ?? null;
        if ($sekolahId) { $conditions[] = 's.sekolahId = ?'; $params[] = $sekolahId; }
        $whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $sql = "SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas FROM siswa s
                LEFT JOIN sekolah sk ON s.sekolahId = sk.id LEFT JOIN kelas k ON s.kelasId = k.id
                {$whereSql} ORDER BY s.nama LIMIT ? OFFSET ?";
        $sqlCount = 'SELECT COUNT(*) as total FROM siswa s' . $whereSql;
        $paramsCount = $params;
        $params[] = $limit; $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($paramsCount);
        $total = (int)$stmtCount->fetch()['total'];

        echo json_encode([
            'data' => $rows ?: [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $nama = $inputBody['nama'] ?? '';
        $nisn = $inputBody['nisn'] ?? '';
        $sekolahId = $inputBody['sekolahId'] ?? 1;
        $kelasId = $inputBody['kelasId'] ?? 1;

        $stmt = $db->prepare('INSERT INTO siswa (nama, nisn, sekolahId, kelasId) VALUES (?, ?, ?, ?)');
        $stmt->execute([$nama, $nisn, $sekolahId, $kelasId]);
        echo json_encode(['id' => $db->lastInsertId()]);
        exit;
    }
}

// 6.b Generate Student Token Link
if ($uri === '/api/siswa/generate-link' && $method === 'POST') {
    $user = authenticateToken();
    if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $siswaId = $inputBody['siswaId'] ?? null;
    if (!$siswaId) {
        http_response_code(400);
        echo json_encode(['error' => 'Siswa ID required']);
        exit;
    }
    
    $stmt = $db->prepare('SELECT * FROM siswa WHERE id = ?');
    $stmt->execute([$siswaId]);
    $siswa = $stmt->fetch();
    
    if (!$siswa) {
        http_response_code(404);
        echo json_encode(['error' => 'Siswa tidak ditemukan']);
        exit;
    }
    
    $token = jwt_encode([
        'reference_id' => $siswa['id'],
        'username' => $siswa['nama'],
        'role' => 'siswa'
    ], JWT_SECRET, 168); // 7 days expiry

    echo json_encode([
        'success' => true,
        'token' => $token
    ]);
    exit;
}

// 7. Sekolah & Kelas Endpoints
if ($uri === '/api/sekolah') {
    if ($method === 'GET') {
        authenticateToken();
        $stmt = $db->query('SELECT * FROM sekolah');
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $nama = $inputBody['nama'] ?? '';
        $alamat = $inputBody['alamat'] ?? '';
        $kota = $inputBody['kota'] ?? '';
        $stmt = $db->prepare('INSERT INTO sekolah (nama, alamat, kota) VALUES (?, ?, ?)');
        $stmt->execute([$nama, $alamat, $kota]);
        echo json_encode(['id' => $db->lastInsertId()]);
        exit;
    }
}

if ($uri === '/api/kelas') {
    if ($method === 'GET') {
        authenticateToken();
        $stmt = $db->query('SELECT k.*, s.nama as namaSekolah FROM kelas k LEFT JOIN sekolah s ON k.sekolahId = s.id');
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $nama = $inputBody['nama'] ?? '';
        $tingkat = $inputBody['tingkat'] ?? '';
        $sekolahId = $inputBody['sekolahId'] ?? null;
        $stmt = $db->prepare('INSERT INTO kelas (nama, tingkat, sekolahId) VALUES (?, ?, ?)');
        $stmt->execute([$nama, $tingkat, $sekolahId]);
        echo json_encode(['id' => $db->lastInsertId()]);
        exit;
    }
}

// 8. Grup Endpoints
if ($uri === '/api/grup') {
    if ($method === 'GET') {
        authenticateToken();
        $stmt = $db->query('SELECT g.*, j.sekolahId, j.tanggal, s.nama as namaSekolah 
                            FROM grup g 
                            LEFT JOIN jadwal j ON g.jadwalId = j.id 
                            LEFT JOIN sekolah s ON j.sekolahId = s.id 
                            ORDER BY g.created DESC');
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $nama = $inputBody['nama'] ?? null;
        $jadwalId = $inputBody['jadwalId'] ?? null;
        $asistenId = $inputBody['asistenId'] ?? null;
        $siswaIds = $inputBody['siswaIds'] ?? null;

        if (!$jadwalId || !$asistenId || !$siswaIds) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields required']);
            exit;
        }
        $stmt = $db->prepare('INSERT INTO grup (nama, jadwalId, asistenId, siswaIds) VALUES (?, ?, ?, ?)');
        $stmt->execute([$nama, $jadwalId, $asistenId, $siswaIds]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

// 9. Siswa Bulk Upload
if ($uri === '/api/siswa/bulk' && $method === 'POST') {
    $user = authenticateToken();
    if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $list = $inputBody['list'] ?? [];
    $sekolahId = $inputBody['sekolahId'] ?? 1;
    $kelasId = $inputBody['kelasId'] ?? 1;

    if (!is_array($list)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid list format']);
        exit;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT OR IGNORE INTO siswa (nama, nisn, sekolahId, kelasId) VALUES (?, ?, ?, ?)');
        foreach ($list as $s) {
            $stmt->execute([$s['nama'], $s['nisn'], $sekolahId, $kelasId]);
        }
        $db->commit();
        echo json_encode(['success' => true, 'count' => count($list)]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// 10. Observasi Endpoints
if ($uri === '/api/observasi') {
    if ($method === 'GET') {
        $user = authenticateToken();
        if (!in_array($user['role'] ?? '', ['owner', 'evaluator', 'asisten'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
        }
        $sql = 'SELECT o.*, s.nama, s.nisn, sk.nama as namaSekolah, k.nama as namaKelas, b.pertanyaan 
                            FROM observasi o 
                            JOIN siswa s ON o.siswaId = s.id 
                            LEFT JOIN sekolah sk ON s.sekolahId = sk.id
                            LEFT JOIN kelas k ON s.kelasId = k.id
                            JOIN bank_soal b ON o.soalId = b.id 
                            ';
        if ($user['role'] === 'asisten') $sql .= 'WHERE o.asistenId = ? ';
        $sql .= 'ORDER BY o.timestamp DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($user['role'] === 'asisten' ? [$user['username']] : []);
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        
        // Identity Hardening: if role is 'siswa', force siswaId from JWT (authoritative source)
        if (isset($user['role']) && $user['role'] === 'siswa') {
            $siswaId = $user['reference_id'] ?? $user['id'];
        } else {
        if (!in_array($user['role'] ?? '', ['owner', 'evaluator', 'asisten'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
        }
        $siswaId = (int)($inputBody['siswaId'] ?? 0);
        }
        
        $soalId = $inputBody['soalId'] ?? null;
        $skor = $inputBody['skor'] ?? null;
        // Verify asistenId matches logged in username (or default to it) to prevent spoofing
        $asistenId = $user['username'];

        $soalId = (int)$soalId;
        if ($siswaId < 1 || $soalId < 1 || !is_numeric($skor) || (int)$skor < 1 || (int)$skor > 5 || !observerCanAccessStudent($db, $user, $siswaId)) {
            http_response_code(400); echo json_encode(['error' => 'Data observasi tidak valid atau siswa bukan tanggung jawab asisten']); exit;
        }
        $check = $db->prepare('SELECT 1 FROM siswa WHERE id = ?'); $check->execute([$siswaId]);
        $checkSoal = $db->prepare('SELECT 1 FROM bank_soal WHERE id = ?'); $checkSoal->execute([$soalId]);
        if (!$check->fetchColumn() || !$checkSoal->fetchColumn()) { http_response_code(400); echo json_encode(['error' => 'Siswa atau soal tidak ditemukan']); exit; }
        $stmt = $db->prepare('INSERT INTO observasi (siswaId, soalId, skor, asistenId) VALUES (?, ?, ?, ?)');
        $stmt->execute([$siswaId, $soalId, $skor, $asistenId]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

if ($uri === '/api/observasi/bulk' && $method === 'POST') {
    $user = authenticateToken();
    if (!in_array($user['role'] ?? '', ['owner', 'evaluator', 'asisten'], true)) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit; }
    $scores = $inputBody['scores'] ?? null;
    $asistenId = $user['username']; // enforce token identity

    if (!$scores) {
        http_response_code(400);
        echo json_encode(['error' => 'No scores provided']);
        exit;
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO observasi (siswaId, soalId, skor, asistenId, timestamp) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
        foreach ($scores as $soalIndex => $muridScores) {
            $soalId = (int)$soalIndex + 1;
            foreach ($muridScores as $muridId => $skor) {
                $studentId = (int)$muridId;
                if ($studentId < 1 || !is_numeric($skor) || (int)$skor < 1 || (int)$skor > 5 || !observerCanAccessStudent($db, $user, $studentId)) {
                    throw new RuntimeException('Invalid observation assignment or score');
                }
                $exists = $db->prepare('SELECT 1 FROM siswa WHERE id = ?'); $exists->execute([$studentId]);
                $questionExists = $db->prepare('SELECT 1 FROM bank_soal WHERE id = ?'); $questionExists->execute([$soalId]);
                if (!$exists->fetchColumn() || !$questionExists->fetchColumn()) throw new RuntimeException('Unknown student or question');
                $stmt->execute([$studentId, $soalId, (int)$skor, $asistenId]);
            }
        }
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Bulk observasi tersimpan']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// 10.b Verify Student for Parent Reflection (Public Endpoint)
if ($uri === '/api/reflection/verify-student' && $method === 'POST') {
    enforceRateLimit($db, 'reflection-verify', 8, 900);
    $nama = trim($inputBody['nama'] ?? '');
    $nisn = trim($inputBody['nisn'] ?? '');

    if (!$nama || !$nisn) {
        http_response_code(400);
        echo json_encode(['error' => 'Nama lengkap murid dan NISN wajib diisi']);
        exit;
    }

    $stmt = $db->prepare('SELECT s.id, s.nama, sk.nama as namaSekolah, k.nama as namaKelas 
                        FROM siswa s 
                        LEFT JOIN sekolah sk ON s.sekolahId = sk.id
                        LEFT JOIN kelas k ON s.kelasId = k.id
                        WHERE LOWER(s.nama) = LOWER(?) AND s.nisn = ?');
    $stmt->execute([$nama, $nisn]);
    $student = $stmt->fetch();

    if ($student) {
        session_regenerate_id(true);
        $_SESSION['verified_reflection_student'] = (int)$student['id'];
        $_SESSION['verified_reflection_expires'] = time() + 1800;
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Data murid tidak ditemukan. Silakan periksa kembali Nama dan NISN yang dimasukkan.']);
    }
    exit;
}

// 11. Reflection (Parent Reflections) Endpoints
if ($uri === '/api/reflection') {
    if ($method === 'GET') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner' && $user['role'] !== 'evaluator') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $stmt = $db->query('SELECT pr.*, s.nama as namaSiswa, s.nisn, sk.nama as namaSekolah, k.nama as namaKelas 
                            FROM parent_reflections pr 
                            JOIN siswa s ON pr.siswaId = s.id
                            LEFT JOIN sekolah sk ON s.sekolahId = sk.id
                            LEFT JOIN kelas k ON s.kelasId = k.id
                            ORDER BY pr.timestamp DESC');
        $rows = $stmt->fetchAll() ?: [];
        
        // Match response mapping of Node.js
        $formatted = [];
        foreach ($rows as $r) {
            $formatted[] = [
                'id' => $r['id'],
                'siswaId' => $r['siswaId'],
                'siswaNama' => $r['namaSiswa'],
                'nisn' => $r['nisn'],
                'namaSekolah' => $r['namaSekolah'],
                'namaKelas' => $r['namaKelas'],
                'parent_name' => $r['parentName'],
                'kesiapan' => $r['kesiapan'],
                'fokus' => $r['fokus'],
                'kemandirian' => $r['kemandirian'],
                'ketekunan' => $r['ketekunan'],
                'emosional' => $r['emosional'],
                'minat' => $r['minat'],
                'catatan' => $r['catatan'],
                'created_at' => $r['timestamp']
            ];
        }
        echo json_encode($formatted);
        exit;
    } elseif ($method === 'POST') {
        enforceRateLimit($db, 'reflection-submit', 3, 3600);
        $siswaId = (int)($_SESSION['verified_reflection_student'] ?? 0);
        $verifiedUntil = (int)($_SESSION['verified_reflection_expires'] ?? 0);
        if (!$siswaId || $verifiedUntil < time() || $siswaId !== (int)($inputBody['siswaId'] ?? 0)) {
            http_response_code(403); echo json_encode(['error' => 'Verifikasi siswa diperlukan atau sudah kedaluwarsa']); exit;
        }
        $parentName = $inputBody['parent_name'] ?? null;
        $kesiapan = $inputBody['kesiapan'] ?? null;
        $fokus = $inputBody['fokus'] ?? null;
        $kemandirian = $inputBody['kemandirian'] ?? null;
        $ketekunan = $inputBody['ketekunan'] ?? null;
        $emosional = $inputBody['emosional'] ?? null;
        $minat = $inputBody['minat'] ?? null;
        $catatan = $inputBody['catatan'] ?? '';

        if (!$parentName || strlen($parentName) > 480 || strlen((string)$catatan) > 8000) {
            http_response_code(400);
            echo json_encode(['error' => 'Nama orang tua atau catatan tidak valid']);
            exit;
        }
        foreach ([$kesiapan, $fokus, $kemandirian, $ketekunan, $emosional, $minat] as $rating) {
            if (!is_numeric($rating) || (int)$rating < 1 || (int)$rating > 4) {
                http_response_code(400); echo json_encode(['error' => 'Nilai refleksi harus 1 sampai 4']); exit;
            }
        }
        $stmt = $db->prepare('INSERT INTO parent_reflections (siswaId, parentName, kesiapan, fokus, kemandirian, ketekunan, emosional, minat, catatan) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$siswaId, $parentName, $kesiapan, $fokus, $kemandirian, $ketekunan, $emosional, $minat, $catatan]);
        unset($_SESSION['verified_reflection_student'], $_SESSION['verified_reflection_expires']);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

// 11.a Kegiatan WLC & Public Certificate Endpoints
if ($uri === '/api/kegiatan-wlc') {
    if ($method === 'GET') {
        $user = authenticateToken();
        $siswaId = $queryParams['siswaId'] ?? null;
        if ($user['role'] === 'siswa') {
            $stmt = $db->prepare('SELECT id, siswaId, wlc_tipe, tanggal, org_name, kesiapan, fokus, respons, kemandirian, ketekunan, emosional, minat, timestamp FROM kegiatan_wlc WHERE siswaId = ? ORDER BY tanggal DESC, id DESC');
            $stmt->execute([$user['reference_id']]);
        } elseif ($user['role'] === 'asisten') {
            $groupStmt = $db->prepare('SELECT siswaIds FROM grup WHERE asistenId = ?');
            $groupStmt->execute([$user['username']]);
            $assignedIds = [];
            foreach ($groupStmt->fetchAll(PDO::FETCH_COLUMN) as $jsonIds) {
                $ids = json_decode((string)$jsonIds, true);
                if (is_array($ids)) $assignedIds = array_merge($assignedIds, array_map('intval', $ids));
            }
            $assignedIds = array_values(array_unique(array_filter($assignedIds, static fn($id) => $id > 0)));
            if (!$assignedIds) { echo json_encode([]); exit; }
            $marks = implode(',', array_fill(0, count($assignedIds), '?'));
            if ($siswaId && !in_array((int)$siswaId, $assignedIds, true)) { http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit; }
            if ($siswaId) {
                $stmt = $db->prepare("SELECT * FROM kegiatan_wlc WHERE siswaId = ? AND siswaId IN ({$marks}) ORDER BY tanggal DESC, id DESC");
                $stmt->execute(array_merge([(int)$siswaId], $assignedIds));
            } else {
                $stmt = $db->prepare("SELECT * FROM kegiatan_wlc WHERE siswaId IN ({$marks}) ORDER BY tanggal DESC, id DESC");
                $stmt->execute($assignedIds);
            }
        } elseif (in_array($user['role'], ['owner', 'evaluator'], true) && $siswaId) {
            $stmt = $db->prepare('SELECT * FROM kegiatan_wlc WHERE siswaId = ? ORDER BY tanggal DESC, id DESC');
            $stmt->execute([$siswaId]);
        } elseif (in_array($user['role'], ['owner', 'evaluator'], true)) {
            $stmt = $db->query('SELECT * FROM kegiatan_wlc ORDER BY tanggal DESC, id DESC');
        } else {
            http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
        }
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        if (!in_array($user['role'] ?? '', ['owner', 'evaluator', 'asisten'], true)) {
            http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
        }
        
        // Identity Hardening: if role is 'siswa', force siswaId from JWT (authoritative source)
        if (isset($user['role']) && $user['role'] === 'siswa') {
            $siswaId = $user['reference_id'] ?? $user['id'];
        } else {
            $siswaId = $inputBody['siswaId'] ?? null;
        }
        
        $wlc_tipe = $inputBody['wlc_tipe'] ?? null;
        $tanggal = $inputBody['tanggal'] ?? null;
        $org_name = $inputBody['org_name'] ?? '';
        $kesiapan = $inputBody['kesiapan'] ?? '';
        $fokus = $inputBody['fokus'] ?? '';
        $respons = $inputBody['respons'] ?? '';
        $kemandirian = $inputBody['kemandirian'] ?? '';
        $ketekunan = $inputBody['ketekunan'] ?? '';
        $emosional = $inputBody['emosional'] ?? '';
        $minat = $inputBody['minat'] ?? '';

        if (!$siswaId || !$wlc_tipe || !$tanggal) {
            http_response_code(400);
            echo json_encode(['error' => 'SiswaId, WLC Tipe, dan Tanggal wajib diisi']);
            exit;
        }
        if (!observerCanAccessStudent($db, $user, (int)$siswaId)) {
            http_response_code(403); echo json_encode(['error' => 'Siswa bukan tanggung jawab asisten']); exit;
        }

        $shareToken = bin2hex(random_bytes(32));
        $stmt = $db->prepare('INSERT INTO kegiatan_wlc (siswaId, wlc_tipe, tanggal, org_name, kesiapan, fokus, respons, kemandirian, ketekunan, emosional, minat, share_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$siswaId, $wlc_tipe, $tanggal, $org_name, $kesiapan, $fokus, $respons, $kemandirian, $ketekunan, $emosional, $minat, $shareToken]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'share_token' => $shareToken]);
        exit;
    }
}

if ($uri === '/api/public/cert' && $method === 'GET') {
    $id = $queryParams['id'] ?? null;
    $shareToken = $queryParams['key'] ?? '';
    if (!$id || !preg_match('/^[a-f0-9]{64}$/', (string)$shareToken)) {
        http_response_code(404);
        echo json_encode(['error' => 'Sertifikat tidak ditemukan']);
        exit;
    }
    $stmt = $db->prepare('
        SELECT k.wlc_tipe, k.tanggal, k.org_name, k.kesiapan, k.fokus, k.respons,
               k.kemandirian, k.ketekunan, k.emosional, k.minat,
               s.nama as namaSiswa, sk.nama as namaSekolah, kl.nama as namaKelas
        FROM kegiatan_wlc k
        JOIN siswa s ON k.siswaId = s.id
        LEFT JOIN sekolah sk ON s.sekolahId = sk.id
        LEFT JOIN kelas kl ON s.kelasId = kl.id
        WHERE k.id = ? AND k.share_token = ?
    ');
    $stmt->execute([$id, $shareToken]);
    $cert = $stmt->fetch();
    if (!$cert) {
        http_response_code(404);
        echo json_encode(['error' => 'Sertifikat tidak ditemukan']);
        exit;
    }
    echo json_encode($cert);
    exit;
}

// 12. Seed Bank Soal Endpoint
if ($uri === '/api/seed-bank-soal' && $method === 'POST') {
    $user = authenticateToken();
    if ($user['role'] !== 'owner') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    try {
        seedBankSoal($db);
        $stmt = $db->query('SELECT COUNT(*) as count FROM bank_soal');
        $count = $stmt->fetch()['count'];
        echo json_encode(['success' => true, 'count' => $count]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit;
}

// 13. Dynamic DELETE with Cascade
if (preg_match('#^/api/([^/]+)/([0-9]+)$#', $uri, $matches)) {
    $table = $matches[1];
    $id = (int)$matches[2];

    if ($method === 'DELETE') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $allowedTables = ['users', 'sekolah', 'kelas', 'siswa', 'jadwal', 'grup', 'bank_soal', 'kegiatan_wlc'];
        if (!in_array($table, $allowedTables)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid table']);
            exit;
        }

        $cascadeRules = [
            'sekolah' => ['DELETE FROM kelas WHERE sekolahId = ?', 'DELETE FROM siswa WHERE sekolahId = ?'],
            'kelas' => ['DELETE FROM siswa WHERE kelasId = ?'],
            'siswa' => ['DELETE FROM observasi WHERE siswaId = ?', 'DELETE FROM parent_reflections WHERE siswaId = ?', 'DELETE FROM kegiatan_wlc WHERE siswaId = ?'],
            'jadwal' => ['DELETE FROM grup WHERE jadwalId = ?'],
            'grup' => []
        ];

        $db->beginTransaction();
        try {
            if (isset($cascadeRules[$table])) {
                foreach ($cascadeRules[$table] as $sql) {
                    $stmtC = $db->prepare($sql);
                    $stmtC->execute([$id]);
                }
            }
            $stmtM = $db->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmtM->execute([$id]);
            $changes = $stmtM->rowCount();
            $db->commit();
            echo json_encode(['success' => true, 'changes' => $changes]);
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }

    // 14. Dynamic UPDATE
    if ($method === 'PUT') {
        $user = authenticateToken();
        if ($user['role'] !== 'owner') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $tableWhitelists = [
            'users' => ['username', 'password', 'role'],
            'sekolah' => ['nama', 'alamat', 'kota', 'status'],
            'kelas' => ['nama', 'tingkat', 'sekolahId'],
            'siswa' => ['nama', 'nisn', 'sekolahId', 'kelasId'],
            'jadwal' => ['sekolahId', 'tanggal', 'status', 'catatan'],
            'grup' => ['nama', 'jadwalId', 'asistenId', 'siswaIds'],
            'bank_soal' => ['wlc', 'type', 'komponen', 'indikator', 'pertanyaan', 'contoh'],
            'kegiatan_wlc' => ['siswaId', 'wlc_tipe', 'tanggal', 'org_name', 'kesiapan', 'fokus', 'respons', 'kemandirian', 'ketekunan', 'emosional', 'minat']
        ];

        if (!isset($tableWhitelists[$table])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid table']);
            exit;
        }

        $keys = array_intersect(array_keys($inputBody), $tableWhitelists[$table]);
        if (empty($keys)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid data']);
            exit;
        }

        if ($table === 'users' && isset($inputBody['password'])) {
            $inputBody['password'] = password_hash($inputBody['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        }

        $setParts = [];
        $values = [];
        foreach ($keys as $k) {
            $setParts[] = "{$k} = ?";
            $values[] = $inputBody[$k];
        }
        $values[] = $id;

        $setClause = implode(', ', $setParts);
        try {
            $stmt = $db->prepare("UPDATE {$table} SET {$setClause} WHERE id = ?");
            $stmt->execute($values);
            if ($table === 'users' && isset($inputBody['password'])) {
                $db->prepare('UPDATE users SET token_version = token_version + 1 WHERE id = ?')->execute([$id]);
            }
            echo json_encode(['success' => true, 'changes' => $stmt->rowCount()]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
}

// 404 Route Fallback
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);

