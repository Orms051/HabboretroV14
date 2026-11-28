<?php
/* =====================================================================
 * admin/inc/looks_engine.php  —  « Apparence » (vêtements & couleurs)
 * ÉTAPE 1 : SOCLE LECTURE SEULE.
 * ---------------------------------------------------------------------
 * Ce moteur LIT la source de vérité des vêtements/couleurs du client :
 *     Server/www/gamedata/figuredata.txt   (téléchargé par le client)
 *     == copie identique de  dcr/14.1_b8/figuredata.txt
 * Il ne modifie RIEN à l'étape 1 (aucune écriture de fichier, aucune
 * écriture SQL). Le sérialiseur n'est présent que pour l'AUTO-TEST de
 * fidélité du format (parse -> serialize -> comparaison à l'original),
 * afin de prouver, avant toute édition (étapes ultérieures), qu'on sait
 * reproduire le format Lingo À L'OCTET PRÈS.
 *
 * Format figuredata.txt (property-list Lingo, compact, SANS espace) :
 *   ["M":[ "ch":[ ["s":210,"p":["rs":"1","ls":"1","ch":"1"],
 *                  "c":["E8B137", ... ] ], ... ],
 *          "hd":[...], "hr":[...], "lg":[...], "sh":[...] ],
 *    "F":[ ... ] ]
 *   - genre M/F  ->  type de partie (hr,hd,ch,lg,sh)  ->  liste de SETS
 *   - un SET = { s:<id 3 chiffres>, p:<couches sprites>, c:[<hex couleurs>] }
 *   - teinte à la volée : l'index couleur (1-based) choisit le hex de "c".
 *   - fin de fichier : "]" suivi de DEUX sauts de ligne LF (\n\n).
 *
 * HC : il n'existe AUCUNE table « vêtements ». Les droits HC se jouent au
 * niveau du SET via deux réglages (table `settings`) :
 *     users.figure.parts.default  = sets accessibles à TOUS
 *     users.figure.parts.club     = sets accessibles aux HC (sur-ensemble)
 * -> un set présent dans club mais pas default = HC exclusif.
 * -> un set absent des deux = inactif (non proposé au vestiaire).
 * Le serveur Kepler ne VALIDE pas la figure enregistrée (gating client).
 * ===================================================================== */

if (defined('LOOKS_ENGINE')) { return; }
define('LOOKS_ENGINE', 1);

/* ---- Chemins (les deux copies à garder synchronisées aux étapes d'écriture) ----
 *  Seam de test INVIOLABLE depuis le web : une CONSTANTE (définissable uniquement
 *  par du code PHP, jamais par une requête HTTP — pas de register_globals, pas
 *  d'extract d'entrée utilisateur). En production elle n'est JAMAIS définie, donc
 *  les chemins LIVE réels sont toujours retournés. Sert aux tests hors-LIVE. */
function figd_paths(): array {
    if (defined('LOOKS_TEST_FIGD_PATHS')) {
        $o = constant('LOOKS_TEST_FIGD_PATHS');
        if (is_array($o) && isset($o['gamedata'], $o['dcr'])) return $o;
    }
    $www = dirname(__DIR__, 2); // .../Server/www
    return [
        'gamedata' => $www . '/gamedata/figuredata.txt',      // lu par le client
        'dcr'      => $www . '/dcr/14.1_b8/figuredata.txt',    // copie (imageur/figure_lib)
    ];
}
const FIGD_TRAILER = "\n\n"; // fin de fichier exacte observée

/* ---- Valeurs HC par DÉFAUT (identiques au GameConfigWriter de kepler.jar).
 *      Utilisées en repli si la table `settings` ne contient pas la clé. ---- */
const FIGD_DEFAULT_PARTS = '100,105,110,115,120,125,130,135,140,145,150,155,160,165,170,175,176,177,178,180,185,190,195,200,205,206,207,210,215,220,225,230,235,240,245,250,255,260,265,266,267,270,275,280,281,285,290,295,300,305,500,505,510,515,520,525,530,535,540,545,550,555,565,570,575,580,585,590,595,596,600,605,610,615,620,625,626,627,630,635,640,645,650,655,660,665,667,669,670,675,680,685,690,695,696,700,705,710,715,720,725,730,735,740';
const FIGD_CLUB_PARTS = '100,105,110,115,120,125,130,135,140,145,150,155,160,165,170,175,176,177,178,180,185,190,195,200,205,206,207,210,215,220,225,230,235,240,245,250,255,260,265,266,267,270,275,280,281,285,290,295,300,305,500,505,510,515,520,525,530,535,540,545,550,555,565,570,575,580,585,590,595,596,600,605,610,615,620,625,626,627,630,635,640,645,650,655,660,665,667,669,670,675,680,685,690,695,696,700,705,710,715,720,725,730,735,740,800,801,802,803,804,805,806,807,808,809,810,811,812,813,814,815,816,817,818,819,820,821,822,823,824,825,826,827,828,829,830,831,832,833,834,835,836,837,838,839,840,841,842,843,844,845,846,847,848,849,850,851,852,853,854,855,856,857,858,859,860,861,862,863,864,865,866,867,868,869,870,871,872,873';

/* ---- Libellés FR des types de parties (ordre d'affichage) ---- */
function figd_type_labels(): array {
    return [
        'hr' => 'Coiffures',
        'hd' => 'Visage / Peau',
        'ch' => 'Hauts',
        'lg' => 'Pantalons',
        'sh' => 'Chaussures',
    ];
}
function figd_type_label(string $t): string { return figd_type_labels()[$t] ?? strtoupper($t); }

/* =====================================================================
 *  PARSEUR — descente récursive fidèle du property-list Lingo.
 *  Les listes/maps sont enveloppées dans ['__list'=>…] / ['__map'=>…]
 *  afin de distinguer liste linéaire et map, et de préserver l'ordre
 *  des clés pour une ré-sérialisation exacte.
 * ===================================================================== */
function figd_parse(string $s) {
    $i = 0; $n = strlen($s);
    $skip = function () use (&$i, $n, $s) {
        while ($i < $n) { $c = $s[$i]; if ($c === ' ' || $c === "\n" || $c === "\r" || $c === "\t") { $i++; } else { break; } }
    };
    $parseString = function () use (&$i, $n, $s) { // s[i] == '"'
        $i++; $buf = '';
        while ($i < $n) { $c = $s[$i]; if ($c === '"') { $i++; return $buf; } $buf .= $c; $i++; }
        throw new RuntimeException('figuredata : chaîne non terminée');
    };
    $parseValue = null;
    $parseValue = function () use (&$i, $n, $s, &$parseValue, $skip, $parseString) {
        $skip();
        if ($i >= $n) throw new RuntimeException('figuredata : fin inattendue');
        $c = $s[$i];
        if ($c === '"') return $parseString();
        if ($c === '[') {
            $i++; $skip();
            if ($i < $n && $s[$i] === ']') { $i++; return ['__list' => []]; }
            $first = $parseValue(); $skip();
            if ($i < $n && $s[$i] === ':') { // MAP
                $i++; $val = $parseValue(); $map = []; $map[(string)$first] = $val; $skip();
                while ($i < $n && $s[$i] === ',') {
                    $i++; $k = $parseValue(); $skip();
                    if ($i >= $n || $s[$i] !== ':') throw new RuntimeException("figuredata : ':' attendu @ $i");
                    $i++; $v = $parseValue(); $map[(string)$k] = $v; $skip();
                }
                if ($i >= $n || $s[$i] !== ']') throw new RuntimeException("figuredata : ']' attendu (map) @ $i");
                $i++; return ['__map' => $map];
            }
            // LISTE linéaire
            $arr = [$first]; $skip();
            while ($i < $n && $s[$i] === ',') { $i++; $arr[] = $parseValue(); $skip(); }
            if ($i >= $n || $s[$i] !== ']') throw new RuntimeException("figuredata : ']' attendu (liste) @ $i");
            $i++; return ['__list' => $arr];
        }
        // NOMBRE
        $start = $i;
        while ($i < $n && strpos('0123456789-.', $s[$i]) !== false) $i++;
        $num = substr($s, $start, $i - $start);
        if ($num === '') throw new RuntimeException("figuredata : token inattendu @ $i : '" . $s[$i] . "'");
        return (strpos($num, '.') !== false) ? (float)$num : (int)$num;
    };
    $skip();
    $root = $parseValue();
    return $root;
}

/* ---- Sérialiseur : reproduit le format compact EXACT (hors trailer). ---- */
function figd_serialize($node): string {
    if (is_array($node)) {
        if (array_key_exists('__list', $node)) {
            $out = '['; $first = true;
            foreach ($node['__list'] as $v) { if (!$first) $out .= ','; $out .= figd_serialize($v); $first = false; }
            return $out . ']';
        }
        if (array_key_exists('__map', $node)) {
            $out = '['; $first = true;
            foreach ($node['__map'] as $k => $v) { if (!$first) $out .= ','; $out .= '"' . $k . '":' . figd_serialize($v); $first = false; }
            return $out . ']';
        }
    }
    if (is_string($node)) return '"' . $node . '"';
    if (is_int($node)) return (string)$node;
    if (is_float($node)) { $r = rtrim(rtrim(sprintf('%.6F', $node), '0'), '.'); return $r === '' ? '0' : $r; }
    return (string)$node;
}

/* ---- Lecture brute d'une des deux copies ('gamedata' par défaut). ---- */
function figd_read_raw(string $which = 'gamedata'): string {
    $p = figd_paths();
    $path = $p[$which] ?? $p['gamedata'];
    $s = @file_get_contents($path);
    if ($s === false) throw new RuntimeException('figuredata introuvable : ' . $path);
    return $s;
}

/* ---- AUTO-TEST : parse -> serialize (+trailer) == original ? ---- */
function figd_roundtrip_ok(string $which = 'gamedata', ?string &$detail = null): bool {
    try {
        $raw = figd_read_raw($which);
        $re  = figd_serialize(figd_parse($raw)) . FIGD_TRAILER;
        if ($re === $raw) { $detail = 'identique (' . strlen($raw) . ' octets)'; return true; }
        // localiser la 1re différence
        $min = min(strlen($raw), strlen($re)); $d = -1;
        for ($k = 0; $k < $min; $k++) { if ($raw[$k] !== $re[$k]) { $d = $k; break; } }
        if ($d < 0) $d = $min;
        $detail = 'DIFFÈRE @ ' . $d . ' (orig ' . strlen($raw) . ' / regénéré ' . strlen($re) . ') : ...'
            . substr($raw, max(0, $d - 20), 40) . ' | ' . substr($re, max(0, $d - 20), 40);
        return false;
    } catch (Throwable $e) { $detail = 'erreur : ' . $e->getMessage(); return false; }
}

/* =====================================================================
 *  DROITS HC — lecture des deux réglages `settings` (repli = défauts).
 *  Retourne ['default'=>int[], 'club'=>int[], 'src'=>'settings'|'defaut'].
 * ===================================================================== */
function figd_parts_csv_to_ids(string $csv): array {
    $out = [];
    foreach (explode(',', $csv) as $tok) { $tok = trim($tok); if ($tok !== '' && ctype_digit($tok)) $out[(int)$tok] = true; }
    return $out; // map id=>true (test rapide)
}
/** CSV BRUTS des deux réglages HC (ordre préservé). Repli = défauts jar. */
function figd_hc_csv(?PDO $pdo): array {
    $def = FIGD_DEFAULT_PARTS; $club = FIGD_CLUB_PARTS; $src = 'defaut';
    if ($pdo) {
        try {
            $st = $pdo->prepare("SELECT setting,value FROM settings WHERE setting IN ('users.figure.parts.default','users.figure.parts.club')");
            $st->execute();
            $got = [];
            foreach ($st as $r) $got[$r['setting']] = (string)$r['value'];
            if (isset($got['users.figure.parts.default']) && $got['users.figure.parts.default'] !== '') { $def = $got['users.figure.parts.default']; $src = 'settings'; }
            if (isset($got['users.figure.parts.club']) && $got['users.figure.parts.club'] !== '') { $club = $got['users.figure.parts.club']; $src = 'settings'; }
        } catch (Throwable $e) { /* DB absente -> repli défauts */ }
    }
    return ['default' => $def, 'club' => $club, 'src' => $src];
}
function figd_hc_lists(?PDO $pdo): array {
    $csv = figd_hc_csv($pdo);
    return ['default' => figd_parts_csv_to_ids($csv['default']), 'club' => figd_parts_csv_to_ids($csv['club']), 'src' => $csv['src']];
}

/* =====================================================================
 *  INVENTAIRE — liste à plat de tous les sets, prêts pour l'affichage.
 *  Chaque entrée :
 *   gender, type, type_label, set_id, colors[], ncolors, sprites{},
 *   available(bool), hc(bool), status('libre'|'hc'|'inactif')
 * ===================================================================== */
