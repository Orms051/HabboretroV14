<#
  pack.ps1 — Lanceur du rétro HabboretroV14 (démarrage / arrêt propres).
  Appelé par START-HABBORETROV14.bat (-Action start) et STOP-...bat (-Action stop).

  Principes :
   - Ne touche QUE les composants du pack, identifiés par leur CHEMIN (pas juste
     le nom de process ni un port ouvert).
   - Aucun kill global (pas de "taskkill /f /im mysqld.exe" ni "httpd.exe").
   - Réutilise un service déjà lancé par le pack ; signale (sans tuer) un port
     occupé par une application étrangère.
   - Respecte les dépendances (MariaDB prête AVANT Kepler) et vérifie que chaque
     service est RÉELLEMENT prêt (pas seulement "le port répond").
   - Apache est le service PARTAGÉ de Laragon : on le démarre si besoin, mais on
     ne l'arrête jamais (d'autres projets peuvent l'utiliser).
#>
param([ValidateSet('start','stop')] [string]$Action = 'start')

$ErrorActionPreference = 'Continue'
# Accents lisibles dans la fenêtre cmd.
try { [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false); $OutputEncoding = [System.Text.UTF8Encoding]::new($false) } catch {}

# ============================================================================
#  CONFIGURATION — tout ce qui dépend de la machine est ICI (un seul endroit).
# ============================================================================
$Root = Split-Path -Parent $PSScriptRoot          # racine du pack (scripts/ est dessous)
$Cfg = @{
    Root        = $Root
    Www         = Join-Path $Root 'Server\www'
    MariaDir    = Join-Path $Root 'MariaDB'
    MariaIni    = Join-Path $Root 'MariaDB\data\my.ini'
    MinervaDir  = Join-Path $Root 'tools\minerva-bin'
    LogDir      = Join-Path $Root 'tools\pack-logs'
    # Chemin externe configurable (Apache de Laragon). Vide => détection auto s'il
    # n'existe qu'une seule installation dans C:\laragon\bin\apache.
    ApacheDir   = ''
    ApacheGlob  = 'C:\laragon\bin\apache\httpd-*'
    DbName      = 'v14'
    DbUser      = 'root'
    DbTables    = @('users','rooms','items','catalogue_items','settings')  # tables indispensables
    Ports       = @{ Db=3306; Web=80; Game=12321; Rcon=12309; Minerva=5123 }
    WebUrl      = 'http://localhost/'
    WebMarker   = "l'H"  # marqueur attendu dans la page d'accueil ("l'Hôtel…")
    StartTimeoutDb      = 45
    StartTimeoutWeb     = 15
    StartTimeoutGame    = 30
    StartTimeoutMinerva = 30
    StopTimeoutKepler   = 25
    StopTimeoutDb       = 25
}

# ============================================================================
#  Utilitaires affichage + journal
# ============================================================================
function Say([string]$tag,[string]$msg,[string]$color='Gray'){
    $t = switch($tag){ 'OK'{'[ Prêt ]'} 'RUN'{'[ Démarrage ]'} 'HAVE'{'[ Déjà actif ]'} 'FAIL'{'[ ÉCHEC ]'} 'INFO'{'[ Info ]'} 'STOP'{'[ Arrêt ]'} default{"[$tag]"} }
    $c = switch($tag){ 'OK'{'Green'} 'HAVE'{'DarkGreen'} 'RUN'{'Cyan'} 'FAIL'{'Red'} 'STOP'{'Yellow'} default{'Gray'} }
    Write-Host ("{0,-14} " -f $t) -ForegroundColor $c -NoNewline; Write-Host $msg
    Log "$t $msg"
}
function Log([string]$line){
    try{
        if(-not (Test-Path $Cfg.LogDir)){ New-Item -ItemType Directory -Force -Path $Cfg.LogDir | Out-Null }
        $f = Join-Path $Cfg.LogDir 'pack.log'
        if((Test-Path $f) -and (Get-Item $f).Length -gt 2MB){ Move-Item $f "$f.old" -Force -ErrorAction SilentlyContinue }
        ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), ($line -replace '\s+',' ')) | Out-File -FilePath $f -Append -Encoding utf8
    }catch{}
}

