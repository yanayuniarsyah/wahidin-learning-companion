<?php
header('Content-Type: application/json');
$response = [];
$path = __DIR__ . '/wlc.db';
$response['DATABASE_CONNECTION'] = $path ? 'OK' : 'FAIL';
$response['DATABASE_PATH_PRODUCTION'] = $path;
$response['DATABASE_FILE_FOUND'] = file_exists($path) ? 'YES' : 'NO';
$response['DATABASE_FILE_SIZE'] = $response['DATABASE_FILE_FOUND'] === 'YES' ? filesize($path) : null;
$response['DATABASE_READABLE'] = is_readable($path) ? 'YES' : 'NO';
$pdo = null;
if ($response['DATABASE_FILE_FOUND'] === 'YES' && $response['DATABASE_READABLE'] === 'YES') {
    try {
        $pdo = new PDO('sqlite:' . $path);
        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $response['TABLES'] = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $response['TABLES'] = [];
    }
} else {
    $response['TABLES'] = [];
}
if (in_array('users', $response['TABLES'])) {
    $stmt = $pdo->prepare("SELECT username, role, password IS NOT NULL AS pwd_status FROM users WHERE username = :owner LIMIT 1");
    $stmt->execute(['owner' => 'owner']);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['USERS_TABLE'] = true;
    $colsStmt = $pdo->query('PRAGMA table_info(users)');
    $response['USERS_COLUMNS'] = [];
    while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
        $response['USERS_COLUMNS'][] = $col['name'];
    }
    $response['OWNER_ACCOUNT'] = $owner ? $owner['username'] : null;
    $response['OWNER_ROLE'] = $owner ? $owner['role'] : null;
    $response['PASSWORD_HASH_STATUS'] = $owner ? ($owner['pwd_status'] ? 'NON-NULL' : 'NULL') : null;
    $totalStmt = $pdo->query('SELECT COUNT(*) FROM users');
    $response['TOTAL_USERS'] = $totalStmt->fetchColumn();
} else {
    $response['USERS_TABLE'] = false;
}

echo json_encode($response);
?>
