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
param(
    [ValidateSet('start','stop','restart-kepler','stop-kepler')] [string]$Action = 'start',
    [string]$StatusFile = '',   # fichier JSON de suivi (redémarrage Kepler piloté depuis l'admin)
    [int]$DelaySeconds = 0,      # redémarrage différé (fenêtre d'annulation)
    [switch]$DryRun              # simulation : prechecks + statut, SANS arrêter/démarrer Kepler
)

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
    StartTimeoutDb=45; StartTimeoutWeb=15; StartTimeoutGame=120; StartTimeoutMinerva=25
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
function Norm([string]$p){ if(-not $p){ return '' }; try { return ([IO.Path]::GetFullPath($p)).TrimEnd('\').ToLower() } catch { return $p.TrimEnd('\').ToLower() } }
function Under([string]$path,[string]$dir){
    # Frontière de dossier : évite que "…\HabboretroV14-dev" soit vu sous "…\HabboretroV14".
    if(-not $path -or -not $dir){ return $false }
    $p = Norm $path; $d = Norm $dir
    return ($p -eq $d -or $p.StartsWith($d + '\'))
}
function Resolve-ApacheDir {
    if($Cfg.ApacheDir -and (Test-Path (Join-Path $Cfg.ApacheDir 'bin\httpd.exe'))){ return $Cfg.ApacheDir }
    $dirs = @(Get-ChildItem $Cfg.ApacheGlob -Directory -EA SilentlyContinue | Where-Object { Test-Path (Join-Path $_.FullName 'bin\httpd.exe') })
    if($dirs.Count -eq 1){ return $dirs[0].FullName }
    return $null
}
function Get-PackMysqld { Get-CimInstance Win32_Process -Filter "Name='mysqld.exe'" -EA SilentlyContinue | Where-Object { Under $_.ExecutablePath $Cfg.MariaDir } }
# Kepler du pack = java dont le marqueur -Dhabbo.pack pointe EXACTEMENT sur notre Server\www.
function Get-PackKepler {
    $want = Norm $Cfg.Www
    Get-CimInstance Win32_Process -Filter "Name='java.exe'" -EA SilentlyContinue | Where-Object {
        if(-not $_.CommandLine){ return $false }
        $m = [regex]::Match($_.CommandLine, '-Dhabbo\.pack=("?)([^"]+?)\1(\s|$)')
        if(-not $m.Success){ return $false }
        (Norm ($m.Groups[2].Value)) -eq $want
    }
}
function Get-PackKeplerPids { @(Get-PackKepler | ForEach-Object { [int]$_.ProcessId }) }
# Arrêt PROPRE de Kepler : envoie un vrai Ctrl+C (CTRL_C_EVENT) à SA console, ce qui
# déclenche le shutdown hook dispose() de la JVM (sauvegarde items/chat/joueurs, puis
# fermeture des sockets). Un java.exe en console N'A PAS de fenêtre : un taskkill SANS
# /f ne lui parvient pas. On opère dans un process PowerShell ISOLÉ (sa propre console)
# pour ne pas perturber la console de ce lanceur.
# Demande d'arrêt PROPRE via RCON (127.0.0.1:12309) — mécanisme PRINCIPAL.
# Indépendant de la console Windows : fiable quel que soit le lanceur (contrairement au
# Ctrl+C via AttachConsole, qui échoue quand le script est lancé détaché par Apache).
# Kepler reçoit le header 'shutdown' -> System.exit(0) -> shutdown hook -> sauvegardes.
# Protocole identique au décodeur RCON : [int32 BE longueur body][body], body =
# [int32 BE longueur header]['shutdown'][int32 BE nb params = 0].
function Send-RconShutdown {
    $client = $null
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $iar = $client.BeginConnect('127.0.0.1', [int]$Cfg.Ports.Rcon, $null, $null)
        if(-not $iar.AsyncWaitHandle.WaitOne(2000)){ $client.Close(); return $false }
        $client.EndConnect($iar)
        $ns = $client.GetStream()
        $be = { param([int]$v) $b=[BitConverter]::GetBytes([int]$v); if([BitConverter]::IsLittleEndian){ [Array]::Reverse($b) }; return $b }
        $hdr = [Text.Encoding]::ASCII.GetBytes('shutdown')
        $body = New-Object System.IO.MemoryStream
        $t = & $be $hdr.Length;       $body.Write($t,0,4)   # longueur du header
        $body.Write($hdr,0,$hdr.Length)                      # 'shutdown'
        $t = & $be 0;                 $body.Write($t,0,4)    # 0 paramètre
        $bb = $body.ToArray()
        $t = & $be $bb.Length;        $ns.Write($t,0,4)      # longueur du body
        $ns.Write($bb,0,$bb.Length)                          # body
        $ns.Flush()
        Start-Sleep -Milliseconds 300
        $client.Close()
        return $true
    } catch { if($client){ $client.Close() }; return $false }
}

function Request-KeplerShutdown([int]$kpid){
    # Journal de diagnostic : prouve si AttachConsole ET GenerateConsoleCtrlEvent réussissent
    # (avec code d'erreur Windows), pour savoir où casse l'arrêt propre via le bouton admin.
    $log = Join-Path (Split-Path $PSScriptRoot -Parent) '_runtime\shutdown_debug.log'
    $dir = Split-Path $log -Parent
    if(-not (Test-Path $dir)){ New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    $code = @"
`$ErrorActionPreference='SilentlyContinue'
Add-Type -Namespace Kp -Name Con -MemberDefinition @'
[DllImport("kernel32.dll", SetLastError=true)] public static extern bool AttachConsole(uint p);
[DllImport("kernel32.dll", SetLastError=true)] public static extern bool FreeConsole();
[DllImport("kernel32.dll", SetLastError=true)] public static extern bool SetConsoleCtrlHandler(System.IntPtr h, bool a);
[DllImport("kernel32.dll", SetLastError=true)] public static extern bool GenerateConsoleCtrlEvent(uint e, uint g);
'@
[Kp.Con]::FreeConsole() | Out-Null
`$at=[Kp.Con]::AttachConsole($kpid)
`$eAttach=[Runtime.InteropServices.Marshal]::GetLastWin32Error()
`$sent=`$false; `$eCtrlC='n/a'
if(`$at){
  [Kp.Con]::SetConsoleCtrlHandler([System.IntPtr]::Zero,`$true) | Out-Null  # ce helper ignore le Ctrl+C
  `$sent=[Kp.Con]::GenerateConsoleCtrlEvent(0,0)                             # 0 = CTRL_C_EVENT
  `$eCtrlC=[Runtime.InteropServices.Marshal]::GetLastWin32Error()
  Start-Sleep -Milliseconds 500
}
Add-Content -Path '$log' -Value ("{0} pid=$kpid Attach={1} errAttach={2} CtrlC={3} errCtrlC={4}" -f (Get-Date).ToString('o'),`$at,`$eAttach,`$sent,`$eCtrlC) -Encoding UTF8
"@
    $enc = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($code))
    Start-Process powershell -ArgumentList '-NoProfile','-ExecutionPolicy','Bypass','-EncodedCommand',$enc -WindowStyle Hidden -Wait
}

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
function Test-KeplerReady {
    # Le PID qui ÉCOUTE le port du jeu doit être celui de NOTRE Kepler identifié.
    $c = Get-NetTCPConnection -LocalPort $Cfg.Ports.Game -State Listen -EA SilentlyContinue | Select-Object -First 1
    if(-not $c){ return $false }
    return ((Get-PackKeplerPids) -contains [int]$c.OwningProcess)
}
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
    # Les scripts versionnés (MinervaSrc) sont copiés vers tools\minerva-bin (ignoré).
    # Exécuté à CHAQUE démarrage, que Minerva tourne ou non : on ne redémarre PAS le
    # moteur actif ; la nouvelle version s'applique au prochain redémarrage de Minerva.
    if(-not (Test-Path $Cfg.MinervaSrc) -or -not (Test-Path $Cfg.MinervaDir)){ return }
    foreach($n in @('start-minerva.ps1','stop-minerva.ps1')){
        $src = Join-Path $Cfg.MinervaSrc $n; $dst = Join-Path $Cfg.MinervaDir $n
        if(-not (Test-Path $src)){ continue }
        $diff = $true
        if(Test-Path $dst){ try { $diff = ((Get-FileHash $src).Hash -ne (Get-FileHash $dst).Hash) } catch { $diff = $true } }
        if(-not $diff){ continue }
        try {
            if(Test-Path $dst){ Copy-Item $dst "$dst.bak" -Force -ErrorAction Stop }   # sauvegarde l'ancienne copie
            Copy-Item $src $dst -Force -ErrorAction Stop
            # On vérifie RÉELLEMENT que la copie est conforme avant d'annoncer le succès.
            if((Get-FileHash $src).Hash -eq (Get-FileHash $dst).Hash){
                Say 'INFO' "Script Minerva mis à jour : $n (appliqué au prochain redémarrage de Minerva ; ancienne copie -> $n.bak)."
            } else {
                Say 'WARN' "Mise à jour du script Minerva $n NON conforme (copie différente de la source)."; Log "ECHEC sync $n (hash different apres copie)"
            }
        } catch {
            Say 'WARN' "Impossible de mettre à jour le script Minerva $n : $($_.Exception.Message)"; Log "ECHEC sync $n : $($_.Exception.Message)"
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
        else { Sync-MinervaScripts }   # met à jour les scripts, même si Minerva tourne déjà (pas de redémarrage)

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
            else {
                # Distinguer les deux échecs : rien n'écoute (Java a quitté au lancement)
                # vs un AUTRE processus écoute sans être notre Kepler identifié.
                $o = Get-PortOwner $Cfg.Ports.Game
                if(-not $o){ Say 'FAIL' "Kepler n'a pas démarré : rien n'écoute sur $($Cfg.Ports.Game) après $($Cfg.StartTimeoutGame)s. Java a probablement quitté au lancement — regarde la fenêtre « Émulateur » (dernières lignes) et server.log." }
                elseif(-not ((Get-PackKeplerPids) -contains [int]$o.Pid)){ Say 'FAIL' "Le port $($Cfg.Ports.Game) est tenu par un processus NON reconnu comme le Kepler du pack : $($o.Name) (PID $($o.Pid)) — $($o.Path). Vérifie-le avant d'agir." }
                else { Say 'FAIL' "Kepler (pack) tourne mais n'écoute pas sur $($Cfg.Ports.Game) après $($Cfg.StartTimeoutGame)s." }
                $state.kepler='échec'; Show-Summary $state; return 1
            }
        }

        # 4) Minerva (FACULTATIF) — ne bloque jamais le reste
        if(-not $minervaOk){ $state.minerva='secours' }
        elseif(Test-MinervaReady){ Say 'HAVE' "Avatars (Minerva) déjà en marche (image valide)."; $state.minerva='prêt' }
        else {
            $o = Get-PortOwner $Cfg.Ports.Minerva
            if($o){ Say 'WARN' "Port $($Cfg.Ports.Minerva) occupé mais Minerva ne rend pas d'image valide → MODE SECOURS (je ne tue rien)."; $state.minerva='secours' }
            else {
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
        $fail = @()   # composants du PACK restés actifs (processus étrangers préservés exclus)

        # 1) Minerva : couper la supervision AVANT le process
        if((Get-PortOwner $Cfg.Ports.Minerva) -or (Test-Path (Join-Path $Cfg.MinervaDir 'minerva.supervisor.pid'))){
            Say 'STOP' "Avatars (Minerva) : arrêt de la supervision + du service…"
            $sm = Join-Path $Cfg.MinervaDir 'stop-minerva.ps1'
            if(Test-Path $sm){ & powershell -NoProfile -ExecutionPolicy Bypass -File $sm *> $null }
            Start-Sleep -Milliseconds 400
            $mo = Get-PortOwner $Cfg.Ports.Minerva
            if(-not $mo){ Say 'OK' "Avatars arrêtés." }
            elseif(Under $mo.Path $Cfg.MinervaDir){ Say 'FAIL' "Minerva (pack) répond encore sur $($Cfg.Ports.Minerva)."; $fail += 'Minerva' }
            else { Say 'INFO' "Port $($Cfg.Ports.Minerva) occupé par un processus étranger : préservé." }
        } else { Say 'INFO' "Avatars (Minerva) déjà arrêtés." }

        # 2) Kepler (pack, marqueur) : arrêt PROPRE via RCON 'shutdown' (principal) ->
        #    shutdown hook dispose() (sauvegarde items/chat/joueurs). Ctrl+C en secours
        #    (lancement interactif). /f reste un DERNIER recours explicite.
        $kepler = @(Get-PackKepler)
        $keplerStopped = $true
        if($kepler.Count){
            # mémoriser les fenêtres cmd parentes (run.bat) pour les nettoyer après coup
            $parents = @($kepler | ForEach-Object { [int]$_.ParentProcessId } | Where-Object { $_ -gt 0 } | Select-Object -Unique)
            $rcon = Send-RconShutdown
            if($rcon){ Say 'STOP' ("Émulateur Kepler : arrêt propre demandé via RCON (-> sauvegarde), PID {0}…" -f (($kepler | ForEach-Object { $_.ProcessId }) -join ',')) }
            else { Say 'STOP' "Émulateur Kepler : RCON injoignable — tentative Ctrl+C (secours)…" }
            foreach($k in $kepler){ Request-KeplerShutdown ([int]$k.ProcessId) }  # secours console (interactif)
            $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
            while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutKepler){ if(-not (Get-PackKepler)){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
            if($gone){
                # fenêtres run.bat restées sur « Terminate batch job (Y/N)? » : Kepler a
                # déjà sauvegardé et quitté, ces cmd ne sont plus que cosmétiques.
                foreach($pp in $parents){ $pc = Get-Process -Id $pp -EA SilentlyContinue; if($pc -and $pc.ProcessName -eq 'cmd'){ & taskkill /F /PID $pp *> $null } }
                if($rcon){ Say 'OK' "Kepler : arrêté proprement via RCON (sauvegarde déclenchée). server.log : « Shutting down server! » -> chat/items/players saved -> « Shutdown complete »." }
                else { Say 'OK' "Kepler : arrêt propre demandé (Ctrl+C secours). ⚠ Sauvegarde À CONSTATER dans server.log (« Shutting down server! » + sauvegardes items/chat)." }
            }
            else {
                Say 'FAIL' "Kepler ne s'est pas arrêté en $($Cfg.StopTimeoutKepler)s."
                $force = $false
                if([Environment]::UserInteractive){
                    try { $ans = Read-Host "   Forcer l'arrêt CIBLÉ de Kepler (risque de perte des données non sauvegardées) ? (o/N)"; $force = ($ans -match '^(o|oui|y|yes)$') } catch { $force = $false }
                }
                if($force){ foreach($k in (Get-PackKepler)){ & taskkill /F /PID $k.ProcessId *> $null; Say 'STOP' "Kepler forcé (PID $($k.ProcessId))." }; Start-Sleep -Milliseconds 500 }
                else { Say 'WARN' "Kepler laissé actif (aucun arrêt forcé)." }
                if(Get-PackKepler){ $keplerStopped=$false; $fail += 'Kepler' }
            }
        } else { Say 'INFO' "Aucun Kepler du pack (marqueur) en cours." }

        # 2b) INCERTITUDE : un processus NON identifié écoute le port du jeu (ex. ancien
        #     Kepler sans marqueur, susceptible d'utiliser NOTRE base). On ne le tue pas,
        #     on signale, et on NE COUPE PAS MariaDB.
        $gameUncertain = $false
        $go = Get-PortOwner $Cfg.Ports.Game
        if($go -and (-not ((Get-PackKeplerPids) -contains [int]$go.Pid))){
            $gameUncertain = $true
            Say 'WARN' "Port du jeu ($($Cfg.Ports.Game)) occupé par un processus NON identifié : $($go.Name) (PID $($go.Pid))."
            Write-Host "   -> Je ne le tue pas et je GARDE MariaDB (il peut encore utiliser la base)." -ForegroundColor Yellow
            Write-Host "   -> IDENTIFIE d'abord ce processus avant toute action :" -ForegroundColor Yellow
            Write-Host ("        powershell -NoProfile -Command ""Get-CimInstance Win32_Process -Filter 'ProcessId={0}' | Select-Object ProcessId,ExecutablePath,CommandLine | Format-List""" -f $go.Pid) -ForegroundColor Gray
            Write-Host "      S'il s'agit bien d'un ancien Kepler du pack, arrête-le par ses propres moyens." -ForegroundColor Gray
            Write-Host "      (Une sauvegarde propre de Kepler n'est PAS garantie par un simple arrêt du processus :" -ForegroundColor Gray
            Write-Host "       vérifie-la dans server.log.) Puis relance STOP une fois le port du jeu libéré." -ForegroundColor Gray
            $fail += 'Kepler (non identifié)'
        }

        # 3) MariaDB (pack) : seulement si Kepler est bien arrêté ET sans incertitude,
        #    et uniquement si le port 3306 est tenu par NOTRE MariaDB.
        if((-not $keplerStopped) -or $gameUncertain){
            Say 'WARN' "MariaDB NON arrêtée : Kepler encore actif ou incertain (on ne coupe pas la base sous lui)."
        } elseif(Get-PackMysqld){
            if(Test-DbPackOnPort){
                Say 'STOP' "Base MariaDB (pack) : arrêt propre…"
                $adm = Join-Path $Cfg.MariaDir 'bin\mysqladmin.exe'
                if(Test-Path $adm){ & $adm -u $Cfg.DbUser -h 127.0.0.1 -P $Cfg.Ports.Db shutdown *> $null }
                $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
                while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutDb){ if(-not (Get-PackMysqld)){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
                if($gone){ Say 'OK' "Base arrêtée proprement." } else { Say 'FAIL' "MariaDB (pack) ne s'est pas arrêtée en $($Cfg.StopTimeoutDb)s (non forcée)."; $fail += 'MariaDB' }
            } else {
                Say 'FAIL' "Notre MariaDB (pack) tourne mais pas sur le port $($Cfg.Ports.Db) : arrêt non tenté — composant du pack encore actif."
                $fail += 'MariaDB'
            }
        } else { Say 'INFO' "Base MariaDB (pack) déjà arrêtée." }

        # 4) Apache : partagé Laragon -> préservé.
        if(Get-PortOwner $Cfg.Ports.Web){ Say 'INFO' "Apache laissé en marche (service partagé Laragon)." }

        Write-Host ''
        if($fail.Count -eq 0){
            Write-Host "Rétro arrêté. (Le MySQL de Laragon reste disponible pour tes autres projets.)" -ForegroundColor Green
            Log 'ARRET terminé (complet)'; return 0
        } else {
            Write-Host ("ARRÊT INCOMPLET — à régler : {0}." -f ($fail -join ', ')) -ForegroundColor Red
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
#  REDÉMARRAGE DE KEPLER UNIQUEMENT (piloté par l'admin web, suivi par fichier statut)
#  MariaDB / Apache / Minerva ne sont PAS touchés. Aucun arrêt forcé automatique.
# ============================================================================
function Set-Status([string]$step,[string]$state,[string]$label,[string]$detail=''){
    if(-not $StatusFile){ return }
    $o = [ordered]@{ step=$step; state=$state; label=$label; detail=$detail; at=(Get-Date -Format 'o'); dry=[bool]$DryRun }
    try {
        $tmp = "$StatusFile.tmp"
        ($o | ConvertTo-Json -Compress) | Out-File -FilePath $tmp -Encoding utf8 -Force
        Move-Item $tmp $StatusFile -Force
    } catch {}
    Log ("STATUS {0}/{1} {2} {3}" -f $step,$state,$label,$detail)
}
# Constate dans server.log que l'arrêt a bien déclenché le shutdown (sauvegarde).
function Test-ShutdownSaved {
    $f = Join-Path $Cfg.Www 'server.log'
    if(-not (Test-Path $f)){ return $false }
    try { $tail = Get-Content $f -Tail 80 -EA SilentlyContinue } catch { return $false }
    return (@($tail | Where-Object { $_ -match 'Shutting down server' }).Count -gt 0)
}
function Restart-Kepler {
    Log 'RESTART-KEPLER demandé'
    $mtx = Lock-Pack
    if(-not $mtx){ Set-Status 'lock' 'error' 'Opération impossible' "Un lanceur (START/STOP) est déjà en cours pour ce pack."; return 1 }
    try {
        # Redémarrage différé : fenêtre d'annulation (drapeau <StatusFile>.cancel).
        if($DelaySeconds -gt 0){
            $cancel = "$StatusFile.cancel"
            if(Test-Path $cancel){ Remove-Item $cancel -Force -EA SilentlyContinue }
            $sw=[Diagnostics.Stopwatch]::StartNew()
            while($sw.Elapsed.TotalSeconds -lt $DelaySeconds){
                if(Test-Path $cancel){ Remove-Item $cancel -Force -EA SilentlyContinue; Set-Status 'cancelled' 'cancelled' 'Redémarrage annulé' "Annulé avant l'arrêt — aucun impact."; return 2 }
                $left=[int][math]::Ceiling($DelaySeconds - $sw.Elapsed.TotalSeconds)
                Set-Status 'scheduled' 'running' 'Redémarrage imminent' ("Arrêt dans {0}s (annulable)." -f $left)
                Start-Sleep -Seconds 1
            }
        }
        # 1) Précheck : MariaDB prête (Kepler en dépend — on ne redémarre pas dans une base cassée).
        Set-Status 'precheck' 'running' 'Vérifications' 'MariaDB disponible + identité du serveur de jeu.'
        if(-not (Test-DbReady)){ Set-Status 'precheck' 'error' 'Vérifications échouées' "MariaDB ne répond pas / base incomplète. Kepler NON redémarré ; MariaDB intacte."; return 1 }
        $kepler = @(Get-PackKepler)
        # 2) Arrêt PROPRE : RCON 'shutdown' (principal) -> shutdown hook / sauvegarde.
        #    Ctrl+C conservé en secours (utile en lancement interactif). Jamais de force-kill auto.
        if($kepler.Count){
            Set-Status 'stop' 'running' 'Sauvegarde et arrêt' ("Arrêt propre de Kepler (RCON), PID {0}." -f (($kepler | ForEach-Object { $_.ProcessId }) -join ','))
            $parents = @($kepler | ForEach-Object { [int]$_.ParentProcessId } | Where-Object { $_ -gt 0 } | Select-Object -Unique)
            if(-not $DryRun){
                $rcon = Send-RconShutdown
                if(-not $rcon){ Set-Status 'stop' 'running' 'Sauvegarde et arrêt' 'RCON injoignable — tentative Ctrl+C (secours).' }
                foreach($k in $kepler){ Request-KeplerShutdown ([int]$k.ProcessId) }  # secours console (interactif)
                $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
                while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutKepler){ if(-not (Get-PackKepler)){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
                if(-not $gone){ Set-Status 'stop' 'error' 'Arrêt échoué' ("Kepler ne s'est pas arrêté en {0}s. AUCUN arrêt forcé — MariaDB préservée. Vérifie la fenêtre Émulateur." -f $Cfg.StopTimeoutKepler); return 1 }
                foreach($pp in $parents){ $pc = Get-Process -Id $pp -EA SilentlyContinue; if($pc -and $pc.ProcessName -eq 'cmd'){ & taskkill /F /PID $pp *> $null } }
            }
            $saveNote = 'server.log : marqueurs de sauvegarde non confirmés — à vérifier.'
            if(Test-ShutdownSaved){ $saveNote = "server.log : arrêt (« Shutting down server ») repéré. Complétude des sauvegardes à confirmer côté log." }
            Set-Status 'backup' 'running' 'Arrêt constaté' $saveNote
        } else {
            Set-Status 'stop' 'running' 'Aucun Kepler actif' 'Rien à arrêter — démarrage direct.'
        }
        # 3) Démarrage + disponibilité RÉELLE (port du jeu tenu par NOTRE Kepler).
        Set-Status 'start' 'running' 'Démarrage' 'Lancement de Kepler (run.bat)…'
        if(-not $DryRun){
            $o = Get-PortOwner $Cfg.Ports.Game
            if($o -and (-not ((Get-PackKeplerPids) -contains [int]$o.Pid))){ Set-Status 'start' 'error' 'Démarrage impossible' ("Port {0} tenu par un process non reconnu : {1} (PID {2}). Aucune 2e instance lancée." -f $Cfg.Ports.Game,$o.Name,$o.Pid); return 1 }
            Start-Process 'cmd.exe' -ArgumentList '/c','run.bat' -WorkingDirectory $Cfg.Www -WindowStyle Minimized
            if(-not (Wait-KeplerReady $Cfg.StartTimeoutGame)){ Set-Status 'start' 'error' 'Démarrage échoué' ("Kepler n'écoute pas sur {0} après {1}s (voir fenêtre Émulateur / server.log)." -f $Cfg.Ports.Game,$Cfg.StartTimeoutGame); return 1 }
        } else {
            Start-Sleep -Milliseconds 300
        }
        $readyNote = 'Kepler redémarré et à l''écoute du port du jeu.'
        if($DryRun){ $readyNote = 'Simulation (DryRun) terminée — rien n''a été arrêté ni démarré.' }
        Set-Status 'ready' 'ok' 'Prêt' $readyNote
        return 0
    } finally { if($mtx){ $mtx.ReleaseMutex() | Out-Null; $mtx.Dispose() } }
}

# ============================================================================
# Arrêt de Kepler SEUL (pour le bouton admin « Arrêter ») — propre, sans force-kill,
# sans toucher MariaDB/Apache/Minerva. Réutilisé par _emu_stop.ps1.
function Stop-KeplerOnly {
    Log 'STOP-KEPLER (admin) demandé'
    $mtx = Lock-Pack
    if(-not $mtx){ Write-Host "Un lanceur (START/STOP) est déjà en cours pour ce pack."; return 1 }
    try {
        $kepler = @(Get-PackKepler)
        if(-not $kepler.Count){ Write-Host "Aucun Kepler du pack (marqueur) en cours."; return 0 }
        $parents = @($kepler | ForEach-Object { [int]$_.ParentProcessId } | Where-Object { $_ -gt 0 } | Select-Object -Unique)
        $rcon = Send-RconShutdown
        if($rcon){ Say 'STOP' "Émulateur Kepler : arrêt propre demandé via RCON (-> sauvegarde)…" }
        else { Say 'STOP' "RCON injoignable — tentative Ctrl+C (secours)." }
        foreach($k in $kepler){ Request-KeplerShutdown ([int]$k.ProcessId) }  # secours console (interactif)
        $sw=[Diagnostics.Stopwatch]::StartNew(); $gone=$false
        while($sw.Elapsed.TotalSeconds -lt $Cfg.StopTimeoutKepler){ if(-not (Get-PackKepler)){ $gone=$true; break }; Start-Sleep -Milliseconds 700 }
        if($gone){
            foreach($pp in $parents){ $pc = Get-Process -Id $pp -EA SilentlyContinue; if($pc -and $pc.ProcessName -eq 'cmd'){ & taskkill /F /PID $pp *> $null } }
            Say 'OK' "Kepler arrêté proprement (Ctrl+C). MariaDB/Apache/Minerva intacts."; return 0
        } else {
            Say 'FAIL' "Kepler ne s'est pas arrêté en $($Cfg.StopTimeoutKepler)s. AUCUN arrêt forcé — on le laisse actif."; return 1
        }
    } finally { if($mtx){ $mtx.ReleaseMutex() | Out-Null; $mtx.Dispose() } }
}

# ============================================================================
switch($Action){ 'start'{ exit (Start-Pack) } 'stop'{ exit (Stop-Pack) } 'restart-kepler'{ exit (Restart-Kepler) } 'stop-kepler'{ exit (Stop-KeplerOnly) } }