function figd_inventory(?PDO $pdo = null): array {
    return figd_inventory_from_tree(figd_parse(figd_read_raw('gamedata')), $pdo);
}
/** Même inventaire, mais à partir d'un arbre déjà parsé (LIVE ou brouillon). */
function figd_inventory_from_tree(array $root, ?PDO $pdo = null, ?array $hc = null): array {
    if (!isset($root['__map'])) throw new RuntimeException('figuredata : racine inattendue');
    if ($hc === null) $hc = figd_hc_lists($pdo);
    $labels = figd_type_labels();
    $rows = [];
    foreach ($root['__map'] as $gender => $gnode) {        // M / F
        if (!isset($gnode['__map'])) continue;
        foreach ($gnode['__map'] as $type => $tnode) {     // ch / hd / hr / lg / sh
            if (!isset($tnode['__list'])) continue;
            foreach ($tnode['__list'] as $set) {
                if (!isset($set['__map'])) continue;
                $m = $set['__map'];
                $sid = isset($m['s']) ? (int)$m['s'] : 0;
                $colors = [];
                if (isset($m['c']['__list'])) foreach ($m['c']['__list'] as $hex) $colors[] = (string)$hex;
                $sprites = [];
                if (isset($m['p']['__map'])) foreach ($m['p']['__map'] as $pk => $pv) $sprites[$pk] = (string)$pv;
                $inDef = isset($hc['default'][$sid]);
                $inClub = isset($hc['club'][$sid]);
                $avail = $inDef || $inClub;
                $isHc = $inClub && !$inDef;
                $rows[] = [
                    'gender'     => (string)$gender,
                    'type'       => (string)$type,
                    'type_label' => $labels[(string)$type] ?? strtoupper((string)$type),
                    'set_id'     => $sid,
                    'colors'     => $colors,
                    'ncolors'    => count($colors),
                    'sprites'    => $sprites,
                    'available'  => $avail,
                    'hc'         => $isHc,
                    'status'     => !$avail ? 'inactif' : ($isHc ? 'hc' : 'libre'),
                ];
            }
        }
    }
    return $rows;
}

/* ---- Synthèse chiffrée pour l'en-tête. ---- */
function figd_stats(array $rows): array {
    $st = ['total' => count($rows), 'M' => 0, 'F' => 0, 'libre' => 0, 'hc' => 0, 'inactif' => 0, 'types' => [], 'colors_distinct' => 0];
    $cols = [];
    foreach ($rows as $r) {
        $st[$r['gender']] = ($st[$r['gender']] ?? 0) + 1;
        $st[$r['status']]++;
        $st['types'][$r['type']] = ($st['types'][$r['type']] ?? 0) + 1;
        foreach ($r['colors'] as $h) $cols[strtoupper($h)] = true;
    }
    $st['colors_distinct'] = count($cols);
    return $st;
}

/* =====================================================================
 *  APERÇU — compose une figure 25 chiffres plaçant UN set dans son slot
 *  (couleur 1) sur un mannequin par défaut, pour l'imageur Minerva
 *  (avatar.php?figure=…&sex=…). Ordre figure : hr,hd,ch,lg,sh (cf. figure_lib).
 *  LECTURE SEULE : ne touche à aucune figure de joueur.
 * ===================================================================== */
function figd_default_figure(string $g): array {
    // type => [set_id, index_couleur(1-based)] — sets « libres » connus, par genre
    return ($g === 'F')
        ? ['hr' => [500, 1], 'hd' => [600, 1], 'ch' => [630, 1], 'lg' => [695, 1], 'sh' => [725, 1]]
        : ['hr' => [100, 1], 'hd' => [180, 1], 'ch' => [210, 1], 'lg' => [270, 1], 'sh' => [290, 1]];
}
function figd_figure_string(array $slots): string {
    $out = '';
    foreach (['hr', 'hd', 'ch', 'lg', 'sh'] as $t) {
        $sid = (int)($slots[$t][0] ?? 0); $ci = (int)($slots[$t][1] ?? 1);
        if ($ci < 1) $ci = 1;
        $out .= sprintf('%03d%02d', $sid, $ci);
    }
    return $out;
}
/** Figure d'aperçu pour une entrée d'inventaire (couleur = index 1). */
function figd_preview_figure(array $row, int $colorIndex = 1): array {
    $slots = figd_default_figure($row['gender']);
    $t = $row['type'];
    if (isset($slots[$t])) $slots[$t] = [$row['set_id'], max(1, $colorIndex)];
    return ['figure' => figd_figure_string($slots), 'sex' => $row['gender']];
}

/* ---- État des deux copies (synchro + intégrité). ---- */
function figd_files_status(): array {
    $p = figd_paths();
    $g = is_file($p['gamedata']) ? md5_file($p['gamedata']) : null;
    $d = is_file($p['dcr']) ? md5_file($p['dcr']) : null;
    return [
        'gamedata' => $p['gamedata'], 'dcr' => $p['dcr'],
        'md5_gamedata' => $g, 'md5_dcr' => $d,
        'exists' => ($g !== null && $d !== null),
        'synced' => ($g !== null && $g === $d),
    ];
}

/* =====================================================================
 *  ÉTAPE 2 — ÉDITION DES COULEURS EN BROUILLON (rien de publié)
 * ---------------------------------------------------------------------
 *  Tout passe par un BROUILLON (table `looks_drafts`, un par admin) qui
 *  contient une COPIE DE TRAVAIL du texte figuredata. Les opérations
 *  (ajout/remplacement/retrait/réordonnancement de couleurs) mutent cette
 *  copie puis la ré-écrivent dans le brouillon. Le fichier figuredata.txt
 *  LIVE n'est JAMAIS touché à cette étape (publication = étape 4).
 *
 *  ⚠️ L'index couleur de la figure 25 chiffres est POSITIONNEL (1-based) :
 *     - « ajouter » (en fin) et « remplacer » (même position) sont SÛRS ;
 *     - « retirer » et « réordonner » DÉCALENT les index -> les tenues des
 *       joueurs qui pointent ces positions changeraient de couleur une fois
 *       publié. On les autorise (demande de Yoann) mais on les SIGNALE.
 * ===================================================================== */

/** Normalise un hex 6 caractères (sans #, majuscules). Null si invalide. */
function figd_norm_hex($raw): ?string {
    $h = strtoupper(trim((string)$raw));
    if ($h !== '' && $h[0] === '#') $h = substr($h, 1);
    return preg_match('/^[0-9A-F]{6}$/', $h) ? $h : null;
}

/** Mute l'arbre parsé : applique une opération couleur sur un set précis. */
function figd_tree_color_op(array &$tree, array $op, ?string &$err = null): bool {
    $g = (string)($op['gender'] ?? ''); $p = (string)($op['part'] ?? ''); $sid = (int)($op['set_id'] ?? 0);
    if (!isset($tree['__map'][$g]['__map'][$p]['__list'])) { $err = 'genre/catégorie introuvable'; return false; }
    $list = &$tree['__map'][$g]['__map'][$p]['__list'];
    $idx = -1;
    foreach ($list as $i => $set) { if (isset($set['__map']['s']) && (int)$set['__map']['s'] === $sid) { $idx = $i; break; } }
    if ($idx < 0) { $err = 'set #' . $sid . ' introuvable'; return false; }
    if (!isset($list[$idx]['__map']['c']['__list'])) { $err = 'set sans liste de couleurs'; return false; }
    $colors = &$list[$idx]['__map']['c']['__list'];
    $n = count($colors);
    switch ((string)($op['type'] ?? '')) {
        case 'add':
            $hex = figd_norm_hex($op['hex'] ?? ''); if ($hex === null) { $err = 'couleur hex invalide'; return false; }
            $colors[] = $hex; break;
        case 'replace':
            $k = (int)($op['index'] ?? 0) - 1; if ($k < 0 || $k >= $n) { $err = 'index hors limites'; return false; }
            $hex = figd_norm_hex($op['hex'] ?? ''); if ($hex === null) { $err = 'couleur hex invalide'; return false; }
            $colors[$k] = $hex; break;
        case 'remove':
            $k = (int)($op['index'] ?? 0) - 1; if ($k < 0 || $k >= $n) { $err = 'index hors limites'; return false; }
            if ($n <= 1) { $err = 'impossible de retirer la dernière couleur'; return false; }
            array_splice($colors, $k, 1); break;
        case 'reorder':
            $order = $op['order'] ?? []; if (!is_array($order) || count($order) !== $n) { $err = 'ordre invalide (taille ' . count($order) . ' ≠ ' . $n . ')'; return false; }
            $seen = []; $new = [];
            foreach ($order as $oi) { $oi = (int)$oi; if ($oi < 1 || $oi > $n || isset($seen[$oi])) { $err = 'ordre invalide (permutation)'; return false; } $seen[$oi] = 1; $new[] = $colors[$oi - 1]; }
            $colors = $new; break;
        default: $err = 'opération inconnue'; return false;
    }
    unset($colors, $list);
    return true;
}

/** Applique une opération sur un TEXTE figuredata, renvoie le nouveau texte (ou null). */
function looks_apply_op_to_text(string $text, array $op, ?string &$err = null): ?string {
    try { $tree = figd_parse($text); } catch (Throwable $e) { $err = 'parse : ' . $e->getMessage(); return null; }
    if (!figd_tree_color_op($tree, $op, $err)) return null;
    return figd_serialize($tree) . FIGD_TRAILER;
}

/* ---------- Couche brouillon (SQL) ---------- */
function looks_ensure_draft_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS looks_drafts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        data LONGTEXT NOT NULL,
        hc_data LONGTEXT NULL,
        note VARCHAR(190) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_admin (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Étape 3 : ajoute hc_data si la table pré-existait sans cette colonne.
    try {
        $has = $pdo->query("SHOW COLUMNS FROM looks_drafts LIKE 'hc_data'")->fetch();
        if (!$has) $pdo->exec("ALTER TABLE looks_drafts ADD COLUMN hc_data LONGTEXT NULL AFTER data");
    } catch (Throwable $e) { /* best effort */ }
}
function looks_draft_current(PDO $pdo, int $adminId): ?array {
    looks_ensure_draft_table($pdo);
    $st = $pdo->prepare('SELECT * FROM looks_drafts WHERE admin_id=?'); $st->execute([$adminId]);
    $r = $st->fetch(); return $r ?: null;
}
function looks_draft_open(PDO $pdo, int $adminId): array {
    $cur = looks_draft_current($pdo, $adminId); if ($cur) return $cur;
    // Correctif A : capture COHÉRENTE (2 passes). Le seed de la copie de travail
    // EST exactement le texte dont l'empreinte devient la baseline (même lecture).
    $cap  = looks_capture_baseline($pdo);
    $seed = $cap['fig_gm_text'];
    if (looks_draft_has_baseline_cols($pdo)) {
        $st = $pdo->prepare('INSERT INTO looks_drafts (admin_id, data, base_fig_gm, base_fig_dcr, base_hc_def, base_hc_club, base_hc_def_pres, base_hc_club_pres, base_captured_at) VALUES (?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)');
        $st->execute([$adminId, $seed, $cap['base_fig_gm'], $cap['base_fig_dcr'], $cap['base_hc_def'], $cap['base_hc_club'], $cap['base_hc_def_pres'], $cap['base_hc_club_pres']]);
    } else { // migration non jouée : brouillon sans baseline (aucune sécurité présumée)
        $st = $pdo->prepare('INSERT INTO looks_drafts (admin_id, data) VALUES (?, ?)'); $st->execute([$adminId, $seed]);
    }
    return looks_draft_current($pdo, $adminId);
}
function looks_draft_discard(PDO $pdo, int $adminId): void {
    looks_ensure_draft_table($pdo);
    $st = $pdo->prepare('DELETE FROM looks_drafts WHERE admin_id=?'); $st->execute([$adminId]);
}
function looks_draft_text(PDO $pdo, int $adminId): ?string {
    $c = looks_draft_current($pdo, $adminId); return $c ? (string)$c['data'] : null;
}
/** Texte « effectif » à afficher : brouillon s'il existe, sinon LIVE. */
function looks_effective_text(PDO $pdo, int $adminId): string {
    $t = looks_draft_text($pdo, $adminId); return $t !== null ? $t : figd_read_raw('gamedata');
}
/** Applique une opération au brouillon (le crée si besoin). */
function looks_draft_apply(PDO $pdo, int $adminId, array $op, ?string &$err = null): bool {
    $d = looks_draft_open($pdo, $adminId);
    $new = looks_apply_op_to_text((string)$d['data'], $op, $err);
    if ($new === null) return false;
    $st = $pdo->prepare('UPDATE looks_drafts SET data=?, updated_at=CURRENT_TIMESTAMP WHERE admin_id=?');
    $st->execute([$new, $adminId]);
    return true;
}

