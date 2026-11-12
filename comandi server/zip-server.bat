@echo off
rem Zip ottimizzato per il caricamento su un server Windows (VPS): solo cio' che serve
rem per girare in produzione.
rem
rem  - compila gli asset (npm run build) cosi' public\build e' aggiornato
rem  - vendor reinstallato in una cartella temporanea SENZA pacchetti di sviluppo
rem    e con autoload ottimizzato (la cartella vendor del progetto non viene toccata)
rem  - NON include: .env, database, file caricati, log, cache, node_modules, .git,
rem    test, sorgenti React, strumenti di sviluppo, file di documentazione
rem    (cosi' decomprimere sopra a una installazione esistente non cancella i suoi dati)
rem  - include .env.example, le migrazioni e le cartelle storage vuote
rem
rem Lo zip finisce fuori dal progetto, in ..\digithub-connect-react-zip
rem Con la variabile NO_PAUSE impostata non aspetta un tasto alla fine.
setlocal
title Zip server
cd /d "%~dp0.."
set "ROOT=%CD%"
set "TAR=%SystemRoot%\System32\tar.exe"
set "DEST=%ROOT%\..\digithub-connect-react-zip"
set "STAGE=%TEMP%\digithub-server-staging"

echo.
echo === Zip per il server ===
echo.

if not exist "%TAR%" goto manca_tar
where composer >nul 2>nul
if errorlevel 1 goto manca_composer
where npm >nul 2>nul
if errorlevel 1 goto manca_npm

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set "TS=%%i"
if not exist "%DEST%" mkdir "%DEST%"
set "ZIP=%DEST%\server-%TS%.zip"

echo [1/5] Compilo gli asset (npm run build)
call npm run build
if errorlevel 1 goto errore

echo.
echo [2/5] Copio i file necessari in %STAGE%
if exist "%STAGE%" rmdir /s /q "%STAGE%"
mkdir "%STAGE%"
robocopy "%ROOT%" "%STAGE%" /E /NFL /NDL /NJH /NJS /NP ^
  /XD "%ROOT%\.git" "%ROOT%\.github" "%ROOT%\.idea" "%ROOT%\.vscode" "%ROOT%\.agents" "%ROOT%\.ai" "%ROOT%\.claude" "%ROOT%\.codex" "%ROOT%\.cursor" "%ROOT%\.pi" ^
      "%ROOT%\node_modules" "%ROOT%\vendor" "%ROOT%\tests" "%ROOT%\scratchpad" "%ROOT%\comandi server" ^
      "%ROOT%\storage" "%ROOT%\public\storage" "%ROOT%\bootstrap\cache" ^
      "%ROOT%\resources\js" "%ROOT%\resources\css" ^
  /XF .env .env.backup .env.production *.sqlite *.sqlite-wal *.sqlite-shm *.log hot ^
      AGENTS.md CLAUDE.md LOG.md COLLAUDO.md boost.json .mcp.json opencode.json ^
      phpstan.neon phpunit.xml pint.json components.json tsconfig.json vite.config.js ^
      package.json package-lock.json .npmrc .editorconfig .gitattributes .gitignore
if errorlevel 8 goto errore

echo.
echo [3/5] Installo vendor di produzione (senza pacchetti di sviluppo)
pushd "%STAGE%"
call composer install --no-dev --optimize-autoloader --classmap-authoritative --no-scripts --no-interaction
if errorlevel 1 popd & goto errore
popd

echo.
echo [4/5] Creo le cartelle storage vuote
for %%d in ("storage\app\public" "storage\app\private" "storage\framework\cache\data" "storage\framework\sessions" "storage\framework\views" "storage\logs" "bootstrap\cache") do mkdir "%STAGE%\%%~d"

echo.
echo [5/5] Creo lo zip
"%TAR%" -a -c -f "%ZIP%" -C "%STAGE%" .
if errorlevel 1 goto errore
rmdir /s /q "%STAGE%"

echo.
echo [OK] Zip per il server creato:
for %%f in ("%ZIP%") do echo      %%~ff  (%%~zf byte)
echo.
echo Sul server, dopo aver decompresso nella cartella del sito (PHP 8.4 o superiore):
echo   1. prima volta: copia .env.example in .env, imposta APP_ENV=production, APP_DEBUG=false,
echo      APP_URL, la posta; poi  php artisan key:generate  (APP_KEY va poi conservata)
echo   2. php artisan migrate --force
echo   3. php artisan storage:link
echo   4. php artisan optimize
echo   Il sito deve puntare alla cartella public. Servono cron dello scheduler e worker della coda.
goto fine

:manca_tar
echo [ERRORE] tar.exe non trovato in %SystemRoot%\System32 (serve Windows 10 o successivo).
goto fine_errore

:manca_composer
echo [ERRORE] composer non trovato nel PATH.
goto fine_errore

:manca_npm
echo [ERRORE] npm non trovato nel PATH.
goto fine_errore

:errore
echo.
echo [ERRORE] Un passaggio e' fallito: leggi il messaggio qui sopra. Nessuno zip valido creato.
if exist "%ZIP%" del "%ZIP%"
if exist "%STAGE%" rmdir /s /q "%STAGE%"

:fine_errore
echo.
if not defined NO_PAUSE pause
exit /b 1

:fine
echo.
if not defined NO_PAUSE pause
