<?php
/**
 * trax_player_png.php — retouche player.png pour le Traxplayer de la Home.
 *
 * Part de l'ORIGINAL (player.png.bak) et :
 *   1) efface la "barre grise horizontale" du volume (bandeau plein redondant) ;
 *   2) efface l'égaliseur dessiné à droite (statique) — remplacé par un égaliseur
 *      ANIMÉ en CSS/JS (v2/index.php, case widget_trax) ;
 *   3) DESCEND l'indicateur de volume (haut-parleurs + barres) de 8 px pour le
 *      centrer sur la même ligne que le bouton Lecture et l'égaliseur (alignement).
 *
 * Idempotent : crée player.png.bak au 1er passage, puis réécrit toujours depuis le .bak.
 * ⚠️ web-gallery/images est gitignoré (disque only) → ce script reproduit la retouche.
 *    Après exécution, bumper le ?v=N de l'URL dans v2/index.php (cache navigateur).
 *
 * Usage : php scripts/trax_player_png.php
 */
$p = dirname(__DIR__) . '/Server/www/web-gallery/images/traxplayer/player.png';
if (!is_file($p)) { fwrite(STDERR, "Introuvable: $p\n"); exit(1); }
if (!is_file($p . '.bak')) copy($p, $p . '.bak'); // sauvegarde de l'original (1x)
copy($p . '.bak', $p);                             // repartir de l'original (idempotent)

$im = imagecreatefrompng($p);
imagesavealpha($im, true);

// 3a) récupérer l'indicateur de volume (x74-135, y41-49) AVANT d'effacer quoi que ce soit
$mw = 135 - 74 + 1; $mh = 49 - 41 + 1;            // 62 x 9
$tmp = imagecreatetruecolor($mw, $mh);
imagesavealpha($tmp, true);
imagealphablending($tmp, false);
imagefilledrectangle($tmp, 0, 0, $mw, $mh, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
imagecopy($tmp, $im, 0, 0, 74, 41, $mw, $mh);

imagealphablending($im, false);
$t = imagecolorallocatealpha($im, 0, 0, 0, 127); // transparent
imagefilledrectangle($im, 74, 41, 135, 49, $t);  // efface l'ancien indicateur (position haute)
imagefilledrectangle($im, 64, 53, 140, 66, $t);  // 1) barre grise horizontale du volume
imagefilledrectangle($im, 154, 37, 209, 66, $t); // 2) égaliseur dessiné (remplacé par l'animé)
imagecopy($im, $tmp, 74, 49, 0, 0, $mw, $mh);    // 3b) recolle l'indicateur 8 px plus bas (centre ~53)

imagepng($im, $p);
echo "OK — barre grise + égaliseur effacés, indicateur de volume descendu de 8 px (aligné).\n";