/* ---------- Diff brouillon ↔ LIVE (sets dont les couleurs changent) ---------- */
function looks_draft_diff(PDO $pdo, int $adminId): array {
    $t = looks_draft_text($pdo, $adminId); if ($t === null) return [];
    $live  = figd_inventory_from_tree(figd_parse(figd_read_raw('gamedata')), $pdo);
    $draft = figd_inventory_from_tree(figd_parse($t), $pdo);
    $key = fn($r) => $r['gender'] . '/' . $r['type'] . '/' . $r['set_id'];
    $lm = []; foreach ($live as $r) $lm[$key($r)] = $r;
    $out = [];
    foreach ($draft as $r) {
        $k = $key($r); $lv = $lm[$k] ?? null;
        if ($lv === null || $lv['colors'] !== $r['colors']) {
            $out[] = [
                'gender' => $r['gender'], 'part' => $r['type'], 'type_label' => $r['type_label'],
                'set_id' => $r['set_id'], 'before' => $lv['colors'] ?? [], 'after' => $r['colors'],
            ];
        }
    }
    return $out;
}

/** Récupère une entrée d'inventaire précise depuis un texte (pour la fiche). */
function looks_get_set(string $text, string $gender, string $part, int $setId, ?PDO $pdo = null, ?array $hc = null): ?array {
    foreach (figd_inventory_from_tree(figd_parse($text), $pdo, $hc) as $r) {
        if ($r['gender'] === $gender && $r['type'] === $part && $r['set_id'] === $setId) return $r;
    }
    return null;
}

/* =====================================================================
 *  ÉTAPE 3 — DROITS HC / NON-HC EN BROUILLON (rien de publié)
 * ---------------------------------------------------------------------
 *  Le HC n'est PAS dans figuredata.txt : il vit dans deux réglages
 *  `settings` (users.figure.parts.default = libres ; .club = sur-ensemble
 *  HC). On gère donc un BROUILLON SÉPARÉ, stocké en JSON dans la colonne
 *  looks_drafts.hc_data = {"default":"csv","club":"csv"} (ordre préservé).
 *  Aucune écriture dans `settings` à cette étape (publication = étape 4).
 *
 *  Statut d'un set (par ID) déduit de l'appartenance aux deux listes :
 *     libre   = présent dans default (et club)
 *     hc      = présent dans club MAIS PAS dans default
 *     inactif = absent des deux (non proposé au vestiaire)
 *  ⚠️ Le serveur ne valide pas la figure : passer un set en « inactif »
 *     n'efface AUCUNE tenue déjà portée ; le set n'est simplement plus
 *     proposable au vestiaire. Entièrement réversible (repasser libre/HC).
 * ===================================================================== */

/** CSV -> liste ORDONNÉE d'ids uniques (préserve l'ordre d'apparition). */
function figd_csv_ids_ordered(string $csv): array {
    $out = []; $seen = [];
    foreach (explode(',', $csv) as $t) {
        $t = trim($t);
        if ($t !== '' && ctype_digit($t)) { $v = (int)$t; if (!isset($seen[$v])) { $seen[$v] = 1; $out[] = $v; } }
    }
    return $out;
}
function figd_ids_to_csv(array $ids): string { return implode(',', $ids); }
function figd_csv_add(string $csv, int $id): string { $o = figd_csv_ids_ordered($csv); if (!in_array($id, $o, true)) $o[] = $id; return figd_ids_to_csv($o); }
function figd_csv_del(string $csv, int $id): string { return figd_ids_to_csv(array_values(array_filter(figd_csv_ids_ordered($csv), fn($x) => $x !== $id))); }

/** Statut d'un id d'après deux maps d'appartenance (id=>true). */
function figd_status_from_lists(int $id, array $defMap, array $clubMap): string {
    $inDef = isset($defMap[$id]); $inClub = isset($clubMap[$id]);
    if (!$inDef && !$inClub) return 'inactif';
    return ($inClub && !$inDef) ? 'hc' : 'libre';
}

/** CSV HC du brouillon ({default,club}) ou null si pas de brouillon HC. */
function looks_hc_draft_csv(PDO $pdo, int $adminId): ?array {
    $cur = looks_draft_current($pdo, $adminId);
    if ($cur && isset($cur['hc_data']) && (string)$cur['hc_data'] !== '') {
        $j = json_decode((string)$cur['hc_data'], true);
        if (is_array($j) && isset($j['default'], $j['club'])) return ['default' => (string)$j['default'], 'club' => (string)$j['club']];
    }
    return null;
}
/** CSV HC effectifs : brouillon s'il existe, sinon LIVE (settings/défauts). */
function looks_hc_effective_csv(PDO $pdo, int $adminId): array {
    $d = looks_hc_draft_csv($pdo, $adminId);
    if ($d !== null) return ['default' => $d['default'], 'club' => $d['club'], 'src' => 'brouillon'];
    return figd_hc_csv($pdo);
}
/** Maps d'appartenance effectives (pour override d'inventaire). */
function looks_hc_effective_lists(PDO $pdo, int $adminId): array {
    $csv = looks_hc_effective_csv($pdo, $adminId);
    return ['default' => figd_parts_csv_to_ids($csv['default']), 'club' => figd_parts_csv_to_ids($csv['club']), 'src' => $csv['src']];
}

/** Applique un statut HC ('libre'|'hc'|'inactif') à plusieurs sets dans le
 *  brouillon. Renvoie le nombre de sets traités (0 si statut invalide). */
function looks_hc_apply_bulk(PDO $pdo, int $adminId, array $setIds, string $status, ?string &$err = null): int {
    if (!in_array($status, ['libre', 'hc', 'inactif'], true)) { $err = 'statut inconnu'; return 0; }
    looks_draft_open($pdo, $adminId); // garantit la ligne brouillon (snapshot couleurs inclus)
    $eff = looks_hc_effective_csv($pdo, $adminId);
    $def = $eff['default']; $club = $eff['club']; $cnt = 0;
    foreach ($setIds as $sid) {
        $sid = (int)$sid; if ($sid <= 0) continue;
        if ($status === 'libre')  { $def = figd_csv_add($def, $sid); $club = figd_csv_add($club, $sid); }
        elseif ($status === 'hc') { $def = figd_csv_del($def, $sid); $club = figd_csv_add($club, $sid); }
        else                      { $def = figd_csv_del($def, $sid); $club = figd_csv_del($club, $sid); } // inactif
        $cnt++;
    }
    $json = json_encode(['default' => $def, 'club' => $club]);
    $pdo->prepare('UPDATE looks_drafts SET hc_data=?, updated_at=CURRENT_TIMESTAMP WHERE admin_id=?')->execute([$json, $adminId]);
    return $cnt;
}
/** Applique un statut HC à un seul set. */
function looks_hc_apply(PDO $pdo, int $adminId, int $setId, string $status, ?string &$err = null): bool {
    return looks_hc_apply_bulk($pdo, $adminId, [$setId], $status, $err) === 1;
}

/** Diff HC brouillon ↔ LIVE : sets RÉELS dont le statut change. */
function looks_hc_diff(PDO $pdo, int $adminId): array {
    $d = looks_hc_draft_csv($pdo, $adminId); if ($d === null) return [];
    $live = figd_hc_csv($pdo);
    $lDef = figd_parts_csv_to_ids($live['default']); $lClub = figd_parts_csv_to_ids($live['club']);
    $dDef = figd_parts_csv_to_ids($d['default']);     $dClub = figd_parts_csv_to_ids($d['club']);
    $inv = figd_inventory_from_tree(figd_parse(figd_read_raw('gamedata')), $pdo, ['default' => $lDef, 'club' => $lClub]);
    $out = []; $seen = [];
    foreach ($inv as $r) {
        $sid = $r['set_id']; if (isset($seen[$sid])) continue; $seen[$sid] = 1;
        $a = figd_status_from_lists($sid, $lDef, $lClub);
        $b = figd_status_from_lists($sid, $dDef, $dClub);
        if ($a !== $b) $out[] = ['gender' => $r['gender'], 'part' => $r['type'], 'type_label' => $r['type_label'], 'set_id' => $sid, 'before' => $a, 'after' => $b];
    }
    return $out;
}

/* =====================================================================
 *  ÉTAPE 4 — PUBLICATION SÉCURISÉE + SNAPSHOTS + RESTAURATION
 * ---------------------------------------------------------------------
 *  Publier = appliquer le brouillon au LIVE, UNIQUEMENT sur action admin :
 *    1) PRÉ-VOL : résumé des changements + contrôle de fidélité du format
 *       (le texte du brouillon doit se parser ET se re-sérialiser) +
 *       signalement des décalages d'index (retrait/réordre de couleurs).
 *    2) SNAPSHOT automatique de l'état LIVE (2 copies figuredata + 2
 *       réglages HC, présence comprise) AVANT toute écriture.
 *    3) ÉCRITURE : figuredata.txt (2 copies) seulement si les couleurs ont
 *       changé ; réglages `settings` HC seulement si le HC a changé ;
 *       re-vérification après écriture (re-parse + copies synchro).
 *    4) Le brouillon est consommé (supprimé) une fois publié.
 *  RESTAURATION : réécrit un snapshot, après un snapshot de sécurité
 *  (pre_restore) → entièrement réversible. Rien n'est détruit.
 *
 *  ⚠️ Effet en jeu : les droits HC sont lus par l'émulateur au démarrage
 *     (GameConfigWriter). Après publication d'un changement HC, REDÉMARRER
 *     l'émulateur. Les couleurs (figuredata) sont relues par le client.
 * ===================================================================== */

/** Écrit un texte figuredata sur DEUX copies, avec garde-fous de format.
 *  Fonction PURE (chemins explicites) → testable hors LIVE. */
function looks_write_two_files(string $text, string $pathA, string $pathB, ?string &$err = null): bool {
    // Garde-fou : le texte doit se parser (sinon on n'écrit RIEN).
    try { figd_parse($text); } catch (Throwable $e) { $err = 'texte figuredata invalide : ' . $e->getMessage(); return false; }
    // Garantir le trailer exact (\n\n, LF).
    if (substr($text, -2) !== "\n\n") $text = rtrim($text, "\r\n") . "\n\n";
    foreach ([$pathA, $pathB] as $p) {
        if (@file_put_contents($p, $text) === false) { $err = 'écriture impossible : ' . $p; return false; }
    }
    // Vérifs post-écriture : fichiers présents, copies identiques, re-parse OK.
    if (!is_file($pathA) || !is_file($pathB)) { $err = 'fichier manquant après écriture'; return false; }
    if (md5_file($pathA) !== md5_file($pathB)) { $err = 'copies désynchronisées après écriture'; return false; }
    try { figd_parse((string)file_get_contents($pathA)); } catch (Throwable $e) { $err = 'relecture disque invalide : ' . $e->getMessage(); return false; }
    return true;
}
/** Écrit un texte figuredata sur les 2 copies LIVE. */
function looks_write_figuredata(string $text, ?string &$err = null): bool {
    $p = figd_paths();
    return looks_write_two_files($text, $p['gamedata'], $p['dcr'], $err);
}

/** Upsert d'un réglage `settings` ($val=null → supprime la ligne). */
function looks_write_setting(PDO $pdo, string $key, ?string $val): void {
    if ($val === null) { $pdo->prepare('DELETE FROM settings WHERE setting=?')->execute([$key]); return; }
    $u = $pdo->prepare('UPDATE settings SET value=? WHERE setting=?'); $u->execute([$val, $key]);
    if ($u->rowCount() === 0) { // 0 = valeur inchangée OU ligne absente : on teste l'existence
        $c = $pdo->prepare('SELECT 1 FROM settings WHERE setting=?'); $c->execute([$key]);
        if (!$c->fetchColumn()) $pdo->prepare('INSERT INTO settings (setting,value) VALUES (?,?)')->execute([$key, $val]);
    }
}

/* ---------- Snapshots (table looks_snapshots) ---------- */
function looks_ensure_snapshot_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS looks_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        kind VARCHAR(20) NOT NULL DEFAULT 'pre_publish',
        note VARCHAR(190) NOT NULL DEFAULT '',
        fig_gamedata LONGTEXT NOT NULL,
        fig_dcr LONGTEXT NOT NULL,
        hc_default LONGTEXT NULL,
        hc_club LONGTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** Capture l'état LIVE complet (fichiers + réglages HC, présence comprise).
 *  STRICT (correctif B / fail-closed) : toute lecture impossible lève une
 *  exception au lieu d'enregistrer un état tronqué (jamais pris pour un NULL). */
function looks_snapshot_capture(PDO $pdo, int $adminId, string $kind, string $note = ''): int {
    $p = figd_paths();
    $g  = looks_read_file_strict($p['gamedata']);
    $d  = looks_read_file_strict($p['dcr']);
    $hc = looks_setting_state($pdo, ['users.figure.parts.default', 'users.figure.parts.club']);
    return looks_snapshot_store($pdo, $adminId, $kind, $note, $g, $d, $hc);
}
/** Enregistre un snapshot à partir d'un état DÉJÀ lu (présence HC comprise).
 *  $hcState = ['clé'=>['present'=>bool,'value'=>?string], ...] (cf. looks_setting_state). */
