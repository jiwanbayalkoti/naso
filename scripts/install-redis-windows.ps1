# Requires Administrator privileges
# Installs Memurai (Redis-compatible) for Windows via winget

$ErrorActionPreference = 'Stop'

Write-Host "NASO — Redis/Memurai Installer for Windows" -ForegroundColor Cyan
Write-Host ""

$winget = Get-Command winget -ErrorAction SilentlyContinue
if (-not $winget) {
    Write-Host "winget is not available. Install Memurai manually:" -ForegroundColor Yellow
    Write-Host "  https://www.memurai.com/get-memurai" -ForegroundColor White
    exit 1
}

Write-Host "Installing Memurai (Redis-compatible)..." -ForegroundColor Green
winget install Memurai.MemuraiDeveloper --accept-package-agreements --accept-source-agreements

Write-Host ""
Write-Host "After installation, update .env:" -ForegroundColor Cyan
Write-Host "  REDIS_CLIENT=predis"
Write-Host "  CACHE_DRIVER=redis"
Write-Host "  QUEUE_CONNECTION=redis"
Write-Host "  BROADCAST_DRIVER=redis"
Write-Host ""
Write-Host "Then run: php artisan naso:infra-check" -ForegroundColor Green
