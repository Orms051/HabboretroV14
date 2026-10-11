<?php
/**
 * HabboretroV14 — Site v2 (refonte fidèle 2007) — SOCLE commun
 * Copie de travail séparée : le site actuel (/) reste intact.
 * Même base `v14`, mêmes comptes. Assets servis depuis /c_images et /web-gallery.
 */
declare(strict_types=1);
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => (($_SERVER['HTTPS'] ?? '') !== '')]);
session_start();
mb_internal_encoding('UTF-8');

const DB_HOST = '127.0.0.1', DB_PORT = 3306, DB_NAME = 'v14', DB_USER = 'root', DB_PASS = '';
const HOTEL = 'Habbo';
const DEFAULT_FIGURE = '1000118001270012900121001';
const GAME_URL = '/client.php';   // loader Shockwave (racine, inchangé)

function db(): PDO {
    static $p = null;
    if ($p === null) $p = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2,
    ]);
    return $p;
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $to) { header('Location: ' . $to); exit; }
function me(): ?array { return $_SESSION['site_user'] ?? null; }
function csrf(): string { if (empty($_SESSION['scsrf'])) $_SESSION['scsrf'] = bin2hex(random_bytes(16)); return $_SESSION['scsrf']; }
function csrf_ok(): bool { $t = (string)($_POST['csrf'] ?? ''); return $t !== '' && !empty($_SESSION['scsrf']) && hash_equals((string)$_SESSION['scsrf'], $t); }
function hash_pw(string $pw): string { return password_hash($pw, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 2, 'threads' => 1]); }
function parse_fr_date(?string $s): string { $s = trim((string)$s); if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]); if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s; return '1990-01-01'; }

/* ---- Limitation des tentatives de connexion (anti brute-force, par IP, persistante au-delà des cookies) ---- */
function login_throttle_file(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $dir = __DIR__ . '/../_runtime';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return $dir . '/login_' . substr(sha1($ip), 0, 20) . '.json';
}
function login_lock_remaining(): int { // secondes restantes de blocage, 0 sinon
    $f = login_throttle_file(); if (!is_file($f)) return 0;
    $d = json_decode((string)@file_get_contents($f), true) ?: [];
    $u = (int)($d['until'] ?? 0); return $u > time() ? $u - time() : 0;
}
function login_register_fail(): void {
    $f = login_throttle_file();
    $d = json_decode((string)@file_get_contents($f), true) ?: [];
    if (time() - (int)($d['first'] ?? 0) > 900) $d = ['first' => time(), 'fails' => 0]; // fenêtre glissante 15 min
    $d['fails'] = (int)($d['fails'] ?? 0) + 1;
    if ($d['fails'] >= 5) $d['until'] = time() + 600; // 10 min de blocage après 5 échecs
    @file_put_contents($f, json_encode($d));
}
function login_register_success(): void { @unlink(login_throttle_file()); }

/* ---- Mode maintenance : fichier Server/www/maintenance.json (écrit par l'admin). Le staff garde l'accès. ---- */
function maintenance_active(): bool { return is_file(dirname(__DIR__, 2) . '/maintenance.json'); }