function looks_snapshot_store(PDO $pdo, int $adminId, string $kind, string $note, string $figGm, string $figDcr, array $hcState): int {
    looks_ensure_snapshot_table($pdo);
    $hcDef  = (!empty($hcState['users.figure.parts.default']['present'])) ? (string)$hcState['users.figure.parts.default']['value'] : null;
    $hcClub = (!empty($hcState['users.figure.parts.club']['present']))    ? (string)$hcState['users.figure.parts.club']['value']    : null;
    $ins = $pdo->prepare('INSERT INTO looks_snapshots (admin_id,kind,note,fig_gamedata,fig_dcr,hc_default,hc_club) VALUES (?,?,?,?,?,?,?)');
    $ins->execute([$adminId, $kind, $note, $figGm, $figDcr, $hcDef, $hcClub]);
    return (int)$pdo->lastInsertId();
}
function looks_snapshots_list(PDO $pdo, int $limit = 60): array {
    looks_ensure_snapshot_table($pdo);
    $st = $pdo->query('SELECT id,admin_id,kind,note,created_at,CHAR_LENGTH(fig_gamedata) AS fig_len,hc_default,hc_club FROM looks_snapshots ORDER BY id DESC LIMIT ' . (int)$limit);
    return $st->fetchAll();
}

/* ---------- Pré-vol de publication ---------- */
function looks_publish_preflight(PDO $pdo, int $adminId): array {
    $out = ['has_draft' => false, 'color_changes' => 0, 'hc_changes' => 0, 'roundtrip_ok' => true,
            'risky' => [], 'color_diff' => [], 'hc_diff' => [], 'fig_will_write' => false, 'hc_will_write' => false,
            'stale' => null];
    $draft = looks_draft_current($pdo, $adminId);
    if (!$draft) return $out;
    $out['has_draft'] = true;
    $draftText = (string)$draft['data'];
    $out['fig_will_write'] = ($draftText !== figd_read_raw('gamedata'));
    // Fidélité du format : parse + re-sérialise + re-parse.
    try { $re = figd_serialize(figd_parse($draftText)) . FIGD_TRAILER; figd_parse($re); $out['roundtrip_ok'] = true; }
    catch (Throwable $e) { $out['roundtrip_ok'] = false; }
    // Couleurs
    $cd = looks_draft_diff($pdo, $adminId); $out['color_diff'] = $cd; $out['color_changes'] = count($cd);
    foreach ($cd as $dd) {
        $b = $dd['before']; $a = $dd['after'];
        $sb = $b; $sa = $a; sort($sb); sort($sa);
        $isShift = (count($a) < count($b)) || (count($a) === count($b) && $a !== $b && $sa === $sb);
        if ($isShift) $out['risky'][] = $dd;
    }
    // Accès HC
    $hd = looks_hc_diff($pdo, $adminId); $out['hc_diff'] = $hd; $out['hc_changes'] = count($hd);
    $hcDraft = looks_hc_draft_csv($pdo, $adminId);
    if ($hcDraft !== null) {
        $liveCsv = figd_hc_csv($pdo);
        $sameDef  = figd_parts_csv_to_ids($hcDraft['default']) == figd_parts_csv_to_ids($liveCsv['default']);
        $sameClub = figd_parts_csv_to_ids($hcDraft['club'])    == figd_parts_csv_to_ids($liveCsv['club']);
        $out['hc_will_write'] = !($sameDef && $sameClub);
    }
    // Correctif A — fraîcheur (informatif) : le LIVE a-t-il changé depuis
    // l'ouverture du brouillon ? (la décision d'autorité reste la garde SOUS verrou).
    try { $out['stale'] = looks_draft_stale($pdo, $adminId); }
    catch (\Throwable $e) { $out['stale'] = ['error' => $e->getMessage(), 'changed' => false, 'no_baseline' => false, 'labels' => []]; }
    return $out;
}

/* ---------- Publication (transactionnelle, crash-safe B + garde staleness A) ----------
 *  $force=true : publier MALGRÉ un LIVE modifié depuis l'ouverture du brouillon.
 *  Le forçage lève UNIQUEMENT le refus de staleness ; il ne contourne JAMAIS la
 *  garde finale sous verrou, le snapshot préalable, le contrôle au plus près, le
 *  verrou, la lecture fail-closed, ni l'état CONFLICT. Il exige une confirmation
 *  explicite (côté appelant) et laisse une trace COMPLÈTE dans looks_publish_log. */
function looks_publish(PDO $pdo, int $adminId, bool $force = false, ?string &$err = null): ?array {
    $pf = looks_publish_preflight($pdo, $adminId);
    if (!$pf['has_draft'])                 { $err = 'aucun brouillon à publier'; return null; }
    if (!$pf['roundtrip_ok'])              { $err = 'format du brouillon invalide — publication annulée (sécurité)'; return null; }
    if (!$pf['fig_will_write'] && !$pf['hc_will_write']) { $err = 'le brouillon est identique au LIVE — rien à publier'; return null; }
    $draftText = (string)looks_draft_current($pdo, $adminId)['data'];
    $baseline  = looks_draft_baseline($pdo, $adminId);
    $seen = null; // rempli par la garde (état LIVE EXACT vu sous verrou + diff)
    $plan = [
        'op'            => 'publish',
        'tx_dir'        => looks_tx_dir_default(),
        'paths'         => figd_paths(),
        'fig_text'      => $pf['fig_will_write'] ? $draftText : null,
        'hc_intended'   => null,
        'snapshot_kind' => 'pre_publish',
        'snapshot_note' => 'avant publication (' . $pf['color_changes'] . ' couleur(s), ' . $pf['hc_changes'] . ' accès)' . ($force ? ' [FORCÉ]' : ''),
        'guard'         => looks_staleness_guard($baseline, $force, $seen),
        'after_commit'  => function () use ($pdo, $adminId) { looks_draft_discard($pdo, $adminId); },
    ];
    if ($pf['hc_will_write']) {
        $hc = looks_hc_draft_csv($pdo, $adminId);
        if ($hc !== null) $plan['hc_intended'] = [
            'users.figure.parts.default' => $hc['default'],
            'users.figure.parts.club'    => $hc['club'],
        ];
    }
    $res = looks_tx_execute($pdo, $adminId, $plan, $err);

    // Traçabilité (si la table existe) : journalise TOUTE issue, avec l'état LIVE
    // initial capturé SOUS VERROU par la garde (pas une relecture indépendante).
    $diff    = $seen['diff'] ?? ['changed' => false, 'labels' => [], 'no_baseline' => ($baseline === null || ($baseline['base_fig_gm'] ?? null) === null)];
    $isStale = !empty($diff['changed']);
    $result  = $res !== null ? 'ok'
             : (strpos((string)$err, 'CONFLIT') !== false ? 'conflict'
             : (strpos((string)$err, 'modifié depuis') !== false ? 'refused' : 'error'));
    looks_publish_log_write($pdo, [
        'admin_id'     => $adminId,
        'mode'         => ($force && $isStale) ? 'force' : 'normal',
        'live_fig_gm'  => $seen['md5_gm'] ?? null,
        'live_fig_dcr' => $seen['md5_dcr'] ?? null,
        'base_fig_gm'  => $baseline['base_fig_gm'] ?? null,
        'base_fig_dcr' => $baseline['base_fig_dcr'] ?? null,
        'diff_json'    => json_encode($diff, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'confirmed'    => ($force && $isStale),
        'snapshot_id'  => $res['snapshot_id'] ?? null,
        'result'       => $result,
        'err'          => $res === null ? (string)$err : '',
    ]);

    if ($res === null) return null;
    return [
        'snapshot_id'        => $res['snapshot_id'],
        'figuredata_written' => $res['figuredata_written'],
        'settings_written'   => $res['settings_written'],
        'color_changes'      => $pf['color_changes'],
        'hc_changes'         => $pf['hc_changes'],
        'forced'             => ($force && $isStale),
        'was_stale'          => $isStale,
    ];
}

/* ---------- Restauration d'un snapshot (transactionnelle — correctif B) ----------
 *  En cas d'échec, le rollback revient à l'état JUSTE AVANT la restauration
 *  (fichiers .bak + réglages consignés), PAS au contenu du snapshot cible. */
function looks_snapshot_restore(PDO $pdo, int $adminId, int $snapId, ?string &$err = null): ?array {
    looks_ensure_snapshot_table($pdo);
    $st = $pdo->prepare('SELECT * FROM looks_snapshots WHERE id=?'); $st->execute([$snapId]); $s = $st->fetch();
    if (!$s) { $err = 'snapshot introuvable'; return null; }
    $text = (string)$s['fig_gamedata'];
    if ($text === '') { $err = 'snapshot sans contenu figuredata'; return null; }
    $plan = [
        'op'            => 'restore',
        'tx_dir'        => looks_tx_dir_default(),
        'paths'         => figd_paths(),
        'fig_text'      => $text,
        'hc_intended'   => [
            'users.figure.parts.default' => $s['hc_default'] !== null ? (string)$s['hc_default'] : null,
            'users.figure.parts.club'    => $s['hc_club']    !== null ? (string)$s['hc_club']    : null,
        ],
        'snapshot_kind' => 'pre_restore',
        'snapshot_note' => 'avant restauration du snapshot #' . $snapId,
    ];
    $res = looks_tx_execute($pdo, $adminId, $plan, $err);
    if ($res === null) return null;
    return ['restored' => $snapId, 'safety' => $res['snapshot_id']];
}

/* =====================================================================
 *  ÉTAPE 4-B — TRANSACTION CRASH-SAFE (correctif B)
 * ---------------------------------------------------------------------
 *  Les 2 copies figuredata (fichiers) et les 2 réglages HC (lignes SQL
 *  `settings`) n'ont pas de transaction atomique commune. On encapsule
 *  publication ET restauration dans une transaction maison TOUT-OU-RIEN,
 *  protégée contre les interruptions (PHP/Laragon/Windows) par :
 *    - un VERROU (un seul publish/restore à la fois) ;
 *    - un JOURNAL sur disque (étape atteinte + de quoi défaire) ;
 *    - une REPRISE au chargement de l'admin (réservée au rang 7) ;
 *    - des LECTURES STRICTES (fail-closed) : toute lecture/vérif impossible
 *      fait ÉCHOUER l'opération (jamais interprétée comme NULL / sans-effet) ;
 *    - un NETTOYAGE des .bak UNIQUEMENT après succès complet (2 fichiers +
 *      2 réglages HC vérifiés + snapshot confirmé + transaction terminée).
 *  Les snapshots durables ne sont JAMAIS supprimés (ni commit, ni rollback,
 *  ni reprise). Aucun nettoyage AUTOMATIQUE d'une transaction inachevée.
 * ===================================================================== */

/** Exception interne : simule une interruption BRUTALE (tests de reprise).
 *  Contrairement à une erreur gérée, elle NE déclenche PAS le rollback :
 *  l'état disque (verrou + journal + .bak) reste en place pour la reprise. */
class Looks_TxCrash extends \RuntimeException {}

/* ---------- Lectures strictes (fail-closed) ---------- */
/** Lit un fichier attendu existant ; lève une exception si absent/illisible. */
function looks_read_file_strict(string $path): string {
    if (!is_file($path))     throw new \RuntimeException('fichier introuvable : ' . $path);
    if (!is_readable($path)) throw new \RuntimeException('fichier illisible : ' . $path);
    $c = @file_get_contents($path);
    if ($c === false)        throw new \RuntimeException('lecture impossible : ' . $path);
    return $c;
}
/** État des réglages demandés : [clé=>['present'=>bool,'value'=>?string]].
 *  Distingue l'ABSENCE légitime (requête OK, 0 ligne) d'une ERREUR (exception
 *  SQL) qui est RE-LEVÉE → opération refusée (jamais prise pour un NULL). */
function looks_setting_state(PDO $pdo, array $keys): array {
    $out = [];
    foreach ($keys as $k) $out[$k] = ['present' => false, 'value' => null];
    if (!$keys) return $out;
    $in = implode(',', array_fill(0, count($keys), '?'));
    try {
        $st = $pdo->prepare('SELECT setting,value FROM settings WHERE setting IN (' . $in . ')');
        $st->execute(array_values($keys));
        foreach ($st as $r) {
            $k = $r['setting'];
            if (isset($out[$k])) $out[$k] = ['present' => true, 'value' => (string)$r['value']];
        }
    } catch (\Throwable $e) {
        throw new \RuntimeException('lecture settings impossible : ' . $e->getMessage());
    }
    return $out;
}

/* ---------- Dossier runtime _looks_tx (protégé web + exclu Git) ---------- */
function looks_tx_dir_default(): string {
    // Seam de test inviolable (constante, jamais définie en production).
    if (defined('LOOKS_TEST_TX_DIR')) {
        $d = constant('LOOKS_TEST_TX_DIR');
        if (is_string($d) && $d !== '') { looks_tx_ensure_dir($d); return $d; }
    }
    $dir = __DIR__ . '/_looks_tx';
    looks_tx_ensure_dir($dir);
    return $dir;
}
function looks_tx_ensure_dir(string $dir): void {
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    // Interdit l'accès HTTP (Apache 2.2 ET 2.4).
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht,
        "Order allow,deny\nDeny from all\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n");
    // Garde-fou si .htaccess est ignoré par le serveur.
    $idx = $dir . '/index.php';
    if (!is_file($idx)) @file_put_contents($idx, "<?php http_response_code(403); exit('Forbidden');\n");
    // Exclut le contenu volatil de Git SANS toucher au .gitignore du dépôt.
    $gi = $dir . '/.gitignore';
    if (!is_file($gi)) @file_put_contents($gi,
        "# Runtime transactionnel Apparence — contenu volatil, JAMAIS commité.\n*\n!.gitignore\n!.htaccess\n!index.php\n");
}
function looks_tx_lock_path(string $dir): string    { return $dir . '/tx.lock'; }
function looks_tx_journal_path(string $dir): string { return $dir . '/tx.journal'; }

