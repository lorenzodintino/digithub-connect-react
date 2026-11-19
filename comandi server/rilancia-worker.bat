@echo off
setlocal
title Atrio - Riavvio worker e scheduler
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0gestione-server.ps1" -Action RestartWorker
set "WORKER_EXIT=%errorlevel%"
if not defined NO_PAUSE pause
exit /b %WORKER_EXIT%
