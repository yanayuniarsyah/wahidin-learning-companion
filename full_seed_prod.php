<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON;');

echo "Dropping dummy data...\n";
$db->exec("DELETE FROM wlc_items");
$db->exec("DELETE FROM wlc_constructs");
$db->exec("DELETE FROM wlc_instruments");

echo "Starting perfect full seed...\n";
$db->beginTransaction();
try {
    $data = json_decode(file_get_contents('db_export.json'), true);
    
    $insertInst = $db->prepare("INSERT INTO wlc_instruments (id, version, methodology, status, audience, target_grade) VALUES (?, ?, ?, ?, ?, ?)");
    $insertConst = $db->prepare("INSERT INTO wlc_constructs (id, instrument_id, name) VALUES (?, ?, ?)");
    $insertItem = $db->prepare("INSERT INTO wlc_items (id, construct_id, text, is_reverse) VALUES (?, ?, ?, ?)");
    
    foreach ($data['instruments'] as $inst) {
        $insertInst->execute([$inst['id'], $inst['version'], $inst['methodology'], $inst['status'], $inst['audience'], $inst['target_grade']]);
    }
    
    foreach ($data['constructs'] as $c) {
        $insertConst->execute([$c['id'], $c['instrument_id'], $c['name']]);
    }
    
    foreach ($data['items'] as $item) {
        $insertItem->execute([$item['id'], $item['construct_id'], $item['text'], $item['is_reverse']]);
    }
    
    $db->commit();
    echo "Seed perfectly applied! All SMP and KIDS items successfully populated.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Failed: " . $e->getMessage() . "\n";
}
?>
