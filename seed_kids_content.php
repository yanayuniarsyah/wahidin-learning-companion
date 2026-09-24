<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');

$constructs = [
    'Transisi ke Belajar', 'Keterlibatan terhadap Aktivitas', 'Penerapan Instruksi', 
    'Inisiatif Penyelesaian', 'Respons terhadap Kendala', 'Partisipasi Aktif', 'Lingkungan Belajar'
];

$items_data = [
    'TK' => [
        "Anak bersedia duduk di kursi belajarnya saat sesi dimulai", "Anak meletakkan alat bermainnya saat pengajar memberi aba-aba waktu belajar",
        "Anak mempertahankan kontak mata pada materi selama minimal 3 menit", "Anak menunjuk atau menyentuh materi belajar saat diarahkan",
        "Anak mengikuti instruksi tunggal dengan tepat", "Anak memulai aktivitas setelah pengajar memberikan contoh atau panduan awal",
        "Anak berusaha menyelesaikan satu aktivitas/lembar kerja penuh sebelum berpindah", "Anak tetap berada di area belajar hingga instruksi selesai diberikan",
        "Anak memberikan gestur atau meminta bantuan secara langsung saat menemui hambatan", "Anak tidak menangis atau melempar barang ketika kesulitan",
        "Anak merespons arahan pengajar melalui ucapan singkat, anggukan, atau gelengan", "Anak menirukan intruksi gerak atau suara dari pengajar",
        "Anak mempertahankan posisi tubuh yang sesuai untuk belajar", "Anak menggunakan alat tulis sesuai dengan fungsinya tanpa merusak"
    ],
    'SD 1-3' => [
        "Anak meletakkan buku dan alat tulis di meja secara mandiri", "Anak mulai fokus bekerja dalam waktu kurang dari 3 menit sejak duduk",
        "Anak memusatkan perhatian pada lembar tugas selama minimal 10 menit tanpa terdistraksi", "Anak menulis atau membaca materi tanpa peringatan lisan yang berulang",
        "Anak melaksanakan instruksi majemuk (dua tahap atau lebih) dengan urutan yang benar", "Anak mengaplikasikan cara pengerjaan yang baru saja dicontohkan",
        "Anak menyelesaikan seluruh tugas di halamannya sebelum berpaling ke hal lain", "Anak membalik halaman dan melanjutkan tugas secara mandiri",
        "Anak mengangkat tangan atau bertanya lisan secara wajar saat tidak mengerti", "Anak mencoba melihat kembali soal sebelum mencari bantuan pengajar",
        "Anak menjawab pertanyaan pengajar dengan kalimat verbal yang relevan dengan topik", "Anak menceritakan kembali secara singkat aktivitas yang baru dikerjakan",
        "Anak tidak memainkan alat tulis di luar konteks pengerjaan tugas", "Anak mempertahankan kerapian material belajar di mejanya"
    ],
    'SD 4-6' => [
        "Anak langsung memeriksa daftar tugas atau materinya sesaat setelah tiba", "Anak melakukan rutinitas awal belajar tanpa bimbingan pengajar",
        "Anak mempertahankan fokus pada tugas utamanya walau ada pergerakan di sekitarnya", "Anak secara proaktif membuat catatan atau menandai bagian penting",
        "Anak membaca dan melaksanakan instruksi tertulis dengan benar", "Anak langsung memperbaiki kesalahannya setelah mendapat petunjuk singkat",
        "Anak secara sadar meninjau ulang pekerjaannya sebelum dianggap selesai", "Anak memilah dan mengerjakan tugas yang tersisa tanpa teguran",
        "Anak mencari referensi dari contoh soal yang ada sebelum bertanya pada pengajar", "Anak tidak menunjukkan perilaku disruptif saat tugas terasa menantang",
        "Anak memberikan pertanyaan balikan yang memperluas topik yang sedang dipelajari", "Anak dapat menjelaskan proses berpikir atau cara menemukan jawaban",
        "Anak mampu mengabaikan gangguan suara skala ringan dari luar area kerjanya", "Anak mengembalikan perlengkapan belajar ke lokasi semula setelah usai"
    ]
];

// Drop old Kids instrument
$db->exec("DELETE FROM wlc_instruments WHERE audience = 'KIDS'");

$insertInst = $db->prepare("INSERT INTO wlc_instruments (version, methodology, status, audience, target_grade) VALUES (1, 'STRUCTURED OBSERVATION', 'PROVISIONAL', 'KIDS', ?)");
$insertConst = $db->prepare("INSERT INTO wlc_constructs (instrument_id, name) VALUES (?, ?)");
$insertItem = $db->prepare("INSERT INTO wlc_items (construct_id, text, is_reverse) VALUES (?, ?, 0)");

foreach ($items_data as $grade => $items) {
    $insertInst->execute([$grade]);
    $inst_id = $db->lastInsertId();
    
    $item_idx = 0;
    foreach ($constructs as $c_name) {
        $insertConst->execute([$inst_id, $c_name]);
        $c_id = $db->lastInsertId();
        
        $insertItem->execute([$c_id, $items[$item_idx++]]);
        $insertItem->execute([$c_id, $items[$item_idx++]]);
    }
}
echo "Kids instruments populated.\n";
?>
