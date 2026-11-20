@echo off
rem Uso: scripts\respaldo.bat "E:\Respaldos"   (la carpeta es opcional)
cd /d "%~dp0.."
if "%~1"=="" (php artisan carpisys:respaldo) else (php artisan carpisys:respaldo --destino="%~1")
