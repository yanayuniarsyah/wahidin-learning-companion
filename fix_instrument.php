<?php
// fix_instrument.php
$dashPath = 'dashboard.html';
$dashContent = file_get_contents($dashPath);

// Replace modal form for soal
$oldSoalForm = '/<div id="soalFormContainer" class="hidden">.*?<\/div>\s*<\/div>/is';
$newSoalForm = <<<EOD
<div id="soalFormContainer" class="hidden">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;"><h3 style="color:#1e293b;font-family:'Outfit',sans-serif;margin:0;">Tambah Item Instrumen</h3><button class="btn btn-secondary" style="border-radius:50%;width:40px;height:40px;padding:0;" onclick="document.getElementById('soalFormContainer').classList.add('hidden')"><i class="fas fa-times" style="margin:0;color:#ef4444;"></i></button></div>
    <div class="form-group">
        <label class="form-label">Tujuan Penambahan (Audience / Grade)</label>
        <select class="form-input" id="newSoalTarget">
            <option value="KIDS|TK">WLC Kids - TK</option>
            <option value="KIDS|SD 1-3">WLC Kids - SD 1-3</option>
            <option value="KIDS|SD 4-6">WLC Kids - SD 4-6</option>
            <option value="SMP/TEEN|">WLC Teen / SMP</option>
        </select>
    </div>
    <div class="form-group"><label class="form-label">Komponen / Konstruk (Misal: Transisi ke Belajar)</label><input type="text" class="form-input" id="newSoalKomponen" placeholder="Nama Komponen"></div>
    <div class="form-group"><label class="form-label">Pertanyaan / Pernyataan</label><input type="text" class="form-input" id="newSoalPertanyaan" placeholder="Pernyataan lengkap..."></div>
    <button class="btn btn-primary" onclick="saveNewWlcItem()" style="width:100%;margin-top:0.5rem;"><i class="fas fa-save"></i> Simpan Item</button>
</div>
EOD;

// 1. apply modal change
$dashContent = preg_replace($oldSoalForm, $newSoalForm, $dashContent, 1);

// 2. update dashboard UI
$oldDashboard = <<<EOD
<header class="panel-header"><button class="btn btn-secondary" onclick="exitManageSoal()"><i class="fas fa-arrow-left"></i> Kembali</button><h1>Komponen Observasi</h1><button class="btn btn-primary" onclick="document.getElementById('soalFormContainer').classList.toggle('hidden')"><i class="fas fa-plus"></i> Tambah</button></header>
EOD;

$newDashboard = <<<EOD
<header class="panel-header">
    <button class="btn btn-secondary" onclick="exitManageSoal()"><i class="fas fa-arrow-left"></i> Kembali</button>
    <h1>Manajemen Instrumen (WLC)</h1>
    <button class="btn btn-primary" onclick="document.getElementById('soalFormContainer').classList.remove('hidden')"><i class="fas fa-plus"></i> Tambah Item</button>
</header>
<div class="tabs-container" style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
    <button class="btn btn-primary" onclick="loadWLCInstruments('KIDS', 'TK')">WLC Kids - TK</button>
    <button class="btn btn-primary" onclick="loadWLCInstruments('KIDS', 'SD 1-3')">WLC Kids - SD 1-3</button>
    <button class="btn btn-primary" onclick="loadWLCInstruments('KIDS', 'SD 4-6')">WLC Kids - SD 4-6</button>
    <button class="btn btn-secondary" onclick="loadWLCInstruments('SMP/TEEN', null)">WLC Teen (SMP)</button>
</div>
<h3 id="instrumentTitle" style="margin-bottom:15px; font-weight:600; font-size:1.2rem;">WLC Kids - TK</h3>
EOD;

$dashContent = str_replace($oldDashboard, $newDashboard, $dashContent);

// 3. update table header
$oldTableHead = '<thead><tr><th>No</th><th>Komponen</th><th>Indikator</th><th>Pertanyaan</th><th>Aksi</th></tr></thead>';
$newTableHead = '<thead><tr><th>No</th><th>Konstruk / Komponen</th><th>Pernyataan Observasi / Kuesioner</th><th>Aksi</th></tr></thead>';
$dashContent = str_replace($oldTableHead, $newTableHead, $dashContent);

// replace loadSoalTable script and add saveNewWlcItem
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

window.saveNewWlcItem = async function() {
    const targetVal = document.getElementById('newSoalTarget').value.split('|');
    const audience = targetVal[0];
    const target_grade = targetVal[1] || null;
    const komponen = document.getElementById('newSoalKomponen').value;
    const text = document.getElementById('newSoalPertanyaan').value;

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

$dashContent = preg_replace('/async function loadSoalTable\(\).*?catch\s*\(e\)\s*\{\s*console\.error\(e\);\s*showToast\(\'(?:❌|.*?)\s*Terjadi kesalahan sistem\.\'\);\s*\}\s*\}/is', $newScript, $dashContent);
file_put_contents($dashPath, $dashContent);
echo "dashboard fixed\n";
