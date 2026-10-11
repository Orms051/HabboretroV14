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
2. **Kepler** : demande d'arrêt **propre** par un vrai **Ctrl+C** (`CTRL_C_EVENT`)
   envoyé à SA console, ce qui déclenche le *shutdown hook* `dispose()` de la JVM
   (sauvegarde items/chat/joueurs, puis fermeture des sockets), puis attente de sa
   fin. *(Un `java.exe` en console n'a pas de fenêtre : un `taskkill` sans `/f` ne
   l'atteindrait pas — d'où le Ctrl+C.)* La fenêtre « Émulateur » peut rester sur
   « Terminate batch job (Y/N)? » : Kepler a déjà sauvegardé et quitté, le lanceur
   referme cette fenêtre (cosmétique).
   - **Pas d'arrêt forcé automatique.** S'il refuse de s'arrêter dans le délai, le
     lanceur **demande explicitement** s'il faut forcer l'arrêt ciblé de ce PID
     (`/f`, **sans** sauvegarde) ; sinon le processus est **laissé actif**.
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
n'a pas ce marqueur. Après la mise à jour :
- au **START**, il est signalé comme **conflit** de port (sans être tué) ;
- au **STOP**, il est signalé comme **processus non identifié sur le port du jeu**,
  **MariaDB est conservée** (il peut encore utiliser la base), et l'arrêt est
  marqué **incomplet** — il n'est pas tué.

**N'utilise pas l'ancien STOP** (il force des arrêts et coupe *tous* les
`httpd.exe` d'Apache, partagé — inadapté, surtout en panne partielle).

Marche à suivre, en gardant la base active :

1. **Identifie d'abord** le processus qui écoute le port du jeu (12321) — ne tue
   rien à l'aveugle :
   ```bat
   powershell -NoProfile -Command "$p=(Get-NetTCPConnection -LocalPort 12321 -State Listen).OwningProcess; Get-CimInstance Win32_Process -Filter \"ProcessId=$p\" | Select-Object ProcessId,ExecutablePath,CommandLine | Format-List"
   ```
2. **Confirme** qu'il s'agit bien d'un ancien Kepler du pack (chemin / ligne de
   commande) avant toute action.
3. S'il l'est, **arrête-le par ses propres moyens** (sa fenêtre Émulateur, par
   exemple). ⚠️ Une **sauvegarde propre n'est PAS garantie** par un simple arrêt
   du processus : **vérifie-la dans `Server\www\server.log`**.
4. Une fois le port du jeu libéré, redémarre via le nouveau `START` : Kepler
   portera le marqueur et sera géré normalement (au START comme au STOP).

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

## Procédure finale — installation & tests (à exécuter par toi, sous Windows)

> Les lanceurs livrés n'ont **pas** été validés par un démarrage/arrêt réel : les
> processus lancés depuis l'environnement d'assistance ne survivent pas à sa
> session (ce n'est **pas** représentatif d'un double-clic Windows). La
> validation ci-dessous se fait donc **par toi**, **sans joueur connecté**.

### 1. Sauvegarder les fichiers actuellement utilisés
Déjà fait côté dépôt : `_backups_refonte/lanceurs_avant_pr5_*` contient les
`START/STOP-HABBORETROV14.bat`, `run.bat` et les scripts `tools/minerva-bin/*.ps1`
d'avant. (Au besoin, refais une copie juste avant la mise à jour.)

### 2. Installer **ensemble** la version cohérente
Lanceurs, `run.bat` (marqueur Kepler) et scripts Minerva viennent de la **même**
PR #5 : on les met à jour d'un bloc.

**2a — Fusionner d'abord la PR #5 dans `main`.** La PR #5 doit être **fusionnée**
(sur GitHub, bouton *Merge*, ou `gh pr merge 5`) **avant** la mise à jour du live.
Tant qu'elle n'est pas fusionnée, `origin/main` **ne contient pas** le nouveau
lanceur et le `git merge` ci-dessous n'apporterait rien.

**2b — Mettre le live à jour** (fast-forward uniquement, aucun retour arrière).
La ligne `git merge` ci-dessous apporte d'un bloc : START/STOP, `scripts\pack.ps1`,
`run.bat` et les scripts Minerva versionnés.
```bat
cd /d C:\laragon\www\HabboretroV14
git fetch origin
git merge --ff-only origin/main
```
> Remarque .bat : ne mets **jamais** de `REM …` à la suite d'une commande sur la
> **même ligne** — il serait passé comme **argument** à `git` (erreur). Un `REM`
> doit toujours être **seul sur sa ligne**.

Les copies ignorées de Minerva (`tools/minerva-bin/*.ps1`) seront **synchronisées
au premier START** (sauvegarde `*.bak`, sans toucher binaire/runtime/logs).
> ⚠️ Le marqueur d'identité de `run.bat` ne s'applique qu'à un Kepler **relancé
> après** la mise à jour. Si l'ancien Kepler tourne encore, suis d'abord la
> **transition** décrite plus haut (l'identifier, l'arrêter par ses moyens,
> vérifier la sauvegarde dans `server.log`).

### 3. Démarrer (double-clic) — sans joueur connecté
Double-clique le fichier **`C:\laragon\www\HabboretroV14\START-HABBORETROV14.bat`**
(c'est **uniquement** le nouveau lanceur ; il appelle `scripts\pack.ps1 -Action start`).
Vérifie que chaque étape affiche **Déjà actif / Démarrage / Prêt**, et que le
**bilan** final est : MariaDB *prête*, Apache *prêt*, Kepler *prêt*, Minerva *prêt*.

### 4. Vérifier le fonctionnement
- **Jeu** : ouvre `http://localhost/client.php` dans **Basilisk**, entre dans
  l'hôtel, connecte-toi en jeu.
- **Avatar non caché** : **ne vide pas** le cache. Force plutôt un rendu neuf en
  demandant une **figure absente du cache** (le cache est indexé par la chaîne
  `figure` : une combinaison de couleurs inhabituelle donne une nouvelle clé sans
  toucher aux fichiers déjà générés). Ouvre dans Basilisk :
  ```
  http://localhost/avatar.php?figure=hr-115-42.hd-190-10.ch-215-66.lg-285-82.sh-290-80&sex=M&direction=2&size=b
  ```
  L'image de l'avatar doit s'afficher (pas la silhouette de secours). Tu peux
  changer un chiffre de couleur pour forcer une autre clé si besoin.
- **Maintien après fermeture du lanceur** : **ferme la fenêtre** du lanceur
  principal (START). Vérifie que **MariaDB, Apache, Kepler et Minerva restent
  actifs** (le site et le jeu continuent de répondre). C'est le point clé que je
  n'ai pas pu valider moi-même.

### 5. Arrêter et **constater la sauvegarde**
Double-clique **`STOP-HABBORETROV14.bat`**. Avant de le déclarer « propre » :
- vérifie dans `Server/www/server.log` la trace de **sauvegarde/arrêt** de Kepler
  (le *shutdown hook* seul ne prouve rien) ;
- vérifie le **bilan** : Kepler arrêté, **MariaDB arrêtée après lui**, **Apache
  préservé** (partagé) ; aucun « ARRÊT INCOMPLET ».
- (Optionnel) teste le refus de forcer : si Kepler tarde, réponds **N** au prompt
  → Kepler reste actif, MariaDB conservée, arrêt marqué **incomplet**.

Reporte-moi ce qui s'est passé à chaque point : on ajustera si besoin, puis on
clôt le chantier.
