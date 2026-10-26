# Bantay Aso Local Runner for Windows
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   Bantay Aso — Dev Server & Sentinel Parity Hub" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Local Sign In  : " -NoNewline
Write-Host "http://127.0.0.1:8000/login.php" -ForegroundColor Yellow
Write-Host "Local Dashboard: " -NoNewline
Write-Host "http://127.0.0.1:8000/index.php" -ForegroundColor Yellow
Write-Host "State API      : " -NoNewline
Write-Host "http://127.0.0.1:8000/api/state" -ForegroundColor Cyan
Write-Host "Auth Profile   : " -NoNewline
Write-Host "admin / [Secured via .env]" -ForegroundColor DarkGray
Write-Host ""
Write-Host "Launching local server (Ctrl+C to stop)..." -ForegroundColor White

Start-Process "http://127.0.0.1:8000/login.php"

if (Get-Command python.exe -ErrorAction SilentlyContinue) {
    python server.py
} elseif (Test-Path "C:\Python314\python.exe") {
    & "C:\Python314\python.exe" server.py
} elseif (Get-Command node.exe -ErrorAction SilentlyContinue) {
    node server.js
} elseif (Test-Path "C:\xampp\php\php.exe") {
    & "C:\xampp\php\php.exe" -S 127.0.0.1:8000 router.php
} elseif (Get-Command php.exe -ErrorAction SilentlyContinue) {
    php -S 127.0.0.1:8000 router.php
    $wslScript = wsl wslpath -a (Join-Path $PSScriptRoot "server.py")
    wsl -d Ubuntu -e python3 $wslScript
}