/** Pose le verrou. Refuse si un verrou existe DÉJÀ (frais OU périmé : un
 *  verrou résiduel => crash probable => à traiter par la REPRISE, jamais
 *  volé automatiquement). */
function looks_tx_lock(string $dir, string $op, int $adminId, ?string &$err = null): ?array {
    looks_tx_ensure_dir($dir);
    $path = looks_tx_lock_path($dir);
    $fh = @fopen($path, 'x'); // 'x' = échoue si le fichier existe déjà (atomique)
    if ($fh === false) { $err = 'une opération Apparence est déjà en cours (ou une reprise est à résoudre)'; return null; }
    fwrite($fh, json_encode(['op' => $op, 'admin_id' => $adminId, 'pid' => getmypid(), 'ts' => time()]));
    fclose($fh);
    return ['path' => $path, 'dir' => $dir];
}
function looks_tx_unlock(?array $handle): void {
    if ($handle && isset($handle['path']) && is_file($handle['path'])) @unlink($handle['path']);
}

/* ---------- Journal ---------- */
function looks_tx_log(string $dir, string $marker, array $data = []): void {
    looks_tx_ensure_dir($dir);
    $line = json_encode(['t' => time(), 'm' => $marker, 'd' => $data], JSON_UNESCAPED_SLASHES);
    @file_put_contents(looks_tx_journal_path($dir), $line . "\n", FILE_APPEND);
}
function looks_tx_journal_read(string $dir): array {
    $path = looks_tx_journal_path($dir);
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false) return [['m' => '__CORRUPT__', 'd' => []]];
    $out = [];
    foreach (preg_split('/\r?\n/', trim($raw)) as $ln) {
        if ($ln === '') continue;
        $j = json_decode($ln, true);
        if (!is_array($j) || !isset($j['m'])) { $out[] = ['m' => '__CORRUPT__', 'd' => []]; continue; }
        $out[] = $j;
    }
    return $out;
}
function looks_tx_journal_clear(string $dir): void {
    $path = looks_tx_journal_path($dir);
    if (is_file($path)) @unlink($path);
}
/** Résumé si une transaction est INACHEVÉE, sinon null (dernier marqueur
 *  ≠ COMMITTED/ROLLED_BACK). Signale une corruption éventuelle du journal. */
function looks_tx_pending(string $dir): ?array {
    $j = looks_tx_journal_read($dir);
    if (!$j) return null;
    $markers = array_map(fn($e) => $e['m'], $j);
    $corrupt = in_array('__CORRUPT__', $markers, true);
    $last = end($markers);
    if (!$corrupt && ($last === 'COMMITTED' || $last === 'ROLLED_BACK')) return null;
    $by = [];
    foreach ($j as $e) { if (($e['m'] ?? '') !== '__CORRUPT__') $by[$e['m']] = $e['d'] ?? []; }
    return [
        'markers'          => $markers,
        'last'             => $last,
        'corrupt'          => $corrupt,
        'conflict'         => in_array('CONFLICT', $markers, true), // correctif A : état bloquant à résoudre manuellement (rang 7)
        'has_files_staged' => in_array('FILES_STAGED', $markers, true),
        'has_verified'     => in_array('VERIFIED', $markers, true),
        'data'             => $by,
        'op'               => $by['BEGIN']['op'] ?? '?',
    ];
}

/* ---------- Staging des fichiers (remplacement contrôlé + .bak) ---------- */
function looks_files__undo_partial(array $done): void {
    foreach ($done as $it) {
        if (is_file($it['bak'])) { @unlink($it['path']); @copy($it['bak'], $it['path']); @unlink($it['bak']); }
    }
}
/** Écrit $text sur chaque chemin via temporaire + vérif + remplacement, en
 *  conservant un .bak par cible. NE supprime PAS les .bak (commit plus tard).
 *  Renvoie ['items'=>[['path','tmp','bak'],...]] ou null (rien remplacé).
 *
 *  Correctif A — $expect (optionnel) : map [chemin => md5 attendu sur disque].
 *  Juste AVANT la phase de remplacement, on re-contrôle que chaque cible
 *  contient encore EXACTEMENT ce qu'on avait capturé (TOUT-OU-RIEN : si une
 *  seule cible a divergé, on ne remplace RIEN et on signale $conflict=true).
 *  Honnêteté : une fenêtre TOCTOU résiduelle subsiste entre ce contrôle et le
 *  rename (cf. règle opérationnelle) ; ce n'est pas une garantie absolue contre
 *  un écrivain externe non coopératif. */
function looks_files_stage(string $text, array $paths, ?string &$err = null, ?array $expect = null, ?bool &$conflict = null): ?array {
    $conflict = false;
    try { figd_parse($text); } catch (\Throwable $e) { $err = 'texte figuredata invalide : ' . $e->getMessage(); return null; }
    if (substr($text, -2) !== "\n\n") $text = rtrim($text, "\r\n") . "\n\n";
    $items = []; $tmps = [];
    foreach ($paths as $p) {
        $tmp = $p . '.tmp' . getmypid() . mt_rand(1000, 9999);
        if (@file_put_contents($tmp, $text) === false) { $err = 'écriture temporaire impossible : ' . $tmp; foreach ($tmps as $t) @unlink($t); return null; }
        try { figd_parse(looks_read_file_strict($tmp)); } catch (\Throwable $e) { $err = 'temporaire invalide : ' . $e->getMessage(); @unlink($tmp); foreach ($tmps as $t) @unlink($t); return null; }
        $items[] = ['path' => $p, 'tmp' => $tmp, 'bak' => $p . '.bak']; $tmps[] = $tmp;
    }
    if (count($items) === 2 && md5_file($items[0]['tmp']) !== md5_file($items[1]['tmp'])) {
        $err = 'copies temporaires désynchronisées'; foreach ($tmps as $t) @unlink($t); return null;
    }
    // Contrôle au plus près (correctif A) : la cible n'a pas changé depuis la capture.
    if ($expect !== null) {
        foreach ($items as $it) {
            if (!isset($expect[$it['path']])) continue;
            $cur = is_file($it['path']) ? md5_file($it['path']) : '__absent__';
            if ($cur !== (string)$expect[$it['path']]) {
                $conflict = true;
                $err = 'modification externe détectée avant écriture (' . $it['path'] . ') — remplacement annulé (aucune cible modifiée)';
                foreach ($tmps as $t) @unlink($t);
                return null;
            }
        }
    }
    $done = [];
    foreach ($items as $it) {
        if (is_file($it['path'])) {
            if (!@copy($it['path'], $it['bak'])) { $err = 'sauvegarde .bak impossible : ' . $it['bak']; looks_files__undo_partial($done); foreach ($tmps as $t) @unlink($t); return null; }
        }
        @unlink($it['path']);
        if (!@rename($it['tmp'], $it['path'])) {
            $err = 'remplacement impossible : ' . $it['path'];
            if (is_file($it['bak'])) { @copy($it['bak'], $it['path']); @unlink($it['bak']); }
            looks_files__undo_partial($done);
            foreach ($tmps as $t) @unlink($t);
            return null;
        }
        $done[] = $it;
    }
    return ['items' => $items];
}
function looks_files_commit(array $staged): void {
    foreach ($staged['items'] as $it) { if (is_file($it['bak'])) @unlink($it['bak']); if (is_file($it['tmp'])) @unlink($it['tmp']); }
}
function looks_files_rollback(array $staged): bool {
    $ok = true;
    foreach ($staged['items'] as $it) {
        if (is_file($it['bak'])) { @unlink($it['path']); if (!@copy($it['bak'], $it['path'])) $ok = false; @unlink($it['bak']); }
        if (is_file($it['tmp'])) @unlink($it['tmp']);
    }
    return $ok;
}
/** Correctif A — rollback fichiers CONDITIONNEL : ne restaure JAMAIS un fichier
 *  qui a divergé de CE QU'ON AVAIT ÉCRIT ($wroteMd5) — signe d'une modification
 *  externe pendant la transaction. TOUT-OU-RIEN :
 *    - toutes les cibles == $wroteMd5          -> on restaure toutes depuis .bak ;
 *    - au moins une cible divergente           -> on ne restaure AUCUNE cible,
 *      on conserve une copie « .conflict » du contenu externe et on garde les
 *      .bak, puis $conflict=true (résolution manuelle requise).
 *  Honnêteté : une fenêtre TOCTOU résiduelle demeure entre ce contrôle et
 *  l'écriture (cf. règle opérationnelle) ; si malgré tout une erreur d'E/S
 *  interrompt la restauration déjà entamée, le résultat est renvoyé par cible
 *  et JAMAIS présenté comme « rollback complet ».
 *  Renvoie ['restored'=>int,'conflict'=>bool,'items'=>[ ['path','status'] ]]. */
function looks_files_rollback_conditional(array $staged, string $wroteMd5, ?bool &$conflict = null): array {
    $conflict = false;
    $items = $staged['items'] ?? [];
    // 1) Phase d'évaluation : aucune cible divergente ?
    $status = []; $anyDiverged = false;
    foreach ($items as $it) {
        $cur = is_file($it['path']) ? md5_file($it['path']) : '__absent__';
        if ($cur === $wroteMd5) { $status[$it['path']] = 'restorable'; }
        else { $status[$it['path']] = 'conflict'; $anyDiverged = true; }
    }
    if ($anyDiverged) {
        // Conserve le contenu externe (.conflict) + garde les .bak. N'écrase RIEN.
        foreach ($items as $it) {
            if (($status[$it['path']] ?? '') === 'conflict' && is_file($it['path'])) {
                @copy($it['path'], $it['path'] . '.conflict');
            }
            if (!empty($it['tmp']) && is_file($it['tmp'])) @unlink($it['tmp']);
        }
        $conflict = true;
        return ['restored' => 0, 'conflict' => true, 'items' => array_map(fn($p, $s) => ['path' => $p, 'status' => $s], array_keys($status), array_values($status))];
    }
    // 2) Phase d'action : restauration de toutes les cibles depuis .bak.
    $restored = 0; $out = [];
    foreach ($items as $it) {
        $st = 'restored';
        if (is_file($it['bak'])) {
            @unlink($it['path']);
            if (@copy($it['bak'], $it['path'])) { $restored++; @unlink($it['bak']); }
            else { $st = 'io_error'; } // E/S : ne pas marquer « complet »
        } else { $st = 'no_bak'; }
        if (!empty($it['tmp']) && is_file($it['tmp'])) @unlink($it['tmp']);
        $out[] = ['path' => $it['path'], 'status' => $st];
    }
    // Si une E/S a empêché une restauration, c'est un conflit partiel (pas complet).
    foreach ($out as $o) if ($o['status'] !== 'restored') $conflict = true;
    return ['restored' => $restored, 'conflict' => $conflict, 'items' => $out];
}

/* ---------- Réglages HC (apply / restore avec présence) ---------- */
/** Applique les réglages voulus : [clé=>?string] (null => supprime la ligne).
 *  Correctif A — la paire HC est écrite dans UNE transaction SQL (atomique :
 *  tout ou rien). En cas d'erreur sur une clé, la transaction est annulée
 *  (ROLLBACK) → aucune écriture HC partielle, l'exception est re-levée. */
function looks_settings_apply(PDO $pdo, array $intended): void {
    $own = false;
    try {
        if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $own = true; }
        foreach ($intended as $k => $v) looks_write_setting($pdo, $k, $v === null ? null : (string)$v);
        if ($own) $pdo->commit();
    } catch (\Throwable $e) {
        if ($own && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (\Throwable $e2) { /* best effort */ } }
        throw $e;
    }
}
/** Restaure l'état précédent : présent => upsert valeur ; absent => suppression.
 *  INCONDITIONNEL (ne vérifie pas l'état courant) — réservé aux cas sans
 *  mutation externe possible. Pour un rollback sûr, préférer la version
 *  conditionnelle (correctif A). */
function looks_settings_restore(PDO $pdo, array $prevState): void {
    $own = false;
    try {
        if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $own = true; }
        foreach ($prevState as $k => $st) {
            looks_write_setting($pdo, $k, !empty($st['present']) ? (string)$st['value'] : null);
        }
        if ($own) $pdo->commit();
    } catch (\Throwable $e) {
        if ($own && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (\Throwable $e2) {} }
        throw $e;
    }
}

