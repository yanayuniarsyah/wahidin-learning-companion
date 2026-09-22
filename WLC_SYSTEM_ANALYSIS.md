# Analisis Komprehensif Sistem WLC App: Teknologi dan Substansi Psikologi

Dokumen ini merupakan tinjauan analitis komprehensif mengenai **WLC (Wahidin Learning Companion) App**. Tinjauan ini membedah arsitektur teknologi yang menopang sistem, landasan metodologi psikologi pendidikan di dalamnya, serta catatan kritis untuk pengembangan selanjutnya. Dokumen ini dirancang agar mudah dibaca oleh pemangku kepentingan maupun dianalisis oleh AI.

---

## 1. Tinjauan Umum & Positioning Sistem

**WLC App** diposisikan bukan sebagai sistem penilai akademis, melainkan sebagai instrumen **observasi perilaku belajar** dengan *tagline* utama: 
> *"Kami tidak menilai anak. Kami mendampingi kebiasaan belajarnya."*

Sistem ini memfasilitasi **Kolaborasi Ekologis Tiga Arah** dalam ekosistem pendidikan anak:
1. **Owner/Evaluator**: Mengawasi tren keseluruhan, melakukan *Growth Check*, dan merumuskan intervensi.
2. **Asisten**: Berperan sebagai observer klinis di lapangan yang mencatat data empiris saat sesi belajar berlangsung.
3. **Orang Tua**: Memberikan data komplementer terkait perilaku anak di mikrosistem rumah (melalui modul *Parent Reflection*).

---

## 2. Analisis Arsitektur Teknologi

Sistem WLC App sengaja didesain dengan prinsip **Ringan, Portabel, dan Independen**. Pendekatan ini (tanpa *framework* berat) memastikan sistem dapat berjalan optimal pada infrastruktur kelas menengah (seperti *Shared Hosting* cPanel) yang biasa digunakan oleh unit cabang pendidikan independen.

### 2.1. Arsitektur Frontend
* **Single Page Application (SPA)**: Dibangun murni dengan **Vanilla HTML, CSS, dan JavaScript**. 
* **Zero Page-Load Time**: Transisi antarmuka dikendalikan secara dinamis via manipulasi DOM, memberikan pengalaman pengguna (*User Experience*) setara aplikasi native yang responsif.
* **Client-Side Generation**: Pencetakan PDF dan Sertifikat dieksekusi di *client-side* menggunakan `jspdf` dan `html2canvas`. Pendekatan ini dilengkapi dengan blokade validasi (mencegah *auto-fill* data kosong) sebelum dirender, guna memastikan integritas laporan.

### 2.2. Arsitektur Backend & Database
* **PHP Native (v8.x)**: Berjalan sebagai penyedia *RESTful API Endpoint* yang efisien (`api.php`).
* **SQLite Database (`wlc.db`)**: Sangat taktis untuk aplikasi *read-heavy* berskala unit lokal. Memudahkan *backup* dan portabilitas.
* **Write-Ahead Logging (WAL)**: Diaktifkan melalui `PRAGMA journal_mode = WAL` dan `busy_timeout = 5000` untuk meminimalisasi risiko *Write Lock Contention* saat multi-user (Asisten dan Orang Tua) men-submit data bersamaan.
* **Dynamic Parameterized Handler**: Semua operasi CRUD melalui API dibatasi oleh *whitelist* kolom yang ketat (*hardcoded array*) dan diamankan dari *SQL Injection* menggunakan *parameterized binding* pada ekstensi PDO.

### 2.3. Keamanan (Security)
* **JWT (JSON Web Tokens)**: Menangani autentikasi *stateless*. File *secret key* (`.jwt_secret`) diproteksi penuh dari akses publik via `.htaccess`.
* **Kriptografi BCRYPT**: Melindungi *password* pengguna dengan algoritma *hashing* satu arah standar industri yang kebal terhadap serangan *brute-force*.

---

## 3. Analisis Substansi Psikologi dan Perilaku

WLC berfungsi sebagai instrumen diagnostik observasional. Indikator di dalamnya dioperasionalkan dari teori kognitif dan perilaku anak. Pendekatan ini menggeser fokus dari **Penilaian Sumatif** ke **Penilaian Formatif dan Observasional** (memantau proses, bukan sekadar menghakimi hasil).

### 3.1. 7 Dimensi Psikologis yang Diobservasi

Setiap komponen dirancang untuk menerjemahkan atribut mental abstrak menjadi perilaku nyata (*observable behaviors*):

