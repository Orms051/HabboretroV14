<?php
/**
 * commands.docs.php — DOCUMENTATION FRANÇAISE, ÉCRITE À LA MAIN.
 *
 * Ce fichier est séparé de commands.generated.json (données extraites du
 * serveur par gen_commands.php). Le générateur ne touche JAMAIS ce fichier :
 * une régénération ne perd donc aucune documentation.
 *
 * Chaque entrée est indexée par le NOM PRINCIPAL de la commande (= premier
 * alias dans CommandManager). Les permissions NE sont PAS redéfinies ici :
 * elles viennent du JAR (generated.json). On ne documente ici que le sens,
 * les paramètres, conditions, effets, annulation et causes d'échec.
 *
 * Champs par commande :
 *   theme       clé de thème (voir commands_doc_themes())
 *   descFr      description courte en français
 *   params      [ [name, optional(bool), default(?string), desc], ... ]
 *   subcommands [ [syntax, desc], ... ]  (facultatif)
 *   examples    [ "….", … ]
 *   conditions  [ "….", … ]   pré-requis in-game
 *   effects     texte : ce que la commande fait
 *   undo        texte : comment annuler (ou null)
 *   failures    [ "….", … ]   causes d'échec connues / messages du serveur
 *   scope       texte court : sur qui/quoi porte la commande
 *   sensitive   bool : action sensible (à manier avec précaution)
 *   notes       texte libre (facultatif)
 */

function commands_doc_themes(): array {
    return [
        'soi'        => ['👤', 'Soi-même / avatar'],
        'salle'      => ['🏠', 'Salle & meubles'],
        'social'     => ['🥤', 'Social'],
        'infos'      => ['ℹ️', 'Informations'],
        'economie'   => ['💰', 'Crédits, badges & catalogue'],
        'animation'  => ['🎪', 'Animation & modération'],
        'systeme'    => ['🛠️', 'Système & maintenance'],
    ];
}

/** Petites notes FR pour la section « Propositions » (classes non enregistrées). */
function commands_doc_proposals(): array {
    return [
        'GiveItemCommand' => "Classe vide (un simple `// TODO: ;giveitem Alex throne`). Idée d'une commande pour donner un meuble en main à un joueur. Non développée côté serveur.",
        'GiveClubCommand' => "Classe vide. Idée d'une commande pour ajouter des jours de Habbo Club. Non développée côté serveur.",
        'NoAfkCommand'    => "Classe présente et compilée, mais NON enregistrée dans CommandManager (donc inutilisable en jeu). Son code se contente d'afficher « AFK turned off » sans réellement réveiller l'avatar. À ne pas confondre avec `:afk`, qui, lui, fonctionne.",
    ];
}

