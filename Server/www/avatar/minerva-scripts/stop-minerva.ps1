# Arrêt propre du service d'avatars Minerva (superviseur + processus).
$ErrorActionPreference = 'Continue'
$base    = $PSScriptRoot
$pidFile = Join-Path $base 'minerva.supervisor.pid'
$stopF   = Join-Path $base 'minerva.stop'
$port    = 5123

# 1) Drapeau d'arrêt : empêche le superviseur de relancer.
Set-Content -Path $stopF -Value '1' -Encoding ascii

# 2) Tuer le processus Minerva qui écoute sur 5123 (fait sortir le superviseur de l'attente).
#    L'écouteur = RemotePort=0 (fiable). PAS « -State Listen » : ce champ revient parfois VIDE
#    ici -> la liste était vide -> Minerva n'était jamais tuée -> « répond encore sur 5123 ».
$conns = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue |
         Where-Object { $_.RemotePort -eq 0 -and [int]$_.OwningProcess -gt 4 }
foreach ($c in $conns) {
    try { Stop-Process -Id $c.OwningProcess -Force -ErrorAction SilentlyContinue } catch {}
}

# 3) Repli : tuer le superviseur via son PID (s'il dort entre deux relances).
if (Test-Path $pidFile) {
    $supPid = (Get-Content $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1)
    if ($supPid -match '^\d+$') {
        $p = Get-Process -Id $supPid -ErrorAction SilentlyContinue
        if ($p -and $p.ProcessName -match 'powershell|pwsh') {
            try { Stop-Process -Id $supPid -Force -ErrorAction SilentlyContinue } catch {}
        }
    }
    Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
}

# Laisser le temps à dotnet de libérer le port (il peut prendre >400 ms) : on vérifie en boucle.
$sw = [Diagnostics.Stopwatch]::StartNew()
do {
    Start-Sleep -Milliseconds 300
    $still = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue |
             Where-Object { $_.RemotePort -eq 0 -and [int]$_.OwningProcess -gt 4 }
} while ($still -and $sw.Elapsed.TotalSeconds -lt 6)
Remove-Item $stopF -Force -ErrorAction SilentlyContinue
if ($still) {
    Write-Host "Attention : le port $port repond encore."
} else {
    Write-Host "Minerva arrete."
}
