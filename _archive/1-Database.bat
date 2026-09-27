@echo off
cd /d "%~dp0MariaDB\bin"
mysqld.exe --defaults-file="%~dp0MariaDB\data\my.ini" --console
pause