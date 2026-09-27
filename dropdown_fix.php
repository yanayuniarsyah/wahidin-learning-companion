<?php
$file = 'dashboard.html';
$content = file_get_contents($file);

$target = '<div class="form-group"><label class="form-label">Komponen / Konstruk (Misal: Transisi ke Belajar)</label><input type="text" class="form-input" id="newSoalKomponen" placeholder="Nama Komponen"></div>';
$replacement = <<<EOD
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

$content = str_replace($target, $replacement, $content);
file_put_contents($file, $content);
echo "Replaced input with select";
