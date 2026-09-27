<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Starting fixes...\n";

// --- Fix 4: Tambah kolom status absen ke table siswa (jika belum ada) ---
try {
    $db->exec("ALTER TABLE siswa ADD COLUMN status TEXT DEFAULT 'active'");
    echo "Added 'status' column to 'siswa' table.\n";
} catch (Exception $e) {
    echo "'status' column might already exist.\n";
}

// --- Fix 3 & 4: Multi Tenancy & Absen Endpoint in api.php ---
$apiContent = file_get_contents('api.php');

// Multi Tenancy
// We need to modify GET /api/siswa
$targetSiswaGet = "if (\$uri === '/api/siswa') {\n    if (\$method === 'GET') {\n        authenticateToken();";
$replacementSiswaGet = "if (\$uri === '/api/siswa') {\n    if (\$method === 'GET') {\n        \$user = authenticateToken();\n        \$role = \$user['role'] ?? 'asisten';";
$apiContent = str_replace($targetSiswaGet, $replacementSiswaGet, $apiContent);

// We need to change the base SQL for GET /api/siswa to filter by role
$targetSqlSiswa = "\$sql = \"SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas \n                    FROM siswa s \n                    LEFT JOIN sekolah sk ON s.sekolahId = sk.id \n                    LEFT JOIN kelas k ON s.kelasId = k.id\n                    ORDER BY s.nama LIMIT ? OFFSET ?\";";
$replacementSqlSiswa = "
            if (\$role === 'owner') {
                \$sql = \"SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                        FROM siswa s 
                        LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                        LEFT JOIN kelas k ON s.kelasId = k.id
                        WHERE s.status != 'absent'
                        ORDER BY s.nama LIMIT ? OFFSET ?\";
            } else {
                // Multi-tenancy for Asisten
                \$grupStmt = \$db->prepare('SELECT siswaIds FROM grup WHERE asistenId = ?');
                \$grupStmt->execute([\$user['id']]);
                \$siswaIds = [];
                while (\$g = \$grupStmt->fetch()) {
                    \$ids = explode(',', \$g['siswaIds']);
                    \$siswaIds = array_merge(\$siswaIds, \$ids);
                }
                \$siswaIds = array_filter(\$siswaIds);
                if (empty(\$siswaIds)) \$siswaIds = [0]; // prevent empty IN clause
                \$inPlaceholders = implode(',', array_fill(0, count(\$siswaIds), '?'));
                \$sql = \"SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                        FROM siswa s 
                        LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                        LEFT JOIN kelas k ON s.kelasId = k.id
                        WHERE s.id IN (\$inPlaceholders) AND s.status != 'absent'
                        ORDER BY s.nama LIMIT ? OFFSET ?\";
                \$params = array_merge(\$siswaIds, \$params);
            }
