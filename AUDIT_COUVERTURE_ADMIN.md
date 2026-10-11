# Inventaire de couverture — Administration HabboretroV14

But : répondre précisément à l'impression « on a créé beaucoup de choses, mais on ne
peut pas encore tout gérer correctement ». Pour chaque module de l'admin (`admin/index.php`,
~4 450 lignes), on indique **l'état réel**, **ce qui est modifiable**, **ce qui reste codé
en dur / manque**, et **ce qui dépend du serveur de jeu**.

**Audit du code uniquement** (lecture de `admin/index.php` au 2026-10-11). Les dimensions
écran restent à vérifier dans Chrome/Basilisk.

## Légende
- 🟢 **Fonctionnel** — on peut consulter ET modifier, c'est enregistré.
- 🟡 **Partiel** — marche en partie ; manque filtres, aperçu, granularité ou garde-fous.
- 📄 **Documentation** — l'écran explique/guide, mais ne gère rien (action réelle en jeu).
- ⬜ **Non développé** — annoncé « À construire / à venir », ou inexistant.

---

## 1. Accueil & recherche
| Module | État | Détail |
|---|---|---|
| **Dashboard** (`dashboard`) | 🟡 | Affiche chiffres + listes + bouton « Redémarrer l'ému » et « Distribuer crédits à tous » en avant. Expose **les dernières actions admin sans le contrôle d'accès de l'onglet Journal**. Manque une vraie vue « à traiter ». |
| **Recherche globale** (`search`) | 🟢 | Joueurs / catalogue / meubles / salles, liens vers les fiches. Simple et utile. |