| Dimensi | Indikator Utama | Landasan Teori Psikologi |
| :--- | :--- | :--- |
| **1. Kesiapan** | Transisi dari aktivitas bermain; postur awal. | **Executive Function**: Mengukur *task switching* dan regulasi diri di fase awal. |
| **2. Fokus** | Durasi bertahan, rentan distraksi atau tidak. | **Sustained Attention**: Relevan mendeteksi dini perilaku rentan distraksi yang perlu intervensi khusus. |
| **3. Instruksi** | Pemahaman lisan, inisiatif bertanya. | **Receptive Language & Working Memory**: Kapasitas memproses bahasa. |
| **4. Kemandirian**| Inisiatif mencoba sendiri tanpa *scaffolding*. | **Self-Regulated Learning (SRL)**: Mencegah sindrom *learned helplessness*. |
| **5. Ketekunan** | Reaksi saat soal sulit atau salah (mencoba ulang). | **Grit & Growth Mindset (Duckworth/Dweck)**: Resiliensi anak terhadap kegagalan. |
| **6. Emosi** | Respons gagal/berhasil, tingkat frustrasi. | **Emotional Regulation (EQ)**: Kemampuan *self-soothing* saat amigdala terpicu. |
| **7. Minat** | Antusiasme, motivasi melanjutkan tanpa paksaan. | **Intrinsic Motivation (Deci & Ryan)**: Dorongan belajar dari dalam diri. |

### 3.2. Jembatan Ekologis: Parent Reflection
Mengacu pada teori *Ecological Systems* (Bronfenbrenner), WLC memetakan **Mesosystem** dengan menghubungkan mikrosistem Kumon dan mikrosistem rumah.
- Data ini memungkinkan konselor melihat disparitas perilaku (misal: mandiri di kelas, manja di rumah).
- Temuan digunakan sebagai amunisi komunikasi konseling secara objektif dan kolaboratif, tanpa menyudutkan orang tua.

---

## 4. Metodologi Diagnostik & Peta Jalan Validasi

Untuk memastikan WLC App beroperasi sebagai instrumen ukur psikologi yang andal dan bukan sekadar asumsi, sistem ini menerapkan standar metodologi yang ketat melalui **Dua Jalur Validasi**:

### 4.1. Membangun Reliabilitas Antar-Rater (BARS)
Masalah terbesar dalam observasi lapangan adalah subjektivitas (bias pengamat). Untuk menekan hal ini, WLC App tidak lagi menggunakan skala numerik bebas, melainkan **Behaviorally Anchored Rating Scale (BARS)**. 
- Alih-alih menebak skor 1–4, Asisten Kumon disuguhkan dengan UI berupa **kartu deskriptor perilaku konkret** pada setiap dimensi (Kesiapan, Fokus, Ketekunan, dll). 
- Asisten hanya perlu mencocokkan apa yang mereka lihat secara riil dengan teks di aplikasi. Pendekatan ini membuat hasil skor Asisten A dan Asisten B akan sangat konsisten terhadap anak yang sama (*High Inter-Rater Reliability*).
- **Kalibrasi**: Secara berkala, perlu diadakan sesi *Rater Calibration*, di mana asisten menonton rekaman video yang sama dan menyamakan persepsi observasi mereka.

### 4.2. Peta Jalan Validasi Kriterion (*Criterion Validity Roadmap*)
Validasi dilakukan tidak secara teoritis, tapi dibuktikan melalui korelasi data empiris (*Longitudinal Data*).
1. **Fase Pengumpulan (Bulan 1-6)**: Asisten menggunakan instrumen BARS untuk menghimpun data murni tanpa bias diagnostik.
2. **Fase Korelasi (Bulan 7)**: Melakukan analisis korelasi silang (misal menggunakan Pearson `CORREL()` di Excel) antara *Rerata Skor Ketekunan & Kemandirian* dengan data objektif *Kecepatan Kenaikan Level Kumon* (lembar/level per bulan).
3. **Standar Etika Klaim (Pembatasan)**: Sebelum korelasi tersebut terbukti kuat secara statistik (dengan $n \ge 30$ sampel kontinu), materi laporan sistem ini diposisikan murni sebagai **"Alat Observasi dan Pendampingan"**, bukan "Alat Diagnostik Psikometri Klinis". Hal ini menjaga kehati-hatian etis terhadap subjek anak.

---

## 5. Kesimpulan

**WLC App** membuktikan bahwa teknologi konvensional (*Vanilla JS, PHP, SQLite*) bila diarsiteki dengan cermat (SPA, JWT, WAL), mampu menghasilkan platform kelas *enterprise* yang berbiaya rendah dan sangat responsif.

Lebih dari itu, WLC bukan sekadar "perekam data akademis", melainkan **Instrumen Profiling Perilaku Anak**. Dengan memadukan 7 Dimensi Psikologis dan pendekatan kolaboratif, sistem ini mempersenjatai tenaga pendidik dengan pemahaman mendalam tentang *Learning Profile* tiap anak, demi mewujudkan pendidikan yang benar-benar berpusat pada perkembangan mental anak (*Child-Centered Education*).
