<?php
$db=new PDO('sqlite:wlc.db'); 
echo "--- grup ---\n";
foreach($db->query('PRAGMA table_info(grup)') as $r) echo $r['name'] . "\n";
echo "--- kelas ---\n";
foreach($db->query('PRAGMA table_info(kelas)') as $r) echo $r['name'] . "\n";
