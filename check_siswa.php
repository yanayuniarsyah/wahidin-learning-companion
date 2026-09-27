<?php
$db=new PDO('sqlite:wlc.db'); 
foreach($db->query('PRAGMA table_info(siswa)') as $r) echo $r['name'] . "\n";
echo "-----\n";
foreach($db->query('PRAGMA table_info(kelompok)') as $r) echo $r['name'] . "\n";
