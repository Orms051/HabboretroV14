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
2. **Kepler** : arrêt **propre** (déclenche la sauvegarde via le *shutdown hook*
   de la JVM), puis attente de sa fin. Arrêt forcé = **dernier recours explicite**
   seulement s'il refuse de s'arrêter dans le délai.
3. **MariaDB** (pack) : arrêt propre (`mysqladmin shutdown`), **après** Kepler.
4. **Apache** : **laissé en marche** (service partagé Laragon, utilisé par
   d'autres projets). Jamais de `taskkill` global sur `httpd.exe` ou `java.exe`.

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
