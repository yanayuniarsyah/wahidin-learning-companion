<?php
// router.php

// Disable cache for development
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

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

// Serve static files
$realBase = realpath(__DIR__);
$filePath = __DIR__ . $uri;

// Safe path validation checking parent directory to support non-existent files (like favicon.ico)
$dirPath = realpath(dirname($filePath));
if ($dirPath === false || strpos($dirPath, $realBase) !== 0) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Block sensitive files specifically, while allowing general files (like manifest.json)
if (preg_match('/(wlc\.db|bank_soal.*\.json|\.(bat|md|git|sh))$/i', $uri)) {
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
