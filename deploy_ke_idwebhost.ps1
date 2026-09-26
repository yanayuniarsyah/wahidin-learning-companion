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
$ftpCredFile = Join-Path $PWD ".ftp_credentials"
$ftpUser = $Env:FTP_USER
$ftpPass = $Env:FTP_PASS

if (Test-Path $ftpCredFile) {
    $creds = Get-Content $ftpCredFile | ConvertFrom-Json
    if (-not $ftpUser) { $ftpUser = $creds.user }
    if (-not $ftpPass) { $ftpPass = $creds.pass }
}

if ([string]::IsNullOrWhiteSpace($ftpUser) -or [string]::IsNullOrWhiteSpace($ftpPass)) {
    Write-Host "Kredensial FTP belum diatur." -ForegroundColor Yellow
    $ftpUser = Read-Host "Masukkan FTP Username (Idwebhost)"
    $ftpPass = Read-Host "Masukkan FTP Password"

    $save = Read-Host "Simpan kredensial ini di file .ftp_credentials untuk auto-update selanjutnya? (y/n)"
    if ($save -eq 'y') {
        $credObj = @{ user = $ftpUser; pass = $ftpPass }
        $credObj | ConvertTo-Json | Set-Content $ftpCredFile
        Write-Host "Kredensial disimpan ke .ftp_credentials. File ini sudah diabaikan oleh Git." -ForegroundColor Green
    }
}

$webUrl = "https://wlc.kumonwahidincilacap.com/unzipper.php"

Write-Host "Memulai Proses Update WLC App ke Idwebhost..." -ForegroundColor Cyan

# 1. Bikin file ZIP lokal (TANPA wlc.db)
Write-Host "Sedang mengemas file update dan file wlc diabaikan agar aman"
$filesToZip = @(
    ".htaccess", ".jwt_secret", "api.php", "api_v2_student.php", "api_v2_observer.php", "router.php", 
    "index.html", "dashboard.html", "dashboard2.html", "student.html", "observer.html", "wlc_kids.js",
    "share.html", "reflection.html", "bank_soal_wlc1.json", 
    "logo_wahidin.png", "manifest.json", "WLC Certificate - Budi.pdf",
    "migrate.php", "migrate_kids.php", "migrate_seed.php", "seed_kids_content.php", "full_seed_prod.php", "db_export.json"
    "diagnose_prod_db.php"
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
