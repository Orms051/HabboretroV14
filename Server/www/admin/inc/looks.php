<?php
/* =====================================================================
 * admin/inc/looks.php  —  « Apparence » (vêtements & couleurs)  ÉTAPE 1
 * ---------------------------------------------------------------------
 * SOCLE LECTURE SEULE : inventaire des sets de figuredata.txt, recherche,
 * filtres (genre / catégorie / statut HC), swatches couleurs, aperçu
 * Minerva au clic (avatar.php). AUCUNE écriture (fichier ou SQL).
 * Le moteur (looks_engine.php) est requis par index.php.
 * ===================================================================== */
require_once __DIR__ . '/looks_engine.php';

function page_looks(): void {
    $pdo = db();

    // Inventaire + synthèse (HC lus en base, repli sur les défauts Kepler)
    try {
        $rows = figd_inventory($pdo);
    } catch (Throwable $e) {
        page_title('🧥 Apparence', 'Erreur de lecture de figuredata.');
        echo '<div class="panel"><div class="empty">❌ Impossible de lire figuredata.txt : ' . h($e->getMessage()) . '</div></div>';
        return;
    }
    $st  = figd_stats($rows);
    $hc  = figd_hc_lists($pdo);
    $fs  = figd_files_status();
    $rtDetail = null; $rtOk = figd_roundtrip_ok('gamedata', $rtDetail);

    page_title('🧥 Apparence — vêtements & couleurs',
        'Inventaire en LECTURE SEULE des vêtements du client (figuredata.txt). Les joueurs choisissent leurs tenues en jeu ; ici on administrera couleurs et droits HC. Rien n\'est modifiable à cette étape.');

    /* ---------- Bandeau synthèse ---------- */
    echo '<div class="panel"><div class="ph"><h3>📊 Inventaire</h3>'
        . '<span class="muted sm" style="margin-left:auto">source : <code>gamedata/figuredata.txt</code></span></div>';
    echo '<div class="looks-kpis">';
    $kpi = function (string $v, string $l, string $cls = '') { return '<div class="kpi ' . $cls . '"><b>' . h($v) . '</b><span>' . h($l) . '</span></div>'; };
    echo $kpi((string)$st['total'], 'sets au total');
    echo $kpi($st['M'] . ' / ' . $st['F'], 'masculin / féminin');
    echo $kpi((string)$st['libre'], 'libres', 'ok');
    echo $kpi((string)$st['hc'], 'réservés HC', 'hc');
    echo $kpi((string)$st['inactif'], 'inactifs', ($st['inactif'] > 0 ? 'warn' : ''));
    echo $kpi((string)$st['colors_distinct'], 'couleurs distinctes');
    echo '</div>';

    // Lignes d'état (intégrité / fidélité / modèle)
    echo '<div class="looks-notes">';
    echo '<p>' . ($fs['synced'] ? '✅' : '⚠️') . ' Les deux copies de figuredata '
        . ($fs['synced'] ? 'sont <b>synchronisées</b>' : 'ne sont <b>pas</b> synchronisées')
        . ' <span class="muted sm">(gamedata = ' . substr((string)$fs['md5_gamedata'], 0, 10) . ' · dcr = ' . substr((string)$fs['md5_dcr'], 0, 10) . ')</span></p>';
    echo '<p>' . ($rtOk ? '✅' : '⚠️') . ' Fidélité du format (round-trip) : <b>' . ($rtOk ? 'exacte' : 'à vérifier') . '</b> '
        . '<span class="muted sm">— ' . h((string)$rtDetail) . '</span></p>';
    echo '<p>🛡️ Droits HC lus depuis <b>' . ($hc['src'] === 'settings' ? 'la table <code>settings</code>' : 'les valeurs par défaut de Kepler (aucun réglage en base)') . '</b>.</p>';
    echo '<p class="muted sm">ℹ️ En v14 (2007-2009), la figure n\'a que <b>5 emplacements</b> : Coiffures, Visage/Peau, Hauts, Pantalons, Chaussures. Les accessoires, barbes, lunettes et chapeaux sont <b>intégrés dans les sets</b> de cheveux/visage (pas d\'emplacement séparé). Le HC se gère au niveau du <b>set</b> (pas par couleur).</p>';
    echo '</div></div>';

    /* ---------- Filtres ---------- */
    echo '<div class="panel"><div class="ph"><h3>🔎 Recherche & filtres</h3>'
        . '<span class="muted sm" id="looks-count" style="margin-left:auto"></span></div>';
    echo '<div class="looks-filters">';
    echo '<input type="search" id="lkq" class="looks-search" placeholder="Rechercher un identifiant (ex. 210) ou une couleur (ex. E8B137)…" autocomplete="off">';
    echo '<select id="lkgender"><option value="">Genre : tous</option><option value="M">Masculin</option><option value="F">Féminin</option></select>';
    echo '<select id="lktype"><option value="">Catégorie : toutes</option>';
    foreach (figd_type_labels() as $tk => $tl) echo '<option value="' . h($tk) . '">' . h($tl) . ' (' . (int)($st['types'][$tk] ?? 0) . ')</option>';
    echo '</select>';
    echo '<select id="lkstatus"><option value="">Statut : tous</option><option value="libre">Libre</option><option value="hc">HC</option><option value="inactif">Inactif</option></select>';
    echo '<button type="button" class="mini ghost" id="lkreset">Réinitialiser</button>';
    echo '</div></div>';

    /* ---------- Tableau ---------- */
    echo '<div class="panel"><div class="ph"><h3>👕 Vêtements</h3>'
        . '<span class="muted sm" style="margin-left:auto">clique « Aperçu » pour générer le rendu Minerva (mis en cache)</span></div>';
    echo '<table class="clean looks-table"><thead><tr>'
        . '<th style="width:70px">Aperçu</th><th>Catégorie</th><th style="width:60px">Genre</th>'
        . '<th style="width:70px">ID set</th><th>Couleurs</th><th style="width:60px">Nb</th>'
        . '<th style="width:110px">Statut</th><th>Couches (technique)</th></tr></thead><tbody>';

    foreach ($rows as $r) {
        $pv = figd_preview_figure($r);
        $badge = $r['status'] === 'hc'
            ? '<span class="lk-badge hc">⭐ HC</span>'
            : ($r['status'] === 'inactif' ? '<span class="lk-badge warn">⏸ Inactif</span>' : '<span class="lk-badge ok">✅ Libre</span>');
        $search = $r['set_id'] . ' ' . implode(' ', $r['colors']); // clef de recherche

        // swatches (12 max affichées, le total reste en data)
        $sw = '';
        foreach (array_slice($r['colors'], 0, 14) as $hex) {
            $sw .= '<span class="sw" style="background:#' . h($hex) . '" title="#' . h($hex) . '"></span>';
        }
        if ($r['ncolors'] > 14) $sw .= '<span class="sw-more">+' . ($r['ncolors'] - 14) . '</span>';

        echo '<tr class="lk-row"'
            . ' data-gender="' . h($r['gender']) . '" data-type="' . h($r['type']) . '"'
            . ' data-status="' . h($r['status']) . '" data-search="' . h(strtolower($search)) . '">';
        echo '<td class="lk-prev"><button type="button" class="mini ghost lk-pvbtn" data-fig="' . h($pv['figure']) . '" data-sex="' . h($pv['sex']) . '">👁️</button></td>';
        echo '<td><b>' . h($r['type_label']) . '</b></td>';
        echo '<td class="sm">' . ($r['gender'] === 'F' ? '♀ F' : '♂ M') . '</td>';
        echo '<td class="sm"><code>' . (int)$r['set_id'] . '</code></td>';
        echo '<td class="lk-sw">' . $sw . '</td>';
        echo '<td class="sm" style="text-align:center">' . (int)$r['ncolors'] . '</td>';
        echo '<td>' . $badge . '</td>';
        echo '<td class="sm muted">' . h(implode(' · ', array_keys($r['sprites']))) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';

    /* ---------- Styles (scopés) + JS filtre/aperçu ---------- */
    ?>
<style>
.looks-kpis{display:flex;flex-wrap:wrap;gap:10px;padding:4px 2px 10px}
.looks-kpis .kpi{flex:1 1 120px;min-width:120px;background:#f6f7fb;border:1px solid #e6e8f0;border-radius:10px;padding:10px 12px;text-align:center}
.looks-kpis .kpi b{display:block;font-size:22px;line-height:1.1}
.looks-kpis .kpi span{font-size:12px;color:#667}
.looks-kpis .kpi.ok{background:#eefaf0;border-color:#cdebd4}
.looks-kpis .kpi.hc{background:#fff6e6;border-color:#f3dca6}
.looks-kpis .kpi.warn{background:#fdeff0;border-color:#f3c7cb}
.looks-notes p{margin:4px 0;font-size:13px}
.looks-filters{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.looks-filters .looks-search{flex:1 1 320px;min-width:220px;padding:8px 10px;border:1px solid #ccd;border-radius:8px}
.looks-filters select{padding:8px 10px;border:1px solid #ccd;border-radius:8px;background:#fff}
.looks-table td{vertical-align:middle}
.looks-table .lk-sw{line-height:0}
.sw{display:inline-block;width:14px;height:14px;border-radius:3px;margin:1px;border:1px solid rgba(0,0,0,.18);vertical-align:middle}
.sw-more{display:inline-block;font-size:11px;color:#667;margin-left:4px;vertical-align:middle;line-height:16px}
.lk-badge{display:inline-block;font-size:12px;padding:2px 8px;border-radius:20px;white-space:nowrap}
.lk-badge.ok{background:#eafaef;color:#1a7a33;border:1px solid #bfe8c8}
.lk-badge.hc{background:#fff3da;color:#9a6a06;border:1px solid #f0d89a}
.lk-badge.warn{background:#fdecee;color:#a3262f;border:1px solid #f1c3c8}
.lk-prev img{width:40px;height:auto;image-rendering:pixelated;background:#f3f4f8;border-radius:6px}
.lk-pvbtn{font-size:15px;line-height:1}
.lk-row.hide{display:none}
@media (prefers-color-scheme:dark){
  .looks-kpis .kpi{background:#1e2130;border-color:#2c3144}
  .looks-kpis .kpi span{color:#9aa}
  .looks-filters .looks-search,.looks-filters select{background:#171a26;border-color:#333a52;color:#dde}
}
</style>
<script>
(function(){
  var q=document.getElementById('lkq'), g=document.getElementById('lkgender'),
      t=document.getElementById('lktype'), s=document.getElementById('lkstatus'),
      reset=document.getElementById('lkreset'), count=document.getElementById('looks-count'),
      rows=[].slice.call(document.querySelectorAll('.lk-row'));
  function apply(){
    var qq=(q.value||'').trim().toLowerCase(), gg=g.value, tt=t.value, ss=s.value, n=0;
    rows.forEach(function(r){
      var ok = (!qq || r.dataset.search.indexOf(qq)>=0)
            && (!gg || r.dataset.gender===gg)
            && (!tt || r.dataset.type===tt)
            && (!ss || r.dataset.status===ss);
      r.classList.toggle('hide', !ok); if(ok) n++;
    });
    count.textContent = n + ' / ' + rows.length + ' vêtements';
  }
  [q,g,t,s].forEach(function(el){ el.addEventListener('input',apply); el.addEventListener('change',apply); });
  reset.addEventListener('click',function(){ q.value='';g.value='';t.value='';s.value='';apply();q.focus(); });
  // Aperçu Minerva au clic (lazy, 1 image injectée par ligne)
  document.querySelector('.looks-table').addEventListener('click',function(ev){
    var b=ev.target.closest('.lk-pvbtn'); if(!b) return;
    var cell=b.parentNode, fig=b.dataset.fig, sex=b.dataset.sex;
    var img=new Image(); img.alt='aperçu'; img.loading='lazy';
    img.src='/avatar.php?figure='+encodeURIComponent(fig)+'&sex='+encodeURIComponent(sex)+'&size=b&direction=2&head=0';
    cell.innerHTML=''; cell.appendChild(img);
  });
  apply();
})();
</script>
    <?php
}

page_looks();
