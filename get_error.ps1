$ftpHost = "ftp://ftp.kumonwahidincilacap.com/wlc.kumonwahidincilacap.com/"
$ftpCredFile = Join-Path $PWD ".ftp_credentials"
$creds = Get-Content $ftpCredFile | ConvertFrom-Json
$webClient = New-Object System.Net.WebClient
$webClient.Credentials = New-Object System.Net.NetworkCredential($creds.user, $creds.pass)

try {
    $errorLog = $webClient.DownloadString($ftpHost + "error_log")
    Write-Host "--- ERROR LOG ---"
    Write-Host $errorLog
} catch {
    Write-Host "No error_log found or permission denied."
}
