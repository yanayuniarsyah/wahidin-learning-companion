<?php
// api.php

// CORS and response headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, x-user-role, x-wlc-token");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

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
    $db->exec('PRAGMA journal_mode = DELETE;');
    $db->exec('PRAGMA busy_timeout = 5000;');
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
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
    
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $signature64));
    $expected_signature = hash_hmac('sha256', $header64 . "." . $payload64, $secret, true);
    
    if (!hash_equals($signature, $expected_signature)) {
        return null;
    }
    
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload64)), true);
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return null;
    }
    
    return $payload;
}

function getBearerToken() {
    $authHeader = null;
    
    // Check multiple potential locations for the Authorization header
    if (isset($_GET['token'])) {
        return trim($_GET['token']);
    }
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
    return $decoded;
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
        $insertUser = $db->prepare("INSERT OR IGNORE INTO users (role, username, password) VALUES (?, ?, ?)");
        $insertUser->execute(['owner', 'owner', password_hash('owner123', PASSWORD_BCRYPT, ['cost' => 10])]);
        $insertUser->execute(['evaluator', 'evaluator', password_hash('evaluator123', PASSWORD_BCRYPT, ['cost' => 10])]);
        $insertUser->execute(['asisten', 'asisten', password_hash('asisten123', PASSWORD_BCRYPT, ['cost' => 10])]);
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
$method = $_SERVER['REQUEST_METHOD'];

$queryParams = [];
if (isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $queryParams);
}
sanitizeObject($queryParams);

$inputBody = json_decode(file_get_contents('php://input'), true) ?? [];
sanitizeObject($inputBody);

// ROUTING

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
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
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
        $stmt = $db->query('SELECT id, role, username FROM users');
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

        if (!$role || !$username || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields required']);
            exit;
        }
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        try {
            $stmt = $db->prepare('INSERT INTO users (role, username, password) VALUES (?, ?, ?)');
            $stmt->execute([$role, $username, $hashed]);
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
    } elseif ($user['password'] === $password) {
        $valid = true;
        // Upgrade password to hash
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmtUpgrade = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmtUpgrade->execute([$hashed, $user['id']]);
    }

    if (!$valid) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    $token = jwt_encode([
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role']
    ], JWT_SECRET, 24);

    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => ['role' => $user['role'], 'username' => $user['username']]
    ]);
    exit;
}

