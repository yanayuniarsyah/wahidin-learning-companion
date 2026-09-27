<?php
$db=new PDO('sqlite:wlc.db');
foreach($db->query('PRAGMA table_info(users)') as $r) {
    echo $r['name'] . ' ' . $r['type'] . "\n";
}