/** Correctif A — RESTAURE conditionnellement l'état précédent des réglages HC,
 *  dans UNE transaction SQL, SANS jamais écraser une modification externe.
 *  Pour chaque clé : SELECT ... FOR UPDATE (verrou de ligne), on compare l'état
 *  courant à CE QU'ON AVAIT ÉCRIT ($wroteState). TOUT-OU-RIEN :
 *    - toutes les clés conformes à $wroteState  -> on restaure $prevState, COMMIT ;
 *    - au moins une clé divergente (modif externe) -> ROLLBACK, $conflict=true,
 *      AUCUNE écriture (la valeur externe n'est jamais écrasée).
 *  $wroteState / $prevState : [clé => ['present'=>bool,'value'=>?string]].
 *  rowCount() n'est PAS utilisé pour décider : la décision vient du SELECT
 *  FOR UPDATE + comparaison (présence réelle de la ligne comprise). */
function looks_settings_restore_conditional(PDO $pdo, array $prevState, array $wroteState, ?bool &$conflict = null): bool {
    $conflict = false;
    $keys = array_keys($wroteState);
    if (!$keys) return true;
    $own = false;
    try {
        if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $own = true; }
        // 1) Phase d'évaluation : lecture verrouillée + comparaison (aucune écriture).
        $diverged = [];
        foreach ($keys as $k) {
            $st = $pdo->prepare('SELECT value FROM settings WHERE setting=? FOR UPDATE');
            $st->execute([$k]);
            $row = $st->fetch();
            $curPresent = ($row !== false);
            $curValue   = $curPresent ? (string)$row['value'] : null;
            $w = $wroteState[$k] ?? ['present' => false, 'value' => null];
            $wPresent = !empty($w['present']);
            $wValue   = $wPresent ? (string)$w['value'] : null;
            if ($curPresent !== $wPresent || ($curPresent && $curValue !== $wValue)) {
                $diverged[$k] = ['present' => $curPresent, 'value' => $curValue];
            }
        }
        if ($diverged) { // conflit externe : on n'écrase RIEN
            if ($own && $pdo->inTransaction()) $pdo->rollBack();
            $conflict = true;
            return false;
        }
        // 2) Phase d'action : restauration de l'état précédent (valeur ou suppression).
        foreach ($prevState as $k => $ps) {
            looks_write_setting($pdo, $k, !empty($ps['present']) ? (string)$ps['value'] : null);
        }
        if ($own) $pdo->commit();
        return true;
    } catch (\Throwable $e) {
        if ($own && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (\Throwable $e2) {} }
        throw $e;
    }
}

/* ---------- Vérification finale (fail-closed) ---------- */
function looks_tx_verify(PDO $pdo, array $plan, ?array $staged): void {
    if (!empty($plan['__verify_fail'])) throw new \RuntimeException('vérification simulée en échec');
    if ($staged) {
        $md5 = [];
        foreach ($staged['items'] as $it) {
            $disk = looks_read_file_strict($it['path']); // strict : illisible => exception
            figd_parse($disk);                            // doit se re-parser
            $md5[] = md5($disk);
        }
        if (count($md5) === 2 && $md5[0] !== $md5[1]) throw new \RuntimeException('copies désynchronisées après écriture');
    }
    if (($plan['hc_intended'] ?? null) !== null) {
        $keys  = array_keys($plan['hc_intended']);
        $state = looks_setting_state($pdo, $keys); // strict
        foreach ($plan['hc_intended'] as $k => $want) {
            $cur = $state[$k] ?? ['present' => false, 'value' => null];
            if ($want === null) {
                if (!empty($cur['present'])) throw new \RuntimeException('réglage ' . $k . ' aurait dû être supprimé');
            } else {
                if (empty($cur['present']) || (string)$cur['value'] !== (string)$want) throw new \RuntimeException('réglage ' . $k . ' non conforme après écriture');
            }
        }
    }
}

/** Point d'injection de panne pour les tests (sinon no-op). */
function looks_tx__fault(array $plan, string $point): void {
    if (($plan['__fault'] ?? null) === $point) throw new Looks_TxCrash('panne simulée @ ' . $point);
}

/* ---------- Orchestrateur transactionnel ---------- */
function looks_tx_execute(PDO $pdo, int $adminId, array $plan, ?string &$err = null): ?array {
    $dir = $plan['tx_dir'];
    looks_tx_ensure_dir($dir);
    if (looks_tx_pending($dir) !== null) { $err = 'une transaction Apparence inachevée doit d\'abord être résolue (reprise)'; return null; }
    $lock = looks_tx_lock($dir, $plan['op'], $adminId, $err);
    if ($lock === null) return null;

    $staged = null; $hcPrev = null; $snapId = null; $hcWritten = false; $fileWroteMd5 = null;
    try {
        looks_tx_journal_clear($dir);
        looks_tx_log($dir, 'BEGIN', ['op' => $plan['op'], 'admin' => $adminId]);

        // 1) Capture de l'état précédent (strict), SOUS VERROU, en une seule fois.
        $keys   = ['users.figure.parts.default', 'users.figure.parts.club'];
        $hcPrev = looks_setting_state($pdo, $keys);
        $figGm  = looks_read_file_strict($plan['paths']['gamedata']);
        $figDcr = looks_read_file_strict($plan['paths']['dcr']);
        $md5Gm  = md5($figGm);
        $md5Dcr = md5($figDcr);
        looks_tx_log($dir, 'STATE_CAPTURED', ['hc_prev' => $hcPrev, 'paths' => $plan['paths'], 'md5_gm' => $md5Gm, 'md5_dcr' => $md5Dcr]);

        // 1-bis) GARDE (correctif A) : exécutée SOUS LE MÊME VERROU, sur l'état
        //   EXACT qui vient d'être capturé (aucune relecture indépendante). Si
        //   elle refuse (LIVE périmé, etc.) : abandon PROPRE, AVANT tout snapshot
        //   et toute écriture (rien de mutable n'a été touché).
        if (isset($plan['guard']) && is_callable($plan['guard'])) {
            $gErr = ($plan['guard'])($pdo, ['md5_gm' => $md5Gm, 'md5_dcr' => $md5Dcr, 'fig_gm' => $figGm, 'fig_dcr' => $figDcr, 'hc_prev' => $hcPrev]);
            if ($gErr !== null) {
                looks_tx_log($dir, 'ROLLED_BACK', ['reason' => 'guard:' . $gErr]);
                looks_tx_journal_clear($dir); looks_tx_unlock($lock);
                $err = $gErr; return null;
            }
        }

        // 2) Snapshot durable AVANT écriture, depuis l'état capturé validé par la garde.
        $snapId = looks_snapshot_store($pdo, $adminId, $plan['snapshot_kind'], $plan['snapshot_note'], $figGm, $figDcr, $hcPrev);
        looks_tx_log($dir, 'SNAPSHOT_OK', ['snap_id' => $snapId]);
        looks_tx__fault($plan, 'after_snapshot');

        // 3) Fichiers (staging) si figuredata à écrire — avec contrôle au plus
        //    près (expect = empreintes capturées), TOUT-OU-RIEN.
        if (($plan['fig_text'] ?? null) !== null) {
            $txt = $plan['fig_text'];
            $fileWroteMd5 = md5(substr($txt, -2) === "\n\n" ? $txt : rtrim($txt, "\r\n") . "\n\n");
            $expect = [$plan['paths']['gamedata'] => $md5Gm, $plan['paths']['dcr'] => $md5Dcr];
            $stageConflict = false;
            $staged = looks_files_stage($txt, [$plan['paths']['gamedata'], $plan['paths']['dcr']], $err, $expect, $stageConflict);
            if ($staged === null) { // échec AVANT tout remplacement => rien de mutable touché
                looks_tx_log($dir, 'ROLLED_BACK', ['reason' => ($stageConflict ? 'conflit externe pré-écriture:' : 'stage:') . $err]);
                looks_tx_journal_clear($dir); looks_tx_unlock($lock);
                return null; // refus propre (aucune cible modifiée) — PAS un état CONFLICT
            }
            looks_tx_log($dir, 'FILES_STAGED', ['items' => $staged['items'], 'wrote_md5' => $fileWroteMd5]);
        }
        looks_tx__fault($plan, 'after_files_staged');

        // 4) Réglages HC — paire écrite dans une transaction SQL atomique.
        if (($plan['hc_intended'] ?? null) !== null) {
            looks_tx__fault($plan, 'before_settings');
            looks_settings_apply($pdo, $plan['hc_intended']);
            $hcWritten = true;
            looks_tx_log($dir, 'SETTINGS_APPLIED', ['intended' => $plan['hc_intended']]);
        }
        looks_tx__fault($plan, 'after_settings');

        // 4) Vérification finale (fail-closed) : 2 fichiers + 2 réglages HC.
        looks_tx_verify($pdo, $plan, $staged);
        looks_tx_log($dir, 'VERIFIED', []);
        looks_tx__fault($plan, 'before_commit');

        // 5) COMMIT : suppression des .bak UNIQUEMENT ici (succès complet
        //    confirmé : fichiers + HC vérifiés + snapshot + VERIFIED journalisé).
        if ($staged) looks_files_commit($staged);
        looks_tx_log($dir, 'COMMITTED', []);
        looks_tx_journal_clear($dir);
        looks_tx_unlock($lock);

        if (!empty($plan['after_commit']) && is_callable($plan['after_commit'])) ($plan['after_commit'])();

        return ['snapshot_id' => $snapId, 'figuredata_written' => ($staged !== null), 'settings_written' => $hcWritten];

    } catch (Looks_TxCrash $c) {
        // Interruption BRUTALE simulée : on LAISSE verrou + journal + .bak en
        // place (comme un vrai crash) → la REPRISE s'en chargera. PAS de rollback.
        throw $c;
    } catch (\Throwable $e) {
        // Erreur gérée : rollback CONDITIONNEL (correctif A) — ne JAMAIS écraser
        // une modification externe survenue pendant la transaction.
        $fileConflict = false; $hcConflict = false; $fileRes = null;
        if ($staged && $fileWroteMd5 !== null) {
            $fileRes = looks_files_rollback_conditional($staged, $fileWroteMd5, $fileConflict);
        } elseif ($staged) {
            if (!looks_files_rollback($staged)) $fileConflict = true;
        }
        if ($hcWritten && ($plan['hc_intended'] ?? null) !== null) {
            $wroteState = [];
            foreach ($plan['hc_intended'] as $k => $v) $wroteState[$k] = ['present' => $v !== null, 'value' => $v];
            try { looks_settings_restore_conditional($pdo, $hcPrev, $wroteState, $hcConflict); }
            catch (\Throwable $e2) { $hcConflict = true; }
        }
        if ($fileConflict || $hcConflict) {
            // ÉTAT CONFLICT : conservation du journal, des .bak et des .conflict ;
            // verrou maintenu ; AUCUNE finalisation ni suppression automatique.
            // Bloque toute nouvelle publication/restauration/reprise jusqu'à
            // résolution manuelle explicite (rang 7). Jamais présenté « complet ».
            looks_tx_log($dir, 'CONFLICT', [
                'reason'        => $e->getMessage(),
                'file_conflict' => $fileConflict,
                'hc_conflict'   => $hcConflict,
                'file_items'    => $fileRes['items'] ?? [],
            ]);
            // NE PAS vider le journal ; NE PAS libérer le verrou.
            $err = 'CONFLIT externe pendant le rollback — rollback INCOMPLET, résolution manuelle requise (rang 7). Détail : ' . $e->getMessage();
            return null;
        }
        // Rollback propre (aucune divergence externe) : état précédent rétabli.
        looks_tx_log($dir, 'ROLLED_BACK', ['reason' => $e->getMessage()]);
        looks_tx_journal_clear($dir);
        looks_tx_unlock($lock);
        $err = $e->getMessage();
        return null;
    }
}

/* ---------- Reprise (rang 7 — vérifie l'intégrité AVANT toute action) ----------
 *  Aucune reprise n'agit sans contrôler le journal ET les sauvegardes. */