// 6. Siswa Endpoints
if ($uri === '/api/siswa') {
    if ($method === 'GET') {
        authenticateToken();
        $page = (int)($queryParams['page'] ?? 1);
        $limit = (int)($queryParams['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $sekolahId = $queryParams['sekolahId'] ?? null;
        if ($sekolahId) {
            $sql = "SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                    FROM siswa s 
                    LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                    LEFT JOIN kelas k ON s.kelasId = k.id
                    WHERE s.sekolahId = ?
                    ORDER BY s.nama LIMIT ? OFFSET ?";
            $params = [$sekolahId, $limit, $offset];
            
            $sqlCount = "SELECT COUNT(*) as total FROM siswa s WHERE s.sekolahId = ?";
            $paramsCount = [$sekolahId];
        } else {
            $sql = "SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                    FROM siswa s 
                    LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                    LEFT JOIN kelas k ON s.kelasId = k.id
                    ORDER BY s.nama LIMIT ? OFFSET ?";
            $params = [$limit, $offset];
            
            $sqlCount = "SELECT COUNT(*) as total FROM siswa s";
            $paramsCount = [];
        }

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
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 10. Observasi Endpoints
if ($uri === '/api/observasi') {
    if ($method === 'GET') {
        authenticateToken();
        $stmt = $db->query('SELECT o.*, s.nama, s.nisn, sk.nama as namaSekolah, k.nama as namaKelas, b.pertanyaan 
                            FROM observasi o 
                            JOIN siswa s ON o.siswaId = s.id 
                            LEFT JOIN sekolah sk ON s.sekolahId = sk.id
                            LEFT JOIN kelas k ON s.kelasId = k.id
                            JOIN bank_soal b ON o.soalId = b.id 
                            ORDER BY o.timestamp DESC');
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        $siswaId = $inputBody['siswaId'] ?? null;
        $soalId = $inputBody['soalId'] ?? null;
        $skor = $inputBody['skor'] ?? null;
        // Verify asistenId matches logged in username (or default to it) to prevent spoofing
        $asistenId = $user['username'];

        $stmt = $db->prepare('INSERT INTO observasi (siswaId, soalId, skor, asistenId) VALUES (?, ?, ?, ?)');
        $stmt->execute([$siswaId, $soalId, $skor, $asistenId]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

if ($uri === '/api/observasi/bulk' && $method === 'POST') {
    $user = authenticateToken();
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
                $stmt->execute([$muridId, $soalId, $skor, $asistenId]);
            }
        }
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Bulk observasi tersimpan']);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 10.b Verify Student for Parent Reflection (Public Endpoint)
if ($uri === '/api/reflection/verify-student' && $method === 'POST') {
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
        // Kept open without authenticateToken() so parent users can insert observations,
        // but sanitizing and restricting to insert only.
        $siswaId = $inputBody['siswaId'] ?? null;
        $parentName = $inputBody['parent_name'] ?? null;
        $kesiapan = $inputBody['kesiapan'] ?? null;
        $fokus = $inputBody['fokus'] ?? null;
        $kemandirian = $inputBody['kemandirian'] ?? null;
        $ketekunan = $inputBody['ketekunan'] ?? null;
        $emosional = $inputBody['emosional'] ?? null;
        $minat = $inputBody['minat'] ?? null;
        $catatan = $inputBody['catatan'] ?? '';

        if (!$siswaId || !$parentName) {
            http_response_code(400);
            echo json_encode(['error' => 'Siswa dan Nama Orang Tua wajib diisi']);
            exit;
        }
        $stmt = $db->prepare('INSERT INTO parent_reflections (siswaId, parentName, kesiapan, fokus, kemandirian, ketekunan, emosional, minat, catatan) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$siswaId, $parentName, $kesiapan, $fokus, $kemandirian, $ketekunan, $emosional, $minat, $catatan]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

// 11.a Kegiatan WLC & Public Certificate Endpoints
if ($uri === '/api/kegiatan-wlc') {
    if ($method === 'GET') {
        authenticateToken();
        $siswaId = $queryParams['siswaId'] ?? null;
        if ($siswaId) {
            $stmt = $db->prepare('SELECT * FROM kegiatan_wlc WHERE siswaId = ? ORDER BY tanggal DESC, id DESC');
            $stmt->execute([$siswaId]);
        } else {
            $stmt = $db->query('SELECT * FROM kegiatan_wlc ORDER BY tanggal DESC, id DESC');
        }
        echo json_encode($stmt->fetchAll() ?: []);
        exit;
    } elseif ($method === 'POST') {
        $user = authenticateToken();
        $siswaId = $inputBody['siswaId'] ?? null;
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

        $stmt = $db->prepare('INSERT INTO kegiatan_wlc (siswaId, wlc_tipe, tanggal, org_name, kesiapan, fokus, respons, kemandirian, ketekunan, emosional, minat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$siswaId, $wlc_tipe, $tanggal, $org_name, $kesiapan, $fokus, $respons, $kemandirian, $ketekunan, $emosional, $minat]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
}

if ($uri === '/api/public/cert' && $method === 'GET') {
    $id = $queryParams['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID sertifikat wajib diberikan']);
        exit;
    }
    $stmt = $db->prepare('
        SELECT k.*, s.nama as namaSiswa, s.nisn, sk.nama as namaSekolah, kl.nama as namaKelas
        FROM kegiatan_wlc k
        JOIN siswa s ON k.siswaId = s.id
        LEFT JOIN sekolah sk ON s.sekolahId = sk.id
        LEFT JOIN kelas kl ON s.kelasId = kl.id
        WHERE k.id = ?
    ');
    $stmt->execute([$id]);
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
        echo json_encode(['error' => $e->getMessage()]);
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
            'siswa' => ['DELETE FROM observasi WHERE siswaId = ?', 'DELETE FROM kegiatan_wlc WHERE siswaId = ?'],
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
