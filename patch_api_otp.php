<?php
$content = file_get_contents('api.php');

$endpoints = <<<'PHP'
// 5.6 Request OTP Endpoint
if ($uri === '/api/request-otp' && $method === 'POST') {
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
        http_response_code(400); echo json_encode(['error' => 'OTP salah']); exit;
    }

    // OTP Valid. Reset password.
    $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 10]);
    $db->prepare('UPDATE users SET password = ?, otp_hash = NULL, otp_expires = NULL, otp_attempts = 0 WHERE id = ?')
       ->execute([$hashed, $user['id']]);

    echo json_encode(['success' => true]);
    exit;
}
PHP;

$target = "// 6. Siswa Endpoints";
if (strpos($content, $target) !== false) {
    $newContent = str_replace($target, $endpoints . "\n\n" . $target, $content);
    file_put_contents('api.php', $newContent);
    echo "API Patched successfully!\n";
} else {
    echo "Target not found in api.php\n";
}
