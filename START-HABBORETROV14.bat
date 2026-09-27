@echo off
title HabboretroV14 - Lanceur
cd /d "%~dp0"

echo ============================================
echo   HabboretroV14 (Kepler v14) - Demarrage
echo ============================================
echo.
echo   Demarre : base MariaDB + Apache + emulateur.
echo   (Coupe le MySQL de Laragon : meme port 3306.)
echo.
pause

echo.
echo [1/4] Arret des MySQL/MariaDB en cours (liberation du port 3306)...
taskkill /f /im mysqld.exe >nul 2>&1
timeout /t 2 /nobreak >nul

echo [2/4] Demarrage de la base MariaDB du pack...
start "MariaDB - HabboretroV14" /min /d "%~dp0MariaDB\bin" mysqld.exe --defaults-file="%~dp0MariaDB\data\my.ini" --console

echo     Attente que la base soit prete (peut prendre 30 s a froid)...
set /a n=0
:waitdb
"%~dp0MariaDB\bin\mysql.exe" -u root -h 127.0.0.1 -P 3306 -e "SELECT 1" >nul 2>&1
if %errorlevel%==0 goto dbok
set /a n+=1
if %n% geq 40 goto dberr
timeout /t 2 /nobreak >nul
goto waitdb
:dberr
echo.
echo   /!\ La base ne repond pas. Regarde la fenetre "MariaDB - HabboretroV14"
echo       pour voir l'erreur. (Souvent : un autre MySQL occupe le port 3306.)
echo.
pause
goto :eof
:dbok
echo     Base v14 prete !

echo [3/4] Serveur web Apache...
netstat -ano | findstr ":80 " | findstr LISTENING >nul
if errorlevel 1 (
  for /d %%A in ("C:\laragon\bin\apache\httpd-*") do start "" /min "%%A\bin\httpd.exe"
  echo     Apache demarre.
) else (
  echo     Apache deja en marche.
)

echo [4/4] Demarrage de l'emulateur Kepler...
start "HabboretroV14 - Emulateur" /min "%~dp0Server\www\run.bat"

echo.
echo ============================================
echo   HabboretroV14 est lance !
echo.
echo   Jeu   : http://localhost/          (dans Basilisk)
echo   Admin : http://localhost/admin/    (Orms / Orms2026!)
echo ============================================
echo.
echo   Laisse les fenetres MariaDB et Emulateur ouvertes pendant que tu joues.
echo.
pause
