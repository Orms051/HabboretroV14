<#
  pack.ps1 — Lanceur du rétro HabboretroV14 (démarrage / arrêt propres).
  Appelé par START-HABBORETROV14.bat (-Action start) et STOP-...bat (-Action stop).

  Principes :
   - Agit UNIQUEMENT sur les composants de CE pack, identifiés de façon vérifiable
     (chemin du binaire, marqueur de lancement) — jamais par nom seul ni port seul.
   - Aucun kill global. Un port pris par une autre appli est signalé, pas tué.
   - Verrou atomique par installation : deux lanceurs simultanés ne se marchent
     pas dessus.
   - Respecte les dépendances et vérifie la disponibilité RÉELLE (pas juste un
     port ouvert). Le bilan reflète ces contrôles.
   - Apache = service PARTAGÉ de Laragon : démarré si besoin, jamais arrêté.
   - Minerva (avatars) est FACULTATIF : son absence n'empêche pas de démarrer le
     reste ; le site passe en « mode secours » (silhouette).
#>
param([ValidateSet('start','stop')] [string]$Action = 'start')

$ErrorActionPreference = 'Continue'
try { [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new($false); $OutputEncoding = [System.Text.UTF8Encoding]::new($false) } catch {}

# ============================================================================
#  CONFIGURATION — tout ce qui dépend de la machine est ICI (un seul endroit).
# ============================================================================
$Root = Split-Path -Parent $PSScriptRoot
$Cfg = @{
    Root        = $Root
    Www         = Join-Path $Root 'Server\www'
    MariaDir    = Join-Path $Root 'MariaDB'
    MariaIni    = Join-Path $Root 'MariaDB\data\my.ini'
    MinervaDir  = Join-Path $Root 'tools\minerva-bin'
    MinervaSrc  = Join-Path $Root 'Server\www\avatar\minerva-scripts'   # scripts versionnés (source de vérité)
    LogDir      = Join-Path $Root 'tools\pack-logs'
    ApacheDir   = ''                                   # vide => détection auto si une seule install
    ApacheGlob  = 'C:\laragon\bin\apache\httpd-*'
    DbName      = 'v14'
    DbUser      = 'root'
    DbTables    = @('users','rooms','items','catalogue_items','settings')
    Ports       = @{ Db=3306; Web=80; Game=12321; Rcon=12309; Minerva=5123 }
    WebUrl      = 'http://localhost/'
    WebMarker   = "l'H"
    StartTimeoutDb=45; StartTimeoutWeb=15; StartTimeoutGame=30; StartTimeoutMinerva=25
    StopTimeoutKepler=25; StopTimeoutDb=25
}

# ============================================================================
#  Affichage + journal
# ============================================================================
function Say([string]$tag,[string]$msg){
    $t = switch($tag){ 'OK'{'[ Prêt ]'} 'RUN'{'[ Démarrage ]'} 'HAVE'{'[ Déjà actif ]'} 'FAIL'{'[ Échec ]'} 'INFO'{'[ Info ]'} 'WARN'{'[ Attention ]'} 'STOP'{'[ Arrêt ]'} default{"[$tag]"} }
    $c = switch($tag){ 'OK'{'Green'} 'HAVE'{'DarkGreen'} 'RUN'{'Cyan'} 'FAIL'{'Red'} 'WARN'{'Yellow'} 'STOP'{'Yellow'} default{'Gray'} }
    Write-Host ("{0,-14} " -f $t) -ForegroundColor $c -NoNewline; Write-Host $msg
    Log "$t $msg"
}
function Log([string]$line){
    try{
        if(-not (Test-Path $Cfg.LogDir)){ New-Item -ItemType Directory -Force -Path $Cfg.LogDir | Out-Null }
        $f = Join-Path $Cfg.LogDir 'pack.log'
        if((Test-Path $f) -and (Get-Item $f).Length -gt 2MB){ Move-Item $f "$f.old" -Force -EA SilentlyContinue }
        ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), ($line -replace '\s+',' ')) | Out-File -FilePath $f -Append -Encoding utf8
    }catch{}
}

# ============================================================================
#  Verrou atomique par installation (évite deux lanceurs simultanés)
# ============================================================================
function Lock-Pack {
    $sha = [Security.Cryptography.SHA1]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes($Cfg.Root.ToLower()))
    $name = 'Global\HabboPack_' + (([BitConverter]::ToString($sha) -replace '-').Substring(0,16))
    $mtx = New-Object System.Threading.Mutex($false, $name)
    $got = $false; try { $got = $mtx.WaitOne(0) } catch { $got = $true }  # abandonné => on reprend
    if(-not $got){ return $null }
    return $mtx
}

