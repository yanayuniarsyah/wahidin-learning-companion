# Panduan Deploy WLC App ke Subdomain IDWebHost (cPanel)

Panduan ini menjelaskan langkah demi langkah untuk mengunggah dan mengaktifkan aplikasi **WLC App** di subdomain Anda: **`wlc.kumonwahidincilacap.com`** di hosting **IDWebHost**.

---

## Langkah 1: Persiapan File di Laptop / Komputer
Sebelum mengunggah, kumpulkan file-file penting yang diperlukan oleh aplikasi.
1. Buat sebuah file ZIP (misal: `wlc-app.zip`) yang berisi file-file berikut langsung di dalamnya (jangan masukkan ke dalam folder baru di dalam zip):
   * `index.html`
   * `api.php`
   * `.htaccess` (Pastikan file titik ini ikut dikompres)
   * `manifest.json`
   * `bank_soal_wlc1.json`

---

## Langkah 2: Login ke Member Area & cPanel IDWebHost
1. Buka [https://member.idwebhost.com](https://member.idwebhost.com) dan login dengan akun Anda.
2. Cari menu **Layanan / Hosting** Anda, lalu klik tombol **Manage / Kelola**.
3. Klik tombol **Login cPanel** untuk masuk ke kontrol panel hosting Anda.

---

## Langkah 3: Membuat & Mengetahui Folder Subdomain di cPanel
Jika subdomain **`wlc.kumonwahidincilacap.com`** belum dibuat:
1. Di halaman cPanel, cari dan pilih menu **Subdomains** atau **Domains**.
2. Daftarkan subdomain baru:
   * **Subdomain**: `wlc`
   * **Domain**: `kumonwahidincilacap.com`
   * **Document Root**: Biasanya otomatis terisi `/public_html/wlc` atau `/wlc.kumonwahidincilacap.com`. Catat nama folder ini!
3. Klik **Create**.

---

## Langkah 4: Upload File Menggunakan File Manager
1. Di halaman cPanel, cari dan buka menu **File Manager**.
2. Masuk ke folder **Document Root** dari subdomain Anda (misalnya: **`/public_html/wlc`** atau **`/wlc.kumonwahidincilacap.com`**).
   > [!IMPORTANT]
   > Pastikan Anda berada di folder subdomain tersebut, **bukan** langsung di root `public_html`, agar tidak menimpa website utama `kumonwahidincilacap.com`.
3. Klik tombol **Upload** di bagian menu atas File Manager.
4. Pilih file **`wlc-app.zip`** yang sudah dipersiapkan di Langkah 1.
5. Tunggu hingga proses upload selesai (bar berwarna hijau 100%).
6. Kembali ke File Manager, klik kanan pada file `wlc-app.zip` yang baru diupload, lalu klik **Extract**. Ekstrak langsung ke folder subdomain tersebut.
7. Hapus file `wlc-app.zip` dari server untuk menghemat ruang hosting.

---

## Langkah 5: Pastikan Versi PHP Sesuai
Aplikasi ini berjalan dengan sangat baik di PHP versi **7.4 ke atas** (rekomendasi PHP 8.1 / 8.2).
1. Di kolom pencarian cPanel, cari **Select PHP Version** atau **MultiPHP Manager**.
2. Pastikan subdomain `wlc.kumonwahidincilacap.com` diset untuk menggunakan versi PHP minimal **8.1** atau **8.2**.
3. Pastikan ekstensi **`pdo_sqlite`** dan **`sqlite3`** aktif di halaman extensions Select PHP Version.

---

## Langkah 6: Pengujian Aplikasi (Go Live!)
1. Buka browser Anda dan akses subdomain Anda:
   👉 **`https://wlc.kumonwahidincilacap.com`**
2. Halaman Login WLC App akan muncul.
3. Login menggunakan akun default:
   * **Username**: `owner`
   * **Password**: `owner123`
4. Jika berhasil masuk ke Dashboard Owner, berarti backend API dan database SQLite Anda sudah berfungsi dengan sempurna!

---

## Tips Setelah Go Live
* **Ubah Password**: Buka menu **Kelola User** di Dashboard Owner dan segera perbarui password untuk user `owner`, `evaluator`, dan `asisten` dengan password baru yang lebih aman.
* **SSL/HTTPS**: SSL gratis dari IDWebHost biasanya aktif otomatis dalam waktu 24 jam untuk subdomain baru. Pastikan Anda mengakses menggunakan `https://` agar koneksi terenkripsi aman.
