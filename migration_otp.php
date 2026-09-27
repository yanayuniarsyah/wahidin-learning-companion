<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $db->exec("ALTER TABLE users ADD COLUMN otp_hash TEXT");
    echo "Added otp_hash\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE users ADD COLUMN otp_attempts INTEGER DEFAULT 0");
    echo "Added otp_attempts\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE users ADD COLUMN otp_last_request INTEGER");
    echo "Added otp_last_request\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
