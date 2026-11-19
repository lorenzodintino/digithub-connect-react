@echo off
setlocal
title Atrio - Arresto progetto
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0gestione-server.ps1" -Action Stop
set "STOP_EXIT=%errorlevel%"
if not defined NO_PAUSE pause
exit /b %STOP_EXIT%
