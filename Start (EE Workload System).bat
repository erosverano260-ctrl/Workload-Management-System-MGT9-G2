
@echo off
title PHP + BrowserSync Launcher
cd /d "%~dp0"

if not exist sessions mkdir sessions
set "SESS=%~dp0sessions"

set "PHP=php"
if exist C:\xampp\php\php.exe set "PHP=C:\xampp\php\php.exe"
if exist D:\xampp\php\php.exe set "PHP=D:\xampp\php\php.exe"
if exist E:\xampp\php\php.exe set "PHP=E:\xampp\php\php.exe"

echo Starting PHP Server...
start "PHP server" cmd /k ""%PHP%" -d session.save_path="%SESS%" -S localhost:8000"

timeout /t 2 /nobreak >nul

echo Starting BrowserSync...
start "BrowserSync" cmd /k npx.cmd browser-sync start --proxy localhost:8000 --no-ghost-mode --no-open --files "**/*.php, **/*.css, **/*.js"

echo Waiting for servers to initialize...
timeout /t 5 /nobreak >nul

echo Opening browser tabs...
start "" "http://localhost:3000"
start "" "http://127.0.0.1:3000"

echo.
echo Both browser tabs are ready!
exit