/** Restaure l'état pré-transaction depuis les .bak + réglages consignés. */
function looks_tx_recover_rollback(PDO $pdo, string $dir, ?string &$err = null): ?array {
    $p = looks_tx_pending($dir);
    if ($p === null)   { $err = 'aucune transaction inachevée à restaurer'; return null; }
    if (!empty($p['conflict'])) { $err = 'conflit externe non résolu — la reprise est bloquée. Résolution manuelle requise (rang 7).'; return null; }
    if ($p['corrupt']) { $err = 'journal de transaction corrompu — restauration automatique refusée (vérification manuelle requise)'; return null; }
    $data = $p['data'];
    if (!isset($data['STATE_CAPTURED'])) { $err = 'journal incomplet (état précédent absent) — restauration refusée'; return null; }
    $hcPrev = $data['STATE_CAPTURED']['hc_prev'] ?? null;
    $hcIntended = $data['SETTINGS_APPLIED']['intended'] ?? null; // HC réellement écrit ?

    // Restauration HC CONDITIONNELLE (ne jamais écraser une modif externe).
    $restoreHc = function () use ($pdo, $hcPrev, $hcIntended, &$err): ?bool {
        if ($hcIntended === null || $hcPrev === null) return true; // HC non écrit : rien à restaurer
        $wroteState = [];
        foreach ($hcIntended as $k => $v) $wroteState[$k] = ['present' => $v !== null, 'value' => $v];
        $conf = false;
        try { looks_settings_restore_conditional($pdo, $hcPrev, $wroteState, $conf); }
        catch (\Throwable $e) { $err = 'restauration HC impossible : ' . $e->getMessage(); return null; }
        return $conf ? false : true; // false = conflit
    };

    // Aucun fichier remplacé => rien de mutable n'a été touché côté fichiers.
    if (!$p['has_files_staged']) {
        $hcOk = $restoreHc();
        if ($hcOk === null) return null;
        if ($hcOk === false) {
            looks_tx_log($dir, 'CONFLICT', ['reason' => 'reprise : réglage HC modifié à l\'extérieur', 'hc_conflict' => true]);
            $err = 'CONFLIT : un réglage HC a été modifié à l\'extérieur — reprise bloquée, résolution manuelle requise (rang 7).';
            return null;
        }
        looks_tx_log($dir, 'ROLLED_BACK', ['reason' => 'reprise (aucun fichier remplacé)']);
        looks_tx_journal_clear($dir);
        looks_tx_unlock(['path' => looks_tx_lock_path($dir)]);
        return ['mode' => 'rollback', 'files' => 0, 'note' => 'aucun fichier à restaurer'];
    }

    // Intégrité des sauvegardes AVANT d'agir (fail-closed).
    $items = $data['FILES_STAGED']['items'] ?? [];
    $wroteMd5 = $data['FILES_STAGED']['wrote_md5'] ?? null;
    if (!$items) { $err = 'journal incomplet (sauvegardes absentes) — restauration refusée'; return null; }
    foreach ($items as $it) {
        if (empty($it['bak']) || !is_file($it['bak']) || !is_readable($it['bak'])) { $err = 'sauvegarde .bak manquante ou illisible (' . ($it['bak'] ?? '?') . ') — restauration refusée (sécurité)'; return null; }
        try { figd_parse(looks_read_file_strict($it['bak'])); } catch (\Throwable $e) { $err = 'sauvegarde .bak corrompue (' . $it['bak'] . ') — restauration refusée (sécurité)'; return null; }
    }
    // Rollback fichiers CONDITIONNEL : aucune cible modifiée à l'extérieur
    // (comparaison à CE QU'ON AVAIT ÉCRIT). TOUT-OU-RIEN.
    $fileConflict = false;
    if ($wroteMd5 !== null) {
        $res = looks_files_rollback_conditional(['items' => $items], $wroteMd5, $fileConflict);
    } else { // journal legacy sans wrote_md5 : restauration inconditionnelle (filet B)
        $okr = looks_files_rollback(['items' => $items]); $fileConflict = !$okr;
        $res = ['restored' => $okr ? count($items) : 0, 'conflict' => !$okr, 'items' => []];
    }
    if ($fileConflict) {
        looks_tx_log($dir, 'CONFLICT', ['reason' => 'reprise : fichier modifié à l\'extérieur', 'file_conflict' => true, 'file_items' => $res['items'] ?? []]);
        $err = 'CONFLIT : un fichier figuredata a été modifié à l\'extérieur — reprise bloquée (rollback INCOMPLET), résolution manuelle requise (rang 7).';
        return null;
    }
    // Vérif post-restauration (fichiers restaurés depuis .bak).
    foreach ($items as $it) { try { figd_parse(looks_read_file_strict($it['path'])); } catch (\Throwable $e) { $err = 'CRITIQUE : fichier illisible après restauration (' . $it['path'] . ')'; return null; } }
    // Réglages HC => restauration conditionnelle.
    $hcOk = $restoreHc();
    if ($hcOk === null) return null;
    if ($hcOk === false) {
        looks_tx_log($dir, 'CONFLICT', ['reason' => 'reprise : réglage HC modifié à l\'extérieur (fichiers déjà restaurés)', 'hc_conflict' => true]);
        $err = 'CONFLIT : fichiers restaurés mais un réglage HC a été modifié à l\'extérieur — rollback INCOMPLET, résolution manuelle requise (rang 7).';
        return null;
    }
    looks_tx_log($dir, 'ROLLED_BACK', ['reason' => 'reprise sécurisée']);
    looks_tx_journal_clear($dir);
    looks_tx_unlock(['path' => looks_tx_lock_path($dir)]);
    return ['mode' => 'rollback', 'files' => count($items), 'note' => 'état pré-opération restauré'];
}
/** Termine une transaction VÉRIFIÉE mais non committée (nettoyage des .bak). */
function looks_tx_recover_commit(PDO $pdo, string $dir, ?string &$err = null): ?array {
    $p = looks_tx_pending($dir);
    if ($p === null)        { $err = 'aucune transaction inachevée'; return null; }
    if (!empty($p['conflict'])) { $err = 'conflit externe non résolu — finalisation bloquée. Résolution manuelle requise (rang 7).'; return null; }
    if ($p['corrupt'])      { $err = 'journal corrompu — finalisation refusée'; return null; }
    if (!$p['has_verified']) { $err = 'transaction non vérifiée — finalisation impossible (utilisez la restauration sécurisée)'; return null; }
    $items = $p['data']['FILES_STAGED']['items'] ?? [];
    $md5 = [];
    foreach ($items as $it) {
        try { $disk = looks_read_file_strict($it['path']); figd_parse($disk); $md5[] = md5($disk); }
        catch (\Throwable $e) { $err = 'fichier publié illisible (' . $it['path'] . ') — finalisation refusée (sécurité)'; return null; }
    }
    if (count($md5) === 2 && $md5[0] !== $md5[1]) { $err = 'copies désynchronisées — finalisation refusée'; return null; }
    foreach ($items as $it) { if (!empty($it['bak']) && is_file($it['bak'])) @unlink($it['bak']); if (!empty($it['tmp']) && is_file($it['tmp'])) @unlink($it['tmp']); }
    looks_tx_log($dir, 'COMMITTED', ['reason' => 'reprise (finalisation)']);
    looks_tx_journal_clear($dir);
    looks_tx_unlock(['path' => looks_tx_lock_path($dir)]);
    return ['mode' => 'commit', 'files' => count($items), 'note' => 'publication finalisée'];
}

/* =====================================================================
 *  ÉTAPE 4-C — CORRECTIF A : DÉTECTION DES MODIFICATIONS LIVE
 * ---------------------------------------------------------------------
 *  Depuis l'ouverture d'un brouillon, le LIVE (2 fichiers figuredata + 2
 *  réglages HC) peut avoir changé (autre admin via le moteur, OU modification
 *  externe : éditeur, FTP, ému). On enregistre une BASELINE à l'ouverture
 *  (empreintes + présence réelle) et on COMPARE au LIVE avant publication,
 *  SOUS VERROU (garde de l'orchestrateur). Si le LIVE a changé : refus propre,
 *  sauf publication FORCÉE explicitement confirmée et TRACÉE.
 *
 *  Limites honnêtes (cf. échange) : le verrou applicatif ne bloque PAS un
 *  écrivain externe non coopératif ; le contrôle au plus près réduit la fenêtre
 *  TOCTOU sans l'annuler (règle opérationnelle : toute modif LIVE passe par le
 *  moteur, ou pendant une fenêtre sans publication). Le CAS SQL (SELECT ...
 *  FOR UPDATE) protège réellement les réglages ; les fichiers reposent sur le
 *  contrôle au plus près + l'état CONFLICT en cas de divergence au rollback.
 *
 *  Prérequis SQL (migration 2026-11-21, JAMAIS auto-jouée) :
 *    looks_drafts.base_*  (références de baseline)
 *    looks_draft_backups  (sauvegardes intégrales)
 *    looks_publish_log    (traçabilité des publications forcées)
 *  Le moteur DÉGRADE proprement si ces colonnes/tables sont absentes
 *  (aucune sécurité présumée, aucune auto-migration).
 * ===================================================================== */

/** Les colonnes base_* existent-elles ? (migration jouée). Jamais créées ici. */
function looks_draft_has_baseline_cols(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    try { $cache = (bool)$pdo->query("SHOW COLUMNS FROM looks_drafts LIKE 'base_fig_gm'")->fetch(); }
    catch (\Throwable $e) { $cache = false; }
    return $cache;
}

/** Capture COHÉRENTE de l'état LIVE (2 lectures complètes successives qui
 *  doivent être identiques, sinon refus). Renvoie les textes + empreintes +
 *  présence/valeur HC. Sert à INITIALISER un brouillon (le seed = exactement
 *  le texte dont l'empreinte devient la baseline) et à mesurer la fraîcheur. */
function looks_capture_baseline(PDO $pdo): array {
    $read = function () use ($pdo) {
        $p   = figd_paths();
        $gm  = looks_read_file_strict($p['gamedata']);
        $dcr = looks_read_file_strict($p['dcr']);
        $hc  = looks_setting_state($pdo, ['users.figure.parts.default', 'users.figure.parts.club']);
        return ['gm' => $gm, 'dcr' => $dcr, 'md5_gm' => md5($gm), 'md5_dcr' => md5($dcr),
                'hc_def' => $hc['users.figure.parts.default'], 'hc_club' => $hc['users.figure.parts.club']];
    };
    $a = $read();
    $b = $read();
    if ($a['md5_gm'] !== $b['md5_gm'] || $a['md5_dcr'] !== $b['md5_dcr']
        || $a['hc_def'] != $b['hc_def'] || $a['hc_club'] != $b['hc_club']) {
        throw new \RuntimeException('capture de baseline incohérente (le LIVE a changé pendant la lecture) — refus');
    }
    return [
        'fig_gm_text'       => $a['gm'],
        'fig_dcr_text'      => $a['dcr'],
        'base_fig_gm'       => $a['md5_gm'],
        'base_fig_dcr'      => $a['md5_dcr'],
        'base_hc_def'       => !empty($a['hc_def']['present'])  ? (string)$a['hc_def']['value']  : null,
        'base_hc_def_pres'  => !empty($a['hc_def']['present'])  ? 1 : 0,
        'base_hc_club'      => !empty($a['hc_club']['present']) ? (string)$a['hc_club']['value'] : null,
        'base_hc_club_pres' => !empty($a['hc_club']['present']) ? 1 : 0,
        'files_synced'      => ($a['md5_gm'] === $a['md5_dcr']),
    ];
}

/** Baseline stockée du brouillon (ou null si colonnes absentes / pas de
 *  brouillon). ['base_fig_gm'=>null] = brouillon legacy sans baseline rattachée. */
function looks_draft_baseline(PDO $pdo, int $adminId): ?array {
    if (!looks_draft_has_baseline_cols($pdo)) return null;
    $st = $pdo->prepare('SELECT base_fig_gm,base_fig_dcr,base_hc_def,base_hc_club,base_hc_def_pres,base_hc_club_pres,base_captured_at FROM looks_drafts WHERE admin_id=?');
    $st->execute([$adminId]); $r = $st->fetch();
    if (!$r) return null;
    if (($r['base_fig_gm'] ?? null) === null && ($r['base_captured_at'] ?? null) === null) return ['base_fig_gm' => null]; // non rattachée
    return $r;
}

/** Compare une baseline à un état LIVE capturé. Renvoie ['changed','no_baseline',
 *  'labels'[],'details'[]]. Présence RÉELLE des lignes HC prise en compte. */
function looks_baseline_diff(?array $baseline, array $state): array {
    if (!$baseline || ($baseline['base_fig_gm'] ?? null) === null) {
        return ['changed' => false, 'no_baseline' => true, 'labels' => [], 'details' => []];
    }
    $labels = []; $details = [];
    if ((string)$baseline['base_fig_gm'] !== (string)$state['md5_gm']) { $labels[] = 'fichier gamedata'; $details['fig_gm'] = ['base' => $baseline['base_fig_gm'], 'live' => $state['md5_gm']]; }
    if ((string)$baseline['base_fig_dcr'] !== (string)$state['md5_dcr']) { $labels[] = 'fichier dcr'; $details['fig_dcr'] = ['base' => $baseline['base_fig_dcr'], 'live' => $state['md5_dcr']]; }
    $cmp = function (string $label, string $key, $basePres, $baseVal, array $st) use (&$labels, &$details) {
        $curPres = !empty($st['present']); $curVal = $curPres ? (string)$st['value'] : null;
        $bp = ((int)$basePres === 1); $bv = $bp ? (string)$baseVal : null;
        if ($curPres !== $bp || ($curPres && $curVal !== $bv)) {
            $labels[] = $label;
            $details[$key] = ['base_present' => $bp, 'base_value' => $bv, 'live_present' => $curPres, 'live_value' => $curVal];
        }
    };
    $hcP = $state['hc_prev'] ?? [];
    $cmp('réglage HC (libres)', 'hc_def', $baseline['base_hc_def_pres'], $baseline['base_hc_def'], $hcP['users.figure.parts.default'] ?? ['present' => false, 'value' => null]);
    $cmp('réglage HC (club)',   'hc_club', $baseline['base_hc_club_pres'], $baseline['base_hc_club'], $hcP['users.figure.parts.club'] ?? ['present' => false, 'value' => null]);
    return ['changed' => !empty($labels), 'no_baseline' => false, 'labels' => $labels, 'details' => $details];
}

