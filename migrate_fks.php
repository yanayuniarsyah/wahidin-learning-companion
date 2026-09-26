<?php
$db = new PDO("sqlite:wlc.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Enable foreign keys
$db->exec("PRAGMA foreign_keys = ON;");

// Drop existing Phase 1 tables to recreate with FKs
$db->exec("DROP TABLE IF EXISTS wlc_responses;");
$db->exec("DROP TABLE IF EXISTS wlc_sessions;");
$db->exec("DROP TABLE IF EXISTS wlc_items;");
$db->exec("DROP TABLE IF EXISTS wlc_constructs;");
$db->exec("DROP TABLE IF EXISTS wlc_instruments;");

// Re-create with Foreign Key Constraints
$db->exec("CREATE TABLE wlc_instruments (
    id INTEGER PRIMARY KEY,
    version INTEGER,
    methodology TEXT,
    status TEXT DEFAULT 'active'
)");

$db->exec("CREATE TABLE wlc_constructs (
    id INTEGER PRIMARY KEY,
    instrument_id INTEGER,
    name TEXT,
    FOREIGN KEY(instrument_id) REFERENCES wlc_instruments(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE wlc_items (
    id INTEGER PRIMARY KEY,
    construct_id INTEGER,
    text TEXT,
    FOREIGN KEY(construct_id) REFERENCES wlc_constructs(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE wlc_sessions (
    id INTEGER PRIMARY KEY,
    siswa_id INTEGER,
    instrument_id INTEGER,
    status TEXT DEFAULT 'DRAFT',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    FOREIGN KEY(instrument_id) REFERENCES wlc_instruments(id) ON DELETE RESTRICT
)");

$db->exec("CREATE TABLE wlc_responses (
    id INTEGER PRIMARY KEY,
    session_id INTEGER,
    item_id INTEGER,
    skor INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(session_id) REFERENCES wlc_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY(item_id) REFERENCES wlc_items(id) ON DELETE RESTRICT,
    UNIQUE(session_id, item_id)
)");

// Seed Instrument (Methodology: SMP - Student Self-Report)
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
    $insertItem->execute([$c_id, "[SEED GAP] Indikator observasi mandiri untuk $c"]);
}
echo "Migration with Foreign Keys OK.\n";
?>
