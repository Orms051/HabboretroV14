@echo off
title HabboretroV14 - Arret
echo Arret de HabboretroV14 (emulateur + MariaDB + Apache)...

REM Emulateur Kepler (java)
for /f "tokens=2 delims=," %%P in ('tasklist /fi "imagename eq java.exe" /fo csv /nh 2^>nul') do (
  wmic process where "ProcessId=%%~P" get CommandLine 2>nul | findstr /i "kepler" >nul && taskkill /pid %%~P /f >nul 2>&1
)
REM MariaDB du pack
for /f "tokens=2 delims=," %%P in ('tasklist /fi "imagename eq mysqld.exe" /fo csv /nh 2^>nul') do (
  wmic process where "ProcessId=%%~P" get ExecutablePath 2>nul | findstr /i "HabboretroV14" >nul && taskkill /pid %%~P /f >nul 2>&1
)
REM Apache
taskkill /im httpd.exe /f >nul 2>&1

echo Termine. (Tu peux relancer MySQL dans Laragon pour tes autres projets.)
timeout /t 3 >nul
