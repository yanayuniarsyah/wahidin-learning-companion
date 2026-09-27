<?php
$c = file_get_contents('dashboard.html');
$target = "    <div class=\"form-group\"><label class=\"form-label\">Pertanyaan</label><input type=\"text\" class=\"form-input\" id=\"newSoalPertanyaan\" placeholder=\"Pertanyaan lengkap...\"></div>\n    <button class=\"btn btn-primary\" onclick=\"saveNewSoal()\" style=\"width:100%;margin-top:0.5rem;\"><i class=\"fas fa-save\"></i> Simpan Soal</button>\n</div>";
$target2 = "    <div class=\"form-group\"><label class=\"form-label\">Pertanyaan</label><input type=\"text\" class=\"form-input\" id=\"newSoalPertanyaan\" placeholder=\"Pertanyaan lengkap...\"></div>\r\n    <button class=\"btn btn-primary\" onclick=\"saveNewSoal()\" style=\"width:100%;margin-top:0.5rem;\"><i class=\"fas fa-save\"></i> Simpan Soal</button>\r\n</div>";
$c = str_replace($target, "", $c);
$c = str_replace($target2, "", $c);
file_put_contents('dashboard.html', $c);
echo "Cleaned stray div";
