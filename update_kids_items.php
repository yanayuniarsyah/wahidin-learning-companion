<?php
$db = new PDO('sqlite:wlc.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$updates = [
    // TK
    17 => "Anak tetap melihat materi belajarnya saat pengajar sedang memberikan penjelasan awal",
    19 => "Anak melakukan satu tindakan sesuai instruksi yang diberikan (misal: mengambil alat belajar)",
    24 => "Anak tetap duduk di kursinya saat proses pengerjaan tugas terhenti",
    27 => "Anak duduk menghadap meja selama proses pengerjaan tugas berlangsung (tidak memanjat/berbaring di kursi)",
    28 => "Anak menggunakan alat tulis hanya pada kertas tugas (tidak mencoret area di luarnya)",

    // SD 1-3
    30 => "Anak segera membuka buku atau lembar kerja setelah menempati kursi belajarnya",
    31 => "Anak terus memandangi dan mengerjakan lembar tugas meskipun ada pergerakan lain di sekitarnya",
    33 => "Anak melakukan serangkaian instruksi (misal: membuka halaman dan mengambil pensil) tanpa perlu pengulangan dari pengajar",
    37 => "Anak mengangkat tangan atau memanggil pengajar ketika berhenti mengerjakan soal",
    38 => "Anak mencoba menghapus jawaban atau melihat materi sebelum memanggil pengajar ketika terhenti",
    39 => "Anak memberikan respons verbal atau gestur ketika ditanya oleh pengajar terkait tugasnya",
    40 => "Anak dapat menunjuk bagian tugas yang telah selesai dikerjakan saat pengajar memeriksanya",
    42 => "Anak menempatkan alat belajar agar tidak berserakan hingga keluar dari area mejanya",

    // SD 4-6
    45 => "Anak tidak mengalihkan pandangan dari tugasnya ketika ada orang yang berjalan di sekitarnya",
    47 => "Anak mulai mengerjakan soal setelah membaca instruksi tertulis tanpa meminta penjelasan lisan tambahan",
    49 => "Anak membolak-balik halaman pekerjaannya kembali sesaat sebelum mengumpulkannya kepada pengajar",
    52 => "Anak tetap di posisinya melihat materi ketika terhenti pada bagian yang tidak dimengerti",
    53 => "Anak mengajukan pertanyaan lisan seputar penyelesaian tugas kepada pengajar",
    54 => "Anak menunjukkan bagian referensi yang ia gunakan untuk menjawab saat ditanya oleh pengajar",
    55 => "Anak tetap membaca atau menulis pada tugasnya ketika terdengar suara obrolan dari jarak sedang"
];

$stmt = $db->prepare("UPDATE wlc_items SET text = ? WHERE id = ?");
foreach ($updates as $id => $text) {
    $stmt->execute([$text, $id]);
}

echo "OK\n";
?>
