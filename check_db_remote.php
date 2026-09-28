<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dbPath = __DIR__ . '/wlc.db';
echo "File exists: " . (file_exists($dbPath) ? "Yes" : "No") . "<br>\n";
echo "Readable: " . (is_readable($dbPath) ? "Yes" : "No") . "<br>\n";
echo "Writable: " . (is_writable($dbPath) ? "Yes" : "No") . "<br>\n";
echo "File size: " . filesize($dbPath) . " bytes<br>\n";

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Users count: " . count($users) . "<br>\n";
    foreach ($users as $u) {
        if ($u['username'] == 'owner') {
            echo "Owner password hash: " . $u['password'] . "<br>\n";
        }
    }
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
unlink(__FILE__);
?>
