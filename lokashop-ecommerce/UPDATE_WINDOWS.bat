@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (echo PHP was not found. Add your PHP folder to PATH first.& pause& exit /b 1)
if not exist .env (echo Keep or create your .env first. Read START_HERE.md.& pause& exit /b 1)
echo This upgrade keeps existing records. Back up MySQL before proceeding.
php artisan optimize:clear
if errorlevel 1 goto failed
php artisan migrate --force
if errorlevel 1 goto failed
php artisan lokashop:sync-storage
if errorlevel 1 goto failed
if not exist public\storage php artisan storage:link
if errorlevel 1 goto failed
echo Upgrade complete. Start the app with php artisan serve.
pause
exit /b 0
:failed
echo Upgrade stopped. Read the error above; do not run migrate:fresh.
pause
exit /b 1
