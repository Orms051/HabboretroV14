<?php
/**
 * trax_fix_player_png.php — retire la "barre témoin" de volume statique de player.png.
 *
 * Le chrome d'origine web-gallery/images/traxplayer/player.png dessinait DEUX éléments
 * de volume dans la zone centrale : un petit bandeau témoin (haut-parleur + traits) figé
 * ET laissait la place au curseur. Résultat à l'écran = deux barres, dont une (le témoin
 * dessiné) ne réagit pas. On efface la zone centrale (→ transparente) pour ne garder qu'UN
 * seul curseur de volume fonctionnel (stylé en CSS dans v2/index.php, case widget_trax).
 *
 * Idempotent : sauvegarde player.png.bak au 1er passage, puis réécrit depuis le .bak.
 * ⚠️ web-gallery/images est gitignoré (disque only) → ce script reproduit la retouche.
 *
 * Usage : php scripts/trax_fix_player_png.php
 */
$p = dirname(__DIR__) . '/Server/www/web-gallery/images/traxplayer/player.png';
if (!is_file($p)) { fwrite(STDERR, "Introuvable: $p\n"); exit(1); }
if (!is_file($p . '.bak')) copy($p, $p . '.bak');      // sauvegarde de l'original (1x)
copy($p . '.bak', $p);                                  // repartir de l'original (idempotent)

$im = imagecreatefrompng($p);
imagesavealpha($im, true);
imagealphablending($im, false);
$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
// Zone centrale (entre le bouton Lecture ~x0-42 et l'égaliseur ~x157+), bas du player.
imagefilledrectangle($im, 56, 36, 153, 65, $transparent);
imagepng($im, $p);
echo "OK — zone volume effacée dans player.png (barre témoin statique retirée).\n";