# ============================================================================
#  Détection par CHEMIN (pas juste par nom/port)
# ============================================================================
function Get-ProcByPath([string]$name,[string]$pathLike){
    Get-CimInstance Win32_Process -Filter "Name='$name'" -ErrorAction SilentlyContinue |
        Where-Object { $_.ExecutablePath -and $_.ExecutablePath -like $pathLike }
}
function Get-ProcByCmd([string]$name,[string]$cmdLike){
    Get-CimInstance Win32_Process -Filter "Name='$name'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -and $_.CommandLine -like $cmdLike }
}
function Get-PortOwner([int]$port){
    $c = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if(-not $c){ return $null }
    $p = Get-Process -Id $c.OwningProcess -ErrorAction SilentlyContinue
    $path = $null; try { $path = (Get-CimInstance Win32_Process -Filter "ProcessId=$($c.OwningProcess)" -ErrorAction SilentlyContinue).ExecutablePath } catch {}
    [pscustomobject]@{ Pid=$c.OwningProcess; Name=$p.ProcessName; Path=$path }
}
function Resolve-ApacheDir {
    if($Cfg.ApacheDir -and (Test-Path (Join-Path $Cfg.ApacheDir 'bin\httpd.exe'))){ return $Cfg.ApacheDir }
    $dirs = @(Get-ChildItem $Cfg.ApacheGlob -Directory -ErrorAction SilentlyContinue | Where-Object { Test-Path (Join-Path $_.FullName 'bin\httpd.exe') })
    if($dirs.Count -eq 1){ return $dirs[0].FullName }
    return $null  # 0 ou plusieurs => on refuse de deviner
}

# ============================================================================
#  Vérification des prérequis
# ============================================================================
function Test-Prereqs {
    $miss = @()
    if(-not (Test-Path (Join-Path $Cfg.MariaDir 'bin\mysqld.exe'))){ $miss += "MariaDB : $($Cfg.MariaDir)\bin\mysqld.exe introuvable (pack MariaDB manquant)." }
    if(-not (Test-Path $Cfg.MariaIni)){ $miss += "MariaDB : $($Cfg.MariaIni) introuvable (configuration de la base)." }
    $apache = Resolve-ApacheDir
    if(-not $apache){ $miss += "Apache : aucune (ou plusieurs) installation sous '$($Cfg.ApacheGlob)'. Renseigne ApacheDir dans scripts\pack.ps1." }
    if(-not (Test-Path (Join-Path $Cfg.Www 'kepler.jar'))){ $miss += "Kepler : $($Cfg.Www)\kepler.jar introuvable." }
    if(-not (Test-Path (Join-Path $Cfg.Www 'run.bat'))){ $miss += "Kepler : $($Cfg.Www)\run.bat introuvable." }
    if(-not (Test-Path (Join-Path $Cfg.Www 'lib'))){ $miss += "Kepler : $($Cfg.Www)\lib (dépendances .jar) introuvable." }
    if(-not (Test-Path (Join-Path $Cfg.MinervaDir 'win-x64\Minerva.dll'))){ $miss += "Minerva : $($Cfg.MinervaDir)\win-x64\Minerva.dll introuvable (voir Server\www\avatar\README.md)." }
    if(-not (Test-Path (Join-Path $Cfg.MinervaDir 'dotnet\dotnet.exe'))){ $miss += "Minerva : runtime .NET local absent ($($Cfg.MinervaDir)\dotnet). Voir Server\www\avatar\README.md." }
    return $miss
}

