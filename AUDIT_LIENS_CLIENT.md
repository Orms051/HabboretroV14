# Audit des liens client ↔ site (après bascule V2)

Vérification des liens configurés dans le **client Shockwave v14** (`client.version.id=401`)
vers le **site** servi à la racine (V2). Objectif : aucun lien mort, destinations
cohérentes, pages staff protégées. **Aucune refonte**, corrections ciblées uniquement.

## Où sont configurés les liens

| Source | Rôle |
|---|---|
| `client.php` | Loader : définit `url.prefix=http://<hôte>` et `site.url`. Charge les variables/textes externes. |
| `dcr/14.1_b8/external_variables.php` | Wrapper dynamique : lit `external_variables.txt` et remplace `localhost` par l'hôte réel. |
| `dcr/14.1_b8/external_variables.txt` | Variables client. **Seul lien ici** : `link.format.userpage`. |
| `dcr/14.1_b8/external_texts.txt` | Textes + **URLs** des fenêtres (aide, club, crédits, modération…). Encodage **CP1252 / CRLF** (préservé). |
| `dcr/14.1_b8/external_textsOrigine !!!.txt` | **Référence d'origine Sulake** (non servie). Sert de comparatif. |
| `Server/www/v2/index.php` | Routeur du site : `?p=home|profile|help|club|credits|games|…` + `home/<pseudo>`. |

> Le token `%predefined%` des textes est remplacé **par le client** par l'hôte
> (ex. `localhost`) → `http://%predefined%/?p=help` → `http://localhost/?p=help`.
> Cette substitution a lieu **dans le client Shockwave** : à valider en jeu (ci-dessous).

## Inventaire (contexte · URL configurée · destination attendue · résultat · correction)

### Profil du joueur vs paramètres du compte
| Contexte | URL configurée | Attendu | Résultat | Correction |
|---|---|---|---|---|
| Fiche joueur (`userpage`) | `link.format.userpage=…/?p=profile&id=%ID%` | **Consulter** la Habbo Home | `?p=profile&id=X` → redirige vers `?p=home/<pseudo>` (Home publique), **conserve `hotelclient`** | ✅ Correct, rien à changer |
| Mon compte | *(pas de lien client dédié)* | Page compte | Site : `?p=me` (Mon Habbo : crédits, club, mission) | ✅ Distinction respectée (consultation Home ≠ `?p=me`), aucune confusion |

### Aide / fenêtres d'information
| Clé | URL configurée | Résultat | Correction |
|---|---|---|---|
| `url_help_1` | `…/?p=help#credits` | interne OK | — |
| `url_help_3/4/5` | `…/?p=help`(#regles/#securite) | interne OK | — |
| `url_helpterms/helpparents/helppledge/privacypledge/purse_link/purselink` | `…/?p=help` | interne OK | — |
| `url_help_6` | ~~`…/?p=contact`~~ | route `contact` **absente** → 404 | ✅ **corrigé → `…/?p=help`** |
| `hubu_info_url_1` | ~~`habbohotel.co.uk/…`~~ | hôte **mort** | ✅ **corrigé → `…/?p=help`** |
| `hubu_info_url_2` | ~~`talktofrank.com`~~ | externe hors sujet | ✅ **corrigé → `…/?p=help`** |
| `NUF_groups_url` | ~~`d16-1.web.varoke.net/…`~~ | hôte **mort** | ✅ **corrigé → `…/?p=help`** |
| `NUF_habbo_home_url` | ~~`d15-1.web.varoke.net/…`~~ | hôte **mort** | ✅ **corrigé → `…/?p=help`** |

### Crédits & Habbo Club
| Clé | URL configurée | Résultat | Correction |
|---|---|---|---|
| `credit_redeem_url`, `url_nobalance`, `url_purse_subscribe` | `…/?p=help` | interne OK (pas de boutique crédits dédiée → aide) | — |
| `club_info_url`, `club_buy_url`, `club_change_url`, `buddyremove_hc_info_url` | `…/?p=club` | interne OK | — |

### Modération
| Clé | URL configurée | Résultat | Correction |
|---|---|---|---|
| `chatlog.url` (`hobba_chatlog` = « Voir le journal de discussion ») | `https://theallseeingeye.sulake.com/…/chatlog.action?chatId=` | **MORT (Sulake)** | ⏭️ **Chantier distinct** — non corrigé (voir ci-dessous) |

**Pourquoi pas corrigé ici** : le client envoie un `chatId` ; il manque un **endpoint staff** qui
mappe ce `chatId` → `room_chatlogs` (le visualiseur chatlog existe déjà dans `admin/`, mais
pas en version paramétrée par `chatId` pour le client). Conformément à la consigne, on **ne crée
pas de page vide** : à traiter dans un chantier dédié. En attendant, le lien reste inopérant
(il n'est **pas** renvoyé vers l'accueil).

### Hors périmètre prioritaire (notés, non corrigés)
| Clé | État | Remarque |
|---|---|---|
| `login_forgottenPassword_url`, `url_help_2` (`?p=forgot`) | mort / route absente | ⏭️ **Chantier « récupération mot de passe »** (traité séparément, à ta demande) |
| `recycler_info_link_url` | `…/help/36` (ancien format chemin) | 404 probable — mineur |
| `group_homepage_url`, `bb_link_*`/`sw_link_* highScores` → `/groups/…/id` | pas de feature « groupes » côté site | 404 — mineur, hors priorité |
| `url_logged_out`, `url_logout_timeout`, `url_logout_concurrent` | `…/?p=home` | OK |

## Sécurité staff (vérifiée côté serveur)
`admin/index.php` applique `require_login()` (l.164) **puis** re-vérifie le rang ≥ 5 **en base à
chaque requête** (l.169 : session détruite + redirection sinon). Actions sensibles gardées en plus
(rang 7, l.187/190). → Un accès par **URL directe** est bloqué côté serveur, même avec une session
périmée. ✅

## Tests réellement exécutés (statique / lecture seule)
- Routage V2 lu : `profile`→`home/<pseudo>` (conserve `hotelclient`), `help/club/credits/games/me/home` présents, `?p=` inconnu → **404 propre** (pas de renvoi silencieux à l'accueil).
- Intégrité de `external_texts.txt` après édition : **CP1252 préservé** (aucun ré-encodage), **CRLF intacts** (4649), pas de BOM, accents inchangés, 0 occurrence restante des hôtes morts.
- Protection `admin/` relue (gardiennage serveur).

> ⚠️ Les services étant arrêtés et ne devant **pas** être relancés depuis l'outillage, les
> vérifications **HTTP réelles** et la **substitution `%predefined%` dans le client** restent à
> faire en jeu (liste ci-dessous).

## À vérifier en jeu (Basilisk) — contrôles manuels
1. **Fiche d'un joueur** (clic sur un avatar → « Page perso ») → doit ouvrir **sa Habbo Home** sur le site, URL `…/?p=home/<pseudo>` (et non l'accueil).
2. **Fenêtre d'aide** (bouton « ? » / liens d'aide) → doit ouvrir `…/?p=help` sur le site.
3. **Club Habbo** (infos/achat) → `…/?p=club`.
4. **Crédits** (porte-monnaie « plus d'infos ») → `…/?p=help`.
5. **Modération → « Voir le journal de discussion »** → actuellement **inopérant** (Sulake) : confirmer qu'on le traite en chantier distinct.
6. Vérifier qu'aucun lien d'aide n'ouvre une page **404** (surtout l'ancien `?p=contact`, désormais → aide).
