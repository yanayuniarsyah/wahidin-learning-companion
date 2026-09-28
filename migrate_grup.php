<?php
// One-time, idempotent legacy membership backfill. The old JSON column is retained
// until all deployed clients have moved to the relational API.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$db = new PDO('sqlite:' . __DIR__ . '/wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');

try {
    $db->beginTransaction();

    // 1. Create the new table
    $db->exec('CREATE TABLE IF NOT EXISTS grup_siswa (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        grupId INTEGER NOT NULL,
        siswaId INTEGER NOT NULL,
        FOREIGN KEY (grupId) REFERENCES grup(id) ON DELETE CASCADE,
        FOREIGN KEY (siswaId) REFERENCES siswa(id) ON DELETE CASCADE,
        UNIQUE(grupId, siswaId)
    )');

    echo "Tabel grup_siswa berhasil dibuat.\n";

    $groupColumns = array_column($db->query('PRAGMA table_info(grup)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('siswaIds', $groupColumns, true)) {
        $db->commit();
        echo "Kolom JSON sudah tidak ada; tabel relasional dibiarkan apa adanya.\n";
        exit(0);
    }

    // 2. Fetch existing groups
    $stmt = $db->query('SELECT id, siswaIds FROM grup');
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $insertStmt = $db->prepare('INSERT OR IGNORE INTO grup_siswa (grupId, siswaId) SELECT ?, id FROM siswa WHERE id = ?');

    $migratedCount = 0;
    foreach ($groups as $grup) {
        $grupId = $grup['id'];
        $siswaIdsJson = $grup['siswaIds'];
        if ($siswaIdsJson) {
            $siswaIds = json_decode($siswaIdsJson, true);
            if (is_array($siswaIds)) {
                foreach (array_unique(array_map('intval', $siswaIds)) as $siswaId) {
                    if ($siswaId < 1) continue;
                    $insertStmt->execute([$grupId, $siswaId]);
                    $migratedCount += $insertStmt->rowCount();
                }
            }
        }
    }

    $db->commit();
    echo "Berhasil memigrasi $migratedCount data anggota grup ke tabel relasional!\n";

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}

