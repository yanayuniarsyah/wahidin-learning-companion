<?php
// Script to set admin email for testing OTP
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$email = 'admin@wlc.kumonwahidincilacap.com'; // Ganti ini saat testing
$stmt = $db->prepare("UPDATE users SET email = ? WHERE username = 'admin' OR username = 'owner'");
$stmt->execute([$email]);

echo "Email untuk admin/owner berhasil diset ke: $email\n";
