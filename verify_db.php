<?php
$db = new PDO('sqlite:wlc.db');
$stmt = $db->query("SELECT i.target_grade, c.name, it.id FROM wlc_instruments i JOIN wlc_constructs c ON i.id = c.instrument_id JOIN wlc_items it ON c.id = it.construct_id WHERE i.audience = 'KIDS' ORDER BY it.id");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
?>
