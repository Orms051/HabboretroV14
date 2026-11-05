<?php
/**
 * trax_extract_samples.php — extrait les VRAIS samples du Sound Machine (Trax)
 * depuis les casts Shockwave d'origine vers des .mp3 jouables par la Habbo Home.
 *
 * Source  : Server/www/dcr/sound/sound_machine_sample_<id>.cct  (Director Afterburner)
 * Sortie  : Server/www/web-gallery/sounds/trax/sound_machine_sample_<id>.mp3
 *
 * Les .cct ne re-compressent PAS l'audio (il est déjà en SWA = MP3). On repère donc
 * directement la plus longue chaîne de trames MPEG-1/2/2.5 Layer III valides dans le
 * fichier brut et on l'écrit telle quelle. ~630/648 samples sortent (les derniers IDs
 * sont des casts placeholder sans audio). Rejouable à volonté (idempotent).
 *
 * Usage : php scripts/trax_extract_samples.php [dossier_src] [dossier_dst]
 *   (dossiers par défaut relatifs à la racine du dépôt)
 *
 * ⚠️ dcr/sound et web-gallery/sounds sont gitignorés (sur disque, non versionnés) :
 *    ce script EST la source de vérité pour régénérer les mp3.
 */

$root = dirname(__DIR__) . '/Server/www';
$src  = $argv[1] ?? $root . '/dcr/sound';
$dst  = $argv[2] ?? $root . '/web-gallery/sounds/trax';
@mkdir($dst, 0777, true);

$br   = [null, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, null]; // MPEG1 L3
$sr1  = [44100, 48000, 32000, 0];
$sr2  = [22050, 24000, 16000, 0];
$sr25 = [11025, 12000, 8000, 0];

function frameLen(string $d, int $i): int {
    global $br, $sr1, $sr2, $sr25;
    $n = strlen($d); if ($i + 4 > $n) return 0;
    if (ord($d[$i]) !== 0xFF) return 0;
    $b1 = ord($d[$i + 1]); if (($b1 & 0xE0) !== 0xE0) return 0;
    $ver = ($b1 >> 3) & 3; $layer = ($b1 >> 1) & 3;
    if ($ver === 1 || $layer !== 1) return 0; // version réservée / pas Layer III
    $b2 = ord($d[$i + 2]); $bi = ($b2 >> 4) & 0xF; $si = ($b2 >> 2) & 3; $pad = ($b2 >> 1) & 1;
    if ($bi === 0 || $bi === 15 || $si === 3) return 0;
    $kbps = $br[$bi]; if (!$kbps) return 0;
    if ($ver === 3)      { $sr = $sr1[$si];  $spf = 1152; } // MPEG1
    elseif ($ver === 2)  { $sr = $sr2[$si];  $spf = 576; }  // MPEG2
    else                 { $sr = $sr25[$si]; $spf = 576; }  // MPEG2.5
    if (!$sr) return 0;
    return intval(($spf / 8 * $kbps * 1000) / $sr) + $pad;
}

function longestMp3(string $d): array {
    $n = strlen($d); $best = [-1, 0, 0]; // [start, nframes, bytes]
    for ($i = 0; $i < $n - 4; $i++) {
        if (ord($d[$i]) !== 0xFF) continue;
        if (frameLen($d, $i) < 24) continue;
        $j = $i; $c = 0;
        while ($j < $n - 4) { $f = frameLen($d, $j); if ($f < 24) break; $j += $f; $c++; if ($c > 20000) break; }
        if ($c > $best[1]) $best = [$i, $c, $j - $i];
        if ($c > 3) $i = $j - 1;
    }
    return $best;
}

$files = glob($src . '/sound_machine_sample_*.cct');
natsort($files);
$ok = 0; $fail = [];
foreach ($files as $f) {
    if (!preg_match('/sample_(\d+)\.cct$/', $f, $m)) continue;
    $id = $m[1];
    [$st, $nf, $by] = longestMp3(file_get_contents($f));
    if ($nf < 2 || $by < 200) { $fail[] = $id; continue; }
    file_put_contents($dst . '/sound_machine_sample_' . $id . '.mp3', substr(file_get_contents($f), $st, $by));
    $ok++;
}
printf("Source : %s\nSortie : %s\nTraités : %d  Extraits : %d  Sans audio : %d\n",
    $src, $dst, count($files), $ok, count($fail));
if ($fail) echo "IDs sans audio (placeholders) : " . implode(',', $fail) . "\n";
