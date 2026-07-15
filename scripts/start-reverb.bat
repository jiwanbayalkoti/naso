@echo off
title NASO Reverb WebSocket Server
cd /d "%~dp0.."
echo Laravel Reverb requires PHP 8.2+ and laravel/reverb package.
echo Run scripts/upgrade-php-xampp.ps1 first, then:
echo   composer require laravel/reverb
echo   php artisan reverb:install
echo   php artisan reverb:start
echo.
php -r "if (version_compare(PHP_VERSION, '8.2.0', '<')) { echo 'Current PHP '.PHP_VERSION.' is too old.\n'; exit(1); }"
if errorlevel 1 pause & exit /b 1
php artisan reverb:start
pause
