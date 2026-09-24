<?php
// migrate_seed.php
// Re-builds WLC Phase 1 schema with 14 Draft items and is_reverse flag.
require 'api.php';

try {
    $db->exec('PRAGMA foreign_keys = ON;');
    
    // Drop Phase 1 tables
    $db->exec("DROP TABLE IF EXISTS wlc_reports");
    $db->exec("DROP TABLE IF EXISTS wlc_responses");
    $db->exec("DROP TABLE IF EXISTS wlc_sessions");
    $db->exec("DROP TABLE IF EXISTS wlc_items");
    $db->exec("DROP TABLE IF EXISTS wlc_constructs");
    $db->exec("DROP TABLE IF EXISTS wlc_instruments");

    // Recreate
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
        is_reverse INTEGER DEFAULT 0,
        FOREIGN KEY(construct_id) REFERENCES wlc_constructs(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE wlc_sessions (
        id INTEGER PRIMARY KEY,
        siswa_id INTEGER,
        instrument_id INTEGER,
        status TEXT DEFAULT 'draft',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        submitted_at DATETIME,
        FOREIGN KEY(siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
        FOREIGN KEY(instrument_id) REFERENCES wlc_instruments(id) ON DELETE RESTRICT
    )");

    $db->exec("CREATE TABLE wlc_responses (
        id INTEGER PRIMARY KEY,
        session_id INTEGER,
        item_id INTEGER,
        skor INTEGER,
        FOREIGN KEY(session_id) REFERENCES wlc_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY(item_id) REFERENCES wlc_items(id) ON DELETE CASCADE,
        UNIQUE(session_id, item_id)
    )");
    
    $db->exec("CREATE TABLE wlc_reports (
        id INTEGER PRIMARY KEY,
        session_id INTEGER,
        construct_id INTEGER,
        mean_score REAL,
        category TEXT,
        FOREIGN KEY(session_id) REFERENCES wlc_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY(construct_id) REFERENCES wlc_constructs(id) ON DELETE CASCADE,
        UNIQUE(session_id, construct_id)
    )");

    echo "Tables recreated securely with ON DELETE CASCADE and is_reverse.\n";

    // ---------------------------------------------------------
    // SEEDING
    // ---------------------------------------------------------
    
    // 1. Instrument (PROVISIONAL)
    $stmt = $db->prepare("INSERT INTO wlc_instruments (version, methodology, status) VALUES (?, ?, ?)");
    $stmt->execute([1, 'SMP Self-Report', 'provisional']);
    $instrumentId = $db->lastInsertId();

    $constructs = [
        "Transisi ke Belajar",
        "Keterlibatan terhadap Aktivitas",
        "Penerapan Instruksi",
        "Inisiatif Penyelesaian",
        "Respons terhadap Kendala",
        "Partisipasi Aktif",
        "Lingkungan Belajar"
    ];
    
    $items = [
        // Transisi (1)
        [1, "Saya segera menyiapkan buku dan alat tulis ketika waktu belajar dimulai.", 0],
        [1, "Saya butuh waktu lama untuk benar-benar mulai mengerjakan soal.", 1],
        // Keterlibatan (2)
        [2, "Saya menyelesaikan satu bagian tugas sebelum beralih ke aktivitas lain.", 0],
        [2, "Perhatian saya mudah teralihkan oleh hal lain saat sedang belajar.", 1],
        // Instruksi (3)
        [3, "Saya membaca instruksi soal dengan teliti sebelum mulai menjawab.", 0],
        [3, "Saya langsung mengerjakan soal tanpa melihat contoh cara penyelesaian.", 1],
        // Inisiatif (4)
        [4, "Saya mencoba mengerjakan soal sendiri terlebih dahulu sebelum bertanya.", 0],
        [4, "Saya harus selalu diingatkan agar segera menyelesaikan tugas.", 1],
        // Respons (5)
        [5, "Jika menemui soal sulit, saya mencoba membacanya sekali lagi dengan lebih pelan.", 0],
        [5, "Saya langsung berhenti dan menyerah jika soalnya terlihat susah.", 1],
        // Partisipasi (6)
        [6, "Saya mengecek kembali hasil pekerjaan saya sebelum menyatakannya selesai.", 0],
        [6, "Saya bertanya kepada guru atau teman jika ada bagian yang benar-benar tidak saya pahami.", 0],
        // Lingkungan (7)
        [7, "Saya memastikan tempat belajar saya rapi dari barang yang tidak diperlukan.", 0],
        [7, "Saya terbiasa belajar sambil menyalakan televisi atau media sosial yang tidak terkait pelajaran.", 1]
    ];

    $stmtConstruct = $db->prepare("INSERT INTO wlc_constructs (instrument_id, name) VALUES (?, ?)");
    $stmtItem = $db->prepare("INSERT INTO wlc_items (construct_id, text, is_reverse) VALUES (?, ?, ?)");
    
    foreach ($constructs as $index => $cName) {
        $stmtConstruct->execute([$instrumentId, $cName]);
        $cId = $db->lastInsertId();
        
        // Find items for this construct (1-indexed)
        foreach ($items as $item) {
            if ($item[0] == ($index + 1)) {
                $stmtItem->execute([$cId, $item[1], $item[2]]);
            }
        }
    }

    echo "Seed completed: 7 constructs and 14 candidate items (Provisional).\n";
} catch (Exception $e) {
    echo "MIGRATION ERROR: " . $e->getMessage() . "\n";
}
