# deploy.ps1 — Push ke GitHub + trigger auto-pull di server
# Usage: powershell -File deploy.ps1 -Message "pesan commit"
param(
    [string]$Message = "chore: deploy update"
)

$projectPath = "C:\Users\yanay\OneDrive\Documents\YANA\ELYANA.BIZ.ID\2 KUMON\CSR\WLC APP4 PHP"
$pullUrl     = "https://wlc.kumonwahidincilacap.com/git_pull.php?token=wlc-deploy-2026"

Set-Location $projectPath

Write-Host "`n=== STEP 1: Git add & commit ===" -ForegroundColor Cyan
git add -A
git commit -m $Message
if ($LASTEXITCODE -ne 0) {
    Write-Host "Tidak ada perubahan baru atau commit gagal." -ForegroundColor Yellow
}

Write-Host "`n=== STEP 2: Push ke GitHub ===" -ForegroundColor Cyan
git push origin main
if ($LASTEXITCODE -ne 0) {
    Write-Host "Push GAGAL. Periksa koneksi/credentials." -ForegroundColor Red
    exit 1
}
Write-Host "Push berhasil!" -ForegroundColor Green

Write-Host "`n=== STEP 3: Trigger git pull di server ===" -ForegroundColor Cyan
try {
    $response = Invoke-WebRequest -Uri $pullUrl -TimeoutSec 30 -UseBasicParsing
    Write-Host "Server response ($($response.StatusCode)):" -ForegroundColor Green
    Write-Host $response.Content
} catch {
    Write-Host "GAGAL trigger pull: $_" -ForegroundColor Red
    Write-Host "Pull manual: ssh ke server lalu jalankan:" -ForegroundColor Yellow
    Write-Host "  git -C ~/wlc.kumonwahidincilacap.com pull origin main" -ForegroundColor Yellow
}

Write-Host "`n=== DEPLOY SELESAI ===" -ForegroundColor Green
Write-Host "Buka browser dan tekan Ctrl+Shift+R untuk hard refresh." -ForegroundColor White
