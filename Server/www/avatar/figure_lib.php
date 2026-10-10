<?php
/**
 * figure_lib.php — Conversion figure V14 (Shockwave, 25 chiffres) -> format
 * « nouveau » consommé par Avatara/Minerva, en utilisant NOS couleurs.
 *
 * Principe (générique, valable pour toutes les figures) :
 *  - On décode la figure avec NOTRE figuredata.txt (source que lit le client) :
 *    5 parties = set(3) + couleur(2), ordre hr, hd, puis ch/lg/sh détectés par type.
 *    L'index couleur est 1-based dans la liste de couleurs de chaque set.
 *  - Pour chaque partie, on prend le HEX réel (nos données) et on retrouve
 *    l'identifiant de couleur Avatara dans la palette de CETTE partie
 *    (correspondance hex exacte, sinon plus proche en RGB).
 *  - La structure (sets, chapeau dérivé des cheveux, cas particuliers) reprend
 *    la logique d'Avatara (TakeCareOfHats), MAIS les couleurs viennent de nous.
 *    => le bug de palette du convertisseur d'Avatara (chapeau/pantalon gris) est
 *       évité, sans figer les identifiants d'un personnage précis.
 *
 * On ne modifie JAMAIS la figure stockée en base : lecture seule.
 */

if (!defined('AVATAR_LIB')) define('AVATAR_LIB', 1);

const AVATAR_CONV_VERSION = '1';   // bump = invalide le cache de conversion/figuredata

function fig_paths(): array {
    return [
        'our' => dirname(__DIR__) . '/dcr/14.1_b8/figuredata.txt',
        'ava' => dirname(__DIR__, 3) . '/tools/minerva-bin/win-x64/figuredata/figuredata.xml',
        'cache' => __DIR__ . '/cache/figuredata.cache',
    ];
}

/** Table cheveux -> chapeau d'Avatara (TakeCareOfHats), fidèlement reprise. */
function fig_hat_map(): array {
    // set cheveux => ['ha'=>idChapeau|null, 'col'=>'hair'|'0'|valeur, 'extra'=>suffixe, 'hrOverride'=>set|null]
    return [
        120 => ['ha' => 1001, 'col' => '0'],
        525 => ['ha' => 1002, 'col' => 'hair'],
        140 => ['ha' => 1002, 'col' => 'hair'],
        150 => ['ha' => 1003, 'col' => 'hair'],
        535 => ['ha' => 1003, 'col' => 'hair'],
        160 => ['ha' => 1004, 'col' => 'hair'],
        565 => ['ha' => 1004, 'col' => 'hair'],
        570 => ['ha' => 1005, 'col' => 'hair'],
        585 => ['ha' => 1006, 'col' => '0'],
        175 => ['ha' => 1006, 'col' => '0'],
        580 => ['ha' => 1007, 'col' => '0', 'extra' => '.fa-1202-70'],
        176 => ['ha' => 1007, 'col' => '0', 'extra' => '.fa-1202-70'],
        590 => ['ha' => 1008, 'col' => '0', 'extra' => '.fa-1202-1294'],
        177 => ['ha' => 1008, 'col' => '0', 'extra' => '.fa-1202-1294'],
        595 => ['ha' => 1009, 'col' => '1321'],
        178 => ['ha' => 1009, 'col' => '1321'],
        130 => ['ha' => 1010, 'col' => 'hair'],
        801 => ['ha' => 1011, 'col' => 'hair', 'hrOverride' => 829, 'extra' => '.fa-1201-62'],
        800 => ['ha' => 1012, 'col' => 'hair'],
        810 => ['ha' => 1012, 'col' => 'hair'],
        802 => ['ha' => 1013, 'col' => 'hair'],
        811 => ['ha' => 1013, 'col' => 'hair'],
    ];
}

/** Construit (et met en cache) les tables dérivées des deux figuredata. */
function fig_caches(): array {
    static $c = null;
    if ($c !== null) return $c;
    $p = fig_paths();
    $cacheOk = is_file($p['cache']) && is_file($p['our']) && is_file($p['ava'])
        && filemtime($p['cache']) >= max(filemtime($p['our']), filemtime($p['ava']));
    if ($cacheOk) {
        $raw = @file_get_contents($p['cache']);
        $data = $raw !== false ? @unserialize($raw) : false;
        if (is_array($data) && ($data['v'] ?? '') === AVATAR_CONV_VERSION) { return $c = $data; }
    }
    $c = fig_build_caches($p);
    @mkdir(dirname($p['cache']), 0775, true);
    @file_put_contents($p['cache'], serialize($c));
    return $c;
}

