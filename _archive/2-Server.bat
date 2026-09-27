@echo off
cd /d "%~dp0Server\www"
start "" "%~dp0Server\php\php.exe" -S 127.0.0.1:80
exit