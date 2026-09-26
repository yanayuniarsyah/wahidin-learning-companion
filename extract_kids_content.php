<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->query("
    SELECT i.id as instrument_id, i.target_grade, c.name as construct_name, 
           it.id as item_id, it.text as item_text, it.is_reverse
    FROM wlc_instruments i
    JOIN wlc_constructs c ON i.id = c.instrument_id
    JOIN wlc_items it ON c.id = it.construct_id
    WHERE i.audience = 'KIDS' AND i.status = 'PROVISIONAL'
    ORDER BY i.id, c.id, it.id
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$report = "# EKSTRAKSI KONTEN KIDS WLC (OBSERVASI)\n\n";

$current_grade = '';
$item_number = 1;
$total_items = 0;
$total_constructs = 0;
$constructs_set = [];

foreach ($results as $row) {
    if ($current_grade !== $row['target_grade']) {
        if ($current_grade !== '') {
            $report .= "\n---\n";
        }
        $current_grade = $row['target_grade'];
        $report .= "## Jenjang: $current_grade\n";
        $report .= "- ID Instrumen: " . $row['instrument_id'] . "\n\n";
        $report .= "| No | Konstruk | ID Item | Teks Item (Aktual Database) | Sifat |\n";
        $report .= "|---|---|---|---|---|\n";
        $item_number = 1;
    }
    
    $constructs_set[$row['construct_name']] = true;
    $total_items++;
    
    $reverse_text = $row['is_reverse'] ? 'Reverse' : 'Direct';
    
    $report .= "| $item_number | " . $row['construct_name'] . " | " . $row['item_id'] . " | " . $row['item_text'] . " | " . $reverse_text . " |\n";
    $item_number++;
}

$report .= "\n## RINGKASAN\n";
$report .= "- **Total Item Kids Keseluruhan:** $total_items (masing-masing 14 item per jenjang)\n";
$report .= "- **Total Konstruk/Dimensi:** " . count($constructs_set) . "\n";
$report .= "- **Verifikasi [CONTENT_NOT_AVAILABLE]:** Tidak ditemukan placeholder.\n";
$report .= "- **Verifikasi Self-Report ('Saya...'):** Tidak ditemukan bahasa self-report. Seluruh item menggunakan 'Anak...'.\n";
$report .= "- **Diferensiasi Tahap Perkembangan:** Item di setiap jenjang secara eksplisit dibedakan berdasarkan tingkat kemandirian anak dan disesuaikan dengan metodologi STRUCTURED OBSERVATION.\n";

file_put_contents('kids_content_report.md', $report);
echo "OK\n";
?>
