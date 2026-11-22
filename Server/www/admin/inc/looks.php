<?php
/* =====================================================================
 * admin/inc/looks.php  —  « Apparence » (vêtements & couleurs)
 * ÉTAPE 1 : inventaire lecture seule.
 * ÉTAPE 2 : fiche détaillée + édition des couleurs EN BROUILLON.
 * ---------------------------------------------------------------------
 * Aucune publication : toutes les modifs vont dans un brouillon par admin
 * (table looks_drafts). figuredata.txt LIVE n'est jamais touché ici.
 * Le moteur (looks_engine.php) est requis par index.php.
 * ===================================================================== */
require_once __DIR__ . '/looks_engine.php';

function looks_part_slot_offset(string $part): int {
    $o = ['hr' => 0, 'hd' => 5, 'ch' => 10, 'lg' => 15, 'sh' => 20];
    return $o[$part] ?? 0;
}

/* Qui porte un set ? Lecture seule sur users.figure (25 car. : 5 slots de set3+coul2).
 * Les positions SUBSTRING sont calculées à partir d'une table fixe (jamais une
 * entrée web) -> inlinées en int ; seul l'id du set (3 chiffres) est lié. */
function looks_wearers(PDO $pdo, string $part, int $sid): array {
    $off  = looks_part_slot_offset($part);
    $soff = $off + 1;   // position du set (1-indexé)
    $coff = $off + 4;   // position de la couleur (1-indexé)
    $sid3 = sprintf('%03d', $sid);
    $out  = ['total' => 0, 'by_ci' => [], 'sample' => [], 'truncated' => false];
    try {
        $q = $pdo->prepare('SELECT CAST(SUBSTRING(figure, ' . $coff . ', 2) AS UNSIGNED) AS ci, COUNT(*) AS n
                            FROM users WHERE SUBSTRING(figure, ' . $soff . ', 3) = ? GROUP BY ci ORDER BY ci');
        $q->execute([$sid3]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out['by_ci'][(int)$r['ci']] = (int)$r['n'];
            $out['total'] += (int)$r['n'];
        }
        if ($out['total'] > 0) {
            $s = $pdo->prepare('SELECT username, CAST(SUBSTRING(figure, ' . $coff . ', 2) AS UNSIGNED) AS ci
                                FROM users WHERE SUBSTRING(figure, ' . $soff . ', 3) = ? ORDER BY ci, username LIMIT 61');
            $s->execute([$sid3]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 60) { $out['truncated'] = true; array_pop($rows); }
            foreach ($rows as $r) $out['sample'][] = ['username' => (string)$r['username'], 'ci' => (int)$r['ci']];
        }
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    return $out;
}

function page_looks(): void {
    $pdo = db();
    $adminId = (int)($_SESSION['admin']['id'] ?? 0);

    // Brouillon courant + texte effectif (brouillon si présent, sinon LIVE)
    $draft = $pdo ? looks_draft_current($pdo, $adminId) : null;
    $hasDraft = (bool)$draft;
    $effText = $pdo ? looks_effective_text($pdo, $adminId) : figd_read_raw('gamedata');
    // Droits HC effectifs (brouillon HC si présent, sinon LIVE settings/défauts)
    $hcEff = $pdo ? looks_hc_effective_lists($pdo, $adminId) : figd_hc_lists(null);
    $diff   = ($pdo && $hasDraft) ? looks_draft_diff($pdo, $adminId) : [];   // couleurs
    $hcDiff = ($pdo && $hasDraft) ? looks_hc_diff($pdo, $adminId)   : [];     // accès HC
    $changedKeys = [];
    foreach ($diff as $d) $changedKeys[$d['gender'] . '/' . $d['part'] . '/' . $d['set_id']] = $d;
    $hcChangedSids = [];
    foreach ($hcDiff as $d) $hcChangedSids[(int)$d['set_id']] = $d;

    page_title('🧥 Apparence — vêtements, couleurs & accès',
        'Édition en BROUILLON (couleurs + droits HC). Rien n\'est publié ni appliqué en jeu tant qu\'il n\'y a pas de publication (étape 4). figuredata.txt et les réglages HC LIVE ne sont jamais touchés ici.');

    looks_render_draft_banner($hasDraft, $diff, $hcDiff);

    $view = (string)($_GET['view'] ?? '');

    // ----- Vue FICHE d'un vêtement (hors onglets) -----
    if (isset($_GET['set'])) {
        looks_render_fiche((string)$_GET['set'], $effText, $pdo, $changedKeys, $hcEff, $hcChangedSids);
        looks_render_assets();
        return;
    }

    // ----- Vue PUBLICATION / VERSIONS -----
    if ($view === 'publish') {
        looks_render_tabs('publish');
        looks_render_publish($pdo, $adminId);
        looks_render_assets();
        return;
    }

    // ----- Vues basées sur l'inventaire (galerie / tableau / studio) -----
    try {
        $rows = figd_inventory_from_tree(figd_parse($effText), $pdo, $hcEff);
    } catch (Throwable $e) {
        echo '<div class="panel"><div class="empty">❌ Lecture figuredata : ' . h($e->getMessage()) . '</div></div>'; return;
    }

    if ($view === 'table') {
        looks_render_tabs('table');
        looks_render_table($rows, $changedKeys, $hcChangedSids);
        looks_render_assets();
        return;
    }
    if ($view === 'studio') {
        looks_render_tabs('studio');
        looks_render_studio($rows);
        looks_render_assets();
        return;
    }
    if ($view === 'colors') {
        looks_render_tabs('colors');
        looks_render_colors($rows);
        looks_render_assets();
        return;
    }
    if ($view === 'trends') {
        looks_render_tabs('trends');
        looks_render_trends($rows, $pdo);
        looks_render_assets();
        return;
    }

    // ----- Vue GALERIE (défaut) -----
    looks_render_tabs('gallery');
    looks_render_list($rows, $changedKeys, $hcChangedSids);
    looks_render_assets();
}

/* ---------- Barre d'onglets (navigation entre vues) ---------- */
function looks_render_tabs(string $active): void {
    $tabs = [
        'gallery' => ['🖼️ Galerie', '?p=looks'],
        'table'   => ['📋 Tableau', '?p=looks&view=table'],
        'studio'  => ['🎨 Studio',  '?p=looks&view=studio'],
        'colors'  => ['🌈 Couleurs', '?p=looks&view=colors'],
        'trends'  => ['📊 Tendances', '?p=looks&view=trends'],
        'publish' => ['🚀 Publier', '?p=looks&view=publish'],
    ];
    echo '<div class="lk-tabs">';
    foreach ($tabs as $k => $t) {
        $on = ($k === $active) ? ' on' : '';
        echo '<a class="lk-tab' . $on . '" href="' . $t[1] . '">' . h($t[0]) . '</a>';
    }
    echo '</div>';
}

/* ---------- Vue TABLEAU (inventaire dense) ---------- */
function looks_render_table(array $rows, array $changedKeys, array $hcChangedSids): void {
    echo '<div class="panel"><div class="ph"><h3>📋 Tableau</h3>'
        . '<input type="search" id="lktq" class="looks-search" placeholder="Filtrer (id, couleur, catégorie…)" autocomplete="off" style="margin-left:auto;max-width:340px">'
        . '</div>';
    echo '<div class="lk-tblwrap"><table class="clean lk-tbl"><thead><tr>'
        . '<th style="width:56px">Aperçu</th><th>Catégorie</th><th style="width:70px">ID</th><th style="width:60px">Genre</th><th>Couleurs</th><th style="width:50px">Nb</th><th style="width:90px">Statut</th><th style="width:90px">Fiche</th>'
        . '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $pv = figd_preview_figure($r);
        $src = '/avatar.php?figure=' . h($pv['figure']) . '&sex=' . h($pv['sex']) . '&size=s&direction=2&head=0';
        $badge = $r['status'] === 'hc'
            ? '<span class="lk-badge hc">⭐ HC</span>'
            : ($r['status'] === 'inactif' ? '<span class="lk-badge warn">⏸️ Inactif</span>' : '<span class="lk-badge ok">✅ Libre</span>');
        $sw = '';
        foreach (array_slice($r['colors'], 0, 8) as $hex) $sw .= '<span class="sw" style="background:#' . h($hex) . '" title="#' . h($hex) . '"></span>';
        if ($r['ncolors'] > 8) $sw .= '<span class="sw-more">+' . ($r['ncolors'] - 8) . '</span>';
        $ficheUrl = '?p=looks&set=' . h($r['gender'] . '-' . $r['type'] . '-' . $r['set_id']);
        $search = strtolower($r['set_id'] . ' ' . $r['type_label'] . ' ' . $r['gender'] . ' ' . implode(' ', $r['colors']));
        echo '<tr class="lk-trow" data-search="' . h($search) . '">'
            . '<td><img class="lk-tava" loading="lazy" alt="" src="' . $src . '" onerror="this.style.visibility=\'hidden\'"></td>'
            . '<td>' . h($r['type_label']) . '</td>'
            . '<td><a class="lnk" href="' . $ficheUrl . '"><code>#' . (int)$r['set_id'] . '</code></a></td>'
            . '<td>' . ($r['gender'] === 'F' ? '♀ F' : '♂ M') . '</td>'
            . '<td class="lk-sw">' . $sw . '</td>'
            . '<td>' . (int)$r['ncolors'] . '</td>'
            . '<td>' . $badge . '</td>'
            . '<td><a class="lk-bk ghost" href="' . $ficheUrl . '">✏️ Fiche</a></td>'
            . '</tr>';
    }
    echo '</tbody></table></div>';
    echo '<p class="muted sm" id="lktcount" style="margin-top:8px"></p>';
    echo '</div>';
}

/* ---------- Vue STUDIO (essayage + couleurs en direct) ---------- */
function looks_render_studio(array $rows): void {
    // Données compactes pour le JS (aucune écriture — prévisualisation Minerva).
    $data = [];
    foreach ($rows as $r) {
        $pv = figd_preview_figure($r);
        $data[] = [
            'g'      => $r['gender'],
            't'      => $r['type'],
            'sid'    => (int)$r['set_id'],
            'label'  => $r['type_label'],
            'status' => $r['status'],
            'fig'    => $pv['figure'],
            'sex'    => $pv['sex'],
            'coloff' => looks_part_slot_offset($r['type']) + 3,
            'colors' => array_values($r['colors']),
        ];
    }
    $typeLabels = figd_type_labels();
    $typeIcons  = ['hr' => '💇', 'hd' => '😀', 'ch' => '👕', 'lg' => '👖', 'sh' => '👟'];

    echo '<div class="panel"><div class="ph"><h3>🎨 Studio d\'essayage</h3>'
        . '<span class="muted sm" style="margin-left:auto">Prévisualise un set et teste ses couleurs en direct — <b>aucune modification</b></span></div>';

    echo '<div id="studio" class="lk-studio">';

    // Colonne scène (mannequin)
    echo '<div class="st-stage">';
    echo   '<div class="st-rot"><button type="button" class="lk-bk ghost" id="stleft" title="Tourner à gauche">◀</button>'
         . '<span id="sttitle" class="st-title muted sm">Choisis un vêtement →</span>'
         . '<button type="button" class="lk-bk ghost" id="stright" title="Tourner à droite">▶</button></div>';
    echo   '<div class="st-ava-wrap"><img id="stava" alt="aperçu"></div>';
    echo   '<div id="stcolors" class="st-colors"></div>';
    echo   '<div class="st-actions">'
         . '<a id="stfiche" class="lk-bk ok" href="?p=looks">✏️ Fiche</a>'
         . '<button type="button" class="lk-bk" id="strandom" title="Tenue aléatoire">🎲 Surprends-moi</button>'
         . '<button type="button" class="lk-bk ghost" id="stcopy" title="Copier la chaîne figure de la tenue">📋 Copier</button>'
         . '<button type="button" class="lk-bk ghost" id="streset" title="Remettre la tenue par défaut">↺ Reset</button>'
         . '</div>';
    echo '</div>';

    // Colonne sélecteur
    echo '<div class="st-picker">';
    echo   '<div class="st-controls">';
    echo     '<div class="st-cats">';
    foreach ($typeLabels as $tk => $tl) {
        echo '<button type="button" class="st-cat lk-cat' . ($tk === 'ch' ? ' on' : '') . '" data-cat="' . h($tk) . '"><span class="i">' . ($typeIcons[$tk] ?? '👚') . '</span> ' . h($tl) . '</button>';
    }
    echo     '</div>';
    echo     '<div class="st-gender"><button type="button" class="st-g lk-bk on" data-g="M">♂ Masculin</button><button type="button" class="st-g lk-bk" data-g="F">♀ Féminin</button></div>';
    echo   '</div>';
    echo   '<div id="ststrip" class="st-strip"></div>';
    echo '</div>';

    echo '</div>'; // studio

    $defaults = ['M' => figd_figure_string(figd_default_figure('M')), 'F' => figd_figure_string(figd_default_figure('F'))];
    $payload = ['sets' => $data, 'defaults' => $defaults];
    echo '<script type="application/json" id="studio-data">' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    echo '</div>'; // panel
}

/* ---------- Vue COULEURS (palette globale, lecture seule) ---------- */
function looks_render_colors(array $rows): void {
    $map = [];
    foreach ($rows as $r) {
        $seen = [];
        foreach ($r['colors'] as $hex) {
            $H = strtoupper($hex);
            if (isset($seen[$H])) continue;
            $seen[$H] = true;
            $map[$H] = ($map[$H] ?? 0) + 1;
        }
    }
    arsort($map);
    echo '<div class="panel"><div class="ph"><h3>🌈 Couleurs</h3>'
        . '<span class="muted sm" style="margin-left:auto">' . count($map) . ' couleurs distinctes</span></div>';
    echo '<p class="muted sm" style="margin:0 2px 10px">Toutes les teintes présentes dans le catalogue, triées par fréquence. Le compteur = nombre de sets qui utilisent la couleur. Lecture seule (l\'édition se fait dans la fiche d\'un vêtement).</p>';
    echo '<input type="search" id="lkcq" class="looks-search" placeholder="Filtrer un hex (ex. E8B137)…" autocomplete="off" style="max-width:320px;margin-bottom:12px">';
    echo '<div class="lk-palette">';
    foreach ($map as $hex => $cnt) {
        echo '<div class="lk-col" data-hex="' . h(strtolower($hex)) . '">'
            . '<span class="lk-col-sw" style="background:#' . h($hex) . '"></span>'
            . '<span class="lk-col-hex">#' . h($hex) . '</span>'
            . '<span class="lk-col-cnt">' . (int)$cnt . ' set' . ($cnt > 1 ? 's' : '') . '</span>'
            . '</div>';
    }
    echo '</div>';
    echo '<p class="muted sm" id="lkccount" style="margin-top:10px"></p>';
    echo '</div>';
}

/* ---------- Vue TENDANCES (sets les plus portés — lecture seule) ---------- */
function looks_wear_ranking(PDO $pdo): array {
    $slots = ['hr' => 0, 'hd' => 5, 'ch' => 10, 'lg' => 15, 'sh' => 20];
    $out = [];
    foreach ($slots as $t => $off) {
        $soff = $off + 1; $minlen = $off + 3;
        $out[$t] = [];
        try {
            $q = $pdo->query('SELECT CAST(SUBSTRING(figure, ' . $soff . ', 3) AS UNSIGNED) AS sid, COUNT(*) AS n
                              FROM users WHERE CHAR_LENGTH(figure) >= ' . $minlen . ' GROUP BY sid');
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $sid = (int)$r['sid'];
                if ($sid > 0) $out[$t][$sid] = (int)$r['n'];
            }
        } catch (Throwable $e) { /* slot ignoré */ }
    }
    return $out;
}

function looks_trend_card(array $r, int $n, int $max): string {
    $pv = figd_preview_figure($r);
    $src = '/avatar.php?figure=' . h($pv['figure']) . '&sex=' . h($pv['sex']) . '&size=s&direction=2&head=0';
    $pct = $max > 0 ? round($n / $max * 100) : 0;
    $ficheUrl = '?p=looks&set=' . h($r['gender'] . '-' . $r['type'] . '-' . $r['set_id']);
    return '<a class="lk-trend" href="' . $ficheUrl . '" title="Ouvrir la fiche">'
        . '<div class="lk-trend-ava"><img loading="lazy" alt="" src="' . $src . '" onerror="this.style.visibility=\'hidden\'"></div>'
        . '<div class="lk-trend-meta"><div class="lk-trend-nm">' . h($r['type_label']) . ' <code>#' . (int)$r['set_id'] . '</code></div>'
        . '<div class="lk-trend-bar"><span style="width:' . $pct . '%"></span></div>'
        . '<div class="lk-trend-n">' . (int)$n . ' porteur' . ($n > 1 ? 's' : '') . '</div></div></a>';
}

function looks_render_trends(array $rows, ?PDO $pdo): void {
    echo '<div class="panel"><div class="ph"><h3>📊 Tendances — sets les plus portés</h3>'
        . '<span class="muted sm" style="margin-left:auto">lecture seule · d\'après <code>users.figure</code></span></div>';
    if (!$pdo) { echo '<p class="muted">Base indisponible.</p></div>'; return; }
    $rank = looks_wear_ranking($pdo);
    $idx = [];
    foreach ($rows as $r) $idx[$r['type']][(int)$r['set_id']] = $r;
    $labels = figd_type_labels();
    $icons  = ['hr' => '💇', 'hd' => '😀', 'ch' => '👕', 'lg' => '👖', 'sh' => '👟'];

    $all = [];
    foreach ($rank as $t => $m) foreach ($m as $sid => $n) {
        if (isset($idx[$t][$sid])) $all[] = ['n' => $n, 'r' => $idx[$t][$sid]];
    }
    if (!$all) { echo '<p class="muted">Aucune donnée de port (aucune figure joueur exploitable).</p></div>'; return; }
    usort($all, function ($a, $b) { return $b['n'] <=> $a['n']; });
    $maxAll = $all[0]['n'];

    echo '<p class="muted sm" style="margin:0 2px 10px">Classement des vêtements réellement portés par les joueurs. Clique une carte pour ouvrir sa fiche. Les barres sont relatives au plus porté de chaque bloc.</p>';
    echo '<h4 class="lk-th">🏆 Top 12 — toutes catégories</h4><div class="lk-trend-grid">';
    foreach (array_slice($all, 0, 12) as $it) echo looks_trend_card($it['r'], $it['n'], $maxAll);
    echo '</div>';

    foreach ($labels as $t => $lbl) {
        $list = [];
        foreach (($rank[$t] ?? []) as $sid => $n) if (isset($idx[$t][$sid])) $list[] = ['n' => $n, 'r' => $idx[$t][$sid]];
        if (!$list) continue;
        usort($list, function ($a, $b) { return $b['n'] <=> $a['n']; });
        $maxC = $list[0]['n'];
        echo '<h4 class="lk-th">' . ($icons[$t] ?? '') . ' ' . h($lbl) . ' <span class="muted sm">(top ' . min(10, count($list)) . ' / ' . count($list) . ' portés)</span></h4><div class="lk-trend-grid">';
        foreach (array_slice($list, 0, 10) as $it) echo looks_trend_card($it['r'], $it['n'], $maxC);
        echo '</div>';
    }
    echo '</div>';
}

/* ---------- Bannière brouillon (couleurs + accès HC) ---------- */
function looks_render_draft_banner(bool $hasDraft, array $diff, array $hcDiff = []): void {
    if (!$hasDraft) {
        echo '<div class="panel lk-draft none"><div class="lk-draft-row">'
            . '<span>🟢 <b>Aucun brouillon.</b> Dès que tu modifies une couleur ou un accès HC, un brouillon est créé automatiquement — rien n\'est publié.</span>'
            . '</div></div>';
        return;
    }
    $nc = count($diff); $nh = count($hcDiff);
    $sum = [];
    if ($nc) $sum[] = $nc . ' couleur' . ($nc > 1 ? 's' : '');
    if ($nh) $sum[] = $nh . ' accès HC';
    $sumTxt = $sum ? implode(' · ', $sum) . ' modifié' . (($nc + $nh) > 1 ? 's' : '') : 'modifications';
    echo '<div class="panel lk-draft on"><div class="lk-draft-row">';
    echo '<span>📝 <b>Brouillon en cours</b> — ' . h($sumTxt) . ' <span class="muted sm">(non publié)</span></span>';
    echo '<a class="mini" style="margin-left:auto" href="?p=looks&view=publish">⚙️ Publier / versions</a>';
    echo '<form method="post" class="js" onsubmit="return confirm(\'Jeter le brouillon ? Toutes les modifications non publiées (couleurs ET accès HC) seront perdues.\');">'
        . csrf_field() . '<input type="hidden" name="action" value="looks_draft_discard"><input type="hidden" name="back" value="?p=looks">'
        . '<button class="mini danger">🗑️ Jeter le brouillon</button></form>';
    echo '</div>';
    if ($nc) {
        echo '<div class="lk-diff">';
        foreach ($diff as $d) {
            $url = '?p=looks&set=' . h($d['gender'] . '-' . $d['part'] . '-' . $d['set_id']);
            $delta = count($d['after']) - count($d['before']);
            $tag = $delta > 0 ? '+' . $delta . ' couleur' . ($delta > 1 ? 's' : '') : ($delta < 0 ? $delta . ' couleur' . ($delta < -1 ? 's' : '') : 'couleurs modifiées');
            echo '<a class="lk-diff-item" href="' . $url . '">' . ($d['gender'] === 'F' ? '♀' : '♂') . ' ' . h($d['type_label']) . ' #' . (int)$d['set_id'] . ' <span class="muted sm">(' . h($tag) . ')</span></a>';
        }
        echo '</div>';
    }
    if ($nh) {
        $lbl = ['libre' => 'Libre', 'hc' => 'HC', 'inactif' => 'Inactif'];
        echo '<div class="lk-diff">';
        foreach ($hcDiff as $d) {
            $url = '?p=looks&set=' . h($d['gender'] . '-' . $d['part'] . '-' . $d['set_id']);
            $tag = ($lbl[$d['before']] ?? $d['before']) . ' → ' . ($lbl[$d['after']] ?? $d['after']);
            echo '<a class="lk-diff-item hc" href="' . $url . '">🔐 ' . ($d['gender'] === 'F' ? '♀' : '♂') . ' ' . h($d['type_label']) . ' #' . (int)$d['set_id'] . ' <span class="muted sm">(' . h($tag) . ')</span></a>';
        }
        echo '</div>';
    }
    echo '</div>';
}

/* ---------- Vue publication / snapshots (étape 4) ---------- */
function looks_render_publish(?PDO $pdo, int $adminId): void {
    echo '<div class="panel"><div class="ph"><h3>⚙️ Publication</h3>'
        . '<a class="lnk" href="?p=looks" style="margin-left:auto">← Retour à l\'inventaire</a></div>';
    if (!$pdo) { echo '<div class="empty">Base indisponible.</div></div>'; return; }

    $pf = looks_publish_preflight($pdo, $adminId);
    if (!$pf['has_draft']) {
        echo '<p class="muted">🟢 Aucun brouillon en cours — rien à publier. Modifie des couleurs ou des accès HC, puis reviens ici.</p>';
    } else {
        echo '<div class="lk-pub-sum">';
        echo '<div class="lk-pk"><b>' . (int)$pf['color_changes'] . '</b><span>couleur(s) modifiée(s)</span></div>';
        echo '<div class="lk-pk"><b>' . (int)$pf['hc_changes'] . '</b><span>accès HC modifié(s)</span></div>';
        echo '<div class="lk-pk ' . ($pf['roundtrip_ok'] ? 'ok' : 'warn') . '"><b>' . ($pf['roundtrip_ok'] ? '✓' : '✕') . '</b><span>fidélité du format</span></div>';
        echo '</div>';
        echo '<ul class="lk-pub-list">';
        echo '<li>' . ($pf['fig_will_write'] ? '✍️ <b>figuredata.txt</b> (2 copies : gamedata + dcr) sera réécrit' : '➖ figuredata.txt inchangé (aucune couleur modifiée)') . '</li>';
        echo '<li>' . ($pf['hc_will_write'] ? '✍️ <b>réglages HC</b> (settings <code>default</code> + <code>club</code>) seront réécrits — ⚠️ <b>redémarrer l\'émulateur</b> ensuite' : '➖ réglages HC inchangés') . '</li>';
        echo '</ul>';
        if ($pf['risky']) {
            $t = [];
            foreach ($pf['risky'] as $d) $t[] = ($d['gender'] === 'F' ? '♀' : '♂') . ' ' . h($d['type_label']) . ' #' . (int)$d['set_id'];
            echo '<div class="lk-warn">⚠️ <b>' . count($pf['risky']) . ' vêtement(s) avec décalage d\'index</b> (retrait/réordre de couleurs) : une fois publié, les joueurs portant ces positions verront une autre couleur. ' . implode(', ', $t) . '.</div>';
        }
        // --- Correctif A : fraîcheur du brouillon vs LIVE ---
        $stale = $pf['stale'] ?? null;
        $isStale = is_array($stale) && !empty($stale['changed']);
        if (is_array($stale)) {
            if (!empty($stale['no_baseline'])) {
                echo '<div class="lk-warn">ℹ️ <b>Référence non rattachée</b> à ce brouillon : la détection d\'une modification du LIVE depuis son ouverture est indisponible (aucune sécurité présumée). '
                    . '<form method="post" class="js" data-reload style="display:inline" onsubmit="return confirm(\'Rattacher la référence (baseline) au brouillon courant, SANS en modifier le contenu ?\');">'
                    . csrf_field() . '<input type="hidden" name="action" value="looks_attach_baseline"><input type="hidden" name="back" value="?p=looks&view=publish"><button class="mini">📌 Rattacher la référence</button></form></div>';
            } elseif (!empty($stale['error'])) {
                echo '<div class="lk-warn">⚠️ Contrôle de fraîcheur indisponible : ' . h((string)$stale['error']) . '</div>';
            } elseif ($isStale) {
                echo '<div class="lk-warn" style="border-left-color:#c0392b;background:#fdecea">⛔ <b>Le LIVE a changé depuis l\'ouverture du brouillon</b> : ' . h(implode(', ', $stale['labels'])) . '.<br>'
                    . 'La publication normale sera <b>refusée</b>. Deux choix : <b>recharger</b> le brouillon depuis le LIVE (recommandé — tes éditions sont sauvegardées), ou <b>forcer</b> (le LIVE sera écrasé ; action tracée).'
                    . '<div style="margin-top:6px"><form method="post" class="js" data-reload style="display:inline" onsubmit="return confirm(\'Recharger le brouillon depuis le LIVE ?\\n\\nUne sauvegarde de ton brouillon actuel est créée AVANT (restaurable). Tes éditions locales seront mises de côté.\');">'
                    . csrf_field() . '<input type="hidden" name="action" value="looks_reseed"><input type="hidden" name="back" value="?p=looks&view=publish"><button class="mini">🔄 Recharger depuis le LIVE</button></form></div></div>';
            } else {
                echo '<p class="muted sm">✓ Brouillon à jour par rapport au LIVE (aucune modification externe détectée depuis son ouverture).</p>';
            }
        }

        $canPublish = $pf['roundtrip_ok'] && ($pf['fig_will_write'] || $pf['hc_will_write']);
        $confLine = $isStale
            ? 'FORCER la publication : le LIVE a changé depuis l\\\'ouverture et sera ÉCRASÉ.\\n\\nUn snapshot de l\\\'état actuel est créé AVANT (restaurable). Action tracée.'
            : 'PUBLIER le brouillon sur le LIVE ?\\n\\nUn snapshot de l\\\'état actuel est créé AVANT (restaurable à tout moment).\\n' . (int)$pf['color_changes'] . ' couleur(s) · ' . (int)$pf['hc_changes'] . ' accès HC.';
        echo '<form method="post" class="js" data-reload onsubmit="return confirm(\'' . $confLine . '\');">'
            . csrf_field() . '<input type="hidden" name="action" value="looks_publish"><input type="hidden" name="back" value="?p=looks&view=publish">';
        if ($isStale) {
            // Forçage : toggle EXPLICITE + jeton de confirmation. Jamais silencieux.
            echo '<label class="lk-force"><input type="checkbox" name="force" value="1"> Forcer malgré le LIVE modifié (écrase le LIVE, tracé)</label>'
               . '<input type="hidden" name="force_confirm" value="oui">';
        }
        echo '<button class="lk-pubbtn"' . ($canPublish ? '' : ' disabled') . '>' . ($isStale ? '🚀⚠️ Publier' : '🚀 Publier maintenant') . '</button>';
        if (!$pf['roundtrip_ok']) echo ' <span class="muted sm">Publication bloquée : format du brouillon invalide.</span>';
        elseif (!$canPublish) echo ' <span class="muted sm">Le brouillon est identique au LIVE.</span>';
        elseif ($isStale) echo ' <span class="muted sm">Sans cocher « Forcer », la publication sera refusée (LIVE modifié).</span>';
        echo '</form>';
    }
    echo '</div>';

    // --- Correctif A : sauvegardes de brouillon (restaurables, jamais purgées) ---
    $backups = looks_draft_backups_list($pdo, $adminId);
    if ($backups) {
        echo '<div class="panel"><div class="ph"><h3>💾 Sauvegardes de brouillon</h3>'
            . '<span class="muted sm" style="margin-left:auto">' . count($backups) . ' sauvegarde(s)</span></div>';
        $rlbl = ['manual' => '✋ manuel', 'reseed' => '🔄 avant rechargement', 'pre_restore' => '🛟 avant restauration'];
        echo '<table class="clean"><thead><tr><th style="width:50px">#</th><th>Date</th><th>Motif</th><th style="width:70px">HC</th><th style="width:120px">Restaurer</th></tr></thead><tbody>';
        foreach ($backups as $b) {
            echo '<tr><td class="sm"><code>' . (int)$b['id'] . '</code></td>'
                . '<td class="sm">' . h((string)$b['created_at']) . '</td>'
                . '<td class="sm">' . ($rlbl[$b['reason']] ?? h((string)$b['reason'])) . ' ' . h((string)$b['note']) . '</td>'
                . '<td class="sm">' . ((int)$b['has_hc'] ? 'oui' : '<span class="muted">—</span>') . '</td>'
                . '<td><form method="post" class="js" data-reload onsubmit="return confirm(\'Restaurer INTÉGRALEMENT le brouillon depuis la sauvegarde #' . (int)$b['id'] . ' ? Le brouillon courant sera remplacé.\');">'
                . csrf_field() . '<input type="hidden" name="action" value="looks_backup_restore"><input type="hidden" name="id" value="' . (int)$b['id'] . '"><input type="hidden" name="back" value="?p=looks&view=publish">'
                . '<button class="mini">↩️ Restaurer</button></form></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="muted sm" style="margin-top:8px">ℹ️ Les sauvegardes conservent data + accès HC + référence. Elles ne sont jamais supprimées automatiquement.</p>';
        echo '</div>';
    }

    // --- Snapshots / versions ---
    $snaps = looks_snapshots_list($pdo);
    echo '<div class="panel"><div class="ph"><h3>🗂️ Versions / snapshots</h3>'
        . '<span class="muted sm" style="margin-left:auto">' . count($snaps) . ' version(s) — restaurables</span></div>';
    if (!$snaps) {
        echo '<p class="muted">Aucun snapshot pour l\'instant. Le premier sera créé automatiquement à ta première publication.</p>';
    } else {
        $kindLbl = ['pre_publish' => '📸 avant publication', 'pre_restore' => '🛟 avant restauration', 'manual' => '✋ manuel'];
        echo '<table class="clean"><thead><tr><th style="width:50px">#</th><th>Date</th><th>Type</th><th>Note</th><th style="width:80px">HC</th><th style="width:110px">Restaurer</th></tr></thead><tbody>';
        foreach ($snaps as $s) {
            $hc = ($s['hc_default'] !== null || $s['hc_club'] !== null) ? 'oui' : '<span class="muted">défauts</span>';
            echo '<tr><td class="sm"><code>' . (int)$s['id'] . '</code></td>'
                . '<td class="sm">' . h((string)$s['created_at']) . '</td>'
                . '<td class="sm">' . ($kindLbl[$s['kind']] ?? h((string)$s['kind'])) . '</td>'
                . '<td class="sm">' . h((string)$s['note']) . '</td>'
                . '<td class="sm">' . $hc . '</td>'
                . '<td><form method="post" class="js" data-reload onsubmit="return confirm(\'Restaurer le snapshot #' . (int)$s['id'] . ' ?\\n\\nUn snapshot de sécurité de l\\\'état actuel est créé AVANT. figuredata (2 copies) + réglages HC reviendront à cet état. Redémarrer l\\\'émulateur ensuite.\');">'
                . csrf_field() . '<input type="hidden" name="action" value="looks_restore"><input type="hidden" name="id" value="' . (int)$s['id'] . '"><input type="hidden" name="back" value="?p=looks&view=publish">'
                . '<button class="mini">♻️ Restaurer</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<p class="muted sm" style="margin-top:8px">ℹ️ Chaque publication et chaque restauration crée un snapshot (état complet : figuredata 2 copies + réglages HC). Rien n\'est jamais supprimé — on peut toujours revenir en arrière.</p>';
    echo '</div>';
}

/* ---------- Fiche détaillée + éditeur de couleurs ---------- */
function looks_render_fiche(string $key, string $effText, ?PDO $pdo, array $changedKeys, ?array $hcEff = null, array $hcChangedSids = []): void {
    $parts = explode('-', $key);
    if (count($parts) !== 3) { echo '<div class="panel"><div class="empty">Vêtement introuvable.</div></div>'; return; }
    [$g, $part, $sidS] = $parts; $sid = (int)$sidS;
    $row = looks_get_set($effText, $g, $part, $sid, $pdo, $hcEff);
    if (!$row) { echo '<div class="panel"><div class="empty">Vêtement ' . h($key) . ' introuvable. <a href="?p=looks">← Retour</a></div></div>'; return; }

    $back = '?p=looks&set=' . h($g . '-' . $part . '-' . $sid);
    $pv = figd_preview_figure($row, 1);
    $coloff = looks_part_slot_offset($part) + 3; // position des 2 chiffres couleur dans la figure
    $statusBadge = $row['status'] === 'hc'
        ? '<span class="lk-badge hc">⭐ HC</span>'
        : ($row['status'] === 'inactif' ? '<span class="lk-badge warn">⏸️ Inactif</span>' : '<span class="lk-badge ok">✅ Libre</span>');

    echo '<div class="panel"><div class="ph"><h3>' . ($g === 'F' ? '♀' : '♂') . ' ' . h($row['type_label']) . ' <code>#' . $sid . '</code></h3>'
        . '<a class="lnk" href="?p=looks" style="margin-left:auto">← Retour à l\'inventaire</a></div>';

    echo '<div class="lk-fiche">';
    // Colonne aperçu
    echo '<div class="lk-fiche-prev">';
    echo '<img id="lkfig-prev" alt="aperçu" data-base="' . h($pv['figure']) . '" data-coloff="' . $coloff . '" data-sex="' . h($pv['sex']) . '" '
        . 'src="/avatar.php?figure=' . h($pv['figure']) . '&sex=' . h($pv['sex']) . '&size=b&direction=2&head=0">';
    echo '<p class="muted sm">Clique une couleur ci-contre pour l\'essayer sur le mannequin.</p>';
    $palMap = ['hr' => 'Cheveux', 'hd' => 'Peau / visage', 'ch' => 'Vêtement (haut)', 'lg' => 'Vêtement (bas)', 'sh' => 'Vêtement (chaussures)'];
    $ncv = (int)$row['ncolors'];
    $tint = $ncv > 1 ? '✅ Teintable (' . $ncv . ' couleurs)' : ($ncv === 1 ? '🔒 Couleur fixe (1)' : '— aucune');
    echo '<div class="lk-meta">'
        . '<div class="lk-meta-row"><span>🎨 Palette</span><b>' . h($palMap[$part] ?? strtoupper($part)) . '</b></div>'
        . '<div class="lk-meta-row"><span>Teinte</span><b>' . h($tint) . '</b></div>'
        . '<div class="lk-meta-row"><span>Couches sprite</span><b>' . h(implode(' · ', array_keys($row['sprites']))) . '</b></div>'
        . '</div>';

    // ----- Éditeur d'accès HC (étape 3) -----
    $cur = $row['status'];
    $hcChanged = isset($hcChangedSids[$sid]);
    echo '<div class="lk-access">';
    echo '<div class="lk-access-h">🔐 Accès ' . ($hcChanged ? '<span class="lk-chip">✎ modifié</span>' : '') . '</div>';
    echo '<div class="lk-access-cur">Statut effectif : ' . $statusBadge . '</div>';
    // 1 mini-formulaire par statut : la valeur est dans un champ caché (les
    // forms « js » partent en AJAX via FormData et NE transmettent PAS la
    // valeur d'un <button>, d'où un « statut inconnu » si on s'y fiait).
    $accForm = function (string $val, string $ico, string $lab) use ($cur, $sid, $back): string {
        if ($cur === $val) return '<button class="lk-accbtn on" disabled>' . $ico . ' ' . $lab . '</button>';
        return '<form method="post" class="js lk-inline" data-reload style="margin:0">' . csrf_field()
            . '<input type="hidden" name="action" value="looks_hc_set">'
            . '<input type="hidden" name="set_id" value="' . $sid . '">'
            . '<input type="hidden" name="status" value="' . $val . '">'
            . '<input type="hidden" name="back" value="' . h($back) . '">'
            . '<button class="lk-accbtn">' . $ico . ' ' . $lab . '</button></form>';
    };
    echo '<div class="lk-access-f">';
    echo $accForm('libre', '✅', 'Libre (tous)');
    echo $accForm('hc', '⭐', 'Club (HC)');
    echo $accForm('inactif', '⏸️', 'Inactif');
    echo '</div>';
    echo '<p class="muted sm">« Inactif » retire le set du vestiaire mais n\'efface aucune tenue déjà portée (réversible). Le HC se gère au niveau du <b>set</b>, jamais par couleur.</p>';
    echo '</div>';
    echo '</div>';

    // Colonne couleurs (éditeur)
    echo '<div class="lk-fiche-cols">';
    echo '<div class="lk-warn">⚠️ <b>Ordre = identité de la couleur.</b> « Ajouter » (en fin) et « Remplacer » (même place) sont sûrs. « Retirer » et « ▲▼ » décalent les positions : une fois publié, les joueurs portant ces couleurs en changeraient. Ici tout reste en <b>brouillon</b>.</div>';

    // Palette rapide (clic → remplit le dernier champ hex utilisé, sinon « Ajouter »)
    $palette = ['FFFFFF','DDDDDD','AAAAAA','777777','333333','000000',
                'FFCB9C','E8B187','C88F56','8C5A2B','5B3418',
                'F7E017','E8B137','F4900C','E74C3C','C0392B','9B0000',
                '2ECC71','1A7A33','0E6B3D','1ABC9C',
                '3498DB','2B5E97','2C3E80','8E44AD','C86DD7','E84BA5'];
    echo '<div class="lk-qpal"><span class="lk-qpal-lbl">🎨 Palette rapide :</span>';
    foreach ($palette as $hx) echo '<button type="button" class="lk-qpal-sw" data-hex="' . $hx . '" style="background:#' . $hx . '" title="#' . $hx . '"></button>';
    echo '</div>';

    echo '<table class="clean lk-coltab"><thead><tr><th style="width:40px">#</th><th style="width:48px">Coul.</th><th>Hex</th><th style="width:150px">Ordre</th><th style="width:90px">Retirer</th></tr></thead><tbody>';
    $n = $row['ncolors'];
    foreach ($row['colors'] as $i => $hex) {
        $idx = $i + 1;
        $hidden = csrf_field()
            . '<input type="hidden" name="gender" value="' . h($g) . '">'
            . '<input type="hidden" name="part" value="' . h($part) . '">'
            . '<input type="hidden" name="set_id" value="' . $sid . '">'
            . '<input type="hidden" name="index" value="' . $idx . '">'
            . '<input type="hidden" name="back" value="' . h($back) . '">';
        echo '<tr>';
        echo '<td class="sm muted">' . $idx . '</td>';
        echo '<td><span class="sw big lk-pick" data-idx="' . $idx . '" style="background:#' . h($hex) . '" title="Essayer cette couleur"></span></td>';
        // Remplacer (form avec input hex)
        echo '<td><form method="post" class="js lk-inline" data-reload>' . $hidden
            . '<input type="hidden" name="action" value="looks_color_replace">'
            . '<input class="lk-hex" name="hex" value="' . h($hex) . '" maxlength="7" spellcheck="false">'
            . '<button class="mini" title="Remplacer cette couleur">💾</button></form></td>';
        // Ordre ▲▼ (2 mini-forms)
        echo '<td class="lk-move">';
        echo '<form method="post" class="js lk-inline" data-reload>' . $hidden . '<input type="hidden" name="action" value="looks_color_move"><input type="hidden" name="dir" value="up"><button class="mini ghost"' . ($idx === 1 ? ' disabled' : '') . ' title="Monter">▲</button></form>';
        echo '<form method="post" class="js lk-inline" data-reload>' . $hidden . '<input type="hidden" name="action" value="looks_color_move"><input type="hidden" name="dir" value="down"><button class="mini ghost"' . ($idx === $n ? ' disabled' : '') . ' title="Descendre">▼</button></form>';
        echo '</td>';
        // Retirer
        echo '<td><form method="post" class="js lk-inline" data-reload onsubmit="return confirm(\'Retirer la couleur #' . $idx . ' ? Les positions suivantes seront décalées (brouillon).\');">' . $hidden
            . '<input type="hidden" name="action" value="looks_color_remove">'
            . '<button class="mini danger"' . ($n <= 1 ? ' disabled title="Dernière couleur"' : ' title="Retirer"') . '>✕</button></form></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // Ajouter une couleur (sûr)
    echo '<form method="post" class="js lk-add" data-reload>' . csrf_field()
        . '<input type="hidden" name="action" value="looks_color_add">'
        . '<input type="hidden" name="gender" value="' . h($g) . '"><input type="hidden" name="part" value="' . h($part) . '">'
        . '<input type="hidden" name="set_id" value="' . $sid . '"><input type="hidden" name="back" value="' . h($back) . '">';
    echo '<span>➕ Ajouter une couleur (sûr, ajoutée en fin) :</span> <input class="lk-hex" name="hex" placeholder="E8B137" maxlength="7" spellcheck="false" required> <button class="mini">Ajouter</button>';
    echo '</form>';

    echo '</div></div></div>'; // cols / fiche / panel

    // --- Qui porte ce vêtement ? (lecture seule) ---
    if ($pdo) {
        $w = looks_wearers($pdo, $part, $sid);
        echo '<div class="panel"><div class="ph"><h3>👥 Qui porte ce vêtement ?</h3>'
            . '<span class="muted sm" style="margin-left:auto">lecture seule · ' . (int)$w['total'] . ' joueur' . ($w['total'] > 1 ? 's' : '') . '</span></div>';
        if (!empty($w['error'])) {
            echo '<p class="muted">Indisponible : ' . h((string)$w['error']) . '</p>';
        } elseif ($w['total'] === 0) {
            echo '<p class="muted">🟢 Personne ne porte ce set actuellement — tu peux retirer/réordonner ses couleurs sans impact joueur.</p>';
        } else {
            $nc = (int)$row['ncolors'];
            echo '<p class="muted sm" style="margin:0 2px 8px">Ventilation par <b>position de couleur</b> — utile avant un retrait/réordre (ces positions changeraient la couleur portée par ces joueurs) :</p>';
            echo '<div class="lk-wear-ci">';
            $maxN = max($w['by_ci']);
            foreach ($w['by_ci'] as $ci => $n) {
                $hex = ($ci >= 1 && $ci <= $nc) ? $row['colors'][$ci - 1] : null;
                $sw  = $hex ? '<span class="sw" style="background:#' . h($hex) . '"></span>' : '<span class="sw sw-x">✕</span>';
                $lbl = $hex ? '#' . h($hex) : 'hors liste';
                $pct = $maxN > 0 ? round($n / $maxN * 100) : 0;
                echo '<div class="lk-wear-row">'
                    . '<span class="lk-wear-k">' . $sw . ' couleur #' . (int)$ci . ' <span class="muted sm">' . $lbl . '</span></span>'
                    . '<span class="lk-wear-bar"><span style="width:' . $pct . '%"></span></span>'
                    . '<span class="lk-wear-n">' . (int)$n . '</span>'
                    . '</div>';
            }
            echo '</div>';
            echo '<div class="lk-wear-names">';
            foreach ($w['sample'] as $s) {
                $hex = ($s['ci'] >= 1 && $s['ci'] <= $nc) ? $row['colors'][$s['ci'] - 1] : null;
                $dot = $hex ? '<span class="sw" style="background:#' . h($hex) . '"></span>' : '<span class="sw sw-x"></span>';
                echo '<span class="lk-wear-tag">' . $dot . h($s['username']) . '</span>';
            }
            echo '</div>';
            if ($w['truncated']) echo '<p class="muted sm" style="margin-top:6px">… et d\'autres (liste limitée à 60 pseudos).</p>';
        }
        echo '</div>';
    }
}

/* ---------- Liste (inventaire effectif) ---------- */
function looks_render_list(array $rows, array $changedKeys, array $hcChangedSids = []): void {
    $st = figd_stats($rows);

    echo '<div class="panel"><div class="ph"><h3>📊 Inventaire</h3>'
        . '<span class="muted sm" style="margin-left:auto">source : ' . ($changedKeys ? 'brouillon' : '<code>gamedata/figuredata.txt</code>') . '</span></div>';
    echo '<div class="looks-kpis">';
    $kpi = function (string $v, string $l, string $cls = '', string $ico = '') {
        return '<div class="kpi' . ($cls !== '' ? ' kpi--' . $cls : '') . '">' . ($ico ? '<i class="kpi-i">' . $ico . '</i>' : '')
            . '<div class="kpi-tx"><b>' . h($v) . '</b><span>' . h($l) . '</span></div></div>';
    };
    echo $kpi((string)$st['total'], 'sets au total', '', '👕');
    echo $kpi($st['M'] . ' / ' . $st['F'], 'masculin / féminin', '', '👫');
    echo $kpi((string)$st['libre'], 'libres', 'ok', '✅');
    echo $kpi((string)$st['hc'], 'réservés HC', 'hc', '⭐');
    echo $kpi((string)$st['inactif'], 'inactifs', ($st['inactif'] > 0 ? 'warn' : ''), '⏸️');
    echo $kpi((string)$st['colors_distinct'], 'couleurs distinctes', '', '🎨');
    echo '</div>';
    echo '<p class="muted sm" style="margin:6px 2px 0">ℹ️ 5 emplacements en v14 : Coiffures, Visage/Peau, Hauts, Pantalons, Chaussures. Accessoires/barbes/lunettes/chapeaux sont intégrés aux sets. Clique <b>Fiche</b> pour voir et éditer les couleurs (en brouillon).</p>';
    echo '</div>';

    echo '<div class="panel"><div class="ph"><h3>🔎 Recherche & filtres</h3>'
        . '<span class="muted sm" id="looks-count" style="margin-left:auto"></span></div>';
    echo '<div class="looks-filters">';
    echo '<input type="search" id="lkq" class="looks-search" placeholder="Rechercher un identifiant (ex. 210) ou une couleur (ex. E8B137)…" autocomplete="off">';
    echo '<select id="lkgender"><option value="">Genre : tous</option><option value="M">Masculin</option><option value="F">Féminin</option></select>';
    echo '<input type="hidden" id="lktype" value="">'; // catégorie pilotée par la sous-barre à icônes ci-dessous
    echo '<select id="lkstatus"><option value="">Statut : tous</option><option value="libre">Libre</option><option value="hc">HC</option><option value="inactif">Inactif</option></select>';
    echo '<button type="button" class="mini ghost" id="lkreset">Réinitialiser</button>';
    echo '</div></div>';

    echo '<div class="panel"><div class="ph"><h3>👕 Vêtements</h3>'
        . '<span class="muted sm" style="margin-left:auto">Avatars Habbo réels (Minerva) · coche des cartes → accès en masse · ⚡ au survol = accès rapide · « Fiche » pour les couleurs</span></div>';

    // Sous-barre de catégories (icônes) — pilote le filtre #lktype
    $typeIcons = ['hr' => '💇', 'hd' => '😀', 'ch' => '👕', 'lg' => '👖', 'sh' => '👟'];
    echo '<div class="lk-cats">';
    echo '<button type="button" class="lk-cat on" data-cat=""><span class="i">✨</span> Tous <b>' . (int)$st['total'] . '</b></button>';
    foreach (figd_type_labels() as $tk => $tl) {
        echo '<button type="button" class="lk-cat" data-cat="' . h($tk) . '"><span class="i">' . ($typeIcons[$tk] ?? '👚') . '</span> ' . h($tl) . ' <b>' . (int)($st['types'][$tk] ?? 0) . '</b></button>';
    }
    echo '</div>';

    // Barre d'outils (sélection + actions + options d'affichage) — formulaire d'accès en masse
    echo '<form id="lkbulk" method="post">' . csrf_field()
        . '<input type="hidden" name="action" value="looks_hc_bulk">'
        . '<input type="hidden" name="status" id="lkbulkstatus" value="">'
        . '<input type="hidden" name="back" value="?p=looks">';
    echo '<div class="lk-toolbar">';
    echo   '<div class="lk-tb-grp"><span id="lksel" class="lk-selcount">0 sélectionné</span></div>';
    echo   '<span class="lk-tb-div" aria-hidden="true"></span>';
    echo   '<div class="lk-tb-grp"><span class="lk-tb-lbl">Mettre en :</span>'
         . '<button class="lk-bk ok" name="status" value="libre" formnovalidate>✅ Libre</button>'
         . '<button class="lk-bk hc" name="status" value="hc" formnovalidate>⭐ HC</button>'
         . '<button class="lk-bk warn" name="status" value="inactif" formnovalidate onclick="return confirm(\'Passer la sélection en Inactif ? Les sets ne seront plus proposés au vestiaire — réversible, aucune tenue déjà portée n\\\'est effacée (brouillon).\');">⏸️ Inactif</button>'
         . '</div>';
    echo   '<span class="lk-tb-div" aria-hidden="true"></span>';
    echo   '<div class="lk-tb-grp"><button type="button" class="lk-bk ghost" id="lkcheckvis">Cocher visibles</button>'
         . '<button type="button" class="lk-bk ghost" id="lkuncheck">Tout décocher</button></div>';
    echo   '<div class="lk-tb-grp lk-tb-end"><label class="lk-toggle"><input type="checkbox" id="lkframe" checked> 🔍 Cadrer</label></div>';
    echo '</div>';

    echo '<div class="lk-grid framed">';
    foreach ($rows as $r) {
        $pv = figd_preview_figure($r);
        $k = $r['gender'] . '/' . $r['type'] . '/' . $r['set_id'];
        $changed = isset($changedKeys[$k]);
        $hcCh = isset($hcChangedSids[(int)$r['set_id']]);
        $badge = $r['status'] === 'hc'
            ? '<span class="lk-badge hc">⭐ HC</span>'
            : ($r['status'] === 'inactif' ? '<span class="lk-badge warn">⏸️ Inactif</span>' : '<span class="lk-badge ok">✅ Libre</span>');
        $search = $r['set_id'] . ' ' . implode(' ', $r['colors']);
        $sw = '';
        foreach (array_slice($r['colors'], 0, 8) as $hex) $sw .= '<span class="sw" style="background:#' . h($hex) . '" title="#' . h($hex) . '"></span>';
        if ($r['ncolors'] > 8) $sw .= '<span class="sw-more">+' . ($r['ncolors'] - 8) . '</span>';
        $ficheUrl = '?p=looks&set=' . h($r['gender'] . '-' . $r['type'] . '-' . $r['set_id']);
        $src = '/avatar.php?figure=' . h($pv['figure']) . '&sex=' . h($pv['sex']) . '&size=b&direction=2&head=0';

        $sid = (int)$r['set_id'];
        echo '<div class="lk-card" data-gender="' . h($r['gender']) . '" data-type="' . h($r['type']) . '" data-status="' . h($r['status']) . '" data-search="' . h(strtolower($search)) . '">';
        echo   '<div class="lk-card-stage">'
             . '<a class="lk-card-link" href="' . $ficheUrl . '" title="Ouvrir la fiche"><img class="lk-ava" loading="lazy" alt="" src="' . $src . '" onerror="this.style.display=\'none\';this.closest(\'.lk-card-stage\').classList.add(\'noimg\')"></a>'
             . '<label class="lk-pick" title="Sélectionner (accès en masse)"><input type="checkbox" class="lkcb" name="ids[]" value="' . $sid . '"></label>'
             . '<span class="lk-card-st">' . $badge . '</span>'
             . '<div class="lk-quick">'
             .   '<button type="button" class="lk-qi" data-sid="' . $sid . '" data-st="libre" title="Mettre en Libre (brouillon)">✅</button>'
             .   '<button type="button" class="lk-qi" data-sid="' . $sid . '" data-st="hc" title="Réserver au Club / HC (brouillon)">⭐</button>'
             .   '<button type="button" class="lk-qi" data-sid="' . $sid . '" data-st="inactif" title="Mettre en Inactif (brouillon)">⏸️</button>'
             . '</div>'
             . '</div>';
        echo   '<div class="lk-card-meta">'
             . '<div class="lk-card-nm">' . h($r['type_label'])
             . ($changed ? ' <span class="lk-chip">✎ couleur</span>' : '')
             . ($hcCh ? ' <span class="lk-chip hc">🔐 accès</span>' : '') . '</div>'
             . '<div class="lk-card-sub"><a class="lnk" href="' . $ficheUrl . '"><code>#' . (int)$r['set_id'] . '</code></a> · ' . ($r['gender'] === 'F' ? '♀ F' : '♂ M') . ' · ' . (int)$r['ncolors'] . ' coul.</div>'
             . '<div class="lk-sw">' . $sw . '</div>'
             . '<a class="mini ghost lk-card-fiche" href="' . $ficheUrl . '">✏️ Fiche</a>'
             . '</div>';
        echo '</div>';
    }
    echo '</div></form></div>';
}

/* ---------- Styles + JS (communs liste/fiche) ---------- */
function looks_render_assets(): void {
    ?>
<style>
.lk-draft{margin-top:0}
.lk-draft.on{border-left:4px solid #e0a106;background:#fffaf0}
.lk-draft.none{border-left:4px solid #cdd}
.lk-draft-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.lk-draft-row form{margin:0}
.lk-draft-row .mini{display:inline-flex;align-items:center;gap:5px;font-size:13px;padding:6px 12px;border-radius:8px;border:1px solid #d7dcea;background:#fff;color:#334;text-decoration:none;cursor:pointer;line-height:1.2;font-weight:500}
.lk-draft-row a.mini{background:#eef4fb;border-color:#9dc0e8;color:#2b5e97}
.lk-draft-row a.mini:hover{background:#e2eefb}
.lk-draft-row .mini.danger{background:#fff;border-color:#eeb8bd;color:#a3262f}
.lk-draft-row .mini.danger:hover{background:#fdecee;border-color:#e59aa1}
.lk-diff{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.lk-diff-item{font-size:12px;padding:3px 8px;border:1px solid #e6d7ad;background:#fff6e0;border-radius:16px;text-decoration:none;color:#7a5a06}
.lk-chip{font-size:11px;background:#fff3da;color:#9a6a06;border:1px solid #f0d89a;border-radius:12px;padding:1px 7px}
.looks-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;padding:4px 2px 10px}
.looks-kpis .kpi{display:flex!important;align-items:center;gap:11px;width:100%;min-width:0;box-sizing:border-box;justify-self:stretch;background:#f6f7fb;border:1px solid #e6e8f0;border-radius:12px;padding:12px 14px;text-align:left}
@media (max-width:1100px){.looks-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:640px){.looks-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.looks-kpis .kpi-i{font-size:19px;line-height:1;width:38px;height:38px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #e6e8f0;border-radius:10px;flex:0 0 auto;font-style:normal}
.looks-kpis .kpi-tx{display:flex;flex-direction:column;min-width:0}
.looks-kpis .kpi b{display:block;font-size:20px;line-height:1.15}
.looks-kpis .kpi span{font-size:12px;color:#667}
.looks-kpis .kpi--ok{background:#eefaf0;border-color:#cdebd4}
.looks-kpis .kpi--hc{background:#fff6e6;border-color:#f3dca6}
.looks-kpis .kpi--warn{background:#fdeff0;border-color:#f3c7cb}
.looks-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.looks-filters .looks-search{flex:1 1 320px;min-width:220px;padding:8px 10px;border:1px solid #ccd;border-radius:8px}
.looks-filters select{padding:8px 10px;border:1px solid #ccd;border-radius:8px;background:#fff}
.looks-table td{vertical-align:middle}
.looks-table .lk-sw{line-height:0}
.sw{display:inline-block;width:14px;height:14px;border-radius:3px;margin:1px;border:1px solid rgba(0,0,0,.18);vertical-align:middle}
.sw.big{width:26px;height:26px;cursor:pointer}
.sw.big:hover{outline:2px solid #3b7ac0;outline-offset:1px}
.sw-more{display:inline-block;font-size:11px;color:#667;margin-left:4px;vertical-align:middle;line-height:16px}
.lk-badge{display:inline-block;font-size:12px;padding:2px 8px;border-radius:20px;white-space:nowrap}
.lk-badge.ok{background:#eafaef;color:#1a7a33;border:1px solid #bfe8c8}
.lk-badge.hc{background:#fff3da;color:#9a6a06;border:1px solid #f0d89a}
.lk-badge.warn{background:#fdecee;color:#a3262f;border:1px solid #f1c3c8}
.lk-prev img{width:40px;height:auto;image-rendering:pixelated;background:#f3f4f8;border-radius:6px}
.lk-pvbtn{font-size:15px;line-height:1}
.lk-row.hide{display:none}
/* Galerie de cartes (avatars Habbo réels) */
.lk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(188px,1fr));gap:14px;margin-top:4px}
.lk-card{border:1px solid #e6e8f0;border-radius:14px;overflow:hidden;background:#fff;display:flex;flex-direction:column;transition:box-shadow .15s ease,transform .15s ease}
.lk-card:hover{box-shadow:0 8px 20px rgba(30,40,80,.10);transform:translateY(-2px)}
.lk-card-stage{position:relative;height:172px;display:flex;align-items:center;justify-content:center;background:radial-gradient(120% 90% at 50% 18%,#fbfcfe,#eef1f8);border-bottom:1px solid #eef}
.lk-card-link{display:flex;align-items:center;justify-content:center;width:100%;height:100%}
.lk-ava{height:144px;width:auto;image-rendering:pixelated;filter:drop-shadow(0 4px 6px rgba(20,24,50,.12))}
.lk-pick{position:absolute;top:8px;right:8px;background:rgba(255,255,255,.92);border:1px solid #dde;border-radius:8px;padding:4px;line-height:0;box-shadow:0 1px 3px rgba(0,0,0,.08);cursor:pointer}
.lk-card-st{position:absolute;left:8px;top:8px}
.lk-card-meta{padding:10px 12px;display:flex;flex-direction:column;gap:6px}
.lk-card-nm{font-weight:600;font-size:14px}
.lk-card-sub{font-size:12px;color:#667}
.lk-card-sub .lnk{text-decoration:none}
.lk-card .lk-sw{line-height:0}
.lk-card-fiche{align-self:flex-start;margin-top:2px;text-decoration:none}
.lk-card.hide{display:none}
/* Sous-barre catégories + options d'aperçu */
.lk-cats{display:flex;flex-wrap:wrap;gap:8px;padding:2px 2px 10px}
.lk-cat{display:inline-flex;align-items:center;gap:6px;font-size:13px;padding:7px 12px;border:1px solid #dfe3ee;border-radius:999px;background:#fff;color:#334;cursor:pointer;transition:background .12s ease,border-color .12s ease,color .12s ease}
.lk-cat .i{font-size:15px;line-height:1}
.lk-cat b{font-size:12px;color:#8a93a6;font-weight:600}
.lk-cat:hover{border-color:#b9c4e0;background:#f6f8fe}
.lk-cat.on{background:#eef4fb;border-color:#3b7ac0;color:#2b5e97}
.lk-cat.on b{color:#3b7ac0}
.lk-viewopts{display:flex;justify-content:flex-end;padding:0 2px 8px}
.lk-toggle{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#667;cursor:pointer;user-select:none}
/* Cadrage ciblé par type (mode « framed ») */
.lk-grid.framed .lk-card-stage{overflow:hidden}
.lk-grid.framed .lk-ava{transition:transform .2s ease}
.lk-grid.framed .lk-card[data-type="hr"] .lk-ava,
.lk-grid.framed .lk-card[data-type="hd"] .lk-ava{transform-origin:50% 20%;transform:scale(1.55)}
.lk-grid.framed .lk-card[data-type="ch"] .lk-ava{transform-origin:50% 46%;transform:scale(1.62)}
.lk-grid.framed .lk-card[data-type="lg"] .lk-ava{transform-origin:50% 74%;transform:scale(1.78)}
.lk-grid.framed .lk-card[data-type="sh"] .lk-ava{transform-origin:50% 95%;transform:scale(1.85)}
/* Repli si l'imageur Minerva ne répond pas */
.lk-card-stage.noimg::after{content:'👤';font-size:58px;line-height:1;opacity:.22}
/* Barre d'accès rapide (apparaît au survol) */
.lk-quick{position:absolute;left:0;right:0;bottom:0;display:flex;gap:6px;justify-content:center;padding:7px 6px;background:linear-gradient(transparent,rgba(255,255,255,.96) 42%);opacity:0;transform:translateY(100%);transition:opacity .15s ease,transform .15s ease}
.lk-card:hover .lk-quick{opacity:1;transform:none}
.lk-qi{font-size:14px;line-height:1;padding:5px 8px;border:1px solid #d7dcea;border-radius:8px;background:#fff;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.06)}
.lk-qi:hover{border-color:#3b7ac0;background:#f3f8ff;transform:translateY(-1px)}
/* Onglets */
.lk-tabs{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 12px;border-bottom:2px solid #e6e8f0}
.lk-tab{display:inline-flex;align-items:center;gap:6px;font-size:14px;font-weight:600;padding:9px 16px;color:#556;text-decoration:none;border:2px solid transparent;border-bottom:none;border-radius:10px 10px 0 0;margin-bottom:-2px}
.lk-tab:hover{background:#f6f8fe;color:#2b5e97}
.lk-tab.on{color:#2b5e97;background:#fff;border-color:#e6e8f0;border-bottom-color:#fff}
/* Tableau */
.lk-tblwrap{overflow-x:auto}
.lk-tbl{width:100%}
.lk-tbl td{vertical-align:middle}
.lk-tbl .lk-sw{line-height:0}
.lk-tava{height:46px;width:auto;image-rendering:pixelated;background:#f3f4f8;border-radius:6px}
.lk-trow:hover{background:#f8f9fd}
/* Studio */
.lk-studio{display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start}
.st-stage{flex:0 0 300px;max-width:300px;position:sticky;top:10px;display:flex;flex-direction:column;align-items:center;gap:10px;background:radial-gradient(120% 80% at 50% 14%,#fbfcfe,#eef1f8);border:1px solid #e6e8f0;border-radius:16px;padding:14px}
.st-rot{display:flex;align-items:center;gap:8px;width:100%;justify-content:space-between}
.st-title{flex:1;text-align:center}
.st-ava-wrap{height:300px;display:flex;align-items:center;justify-content:center}
#stava{height:280px;width:auto;image-rendering:pixelated;filter:drop-shadow(0 6px 10px rgba(20,24,50,.16))}
.st-colors{display:flex;flex-wrap:wrap;gap:5px;justify-content:center;min-height:26px}
.st-colors .sw{width:24px;height:24px;cursor:pointer;border-radius:5px}
.st-colors .sw.on{outline:3px solid #3b7ac0;outline-offset:1px}
.st-picker{flex:1 1 360px;min-width:300px}
.st-controls{display:flex;flex-direction:column;gap:10px;margin-bottom:12px}
.st-cats{display:flex;flex-wrap:wrap;gap:6px}
.st-gender{display:flex;gap:6px}
.st-g{cursor:pointer}
.st-g.on{background:#eef4fb;border-color:#3b7ac0;color:#2b5e97}
.st-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:8px;max-height:560px;overflow-y:auto;padding:6px;border:1px solid #eef0f6;border-radius:12px;background:#fafbfe}
.st-item{border:1px solid #e6e8f0;border-radius:10px;background:#fff;cursor:pointer;padding:6px 4px 4px;text-align:center;transition:border-color .12s ease,box-shadow .12s ease}
.st-item:hover{border-color:#b9c4e0;box-shadow:0 3px 8px rgba(30,40,80,.08)}
.st-item.on{border-color:#3b7ac0;box-shadow:0 0 0 2px #cfe0f5}
.st-item img{height:78px;width:auto;image-rendering:pixelated;display:block;margin:0 auto}
.st-item .st-id{font-size:11px;color:#778;margin-top:3px}
.st-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:3px;vertical-align:middle}
.st-dot.ok{background:#1a7a33}
.st-dot.hc{background:#e0a106}
.st-dot.inactif{background:#c0392b}
.st-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:center;margin-top:4px}
/* Couleurs (palette) */
.lk-palette{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px}
.lk-col{display:flex;align-items:center;gap:8px;border:1px solid #e6e8f0;border-radius:10px;padding:7px 10px;background:#fff}
.lk-col-sw{width:28px;height:28px;border-radius:6px;border:1px solid rgba(0,0,0,.15);flex:0 0 auto}
.lk-col-hex{font-family:ui-monospace,monospace;font-size:12px;color:#334}
.lk-col-cnt{font-size:11px;color:#778;margin-left:auto;white-space:nowrap}
/* Fiche */
.lk-fiche{display:flex;gap:18px;flex-wrap:wrap}
.lk-fiche-prev{flex:0 0 180px}
.lk-fiche-prev img{width:100%;max-width:170px;height:auto;image-rendering:pixelated;display:block;margin:0 auto;padding:14px 10px;background:radial-gradient(120% 90% at 50% 18%,#fbfcfe,#eef1f8);border:1px solid #e6e8f0;border-radius:14px;filter:drop-shadow(0 4px 6px rgba(20,24,50,.10))}
.lk-fiche-cols{flex:1 1 420px;min-width:300px}
.lk-meta{margin-top:10px;display:flex;flex-direction:column;gap:4px}
.lk-warn{background:#fff8e6;border:1px solid #f0dca0;border-radius:8px;padding:8px 10px;font-size:12px;margin-bottom:10px}
.lk-coltab td{vertical-align:middle}
.lk-inline{display:inline-flex;align-items:center;gap:4px;margin:0}
.lk-hex{width:90px;padding:4px 6px;border:1px solid #ccd;border-radius:6px;font-family:ui-monospace,monospace;font-size:12px}
.lk-cp{width:30px;height:30px;padding:0;border:1px solid #ccd;border-radius:6px;background:none;cursor:pointer;flex:0 0 auto}
.lk-qpal{display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin-bottom:10px}
.lk-qpal-lbl{font-size:12px;color:#667;margin-right:2px}
.lk-qpal-sw{width:22px;height:22px;border:1px solid rgba(0,0,0,.2);border-radius:5px;cursor:pointer;padding:0}
.lk-qpal-sw:hover{outline:2px solid #3b7ac0;outline-offset:1px}
/* Qui porte ça ? */
.lk-wear-ci{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
.lk-wear-row{display:flex;align-items:center;gap:10px}
.lk-wear-k{flex:0 0 210px;font-size:13px;display:flex;align-items:center;gap:6px}
.lk-wear-bar{flex:1;height:10px;background:#eef0f6;border-radius:6px;overflow:hidden}
.lk-wear-bar>span{display:block;height:100%;background:#3b7ac0}
.lk-wear-n{flex:0 0 40px;text-align:right;font-weight:600;font-size:13px}
.lk-wear-names{display:flex;flex-wrap:wrap;gap:6px}
.lk-wear-tag{display:inline-flex;align-items:center;gap:5px;font-size:12px;background:#f6f7fb;border:1px solid #e6e8f0;border-radius:14px;padding:3px 9px}
.sw-x{display:inline-flex;align-items:center;justify-content:center;font-size:9px;color:#a33;background:#fdecee}
.lk-meta-row{display:flex;justify-content:space-between;gap:10px;font-size:12px;color:#556}
.lk-meta-row b{color:#223;font-weight:600;text-align:right}
/* Tendances */
.lk-th{margin:18px 2px 8px;font-size:15px}
.lk-trend-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px;align-items:stretch}
.lk-trend{display:flex;gap:10px;align-items:center;min-width:0;overflow:hidden;border:1px solid #e6e8f0;border-radius:12px;background:#fff;padding:8px 10px;text-decoration:none;color:inherit;transition:box-shadow .12s ease}
.lk-trend:hover{box-shadow:0 4px 12px rgba(30,40,80,.1)}
.lk-trend-ava{flex:0 0 auto;width:44px;height:56px;display:flex;align-items:center;justify-content:center;background:radial-gradient(120% 90% at 50% 15%,#fbfcfe,#eef1f8);border-radius:8px;overflow:hidden}
.lk-trend-ava img{height:68px;width:auto;image-rendering:pixelated}
.lk-trend-meta{flex:1 1 auto;min-width:0;overflow:hidden}
.lk-trend-nm{font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lk-trend-nm code{font-size:11px}
.lk-trend-bar{height:8px;background:#eef0f6;border-radius:5px;overflow:hidden;margin:5px 0 3px}
.lk-trend-bar>span{display:block;height:100%;background:#3b7ac0}
.lk-trend-n{font-size:11px;color:#667}
.lk-move{white-space:nowrap}
.lk-add{margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.mini.danger{color:#a3262f;border-color:#eeb8bd}
.lk-chip.hc{background:#fff6e6;color:#8a5a00;border-color:#f0d89a}
.lk-diff-item.hc{border-color:#f0d89a;background:#fff6e0;color:#8a5a00}
/* Barre d'outils groupée */
.lk-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px;padding:10px 12px;margin:2px 0 14px;background:#f6f7fb;border:1px solid #e6e8f0;border-radius:12px}
.lk-tb-grp{display:flex;align-items:center;gap:8px}
.lk-tb-end{margin-left:auto}
.lk-tb-lbl{font-size:12px;color:#667;white-space:nowrap}
.lk-tb-div{width:1px;height:22px;background:#e2e5f0;flex:0 0 auto}
.lk-selcount{font-size:13px;font-weight:600;color:#334;background:#fff;border:1px solid #e0e3ee;border-radius:8px;padding:6px 10px;white-space:nowrap}
.lkcb{width:16px;height:16px;cursor:pointer}
.lk-bk{font-size:13px;font-weight:600;padding:6px 12px;border-radius:8px;border:1px solid #d7dcea;background:#fff;color:#334;cursor:pointer;line-height:1.2}
.lk-bk.ok{background:#eafaef;border-color:#bfe8c8;color:#1a7a33}
.lk-bk.ok:hover{background:#dcf5e5}
.lk-bk.hc{background:#fff3da;border-color:#f0d89a;color:#9a6a06}
.lk-bk.hc:hover{background:#ffedc6}
.lk-bk.warn{background:#fdecee;border-color:#f1c3c8;color:#a3262f}
.lk-bk.warn:hover{background:#fbdce0}
.lk-bk.ghost{font-weight:500;color:#556;background:#fff}
.lk-bk.ghost:hover{background:#f6f8fe;border-color:#b9c4e0}
/* Éditeur d'accès (fiche) */
.lk-access{margin-top:12px;padding-top:10px;border-top:1px solid #eef}
.lk-access-h{font-weight:600;margin-bottom:4px}
.lk-access-cur{font-size:13px;margin-bottom:8px}
.lk-access-f{display:flex;flex-direction:column;gap:6px;margin:0}
.lk-access-f .lk-inline{display:flex;margin:0;width:100%}
.lk-access-f .lk-accbtn{width:100%}
.lk-accbtn{font-size:13px;padding:7px 10px;border:1px solid #ccd;border-radius:7px;background:#fff;color:#334;cursor:pointer;text-align:center;white-space:nowrap}
.lk-accbtn:hover{border-color:#3b7ac0}
.lk-accbtn.on{background:#eef4fb;border-color:#3b7ac0;color:#2b5e97;font-weight:600;cursor:default}
/* Publication */
.lk-pub-sum{display:flex;gap:10px;flex-wrap:wrap;margin:4px 0 12px}
.lk-pk{flex:1 1 150px;min-width:150px;background:#f6f7fb;border:1px solid #e6e8f0;border-radius:10px;padding:10px 12px;text-align:center}
.lk-pk b{display:block;font-size:22px;line-height:1.1}
.lk-pk span{font-size:12px;color:#667}
.lk-pk.ok{background:#eefaf0;border-color:#cdebd4}
.lk-pk.warn{background:#fdeff0;border-color:#f3c7cb}
.lk-pub-list{margin:4px 0 12px;padding-left:18px;font-size:14px}
.lk-pub-list li{margin:3px 0}
.lk-pubbtn{font-size:14px;padding:9px 16px;border:1px solid #1a7a33;border-radius:9px;background:#1e9e42;color:#fff;cursor:pointer;font-weight:600}
.lk-pubbtn:hover{background:#188537}
.lk-pubbtn:disabled{background:#cfd4dd;border-color:#bcc3cf;cursor:not-allowed}
.lk-force{display:inline-flex;align-items:center;gap:6px;margin-right:10px;padding:5px 9px;border:1px solid #e0a800;border-radius:7px;background:#fff8e1;font-size:13px;color:#8a6d00;cursor:pointer}
@media (prefers-color-scheme:dark){
  .lk-toolbar{background:#1a1e2c;border-color:#2c3144}
  .lk-tb-lbl{color:#9aa}
  .lk-tb-div{background:#2c3144}
  .lk-selcount{background:#171a26;border-color:#333a52;color:#dde}
  .lk-access{border-color:#2c3144}
  .lk-accbtn{background:#171a26;border-color:#333a52;color:#dde}
  .lk-accbtn.on{background:#1f2a3b;border-color:#3b7ac0;color:#bcd}
  .lk-pk{background:#1e2130;border-color:#2c3144}
  .lk-pk span{color:#9aa}
  .lk-draft.on{background:#2a2410}
  .looks-kpis .kpi{background:#1e2130;border-color:#2c3144}
  .looks-kpis .kpi span{color:#9aa}
  .looks-kpis .kpi-i{background:#171a26;border-color:#2c3144}
  .looks-filters .looks-search,.looks-filters select,.lk-hex{background:#171a26;border-color:#333a52;color:#dde}
  .lk-card{background:#171a26;border-color:#2c3144}
  .lk-card-stage{background:radial-gradient(120% 90% at 50% 18%,#1d2130,#141726);border-color:#2c3144}
  .lk-card-sub{color:#9aa}
  .lk-pick{background:rgba(23,26,38,.9);border-color:#333a52}
  .lk-card:hover{box-shadow:0 8px 20px rgba(0,0,0,.35)}
  .lk-cat{background:#171a26;border-color:#2c3144;color:#cdd}
  .lk-cat b{color:#8892a6}
  .lk-cat:hover{background:#1d2130;border-color:#3b4b6b}
  .lk-cat.on{background:#1f2a3b;border-color:#3b7ac0;color:#bcd}
  .lk-cat.on b{color:#7fb0e6}
  .lk-quick{background:linear-gradient(transparent,rgba(20,23,38,.96) 42%)}
  .lk-qi{background:#171a26;border-color:#333a52;color:#dde}
  .lk-qi:hover{background:#1f2a3b;border-color:#3b7ac0}
  .lk-fiche-prev img{background:radial-gradient(120% 90% at 50% 18%,#1d2130,#141726);border-color:#2c3144}
  .lk-bk{background:#171a26;border-color:#333a52;color:#dde}
  .lk-bk.ok{background:#15271b;border-color:#2b5a39;color:#8ee0a4}
  .lk-bk.hc{background:#2a2410;border-color:#5a4a1e;color:#e8c878}
  .lk-bk.warn{background:#2a1618;border-color:#5a2b31;color:#f0a0a8}
  .lk-bk.ghost{background:#171a26;border-color:#333a52;color:#cdd}
  .lk-tabs{border-color:#2c3144}
  .lk-tab{color:#9aa}
  .lk-tab:hover{background:#1d2130;color:#bcd}
  .lk-tab.on{color:#bcd;background:#12151f;border-color:#2c3144;border-bottom-color:#12151f}
  .lk-tava{background:#1d2130}
  .lk-trow:hover{background:#1b1f2d}
  .st-stage{background:radial-gradient(120% 80% at 50% 14%,#1d2130,#141726);border-color:#2c3144}
  .st-strip{background:#141726;border-color:#2c3144}
  .st-item{background:#171a26;border-color:#2c3144}
  .st-item .st-id{color:#9aa}
  .st-g.on{background:#1f2a3b;border-color:#3b7ac0;color:#bcd}
  .lk-col{background:#171a26;border-color:#2c3144}
  .lk-col-hex{color:#dde}
  .lk-col-cnt{color:#9aa}
  .lk-cp{border-color:#333a52}
  .lk-qpal-lbl{color:#9aa}
  .lk-wear-bar{background:#2c3144}
  .lk-wear-tag{background:#171a26;border-color:#2c3144}
  .lk-meta-row{color:#9aa}
  .lk-meta-row b{color:#dde}
  .lk-trend{background:#171a26;border-color:#2c3144}
  .lk-trend-ava{background:radial-gradient(120% 90% at 50% 15%,#1d2130,#141726)}
  .lk-trend-bar{background:#2c3144}
  .lk-trend-n{color:#9aa}
}
</style>
<script>
(function(){
  // Filtres liste
  var q=document.getElementById('lkq');
  if(q){
    var g=document.getElementById('lkgender'),t=document.getElementById('lktype'),s=document.getElementById('lkstatus'),
        reset=document.getElementById('lkreset'),count=document.getElementById('looks-count'),
        cats=[].slice.call(document.querySelectorAll('.lk-cat')),
        rows=[].slice.call(document.querySelectorAll('.lk-card'));
    function apply(){
      var qq=(q.value||'').trim().toLowerCase(),gg=g.value,tt=t.value,ss=s.value,n=0;
      rows.forEach(function(r){
        var ok=(!qq||r.dataset.search.indexOf(qq)>=0)&&(!gg||r.dataset.gender===gg)&&(!tt||r.dataset.type===tt)&&(!ss||r.dataset.status===ss);
        r.classList.toggle('hide',!ok); if(ok)n++;
      });
      count.textContent=n+' / '+rows.length+' vêtements';
    }
    function syncCats(){cats.forEach(function(x){x.classList.toggle('on',x.dataset.cat===t.value);});}
    [q,g,t,s].forEach(function(el){el.addEventListener('input',apply);el.addEventListener('change',apply);});
    cats.forEach(function(b){b.addEventListener('click',function(){t.value=b.dataset.cat;syncCats();apply();});});
    reset.addEventListener('click',function(){q.value='';g.value='';t.value='';s.value='';syncCats();apply();q.focus();});
    // Cadrage ciblé on/off
    var frame=document.getElementById('lkframe'),grid=document.querySelector('.lk-grid');
    if(frame&&grid)frame.addEventListener('change',function(){grid.classList.toggle('framed',frame.checked);});
    apply();
  }
  // Sélection groupée (accès HC en masse)
  var cbs=[].slice.call(document.querySelectorAll('.lkcb'));
  if(cbs.length){
    var sel=document.getElementById('lksel');
    function upd(){var n=cbs.filter(function(c){return c.checked;}).length; if(sel)sel.textContent=n+' sélectionné'+(n>1?'s':'');}
    cbs.forEach(function(c){c.addEventListener('change',upd);});
    var cv=document.getElementById('lkcheckvis');
    if(cv)cv.addEventListener('click',function(){cbs.forEach(function(c){var cd=c.closest('.lk-card'); if(cd&&!cd.classList.contains('hide'))c.checked=true;});upd();});
    var uc=document.getElementById('lkuncheck');
    if(uc)uc.addEventListener('click',function(){cbs.forEach(function(c){c.checked=false;});upd();});
    // Accès rapide par carte : réutilise le formulaire d'accès en masse (endpoint looks_hc_bulk)
    var bulk=document.getElementById('lkbulk'),bstat=document.getElementById('lkbulkstatus');
    document.querySelectorAll('.lk-qi').forEach(function(b){
      b.addEventListener('click',function(e){
        e.preventDefault();
        if(!bulk||!bstat)return;
        var sid=parseInt(b.dataset.sid,10),st=b.dataset.st;
        if(st==='inactif'&&!confirm('Passer ce vêtement en Inactif ? Il ne sera plus proposé au vestiaire — réversible, aucune tenue déjà portée n\'est effacée (brouillon).'))return;
        cbs.forEach(function(c){c.checked=(parseInt(c.value,10)===sid);});
        bstat.value=st;
        bulk.submit();
      });
    });
    upd();
  }
  // Fiche : clic couleur -> aperçu
  var prev=document.getElementById('lkfig-prev');
  if(prev){
    document.querySelectorAll('.lk-pick').forEach(function(el){
      el.addEventListener('click',function(){
        var idx=parseInt(el.dataset.idx,10),base=prev.dataset.base,off=parseInt(prev.dataset.coloff,10);
        var cc=('0'+idx).slice(-2);
        var fig=base.substring(0,off)+cc+base.substring(off+2);
        prev.src='/avatar.php?figure='+encodeURIComponent(fig)+'&sex='+encodeURIComponent(prev.dataset.sex)+'&size=b&direction=2&head=0';
      });
    });
  }
  // Fiche : color-picker natif synchronisé + palette rapide
  if(document.querySelector('.lk-hex')){
    var lastHex=null;
    [].forEach.call(document.querySelectorAll('.lk-hex'),function(inp){
      var cp=document.createElement('input');cp.type='color';cp.className='lk-cp';
      var init=(inp.value||inp.getAttribute('placeholder')||'000000').replace('#','');
      if(/^[0-9a-fA-F]{6}$/.test(init)){try{cp.value='#'+init;}catch(e){}}
      inp.parentNode.insertBefore(cp,inp);
      cp.addEventListener('input',function(){inp.value=cp.value.slice(1).toUpperCase();lastHex=inp;});
      inp.addEventListener('input',function(){var v=inp.value.replace('#','');if(/^[0-9a-fA-F]{6}$/.test(v)){try{cp.value='#'+v;}catch(e){}}});
      inp.addEventListener('focus',function(){lastHex=inp;});
    });
    [].forEach.call(document.querySelectorAll('.lk-qpal-sw'),function(b){
      b.addEventListener('click',function(){
        var tgt=lastHex||document.querySelector('.lk-add .lk-hex')||document.querySelector('.lk-hex');
        if(!tgt)return;
        tgt.value=b.dataset.hex;
        tgt.dispatchEvent(new Event('input'));
        tgt.focus();
      });
    });
  }
  // Studio d'essayage — tenue persistante (chaque catégorie garde son set)
  var stRoot=document.getElementById('studio');
  if(stRoot){
    var DATA={}; try{DATA=JSON.parse(document.getElementById('studio-data').textContent||'{}');}catch(e){DATA={};}
    var SD=DATA.sets||[], DEF=DATA.defaults||{};
    var stAva=document.getElementById('stava'),stColors=document.getElementById('stcolors'),
        stStrip=document.getElementById('ststrip'),stTitle=document.getElementById('sttitle'),
        stFiche=document.getElementById('stfiche'),stReset=document.getElementById('streset'),
        stRandom=document.getElementById('strandom'),stCopy=document.getElementById('stcopy');
    var cat='ch',gender='M',dir=2,cur=null;
    var look={M:DEF.M||'',F:DEF.F||''};   // figure 25 car. persistante par genre
    var worn={M:{},F:{}};                 // slot -> set porté
    var wornCI={M:{},F:{}};               // slot -> index couleur
    function pad2(n){return ('0'+n).slice(-2);}
    function updAva(){stAva.src='/avatar.php?figure='+encodeURIComponent(look[gender])+'&sex='+encodeURIComponent(gender)+'&size=b&direction='+dir+'&head=0';}
    function setSlot(d,ci){
      var off=d.coloff-3;                 // début du slot (5 car. : set 3 + couleur 2)
      var slot=d.fig.substr(off,5).substring(0,3)+pad2(ci);
      look[gender]=look[gender].substring(0,off)+slot+look[gender].substring(off+5);
    }
    function renderColors(){
      stColors.innerHTML='';
      if(!cur){stColors.innerHTML='<span class="muted sm">Clique un set pour changer sa couleur</span>';return;}
      var ci=wornCI[gender][cur.t]||1;
      cur.colors.forEach(function(hex,i){
        var s=document.createElement('span');
        s.className='sw'+((i+1)===ci?' on':'');s.style.background='#'+hex;s.title='#'+hex;
        s.addEventListener('click',function(){wornCI[gender][cur.t]=i+1;setSlot(cur,i+1);renderColors();updAva();});
        stColors.appendChild(s);
      });
    }
    function highlightStrip(){
      var w=worn[gender][cat];
      [].forEach.call(stStrip.querySelectorAll('.st-item'),function(x){x.classList.toggle('on',!!w&&parseInt(x.dataset.sid,10)===w.sid);});
    }
    function setTitle(){stTitle.textContent=cur?((cur.g==='F'?'♀ ':'♂ ')+cur.label+' #'+cur.sid):('Tenue '+(gender==='F'?'féminine':'masculine'));}
    function wear(d){
      worn[gender][d.t]=d;
      if(!wornCI[gender][d.t])wornCI[gender][d.t]=1;
      setSlot(d,wornCI[gender][d.t]);
      cur=d; stFiche.href='?p=looks&set='+d.g+'-'+d.t+'-'+d.sid;
      setTitle();renderColors();highlightStrip();updAva();
    }
    function renderStrip(){
      stStrip.innerHTML='';
      var list=SD.filter(function(d){return d.t===cat&&d.g===gender;});
      if(!list.length){stStrip.innerHTML='<p class="muted sm" style="padding:8px;grid-column:1/-1">Aucun set.</p>';cur=null;setTitle();renderColors();return;}
      list.forEach(function(d){
        var it=document.createElement('div');it.className='st-item';it.dataset.sid=d.sid;
        var img=new Image();img.loading='lazy';img.alt='';
        img.src='/avatar.php?figure='+encodeURIComponent(d.fig)+'&sex='+encodeURIComponent(d.sex)+'&size=s&direction=2&head=0';
        img.onerror=function(){this.style.visibility='hidden';};
        var id=document.createElement('div');id.className='st-id';id.innerHTML='<span class="st-dot '+d.status+'"></span>#'+d.sid;
        it.appendChild(img);it.appendChild(id);
        it.addEventListener('click',function(){wear(d);});
        stStrip.appendChild(it);
      });
      cur=worn[gender][cat]||null;        // refléter le set déjà porté, sans toucher la tenue
      if(cur)stFiche.href='?p=looks&set='+cur.g+'-'+cur.t+'-'+cur.sid;
      setTitle();renderColors();highlightStrip();
    }
    [].forEach.call(stRoot.querySelectorAll('.st-cat'),function(b){
      b.addEventListener('click',function(){cat=b.dataset.cat;[].forEach.call(stRoot.querySelectorAll('.st-cat'),function(x){x.classList.toggle('on',x===b);});renderStrip();});
    });
    [].forEach.call(stRoot.querySelectorAll('.st-g'),function(b){
      b.addEventListener('click',function(){gender=b.dataset.g;[].forEach.call(stRoot.querySelectorAll('.st-g'),function(x){x.classList.toggle('on',x===b);});renderStrip();updAva();});
    });
    document.getElementById('stleft').addEventListener('click',function(){dir=(dir+7)%8;updAva();});
    document.getElementById('stright').addEventListener('click',function(){dir=(dir+1)%8;updAva();});
    if(stReset)stReset.addEventListener('click',function(){look[gender]=DEF[gender]||'';worn[gender]={};wornCI[gender]={};cur=null;renderStrip();updAva();});
    if(stRandom)stRandom.addEventListener('click',function(){
      ['hr','hd','ch','lg','sh'].forEach(function(slot){
        var list=SD.filter(function(d){return d.t===slot&&d.g===gender;});
        if(!list.length)return;
        var d=list[Math.floor(Math.random()*list.length)];
        var ci=1+Math.floor(Math.random()*Math.max(1,d.colors.length));
        worn[gender][slot]=d;wornCI[gender][slot]=ci;setSlot(d,ci);
      });
      renderStrip();updAva();
    });
    if(stCopy)stCopy.addEventListener('click',function(){
      var fig=look[gender],lbl=stCopy.textContent;
      function ok(){stCopy.textContent='✓ Copié';setTimeout(function(){stCopy.textContent=lbl;},1200);}
      if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(fig).then(ok,function(){window.prompt('Chaîne figure :',fig);});}
      else{window.prompt('Chaîne figure :',fig);}
    });
    renderStrip();updAva();
  }
  // Tableau : filtre + compteur
  var tq=document.getElementById('lktq');
  if(tq){
    var trs=[].slice.call(document.querySelectorAll('.lk-trow')),tc=document.getElementById('lktcount');
    function tapply(){
      var v=tq.value.trim().toLowerCase(),n=0;
      trs.forEach(function(tr){var ok=!v||tr.dataset.search.indexOf(v)>=0;tr.style.display=ok?'':'none';if(ok)n++;});
      if(tc)tc.textContent=n+' / '+trs.length+' vêtements';
    }
    tq.addEventListener('input',tapply);tapply();
  }
  // Couleurs : filtre hex
  var cq=document.getElementById('lkcq');
  if(cq){
    var cols=[].slice.call(document.querySelectorAll('.lk-col')),cc=document.getElementById('lkccount');
    function capply(){
      var v=cq.value.trim().toLowerCase().replace('#',''),n=0;
      cols.forEach(function(el){var ok=!v||el.dataset.hex.indexOf(v)>=0;el.style.display=ok?'':'none';if(ok)n++;});
      if(cc)cc.textContent=n+' / '+cols.length+' couleurs';
    }
    cq.addEventListener('input',capply);capply();
  }
})();
</script>
    <?php
}

page_looks();