# ============================================================================
#  Attentes de disponibilité RÉELLE
# ============================================================================
function Invoke-Mysql([string]$sql){
    $exe = Join-Path $Cfg.MariaDir 'bin\mysql.exe'
    if(-not (Test-Path $exe)){ return $null }
    & $exe -u $Cfg.DbUser -h 127.0.0.1 -P $Cfg.Ports.Db -N -B -e $sql 2>$null
}
function Wait-Db([int]$timeout){
    $sw=[Diagnostics.Stopwatch]::StartNew()
    while($sw.Elapsed.TotalSeconds -lt $timeout){
        $n = Invoke-Mysql ("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{0}' AND table_name IN ('{1}')" -f $Cfg.DbName, ($Cfg.DbTables -join "','"))
        if($n -and [int]$n -ge $Cfg.DbTables.Count){ return $true }
        Start-Sleep -Milliseconds 1200
    }
    return $false
}
function Wait-Web([int]$timeout){
    $sw=[Diagnostics.Stopwatch]::StartNew()
    while($sw.Elapsed.TotalSeconds -lt $timeout){
        try{ $r = Invoke-WebRequest -Uri $Cfg.WebUrl -UseBasicParsing -TimeoutSec 4; if($r.StatusCode -eq 200 -and $r.Content -like "*$($Cfg.WebMarker)*"){ return $true } }catch{}
        Start-Sleep -Milliseconds 1000
    }
    return $false
}
function Wait-Port([int]$port,[int]$timeout){
    $sw=[Diagnostics.Stopwatch]::StartNew()
    while($sw.Elapsed.TotalSeconds -lt $timeout){ if(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue){ return $true }; Start-Sleep -Milliseconds 800 }
    return $false
}
function Wait-Minerva([int]$timeout){
    $sw=[Diagnostics.Stopwatch]::StartNew()
    $u = "http://localhost:$($Cfg.Ports.Minerva)/habbo-imaging/avatarimage?figure=hr-100-1-1.hd-180-1.ch-210-1.lg-270-1.sh-290-1&size=b"
    while($sw.Elapsed.TotalSeconds -lt $timeout){
        try{
            $r = Invoke-WebRequest -Uri $u -UseBasicParsing -TimeoutSec 5
            $b = $r.Content; if($b -is [string]){ $b=[Text.Encoding]::GetEncoding('ISO-8859-1').GetBytes($b) }
            if($b.Length -gt 8 -and $b[0]-eq 0x89 -and $b[1]-eq 0x50 -and $b[2]-eq 0x4E -and $b[3]-eq 0x47){ return $true }  # signature PNG
        }catch{}
        Start-Sleep -Milliseconds 1000
    }
    return $false
}

