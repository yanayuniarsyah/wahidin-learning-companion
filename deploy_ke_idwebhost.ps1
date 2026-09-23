<#
.SYNOPSIS
Script otomatis untuk meng-update WLC App ke Idwebhost.
Script ini akan:
1. Meng-zip semua file TERBARU (Kecuali wlc.db agar data live tidak tertimpa!)
2. Membuat unzipper otomatis
3. Mengupload ke FTP idwebhost
4. Mengekstrak dari jarak jauh
#>

$ftpHost = "ftp://ftp.kumonwahidincilacap.com/wlc.kumonwahidincilacap.com/"
$ftpUser = "kumonwah"
$ftpPass = "V75]XBJu7:juj6"
$webUrl = "https://wlc.kumonwahidincilacap.com/unzipper.php"

Write-Host "Memulai Proses Update WLC App ke Idwebhost..." -ForegroundColor Cyan

# 1. Bikin file ZIP lokal (TANPA wlc.db)
Write-Host "Sedang mengemas file update dan file wlc diabaikan agar aman"
$filesToZip = @(
    ".htaccess", ".jwt_secret", "api.php", "router.php", 
    "index.html", "dashboard.html", 
    "share.html", "reflection.html", "bank_soal_wlc1.json", 
    "logo_wahidin.png", "manifest.json", "WLC Certificate - Budi.pdf"
)
Compress-Archive -Path $filesToZip -DestinationPath "update_wlc.zip" -Force

# 2. Bikin script unzipper
Write-Host "Membuat script penyusup (unzipper.php)..."
$phpScript = @'
<?php
$zipFile = 'update_wlc.zip';
$path = __DIR__;
$zip = new ZipArchive;
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($path);
    $zip->close();
    echo 'SUCCESS';
    unlink($zipFile);
    unlink(__FILE__);
} else {
    echo 'FAILED';
}
?>
'@
Set-Content -Path "unzipper.php" -Value $phpScript

# 3. Upload via FTP
Write-Host "Mengupload file ke server FTP ($ftpUser)..." -ForegroundColor Yellow
$webClient = New-Object System.Net.WebClient
$webClient.Credentials = New-Object System.Net.NetworkCredential($ftpUser, $ftpPass)

try {
    $zipPath = Join-Path $PWD "update_wlc.zip"
    $unzipperPath = Join-Path $PWD "unzipper.php"
    $webClient.UploadFile($ftpHost + "update_wlc.zip", $zipPath)
    $webClient.UploadFile($ftpHost + "unzipper.php", $unzipperPath)
} catch {
    Write-Host "GAGAL UPLOAD FTP: $_" -ForegroundColor Red
    exit
}

# 4. Trigger Extract
Write-Host "Memicu pengekstrakan jarak jauh..." -ForegroundColor Yellow
try {
    $result = Invoke-RestMethod -Uri $webUrl
    if ($result -match "SUCCESS") {
        Write-Host "UPDATE BERHASIL 100%! Semua jejak sudah dihapus." -ForegroundColor Green
    } else {
        Write-Host "Ekstrak Gagal! Output: $result" -ForegroundColor Red
    }
} catch {
    Write-Host "GAGAL MEMICU UNZIPPER: $_" -ForegroundColor Red
}

# 5. Cleanup lokal
Remove-Item "update_wlc.zip" -ErrorAction SilentlyContinue
Remove-Item "unzipper.php" -ErrorAction SilentlyContinue

Write-Host "Tugas selesai!"
