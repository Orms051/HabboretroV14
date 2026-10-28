<?php
/**
 * HabboretroV14 — Site v2 (refonte fidèle 2007) — SOCLE commun
 * Copie de travail séparée : le site actuel (/) reste intact.
 * Même base `v14`, mêmes comptes. Assets servis depuis /c_images et /web-gallery.
 */
declare(strict_types=1);
// SEC05 : le cookie de session ne doit être Secure qu'en HTTPS réel. L'ancien test
// « HTTPS non vide » marquait aussi Secure quand $_SERVER['HTTPS'] vaut la chaîne 'off'
// (certains serveurs) -> cookie rejeté en HTTP, session cassée. Même logique que site_url().
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')]);
session_start();
mb_internal_encoding('UTF-8');

// Identifiants DB : surchargés par l'environnement si présent, sinon repli local
// (défaut Laragon root/''). Évite de committer des identifiants réels en prod.
define('DB_HOST', getenv('V14_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('V14_DB_PORT') ?: 3306));
define('DB_NAME', getenv('V14_DB_NAME') ?: 'v14');
define('DB_USER', getenv('V14_DB_USER') ?: 'root');
define('DB_PASS', getenv('V14_DB_PASS') !== false ? getenv('V14_DB_PASS') : '');
const HOTEL = 'Habbo';
const DEFAULT_FIGURE = '1000118001270012900121001';
const GAME_URL = '/client.php';   // loader Shockwave (racine, inchangé)

/* Base du site, configurable pour servir à la racine (/) après bascule — ou sous
   /v2/ avant. Déduite du host de la requête (pas de localhost/host en dur), afin
   que l'accès depuis un autre PC (adresse réseau des joueurs) fonctionne. */
if (!defined('SITE_BASE_PATH')) define('SITE_BASE_PATH', '/');
function site_url(string $path = ''): string {
    $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
    // SEC05 : HTTP_HOST est contrôlable par le client ; on le restreint à un hôte valide
    // (lettres/chiffres/.-: + port) pour empêcher une injection via l'en-tête Host. Repli localhost.
    $host  = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-:]+$/', $host)) $host = 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host . SITE_BASE_PATH . ltrim($path, '/');
}
/* Version des assets (cache-busting CSS/JS sans vidage manuel du cache navigateur). */
if (!defined('ASSET_VER')) define('ASSET_VER', '20261011');

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
$__cv = dirname(__DIR__, 2) . '/inc/conversations.php'; if (is_file($__cv)) require_once $__cv; // modèle conversation partagé (messagerie)
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
    // Seules les conversations comptent désormais ; les anciens user_messages ne sont
    // plus affichés ni comptés (on ne veut plus voir les anciens messages de l'équipe).
    if (function_exists('conv_unread_player')) { try { return conv_unread_player($uid); } catch (Throwable $e) {} }
    return 0;
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
    // SEC03 : ne pas se fier au rang mémorisé en session (périmé après une rétrogradation) — revalider en base à chaque appel.
    $id = (int)($_SESSION['admin']['id'] ?? 0);
    if ($id <= 0) { $u = me(); $id = $u ? (int)$u['id'] : 0; }
    if ($id <= 0) return false;
    try { $r = db()->prepare('SELECT `rank` FROM users WHERE id=?'); $r->execute([$id]); return (int)$r->fetchColumn() >= 5; }
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
        hidden_until DATETIME NULL,
        locked_until DATETIME NULL,
        rev INT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { if (!(int)db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='home_pages' AND COLUMN_NAME='hidden_until'")->fetchColumn()) db()->exec("ALTER TABLE home_pages ADD COLUMN hidden_until DATETIME NULL, ADD COLUMN locked_until DATETIME NULL"); } catch (Throwable $e) {}
    // H02 : révision de page (anti-écrasement). Un enregistrement incrémente rev ; un onglet
    // ancien (rev < rev courant) est refusé pour ne pas écraser une modif plus récente / une modération.
    try { if (!(int)db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='home_pages' AND COLUMN_NAME='rev'")->fetchColumn()) db()->exec("ALTER TABLE home_pages ADD COLUMN rev INT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
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
    // Statut d'une ressource Home (H01) : une LIGNE = ressource non « disponible ».
    //   status 'shop_hidden' = retirée de la boutique (plus proposée à l'ajout) mais
    //                          TOUJOURS affichée chez ses détenteurs ;
    //   status 'banned'      = interdite d'affichage partout (modération), avec motif/trace.
    //   Aucune ligne         = 'available' (proposée + affichée).
    db()->exec("CREATE TABLE IF NOT EXISTS home_assets_disabled (
        kind VARCHAR(8) NOT NULL,
        resource VARCHAR(160) NOT NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'shop_hidden',
        reason VARCHAR(255) NOT NULL DEFAULT '',
        updated_by VARCHAR(64) NOT NULL DEFAULT '',
        updated_at DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (kind, resource)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    home_assets_ensure_schema();
    db()->exec("CREATE TABLE IF NOT EXISTS home_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        target_type VARCHAR(12) NOT NULL,
        target_id INT NOT NULL,
        owner_id INT NOT NULL DEFAULT 0,
        reporter_id INT NOT NULL,
        reporter_name VARCHAR(64) NOT NULL DEFAULT '',
        reason VARCHAR(40) NOT NULL DEFAULT '',
        detail VARCHAR(400) NOT NULL DEFAULT '',
        snapshot VARCHAR(400) NOT NULL DEFAULT '',
        status VARCHAR(12) NOT NULL DEFAULT 'new',
        handled_by VARCHAR(64) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(status), INDEX(owner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Colonne snapshot ajoutée après coup (copie du contenu au moment du signalement)
    try { if (!(int)db()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='home_reports' AND COLUMN_NAME='snapshot'")->fetchColumn()) db()->exec("ALTER TABLE home_reports ADD COLUMN snapshot VARCHAR(400) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    // H02 (2/2) : historique de versions d'une Home (snapshot de l'état avant chaque écrasement).
    db()->exec("CREATE TABLE IF NOT EXISTS home_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        rev INT NOT NULL DEFAULT 0,
        background VARCHAR(255) NOT NULL DEFAULT '',
        items_json MEDIUMTEXT NULL,
        author VARCHAR(64) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** H02 : enregistre l'ÉTAT ACTUEL de la Home comme version restaurable, puis élague (15 max/joueur).
 *  À appeler AVANT d'écraser (dans la transaction de sauvegarde/restauration). Best-effort. */
function home_snapshot_take(int $uid, string $author = 'joueur'): void {
    if ($uid <= 0) return;
    try {
        $hp = db()->prepare('SELECT background,rev FROM home_pages WHERE user_id=?'); $hp->execute([$uid]); $row = $hp->fetch();
        if (!$row) return; // pas de Home existante -> rien à historiser
        $it = db()->prepare('SELECT type,resource,x,y,z,content,style FROM home_items WHERE user_id=? ORDER BY z,id'); $it->execute([$uid]);
        $items = $it->fetchAll();
        db()->prepare('INSERT INTO home_snapshots (user_id,rev,background,items_json,author) VALUES (?,?,?,?,?)')
            ->execute([$uid, (int)$row['rev'], (string)$row['background'], json_encode($items, JSON_UNESCAPED_UNICODE), mb_substr($author, 0, 64)]);
        // Élagage : ne garder que les 15 versions les plus récentes.
        db()->exec('DELETE s FROM home_snapshots s JOIN (SELECT id FROM home_snapshots WHERE user_id=' . (int)$uid . ' ORDER BY id DESC LIMIT 15,1000000) old ON s.id=old.id');
    } catch (Throwable $e) {}
}
/** Liste des versions (récentes d'abord) : id, rev, nb d'éléments, auteur, date. */
function home_snapshots_list(int $uid, int $limit = 15): array {
    ensure_home();
    try {
        $st = db()->prepare('SELECT id,rev,background,items_json,author,created_at FROM home_snapshots WHERE user_id=? ORDER BY id DESC LIMIT ?');
        $st->bindValue(1, $uid, PDO::PARAM_INT); $st->bindValue(2, $limit, PDO::PARAM_INT); $st->execute();
        $out = [];
        foreach ($st->fetchAll() as $r) { $items = json_decode((string)$r['items_json'], true); $out[] = ['id' => (int)$r['id'], 'rev' => (int)$r['rev'], 'count' => is_array($items) ? count($items) : 0, 'author' => (string)$r['author'], 'created_at' => (string)$r['created_at']]; }
        return $out;
    } catch (Throwable $e) { return []; }
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
    // Expiration PARESSEUSE des restrictions temporaires (sans CRON) : si la date est
    // dépassée, on lève la restriction au premier accès à la Home.
    try {
        $now = date('Y-m-d H:i:s');
        if ((int)($h['hidden'] ?? 0) === 1 && !empty($h['hidden_until']) && $h['hidden_until'] <= $now) { db()->prepare('UPDATE home_pages SET hidden=0, hidden_until=NULL WHERE user_id=?')->execute([$uid]); $h['hidden'] = 0; $h['hidden_until'] = null; }
        if ((int)($h['edit_locked'] ?? 0) === 1 && !empty($h['locked_until']) && $h['locked_until'] <= $now) { db()->prepare('UPDATE home_pages SET edit_locked=0, locked_until=NULL WHERE user_id=?')->execute([$uid]); $h['edit_locked'] = 0; $h['locked_until'] = null; }
    } catch (Throwable $e) {}
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
/** Ajoute les colonnes de statut si une ancienne table home_assets_disabled existe sans elles.
 *  SQL standard (information_schema + ALTER simple) compatible MySQL 8.4 et MariaDB 11.4. */
function home_assets_ensure_schema(): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $cols = [];
        foreach (db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='home_assets_disabled'") as $r) $cols[$r['COLUMN_NAME']] = 1;
        if ($cols && !isset($cols['status']))     db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'shop_hidden'");
        if ($cols && !isset($cols['reason']))     db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN reason VARCHAR(255) NOT NULL DEFAULT ''");
        if ($cols && !isset($cols['updated_by'])) db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN updated_by VARCHAR(64) NOT NULL DEFAULT ''");
        if ($cols && !isset($cols['updated_at'])) db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN updated_at DATETIME NULL DEFAULT NULL");
    } catch (Throwable $e) {}
}
/** resource => statut ('shop_hidden'|'banned') pour un type. Absent de la map = 'available'. */
function home_asset_status_map(string $kind): array {
    ensure_home();
    static $cache = [];
    if (isset($cache[$kind])) return $cache[$kind];
    $m = [];
    try { $st = db()->prepare('SELECT resource,status FROM home_assets_disabled WHERE kind=?'); $st->execute([$kind]); foreach ($st->fetchAll() as $r) $m[$r['resource']] = ($r['status'] ?? '') === 'banned' ? 'banned' : 'shop_hidden'; }
    catch (Throwable $e) {}
    return $cache[$kind] = $m;
}
/** Statut d'une ressource : 'available' (défaut), 'shop_hidden' ou 'banned'. */
function home_asset_status(string $kind, string $file): string {
    return home_asset_status_map($kind)[basename($file)] ?? 'available';
}
/** Clés des skins de widgets Home (set fixe du thème ; miroir de home_skins()). */
function home_skin_keys(): array { return ['habbohomes', 'metal', 'hcmachine', 'notepad', 'speechbubble', 'stickienote']; }
/** Présente dans le catalogue (anti-chemin arbitraire) — indépendant du statut.
 *  kind 'skin' : la ressource est une clé de skin (pas un fichier). */
function home_asset_present(string $kind, string $file): bool {
    if ($kind === 'skin') return in_array($file, home_skin_keys(), true);
    $file = basename($file);
    if ($file === '' || !preg_match('/\.(gif|png|jpg|jpeg)$/i', $file)) return false;
    return is_file(home_asset_dir($kind) . '/' . $file);
}
/** Liste des ressources proposables à l'AJOUT (palette de l'éditeur) = présentes ET 'available'.
 *  $withAll=true (ex. admin) renvoie toutes les présentes, quel que soit le statut. */
function home_asset_list(string $kind, bool $withAll = false): array {
    $flagged = $withAll ? [] : home_asset_status_map($kind);
    if ($kind === 'skin') {
        $out = [];
        foreach (home_skin_keys() as $k) { if (isset($flagged[$k])) continue; $out[] = $k; }
        return $out;
    }
    $dir = home_asset_dir($kind);
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        if (!preg_match('/\.(gif|png|jpg|jpeg)$/i', $f)) continue;
        if (!is_file($dir . '/' . $f)) continue;
        if (isset($flagged[$f])) continue; // a un statut (retirée boutique ou bannie) -> pas dans la palette
        $out[] = $f;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}
/** AFFICHABLE : présente ET non bannie. H01 — une ressource retirée de la boutique
 *  ('shop_hidden') reste affichée chez ses détenteurs ; seul 'banned' la retire partout.
 *  Utilisé à l'enregistrement d'une Home ET au rendu (on ne casse pas une Home existante). */
function home_asset_ok(string $kind, string $file): bool {
    return home_asset_present($kind, $file) && home_asset_status($kind, $file) !== 'banned';
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
