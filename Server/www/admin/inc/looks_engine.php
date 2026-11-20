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

/* ---- Chemins (les deux copies à garder synchronisées aux étapes d'écriture) ---- */
function figd_paths(): array {
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
function figd_hc_lists(?PDO $pdo): array {
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
    return ['default' => figd_parts_csv_to_ids($def), 'club' => figd_parts_csv_to_ids($club), 'src' => $src];
}

/* =====================================================================
 *  INVENTAIRE — liste à plat de tous les sets, prêts pour l'affichage.
 *  Chaque entrée :
 *   gender, type, type_label, set_id, colors[], ncolors, sprites{},
 *   available(bool), hc(bool), status('libre'|'hc'|'inactif')
 * ===================================================================== */
function figd_inventory(?PDO $pdo = null): array {
    $root = figd_parse(figd_read_raw('gamedata'));
    if (!isset($root['__map'])) throw new RuntimeException('figuredata : racine inattendue');
    $hc = figd_hc_lists($pdo);
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
