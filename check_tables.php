<?php
$db = new PDO('sqlite:wlc.db');
foreach($db->query("SELECT name FROM sqlite_master WHERE type='table'") as $r) {
    echo $r['name'] . "\n";
}
