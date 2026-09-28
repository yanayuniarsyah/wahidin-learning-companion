$ftpHost = "ftp://ftp.kumonwahidincilacap.com/wlc.kumonwahidincilacap.com/"
$ftpCredFile = Join-Path $PWD ".ftp_credentials"
$creds = Get-Content $ftpCredFile | ConvertFrom-Json
$webClient = New-Object System.Net.WebClient
$webClient.Credentials = New-Object System.Net.NetworkCredential($creds.user, $creds.pass)

$patchPath = Join-Path $PWD "patch2.php"
$webClient.UploadFile($ftpHost + "patch2.php", $patchPath)

Write-Host "Uploaded. Triggering..."
$result = Invoke-RestMethod -Uri "https://wlc.kumonwahidincilacap.com/patch2.php"
Write-Host "Result: $result"
