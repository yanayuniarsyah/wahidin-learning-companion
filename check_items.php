<?php
$db = new PDO('sqlite:wlc.db');
$stmt = $db->query('SELECT id, text, construct_id FROM wlc_items WHERE id BETWEEN 8 AND 14');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
