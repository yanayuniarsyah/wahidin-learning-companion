<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
try {
    $dbPath = __DIR__ . '/wlc.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout = 5000;');
    
    $alterQueries = [
        "ALTER TABLE users ADD COLUMN token_version INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE users ADD COLUMN email TEXT UNIQUE",
        "ALTER TABLE users ADD COLUMN otp_hash TEXT",
        "ALTER TABLE users ADD COLUMN otp_expires INTEGER",
        "ALTER TABLE users ADD COLUMN otp_attempts INTEGER DEFAULT 0",
        "ALTER TABLE users ADD COLUMN otp_last_request INTEGER DEFAULT 0"
    ];
    
    foreach ($alterQueries as $q) {
        try {
            $db->exec($q);
            echo "Executed: $q<br>\n";
        } catch (Exception $e) {
            echo "Skipped (maybe already exists): $q<br>\n";
        }
    }
    echo "Schema patch done!";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
unlink(__FILE__);
?>
