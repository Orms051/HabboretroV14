# HabboretroV14

Rétro **Habbo Hotel v14 (2007)** basé sur l'émulateur **Kepler** (Quackster), tournant en local via **Laragon** (Apache + MariaDB), client **Shockwave** dans **Basilisk**.

> Projet privé, non affilié à Sulake. Habbo est une marque de Sulake.

## Contenu versionné
- `Server/www/` — site web (PHP) + **panneau d'administration** (`admin/index.php`)
- `Server/www/dcr/14.1_b8/` — casts du client + config (`external_variables.txt`, `external_texts.txt`) + patch snowstorm (`hh_gamesys_patch.cct`)
- `_archive/dev_sources/Kepler-Server/` — **code source Java** de l'émulateur
- `kepler.jar` — émulateur compilé (officiel Kepler v1.5.1 + correctifs messenger)
- Scripts de lancement (`START-*.bat`, `run.bat`, etc.)

## NON versionné (voir `.gitignore`)
- `MariaDB/` — données de la base **live** (trop gros, contient les comptes)
- `Server/www/c_images/`, `dcr/sound/`, `dcr/hof_furni/`, `web-gallery/`, `lib/` — gros médias binaires
- `tools/` — binaires d'outils (Elias, ProjectorRays, FFDec)
- Logs, sauvegardes et dumps SQL (contiennent des hash de mots de passe)

## Correctifs appliqués (2026-09)
- **Messagerie / console** : accepter un ami (`MESSENGER_ACCEPTBUDDY` : lecture `[compteur][id]`) + messages console (`MESSENGER_MSG` : suppression du `writeInt(1)` parasite). *(dans `kepler.jar` + code source)*
- **SnowStorm** : correctif du checksum côté client via `hh_gamesys_patch.cct` + `cast.entry.24` dans `external_variables.txt` (patch officiel de Sefhriloff/Kepler). *(le serveur reste officiel)*
- **Admin** : sélecteur de fond de connexion (21 pays), alertes hôtel prédéfinies, catégorie + salle « QG du Staff » (rang 4+).

## Lancer en local
1. Démarrer Laragon (Apache + MariaDB), base `v14`.
2. Lancer l'émulateur : `Server/www/run.bat` (ou `START-HABBORETROV14.bat`).
3. Client : ouvrir `http://localhost/` dans Basilisk (plugin Shockwave).
4. Admin : `http://localhost/admin/` (rang 5+).
