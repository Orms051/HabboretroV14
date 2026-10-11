# Workflow de développement

Pour qu'un changement de branche ne modifie **jamais** le site en cours
d'utilisation, on sépare le dossier **live** et le dossier **dev**.

| Dossier | Branche | Rôle |
|---|---|---|
| `C:/laragon/www/HabboretroV14` | **main** | **Live** — servi par Apache (vhost → `Server/www`). On n'y change **jamais** de branche. |
| `C:/laragon/HabboretroV14-dev` | `dev` (+ branches de chantier) | **Développement** — hors `www`, non servi. C'est ici qu'on édite/teste/commite. |

Les deux partagent le **même dépôt Git** (worktrees) : `git worktree list`.

## Faire un nouveau chantier
```bash
cd C:/laragon/HabboretroV14-dev
git checkout main && git pull            # repartir à jour
git checkout -b feat/mon-chantier        # branche de travail
# … éditer / committer ici …
git push -u origin feat/mon-chantier
gh pr create ...                         # ouvrir la PR
```

## Mettre le live à jour (après fusion de la PR dans main)
Dans le dossier **live**, sans jamais revenir à une ancienne version :
```bash
cd C:/laragon/www/HabboretroV14
git fetch origin
git merge --ff-only origin/main          # avance main (fast-forward), aucun revert
```
(Si ce n'est pas un fast-forward, c'est qu'on a commité par erreur dans le live ;
dans ce cas, régler le conflit avant.)

## Fichiers non versionnés (préservés quoi qu'il arrive)
`tools/minerva-bin/` (binaire + runtime), `Server/www/cache/`,
`Server/www/avatar/cache/`, `maintenance.json`, `server.log`, `_backups_refonte/`.
Ils ne sont pas suivis par Git → un changement de branche ne les touche pas.

## Tester une version dev
Le dossier dev n'est pas servi par Apache. Pour le prévisualiser, ajouter au
besoin un vhost/port dédié pointant sur `…/HabboretroV14-dev/Server/www`
(chantier séparé si nécessaire). Le service Minerva (avatars) tourne en local
et est partagé ; inutile de le relancer pour du dev de site.
