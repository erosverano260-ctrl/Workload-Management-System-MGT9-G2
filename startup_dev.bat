@echo off
cd /d "%~dp0"
if not exist sessions mkdir sessions

rem Find PHP: XAMPP on C:, D: or E:, otherwise whatever "php" is on the PATH
set PHP=php
if exist C:\xampp\php\php.exe set PHP=C:\xampp\php\php.exe
if exist D:\xampp\php\php.exe set PHP=D:\xampp\php\php.exe
if exist E:\xampp\php\php.exe set PHP=E:\xampp\php\php.exe

start "PHP server" cmd /k %PHP% -d session.save_path=sessions -S localhost:8000
start "BrowserSync" cmd /k npx.cmd browser-sync start --proxy localhost:8000 --no-ghost-mode --no-open --files "**/*.php, **/*.css, **/*.js"