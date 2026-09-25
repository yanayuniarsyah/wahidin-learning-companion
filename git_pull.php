<?php
// git_pull.php — deploy hook
// Protected by token
$secret = getenv('GIT_PULL_SECRET') ?: 'wlc-deploy-2026';
$token  = $_GET['token'] ?? '';

if ($token !== $secret) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$dir = escapeshellarg(__DIR__);
$output = [];
$return_code = 0;

exec("git -C $dir pull origin main 2>&1", $output, $return_code);

echo '<pre>';
echo 'Exit code: ' . $return_code . "\n";
echo htmlspecialchars(implode("\n", $output));
echo '</pre>';
