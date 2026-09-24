<?php
$db = new PDO("sqlite:" . __DIR__ . "/wlc.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables safely
$db->exec("CREATE TABLE IF NOT EXISTS wlc_instruments (
    id INTEGER PRIMARY KEY,
    version INTEGER,
    methodology TEXT,
    status TEXT DEFAULT 'active'
)");

$db->exec("CREATE TABLE IF NOT EXISTS wlc_constructs (
    id INTEGER PRIMARY KEY,
    instrument_id INTEGER,
    name TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS wlc_items (
    id INTEGER PRIMARY KEY,
    construct_id INTEGER,
    text TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS wlc_sessions (
    id INTEGER PRIMARY KEY,
    siswa_id INTEGER,
    instrument_id INTEGER,
    status TEXT DEFAULT 'DRAFT',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS wlc_responses (
    id INTEGER PRIMARY KEY,
    session_id INTEGER,
    item_id INTEGER,
    skor INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(session_id, item_id)
)");

// Seed Instrument (Methodology: SMP - Student Self-Report)
$stmt = $db->query("SELECT id FROM wlc_instruments WHERE version = 1 AND methodology = 'SMP'");
if (!$stmt->fetch()) {
    $db->exec("INSERT INTO wlc_instruments (version, methodology, status) VALUES (1, 'SMP', 'active')");
    $instrument_id = $db->lastInsertId();

    $constructs = [
        "Transisi ke Belajar",
        "Keterlibatan terhadap Aktivitas",
        "Penerapan Instruksi",
        "Inisiatif Penyelesaian",
        "Respons terhadap Kendala",
        "Partisipasi Aktif",
        "Lingkungan Belajar"
    ];

    $insertConstruct = $db->prepare("INSERT INTO wlc_constructs (instrument_id, name) VALUES (?, ?)");
    $insertItem = $db->prepare("INSERT INTO wlc_items (construct_id, text) VALUES (?, ?)");

    foreach ($constructs as $c) {
        $insertConstruct->execute([$instrument_id, $c]);
        $c_id = $db->lastInsertId();
        // Seed Gap for item
        $insertItem->execute([$c_id, "[SEED GAP] Indikator observasi mandiri untuk $c"]);
    }
    echo "Migration & Seeding OK.\n";
} else {
    echo "Already migrated.\n";
}
?>
