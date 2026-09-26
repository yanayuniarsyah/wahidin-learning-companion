<?php
$c = file_get_contents('dashboard.html');
$c = str_replace('</body>', '<script src="wlc_kids.js"></script></body>', $c);
file_put_contents('dashboard.html', $c);
echo "OK\n";
?>