function commands_docs(): array {
    return [

    // ===== Soi-même / avatar =====================================================
    'sit' => [
        'theme' => 'soi',
        'descFr' => "T'asseoir sur place, là où tu te trouves.",
        'params' => [],
        'examples' => [':sit'],
        'conditions' => ["Être dans une salle.", "Ne pas déjà être assis.", "Ne pas être en train de nager (piscine)."],
        'effects' => "Passe ton avatar en position assise au sol, orienté selon ta direction actuelle.",
        'undo' => "Lève-toi en te déplaçant (clique une case).",
        'failures' => ["Rien ne se passe si tu es déjà assis ou dans l'eau."],
        'scope' => "Toi uniquement, dans la salle courante.",
        'sensitive' => false,
    ],
    'afk' => [
        'theme' => 'soi',
        'descFr' => "Passer « absent » (ton avatar s'endort).",
        'params' => [],
        'examples' => [':afk', ':idle'],
        'conditions' => ["Être dans une salle.", "Être immobile (pas en train de marcher)."],
        'effects' => "Met l'avatar en sommeil (zzz) et lui retire sa boisson éventuelle.",
        'undo' => "Bouge ou parle pour te réveiller.",
        'failures' => ["Ignorée si tu es en déplacement."],
        'scope' => "Toi uniquement.",
        'sensitive' => false,
    ],
    'motto' => [
        'theme' => 'soi',
        'descFr' => "Changer ta mission (le texte sous ton pseudo).",
        'params' => [
            ['texte', true, '(vide)', "Nouvelle mission. Sans texte, la mission est vidée."],
        ],
        'examples' => [':motto Vive Habbo !', ':motto'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Enregistre ta mission et prévient la salle du changement. Le texte est filtré (anti-injection).",
        'undo' => "Refaire `:motto <ancien texte>`.",
        'failures' => ["Aucune (le texte vide est accepté : il efface la mission)."],
        'scope' => "Toi uniquement.",
        'sensitive' => false,
    ],
    'poof' => [
        'theme' => 'soi',
        'descFr' => "Rafraîchir l'affichage de ton avatar (« poof »).",
        'params' => [],
        'examples' => [':poof', ':update'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Recalcule et renvoie l'apparence de ton avatar aux autres joueurs (utile après un changement de look).",
        'undo' => null,
        'failures' => ["Rien si tu n'es pas dans une salle."],
        'scope' => "Toi uniquement.",
        'sensitive' => false,
    ],

    // ===== Social ================================================================
    'givedrink' => [
        'theme' => 'social',
        'descFr' => "Donner TA boisson (ou nourriture) tenue en main à un autre joueur.",
        'params' => [
            ['joueur', false, null, "Pseudo d'un joueur présent dans la MÊME salle que toi."],
        ],
        'examples' => [':givedrink Alex'],
        'conditions' => [
            "Être dans une salle et le joueur cible dans la même salle.",
            "Tu dois toi-même tenir une boisson ou de la nourriture.",
            "La cible ne doit pas dormir, ni déjà avoir une boisson, ni danser.",
        ],
        'effects' => "Transfère l'objet que tu tiens vers le joueur ciblé ; tu ne le tiens plus ensuite.",
        'undo' => null,
        'failures' => [
            "« Could not find user » : cible absente de ta salle.",
            "« You are not carrying any food or drinks » : tu n'as rien en main.",
            "« … is sleeping / already enjoying a drink / is dancing » : état de la cible incompatible.",
        ],
        'scope' => "Un joueur de ta salle.",
        'sensitive' => false,
    ],

    // ===== Salle & meubles =======================================================
    'pickall' => [
        'theme' => 'salle',
        'descFr' => "Ramasser d'un coup tous les meubles de la salle dans ton inventaire.",
        'params' => [],
        'examples' => [':pickall'],
        'conditions' => [
            "Être dans une salle.",
            "⚠ Être PROPRIÉTAIRE de la salle (ou disposer du droit ANY_ROOM_CONTROLLER).",
        ],
        'effects' => "Déplace tous les meubles ramassables vers ton inventaire. Les meubles d'espaces publics et les post-it sont ignorés (comme côté client).",
        'undo' => "Aucune : il faut replacer les meubles à la main depuis l'inventaire.",
        'failures' => [
            "Ne fait rien si tu n'es pas propriétaire de la salle (bien que la commande soit « autorisée » à tous).",
        ],
        'scope' => "Tous les meubles de la salle courante.",
        'sensitive' => true,
        'notes' => "Permission serveur = DEFAULT (tout le monde peut la taper), mais le handler exige la propriété de la salle : c'est la cause n°1 de « ça ne marche pas ».",
    ],
    'rgb' => [
        'theme' => 'salle',
        'descFr' => "Lancer/arrêter un cycle arc-en-ciel sur le gradateur (moodlight) de la salle.",
        'params' => [
            ['secondes', true, '5', "Délai entre deux couleurs, en secondes (minimum 1)."],
        ],
        'examples' => [':rgb', ':rgb 2', ':rainbow 10'],
        'conditions' => [
            "Être dans une salle.",
            "⚠ Être PROPRIÉTAIRE de la salle (ou ANY_ROOM_CONTROLLER).",
            "Un gradateur (moodlight) doit être posé dans la salle.",
        ],
        'effects' => "Démarre le cycle de couleurs ; relancer la commande l'arrête (bascule).",
        'undo' => "Relancer `:rgb` pour arrêter le cycle.",
        'failures' => [
            "« This command requires a moodlight placed » : pas de gradateur dans la salle.",
            "« Please specify the amount of seconds … as a number » : paramètre non numérique.",
            "Ne fait rien si tu n'es pas propriétaire de la salle.",
        ],
        'scope' => "Le gradateur de la salle courante.",
        'sensitive' => false,
    ],
    'coords' => [
        'theme' => 'salle',
        'descFr' => "Afficher tes coordonnées exactes dans la salle.",
        'params' => [],
        'examples' => [':coords'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Ouvre une alerte avec tes X, Y, Z et tes rotations tête/corps.",
        'undo' => null,
        'failures' => ["Rien si tu n'es pas dans une salle."],
        'scope' => "Toi uniquement (lecture seule).",
        'sensitive' => false,
    ],
    'ufos' => [
        'theme' => 'salle',
        'descFr' => "Effet d'animation : fait surgir une nuée d'OVNIs dans la salle, avec une voix.",
        'params' => [],
        'examples' => [':ufos'],
        'conditions' => [
            "Être dans une salle.",
            "⚠ Être propriétaire de la salle OU disposer du droit ANY_ROOM_CONTROLLER.",
        ],
        'effects' => "Affiche un message vocal et fait glisser entre 50 et 94 objets « ovni » aléatoirement dans la salle (effet visuel temporaire).",
        'undo' => "Les objets sont éphémères (non persistés) ; ils disparaissent au rechargement de la salle.",
        'failures' => ["Ne fait rien si tu n'es ni propriétaire ni contrôleur de la salle."],
        'scope' => "Salle courante (effet visuel).",
        'sensitive' => false,
    ],

    // ===== Informations ==========================================================
    'about' => [
        'theme' => 'infos',
        'descFr' => "Afficher les infos de l'émulateur (révision, contributeurs).",
        'params' => [],
        'examples' => [':about', ':info'],
        'conditions' => [],
        'effects' => "Ouvre une alerte avec la version du serveur Kepler et la liste des contributeurs.",
        'undo' => null,
        'failures' => [],
        'scope' => "Lecture seule.",
        'sensitive' => false,
    ],
    'help' => [
        'theme' => 'infos',
        'descFr' => "Lister les commandes disponibles pour TON rang (paginé).",
        'params' => [
            ['page', true, '1', "Numéro de page (10 commandes par page)."],
        ],
        'examples' => [':help', ':help 2', ':commands'],
        'conditions' => [],
        'effects' => "Affiche les commandes auxquelles ton rang a droit, avec leur description. Les paramètres entre &lt; et &gt; y sont notés comme facultatifs.",
        'undo' => null,
        'failures' => ["Une page inexistante retombe sur la page 1."],
        'scope' => "Lecture seule, filtrée selon tes droits.",
        'sensitive' => false,
    ],
    'uptime' => [
        'theme' => 'infos',
        'descFr' => "Afficher la durée de fonctionnement du serveur et des stats.",
        'params' => [],
        'examples' => [':uptime', ':status'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Alerte : temps de fonctionnement, joueurs actifs/authentifiés, pic du jour, mémoire utilisée. (Stats rafraîchies périodiquement.)",
        'undo' => null,
        'failures' => [],
        'scope' => "Lecture seule (serveur).",
        'sensitive' => false,
    ],
    'usersonline' => [
        'theme' => 'infos',
        'descFr' => "Afficher le nombre de joueurs connectés.",
        'params' => [],
        'examples' => [':usersonline', ':whosonline'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Affiche le total de joueurs en ligne.",
        'undo' => null,
        'failures' => [],
        'scope' => "Lecture seule.",
        'sensitive' => false,
    ],
    'chooser' => [
        'theme' => 'infos',
        'descFr' => "Lister les joueurs présents dans la salle (fonction côté client).",
        'params' => [],
        'examples' => [':chooser'],
        'conditions' => ["Nécessite un abonnement Habbo Club (droit USER_LIST_COMMAND)."],
        'effects' => "Déclenche l'affichage client de la liste des joueurs de la salle. Le serveur ne traite rien (commande purement client).",
        'undo' => null,
        'failures' => ["Sans Habbo Club, la fonction n'est pas disponible côté client."],
        'scope' => "Salle courante (affichage client).",
        'sensitive' => false,
        'notes' => "Commande « client-side » : enregistrée pour la liste, mais le comportement est géré par le client, pas par un handler serveur.",
    ],
    'furni' => [
        'theme' => 'infos',
        'descFr' => "Lister les meubles de la salle (fonction côté client).",
        'params' => [],
        'examples' => [':furni'],
        'conditions' => ["Nécessite un abonnement Habbo Club (droit FURNI_LIST_COMMAND)."],
        'effects' => "Déclenche l'affichage client de la liste des meubles. Aucun traitement serveur.",
        'undo' => null,
        'failures' => ["Sans Habbo Club, fonction indisponible côté client."],
        'scope' => "Salle courante (affichage client).",
        'sensitive' => false,
        'notes' => "Commande « client-side ».",
    ],
    'events' => [
        'theme' => 'infos',
        'descFr' => "Afficher les événements organisés par les autres joueurs (côté client).",
        'params' => [],
        'examples' => [':events'],
        'conditions' => [],
        'effects' => "Déclenche l'affichage client des événements en cours. Aucun traitement serveur.",
        'undo' => null,
        'failures' => [],
        'scope' => "Affichage client.",
        'sensitive' => false,
        'notes' => "Commande « client-side ».",
    ],

    // ===== Crédits, badges & catalogue ==========================================
    'givecredits' => [
        'theme' => 'economie',
        'descFr' => "Ajouter des crédits au porte-monnaie d'un joueur connecté.",
        'params' => [
            ['joueur', false, null, "Pseudo exact d'un joueur actuellement EN LIGNE."],
            ['montant', false, null, "Nombre de crédits à ajouter (entier)."],
        ],
        'examples' => [':givecredits Alex 300'],
        'conditions' => ["Être dans une salle.", "Le joueur cible doit être connecté.", "Le montant doit être numérique."],
        'effects' => "Crédite le joueur immédiatement et met à jour son solde à l'écran.",
        'undo' => "Aucune annulation dédiée : refaire la commande avec un montant négatif pour retirer.",
        'failures' => [
            "« Could not find user » : joueur hors ligne ou pseudo erroné.",
            "« Credit amount not provided » : montant manquant.",
            "« Credit amount is not a number » : montant non numérique.",
        ],
        'scope' => "Un joueur en ligne (son compte).",
        'sensitive' => true,
    ],
    'givebadge' => [
        'theme' => 'economie',
        'descFr' => "Attribuer un badge à un joueur connecté.",
        'params' => [
            ['joueur', false, null, "Pseudo exact d'un joueur EN LIGNE."],
            ['badge', false, null, "Code badge : exactement 3 caractères, MAJUSCULES/chiffres."],
        ],
        'examples' => [':givebadge Alex NL1'],
        'conditions' => [
            "Être dans une salle, cible en ligne.",
            "Code de 3 caractères, alphanumérique, en majuscules.",
            "Le joueur ne doit pas déjà posséder ce badge.",
            "Le code ne doit pas être un badge de RANG (sinon il faut changer le rang).",
        ],
        'effects' => "Ajoute le badge, le définit comme badge affiché, prévient la salle et enregistre en base.",
        'undo' => "Aucune commande de retrait : passer par l'admin (gestion des badges du joueur).",
        'failures' => [
            "« … already owns this badge » : badge déjà possédé.",
            "« This badge belongs to a certain rank » : c'est un badge de rang.",
            "« Badge codes have a length of three characters » / « not alphanumeric » / « should be uppercase » : format invalide.",
        ],
        'scope' => "Un joueur en ligne.",
        'sensitive' => true,
    ],
    'setprice' => [
        'theme' => 'economie',
        'descFr' => "Changer le prix d'un article du catalogue (par son code de vente).",
        'params' => [
            ['code_vente', false, null, "Code de vente (sale code) d'un article existant du catalogue."],
            ['prix', false, null, "Nouveau prix, entier."],
        ],
        'examples' => [':setprice sofa_silo 25'],
        'conditions' => ["Le code de vente doit exister.", "Le prix doit être numérique et différent du prix actuel."],
        'effects' => "Met à jour le prix en base et en mémoire ; confirme par un chuchotement (augmenté/diminué).",
        'undo' => "Refaire `:setprice <code> <ancien prix>`.",
        'failures' => [
            "« That sale code doesn't exist » : code inconnu.",
            "« You did not enter a number » : prix non numérique.",
            "« … same price … » : prix identique à l'actuel.",
        ],
        'scope' => "Un article du catalogue (global).",
        'sensitive' => true,
    ],

    // ===== Animation & modération ================================================
    'hotelalert' => [
        'theme' => 'animation',
        'descFr' => "Envoyer une alerte à TOUT l'hôtel (tous les joueurs connectés).",
        'params' => [
            ['message', true, '(vide)', "Texte de l'alerte. Plusieurs mots acceptés. Vide = alerte vide."],
        ],
        'examples' => [':hotelalert Maintenance dans 5 minutes !'],
        'conditions' => [],
        'effects' => "Affiche une alerte à l'écran de tous les joueurs en ligne. Le texte est filtré (anti-injection).",
        'undo' => null,
        'failures' => ["Aucune : un message vide envoie une alerte vide."],
        'scope' => "Tous les joueurs connectés.",
        'sensitive' => true,
    ],
    'talk' => [
        'theme' => 'animation',
        'descFr' => "Faire « parler » la salle via la synthèse vocale (voice-to-text).",
        'params' => [
            ['texte', true, '(vide)', "Phrase à faire dire. Plusieurs mots acceptés."],
        ],
        'examples' => [':talk Bienvenue à tous !'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Crée un objet « haut-parleur » temporaire qui déclenche la lecture vocale du texte dans la salle. Le texte est filtré.",
        'undo' => null,
        'failures' => ["Texte vide = rien de parlé."],
        'scope' => "Salle courante.",
        'sensitive' => false,
    ],
    'infobus' => [
        'theme' => 'animation',
        'descFr' => "Piloter l'Infobus (porte, sondage à options, votes).",
        'params' => [
            ['sous-commande', false, null, "Action à exécuter (voir sous-commandes)."],
        ],
        'subcommands' => [
            [':infobus help', "Affiche l'aide des commandes du bus."],
            [':infobus open', "Ouvre la porte du bus."],
            [':infobus close', "Ferme la porte du bus."],
            [':infobus question <texte>', "Définit la question du sondage."],
            [':infobus option add <texte>', "Ajoute une option de réponse."],
            [':infobus option remove <numéro>', "Retire une option (numéro vu dans :infobus status)."],
            [':infobus status', "Affiche la question, les options et les votes."],
            [':infobus start', "Démarre le sondage (question + options requises)."],
            [':infobus reset', "Réinitialise question, options et votes."],
        ],
        'examples' => [':infobus open', ':infobus question Quel jeu préférez-vous ?', ':infobus option add BattleBall', ':infobus start'],
        'conditions' => ["Être dans une salle (celle du bus pour open/close)."],
        'effects' => "Gère l'état de l'Infobus (porte, question, options, votes) via l'InfobusManager.",
        'undo' => "`:infobus reset` remet le sondage à zéro ; `:infobus close` referme la porte.",
        'failures' => [
            "« You need to set a question » / « You need to add some options » : sondage incomplet au démarrage.",
            "`:infobus option` sans `add`/`remove` renvoie l'usage ; un `option` suivi d'un mot inattendu peut échouer (handler fragile sur args[1]/args[2]).",
        ],
        'scope' => "L'Infobus (salle dédiée).",
        'sensitive' => false,
        'notes' => "Voir aussi l'onglet « Bus (Infobus) » de l'admin.",
    ],

    // ===== Système & maintenance =================================================
    'reload' => [
        'theme' => 'systeme',
        'descFr' => "Recharger à chaud une partie des données du serveur, sans redémarrer.",
        'params' => [
            ['composant', false, null, "Quoi recharger (voir sous-commandes)."],
        ],
        'subcommands' => [
            [':reload catalogue', "Recharge catalogue + définitions de meubles (alias : shop, items). Régénère aussi la collision et renvoie l'index catalogue à tous."],
            [':reload shop', "Identique à catalogue."],
            [':reload items', "Identique à catalogue."],
            [':reload texts', "Recharge les textes du jeu (external_texts)."],
            [':reload models', "Recharge les modèles de salles."],
            [':reload settings', "Recharge la configuration du jeu (alias : config)."],
            [':reload config', "Identique à settings."],
        ],
        'examples' => [':reload catalogue', ':reload texts', ':reload settings'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Relit les données concernées depuis la base/les fichiers et les réapplique à chaud.",
        'undo' => null,
        'failures' => ["Sans composant valide : « You did not specify which component to reload » (liste rappelée)."],
        'scope' => "Serveur (global).",
        'sensitive' => true,
    ],
    'setconfig' => [
        'theme' => 'systeme',
        'descFr' => "Modifier un réglage serveur existant (housekeeping).",
        'params' => [
            ['clé', false, null, "Nom du réglage. Doit déjà exister dans la configuration."],
            ['valeur', false, null, "Nouvelle valeur."],
        ],
        'examples' => [':setconfig shutdown.minutes 5'],
        'conditions' => ["La clé de réglage doit exister."],
        'effects' => "Enregistre la valeur en base et recharge la configuration ; confirme l'ancienne → nouvelle valeur.",
        'undo' => "Refaire `:setconfig <clé> <ancienne valeur>`.",
        'failures' => ["« The setting \"…\" doesn't exist » : clé inconnue."],
        'scope' => "Serveur (global).",
        'sensitive' => true,
    ],
    'packet' => [
        'theme' => 'systeme',
        'descFr' => "Outil de développement : envoie un paquet brut au client (debug protocole).",
        'params' => [
            ['données', true, '(vide)', "Contenu du paquet. Les motifs {0}…{13} sont remplacés par les octets de contrôle correspondants."],
        ],
        'examples' => [':packet BK'],
        'conditions' => ["Réservé au débogage protocole."],
        'effects' => "Envoie la chaîne telle quelle à TON client (suffixe de fin ajouté). Peut désynchroniser ton client.",
        'undo' => null,
        'failures' => ["Un paquet mal formé peut provoquer un comportement erratique du client."],
        'scope' => "Ton client uniquement.",
        'sensitive' => true,
        'notes' => "Outil technique : à n'utiliser qu'en connaissance de cause.",
    ],
    'shutdown' => [
        'theme' => 'systeme',
        'descFr' => "Planifier (ou annuler) l'arrêt de maintenance du serveur.",
        'params' => [
            ['minutes | cancel', true, 'config shutdown.minutes', "Délai en minutes avant l'arrêt. `cancel`, `off` ou `stop` annule un arrêt planifié. Sans argument : valeur par défaut de la config."],
        ],
        'examples' => [':shutdown 10', ':shutdown cancel'],
        'conditions' => ["Être dans une salle."],
        'effects' => "Programme un arrêt de maintenance dans X minutes (PAS immédiat). `cancel`/`off`/`stop` annule l'arrêt programmé.",
        'undo' => "`:shutdown cancel` pour annuler avant l'échéance.",
        'failures' => ["Argument non numérique : repli sur la valeur par défaut (un message le signale)."],
        'scope' => "Serveur entier.",
        'sensitive' => true,
        'notes' => "⚠ Arrête l'hôtel pour tous. NE PAS tester en production.",
    ],

    ];
}
