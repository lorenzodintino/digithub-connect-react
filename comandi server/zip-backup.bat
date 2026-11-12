@echo off
rem Backup completo: zip di TUTTA la cartella del progetto, compresi .env, database,
rem file caricati, vendor, node_modules e .git.
rem Lo zip finisce fuori dal progetto, in ..\digithub-connect-react-zip
rem Attenzione: contiene .env (password e chiavi) e i dati reali. Non condividerlo.
rem Con la variabile NO_PAUSE impostata non aspetta un tasto alla fine.
setlocal
title Zip backup
cd /d "%~dp0.."
set "ROOT=%CD%"
set "TAR=%SystemRoot%\System32\tar.exe"
set "DEST=%ROOT%\..\digithub-connect-react-zip"

echo.
echo === Zip backup completo ===
echo.

if not exist "%TAR%" goto manca_tar

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set "TS=%%i"
if not exist "%DEST%" mkdir "%DEST%"
set "ZIP=%DEST%\backup-%TS%.zip"

echo Origine:      %ROOT%
echo Destinazione: %ZIP%
echo Attendi, con node_modules e vendor ci vuole qualche minuto...
echo.

"%TAR%" -a -c -f "%ZIP%" -C "%ROOT%" .
if errorlevel 1 goto errore

echo.
echo [OK] Backup creato:
for %%f in ("%ZIP%") do echo      %%~ff  (%%~zf byte)
goto fine

:manca_tar
echo [ERRORE] tar.exe non trovato in %SystemRoot%\System32 (serve Windows 10 o successivo).
goto fine_errore

:errore
echo.
echo [ERRORE] Creazione dello zip fallita.
if exist "%ZIP%" del "%ZIP%"

:fine_errore
echo.
if not defined NO_PAUSE pause
exit /b 1

:fine
echo.
if not defined NO_PAUSE pause
