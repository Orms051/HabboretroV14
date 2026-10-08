# Suivi refonte site Habbo 2007 (v2) — HabboretroV14

Référence : https://mangetoica.com/habbo2007/main.php
Copie de travail : `Server/www/v2/` → http://localhost/v2/ (site actuel `/` intact).
Règles : conserver l'existant · sauvegarde base avant SQL · **ne rien publier sur GitHub** · ressources historiques locales (signaler les manquantes) · pas d'offres/horaires/promesses historiques présentés comme actifs.

Légende statut : ⬜ à faire · 🟨 en cours · ✅ réalisée · 🧪 testée (Chrome+Basilisk, connecté+déconnecté)

## Navigation à reproduire (depuis la référence)
Barre haute (raccourcis) : **Mon Habbo** · **Mes Crédits** · **Habbo Club**
Nav principale : **BIENVENUE** · **NOUVEAU ?** · **COMMUNAUTÉ** · **EVENTS** · **JEUX** · **BOUTIQUE** · **MOBILE** · **CRÉDITS** · **HC** · **AIDE**
Sous-barre : Accueil · À propos

## Pages / rubriques

| # | Page | Route v2 | Statut | Différences restantes |
|---|------|----------|--------|-----------------------|
| 1 | Accueil | `?p=home` | 🟨 | FAIT : sprites d'origine (nav tabs.png, cadres box.png 9-slice, boutons new_button.png), densité 3 colonnes, tous les blocs, données réelles. RESTE : loginbox via sprite d'origine (box_login.png), panneaux déroulants raccourcis, carrousel multi-actus, vrais avatars |
| 2 | Nouveau ? (guide/inscription) | `?p=register` | ⬜ | tout |
| 3 | Communauté | `?p=community` | ⬜ | tout (clans actifs, Homes, staff, recherche) |
| 4 | Events | `?p=events` | ⬜ | tout (programme animations/Infobus configurable) |
| 5 | Jeux | `?p=games` | ⬜ | tout (BattleBall/SnowStorm, classements réels, programme) |
| 6 | Boutique | `?p=shop` | ⬜ | tout |
| 7 | Mobile | `?p=mobile` | ⬜ | tout (bloc historique → état explicite/désactivable) |
| 8 | Crédits | `?p=credits` | ⬜ | tout (solde réel, moyens d'achat → état explicite) |
| 9 | HC / Habbo Club | `?p=club` | ⬜ | tout (avantages, statut réel, pages catalogue) |
| 10 | Aide | `?p=help` | ⬜ | reprendre l'aide déjà faite côté site actuel |
| 11 | Mon Habbo (accueil compte) | `?p=me` | ⬜ | distinct de la Home publique |
| 12 | Profil public joueur | `?p=profile&u=` | ⬜ | avatar réel V14, mission, infos |
| 13 | **Habbo Home (publique)** | `?p=home/<pseudo>` | ⬜ | widgets, stickers, fonds, livre d'or |
| 14 | **Habbo Home (édition)** | `?p=home/edit` | ⬜ | déplacement widgets, z-order, save/annuler, permissions proprio |

## Blocs d'accueil à compléter (réf.)
- ⬜ Raccourcis haut **Mon Habbo / Mes Crédits / Habbo Club** + leurs panneaux déroulants
- 🟨 Carrousel **À NE PAS MANQUER** (plusieurs actus mises en avant — actuellement 1 seule image)
- 🟨 **QUOI DE NEUF ?** (OK, données réelles)
- ⬜ **Besoin d'aide ?** + **Conseils de sécurité** + **Slogan sécu de la semaine** (configurable)
- ⬜ Promo **TRAX** distincte
- ⬜ **Clans les plus actifs** (données réelles si dispo)
- ⬜ **Jeux Habbo** + programme
- ⬜ **Active ton adresse e-mail** (état explicite — pas d'email actif chez nous ?)
- 🟨 **Habbo Homes** (showcase — manque les **vrais avatars**)
- ⬜ Emplacement **Publicité** (ou bloc interne désactivable)
- 🟨 **Battle Ball** top 5 (OK, réel) · 🟨 **Infobus** (horaires à rendre configurables)

## Point dur — AVATAR fidèle V14
Aucun imager local, parts dans les .cct, figures en base = format 25 chiffres. Imager moderne incompatible. → à décider : (a) extraire les parts + imager local, (b) conversion 25ch→format moderne + imager externe, (c) placeholder EXPLICITEMENT marqué en attendant. Ne PAS substituer un dessin moderne en silence.

## Journal
- 2026-10-02 : copie v2 créée, boot partagé, accueil v1, sauvegarde base, inventaire ressources.
- 2026-10-02 (2) : **layout partagé** `inc/layout.php` (barre compte + raccourcis Mon Habbo/Mes Crédits/Habbo Club, bannière/hero, nav orange avec état actif, sous-barre + fil d'Ariane). **Routeur** `index.php` : TOUS les onglets routent (fini le retour silencieux à l'accueil), pages internes en **état « en construction » explicite**, 404 géré, route `home/<pseudo>`. Boutons/boîtes aplatis (moins « modernes »). Avatar Homes marqué **provisoire**. Testé : routes 200 (home/community/games/club/credits/mobile/home-pseudo/404), nav active + fil d'Ariane OK (capture community).
- 2026-10-02 (3) : **sprites d'origine intégrés** (depuis `web-gallery/v2/styles` + `images`). Nav `#navi`+`tabs.png`, cadres `.cb/.bt/.bb/.i1/.i2/.i3`+`box.png`/`borders.png`, boutons `a.new-button`+`new_button.png`. Helpers `box_open/box_close/nbtn` dans layout. Accueil sur une ligne de nav, look 2007 authentique (capture OK). MTC = l'ancien site de Yoann (pas de souci de droits).
- 2026-10-02 (4) : **composition corrigée** d'après la structure réelle de l'ancien site (contenu 928 = principale 740 en masonry 2 colonnes + sidebar 180). Vide supprimé, colonnes équilibrées, blocs agrandis, **mentions techniques retirées** du public (gardées ici). Comparaison ours/réf effectuée. Différences restantes : nav orange à icônes (MTC) vs onglets bleus d'origine (tabs.png) ; loginbox illustrée (perso Habbo) ; carrousel 1-2-3-4 ; avatars réels ; bords boutons new_button perfectibles.
- 2026-10-02 (5) : **nav orange d'origine** intégrée (`#mainmenu`, assets `web-gallery/v2/images/navi/` migrés depuis l'ancien site : barre + tab_left/mid/right/act/end + 10 icônes tab_icon_*). **Boîte de connexion** refaite en panneau d'origine (`top_bar/menu_bg*`, boutons `colorlink` orange, assets `colorlink/` migrés) + champs fonctionnels. Perso **Frank** NON reproduit (perso Habbo/Sulake) : emplacement prêt via `is_file()` sur `web-gallery/v2/images/myhabbo_frank.gif` — s'affiche si Yoann dépose le fichier lui-même. CSS nav/login dans `inc/layout.php`.
- 2026-10-02 (6) : **en-tête d'origine complet** repris. `#topbar` (stripe + 3 raccourcis à icônes Mon Habbo/Mes Crédits/Habbo Club), `#tabmenu` panneau 329px avec **3 contenus distincts** (#myhabbo/#mycredits/#habboclub) + **bascule** `switchTab()` au survol + `#tabmenu-bottom`. État initial = Frank + message + 2 liens (inscription/connexion) ; **« Se connecter » ouvre le formulaire** (`showLogin()`), testé OK. `#habbologo` (logo.gif). **`#enter-hotel` = image native `nav/enterHH_fr.gif` (105×106)**, clic → /client.php (plus de cercle CSS). Assets migrés : top_bar/* (stripes, tabs, icônes), logo.gif, nav/enterHH_fr.gif, colorlink/*, mycredits_coins2.gif. Frank = fichier déposé par Yoann. RESTE : vérifier état connecté (login réel), comparaison zoom identique, Basilisk.
- 2026-10-02 (7) : **en-tête aux dimensions d'origine**. `#top` 928×216, fond `view_fr.gif` (928×154) migré, `background-position:0 37px` **taille native** (plus de cover/stretch). Positions d'origine : #habbologo 35/63, #tabmenu 246/41, #enter-hotel 605/50. Largeur contenu = **928 exacte** (bordures parasites retirées). `render_head($active,$err)` construit tout le chrome (plus de banner_home/banner_slim) ; `tabmenu_html()` pour le panneau. Reste : vérifier état connecté + comparaison 100% + Basilisk.
- 2026-10-02 (8) : **3 priorités corrigées** — (1) bug survol tabmenu : zone unique onglets+panneau, fermeture différée 400ms annulable (`switchTab/holdTab/scheduleClose`), testé (Habbo Club reste au survol du bouton) ; (2) nav orange `margin-top:-33px` chevauche le décor (fini la bande vide) ; (3) boutons `new-button` structure d'origine (`a` relatif + `i` cap absolu, bg `new_button.png` -3,0 / i 0,0) = cadres complets. view_fr/enterHH natifs conservés.
  RESTE : panneaux mycredits/habboclub contenu plus fidèle (illustrations/alignements) ; avatars RÉELS Habbo Homes (imager V14 à décider — placeholder pour l'instant) ; pages intérieures réelles + tous liens locaux ; carrousel 1-2-3-4 ; tests connecté + Basilisk + compo 100%.
- RESTE prioritaire : (1) fidélité pixel accueil (boîtes 9-slice box.png/borders, densité, blocs manquants : raccourcis déroulants, carrousel multi-actus, aide/sécu/slogan, promo Trax, clans, jeux+programme, activation email, pub) ; (2) pages register/community/events/games/shop/credits/club/help/me réelles ; (3) Habbo Home publique + édition ; (4) avatar fidèle V14 (décision imager).
