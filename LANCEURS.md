# Lanceurs du rétro — démarrer / arrêter

Deux fichiers à **double-cliquer**, à la racine du pack :

- **`START-HABBORETROV14.bat`** — démarre le rétro.
- **`STOP-HABBORETROV14.bat`** — l'arrête proprement.

Ils appellent `scripts/pack.ps1` (PowerShell). Toute la configuration dépendant
de la machine est **au même endroit**, en haut de `scripts/pack.ps1` (bloc `$Cfg`) :
chemins du pack, chemin externe d'Apache, ports, délais, base et tables.

## Démarrage

Ordre et dépendances respectés :
1. **MariaDB** (pack) — démarrée puis **vérifiée réellement prête** (base `v14` +
   tables indispensables présentes, pas seulement « le port répond »).
2. **Apache** (Laragon, **partagé**) — démarré s'il est absent ; le site doit
   répondre avec la bonne page.
3. **Kepler** (jeu) — démarré seulement si MariaDB est prête.
4. **Minerva** (avatars) — non bloquant : si indisponible, le site et le jeu
   fonctionnent et l'avatar passe en **mode secours** (silhouette).

Chaque étape affiche un état clair : **Déjà actif · Démarrage · Prêt · Échec**.
À la fin : un **bilan** (MariaDB / Apache / Kepler / Minerva) et les **adresses**
(site, admin, jeu). En cas d'échec d'un service, les composants qui en dépendent
ne sont pas lancés, et le journal est indiqué.

Le lanceur **ne tue rien** : un service déjà lancé par le pack est réutilisé ; un
port occupé par une **autre application** est **signalé sans être arrêté** (à toi
de voir). Les services sont reconnus par leur **chemin**, pas seulement par leur
nom ou un port ouvert. Double-cliquer deux fois ne crée pas de doublon.

## Arrêt (propre, ciblé pack)

1. **Minerva** : la supervision est désactivée **avant** d'arrêter le service
   (pas de relance intempestive).
2. **Kepler** : demande d'arrêt **propre** (`taskkill` **sans** `/f`, qui déclenche
   le *shutdown hook* de la JVM), puis attente de sa fin.
   - **Pas d'arrêt forcé automatique.** S'il refuse de s'arrêter dans le délai, le
     lanceur **demande explicitement** s'il faut forcer l'arrêt ciblé de ce PID ;
     sinon le processus est **laissé actif**.
   - ⚠️ Vérifie la **sauvegarde** dans `server.log` pour confirmer que l'arrêt
     propre a bien eu lieu (le *shutdown hook* existe, mais il faut le constater
     sur ta machine).
3. **MariaDB** (pack) : arrêt propre (`mysqladmin shutdown`), **après** Kepler, et
   **seulement** si le port 3306 est bien tenu par NOTRE MariaDB. **Si Kepler est
   encore actif, la base n'est PAS arrêtée** (on ne coupe pas la base sous lui).
4. **Apache** : **laissé en marche** (service partagé Laragon). Jamais de
   `taskkill` global sur `httpd.exe` ou `java.exe`.

Si un composant n'a pas pu être arrêté, le lanceur l'affiche clairement et se
termine en **échec** (pas de faux « Rétro arrêté »).

## Fenêtre Minerva

Minerva tourne dans une fenêtre **Invite de commandes** titrée
**« Minerva — Avatars Habbo »**, ouverte **réduite**, avec les **logs en direct**.
Un superviseur PowerShell **caché** gère la **relance après plantage** (délai
croissant 5→30 s). Service accessible **uniquement en local** (port 5123), **une
seule instance**. Un **arrêt volontaire** (via STOP) **désactive** la relance.

## Logs

- Lanceur : `tools/pack-logs/pack.log` (démarrages/arrêts datés, rotation à 2 Mo).
- Minerva : `tools/minerva-bin/minerva.log` (événements du superviseur, rotation
  à 5 Mo). Les logs **en direct** de Minerva sont dans sa fenêtre CMD.
- Aucun secret n'est écrit dans les logs.

## Erreurs courantes

| Symptôme | Cause probable | Quoi faire |
|---|---|---|
| « Prérequis manquants » | MariaDB/Kepler/Minerva absents | Suivre la liste affichée (chemins exacts) ; pour Minerva voir `Server/www/avatar/README.md`. |
| « port 3306 occupé par une AUTRE application » | MySQL de Laragon actif | L'arrêter dans Laragon, puis relancer. |
| « port 80 occupé par une autre application » | Un autre serveur web | Libérer le port 80 ou adapter la config. |
| « base v14 incomplète » | Base non importée / mauvaise instance | Vérifier la base `v14` et ses tables. |
| « Avatars : MODE SECOURS » | Minerva arrêté/en erreur | Voir `tools/minerva-bin/minerva.log` ; le site et le jeu marchent quand même. |
| Apache a plusieurs versions | Plusieurs `httpd-*` dans Laragon | Renseigner `ApacheDir` en haut de `scripts/pack.ps1`. |

En cas d'erreur, le lanceur affiche une explication courte **et** le chemin du
journal — il ne masque pas les erreurs derrière un faux « succès ».

## Identité de Kepler & première mise à jour (important)

Kepler est reconnu par un **marqueur de lancement** (`-Dhabbo.pack="…"` ajouté
dans `Server/www/run.bat`) tenant à CE dossier : le lanceur ne réutilisera ni
n'arrêtera **jamais** un autre Kepler.

**Transition (une seule fois)** : un Kepler déjà lancé par l'**ancien** `run.bat`
n'a pas ce marqueur. Après la mise à jour, le nouveau lanceur ne le reconnaîtra
pas (il signalera un conflit de port, sans le tuer) et le nouveau STOP ne
l'arrêtera pas. **Arrête ce Kepler une fois manuellement** (ancien STOP, ou la
fenêtre Émulateur), puis redémarre via le nouveau `START` : Kepler portera alors
le marqueur et sera géré normalement.

## Verrou anti-double-lancement

Un **verrou par installation** (mutex nommé) entoure START et STOP : si tu
double-cliques deux fois, le second affiche « un autre lanceur est déjà en
cours ». Le superviseur Minerva a aussi son propre verrou.

## Scripts Minerva : livraison reproductible

Les scripts du superviseur sont **versionnés** dans
`Server/www/avatar/minerva-scripts/`, mais exécutés depuis `tools/minerva-bin/`
(non versionné). Au démarrage, le lanceur **synchronise** automatiquement les
copies (versionné → `tools/minerva-bin/`) en **sauvegardant** l'ancienne
(`*.ps1.bak`) et **sans toucher** au binaire, au runtime ni aux logs. Une simple
fusion Git ne suffit donc pas : c'est le `START` qui met les copies à jour.