# ============================================================================
#  Détection vérifiable (chemin / marqueur) — jamais par nom seul
# ============================================================================
function Get-PortOwner([int]$port){
    $c = Get-NetTCPConnection -LocalPort $port -State Listen -EA SilentlyContinue | Select-Object -First 1
    if(-not $c){ return $null }
    $path = $null; try { $path = (Get-CimInstance Win32_Process -Filter "ProcessId=$($c.OwningProcess)" -EA SilentlyContinue).ExecutablePath } catch {}
    $nm = (Get-Process -Id $c.OwningProcess -EA SilentlyContinue).ProcessName
    [pscustomobject]@{ Pid=$c.OwningProcess; Name=$nm; Path=$path }
}
function Under([string]$path,[string]$dir){ return ($path -and $dir -and ($path.ToLower().StartsWith($dir.ToLower()))) }
function Resolve-ApacheDir {
    if($Cfg.ApacheDir -and (Test-Path (Join-Path $Cfg.ApacheDir 'bin\httpd.exe'))){ return $Cfg.ApacheDir }
    $dirs = @(Get-ChildItem $Cfg.ApacheGlob -Directory -EA SilentlyContinue | Where-Object { Test-Path (Join-Path $_.FullName 'bin\httpd.exe') })
    if($dirs.Count -eq 1){ return $dirs[0].FullName }
    return $null
}
function Get-PackMysqld { Get-CimInstance Win32_Process -Filter "Name='mysqld.exe'" -EA SilentlyContinue | Where-Object { Under $_.ExecutablePath $Cfg.MariaDir } }
function Get-PackKepler { Get-CimInstance Win32_Process -Filter "Name='java.exe'" -EA SilentlyContinue | Where-Object { $_.CommandLine -and $_.CommandLine -like '*habbo.pack*' -and $_.CommandLine -like "*$($Cfg.Www)*" } }

# ============================================================================
#  Disponibilité RÉELLE
# ============================================================================
function Invoke-Mysql([string]$sql){
    $exe = Join-Path $Cfg.MariaDir 'bin\mysql.exe'
    if(-not (Test-Path $exe)){ return $null }
    & $exe -u $Cfg.DbUser -h 127.0.0.1 -P $Cfg.Ports.Db -N -B -e $sql 2>$null
}
function Test-DbPackOnPort {  # le 3306 est-il tenu par NOTRE mysqld ?
    $o = Get-PortOwner $Cfg.Ports.Db
    return ($o -and (Under $o.Path $Cfg.MariaDir))
}
function Test-DbReady {
    if(-not (Test-DbPackOnPort)){ return $false }
    $n = Invoke-Mysql ("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='{0}' AND table_name IN ('{1}')" -f $Cfg.DbName, ($Cfg.DbTables -join "','"))
    return ($n -and [int]$n -ge $Cfg.DbTables.Count)
}
function Wait-DbReady([int]$t){ $sw=[Diagnostics.Stopwatch]::StartNew(); while($sw.Elapsed.TotalSeconds -lt $t){ if(Test-DbReady){return $true}; Start-Sleep -Milliseconds 1200 }; return $false }
function Test-WebReady {
    try{ $r = Invoke-WebRequest -Uri $Cfg.WebUrl -UseBasicParsing -TimeoutSec 4; return ($r.StatusCode -eq 200 -and $r.Content -like "*$($Cfg.WebMarker)*") }catch{ return $false }
}
function Wait-WebReady([int]$t){ $sw=[Diagnostics.Stopwatch]::StartNew(); while($sw.Elapsed.TotalSeconds -lt $t){ if(Test-WebReady){return $true}; Start-Sleep -Milliseconds 1000 }; return $false }
function Test-KeplerReady { [bool](Get-NetTCPConnection -LocalPort $Cfg.Ports.Game -State Listen -EA SilentlyContinue) -and ((Get-PackKepler) -ne $null) }
function Wait-KeplerReady([int]$t){ $sw=[Diagnostics.Stopwatch]::StartNew(); while($sw.Elapsed.TotalSeconds -lt $t){ if(Test-KeplerReady){return $true}; Start-Sleep -Milliseconds 800 }; return $false }
function Test-MinervaReady {  # fonctionnel : rend une image PNG valide (un port ouvert ne suffit pas)
    $u = "http://localhost:$($Cfg.Ports.Minerva)/habbo-imaging/avatarimage?figure=hr-100-1-1.hd-180-1.ch-210-1.lg-270-1.sh-290-1&size=b"
    try{
        $r = Invoke-WebRequest -Uri $u -UseBasicParsing -TimeoutSec 5
        $b = $r.Content; if($b -is [string]){ $b=[Text.Encoding]::GetEncoding('ISO-8859-1').GetBytes($b) }
        return ($b.Length -gt 8 -and $b[0]-eq 0x89 -and $b[1]-eq 0x50 -and $b[2]-eq 0x4E -and $b[3]-eq 0x47)
    }catch{ return $false }
}
function Wait-MinervaReady([int]$t){ $sw=[Diagnostics.Stopwatch]::StartNew(); while($sw.Elapsed.TotalSeconds -lt $t){ if(Test-MinervaReady){return $true}; Start-Sleep -Milliseconds 1000 }; return $false }

