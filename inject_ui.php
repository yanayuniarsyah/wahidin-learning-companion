<?php
$c = file_get_contents('dashboard.html');
$html = '<div id="wlcKidsDashboard" class="hidden"><header class="panel-header"><h1>Observasi WLC Kids</h1></header><div id="wlcKidsContent" class="mt-4"></div></div>';
$c = str_replace('<div id="manageSoalDashboard" class="hidden">', $html . "\n" . '<div id="manageSoalDashboard" class="hidden">', $c);
$c = preg_replace("/('manageSoalDashboard')/", "$1, 'wlcKidsDashboard'", $c);
file_put_contents('dashboard.html', $c);
echo "OK\n";
?>