/** État de fraîcheur du brouillon (informatif, HORS verrou). La vérité
 *  d'autorité est la garde exécutée SOUS verrou par l'orchestrateur. */
function looks_draft_stale(PDO $pdo, int $adminId): array {
    $bl = looks_draft_baseline($pdo, $adminId);
    if ($bl === null || ($bl['base_fig_gm'] ?? null) === null) return ['no_baseline' => true, 'changed' => false, 'labels' => []];
    $cap = looks_capture_baseline($pdo);
    $state = ['md5_gm' => $cap['base_fig_gm'], 'md5_dcr' => $cap['base_fig_dcr'], 'hc_prev' => [
        'users.figure.parts.default' => ['present' => $cap['base_hc_def_pres'] === 1, 'value' => $cap['base_hc_def']],
        'users.figure.parts.club'    => ['present' => $cap['base_hc_club_pres'] === 1, 'value' => $cap['base_hc_club']],
    ]];
    return looks_baseline_diff($bl, $state);
}

/** Fabrique la GARDE de staleness pour l'orchestrateur (exécutée sous verrou,
 *  sur l'état EXACT capturé). $seen reçoit l'état vu + le diff (pour la trace).
 *  - LIVE == baseline        -> null (OK)
 *  - LIVE != baseline, !force -> message de refus
 *  - LIVE != baseline, force  -> null (laisse passer ; traçabilité en amont)
 *  - baseline absente         -> null (aucune détection possible ; signalé par le preflight) */
function looks_staleness_guard(?array $baseline, bool $force, ?array &$seen = null): callable {
    return function (PDO $pdo, array $state) use ($baseline, $force, &$seen): ?string {
        $diff = looks_baseline_diff($baseline, $state);
        $seen = ['md5_gm' => $state['md5_gm'], 'md5_dcr' => $state['md5_dcr'], 'diff' => $diff];
        if (!empty($diff['no_baseline'])) return null;
        if (empty($diff['changed'])) return null;
        if ($force) return null;
        return 'Le LIVE a été modifié depuis l\'ouverture du brouillon : ' . implode(', ', $diff['labels'])
             . '. Publication refusée — rechargez le brouillon depuis le LIVE, ou forcez explicitement la publication.';
    };
}

/** Rattache une baseline à un brouillon EXISTANT sans toucher data/hc_data.
 *  N'écrit QUE les colonnes base_*. Réservé à un appel EXPLICITE (jamais auto,
 *  jamais sur le brouillon Orms sans accord). */
function looks_draft_attach_baseline(PDO $pdo, int $adminId, ?string &$err = null): ?array {
    if (!looks_draft_has_baseline_cols($pdo)) { $err = 'colonnes baseline absentes (migration non jouée)'; return null; }
    $cur = looks_draft_current($pdo, $adminId);
    if (!$cur) { $err = 'aucun brouillon à rattacher'; return null; }
    $cap = looks_capture_baseline($pdo); // cohérente (2 passes) ou refus
    $st = $pdo->prepare('UPDATE looks_drafts SET base_fig_gm=?,base_fig_dcr=?,base_hc_def=?,base_hc_club=?,base_hc_def_pres=?,base_hc_club_pres=?,base_captured_at=CURRENT_TIMESTAMP WHERE admin_id=?');
    $st->execute([$cap['base_fig_gm'], $cap['base_fig_dcr'], $cap['base_hc_def'], $cap['base_hc_club'], $cap['base_hc_def_pres'], $cap['base_hc_club_pres'], $adminId]);
    return ['attached' => true, 'files_synced' => $cap['files_synced']];
}

/* ---------- Sauvegardes intégrales de brouillon (jamais purgées auto) ---------- */
function looks_draft_backups_available(PDO $pdo): bool {
    try { $pdo->query('SELECT 1 FROM looks_draft_backups LIMIT 1'); return true; }
    catch (\Throwable $e) { return false; }
}
/** Sauvegarde INTÉGRALE du brouillon courant (data + hc_data + baseline + dates). */
function looks_draft_backup(PDO $pdo, int $adminId, string $reason = 'manual', string $note = '', ?string &$err = null): ?int {
    if (!looks_draft_backups_available($pdo)) { $err = 'table looks_draft_backups absente (migration non jouée)'; return null; }
    $cur = looks_draft_current($pdo, $adminId);
    if (!$cur) { $err = 'aucun brouillon à sauvegarder'; return null; }
    $hasB = looks_draft_has_baseline_cols($pdo);
    $st = $pdo->prepare('INSERT INTO looks_draft_backups (admin_id,data,hc_data,base_fig_gm,base_fig_dcr,base_hc_def,base_hc_club,base_hc_def_pres,base_hc_club_pres,base_captured_at,draft_created_at,draft_updated_at,reason,note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        $adminId, (string)$cur['data'], $cur['hc_data'] ?? null,
        $hasB ? ($cur['base_fig_gm'] ?? null) : null, $hasB ? ($cur['base_fig_dcr'] ?? null) : null,
        $hasB ? ($cur['base_hc_def'] ?? null) : null, $hasB ? ($cur['base_hc_club'] ?? null) : null,
        $hasB ? ($cur['base_hc_def_pres'] ?? null) : null, $hasB ? ($cur['base_hc_club_pres'] ?? null) : null,
        $hasB ? ($cur['base_captured_at'] ?? null) : null,
        $cur['created_at'] ?? null, $cur['updated_at'] ?? null, $reason, $note,
    ]);
    return (int)$pdo->lastInsertId();
}
function looks_draft_backups_list(PDO $pdo, int $adminId, int $limit = 50): array {
    if (!looks_draft_backups_available($pdo)) return [];
    $st = $pdo->prepare('SELECT id,admin_id,reason,note,created_at,CHAR_LENGTH(data) AS data_len,(hc_data IS NOT NULL) AS has_hc,base_captured_at FROM looks_draft_backups WHERE admin_id=? ORDER BY id DESC LIMIT ' . (int)$limit);
    $st->execute([$adminId]); return $st->fetchAll();
}
/** Restauration INTÉGRALE d'un brouillon depuis une sauvegarde (remplace le
 *  brouillon courant de l'admin propriétaire de la sauvegarde). */
function looks_draft_backup_restore(PDO $pdo, int $backupId, ?string &$err = null): ?array {
    if (!looks_draft_backups_available($pdo)) { $err = 'table looks_draft_backups absente'; return null; }
    $st = $pdo->prepare('SELECT * FROM looks_draft_backups WHERE id=?'); $st->execute([$backupId]); $b = $st->fetch();
    if (!$b) { $err = 'sauvegarde introuvable'; return null; }
    $adminId = (int)$b['admin_id'];
    looks_ensure_draft_table($pdo);
    $pdo->prepare('DELETE FROM looks_drafts WHERE admin_id=?')->execute([$adminId]);
    if (looks_draft_has_baseline_cols($pdo)) {
        $ins = $pdo->prepare('INSERT INTO looks_drafts (admin_id,data,hc_data,base_fig_gm,base_fig_dcr,base_hc_def,base_hc_club,base_hc_def_pres,base_hc_club_pres,base_captured_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$adminId, (string)$b['data'], $b['hc_data'], $b['base_fig_gm'], $b['base_fig_dcr'], $b['base_hc_def'], $b['base_hc_club'], $b['base_hc_def_pres'], $b['base_hc_club_pres'], $b['base_captured_at']]);
    } else {
        $pdo->prepare('INSERT INTO looks_drafts (admin_id,data,hc_data) VALUES (?,?,?)')->execute([$adminId, (string)$b['data'], $b['hc_data']]);
    }
    return ['restored_from' => $backupId, 'admin_id' => $adminId];
}

/** Recharge le brouillon depuis le LIVE (nouvelle baseline), APRÈS sauvegarde
 *  préalable OBLIGATOIRE et VÉRIFIÉE. Les éditions locales sont préservées dans
 *  la sauvegarde (restaurable). data = LIVE, hc_data = NULL (aucun écart HC). */
function looks_draft_reseed(PDO $pdo, int $adminId, ?string &$err = null): ?array {
    $cur = looks_draft_current($pdo, $adminId);
    if (!$cur) { $err = 'aucun brouillon à recharger'; return null; }
    $bid = looks_draft_backup($pdo, $adminId, 'reseed', 'rechargement depuis le LIVE', $err);
    if ($bid === null) return null; // pas de rechargement sans sauvegarde
    // Vérifie la sauvegarde AVANT d'écraser.
    $chk = $pdo->prepare('SELECT data FROM looks_draft_backups WHERE id=?'); $chk->execute([$bid]);
    $cd = $chk->fetchColumn();
    if ($cd === false || (string)$cd !== (string)$cur['data']) { $err = 'sauvegarde non vérifiable — rechargement annulé'; return null; }
    $cap = looks_capture_baseline($pdo);
    if (looks_draft_has_baseline_cols($pdo)) {
        $u = $pdo->prepare('UPDATE looks_drafts SET data=?,hc_data=NULL,base_fig_gm=?,base_fig_dcr=?,base_hc_def=?,base_hc_club=?,base_hc_def_pres=?,base_hc_club_pres=?,base_captured_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE admin_id=?');
        $u->execute([$cap['fig_gm_text'], $cap['base_fig_gm'], $cap['base_fig_dcr'], $cap['base_hc_def'], $cap['base_hc_club'], $cap['base_hc_def_pres'], $cap['base_hc_club_pres'], $adminId]);
    } else {
        $pdo->prepare('UPDATE looks_drafts SET data=?,hc_data=NULL,updated_at=CURRENT_TIMESTAMP WHERE admin_id=?')->execute([$cap['fig_gm_text'], $adminId]);
    }
    return ['backup_id' => $bid, 'reseeded' => true, 'files_synced' => $cap['files_synced']];
}

/* ---------- Traçabilité des publications (normales ET forcées) ---------- */
function looks_publish_log_available(PDO $pdo): bool {
    try { $pdo->query('SELECT 1 FROM looks_publish_log LIMIT 1'); return true; }
    catch (\Throwable $e) { return false; }
}
function looks_publish_log_write(PDO $pdo, array $row): ?int {
    if (!looks_publish_log_available($pdo)) return null; // absence de table : pas de blocage
    $st = $pdo->prepare('INSERT INTO looks_publish_log (admin_id,mode,live_fig_gm,live_fig_dcr,base_fig_gm,base_fig_dcr,diff_json,confirmed,snapshot_id,result,err) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        (int)($row['admin_id'] ?? 0), (string)($row['mode'] ?? 'normal'),
        $row['live_fig_gm'] ?? null, $row['live_fig_dcr'] ?? null,
        $row['base_fig_gm'] ?? null, $row['base_fig_dcr'] ?? null,
        $row['diff_json'] ?? null, !empty($row['confirmed']) ? 1 : 0,
        $row['snapshot_id'] ?? null, (string)($row['result'] ?? ''), (string)($row['err'] ?? ''),
    ]);
    return (int)$pdo->lastInsertId();
}

/* ---------- Résolution manuelle explicite d'un état CONFLICT (rang 7) ---------- */
function looks_tx_conflict_info(string $dir): ?array {
    $p = looks_tx_pending($dir);
    if ($p === null || empty($p['conflict'])) return null;
    $c = $p['data']['CONFLICT'] ?? [];
    return [
        'reason'        => $c['reason'] ?? '',
        'file_conflict' => !empty($c['file_conflict']),
        'hc_conflict'   => !empty($c['hc_conflict']),
        'file_items'    => $c['file_items'] ?? [],
        'markers'       => $p['markers'],
    ];
}
/** Résolution = l'admin (rang 7) acte le conflit et CONSERVE l'état externe
 *  actuel (disque + SQL) tel quel. Ne restaure RIEN (aucun risque d'écrasement),
 *  archive le journal, conserve .bak/.conflict (preuves), libère le verrou.
 *  Pour revenir à un état antérieur : utiliser ENSUITE une restauration de
 *  snapshot (elle-même gardée). */
function looks_tx_resolve_conflict(PDO $pdo, string $dir, string $choice, ?string &$err = null): ?array {
    $info = looks_tx_conflict_info($dir);
    if ($info === null) { $err = 'aucun conflit à résoudre'; return null; }
    if ($choice !== 'accept_external') { $err = 'choix de résolution non reconnu (attendu : accept_external)'; return null; }
    looks_tx_log($dir, 'RESOLVED', ['choice' => $choice]);
    $jp = looks_tx_journal_path($dir);
    $arch = $dir . '/tx.journal.conflict-' . date('Ymd-His');
    if (is_file($jp)) @copy($jp, $arch); // trace archivée (non supprimée)
    looks_tx_journal_clear($dir);
    looks_tx_unlock(['path' => looks_tx_lock_path($dir)]);
    return ['resolved' => true, 'choice' => $choice, 'archive' => basename($arch)];
}
