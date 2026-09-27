<?php
// router.php

// Disable cache for development
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

// Serve index.html by default if path is empty or slash
if ($uri === '/' || $uri === '') {
    $_SERVER['REQUEST_URI'] = '/index.html';
    $uri = '/index.html';
}

// Route API calls to api.php
if (strpos($uri, '/api') === 0) {
    include __DIR__ . '/api.php';
    exit;
}

if (preg_match('#(^|/)\.(git|svn|hg)(/|$)#i', $uri)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Serve static files
$realBase = realpath(__DIR__);
$filePath = __DIR__ . $uri;

// Resolve the target itself and enforce a path-component boundary. A plain
// string-prefix check would also accept sibling folders such as "project2".
$resolvedFile = realpath($filePath);
if ($resolvedFile !== false && strpos($resolvedFile, $realBase . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Block sensitive files specifically, while allowing general files (like manifest.json)
if (preg_match('/(^|\/)(wlc\.db(?:-wal|-shm)?|db_export\.json|bank_soal.*\.json|\.jwt_secret|\.ftp_credentials)$/i', $uri) || preg_match('/\.(bat|md|git|sh)$/i', $uri)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

if (file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// Fallback
$_SERVER['REQUEST_URI'] = '/index.html';
return false;
