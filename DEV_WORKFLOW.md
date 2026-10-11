# Workflow de développement

Pour qu'un changement de branche ne modifie **jamais** le site en cours
d'utilisation, on sépare le dossier **live** et le dossier **dev**.

| Dossier | Branche | Rôle |
|---|---|---|
| `C:/laragon/www/HabboretroV14` | **main** | **Live** — servi par Apache (vhost → `Server/www`). On n'y change **jamais** de branche. |
| `C:/laragon/HabboretroV14-dev` | `dev` (+ branches de chantier) | **Développement** — hors `www`, non servi. Édition / tests / commits ici. |

Les deux partagent le **même dépôt Git** (worktrees) : `git worktree list`.
⚠️ `main` est « occupée » par le worktree live : on ne fait **pas** `git checkout main`
dans le dossier dev (Git refuserait). On part toujours de `origin/main`.

## Démarrer un nouveau chantier (dans le dossier dev)

```bash
cd C:/laragon/HabboretroV14-dev
git status                                   # 1) vérifier les modifs locales en cours
# si des modifs non commitées sont à garder : git stash  (ou committer sur la branche courante)
git fetch origin
git switch -c feat/mon-chantier origin/main  # 2) nouvelle branche depuis origin/main
#   (ne PAS faire « git checkout main » : cette branche sert au worktree live)
# … éditer / committer ici …
git push -u origin feat/mon-chantier
gh pr create ...
```

## Mettre le live à jour (après fusion de la PR dans main)

Dans le dossier **live**, sans jamais revenir à une ancienne version :
```bash
cd C:/laragon/www/HabboretroV14
git fetch origin
git merge --ff-only origin/main              # avance main (fast-forward), aucun revert
```

### Si le fast-forward échoue — NE PAS improviser
Un échec de `--ff-only` ne veut **pas** forcément dire qu'un commit a été fait par
erreur dans le live. **Diagnostiquer d'abord, ne rien forcer** :
```bash
git status                                   # modifs locales ? branche en avance/retard ?
git log --oneline --graph origin/main...HEAD # voir la divergence réelle
```
- **Arrêter le déploiement** tant que ce n'est pas compris.
- **Conserver les modifications locales** (les mettre de côté : `git stash` ou une
  branche de sauvegarde).
- **Interdits ici** : `git reset --hard`, `git clean -fd` / nettoyage forcé,
  résolution automatique. On ne détruit rien avant d'avoir compris la divergence.

## Fichiers ignorés par Git — ⚠️ NE SONT PAS des sauvegardes

Ces fichiers/dossiers ne sont **pas** suivis par Git (donc un changement de branche
ne les touche pas), mais ils **ne sont pas sauvegardés par Git** pour autant : il
faut **les sauvegarder à part** et **ne jamais les supprimer** via une commande de
nettoyage (`git clean`, suppression de dossier, etc.).

| Chemin (dans le live) | Contenu | Nécessaire au fonctionnement ? |
|---|---|---|
| `tools/minerva-bin/` | Binaire Minerva + runtime .NET local + scripts | **Oui** (avatars) |
| `Server/www/cache/` | Avatars générés (régénérables) | Non (cache) |
| `Server/www/avatar/cache/` | Cache figuredata (régénérable) | Non (cache) |
| `maintenance.json` | Drapeau de maintenance (présent seulement si activée) | Selon état |
| `server.log` | Journal de l'émulateur | Non (log) |
| `_backups_refonte/` | **Sauvegardes** (dumps BDD, configs avant bascule) | **À conserver** |

## Le worktree dev ne reçoit PAS les fichiers ignorés du live

`git worktree add` crée une copie de travail **des fichiers suivis uniquement**.
Les éléments ci-dessus **n'y sont pas copiés**. Pour que le dev fonctionne, les
préparer au besoin :

- **Avatars (Minerva)** : soit réutiliser le service live (il écoute déjà sur
  `localhost:5123`, partagé) sans rien installer dans le dev ; soit, pour un dev
  isolé, réinstaller `tools/minerva-bin/` dans le worktree (voir
  `Server/www/avatar/README.md`) sur un **autre port** et pointer
  `avatar.php` → `MINERVA_BASE` dessus.
- **Caches** : se recréent tout seuls, rien à copier.
- **Config locale / secrets** : à recréer dans le dev, **hors Git** (voir ci-dessous).

## Isoler réellement les tests (avant d'utiliser le dev pour écrire)

Un dossier séparé **ne suffit pas** à isoler les tests : le dev pointe par défaut
sur la **même base `v14`** et le **même émulateur** que le live. Avant tout test
qui **écrit** (inscriptions, Homes, messages, achats, commandes…), vérifier :

1. **Base de test distincte** pour les écritures (ex. base `v14_dev` = copie du
   dump) — ne jamais écrire dans la base du live depuis le dev.
2. **Aucun appel de test** aux commandes **RCON / MUS** de l'émulateur **live**
   (alerte hôtel, reload, etc.) : elles agissent sur le serveur réel.
3. **Sessions/cookies distincts** si une adresse de prévisualisation est créée
   (nom de session / domaine différent) pour éviter les conflits avec le live.
4. **Logs, caches et fichiers temporaires propres au dev** (chemins séparés).
5. **Configuration locale et secrets exclus de Git** (jamais commités).

> Tant que ces points ne sont pas en place, le dev sert à éditer le code et à
> faire des vérifications **en lecture** — pas à tester des écritures sur les
> données du live.

---
Le site reste sur **main**. Prochain chantier : **le lanceur**, traité séparément.
