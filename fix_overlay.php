<?php
$c = file_get_contents('prod_dashboard.html');
$c = preg_replace('/(sidebar\.classList\.remove\(\'open\'\);\s*)overlay\.classList\.remove\(\'open\'\);/', '$1if (overlay) overlay.classList.remove(\'open\');', $c);
file_put_contents('prod_dashboard.html', $c);
echo "Fixed overlay\n";
