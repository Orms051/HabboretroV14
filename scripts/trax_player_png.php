<?php
/**
 * trax_player_png.php — retouche player.png pour le Traxplayer de la Home.
 *
 * Part de l'ORIGINAL (player.png.bak) et :
 *   1) efface la "barre grise horizontale" du volume (bandeau plein redondant sous
 *      l'indicateur à barres) — on garde l'indicateur + haut-parleurs + le pouce natif ;
 *   2) efface l'égaliseur dessiné à droite (statique) — il est remplacé par un
 *      égaliseur ANIMÉ en CSS/JS (v2/index.php, case widget_trax, .music-activity/.tp-eq).
 *
 * Idempotent : crée player.png.bak au 1er passage, puis réécrit toujours depuis le .bak.
 * ⚠️ web-gallery/images est gitignoré (disque only) → ce script reproduit la retouche.
 *    Après exécution, penser à bumper le ?v=N de l'URL dans v2/index.php (cache navigateur).
 *
 * Usage : php scripts/trax_player_png.php
 */
$p = dirname(__DIR__) . '/Server/www/web-gallery/images/traxplayer/player.png';
if (!is_file($p)) { fwrite(STDERR, "Introuvable: $p\n"); exit(1); }
if (!is_file($p . '.bak')) copy($p, $p . '.bak'); // sauvegarde de l'original (1x)
copy($p . '.bak', $p);                             // repartir de l'original (idempotent)

$im = imagecreatefrompng($p);
imagesavealpha($im, true);
imagealphablending($im, false);
$t = imagecolorallocatealpha($im, 0, 0, 0, 127); // transparent
imagefilledrectangle($im, 64, 53, 140, 66, $t);  // 1) barre grise horizontale du volume
imagefilledrectangle($im, 154, 37, 209, 66, $t); // 2) égaliseur dessiné (remplacé par l'animé)
imagepng($im, $p);
echo "OK — barre grise volume + égaliseur statique effacés dans player.png.\n";
