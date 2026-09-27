<?php
$dashPath = 'dashboard.html';
$dashContent = file_get_contents($dashPath);

$uiToAdd = <<<EOD
<button class="btn btn-primary" onclick="document.getElementById('soalFormContainer').classList.toggle('hidden')"><i class="fas fa-plus"></i> Tambah Item</button>
</header>

<div id="soalFormContainer" class="form-container hidden">
    <div class="form-group">
        <label>Komponen / Konstruk (Misal: Transisi ke Belajar)</label>
        <input type="text" id="newSoalKomponen" placeholder="Nama Komponen" class="w-full">
    </div>
    <div class="form-group">
        <label>Pertanyaan / Pernyataan</label>
        <input type="text" id="newSoalPertanyaan" placeholder="Pernyataan lengkap" class="w-full">
    </div>
    <button class="btn btn-primary" onclick="saveNewWlcItem()">Simpan Item</button>
</div>
EOD;

// Replace just the header closing tag if it's the new UI
$dashContent = str_replace('</header>', $uiToAdd, $dashContent);

$scriptToAdd = <<<EOD
window.saveNewWlcItem = async function() {
    const komponen = document.getElementById('newSoalKomponen').value;
    const text = document.getElementById('newSoalPertanyaan').value;
    
    // Determine which instrument is currently active from the title
    const titleText = document.getElementById('instrumentTitle').textContent;
    let audience = 'KIDS';
    let target_grade = 'TK';
    if (titleText.includes('SMP')) {
        audience = 'SMP/TEEN';
        target_grade = null;
    } else if (titleText.includes('SD 1-3')) {
        target_grade = 'SD 1-3';
    } else if (titleText.includes('SD 4-6')) {
        target_grade = 'SD 4-6';
    }

    if(!komponen || !text) return showToast('❌ Lengkapi form');
    try {
        const res = await fetch(`\${API_BASE}/api/wlc-items`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({audience, target_grade, construct_name: komponen, text})
        });
        if(res.ok) {
            showToast('✅ Item disimpan!');
            document.getElementById('newSoalPertanyaan').value = '';
            document.getElementById('soalFormContainer').classList.add('hidden');
            await loadWLCInstruments(audience, target_grade);
        } else {
            const err = await res.json();
            showToast('❌ Gagal: ' + (err.error || 'Unknown error'));
        }
    } catch (e) { console.error(e); showToast('❌ Terjadi kesalahan sistem.'); }
};
EOD;

if (strpos($dashContent, 'saveNewWlcItem') === false) {
    $dashContent = str_replace('</script>', $scriptToAdd . "\n</script>", $dashContent);
}
file_put_contents($dashPath, $dashContent);

// Add backend endpoint to api.php
$apiPath = 'api.php';
$apiContent = file_get_contents($apiPath);

$backendToAdd = <<<EOD
if (\$uri === '/api/wlc-items' && \$method === 'POST') {
    \$user = authenticateToken();
    if (\$user['role'] !== 'owner') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    \$audience = \$inputBody['audience'] ?? 'KIDS';
    \$targetGrade = \$inputBody['target_grade'] ?? null;
    \$constructName = \$inputBody['construct_name'] ?? '';
    \$text = \$inputBody['text'] ?? '';
    
    if (empty(\$constructName) || empty(\$text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing data']);
        exit;
    }
    
    // Find instrument
    if (\$audience === 'KIDS') {
        \$stmt = \$db->prepare("SELECT id FROM wlc_instruments WHERE audience = ? AND target_grade = ?");
        \$stmt->execute([\$audience, \$targetGrade]);
    } else {
        // Assume SMP/TEEN
        \$stmt = \$db->prepare("SELECT id FROM wlc_instruments WHERE audience = ? OR audience = 'SMP Self-Report'");
        \$stmt->execute([\$audience]);
    }
    
    \$inst = \$stmt->fetch();
    if (!\$inst) {
        http_response_code(404);
        echo json_encode(['error' => 'Instrument not found']);
        exit;
    }
    \$instId = \$inst['id'];
    
    // Find or create construct
    \$stmt = \$db->prepare("SELECT id FROM wlc_constructs WHERE instrument_id = ? AND name = ?");
    \$stmt->execute([\$instId, \$constructName]);
    \$construct = \$stmt->fetch();
    
    if (\$construct) {
        \$constructId = \$construct['id'];
    } else {
        \$stmt = \$db->prepare("INSERT INTO wlc_constructs (instrument_id, name) VALUES (?, ?)");
        \$stmt->execute([\$instId, \$constructName]);
        \$constructId = \$db->lastInsertId();
    }
    
    // Insert item
    \$stmt = \$db->prepare("INSERT INTO wlc_items (construct_id, text, is_reverse) VALUES (?, ?, 0)");
    \$stmt->execute([\$constructId, \$text]);
    
    echo json_encode(['success' => true]);
    exit;
}
EOD;

if (strpos($apiContent, 'INSERT INTO wlc_items') === false) {
    // Replace the old empty block
    $apiContent = preg_replace("/if \(\\\$uri === '\/api\/wlc-items' && \\\$method === 'POST'\) \{.*?\/\/ Note: Creating items requires selecting construct\. For simplicity, we just update for now\.\s*\}/is", $backendToAdd, $apiContent);
    file_put_contents($apiPath, $apiContent);
}

echo "Done\n";
