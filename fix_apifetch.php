<?php
$file = 'dashboard.html';
$content = file_get_contents($file);

$pattern = '/const urlStr = \(typeof endpoint === \'string\'\)\s*\? \(endpoint\.startsWith\(\'http\'\) \? endpoint : \(endpoint\.startsWith\(\'\/\'\) \? `\$\{API_BASE\}\$\{endpoint\}` : \(endpoint\.startsWith\(\'\.\/\'\) \? `\$\{API_BASE\}\$\{endpoint\.substring\(1\)\}` : `\$\{API_BASE\}\/\$\{endpoint\}`\)\)\)\s*: endpoint\.url;/is';

$newCode = <<<EOD
let urlStr;
if (typeof endpoint === 'string') {
    if (endpoint.startsWith('http')) {
        urlStr = endpoint;
    } else if (API_BASE !== '' && endpoint.startsWith(API_BASE)) {
        urlStr = endpoint; // Already prepended
    } else if (endpoint.startsWith('/')) {
        urlStr = `\${API_BASE}\${endpoint}`;
    } else if (endpoint.startsWith('./')) {
        urlStr = `\${API_BASE}\${endpoint.substring(1)}`;
    } else {
        urlStr = `\${API_BASE}/\${endpoint}`;
    }
} else {
    urlStr = endpoint.url;
}
EOD;

$newContent = preg_replace($pattern, $newCode, $content);
if ($newContent !== $content) {
    file_put_contents($file, $newContent);
    echo "Fixed apiFetch double prepending using regex";
} else {
    echo "Regex failed to match";
}
