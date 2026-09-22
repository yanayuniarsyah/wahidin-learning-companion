@echo off
title WLC One-Click PHP Server 🚀
color 0A
cls

echo.
echo ╔══════════════════════════════════════════════════════╗
echo ║          🔥 WLC APP - PHP ONE-CLICK SERVER 🚀         ║  
echo ║      Whole-Life Coaching Companion - PHP Version     ║
echo ╚══════════════════════════════════════════════════════╝
echo.

echo 📱 Langsung buka: http://localhost:3000
echo.

:: Tentukan lokasi PHP executable
set "PHP_BIN=php"
if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
    echo 🔍 PHP terdeteksi di XAMPP: C:\xampp\php\php.exe
)

echo 🔍 Checking PHP...
"%PHP_BIN%" --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ PHP tidak terdeteksi! Pastikan PHP terinstall dan terdaftar di PATH lingkungan Anda.
    pause
    exit /b 1
)
echo ✅ PHP OK

echo.
echo 🚀 Starting WLC PHP Backend Server (port 3000)...
echo 📱 Membuka browser otomatis di http://localhost:3000/index.html
echo. 

:: Buka browser ke index.html via server
timeout /t 2 /nobreak >nul
start http://localhost:3000/index.html

echo 💚 Server berjalan! Tekan Ctrl+C untuk berhenti.
"%PHP_BIN%" -S localhost:3000 router.php
pause
