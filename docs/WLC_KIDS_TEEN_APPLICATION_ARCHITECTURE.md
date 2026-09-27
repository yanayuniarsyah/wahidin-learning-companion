# WLC KIDS & TEEN APPLICATION ARCHITECTURE

## Overview
WLC Application has been architecturally bifurcated to support two explicit product pathways while maintaining a unified codebase and database. The separation is driven by **Methodology** and **Respondent Identity**.

---

## 1. WLC KIDS
Target Audience: TK, SD 1-3, SD 4-6
Methodology: **STRUCTURED OBSERVATION**

- **Actor/Respondent**: Observer (Asisten / Evaluator / Owner)
- **Subject**: Anak / Siswa (Child)
- **API Scope**: `/api/v2/observer/*` (`api_v2_observer.php`)
- **UI Entry Point**: `dashboard.html` (Menu: *Observasi WLC Kids* via `wlc_kids.js`)
- **Flow**: Observer Login -> Dashboard -> Pilih Target Anak -> Start Session -> Form Observasi (Autosave) -> Submit -> Generate "Learning Habits Profile"
- **Instrument**: `PROVISIONAL`. Seed saat ini memuat 42 aitem kandidat (14 per kelompok usia, dua per construct) dengan deskripsi perilaku yang dapat diamati. Aitem ini belum melewati validasi SME, uji reliabilitas antar-observer, atau validasi empiris.
- **Instrument Catalog**: Menu owner **Instrumen WLC** menampilkan tiga alat observasi terpisah (TK, SD 1–3, SD 4–6). Setiap alat memuat construct/variabel dan butir yang benar-benar tersimpan untuk instrumen jenjang tersebut.
- **Catatan kesesuaian variabel**: Tujuh construct operasional saat ini adalah Transisi ke Belajar, Keterlibatan terhadap Aktivitas, Penerapan Instruksi, Inisiatif Penyelesaian, Respons terhadap Kendala, Partisipasi Aktif, dan Lingkungan Belajar. Belum ada matriks definisi operasional dan pemetaan indikator Kids yang membuktikan seluruh aitem mengukur variabel yang dimaksud. Aitem pada construct Lingkungan Belajar, misalnya, sebagian masih mengukur perilaku anak (penggunaan/penyimpanan alat), bukan kondisi lingkungan secara langsung.
- **Narrative Framing**: "Dalam sesi yang diamati..."

---

## 2. WLC TEEN
Target Audience: SMP 7, 8, 9
Methodology: **SELF_REPORT_QUESTIONNAIRE**

- **Actor/Respondent**: Siswa (Student)
- **Subject**: Siswa itu sendiri (Student)
- **API Scope**: `/api/v2/student/*` (`api_v2_student.php`)
- **UI Entry Point**: `student.html`
- **Flow**: Student Login -> Landing/Dashboard Siswa -> Start Questionnaire -> Form Kuisioner 14-item (Autosave) -> Submit -> Generate Report
- **Instrument**: `PROVISIONAL` (14-items candidate tersedia).
- **Instrument Catalog**: Menu owner **Instrumen WLC** juga menampilkan alat kuesioner laporan diri Teen SMP 7–9 beserta construct/variabel dan butir yang dipakai portal siswa.
- **Narrative Framing**: "Menurut jawaban siswa..."

---

## 3. Database Architecture (Shared & Separated)

- **`wlc_instruments`**: Shared. Dipisahkan secara deklaratif oleh field `audience` (`KIDS` vs `TEEN`), `methodology`, dan `target_grade`.
- **`wlc_sessions`**: Shared. Identitas metodologi ditetapkan saat sesi dibuat. Untuk KIDS, kolom `observer_id` merekam identitas pengisi, dan `respondent_type` memisahkan konteks pelaku.
- **`wlc_constructs` & `wlc_items`**: Separated per Instrument. WLC Kids memiliki ID shell terpisah dari WLC Teen untuk menghambat cross-contamination.

## 4. Security Boundaries

- **IDOR Prevention (Teen)**: `api_v2_student.php` memaksa `siswa_id` diekstrak dari JWT token origin (bukan HTTP Request Body). Siswa tidak bisa mensubmit atas nama siswa lain.
- **Role Enforcement (Kids)**: `api_v2_observer.php` memblokir akses token ber-role `siswa`. API mewajibkan payload `siswa_id` dari Observer yang sah.
- **Session Immutability**: Keduanya (Kids & Teen) secara deterministik mengunci status `SUBMITTED` dan menolak overwrite response selanjutnya.
