@echo off
REM Lance le superviseur Minerva (relance auto). Fenetre masquee.
powershell -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "%~dp0start-minerva.ps1"
