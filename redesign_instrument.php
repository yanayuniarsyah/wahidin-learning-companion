<?php
$dashPath = 'dashboard.html';
$dashContent = file_get_contents($dashPath);

$pattern = '/<div id="manageSoalDashboard" class="hidden">.*?<\/table>\s*<\/div>/is';

$newSection = <<<EOD
<div id="manageSoalDashboard" class="hidden">
<header class="panel-header">
    <button class="btn btn-secondary" onclick="exitManageSoal()"><i class="fas fa-arrow-left"></i> Kembali</button>
    <h1><i class="fas fa-book"></i> Instrumen WLC</h1>
    <div style="width:100px;"></div>
</header>

<p style="color:var(--text-muted);margin:0 0 1.25rem;">Kumpulan alat pengumpulan data untuk kegiatan WLC Kids dan WLC Teen.</p>

<div style="background:var(--secondary-soft); padding:1.5rem; border-radius:var(--radius-md); margin-bottom:2rem; display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
    <div class="form-group" style="margin:0; flex:1; min-width:200px;">
        <label class="form-label" style="font-size:0.8rem;">Cari Konstruk / Pertanyaan</label>
        <input type="text" id="instrumentSearchInput" class="form-input" placeholder="Ketik kata kunci..." onkeyup="filterInstrumentTable()">
    </div>
    <div class="form-group" style="margin:0; flex:1; min-width:150px;">
        <label class="form-label" style="font-size:0.8rem;">Kategori Instrumen</label>
        <select class="form-input" id="instrumentFilterSelect" onchange="const val = this.value.split('|'); loadWLCInstruments(val[0], val[1] || null);">
            <option value="KIDS|TK">WLC Kids - TK (Observasi)</option>
            <option value="KIDS|SD 1-3">WLC Kids - SD 1-3 (Observasi)</option>
            <option value="KIDS|SD 4-6">WLC Kids - SD 4-6 (Observasi)</option>
            <option value="SMP/TEEN|">WLC Teen / SMP (Kuesioner)</option>
        </select>
    </div>
    <div style="display:flex; gap:0.5rem;">
        <button class="btn btn-primary" onclick="document.getElementById('soalFormContainer').classList.remove('hidden')"><i class="fas fa-plus"></i> Tambah Item</button>
    </div>
</div>

<h3 id="instrumentTitle" style="margin-bottom:15px; font-weight:600; font-size:1.2rem; color:var(--primary);"><i class="fas fa-list-ul"></i> WLC Kids - TK</h3>

<table class="data-table" id="soalTable">
    <thead>
        <tr>
            <th style="width:50px;">No</th>
            <th style="width:25%">Konstruk / Komponen</th>
            <th>Pernyataan Observasi / Kuesioner</th>
            <th style="text-align:center;width:150px;">Aksi</th>
        </tr>
    </thead>
    <tbody id="soalTableBody">
        <tr><td colspan="4" style="text-align:center;padding:2rem;">Memuat data...</td></tr>
    </tbody>
</table>
</div>
EOD;

$dashContent = preg_replace($pattern, $newSection, $dashContent);

// Add the search script
$searchScript = <<<EOD
window.filterInstrumentTable = function() {
    const term = document.getElementById('instrumentSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#soalTableBody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
};
EOD;

if (strpos($dashContent, 'filterInstrumentTable') === false) {
    $dashContent = str_replace('</script>', $searchScript . "\n</script>", $dashContent);
}

// Update loadWLCInstruments to sync title and dropdown
$oldLoad = "let title = audience === 'KIDS' ? `WLC Kids - \${targetGrade}` : 'WLC Teen (Kuesioner SMP)';";
$newLoad = "let title = audience === 'KIDS' ? `<i class=\"fas fa-child\"></i> Instrumen Observasi: WLC Kids - \${targetGrade}` : `<i class=\"fas fa-user-graduate\"></i> Instrumen Kuesioner: WLC Teen (SMP)`;
        const selectEl = document.getElementById('instrumentFilterSelect');
        if (selectEl) selectEl.value = audience + '|' + (targetGrade || '');
        const searchEl = document.getElementById('instrumentSearchInput');
        if (searchEl) searchEl.value = '';
    ";

$dashContent = str_replace($oldLoad, $newLoad, $dashContent);

file_put_contents($dashPath, $dashContent);
echo "Done";
