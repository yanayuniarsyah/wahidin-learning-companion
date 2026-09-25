<?php
// git_pull.php — simple deploy hook
// Protect with a secret token
$secret = getenv('GIT_PULL_SECRET') ?: 'wlc-deploy-2026';
$token  = $_GET['token'] ?? '';

if ($token !== $secret) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$output = [];
$return_code = 0;

// Run git pull
exec('git -C ' . escapeshellarg(__DIR__) . ' pull origin main 2>&1', $output, $return_code);

echo '<pre>';
echo 'Exit code: ' . $return_code . "\n";
echo implode("\n", $output);
echo '</pre>';
