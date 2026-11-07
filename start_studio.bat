@echo off
cd /d "%~dp0"
title PHPMaker 2018 Studio - Entwicklungsserver (PHP + MariaDB)
echo ========================================================================
echo   PHPMaker 2018 Studio (PHP 5.4 - 8.4+ kompatibel)
echo ========================================================================
echo.

REM 1. MariaDB Server im Hintergrund starten, falls noch nicht aktiv
tasklist /FI "IMAGENAME eq mariadbd.exe" 2>NUL | find /I /N "mariadbd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MariaDB Datenbankserver laeuft bereits.
) else (
    if exist "C:\Program Files\MariaDB 13.0\bin\mariadbd.exe" (
        echo Starte lokalen MariaDB Datenbankserver auf Port 3306...
        start "MariaDB Server" /MIN "C:\Program Files\MariaDB 13.0\bin\mariadbd.exe" --datadir="%~dp0.mariadb_data" --port=3306
        timeout /t 2 /nobreak >nul
    )
)

REM 2. PHP Server starten und Browser oeffnen
set "PHP_WINGET=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
if exist "%PHP_WINGET%" (
    echo Starte PHPMaker 2018 Studio auf http://localhost:8080 ...
    start http://localhost:8080
    "%PHP_WINGET%" -S localhost:8080
    goto :eof
)

where php >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    echo Starte lokalen PHP-Server auf http://localhost:8080 ...
    start http://localhost:8080
    php -S localhost:8080
    goto :eof
)

if exist "C:\xampp\php\php.exe" (
    echo Starte XAMPP PHP-Server auf http://localhost:8080 ...
    start http://localhost:8080
    "C:\xampp\php\php.exe" -S localhost:8080
    goto :eof
)

echo [HINWEIS] php.exe wurde nicht gefunden.
pause
