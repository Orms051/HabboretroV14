$dir = Split-Path -Parent $PSScriptRoot
Start-Process cmd -ArgumentList '/c','run.bat' -WorkingDirectory $dir
