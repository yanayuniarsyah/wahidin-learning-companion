<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$stmt = $db->query('SELECT * FROM wlc_instruments');
print_r($stmt->fetchAll());
?>
