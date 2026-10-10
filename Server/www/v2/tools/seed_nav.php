<?php
/**
 * Peuple site_nav_tabs / site_nav_items depuis le défaut codé (layout.php), une seule fois.
 * Idempotent : ne touche rien si les tables contiennent déjà des lignes.
 * Usage : php v2/tools/seed_nav.php [--force]
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('403 — script de maintenance réservé à la ligne de commande (CLI).'); }
chdir(dirname(__DIR__, 2));
require dirname(__DIR__, 2) . '/v2/inc/boot.php';
require dirname(__DIR__, 2) . '/v2/inc/layout.php';
ensure_site_nav();

$force = in_array('--force', $argv, true);
$n = (int) db()->query('SELECT COUNT(*) FROM site_nav_tabs')->fetchColumn();
if ($n > 0 && !$force) { echo "Déjà peuplé ($n onglets). --force pour réécraser.\n"; exit; }
if ($force) { db()->exec('DELETE FROM site_nav_items'); db()->exec('DELETE FROM site_nav_tabs'); }

$tabs = nav_tabs_default();   // key => [label, longLabel, icon]
$subs = sub_menus_default();  // key => [section, items[]]
$ti = db()->prepare('INSERT INTO site_nav_tabs (tab_key,label,icon,section_label,ord,visible,archived) VALUES (?,?,?,?,?,1,0)');
$ii = db()->prepare('INSERT INTO site_nav_items (tab_key,label,route,target,ord,visible,archived) VALUES (?,?,?,?,?,1,0)');

$ord = 0;
foreach ($tabs as $key => $t) {
    $ord += 10;
    $section = $subs[$key][0] ?? $t[0];
    $ti->execute([$key, $t[0], $t[2], $section, $ord]);
    $iord = 0;
    foreach (($subs[$key][1] ?? []) as $item) {
        // $item = [label, href|null, target?] ; le 1er (href null) est le libellé de section -> ignoré ici
        if (($item[1] ?? null) === null) continue;
        $iord += 10;
        $ii->execute([$key, (string)$item[0], (string)$item[1], (string)($item[2] ?? ''), $iord]);
    }
}
$nt = (int) db()->query('SELECT COUNT(*) FROM site_nav_tabs')->fetchColumn();
$ni = (int) db()->query('SELECT COUNT(*) FROM site_nav_items')->fetchColumn();
echo "Peuplé : $nt onglets, $ni entrées de sous-menu.\n";
