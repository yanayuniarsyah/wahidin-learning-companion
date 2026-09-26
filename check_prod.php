<?php
$db = new PDO('sqlite:wlc.db');
$stmt = $db->query('SELECT id, text, construct_id FROM wlc_items WHERE id BETWEEN 1 AND 7');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
