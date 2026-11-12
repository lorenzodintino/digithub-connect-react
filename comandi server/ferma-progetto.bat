@echo off
rem Ferma tutto cio' che avvia-progetto.bat ha lanciato: server web (porta 8000),
rem Vite (porta 5173), worker della coda e scheduler, anche se sono rimasti orfani.
rem Attenzione: chiude TUTTI i processi "php artisan serve / queue / schedule" della macchina.
rem Con la variabile NO_PAUSE impostata non aspetta un tasto alla fine.
setlocal
title Ferma progetto
cd /d "%~dp0.."
set "ROOT=%CD%"

echo.
echo === Arresto del progetto ===
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command "$n = 0; Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'node.exe' -and $_.CommandLine -like ('*' + $env:ROOT + '*') -and $_.CommandLine -match 'vite|concurrently' } | ForEach-Object { taskkill /PID $_.ProcessId /T /F 2>$null | Out-Null; $n++; Write-Host ('  chiuso node (' + $_.ProcessId + ')') }; Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'php.exe' -and $_.CommandLine -match 'artisan(\.php)?[\x22'' ]+(serve|queue:|schedule:)' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue; $n++; Write-Host ('  chiuso php (' + $_.ProcessId + '): ' + $_.CommandLine) }; foreach ($p in 8000, 5173) { Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue; $n++; Write-Host ('  chiuso processo sulla porta ' + $p + ' (' + $_.OwningProcess + ')') } }; if ($n -eq 0) { Write-Host '  Niente da fermare: il progetto non era avviato.' } else { Write-Host ''; Write-Host '[OK] Progetto fermato.' }"

echo.
if not defined NO_PAUSE pause
