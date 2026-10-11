# ============================================================================
# Superviseur du service d'avatars Minerva (HabboretroV14)
# - Écoute UNIQUEMENT en local sur le port 5123.
# - Garde mono-instance (ne démarre pas si le port répond déjà).
# - Relance automatique en cas de plantage, avec délai croissant entre essais.
# - Journal limité en taille (rotation à 5 Mo, 1 sauvegarde).
# - Arrêt propre via STOP-HABBORETROV14.bat (drapeau minerva.stop).
# Prérequis : runtime ASP.NET Core 8 dans .\dotnet (voir Server/www/avatar/README.md).
# ============================================================================
$ErrorActionPreference = 'Continue'
$base   = $PSScriptRoot
$dotnet = Join-Path $base 'dotnet\dotnet.exe'
$dll    = Join-Path $base 'win-x64\Minerva.dll'
$log    = Join-Path $base 'minerva.log'
$pidFile= Join-Path $base 'minerva.supervisor.pid'
$stopF  = Join-Path $base 'minerva.stop'
$port   = 5123

$env:DOTNET_ROOT = Join-Path $base 'dotnet'
$env:ASPNETCORE_URLS = "http://localhost:$port"   # localhost = jamais exposé au réseau
$env:DOTNET_CLI_TELEMETRY_OPTOUT = '1'

function Port-Busy { param($p) [bool](Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue) }

# --- Garde mono-instance ---
if (Port-Busy $port) { Write-Host "Minerva tourne deja (port $port). Rien a faire."; exit 0 }
if (Test-Path $stopF) { Remove-Item $stopF -Force -ErrorAction SilentlyContinue }
if (-not (Test-Path $dotnet)) { Write-Host "Runtime .NET absent ($dotnet). Voir README."; exit 1 }
if (-not (Test-Path $dll))    { Write-Host "Minerva.dll absent ($dll). Voir README."; exit 1 }

Set-Content -Path $pidFile -Value $PID -Encoding ascii
Set-Location (Join-Path $base 'win-x64')

$delay = 5            # secondes entre deux relances
$delayMax = 30
Write-Host "Superviseur Minerva demarre (PID $PID). Logs: $log"

while ($true) {
    if (Test-Path $stopF) { Write-Host "Arret demande."; break }

    # Rotation du journal (> 5 Mo -> .old)
    if ((Test-Path $log) -and ((Get-Item $log).Length -gt 5MB)) {
        Move-Item $log "$log.old" -Force -ErrorAction SilentlyContinue
    }
    ("[{0}] Lancement de Minerva..." -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')) | Out-File -FilePath $log -Append -Encoding utf8

    $started = Get-Date
    # Lancement bloquant ; toutes les sorties vont dans le journal.
    & $dotnet $dll --shockwave-badge-render *>> $log
    $ran = (New-TimeSpan -Start $started -End (Get-Date)).TotalSeconds

    if (Test-Path $stopF) { Write-Host "Arret demande apres sortie."; break }

    # Backoff : si ca a tenu > 60 s, on repart du delai mini ; sinon on augmente.
    if ($ran -gt 60) { $delay = 5 } else { $delay = [Math]::Min($delay * 2, $delayMax) }
    ("[{0}] Minerva s'est arrete (uptime {1:N0}s). Relance dans {2}s." -f (Get-Date -Format 'HH:mm:ss'), $ran, $delay) | Out-File -FilePath $log -Append -Encoding utf8
    Start-Sleep -Seconds $delay
}

Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
Remove-Item $stopF   -Force -ErrorAction SilentlyContinue
Write-Host "Superviseur Minerva termine."
