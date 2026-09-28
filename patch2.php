<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
try {
    $dbPath = __DIR__ . '/wlc.db';
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $newPassword = 'admin123';
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'owner' OR role = 'owner'");
    $stmt->execute([$hashed]);
    echo "UPDATED";
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
unlink(__FILE__);
?>
