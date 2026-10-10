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

```bat
tools\minerva-bin\start-minerva.bat        REM fenêtre masquée, port 5123
```

ou directement :

```powershell
tools\minerva-bin\start-minerva.ps1
```

Le service écoute sur `http://localhost:5123` (jamais exposé au réseau). Au
premier lancement il charge les assets Shockwave (`9762 flash assets`, `301
figure sets`).

### Démarrage automatique

- **Dev / Windows** : placer un raccourci vers `start-minerva.bat` dans
  `shell:startup`, **ou** créer une tâche planifiée « Au démarrage de session »
  pointant sur le `.bat`.
- **Serveur Windows** : tâche planifiée « Au démarrage de l'ordinateur »
  (compte de service, « Exécuter même si l'utilisateur n'est pas connecté »).
- **Serveur Linux** : prendre `Minerva-linux-x64.zip`, installer le runtime
  aspnetcore 8, et créer un service `systemd` lançant
  `dotnet Minerva.dll --shockwave-badge-render` avec `ASPNETCORE_URLS=http://localhost:5123`.

Mettre `avatar.php` → `MINERVA_BASE` en cohérence avec le port.

## Fonctionnement & cache

- Clé de cache = `sha1(figure | sexe | size | direction | head | version_convertisseur)`.
  Une **nouvelle tenue en base = nouvelle clé = nouvelle image**, sans purge.
- Pour forcer un rafraîchissement global (ex. après correction du convertisseur) :
  bump `AVATAR_CONV_VERSION` (figure_lib.php) **et** `AVATAR_URL_VER`
  (v2/inc/boot.php). On peut aussi vider `Server/www/cache/avatars/`.
- Si Minerva est **arrêté** : les avatars déjà en cache restent servis ; les
  autres basculent sur une **silhouette de secours** (en-tête `X-Avatar: fallback`,
  non mise en cache → réessai au prochain chargement). La page n'est jamais bloquée.

## Rappels

- Lecture seule : on **ne modifie jamais** la figure, la mission ni la connexion au jeu.
- Minerva reste **local**. Ne pas l'exposer publiquement.
- Dossiers non versionnés : `tools/minerva-bin/` et `Server/www/cache/`.
