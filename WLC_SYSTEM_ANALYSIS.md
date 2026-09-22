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

WLC berfungsi sebagai **sistem observasi dan pendampingan kebiasaan belajar anak berbasis perilaku yang dapat diamati**. Indikator di dalamnya dioperasionalkan dari perilaku nyata anak di lingkungan belajar. Pendekatan ini menggeser fokus dari **Penilaian Sumatif** ke **Penilaian Formatif dan Observasional** (memantau proses, bukan sekadar menghakimi hasil), dan secara sadar menghindari klaim diagnosis klinis.

### 3.1. WLC Learning Habit Framework (7 Dimensi Observasi)

Setiap komponen dirancang murni untuk menerjemahkan perilaku nyata (*observable behaviors*), bukan menyimpulkan profil neurobiologis atau kognitif secara sepihak:

| Dimensi | Fokus Observasi | Indikator Perilaku Utama |
| :--- | :--- | :--- |
| **1. Kesiapan Belajar** | Transisi dan kesiapan memulai aktivitas. | Beralih dari aktivitas sebelumnya, menyiapkan alat belajar, merespons rutinitas awal. |
| **2. Keterlibatan & Fokus** | Kemampuan mempertahankan keterlibatan terhadap tugas. | Rentang durasi mengerjakan tugas tanpa mengalihkan pandangan atau aktivitas secara berlebihan. |
| **3. Respons Instruksi** | Kemampuan memahami dan menjalankan instruksi. | Kemampuan memulai langkah yang diminta setelah instruksi diberikan tanpa pengulangan berlebih. |
| **4. Kemandirian Belajar**| Kemampuan mencoba tugas tanpa bantuan berlebihan. | Mencoba sebelum meminta bantuan, merujuk pada contoh, dan inisiatif mengevaluasi kerja sendiri. |
| **5. Ketekunan Tantangan**| Respons ketika menghadapi kesulitan. | Upaya untuk tetap mencoba kembali setelah melakukan kesalahan atau menemukan soal sulit. |
| **6. Respons Kesulitan** | Cara anak merespons frustrasi dan dukungan. | Reaksi emosional saat terhambat (tetap lanjut, minta tolong, atau berhenti sementara), serta respons terhadap dorongan. |
| **7. Engagement Belajar** | Keterlibatan dan kemauan mengikuti aktivitas belajar. | Bersedia memulai, menunjukkan ketertarikan pada tugas, dan kemauan menyelesaikan sesi. |

### 3.2. Jembatan Ekologis: Parent Reflection
Mengacu pada teori *Ecological Systems* (Bronfenbrenner), WLC memetakan **Mesosystem** dengan menghubungkan mikrosistem Kumon dan mikrosistem rumah.
- Data ini memungkinkan konselor melihat disparitas perilaku (misal: mandiri di kelas, butuh banyak dorongan di rumah).
- Temuan ini diposisikan sebagai sumber data berbeda (*complementary*) yang digunakan sebagai amunisi komunikasi konseling secara objektif, tanpa menyudutkan orang tua.

---

## 4. Metodologi Observasi & Peta Jalan Validasi

Untuk memastikan WLC App beroperasi sebagai instrumen ukur yang andal, sistem ini menerapkan standar metodologi observasi perilaku yang ketat:

### 4.1. Membangun Reliabilitas Antar-Rater (BARS)
Masalah terbesar dalam observasi lapangan adalah subjektivitas (bias pengamat). Untuk menekan hal ini, WLC App tidak lagi menggunakan skala numerik bebas, melainkan **Behaviorally Anchored Rating Scale (BARS)**. 
- Alih-alih menebak skor 1–4, Asisten Kumon disuguhkan dengan deskriptor perilaku konkret pada setiap dimensi. 
- Asisten mencocokkan apa yang mereka lihat secara riil. Namun, BARS saja tidak otomatis menghasilkan reliabilitas tinggi tanpa definisi operasional yang ketat dan pelatihan.
- **Kalibrasi**: Secara berkala, perlu diadakan sesi *Rater Calibration*, di mana asisten menonton rekaman video yang sama dan menyamakan persepsi observasi mereka.

### 4.2. Batasan Klaim dan Etika Data
1. **Fase Pengumpulan**: Asisten menghimpun data murni tanpa bias atau asumsi klinis.
2. **Standar Etika Klaim**: Materi laporan sistem ini diposisikan murni sebagai **"Alat Observasi dan Pendampingan"**, dan secara tegas dilarang disebut sebagai "Alat Diagnostik Psikometri Klinis", "Tes IQ", atau alat pembuat profil kepribadian individual. Hal ini menjaga kehati-hatian etis terhadap subjek anak.
3. **Privasi Anak**: Data observasi perilaku wajib dikelola dengan sistem *legal/ethical governance* yang menjamin kerahasiaan anak.

---

## 5. Kesimpulan

**WLC App** membuktikan bahwa teknologi konvensional (*Vanilla JS, PHP, SQLite*) bila diarsiteki dengan cermat (SPA, JWT, WAL), mampu menghasilkan platform kelas *enterprise* yang berbiaya rendah dan sangat responsif.

Lebih dari itu, WLC bukan instrumen diagnosa, melainkan **Sistem Pendampingan Kebiasaan Belajar**. Dengan memadukan 7 Dimensi Perilaku yang dapat diamati dan pendekatan kolaboratif, sistem ini mempersenjatai tenaga pendidik dengan pemahaman objektif tentang *Learning Habit Profile* tiap anak, demi mewujudkan pendidikan yang benar-benar berpusat pada perkembangan positif (*Child-Centered Education*).