## 2. Gestion du Site (v2)
| Sous-rubrique | État | Détail |
|---|---|---|
| Vue d'ensemble | 🟡 | Favoris + raccourcis ; pas de vue « contenus à finir / brouillons / liens suspects ». |
| Tous les contenus | 🟡 | Liste avec recherche ; filtres limités (type/page/auteur/menu à enrichir). |
| Pages & navigation | 🟢 | Édition de pages + blocs + arborescence ; « modifier la page » vs « modifier le lien menu » pas assez distingués. |
| Accueil (blocs) | 🟡 | Blocs = **petits champs texte 400 car.** (`site.home_*`). Pas de gestion de présentation (image/bouton/emplacement). |
| Actualités | 🟡 | **Deux éditeurs** modifient la même table `site_news` (à unifier). Éditeur visuel complet existant. |
| Carrousels | 🟢 | 4 slots par page, image+titre+lien ; saisie du chemin d'image à la main (pas de sélecteur). |
| Médias | 🟡 | Import + bibliothèque + dimensions. Marqué « ⬜ À compléter » : catégories, recherche, **« utilisé dans quelles pages ? »**, garde-fou de suppression. |
| **Apparence (fond du site)** | 🟢 | Couleur + galerie `bg_patterns/` + import + modes + aperçu + restaurer (PR #10→#13). Fait récemment. |
| Événements & Infobus | ⬜ | Marqué **« À construire »** (titre/date/intervenant/salle/état/mise en avant). |
| Historique | 🟡 | Journal des changements ; **restauration « À venir »** + pas d'anti-écrasement concurrent. |

## 3. Joueurs & modération
| Module | État | Détail |
|---|---|---|
| **Joueurs** (`users`) | 🟢 | Recherche + comptes + **gros formulaire de création en haut** (à mettre derrière « Créer »). |
| **Fiche joueur** (`user`) | 🟢 | Très riche (compte, badges, salles, IP, notes, messages) mais **tout empilé** ; à passer en onglets. |
| **Badges** (`badges`) | 🟢 | Attribution individuelle + distribution collective ; galerie/recherche à ajouter. |
| **Rangs** (`ranks`) | 🟢 | Badges par rang ; les **droits en jeu** ne sont pas affichés clairement. |
| **Modération** (`moderation`) | 🟡 | Dense : bans, audit en jeu, logs chat, multi-comptes, débloquer inventaire, mémo alertes. Plusieurs défauts (voir § Bugs). |
| **Habbo Homes** (`homemod`) | 🟢 | Signalements + liens Homes + actions. À mieux exploiter (file à gauche / dossier à droite), pas à recréer. |
| **Journal admin** (`audit`) | 🟡 | Liste + pagination ; **pas de filtres** (auteur/action/cible/date) ni **détail avant→après**. |
| **Commandes en jeu** (`commandes`) | 🟢 | Constructeur + favoris ; relier au contexte + signaler inventaire ancien après changement de JAR. |

## 4. Catalogue & mobis
| Module | État | Détail |
|---|---|---|
| **Catalogue** (`catalogue`) | 🟢 | Pages + articles, recherche. Outil séparé des définitions/packs. |
| **Catégories navigateur** (`navcats`) | 🟢 | CRUD ; **mal rangé** (devrait être côté Hôtel). |
| **Packs** (`packages`) | 🟢 | CRUD mais **saisie des IDs à la main** (pas de sélection visuelle). |
| **Meubles — définitions** (`furni`) | 🟢 | Édition + distribution à tous ; mélange infos courantes et techniques. |
| **Convertir furni** (`convert`) | 🟡 | Télécharge/convertit un .swf→.cct. **Le résultat « cube réparé » n'est pas prouvé** (cf. essais) ; à passer en « outils expérimentaux ». |
| **Trax** (`trax`) | 🟢 | Renommer/supprimer les pistes composées en jeu ; écoute web = dev distinct. |

## 5. Hôtel & animations
| Module | État | Détail |
|---|---|---|
| **Salles & décors** (`rooms`) | 🟢 | Édition + décors saisonniers + bouton « Redémarrer l'ému ». Séparer publiques/appartements. |
| **Modèles de salles** (`models`) | 🟢 | Valeurs techniques ; manque aperçu plan/porte/cases. |
| **Bots** (`bots`) | 🟢 | Édition par bot ; champs guidés parler/crier/chuchoter à ajouter. |
| **Jeux** (`games`) | 🟢 | Classements + réglages + points ; corrections de points sans motif obligatoire. |
| **Cartes de jeux** (`gamemaps`) | 🟡 | Édition de chaînes **« à l'aveugle »** ; manque aperçu de grille + validation des dimensions. |
| **Événements** (`events`) | 🟢 | Consulter/terminer les événements **créés en jeu**. Pas de **calendrier éditorial** (c'est la partie ⬜). |
| **Recycleur / Ecotron** (`recycler`) | 🟢 | CRUD récompenses ; durées en secondes brutes (unités à clarifier). |
| **Codes promo** (`vouchers`) | 🟢 | CRUD ; sale codes à la main ; pas de suivi d'utilisation. |
| **Bus / Infobus** (`bus`) | 📄 | **Guide + mémo seulement** : « se pilote en jeu ». Aucune gestion de séance. |

## 6. Messagerie
| Module | État | Détail |
|---|---|---|
| **Messages** (`messages`) | 🟡 | Mélange : messages Contact joueurs, **file de récupération de mot de passe**, discussion staff. À séparer (conversations / file dédiée). Récupération = mot de passe provisoire `Habbo`+4 chiffres (**secret retiré du journal en PR #13**). |

## 7. Administration (technique)
| Module | État | Détail |
|---|---|---|
| **Réglages** (`settings`) | 🟢 | Réglages courants + tous + filtre ; **valeurs en texte libre** (pas d'interrupteurs/unités/défauts). |
| **Textes du jeu** (`textes`) | 🟢 | Édite `external_texts` (serveur) ; distinct des textes **client** (`dcr/14.1_b8`, CP1252). Recherche/catégories à ajouter. Appliqué **après redémarrage de l'ému**. |
| **Accès admin** (`access`) | 🟡 | Rang minimum **par onglet** ; trop grossier (pas de distinction consulter/modifier/publier/action sensible). |
| **Base MySQL** (`mysql`) | 🟡 | Explorateur **lecture seule** (liste blanche) ; à renommer « Explorateur » et **masquer les colonnes sensibles** (hash mot de passe visibles comme les autres). |
| **Serveur** (`server`) | 🟡 | Démarrer/arrêter/redémarrer l'ému ; **l'arrêt force le kill** (voir Bugs) ; « Redémarrer » annonce OK sans vérifier que Kepler est reparti ; diagnostics « port ouvert = OK » insuffisants. |

---

## Bugs confirmés dans le code (priorité « avant présentation »)
| # | Constat (ligne) | État |
|---|---|---|
| 1 | **`_emu_stop.ps1` force l'arrêt de Kepler** (`Stop-Process -Force`, sans sauvegarde). | ⚠️ à corriger (dédié) |
| 2 | Réinit mot de passe : **secret en clair dans le journal** (logger générique `admin_log($a,$msg)`). | ✅ **corrigé PR #13** |
| 3 | **`site_bg_apply`/`site_bg_reset` refusés aux rangs < 7** (absents de `action_tab`). | ✅ **corrigé PR #13** |
| 4 | **Formulaire d'import Apparence non fermé** (formulaires imbriqués). | ✅ **corrigé PR #13** |
| 5 | **Bannissements « actifs » : la requête ne filtre pas l'échéance** (`users_bans` sans `WHERE banned_until>maintenant`) → les bans expirés restent listés comme actifs. | ⚠️ à corriger |
| 6 | **« Débloquer un inventaire » vide TOUTE la main** (`clear_hand`) au lieu de cibler l'objet coincé. | ⚠️ à corriger (ciblage + libellé) |
| 7 | **Logs de chat limités à 100**, filtre joueur seulement (pas salle/période/type, pas de pagination, pas de contexte). | 🟡 à enrichir |
| 8 | **Dashboard expose les dernières actions admin** sans le contrôle d'accès de l'onglet Journal. | ⚠️ à aligner |
| 9 | Textes : « rang 6 = administrateur maximum » vs config à **rang 7** → incohérence de libellés. | 🟡 à unifier |
| 10 | **Deux éditeurs d'actualités** sur la même table → faire converger vers un seul. | 🟡 à unifier |

## Ce qui dépend d'un développement serveur (émulateur Kepler)
- **Infobus** (séances) : piloté en jeu ; pas d'API d'orchestration côté admin.
- **Journal de discussion client** (`chatlog.url`) : nécessite un endpoint staff `chatId→room_chatlogs` (chantier distinct, déjà signalé).
- **Alertes/annonces aux joueurs connectés** : via commandes en jeu (RCON/MUS), pas depuis l'admin web.
- Tout changement de **réglages, bots, décors, textes serveur** nécessite un **redémarrage** de l'ému pour s'appliquer.

## Synthèse — pourquoi « on ne peut pas tout gérer »
- **La plupart des modules sont 🟢 fonctionnels** pour du CRUD de base : l'admin gère déjà beaucoup.
- Mais : **3 zones ⬜/📄** promettent plus qu'elles ne font (Événements éditoriaux, Infobus, Historique/restauration) ;
- plusieurs **🟡** manquent de **filtres, aperçu, granularité et garde-fous** (modération, journal, médias, réglages, accès, MySQL) ;
- des **outils liés sont séparés** (catalogue ↔ packs ↔ définitions ↔ ressources ; joueur ↔ inventaire ↔ sanctions) → on navigue au lieu de voir une fiche transversale ;
- et quelques **bugs fonctionnels** (tableau ci-dessus) faussent la confiance.

L'impression est donc juste : **le socle existe**, il faut surtout **relier, filtrer, cadrer** et **finir les 3 zones non développées**, pas tout refaire.
