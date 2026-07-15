# Downloads PHP 8.3 ZTS x64 for XAMPP (backup current PHP first)
# Run as Administrator from PowerShell

$ErrorActionPreference = 'Stop'

$XamppPath = 'C:\xampp'
$PhpPath = Join-Path $XamppPath 'php'
$BackupPath = Join-Path $XamppPath ('php_backup_' + (Get-Date -Format 'yyyyMMdd_HHmmss'))
$DownloadUrl = 'https://windows.php.net/downloads/releases/archives/php-8.3.14-Win32-vs16-x64.zip'
$TempZip = Join-Path $env:TEMP 'php-8.3-x64.zip'
$ExtractPath = Join-Path $env:TEMP 'php-8.3-extract'

Write-Host "NASO — XAMPP PHP 8.3 Upgrade" -ForegroundColor Cyan
Write-Host "Current PHP: $(php -v | Select-Object -First 1)"
Write-Host ""

if (-not (Test-Path $PhpPath)) {
    Write-Error "XAMPP PHP not found at $PhpPath"
}

$confirm = Read-Host "Backup $PhpPath to $BackupPath and install PHP 8.3? (y/N)"
if ($confirm -ne 'y' -and $confirm -ne 'Y') {
    Write-Host "Cancelled."
    exit 0
}

Write-Host "Backing up current PHP..." -ForegroundColor Yellow
Copy-Item -Path $PhpPath -Destination $BackupPath -Recurse -Force

Write-Host "Downloading PHP 8.3..." -ForegroundColor Yellow
Invoke-WebRequest -Uri $DownloadUrl -OutFile $TempZip -UseBasicParsing

if (Test-Path $ExtractPath) {
    Remove-Item $ExtractPath -Recurse -Force
}
New-Item -ItemType Directory -Path $ExtractPath | Out-Null
Expand-Archive -Path $TempZip -DestinationPath $ExtractPath -Force

$phpIniProduction = Join-Path $PhpPath 'php.ini'
$phpIniBackup = Join-Path $BackupPath 'php.ini'
$extensionsToKeep = @('php_mysql.dll', 'php_mysqli.dll', 'php_pdo_mysql.dll', 'php_openssl.dll', 'php_curl.dll', 'php_fileinfo.dll', 'php_mbstring.dll', 'php_gd.dll', 'php_intl.dll', 'php_zip.dll')

Write-Host "Installing PHP 8.3 files..." -ForegroundColor Yellow
Get-ChildItem $PhpPath -Exclude 'php.ini', 'ext' | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
Copy-Item -Path (Join-Path $ExtractPath '*') -Destination $PhpPath -Recurse -Force

if (Test-Path $phpIniBackup) {
    Copy-Item $phpIniBackup $phpIniProduction -Force
    Write-Host "Restored php.ini from backup." -ForegroundColor Green
} else {
    Copy-Item (Join-Path $PhpPath 'php.ini-production') $phpIniProduction -Force
    Write-Host "Created php.ini from production template — configure extensions manually." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "PHP upgraded. Verify with: php -v" -ForegroundColor Green
Write-Host "Backup location: $BackupPath" -ForegroundColor Gray
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. Restart Apache in XAMPP Control Panel"
Write-Host "  2. cd c:\xampp\htdocs\naso_app"
Write-Host "  3. composer update --ignore-platform-reqs  (for Laravel 13)"
Write-Host "  4. composer require laravel/reverb"
Write-Host "  5. php artisan naso:infra-check"

Remove-Item $TempZip -Force -ErrorAction SilentlyContinue
Remove-Item $ExtractPath -Recurse -Force -ErrorAction SilentlyContinue