# ============================================================================
#  Prérequis : INDISPENSABLES vs FACULTATIF (Minerva)
# ============================================================================
function Test-RequiredPrereqs {
    $miss = @()
    if(-not (Test-Path (Join-Path $Cfg.MariaDir 'bin\mysqld.exe'))){ $miss += "MariaDB : $($Cfg.MariaDir)\bin\mysqld.exe introuvable." }
    if(-not (Test-Path $Cfg.MariaIni)){ $miss += "MariaDB : $($Cfg.MariaIni) introuvable." }
    if(-not (Test-Path (Join-Path $Cfg.MariaDir 'bin\mysql.exe'))){ $miss += "MariaDB : $($Cfg.MariaDir)\bin\mysql.exe introuvable (vérif base)." }
    if(-not (Resolve-ApacheDir)){ $miss += "Apache : aucune (ou plusieurs) install sous '$($Cfg.ApacheGlob)'. Renseigne ApacheDir dans scripts\pack.ps1." }
    if(-not (Test-Path (Join-Path $Cfg.Www 'kepler.jar'))){ $miss += "Kepler : $($Cfg.Www)\kepler.jar introuvable." }
    if(-not (Test-Path (Join-Path $Cfg.Www 'run.bat'))){ $miss += "Kepler : $($Cfg.Www)\run.bat introuvable." }
    if(-not (Test-Path (Join-Path $Cfg.Www 'lib'))){ $miss += "Kepler : $($Cfg.Www)\lib introuvable." }
    return $miss
}
function Test-MinervaInstalled {
    return ((Test-Path (Join-Path $Cfg.MinervaDir 'win-x64\Minerva.dll')) -and (Test-Path (Join-Path $Cfg.MinervaDir 'dotnet\dotnet.exe')))
}

# ============================================================================
#  Installation reproductible des scripts Minerva (versionnés -> tools/minerva-bin)
#  Une fusion Git ne met PAS à jour les copies ignorées : on les synchronise ici.
# ============================================================================
function Sync-MinervaScripts {
    if(-not (Test-Path $Cfg.MinervaSrc)){ return }
    if(-not (Test-Path $Cfg.MinervaDir)){ return }  # pas installé : rien à synchroniser
    foreach($n in @('start-minerva.ps1','stop-minerva.ps1')){
        $src = Join-Path $Cfg.MinervaSrc $n; $dst = Join-Path $Cfg.MinervaDir $n
        if(-not (Test-Path $src)){ continue }
        $diff = $true
        if(Test-Path $dst){ try { $diff = ((Get-FileHash $src).Hash -ne (Get-FileHash $dst).Hash) } catch { $diff = $true } }
        if($diff){
            if(Test-Path $dst){ Copy-Item $dst "$dst.bak" -Force -EA SilentlyContinue }   # sauvegarde l'ancienne copie
            Copy-Item $src $dst -Force -EA SilentlyContinue
            Log "Script Minerva mis à jour : $n (ancienne copie -> $n.bak)"
        }
    }
}

