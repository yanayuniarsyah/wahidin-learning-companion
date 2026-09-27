<?php
// Script to inject WLC Instruments management into dashboard and api.php

// 1. Modify api.php
$apiPath = 'api.php';
$apiContent = file_get_contents($apiPath);
if (strpos($apiContent, '/api/wlc-instruments') === false) {
    $apiNewEndpoint = <<<EOD
    
if (\$uri === '/api/wlc-instruments' && \$method === 'GET') {
    authenticateToken();
    \$audience = \$_GET['audience'] ?? 'KIDS'; // 'KIDS' or 'SMP Self-Report'
    \$targetGrade = \$_GET['target_grade'] ?? 'TK';
    
    // Find the instrument ID
    if (\$audience === 'KIDS') {
        \$stmt = \$db->prepare("SELECT id FROM wlc_instruments WHERE audience = ? AND target_grade = ?");
        \$stmt->execute([\$audience, \$targetGrade]);
    } else {
        \$stmt = \$db->prepare("SELECT id FROM wlc_instruments WHERE audience = ?");
        \$stmt->execute([\$audience]);
    }
    
    \$inst = \$stmt->fetch();
    if (!\$inst) {
        echo json_encode([]);
        exit;
    }
    
    \$instId = \$inst['id'];
    
    // Fetch constructs and items
    \$stmt = \$db->prepare("
        SELECT i.id, c.name as construct_name, i.text, i.is_reverse 
        FROM wlc_items i 
        JOIN wlc_constructs c ON i.construct_id = c.id 
        WHERE c.instrument_id = ?
        ORDER BY c.id, i.id
    ");
    \$stmt->execute([\$instId]);
    echo json_encode(\$stmt->fetchAll());
    exit;
}

if (\$uri === '/api/wlc-items' && \$method === 'POST') {
    \$user = authenticateToken();
    if (\$user['role'] !== 'owner') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    // Note: Creating items requires selecting construct. For simplicity, we just update for now.
}

EOD;
    $apiContent = str_replace("// 2. Bank Soal Endpoints", $apiNewEndpoint . "\n// 2. Bank Soal Endpoints", $apiContent);
    file_put_contents($apiPath, $apiContent);
    echo "api.php updated.\n";
}

// 2. Modify dashboard.html
$dashPath = 'dashboard.html';
$dashContent = file_get_contents($dashPath);

// Replace UI section
$oldUI = <<<EOD
<header class="panel-header"><button class="btn btn-secondary" onclick="exitManageSoal()"><i class="fas fa-arrow-left"></i> Kembali</button><h1>Komponen Observasi</h1><button class="btn btn-primary" onclick="document.getElementById('soalFormContainer').classList.toggle('hidden')"><i class="fas fa-plus"></i> Tambah</button></header>
                
                <div id="soalFormContainer" class="form-container hidden">
                    <div class="form-group">
                        <label>Komponen (Aspek yang dinilai)</label>
                        <input type="text" id="newSoalKomponen" placeholder="Misal: Transisi ke Belajar" class="w-full">
                    </div>
                    <div class="form-group">
                        <label>Indikator</label>
                        <input type="text" id="newSoalIndikator" placeholder="Misal: TR-1" class="w-full">
                    </div>
                    <div class="form-group">
                        <label>Pertanyaan / Pernyataan</label>
                        <input type="text" id="newSoalPertanyaan" placeholder="Pernyataan lengkap" class="w-full">
                    </div>
                    <button class="btn btn-primary" onclick="saveNewSoal()">Simpan Soal</button>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead><tr><th>No</th><th>Komponen</th><th>Indikator</th><th>Pertanyaan</th><th>Aksi</th></tr></thead>
                        <tbody id="soalTableBody"></tbody>
                    </table>
                </div>
EOD;

$newUI = <<<EOD
<header class="panel-header">
    <button class="btn btn-secondary" onclick="exitManageSoal()"><i class="fas fa-arrow-left"></i> Kembali</button>
    <h1>Manajemen Instrumen (WLC)</h1>
</header>

<div class="tabs-container" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
    <button class="btn btn-primary" onclick="loadWLCInstruments('KIDS', 'TK')">WLC Kids - TK</button>
    <button class="btn btn-primary" onclick="loadWLCInstruments('KIDS', 'SD 1-3')">WLC Kids - SD 1-3</button>
    <button class="btn btn-primary" onclick="loadWLCInstruments('KIDS', 'SD 4-6')">WLC Kids - SD 4-6</button>
    <button class="btn btn-secondary" onclick="loadWLCInstruments('SMP/TEEN', null)">WLC Teen (SMP)</button>
</div>
<h3 id="instrumentTitle" style="margin-bottom:15px; font-weight:600; font-size:1.2rem;">WLC Kids - TK</h3>
<div class="table-container">
    <table>
        <thead><tr><th>No</th><th>Konstruk / Komponen</th><th>Pernyataan Observasi / Kuesioner</th><th>Aksi</th></tr></thead>
        <tbody id="soalTableBody"></tbody>
    </table>
</div>
EOD;

if (strpos($dashContent, 'loadWLCInstruments') === false) {
    $dashContent = str_replace($oldUI, $newUI, $dashContent);

    $newScript = <<<EOD
async function loadSoalTable() {
            await loadWLCInstruments('KIDS', 'TK');
        }

        window.loadWLCInstruments = async function(audience, targetGrade) {
            try {
                let url = `\${API_BASE}/api/wlc-instruments?audience=\${encodeURIComponent(audience)}`;
                if (targetGrade) url += `&target_grade=\${encodeURIComponent(targetGrade)}`;
                
                const res = await fetch(url); 
                let data = await res.json();
                
                let title = audience === 'KIDS' ? `WLC Kids - \${targetGrade}` : 'WLC Teen (Kuesioner SMP)';
                document.getElementById('instrumentTitle').textContent = title;
                
                const tbody = document.getElementById('soalTableBody');
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">Belum ada data.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.map((s, i) => `<tr>
                    <td>\${i+1}</td>
                    <td>\${escapeHtml(s.construct_name)}</td>
                    <td>\${escapeHtml(s.text)}</td>
                    <td>
                        <div class="action-group">
                            <button class="btn-action btn-edit-alt" style="width:auto; padding:0 0.8rem;" onclick="alert('Fitur edit segera hadir.')"><i class="fas fa-edit"></i> Edit</button>
                        </div>
                    </td>
                </tr>`).join('');
            } catch (e) { console.error(e); showToast('❌ Terjadi kesalahan sistem.'); }
        };
EOD;

    $dashContent = preg_replace('/async function loadSoalTable\(\).*?catch\s*\(e\)\s*\{\s*console\.error\(e\);\s*showToast\(\'(?:❌|.*?)\s*Terjadi kesalahan sistem\.\'\);\s*\}\s*\}/is', $newScript, $dashContent);
    
    file_put_contents($dashPath, $dashContent);
    echo "dashboard.html updated.\n";
} else {
    echo "dashboard.html already updated.\n";
}

?>
