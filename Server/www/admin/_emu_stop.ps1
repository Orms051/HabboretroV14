# Arrêt de l'émulateur depuis l'admin — PROPRE (Ctrl+C -> sauvegarde), SANS force-kill.
# S01 : on ne fait plus « Stop-Process -Force » (risque de perte de données + ciblage par nom).
# On délègue au lanceur officiel pack.ps1 (-Action stop-kepler) qui identifie NOTRE Kepler
# (marqueur -Dhabbo.pack) et envoie un vrai Ctrl+C, sans toucher MariaDB/Apache/Minerva.
$root = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $PSScriptRoot))  # admin -> www -> Server -> racine
$pack = Join-Path $root 'scripts\pack.ps1'
if (Test-Path $pack) {
    & "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File $pack -Action stop-kepler
    exit $LASTEXITCODE
} else {
    Write-Host "pack.ps1 introuvable : $pack"
    exit 1
}
