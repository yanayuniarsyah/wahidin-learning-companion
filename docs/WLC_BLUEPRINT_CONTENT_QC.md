# WLC Blueprint Content QC

## 1. Construct Validity — Content Level
- **Definisi & Batas:** Definisi sudah jelas dan batas antar-construct ditegaskan di bagian "Notes / Rationale" dan "Construct Overlap Analysis".
- **Kesesuaian Indikator:** Indikator turunan sudah sesuai dengan definisi operasional untuk SMP.
- **Overlap:** Sudah ada identifikasi *overlap* (Transisi vs Inisiatif, Keterlibatan vs Respons).
- **Status:** **PASS**

## 2. Methodology Fit
- **Framing:** Aturan *Self-Report* (e.g., "Menurut jawaban siswa...") diwajibkan secara eksplisit pada bagian "Narrative Rules".
- **Target:** Kalimat pada item kandidat disusun sebagai laporan-diri (menggunakan kata ganti "Saya"). Tidak ada *observer framing* (misal: "Siswa tampak...").
- **Status:** **PASS**

## 3. Scale & Scoring
- **Skala:** Likert 1–4 (Frekuensi: 1=Sangat Jarang, 4=Selalu) sudah konsisten dan aplikatif untuk seluruh item.
- **Reverse Scoring:** Formula matematika untuk *reverse scoring* (1→4, 4→1) sudah tepat.
- **Mean Calculation:** Penghitungan *mean per construct* secara matematis memetakan kembali nilai ke rentang 1.0–4.0 dengan valid.
- **Missing Response:** Aturan menolak kalkulasi (kewajiban pengisian) untuk menghindari error rata-rata sudah dicantumkan.
- **Thresholds:** Threshold baru (Foundational 1.0-2.0, Developing 2.1-3.2, Prominent 3.3-4.0) diturunkan secara logis dari skala Likert 1-4.
- **Status:** **PASS**

## 4. Interpretation
- **Prinsip:** Tidak menggunakan bahasa diagnosis, *personality labeling*, IQ, atau prediksi masa depan. Istilah "Learning Habits Profile" digunakan dengan tepat.
- **Status:** **PASS**

## 5. Content Governance
- `Ekspresi Perilaku` dinyatakan RETIRED dan tidak ada item untuknya.
- Tidak ada *legacy construct* (Kesiapan, Fokus, dll) yang diselundupkan secara diam-diam.
- Status *Draft* dan limitasi validasi dinyatakan dengan sangat gamblang di bagian atas dan bawah dokumen.
- **Status:** **PASS**

## 6. Construct ↔ Item Matrix

| Construct | Item ID | Item | Intended Construct | Cross-loading Risk | Double-barreled | Bias Risk | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Transisi ke Belajar | TR-1 | Saya segera menyiapkan buku dan alat tulis ketika waktu belajar dimulai. | Transisi | Low | Low | Medium | **PASS** |
| Transisi ke Belajar | TR-2 (R) | Saya butuh waktu lama untuk benar-benar mulai mengerjakan soal. | Transisi | Low | Low | Low | **PASS** |
| Keterlibatan Aktivitas | KA-1 | Saya menyelesaikan satu bagian tugas sebelum beralih ke aktivitas lain. | Keterlibatan | Low | Low | High | **PASS** |
| Keterlibatan Aktivitas | KA-2 (R) | Perhatian saya mudah teralihkan oleh hal lain saat sedang belajar. | Keterlibatan | Medium (vs Lingkungan) | Low | Low | **PASS** |
| Penerapan Instruksi | PI-1 | Saya membaca instruksi soal dengan teliti sebelum mulai menjawab. | Penerapan Instruksi | Low | Low | High | **PASS** |
| Penerapan Instruksi | PI-2 (R) | Saya langsung mengerjakan soal tanpa melihat contoh cara penyelesaian. | Penerapan Instruksi | Low | Low | Low | **PASS** |
| Inisiatif Penyelesaian| IP-1 | Saya mencoba mengerjakan soal sendiri terlebih dahulu sebelum bertanya. | Inisiatif | Medium (vs Partisipasi)| Low | Medium | **PASS** |
| Inisiatif Penyelesaian| IP-2 (R) | Saya harus selalu diingatkan agar segera menyelesaikan tugas. | Inisiatif | Medium (vs Transisi) | Low | Low | **PASS** |
| Respons thd Kendala | RK-1 | Jika menemui soal sulit, saya mencoba membacanya sekali lagi dengan lebih pelan. | Respons Kendala | Low | Low | Medium | **PASS** |
| Respons thd Kendala | RK-2 (R) | Saya langsung berhenti dan menyerah jika soalnya terlihat susah. | Respons Kendala | Low | Low | Low | **PASS** |
| Partisipasi Aktif | PA-1 | Saya mengecek kembali hasil pekerjaan saya sebelum menyatakannya selesai. | Partisipasi Aktif | Low | Low | High | **PASS** |
| Partisipasi Aktif | PA-2 | Saya bertanya kepada guru atau teman jika ada bagian yang benar-benar tidak saya pahami. | Partisipasi Aktif | Low | **High** ("guru atau teman") | Medium | **REVISE** |
| Lingkungan Belajar | LB-1 | Saya memastikan tempat belajar saya rapi dari barang yang tidak diperlukan. | Lingkungan Belajar | Low | Low | High | **PASS** |
| Lingkungan Belajar | LB-2 (R) | Saya terbiasa belajar sambil menyalakan televisi atau media sosial yang tidak terkait pelajaran. | Lingkungan Belajar | Low | **High** ("televisi atau media sosial")| Low | **REVISE** |

**Catatan Revisi:**
- **PA-2:** Memuat "guru atau teman" (*double-barreled*). Siswa mungkin berani bertanya ke teman tapi takut ke guru, sehingga ia akan bingung menjawab. *Rekomendasi*: Pisahkan menjadi dua item atau gunakan istilah umum "orang lain / pembimbing".
- **LB-2:** Memuat "televisi atau media sosial" (*double-barreled*). *Rekomendasi*: Gunakan istilah "hal yang menimbulkan suara/gangguan (seperti televisi/gadget)".

## FINAL OUTPUT

STATUS: DRAFT REVIEWED
CRITICAL ISSUES: 0
HIGH ISSUES: 2 (Double-barreled items)
MEDIUM ISSUES: 0
LOW ISSUES: 0
ITEMS PASS: 12
ITEMS REVISE: 2
ITEMS REJECT: 0

FINAL DECISION: **SME_READY**
Blueprint secara konten sudah cukup koheren, terstruktur, dan selaras secara metodologis untuk dibawa ke tahap **Human SME Validation**, asalkan 2 item yang terindikasi *double-barreled* direvisi oleh *Subject Matter Expert* (SME) saat validasi dilakukan.
