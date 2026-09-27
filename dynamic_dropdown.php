<?php
$file = 'dashboard.html';
$content = file_get_contents($file);

$targetStart = '<div class="form-group">';
$targetEnd = '</select>' . "\n" . '    </div>';

$oldHtml = <<<EOD
    <div class="form-group">
        <label class="form-label">Tujuan Penambahan (Audience / Grade)</label>
        <select class="form-input" id="newSoalTarget">
            <option value="KIDS|TK">WLC Kids - TK</option>
            <option value="KIDS|SD 1-3">WLC Kids - SD 1-3</option>
            <option value="KIDS|SD 4-6">WLC Kids - SD 4-6</option>
            <option value="TEEN|SMP">WLC Teen / SMP</option>
        </select>
    </div>
        <div class="form-group">
        <label class="form-label">Komponen / Konstruk</label>
        <select class="form-input" id="newSoalKomponen">
            <optgroup label="WLC Kids (Observasi)">
                <option value="Kesiapan">Kesiapan</option>
                <option value="Fokus">Fokus</option>
                <option value="Instruksi">Instruksi</option>
                <option value="Kemandirian">Kemandirian</option>
                <option value="Ketekunan">Ketekunan</option>
                <option value="Emosi">Emosi</option>
                <option value="Minat">Minat</option>
            </optgroup>
            <optgroup label="WLC Teen (Kuesioner)">
                <option value="Regulasi Emosi">Regulasi Emosi</option>
                <option value="Motivasi Belajar">Motivasi Belajar</option>
                <option value="Kemandirian Belajar">Kemandirian Belajar</option>
                <option value="Manajemen Waktu">Manajemen Waktu</option>
                <option value="Resiliensi">Resiliensi</option>
            </optgroup>
        </select>
    </div>
EOD;

$newHtml = <<<EOD
    <div class="form-group">
        <label class="form-label">Tujuan Penambahan (Audience / Grade)</label>
        <select class="form-input" id="newSoalTarget" onchange="updateKomponenOptions(this.value)">
            <option value="KIDS|TK">WLC Kids - TK</option>
            <option value="KIDS|SD 1-3">WLC Kids - SD 1-3</option>
            <option value="KIDS|SD 4-6">WLC Kids - SD 4-6</option>
            <option value="TEEN|SMP">WLC Teen / SMP</option>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Komponen / Konstruk</label>
        <select class="form-input" id="newSoalKomponen">
            <!-- Populated by JS -->
        </select>
    </div>
    <script>
        const KIDS_KOMPONEN = ['Kesiapan','Fokus','Instruksi','Kemandirian','Ketekunan','Emosi','Minat'];
        const TEEN_KOMPONEN = ['Regulasi Emosi','Motivasi Belajar','Kemandirian Belajar','Manajemen Waktu','Resiliensi'];
        function updateKomponenOptions(targetVal) {
            const select = document.getElementById('newSoalKomponen');
            if(!select) return;
            select.innerHTML = '';
            const opts = targetVal.startsWith('KIDS') ? KIDS_KOMPONEN : TEEN_KOMPONEN;
            opts.forEach(opt => {
                const el = document.createElement('option');
                el.value = opt;
                el.textContent = opt;
                select.appendChild(el);
            });
        }
        // Initialize once
        setTimeout(() => {
            const tgt = document.getElementById('newSoalTarget');
            if(tgt) updateKomponenOptions(tgt.value);
        }, 500);
    </script>
EOD;

$content = str_replace($oldHtml, $newHtml, $content);
file_put_contents($file, $content);
echo "Updated to dynamic dropdowns";