";
// We just inject it. But wait, the original code had:
/*
        } else {
            $sql = "SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                    FROM siswa s 
                    LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                    LEFT JOIN kelas k ON s.kelasId = k.id
                    ORDER BY s.nama LIMIT ? OFFSET ?";
            $params = [$limit, $offset];
            
            $sqlCount = "SELECT COUNT(*) as total FROM siswa s";
            $paramsCount = [];
        }
*/
// It's safer to just inject at the top of the GET block to rebuild it:
$fullTargetGetSiswaBlock = <<<'PHP'
    if ($method === 'GET') {
        $user = authenticateToken();
        $role = $user['role'] ?? 'asisten';
        $page = (int)($queryParams['page'] ?? 1);
        $limit = (int)($queryParams['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $sekolahId = $queryParams['sekolahId'] ?? null;
        if ($sekolahId) {
            $sql = "SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                    FROM siswa s 
                    LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                    LEFT JOIN kelas k ON s.kelasId = k.id
                    WHERE s.sekolahId = ?
                    ORDER BY s.nama LIMIT ? OFFSET ?";
            $params = [$sekolahId, $limit, $offset];
            
            $sqlCount = "SELECT COUNT(*) as total FROM siswa s WHERE s.sekolahId = ?";
            $paramsCount = [$sekolahId];
        } else {
            $sql = "SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                    FROM siswa s 
                    LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                    LEFT JOIN kelas k ON s.kelasId = k.id
                    ORDER BY s.nama LIMIT ? OFFSET ?";
            $params = [$limit, $offset];
            
            $sqlCount = "SELECT COUNT(*) as total FROM siswa s";
            $paramsCount = [];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($paramsCount);
        $total = $stmtCount->fetch()['total'] ?? 0;

        echo json_encode(['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit]);
        exit;
    }
PHP;

$newGetSiswaBlock = <<<'PHP'
    if ($method === 'GET') {
        $user = authenticateToken();
        $role = $user['role'] ?? 'asisten';
        $page = (int)($queryParams['page'] ?? 1);
        $limit = (int)($queryParams['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $baseWhere = "s.status != 'absent'";
        $params = [];
        
        if ($role !== 'owner') {
            $grupStmt = $db->prepare('SELECT siswaIds FROM grup WHERE asistenId = ?');
            $grupStmt->execute([$user['id']]);
            $siswaIds = [];
            while ($g = $grupStmt->fetch()) {
                if($g['siswaIds']) {
                    $siswaIds = array_merge($siswaIds, explode(',', $g['siswaIds']));
                }
            }
            $siswaIds = array_filter(array_unique($siswaIds));
            if (empty($siswaIds)) $siswaIds = [0];
            $inPlaceholders = implode(',', array_fill(0, count($siswaIds), '?'));
            $baseWhere .= " AND s.id IN ($inPlaceholders)";
            $params = array_merge($params, $siswaIds);
        }

        $sekolahId = $queryParams['sekolahId'] ?? null;
        if ($sekolahId) {
            $baseWhere .= " AND s.sekolahId = ?";
            $params[] = $sekolahId;
        }
        
        $paramsCount = $params;
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT s.*, sk.nama as namaSekolah, k.nama as namaKelas 
                FROM siswa s 
                LEFT JOIN sekolah sk ON s.sekolahId = sk.id 
                LEFT JOIN kelas k ON s.kelasId = k.id
                WHERE $baseWhere
                ORDER BY s.nama LIMIT ? OFFSET ?";
                
        $sqlCount = "SELECT COUNT(*) as total FROM siswa s WHERE $baseWhere";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $stmtCount = $db->prepare($sqlCount);
        $stmtCount->execute($paramsCount);
        $total = $stmtCount->fetch()['total'] ?? 0;

        echo json_encode(['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit]);
        exit;
    }
PHP;

// Find the start and end of GET block to replace
$startPos = strpos($apiContent, "if (\$method === 'GET') {", strpos($apiContent, "if (\$uri === '/api/siswa') {"));
$endPos = strpos($apiContent, "    }\n\n    if (\$method === 'POST') {", $startPos);
if ($startPos !== false && $endPos !== false) {
    $apiContent = substr_replace($apiContent, $newGetSiswaBlock, $startPos, $endPos - $startPos);
}

// Add Endpoint /api/siswa/absen
$absenEndpoint = <<<'PHP'
// 6.X Mark Siswa Absent
if ($uri === '/api/siswa/absen' && $method === 'POST') {
    authenticateToken();
    $siswaId = $inputBody['siswa_id'] ?? null;
    if ($siswaId) {
        $db->prepare("UPDATE siswa SET status = 'absent' WHERE id = ?")->execute([$siswaId]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400); echo json_encode(['error' => 'Missing siswa_id']);
    }
    exit;
}
PHP;
$apiContent = str_replace("// 6. Siswa Endpoints", "// 6. Siswa Endpoints\n" . $absenEndpoint, $apiContent);

file_put_contents('api.php', $apiContent);
echo "Fixed api.php\n";

// --- Fix 1 & 5: Role Deletion & Remove offline fallback in script0.js ---
$scriptContent = file_get_contents('script0.js');

// 1. Hard Delete non-owner DOM
$targetShowRole = "            if (role === 'owner') {";
$replacementShowRole = "            if (role !== 'owner') {
                const toRemove = ['ownerDashboard', 'manageUsersDashboard', 'manageSiswaDashboard', 'manageJadwalDashboard', 'manageSekolahDashboard', 'manageSoalDashboard', 'manageCertDashboard', 'manageSettingsDashboard'];
                toRemove.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.remove();
                });
            }
            if (role === 'owner') {";
$scriptContent = str_replace($targetShowRole, $replacementShowRole, $scriptContent);

// 5. Remove simulateOfflineApi call
$scriptContent = preg_replace('/if \(isOffline\) \{.*?return simulateOfflineApi.*?\}\n/s', '', $scriptContent);

file_put_contents('script0.js', $scriptContent);
echo "Fixed script0.js\n";


// --- Fix 2 & 4: Auto-save & Siswa Absen button in wlc_kids.js ---
$wlcKidsContent = file_get_contents('wlc_kids.js');

// Siswa Absen Button
$targetButtonAbsen = "<div class=\"font-bold truncate\" title=\"\${s.nama}\">\${s.nama}</div>\n            </div>";
$replacementButtonAbsen = "<div class=\"font-bold truncate\" title=\"\${s.nama}\">\${s.nama}</div>\n            </div>\n            <button class=\"btn btn-secondary text-xs px-2 py-1\" onclick=\"markAbsentKids(\${s.id})\">Tidak Hadir</button>";
$wlcKidsContent = str_replace($targetButtonAbsen, $replacementButtonAbsen, $wlcKidsContent);

// Add markAbsentKids function
$absenFunc = <<<'JS'

async function markAbsentKids(siswaId) {
    if (!confirm('Tandai siswa ini tidak hadir? Data tidak akan diobservasi.')) return;
    try {
        await fetch(`${API_BASE}/api/siswa/absen`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${localStorage.getItem('wlc_token')}`, 'Content-Type': 'application/json' },
            body: JSON.stringify({ siswa_id: siswaId })
        });
        KIDS_STATE.siswaList = KIDS_STATE.siswaList.filter(s => s.id !== siswaId);
        renderKidsGroupQuestion();
    } catch (e) {
        console.error(e);
        alert('Gagal menandai absen');
    }
}
JS;
$wlcKidsContent .= $absenFunc;

// 2. Auto-save localStorage implementation
// Replace autoSaveKidsGroup to save to localStorage
$targetAutoSave = "KIDS_STATE.localScores[`\${siswaId}_\${itemId}`] = skor;";
$replacementAutoSave = "KIDS_STATE.localScores[`\${siswaId}_\${itemId}`] = skor;\n    localStorage.setItem(`wlc_obs_draft_\${siswaId}_\${itemId}`, skor);";
$wlcKidsContent = str_replace($targetAutoSave, $replacementAutoSave, $wlcKidsContent);

// Load from localStorage in startKidsGroupObservation
$targetLoadScores = "if (item.skor !== null && item.skor !== undefined) {\n                KIDS_STATE.localScores[`\${KIDS_STATE.siswaList[0].id}_\${item.item_id}`] = Number(item.skor);\n            }";
$replacementLoadScores = "if (item.skor !== null && item.skor !== undefined) {\n                KIDS_STATE.localScores[`\${KIDS_STATE.siswaList[0].id}_\${item.item_id}`] = Number(item.skor);\n            }\n            KIDS_STATE.siswaList.forEach(s => {\n                const draft = localStorage.getItem(`wlc_obs_draft_\${s.id}_\${item.item_id}`);\n                if (draft) KIDS_STATE.localScores[`\${s.id}_\${item.item_id}`] = Number(draft);\n            });";
$wlcKidsContent = str_replace($targetLoadScores, $replacementLoadScores, $wlcKidsContent);

// Clear localStorage in submitKidsGroup
$targetSubmitSuccess = "successCount++;\n            }";
$replacementSubmitSuccess = "successCount++;\n                KIDS_STATE.items.forEach(item => localStorage.removeItem(`wlc_obs_draft_\${s.id}_\${item.item_id}`));\n            }";
$wlcKidsContent = str_replace($targetSubmitSuccess, $replacementSubmitSuccess, $wlcKidsContent);

file_put_contents('wlc_kids.js', $wlcKidsContent);
echo "Fixed wlc_kids.js\n";

echo "All done!\n";
