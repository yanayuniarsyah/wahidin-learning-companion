<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
try {
    $dbPath = __DIR__ . '/wlc.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $db->query("PRAGMA table_info(users)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo $col['name'] . " (" . $col['type'] . ")<br>\n";
    }
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
unlink(__FILE__);
?>
