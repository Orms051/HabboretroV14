# ============================================================================
# Superviseur du service d'avatars Minerva (HabboretroV14)
# - Minerva tourne dans une fenêtre CMD visible (titre « Minerva — Avatars Habbo »,
#   réduite, logs en direct). CE superviseur PowerShell reste caché et gère la
#   relance automatique.
# - Écoute UNIQUEMENT en local sur le port 5123.
# - Mono-instance (ne démarre pas si le port répond déjà).
# - Relance auto après plantage, délai croissant entre essais.
# - Journal d'événements limité en taille (rotation 5 Mo, 1 sauvegarde).
# - Arrêt volontaire via STOP (drapeau minerva.stop) : désactive la relance.
# Prérequis : runtime ASP.NET Core 8 dans .\dotnet (voir Server\www\avatar\README.md).
# ============================================================================
$ErrorActionPreference = 'Continue'
$base   = $PSScriptRoot
$dotnet = Join-Path $base 'dotnet\dotnet.exe'
$dll    = Join-Path $base 'win-x64\Minerva.dll'
$workdir= Join-Path $base 'win-x64'
$log    = Join-Path $base 'minerva.log'
$pidFile= Join-Path $base 'minerva.supervisor.pid'
$stopF  = Join-Path $base 'minerva.stop'
$port   = 5123

$env:DOTNET_ROOT = Join-Path $base 'dotnet'
$env:ASPNETCORE_URLS = "http://localhost:$port"   # localhost = jamais exposé au réseau
$env:DOTNET_CLI_TELEMETRY_OPTOUT = '1'

function Port-Busy { param($p) [bool](Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue) }
function Log([string]$m){ try{ if((Test-Path $log) -and (Get-Item $log).Length -gt 5MB){ Move-Item $log "$log.old" -Force -ErrorAction SilentlyContinue }; ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $m) | Out-File -FilePath $log -Append -Encoding utf8 }catch{} }

# --- Verrou atomique (anti-course) : empeche deux superviseurs simultanes meme
#     si le port n'est pas encore lie. Mutex nomme par installation. ---
$sha = [Security.Cryptography.SHA1]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes($base.ToLower()))
$mtxName = 'Global\HabboMinerva_' + (([BitConverter]::ToString($sha) -replace '-').Substring(0,16))
$script:Mtx = New-Object System.Threading.Mutex($false, $mtxName)
$gotLock = $false; try { $gotLock = $script:Mtx.WaitOne(0) } catch { $gotLock = $true }
if (-not $gotLock) { Write-Host "Un superviseur Minerva tourne deja (verrou)."; exit 0 }

# --- Gardes complementaires : ne pas cumuler Minerva ni plusieurs superviseurs ---
if (Port-Busy $port) { Write-Host "Minerva tourne deja (port $port). Rien a faire."; $script:Mtx.ReleaseMutex(); exit 0 }
if (Test-Path $pidFile) {
    $old = Get-Content $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($old -match '^\d+$' -and (Get-Process -Id $old -ErrorAction SilentlyContinue)) { Write-Host "Un superviseur Minerva tourne deja (PID $old)."; $script:Mtx.ReleaseMutex(); exit 0 }
}
if (Test-Path $stopF) { Remove-Item $stopF -Force -ErrorAction SilentlyContinue }
if (-not (Test-Path $dotnet)) { Write-Host "Runtime .NET absent ($dotnet). Voir README."; exit 1 }
if (-not (Test-Path $dll))    { Write-Host "Minerva.dll absent ($dll). Voir README."; exit 1 }

Set-Content -Path $pidFile -Value $PID -Encoding ascii
Log "Superviseur demarre (PID $PID)."

$delay = 5; $delayMax = 30
while ($true) {
    if (Test-Path $stopF) { Log "Arret demande."; break }

    # Lance Minerva dans une fenetre CMD visible (titre + logs en direct), reduite.
    # Le & final maintient la fenetre ouverte si le process se termine trop vite,
    # pour laisser lire l'erreur.
    $cmd = 'title Minerva - Avatars Habbo & "' + $dotnet + '" "' + $dll + '" --shockwave-badge-render'
    Log "Lancement de Minerva (fenetre CMD)."
    $started = Get-Date
    Start-Process 'cmd.exe' -ArgumentList '/c', $cmd -WorkingDirectory $workdir -WindowStyle Minimized -Wait
    $ran = (New-TimeSpan -Start $started -End (Get-Date)).TotalSeconds

    if (Test-Path $stopF) { Log "Arret demande (apres sortie)."; break }

    if ($ran -gt 60) { $delay = 5 } else { $delay = [Math]::Min($delay * 2, $delayMax) }
    Log ("Minerva s'est arrete (uptime {0:N0}s). Relance dans {1}s." -f $ran, $delay)
    Start-Sleep -Seconds $delay
}

Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
Remove-Item $stopF   -Force -ErrorAction SilentlyContinue
try { if($script:Mtx){ $script:Mtx.ReleaseMutex(); $script:Mtx.Dispose() } } catch {}
Log "Superviseur termine."