function fig_build_caches(array $p): array {
    $our = fig_parse_our(@file_get_contents($p['our']) ?: '');
    $ava = fig_parse_ava(@file_get_contents($p['ava']) ?: '');
    return [
        'v' => AVATAR_CONV_VERSION,
        'our' => $our['sets'],      // [G][type][set] => [hex,...]
        'typeOf' => $our['typeOf'], // [G][set] => type
        'avaPal' => $ava['pal'],    // [paletteId] => [ [id,hex], ... ]
        'avaType' => $ava['type'],  // [type] => paletteId
    ];
}

/** Parse NOTRE figuredata.txt (format Lingo). */
function fig_parse_our(string $raw): array {
    $raw = str_replace("\r\n", "\n", $raw);
    $sets = ['M' => [], 'F' => []]; $typeOf = ['M' => [], 'F' => []];
    $fpos = strpos($raw, '"F":[');
    $blocks = ['M' => substr($raw, 0, $fpos !== false ? $fpos : strlen($raw)), 'F' => $fpos !== false ? substr($raw, $fpos) : ''];
    foreach ($blocks as $g => $blk) {
        if ($blk === '') continue;
        preg_match_all('/"s":(\d+),"p":\[[^\]]*\],"c":\[([^\]]*)\]/', $blk, $sm, PREG_OFFSET_CAPTURE);
        preg_match_all('/"([A-Za-z]{2})":\[\["s"/', $blk, $tm, PREG_OFFSET_CAPTURE);
        $typeAt = [];
        foreach ($tm[1] as $i => $t) $typeAt[] = ['type' => $t[0], 'off' => $tm[0][$i][1]];
        $curType = function ($off) use ($typeAt) { $c = null; foreach ($typeAt as $ta) { if ($ta['off'] <= $off) $c = $ta['type']; else break; } return $c; };
        for ($i = 0; $i < count($sm[1]); $i++) {
            $set = (int)$sm[1][$i][0]; $off = $sm[1][$i][1];
            preg_match_all('/"([0-9A-Fa-f]{6})"/', $sm[2][$i][0], $cm);
            $type = $curType($off);
            if ($type === null) continue;
            $sets[$g][$type][$set] = array_map('strtoupper', $cm[1]);
            $typeOf[$g][$set] = $type;
        }
    }
    return ['sets' => $sets, 'typeOf' => $typeOf];
}

/** Parse le figuredata.xml d'Avatara (palettes + settypes). */
function fig_parse_ava(string $xml): array {
    $type = [];
    if (preg_match_all('/<settype type="(\w+)" paletteid="(\d+)"/', $xml, $tm, PREG_SET_ORDER)) {
        foreach ($tm as $m) $type[$m[1]] = (int)$m[2];
    }
    $pal = [];
    if (preg_match_all('/<palette id="(\d+)">(.*?)<\/palette>/s', $xml, $pm, PREG_SET_ORDER)) {
        foreach ($pm as $m) {
            preg_match_all('/<color id="(\d+)"[^>]*>([0-9A-Fa-f]{6})<\/color>/', $m[2], $cm, PREG_SET_ORDER);
            $list = [];
            foreach ($cm as $c) $list[] = ['id' => $c[1], 'hex' => strtoupper($c[2])];
            $pal[(int)$m[1]] = $list;
        }
    }
    return ['pal' => $pal, 'type' => $type];
}

/** Id de couleur Avatara pour un hex dans la palette d'un type (exact sinon plus proche). */
function fig_color_id(string $type, string $hex, array $c, bool &$approx = false): ?string {
    $approx = false;
    $pid = $c['avaType'][$type] ?? null;
    if ($pid === null || empty($c['avaPal'][$pid])) return null;
    $hex = strtoupper($hex);
    foreach ($c['avaPal'][$pid] as $col) if ($col['hex'] === $hex) return $col['id'];
    // plus proche en RGB
    $fr = hexdec(substr($hex, 0, 2)); $fg = hexdec(substr($hex, 2, 2)); $fb = hexdec(substr($hex, 4, 2));
    $best = null; $bd = PHP_INT_MAX;
    foreach ($c['avaPal'][$pid] as $col) {
        $cr = hexdec(substr($col['hex'], 0, 2)); $cg = hexdec(substr($col['hex'], 2, 2)); $cb = hexdec(substr($col['hex'], 4, 2));
        $d = ($fr - $cr) ** 2 + ($fg - $cg) ** 2 + ($fb - $cb) ** 2;
        if ($d < $bd) { $bd = $d; $best = $col['id']; }
    }
    $approx = true;
    return $best;
}

