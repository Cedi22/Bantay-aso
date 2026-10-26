@echo off
setlocal
cd /d "%~dp0"
echo ========================================================
echo   Bantay Aso -- Dev Server ^& Parity Hub
echo ========================================================
echo.
echo Starting local web server on http://127.0.0.1:8000 ...
echo Local Sign In  : http://127.0.0.1:8000/login.php
echo Local Dashboard: http://127.0.0.1:8000/index.php
echo State API      : http://127.0.0.1:8000/api/state
echo.
echo Press Ctrl+C in this window to stop the server.
echo.

start http://127.0.0.1:8000/login.php

where python >nul 2>nul
if %errorlevel% equ 0 (
    python server.py
    goto end
)

if exist "C:\Python314\python.exe" (
    "C:\Python314\python.exe" server.py
    goto end
)

where node >nul 2>nul
if %errorlevel% equ 0 (
    node server.js
    goto end
)

if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S 127.0.0.1:8000 router.php
    goto end
)

where php >nul 2>nul
if %errorlevel% equ 0 (
    php -S 127.0.0.1:8000 router.php
    goto end
)

for /f "tokens=*" %%i in ('wsl wslpath -a "%~dp0server.py"') do set WSL_SERVER_PATH=%%i
wsl -d Ubuntu -e python3 %WSL_SERVER_PATH%

:end
pause
