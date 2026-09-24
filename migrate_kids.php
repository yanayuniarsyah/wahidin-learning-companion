<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$db = new PDO('sqlite:' . __DIR__ . '/wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Add columns to wlc_instruments
try {
    $db->exec("ALTER TABLE wlc_instruments ADD COLUMN audience TEXT DEFAULT 'TEEN'");
    $db->exec("ALTER TABLE wlc_instruments ADD COLUMN target_grade TEXT DEFAULT 'SMP'");
} catch (Exception $e) {}

// Add columns to wlc_sessions
try {
    $db->exec("ALTER TABLE wlc_sessions ADD COLUMN observer_id INTEGER NULL");
    $db->exec("ALTER TABLE wlc_sessions ADD COLUMN respondent_type TEXT DEFAULT 'STUDENT'");
} catch (Exception $e) {}

// Update existing Teen instrument
$db->exec("UPDATE wlc_instruments SET audience='TEEN', methodology='SELF_REPORT_QUESTIONNAIRE', target_grade='SMP' WHERE methodology='SMP' OR methodology='SMP Self-Report' OR audience='TEEN'");

// Create Kids instrument
$stmt = $db->prepare("SELECT id FROM wlc_instruments WHERE audience = 'KIDS'");
$stmt->execute();
if (!$stmt->fetch()) {
    $db->exec("INSERT INTO wlc_instruments (version, methodology, status, audience, target_grade) VALUES (1, 'STRUCTURED OBSERVATION', 'PROVISIONAL', 'KIDS', 'TK, SD 1-6')");
    $kids_id = $db->lastInsertId();
    
    $constructs = [
        'Transisi ke Belajar', 'Keterlibatan terhadap Aktivitas', 'Penerapan Instruksi', 
        'Inisiatif Penyelesaian', 'Respons terhadap Kendala', 'Partisipasi Aktif', 'Lingkungan Belajar'
    ];
    
    // Add columns is_reverse if missing
    try {
        $db->exec("ALTER TABLE wlc_items ADD COLUMN is_reverse INTEGER DEFAULT 0");
    } catch (Exception $e) {}
    
    $insertConstruct = $db->prepare("INSERT INTO wlc_constructs (instrument_id, name) VALUES (?, ?)");
    $insertItem = $db->prepare("INSERT INTO wlc_items (construct_id, text, is_reverse) VALUES (?, ?, 0)");
    
    foreach ($constructs as $c) {
        $insertConstruct->execute([$kids_id, $c]);
        $c_id = $db->lastInsertId();
        $insertItem->execute([$c_id, '[CONTENT_NOT_AVAILABLE] Indikator Kids - ' . $c . ' 1']);
        $insertItem->execute([$c_id, '[CONTENT_NOT_AVAILABLE] Indikator Kids - ' . $c . ' 2']);
    }
    echo "Kids instrument created.\n";
} else {
    echo "Kids instrument already exists.\n";
}
echo "Migration OK.\n";
?>
