<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
try {
    $dbPath = __DIR__ . '/wlc.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout = 5000;');
    
    // Add missing email column without UNIQUE constraint because SQLite doesn't allow adding UNIQUE columns directly
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN email TEXT"
    ];
    
    foreach ($alterQueries as $q) {
        try {
            $db->exec($q);
            echo "Executed: $q<br>\n";
        } catch (Exception $e) {
            echo "Skipped/Error: $q (" . $e->getMessage() . ")<br>\n";
        }
    }
    
    // Check if the column is there now
    $stmt = $db->query("PRAGMA table_info(users)");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasEmail = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'email') $hasEmail = true;
    }
    echo "Has email column: " . ($hasEmail ? "Yes" : "No") . "<br>\n";
    
    echo "Schema patch done!";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
unlink(__FILE__);
?>
