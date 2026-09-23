# Wahidin Learning Companion (WLC) - Dokumen Analisis Teknis & Sistem

Dokumen ini disusun sebagai panduan arsitektur sistem, struktur database, dan teknologi backend/frontend aplikasi WLC. Tujuannya adalah untuk memberikan konteks teknis yang komprehensif bagi AI (Artificial Intelligence) atau *developer* lain yang akan menganalisa, mengembangkan, atau melakukan *security audit* pada sistem ini.

---

## 1. Arsitektur Perangkat Lunak (Software Architecture)
WLC dibangun menggunakan pendekatan **SPA (Single Page Application) hibrida** yang sangat ringan, tanpa *framework* berat (seperti React/Vue), untuk memaksimalkan kompatibilitas dan menekan biaya *hosting*.

* **Frontend:** HTML5, Vanilla JavaScript, CSS3 (menggunakan CSS Variables untuk manajemen *theme/design system*).
* **Backend:** PHP *Native* (REST API).
* **Database:** SQLite 3 (`wlc.db`), disimpan secara lokal pada disk untuk portabilitas maksimal.
* **Paradigma Komunikasi:** Asynchronous RESTful API menggunakan Fetch API, dengan format respons standar JSON.

---

## 2. Struktur Database (SQLite Schema)
Skema database sepenuhnya relasional dan di-*auto-generate* oleh `api.php` jika file `wlc.db` belum ada.

### Tabel Utama:
1. **`users`**
   - Kolom: `id`, `role` (owner, evaluator, asisten), `username`, `password` (BCRYPT hash).
2. **`sekolah`**, **`kelas`**, **`siswa`**
   - Ketiganya memiliki relasi hierarkis (Siswa berada di sebuah Kelas, Kelas berada di sebuah Sekolah).
   - Validasi `nisn` unik (UNIQUE constraint).
3. **`bank_soal`**
   - Menampung komponen instrumen observasi.
   - Kolom: `wlc` (level/modul), `type`, `komponen`, `indikator`, `pertanyaan`, `contoh`.
4. **`observasi`**
   - Tabel *transactional* untuk mencatat skor.
   - Relasi: `siswaId`, `soalId`, `asistenId` (siapa yang menilai).
   - Menyimpan *timestamp* untuk histori progres.
5. **`parent_reflections`**
   - Menyimpan isian form dari orang tua (Kesiapan, Fokus, Kemandirian, Ketekunan, Emosional, Minat).
6. **`jadwal`** & **`grup`**
   - Sistem *grouping* siswa per jadwal kunjungan asisten. Menyimpan array `siswaIds` (format JSON/String).

---

## 3. Keamanan Sistem (Security Mechanisms)
Sistem ini menggunakan perlindungan keamanan standar industri untuk aplikasi berbasis API:

### A. Autentikasi (Authentication)
* **Token JWT (JSON Web Token):** Login menghasilkan token algoritma `HS256` yang valid selama 24 jam. Kunci rahasia (Secret Key) di-generate secara acak dan disimpan secara permanen di file `.jwt_secret`.
* **Token Transmission:** Frontend mengirimkan token melalui Header HTTP (`Authorization: Bearer <token>`), dengan *fallback* pada header `X-WLC-TOKEN` atau query `?token=` (berguna untuk endpoint *print/download* khusus).
* **Hashing Password:** Password tidak pernah disimpan dalam *plaintext*. Enkripsi menggunakan fungsi standar PHP `password_hash()` dengan algoritma `PASSWORD_BCRYPT` (Cost 10).

### B. Otorisasi (Role-Based Access Control / RBAC)
API memvalidasi nilai klaim `role` di dalam JWT payload sebelum memproses data:
* **Owner:** Akses penuh ke semua endpoint (termasuk *Settings*, Manajemen User).
* **Evaluator:** Akses CRUD pada jadwal, sekolah, kelas, siswa, dan grup.
* **Asisten:** Hanya dapat membaca (GET) data siswa/jadwal dan menginput observasi (POST).

### C. Proteksi Injeksi & XSS
* **SQL Injection:** Seluruh kueri ke database SQLite secara eksklusif menggunakan *Prepared Statements* (PDO param binding `?`).
* **Input Sanitization (XSS):** Request body `php://input` dilewatkan ke fungsi rekursif `sanitizeObject()` yang mengamankan semua *string* menggunakan `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

## 4. API Endpoints Overview
Struktur *Routing* API didesain dalam satu file terpusat (`api.php`) untuk kemudahan pengelolaan di *shared hosting*:

* **Auth & Settings:**
  - `POST /api/login`
  - `GET/POST /api/settings`
* **Data Master (Admin & Evaluator):**
  - `GET/POST /api/users`
  - `GET/POST /api/sekolah`
  - `GET/POST /api/kelas`
  - `GET/POST /api/siswa` (serta `POST /api/siswa/bulk` untuk *import* massal).
  - `GET/POST /api/bank-soal`
* **Operasional Kelas:**
  - `GET/POST /api/jadwal`
  - `GET/POST /api/grup`
* **Transaksi Data (Observasi & Refleksi):**
  - `GET/POST /api/observasi`
  - `POST /api/public/reflection` (Akses publik tanpa JWT).
  - `GET /api/public/cert` (Menarik data hasil agregasi observasi dan refleksi untuk rendering sertifikat).

---

## 5. Deployment & Operasional Server
* **Persyaratan Lingkungan (Environment):**
  - PHP Versi 7.4 ke atas (Direkomendasikan PHP 8.1 atau 8.2).
  - Ekstensi PHP wajib: `pdo_sqlite`, `sqlite3`, `json`.
  - Apache/LiteSpeed web server dengan konfigurasi *URL Rewriting* melalui `.htaccess` (mengarahkan rute REST ke `/api.php`).
* **Portabilitas:** Karena menggunakan SQLite, migrasi *database* atau *backup* hanya semudah menyalin file tunggal `wlc.db` ke tempat penyimpanan cadangan. Tidak memerlukan server MySQL eksternal.

---

## 6. Sudut Pandang untuk Analisa AI Selanjutnya
Berdasarkan dokumen teknis ini, AI dapat diminta untuk melakukan analisis lanjutan:
1. **Security Vulnerability Scan:** Menganalisa kode `api.php` untuk menemukan kelemahan spesifik seperti potensi *Path Traversal*, kebocoran data di endpoint publik, atau penyalahgunaan *Rate Limiting*.
2. **Refactoring & Scalability:** Memberikan strategi untuk memecah `api.php` tunggal (yang saat ini lebih dari 1000 baris) menjadi pola MVC (Model-View-Controller) atau pola *Repository* jika aplikasi akan di-skala-kan ke banyak cabang.
3. **Database Indexing Optimization:** Mengevaluasi kueri kompleks pada endpoint Sertifikat dan memberikan saran penambahan indeks (CREATE INDEX) baru di SQLite untuk mempercepat eksekusi (meskipun tabel *join* cukup panjang).
4. **Offline Sync Capabilities:** Mendesain arsitektur *Service Worker* (PWA) agar asisten dapat mengisi nilai saat tablet tidak ada sinyal internet, lalu menyinkronkannya (sync) ke backend SQLite saat kembali *online*.
