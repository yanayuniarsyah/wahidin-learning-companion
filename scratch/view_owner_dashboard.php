<?php
$html = file_get_contents(__DIR__ . '/../index.html');
$pos = strpos($html, 'id="ownerDashboard"');
if ($pos !== false) {
    echo substr($html, $pos, 2500) . "\n";
}