# ============================================================================
#  DÉMARRAGE
# ============================================================================
function Start-Pack {
    Write-Host ''
    Write-Host '============================================================'
    Write-Host '  HabboretroV14 — Démarrage' -ForegroundColor White
    Write-Host '============================================================'
    Log 'DEMARRAGE demandé'

    # 0) Prérequis
    $miss = Test-Prereqs
    if($miss.Count){
        Say 'FAIL' "Prérequis manquants :"
        $miss | ForEach-Object { Write-Host ('   - ' + $_) -ForegroundColor Red }
        Write-Host ''; Write-Host "Corrige les points ci-dessus puis relance." -ForegroundColor Yellow
        return 1
    }
    $apacheDir = Resolve-ApacheDir

    # 1) MariaDB (pack)
    $dbOwner = Get-PortOwner $Cfg.Ports.Db
    $dbPack  = Get-ProcByPath 'mysqld.exe' (Join-Path $Cfg.MariaDir '*')
    if($dbPack){
        Say 'HAVE' "MariaDB (pack) déjà en marche (port $($Cfg.Ports.Db))."
    } elseif($dbOwner){
        Say 'FAIL' "Le port $($Cfg.Ports.Db) est occupé par une AUTRE application : $($dbOwner.Name) (PID $($dbOwner.Pid))."
        Write-Host "   -> C'est sans doute le MySQL de Laragon ou un autre service. Arrête-le toi-même (je ne tue rien)." -ForegroundColor Yellow
        Write-Host "   -> $($dbOwner.Path)" -ForegroundColor DarkGray
        return 1
    } else {
        Say 'RUN' "MariaDB (pack)…"
        Start-Process (Join-Path $Cfg.MariaDir 'bin\mysqld.exe') -ArgumentList "--defaults-file=`"$($Cfg.MariaIni)`"" -WorkingDirectory (Join-Path $Cfg.MariaDir 'bin') -WindowStyle Minimized
        if(Wait-Db $Cfg.StartTimeoutDb){ Say 'OK' "Base '$($Cfg.DbName)' prête (tables vérifiées)." }
        else { Say 'FAIL' "MariaDB ne répond pas / base '$($Cfg.DbName)' incomplète après $($Cfg.StartTimeoutDb)s. Voir la fenêtre MariaDB."; return 1 }
    }
    # Revérifier que la base est bien utilisable même si déjà en marche
    if($dbPack){ if(Wait-Db 6){ } else { Say 'FAIL' "MariaDB tourne mais la base '$($Cfg.DbName)' n'est pas prête (tables manquantes)."; return 1 } }

    # 2) Apache (PARTAGÉ Laragon) — démarrer si absent, une seule installation
    $webOwner = Get-PortOwner $Cfg.Ports.Web
    if($webOwner){
        $isApache = $webOwner.Path -and ($webOwner.Path -like (Join-Path $apacheDir '*'))
        if($isApache -or $webOwner.Name -eq 'httpd'){ Say 'HAVE' "Serveur web déjà en marche (Apache, port $($Cfg.Ports.Web))." }
        else { Say 'FAIL' "Le port $($Cfg.Ports.Web) est occupé par une AUTRE application : $($webOwner.Name) (PID $($webOwner.Pid)). Je ne la tue pas."; return 1 }
    } else {
        Say 'RUN' "Serveur web (Apache $([IO.Path]::GetFileName($apacheDir)))…"
        Start-Process (Join-Path $apacheDir 'bin\httpd.exe') -WorkingDirectory (Join-Path $apacheDir 'bin') -WindowStyle Minimized
    }
    if(Wait-Web $Cfg.StartTimeoutWeb){ Say 'OK' "Site accessible ($($Cfg.WebUrl))." }
    else { Say 'FAIL' "Le site ne répond pas correctement sur $($Cfg.WebUrl) après $($Cfg.StartTimeoutWeb)s."; return 1 }

    # 3) Kepler (pack) — dépend de MariaDB
    $kepler = Get-ProcByCmd 'java.exe' '*kepler*'
    if($kepler){ Say 'HAVE' "Émulateur Kepler déjà en marche (port $($Cfg.Ports.Game))." }
    else {
        $gameOwner = Get-PortOwner $Cfg.Ports.Game
        if($gameOwner){ Say 'FAIL' "Le port $($Cfg.Ports.Game) est occupé par une autre application : $($gameOwner.Name) (PID $($gameOwner.Pid))."; return 1 }
        Say 'RUN' "Émulateur Kepler…"
        Start-Process 'cmd.exe' -ArgumentList '/c','run.bat' -WorkingDirectory $Cfg.Www -WindowStyle Minimized
        if(Wait-Port $Cfg.Ports.Game $Cfg.StartTimeoutGame){ Say 'OK' "Kepler démarré (écoute le jeu sur $($Cfg.Ports.Game))." }
        else { Say 'FAIL' "Kepler n'écoute pas sur $($Cfg.Ports.Game) après $($Cfg.StartTimeoutGame)s. Voir la fenêtre Émulateur / server.log."; return 1 }
    }

    # 4) Minerva (pack) — non bloquant : une panne laisse le site + le jeu marcher
    $minervaUp = (Get-PortOwner $Cfg.Ports.Minerva) -ne $null
    if($minervaUp){ Say 'HAVE' "Avatars (Minerva) déjà en marche (port $($Cfg.Ports.Minerva))." }
    else {
        Say 'RUN' "Avatars (Minerva)…"
        $sup = Join-Path $Cfg.MinervaDir 'start-minerva.ps1'
        if(Test-Path $sup){ Start-Process 'powershell' -ArgumentList '-NoProfile','-WindowStyle','Hidden','-ExecutionPolicy','Bypass','-File',$sup -WindowStyle Hidden }
    }
    if(Wait-Minerva $Cfg.StartTimeoutMinerva){ Say 'OK' "Avatars prêts (image de test valide)." }
    else { Say 'INFO' "Avatars : MODE SECOURS (Minerva indisponible). Le site et le jeu fonctionnent ; une silhouette s'affiche à la place des avatars." }

    Show-Summary
    return 0
}

# ============================================================================
#  ARRÊT (propre, ciblé pack uniquement)
# ============================================================================
function Stop-Pack {
    Write-Host ''
    Write-Host '============================================================'
    Write-Host '  HabboretroV14 — Arrêt' -ForegroundColor White
    Write-Host '============================================================'
    Log 'ARRET demandé'

    # 1) Minerva : désactiver la supervision AVANT de stopper le process
    $stopMin = Join-Path $Cfg.MinervaDir 'stop-minerva.ps1'
    if((Get-PortOwner $Cfg.Ports.Minerva) -or (Test-Path (Join-Path $Cfg.MinervaDir 'minerva.supervisor.pid'))){
        Say 'STOP' "Avatars (Minerva) : arrêt de la supervision + du service…"
        if(Test-Path $stopMin){ & powershell -NoProfile -ExecutionPolicy Bypass -File $stopMin *> $null }
        Start-Sleep -Milliseconds 400
        if(Get-PortOwner $Cfg.Ports.Minerva){ Say 'FAIL' "Minerva répond encore sur $($Cfg.Ports.Minerva)." } else { Say 'OK' "Avatars arrêtés." }
    } else { Say 'INFO' "Avatars (Minerva) déjà arrêtés." }

    # 2) Kepler : arrêt PROPRE (shutdown hook JVM via taskkill sans /f), puis attente
    $kepler = Get-ProcByCmd 'java.exe' '*kepler*'
    if($kepler){
        foreach($k in $kepler){
            Say 'STOP' "Émulateur Kepler : arrêt propre (PID $($k.ProcessId))…"
            & taskkill /PID $k.ProcessId *> $null    # SANS /f : déclenche le shutdown hook (sauvegarde)
        }
        $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
        while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutKepler){ if(-not (Get-ProcByCmd 'java.exe' '*kepler*')){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
        if($gone){ Say 'OK' "Kepler arrêté proprement." }
        else {
            Say 'FAIL' "Kepler ne s'est pas arrêté en $($Cfg.StopTimeoutKepler)s."
            Write-Host "   -> Arrêt forcé ciblé (dernier recours) :" -ForegroundColor Yellow
            foreach($k in (Get-ProcByCmd 'java.exe' '*kepler*')){ & taskkill /F /PID $k.ProcessId *> $null; Say 'STOP' "Kepler forcé (PID $($k.ProcessId))." }
        }
    } else { Say 'INFO' "Émulateur Kepler déjà arrêté." }

    # 3) MariaDB (pack) : arrêt propre via mysqladmin shutdown — APRÈS Kepler
    $dbPack = Get-ProcByPath 'mysqld.exe' (Join-Path $Cfg.MariaDir '*')
    if($dbPack){
        Say 'STOP' "Base MariaDB (pack) : arrêt propre…"
        $adm = Join-Path $Cfg.MariaDir 'bin\mysqladmin.exe'
        if(Test-Path $adm){ & $adm -u $Cfg.DbUser -h 127.0.0.1 -P $Cfg.Ports.Db shutdown *> $null }
        $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
        while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutDb){ if(-not (Get-ProcByPath 'mysqld.exe' (Join-Path $Cfg.MariaDir '*'))){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
        if($gone){ Say 'OK' "Base arrêtée proprement." }
        else { Say 'FAIL' "MariaDB (pack) ne s'est pas arrêtée en $($Cfg.StopTimeoutDb)s (arrêt forcé non appliqué automatiquement — à faire manuellement si besoin)." }
    } else { Say 'INFO' "Base MariaDB (pack) déjà arrêtée." }

    # 4) Apache : PARTAGÉ (Laragon) — on ne l'arrête pas
    if(Get-PortOwner $Cfg.Ports.Web){ Say 'INFO' "Apache laissé en marche (service partagé Laragon, utilisé par d'autres projets)." }

    Write-Host ''
    Write-Host "Rétro arrêté. (MySQL de Laragon reste disponible pour tes autres projets.)" -ForegroundColor Green
    Log 'ARRET terminé'
    return 0
}

# ============================================================================
#  Bilan + adresses
# ============================================================================
function Show-Summary {
    Write-Host ''
    Write-Host '------------------------------------------------------------'
    Write-Host '  Bilan' -ForegroundColor White
    $db  = if(Get-ProcByPath 'mysqld.exe' (Join-Path $Cfg.MariaDir '*')){'en marche'}else{'arrêtée'}
    $web = if(Get-PortOwner $Cfg.Ports.Web){'en marche'}else{'arrêté'}
    $kep = if(Get-ProcByCmd 'java.exe' '*kepler*'){'en marche'}else{'arrêté'}
    $min = if(Get-PortOwner $Cfg.Ports.Minerva){'en marche'}else{'MODE SECOURS'}
    Write-Host ("   MariaDB (base)   : {0}" -f $db)
    Write-Host ("   Apache (site)    : {0}" -f $web)
    Write-Host ("   Kepler (jeu)     : {0}" -f $kep)
    Write-Host ("   Minerva (avatars): {0}" -f $min)
    Write-Host '------------------------------------------------------------'
    Write-Host '  Adresses' -ForegroundColor White
    Write-Host '   Site  : http://localhost/'
    Write-Host '   Admin : http://localhost/admin/'
    Write-Host '   Jeu   : http://localhost/client.php   (dans Basilisk)'
    Write-Host '------------------------------------------------------------'
    Write-Host "  Laisse ouvertes les fenêtres MariaDB, Émulateur et « Minerva — Avatars Habbo »." -ForegroundColor Gray
    Write-Host "  Pour arrêter le rétro : double-clique STOP-HABBORETROV14.bat" -ForegroundColor Gray
    Write-Host ("  Journal : {0}\pack.log" -f $Cfg.LogDir) -ForegroundColor DarkGray
}

# ============================================================================
switch($Action){
    'start'{ exit (Start-Pack) }
    'stop' { exit (Stop-Pack) }
}
