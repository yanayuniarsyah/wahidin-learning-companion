# WLC Business Process: Dari Penawaran Sekolah Hingga Sertifikasi

Dokumen ini menjelaskan alur proses bisnis (Business Process) terintegrasi untuk program CSR **Wahidin Learning Companion (WLC)**, yang mencakup varian **WLC Kids** (untuk TK/SD) dan **WLC Teen** (untuk SMP/SMA). Alur ini menggambarkan perjalanan end-to-end mulai dari pendekatan ke sekolah hingga orang tua menerima sertifikat dan penawaran program Kumon.

---

## 📊 Diagram Alur Proses Bisnis (BPMN / Flowchart)

```mermaid
graph TD
    %% 1. Tahap Penawaran & Kemitraan
    subgraph Fase 1: Kemitraan Sekolah & Setup Awal
        A[Kumon: Penawaran Program CSR WLC ke Sekolah] --> B{Sekolah Setuju?}
        B -- Tidak --> Z[Follow up di lain waktu]
        B -- Ya --> C[Sekolah Menyerahkan Data Siswa ke WLC]
        C --> D[Kumon Admin: Generate Link & Kode Kelas WLC]
    end

    %% 1.5. Tahap Persiapan Internal & Pengelompokan
    subgraph Fase 1.5: Persiapan Internal WLC & Pengelompokan (Kids)
        D --> F1[Pihak WLC: Membagi tugas Evaluator & Asisten]
        F1 --> F2[Evaluator WLC: Menerima jadwal & tanggung jawab <br/>kelas yang akan dipimpin]
        F2 --> F3[Evaluator WLC: Mengelompokkan data siswa <br/>(Max 7 anak per kelompok)]
        F3 --> F4[Sistem: Assign 1 Asisten <br/>untuk setiap 7 Siswa]
        F4 --> F5[Asisten WLC: Menerima daftar kelompok <br/>dan jadwal sebelum hari pelaksanaan di sekolah]
        F5 --> E[Sekolah / WLC: Distribusi Link WLC ke Orang Tua]
    end

    %% 2. Tahap Pelaksanaan Siswa & Observasi Asisten
    subgraph Fase 2: Pelaksanaan Program WLC
        E --> F{Kategori Program}
        F4 -.-> F
        
        %% WLC Kids Branch
        F -- WLC Kids (TK/SD) --> G2[Asisten WLC: Login ke Sistem]
        G2 --> G3[Sistem: Menampilkan List Kelompok Siswa <br/>yang menjadi tugas Asisten]
        G3 --> G4[Asisten WLC: Klik Kelompok & <br/>Pilih 'Mulai Observasi']
        G4 --> G5[Sistem: Tampilkan Form Observasi per Point <br/>(List Nama Siswa & Pilihan Jawaban)]
        G5 --> G6[Asisten WLC: Mengisi hasil observasi <br/>untuk setiap poin]
        G6 --> I[System: Simpan Data & Progress Observasi Kids]
        
        %% WLC Teen Branch
        F -- WLC Teen (SMP/SMA) --> J[Siswa Akses WLC Teen]
        J --> K[Assessmen Diri, Gaya Belajar & Habit]
        K --> L[Refleksi Mandiri & Jurnal Remaja]
        L --> I2[System: Kalkulasi Progress & Skor Teen]
    end

    %% 3. Tahap Monitoring & Observer
    subgraph Fase 3: Monitoring
        I -.-> N[Guru / Sekolah: Pantau via Observer Dashboard]
        I2 -.-> N
        I -.-> O[Kumon: Pantau via Admin Dashboard]
        I2 -.-> O
    end

    %% 4. Tahap Sertifikasi & Follow Up
    subgraph Fase 4: Sertifikasi & Konversi
        I --> P{Progress Selesai?}
        I2 --> P
        P -- Belum --> I
        P -- Sudah --> Q[System: Generate E-Certificate WLC]
        Q --> R[Orang Tua / Siswa: Unduh Sertifikat & Rapor Hasil]
        R --> S((Milestone: Brand Awareness Tercapai))
        
        S --> T[Kumon: Follow up Coba Gratis / Pendaftaran Kumon]
        T --> U((Goal: Siswa Mendaftar Kumon))
    end
```

---

## 📝 Penjelasan Detail Per Fase

### Fase 1: Kemitraan Sekolah & Setup Awal
1. **Penawaran:** Pihak Kumon (Cilacap) melakukan pendekatan ke sekolah-sekolah (TK/SD untuk Kids, SMP/SMA untuk Teen) menawarkan program CSR WLC secara gratis untuk melatih karakter dan kebiasaan belajar siswa.
2. **Persetujuan & Data:** Jika sekolah setuju, pihak sekolah akan menyerahkan **data siswa** ke pihak WLC.
3. **Setup Sistem:** Admin WLC Kumon membuatkan kode akses khusus atau link akses untuk sekolah tersebut.

