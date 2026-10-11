@echo off
chcp 65001 >nul
title HabboretroV14 - Lanceur
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\pack.ps1" -Action start
echo.
pause
