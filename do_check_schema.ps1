$ftpHost = "ftp://ftp.kumonwahidincilacap.com/wlc.kumonwahidincilacap.com/"
$ftpCredFile = Join-Path $PWD ".ftp_credentials"
$creds = Get-Content $ftpCredFile | ConvertFrom-Json
$webClient = New-Object System.Net.WebClient
$webClient.Credentials = New-Object System.Net.NetworkCredential($creds.user, $creds.pass)

$patchPath = Join-Path $PWD "check_schema.php"
$webClient.UploadFile($ftpHost + "check_schema.php", $patchPath)

Write-Host "Uploaded. Triggering..."
$result = Invoke-RestMethod -Uri "https://wlc.kumonwahidincilacap.com/check_schema.php"
Write-Host "Result: $result"