/* ---- Messagerie : messages reçus du staff (user_messages, partagés avec le site /) ---- */
function ensure_user_msgs(): void {
    static $done = false; if ($done) return; $done = true;
    try { db()->exec("CREATE TABLE IF NOT EXISTS user_messages (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, from_staff VARCHAR(64) NOT NULL DEFAULT '', subject VARCHAR(120) NOT NULL DEFAULT '', body VARCHAR(2000) NOT NULL, read_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), INDEX(read_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
    catch (Throwable $e) {}
}
function unread_msgs(int $uid): int {
    if ($uid <= 0) return 0;
    try { ensure_user_msgs(); $st = db()->prepare('SELECT COUNT(*) FROM user_messages WHERE user_id=? AND read_at IS NULL'); $st->execute([$uid]); return (int)$st->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

/* Pseudo d'avatar en attendant l'imager V14 fidèle (placeholder stylisé — voir chantier avatar). */
function av_url(string $figure, string $sex, string $direction = '2', string $size = 'b'): string {
    // TODO imager V14 : pour l'instant renvoie '' -> le gabarit affiche un SVG de repli.
    return '';
}

/* ---- Carrousel « À ne pas manquer » (4 emplacements, géré dans l'admin) ---- */
function ensure_carousel(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_carousel (page VARCHAR(64) NOT NULL DEFAULT 'home', slot TINYINT NOT NULL, title VARCHAR(120) NOT NULL DEFAULT '', image VARCHAR(255) NOT NULL DEFAULT '', body VARCHAR(400) NOT NULL DEFAULT '', link VARCHAR(255) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1, PRIMARY KEY (page,slot)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $n = (int) db()->query("SELECT COUNT(*) FROM site_carousel WHERE page='home'")->fetchColumn();
    if ($n === 0) {
        $def = [
            [1, "Bienvenue dans l'Hôtel !", '/c_images/Frontpage_images/front_page_hotel_with_habbos.png', "Rencontre tes amis et décore ton appart. L'entrée est gratuite !", '/client.php'],
            [2, 'La Trax Machine', '/c_images/banners/425x178/purpletrax_topstory.gif', 'Compose tes propres mixes et deviens DJ dans l\'hôtel.', '?p=trax'],
            [3, 'Le Habbo Club', '/c_images/web_promo/promo_hc.png', 'Mobis exclusifs, cadeaux et badge doré avec le HC.', '?p=club'],
            [4, 'Les jeux Habbo', '/c_images/banners/425x178/bb2_topstory01.gif', 'BattleBall, SnowStorm… défie les autres Habbos !', '?p=games'],
        ];
        $st = db()->prepare("INSERT INTO site_carousel (page,slot,title,image,body,link,active) VALUES ('home',?,?,?,?,?,1)");
        foreach ($def as $d) { try { $st->execute($d); } catch (Throwable $e) {} }
    }
}
/** Diapos actives d'un carrousel de page (défaut : accueil). Chaque page a ses propres 4 emplacements. */
function carousel_slides(string $page = 'home'): array {
    try { ensure_carousel(); $st = db()->prepare('SELECT slot,title,image,body,link FROM site_carousel WHERE page=? AND active=1 ORDER BY slot'); $st->execute([$page]); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}

/* ---- Navigation pilotée par la base (onglets + sous-menus éditables, repli sur le défaut codé) ---- */
function ensure_site_nav(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_nav_tabs (
        tab_key VARCHAR(20) NOT NULL PRIMARY KEY,
        label VARCHAR(40) NOT NULL DEFAULT '',
        icon VARCHAR(60) NOT NULL DEFAULT '',
        section_label VARCHAR(60) NOT NULL DEFAULT '',
        ord SMALLINT NOT NULL DEFAULT 0,
        visible TINYINT NOT NULL DEFAULT 1,
        archived TINYINT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS site_nav_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tab_key VARCHAR(20) NOT NULL,
        label VARCHAR(80) NOT NULL DEFAULT '',
        route VARCHAR(255) NOT NULL DEFAULT '',
        target VARCHAR(20) NOT NULL DEFAULT '',
        ord SMALLINT NOT NULL DEFAULT 0,
        visible TINYINT NOT NULL DEFAULT 1,
        archived TINYINT NOT NULL DEFAULT 0,
        INDEX(tab_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---- Contenus de pages importés (reproduction fidèle de l'ancien site, éditables dans l'admin) ---- */
function ensure_site_pages(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_pages (
        slug VARCHAR(60) NOT NULL PRIMARY KEY,
        parent_tab VARCHAR(20) NOT NULL DEFAULT 'home',
        title VARCHAR(160) NOT NULL DEFAULT '',
        body_html MEDIUMTEXT NULL,
        source_ref VARCHAR(255) NOT NULL DEFAULT '',
        missing_note TEXT NULL,
        is_historical TINYINT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function site_page_get(string $slug): ?array {
    try { ensure_site_pages(); $st = db()->prepare('SELECT * FROM site_pages WHERE slug=?'); $st->execute([$slug]); $r = $st->fetch(); return $r ?: null; }
    catch (Throwable $e) { return null; }
}
/* Staff connecté (rang >= 5) — pour l'aperçu des brouillons. Reconnaît la session site OU admin (même session partagée). */
function is_staff(): bool {
    if ((int)($_SESSION['admin']['rank'] ?? 0) >= 5) return true;
    $u = me(); if (!$u) return false;
    try { $r = db()->prepare('SELECT `rank` FROM users WHERE id=?'); $r->execute([(int)$u['id']]); return (int)$r->fetchColumn() >= 5; }
    catch (Throwable $e) { return false; }
}

/* ---- Habbo Homes (fonctionnalité web ; Kepler n'a pas de table Home) ---- */
function ensure_home(): void {
    static $done = false; if ($done) return; $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS home_pages (
        user_id INT NOT NULL PRIMARY KEY,
        background VARCHAR(255) NOT NULL DEFAULT '',
        published TINYINT NOT NULL DEFAULT 1,
        hidden TINYINT NOT NULL DEFAULT 0,
        edit_locked TINYINT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS home_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(24) NOT NULL,
        resource VARCHAR(160) NOT NULL DEFAULT '',
        x SMALLINT NOT NULL DEFAULT 0,
        y SMALLINT NOT NULL DEFAULT 0,
        z SMALLINT NOT NULL DEFAULT 0,
        style VARCHAR(40) NOT NULL DEFAULT '',
        content TEXT NULL,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS home_guestbook (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_id INT NOT NULL,
        author_id INT NOT NULL,
        author_name VARCHAR(64) NOT NULL DEFAULT '',
        message VARCHAR(255) NOT NULL DEFAULT '',
        hidden TINYINT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(owner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS home_assets_disabled (
        kind VARCHAR(8) NOT NULL,
        resource VARCHAR(160) NOT NULL,
        PRIMARY KEY (kind, resource)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS home_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        target_type VARCHAR(12) NOT NULL,
        target_id INT NOT NULL,
        owner_id INT NOT NULL DEFAULT 0,
        reporter_id INT NOT NULL,
        reporter_name VARCHAR(64) NOT NULL DEFAULT '',
        reason VARCHAR(40) NOT NULL DEFAULT '',
        detail VARCHAR(400) NOT NULL DEFAULT '',
        status VARCHAR(12) NOT NULL DEFAULT 'new',
        handled_by VARCHAR(64) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(status), INDEX(owner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function home_guestbook_count(int $uid): int {
    ensure_home();
    try { $st = db()->prepare('SELECT COUNT(*) FROM home_guestbook WHERE owner_id=? AND hidden=0'); $st->execute([$uid]); return (int)$st->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
/** Composition de départ (widgets par défaut) pour un compte sans Home. Ne touche pas aux Homes existantes. */
function home_init_default(int $uid): void {
    $def = [
        ['widget_profile', '', 24, 16, 1],
        ['widget_friends', '', 24, 232, 2],
        ['widget_guestbook', '', 336, 232, 3],
        ['widget_badges', '', 24, 470, 4],
        ['widget_rooms', '', 664, 16, 5],
        ['widget_notes', '', 664, 300, 6],
    ];
    $st = db()->prepare('INSERT INTO home_items (user_id,type,resource,x,y,z,content) VALUES (?,?,?,?,?,?,?)');
    foreach ($def as $d) $st->execute([$uid, $d[0], $d[1], $d[2], $d[3], $d[4], $d[0] === 'widget_notes' ? 'Bienvenue sur ma Habbo Home !' : null]);
}
/** Renvoie la Home d'un compte (crée une composition de départ si absente). */
function home_for_user(int $uid): array {
    ensure_home();
    $st = db()->prepare('SELECT * FROM home_pages WHERE user_id=?'); $st->execute([$uid]);
    $h = $st->fetch();
    if (!$h) {
        try { db()->prepare('INSERT INTO home_pages (user_id,background,published) VALUES (?,?,1)')->execute([$uid, '']); home_init_default($uid); }
        catch (Throwable $e) {}
        $st->execute([$uid]); $h = $st->fetch() ?: ['user_id' => $uid, 'background' => '', 'published' => 1, 'hidden' => 0, 'edit_locked' => 0];
    }
    return $h;
}
function home_items(int $uid): array {
    ensure_home();
    try { $st = db()->prepare('SELECT * FROM home_items WHERE user_id=? ORDER BY z, id'); $st->execute([$uid]); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function home_guestbook(int $uid, int $limit = 20, int $offset = 0): array {
    ensure_home();
    try { $st = db()->prepare('SELECT * FROM home_guestbook WHERE owner_id=? AND hidden=0 ORDER BY id DESC LIMIT ? OFFSET ?'); $st->bindValue(1, $uid, PDO::PARAM_INT); $st->bindValue(2, $limit, PDO::PARAM_INT); $st->bindValue(3, $offset, PDO::PARAM_INT); $st->execute(); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
/* Catalogue des ressources (vrais fichiers du pack). */
function home_asset_dir(string $kind): string {
    // Assets spécifiques Habbo Home (jeux d'origine), pas les génériques du site.
    $root = dirname(__DIR__, 2) . '/c_images/myhabbo';   // .../www/c_images/myhabbo
    return $kind === 'bg' ? $root . '/backgrounds2' : $root . '/stickers';
}
function home_disabled_set(string $kind): array {
    ensure_home();
    static $cache = [];
    if (isset($cache[$kind])) return $cache[$kind];
    $set = [];
    try { $st = db()->prepare('SELECT resource FROM home_assets_disabled WHERE kind=?'); $st->execute([$kind]); foreach ($st->fetchAll() as $r) $set[$r['resource']] = 1; }
    catch (Throwable $e) {}
    return $cache[$kind] = $set;
}
function home_asset_list(string $kind, bool $withDisabled = false): array {
    $dir = home_asset_dir($kind);
    if (!is_dir($dir)) return [];
    $disabled = $withDisabled ? [] : home_disabled_set($kind);
    $out = [];
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (!preg_match('/\.(gif|png|jpg|jpeg)$/i', $f)) continue;
        if (!is_file($dir . '/' . $f)) continue;
        if (isset($disabled[$f])) continue;
        $out[] = $f;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}
/** Un fichier de ressource est-il réellement présent dans le catalogue (anti-chemin arbitraire) ? */
function home_asset_ok(string $kind, string $file): bool {
    $file = basename($file);
    if ($file === '' || !preg_match('/\.(gif|png|jpg|jpeg)$/i', $file)) return false;
    if (!is_file(home_asset_dir($kind) . '/' . $file)) return false;
    if (isset(home_disabled_set($kind)[$file])) return false; // ressource désactivée par l'admin : refusée à l'enregistrement
    return true;
}
function home_asset_url(string $kind, string $file): string {
    return '/c_images/myhabbo/' . ($kind === 'bg' ? 'backgrounds2' : 'stickers') . '/' . rawurlencode(basename($file));
}

function user_by_name(string $name): ?array {
    try { $st = db()->prepare('SELECT id,username,figure,sex,motto FROM users WHERE username=?'); $st->execute([$name]); $r = $st->fetch(); return $r ?: null; }
    catch (Throwable $e) { return null; }
}

/* Nombre de joueurs en ligne (setting Kepler). */
function online_count(): int {
    try { $v = db()->query("SELECT value FROM settings WHERE setting='players.online'")->fetchColumn(); return (int)$v; }
    catch (Throwable $e) { return 0; }
}

/**
 * avatar_tag — <img> du vrai avatar V14 d'un joueur (rendu par /avatar.php, mis en cache).
 * Options : head(bool miniature tête), size('s'|'b'), dir(2|4), w/h(px natifs recommandés),
 * class, alt, style. Pixels nets (Chrome + Basilisk), proportions conservées.
 */
const AVATAR_URL_VER = '2';   // bump = force le rafraîchissement des <img> (contourne le cache immutable)
function avatar_tag(string $username, array $o = []): string {
    $head = !empty($o['head']);
    $size = $o['size'] ?? ($head ? 's' : 'b');
    $dir  = (int)($o['dir'] ?? 2);
    $q = http_build_query(['user' => $username, 'size' => $size, 'direction' => $dir, 'head' => $head ? 1 : 0, 'v' => AVATAR_URL_VER]);
    // dimensions natives par défaut (pas d'étirement) : corps 64x110, tête s 32x55
    $w = $o['w'] ?? ($head ? 32 : 64);
    $hgt = $o['h'] ?? ($head ? 55 : 110);
    $style = 'image-rendering:pixelated;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;vertical-align:bottom;'
           . 'width:' . (int)$w . 'px;height:' . (int)$hgt . 'px;' . ($o['style'] ?? '');
    return '<img src="/avatar.php?' . h($q) . '" alt="' . h($o['alt'] ?? $username) . '" loading="lazy"'
         . ' class="' . h($o['class'] ?? 'hb-av') . '" style="' . h($style) . '">';
}
