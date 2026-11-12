@echo off
rem Avvia il progetto in locale: server web, coda, scheduler e Vite (composer run dev).
rem Si ferma con Ctrl+C in questa finestra, oppure con ferma-progetto.bat.
rem Con la variabile NO_BROWSER impostata il browser non viene aperto.
setlocal
title Digithub Connect - progetto avviato
cd /d "%~dp0.."

echo.
echo === Avvio del progetto ===
echo Cartella: %CD%
echo.

rem --- PHP: serve la 8.4 o superiore (il progetto usa la 8.5) ---
php -r "exit(PHP_VERSION_ID >= 80400 ? 0 : 1);"
if errorlevel 1 goto php_non_valido

rem --- Porta 8000 gia' occupata? ---
netstat -ano | findstr /R /C:":8000 .*LISTENING" >nul
if not errorlevel 1 goto porta_occupata

rem --- File .env ---
if exist ".env" goto env_ok
echo [..] .env mancante: lo creo da .env.example
copy /y ".env.example" ".env" >nul
call php artisan key:generate --force
if errorlevel 1 goto errore
:env_ok

rem --- Dipendenze ---
if exist "vendor\autoload.php" goto vendor_ok
echo [..] Installo le dipendenze PHP (composer install)
call composer install --no-interaction
if errorlevel 1 goto errore
:vendor_ok

if exist "node_modules" goto node_ok
echo [..] Installo le dipendenze JavaScript (npm install)
call npm install
if errorlevel 1 goto errore
:node_ok

rem --- Database: se manca lo creo con i dati demo, altrimenti applico le migrazioni in sospeso ---
if exist "database\database.sqlite" goto db_esiste
echo [..] Database mancante: lo creo con i dati demo
type nul > "database\database.sqlite"
call php artisan migrate --seed --force
if errorlevel 1 goto errore
goto db_ok
:db_esiste
call php artisan migrate --force
if errorlevel 1 goto errore
:db_ok

echo.
echo [OK] Tutto pronto. Indirizzo: http://localhost:8000
echo      Accessi demo (password: password):
echo        admin@digithub.test   manager@digithub.test   giulia@digithub.test
echo      Portale pubblico: http://localhost:8000/p/studio-rossi
echo.
echo Per fermare tutto: Ctrl+C in questa finestra, oppure ferma-progetto.bat
echo.

if defined NO_BROWSER goto avvio
start "" cmd /c "timeout /t 8 /nobreak >nul & start "" http://localhost:8000"

:avvio
call composer run dev
goto fine

:php_non_valido
echo [ERRORE] PHP non valido: serve la versione 8.4 o superiore, il progetto usa la 8.5.
php -v
echo Se vedi la 8.2 e' il PATH di XAMPP: chiudi questa finestra e riaprila.
goto fine_errore

:porta_occupata
echo [ERRORE] La porta 8000 e' gia' occupata: il progetto e' probabilmente gia' avviato.
echo Apri http://localhost:8000 oppure lancia prima ferma-progetto.bat
goto fine_errore

:errore
echo.
echo [ERRORE] Un passaggio e' fallito: leggi il messaggio qui sopra.

:fine_errore
echo.
pause
exit /b 1

:fine
echo.
pause
