# Avatars V14 sur le site — Minerva (service de rendu local)

Rend l'avatar Shockwave/V14 des joueurs en PNG transparent, à partir de la
figure stockée en base. **Le service n'est accessible qu'en local** ; les
visiteurs ne reçoivent qu'une image via `/avatar.php`. Aucune donnée de compte
n'est envoyée à un tiers. **Aucun secret** n'est nécessaire ni stocké ici.

## Pièces

| Élément | Rôle |
|---|---|
| `tools/minerva-bin/win-x64/` | Binaire **Minerva** (par Quackster — auteur de Kepler/Avatara). Télécharge depuis https://github.com/Quackster/Minerva (release `latest`, `Minerva-win-x64.zip`). SHA-256 de la version utilisée : `894f08da66d6d6ba6b7d7ad2e4a961d2972a21d27f5455f7ac972dc2e098bdf3`. |
| `tools/minerva-bin/win-x64/figuredata/` | Ressources **Shockwave 2009** (fournies dans la release) : `figuredata.xml`, `compiled/*.swf` (`hh_human_50_hair/body/hats…`), `converter/old+newfiguredata.json`. C'est l'ère V14. |
| `tools/minerva-bin/dotnet/` | Runtime **ASP.NET Core 8** installé en local (voir ci-dessous). |
| `Server/www/avatar.php` | Endpoint public : `?user=<pseudo>` ou `?figure=<25 chiffres>&sex=M|F`, `size=s|b`, `direction=2|4`, `head=0|1`. Cache + secours. |
| `Server/www/avatar/figure_lib.php` | Convertit la figure 25 chiffres → format Avatara **avec NOS couleurs** (lues dans `dcr/14.1_b8/figuredata.txt`). |
| `Server/www/cache/avatars/` | PNG mis en cache (gitignoré). |

## Dépendances

- **Minerva win-x64 n'est PAS autonome** : build *framework-dependent*. Il lui
  faut le runtime **.NET 8** + **ASP.NET Core 8**.
- Le runtime ASP.NET Core 8 est fourni ici en local (`./dotnet/`), installé
  **sans droits administrateur**.

### (Ré)installer le runtime local (sans admin)

```powershell
# depuis tools/minerva-bin
iwr https://dot.net/v1/dotnet-install.ps1 -OutFile dotnet-install.ps1
./dotnet-install.ps1 -Channel 8.0 -Runtime aspnetcore -InstallDir .\dotnet -NoPath
```

### (Re)télécharger Minerva

```powershell
iwr https://github.com/Quackster/Minerva/releases/download/latest/Minerva-win-x64.zip -OutFile Minerva.zip
Expand-Archive Minerva.zip -DestinationPath .   # crée .\win-x64\
```

## Démarrage

### Automatique (recommandé) — avec le serveur

Minerva démarre **automatiquement** avec le serveur : `START-HABBORETROV14.bat`
le lance en étape 5 (après MariaDB, Apache, émulateur) et
`STOP-HABBORETROV14.bat` l'arrête. **Rien à faire de plus** — pas de second
mécanisme à mettre en place (évite les instances multiples).

Le lanceur utilisé est un **superviseur** (`tools/minerva-bin/start-minerva.ps1`) :
- écoute **uniquement en local** sur `http://localhost:5123` ;
- **mono-instance** : ne démarre pas si le port répond déjà ;
- **relance automatique** en cas de plantage, avec délai croissant (5 s → 30 s,
  remis à 5 s après plus d'une minute de bon fonctionnement) ;
- **journal limité** : `minerva.log`, rotation à 5 Mo (1 sauvegarde `.old`).

### Manuel (sans le lanceur)

```bat
tools\minerva-bin\start-minerva.bat     REM démarre (superviseur, fenêtre masquée)
tools\minerva-bin\stop-minerva.bat      REM arrête proprement (pas de relance)
```

### Vérifier

```bat
powershell -Command "Get-NetTCPConnection -LocalPort 5123 -State Listen"   REM up ?
curl http://localhost:5123/habbo-imaging/avatarimage?figure=hr-100-1-1.hd-180-1.ch-210-1.lg-270-1.sh-290-1
curl "http://localhost/avatar.php?user=<pseudo>" -o test.png                 REM via le site
```

Le premier lancement charge les assets Shockwave (`9762 flash assets`, `301
figure sets`). En-tête `X-Avatar: fallback` = Minerva indisponible (silhouette).

### Démarrage automatique **sans ouvrir le lanceur** (option)

Si tu ne lances pas `START-HABBORETROV14.bat` à chaque session :
- **Windows (dev)** : raccourci vers `start-minerva.bat` dans `shell:startup`.
- **Serveur Windows** : tâche planifiée « Au démarrage de l'ordinateur »
  (« Exécuter même si l'utilisateur n'est pas connecté »), action =
  `start-minerva.bat`. Une seule de ces options — pas en plus du lanceur.
- **Serveur Linux** : `Minerva-linux-x64.zip` + runtime aspnetcore 8 + service
  `systemd` lançant `dotnet Minerva.dll --shockwave-badge-render`
  (`ASPNETCORE_URLS=http://localhost:5123`, `Restart=always`, `RestartSec=5`).

Garder `avatar.php` → `MINERVA_BASE` en cohérence avec le port.

## Fonctionnement & cache

- Clé de cache = `sha1(figure | sexe | size | direction | head | version_convertisseur)`.
  Une **nouvelle tenue en base = nouvelle clé = nouvelle image**, sans purge.
- Pour forcer un rafraîchissement global (ex. après correction du convertisseur) :
  bump `AVATAR_CONV_VERSION` (figure_lib.php) **et** `AVATAR_URL_VER`
  (v2/inc/boot.php). On peut aussi vider `Server/www/cache/avatars/`.
- Si Minerva est **arrêté** : les avatars déjà en cache restent servis ; les
  autres basculent sur une **silhouette de secours** (en-tête `X-Avatar: fallback`,
  non mise en cache → réessai au prochain chargement). La page n'est jamais bloquée.

## Réinstaller sur un nouveau serveur

1. Télécharger Minerva (`win-x64` ou `linux-x64`) et le runtime aspnetcore 8
   (sections ci-dessus) dans `tools/minerva-bin/`.
2. Copier les scripts du superviseur (versionnés) à côté du binaire :
   `copy Server\www\avatar\minerva-scripts\* tools\minerva-bin\`
   (sous Linux : adapter en service `systemd`, voir plus haut).
3. Vérifier `MINERVA_BASE` dans `Server/www/avatar.php` (port 5123 par défaut).
4. Démarrer avec `START-HABBORETROV14.bat` (Minerva = étape 5).

Les scripts de référence sont versionnés dans `Server/www/avatar/minerva-scripts/` ;
ils sont copiés dans `tools/minerva-bin/` (non versionné) à l'installation.

## Rappels

- Lecture seule : on **ne modifie jamais** la figure, la mission ni la connexion au jeu.
- Minerva reste **local** (localhost:5123). Ne pas l'exposer publiquement.
- Non versionnés : `tools/minerva-bin/` (binaire + runtime) et les caches
  (`Server/www/cache/`, `Server/www/avatar/cache/`).
