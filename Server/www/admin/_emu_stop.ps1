Get-CimInstance Win32_Process -Filter "Name='java.exe'" | Where-Object { $_.CommandLine -like '*kepler*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
