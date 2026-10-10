<?php
/**
 * avatar.php — rend l'avatar V14 d'un joueur en PNG transparent.
 *
 * Le visiteur reçoit une simple image. Le rendu est produit localement par
 * Minerva (http://localhost — jamais exposé au public) à partir de la figure
 * stockée en base, convertie avec NOS couleurs (voir avatar/figure_lib.php).
 * Lecture seule : ne modifie jamais la figure, la mission ni la connexion.
 *
 * Paramètres (tous validés / mis en liste blanche) :
 *   user=<pseudo>            → figure+sexe lus en base
 *   figure=<25 chiffres>&sex=M|F → rendu direct (aperçu admin)
 *   size=s|b   direction=2|4   head=0|1
 *
 * Cache : cache/avatars/<hash>.png — clé = figure+sexe+size+direction+head+version
 * convertisseur. Une nouvelle tenue = nouvelle clé = nouvelle image (pas de purge
 * manuelle). Si Minerva est indisponible : image de secours discrète, sans bloquer.
 */

// Endpoint binaire : ne jamais laisser un warning PHP polluer le flux image.
@ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require __DIR__ . '/avatar/figure_lib.php';

if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1'); define('DB_PORT', 3306);
    define('DB_NAME', 'v14'); define('DB_USER', 'root'); define('DB_PASS', '');
}
const MINERVA_BASE = 'http://localhost:5123';
const AVA_CACHE_DIR = __DIR__ . '/cache/avatars';
const AVA_TIMEOUT   = 4;   // secondes max pour Minerva

/* ---------- Paramètres ---------- */
$ALLOWED_SIZE = ['s' => 's', 'b' => 'b'];
$ALLOWED_DIR  = [2, 4];

$size = $ALLOWED_SIZE[$_GET['size'] ?? 'b'] ?? 'b';
$dirReq = (int)($_GET['direction'] ?? 2);
$dir  = in_array($dirReq, $ALLOWED_DIR, true) ? $dirReq : 2;
$head = (($_GET['head'] ?? '') === '1') ? 1 : 0;

$figure = ''; $sex = 'M';
$user = isset($_GET['user']) ? preg_replace('/[^A-Za-z0-9_.\-]/', '', (string)$_GET['user']) : '';
if ($user !== '') {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $st = $pdo->prepare('SELECT figure, sex FROM users WHERE username = ? LIMIT 1');
        $st->execute([$user]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) { $figure = (string)$row['figure']; $sex = strtoupper((string)$row['sex']) === 'F' ? 'F' : 'M'; }
    } catch (Throwable $e) { /* tombe en secours */ }
} else {
    $figure = preg_replace('/\D/', '', (string)($_GET['figure'] ?? ''));
    $sex = (strtoupper((string)($_GET['sex'] ?? 'M')) === 'F') ? 'F' : 'M';
}

/* ---------- Secours discret ---------- */
function avatar_fallback(int $head): void {
    // silhouette grise neutre, fond transparent
    $w = 64; $h = $head ? 60 : 110;
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true); imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    $g = imagecolorallocatealpha($im, 150, 160, 170, 40);
    // tête
    imagefilledellipse($im, 32, $head ? 30 : 34, 26, 28, $g);
    if (!$head) {
        // corps
        imagefilledrectangle($im, 20, 50, 44, 92, $g);
        imagefilledrectangle($im, 24, 92, 40, 108, $g);
    }
    header('Content-Type: image/png');
    header('Cache-Control: no-store, max-age=0');  // on retentera au prochain chargement
    header('X-Avatar: fallback');
    imagepng($im); imagedestroy($im);
    exit;
}

if ($figure === '' || strlen(preg_replace('/\D/', '', $figure)) < 25) { avatar_fallback($head); }

/* ---------- Cache ---------- */
$key = sha1(implode('|', [$figure, $sex, $size, $dir, $head, AVATAR_CONV_VERSION]));
$cacheFile = AVA_CACHE_DIR . '/' . $key . '.png';

function serve_png(string $file, string $etag): void {
    $mtime = filemtime($file);
    header('Content-Type: image/png');
    // Revalidation par ETag : le navigateur garde l'image mais vérifie à chaque
    // chargement (304 instantané depuis le cache disque, sans régénérer via Minerva).
    // Évite qu'une réponse cassée reste figée (pas d'« immutable »).
    header('Cache-Control: public, max-age=0, must-revalidate');
    header('ETag: "' . $etag . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    $inm = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), '"');
    if ($inm === $etag) { http_response_code(304); exit; }
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

if (is_file($cacheFile) && filesize($cacheFile) > 0) { serve_png($cacheFile, $key); }

/* ---------- Génération ---------- */
$conv = fig_old_to_new($figure, $sex);
if (empty($conv['figure'])) { avatar_fallback($head); }

$qs = http_build_query([
    'figure' => $conv['figure'],
    'size' => $size,
    'direction' => $dir,
    'head_direction' => $dir,
    'head' => $head,
]);
$url = MINERVA_BASE . '/habbo-imaging/avatarimage?' . $qs;

$png = null; $httpCode = 0;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => AVA_TIMEOUT, CURLOPT_CONNECTTIMEOUT => 2]);
    $png = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => ['timeout' => AVA_TIMEOUT, 'ignore_errors' => true]]);
    $png = @file_get_contents($url, false, $ctx);
    foreach (($http_response_header ?? []) as $hh) if (preg_match('#HTTP/\S+\s+(\d+)#', $hh, $m)) $httpCode = (int)$m[1];
}

// On ne met en cache QUE si le PNG se décode réellement (évite de figer une
// image tronquée si Minerva dépasse le timeout en plein flux).
$valid = false;
if ($png !== false && $png !== null && $httpCode === 200 && strncmp($png, "\x89PNG", 4) === 0) {
    $sz = @getimagesizefromstring($png);
    $valid = $sz && $sz[0] > 0 && $sz[1] > 0 && $sz['mime'] === 'image/png';
}
if ($valid) {
    // Mise en cache best-effort (écriture atomique : tmp + rename), nettoyage du tmp si échec.
    @mkdir(AVA_CACHE_DIR, 0775, true);
    $tmp = $cacheFile . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $png, LOCK_EX) !== false) {
        if (!@rename($tmp, $cacheFile)) @unlink($tmp);
    }
    // On sert le PNG depuis la mémoire (insensible aux courses d'écriture du cache).
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=0, must-revalidate');
    header('ETag: "' . $key . '"');
    header('Content-Length: ' . strlen($png));
    echo $png; exit;
}

avatar_fallback($head);
