# Bascule V2 → site principal (racine)

La V2 est désormais servie **à la racine** du domaine (plus de `/v2/`). La bascule
est **uniquement une réécriture Apache** (`Server/www/.htaccess`) : **aucune donnée
n'est déplacée ni modifiée**. La base `v14` (comptes, Homes, messages, achats,
articles, carrousels, permissions) est intacte et partagée.

## Ce qui a été fait

- `Server/www/.htaccess` :
  - `DirectoryIndex v2/index.php index.php …` → `/` sert la V2 ; les sous-dossiers
    (ex. `/admin/`) retombent sur leur propre `index.php`.
  - `/v2/…` → redirection **301** vers la racine (paramètres conservés, `QSA`).
  - `/index.php` (ancienne V1) → **301** vers `/` (V1 conservée pour rollback,
    mais non atteignable publiquement).
  - Blindage : `*.log *.sql *.md *.ps1 *.bat *.sh *.ini *.bak` refusés ;
    `/tools /_migrations /_backups_refonte`, `/admin/tools /admin/data`,
    `/avatar/cache /avatar/minerva-scripts` → 404.
- Code (non destructif) :
  - `v2/inc/boot.php` : `site_url()` (base déduite du host, **pas de localhost en
    dur** → accès réseau OK) + `ASSET_VER` (cache-bust si besoin futur).
  - lien de partage de Home : plus de `/v2/` en dur.
  - route `?p=profile&id=…|&u=…` (lien ouvert par le client) → redirige vers la
    Habbo Home, en **conservant `hotelclient`**.
  - page **404** : renvoie un vrai code HTTP 404, dans le style du site.
- Cookies/sessions : chemin `/` (déjà le cas) → connexion site + admin sans boucle.
  V1 et V2 partagent `$_SESSION['site_user']` (pas de conflit).

## Adresse du site principal

- **http://localhost/** (ou l'adresse réseau du serveur pour les autres PC).
- Admin : **http://localhost/admin/**
- Jeu (client) : **http://localhost/client.php** (inchangé).

## RETOUR ARRIÈRE (rollback) — sans aucune perte de données

La bascule ne touche QUE le routage. Restaurer les fichiers **ne fait perdre
aucun message, achat ou Home** (tout est en base, non modifié par la bascule).

1. Restaurer l'ancien `.htaccess` :
   ```
   copy _backups_refonte\bascule_v2_AAAAMMJJ_HHMMSS\root.htaccess  Server\www\.htaccess
   ```
   (sauvegarde du `.htaccess` d'origine faite avant la bascule.)
2. C'est tout : `/` resert la V1, `/v2/` resert la V2. Aucune commande SQL.

> La V1 est restée le fichier `Server/www/index.php` (jamais supprimé).
> Le dump de base pré-bascule est dans le même dossier de sauvegarde
> (`v14_AAAAMMJJ_HHMMSS.sql`) — à n'utiliser qu'en cas de besoin explicite, car il
> est **antérieur** aux changements faits depuis (il écraserait les données
> récentes). Pour un simple retour d'affichage V1, l'étape 1 suffit.

## Restrictions d'accès (inchangées, vérifiées)

- Pages/actions admin : contrôle de rang **côté serveur** (`tab_allowed`), intact.
- Maintenance : `Server/www/maintenance.json` (écrit par l'admin) ferme site + jeu
  pour les non-staff ; chemin cohérent admin ↔ site.