# ============================================================================
#  DÉMARRAGE
# ============================================================================
function Start-Pack {
    Write-Host ''; Write-Host '============================================================'
    Write-Host '  HabboretroV14 — Démarrage' -ForegroundColor White
    Write-Host '============================================================'
    Log 'DEMARRAGE demandé'

    $mtx = Lock-Pack
    if(-not $mtx){ Say 'FAIL' "Un autre lanceur (START/STOP) est déjà en cours pour ce pack. Réessaie dans un instant."; return 1 }
    try {
        $state = @{ db='?'; web='?'; kepler='?'; minerva='?' }

        # 0) Prérequis indispensables
        $miss = Test-RequiredPrereqs
        if($miss.Count){
            Say 'FAIL' "Prérequis indispensables manquants :"
            $miss | ForEach-Object { Write-Host ('   - ' + $_) -ForegroundColor Red }
            Write-Host ''; Write-Host "Corrige les points ci-dessus puis relance." -ForegroundColor Yellow
            return 1
        }
        $apacheDir = Resolve-ApacheDir
        $minervaOk = Test-MinervaInstalled
        if(-not $minervaOk){ Say 'WARN' "Minerva (avatars) non installé : démarrage en MODE SECOURS. Voir Server\www\avatar\README.md." }

        # 1) MariaDB (pack)
        if(Get-PackMysqld){
            if(Test-DbReady){ Say 'HAVE' "MariaDB (pack) déjà en marche, base '$($Cfg.DbName)' prête."; $state.db='prête' }
            elseif(Wait-DbReady 8){ Say 'HAVE' "MariaDB (pack) déjà en marche, base prête."; $state.db='prête' }
            else { Say 'FAIL' "MariaDB (pack) tourne mais la base '$($Cfg.DbName)' n'est pas prête (tables manquantes)."; $state.db='échec'; Show-Summary $state; return 1 }
        } else {
            $o = Get-PortOwner $Cfg.Ports.Db
            if($o){ Say 'FAIL' "Port $($Cfg.Ports.Db) occupé par une AUTRE application : $($o.Name) (PID $($o.Pid)). Je ne la tue pas."; if($o.Path){ Write-Host "   -> $($o.Path)" -ForegroundColor DarkGray }; Write-Host "   -> C'est sans doute le MySQL de Laragon : arrête-le toi-même puis relance." -ForegroundColor Yellow; $state.db='conflit'; Show-Summary $state; return 1 }
            Say 'RUN' "MariaDB (pack)…"
            Start-Process (Join-Path $Cfg.MariaDir 'bin\mysqld.exe') -ArgumentList "--defaults-file=`"$($Cfg.MariaIni)`"" -WorkingDirectory (Join-Path $Cfg.MariaDir 'bin') -WindowStyle Minimized
            if(Wait-DbReady $Cfg.StartTimeoutDb){ Say 'OK' "Base '$($Cfg.DbName)' prête (tables vérifiées)."; $state.db='prête' }
            else { Say 'FAIL' "MariaDB ne répond pas / base incomplète après $($Cfg.StartTimeoutDb)s (voir la fenêtre MariaDB)."; $state.db='échec'; Show-Summary $state; return 1 }
        }

        # 2) Apache (PARTAGÉ) — accepté seulement si c'est l'install configurée (chemin)
        $o = Get-PortOwner $Cfg.Ports.Web
        if($o){
            if(Under $o.Path $apacheDir){ Say 'HAVE' "Serveur web déjà en marche (Apache configuré, port $($Cfg.Ports.Web))." }
            else { Say 'FAIL' "Port $($Cfg.Ports.Web) occupé par un processus NON reconnu : $($o.Name) (PID $($o.Pid)). Je ne le tue pas."; if($o.Path){ Write-Host "   -> $($o.Path)" -ForegroundColor DarkGray }; $state.web='conflit'; Show-Summary $state; return 1 }
        } else {
            Say 'RUN' "Serveur web (Apache $([IO.Path]::GetFileName($apacheDir)))…"
            Start-Process (Join-Path $apacheDir 'bin\httpd.exe') -WorkingDirectory (Join-Path $apacheDir 'bin') -WindowStyle Minimized
        }
        if(Wait-WebReady $Cfg.StartTimeoutWeb){ Say 'OK' "Site accessible ($($Cfg.WebUrl))."; $state.web='prêt' }
        else { Say 'FAIL' "Le site ne répond pas correctement sur $($Cfg.WebUrl) après $($Cfg.StartTimeoutWeb)s."; $state.web='échec'; Show-Summary $state; return 1 }

        # 3) Kepler (pack) — dépend de MariaDB prête
        if(Get-PackKepler){
            if(Wait-KeplerReady 6){ Say 'HAVE' "Émulateur Kepler déjà en marche (écoute $($Cfg.Ports.Game))."; $state.kepler='prêt' }
            else { Say 'FAIL' "Kepler (pack) tourne mais n'écoute pas sur $($Cfg.Ports.Game)."; $state.kepler='échec'; Show-Summary $state; return 1 }
        } else {
            $o = Get-PortOwner $Cfg.Ports.Game
            if($o){ Say 'FAIL' "Port $($Cfg.Ports.Game) occupé par une autre application : $($o.Name) (PID $($o.Pid)). Je ne la tue pas."; $state.kepler='conflit'; Show-Summary $state; return 1 }
            Say 'RUN' "Émulateur Kepler…"
            Start-Process 'cmd.exe' -ArgumentList '/c','run.bat' -WorkingDirectory $Cfg.Www -WindowStyle Minimized
            if(Wait-KeplerReady $Cfg.StartTimeoutGame){ Say 'OK' "Kepler démarré (jeu sur $($Cfg.Ports.Game))."; $state.kepler='prêt' }
            else { Say 'FAIL' "Kepler n'écoute pas sur $($Cfg.Ports.Game) après $($Cfg.StartTimeoutGame)s (voir fenêtre Émulateur / server.log)."; $state.kepler='échec'; Show-Summary $state; return 1 }
        }

        # 4) Minerva (FACULTATIF) — ne bloque jamais le reste
        if(-not $minervaOk){ $state.minerva='secours' }
        elseif(Test-MinervaReady){ Say 'HAVE' "Avatars (Minerva) déjà en marche (image valide)."; $state.minerva='prêt' }
        else {
            $o = Get-PortOwner $Cfg.Ports.Minerva
            if($o){ Say 'WARN' "Port $($Cfg.Ports.Minerva) occupé mais Minerva ne rend pas d'image valide → MODE SECOURS (je ne tue rien)."; $state.minerva='secours' }
            else {
                Sync-MinervaScripts
                Say 'RUN' "Avatars (Minerva)…"
                $sup = Join-Path $Cfg.MinervaDir 'start-minerva.ps1'
                if(Test-Path $sup){ Start-Process 'powershell' -ArgumentList '-NoProfile','-WindowStyle','Hidden','-ExecutionPolicy','Bypass','-File',$sup -WindowStyle Hidden }
                if(Wait-MinervaReady $Cfg.StartTimeoutMinerva){ Say 'OK' "Avatars prêts (image de test valide)."; $state.minerva='prêt' }
                else { Say 'WARN' "Avatars : MODE SECOURS (Minerva ne répond pas). Le site et le jeu fonctionnent."; $state.minerva='secours' }
            }
        }

        Show-Summary $state
        # Succès si les composants indispensables sont prêts (Minerva facultatif).
        if($state.db -eq 'prête' -and $state.web -eq 'prêt' -and $state.kepler -eq 'prêt'){ return 0 } else { return 1 }
    } finally { if($mtx){ $mtx.ReleaseMutex() | Out-Null; $mtx.Dispose() } }
}

# ============================================================================
#  ARRÊT (propre, ciblé pack)
# ============================================================================
function Stop-Pack {
    Write-Host ''; Write-Host '============================================================'
    Write-Host '  HabboretroV14 — Arrêt' -ForegroundColor White
    Write-Host '============================================================'
    Log 'ARRET demandé'

    $mtx = Lock-Pack
    if(-not $mtx){ Say 'FAIL' "Un autre lanceur (START/STOP) est déjà en cours pour ce pack. Réessaie dans un instant."; return 1 }
    try {
        $fail = @()

        # 1) Minerva : couper la supervision AVANT le process
        if((Get-PortOwner $Cfg.Ports.Minerva) -or (Test-Path (Join-Path $Cfg.MinervaDir 'minerva.supervisor.pid'))){
            Say 'STOP' "Avatars (Minerva) : arrêt de la supervision + du service…"
            $sm = Join-Path $Cfg.MinervaDir 'stop-minerva.ps1'
            if(Test-Path $sm){ & powershell -NoProfile -ExecutionPolicy Bypass -File $sm *> $null }
            Start-Sleep -Milliseconds 400
            if(Get-PortOwner $Cfg.Ports.Minerva){ Say 'WARN' "Minerva répond encore sur $($Cfg.Ports.Minerva) (non bloquant)." } else { Say 'OK' "Avatars arrêtés." }
        } else { Say 'INFO' "Avatars (Minerva) déjà arrêtés." }

        # 2) Kepler : arrêt propre (shutdown hook via taskkill SANS /f), puis attente.
        $kepler = @(Get-PackKepler)
        $keplerStopped = $true
        if($kepler.Count){
            foreach($k in $kepler){ Say 'STOP' "Émulateur Kepler : demande d'arrêt propre (PID $($k.ProcessId))…"; & taskkill /PID $k.ProcessId *> $null }
            $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
            while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutKepler){ if(-not (Get-PackKepler)){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
            if($gone){ Say 'OK' "Kepler arrêté (vérifie la sauvegarde dans server.log si besoin)." }
            else {
                Say 'FAIL' "Kepler ne s'est pas arrêté en $($Cfg.StopTimeoutKepler)s."
                $force = $false
                if([Environment]::UserInteractive){
                    try { $ans = Read-Host "   Forcer l'arrêt CIBLÉ de Kepler (risque de perte des données non sauvegardées) ? (o/N)"; $force = ($ans -match '^(o|oui|y|yes)$') } catch { $force = $false }
                }
                if($force){ foreach($k in (Get-PackKepler)){ & taskkill /F /PID $k.ProcessId *> $null; Say 'STOP' "Kepler forcé (PID $($k.ProcessId))." }; Start-Sleep -Milliseconds 500; if(Get-PackKepler){ $keplerStopped=$false; $fail += 'Kepler' } }
                else { Say 'WARN' "Kepler laissé actif (aucun arrêt forcé)."; $keplerStopped=$false; $fail += 'Kepler' }
            }
        } else { Say 'INFO' "Émulateur Kepler (pack) déjà arrêté." }

        # 3) MariaDB (pack) : UNIQUEMENT si Kepler est bien arrêté, et si le 3306 est à NOUS.
        if(-not $keplerStopped){
            Say 'WARN' "MariaDB NON arrêtée : Kepler est encore actif (on ne coupe pas la base sous lui)."
        } elseif(Get-PackMysqld){
            if(Test-DbPackOnPort){
                Say 'STOP' "Base MariaDB (pack) : arrêt propre…"
                $adm = Join-Path $Cfg.MariaDir 'bin\mysqladmin.exe'
                if(Test-Path $adm){ & $adm -u $Cfg.DbUser -h 127.0.0.1 -P $Cfg.Ports.Db shutdown *> $null }
                $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
                while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutDb){ if(-not (Get-PackMysqld)){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
                if($gone){ Say 'OK' "Base arrêtée proprement." } else { Say 'FAIL' "MariaDB (pack) ne s'est pas arrêtée en $($Cfg.StopTimeoutDb)s (non forcée)."; $fail += 'MariaDB' }
            } else {
                Say 'WARN' "Le port $($Cfg.Ports.Db) n'est pas tenu par NOTRE MariaDB : je ne lance pas mysqladmin shutdown."
            }
        } else { Say 'INFO' "Base MariaDB (pack) déjà arrêtée." }

        # 4) Apache : partagé Laragon -> préservé.
        if(Get-PortOwner $Cfg.Ports.Web){ Say 'INFO' "Apache laissé en marche (service partagé Laragon)." }

        Write-Host ''
        if($fail.Count -eq 0){
            Write-Host "Rétro arrêté. (Le MySQL de Laragon reste disponible pour tes autres projets.)" -ForegroundColor Green
            Log 'ARRET terminé (complet)'; return 0
        } else {
            Write-Host ("ARRÊT INCOMPLET — composant(s) encore actif(s) : {0}." -f ($fail -join ', ')) -ForegroundColor Red
            Write-Host ("Journal : {0}\pack.log" -f $Cfg.LogDir) -ForegroundColor DarkGray
            Log ("ARRET incomplet : " + ($fail -join ', ')); return 1
        }
    } finally { if($mtx){ $mtx.ReleaseMutex() | Out-Null; $mtx.Dispose() } }
}

# ============================================================================
#  Bilan (depuis les contrôles de disponibilité) + adresses
# ============================================================================
function Show-Summary($state){
    Write-Host ''; Write-Host '------------------------------------------------------------'
    Write-Host '  Bilan (résultats des contrôles)' -ForegroundColor White
    Write-Host ("   MariaDB (base)   : {0}" -f $state.db)
    Write-Host ("   Apache (site)    : {0}" -f $state.web)
    Write-Host ("   Kepler (jeu)     : {0}" -f $state.kepler)
    Write-Host ("   Minerva (avatars): {0}" -f $state.minerva)
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
switch($Action){ 'start'{ exit (Start-Pack) } 'stop'{ exit (Stop-Pack) } }