/**
 * Convertit la figure ancienne -> nouvelle, couleurs issues de nos données.
 * @return array ['figure'=>?string, 'parts'=>[...], 'missing'=>[...]]
 */
function fig_old_to_new(string $old, string $sex): array {
    $old = preg_replace('/\D/', '', $old);
    $g = strtoupper($sex) === 'F' ? 'F' : 'M';
    $missing = []; $parts = [];
    if (strlen($old) < 25) return ['figure' => null, 'parts' => [], 'missing' => ['Figure trop courte (' . strlen($old) . ' chiffres, 25 attendus).']];
    $c = fig_caches();

    // Découpe : 5 x (set 3 + couleur 2)
    $raw = [];
    for ($i = 0; $i < 5; $i++) { $raw[] = [(int)substr($old, $i * 5, 3), (int)substr($old, $i * 5 + 3, 2)]; }
    // hr = pos0, hd = pos1 ; ch/lg/sh = pos2..4 détectés par type
    $slot = ['hr' => $raw[0], 'hd' => $raw[1]];
    foreach ([2, 3, 4] as $i) {
        $set = $raw[$i][0];
        $t = $c['typeOf'][$g][$set] ?? null;
        if ($t === null) { $missing[] = "Set $set (position " . ($i + 1) . ") inconnu dans notre figuredata ($g)."; $t = ['ch', 'lg', 'sh'][$i - 2]; }
        $slot[$t] = $raw[$i];
    }

    $pieces = [];
    $hairHex = null; $hrColId = null;
    foreach (['hr', 'hd', 'ch', 'lg', 'sh'] as $t) {
        if (!isset($slot[$t])) { $missing[] = "Partie $t absente de la figure."; continue; }
        [$set, $cidx] = $slot[$t];
        $colors = $c['our'][$g][$t][$set] ?? null;
        $hex = null;
        if ($colors === null) { $missing[] = "Set $t-$set introuvable dans notre figuredata ($g)."; }
        elseif ($cidx >= 1 && $cidx <= count($colors)) { $hex = $colors[$cidx - 1]; }
        else { $missing[] = "Couleur $cidx hors palette pour $t-$set (" . count($colors) . " couleurs)."; $hex = $colors[0] ?? null; }

        $setOut = $set;
        if ($t === 'sh' && $set === 730) $setOut = 3206;   // cas particulier Avatara
        $ap = false;
        $colId = $hex !== null ? fig_color_id($t, $hex, $c, $ap) : null;
        if ($hex !== null && $colId === null) $missing[] = "Pas d'id de couleur Avatara pour $t #$hex.";
        if ($t === 'hr') { $hairHex = $hex; $hrColId = $colId; }
        $pieces[$t] = $t . '-' . $setOut . '-' . ($colId ?? '0');
        $parts[$t] = ['set' => $set, 'colorIndex' => $cidx, 'hex' => $hex, 'avaColorId' => $colId, 'approx' => $ap ?? false];
    }

    // Chapeau dérivé des cheveux
    $hat = '';
    $hairSet = $slot['hr'][0] ?? 0;
    $hmap = fig_hat_map();
    if (isset($hmap[$hairSet])) {
        $h = $hmap[$hairSet];
        if (!empty($h['hrOverride'])) { $pieces['hr'] = 'hr-' . $h['hrOverride'] . '-' . ($hrColId ?? '0'); }
        if ($h['ha'] !== null) {
            $haCol = '0';
            if ($h['col'] === 'hair') { $haCol = ($hairHex !== null ? (fig_color_id('ha', $hairHex, $c) ?? '0') : '0'); }
            elseif ($h['col'] !== '0') { $haCol = $h['col']; }
            $hat = '.ha-' . $h['ha'] . '-' . $haCol;
        }
        if (!empty($h['extra'])) $hat .= $h['extra'];
        $parts['ha'] = ['set' => $h['ha'], 'fromHair' => $hairSet];
    }

    $order = ['hr', 'hd', 'ch', 'lg', 'sh'];
    $out = [];
    foreach ($order as $t) if (isset($pieces[$t])) $out[] = $pieces[$t];
    $figure = implode('.', $out) . $hat;
    return ['figure' => $figure, 'parts' => $parts, 'missing' => $missing];
}
