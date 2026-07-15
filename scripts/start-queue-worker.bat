@echo off
title NASO Queue Worker
cd /d "%~dp0.."
echo Starting NASO queue worker (database driver)...
echo Press Ctrl+C to stop.
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
pause
