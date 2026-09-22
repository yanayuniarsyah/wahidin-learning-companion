# 📖 Panduan Penggunaan Aplikasi WLC (Updated v1.1)

## 🎯 Status Aplikasi
Aplikasi WLC kini telah diperbarui dengan sistem backend (Node.js + SQLite) dan desain premium terbaru.

## 🚀 Cara Menjalankan
Aplikasi ini **TIDAK BOLEH** dibuka langsung via `file://`.
1. Jalankan backend:
   ```powershell
   node backend/server.js
   ```
2. Buka di browser:
   **[http://localhost:3000](http://localhost:3000)**

> [!IMPORTANT]
> Jika Anda tidak melihat perubahan (seperti judul "Komponen Observasi" atau layout landsc   ape), silakan tekan **CTRL + F5** di browser untuk membersihkan cache.

## 👥 Akun Akses
| Role | Username | Password |
|------|----------|----------|
| **Owner** | `owner` | `admin` |
| **Asisten** | `asisten` | `admin` |

## 🛠️ Perubahan Terbaru (v1.1)
1. **Terminologi**: "Bank WLC" resmi diganti menjadi **"Komponen Observasi"**.
2. **Sertifikat**: Layout diubah menjadi **A4 Landscape** dengan desain premium (Expert DKV Layout) agar pas dalam 1 halaman.
3. **Form Modal**: Semua form (Tambah/Edit) kini muncul sebagai **modal di tengah layar** (fixed), tidak lagi berada jauh di bawah.
4. **Fitur Edit**: Perbaikan total pada fitur Edit Sekolah, Kelas, Siswa, dan Soal (mendukung dropdown relasional).

## 📋 Modul Utama
- **Beranda**: Statistik perkembangan siswa.
- **Sekolah & Kelas**: Manajemen lokasi belajar.
- **Siswa**: Data lengkap siswa beserta NISN.
- **Komponen Observasi**: Daftar pertanyaan/indikator observasi.
- **Sertifikat WLC**: Cetak laporan perkembangan (Snapshot) format Landscape.

## 🔧 Troubleshooting
- **Database tidak update?** Pastikan `server.js` berjalan dan login sebagai `owner`.
- **Form tidak muncul?** Cek apakah ada modal yang belum ditutup.
- **Sertifikat terpotong?** Pastikan setingan print di browser adalah: **Orientation: Landscape**, **Paper Size: A4**, **Margins: Default/None**.

*WLC: Pendampingan, bukan penilaian.* 🌿
