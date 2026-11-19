@echo off
rem Pacchetto sorgente per IIS. Le dipendenze e la build si preparano sul VPS.
setlocal
title Zip server
cd /d "%~dp0.." || exit /b 1
set "TAR=%SystemRoot%\System32\tar.exe"
if not defined ZIP_SERVER_DEST set "ZIP_SERVER_DEST=%~dp0..\..\digithub-connect-react-zip"
for %%I in ("%ZIP_SERVER_DEST%") do set "ZIP_SERVER_DEST=%%~fI"

if not exist "%TAR%" (
    echo [ERRORE] tar.exe non trovato in System32.
    goto errore
)
for /f %%I in ('powershell.exe -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmssfff"') do set "TIMESTAMP=%%I"
if not defined TIMESTAMP goto errore
if not exist "%ZIP_SERVER_DEST%" mkdir "%ZIP_SERVER_DEST%"
set "ARCHIVE=%ZIP_SERVER_DEST%\server-%TIMESTAMP%.zip"
set "PARTIAL=%ZIP_SERVER_DEST%\server-%TIMESTAMP%.partial.zip"

echo [..] Creo il pacchetto sorgente per il server...
"%TAR%" -a -c -f "%PARTIAL%" ^
    --exclude "public/build" --exclude "public/storage" --exclude "public/hot" ^
    --exclude "public/fonts-manifest.dev.json" ^
    --exclude "resources/js/actions" --exclude "resources/js/routes" --exclude "resources/js/wayfinder" ^
    --exclude "*.sqlite*" --exclude "*.log" ^
    app bootstrap/app.php bootstrap/providers.php config ^
    database/factories database/migrations database/seeders lang public resources routes ^
    artisan composer.json composer.lock package.json package-lock.json .npmrc ^
    vite.config.js tsconfig.json .env.example .gitignore ^
    "comandi server/installa-server.bat" "comandi server/ferma-progetto.bat" ^
    "comandi server/rilancia-worker.bat" "comandi server/gestione-server.ps1"
if errorlevel 1 goto errore

move /y "%PARTIAL%" "%ARCHIVE%" >nul
if errorlevel 1 goto errore

echo [OK] %ARCHIVE%
echo Estrai nella cartella del sito sul VPS e avvia "comandi server\installa-server.bat".
if not defined NO_PAUSE pause
exit /b 0

:errore
if defined PARTIAL if exist "%PARTIAL%" del "%PARTIAL%"
echo [ERRORE] Creazione del pacchetto fallita.
if not defined NO_PAUSE pause
exit /b 1
