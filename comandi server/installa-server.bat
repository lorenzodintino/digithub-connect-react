@echo off
setlocal
title Atrio - Installazione server
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0gestione-server.ps1" -Action Install
set "INSTALL_EXIT=%errorlevel%"
if not defined NO_PAUSE pause
exit /b %INSTALL_EXIT%