### Fase 1.5: Persiapan Internal WLC & Pengelompokan (Khusus WLC Kids)
Sebelum pelaksanaan di sekolah dimulai, tim internal WLC melakukan persiapan dan pembagian tim (berbekal data dari Fase 1):
*   **Pembagian Tugas:** Pihak WLC menunjuk siapa saja yang akan bertugas sebagai Evaluator dan Asisten.
*   **Peran Evaluator:** Evaluator WLC di-assign jadwal dan tanggung jawab untuk memimpin satu kelas tertentu.
*   **Pengelompokan Data:** Evaluator WLC kemudian mengelompokkan data siswa di kelas tersebut (maksimal 7 siswa per kelompok).
*   **Peran Asisten:** Sistem/Evaluator memberikan penugasan (assign) 1 Asisten untuk masing-masing kelompok (yang berisi 7 siswa).
*   **Kesiapan Tim:** Sebelum datang ke sekolah, Evaluator sudah tahu kelas mana dan jam berapa dia bertanggung jawab. Begitu juga Asisten sudah menerima daftar kelompok dan daftar nama anak-anak di kelompoknya.
*   **Distribusi Akses:** Setelah semua struktur tim siap, barulah link/akses WLC didistribusikan kepada Orang Tua.

### Fase 2: Pelaksanaan Program WLC di Sekolah
Alur di sini terbagi berdasarkan jenjang usia:

*   **Untuk WLC Kids (TK/SD) - Observasi Berbasis Asisten:**
    *   **Tugas Asisten:** Asisten login ke dalam sistem dan akan melihat *list* (daftar) kelompok siswa yang menjadi tanggung jawabnya (hasil pengelompokan di Fase 1.5).
    *   **Proses Observasi:** 
        *   Ketika Asisten mengklik salah satu kelompok, akan muncul pilihan untuk **mulai observasi**.
        *   Sistem akan menampilkan nomor poin observasi (misalnya poin no. 1). Di layar ini, Asisten akan melihat daftar nama anak dalam kelompok tersebut beserta pilihan jawabannya secara langsung. 
        *   Proses ini diulang untuk poin-poin observasi selanjutnya. System kemudian akan menyimpan hasil observasi.
*   **Untuk WLC Teen (SMP/SMA):**
    *   **Mandiri:** Remaja menggunakan aplikasi sendiri.
    *   **Aktivitas Utama:** Menjawab kuesioner gaya belajar, manajemen waktu, menetapkan tujuan (*goal setting*), dan jurnal refleksi diri yang lebih mendalam.

### Fase 3: Monitoring (Observer)
*   **Guru/Sekolah:** Diberikan link akses sebagai **Observer** untuk melihat murid mana yang rajin mengisi WLC dan mana yang belum, tanpa bisa merubah data. Ini menjadi nilai tambah CSR ke sekolah.
*   **Kumon:** Admin Kumon melihat *traffic* dan partisipasi keseluruhan untuk mengukur efektivitas program.

### Fase 4: Sertifikasi & Konversi
1. **Penerbitan Sertifikat:** Setelah siswa menyelesaikan *milestone* (misalnya 14 hari *check-in* penuh atau menyelesaikan modul *assessment* Teen), sistem akan otomatis men-generate **E-Certificate** atas nama anak.
2. **Penerimaan:** Orang Tua (Kids) atau Siswa (Teen) bisa men-download sertifikat tersebut berformat PDF secara langsung melalui aplikasi atau dikirim via WhatsApp.
3. **Konversi Bisnis (Kumon):** Momen pengambilan/penerimaan sertifikat ini adalah *golden time*. Kumon menggunakan momentum pencapaian anak ini untuk mengapresiasi orang tua dan menawarkan **Coba Gratis (Free Trial)** atau pendaftaran kelas reguler Kumon, dengan bekal data *habit* anak yang sudah terbentuk selama program WLC.

---

### 💡 Keuntungan Model Bisnis Ini:
- **Bagi Sekolah:** Mendapat program pembentukan karakter digital gratis, mempermudah tugas BK/Wali Kelas.
- **Bagi Orang Tua/Siswa:** Anak terbangun kebiasaannya, mendapat sertifikat penghargaan untuk portofolio/prestasi.
- **Bagi Kumon:** Mengumpulkan *leads* (database calon murid) berkualitas tinggi yang sudah memiliki kedisiplinan dasar, sehingga konversi ke pendaftaran (enrollment) jauh lebih mudah.
