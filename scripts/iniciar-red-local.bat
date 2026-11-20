@echo off
chcp 65001 >nul
cd /d "%~dp0.."
echo.
echo  CarpiSys en la red local. Desde otro dispositivo en el mismo WiFi abre una de estas direcciones:
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do echo    http://%%a:8000
echo.
echo  Deja esta ventana abierta mientras se use el sistema. Ctrl+C para detenerlo.
echo.
php artisan serve --host=0.0.0.0 --port=8000
