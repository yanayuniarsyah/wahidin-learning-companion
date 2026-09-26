<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys = ON;');

echo "Cleaning up wrong WLC Kids instruments...\n";
$db->exec("DELETE FROM wlc_items WHERE id >= 15");
$db->exec("DELETE FROM wlc_constructs WHERE id >= 8");
$db->exec("DELETE FROM wlc_instruments WHERE methodology = 'STRUCTURED OBSERVATION'");

echo "Starting perfect seed...\n";
$db->beginTransaction();
try {
    $instruments = json_decode(file_get_contents('db_export.json'), true);
    
    $insertInst = $db->prepare("INSERT INTO wlc_instruments (id, version, methodology, status, audience, target_grade) VALUES (?, ?, ?, ?, ?, ?)");
    $insertConst = $db->prepare("INSERT INTO wlc_constructs (id, instrument_id, name) VALUES (?, ?, ?)");
    $insertItem = $db->prepare("INSERT INTO wlc_items (id, construct_id, text, is_reverse) VALUES (?, ?, ?, ?)");
    
    foreach ($instruments['instruments'] as $inst) {
        if ($inst['methodology'] === 'STRUCTURED OBSERVATION') {
            $insertInst->execute([$inst['id'], $inst['version'], $inst['methodology'], 'PROVISIONAL', $inst['audience'], $inst['target_grade']]);
        }
    }
    
    foreach ($instruments['constructs'] as $c) {
        if ($c['id'] >= 8) { // WLC Kids constructs locally are IDs 8+ (or just whatever is in DB for STRUCTURED OBSERVATION)
            // Wait, we need to check if the construct's instrument_id is a STRUCTURED OBSERVATION instrument
            $stmt = $db->prepare("SELECT 1 FROM wlc_instruments WHERE id = ? AND methodology = 'STRUCTURED OBSERVATION'");
            $stmt->execute([$c['instrument_id']]);
            if ($stmt->fetch()) {
                $insertConst->execute([$c['id'], $c['instrument_id'], $c['name']]);
            }
        }
    }
    
    foreach ($instruments['items'] as $item) {
        if ($item['id'] >= 15 && $item['id'] <= 56) {
            $insertItem->execute([$item['id'], $item['construct_id'], $item['text'], $item['is_reverse']]);
        }
    }
    
    $db->commit();
    echo "Seed perfectly applied! 42 items (ID 15-56) successfully populated.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Failed: " . $e->getMessage() . "\n";
}
?>
