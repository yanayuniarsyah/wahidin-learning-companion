<?php
// make_instrument_communicative.php
$dashPath = 'dashboard.html';
$dashContent = file_get_contents($dashPath);

// Find the start of manageSoalDashboard
$startMarker = '<div id="manageSoalDashboard" class="hidden">';
$endMarker = '</div>
<div id="manageCertDashboard" class="hidden">';

$startIndex = strpos($dashContent, $startMarker);
$endIndex = strpos($dashContent, $endMarker);

if ($startIndex !== false && $endIndex !== false) {
    // Extract the section
    $oldSection = substr($dashContent, $startIndex, $endIndex - $startIndex);
    
    // New Communicative Section
    $newSection = <<<EOD
<div id="manageSoalDashboard" class="hidden">
<header class="panel-header">
    <button class="btn btn-secondary" onclick="exitManageSoal()"><i class="fas fa-arrow-left"></i> Kembali</button>
    <h1><i class="fas fa-book"></i> Manajemen Instrumen (WLC)</h1>
</header>

<div style="background:var(--secondary-soft); padding:1.5rem; border-radius:var(--radius-md); margin-bottom:2rem;">
    <div style="margin-bottom: 1.5rem;">
        <h3 style="margin: 0 0 0.5rem 0; color:var(--primary);"><i class="fas fa-info-circle"></i> Kumpulan Alat Ukur</h3>
        <p style="margin: 0; color:var(--text-muted); font-size: 0.95rem;">
            Halaman ini adalah pusat pengelolaan instrumen pengumpulan data. 
            Anda dapat melihat dan menambahkan item pengamatan (untuk Observer WLC Kids) atau item kuesioner (untuk Self-Report WLC Teen).
        </p>
    </div>
    
    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
        <div class="form-group" style="margin:0; flex:1; min-width:200px;">
            <label class="form-label" style="font-size:0.8rem;">Filter Kategori Instrumen</label>
            <select class="form-input" id="instrumentFilterSelect" onchange="const val = this.value.split('|'); loadWLCInstruments(val[0], val[1] || null);">
                <option value="KIDS|TK">WLC Kids - TK (Observasi)</option>
                <option value="KIDS|SD 1-3">WLC Kids - SD 1-3 (Observasi)</option>
                <option value="KIDS|SD 4-6">WLC Kids - SD 4-6 (Observasi)</option>
                <option value="SMP/TEEN|">WLC Teen / SMP (Kuesioner)</option>
            </select>
        </div>
        <div class="form-group" style="margin:0; flex:1; min-width:200px;">
            <label class="form-label" style="font-size:0.8rem;">Cari Konstruk / Pertanyaan</label>
            <input type="text" id="instrumentSearchInput" class="form-input" placeholder="Ketik kata kunci..." onkeyup="filterInstrumentTable()">
        </div>
        <div style="display:flex; gap:0.5rem;">
            <button class="btn btn-primary" onclick="document.getElementById('soalFormContainer').classList.remove('hidden')"><i class="fas fa-plus"></i> Tambah Item</button>
        </div>
    </div>
</div>

<h3 id="instrumentTitle" style="margin-bottom:15px; font-weight:600; font-size:1.2rem; border-bottom: 2px solid var(--border); padding-bottom: 10px;">WLC Kids - TK</h3>
<table class="data-table" id="soalTable">
    <thead>
        <tr>
            <th style="width:50px;">No</th>
            <th>Komponen / Konstruk</th>
            <th>Pernyataan Observasi / Kuesioner</th>
            <th style="text-align:center;width:150px;">Aksi</th>
        </tr>
    </thead>
    <tbody id="soalTableBody">
        <tr><td colspan="4" style="text-align:center;padding:2rem;">Memuat instrumen...</td></tr>
    </tbody>
</table>
EOD;

    $dashContent = str_replace($oldSection, $newSection, $dashContent);
    
    // Also inject the search filter function into the script
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
    
    // make sure the loadSoalTable script correctly handles select value updates if it's called
    $oldLoad = "let title = audience === 'KIDS' ? `WLC Kids - \${targetGrade}` : 'WLC Teen (Kuesioner SMP)';";
    $newLoad = "let title = audience === 'KIDS' ? `Instrumen Observasi: WLC Kids - \${targetGrade}` : 'Instrumen Kuesioner: WLC Teen (SMP)';
        const selectEl = document.getElementById('instrumentFilterSelect');
        if (selectEl) selectEl.value = audience + '|' + (targetGrade || '');
        document.getElementById('instrumentSearchInput').value = '';
    ";
    
    $dashContent = str_replace($oldLoad, $oldLoad . "\n" . $newLoad, $dashContent);

    file_put_contents($dashPath, $dashContent);
    echo "Done";
} else {
    echo "Could not find markers";
}
?>
