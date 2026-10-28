<?php
/**
 * HabboretroV14 — Panneau d'administration PRO (housekeeping)
 * Kepler v14 · PHP 8.3 (Laragon) · base v14 sur 127.0.0.1:3306
 * Accès : http://localhost/admin/  — rang >= 5 requis.
 */
declare(strict_types=1);
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => (($_SERVER['HTTPS'] ?? '') !== '')]);
session_start();
mb_internal_encoding('UTF-8');

// Identifiants DB : surchargés par l'environnement si présent, sinon repli local
// (défaut Laragon root/''). Permet de ne pas committer d'identifiants réels en prod.
define('DB_HOST', getenv('V14_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('V14_DB_PORT') ?: 3306));
define('DB_NAME', getenv('V14_DB_NAME') ?: 'v14');
define('DB_USER', getenv('V14_DB_USER') ?: 'root');
define('DB_PASS', getenv('V14_DB_PASS') !== false ? getenv('V14_DB_PASS') : '');
const MIN_RANK = 5;
const DCR_DIR = __DIR__ . '/../dcr/14.1_b8';
const BADGE_DIR = __DIR__ . '/../c_images/badges';
const BAN_PERMANENT = 253402214400000;
const PER_PAGE = 20;

const RANKS = [1 => '1 · Joueur', 2 => '2 · Community Manager', 3 => '3 · Guide', 4 => '4 · Hobba', 5 => '5 · Super Hobba', 6 => '6 · Modérateur', 7 => '7 · Administrateur'];

const DECOR_LABELS = [
    '' => '🏛️ Normal', 'xmas' => '🎄 Noël', 'halloween' => '🎃 Halloween', 'easter' => '🐣 Pâques',
    'valentine' => '💕 St-Valentin', 'summer' => '☀️ Été', 'winter' => '❄️ Hiver', 'king' => '👑 King',
    'nokia' => '📱 Nokia', 'joepie' => '🍦 Joepie', 'axe' => '🧴 Axe', 'deli' => '🥪 Deli', 'samsung' => '📺 Samsung', 'hbowood' => '🎬 HBO Wood',
];

const SETTING_DESC = [
    'afk.timer.seconds' => "Délai d'inactivité (secondes) avant le statut AFK.",
    'battleball.create.game.enabled' => "Autoriser la création de parties BattleBall.",
    'battleball.game.lifetime.seconds' => "Durée d'une partie BattleBall (secondes).",
    'battleball.ticket.charge' => "Prix d'une partie BattleBall (tickets/crédits).",
    'carry.timer.seconds' => "Durée pendant laquelle un objet porté (boisson) reste en main.",
    'chat.bubble.timeout.seconds' => "Durée d'affichage des bulles de chat.",
    'chat.garbled.text' => "Brouille le texte pour les comptes non finalisés (true/false).",
    'club.gift.interval' => "Intervalle entre deux cadeaux du Club Habbo.",
    'club.gift.timeunit' => "Unité de temps du cadeau Club (DAYS, HOURS…).",
    'credits.scheduler.amount' => "Crédits offerts automatiquement à chaque intervalle.",
    'credits.scheduler.enabled' => "Activer les crédits automatiques (true/false).",
    'credits.scheduler.interval' => "Intervalle des crédits automatiques.",
    'credits.scheduler.timeunit' => "Unité de temps des crédits auto (MINUTES, HOURS…).",
    'disable.purchase.successful.alert' => "Masquer l'alerte « achat réussi » (true/false).",
    'events.category.count' => "Nombre de catégories d'événements de salle.",
    'events.expiry.minutes' => "Durée de vie d'un événement de salle (minutes).",
    'max.connections.per.ip' => "Connexions simultanées autorisées par IP.",
    'messenger.max.friends.club' => "Amis max pour les membres du Club Habbo.",
    'messenger.max.friends.nonclub' => "Amis max pour les non-membres.",
    'navigator.show.hidden.rooms' => "Afficher les salles masquées dans le navigateur (true/false).",
    'players.online' => "Compteur de joueurs en ligne (automatique).",
    'poker.entry.price' => "Prix d'entrée d'une partie de poker.",
    'profile.editing' => "Autoriser les joueurs à modifier leur profil (true/false).",
    'rare.cycle.refresh.interval' => "Fréquence de rotation des raretés au catalogue.",
    'rare.cycle.reuse.interval' => "Délai avant qu'une rareté puisse réapparaître.",
    'recycler.item.quarantine.seconds' => "Durée de possession requise avant recyclage.",
    'recycler.max.time.to.collect.seconds' => "Temps max pour récupérer un recyclage.",
    'recycler.session.length.seconds' => "Durée d'une session de recyclage.",
    'reset.sso.after.login' => "Réinitialiser le ticket SSO après connexion (true/false).",
    'roller.tick.default' => "Vitesse des tapis roulants (ms).",
    'room.bots.enabled' => "Activer les bots dans les salles (true/false).",
    'room.dispose.timer.enabled' => "Décharger les salles vides après un délai (true/false).",
    'room.dispose.timer.seconds' => "Délai avant déchargement d'une salle vide.",
    'shutdown.minutes' => "Délai (minutes) affiché à l'arrêt du serveur.",
    'sleep.timer.seconds' => "Délai avant que l'avatar s'endorme.",
    'snowstorm.create.game.enabled' => "Autoriser la création de parties SnowStorm.",
    'snowstorm.ticket.charge' => "Prix d'une partie SnowStorm.",
    'stack.height.limit' => "Hauteur maximale d'empilement des meubles.",
    'tutorial.enabled' => "Activer le tutoriel des nouveaux (true/false).",
    'users.figure.parts.club' => "Pièces d'apparence réservées aux membres du Club.",
    'users.figure.parts.default' => "Pièces d'apparence disponibles par défaut.",
    'vouchers.enabled' => "Activer les codes/bons de crédits (true/false).",
    'welcome.message.content' => "Texte du message de bienvenue (%username% = nom).",
    'welcome.message.enabled' => "Message de bienvenue à la connexion (true/false).",
];
const FRIENDLY_KEYS = ['credits.scheduler.amount', 'credits.scheduler.interval', 'credits.scheduler.enabled', 'battleball.ticket.charge', 'snowstorm.ticket.charge', 'messenger.max.friends.nonclub', 'messenger.max.friends.club', 'welcome.message.enabled', 'welcome.message.content', 'afk.timer.seconds', 'stack.height.limit', 'tutorial.enabled', 'max.connections.per.ip'];

/* Thèmes (repris de Cadurix). [nom, emoji, mode, bg, bg2(cartes), bg3, sidebar, accent, text, text2, border, border2] */
const THEMES = [
    'ardoise'     => ['Ardoise', '🪨', 'light', '#F8F9FB', '#FFFFFF', '#EFF1F5', '#F1F3F7', '#4F46E5', '#1B2230', '#5C6677', '#E4E7EC', '#D3D8E0'],
    'bleu_medical'=> ['Bleu Médical', '💙', 'light', '#F7FAFE', '#FFFFFF', '#EEF4FC', '#F0F6FF', '#2563EB', '#15233D', '#5A6B86', '#E1E9F5', '#CFDBEE'],
    'menthe'      => ['Menthe', '🌿', 'light', '#F5FBF8', '#FFFFFF', '#E9F6F0', '#ECFAF4', '#0E9F6E', '#103128', '#4A6B5E', '#D4EDE3', '#BCE3D3'],
    'sable'       => ['Sable', '🏜️', 'light', '#FDFBF7', '#FFFFFF', '#F6F1E9', '#FBF6EE', '#E0792B', '#2B2018', '#6E5D4E', '#ECE3D5', '#DECFB9'],
    'nuit_bleue'  => ['Nuit Bleue', '🌌', 'dark', '#0B1220', '#131C2E', '#1B2740', '#0E1626', '#3B82F6', '#E8EEF8', '#8A98B5', '#1F2B44', '#2A3856'],
    'carbone'     => ['Carbone', '⚫', 'dark', '#0C0E12', '#14171D', '#1C2129', '#0F1216', '#06B6D4', '#E6EAF0', '#8A93A3', '#20242C', '#2C3038'],
    'ambre'       => ['Ambre Nocturne', '🟠', 'dark', '#13100B', '#1C1812', '#261F17', '#161108', '#F59E0B', '#F5ECDC', '#A8987F', '#2A2318', '#382E1F'],
    'indigo'      => ['Indigo Profond', '💜', 'dark', '#0C0A1A', '#15122A', '#1E1A3A', '#0F0C20', '#8B5CF6', '#EAE7FB', '#9089B8', '#221E40', '#2E2952'],
];
const THEME_DEFAULT = 'ardoise';
function current_theme_key(): string { $k = $_COOKIE['r14theme'] ?? THEME_DEFAULT; return isset(THEMES[$k]) ? $k : THEME_DEFAULT; }
function hexrgb(string $hex): string { $h = ltrim($hex, '#'); return hexdec(substr($h, 0, 2)) . ',' . hexdec(substr($h, 2, 2)) . ',' . hexdec(substr($h, 4, 2)); }

/* ---------- Helpers ---------- */
function db(): PDO { static $pdo = null; if ($pdo === null) $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 2]); return $pdo; }
$__cv = __DIR__ . '/../inc/conversations.php'; if (is_file($__cv)) require_once $__cv; // modèle conversation partagé (messagerie)
function admin_db_down(): void {
    if (!headers_sent()) http_response_code(503);
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Habbo · Admin</title><style>body{font:14px/1.6 Segoe UI,Arial,sans-serif;background:#20262e;color:#e6ebf2;display:grid;place-items:center;height:100vh;margin:0}.box{background:#2b333d;border:1px solid #3a444f;border-radius:14px;padding:30px 34px;max-width:440px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.4)}h1{font-size:20px;margin:0 0 10px}p{color:#aeb8c4;margin:8px 0}b{color:#ffcf3f}a{color:#6fb1ff}</style></head><body><div class="box"><h1>🛠 La base de données est éteinte</h1><p>Le panneau d\'administration a besoin de la base pour fonctionner.</p><p>Lance le serveur (fichier <b>START</b> dans le dossier du jeu), attends « Base prête ! », puis <a href="?p=dashboard">recharge cette page</a>.</p></div></body></html>';
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/* Dates à la française (le champ HTML type=date n'est pas géré par Basilisk/Goanna) */
function fr_date(?string $iso): string { if ($iso && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$iso, $m)) return $m[3] . '/' . $m[2] . '/' . $m[1]; return (string)$iso; }
function parse_fr_date(?string $s): string { $s = trim((string)$s); if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]); if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s; return '1990-01-01'; }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function csrf_check(): void { $t = (string)($_POST['csrf'] ?? ''); if ($t === '' || empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $t)) { http_response_code(400); exit('CSRF invalide.'); } }
function flash(?string $m = null): ?string { if ($m !== null) { $_SESSION['flash'] = $m; return null; } $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function require_login(): void { if (empty($_SESSION['admin'])) { header('Location: /?p=login'); exit; } }
function make_hash(string $pw): string { return password_hash($pw, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 2, 'threads' => 1]); }
/* SEC02 : mot de passe provisoire ALÉATOIRE fort, compatible client V14 (6-10 car., alphanum sans ambiguïté). */
function make_temp_password(int $len = 9): string {
    $a = 'abcdefghjkmnpqrstuvwxyz23456789'; $n = strlen($a); $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $a[random_int(0, $n - 1)];
    return $s;
}
function redirect(string $to): void { header('Location: ' . $to); exit; }
function is_ajax(): bool { return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') || !empty($_POST['ajax']); }
function cur_page(): int { return max(1, (int)($_GET['pg'] ?? 1)); }
/* Rang max qu'un admin peut ATTRIBUER = son propre rang (anti-escalade de privilèges) */
function my_rank(): int { return (int)($_SESSION['admin']['rank'] ?? 1); }
/* Rang actuel du compte cible (0 si introuvable). */
function target_rank(int $id): int { if ($id <= 0) return 0; $st = db()->prepare('SELECT `rank` FROM users WHERE id=?'); $st->execute([$id]); $r = $st->fetchColumn(); return $r === false ? 0 : (int)$r; }
/* Anti-escalade : un compte de rang STRICTEMENT supérieur au mien est protégé (pas de modif/suppression/loginas). */
function is_protected_target(int $id): bool { return $id > 0 && target_rank($id) > my_rank(); }
/* ---- Anti-bruteforce connexion admin (fichier, par IP ; 5 échecs / 15 min => blocage 10 min) ---- */
function throttle_file(string $ctx): string { return sys_get_temp_dir() . '/retro14_thr_' . preg_replace('/[^a-z]/', '', $ctx) . '_' . md5((string)($_SERVER['REMOTE_ADDR'] ?? 'cli')) . '.json'; }
function throttle_blocked(string $ctx): int { $d = @json_decode((string)@file_get_contents(throttle_file($ctx)), true); $u = (int)($d['until'] ?? 0); $now = time(); return $u > $now ? $u - $now : 0; }
function throttle_fail(string $ctx): void { $f = throttle_file($ctx); $now = time(); $d = @json_decode((string)@file_get_contents($f), true) ?: []; if ((int)($d['first'] ?? 0) < $now - 900) $d = ['first' => $now, 'fails' => 0, 'until' => 0]; $d['fails'] = (int)($d['fails'] ?? 0) + 1; if ($d['fails'] >= 5) $d['until'] = $now + 600; @file_put_contents($f, json_encode($d), LOCK_EX); }
function throttle_ok(string $ctx): void { @unlink(throttle_file($ctx)); }
/* Icône réelle d'un mobi (téléchargée depuis Habbo -> c_images/furni_icons/<base>.png) */
function furni_icon(string $sprite): string {
    $b = preg_replace('/\*.*$/', '', $sprite);
    if ($b === '') return '';
    return is_file(__DIR__ . '/../c_images/furni_icons/' . $b . '.png') ? '/c_images/furni_icons/' . rawurlencode($b) . '.png' : '';
}
function furni_icon_img(string $sprite, int $size = 32): string {
    $u = furni_icon($sprite);
    $box = 'width:' . $size . 'px;height:' . $size . 'px';
    if ($u === '') return '<span class="ficon none" style="display:inline-block;' . $box . ';border-radius:6px;background:rgba(128,128,128,.12)"></span>';
    return '<img class="ficon" src="' . h($u) . '" alt="" loading="lazy" style="max-width:' . $size . 'px;max-height:' . $size . 'px;object-fit:contain;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated;vertical-align:middle">';
}

$p = $_GET['p'] ?? 'dashboard';

/* ---------- Base éteinte : page propre au lieu d'une erreur fatale ---------- */
try { db(); } catch (PDOException $e) { admin_db_down(); exit; }

/* ---------- Auth ---------- */
if ($p === 'logout') { $_SESSION = []; session_destroy(); redirect('?p=login'); }
if ($p === 'login') {
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        if (($w = throttle_blocked('admin')) > 0) { render_login('Trop de tentatives. Réessaie dans ' . (int)ceil($w / 60) . ' min.'); exit; }
        $st = db()->prepare('SELECT id,username,password,rank FROM users WHERE username=?'); $st->execute([trim($_POST['username'] ?? '')]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password'])) {
            if ((int)$u['rank'] >= MIN_RANK) { throttle_ok('admin'); session_regenerate_id(true); $_SESSION['admin'] = ['id' => $u['id'], 'username' => $u['username'], 'rank' => (int)$u['rank']]; redirect('?p=dashboard'); }
            $err = "Ce compte n'a pas les droits (rang " . MIN_RANK . "+ requis).";
        } else { throttle_fail('admin'); $err = 'Nom ou mot de passe incorrect.'; }
    }
    render_login($err); exit;
}
/* ---------- SSO : identifié sur le site = identifié à l'admin (une seule connexion) ---------- */
if (empty($_SESSION['admin']) && !empty($_SESSION['site_user']['id'])) {
    $st = db()->prepare('SELECT id,username,rank FROM users WHERE id=?'); $st->execute([(int)$_SESSION['site_user']['id']]);
    $su = $st->fetch();
    if ($su && (int)$su['rank'] >= MIN_RANK) $_SESSION['admin'] = ['id' => $su['id'], 'username' => $su['username'], 'rank' => (int)$su['rank']];
}
require_login();

/* ---------- Revérifie compte + rang en base à chaque requête (source de vérité) ---------- */
$rv = db()->prepare('SELECT `rank` FROM users WHERE id=?'); $rv->execute([(int)($_SESSION['admin']['id'] ?? 0)]);
$curRank = $rv->fetchColumn();
if ($curRank === false || (int)$curRank < MIN_RANK) { session_destroy(); redirect('/?p=login'); }
$_SESSION['admin']['rank'] = (int)$curRank;

/* ---------- Statut du redémarrage Kepler (AJAX, lecture seule, droit « server ») ---------- */
if (isset($_GET['rkstatus'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!tab_allowed('server')) { http_response_code(403); echo '{"state":"error","label":"Non autorisé"}'; exit; }
    $f = rk_status_path();
    echo (is_file($f) ? (string)@file_get_contents($f) : '{"state":"idle","label":"Aucune opération en cours"}');
    exit;
}

/* ---------- Téléchargement d'une sauvegarde (rang 7) ---------- */
if (isset($_GET['dlbackup']) && (int)($_SESSION['admin']['rank'] ?? 0) >= 7) {
    $name = basename((string)$_GET['dlbackup']);
    $path = backup_dir() . '/' . $name;
    if (preg_match('/^v14_\d{8}_\d{6}\.sql$/', $name) && is_file($path)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

/* ---------- Connexion « en tant que » un joueur (POST + CSRF, rang 7) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'loginas') {
    if ((int)($_SESSION['admin']['rank'] ?? 0) < 7) { http_response_code(403); exit('Réservé au rang 7.'); }
    csrf_check();
    $uid = (int)($_POST['id'] ?? 0);
    if (is_protected_target($uid)) { admin_log('denied', 'loginas refusé sur compte supérieur #' . $uid); http_response_code(403); exit('Action interdite : compte de rang supérieur.'); }
    $st = db()->prepare('SELECT username FROM users WHERE id=?'); $st->execute([$uid]); $uname = $st->fetchColumn();
    if ($uname !== false) {
        $ticket = bin2hex(random_bytes(16));
        db()->prepare('UPDATE users SET sso_ticket=? WHERE id=?')->execute([$ticket, $uid]);
        admin_log('login_as', 'Connexion en tant que ' . $uname . ' (#' . $uid . ')');
        redirect('/client.php?sso=' . $ticket);
    }
    redirect('?p=user&id=' . $uid);
}

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $back = $_POST['back'] ?? ('?p=' . $p); $ok = true; $msg = '';
    /* ---- Contrôle du droit PAR action, AVANT exécution (ferme le contournement POST) ---- */
    $reqTab = action_tab($a);
    if ($a !== '' && $reqTab === '') {
        // Action non cartographiée : REFUS par défaut (fail-closed), sauf rang maximum (7).
        if ((int)($_SESSION['admin']['rank'] ?? 0) < 7) {
            admin_log('denied', 'Action non cartographiée refusée: ' . $a . ' (rang ' . (int)($_SESSION['admin']['rank'] ?? 0) . ')');
            $deny = '🔒 Action inconnue refusée.';
            if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $deny]); exit; }
            flash($deny); redirect($back);
        }
    } elseif ($reqTab !== '' && !tab_allowed($reqTab)) {
        admin_log('denied', 'Action refusée: ' . $a . ' (onglet ' . $reqTab . ', rang ' . (int)($_SESSION['admin']['rank'] ?? 0) . ')');
        $deny = '🔒 Accès refusé : ton rang n\'autorise pas cette action.';
        if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $deny]); exit; }
        flash($deny); redirect($back);
    }
    /* ---- Anti-escalade : aucune action sur un compte de rang STRICTEMENT supérieur ---- */
    $targetActions = ['user_update', 'user_details', 'user_rank', 'user_password', 'user_credits', 'user_hc', 'user_delete'];
    if (in_array($a, $targetActions, true) && is_protected_target((int)($_POST['id'] ?? 0))) {
        admin_log('denied', 'Action ' . $a . ' refusée sur compte supérieur #' . (int)($_POST['id'] ?? 0) . ' (rang ' . (int)($_SESSION['admin']['rank'] ?? 0) . ')');
        $deny = '🔒 Action interdite : ce compte est d\'un rang supérieur au tien.';
        if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $deny]); exit; }
        flash($deny); redirect($back);
    }
    try {
        switch ($a) {
            case 'user_update':
                $uid = (int)$_POST['id']; $nr = max(1, min(my_rank(), (int)$_POST['rank'])); $nc = (int)$_POST['credits']; $nm = trim($_POST['motto'] ?? '');
                $os = db()->prepare('SELECT username,`rank`,credits,motto FROM users WHERE id=?'); $os->execute([$uid]); $o = $os->fetch();
                db()->prepare('UPDATE users SET rank=?, credits=?, motto=? WHERE id=?')->execute([$nr, $nc, $nm, $uid]);
                $ch = [];
                if ($o) { if ((int)$o['rank'] !== $nr) $ch[] = 'rang ' . $o['rank'] . '→' . $nr; if ((int)$o['credits'] !== $nc) $ch[] = 'crédits ' . $o['credits'] . '→' . $nc; if ((string)$o['motto'] !== $nm) $ch[] = 'motto modifié'; }
                $msg = '✅ ' . ($o['username'] ?? ('#' . $uid)) . ' mis à jour' . ($ch ? ' (' . implode(', ', $ch) . ')' : '') . '.'; break;
            case 'user_details':
                $bd = parse_fr_date($_POST['birthday'] ?? '');
                $sx = ($_POST['sex'] ?? 'M') === 'F' ? 'F' : 'M';
                db()->prepare('UPDATE users SET email=?, birthday=?, sex=?, motto=? WHERE id=?')->execute([trim($_POST['email'] ?? ''), $bd, $sx, trim($_POST['motto'] ?? ''), (int)$_POST['id']]);
                $msg = '✅ Détails du joueur enregistrés.'; break;
            case 'user_rank':
                db()->prepare('UPDATE users SET rank=? WHERE id=?')->execute([max(1, min(my_rank(), (int)$_POST['rank'])), (int)$_POST['id']]);
                $msg = '🎖️ Rang modifié. (Le joueur doit se reconnecter pour les nouveaux droits.)'; break;
            case 'credits_all':
                $d = (int)$_POST['delta']; db()->prepare('UPDATE users SET credits = GREATEST(0, credits + ?)')->execute([$d]);
                $msg = '💰 ' . ($d >= 0 ? '+' : '') . $d . ' crédits distribués à TOUS les joueurs.'; break;
            case 'user_password':
                if (strlen((string)$_POST['newpass']) < 4) { $ok = false; $msg = '❌ Mot de passe trop court (4 min).'; }
                else { db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([make_hash((string)$_POST['newpass']), (int)$_POST['id']]); $msg = '✅ Mot de passe changé.'; } break;
            case 'user_create':
                $u = trim($_POST['username'] ?? ''); $np = (string)$_POST['newpass'];
                if ($u === '' || strlen($np) < 4) { $ok = false; $msg = '❌ Nom vide ou mot de passe trop court.'; break; }
                $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([$u]);
                if ($st->fetch()) { $ok = false; $msg = '❌ Ce nom existe déjà.'; break; }
                $bd = parse_fr_date($_POST['birthday'] ?? '');
                db()->prepare('INSERT INTO users (username,password,rank,credits,email,birthday,motto,sex) VALUES (?,?,?,?,?,?,?,?)')->execute([$u, make_hash($np), max(1, min(my_rank(), (int)($_POST['rank'] ?? 1))), 100, $u . '@retro14.local', $bd, 'Nouveau Habbo', 'M']);
                $msg = '✅ Compte "' . $u . '" créé.'; break;
            case 'user_credits':
                db()->prepare('UPDATE users SET credits = credits + ? WHERE id=?')->execute([(int)$_POST['delta'], (int)$_POST['id']]);
                $msg = '💰 ' . ((int)$_POST['delta'] >= 0 ? '+' : '') . (int)$_POST['delta'] . ' crédits.'; break;
            case 'user_hc':
                $days = (int)$_POST['days']; $now = time(); $uid = (int)$_POST['id']; // UNIX en SECONDES (comme le JAR Kepler)
                if ($days > 0) {
                    $cs = db()->prepare('SELECT club_subscribed, club_expiration FROM users WHERE id=?'); $cs->execute([$uid]); $cur = $cs->fetch() ?: ['club_subscribed' => 0, 'club_expiration' => 0];
                    $first = (int)$cur['club_subscribed'] > 0 ? (int)$cur['club_subscribed'] : $now;         // conserve la 1re inscription (badge or)
                    $base = (int)$cur['club_expiration'] > $now ? (int)$cur['club_expiration'] : $now;        // prolonge si encore actif
                    db()->prepare('UPDATE users SET club_subscribed=?, club_expiration=? WHERE id=?')->execute([$first, $base + $days * 86400, $uid]);
                } else {
                    db()->prepare('UPDATE users SET club_subscribed=0, club_expiration=0 WHERE id=?')->execute([$uid]);
                }
                $msg = $days > 0 ? '⭐ Club Habbo accordé (' . $days . ' j).' : '⭐ Club Habbo retiré.'; break;
            case 'user_delete':
                $uid = (int)$_POST['id'];
                $un = db()->prepare('SELECT username FROM users WHERE id=?'); $un->execute([$uid]); $dname = $un->fetchColumn();
                if ($dname === false) { $ok = false; $msg = '❌ Joueur introuvable.'; break; }
                db()->beginTransaction();
                try {
                    foreach ([
                        'messenger_requests WHERE from_id=? OR to_id=?',
                        'messenger_friends WHERE from_id=? OR to_id=?',
                        'users_badges WHERE user_id=?',
                        'users_bans WHERE ban_type=\'USER_ID\' AND banned_value=?',
                    ] as $frag) {
                        $params = substr_count($frag, '?') === 2 ? [$uid, $uid] : [$uid];
                        try { db()->prepare('DELETE FROM ' . $frag)->execute($params); } catch (Throwable $e) {} // tables optionnelles
                    }
                    db()->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
                    db()->commit();
                    $msg = '🗑️ Compte "' . $dname . '" supprimé (demandes/amis/badges nettoyés).';
                } catch (Throwable $e) {
                    if (db()->inTransaction()) db()->rollBack();
                    $ok = false; $msg = '❌ Suppression annulée (erreur) : rien n\'a été supprimé.';
                }
                break;
            case 'pwreset_do':
                ensure_pw_resets();
                $rid = (int)($_POST['rid'] ?? 0);
                $rq = db()->prepare('SELECT username,status FROM password_resets WHERE id=?'); $rq->execute([$rid]); $req = $rq->fetch();
                if (!$req) { $ok = false; $msg = '❌ Demande introuvable.'; break; }
                $us = db()->prepare('SELECT id,`rank` FROM users WHERE username=?'); $us->execute([(string)$req['username']]); $tu = $us->fetch();
                if (!$tu) { $ok = false; $msg = '❌ Aucun compte nommé « ' . $req['username'] .' » (demande marquée rejetée).'; db()->prepare('UPDATE password_resets SET status=\'rejected\', handled_by=?, handled_at=NOW() WHERE id=?')->execute([$_SESSION['admin']['username'] ?? '?', $rid]); break; }
                if (is_protected_target((int)$tu['id'])) { $ok = false; $msg = '🔒 Ce compte est d\'un rang supérieur au tien : reset refusé.'; break; }
                // SEC02 : usage unique — on ne (re)traite qu'une demande 'pending'.
                if (($req['status'] ?? '') !== 'pending') { $ok = false; $msg = 'ℹ️ Cette demande a déjà été traitée (aucun nouveau mot de passe généré).'; break; }
                // SEC02 : mot de passe provisoire ALÉATOIRE fort (fini « Habbo + 4 chiffres ») + invalidation du ticket SSO existant.
                $tmp = make_temp_password();
                db()->prepare('UPDATE users SET password=?, sso_ticket=? WHERE id=?')->execute([make_hash($tmp), '', (int)$tu['id']]);
                db()->prepare('UPDATE password_resets SET status=\'done\', handled_by=?, handled_at=NOW() WHERE id=?')->execute([$_SESSION['admin']['username'] ?? '?', $rid]);
                admin_log('pwreset_do', 'réinitialisation pour « ' . $req['username'] . ' » (mot de passe provisoire aléatoire NON journalisé ; ticket SSO invalidé)');
                $msg = '🔑 Mot de passe provisoire pour « ' . $req['username'] . ' » : ' . $tmp . ' — à usage unique, communique-le au joueur et demande-lui de le changer. Ses anciennes sessions/tickets sont invalidés.'; break;
            case 'pwreset_reject':
                ensure_pw_resets();
                db()->prepare('UPDATE password_resets SET status=\'rejected\', handled_by=?, handled_at=NOW() WHERE id=?')->execute([$_SESSION['admin']['username'] ?? '?', (int)($_POST['rid'] ?? 0)]);
                $msg = '🗑️ Demande rejetée.'; break;
            case 'cmsg_handle':
                ensure_contact_msgs();
                db()->prepare('UPDATE contact_messages SET status=\'handled\', handled_by=?, handled_at=NOW() WHERE id=?')->execute([$_SESSION['admin']['username'] ?? '?', (int)($_POST['id'] ?? 0)]);
                $msg = '✅ Message marqué comme traité.'; break;
            case 'cmsg_delete':
                ensure_contact_msgs();
                db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
                $msg = '🗑️ Message supprimé.'; break;
            /* --- Assistance : conversations joueur ↔ staff --- */
            case 'conv_reply': {
                ensure_conversations();
                $cid = (int)($_POST['cid'] ?? 0);
                $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 4000);
                if ($cid <= 0 || $body === '') { $ok = false; $msg = '❌ Réponse vide.'; break; }
                $meU = (string)($_SESSION['admin']['username'] ?? 'Équipe');
                conv_add_message($cid, 'staff', $meU, $body, 0);
                if (!empty($_POST['resolve'])) db()->prepare("UPDATE conversations SET status='resolved' WHERE id=?")->execute([$cid]);
                conv_event_log($cid, $meU, 'reply', !empty($_POST['resolve']) ? 'réponse + résolu' : 'réponse');
                admin_log('conv_reply', '#' . $cid . (!empty($_POST['resolve']) ? ' (résolu)' : ''));
                $msg = '✉️ Réponse envoyée au joueur.'; break;
            }
            case 'conv_status': {
                ensure_conversations();
                $cid = (int)($_POST['cid'] ?? 0);
                $s = in_array(($_POST['status'] ?? ''), ['waiting_staff', 'waiting_player', 'resolved'], true) ? $_POST['status'] : 'waiting_staff';
                db()->prepare('UPDATE conversations SET status=? WHERE id=?')->execute([$s, $cid]);
                conv_event_log($cid, (string)($_SESSION['admin']['username'] ?? ''), 'status', $s);
                admin_log('conv_status', '#' . $cid . ' → ' . $s);
                $msg = '✅ Statut mis à jour.'; break;
            }
            case 'conv_new': {
                ensure_conversations();
                $toName = trim((string)($_POST['username'] ?? ''));
                $uid = 0; if ($toName !== '') { $rs = db()->prepare('SELECT id FROM users WHERE username=?'); $rs->execute([$toName]); $uid = (int)$rs->fetchColumn(); }
                if ($uid <= 0) { $ok = false; $msg = '❌ Joueur introuvable' . ($toName !== '' ? ' : « ' . $toName . ' »' : '') . '.'; break; }
                $subj = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 150); if ($subj === '') $subj = 'Message de l\'équipe';
                $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 4000);
                if ($body === '') { $ok = false; $msg = '❌ Message vide.'; break; }
                $meU = (string)($_SESSION['admin']['username'] ?? 'Équipe');
                $cid = conv_create($uid, $subj, 'staff', 'staff', $meU, $body);
                if ($cid) conv_event_log($cid, $meU, 'create', 'conversation créée par le staff');
                admin_log('conv_new', '→ ' . $toName . ' #' . $cid);
                $msg = $cid ? '✉️ Conversation créée et message envoyé.' : '❌ Échec de l\'envoi.'; break;
            }
            case 'conv_reprise': {
                $n = conv_reprise();
                admin_log('conv_reprise', $n . ' repris');
                $msg = '📥 Reprise terminée : ' . $n . ' ancien(s) message(s) importé(s) en conversations.'; break;
            }
            case 'conv_assign': {
                ensure_conversations();
                $cid = (int)($_POST['cid'] ?? 0);
                $me = (string)($_SESSION['admin']['username'] ?? '');
                $to = (($_POST['to'] ?? '') === 'me') ? $me : '';
                db()->prepare('UPDATE conversations SET assigned_to=? WHERE id=?')->execute([$to, $cid]);
                conv_event_log($cid, $me, 'assign', $to !== '' ? ('→ ' . $to) : 'libérée');
                admin_log('conv_assign', '#' . $cid . ($to !== '' ? ' → ' . $to : ' (libérée)'));
                $msg = $to !== '' ? '🙋 Conversation prise en charge.' : '↩︎ Conversation libérée.'; break;
            }
            case 'conv_note': {
                ensure_conversations();
                $cid = (int)($_POST['cid'] ?? 0);
                $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 4000);
                if ($cid <= 0 || $body === '') { $ok = false; $msg = '❌ Note vide.'; break; }
                $meU = (string)($_SESSION['admin']['username'] ?? 'Équipe');
                conv_add_message($cid, 'staff', $meU, $body, 1);
                conv_event_log($cid, $meU, 'note', 'note interne');
                admin_log('conv_note', '#' . $cid);
                $msg = '🔒 Note interne ajoutée (non visible par le joueur).'; break;
            }
            case 'conv_category': {
                ensure_conversations();
                $cid = (int)($_POST['cid'] ?? 0);
                $cat = mb_substr(trim((string)($_POST['category'] ?? '')), 0, 40);
                if ($cat === '') $cat = 'question';
                db()->prepare('UPDATE conversations SET category=? WHERE id=?')->execute([$cat, $cid]);
                conv_event_log($cid, (string)($_SESSION['admin']['username'] ?? ''), 'category', $cat);
                admin_log('conv_category', '#' . $cid . ' → ' . $cat);
                $msg = '🏷️ Catégorie mise à jour.'; break;
            }
            case 'conv_ack_set': {
                $txt = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 1000);
                try { db()->prepare('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')->execute(['conv.ack', $txt]); } catch (Throwable $e) {}
                admin_log('conv_ack_set', $txt === '' ? '(désactivé)' : 'maj');
                $msg = $txt === '' ? '✅ Accusé de réception désactivé.' : '✅ Accusé de réception enregistré.'; break;
            }
            case 'conv_broadcast': {
                ensure_conversations();
                $subject = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 150);
                $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 4000);
                $mode = ($_POST['mode'] ?? 'list') === 'rank' ? 'rank' : 'list';
                if ($body === '') { $ok = false; $msg = '❌ Message vide.'; break; }
                $ids = [];
                if ($mode === 'rank') {
                    $minRank = max(1, (int)($_POST['min_rank'] ?? 5));
                    try { foreach (db()->query('SELECT id FROM users WHERE `rank`>=' . $minRank) as $r) $ids[] = (int)$r['id']; } catch (Throwable $e) {}
                } else {
                    $names = preg_split('/[\s,;]+/', (string)($_POST['usernames'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                    $names = array_slice(array_unique($names), 0, 500);
                    foreach ($names as $nm) { try { $q = db()->prepare('SELECT id FROM users WHERE username=?'); $q->execute([$nm]); $uid = (int)$q->fetchColumn(); if ($uid > 0) $ids[] = $uid; } catch (Throwable $e) {} }
                }
                if (!$ids) { $ok = false; $msg = '❌ Aucun destinataire valide.'; break; }
                if (count($ids) > 500) { $ok = false; $msg = '❌ Trop de destinataires (' . count($ids) . '). Limite : 500.'; break; }
                $n = conv_broadcast($ids, $subject, $body, (string)($_SESSION['admin']['username'] ?? 'Équipe'));
                alert_log_add('messagerie (persistant)', ($mode === 'rank' ? 'rang ≥ ' . (int)($_POST['min_rank'] ?? 5) : 'liste de pseudos') . ' — ' . $n . ' destinataire(s)', ($subject !== '' ? '[' . $subject . '] ' : '') . $body, 'créée pour ' . $n . ' joueur(s) · réponses autorisées (privées)');
                admin_log('conv_broadcast', $n . ' destinataires (' . $mode . ')');
                $msg = '📣 Annonce envoyée à ' . $n . ' joueur' . ($n > 1 ? 's' : '') . '.'; break;
            }
            case 'conv_merge': {
                ensure_conversations();
                $target = (int)($_POST['cid'] ?? 0);
                $source = (int)($_POST['src'] ?? 0);
                if (conv_merge($target, $source)) { conv_event_log($target, (string)($_SESSION['admin']['username'] ?? ''), 'merge', 'absorbe #' . $source); admin_log('conv_merge', '#' . $source . ' → #' . $target); $msg = '🔗 Conversations fusionnées.'; }
                else { $ok = false; $msg = '❌ Fusion impossible (joueurs différents ou conversation invalide).'; }
                break;
            }
            case 'conv_staff_box': {
                ensure_conversations();
                $cid = (int)($_POST['cid'] ?? 0);
                $op = (string)($_POST['op'] ?? '');
                $meU = (string)($_SESSION['admin']['username'] ?? '');
                if ($cid <= 0 || !in_array($op, ['archive', 'trash', 'restore'], true)) { $ok = false; $msg = '❌ Action invalide.'; break; }
                if ($op === 'archive')    db()->prepare('UPDATE conversations SET staff_archived=1, staff_trashed=0 WHERE id=?')->execute([$cid]);
                elseif ($op === 'trash')  db()->prepare('UPDATE conversations SET staff_trashed=1, staff_archived=0 WHERE id=?')->execute([$cid]);
                else                      db()->prepare('UPDATE conversations SET staff_trashed=0, staff_archived=0 WHERE id=?')->execute([$cid]);
                conv_event_log($cid, $meU, 'box', $op);
                admin_log('conv_staff_box', '#' . $cid . ' ' . $op);
                $msg = $op === 'archive' ? '🗂 Dossier archivé.' : ($op === 'trash' ? '🗑 Dossier mis à la corbeille.' : '↩︎ Dossier restauré.'); break;
            }
            case 'conv_hide': {
                ensure_conversations();
                $mid = (int)($_POST['mid'] ?? 0);
                $cid = (int)($_POST['cid'] ?? 0);
                $on = (string)($_POST['on'] ?? '1') === '1';
                $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
                $meU = (string)($_SESSION['admin']['username'] ?? '');
                if ($mid <= 0) { $ok = false; $msg = '❌ Message introuvable.'; break; }
                if ($on && $reason === '') { $ok = false; $msg = '❌ Motif obligatoire pour masquer un message.'; break; }
                conv_moderate_msg($mid, $meU, $reason, $on);
                conv_event_log($cid, $meU, $on ? 'hide_msg' : 'unhide_msg', $on ? ('msg#' . $mid . ' : ' . $reason) : ('msg#' . $mid));
                admin_log('conv_hide', ($on ? 'masque' : 'rétablit') . ' msg#' . $mid);
                $msg = $on ? '🚫 Message masqué aux participants.' : '✅ Message rétabli.'; break;
            }
            case 'umsg_send':
                // Bascule vers le nouveau système : crée une conversation (staff -> joueur),
                // lisible par le joueur dans « Mes messages ». (Les anciens user_messages ne
                // sont plus affichés côté joueur.)
                $toName = trim((string)($_POST['username'] ?? ''));
                if ($toName !== '') { $rs = db()->prepare('SELECT id,username FROM users WHERE username=?'); $rs->execute([$toName]); if ($row = $rs->fetch()) { $uid = (int)$row['id']; $un = $row['username']; } else { $uid = 0; $un = false; } }
                else { $uid = (int)($_POST['id'] ?? 0); $chk = db()->prepare('SELECT username FROM users WHERE id=?'); $chk->execute([$uid]); $un = $chk->fetchColumn(); }
                if ($un === false) { $ok = false; $msg = '❌ Joueur introuvable' . ($toName !== '' ? ' : « ' . $toName . ' »' : '') . '.'; break; }
                $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 4000);
                if ($body === '') { $ok = false; $msg = '❌ Message vide.'; break; }
                $meU = (string)($_SESSION['admin']['username'] ?? 'Équipe');
                $subj = mb_substr(trim((string)($_POST['subject'] ?? '')), 0, 150); if ($subj === '') $subj = 'Message de l\'équipe';
                if (function_exists('conv_create')) { $ncid = conv_create($uid, $subj, 'staff', 'staff', $meU, $body); if ($ncid) conv_event_log($ncid, $meU, 'create', 'message depuis la fiche joueur'); }
                else { ensure_user_msgs(); db()->prepare('INSERT INTO user_messages (user_id,from_staff,subject,body) VALUES (?,?,?,?)')->execute([$uid, $meU, $subj, $body]); }
                $msg = '✉️ Message envoyé à ' . $un . '.'; break;
            case 'smsg_post':
                ensure_staff_msgs();
                $b = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000);
                if ($b === '') { $ok = false; $msg = '❌ Message vide.'; break; }
                db()->prepare('INSERT INTO staff_messages (author,body) VALUES (?,?)')->execute([$_SESSION['admin']['username'] ?? '?', $b]);
                $msg = '💬 Message posté sur le fil de l\'équipe.'; break;
            case 'smsg_delete':
                ensure_staff_msgs();
                $mid = (int)($_POST['id'] ?? 0);
                $au = db()->prepare('SELECT author FROM staff_messages WHERE id=?'); $au->execute([$mid]); $author = $au->fetchColumn();
                if ($author !== false && ($author === ($_SESSION['admin']['username'] ?? '') || my_rank() >= 7)) { db()->prepare('DELETE FROM staff_messages WHERE id=?')->execute([$mid]); $msg = '🗑️ Message supprimé.'; }
                else { $ok = false; $msg = '🔒 Tu ne peux supprimer que tes propres messages.'; }
                break;
            case 'badge_add':
                $code = strtoupper(trim($_POST['badge'] ?? '')); $name = trim($_POST['username'] ?? '');
                if (strlen($code) < 1 || strlen($code) > 3 || !in_array($code, badge_codes(), true)) { $ok = false; $msg = '❌ Code de badge invalide.'; break; }
                $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([$name]); $uid = $st->fetchColumn();
                if (!$uid) { $ok = false; $msg = '❌ Joueur "' . $name . '" introuvable.'; break; }
                $st = db()->prepare('SELECT 1 FROM users_badges WHERE user_id=? AND badge=?'); $st->execute([$uid, $code]);
                if ($st->fetch()) { $ok = false; $msg = 'ℹ️ Ce joueur a déjà ce badge.'; break; }
                db()->prepare('INSERT INTO users_badges (user_id,badge) VALUES (?,?)')->execute([$uid, $code]);
                $msg = '📛 Badge ' . $code . ' attribué à ' . $name . '.'; break;
            case 'badge_remove':
                db()->prepare('DELETE FROM users_badges WHERE user_id=? AND badge=?')->execute([(int)$_POST['uid'], strtoupper((string)$_POST['badge'])]);
                $msg = '✅ Badge retiré.'; break;
            case 'rank_badge_add':
                $code = strtoupper(trim($_POST['badge'] ?? '')); $rk = max(1, min(7, (int)$_POST['rank']));
                if (!in_array($code, badge_codes(), true)) { $ok = false; $msg = '❌ Code de badge invalide.'; break; }
                $st = db()->prepare('SELECT 1 FROM rank_badges WHERE rank=? AND badge=?'); $st->execute([$rk, $code]);
                if (!$st->fetch()) db()->prepare('INSERT INTO rank_badges (rank,badge) VALUES (?,?)')->execute([$rk, $code]);
                $msg = '🎖️ Badge ' . $code . ' ajouté au rang ' . $rk . '.'; break;
            case 'rank_badge_remove':
                db()->prepare('DELETE FROM rank_badges WHERE rank=? AND badge=?')->execute([(int)$_POST['rank'], strtoupper((string)$_POST['badge'])]);
                $msg = '✅ Badge de rang retiré.'; break;
            case 'bot_update':
                $nl2pipe = fn($s) => implode('|', array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)$s)), fn($x) => $x !== ''));
                db()->prepare('UPDATE rooms_bots SET name=?, mission=?, speech=?, response=?, unrecognised_response=? WHERE id=?')
                    ->execute([substr(trim($_POST['name'] ?? ''), 0, 25), trim($_POST['mission'] ?? ''), $nl2pipe($_POST['speech'] ?? ''), $nl2pipe($_POST['response'] ?? ''), $nl2pipe($_POST['unrecognised_response'] ?? ''), (int)$_POST['id']]);
                $msg = '🤖 Bot mis à jour. (Redémarre l\'émulateur pour l\'appliquer en jeu.)'; break;
            case 'game_rank_update':
                db()->prepare('UPDATE games_ranks SET title=?, min_points=?, max_points=? WHERE id=?')
                    ->execute([trim($_POST['title'] ?? ''), (int)$_POST['min_points'], (int)$_POST['max_points'], (int)$_POST['id']]);
                $msg = '🏆 Rang de jeu mis à jour.'; break;
            case 'game_points':
                $col = ($_POST['game'] ?? '') === 'snowstorm' ? 'snowstorm_points' : 'battleball_points';
                db()->prepare("UPDATE users SET $col=? WHERE id=?")->execute([(int)$_POST['points'], (int)$_POST['id']]);
                $msg = '✅ Points mis à jour.'; break;
            case 'setting_update':
                db()->prepare('UPDATE settings SET value=? WHERE setting=?')->execute([(string)$_POST['value'], (string)$_POST['setting']]); $msg = '✅ Réglage enregistré.'; break;
            case 'xtext_update':
                db()->prepare('INSERT INTO external_texts (entry,text) VALUES (?,?) ON DUPLICATE KEY UPDATE text=VALUES(text)')->execute([(string)$_POST['entry'], (string)$_POST['value']]); $msg = '✅ Texte enregistré (redémarre l\'émulateur pour l\'appliquer).'; break;
            case 'tabperm_update':
                if ((int)($_SESSION['admin']['rank'] ?? 0) < 7) { $ok = false; $msg = '🔒 Réservé au rang 7 (Administrateur).'; break; }
                ensure_tab_perms();
                foreach (admin_nav() as $tabk => $__) {
                    if ($tabk === 'dashboard') continue;
                    $mr = max(5, min(7, (int)($_POST['rank_' . $tabk] ?? 5)));
                    db()->prepare('INSERT INTO admin_tab_perms (tab,min_rank) VALUES (?,?) ON DUPLICATE KEY UPDATE min_rank=VALUES(min_rank)')->execute([$tabk, $mr]);
                }
                $msg = '✅ Accès aux onglets enregistrés.'; break;
            case 'room_update':
                db()->prepare('UPDATE rooms SET name=?, description=?, visitors_max=? WHERE id=?')->execute([trim($_POST['name'] ?? ''), trim($_POST['description'] ?? ''), (int)$_POST['visitors_max'], (int)$_POST['id']]); $msg = '✅ Salle mise à jour.'; break;
            case 'room_decor':
                db()->prepare('UPDATE rooms SET ccts=? WHERE id=?')->execute([trim($_POST['ccts'] ?? ''), (int)$_POST['id']]); $msg = '🎨 Décor changé ! Redémarre l\'émulateur.'; break;
            case 'room_toggle':
                db()->prepare('UPDATE rooms SET is_hidden = 1 - is_hidden WHERE id=?')->execute([(int)$_POST['id']]); $msg = '✅ Salle basculée. Redémarre l\'émulateur.'; break;
            case 'furni_convert':
                @set_time_limit(180);
                $cvSprite = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_POST['sprite'] ?? ''));
                $cvRev = (int)($_POST['revision'] ?? 0);
                $cvType = (($_POST['ftype'] ?? 's') === 'i') ? 'i' : 's';
                $cvLen = max(0, (int)($_POST['len'] ?? 1)); $cvWid = max(0, (int)($_POST['wid'] ?? 1));
                if ($cvSprite === '' || $cvRev <= 0) { $ok = false; $msg = '❌ Sprite ou révision invalide.'; break; }
                $kit = 'C:\\laragon\\www\\HabboretroV14\\tools\\elias';
                $hof = dirname(__DIR__) . '\\dcr\\hof_furni';
                foreach (glob($kit . '\\input\\*.swf') as $g) @unlink($g);
                foreach (glob($kit . '\\output\\*.cct') as $g) @unlink($g);
                $url = "https://images.habbo.com/dcr/hof_furni/$cvRev/$cvSprite.swf";
                $ctx = stream_context_create(['http' => ['timeout' => 20], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
                $swf = @file_get_contents($url, false, $ctx);
                if ($swf === false || strlen($swf) < 100 || !in_array(substr($swf, 0, 3), ['CWS', 'FWS', 'ZWS'], true)) { $ok = false; $msg = '❌ SWF introuvable sur Habbo. Vérifie le sprite et la révision. URL testée : ' . $url; break; }
                file_put_contents($kit . '\\input\\' . $cvSprite . '.swf', $swf);
                $dims = $cvType === 'i' ? '"",""' : '"' . $cvLen . '","' . $cvWid . '"';
                file_put_contents($kit . '\\furnidata.txt', '[["' . $cvType . '","0","' . $cvSprite . '","' . $cvRev . '","0",' . $dims . ',"0","' . $cvSprite . '","' . $cvSprite . '"]]');
                @exec('cmd /c "' . $kit . '\\convert.bat" 2>&1', $cvOut, $cvCode);
                $copied = [];
                foreach (glob($kit . '\\output\\*.cct') as $c) { if (@copy($c, $hof . '\\' . basename($c))) $copied[] = basename($c); }
                if (count($copied) > 0) {
                    $extra = '';
                    if (isset($_POST['mkdef'])) {
                        $exists = db()->prepare('SELECT COUNT(*) FROM items_definitions WHERE sprite=?'); $exists->execute([$cvSprite]);
                        if (!(int)$exists->fetchColumn()) {
                            $beh = $cvType === 'i' ? 'wall_item,requires_rights_for_interaction' : 'solid,requires_rights_for_interaction';
                            $sid = (int)db()->query('SELECT COALESCE(MAX(sprite_id),12000)+1 FROM items_definitions')->fetchColumn();
                            db()->prepare('INSERT INTO items_definitions (sprite,sprite_id,name,description,colour,length,width,top_height,max_status,behaviour,interactor,is_tradable,is_recyclable,drink_ids) VALUES (?,?,?,?,\'0,0,0\',?,?,?,?,?,\'default\',1,0,\'\')')
                                ->execute([$cvSprite, $sid, $cvSprite, '', $cvType === 'i' ? 0 : $cvLen, $cvType === 'i' ? 0 : $cvWid, $cvType === 'i' ? 0 : 1, '2', $beh]);
                            $extra = ' + définition créée';
                        }
                    }
                    admin_log('furni_convert', 'items_definitions', 0, null, ['sprite' => $cvSprite, 'rev' => $cvRev, 'files' => $copied]);
                    $msg = '✅ Converti et installé : ' . implode(', ', $copied) . $extra . '. Vide le cache Basilisk pour voir le meuble en jeu.';
                } else { $ok = false; $msg = '❌ Conversion échouée. Détail : ' . h(implode(' | ', array_slice($cvOut, -6))); }
                break;
            case 'furni_decompile':
                @set_time_limit(120);
                $dsprite = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_POST['dsprite'] ?? ''));
                if ($dsprite === '') { $ok = false; $msg = '❌ Sprite invalide.'; break; }
                $pr = 'C:\\laragon\\www\\HabboretroV14\\tools\\projectorrays';
                $hof = dirname(__DIR__) . '\\dcr\\hof_furni';
                $cct = $hof . '\\hh_furni_xx_' . $dsprite . '.cct';
                if (!file_exists($cct)) { $ok = false; $msg = '❌ CCT introuvable : hh_furni_xx_' . $dsprite . '.cct'; break; }
                if (!file_exists($pr . '\\projectorrays.exe')) { $ok = false; $msg = '❌ ProjectorRays introuvable dans ' . $pr; break; }
                $outdir = __DIR__ . '\\_decompiled'; @mkdir($outdir);
                $work = $pr . '\\work_' . $dsprite . '.cct'; @copy($cct, $work);
                @exec('"' . $pr . '\\projectorrays.exe" decompile "' . $work . '" 2>&1', $dout, $dcode);
                $cst = $pr . '\\work_' . $dsprite . '.cst';
                if (file_exists($cst)) { @copy($cst, $outdir . '\\' . $dsprite . '.cst'); @unlink($work); @unlink($cst);
                    admin_log('furni_decompile', 'cct', 0, null, ['sprite' => $dsprite]);
                    $msg = '✅ Décompilé ! Le .cst est dispo en téléchargement dans la liste ci-dessous (ouvre-le dans Adobe Director).';
                } else { $ok = false; $msg = '❌ Décompilation échouée : ' . h(implode(' | ', array_slice($dout, -4))); }
                break;
            case 'cat_item':
                db()->prepare('UPDATE catalogue_items SET name=?, description=?, page_id=?, price=?, amount=?, is_hidden=? WHERE id=?')->execute([trim($_POST['name'] ?? ''), trim($_POST['description'] ?? ''), trim($_POST['page_id'] ?? ''), (int)$_POST['price'], max(1, (int)$_POST['amount']), isset($_POST['is_hidden']) ? 1 : 0, (int)$_POST['id']]); $msg = '✅ Article mis à jour. Redémarre l\'émulateur pour le voir en jeu.'; break;
            case 'cat_page':
                db()->prepare('UPDATE catalogue_pages SET name=?, body=?, image_headline=?, order_id=?, min_role=?, is_club_only=?, index_visible=? WHERE id=?')->execute([trim($_POST['name'] ?? ''), trim($_POST['body'] ?? ''), trim($_POST['image_headline'] ?? ''), (int)$_POST['order_id'], (int)$_POST['min_role'], isset($_POST['is_club_only']) ? 1 : 0, isset($_POST['index_visible']) ? 1 : 0, (int)$_POST['id']]); $msg = '✅ Page catalogue mise à jour. Redémarre l\'émulateur pour la voir en jeu.'; break;
            case 'cat_page_move':
                $pid = (int)$_POST['id']; $dir = ($_POST['dir'] ?? 'up') === 'down' ? 'down' : 'up';
                $cs = db()->prepare('SELECT order_id FROM catalogue_pages WHERE id=?'); $cs->execute([$pid]); $co = $cs->fetchColumn();
                if ($co !== false) {
                    $sql = $dir === 'up' ? 'SELECT id,order_id FROM catalogue_pages WHERE order_id<? ORDER BY order_id DESC LIMIT 1' : 'SELECT id,order_id FROM catalogue_pages WHERE order_id>? ORDER BY order_id ASC LIMIT 1';
                    $ns = db()->prepare($sql); $ns->execute([$co]); $adj = $ns->fetch();
                    if ($adj) { db()->prepare('UPDATE catalogue_pages SET order_id=? WHERE id=?')->execute([(int)$adj['order_id'], $pid]); db()->prepare('UPDATE catalogue_pages SET order_id=? WHERE id=?')->execute([(int)$co, (int)$adj['id']]); }
                }
                $msg = '✅ Ordre modifié. Redémarre l\'émulateur pour le voir en jeu.'; break;
            case 'navcat_update':
                db()->prepare('UPDATE rooms_categories SET name=?, order_id=?, minrole_access=?, allow_trading=? WHERE id=?')->execute([trim($_POST['name'] ?? ''), (int)$_POST['order_id'], (int)$_POST['minrole_access'], isset($_POST['allow_trading']) ? 1 : 0, (int)$_POST['id']]); $msg = '✅ Catégorie mise à jour. Redémarre l\'émulateur pour la voir en jeu.'; break;
            case 'navcat_move':
                $cid = (int)$_POST['id']; $dir = ($_POST['dir'] ?? 'up') === 'down' ? 'down' : 'up';
                $cs = db()->prepare('SELECT order_id,parent_id FROM rooms_categories WHERE id=?'); $cs->execute([$cid]); $cr = $cs->fetch();
                if ($cr) {
                    $sql = $dir === 'up' ? 'SELECT id,order_id FROM rooms_categories WHERE parent_id=? AND order_id<? ORDER BY order_id DESC LIMIT 1' : 'SELECT id,order_id FROM rooms_categories WHERE parent_id=? AND order_id>? ORDER BY order_id ASC LIMIT 1';
                    $ns = db()->prepare($sql); $ns->execute([(int)$cr['parent_id'], (int)$cr['order_id']]); $adj = $ns->fetch();
                    if ($adj) { db()->prepare('UPDATE rooms_categories SET order_id=? WHERE id=?')->execute([(int)$adj['order_id'], $cid]); db()->prepare('UPDATE rooms_categories SET order_id=? WHERE id=?')->execute([(int)$cr['order_id'], (int)$adj['id']]); }
                }
                $msg = '✅ Ordre modifié. Redémarre l\'émulateur pour le voir en jeu.'; break;
            case 'ban_add':
                $target = trim($_POST['value'] ?? ''); $type = in_array($_POST['type'] ?? '', ['USER_ID', 'IP_ADDRESS', 'MACHINE_ID'], true) ? $_POST['type'] : 'USER_ID';
                $days = (int)($_POST['days'] ?? 0); $until = $days > 0 ? (int)((time() + $days * 86400) * 1000) : BAN_PERMANENT;
                if ($type === 'USER_ID') { $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([$target]); $uid = $st->fetchColumn(); if (!$uid) { $ok = false; $msg = '❌ Joueur "' . $target . '" introuvable.'; break; } $target = (string)$uid; }
                db()->prepare('INSERT INTO users_bans (ban_type,banned_value,message,banned_until) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE message=VALUES(message), banned_until=VALUES(banned_until), ban_type=VALUES(ban_type)')->execute([$type, $target, trim($_POST['message'] ?? 'Banni'), $until]);
                $msg = '🚫 Bannissement enregistré.'; break;
            case 'ban_remove':
                db()->prepare('DELETE FROM users_bans WHERE banned_value=?')->execute([(string)$_POST['value']]); $msg = '✅ Bannissement levé.'; break;
            case 'news_add':
                ensure_news();
                $col = in_array($_POST['color'] ?? '', ['blue', 'green', 'purple', 'orange'], true) ? $_POST['color'] : 'blue';
                db()->prepare('INSERT INTO site_news (title,category,color,body,author) VALUES (?,?,?,?,?)')
                    ->execute([substr(trim($_POST['title'] ?? ''), 0, 150), substr(trim($_POST['category'] ?? 'À la une'), 0, 40), $col, sanitize_html((string)($_POST['body'] ?? '')), (string)$_SESSION['admin']['username']]);
                $msg = '📰 Actualité publiée.'; break;
            case 'news_update':
                ensure_news();
                $col = in_array($_POST['color'] ?? '', ['blue', 'green', 'purple', 'orange'], true) ? $_POST['color'] : 'blue';
                db()->prepare('UPDATE site_news SET title=?, category=?, color=?, body=? WHERE id=?')
                    ->execute([substr(trim($_POST['title'] ?? ''), 0, 150), substr(trim($_POST['category'] ?? 'À la une'), 0, 40), $col, sanitize_html((string)($_POST['body'] ?? '')), (int)$_POST['id']]);
                $msg = '✅ Actualité mise à jour.'; break;
            case 'news_delete':
                ensure_news();
                db()->prepare('DELETE FROM site_news WHERE id=?')->execute([(int)$_POST['id']]); $msg = '🗑️ Actualité supprimée.'; break;
            /* --- Gestion du Site (v2) : carrousel + réglages accueil --- */
            case 'carousel_save':
                ensure_carousel_admin();
                $cpage = preg_replace('/[^a-z0-9_\-]/', '', (string)($_POST['page'] ?? 'home')); if ($cpage === '') $cpage = 'home';
                $slot = max(1, min(4, (int)($_POST['slot'] ?? 0)));
                $active = isset($_POST['active']) ? 1 : 0;
                db()->prepare('INSERT INTO site_carousel (page,slot,title,image,body,link,active) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),image=VALUES(image),body=VALUES(body),link=VALUES(link),active=VALUES(active)')
                    ->execute([$cpage, $slot, mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120), mb_substr(trim((string)($_POST['image'] ?? '')), 0, 255), mb_substr(trim((string)($_POST['body'] ?? '')), 0, 400), mb_substr(trim((string)($_POST['link'] ?? '')), 0, 255), $active]);
                $msg = '🎠 Emplacement carrousel #' . $slot . ' (page « ' . h($cpage) . ' ») enregistré.'; break;
            case 'site_setting':
                $allowed = ['site.infobus', 'site.safety_slogan', 'site.games_prog',
                    'site.home_besoin', 'site.home_bienvenue', 'site.home_securite',
                    'site.home_trax', 'site.home_club', 'site.home_homes', 'site.home_activation'];
                $key = (string)($_POST['key'] ?? '');
                if (!in_array($key, $allowed, true)) { $ok = false; $msg = '❌ Réglage inconnu.'; break; }
                $val = mb_substr(trim((string)($_POST['value'] ?? '')), 0, 400);
                db()->prepare('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')->execute([$key, $val]);
                $msg = '✅ Réglage « ' . $key . ' » enregistré.'; break;
            /* --- Apparence : fond du site (couleur + motif/image + mode) --- */
            case 'site_bg_apply': {
                $color = strtoupper(trim((string)($_POST['color'] ?? '')));
                if ($color !== '' && $color[0] !== '#') $color = '#' . $color;
                if (!preg_match('/^#[0-9A-F]{6}$/', $color)) { $ok = false; $msg = '❌ Couleur invalide (format #RRGGBB).'; break; }
                $mode = (string)($_POST['mode'] ?? 'repeat');
                if (!in_array($mode, ['repeat', 'center', 'cover'], true)) $mode = 'repeat';
                $img = trim((string)($_POST['image'] ?? ''));
                $imgVal = '';
                if ($img !== '') {
                    $bn = basename($img);
                    $bgdir = dirname(__DIR__) . '/web-gallery/v2/images/bg_patterns';
                    if (!preg_match('/^[A-Za-z0-9._-]+\.(gif|png|jpe?g)$/i', $bn) || !is_file($bgdir . '/' . $bn)) { $ok = false; $msg = '❌ Image de fond introuvable.'; break; }
                    $imgVal = '/web-gallery/v2/images/bg_patterns/' . $bn;
                }
                foreach (['site.bg_color' => $color, 'site.bg_mode' => $mode, 'site.bg_image' => $imgVal] as $k => $v)
                    db()->prepare('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')->execute([$k, $v]);
                admin_log('site_bg', "color=$color mode=$mode img=$imgVal");
                $msg = '🎨 Fond du site appliqué.'; break;
            }
            case 'site_bg_upload': {
                $f = $_FILES['file'] ?? null;
                if (!$f || ($f['error'] ?? 1) !== 0) { $ok = false; $msg = '❌ Aucun fichier reçu.'; break; }
                $info = @getimagesize($f['tmp_name']);
                $allowed = [IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
                if (!$info || !isset($allowed[$info[2]])) { $ok = false; $msg = '❌ Format non supporté (GIF, JPG ou PNG).'; break; }
                if (($f['size'] ?? 0) > 3 * 1024 * 1024) { $ok = false; $msg = '❌ Fichier trop lourd (max 3 Mo).'; break; }
                if ($info[0] < 1 || $info[1] < 1 || $info[0] > 2000 || $info[1] > 2000) { $ok = false; $msg = '❌ Dimensions invalides (max 2000×2000 px).'; break; }
                $base = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo((string)$f['name'], PATHINFO_FILENAME));
                $base = $base === '' ? 'fond' : mb_substr($base, 0, 60);
                $bgdir = dirname(__DIR__) . '/web-gallery/v2/images/bg_patterns';
                if (!is_dir($bgdir)) @mkdir($bgdir, 0777, true);
                $name = $base . '.' . $allowed[$info[2]]; $i = 1;
                while (is_file($bgdir . '/' . $name)) { $name = $base . '_' . $i . '.' . $allowed[$info[2]]; $i++; }
                if (!@move_uploaded_file($f['tmp_name'], $bgdir . '/' . $name)) { $ok = false; $msg = '❌ Échec de l\'enregistrement.'; break; }
                admin_log('site_bg_upload', $name);
                $msg = '🖼️ Fond importé : ' . $name . ' — sélectionne-le puis clique Appliquer.'; break;
            }
            case 'site_bg_reset':
                foreach (['site.bg_color' => '#083940', 'site.bg_mode' => 'repeat', 'site.bg_image' => ''] as $k => $v)
                    db()->prepare('INSERT INTO settings (setting,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)')->execute([$k, $v]);
                admin_log('site_bg', 'reset');
                $msg = '↩️ Fond par défaut restauré.'; break;
            case 'sitepage_save': {
                ensure_site_pages_admin();
                $slug = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['slug'] ?? ''));
                if ($slug === '') { $ok = false; $msg = '❌ Page inconnue.'; break; }
                $exists = (int) db()->query("SELECT COUNT(*) FROM site_pages WHERE slug=" . db()->quote($slug))->fetchColumn();
                if (!$exists) { $ok = false; $msg = '❌ Page inexistante.'; break; }
                $mode   = in_array(($_POST['mode'] ?? ''), ['meta','draft','publish','unpublish','republish','publish_draft','discard_draft'], true) ? $_POST['mode'] : 'meta';
                $ptitle = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 160);
                $phist  = isset($_POST['is_historical']) ? 1 : 0;
                $who    = (string)($_SESSION['admin']['username'] ?? '?');
                if ($mode === 'publish_draft') {
                    $cur = db()->prepare('SELECT body_draft FROM site_pages WHERE slug=?'); $cur->execute([$slug]); $bd = (string)$cur->fetchColumn();
                    if (trim($bd) === '') { $ok = false; $msg = '❌ Aucun brouillon à publier.'; break; }
                    db()->prepare('UPDATE site_pages SET body_html=?, status=?, body_draft=NULL, edited_by=? WHERE slug=?')->execute([sanitize_html($bd), 'published', $who, $slug]);
                    $msg = '✅ Brouillon publié. La page publique est à jour.';
                    admin_log('sitepage_save', $slug . ' (publish_draft)'); break;
                }
                if ($mode === 'discard_draft') {
                    db()->prepare('UPDATE site_pages SET body_draft=NULL, edited_by=? WHERE slug=?')->execute([$who, $slug]);
                    $msg = '↩️ Brouillon annulé. La version publiée est conservée.';
                    admin_log('sitepage_save', $slug . ' (discard_draft)'); break;
                }
                if ($mode === 'unpublish') {
                    db()->prepare('UPDATE site_pages SET title=?, is_historical=?, status=?, edited_by=? WHERE slug=?')->execute([$ptitle, $phist, 'draft', $who, $slug]);
                    $msg = '🚫 Page « ' . $slug . ' » dépubliée (non visible sur le site, contenu conservé).';
                } elseif ($mode === 'republish') {
                    db()->prepare('UPDATE site_pages SET title=?, is_historical=?, status=?, edited_by=? WHERE slug=?')->execute([$ptitle, $phist, 'published', $who, $slug]);
                    $msg = '✅ Page « ' . $slug . ' » republiée.';
                } elseif ($mode === 'draft') { // sauvegarde complète du corps (legacy)
                    db()->prepare('UPDATE site_pages SET title=?, is_historical=?, body_draft=?, edited_by=? WHERE slug=?')->execute([$ptitle, $phist, sanitize_html((string)($_POST['body_html'] ?? '')), $who, $slug]);
                    $msg = '📝 Brouillon enregistré.';
                } elseif ($mode === 'publish') { // sauvegarde complète du corps (legacy)
                    db()->prepare('UPDATE site_pages SET title=?, body_html=?, is_historical=?, status=?, body_draft=NULL, edited_by=? WHERE slug=?')->execute([$ptitle, sanitize_html((string)($_POST['body_html'] ?? '')), $phist, 'published', $who, $slug]);
                    $msg = '✅ Page « ' . $slug . ' » publiée.';
                } else { // meta : titre + archive seulement, sans toucher au contenu
                    db()->prepare('UPDATE site_pages SET title=?, is_historical=?, edited_by=? WHERE slug=?')->execute([$ptitle, $phist, $who, $slug]);
                    $msg = '💾 Infos de la page enregistrées.';
                }
                admin_log('sitepage_save', $slug . ' (' . $mode . ')');
                break;
            }
            case 'sitepage_block_save': {
                ensure_site_pages_admin();
                $slug = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['slug'] ?? ''));
                $st = db()->prepare('SELECT body_html, body_draft, status FROM site_pages WHERE slug=?'); $st->execute([$slug]); $row = $st->fetch();
                if (!$row) { $ok = false; $msg = '❌ Page inexistante.'; break; }
                $mode = in_array(($_POST['mode'] ?? ''), ['draft','publish'], true) ? $_POST['mode'] : 'draft';
                $idx  = (int)($_POST['block'] ?? -1);
                $btitle = trim(strip_tags((string)($_POST['block_title'] ?? '')));
                $bbody  = sanitize_html((string)($_POST['block_body'] ?? ''));
                // On part du brouillon s'il existe, sinon de la version publiée.
                $base = ($row['body_draft'] !== null && trim((string)$row['body_draft']) !== '') ? (string)$row['body_draft'] : (string)$row['body_html'];
                $updated = sitepage_block_apply($base, $idx, $btitle, $bbody);
                $updated = sanitize_html($updated);
                $who = (string)($_SESSION['admin']['username'] ?? '?');
                if ($mode === 'publish') {
                    db()->prepare('UPDATE site_pages SET body_html=?, status=?, body_draft=NULL, edited_by=? WHERE slug=?')->execute([$updated, 'published', $who, $slug]);
                    $msg = '✅ Bloc publié. La page publique est à jour.';
                } else {
                    db()->prepare('UPDATE site_pages SET body_draft=?, edited_by=? WHERE slug=?')->execute([$updated, $who, $slug]);
                    $msg = '📝 Bloc enregistré en brouillon. Utilise « Aperçu » pour le voir avant publication.';
                }
                admin_log('sitepage_block_save', $slug . ' #' . $idx . ' (' . $mode . ')');
                break;
            }
            case 'sitepage_arrange': {
                ensure_site_pages_admin();
                $slug = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['slug'] ?? ''));
                $st = db()->prepare('SELECT body_html, body_draft FROM site_pages WHERE slug=?'); $st->execute([$slug]); $row = $st->fetch();
                if (!$row) { $ok = false; $msg = '❌ Page inexistante.'; break; }
                $op = (string)($_POST['op'] ?? '');
                $base = ($row['body_draft'] !== null && trim((string)$row['body_draft']) !== '') ? (string)$row['body_draft'] : (string)$row['body_html'];
                $idx = (int)($_POST['block'] ?? -1);
                if ($op === 'up' || $op === 'down') $upd = sitepage_block_move($base, $idx, $op);
                elseif ($op === 'tozone') $upd = sitepage_block_tozone($base, $idx, (int)($_POST['zone'] ?? -1));
                elseif ($op === 'add') $upd = sitepage_block_add($base, (int)($_POST['zone'] ?? 0), preg_replace('/[^a-z]/', '', (string)($_POST['tpl'] ?? 'illustre')), (int)($_POST['pos'] ?? -1));
                else { $ok = false; $msg = '❌ Opération inconnue.'; break; }
                $upd = sanitize_html($upd);
                db()->prepare('UPDATE site_pages SET body_draft=?, edited_by=? WHERE slug=?')->execute([$upd, (string)($_SESSION['admin']['username'] ?? '?'), $slug]);
                admin_log('sitepage_arrange', $slug . ' ' . $op);
                $msg = '🧩 Disposition mise à jour (brouillon). Utilise « Prévisualiser » puis « Publier ».';
                break;
            }
            case 'site_page_create': {
                ensure_site_pages_admin();
                $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 160);
                if ($title === '') { $ok = false; $msg = '❌ Titre requis.'; break; }
                $tab = preg_replace('/[^a-z]/', '', (string)($_POST['parent_tab'] ?? 'home')); if ($tab === '') $tab = 'home';
                $base = strtolower(preg_replace('/[^a-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title));
                $base = trim(preg_replace('/_+/', '_', $base), '_'); if ($base === '') $base = 'article';
                $base = mb_substr($base, 0, 50);
                $slug = $base; $i = 2;
                while ((int)db()->query('SELECT COUNT(*) FROM site_pages WHERE slug=' . db()->quote($slug))->fetchColumn() > 0) { $slug = $base . '_' . $i; $i++; }
                $body = '<table class="content-2col" width="100%" cellpadding="0" cellspacing="0"><tbody><tr>'
                      . '<td valign="top" style="width: 740px;" class="habboPage-col"><div class="v3box blue"><div class="v3box-top"><h3>' . h($title) . '</h3></div><div class="v3box-content"><div class="v3box-body"><p>Nouveau texte à modifier.</p></div></div><div class="v3box-bottom"><div></div></div></div></td>'
                      . '<td valign="top" style="width: 200px;" class="habboPage-col rightmost"><div class="v3box orange"><div class="v3box-top"><h3>Encadré</h3></div><div class="v3box-content"><div class="v3box-body"><p>…</p></div></div><div class="v3box-bottom"><div></div></div></div></td>'
                      . '</tr></tbody></table>';
                db()->prepare('INSERT INTO site_pages (slug,parent_tab,title,body_html,source_ref,is_historical,status,edited_by) VALUES (?,?,?,?,?,0,?,?)')
                    ->execute([$slug, $tab, $title, $body, 'créé dans l\'admin', 'draft', (string)($_SESSION['admin']['username'] ?? '?')]);
                admin_log('site_page_create', $slug);
                redirect('?p=sitemgr&edit=' . $slug);
            }
            case 'page_pin': {
                ensure_site_pages_admin();
                $slug = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['slug'] ?? ''));
                $val = (int)!!($_POST['val'] ?? 0);
                db()->prepare('UPDATE site_pages SET pinned=? WHERE slug=?')->execute([$val, $slug]);
                $msg = $val ? '📌 Page épinglée.' : '📌 Page retirée des favoris.'; break;
            }
            /* --- Modération des Habbo Homes --- */
            case 'hm_report_status': {
                ensure_home_admin();
                $sid = (int)($_POST['id'] ?? 0);
                $status = in_array(($_POST['status'] ?? ''), ['new', 'progress', 'handled'], true) ? $_POST['status'] : 'new';
                $decision = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 400);
                if ($status === 'handled' && $decision === '') { $ok = false; $msg = '❌ Un motif/décision est obligatoire pour clore le dossier.'; break; }
                $handledAt = ($status === 'handled') ? date('Y-m-d H:i:s') : null;
                db()->prepare('UPDATE home_reports SET status=?, handled_by=?, decision=?, handled_at=? WHERE id=?')->execute([$status, (string)($_SESSION['admin']['username'] ?? '?'), $decision, $handledAt, $sid]);
                admin_log('hm_report_status', '#' . $sid . ' → ' . $status . ($decision ? ' · ' . $decision : ''));
                $msg = '✅ Décision enregistrée.'; break;
            }
            case 'hm_home_hide': {
                ensure_home_admin();
                $uid = (int)($_POST['user_id'] ?? 0); $val = (int)!!($_POST['val'] ?? 0);
                $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
                $until = null; if ($val) { $u2 = trim((string)($_POST['until'] ?? '')); if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $u2)) $until = $u2 . ' 23:59:59'; }
                db()->prepare('INSERT INTO home_pages (user_id,hidden,hidden_until) VALUES (?,?,?) ON DUPLICATE KEY UPDATE hidden=VALUES(hidden), hidden_until=VALUES(hidden_until)')->execute([$uid, $val, $until]);
                admin_log('hm_home_hide', 'user#' . $uid . ' hidden=' . $val . ($until ? ' jusqu\'au ' . substr($until, 0, 10) : '') . ($reason ? ' · ' . $reason : ''));
                $msg = $val ? ('🚫 Home masquée' . ($until ? ' jusqu\'au ' . substr($until, 0, 10) : '') . '.') : '✅ Home rétablie.'; break;
            }
            case 'hm_home_lock': {
                ensure_home_admin();
                $uid = (int)($_POST['user_id'] ?? 0); $val = (int)!!($_POST['val'] ?? 0);
                $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
                $until = null; if ($val) { $u2 = trim((string)($_POST['until'] ?? '')); if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $u2)) $until = $u2 . ' 23:59:59'; }
                db()->prepare('INSERT INTO home_pages (user_id,edit_locked,locked_until) VALUES (?,?,?) ON DUPLICATE KEY UPDATE edit_locked=VALUES(edit_locked), locked_until=VALUES(locked_until)')->execute([$uid, $val, $until]);
                admin_log('hm_home_lock', 'user#' . $uid . ' locked=' . $val . ($until ? ' jusqu\'au ' . substr($until, 0, 10) : '') . ($reason ? ' · ' . $reason : ''));
                $msg = $val ? ('🔒 Édition bloquée' . ($until ? ' jusqu\'au ' . substr($until, 0, 10) : '') . '.') : '🔓 Édition débloquée.'; break;
            }
            case 'hm_gb_hide': {
                ensure_home_admin();
                $mid = (int)($_POST['mid'] ?? 0); $val = (int)!!($_POST['val'] ?? 0);
                $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
                db()->prepare('UPDATE home_guestbook SET hidden=? WHERE id=?')->execute([$val, $mid]);
                admin_log('hm_gb_hide', 'msg#' . $mid . ' hidden=' . $val . ($reason ? ' · ' . $reason : ''));
                $msg = $val ? '🚫 Message masqué.' : '✅ Message rétabli.'; break;
            }
            case 'hm_item_del': {
                ensure_home_admin();
                $iid = (int)($_POST['item_id'] ?? 0);
                $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
                // H02 : récupérer le propriétaire AVANT suppression, pour périmer son onglet d'édition
                // (sinon un enregistrement depuis un onglet ancien réintroduirait l'élément retiré).
                $ownerUid = 0; try { $qo = db()->prepare('SELECT user_id FROM home_items WHERE id=?'); $qo->execute([$iid]); $ownerUid = (int)$qo->fetchColumn(); } catch (Throwable $e) {}
                db()->prepare('DELETE FROM home_items WHERE id=?')->execute([$iid]);
                if ($ownerUid > 0) { try { db()->prepare('UPDATE home_pages SET rev=rev+1 WHERE user_id=?')->execute([$ownerUid]); } catch (Throwable $e) {} }
                admin_log('hm_item_del', 'item#' . $iid . ($reason ? ' · ' . $reason : ''));
                $msg = '🗑️ Élément retiré de la Home.'; break;
            }
            case 'hm_asset_status': {
                // H01 : 3 états. available = supprime la ligne ; shop_hidden/banned = upsert avec motif/trace.
                ensure_home_admin();
                $kind = in_array($_POST['kind'] ?? '', ['bg', 'sticker', 'skin'], true) ? (string)$_POST['kind'] : 'sticker';
                $res = basename((string)($_POST['resource'] ?? ''));
                $status = (string)($_POST['status'] ?? '');
                if ($res === '') { $ok = false; $msg = '❌ Ressource vide.'; break; }
                if (!in_array($status, ['available', 'shop_hidden', 'banned'], true)) { $ok = false; $msg = '❌ Statut invalide.'; break; }
                $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
                if ($status === 'available') {
                    db()->prepare('DELETE FROM home_assets_disabled WHERE kind=? AND resource=?')->execute([$kind, $res]);
                    $msg = '✅ Ressource disponible (proposée à l\'ajout et affichée).';
                } else {
                    $who = (string)($_SESSION['admin']['username'] ?? '');
                    db()->prepare('INSERT INTO home_assets_disabled (kind,resource,status,reason,updated_by,updated_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status), reason=VALUES(reason), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)')
                        ->execute([$kind, $res, $status, $status === 'banned' ? $reason : '', $who]);
                    $msg = $status === 'banned'
                        ? '⛔ Ressource bannie (retirée de l\'affichage partout).'
                        : '🏪 Ressource retirée de la boutique (toujours affichée chez ses détenteurs).';
                }
                admin_log('hm_asset_status', $kind . '/' . $res . ' → ' . $status . ($status === 'banned' && $reason !== '' ? ' · ' . $reason : ''));
                break;
            }
            case 'hm_asset_bulk': {
                // H01 : applique un statut à TOUTES les ressources d'un filtre (type + recherche + statut courant).
                ensure_home_admin();
                $kind = in_array($_POST['kind'] ?? '', ['bg', 'sticker', 'skin'], true) ? (string)$_POST['kind'] : 'sticker';
                $target = (string)($_POST['target'] ?? '');
                if (!in_array($target, ['available', 'shop_hidden', 'banned'], true)) { $ok = false; $msg = '❌ Cible invalide.'; break; }
                $qb = trim((string)($_POST['q'] ?? ''));
                $fst = in_array($_POST['fst'] ?? '', ['available', 'shop_hidden', 'banned'], true) ? (string)$_POST['fst'] : 'all';
                $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
                $map = hm_asset_statusmap($kind);
                $sel = array_values(array_filter(hm_asset_files($kind), function ($f) use ($qb, $fst, $map) {
                    if ($qb !== '' && stripos($f, $qb) === false) return false;
                    $st = isset($map[$f]) ? $map[$f]['status'] : 'available';
                    return $fst === 'all' ? true : $st === $fst;
                }));
                $who = (string)($_SESSION['admin']['username'] ?? ''); $n = 0;
                try {
                    db()->beginTransaction();
                    if ($target === 'available') {
                        $del = db()->prepare('DELETE FROM home_assets_disabled WHERE kind=? AND resource=?');
                        foreach ($sel as $f) { $del->execute([$kind, $f]); $n++; }
                    } else {
                        $up = db()->prepare('INSERT INTO home_assets_disabled (kind,resource,status,reason,updated_by,updated_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status), reason=VALUES(reason), updated_by=VALUES(updated_by), updated_at=VALUES(updated_at)');
                        foreach ($sel as $f) { $up->execute([$kind, $f, $target, $target === 'banned' ? $reason : '', $who]); $n++; }
                    }
                    db()->commit();
                } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); $ok = false; $msg = '❌ Échec de l\'action groupée.'; break; }
                admin_log('hm_asset_bulk', $kind . ' ×' . $n . ' → ' . $target . ($qb !== '' ? ' (q=' . $qb . ')' : '') . ($fst !== 'all' ? ' (st=' . $fst . ')' : ''));
                $lbl = $target === 'available' ? 'Disponible' : ($target === 'shop_hidden' ? 'Hors boutique' : 'Bannie');
                $msg = '✅ ' . $n . ' ressource(s) → ' . $lbl . '.';
                break;
            }
            case 'site_news_save': {
                ensure_site_news_admin();
                $id = (int)($_POST['id'] ?? 0);
                $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150);
                if ($title === '') { $ok = false; $msg = '❌ Titre requis.'; break; }
                $summary = mb_substr(trim((string)($_POST['summary'] ?? '')), 0, 255);
                $body = sanitize_html((string)($_POST['body'] ?? ''));
                $image = mb_substr(trim((string)($_POST['image'] ?? '')), 0, 255);
                $cat = mb_substr(trim((string)($_POST['category'] ?? 'À la une')), 0, 40);
                $status = in_array(($_POST['status'] ?? 'published'), ['published','draft'], true) ? $_POST['status'] : 'published';
                $date = trim((string)($_POST['created_at'] ?? ''));
                $dt = preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2})?/', $date) ? str_replace('T', ' ', $date) : date('Y-m-d H:i:s');
                if (strlen($dt) === 10) $dt .= ' 12:00:00';
                $who = (string)($_SESSION['admin']['username'] ?? '?');
                if ($id > 0) {
                    db()->prepare('UPDATE site_news SET title=?, summary=?, body=?, image=?, category=?, status=?, created_at=? WHERE id=?')
                        ->execute([$title, $summary, $body, $image, $cat, $status, $dt, $id]);
                    $msg = '📰 Actualité mise à jour.';
                } else {
                    db()->prepare('INSERT INTO site_news (title,summary,body,image,category,color,author,status,created_at) VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$title, $summary, $body, $image, $cat, 'blue', $who, $status, $dt]);
                    $id = (int)db()->lastInsertId();
                    $msg = $status === 'published' ? '✅ Actualité publiée. Elle apparaît dans « Quoi de neuf ? ».' : '📝 Actualité enregistrée en brouillon.';
                }
                admin_log('site_news_save', '#' . $id . ' ' . $title);
                break;
            }
            case 'site_news_delete': {
                ensure_site_news_admin();
                db()->prepare('DELETE FROM site_news WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
                admin_log('site_news_delete', '#' . (int)($_POST['id'] ?? 0));
                $msg = '🗑️ Actualité supprimée.'; break;
            }
            /* --- Pages et navigation (arborescence éditable) --- */
            case 'nav_tab_save': {
                ensure_site_nav_admin();
                $k = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['tab_key'] ?? ''));
                if ($k === '') { $ok = false; $msg = '❌ Onglet inconnu.'; break; }
                db()->prepare('UPDATE site_nav_tabs SET label=?, icon=?, section_label=?, visible=? WHERE tab_key=?')
                    ->execute([mb_substr(trim((string)($_POST['label'] ?? '')), 0, 40), mb_substr(trim((string)($_POST['icon'] ?? '')), 0, 60), mb_substr(trim((string)($_POST['section_label'] ?? '')), 0, 60), isset($_POST['visible']) ? 1 : 0, $k]);
                admin_log('nav_tab_save', $k); $msg = '🧭 Onglet « ' . $k . ' » enregistré.'; break;
            }
            case 'nav_item_save': {
                ensure_site_nav_admin();
                $id = (int)($_POST['id'] ?? 0);
                $tk = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['tab_key'] ?? ''));
                $lbl = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 80);
                $rt = mb_substr(trim((string)($_POST['route'] ?? '')), 0, 255);
                $tg = in_array(($_POST['target'] ?? ''), ['', '_blank'], true) ? (string)$_POST['target'] : '';
                if ($tk === '' || $lbl === '') { $ok = false; $msg = '❌ Onglet parent et libellé requis.'; break; }
                if ($id > 0) { db()->prepare('UPDATE site_nav_items SET tab_key=?, label=?, route=?, target=? WHERE id=?')->execute([$tk, $lbl, $rt, $tg, $id]); $msg = '🔗 Entrée mise à jour.'; }
                else { $mx = (int) db()->query('SELECT COALESCE(MAX(ord),0)+10 FROM site_nav_items WHERE tab_key=' . db()->quote($tk))->fetchColumn(); db()->prepare('INSERT INTO site_nav_items (tab_key,label,route,target,ord,visible,archived) VALUES (?,?,?,?,?,1,0)')->execute([$tk, $lbl, $rt, $tg, $mx]); $msg = '➕ Entrée ajoutée.'; }
                admin_log('nav_item_save', $tk . '/' . $lbl); break;
            }
            case 'nav_move': {
                ensure_site_nav_admin();
                $kind = ($_POST['kind'] ?? '') === 'tab' ? 'tab' : 'item';
                $dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
                if ($kind === 'tab') {
                    $k = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['key'] ?? ''));
                    $cur = db()->query('SELECT ord FROM site_nav_tabs WHERE tab_key=' . db()->quote($k))->fetchColumn();
                    if ($cur === false) { $ok = false; $msg = '❌ Onglet inconnu.'; break; }
                    $op = $dir === 'up' ? '<' : '>'; $ordr = $dir === 'up' ? 'DESC' : 'ASC';
                    $nb = db()->query("SELECT tab_key,ord FROM site_nav_tabs WHERE ord $op $cur ORDER BY ord $ordr LIMIT 1")->fetch();
                    if ($nb) { db()->prepare('UPDATE site_nav_tabs SET ord=? WHERE tab_key=?')->execute([$nb['ord'], $k]); db()->prepare('UPDATE site_nav_tabs SET ord=? WHERE tab_key=?')->execute([$cur, $nb['tab_key']]); }
                } else {
                    $id = (int)($_POST['id'] ?? 0);
                    $row = db()->query('SELECT tab_key,ord FROM site_nav_items WHERE id=' . $id)->fetch();
                    if (!$row) { $ok = false; $msg = '❌ Entrée inconnue.'; break; }
                    $op = $dir === 'up' ? '<' : '>'; $ordr = $dir === 'up' ? 'DESC' : 'ASC';
                    $nb = db()->query("SELECT id,ord FROM site_nav_items WHERE tab_key=" . db()->quote($row['tab_key']) . " AND ord $op " . (int)$row['ord'] . " ORDER BY ord $ordr LIMIT 1")->fetch();
                    if ($nb) { db()->prepare('UPDATE site_nav_items SET ord=? WHERE id=?')->execute([$nb['ord'], $id]); db()->prepare('UPDATE site_nav_items SET ord=? WHERE id=?')->execute([$row['ord'], $nb['id']]); }
                }
                admin_log('nav_move', $kind . ' ' . $dir); $msg = '↕️ Ordre modifié.'; break;
            }
            case 'nav_flag': {
                ensure_site_nav_admin();
                $kind = ($_POST['kind'] ?? '') === 'tab' ? 'tab' : 'item';
                $field = ($_POST['field'] ?? '') === 'archived' ? 'archived' : 'visible';
                $val = (int)!!($_POST['val'] ?? 0);
                if ($kind === 'tab') { $k = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['key'] ?? '')); db()->prepare("UPDATE site_nav_tabs SET $field=? WHERE tab_key=?")->execute([$val, $k]); }
                else { db()->prepare("UPDATE site_nav_items SET $field=? WHERE id=?")->execute([$val, (int)($_POST['id'] ?? 0)]); }
                admin_log('nav_flag', "$kind $field=$val"); $msg = '✅ Mis à jour.'; break;
            }
            case 'media_upload': {
                $f = $_FILES['file'] ?? null;
                if (!$f || ($f['error'] ?? 1) !== 0) { $ok = false; $msg = '❌ Aucun fichier reçu.'; break; }
                $info = @getimagesize($f['tmp_name']);
                $allowed = [IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
                if (!$info || !isset($allowed[$info[2]])) { $ok = false; $msg = '❌ Format non supporté (GIF/JPG/PNG/WEBP seulement).'; break; }
                if (($f['size'] ?? 0) > 5 * 1024 * 1024) { $ok = false; $msg = '❌ Fichier trop lourd (max 5 Mo).'; break; }
                $base = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo((string)$f['name'], PATHINFO_FILENAME));
                $base = $base === '' ? 'img' : mb_substr($base, 0, 60);
                $dir = dirname(__DIR__) . '/web-gallery/v2/pages_img';
                if (!is_dir($dir)) @mkdir($dir, 0777, true);
                $name = $base . '.' . $allowed[$info[2]]; $i = 1;
                while (is_file($dir . '/' . $name)) { $name = $base . '_' . $i . '.' . $allowed[$info[2]]; $i++; }
                if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) { $ok = false; $msg = '❌ Échec de l\'enregistrement.'; break; }
                admin_log('media_upload', $name);
                $msg = '🖼️ Image importée : /web-gallery/v2/pages_img/' . $name; break;
            }
            case 'media_delete': {
                $name = basename((string)($_POST['file'] ?? ''));
                $dir = dirname(__DIR__) . '/web-gallery/v2/pages_img';
                $path = $dir . '/' . $name;
                if ($name === '' || !is_file($path)) { $ok = false; $msg = '❌ Fichier introuvable.'; break; }
                // Garde-fou serveur : refuse si l'image est utilisée quelque part (ne jamais casser un contenu).
                $uses = media_usage($name);
                if ($uses) { $ok = false; $msg = '🔒 Suppression bloquée : image utilisée (' . count($uses) . '). Retire-la d\'abord des contenus.'; break; }
                if (@unlink($path)) { admin_log('media_delete', $name); $msg = '🗑️ Image supprimée : ' . $name; }
                else { $ok = false; $msg = '❌ Échec de la suppression.'; }
                break;
            }
            /* --- Codes promo (vouchers) --- */
            case 'voucher_add':
                $code = strtoupper(trim($_POST['code'] ?? '')); if ($code === '') { $ok = false; $msg = '❌ Code vide.'; break; }
                $exp = trim($_POST['expiry'] ?? ''); $expv = $exp !== '' ? $exp : null;
                db()->prepare('INSERT INTO vouchers (voucher_code,credits,expiry_date,is_single_use) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE credits=VALUES(credits),expiry_date=VALUES(expiry_date),is_single_use=VALUES(is_single_use)')
                    ->execute([$code, (int)$_POST['credits'], $expv, isset($_POST['single']) ? 1 : 0]);
                db()->prepare('DELETE FROM vouchers_items WHERE voucher_code=?')->execute([$code]);
                foreach (preg_split('/[\s,]+/', trim($_POST['items'] ?? '')) as $sc) { $sc = trim($sc); if ($sc !== '') db()->prepare('INSERT INTO vouchers_items (voucher_code,catalogue_sale_code) VALUES (?,?)')->execute([$code, $sc]); }
                $msg = '🎁 Code promo enregistré.'; break;
            case 'voucher_delete':
                db()->prepare('DELETE FROM vouchers WHERE voucher_code=?')->execute([(string)$_POST['code']]);
                db()->prepare('DELETE FROM vouchers_items WHERE voucher_code=?')->execute([(string)$_POST['code']]);
                $msg = '🗑️ Code supprimé.'; break;
            /* --- Packs catalogue --- */
            case 'pkg_add':
                db()->prepare('INSERT INTO catalogue_packages (salecode,definition_id,special_sprite_id,amount) VALUES (?,?,?,?)')->execute([trim($_POST['salecode'] ?? ''), (int)$_POST['definition_id'], (int)$_POST['special_sprite_id'], max(1, (int)$_POST['amount'])]); $msg = '📦 Pack ajouté.'; break;
            case 'pkg_update':
                db()->prepare('UPDATE catalogue_packages SET salecode=?,definition_id=?,special_sprite_id=?,amount=? WHERE id=?')->execute([trim($_POST['salecode'] ?? ''), (int)$_POST['definition_id'], (int)$_POST['special_sprite_id'], max(1, (int)$_POST['amount']), (int)$_POST['id']]); $msg = '✅ Pack mis à jour.'; break;
            case 'pkg_delete':
                db()->prepare('DELETE FROM catalogue_packages WHERE id=?')->execute([(int)$_POST['id']]); $msg = '🗑️ Pack supprimé.'; break;
            /* --- Recycleur --- */
            case 'recy_add':
                db()->prepare('INSERT INTO recycler_rewards (id,sale_code,item_cost,recycling_session_time_seconds,collection_time_seconds) VALUES (?,?,?,?,?)')->execute([(int)$_POST['id'], trim($_POST['sale_code'] ?? ''), (int)$_POST['item_cost'], (int)$_POST['session_time'], (int)$_POST['collection_time']]); $msg = '♻️ Récompense ajoutée.'; break;
            case 'recy_update':
                db()->prepare('UPDATE recycler_rewards SET sale_code=?,item_cost=?,recycling_session_time_seconds=?,collection_time_seconds=? WHERE id=?')->execute([trim($_POST['sale_code'] ?? ''), (int)$_POST['item_cost'], (int)$_POST['session_time'], (int)$_POST['collection_time'], (int)$_POST['id']]); $msg = '✅ Récompense mise à jour.'; break;
            case 'recy_delete':
                db()->prepare('DELETE FROM recycler_rewards WHERE id=?')->execute([(int)$_POST['id']]); $msg = '🗑️ Récompense supprimée.'; break;
            /* --- Événements --- */
            case 'event_delete':
                db()->prepare('DELETE FROM rooms_events WHERE room_id=?')->execute([(int)$_POST['room_id']]); $msg = '🗑️ Événement terminé.'; break;
            /* --- Débloquer l'inventaire d'un joueur (objets coincés dans la main) --- */
            case 'clear_hand':
                $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([trim($_POST['username'] ?? '')]); $uid = $st->fetchColumn();
                if (!$uid) { $ok = false; $msg = '❌ Joueur introuvable.'; break; }
                $del = db()->prepare("DELETE FROM items WHERE user_id=? AND room_id=0"); $del->execute([(int)$uid]);
                $msg = '🧹 Inventaire (main) vidé : ' . $del->rowCount() . ' objet(s). Le joueur doit se reconnecter.'; break;
            /* --- Trax --- */
            case 'song_update':
                db()->prepare('UPDATE soundmachine_songs SET title=? WHERE id=?')->execute([substr(trim($_POST['title'] ?? ''), 0, 100), (int)$_POST['id']]); $msg = '🎵 Titre mis à jour.'; break;
            case 'song_delete':
                db()->prepare('DELETE FROM soundmachine_songs WHERE id=?')->execute([(int)$_POST['id']]); $msg = '🗑️ Musique supprimée.'; break;
            /* --- Meubles (définitions) --- */
            case 'def_update':
                /* M01 : ancienne voie d'écriture SANS validation / versionnement / historique — FERMÉE.
                   Toute modification de définition passe désormais par 'furni_def_save' (éditeur validé, anti-écrasement concurrent, journal). */
                $ok = false; $msg = '⚠️ Action obsolète : passe par l\'éditeur de la fiche meuble (enregistrement validé + historique).'; break;
            case 'furni_def_save': { /* Lot 2 : éditeur complet, validé, versionné, anti-écrasement concurrent */
                $fid = (int)($_POST['id'] ?? 0);
                $stc = db()->prepare('SELECT * FROM items_definitions WHERE id=?'); $stc->execute([$fid]); $cur = $stc->fetch();
                if (!$cur) { $ok = false; $msg = '❌ Définition introuvable.'; break; }
                if ((string)($_POST['orig_hash'] ?? '') !== furni_def_hash($cur)) { $ok = false; $msg = '⚠️ Cette fiche a été modifiée entre-temps (autre onglet ou autre staff). Rien n\'a été enregistré : rouvre la fiche pour repartir des valeurs à jour.'; break; }
                $name = substr(trim((string)($_POST['name'] ?? '')), 0, 255);
                if ($name === '') { $ok = false; $msg = '❌ Le nom ne peut pas être vide.'; break; }
                $new = [
                    'name' => $name,
                    'description' => substr(trim((string)($_POST['description'] ?? '')), 0, 255),
                    'length' => max(0, (int)($_POST['length'] ?? 0)),
                    'width' => max(0, (int)($_POST['width'] ?? 0)),
                    'top_height' => max(0.0, (float)str_replace(',', '.', (string)($_POST['top_height'] ?? '0'))),
                    'behaviour' => furni_behaviour_rebuild((string)$cur['behaviour'], (array)($_POST['beh'] ?? [])),
                    'interactor' => (trim((string)($_POST['interactor'] ?? '')) === '' ? 'default' : substr(trim((string)$_POST['interactor']), 0, 150)),
                    'colour' => substr(trim((string)($_POST['colour'] ?? '')), 0, 100),
                    'max_status' => substr(trim((string)($_POST['max_status'] ?? '')), 0, 11),
                    'is_tradable' => isset($_POST['is_tradable']) ? 1 : 0,
                    'is_recyclable' => isset($_POST['is_recyclable']) ? 1 : 0,
                ];
                $changes = furni_diff($cur, $new);
                if (!$changes) { $msg = 'ℹ️ Aucun changement détecté.'; break; }
                // M02 : enregistrement ATOMIQUE anti-concurrence. L'UPDATE n'affecte la ligne que si TOUS
                // les champs sont restés ceux chargés par l'éditeur (WHERE id + anciennes valeurs). Si un
                // autre enregistrement est passé entre-temps -> 0 ligne touchée -> conflit, rien d'écrasé.
                // UPDATE + historique dans la MÊME transaction (le hash lu plus haut reste un pré-contrôle UX).
                try {
                    db()->beginTransaction();
                    $stp = db()->prepare('UPDATE items_definitions SET name=?,description=?,length=?,width=?,top_height=?,behaviour=?,interactor=?,colour=?,max_status=?,is_tradable=?,is_recyclable=? WHERE id=? AND name<=>? AND description<=>? AND length<=>? AND width<=>? AND top_height<=>? AND behaviour<=>? AND interactor<=>? AND colour<=>? AND max_status<=>? AND is_tradable<=>? AND is_recyclable<=>?');
                    $stp->execute([
                        $new['name'], $new['description'], $new['length'], $new['width'], $new['top_height'], $new['behaviour'], $new['interactor'], $new['colour'], $new['max_status'], $new['is_tradable'], $new['is_recyclable'],
                        $fid,
                        $cur['name'], $cur['description'], $cur['length'], $cur['width'], $cur['top_height'], $cur['behaviour'], $cur['interactor'], $cur['colour'], $cur['max_status'], $cur['is_tradable'], $cur['is_recyclable'],
                    ]);
                    if ($stp->rowCount() === 0) { db()->rollBack(); $ok = false; $msg = '⚠️ Conflit : la fiche a été modifiée entre-temps (autre onglet ou staff). Rien n\'a été enregistré — rouvre-la pour repartir des valeurs à jour.'; break; }
                    furni_log_change($fid, $changes, (string)($_POST['reason'] ?? ''));
                    db()->commit();
                } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); $ok = false; $msg = '❌ Échec de l\'enregistrement du meuble.'; break; }
                $msg = '💾 Meuble enregistré (' . count($changes) . ' champ' . (count($changes) > 1 ? 's' : '') . ' modifié' . (count($changes) > 1 ? 's' : '') . '). ⚙️ Redémarre l\'émulateur pour voir le changement en jeu.'; break;
            }
            case 'furni_def_restore': { /* Restaure les valeurs d'une entrée d'historique */
                $logId = (int)($_POST['log_id'] ?? 0);
                ensure_furni_log();
                $lg = db()->prepare('SELECT * FROM furni_edit_log WHERE id=?'); $lg->execute([$logId]); $lg = $lg->fetch();
                if (!$lg) { $ok = false; $msg = '❌ Entrée d\'historique introuvable.'; break; }
                $defId = (int)$lg['definition_id'];
                $chg = json_decode((string)$lg['changes'], true) ?: [];
                $stc = db()->prepare('SELECT * FROM items_definitions WHERE id=?'); $stc->execute([$defId]); $cur = $stc->fetch();
                if (!$cur) { $ok = false; $msg = '❌ Définition introuvable.'; break; }
                $allowed = ['name', 'description', 'length', 'width', 'top_height', 'behaviour', 'interactor', 'colour', 'max_status', 'is_tradable', 'is_recyclable'];
                $sets = []; $vals = []; $newchanges = [];
                foreach ($chg as $f => $ab) {
                    if (!in_array($f, $allowed, true) || !is_array($ab) || !array_key_exists('old', $ab)) continue;
                    if ((string)$cur[$f] === (string)$ab['old']) continue; // déjà à cette valeur
                    $sets[] = "$f=?"; $vals[] = $ab['old']; $newchanges[$f] = ['old' => (string)$cur[$f], 'new' => (string)$ab['old']];
                }
                if (!$sets) { $msg = 'ℹ️ Les valeurs sont déjà celles de cette version.'; break; }
                $vals[] = $defId;
                db()->prepare('UPDATE items_definitions SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
                furni_log_change($defId, $newchanges, 'Restauration de la version #' . $logId);
                $msg = '↩️ Valeurs restaurées (' . count($newchanges) . ' champ(s)). ⚙️ Redémarre l\'émulateur pour appliquer en jeu.'; break;
            }
            case 'furni_name_sync': { /* Lot 3 : applique nom/desc aux emplacements choisis */
                $fid = (int)($_POST['id'] ?? 0);
                $stc = db()->prepare('SELECT * FROM items_definitions WHERE id=?'); $stc->execute([$fid]); $def = $stc->fetch();
                if (!$def) { $ok = false; $msg = '❌ Définition introuvable.'; break; }
                $newName = trim((string)($_POST['new_name'] ?? ''));
                $newDesc = trim((string)($_POST['new_desc'] ?? ''));
                $doName = isset($_POST['apply_name']); $doDesc = isset($_POST['apply_desc']);
                if (!$doName && !$doDesc) { $ok = false; $msg = '❌ Coche « appliquer le nom » et/ou « appliquer la description ».'; break; }
                $report = [];
                /* 1) définition interne */
                if (isset($_POST['sc_def'])) {
                    $chg = [];
                    if ($doName && (string)$def['name'] !== $newName) $chg['name'] = ['old' => (string)$def['name'], 'new' => $newName];
                    if ($doDesc && (string)$def['description'] !== $newDesc) $chg['description'] = ['old' => (string)$def['description'], 'new' => $newDesc];
                    if ($chg) {
                        $sets = []; $vals = [];
                        if (isset($chg['name'])) { $sets[] = 'name=?'; $vals[] = $newName; }
                        if (isset($chg['description'])) { $sets[] = 'description=?'; $vals[] = $newDesc; }
                        $vals[] = $fid; db()->prepare('UPDATE items_definitions SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
                        furni_log_change($fid, $chg, 'Synchronisation nom/description');
                        $report[] = 'définition mise à jour';
                    }
                }
                /* 2) offres catalogue sélectionnées (validées comme appartenant à la définition) */
                $offerIds = array_values(array_unique(array_map('intval', (array)($_POST['sc_offer'] ?? []))));
                if ($offerIds) {
                    $in = implode(',', array_fill(0, count($offerIds), '?'));
                    $chk = db()->prepare("SELECT id FROM catalogue_items WHERE definition_id=? AND id IN ($in)");
                    $chk->execute(array_merge([$fid], $offerIds));
                    $valid = array_map('intval', array_column($chk->fetchAll(), 'id'));
                    $cnt = 0;
                    foreach ($valid as $oid) {
                        $sets = []; $vals = [];
                        if ($doName) { $sets[] = 'name=?'; $vals[] = $newName; }
                        if ($doDesc) { $sets[] = 'description=?'; $vals[] = $newDesc; }
                        if ($sets) { $vals[] = $oid; db()->prepare('UPDATE catalogue_items SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals); $cnt++; }
                    }
                    if ($cnt) $report[] = $cnt . ' offre(s) catalogue';
                }
                /* 3) texte client (CP1252), sauvegarde + vérif, statut honnête */
                if (isset($_POST['sc_text'])) {
                    $keys = furni_text_keys((string)$def['sprite']);
                    if ($keys === null) { $report[] = '⚠️ texte client NON modifié (poster/sprite spécial — clé à gérer à la main)'; $ok = false; }
                    else {
                        $path = client_texts_path(); $parts = []; $bak = '';
                        if ($doName) { $r = client_text_set($path, $keys['name'], $newName); if (!$r['ok']) { $parts[] = 'nom ÉCHEC (' . $r['status'] . ')'; $ok = false; } else { $parts[] = 'nom ' . $r['status'] . ($r['verified'] ? ' ✓' : ' ⚠non vérifié') . (($r['dupes'] ?? 0) > 1 ? ' ⚠' . $r['dupes'] . ' doublons' : ''); $bak = $r['backup'] ?? ''; if (!$r['verified']) $ok = false; } }
                        if ($doDesc) { $r = client_text_set($path, $keys['desc'], $newDesc); if (!$r['ok']) { $parts[] = 'desc ÉCHEC (' . $r['status'] . ')'; $ok = false; } else { $parts[] = 'desc ' . $r['status'] . ($r['verified'] ? ' ✓' : ' ⚠non vérifié') . (($r['dupes'] ?? 0) > 1 ? ' ⚠' . $r['dupes'] . ' doublons' : ''); $bak = $r['backup'] ?? ''; if (!$r['verified']) $ok = false; } }
                        $report[] = 'texte client (' . implode(', ', $parts) . ')' . ($bak !== '' ? ' [sauvegarde ' . $bak . ']' : '');
                    }
                }
                if (!$report) { $msg = 'ℹ️ Rien à appliquer (aucune portée cochée, ou valeurs inchangées).'; }
                else $msg = ($ok ? '✅ ' : '⚠️ ') . 'Synchro : ' . implode(' · ', $report) . '. ⚠️ Texte client en jeu = vide le cache Basilisk côté joueur (pas de redémarrage émulateur). Synchro « complète » seulement si tout est ✓.';
                break;
            }
            /* --- Cartes de jeux --- */
            case 'gmap_update':
                db()->prepare('UPDATE games_maps SET heightmap=?,tile_map=? WHERE id=?')->execute([(string)$_POST['heightmap'], (string)$_POST['tile_map'], (int)$_POST['id']]); $msg = '🗺️ Carte mise à jour. Redémarre l\'émulateur.'; break;
            /* --- Modèles de salles --- */
            case 'model_update':
                db()->prepare('UPDATE rooms_models SET model_name=?,door_x=?,door_y=?,door_z=?,door_dir=?,trigger_class=? WHERE id=?')->execute([trim($_POST['model_name'] ?? ''), (int)$_POST['door_x'], (int)$_POST['door_y'], (float)$_POST['door_z'], (int)$_POST['door_dir'], trim($_POST['trigger_class'] ?? 'flat_trigger'), (int)$_POST['id']]); $msg = '🏗️ Modèle mis à jour. Redémarre l\'émulateur.'; break;
            case 'bus_type':
                $tc = in_array($_POST['trigger_class'] ?? '', ['infobus_park', 'infobus_poll'], true) ? $_POST['trigger_class'] : 'infobus_park';
                db()->prepare('UPDATE rooms_models SET trigger_class=? WHERE id=?')->execute([$tc, (int)$_POST['id']]); $msg = '🚌 Type du bus changé (' . ($tc === 'infobus_poll' ? 'Sondage/Vote' : 'Info/FRANK') . '). Redémarre l\'émulateur.'; break;
            case 'srv_start':
                if (emu_running()) { $msg = 'ℹ️ L\'émulateur tourne déjà.'; break; }
                srv_start(); $msg = '▶️ Démarrage de l\'émulateur… patiente ~10 s puis actualise.'; break;
            case 'srv_stop':
                srv_stop(); usleep(800000);
                if (emu_running()) { $ok = false; $msg = '⚠️ L\'arrêt a échoué (l\'émulateur répond encore). Vérifie que le serveur web a le droit d\'arrêter le process.'; }
                else $msg = '⏹️ Émulateur arrêté.';
                break;
            case 'srv_restart':
                srv_stop(); sleep(3); srv_start();
                $msg = '🔄 Redémarrage de l\'émulateur… patiente ~10 s puis actualise. Les changements de décor sont maintenant appliqués.'; break;
            case 'srv_rk_start': // Redémarrage « Kepler uniquement » — arrêt propre (pack.ps1), détaché, suivi JSON.
                $delay = (($_POST['when'] ?? 'now') === 'later') ? 60 : 0;
                if ($delay > 0) { @rcon_send('hotel_alert', ['message' => kepler_text('Redemarrage du jeu dans 1 minute. Reconnecte-toi juste apres.')]); }
                $ok = rk_launch($delay);
                admin_log('srv_rk_start', 'Redémarrage Kepler (' . ($delay > 0 ? 'dans 1 min, annonce envoyée' : 'immédiat') . ')');
                $msg = $ok ? '🔁 Redémarrage de Kepler lancé — suis la progression ci-dessous (l\'opération continue même si tu fermes la page).' : '❌ Impossible de lancer le script (scripts/pack.ps1 introuvable ?).';
                break;
            case 'srv_rk_cancel': // Annule un redémarrage DIFFÉRÉ avant l'arrêt effectif.
                @file_put_contents(rk_status_path() . '.cancel', '1', LOCK_EX);
                admin_log('srv_rk_cancel', 'Annulation du redémarrage Kepler différé');
                $msg = '✋ Annulation demandée (prise en compte si l\'arrêt n\'a pas encore commencé).'; break;
            case 'maint_on':
                $mj = ['on' => true, 'message' => mb_substr(trim((string)($_POST['message'] ?? '')), 0, 300), 'eta' => mb_substr(trim((string)($_POST['eta'] ?? '')), 0, 60), 'by' => (string)($_SESSION['admin']['username'] ?? ''), 'at' => date('c')];
                $mfp = dirname(__DIR__) . '/maintenance.json';
                $wr = @file_put_contents($mfp, json_encode($mj, JSON_UNESCAPED_UNICODE), LOCK_EX);
                if ($wr === false) { $ok = false; $msg = '❌ Échec : impossible d\'écrire maintenance.json (droits d\'écriture du serveur web ?).'; }
                else $msg = '🚧 Mode maintenance ACTIVÉ — le site public ET le jeu affichent la page de fermeture (le staff garde l\'accès).'; break;
            case 'maint_off':
                $mfp = dirname(__DIR__) . '/maintenance.json';
                clearstatcache(true, $mfp);
                if (!is_file($mfp)) { $msg = '✅ Maintenance déjà désactivée.'; }
                elseif (@unlink($mfp)) { $msg = '✅ Mode maintenance désactivé — le site et le jeu sont de nouveau ouverts.'; }
                else { $ok = false; $msg = '❌ Échec : impossible de supprimer maintenance.json (droits ? fichier verrouillé ?).'; }
                break;
            case 'set_entry_bg':
                $cc = preg_replace('/[^a-z_]/', '', strtolower((string)($_POST['country'] ?? '')));
                if ($cc === '' || !is_file(DCR_DIR . '/hh_entry_' . $cc . '.cct')) { $ok = false; $msg = '❌ Fond introuvable.'; break; }
                $evp = DCR_DIR . '/external_variables.txt';
                $ev = @file_get_contents($evp);
                if ($ev === false) { $ok = false; $msg = '❌ external_variables.txt introuvable.'; break; }
                @copy($evp, $evp . '.bak_bg_' . date('YmdHis'));
                $ev = preg_replace('/^cast\.entry\.16=.*/m', 'cast.entry.16=hh_entry_' . $cc, $ev, 1, $c16);
                if ($c16 === 0) { $ev = rtrim($ev, "\r\n") . "\n" . 'cast.entry.16=hh_entry_' . $cc; }
                if (is_file(DCR_DIR . '/hh_patch_' . $cc . '.cct')) {
                    $ev = preg_replace('/^cast\.entry\.2=.*/m', 'cast.entry.2=hh_patch_' . $cc, $ev, 1, $c2);
                    if ($c2 === 0) { $ev = rtrim($ev, "\r\n") . "\n" . 'cast.entry.2=hh_patch_' . $cc; }
                    $ev = preg_replace('/^fuse\.project\.id=.*/m', 'fuse.project.id=habbo_' . $cc, $ev, 1, $cf);
                    if ($cf === 0) { $ev = rtrim($ev, "\r\n") . "\n" . 'fuse.project.id=habbo_' . $cc; }
                }
                file_put_contents($evp, $ev);
                $ok = true; $msg = '🎨 Fond de connexion changé (' . h(strtoupper($cc)) . '). Vide le cache du client Basilisk pour le voir.'; break;
            case 'hotel_alert':
                $txt = trim((string)($_POST['message'] ?? ''));
                if ($txt === '') { $ok = false; $msg = 'Message vide.'; break; }
                // Le client Habbo (Shockwave) affiche en CP1252 : on convertit + retire les emoji.
                $params = ['message' => kepler_text($txt)];
                if (trim((string)($_POST['sender'] ?? '')) !== '') $params['sender'] = kepler_text(trim((string)$_POST['sender']));
                $ok2 = rcon_send('hotel_alert', $params);
                alert_log_add('jeu (RCON)', 'Tout l\'hôtel (connectés)', $txt, $ok2 ? 'RCON accepté — transmis au serveur' : 'échec — émulateur injoignable');
                $ok = $ok2; $msg = $ok2 ? '📢 Alerte transmise au serveur (joueurs connectés).' : '❌ Émulateur injoignable (RCON). Est-il démarré ?'; break;
            case 'db_backup':
                $ok = db_backup(); $msg = $ok ? '💾 Sauvegarde créée et vérifiée.' : ('❌ Échec de la sauvegarde : ' . ($GLOBALS['backup_err'] ?? 'raison inconnue')); break;
            case 'cat_bulk':
                $pageId = (int)($_POST['page_id'] ?? 0); $what = $_POST['what'] ?? '';
                $where = $pageId > 0 ? 'page_id=' . $pageId : '1';
                if ($what === 'hide') db()->exec('UPDATE catalogue_items SET is_hidden=1 WHERE ' . $where);
                elseif ($what === 'show') db()->exec('UPDATE catalogue_items SET is_hidden=0 WHERE ' . $where);
                elseif ($what === 'price') { $v = max(0, (int)($_POST['value'] ?? 0)); db()->prepare('UPDATE catalogue_items SET price=? WHERE ' . $where)->execute([$v]); }
                elseif ($what === 'mult') { $m = (float)($_POST['value'] ?? 1); if ($m > 0) db()->exec('UPDATE catalogue_items SET price=GREATEST(0,ROUND(price*' . $m . ')) WHERE ' . $where); }
                $msg = '✅ Catalogue mis à jour en masse.'; break;
            case 'note_add':
                ensure_admin_notes();
                $note = trim((string)($_POST['note'] ?? ''));
                if ($note !== '') db()->prepare('INSERT INTO admin_notes (user_id,author,note) VALUES (?,?,?)')->execute([(int)$_POST['user_id'], $_SESSION['admin']['username'] ?? '?', mb_substr($note, 0, 500)]);
                $msg = '📝 Note ajoutée.'; break;
            case 'note_del':
                db()->prepare('DELETE FROM admin_notes WHERE id=?')->execute([(int)$_POST['id']]);
                $msg = '🗑️ Note supprimée.'; break;
            case 'badge_all':
                $code = strtoupper(trim((string)($_POST['badge'] ?? '')));
                if ($code === '') { $ok = false; $msg = 'Code badge vide.'; break; }
                db()->prepare('INSERT INTO users_badges (user_id,badge) SELECT id,? FROM users WHERE id NOT IN (SELECT user_id FROM users_badges WHERE badge=?)')->execute([$code, $code]);
                $msg = '📛 Badge « ' . h($code) . ' » donné à tous les joueurs.'; break;
            case 'furni_all':
                $def = (int)($_POST['definition_id'] ?? 0);
                $lastKey = 'furni_all_last_' . $def; // anti double-distribution (double-clic / renvoi de requête)
                if ((int)($_SESSION[$lastKey] ?? 0) > time() - 8) { $ok = false; $msg = '⏳ Ce meuble vient d\'être distribué il y a quelques secondes — distribution ignorée pour éviter un doublon. Réessaie dans un instant si c\'est bien voulu.'; break; }
                $chk = db()->prepare('SELECT id FROM items_definitions WHERE id=?'); $chk->execute([$def]);
                if (!$chk->fetch()) { $ok = false; $msg = 'Définition de meuble introuvable.'; break; }
                db()->prepare("INSERT INTO items (user_id,room_id,definition_id,x,y,z,wall_position,custom_data) SELECT id,0,?,0,0,0,'','' FROM users")->execute([$def]);
                $_SESSION[$lastKey] = time();
                $nbU = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $msg = '🎁 Meuble distribué à ' . $nbU . ' joueur(s) (dans leur main). Ils doivent se reconnecter.'; break;
            case 'furni_give_me': /* donner un exemplaire au compte admin courant (test) */
                $def = (int)($_POST['definition_id'] ?? 0);
                $meId = (int)($_SESSION['admin']['id'] ?? 0);
                if ($meId <= 0) { $ok = false; $msg = '❌ Compte admin introuvable.'; break; }
                $gk = 'furni_me_last_' . $def;
                if ((int)($_SESSION[$gk] ?? 0) > time() - 5) { $ok = false; $msg = '⏳ Déjà ajouté il y a un instant (anti double-clic).'; break; }
                $chk = db()->prepare('SELECT id FROM items_definitions WHERE id=?'); $chk->execute([$def]);
                if (!$chk->fetch()) { $ok = false; $msg = 'Définition de meuble introuvable.'; break; }
                db()->prepare("INSERT INTO items (user_id,room_id,definition_id,x,y,z,wall_position,custom_data) VALUES (?,0,?,0,0,0,'','')")->execute([$meId, $def]);
                $_SESSION[$gk] = time();
                $msg = '🎁 Un exemplaire ajouté à ton compte (dans ta main — reconnecte-toi au jeu pour le voir).'; break;
            case 'wf_add': { /* Filtre de mots : ajouter/mettre à jour */
                ensure_wordfilter();
                $w = mb_strtolower(trim((string)($_POST['word'] ?? '')));
                if ($w === '' || mb_strlen($w) > 64) { $ok = false; $msg = '❌ Mot vide ou trop long.'; break; }
                $r = trim((string)($_POST['replacement'] ?? 'bobba')); if ($r === '') $r = 'bobba';
                db()->prepare('INSERT INTO wordfilter (word,replacement,enabled) VALUES (?,?,1) ON DUPLICATE KEY UPDATE replacement=VALUES(replacement), enabled=1')->execute([$w, mb_substr($r, 0, 64)]);
                $msg = '✅ Mot « ' . h($w) . ' » ajouté. ⚙️ Redémarre l\'émulateur pour l\'appliquer en jeu.'; break;
            }
            case 'wf_del':
                ensure_wordfilter();
                db()->prepare('DELETE FROM wordfilter WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
                $msg = '🗑️ Mot retiré. ⚙️ Redémarre l\'émulateur pour l\'appliquer.'; break;
            case 'wf_toggle':
                ensure_wordfilter();
                db()->prepare('UPDATE wordfilter SET enabled=1-enabled WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
                $msg = '🔁 Activé/désactivé. ⚙️ Redémarre l\'émulateur pour l\'appliquer.'; break;
            case 'decor_season':
                $season = in_array($_POST['season'] ?? '', array_keys(DECOR_LABELS), true) ? (string)$_POST['season'] : '';
                $casts = available_casts(); $n = 0;
                foreach (db()->query("SELECT id,ccts FROM rooms WHERE ccts LIKE 'hh_room_%'") as $r) {
                    $parts = explode(',', (string)$r['ccts']); $base = $parts[0]; $extra = count($parts) > 1 ? ',' . implode(',', array_slice($parts, 1)) : '';
                    $variants = decor_variants($casts, $base);
                    $target = $variants[$season] ?? ($variants[''] ?? $base);
                    db()->prepare('UPDATE rooms SET ccts=? WHERE id=?')->execute([$target . $extra, (int)$r['id']]); $n++;
                }
                $msg = '🎨 Décor « ' . season_label($season) . ' » appliqué à ' . $n . ' salle(s). Redémarre l\'émulateur.'; break;
            case 'cmd_fav_toggle':
                ensure_command_favorites();
                $cmd = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['command'] ?? '')));
                $uid = (int)($_SESSION['admin']['id'] ?? 0);
                if ($cmd === '' || $uid <= 0) { $ok = false; $msg = 'Commande invalide.'; break; }
                $ex = db()->prepare('SELECT 1 FROM admin_command_favorites WHERE user_id=? AND command=?');
                $ex->execute([$uid, $cmd]);
                if ($ex->fetchColumn()) {
                    db()->prepare('DELETE FROM admin_command_favorites WHERE user_id=? AND command=?')->execute([$uid, $cmd]);
                    $msg = 'retiré';
                } else {
                    db()->prepare('INSERT INTO admin_command_favorites (user_id,command) VALUES (?,?)')->execute([$uid, $cmd]);
                    $msg = 'ajouté';
                }
                break;
            default: $ok = false; $msg = 'Action inconnue.';
        }
    } catch (Throwable $e) { $ok = false; $msg = '❌ Erreur : ' . $e->getMessage(); }
    // pwreset_do est exclu : son $msg contient le mot de passe provisoire EN CLAIR
    // -> il est journalisé séparément, sans le secret (voir case 'pwreset_do').
    if ($ok && !in_array($a, ['note_add', 'note_del', 'cmd_fav_toggle', 'pwreset_do'], true)) admin_log($a, strip_tags($msg));
    if ($ok) user_history_maybe($a); // historique par joueur (sans secret)
    if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'msg' => $msg]); exit; }
    flash($msg); redirect($back);
}

/* ---------- Éditeur d'actualités unique : ?p=news -> Gestion du Site ----------
   Redirection AVANT tout affichage (sinon « headers already sent »). */
if ($p === 'news') {
    $to = '?p=sitemgr&sec=news';
    if (isset($_GET['newsedit'])) $to .= '&newsedit=' . (int)$_GET['newsedit'];
    elseif (isset($_GET['edit'])) $to .= '&newsedit=' . (int)$_GET['edit'];
    elseif (isset($_GET['new'])) $to .= '&new=1';
    redirect($to);
}

/* ---------- Contrôle d'accès aux onglets par rang ---------- */
if (!tab_allowed($p)) { flash('🔒 Ton rang n\'a pas accès à « ' . $p . ' ».'); $p = 'dashboard'; }

/* ---------- Journal en direct : endpoint AJAX (polling) ---------- */
if ($p === 'journal' && isset($_GET['tail'])) { journal_ajax(); exit; }

/* ---------- Rendu ---------- */
render_header($p);
switch ($p) {
    case 'news': page_news(); break;
    case 'models': page_models(); break;
    case 'packages': page_packages(); break;
    case 'furni': page_furni(); break;
    case 'trax': page_trax(); break;
    case 'gamemaps': page_gamemaps(); break;
    case 'events': page_events(); break;
    case 'recycler': page_recycler(); break;
    case 'vouchers': page_vouchers(); break;
    case 'rooms': page_rooms(); break;
    case 'catalogue': page_catalogue(); break;
    case 'navcats': page_navcats(); break;
    case 'convert': page_convert(); break;
    case 'bots': page_bots(); break;
    case 'users': page_users(); break;
    case 'messages': page_messages(); break;
    case 'alerts': page_alerts(); break;
    case 'sitemgr': page_sitemgr(); break;
    case 'homemod': page_homemod(); break;
    case 'user': page_user(); break;
    case 'badges': page_badges(); break;
    case 'ranks': page_ranks(); break;
    case 'games': page_games(); break;
    case 'moderation': page_moderation(); break;
    case 'bus': page_bus(); break;
    case 'commandes': page_commandes(); break;
    case 'settings': page_settings(); break;
    case 'access': page_access(); break;
    case 'audit': page_audit(); break;
    case 'search': page_search(); break;
    case 'textes': page_textes(); break;
    case 'mysql': page_mysql(); break;
    case 'server': page_server(); break;
    case 'journal': page_journal(); break;
    case 'wordfilter': page_wordfilter(); break;
    default: page_dashboard(); break;
}
render_footer();

/* ============ ACCÈS AUX ONGLETS PAR RANG ============ */
function admin_nav(): array {
    return ['dashboard' => ['🏠', 'Accueil'], 'search' => ['🔍', 'Recherche'], 'news' => ['📰', 'Actualités'], 'sitemgr' => ['🌐', 'Gestion du Site'], 'rooms' => ['🏛️', 'Salles & décors'], 'models' => ['🏗️', 'Modèles de salles'], 'navcats' => ['🧭', 'Catégories navigateur'], 'catalogue' => ['🛋️', 'Catalogue'], 'packages' => ['📦', 'Packs catalogue'], 'furni' => ['🪑', 'Meubles (défs)'], 'convert' => ['🔧', 'Convertir furni'], 'bots' => ['🤖', 'Bots'], 'trax' => ['🎵', 'Trax'], 'users' => ['👥', 'Joueurs'], 'messages' => ['💬', 'Messagerie'], 'alerts' => ['📢', 'Alertes & annonces'], 'badges' => ['📛', 'Badges'], 'ranks' => ['🎖️', 'Rangs'], 'games' => ['🎮', 'Jeux'], 'gamemaps' => ['🗺️', 'Cartes de jeux'], 'events' => ['🎉', 'Événements'], 'recycler' => ['♻️', 'Recycleur'], 'vouchers' => ['🎁', 'Codes promo'], 'moderation' => ['🚫', 'Modération'], 'homemod' => ['🏠', 'Modération des Homes'], 'bus' => ['🚌', 'Bus (Infobus)'], 'commandes' => ['⌨️', 'Commandes en jeu'], 'textes' => ['💬', 'Textes du jeu'], 'settings' => ['⚙️', 'Réglages'], 'access' => ['🔒', 'Accès admin'], 'audit' => ['📜', 'Journal admin'], 'mysql' => ['🗄️', 'Base MySQL'], 'server' => ['🖥️', 'Serveur'], 'journal' => ['📡', 'Journal en direct'], 'wordfilter' => ['🤬', 'Filtre de mots']];
}
function tab_default_rank(string $tab): int {
    $d = ['mysql' => 7, 'server' => 7, 'settings' => 7, 'ranks' => 7, 'textes' => 7, 'access' => 7, 'audit' => 7, 'convert' => 7, 'homemod' => 6, 'journal' => 7];
    return $d[$tab] ?? MIN_RANK;
}
function nav_groups(): array {
    return [
        '' => ['dashboard', 'search'],
        'Site & contenus' => ['sitemgr', 'news'],
        'Joueurs' => ['users', 'badges', 'ranks'],
        'Alertes & annonces' => ['alerts'],
        'Modération & assistance' => ['messages', 'moderation', 'homemod', 'commandes', 'wordfilter', 'audit'],
        'Hôtel & appartements' => ['rooms', 'models', 'gamemaps', 'bots', 'games'],
        'Catalogue & économie' => ['catalogue', 'navcats', 'packages', 'furni', 'convert', 'trax', 'recycler', 'vouchers'],
        'Animations' => ['events', 'bus'],
        'Technique' => ['settings', 'textes', 'access', 'mysql', 'server', 'journal'],
    ];
}
function ensure_tab_perms(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS admin_tab_perms (tab VARCHAR(40) NOT NULL PRIMARY KEY, min_rank TINYINT NOT NULL DEFAULT 5) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function tab_perms(): array {
    static $m = null;
    if ($m === null) { ensure_tab_perms(); $m = []; foreach (db()->query('SELECT tab,min_rank FROM admin_tab_perms') as $r) $m[$r['tab']] = (int)$r['min_rank']; }
    return $m;
}
function tab_min(string $tab): int { $m = tab_perms(); return $m[$tab] ?? tab_default_rank($tab); }
function tab_allowed(string $tab): bool { return (int)($_SESSION['admin']['rank'] ?? 0) >= tab_min($tab); }
/* ---- Gestion du Site (v2) : carrousel + réglages accueil ---- */
function ensure_carousel_admin(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_carousel (page VARCHAR(64) NOT NULL DEFAULT 'home', slot TINYINT NOT NULL, title VARCHAR(120) NOT NULL DEFAULT '', image VARCHAR(255) NOT NULL DEFAULT '', body VARCHAR(400) NOT NULL DEFAULT '', link VARCHAR(255) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1, PRIMARY KEY (page,slot)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function site_setting_get(string $k, string $def = ''): string {
    try { $st = db()->prepare('SELECT value FROM settings WHERE setting=?'); $st->execute([$k]); $v = $st->fetchColumn(); return $v === false ? $def : (string)$v; } catch (Throwable $e) { return $def; }
}
function col_exists(string $table, string $col): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $st->execute([$table, $col]); return (int)$st->fetchColumn() > 0;
}
function ensure_site_pages_admin(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_pages (slug VARCHAR(60) NOT NULL PRIMARY KEY, parent_tab VARCHAR(20) NOT NULL DEFAULT 'home', title VARCHAR(160) NOT NULL DEFAULT '', body_html MEDIUMTEXT NULL, source_ref VARCHAR(255) NOT NULL DEFAULT '', missing_note TEXT NULL, is_historical TINYINT NOT NULL DEFAULT 0, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!col_exists('site_pages', 'status'))     db()->exec("ALTER TABLE site_pages ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'published'");
    if (!col_exists('site_pages', 'body_draft')) db()->exec("ALTER TABLE site_pages ADD COLUMN body_draft MEDIUMTEXT NULL");
    if (!col_exists('site_pages', 'edited_by'))  db()->exec("ALTER TABLE site_pages ADD COLUMN edited_by VARCHAR(64) NOT NULL DEFAULT ''");
    if (!col_exists('site_pages', 'pinned'))     db()->exec("ALTER TABLE site_pages ADD COLUMN pinned TINYINT NOT NULL DEFAULT 0");
}
function ensure_site_news_admin(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_news (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, category VARCHAR(40) NOT NULL DEFAULT 'À la une', color VARCHAR(10) NOT NULL DEFAULT 'blue', body TEXT NOT NULL, author VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!col_exists('site_news', 'summary')) db()->exec("ALTER TABLE site_news ADD COLUMN summary VARCHAR(255) NOT NULL DEFAULT ''");
    if (!col_exists('site_news', 'image'))   db()->exec("ALTER TABLE site_news ADD COLUMN image VARCHAR(255) NOT NULL DEFAULT ''");
    if (!col_exists('site_news', 'status'))  db()->exec("ALTER TABLE site_news ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'published'");
    if (!col_exists('site_news', 'pub_at'))  db()->exec("ALTER TABLE site_news ADD COLUMN pub_at DATETIME NULL");
}

/* Nettoyeur HTML : garde la mise en page 2007 (.v3box, tables) mais retire tout script/handler dangereux. */
function sanitize_html(string $html): string {
    if (trim($html) === '') return '';
    $allowedTags = ['p','br','h2','h3','h4','h5','strong','b','em','i','u','s','ul','ol','li','a','img','table','thead','tbody','tr','td','th','div','span','hr','blockquote'];
    $allowedAttr = [
        'a'   => ['href','target','rel','title','class'],
        'img' => ['src','alt','title','width','height','align','vspace','hspace','border','class','style'],
        'td'  => ['colspan','rowspan','valign','align','width','height','class','style'],
        'th'  => ['colspan','rowspan','valign','align','width','height','class','style'],
        'tr'  => ['valign','align','class','style'],
        'table'=>['width','height','border','cellpadding','cellspacing','align','class','style'],
        '*'   => ['class','style'],
    ];
    // Balises SUPPRIMÉES avec tout leur contenu (exécutables / plugins / formulaires) — jamais déballées.
    $dropTags = ['script','style','svg','math','object','embed','applet','iframe','frame','frameset',
                 'form','input','button','textarea','select','option','optgroup','noscript','template',
                 'link','meta','base','title','head','xml','audio','video','source','track','canvas','map','area'];
    // URL sûre : on décode les entités et on retire espaces/contrôles (contre « java\tscript: », encodages) avant de tester le schéma.
    $urlSafe = function (string $v): bool {
        $d = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $d = preg_replace('/[\x00-\x20\x7f]+/', '', (string)$d); // retire tab/newline/espaces/contrôles
        if ($d === '') return true; // href vide / ancre vide = inoffensif
        return !preg_match('#^(javascript|vbscript|livescript|mocha|data|about|file|blob):#i', $d);
    };
    // style sûr : d'abord on refuse les obfuscations rares en style inline 2007 (échappes CSS « \ »,
    // commentaires « /* */ », chevrons) qui servent à casser les mots-clés ; puis on bloque
    // expression(), schémas actifs, behavior, -moz-binding, @import et url(schéma dangereux).
    $styleSafe = function (string $v): bool {
        $d = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strpos($d, '\\') !== false || strpos($d, '<') !== false || strpos($d, '>') !== false) return false; // échappes CSS / chevrons
        if (strpos($d, '/*') !== false || strpos($d, '*/') !== false) return false;                              // commentaires CSS
        $d = strtolower(preg_replace('/\s+/', '', $d));
        return !preg_match('/expression\(|javascript:|vbscript:|livescript:|mocha:|-moz-binding|behavior:|@import|url\(["\']?(?:javascript|vbscript|livescript|mocha|data|about|file|blob):/', (string)$d);
    };
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root__">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__root__');
    if (!$root) return '';
    $walk = function (DOMNode $node) use (&$walk, $allowedTags, $allowedAttr, $dropTags, $urlSafe, $styleSafe) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                /** @var DOMElement $child */
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowedTags, true)) {
                    if (in_array($tag, $dropTags, true)) { // balise dangereuse : on supprime balise ET contenu
                        $child->parentNode->removeChild($child);
                        continue;
                    }
                    // balise inconnue (mise en forme) : NETTOYER le sous-arbre AVANT de déballer,
                    // sinon les enfants promus échappent au parcours (snapshot) — c'était le trou.
                    $walk($child);
                    while ($child->firstChild) $child->parentNode->insertBefore($child->firstChild, $child);
                    $child->parentNode->removeChild($child);
                    continue;
                }
                $allow = array_merge($allowedAttr['*'] ?? [], $allowedAttr[$tag] ?? []);
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $an = strtolower($attr->name); $av = (string)$attr->value;
                    $bad = !in_array($an, $allow, true) || str_starts_with($an, 'on');
                    if (($an === 'href' || $an === 'src' || $an === 'xlink:href') && !$urlSafe($av)) $bad = true;
                    if ($an === 'style' && !$styleSafe($av)) $bad = true;
                    if ($bad) $child->removeAttribute($attr->name);
                }
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
                $walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $child->parentNode->removeChild($child);
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}
function ensure_site_nav_admin(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_nav_tabs (tab_key VARCHAR(20) NOT NULL PRIMARY KEY, label VARCHAR(40) NOT NULL DEFAULT '', icon VARCHAR(60) NOT NULL DEFAULT '', section_label VARCHAR(60) NOT NULL DEFAULT '', ord SMALLINT NOT NULL DEFAULT 0, visible TINYINT NOT NULL DEFAULT 1, archived TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS site_nav_items (id INT AUTO_INCREMENT PRIMARY KEY, tab_key VARCHAR(20) NOT NULL, label VARCHAR(80) NOT NULL DEFAULT '', route VARCHAR(255) NOT NULL DEFAULT '', target VARCHAR(20) NOT NULL DEFAULT '', ord SMALLINT NOT NULL DEFAULT 0, visible TINYINT NOT NULL DEFAULT 1, archived TINYINT NOT NULL DEFAULT 0, INDEX(tab_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ensure_home_admin(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS home_pages (user_id INT NOT NULL PRIMARY KEY, background VARCHAR(255) NOT NULL DEFAULT '', published TINYINT NOT NULL DEFAULT 1, hidden TINYINT NOT NULL DEFAULT 0, edit_locked TINYINT NOT NULL DEFAULT 0, rev INT NOT NULL DEFAULT 0, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { if (!col_exists('home_pages', 'rev')) db()->exec("ALTER TABLE home_pages ADD COLUMN rev INT NOT NULL DEFAULT 0"); } catch (Throwable $e) {} // H02 : anti-écrasement
    db()->exec("CREATE TABLE IF NOT EXISTS home_items (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, type VARCHAR(24) NOT NULL, resource VARCHAR(160) NOT NULL DEFAULT '', x SMALLINT NOT NULL DEFAULT 0, y SMALLINT NOT NULL DEFAULT 0, z SMALLINT NOT NULL DEFAULT 0, style VARCHAR(40) NOT NULL DEFAULT '', content TEXT NULL, INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS home_guestbook (id INT AUTO_INCREMENT PRIMARY KEY, owner_id INT NOT NULL, author_id INT NOT NULL, author_name VARCHAR(64) NOT NULL DEFAULT '', message VARCHAR(255) NOT NULL DEFAULT '', hidden TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(owner_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS home_assets_disabled (kind VARCHAR(8) NOT NULL, resource VARCHAR(160) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'shop_hidden', reason VARCHAR(255) NOT NULL DEFAULT '', updated_by VARCHAR(64) NOT NULL DEFAULT '', updated_at DATETIME NULL DEFAULT NULL, PRIMARY KEY (kind,resource)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // H01 : statut à 3 états (available=absent / shop_hidden / banned). Colonnes ajoutées aux anciennes tables.
    try { if (!col_exists('home_assets_disabled', 'status'))     db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'shop_hidden'"); } catch (Throwable $e) {}
    try { if (!col_exists('home_assets_disabled', 'reason'))     db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN reason VARCHAR(255) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    try { if (!col_exists('home_assets_disabled', 'updated_by')) db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN updated_by VARCHAR(64) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    try { if (!col_exists('home_assets_disabled', 'updated_at')) db()->exec("ALTER TABLE home_assets_disabled ADD COLUMN updated_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) {}
    db()->exec("CREATE TABLE IF NOT EXISTS home_reports (id INT AUTO_INCREMENT PRIMARY KEY, target_type VARCHAR(12) NOT NULL, target_id INT NOT NULL, owner_id INT NOT NULL DEFAULT 0, reporter_id INT NOT NULL, reporter_name VARCHAR(64) NOT NULL DEFAULT '', reason VARCHAR(40) NOT NULL DEFAULT '', detail VARCHAR(400) NOT NULL DEFAULT '', status VARCHAR(12) NOT NULL DEFAULT 'new', handled_by VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(status), INDEX(owner_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Décision motivée + snapshot du contenu signalé (ajouts rétro-compatibles)
    try { if (!col_exists('home_reports', 'decision')) db()->exec("ALTER TABLE home_reports ADD COLUMN decision VARCHAR(400) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    try { if (!col_exists('home_reports', 'handled_at')) db()->exec("ALTER TABLE home_reports ADD COLUMN handled_at DATETIME NULL"); } catch (Throwable $e) {}
    try { if (!col_exists('home_reports', 'snapshot')) db()->exec("ALTER TABLE home_reports ADD COLUMN snapshot VARCHAR(400) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    db()->exec("CREATE TABLE IF NOT EXISTS home_snapshots (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, rev INT NOT NULL DEFAULT 0, background VARCHAR(255) NOT NULL DEFAULT '', items_json MEDIUMTEXT NULL, author VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); // H02
    try { if (!col_exists('home_pages', 'hidden_until')) db()->exec("ALTER TABLE home_pages ADD COLUMN hidden_until DATETIME NULL, ADD COLUMN locked_until DATETIME NULL"); } catch (Throwable $e) {}
}
function sitemgr_sections(): array {
    return ['overview' => ['📋', 'Vue d\'ensemble'], 'allcontent' => ['🗂️', 'Tous les contenus'], 'pagesnav' => ['🧭', 'Pages et navigation'], 'linkcheck' => ['🔗', 'Liens & contrôle'], 'news' => ['📰', 'Actualités'], 'carousel' => ['🎠', 'Carrousel'], 'media' => ['🖼️', 'Médias'], 'apparence' => ['🎨', 'Apparence'], 'ressources' => ['🏠', 'Ressources Homes'], 'events' => ['🎉', 'Événements & Infobus'], 'settings' => ['⚙️', 'Réglages du site'], 'history' => ['🕑', 'Historique']];
}
function sitemgr_style(): void {
    static $done = false; if ($done) return; $done = true;
    echo <<<'CSS'
<style>
.smwrap{max-width:none}
.smwrap .sm-nav{display:flex;flex-wrap:wrap;gap:5px;margin:0 0 16px}
.smwrap .sm-nav a{display:inline-flex;align-items:center;gap:6px;padding:8px 13px;border-radius:9px;text-decoration:none;color:var(--txt);border:1px solid var(--line2);background:var(--panel);font-size:13px;transition:.12s}
.smwrap .sm-nav a:hover{border-color:var(--acc)}
.smwrap .sm-nav a.on{background:var(--acc);color:#fff;border-color:var(--acc);font-weight:600}
.smwrap .sm-crumb{font-size:12px;color:var(--mut);margin:0 0 12px}
.smwrap .sm-crumb b{color:var(--txt)}
.smwrap .sm-crumb a{color:var(--mut)}
.smwrap .card{background:var(--panel);border:1px solid var(--line2);border-radius:12px;padding:16px 18px;margin:0 0 14px}
.smwrap .card>h3{margin:0 0 4px;font-size:15px;display:flex;align-items:center;gap:8px}
.smwrap .card>.sub{color:var(--mut);font-size:12px;margin:0 0 12px}
.smwrap .sm-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid var(--line2);background:var(--panel);color:var(--txt);cursor:pointer;text-decoration:none;font-size:13px;line-height:1}
.smwrap .sm-btn:hover{border-color:var(--acc)}
.smwrap .sm-btn.primary{background:var(--acc);color:#fff;border-color:var(--acc)}
.smwrap .sm-btn.danger{color:var(--red);border-color:var(--red)}
.smwrap .sm-btn.sq{padding:6px 9px}
.smwrap .sm-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:14px}
.smwrap .sm-badge{display:inline-flex;align-items:center;gap:6px;font-size:12px}
.smwrap .pill{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;border:1px solid var(--line2);color:var(--mut)}
.smwrap .pill.on{color:var(--green);border-color:var(--green)}
.smwrap .pill.off{color:var(--red);border-color:var(--red)}
.smwrap .pill.draft{color:#e3a008;border-color:#e3a008}
.smwrap .grid3{display:flex;flex-wrap:wrap;gap:8px}
.smwrap input[type=text],.smwrap input:not([type]),.smwrap select,.smwrap textarea{background:var(--panel2);border:1px solid var(--line2);border-radius:7px;padding:7px 9px;color:var(--txt);font:inherit;width:100%}
.smwrap label{font-size:12px;color:var(--mut);display:block}
.smwrap label input,.smwrap label select{margin-top:3px}
/* Zones / blocs */
.smwrap .zcols{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap}
.smwrap .zcol{flex:1 1 240px;min-width:230px;background:var(--panel2);border:1px solid var(--line2);border-radius:12px;padding:10px}
.smwrap .zhead{display:flex;align-items:center;justify-content:space-between;font-weight:600;font-size:13px;margin:0 0 8px;padding-bottom:6px;border-bottom:1px solid var(--line2)}
.smwrap .zhead .zw{color:var(--mut);font-weight:400;font-size:11px}
.smwrap .blk{background:var(--panel);border:1px solid var(--line2);border-radius:9px;padding:9px 11px;margin:0 0 8px}
.smwrap .blk h4{margin:0;font-size:13px}
.smwrap .blk .prev{color:var(--mut);font-size:11px;margin:2px 0 7px;line-height:1.4}
.smwrap .blk .brow{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.smwrap .addhere{display:block;text-align:center;border:1px dashed var(--line2);border-radius:8px;padding:7px;color:var(--mut);text-decoration:none;font-size:12px;margin:0 0 8px}
.smwrap .addhere:hover{border-color:var(--acc);color:var(--acc)}
.smwrap .loc{background:var(--soft);border:1px solid var(--line2);border-radius:8px;padding:8px 12px;font-size:13px;margin:0 0 12px}
.smwrap table.lst{width:100%;border-collapse:collapse;font-size:13px}
.smwrap table.lst td{padding:6px 0;border-top:1px solid var(--line2)}
/* Galerie de contenus */
.smwrap .cgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(224px,1fr));gap:13px}
.smwrap .ccard{display:flex;flex-direction:column;background:var(--panel);border:1px solid var(--line2);border-radius:13px;overflow:hidden;text-decoration:none;color:var(--txt);transition:.14s}
.smwrap .ccard:hover{border-color:var(--acc);transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,0,0,.14)}
.smwrap .cthumb{height:104px;display:flex;align-items:center;justify-content:center;overflow:hidden;border-bottom:1px solid var(--line2);position:relative}
.smwrap .cthumb img{width:100%;height:100%;object-fit:cover}
.smwrap .cthumb .ph{font-size:34px}
.smwrap .cthumb .cstat{position:absolute;top:7px;right:7px}
.smwrap .cbody{padding:11px 13px;display:flex;flex-direction:column;gap:6px;flex:1}
.smwrap .ctag{align-self:flex-start;font-size:10px;padding:2px 8px;border-radius:20px;background:var(--soft);color:var(--acc);font-weight:700;text-transform:uppercase;letter-spacing:.3px}
.smwrap .ctitle{font-weight:700;font-size:13.5px;line-height:1.3}
.smwrap .cexc{font-size:11px;color:var(--mut);line-height:1.45;flex:1}
.smwrap .cfoot{display:flex;align-items:center;justify-content:space-between;font-size:10.5px;color:var(--mut);border-top:1px solid var(--line2);padding-top:8px;margin-top:3px}
.smwrap .cfoot .cedit{color:var(--acc);font-weight:700}
.smwrap .cpill{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;color:#fff;backdrop-filter:blur(2px)}
.smwrap .cpill.pub{background:rgba(18,168,90,.92)}
.smwrap .cpill.unp{background:rgba(229,72,77,.92)}
.smwrap .cpill.dft{background:rgba(227,160,8,.95)}
.smwrap .cpill.arc{background:rgba(120,120,130,.92)}
.smwrap .cnores{color:var(--mut);font-size:13px;padding:14px 2px}
/* Accueil admin : gros boutons + 2 colonnes */
.smwrap .bigbtns{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin:0 0 16px}
.smwrap .bigbtn{display:flex;flex-direction:column;gap:3px;padding:16px 18px;border-radius:14px;border:1px solid var(--line2);background:var(--panel);text-decoration:none;color:var(--txt);transition:.14s}
.smwrap .bigbtn:hover{border-color:var(--acc);transform:translateY(-2px);box-shadow:0 8px 22px rgba(0,0,0,.14)}
.smwrap .bigbtn .bi{font-size:26px}
.smwrap .bigbtn .bt{font-weight:700;font-size:14.5px}
.smwrap .bigbtn .bd{font-size:11.5px;color:var(--mut)}
.smwrap .twocol{display:grid;grid-template-columns:1fr 1fr;gap:14px;align-items:start}
@media(max-width:820px){.smwrap .twocol{grid-template-columns:1fr}}
.smwrap .chkline{font-size:13px}
.smwrap .chkline b{color:var(--acc)}
</style>
CSS;
}
function sitemgr_subnav(string $cur): void {
    echo '<div class="sm-nav">';
    foreach (sitemgr_sections() as $k => $v) {
        $on = ($k === $cur);
        echo '<a href="?p=sitemgr&sec=' . $k . '" class="' . ($on ? 'on' : '') . '"><span>' . $v[0] . '</span> ' . h($v[1]) . '</a>';
    }
    echo '</div>';
}
/* ===================== Modération des Habbo Homes ===================== */
function hm_uname(int $id): string {
    static $c = []; if (isset($c[$id])) return $c[$id];
    try { $s = db()->prepare('SELECT username FROM users WHERE id=?'); $s->execute([$id]); return $c[$id] = (string)$s->fetchColumn(); } catch (Throwable $e) { return $c[$id] = '#' . $id; }
}
/** Skins de widgets Home : clé => préfixe d'images (miroir de home_skins() côté site). */
function hm_skin_defs(): array {
    return ['habbohomes' => 'HabboHomes', 'metal' => 'Metal', 'hcmachine' => 'HCmachine', 'notepad' => 'Notepad', 'speechbubble' => 'Speechbubble', 'stickienote' => 'Stickienote'];
}
function hm_asset_files(string $kind): array {
    if ($kind === 'skin') return array_keys(hm_skin_defs());
    $dir = dirname(__DIR__) . '/c_images/myhabbo/' . ($kind === 'bg' ? 'backgrounds2' : 'stickers');
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (scandir($dir) as $f) { if ($f === '.' || $f === '..') continue; if (preg_match('/\.(gif|png|jpg|jpeg)$/i', $f) && is_file($dir . '/' . $f)) $out[] = $f; }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE); return $out;
}
/** resource => ['status','reason','updated_by','updated_at'] pour un type (H01). Absent = 'available'. */
function hm_asset_statusmap(string $kind): array {
    try { $s = db()->prepare('SELECT resource,status,reason,updated_by,updated_at FROM home_assets_disabled WHERE kind=?'); $s->execute([$kind]); $o = []; foreach ($s->fetchAll() as $r) { $o[$r['resource']] = ['status' => (($r['status'] ?? '') === 'banned' ? 'banned' : 'shop_hidden'), 'reason' => (string)($r['reason'] ?? ''), 'updated_by' => (string)($r['updated_by'] ?? ''), 'updated_at' => (string)($r['updated_at'] ?? '')]; } return $o; } catch (Throwable $e) { return []; }
}
function hm_reason_action(string $action, array $fields, string $label, string $cls = 'sm-btn', bool $withDate = false): string {
    $h = '<details style="display:inline-block;margin:0 3px 3px 0"><summary class="' . $cls . '" style="list-style:none;display:inline-block">' . h($label) . '</summary>'
       . '<form method="post" class="js" data-reload style="margin-top:5px;display:flex;gap:5px;flex-wrap:wrap;align-items:flex-end">' . csrf_field() . '<input type="hidden" name="action" value="' . h($action) . '">';
    foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . h((string)$k) . '" value="' . h((string)$v) . '">';
    if ($withDate) $h .= '<label style="font-size:11px;color:var(--mut)">Jusqu\'au (vide = permanent)<input type="date" name="until"></label>';
    $h .= '<input name="reason" placeholder="motif (journalisé)" style="width:170px"><button class="sm-btn primary">Confirmer</button></form></details>';
    return $h;
}
function page_homemod(): void {
    ensure_home_admin(); sitemgr_style();
    $sec = preg_replace('/[^a-z]/', '', (string)($_GET['sec'] ?? 'reports')); if ($sec === '') $sec = 'reports';
    $nnew = (int) db()->query("SELECT COUNT(*) FROM home_reports WHERE status='new'")->fetchColumn();
    page_title('Modération des Habbo Homes', 'Signalements, recherche de Home et catalogue des ressources. Masquer une Home (public) et bloquer son édition sont deux actions distinctes.');
    echo '<div class="smwrap">';
    echo '<div class="sm-nav">';
    foreach (['reports' => '🚩 Signalements' . ($nnew ? ' (' . $nnew . ')' : ''), 'search' => '🔎 Rechercher une Home', 'restrictions' => '🚧 Restrictions actives', 'history' => '🕑 Historique'] as $k => $lbl)
        echo '<a href="?p=homemod&sec=' . $k . '" class="' . ($k === $sec ? 'on' : '') . '">' . $lbl . '</a>';
    echo '<a href="?p=sitemgr&sec=ressources" title="Déplacé vers Gestion du Site → Ressources">🗂️ Catalogue de ressources ↗</a>';
    echo '</div>';
    if ($sec === 'search') hm_view_search();
    elseif ($sec === 'catalog') hm_view_catalog();
    elseif ($sec === 'restrictions') hm_view_restrictions();
    elseif ($sec === 'history') hm_view_history();
    else hm_view_reports();
    echo '</div>';
}
function hm_view_reports(): void {
    $st = preg_replace('/[^a-z]/', '', (string)($_GET['st'] ?? ''));
    $rid = (int)($_GET['r'] ?? 0);
    // Compteurs d'état (bandeau cliquable)
    $cnt = ['new' => 0, 'progress' => 0, 'handled' => 0];
    try { foreach (db()->query("SELECT status, COUNT(*) n FROM home_reports GROUP BY status") as $c) { if (isset($cnt[$c['status']])) $cnt[$c['status']] = (int)$c['n']; } } catch (Throwable $e) {}
    echo '<div class="todo" style="margin-bottom:14px">';
    foreach (['' => ['🚩', 'Tous', array_sum($cnt)], 'new' => ['🆕', 'Nouveaux', $cnt['new']], 'progress' => ['⏳', 'En cours', $cnt['progress']], 'handled' => ['✅', 'Clos', $cnt['handled']]] as $k => $v)
        echo '<a class="todocard' . ($st === $k ? ' urgent' : '') . '" href="?p=homemod&sec=reports' . ($k ? '&st=' . $k : '') . '"><span class="tn">' . (int)$v[2] . '</span><span class="tl">' . $v[0] . ' ' . h($v[1]) . '</span></a>';
    echo '</div>';

    $sql = 'SELECT * FROM home_reports' . ($st !== '' ? ' WHERE status=' . db()->quote($st) : '') . ' ORDER BY (status="new") DESC, id DESC LIMIT 100';
    $rows = db()->query($sql)->fetchAll();

    echo '<div class="hmwrap">';
    // --- Liste des signalements (gauche) ---
    echo '<div class="hmlist">';
    if (!$rows) echo '<div class="empty">Aucun signalement' . ($st ? ' (' . h($st) . ')' : '') . '.</div>';
    else foreach ($rows as $r) {
        $owner = hm_uname((int)$r['owner_id']);
        $ico = ['new' => '🆕', 'progress' => '⏳', 'handled' => '✅'][$r['status']] ?? '';
        echo '<a class="hmitem' . ($rid === (int)$r['id'] ? ' on' : '') . '" href="?p=homemod&sec=reports' . ($st ? '&st=' . $st : '') . '&r=' . (int)$r['id'] . '">'
           . '<div class="hmi-top"><b>' . h($owner) . '</b><span>' . $ico . '</span></div>'
           . '<div class="sub">' . ($r['target_type'] === 'gb' ? 'Livre d\'or' : 'Home') . ' · ' . h((string)$r['reason']) . '</div>'
           . '<div class="sub" style="font-size:10px">' . h(substr((string)$r['created_at'], 0, 16)) . ' · par ' . h((string)$r['reporter_name']) . '</div></a>';
    }
    echo '</div>';

    // --- Dossier sélectionné (droite) ---
    echo '<div class="hmdetail">';
    if ($rid <= 0) { echo '<div class="card"><p class="sub" style="padding:24px;text-align:center">← Sélectionne un signalement dans la liste pour l\'examiner.</p></div></div></div>'; return; }
    $dr = db()->prepare('SELECT * FROM home_reports WHERE id=?'); $dr->execute([$rid]); $r = $dr->fetch();
    if (!$r) { echo '<div class="card"><div class="empty">Signalement introuvable.</div></div></div></div>'; return; }
    $ownerId = (int)$r['owner_id']; $owner = hm_uname($ownerId); $homeUrl = '/v2/?p=home/' . rawurlencode($owner);
    $backUrl = '?p=homemod&sec=reports' . ($st ? '&st=' . $st : '') . '&r=' . (int)$r['id'];
    $hp = db()->prepare('SELECT hidden,edit_locked,hidden_until,locked_until FROM home_pages WHERE user_id=?'); $hp->execute([$ownerId]); $home = $hp->fetch() ?: ['hidden' => 0, 'edit_locked' => 0, 'hidden_until' => null, 'locked_until' => null];
    $hidden = (int)$home['hidden']; $locked = (int)$home['edit_locked'];
    $hUntil = !empty($home['hidden_until']) ? ' <span class="sub">jusqu\'au ' . h(substr((string)$home['hidden_until'], 0, 10)) . '</span>' : '';
    $lUntil = !empty($home['locked_until']) ? ' <span class="sub">jusqu\'au ' . h(substr((string)$home['locked_until'], 0, 10)) . '</span>' : '';
    $gb = null; if ($r['target_type'] === 'gb') { $gm = db()->prepare('SELECT message,author_name,author_id,hidden FROM home_guestbook WHERE id=?'); $gm->execute([(int)$r['target_id']]); $gb = $gm->fetch() ?: null; }

    echo '<div class="card">';
    echo '<div class="ph"><h3>Signalement n°' . (int)$r['id'] . ' · Home de ' . h($owner) . '</h3></div>';
    echo '<p class="sub" style="margin:-6px 0 12px">Motif : <b>' . h((string)$r['reason']) . '</b> · signalé par <b>' . h((string)$r['reporter_name']) . '</b> le ' . h(substr((string)$r['created_at'], 0, 16)) . '</p>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px"><a class="sm-btn primary" href="' . h($homeUrl) . '" target="_blank">🏠 Voir la Home ↗</a><a class="sm-btn" href="?p=user&id=' . $ownerId . '">👤 Fiche du propriétaire</a><a class="sm-btn" href="?p=messages&pl=' . $ownerId . '">💬 Messages du joueur</a>';
    if ($r['target_type'] === 'gb') echo '<a class="sm-btn" href="' . h($homeUrl) . '#gb" target="_blank">💬 Voir le livre d\'or ↗</a>';
    echo '</div>';

    echo '<div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>📋 Dossier</h3></div><table class="clean">'
       . '<tr><td class="muted">Statut</td><td>' . (['new' => '🆕 Nouveau', 'progress' => '⏳ En cours', 'handled' => '✅ Clos'][$r['status']] ?? h((string)$r['status'])) . ($r['status'] === 'progress' && (string)$r['handled_by'] !== '' ? ' par <b>' . h((string)$r['handled_by']) . '</b>' : '') . '</td></tr>'
       . '<tr><td class="muted">Traité par</td><td>' . h((string)($r['handled_by'] ?: '—')) . '</td></tr>'
       . '<tr><td class="muted">Décision</td><td>' . h((string)($r['decision'] ?? '') ?: '—') . '</td></tr>'
       . (!empty($r['handled_at']) ? '<tr><td class="muted">Le</td><td>' . h(substr((string)$r['handled_at'], 0, 16)) . '</td></tr>' : '')
       . '</table></div>';
    echo '<div class="panel"><div class="ph"><h3>🏠 État de la Home</h3></div><table class="clean">'
       . '<tr><td class="muted">Visibilité</td><td>' . ($hidden ? '<span class="rk red">Masquée</span>' . $hUntil : '<span class="rk green">Visible</span>') . '</td></tr>'
       . '<tr><td class="muted">Édition</td><td>' . ($locked ? '<span class="rk red">Bloquée</span>' . $lUntil : '<span class="rk green">Autorisée</span>') . '</td></tr>'
       . '</table><p class="sub" style="margin-top:6px">Un dossier clos ne masque pas la Home : états distincts. Une restriction avec date se lève automatiquement à l\'échéance.</p></div></div>';

    echo '<div class="panel"><div class="ph"><h3>📝 Contenu signalé</h3></div>';
    echo '<p><span class="muted">Propriétaire :</span> <a href="?p=user&id=' . $ownerId . '">' . h($owner) . '</a> · <span class="muted">Cible :</span> ' . ($r['target_type'] === 'gb' ? 'Message du livre d\'or #' . (int)$r['target_id'] : 'Home entière') . '</p>';
    if ((string)$r['detail'] !== '') echo '<p><span class="muted">Explication du signalant :</span> « ' . h((string)$r['detail']) . ' »</p>';
    if ($r['target_type'] === 'gb') {
        $snap = (string)($r['snapshot'] ?? '');
        if ($snap !== '') echo '<p class="sub" style="margin-top:8px">Contenu <b>signalé</b> (copie au moment du signalement) :</p><div class="warn">« ' . h($snap) . ' »</div>';
        if ($gb) {
            $changed = ($snap !== '' && (string)$gb['message'] !== $snap);
            echo '<p class="sub" style="margin-top:8px">Contenu <b>actuel</b>' . ($changed ? ' <span class="pill off">modifié depuis</span>' : '') . ' :</p>'
               . '<div class="warn">« ' . h((string)$gb['message']) . ' »<div class="sub" style="margin-top:4px">— écrit par <b>' . h((string)$gb['author_name']) . '</b>' . ((int)$gb['author_id'] !== $ownerId ? ' <span class="pill off">auteur ≠ propriétaire</span>' : '') . ((int)$gb['hidden'] ? ' · <span class="rk red">déjà masqué</span>' : '') . '</div></div>';
        } else {
            echo '<p class="sub" style="margin-top:8px">Contenu <b>actuel</b> : <span class="rk red">supprimé depuis</span></p>';
            if ($snap === '') echo '<div class="warn">Contenu d\'origine non disponible (message supprimé avant conservation). Aucune preuve n\'est reconstituée.</div>';
        }
    }
    echo '</div>';

    // Éléments de la Home (widgets, stickers, notes…) — retirer un élément précis sans chercher.
    try { $items = db()->prepare('SELECT id,type,resource FROM home_items WHERE user_id=? ORDER BY id'); $items->execute([$ownerId]); $itemRows = $items->fetchAll(); } catch (Throwable $e) { $itemRows = []; }
    echo '<div class="panel"><div class="ph"><h3>🧩 Éléments de la Home (' . count($itemRows) . ')</h3></div>';
    echo '<p class="sub" style="margin:-6px 0 8px">Retire un widget ou un élément précis directement depuis le dossier (motif journalisé). Retirer un élément ne touche pas au reste de la Home.</p>';
    if (!$itemRows) echo '<div class="empty">Aucun élément.</div>';
    else {
        echo '<table class="clean"><tr><th>#</th><th>Type</th><th>Ressource</th><th style="text-align:right">Retirer</th></tr>';
        foreach ($itemRows as $it) echo '<tr><td class="sub">' . (int)$it['id'] . '</td><td>' . h((string)$it['type']) . '</td><td class="sub">' . h((string)($it['resource'] ?: '—')) . '</td><td style="text-align:right">' . hm_reason_action('hm_item_del', ['item_id' => (int)$it['id'], 'back' => $backUrl], 'Retirer', 'sm-btn danger') . '</td></tr>';
        echo '</table>';
    }
    echo '</div>';
    // Anti double-traitement : alerte si un autre modérateur l'a déjà pris en charge.
    if ($r['status'] === 'progress' && (string)$r['handled_by'] !== '' && (string)$r['handled_by'] !== (string)($_SESSION['admin']['username'] ?? '')) {
        echo '<div class="warn" style="border-color:#8a5216"><b>⚠️ Dossier déjà pris en charge par ' . h((string)$r['handled_by']) . '.</b> Vérifie avec cette personne avant d\'enregistrer une décision, pour éviter un double traitement.</div>';
    }
    // Autres dossiers du même propriétaire (contexte, pas une preuve)
    try { $others = db()->prepare('SELECT id,reason,status,created_at FROM home_reports WHERE owner_id=? AND id<>? ORDER BY id DESC LIMIT 10'); $others->execute([$ownerId, (int)$r['id']]); $otherRows = $others->fetchAll(); } catch (Throwable $e) { $otherRows = []; }
    if ($otherRows) {
        echo '<div class="panel"><div class="ph"><h3>📂 Autres dossiers de ' . h($owner) . ' (' . count($otherRows) . ')</h3></div><p class="sub" style="margin:-6px 0 8px">Leur nombre n\'est pas une preuve de faute.</p><table class="clean"><tr><th>Date</th><th>Motif</th><th>Statut</th><th></th></tr>';
        foreach ($otherRows as $o) echo '<tr><td class="sub">' . h(substr((string)$o['created_at'], 0, 16)) . '</td><td>' . h((string)$o['reason']) . '</td><td>' . (['new' => '🆕 Nouveau', 'progress' => '⏳ En cours', 'handled' => '✅ Clos'][$o['status']] ?? h((string)$o['status'])) . '</td><td><a class="sm-btn" href="?p=homemod&sec=reports&r=' . (int)$o['id'] . '">Ouvrir →</a></td></tr>';
        echo '</table></div>';
    }
    echo '<div class="panel"><div class="ph"><h3>⚖️ Décision</h3></div>';
    echo '<form method="post" class="js" data-reload style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;max-width:760px">' . csrf_field() . '<input type="hidden" name="action" value="hm_report_status"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="back" value="' . h($backUrl) . '">'
       . '<label>Statut du dossier<select name="status"><option value="new"' . ($r['status'] === 'new' ? ' selected' : '') . '>Nouveau</option><option value="progress"' . ($r['status'] === 'progress' ? ' selected' : '') . '>En cours</option><option value="handled"' . ($r['status'] === 'handled' ? ' selected' : '') . '>Clos (décision prise)</option></select></label>'
       . '<label style="flex:1;min-width:240px">Motif / décision<input name="reason" value="' . h((string)($r['decision'] ?? '')) . '" placeholder="Ex. Message masqué (insulte) ; Home laissée visible"></label>'
       . '<button class="sm-btn primary">💾 Enregistrer la décision</button></form>';
    echo '<p class="sub" style="margin:12px 0 6px">Actions sur la Home (chacune demande un motif, journalisé) :</p><div style="display:flex;flex-wrap:wrap;gap:4px">';
    if ($r['target_type'] === 'gb' && $gb) echo hm_reason_action('hm_gb_hide', ['mid' => (int)$r['target_id'], 'val' => ((int)$gb['hidden'] ? 0 : 1), 'back' => $backUrl], ((int)$gb['hidden'] ? 'Rétablir le message' : 'Masquer le message'));
    echo hm_reason_action('hm_home_hide', ['user_id' => $ownerId, 'val' => ($hidden ? 0 : 1), 'back' => $backUrl], ($hidden ? 'Rendre la Home visible' : 'Masquer la Home'), ($hidden ? 'sm-btn' : 'sm-btn danger'), !$hidden);
    echo hm_reason_action('hm_home_lock', ['user_id' => $ownerId, 'val' => ($locked ? 0 : 1), 'back' => $backUrl], ($locked ? 'Débloquer l\'édition' : 'Bloquer l\'édition'), 'sm-btn', !$locked);
    echo '</div>';
    echo '<p class="sub" style="margin-top:12px">Pour sanctionner le compte (bannissement), ouvre la <a href="?p=user&id=' . $ownerId . '">fiche du propriétaire</a> → onglet Modération.</p>';
    echo '</div>';
    // Notifier le propriétaire (aperçu éditable ; ne révèle jamais le signalant)
    $tpl = 'Bonjour, suite à une vérification de modération concernant ta Habbo Home';
    if ((string)($r['decision'] ?? '') !== '') $tpl .= ' : ' . (string)$r['decision'];
    $tpl .= '. Pour toute question, tu peux contacter l\'assistance.';
    echo '<div class="panel"><div class="ph"><h3>✉️ Prévenir le propriétaire</h3></div>';
    echo '<p class="sub" style="margin:-6px 0 8px">Message lu par le joueur sur le site. Ne révèle jamais l\'identité du signalant. Relis l\'aperçu avant d\'envoyer.</p>';
    echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="umsg_send"><input type="hidden" name="id" value="' . $ownerId . '"><input type="hidden" name="back" value="' . h($backUrl) . '"><label>Sujet<input name="subject" maxlength="100" value="Au sujet de ta Habbo Home"></label><label style="margin-top:6px">Message (aperçu éditable)<textarea name="body" rows="3" maxlength="2000" required>' . h($tpl) . '</textarea></label><button class="sm-btn primary" style="margin-top:8px">✉️ Envoyer au propriétaire</button></form></div>';
    echo '</div></div></div>'; // card + hmdetail + hmwrap
}
function hm_view_restrictions(): void {
    echo '<div class="card"><div class="ph"><h3>🚧 Restrictions actives</h3></div>';
    echo '<p class="sub" style="margin:-6px 0 10px">Homes actuellement <b>masquées</b> au public ou dont l\'<b>édition est bloquée</b>. Chaque rétablissement demande un motif (journalisé).</p>';
    try { $rows = db()->query("SELECT user_id,hidden,edit_locked,hidden_until,locked_until FROM home_pages WHERE hidden=1 OR edit_locked=1 ORDER BY updated_at DESC LIMIT 100")->fetchAll(); }
    catch (Throwable $e) { $rows = []; }
    if (!$rows) { echo '<div class="empty">Aucune restriction active. 🎉</div></div>'; return; }
    echo '<table class="clean"><tr><th>Joueur</th><th>Visibilité</th><th>Édition</th><th style="text-align:right">Rétablir</th></tr>';
    foreach ($rows as $r) {
        $uid = (int)$r['user_id']; $owner = hm_uname($uid); $back = '?p=homemod&sec=restrictions';
        echo '<tr><td><a href="?p=user&id=' . $uid . '"><b>' . h($owner) . '</b></a> <a class="sub" href="/v2/?p=home/' . h(rawurlencode($owner)) . '" target="_blank">(Home ↗)</a></td>'
           . '<td>' . ((int)$r['hidden'] ? '<span class="rk red">Masquée</span>' . (!empty($r['hidden_until']) ? ' <span class="sub">→ ' . h(substr((string)$r['hidden_until'], 0, 10)) . '</span>' : '') : '<span class="rk green">Visible</span>') . '</td>'
           . '<td>' . ((int)$r['edit_locked'] ? '<span class="rk red">Bloquée</span>' . (!empty($r['locked_until']) ? ' <span class="sub">→ ' . h(substr((string)$r['locked_until'], 0, 10)) . '</span>' : '') : '<span class="rk green">Autorisée</span>') . '</td><td style="text-align:right">';
        if ((int)$r['hidden']) echo hm_reason_action('hm_home_hide', ['user_id' => $uid, 'val' => 0, 'back' => $back], 'Rendre visible');
        if ((int)$r['edit_locked']) echo hm_reason_action('hm_home_lock', ['user_id' => $uid, 'val' => 0, 'back' => $back], 'Débloquer l\'édition');
        echo '</td></tr>';
    }
    echo '</table></div>';
}
function hm_view_history(): void {
    $q = trim((string)($_GET['q'] ?? ''));
    echo '<form method="get" class="card" style="display:flex;gap:6px;align-items:flex-end"><input type="hidden" name="p" value="homemod"><input type="hidden" name="sec" value="history">'
       . '<label style="flex:1">Rechercher (joueur, décision, modérateur, motif)<input name="q" value="' . h($q) . '" placeholder="Nom, mot-clé…"></label><button class="sm-btn primary">Filtrer</button></form>';
    echo '<div class="card"><div class="ph"><h3>🕑 Décisions sur les signalements</h3></div>';
    echo '<p class="sub" style="margin:-6px 0 10px">Dossiers clos avec leur décision. « Rouvrir » ramène au dossier (où tu peux remettre le statut « En cours »).</p>';
    try { $rows = db()->query("SELECT * FROM home_reports WHERE status='handled' ORDER BY (handled_at IS NULL), handled_at DESC, id DESC LIMIT 100")->fetchAll(); }
    catch (Throwable $e) { $rows = []; }
    $out = [];
    foreach ($rows as $r) { $owner = hm_uname((int)$r['owner_id']); $hay = $owner . ' ' . (string)($r['decision'] ?? '') . ' ' . (string)$r['handled_by'] . ' ' . (string)$r['reason']; if ($q === '' || stripos($hay, $q) !== false) { $r['_owner'] = $owner; $out[] = $r; } }
    if (!$out) { echo '<div class="empty">Aucune décision enregistrée' . ($q ? ' pour « ' . h($q) . ' »' : '') . '.</div></div>'; return; }
    echo '<table class="clean"><tr><th>Quand</th><th>Home</th><th>Motif signalé</th><th>Décision</th><th>Par</th><th></th></tr>';
    foreach ($out as $r) echo '<tr><td class="sub">' . h(substr((string)(!empty($r['handled_at']) ? $r['handled_at'] : $r['created_at']), 0, 16)) . '</td><td><b>' . h($r['_owner']) . '</b></td><td class="sub">' . h((string)$r['reason']) . '</td><td>' . h((string)($r['decision'] ?? '') ?: '—') . '</td><td class="sub">' . h((string)$r['handled_by']) . '</td><td><a class="sm-btn" href="?p=homemod&sec=reports&r=' . (int)$r['id'] . '">Rouvrir →</a></td></tr>';
    echo '</table></div>';
}
function hm_view_search(): void {
    $q = trim((string)($_GET['q'] ?? ''));
    echo '<form method="get" class="card" style="display:flex;gap:6px;align-items:flex-end"><input type="hidden" name="p" value="homemod"><input type="hidden" name="sec" value="search">'
       . '<label style="flex:1">Pseudo du Habbo<input name="q" value="' . h($q) . '" placeholder="Nom exact ou partiel…"></label><button class="sm-btn primary">Rechercher</button></form>';
    if ($q === '') return;
    $us = db()->prepare('SELECT id,username FROM users WHERE username LIKE ? ORDER BY username LIMIT 20'); $us->execute(['%' . $q . '%']);
    $users = $us->fetchAll();
    if (!$users) { echo '<div class="card"><p class="sub">Aucun Habbo trouvé pour « ' . h($q) . ' ».</p></div>'; return; }
    foreach ($users as $u) {
        $uid = (int)$u['id'];
        $hp = db()->prepare('SELECT hidden,edit_locked FROM home_pages WHERE user_id=?'); $hp->execute([$uid]); $home = $hp->fetch() ?: ['hidden' => 0, 'edit_locked' => 0];
        $hidden = (int)$home['hidden']; $locked = (int)$home['edit_locked'];
        echo '<div class="card"><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><b>' . h((string)$u['username']) . '</b>'
           . ($hidden ? ' <span class="pill off">masquée</span>' : ' <span class="pill on">visible</span>')
           . ($locked ? ' <span class="pill draft">édition bloquée</span>' : '')
           . '<a class="sm-btn" href="/v2/?p=home/' . h(rawurlencode((string)$u['username'])) . '" target="_blank" style="margin-left:auto">Consulter la Home ↗</a></div>';
        echo '<div class="sm-actions">';
        echo hm_reason_action('hm_home_hide', ['user_id' => $uid, 'val' => $hidden ? 0 : 1], $hidden ? 'Rétablir la Home' : 'Masquer la Home', $hidden ? 'sm-btn' : 'sm-btn danger');
        echo hm_reason_action('hm_home_lock', ['user_id' => $uid, 'val' => $locked ? 0 : 1], $locked ? 'Débloquer l\'édition' : 'Bloquer l\'édition');
        echo '</div>';
        // Livre d'or
        $gs = db()->prepare('SELECT id,author_name,message,hidden,created_at FROM home_guestbook WHERE owner_id=? ORDER BY id DESC LIMIT 20'); $gs->execute([$uid]); $gb = $gs->fetchAll();
        if ($gb) {
            echo '<div style="margin-top:8px"><b class="sub">Livre d\'or</b><table class="lst">';
            foreach ($gb as $m) echo '<tr><td>' . h((string)$m['message']) . ' <span class="sub">— ' . h((string)$m['author_name']) . ' · ' . h(substr((string)$m['created_at'], 0, 10)) . ((int)$m['hidden'] ? ' · <span style="color:var(--red)">masqué</span>' : '') . '</span></td><td style="text-align:right">' . hm_reason_action('hm_gb_hide', ['mid' => (int)$m['id'], 'val' => (int)$m['hidden'] ? 0 : 1], (int)$m['hidden'] ? 'Rétablir' : 'Masquer') . '</td></tr>';
            echo '</table></div>';
        }
        // Éléments de la Home (pour retirer un élément problématique)
        $it = db()->prepare('SELECT id,type,resource FROM home_items WHERE user_id=? ORDER BY z,id'); $it->execute([$uid]); $items = $it->fetchAll();
        if ($items) {
            echo '<div style="margin-top:8px"><b class="sub">Éléments de la Home</b><div class="grid3" style="margin-top:4px">';
            foreach ($items as $i) {
                $lbl = $i['type'] === 'sticker' ? 'Sticker : ' . h((string)$i['resource']) : h((string)$i['type']);
                echo '<span class="pill">' . $lbl . ' ' . hm_reason_action('hm_item_del', ['item_id' => (int)$i['id']], 'retirer', 'sm-btn danger sq') . '</span>';
            }
            echo '</div></div>';
        }
        echo '</div>';
    }
}
function hm_asset_url_adm(string $kind, string $f): string {
    if ($kind === 'skin') { $pre = hm_skin_defs()[$f] ?? ''; return $pre === '' ? '' : '/web-gallery/images/skins/Skin_' . rawurlencode($pre) . '_topL.png'; }
    return '/c_images/myhabbo/' . ($kind === 'bg' ? 'backgrounds2' : 'stickers') . '/' . rawurlencode($f);
}
function hm_status_badge(string $status): string {
    if ($status === 'banned')      return '<span class="pill" style="background:#f8d7da;border-color:#e4a0a8;color:#842029">⛔ Bannie</span>';
    if ($status === 'shop_hidden') return '<span class="pill" style="background:#fff3cd;border-color:#e6d28a;color:#664d03">🏪 Hors boutique</span>';
    return '<span class="pill" style="background:#d1e7dd;border-color:#9fcdb5;color:#0f5132">✅ Disponible</span>';
}
/** Boutons pour passer la ressource à un autre état (on masque l'état courant). */
function hm_asset_actions(string $kind, string $f, string $status): string {
    $hid = '<input type="hidden" name="action" value="hm_asset_status"><input type="hidden" name="kind" value="' . $kind . '"><input type="hidden" name="resource" value="' . h($f) . '">';
    $out = '';
    if ($status !== 'available')   $out .= '<form method="post" class="js" data-reload style="display:inline-block;margin:0 2px 2px 0">' . csrf_field() . $hid . '<input type="hidden" name="status" value="available"><button class="sm-btn sq">✅ Dispo</button></form>';
    // Les skins sont un set fixe du thème : pas de « hors boutique » (notion boutique sans objet), juste on/off.
    if ($kind !== 'skin' && $status !== 'shop_hidden') $out .= '<form method="post" class="js" data-reload style="display:inline-block;margin:0 2px 2px 0">' . csrf_field() . $hid . '<input type="hidden" name="status" value="shop_hidden"><button class="sm-btn sq">🏪 Hors boutique</button></form>';
    if ($status !== 'banned') {
        $lbl = $kind === 'skin' ? '⛔ Désactiver' : '⛔ Bannir';
        $out .= '<details style="display:inline-block;margin:0 2px 2px 0"><summary class="sm-btn sq danger" style="list-style:none;display:inline-block">' . $lbl . '</summary><form method="post" class="js" data-reload style="margin-top:4px">' . csrf_field() . $hid . '<input type="hidden" name="status" value="banned"><input name="reason" placeholder="motif (optionnel)" maxlength="255" style="width:150px;display:block;margin-bottom:3px"><button class="sm-btn sq danger">Confirmer</button></form></details>';
    }
    return $out;
}
function hm_asset_card(string $kind, string $f, string $status, array $meta = []): string {
    $dim = $status === 'banned' ? ';opacity:.55' : ($status === 'shop_hidden' ? ';opacity:.85' : '');
    $info = '';
    if ($status === 'banned' && !empty($meta['reason'])) $info .= '<div class="sub" style="font-size:9px;color:#842029">motif : ' . h((string)$meta['reason']) . '</div>';
    if (!empty($meta['updated_by'])) $info .= '<div class="sub" style="font-size:8px;color:#999">' . h((string)$meta['updated_by']) . (!empty($meta['updated_at']) ? ' · ' . h(substr((string)$meta['updated_at'], 0, 10)) : '') . '</div>';
    return '<div style="width:116px;text-align:center;border:1px solid var(--line2);border-radius:6px;padding:5px' . $dim . '">'
        . '<img src="' . hm_asset_url_adm($kind, $f) . '" loading="lazy" style="max-width:100%;max-height:52px;object-fit:contain">'
        . '<div class="sub" style="font-size:9px;word-break:break-all;margin:2px 0">' . h($f) . '</div>'
        . '<div style="margin-bottom:3px">' . hm_status_badge($status) . '</div>' . $info
        . '<div style="margin-top:4px">' . hm_asset_actions($kind, $f, $status) . '</div></div>';
}
function hm_view_catalog(): void {
    $kind = in_array($_GET['kind'] ?? '', ['bg', 'sticker', 'skin'], true) ? (string)$_GET['kind'] : 'sticker';
    $q = trim((string)($_GET['q'] ?? ''));
    $filter = in_array($_GET['st'] ?? '', ['available', 'shop_hidden', 'banned'], true) ? (string)$_GET['st'] : 'all';
    $ppOpts = [24, 48, 96, 200];
    $pp = (int)($_GET['pp'] ?? 48); if (!in_array($pp, $ppOpts, true)) $pp = 48;
    $pg = max(1, (int)($_GET['pg'] ?? 1));

    $map = hm_asset_statusmap($kind);
    $files = hm_asset_files($kind);
    $total = count($files);
    $nBan = 0; $nShop = 0; foreach ($map as $v) { if ($v['status'] === 'banned') $nBan++; else $nShop++; }
    $nAvail = max(0, $total - $nShop - $nBan);

    // Filtrage (statut + recherche) sur TOUT le catalogue, puis pagination.
    $res = array_values(array_filter($files, function ($f) use ($q, $filter, $map) {
        if ($q !== '' && stripos($f, $q) === false) return false;
        $st = isset($map[$f]) ? $map[$f]['status'] : 'available';
        return $filter === 'all' ? true : $st === $filter;
    }));
    $resCount = count($res);
    $pages = max(1, (int)ceil($resCount / $pp));
    if ($pg > $pages) $pg = $pages;
    $slice = array_slice($res, ($pg - 1) * $pp, $pp);
    $qs = function (array $over = []) use ($kind, $q, $filter, $pp, $pg) {
        return '?' . http_build_query(array_merge(['p' => 'sitemgr', 'sec' => 'ressources', 'kind' => $kind, 'st' => $filter, 'q' => $q, 'pp' => $pp, 'pg' => $pg], $over));
    };

    echo '<div class="card">';
    echo '<div class="sub" style="margin-bottom:8px;line-height:1.6">Chaque ressource a <b>3 états</b> : ✅ <b>Disponible</b> (proposée + affichée) · 🏪 <b>Hors boutique</b> (plus proposée mais <b>toujours affichée</b> chez ceux qui l\'ont déjà) · ⛔ <b>Bannie</b> (retirée partout, modération).</div>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">'
       . '<span class="pill">Total <b>' . $total . '</b></span>'
       . '<span class="pill" style="background:#d1e7dd;border-color:#9fcdb5">✅ ' . $nAvail . '</span>'
       . '<span class="pill" style="background:#fff3cd;border-color:#e6d28a">🏪 ' . $nShop . '</span>'
       . '<span class="pill" style="background:#f8d7da;border-color:#e4a0a8">⛔ ' . $nBan . '</span></div>';

    // Filtres (GET) — on repart page 1
    echo '<form method="get" style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap"><input type="hidden" name="p" value="sitemgr"><input type="hidden" name="sec" value="ressources"><input type="hidden" name="pg" value="1">'
       . '<label>Type<select name="kind"><option value="sticker"' . ($kind === 'sticker' ? ' selected' : '') . '>Stickers</option><option value="bg"' . ($kind === 'bg' ? ' selected' : '') . '>Fonds</option><option value="skin"' . ($kind === 'skin' ? ' selected' : '') . '>Skins (cadres)</option></select></label>'
       . '<label>Statut<select name="st">'
       . '<option value="all"' . ($filter === 'all' ? ' selected' : '') . '>Tous</option>'
       . '<option value="available"' . ($filter === 'available' ? ' selected' : '') . '>✅ Disponibles</option>'
       . '<option value="shop_hidden"' . ($filter === 'shop_hidden' ? ' selected' : '') . '>🏪 Hors boutique</option>'
       . '<option value="banned"' . ($filter === 'banned' ? ' selected' : '') . '>⛔ Bannies</option></select></label>'
       . '<label>Par page<select name="pp">' . implode('', array_map(fn($o) => '<option value="' . $o . '"' . ($pp === $o ? ' selected' : '') . '>' . $o . '</option>', $ppOpts)) . '</select></label>'
       . '<label style="flex:1">Rechercher<input name="q" value="' . h($q) . '" placeholder="nom du fichier…"></label><button class="sm-btn primary">Filtrer</button>';
    if ($q !== '' || $filter !== 'all' || $pp !== 48) echo ' <a class="sm-btn" href="?p=sitemgr&sec=ressources">↺ Réinitialiser</a>';
    echo '</form>';

    // Actions groupées sur TOUS les résultats du filtre courant (pas seulement la page affichée)
    if ($resCount > 0) {
        $hid = csrf_field() . '<input type="hidden" name="action" value="hm_asset_bulk"><input type="hidden" name="kind" value="' . $kind . '"><input type="hidden" name="q" value="' . h($q) . '"><input type="hidden" name="fst" value="' . $filter . '">';
        echo '<div style="margin-top:10px;padding:8px;border:1px dashed var(--line2);border-radius:6px">'
           . '<b class="sub">Action groupée sur les ' . $resCount . ' résultat(s) filtrés :</b> '
           . '<form method="post" class="js" data-reload style="display:inline-block;margin-left:6px">' . $hid . '<input type="hidden" name="target" value="available"><button class="sm-btn" onclick="return confirm(\'Mettre ' . $resCount . ' ressource(s) en DISPONIBLE ?\')">✅ Tout Disponible</button></form> '
           . '<form method="post" class="js" data-reload style="display:inline-block;margin-left:4px">' . $hid . '<input type="hidden" name="target" value="shop_hidden"><button class="sm-btn" onclick="return confirm(\'Mettre ' . $resCount . ' ressource(s) HORS BOUTIQUE ?\')">🏪 Tout Hors boutique</button></form>'
           . '<div class="sub" style="font-size:9px;margin-top:4px;color:#888">Astuce : recherche un mot-clé (ex. « xmas », « tokiohotel ») puis « Tout Hors boutique » pour écarter une série d\'un coup.</div></div>';
    }

    // Grille paginée
    echo '<div style="margin-top:12px"><b class="sub">' . $resCount . ' ressource(s)' . ($pages > 1 ? ' — page ' . $pg . '/' . $pages : '') . '</b><div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px">';
    foreach ($slice as $f) { $st = isset($map[$f]) ? $map[$f]['status'] : 'available'; echo hm_asset_card($kind, $f, $st, $map[$f] ?? []); }
    if (!$slice) echo '<div class="sub">Aucune ressource pour ce filtre.</div>';
    echo '</div>';

    // Pagination
    if ($pages > 1) {
        echo '<div style="margin-top:12px;display:flex;gap:4px;flex-wrap:wrap;align-items:center">';
        if ($pg > 1) echo '<a class="sm-btn" href="' . h($qs(['pg' => $pg - 1])) . '">‹ Précédent</a>';
        $from = max(1, $pg - 3); $to = min($pages, $pg + 3);
        if ($from > 1) echo '<a class="sm-btn sq" href="' . h($qs(['pg' => 1])) . '">1</a>' . ($from > 2 ? '<span class="sub"> … </span>' : '');
        for ($i = $from; $i <= $to; $i++) echo '<a class="sm-btn sq' . ($i === $pg ? ' primary' : '') . '" href="' . h($qs(['pg' => $i])) . '">' . $i . '</a>';
        if ($to < $pages) echo ($to < $pages - 1 ? '<span class="sub"> … </span>' : '') . '<a class="sm-btn sq" href="' . h($qs(['pg' => $pages])) . '">' . $pages . '</a>';
        if ($pg < $pages) echo '<a class="sm-btn" href="' . h($qs(['pg' => $pg + 1])) . '">Suivant ›</a>';
        echo '</div>';
    }
    echo '</div></div>';
}
function page_sitemgr(): void {
    ensure_site_pages_admin(); ensure_site_nav_admin(); ensure_carousel_admin();
    // ---- Mode édition d'une page de contenu ----
    $edit = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['edit'] ?? ''));
    if ($edit !== '') { sitemgr_style(); echo '<div class="smwrap">'; sitemgr_page_editor($edit); echo '</div>'; return; }
    $sec = preg_replace('/[^a-z]/', '', (string)($_GET['sec'] ?? 'overview')); if ($sec === '') $sec = 'overview';
    page_title('Gestion du Site', 'Gère tout le site 2007 sans écrire de code.');
    sitemgr_style();
    echo '<div class="smwrap">';
    sitemgr_subnav($sec);
    switch ($sec) {
        case 'allcontent': sitemgr_allcontent(); break;
        case 'pagesnav': sitemgr_pagesnav(); break;
        case 'linkcheck': sitemgr_linkcheck(); break;
        case 'news': sitemgr_news(); break;
        case 'carousel': sitemgr_carousel(); break;
        case 'accueil': case 'blocs': sitemgr_accueil(); break;
        case 'events': sitemgr_events(); break;
        case 'media': sitemgr_media(); break;
        case 'apparence': sitemgr_apparence(); break;
        case 'ressources': ensure_home_admin(); echo '<p class="sm-crumb">Gestion du Site › <b>Ressources Homes</b> (stickers &amp; fonds)</p>'; hm_view_catalog(); break;
        case 'settings': sitemgr_settings(); break;
        case 'history': sitemgr_history(); break;
        default: sitemgr_overview(); break;
    }
    echo '</div>';
}

/* Routes internes connues du site v2 (pour détecter les liens ?p= cassés). */
function site_known_routes(): array {
    $base = ['home', 'news', 'events', 'community', 'help', 'forgot', 'messages', 'me', 'club', 'credits', 'shop', 'mobile', 'register', 'actu', 'logout', 'contact',
        'home_report', 'home_sign', 'home_addfriend', 'home_save', 'conv_new', 'conv_reply', 'conv_move', 'conv_msg_hide'];
    $subref = ['staff', 'mods', 'furniture', 'ecotron', 'pets', 'home_info', 'clans', 'new', 'fansite', 'apparts', 'anepasmanquer', 'homefriends', 'invite', 'filsante',
        'tchatvip', 'bobbaweek', 'habboscope', 'rodeau', 'plongeoir', 'gamesweek', 'minicartes', 'tshirt', 'sonneries', 'fondecran', 'imager',
        'coinsfr', 'coinsbe', 'coinsch', 'coinsca', 'coinsother', 'creditshelp', 'hcjoin', 'hcshop', 'faqs', 'parents_guide', 'habbo_way', 'email', 'habbo_x'];
    $slugs = [];
    try { foreach (db()->query('SELECT slug FROM site_pages') as $r) $slugs[] = (string)$r['slug']; } catch (Throwable $e) {}
    return array_values(array_unique(array_merge($base, $subref, $slugs)));
}

/* Analyse un href et renvoie [statut, détail] : 'ok' | 'empty' | 'broken' | 'external' | 'check'. */
function link_classify(string $href, array $known): array {
    $u = trim($href);
    if ($u === '' || $u === '#' || stripos($u, 'javascript:') === 0) return ['empty', 'bouton sans destination'];
    if (stripos($u, 'mailto:') === 0 || stripos($u, 'tel:') === 0) return ['external', $u];
    if (preg_match('#^(https?:)?//#i', $u)) return ['external', $u];
    // Lien interne : extraire le paramètre p
    if (preg_match('/[?&]p=([^&"#\']+)/', $u, $m)) {
        $p = urldecode($m[1]);
        if (strpos($p, 'home/') === 0) return ['ok', $p];
        if (in_array($p, $known, true)) return ['ok', $p];
        return ['broken', 'route inconnue : p=' . $p];
    }
    // /help et /help/NN sont réécrits vers ?p=help par le .htaccess
    if (preg_match('#^/help(/|$|\?|\#)#i', $u)) return ['ok', $u];
    // Fichiers/chemins connus (client, ancres, racine)
    if ($u === '/' || $u === '?' || strpos($u, '#') === 0) return ['ok', $u];
    if (preg_match('#\.(php|html?|gif|png|jpe?g|swf|dcr|cct|ico)(\?|$)#i', $u)) return ['ok', $u];
    return ['check', $u];
}

function sitemgr_linkcheck(): void {
    echo '<p class="sm-crumb">Gestion du Site › <b>Liens &amp; contrôle</b></p>';
    $known = site_known_routes();
    $broken = []; $empty = []; $incomplete = []; $ext = []; $check = [];

    // Extrait tous les href d'un contenu HTML et les classe, en les rattachant à une source.
    $scan = function (string $html, string $src) use ($known, &$broken, &$empty, &$ext, &$check) {
        if (preg_match_all('/href\s*=\s*"([^"]*)"/i', $html, $mm)) {
            foreach ($mm[1] as $href) {
                [$st, $d] = link_classify($href, $known);
                $row = ['src' => $src, 'href' => $href, 'detail' => $d];
                if ($st === 'broken') $broken[] = $row;
                elseif ($st === 'empty') $empty[] = $row;
                elseif ($st === 'external') $ext[] = $row;
                elseif ($st === 'check') $check[] = $row;
            }
        }
    };

    // 1) Pages du site (contenu + complétude)
    try {
        foreach (db()->query('SELECT slug,title,body_html,missing_note FROM site_pages ORDER BY slug') as $pg) {
            $body = (string)($pg['body_html'] ?? '');
            if (trim(strip_tags($body)) === '') $incomplete[] = ['src' => 'Page « ' . $pg['slug'] . ' »', 'detail' => trim((string)($pg['missing_note'] ?? '')) !== '' ? 'corps vide — note : ' . mb_strimwidth((string)$pg['missing_note'], 0, 80, '…') : 'corps vide (contenu à reproduire)'];
            else $scan($body, 'Page « ' . $pg['slug'] . ' »');
        }
    } catch (Throwable $e) {}

    // 2) Actualités
    try { foreach (db()->query('SELECT id,title,body FROM site_news ORDER BY id DESC LIMIT 200') as $n) $scan((string)$n['body'], 'Actu #' . $n['id'] . ' « ' . mb_strimwidth((string)$n['title'], 0, 30, '…') . ' »'); } catch (Throwable $e) {}

    // 3) Carrousel (image + lien des slides actives)
    try {
        foreach (db()->query('SELECT page,slot,title,image,link,active FROM site_carousel') as $c) {
            if (!(int)$c['active']) continue;
            $src = 'Carrousel ' . $c['page'] . ' #' . $c['slot'];
            if (trim((string)$c['image']) === '') $incomplete[] = ['src' => $src, 'detail' => 'slide active sans image'];
            if (trim((string)$c['link']) !== '') { [$st, $d] = link_classify((string)$c['link'], $known); if ($st === 'broken') $broken[] = ['src' => $src, 'href' => (string)$c['link'], 'detail' => $d]; }
        }
    } catch (Throwable $e) {}

    $tot = count($broken) + count($empty) + count($incomplete);
    echo '<div class="panel"><div class="ph"><h3>🔗 Contrôle des liens & contenus</h3><span class="' . ($tot ? 'rk red' : 'rk') . '" style="margin-left:auto">' . ($tot ? $tot . ' à corriger' : 'aucun problème 🎉') . '</span></div>';
    echo '<p class="muted sm" style="margin:-2px 0 10px">Analyse des contenus gérés (pages, actualités, carrousel) : liens internes cassés, boutons sans destination et contenus incomplets. Les liens externes et les cibles à vérifier sont listés à titre indicatif.</p>';

    $tbl = function (string $title, array $rows, string $kind) {
        echo '<h4 style="margin:12px 0 6px">' . $title . ' <span class="muted">(' . count($rows) . ')</span></h4>';
        if (!$rows) { echo '<div class="empty" style="padding:8px">Rien à signaler.</div>'; return; }
        echo '<table class="clean"><tr><th>Source</th>' . ($kind === 'incomplete' ? '' : '<th>Lien</th>') . '<th>Détail</th></tr>';
        foreach ($rows as $r) echo '<tr><td class="sm">' . h((string)$r['src']) . '</td>' . ($kind === 'incomplete' ? '' : '<td class="sm muted" style="word-break:break-all">' . h((string)($r['href'] ?? '')) . '</td>') . '<td class="sm">' . h((string)$r['detail']) . '</td></tr>';
        echo '</table>';
    };
    $tbl('❌ Liens internes cassés', $broken, 'broken');
    $tbl('🔘 Boutons sans destination', $empty, 'empty');
    $tbl('⚠️ Contenus incomplets', $incomplete, 'incomplete');
    echo '<details style="margin-top:12px"><summary class="mini">🌐 Liens externes (' . count($ext) . ') & à vérifier (' . count($check) . ')</summary>';
    $tbl('🌐 Liens externes (non vérifiés automatiquement)', $ext, 'broken');
    $tbl('❓ Liens à vérifier (forme inhabituelle)', $check, 'broken');
    echo '</details>';
    echo '</div>';

    // ===== Liens ouverts depuis le client (external_texts) =====
    $cli = []; $cliBad = 0;
    foreach (['external_texts.txt', 'external_texts_en.txt'] as $fn) {
        $path = __DIR__ . '/../dcr/14.1_b8/' . $fn;
        if (!is_file($path)) continue;
        $raw = @file_get_contents($path); if ($raw === false) continue;
        $txt = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1252'); // external_texts = CP1252
        foreach (preg_split('/\r\n|\n|\r/', $txt) as $line) {
            if ($line === '' || $line[0] === '#') continue;
            $eq = strpos($line, '='); if ($eq === false) continue;
            $key = substr($line, 0, $eq); $val = trim(substr($line, $eq + 1));
            // On ne garde que les valeurs qui sont des liens (URL ou ?p=)
            if (!preg_match('#https?://|\?p=#i', $val)) continue;
            // Normalise : retire l'hôte dynamique du client
            $norm = preg_replace('#https?://(%predefined%|localhost|127\.0\.0\.1)#i', '', $val);
            [$st, $d] = link_classify($norm, $known);
            if ($st === 'external') continue; // lien externe réel (sulake, imaging…) : non bloquant
            if ($st === 'ok') continue;
            $cli[] = ['src' => $fn . ' · ' . $key, 'href' => $val, 'detail' => $d];
            $cliBad++;
        }
    }
    echo '<div class="panel"><div class="ph"><h3>🎮 Liens ouverts depuis le client</h3><span class="' . ($cliBad ? 'rk red' : 'rk') . '" style="margin-left:auto">' . ($cliBad ? $cliBad . ' à vérifier' : 'OK 🎉') . '</span></div>';
    echo '<p class="muted sm" style="margin:-2px 0 10px">Liens définis dans <code>external_texts</code> (boutons/pages ouverts par le client Habbo). On vérifie que la cible <code>?p=…</code> correspond à une route réelle du site. Les liens vers des pages externes (Sulake, imaging) ne sont pas signalés.</p>';
    if (!$cli) echo '<div class="empty">Aucun lien client cassé détecté 🎉</div>';
    else { echo '<table class="clean"><tr><th>Clé client</th><th>Lien</th><th>Détail</th></tr>';
        foreach ($cli as $r) echo '<tr><td class="sm">' . h((string)$r['src']) . '</td><td class="sm muted" style="word-break:break-all">' . h((string)$r['href']) . '</td><td class="sm">' . h((string)$r['detail']) . '</td></tr>';
        echo '</table>'; }
    echo '</div>';

    // ===== Test HTTP en direct (opt-in) =====
    echo '<div class="panel"><div class="ph"><h3>🌐 Test HTTP des routes (en direct)</h3></div>';
    echo '<p class="muted sm" style="margin:-2px 0 10px">Vérifie l\'état réel (code HTTP) des principales pages du site en les appelant sur <code>127.0.0.1</code>. À lancer <b>site démarré</b> ; un port ouvert ne prouve pas que la page fonctionne.</p>';
    if (($_GET['http'] ?? '') !== '1') {
        echo '<a class="mini" href="?p=sitemgr&sec=linkcheck&http=1">▶️ Lancer le test HTTP</a>';
    } else {
        $routes = ['home', 'news', 'events', 'community', 'help', 'forgot', 'club', 'credits', 'shop', 'mobile', 'register'];
        echo '<table class="clean"><tr><th>Route</th><th>Code</th><th>État</th></tr>';
        foreach ($routes as $rt) {
            $url = 'http://127.0.0.1/?p=' . $rt;
            $code = 0; $err = '';
            if (function_exists('curl_init')) {
                // cURL : évite la variable magique $http_response_header (dépréciée en PHP 8.4)
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'linkcheck']);
                $resp = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                if ($resp === false && $code === 0) $err = 'injoignable';
                curl_close($ch);
            } else {
                $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true, 'header' => "User-Agent: linkcheck\r\n"]]);
                $resp = @file_get_contents($url, false, $ctx);
                $hdrs = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?: []) : [];
                if ($resp === false && !$hdrs) { $err = 'injoignable'; }
                elseif (isset($hdrs[0]) && preg_match('/\s(\d{3})\s/', $hdrs[0], $m)) { $code = (int)$m[1]; }
            }
            $okc = ($code >= 200 && $code < 400);
            echo '<tr><td class="sm">?p=' . h($rt) . '</td><td class="sm">' . ($code ?: '—') . '</td><td class="sm">' . ($err !== '' ? '🔴 ' . h($err) . ' (site démarré ?)' : ($okc ? '🟢 OK' : '🔴 erreur')) . '</td></tr>';
        }
        echo '</table><a class="mini ghost" style="margin-top:8px" href="?p=sitemgr&sec=linkcheck">↩︎ Masquer</a>';
    }
    echo '</div>';
}
function sp_tablabel(string $k): string {
    static $m = ['home' => 'Accueil', 'register' => 'Habbo Hotel', 'community' => 'Communauté', 'events' => 'Events', 'games' => 'Jeux', 'shop' => 'Boutique', 'mobile' => 'Mobile', 'credits' => 'Crédits', 'club' => 'Habbo Club', 'help' => 'Aide'];
    return $m[$k] ?? ucfirst($k);
}
function sitemgr_page_editor(string $edit): void {
    ensure_site_pages_admin();
    $st = db()->prepare('SELECT * FROM site_pages WHERE slug=?'); $st->execute([$edit]); $pg = $st->fetch();
    if (!$pg) { echo '<div class="warn">Page introuvable.</div>'; return; }
    $hasDraft = isset($pg['body_draft']) && trim((string)$pg['body_draft']) !== '';
    $content  = $hasDraft ? (string)$pg['body_draft'] : (string)$pg['body_html'];
    $status   = $pg['status'] ?? 'published';
    $parent   = (string)($pg['parent_tab'] ?: 'home');
    $crumb    = 'Gestion du Site › ' . h(sp_tablabel($parent)) . ' › <b>' . h((string)$pg['title']) . '</b>';
    $block    = isset($_GET['block']) && $_GET['block'] !== '' ? (int)$_GET['block'] : null;

    $mdir = dirname(__DIR__) . '/web-gallery/v2/pages_img';
    $media = is_dir($mdir) ? array_values(array_filter(scandir($mdir), fn($f) => !in_array($f, ['.', '..'], true) && is_file($mdir . '/' . $f))) : [];
    $blocks = sitepage_blocks($content);

    /* ============ Vue : éditer UN bloc ============ */
    if ($block !== null && isset($blocks[$block])) {
        $b = $blocks[$block];
        page_title('Modifier un bloc', 'Choisis ce que tu veux changer : le titre, le texte, les images ou les liens. La présentation du site est conservée.');
        echo '<p class="muted sm" style="margin:0 0 8px">' . $crumb . ' › <b>Bloc : ' . (h($b['title']) ?: ('n°' . ($block + 1))) . '</b></p>';
        echo '<p style="margin:0 0 10px"><a href="?p=sitemgr&edit=' . h($edit) . '">← Revenir à la liste des blocs</a></p>';

        echo '<form method="post" class="js" data-reload id="blkform"><div class="panel">' . csrf_field()
           . '<input type="hidden" name="action" value="sitepage_block_save"><input type="hidden" name="slug" value="' . h($edit) . '">'
           . '<input type="hidden" name="block" value="' . $block . '"><input type="hidden" name="mode" id="blkmode" value="draft">'
           . '<input type="hidden" name="block_body" id="blkbody">';
        echo '<label style="display:block;margin-bottom:10px"><b>Titre du bloc</b><br><input name="block_title" value="' . h($b['title']) . '" style="width:100%"></label>';

        // Barre d'outils (libellés texte lisibles)
        echo '<b>Texte du bloc</b><div style="display:flex;flex-wrap:wrap;gap:4px;margin:4px 0 0;padding:6px;border:1px solid var(--line2);border-bottom:0;border-radius:6px 6px 0 0;background:var(--panel2)">';
        $tb = [
            ['wzBlock(\'P\')', 'Paragraphe'], ['wzBlock(\'H3\')', 'Titre'], ['wzCmd(\'bold\')', 'Gras'],
            ['wzCmd(\'italic\')', 'Italique'], ['wzCmd(\'insertUnorderedList\')', 'Liste à puces'],
            ['wzCmd(\'insertOrderedList\')', 'Liste numérotée'], ['wzLink()', 'Ajouter un lien'], ['wzUnlink()', 'Retirer le lien'],
        ];
        foreach ($tb as $t) echo '<button type="button" onclick="' . h($t[0]) . '" style="padding:5px 11px;border:1px solid var(--line2);border-radius:5px;background:var(--panel);color:var(--txt);cursor:pointer;font-size:12px">' . h($t[1]) . '</button>';
        echo '</div>';
        echo '<div id="wz" contenteditable="true" oninput="wzSync()" style="min-height:160px;border:1px solid var(--line2);border-radius:0 0 6px 6px;padding:12px;background:#fff;color:#222;font:12px/1.5 Verdana,Arial,sans-serif">' . $b['body'] . '</div>';

        // Images du bloc
        echo '<div id="blkimgs" style="margin-top:12px"></div>';
        // Liens du bloc
        echo '<div id="blklinks" style="margin-top:12px"></div>';

        // Bibliothèque (pour « Remplacer l\'image »)
        echo '<div id="wzmedia" data-target="" style="display:none;margin-top:8px;padding:8px;border:1px solid var(--line2);border-radius:6px;max-height:220px;overflow:auto"><div class="row" style="justify-content:space-between"><b class="muted sm">Choisis l\'image de remplacement</b><a href="?p=sitemgr&sec=media" target="_blank" class="muted sm">+ Importer une image ↗</a></div><div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px">';
        foreach ($media as $f) echo '<img src="/web-gallery/v2/pages_img/' . h($f) . '" alt="" title="' . h($f) . '" onclick="wzPick(\'/web-gallery/v2/pages_img/' . h($f) . '\')" style="width:70px;height:48px;object-fit:contain;border:1px solid var(--line2);border-radius:4px;cursor:pointer;background:#fff">';
        if (!$media) echo '<span class="muted sm">Aucune image. Importe-les dans la rubrique « Médias ».</span>';
        echo '</div></div>';

        echo '<div class="row" style="gap:6px;margin-top:14px">'
           . '<a class="chip" href="?p=sitemgr&edit=' . h($edit) . '" style="padding:7px 12px;border:1px solid var(--line2);border-radius:6px;text-decoration:none">Annuler</a>'
           . '<button type="submit" onclick="document.getElementById(\'blkmode\').value=\'draft\'">Enregistrer le brouillon</button>'
           . '<a class="chip" href="/v2/?p=' . h($edit) . '&preview=1" target="_blank" style="padding:7px 12px;border:1px solid var(--line2);border-radius:6px;text-decoration:none">Prévisualiser ↗</a>'
           . '<button type="submit" onclick="document.getElementById(\'blkmode\').value=\'publish\'" style="background:var(--acc);color:#fff;border-color:var(--acc);margin-left:auto">Publier les modifications</button>'
           . '</div>';
        echo '</div></form>';

        echo <<<'JS'
<script>
function wzEl(){return document.getElementById('wz');}
function wzSync(){var e=wzEl();document.getElementById('blkbody').value=e.innerHTML;wzBuildPanels();}
function wzFocus(){wzEl().focus();}
function wzCmd(c){wzFocus();document.execCommand(c,false,null);wzSync();}
function wzBlock(t){wzFocus();document.execCommand('formatBlock',false,t);wzSync();}
function wzLink(){var u=prompt('Page de destination (ex. ?p=club, ?p=home/Pseudo, ou https://…) :','?p=');if(u){wzFocus();document.execCommand('createLink',false,u);wzSync();}}
function wzUnlink(){wzFocus();document.execCommand('unlink',false,null);wzSync();}
var wzTarget=null;
function wzReplace(i){wzTarget=i;var m=document.getElementById('wzmedia');m.style.display='block';m.scrollIntoView({block:'nearest'});}
function wzPick(src){if(wzTarget!=null){var imgs=wzEl().querySelectorAll('img');if(imgs[wzTarget]){imgs[wzTarget].setAttribute('src',src);}}document.getElementById('wzmedia').style.display='none';wzTarget=null;wzSync();}
function wzSetLinkText(i,v){var as=wzEl().querySelectorAll('a');if(as[i])as[i].textContent=v;document.getElementById('blkbody').value=wzEl().innerHTML;}
function wzSetLinkHref(i,v){var as=wzEl().querySelectorAll('a');if(as[i])as[i].setAttribute('href',v);document.getElementById('blkbody').value=wzEl().innerHTML;}
function wzBuildPanels(){
  var e=wzEl();
  var imgs=e.querySelectorAll('img');var ic=document.getElementById('blkimgs');
  if(imgs.length){var h='<b>Images de ce bloc</b><div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px">';
    for(var i=0;i<imgs.length;i++){h+='<div style="text-align:center;border:1px solid var(--line2);border-radius:6px;padding:6px"><img src="'+imgs[i].getAttribute('src')+'" style="max-width:90px;max-height:60px;object-fit:contain;background:#fff"><br><button type="button" onclick="wzReplace('+i+')" style="margin-top:4px;padding:4px 9px;border:1px solid var(--line2);border-radius:5px;background:var(--panel);color:var(--txt);cursor:pointer;font-size:12px">Remplacer l\'image</button></div>';}
    h+='</div>';ic.innerHTML=h;}else ic.innerHTML='';
  var as=e.querySelectorAll('a');var lc=document.getElementById('blklinks');
  if(as.length){var h2='<b>Liens de ce bloc</b><div style="margin-top:6px">';
    for(var j=0;j<as.length;j++){var tx=(as[j].textContent||'').replace(/"/g,'&quot;');var hr=(as[j].getAttribute('href')||'').replace(/"/g,'&quot;');
      h2+='<div class="row" style="gap:6px;margin-bottom:4px"><label style="flex:1">Texte du lien<input value="'+tx+'" oninput="wzSetLinkText('+j+',this.value)"></label><label style="flex:1">Page de destination<input value="'+hr+'" oninput="wzSetLinkHref('+j+',this.value)" placeholder="?p=club ou https://…"></label></div>';}
    h2+='</div>';lc.innerHTML=h2;}else lc.innerHTML='';
}
var wzDirty=false;
(function(){wzSync();wzDirty=false;var f=document.getElementById('blkform');
  if(f){f.addEventListener('submit',function(){wzSync();wzDirty=false;},true);}
  wzEl().addEventListener('input',function(){wzDirty=true;});
  window.addEventListener('beforeunload',function(ev){if(wzDirty){ev.preventDefault();ev.returnValue='';}});
})();
</script>
JS;
        return;
    }

    /* ============ Vue : zones + blocs de la page ============ */
    $zones = sitepage_zones($content);
    page_title('Page : ' . h((string)$pg['title']), 'Choisis où agir : « Modifier » un bloc, le déplacer, ou « Ajouter ici » un contenu à un emplacement précis. La présentation du site est conservée.');
    echo '<p class="sm-crumb">' . $crumb . '</p>';

    // Barre d'état + actions
    $pill = $status === 'published' ? '<span class="pill on">Publiée</span>' : '<span class="pill off">Dépubliée</span>';
    if ($hasDraft) $pill .= ' <span class="pill draft">brouillon en attente</span>';
    echo '<div class="loc"><div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">' . $pill
       . '<a class="sm-btn ghost" href="?p=sitemgr&sec=pagesnav">← Pages et navigation</a>'
       . '<a class="sm-btn ghost" href="/v2/?p=' . h($edit) . '" target="_blank">Voir la page ↗</a>'
       . '<a class="sm-btn ghost" href="/v2/?p=' . h($edit) . '&preview=1" target="_blank">👁️ Prévisualiser ↗</a>';
    if ($hasDraft) {
        echo '<form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="sitepage_save"><input type="hidden" name="slug" value="' . h($edit) . '"><input type="hidden" name="title" value="' . h((string)$pg['title']) . '"><input type="hidden" name="mode" value="publish_draft">' . csrf_field() . '<button class="sm-btn primary">✅ Publier le brouillon</button></form>';
        echo '<form method="post" class="js" data-reload style="display:inline" onsubmit="return confirm(\'Annuler le brouillon et revenir à la version publiée ?\')"><input type="hidden" name="action" value="sitepage_save"><input type="hidden" name="slug" value="' . h($edit) . '"><input type="hidden" name="title" value="' . h((string)$pg['title']) . '"><input type="hidden" name="mode" value="discard_draft">' . csrf_field() . '<button class="sm-btn danger">↩️ Annuler le brouillon</button></form>';
    }
    echo '</div></div>';
    if (!empty($pg['missing_note'])) echo '<div class="card" style="border-color:#e3a008"><b>Images d\'origine indisponibles</b> à remplacer dans les blocs concernés :<div class="sub" style="white-space:pre-wrap;margin-top:4px">' . h((string)$pg['missing_note']) . '</div></div>';

    $tpls = ['illustre' => 'Bloc illustré', 'annonce' => 'Annonce', 'aide' => 'Aide', 'liste' => 'Liste'];
    $tplSel = '<select name="tpl">'; foreach ($tpls as $tk => $tl) $tplSel .= '<option value="' . $tk . '">' . h($tl) . '</option>'; $tplSel .= '</select>';

    echo '<div class="card"><h3>🗂️ Emplacements de la page</h3><div class="sub">Les colonnes correspondent au vrai modèle de cette page. Déplacer ou ajouter met à jour un brouillon : prévisualise puis publie.</div>';
    if (!$zones) echo '<p class="sub">Aucune zone détectée (format particulier).</p>';
    echo '<div class="zcols">';
    foreach ($zones as $z) {
        $zi = (int)$z['zidx'];
        echo '<div class="zcol"><div class="zhead"><span>' . h($z['label']) . '</span>' . ($z['width'] ? '<span class="zw">' . (int)$z['width'] . ' px</span>' : '') . '</div>';
        // Ajouter en tête
        echo sitemgr_addhere($edit, $zi, 0, $tplSel);
        $pos = 0;
        foreach ($z['blocks'] as $bk) {
            $bidx = (int)$bk['bidx'];
            $name = $bk['title'] !== '' ? $bk['title'] : ('Bloc n°' . ($bidx + 1));
            $prev = mb_strimwidth(trim(preg_replace('/\s+/', ' ', strip_tags($blocks[$bidx]['body'] ?? ''))), 0, 70, '…');
            echo '<div class="blk"><h4>' . h($name) . '</h4><div class="prev">' . h($prev ?: '(bloc sans texte)') . ' · <span style="opacity:.7">Position ' . ($pos + 1) . '</span></div><div class="brow">';
            echo '<a class="sm-btn sq" href="?p=sitemgr&edit=' . h($edit) . '&block=' . $bidx . '">✏️ Modifier</a>';
            echo sitemgr_oprow($edit, $bidx, 'up', '↑', 'Monter');
            echo sitemgr_oprow($edit, $bidx, 'down', '↓', 'Descendre');
            if (count($zones) > 1) {
                echo '<form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="sitepage_arrange"><input type="hidden" name="slug" value="' . h($edit) . '"><input type="hidden" name="op" value="tozone"><input type="hidden" name="block" value="' . $bidx . '">' . csrf_field();
                echo '<select name="zone" onchange="this.form.requestSubmit?this.form.requestSubmit():this.form.submit()" style="width:auto"><option value="">Déplacer vers…</option>';
                foreach ($zones as $zz) if ((int)$zz['zidx'] !== $zi) echo '<option value="' . (int)$zz['zidx'] . '">' . h($zz['label']) . '</option>';
                echo '</select></form>';
            }
            echo '</div></div>';
            $pos++;
            echo sitemgr_addhere($edit, $zi, $pos, $tplSel);
        }
        echo '</div>';
    }
    echo '</div></div>';

    // Paramètres de la page
    echo '<form method="post" class="js" data-reload><div class="card"><h3>⚙️ Paramètres de la page</h3>' . csrf_field()
       . '<input type="hidden" name="action" value="sitepage_save"><input type="hidden" name="slug" value="' . h($edit) . '"><input type="hidden" name="mode" id="pgmeta" value="meta">';
    echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end"><label style="flex:1;min-width:220px">Titre de la page<input name="title" value="' . h((string)$pg['title']) . '"></label>'
       . '<label style="white-space:nowrap"><input type="checkbox" name="is_historical" ' . (((int)$pg['is_historical']) ? 'checked' : '') . '> Contenu d\'archive</label></div>';
    echo '<div class="sm-actions"><button class="sm-btn primary" type="submit" onclick="document.getElementById(\'pgmeta\').value=\'meta\'">💾 Enregistrer les infos</button>';
    if ($status === 'published') echo '<button class="sm-btn danger" type="submit" onclick="document.getElementById(\'pgmeta\').value=\'unpublish\';return confirm(\'Dépublier la page ? Elle ne sera plus visible sur le site (contenu conservé).\')" style="margin-left:auto">🚫 Dépublier la page</button>';
    else echo '<button class="sm-btn primary" type="submit" onclick="document.getElementById(\'pgmeta\').value=\'republish\'" style="margin-left:auto">✅ Republier la page</button>';
    echo '</div></div></form>';
    // Épingler / retirer des favoris (accueil Gestion du Site)
    $isPin = (int)($pg['pinned'] ?? 0) === 1;
    echo '<form method="post" class="js" data-reload style="margin-top:-6px"><input type="hidden" name="action" value="page_pin"><input type="hidden" name="slug" value="' . h($edit) . '"><input type="hidden" name="val" value="' . ($isPin ? 0 : 1) . '">' . csrf_field()
       . '<button class="sm-btn">' . ($isPin ? '📌 Retirer des favoris' : '📌 Épingler aux favoris') . '</button></form>';
}
function sitemgr_oprow(string $slug, int $bidx, string $op, string $sym, string $title): string {
    return '<form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="sitepage_arrange"><input type="hidden" name="slug" value="' . h($slug) . '"><input type="hidden" name="op" value="' . $op . '"><input type="hidden" name="block" value="' . $bidx . '">' . csrf_field() . '<button class="sm-btn sq" title="' . h($title) . '">' . $sym . '</button></form>';
}
function sitemgr_addhere(string $slug, int $zone, int $pos, string $tplSel): string {
    $id = 'add_' . $zone . '_' . $pos;
    $h = '<details class="addwrap" style="margin:0 0 8px"><summary class="addhere">+ Ajouter ici</summary>';
    $h .= '<form method="post" class="js" data-reload style="display:flex;gap:6px;align-items:center;margin:6px 0 10px">' . csrf_field()
        . '<input type="hidden" name="action" value="sitepage_arrange"><input type="hidden" name="slug" value="' . h($slug) . '"><input type="hidden" name="op" value="add"><input type="hidden" name="zone" value="' . $zone . '"><input type="hidden" name="pos" value="' . $pos . '">'
        . $tplSel . '<button class="sm-btn">Ajouter</button></form></details>';
    return $h;
}
/* ===== Édition bloc par bloc : découpage / réinjection des blocs .v3box ===== */
function sp_loadfrag(string $html): array {
    $doc = new DOMDocument(); libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    return [$doc, new DOMXPath($doc)];
}
function sp_blocknodes(DOMXPath $xp): DOMNodeList {
    return $xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," v3box ")]');
}
function sp_blockparts(DOMXPath $xp, DOMNode $n): array {
    $h3 = $xp->query('.//*[contains(@class,"v3box-top")]//h3', $n)->item(0) ?: $xp->query('.//h3', $n)->item(0);
    $body = $xp->query('.//*[contains(@class,"v3box-body")]', $n)->item(0) ?: $xp->query('.//*[contains(@class,"v3box-content")]', $n)->item(0);
    return [$h3, $body];
}
/** Liste des blocs d'une page : [ ['idx','title','body'(html)] ... ]. */
function sitepage_blocks(string $html): array {
    if (trim($html) === '') return [];
    [$doc, $xp] = sp_loadfrag($html);
    $out = []; $i = 0;
    foreach (sp_blocknodes($xp) as $n) {
        [$h3, $body] = sp_blockparts($xp, $n);
        $bh = '';
        if ($body) foreach ($body->childNodes as $c) $bh .= $doc->saveHTML($c);
        $out[] = ['idx' => $i, 'title' => $h3 ? trim($h3->textContent) : '', 'body' => trim($bh)];
        $i++;
    }
    return $out;
}
/** Réinjecte titre + corps dans le bloc N et renvoie le HTML complet de la page. */
function sitepage_block_apply(string $html, int $idx, string $title, string $body): string {
    [$doc, $xp] = sp_loadfrag($html);
    $nodes = sp_blocknodes($xp);
    if ($idx < 0 || $idx >= $nodes->length) return $html;
    $n = $nodes->item($idx);
    [$h3, $bodyEl] = sp_blockparts($xp, $n);
    if ($h3) { while ($h3->firstChild) $h3->removeChild($h3->firstChild); $h3->appendChild($doc->createTextNode($title)); }
    if ($bodyEl) {
        while ($bodyEl->firstChild) $bodyEl->removeChild($bodyEl->firstChild);
        [$tmp, $txp] = sp_loadfrag($body);
        $src = $tmp->getElementById('__r');
        if ($src) foreach ($src->childNodes as $c) $bodyEl->appendChild($doc->importNode($c, true));
    }
    $root = $doc->getElementById('__r'); $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}

/* ===== Zones (colonnes) d'une page + déplacement / ajout de blocs ===== */
function sp_isv3box(DOMNode $n): bool {
    return $n->nodeType === XML_ELEMENT_NODE && strpos(' ' . preg_replace('/\s+/', ' ', (string)$n->getAttribute('class')) . ' ', ' v3box ') !== false;
}
function sp_cols(DOMXPath $xp): array {
    return iterator_to_array($xp->query('//td[contains(concat(" ",normalize-space(@class)," ")," habboPage-col ")]'));
}
function sp_output(DOMDocument $doc): string {
    $root = $doc->getElementById('__r'); $out = '';
    if ($root) foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}
function sp_zone_label(int $i, int $n): string {
    if ($n <= 1) return 'Pleine largeur';
    if ($i === 0) return 'Colonne de gauche';
    if ($i === $n - 1) return 'Colonne de droite';
    return 'Colonne centrale' . ($n > 3 ? ' ' . $i : '');
}
/** Zones d'une page : chaque colonne réelle (td.habboPage-col) avec ses blocs (index global .v3box). */
function sitepage_zones(string $html): array {
    if (trim($html) === '') return [];
    [$doc, $xp] = sp_loadfrag($html);
    $allv = iterator_to_array(sp_blocknodes($xp));
    $gidx = function ($node) use ($allv) { foreach ($allv as $i => $n) if ($n === $node) return $i; return -1; };
    $cols = sp_cols($xp);
    if (!$cols) {
        $blocks = [];
        foreach ($allv as $i => $n) { [$h3] = sp_blockparts($xp, $n); $blocks[] = ['bidx' => $i, 'title' => $h3 ? trim($h3->textContent) : '']; }
        return [['zidx' => 0, 'label' => 'Pleine largeur', 'width' => 0, 'blocks' => $blocks]];
    }
    $zones = []; $zi = 0; $cnt = count($cols);
    foreach ($cols as $c) {
        $w = 0; if (preg_match('/width:\s*(\d+)/', $c->getAttribute('style'), $m)) $w = (int)$m[1];
        $blocks = [];
        foreach ($xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," v3box ")]', $c) as $n) {
            [$h3] = sp_blockparts($xp, $n);
            $blocks[] = ['bidx' => $gidx($n), 'title' => $h3 ? trim($h3->textContent) : ''];
        }
        $zones[] = ['zidx' => $zi, 'label' => sp_zone_label($zi, $cnt), 'width' => $w, 'blocks' => $blocks];
        $zi++;
    }
    return $zones;
}
function sitepage_block_move(string $html, int $idx, string $dir): string {
    [$doc, $xp] = sp_loadfrag($html);
    $allv = iterator_to_array(sp_blocknodes($xp));
    if ($idx < 0 || $idx >= count($allv)) return $html;
    $node = $allv[$idx]; $parent = $node->parentNode;
    if ($dir === 'up') {
        $sib = $node->previousSibling;
        while ($sib && !sp_isv3box($sib)) $sib = $sib->previousSibling;
        if ($sib) $parent->insertBefore($node, $sib);
    } else {
        $sib = $node->nextSibling;
        while ($sib && !sp_isv3box($sib)) $sib = $sib->nextSibling;
        if ($sib) { $after = $sib->nextSibling; if ($after) $parent->insertBefore($node, $after); else $parent->appendChild($node); }
    }
    return sp_output($doc);
}
function sitepage_block_tozone(string $html, int $idx, int $zidx): string {
    [$doc, $xp] = sp_loadfrag($html);
    $allv = iterator_to_array(sp_blocknodes($xp)); $cols = sp_cols($xp);
    if ($idx < 0 || $idx >= count($allv) || $zidx < 0 || $zidx >= count($cols)) return $html;
    $cols[$zidx]->appendChild($allv[$idx]);
    return sp_output($doc);
}
function sitepage_block_add(string $html, int $zidx, string $tpl, int $pos = -1): string {
    [$doc, $xp] = sp_loadfrag($html);
    $cols = sp_cols($xp);
    $color = ['illustre' => 'blue', 'annonce' => 'orange', 'aide' => 'green', 'liste' => 'yellow'][$tpl] ?? 'blue';
    $title = ['illustre' => 'Nouveau bloc', 'annonce' => 'Annonce', 'aide' => 'Aide', 'liste' => 'Liste'][$tpl] ?? 'Nouveau bloc';
    $frag = '<div class="v3box ' . $color . '"><div class="v3box-top"><h3>' . $title . '</h3></div><div class="v3box-content"><div class="v3box-body"><p>Nouveau texte à modifier.</p></div></div><div class="v3box-bottom"><div></div></div></div>';
    [$tmp] = sp_loadfrag($frag); $srcRoot = $tmp->getElementById('__r');
    if (!$srcRoot || !$srcRoot->firstChild) return $html;
    $newNode = $doc->importNode($srcRoot->firstChild, true);
    if (!$cols) { $doc->getElementById('__r')->appendChild($newNode); return sp_output($doc); }
    $zone = $cols[max(0, min($zidx, count($cols) - 1))];
    $zblocks = []; foreach ($zone->childNodes as $cc) if (sp_isv3box($cc)) $zblocks[] = $cc;
    if ($pos >= 0 && $pos < count($zblocks)) $zone->insertBefore($newNode, $zblocks[$pos]);
    else $zone->appendChild($newNode);
    return sp_output($doc);
}

/* ---- Vue d'ensemble : brouillons en attente + galerie des contenus ---- */
function sp_sec_emoji(string $k): string {
    static $m = ['home' => '🏠', 'register' => '🏨', 'community' => '👥', 'events' => '🎉', 'games' => '🎮', 'shop' => '🛍️', 'mobile' => '📱', 'credits' => '💰', 'club' => '⭐', 'help' => '❓'];
    return $m[$k] ?? '📄';
}
/** Routes internes valides (?p=slug) pour le vérificateur de liens. */
function sp_valid_routes(): array {
    static $v = null;
    if ($v === null) {
        $v = ['home' => 1, 'register' => 1, 'community' => 1, 'events' => 1, 'games' => 1, 'shop' => 1, 'mobile' => 1, 'credits' => 1, 'club' => 1, 'help' => 1, 'me' => 1, 'apropos' => 1];
        foreach (db()->query('SELECT slug FROM site_pages') as $r) $v[$r['slug']] = 1;
    }
    return $v;
}
/** Liens internes invalides dans les pages publiées : [ [slug, href] ... ]. */
function sp_link_issues(int $limit = 20): array {
    $valid = sp_valid_routes(); $out = [];
    foreach (db()->query('SELECT slug,title,body_html FROM site_pages') as $r) {
        if (!preg_match_all('/href="\?p=([^"&#]+)/i', (string)$r['body_html'], $mm)) continue;
        foreach (array_unique($mm[1]) as $route) {
            $base = explode('/', $route)[0];
            if ($base === 'home') continue; // ?p=home/<pseudo> dynamique
            if (!isset($valid[$route]) && !isset($valid[$base])) { $out[] = [$r['slug'], $r['title'], '?p=' . $route]; if (count($out) >= $limit) return $out; }
        }
    }
    return $out;
}
function sitemgr_overview(): void {
    ensure_site_news_admin();
    $rows = db()->query('SELECT slug,parent_tab,title,status,is_historical,body_draft,updated_at,pinned FROM site_pages ORDER BY updated_at DESC')->fetchAll();
    $statpill = function ($r) {
        $hasDr = $r['body_draft'] !== null && trim((string)$r['body_draft']) !== '';
        if ($hasDr) return '<span class="pill draft">modifs non publiées</span>';
        return ($r['status'] ?? 'published') === 'published' ? '<span class="pill on">Publiée</span>' : '<span class="pill off">Dépubliée</span>';
    };

    echo '<div class="twocol">';
    // ---- Colonne gauche : favoris + derniers modifiés ----
    echo '<div>';
    $fav = array_values(array_filter($rows, fn($r) => (int)$r['pinned'] === 1));
    echo '<div class="card"><h3>📌 Pages favorites</h3>';
    if (!$fav) echo '<div class="sub">Aucune page épinglée. Depuis « Pages et navigation », clique 📌 sur une page pour l\'ajouter ici.</div>';
    else { echo '<table class="lst">'; foreach ($fav as $r) echo '<tr><td><a href="?p=sitemgr&edit=' . h((string)$r['slug']) . '">' . h((string)$r['title']) . '</a> <span class="sub">· ' . h(sp_tablabel((string)$r['parent_tab'])) . '</span></td><td style="text-align:right">' . $statpill($r) . '</td></tr>'; echo '</table>'; }
    echo '</div>';

    echo '<div class="card"><h3>🕑 5 derniers contenus modifiés</h3><table class="lst">';
    foreach (array_slice($rows, 0, 5) as $r) echo '<tr><td><a href="?p=sitemgr&edit=' . h((string)$r['slug']) . '">' . h((string)$r['title']) . '</a> <span class="sub">· ' . h(sp_tablabel((string)$r['parent_tab'])) . '</span></td><td class="sub" style="text-align:right;white-space:nowrap">' . h(substr((string)$r['updated_at'], 0, 10)) . '</td></tr>';
    echo '</table></div>';
    echo '</div>';

    // ---- Colonne droite : brouillons + à vérifier ----
    echo '<div>';
    $drafts = array_values(array_filter($rows, fn($r) => $r['body_draft'] !== null && trim((string)$r['body_draft']) !== ''));
    echo '<div class="card"><h3>📝 Brouillons en attente</h3>';
    if (!$drafts) echo '<div class="sub">Aucun brouillon. Tes pages publiées sont à jour.</div>';
    else { echo '<table class="lst">'; foreach ($drafts as $r) echo '<tr><td><a href="?p=sitemgr&edit=' . h((string)$r['slug']) . '">' . h((string)$r['title']) . '</a></td><td style="text-align:right"><a class="sm-btn sq" href="?p=sitemgr&edit=' . h((string)$r['slug']) . '">Publier…</a></td></tr>'; echo '</table>'; }
    echo '</div>';

    $missing = array_values(array_filter(db()->query('SELECT slug,title,missing_note FROM site_pages')->fetchAll(), fn($r) => $r['missing_note'] !== null && trim((string)$r['missing_note']) !== ''));
    $links = sp_link_issues(50);
    echo '<div class="card"><h3>🔎 À vérifier</h3>';
    // Images manquantes : compteur + détails repliables
    echo '<div class="chkline"><b>' . count($missing) . '</b> page(s) avec une image d\'origine manquante';
    if ($missing) {
        echo ' <details style="display:inline-block"><summary class="sm-btn sq" style="list-style:none;display:inline-block">Voir les détails</summary><div class="sub" style="margin-top:6px">';
        foreach ($missing as $r) echo '<div><a href="?p=sitemgr&edit=' . h((string)$r['slug']) . '">' . h((string)$r['title']) . '</a></div>';
        echo '</div></details>';
    }
    echo '</div>';
    // Liens invalides : compteur + détails repliables
    echo '<div class="chkline" style="margin-top:10px"><b>' . count($links) . '</b> lien(s) interne(s) à vérifier';
    if ($links) {
        echo ' <details style="display:inline-block"><summary class="sm-btn sq" style="list-style:none;display:inline-block">Voir les détails</summary><div class="sub" style="margin-top:6px">';
        foreach ($links as $l) echo '<div><a href="?p=sitemgr&edit=' . h((string)$l[0]) . '">' . h((string)$l[1]) . '</a> → <code>' . h((string)$l[2]) . '</code></div>';
        echo '</div></details>';
    }
    echo '</div>';
    if (!$missing && !$links) echo '<div class="sub">Rien à signaler ✨</div>';
    echo '</div>';
    echo '</div>'; // right col
    echo '</div>'; // twocol
}
/* ---- Tous les contenus : liste unifiée pages + actualités (inventaire complet) ---- */
function sitemgr_allcontent(string $forceType = ''): void {
    ensure_site_pages_admin(); ensure_site_nav_admin(); ensure_site_news_admin(); ensure_carousel_admin();
    // Emplacements : routes du menu + liens du carrousel
    $navRoutes = [];
    foreach (db()->query('SELECT route FROM site_nav_items WHERE visible=1 AND archived=0') as $r)
        if (preg_match('#\?p=([a-z0-9_]+)#', (string)$r['route'], $m)) $navRoutes[$m[1]][] = 1;
    $carLinks = '';
    foreach (db()->query('SELECT link FROM site_carousel WHERE active=1') as $r) $carLinks .= ' ' . (string)$r['link'];

    $items = [];
    // Pages / articles
    foreach (db()->query('SELECT slug,parent_tab,title,status,is_historical,body_draft,updated_at FROM site_pages') as $r) {
        $slug = (string)$r['slug'];
        $inMenu = isset($navRoutes[$slug]);
        $inCar  = strpos($carLinks, '?p=' . $slug) !== false;
        $emp = [];
        $emp[] = $inMenu ? 'Menu' : 'Hors menu';
        $emp[] = 'Page complète';
        if ($inCar) $emp[] = 'Carrousel';
        $hasDr = $r['body_draft'] !== null && trim((string)$r['body_draft']) !== '';
        $items[] = [
            'type' => 'page', 'typeLbl' => (int)$r['is_historical'] ? 'Article (archive)' : 'Article/Page',
            'title' => (string)$r['title'], 'tab' => (string)$r['parent_tab'], 'rub' => sp_tablabel((string)$r['parent_tab']),
            'emp' => implode(' · ', $emp),
            'status' => $hasDr ? 'draft_pending' : (string)($r['status'] ?? 'published'),
            'date' => substr((string)$r['updated_at'], 0, 10),
            'edit' => '?p=sitemgr&edit=' . h($slug), 'see' => '/v2/?p=' . h($slug),
        ];
    }
    // Actualités
    foreach (db()->query('SELECT id,title,category,status,created_at FROM site_news') as $r) {
        $id = (int)$r['id'];
        $inCar = strpos($carLinks, '?p=actu&id=' . $id) !== false || strpos($carLinks, '?p=actu&amp;id=' . $id) !== false;
        $emp = ['Liste des actus (Quoi de neuf ?)']; if ($inCar) $emp[] = 'Carrousel';
        $items[] = [
            'type' => 'news', 'typeLbl' => 'Actualité',
            'title' => (string)$r['title'], 'tab' => 'news', 'rub' => (string)($r['category'] ?: 'Actualités'),
            'emp' => implode(' · ', $emp),
            'status' => (string)($r['status'] ?? 'published'),
            'date' => substr((string)$r['created_at'], 0, 10),
            'edit' => '?p=sitemgr&sec=news&newsedit=' . $id, 'see' => '/v2/?p=actu&id=' . $id,
        ];
    }
    usort($items, fn($a, $b) => strcmp($b['date'], $a['date']));

    $statusLbl = ['published' => '<span class="pill on">Publié</span>', 'draft' => '<span class="pill draft">Brouillon</span>', 'draft_pending' => '<span class="pill draft">Modifs non publiées</span>'];
    $tabs = []; foreach ($items as $it) if ($it['type'] === 'page') $tabs[$it['tab']] = $it['rub'];

    page_title('Tous les contenus', 'Tout ce qui est écrit sur le site — actualités, articles et pages — au même endroit. Filtre, puis clique « Modifier ».');
    echo '<div class="sm-actions" style="margin-bottom:12px"><a class="sm-btn primary" href="?p=sitemgr&sec=news&new=1">📰 Créer une actualité</a>'
       . '<details style="display:inline-block"><summary class="sm-btn" style="list-style:none">📄 Créer un article</summary>'
       . '<form method="post" style="display:flex;gap:6px;align-items:flex-end;margin-top:8px;flex-wrap:wrap">' . csrf_field() . '<input type="hidden" name="action" value="site_page_create">'
       . '<label>Titre<input name="title" required></label><label>Rubrique<select name="parent_tab">';
    foreach (['home', 'register', 'community', 'events', 'games', 'shop', 'mobile', 'credits', 'club', 'help'] as $tk) echo '<option value="' . $tk . '">' . h(sp_tablabel($tk)) . '</option>';
    echo '</select></label><button class="sm-btn primary">Créer</button></form></details></div>';

    // Filtres
    echo '<div class="card"><div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">';
    echo '<label style="flex:1;min-width:200px">Rechercher<input type="text" id="acq" placeholder="Titre…" oninput="acFilter()"></label>';
    echo '<label>Type<select id="acty" onchange="acFilter()"><option value="">Tous</option><option value="news"' . ($forceType === 'news' ? ' selected' : '') . '>Actualités</option><option value="page"' . ($forceType === 'page' ? ' selected' : '') . '>Articles et pages</option></select></label>';
    echo '<label>Rubrique<select id="acrub" onchange="acFilter()"><option value="">Toutes</option>';
    foreach ($tabs as $tk => $tl) echo '<option value="' . h($tk) . '">' . h($tl) . '</option>';
    echo '<option value="news">Actualités</option></select></label>';
    echo '<label>Statut<select id="acst" onchange="acFilter()"><option value="">Tous</option><option value="published">Publié</option><option value="draft">Brouillon</option><option value="draft_pending">Modifs non publiées</option></select></label>';
    echo '</div></div>';

    echo '<div class="card" style="padding-top:6px"><table class="lst" id="actable"><tr class="sub"><td>Titre</td><td>Type</td><td>Rubrique</td><td>Emplacements</td><td>Statut</td><td>Modifié</td><td style="text-align:right">Actions</td></tr>';
    foreach ($items as $it) {
        echo '<tr class="acrow" data-ty="' . h($it['type']) . '" data-tab="' . h($it['tab']) . '" data-st="' . h($it['status']) . '" data-h="' . h(mb_strtolower($it['title'] . ' ' . $it['rub'])) . '">'
           . '<td><b>' . h($it['title']) . '</b></td>'
           . '<td class="sub">' . h($it['typeLbl']) . '</td>'
           . '<td class="sub">' . h($it['rub']) . '</td>'
           . '<td class="sub">' . h($it['emp']) . '</td>'
           . '<td>' . ($statusLbl[$it['status']] ?? h($it['status'])) . '</td>'
           . '<td class="sub" style="white-space:nowrap">' . h($it['date']) . '</td>'
           . '<td style="text-align:right;white-space:nowrap"><a class="sm-btn sq" href="' . $it['edit'] . '">Modifier</a> <a class="sm-btn sq" href="' . $it['see'] . '" target="_blank">Voir ↗</a></td></tr>';
    }
    echo '</table><div class="cnores" id="acnores" style="display:none">Aucun contenu ne correspond aux filtres.</div></div>';
    $jsForce = $forceType !== '' ? $forceType : '';
    echo '<script>function acFilter(){var q=(document.getElementById("acq").value||"").toLowerCase().trim(),ty=document.getElementById("acty").value,rb=document.getElementById("acrub").value,st=document.getElementById("acst").value,n=0;document.querySelectorAll("#actable .acrow").forEach(function(r){var ok=(!q||r.getAttribute("data-h").indexOf(q)>-1)&&(!ty||r.getAttribute("data-ty")===ty)&&(!rb||r.getAttribute("data-tab")===rb)&&(!st||r.getAttribute("data-st")===st);r.style.display=ok?"":"none";if(ok)n++;});document.getElementById("acnores").style.display=n?"none":"block";}' . ($jsForce ? 'acFilter();' : '') . '</script>';
}

/* ---- Accueil du site : blocs + carrousel réunis (point 2) ---- */
function sitemgr_accueil(): void {
    page_title('Page : Accueil', 'Tous les éléments de la page d\'accueil au même endroit : son carrousel et ses blocs. La présentation du site est conservée.');
    echo '<p class="sm-crumb">Gestion du Site › Pages du site › <b>Accueil</b></p>';
    echo '<p style="margin:0 0 10px"><a class="sm-btn ghost" href="?p=sitemgr&sec=pagesnav">← Pages et navigation</a> <a class="sm-btn ghost" href="/v2/?p=home" target="_blank">Voir l\'accueil ↗</a></p>';
    echo '<div class="card"><h3>🎠 Carrousel « À ne pas manquer »</h3><div class="sub">Les 4 diapositives affichées en haut de l\'accueil.</div>';
    sitemgr_carousel_inner();
    echo '</div>';
    echo '<div class="card"><h3>🧱 Blocs de l\'accueil</h3><div class="sub">Textes des encadrés de l\'accueil. Les compteurs (joueurs en ligne, classements) et « Quoi de neuf ? » viennent automatiquement de la base.</div>';
    sitemgr_blocs_inner();
    echo '</div>';
    echo '<div class="card"><h3>📰 « Quoi de neuf ? »</h3><div class="sub">Alimenté par les <a href="?p=sitemgr&sec=news">actualités publiées</a>. <a href="?p=sitemgr&sec=news&new=1">Créer une actualité →</a></div></div>';
}

/* ---- Pages et navigation : arborescence éditable (point 3) ---- */
function sitemgr_pagesnav(): void {
    $tabs = db()->query('SELECT * FROM site_nav_tabs ORDER BY ord,tab_key')->fetchAll();
    $items = db()->query('SELECT * FROM site_nav_items ORDER BY tab_key,ord,id')->fetchAll();
    $byTab = []; foreach ($items as $it) $byTab[$it['tab_key']][] = $it;
    $pages = db()->query('SELECT slug,title FROM site_pages ORDER BY title')->fetchAll();
    $icons = glob(dirname(__DIR__) . '/web-gallery/v2/images/navi/tab_icon_*.gif') ?: [];
    $icons = array_map('basename', $icons);
    $sel = (string)($_GET['sel'] ?? '');

    echo '<div class="card" style="margin-bottom:12px"><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><b>Page d\'accueil</b><span class="sub" style="flex:1">Son carrousel et ses blocs sont réunis sur une seule page.</span><a class="sm-btn primary" href="?p=sitemgr&sec=accueil">🏠 Modifier l\'accueil</a> <a class="sm-btn" href="?p=sitemgr&sec=allcontent">🗂️ Voir tous les contenus</a></div></div>';
    echo '<div class="warn" style="margin:0 0 10px">La barre jaune et le fil d\'Ariane du site se construisent automatiquement depuis ces données. <b>Masquer</b> une entrée la retire de la navigation <u>sans dépublier</u> sa page. Les pages hors menu restent accessibles dans « Tous les contenus ».</div>';
    echo '<div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">';

    /* ---- Colonne gauche : arbre ---- */
    echo '<div style="flex:1;min-width:320px">';
    foreach ($tabs as $t) {
        $k = (string)$t['tab_key'];
        $dim = ((int)$t['visible'] ? '' : 'opacity:.5;');
        echo '<div class="panel" style="margin-bottom:8px;' . $dim . '"><div class="ph" style="gap:6px">'
           . '<img src="/web-gallery/v2/images/navi/' . h((string)$t['icon']) . '" alt="" style="width:18px;height:18px;vertical-align:middle"> '
           . '<b>' . h((string)$t['label']) . '</b> <span class="muted sm">(' . h($k) . ' · section « ' . h((string)$t['section_label']) . ' »)</span>'
           . '<span style="margin-left:auto;display:flex;gap:3px">'
           . sitemgr_navbtn('nav_move', ['kind' => 'tab', 'key' => $k, 'dir' => 'up'], '↑')
           . sitemgr_navbtn('nav_move', ['kind' => 'tab', 'key' => $k, 'dir' => 'down'], '↓')
           . sitemgr_navbtn('nav_flag', ['kind' => 'tab', 'key' => $k, 'field' => 'visible', 'val' => (int)!$t['visible']], (int)$t['visible'] ? '👁️' : '🚫')
           . '<a class="chip" href="?p=sitemgr&sec=pagesnav&sel=tab-' . h($k) . '" style="padding:2px 7px;border:1px solid var(--line2);border-radius:5px;text-decoration:none">✏️</a>'
           . '</span></div>';
        echo '<div style="padding:2px 0">';
        foreach ($byTab[$k] ?? [] as $it) {
            if ((int)$it['archived']) continue;
            $idim = ((int)$it['visible'] ? '' : 'opacity:.5;');
            $routeTxt = $it['route'] !== '' ? h((string)$it['route']) : '<span class="muted">(section)</span>';
            echo '<div class="row" style="align-items:center;gap:6px;padding:3px 4px;border-top:1px dashed var(--line2);' . $idim . '">'
               . '<span style="flex:1">↳ <b>' . h((string)$it['label']) . '</b> <span class="muted sm">' . $routeTxt . '</span></span>'
               . '<span style="display:flex;gap:3px">'
               . sitemgr_navbtn('nav_move', ['kind' => 'item', 'id' => (int)$it['id'], 'dir' => 'up'], '↑')
               . sitemgr_navbtn('nav_move', ['kind' => 'item', 'id' => (int)$it['id'], 'dir' => 'down'], '↓')
               . sitemgr_navbtn('nav_flag', ['kind' => 'item', 'id' => (int)$it['id'], 'field' => 'visible', 'val' => (int)!$it['visible']], (int)$it['visible'] ? '👁️' : '🚫')
               . sitemgr_navbtn('nav_flag', ['kind' => 'item', 'id' => (int)$it['id'], 'field' => 'archived', 'val' => 1], '🗄️')
               . '<a class="chip" href="?p=sitemgr&sec=pagesnav&sel=item-' . (int)$it['id'] . '" style="padding:2px 7px;border:1px solid var(--line2);border-radius:5px;text-decoration:none">✏️</a>'
               . '</span></div>';
        }
        echo '<div style="padding:4px"><a href="?p=sitemgr&sec=pagesnav&sel=new-' . h($k) . '" class="muted sm">➕ Ajouter une entrée dans « ' . h((string)$t['label']) .' »</a></div>';
        echo '</div></div>';
    }
    echo '</div>';

    /* ---- Colonne droite : propriétés ---- */
    echo '<div style="flex:0 0 330px;max-width:100%">';
    if (strpos($sel, 'tab-') === 0) {
        $k = preg_replace('/[^a-z0-9_]/', '', substr($sel, 4));
        $t = null; foreach ($tabs as $x) if ($x['tab_key'] === $k) $t = $x;
        if ($t) {
            echo '<form method="post" class="js" data-reload><div class="panel"><div class="ph"><h3>Onglet : ' . h($k) . '</h3></div>' . csrf_field()
               . '<input type="hidden" name="action" value="nav_tab_save"><input type="hidden" name="tab_key" value="' . h($k) . '">'
               . '<label style="display:block;margin-bottom:6px">Nom affiché<input name="label" value="' . h((string)$t['label']) . '"></label>'
               . '<label style="display:block;margin-bottom:6px">Libellé de section (fil d\'Ariane / 1er mot barre jaune)<input name="section_label" value="' . h((string)$t['section_label']) . '"></label>'
               . '<label style="display:block;margin-bottom:6px">Icône' . sitemgr_icon_select((string)$t['icon'], $icons) . '</label>'
               . '<label style="display:block;margin-bottom:6px"><input type="checkbox" name="visible" ' . ((int)$t['visible'] ? 'checked' : '') . '> Visible dans la navigation</label>'
               . '<button>💾 Enregistrer l\'onglet</button></div></form>';
        }
    } elseif (strpos($sel, 'item-') === 0) {
        $id = (int)substr($sel, 5); $it = null; foreach ($items as $x) if ((int)$x['id'] === $id) $it = $x;
        if ($it) echo sitemgr_item_form($it, $tabs, $pages);
    } elseif (strpos($sel, 'new-') === 0) {
        $k = preg_replace('/[^a-z0-9_]/', '', substr($sel, 4));
        echo sitemgr_item_form(['id' => 0, 'tab_key' => $k, 'label' => '', 'route' => '', 'target' => '', 'visible' => 1], $tabs, $pages);
    } else {
        echo '<div class="panel"><p class="muted" style="padding:6px 0">Sélectionne un onglet ou une entrée (✏️) pour voir et modifier ses propriétés.</p></div>';
    }
    echo '</div></div>';
}
function sitemgr_navbtn(string $action, array $fields, string $label): string {
    $h = '<form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="' . h($action) . '">';
    foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . h((string)$k) . '" value="' . h((string)$v) . '">';
    return $h . '<button style="padding:2px 6px;font-size:12px" title="' . h($action) . '">' . $label . '</button></form>';
}
function sitemgr_icon_select(string $cur, array $icons): string {
    $h = '<select name="icon"><option value="">(aucune)</option>';
    foreach ($icons as $ic) $h .= '<option value="' . h($ic) . '"' . ($ic === $cur ? ' selected' : '') . '>' . h($ic) . '</option>';
    return $h . '</select>';
}
function sitemgr_item_form(array $it, array $tabs, array $pages): string {
    $h = '<form method="post" class="js" data-reload><div class="panel"><div class="ph"><h3>' . ((int)$it['id'] ? 'Entrée de sous-menu' : 'Nouvelle entrée') . '</h3></div>' . csrf_field()
       . '<input type="hidden" name="action" value="nav_item_save"><input type="hidden" name="id" value="' . (int)$it['id'] . '">';
    $h .= '<label style="display:block;margin-bottom:6px">Onglet parent<select name="tab_key">';
    foreach ($tabs as $t) $h .= '<option value="' . h((string)$t['tab_key']) . '"' . ($t['tab_key'] === $it['tab_key'] ? ' selected' : '') . '>' . h((string)$t['label']) . ' (' . h((string)$t['tab_key']) . ')</option>';
    $h .= '</select></label>';
    $h .= '<label style="display:block;margin-bottom:6px">Nom affiché<input name="label" value="' . h((string)$it['label']) . '"></label>';
    $h .= '<label style="display:block;margin-bottom:6px">Page de destination<input name="route" list="pageroutes" value="' . h((string)$it['route']) . '" placeholder="?p=welcome ou URL externe"></label>';
    $h .= '<datalist id="pageroutes">';
    foreach ($pages as $pg) $h .= '<option value="?p=' . h((string)$pg['slug']) . '">' . h((string)$pg['title']) . '</option>';
    foreach (['home', 'register', 'community', 'events', 'games', 'shop', 'mobile', 'credits', 'club', 'help'] as $tk) $h .= '<option value="?p=' . $tk . '">';
    $h .= '</datalist>';
    $h .= '<label style="display:block;margin-bottom:6px">Ouverture<select name="target"><option value=""' . ($it['target'] === '' ? ' selected' : '') . '>Même onglet</option><option value="_blank"' . ($it['target'] === '_blank' ? ' selected' : '') . '>Nouvel onglet</option></select></label>';
    $h .= '<button>💾 Enregistrer</button></div></form>';
    return $h;
}
/* ---- Carrousel (point 7) ---- */
function sitemgr_carousel(): void {
    $pages = db()->query('SELECT slug,title FROM site_pages ORDER BY title')->fetchAll();
    $cur = preg_replace('/[^a-z0-9_\-]/', '', (string)($_GET['cpage'] ?? 'home')); if ($cur === '') $cur = 'home';
    echo '<div class="card"><h3>🎠 Carrousels par page</h3><div class="sub">Chaque page a son propre carrousel (4 emplacements, indépendants). L\'accueil affiche le carrousel « home ».</div>';
    echo '<form method="get" style="margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="hidden" name="p" value="sitemgr"><input type="hidden" name="sec" value="carousel"><label><b>Page :</b></label><select name="cpage" onchange="this.form.submit()">';
    echo '<option value="home"' . ($cur === 'home' ? ' selected' : '') . '>Accueil (home)</option>';
    foreach ($pages as $pg) { $sl = (string)$pg['slug']; echo '<option value="' . h($sl) . '"' . ($cur === $sl ? ' selected' : '') . '>' . h((string)$pg['title']) . ' (' . h($sl) . ')</option>'; }
    echo '</select><span class="sub">Change de page pour éditer son carrousel.</span></form>';
    sitemgr_carousel_inner($cur);
    echo '</div>';
}
function sitemgr_carousel_inner(string $page = 'home'): void {
    $slots = []; $sst = db()->prepare('SELECT * FROM site_carousel WHERE page=?'); $sst->execute([$page]); foreach ($sst->fetchAll() as $r) $slots[(int)$r['slot']] = $r;
    $pages = db()->query('SELECT slug,title FROM site_pages ORDER BY title')->fetchAll();
    echo '<div class="sub" style="margin:4px 0 8px">Carrousel de la page <b>« ' . h($page) . ' »</b>. Chaque diapo a son image et son sujet. Image = chemin servi par Apache (ex. <code>/c_images/banners/425x178/…</code>). Lien = <code>?p=club</code> ou <code>/client.php</code>. Un emplacement désactivé est retiré côté site.</div>';
    for ($i = 1; $i <= 4; $i++) {
        $s = $slots[$i] ?? ['title' => '', 'image' => '', 'body' => '', 'link' => '', 'active' => 1];
        echo '<form method="post" class="js" data-reload style="border-top:1px solid var(--line2);padding:9px 0">' . csrf_field() . '<input type="hidden" name="action" value="carousel_save"><input type="hidden" name="page" value="' . h($page) . '"><input type="hidden" name="slot" value="' . $i . '">';
        echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><b>#' . $i . '</b>';
        if ((string)$s['image'] !== '') echo '<img src="' . h((string)$s['image']) . '" alt="" style="width:84px;height:42px;object-fit:cover;border:1px solid var(--line2);border-radius:5px">';
        echo '<label style="flex:1;min-width:160px">Titre<input name="title" value="' . h((string)$s['title']) . '"></label>'
           . '<label style="flex:1;min-width:160px">Lien<input name="link" list="pageroutesC" value="' . h((string)$s['link']) . '"></label>'
           . '<label style="white-space:nowrap">Actif <input type="checkbox" name="active" ' . (((int)$s['active']) ? 'checked' : '') . '></label></div>';
        echo '<div style="display:flex;gap:10px;margin-top:6px;flex-wrap:wrap"><label style="flex:2;min-width:200px">Image (chemin)<input name="image" value="' . h((string)$s['image']) . '"></label>'
           . '<label style="flex:3;min-width:220px">Texte<input name="body" value="' . h((string)$s['body']) . '" maxlength="400"></label>'
           . '<button class="sm-btn primary" style="align-self:flex-end">💾 Enregistrer</button></div></form>';
    }
    echo '<datalist id="pageroutesC">'; foreach ($pages as $pg) echo '<option value="?p=' . h((string)$pg['slug']) . '">' . h((string)$pg['title']) . '</option>'; echo '<option value="/client.php"></datalist>';
}
function sitemgr_blocs_inner(): void {
    // Chaque bloc de l'accueil est repéré par sa colonne (point 14). Défaut = texte d'origine du site.
    $groups = [
        'Colonne de gauche' => [
            ['site.home_besoin', 'Bloc « Besoin d\'aide ? »', "Un bug, un souci ? L'équipe de l'hôtel est là pour t'aider."],
            ['site.home_bienvenue', 'Bloc « Bienvenue à Habbo »', "Habbo est une communauté virtuelle où tu rencontres tes amis, participes à des jeux et décores ton propre appart. L'entrée est gratuite !"],
            ['site.home_securite', 'Bloc « La sécurité sur Habbo »', "Ne partage jamais ton mot de passe, même avec quelqu'un se présentant comme membre du staff."],
            ['site.safety_slogan', 'Bloc « Slogan sécu de la semaine »', 'Pour vérifier ton e-mail, clique sur le lien reçu — ne le communique à personne !'],
        ],
        'Colonne du centre' => [
            ['site.home_trax', 'Bloc « Trax »', "Le son de Habbo ! Compose tes propres mixes avec la Trax Machine directement en jeu."],
            ['site.home_club', 'Bloc « Habbo Club »', 'Mobis exclusifs, cadeaux et badge doré avec le Habbo Club !'],
            ['site.infobus', 'Bloc « L\'Infobus » (horaires)', 'Dans les Jardins Habbos ! Mercredi 16h30–17h30 et Vendredi 17h–18h.'],
        ],
        'Colonne de droite' => [
            ['site.home_homes', 'Bloc « Habbo Homes »', 'Construis ta page perso : avatar, badges, amis et salles sur ta Habbo Home.'],
            ['site.home_activation', 'Bloc « Activation de l\'adresse e-mail »', 'Vérifie ton adresse e-mail pour sécuriser ton compte et récupérer ton mot de passe.'],
        ],
        'Autres' => [
            ['site.games_prog', 'Programme des jeux (page Jeux)', 'Des tournois sont organisés régulièrement en jeu. Surveille les actualités !'],
        ],
    ];
    echo '<p class="sub" style="margin:2px 0 10px">Les compteurs (joueurs en ligne, classements), les clans et « Quoi de neuf ? » viennent automatiquement de la base — ils ne se modifient pas ici.</p>';
    foreach ($groups as $col => $items) {
        echo '<h4 style="margin:12px 0 6px;border-bottom:1px solid var(--line2);padding-bottom:3px">' . h($col) . '</h4>';
        foreach ($items as $cfg) {
            echo '<form method="post" class="js" data-reload style="margin-bottom:9px">' . csrf_field() . '<input type="hidden" name="action" value="site_setting"><input type="hidden" name="key" value="' . h($cfg[0]) . '">';
            echo '<label style="display:block;font-weight:600;margin-bottom:3px">' . h($cfg[1]) . '</label>';
            echo '<div style="display:flex;gap:8px;align-items:flex-start"><textarea name="value" maxlength="400" rows="2" style="flex:1;resize:vertical;font:inherit">' . h(site_setting_get($cfg[0], $cfg[2])) . '</textarea><button class="sm-btn primary" style="align-self:stretch">💾</button></div></form>';
        }
    }
}
function sitemgr_news(): void {
    ensure_site_news_admin();
    $new = isset($_GET['new']);
    $editId = (int)($_GET['newsedit'] ?? 0);
    // ---- Formulaire création / édition ----
    if ($new || $editId > 0) {
        $n = ['id' => 0, 'title' => '', 'summary' => '', 'body' => '', 'image' => '', 'category' => 'À la une', 'status' => 'published', 'created_at' => date('Y-m-d\TH:i')];
        if ($editId > 0) { $st = db()->prepare('SELECT * FROM site_news WHERE id=?'); $st->execute([$editId]); $r = $st->fetch(); if ($r) { $n = $r; $n['created_at'] = date('Y-m-d\TH:i', strtotime((string)$r['created_at'])); } }
        $mdir = dirname(__DIR__) . '/web-gallery/v2/pages_img';
        $media = is_dir($mdir) ? array_values(array_filter(scandir($mdir), fn($f) => !in_array($f, ['.', '..'], true) && is_file($mdir . '/' . $f))) : [];
        page_title($editId ? 'Modifier l\'actualité' : 'Créer une actualité', 'Remplis le titre, le résumé et le texte. Publie pour l\'afficher dans « Quoi de neuf ? ».');
        echo '<p class="sm-crumb">Gestion du Site › <b>Actualités</b> › ' . ($editId ? 'Modifier' : 'Nouvelle') . '</p>';
        echo '<p style="margin:0 0 10px"><a href="?p=sitemgr&sec=news">← Toutes les actualités</a>' . ($editId ? ' · <a href="/v2/?p=actu&id=' . $editId . '&preview=1" target="_blank">👁️ Prévisualiser ↗</a>' : '') . '</p>';
        echo '<form method="post" class="js" data-reload id="nwform"><div class="card">' . csrf_field()
           . '<input type="hidden" name="action" value="site_news_save"><input type="hidden" name="id" value="' . (int)$n['id'] . '"><input type="hidden" name="body" id="nwbody"><input type="hidden" name="status" id="nwstatus" value="' . h((string)$n['status']) . '">';
        echo '<label style="display:block;margin-bottom:10px">Titre<input name="title" value="' . h((string)$n['title']) . '" required></label>';
        echo '<label style="display:block;margin-bottom:10px">Résumé <span class="sub">(affiché dans « Quoi de neuf ? »)</span><input name="summary" value="' . h((string)$n['summary']) . '" maxlength="255"></label>';
        // éditeur visuel
        echo '<label style="display:block;margin-bottom:4px">Texte complet de l\'article</label>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:4px;padding:6px;border:1px solid var(--line2);border-bottom:0;border-radius:6px 6px 0 0;background:var(--panel2)">';
        foreach ([['nwB(\'formatBlock\',\'P\')', 'Paragraphe'], ['nwB(\'formatBlock\',\'H3\')', 'Titre'], ['nwB(\'bold\')', 'Gras'], ['nwB(\'italic\')', 'Italique'], ['nwB(\'insertUnorderedList\')', 'Liste'], ['nwLink()', 'Lien']] as $t)
            echo '<button type="button" onclick="' . h($t[0]) . '" style="padding:5px 11px;border:1px solid var(--line2);border-radius:5px;background:var(--panel);color:var(--txt);cursor:pointer;font-size:12px">' . h($t[1]) . '</button>';
        echo '</div><div id="nwed" contenteditable="true" oninput="nwSync()" style="min-height:150px;border:1px solid var(--line2);border-radius:0 0 6px 6px;padding:12px;background:#fff;color:#222;font:12px/1.5 Verdana,Arial,sans-serif">' . (string)$n['body'] . '</div>';
        // image + date
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px"><label style="flex:1;min-width:220px">Image (illustration)<input name="image" id="nwimg" value="' . h((string)$n['image']) . '" placeholder="/web-gallery/v2/pages_img/…"></label>'
           . '<label style="min-width:180px">Date<input type="datetime-local" name="created_at" value="' . h((string)$n['created_at']) . '"></label></div>';
        echo '<div id="nwimgprev" style="margin-top:6px">' . ((string)$n['image'] !== '' ? '<img src="' . h((string)$n['image']) . '" style="max-height:60px;border:1px solid var(--line2);border-radius:5px">' : '') . '</div>';
        echo '<div style="margin-top:6px"><button type="button" class="sm-btn" onclick="document.getElementById(\'nwmedia\').style.display=(document.getElementById(\'nwmedia\').style.display===\'none\'?\'block\':\'none\')">🖼️ Choisir une image</button></div>';
        echo '<div id="nwmedia" style="display:none;margin-top:8px;padding:8px;border:1px solid var(--line2);border-radius:6px;max-height:200px;overflow:auto"><div style="display:flex;flex-wrap:wrap;gap:6px">';
        foreach ($media as $f) echo '<img src="/web-gallery/v2/pages_img/' . h($f) . '" title="' . h($f) . '" onclick="nwPick(\'/web-gallery/v2/pages_img/' . h($f) . '\')" style="width:70px;height:46px;object-fit:contain;border:1px solid var(--line2);border-radius:4px;cursor:pointer;background:#fff">';
        echo '</div><a href="?p=sitemgr&sec=media" target="_blank" class="sub">+ Importer une image ↗</a></div>';
        // actions
        echo '<div class="sm-actions"><button class="sm-btn" type="submit" onclick="document.getElementById(\'nwstatus\').value=\'draft\'">📝 Enregistrer le brouillon</button>'
           . '<button class="sm-btn primary" type="submit" onclick="document.getElementById(\'nwstatus\').value=\'published\'">✅ Publier</button>';
        if ($editId) echo '<button class="sm-btn danger" type="submit" formaction="" onclick="if(!confirm(\'Supprimer cette actualité ?\'))return false;this.form.querySelector(\'[name=action]\').value=\'site_news_delete\'" style="margin-left:auto">🗑️ Supprimer</button>';
        echo '</div></div></form>';
        echo <<<'JS'
<script>
function nwEl(){return document.getElementById('nwed');}
function nwSync(){document.getElementById('nwbody').value=nwEl().innerHTML;}
function nwB(c,v){nwEl().focus();document.execCommand(c,false,v||null);nwSync();}
function nwLink(){var u=prompt('Lien (ex. ?p=club ou https://…) :','?p=');if(u){nwEl().focus();document.execCommand('createLink',false,u);nwSync();}}
function nwPick(s){document.getElementById('nwimg').value=s;document.getElementById('nwimgprev').innerHTML='<img src="'+s+'" style="max-height:60px;border:1px solid var(--line2);border-radius:5px">';document.getElementById('nwmedia').style.display='none';}
(function(){nwSync();var f=document.getElementById('nwform');if(f)f.addEventListener('submit',nwSync,true);})();
</script>
JS;
        return;
    }
    // ---- Liste des actualités = la liste unifiée, pré-filtrée sur les news (un seul système) ----
    sitemgr_allcontent('news');
}
function sitemgr_events(): void {
    echo '<div class="panel"><div class="ph"><h3>🎉 Événements & Infobus</h3></div>';
    echo '<p class="muted" style="padding:4px 0">Gère la <b>programmation affichée sur le site</b> (prochains événements + horaires Infobus).</p>';
    echo '<p class="muted sm">⬜ À construire (point 9) : titre/description/illustration/date/intervenant/salle/état/mise en avant, prochains + archive.</p>';
    echo '<div class="warn" style="margin-top:6px">⚠️ Le contrôle du bus <b>en jeu</b> (ouverture, votes) reste dans l\'onglet <a href="?p=bus">Bus (Infobus)</a> et n\'est pas présenté comme pleinement intégré à Kepler tant que ce n\'est pas testé.</div></div>';
}
/* ---- Médias (point 10) : bibliothèque locale ---- */
/* Liste précise des contenus qui utilisent une image (par nom de fichier). */
function media_usage(string $name): array {
    $u = [];
    try { foreach (db()->query('SELECT slug,title,body_html FROM site_pages') as $r) if ($r['body_html'] !== null && strpos((string)$r['body_html'], $name) !== false) $u[] = 'Page « ' . (trim((string)$r['title']) !== '' ? $r['title'] : $r['slug']) . ' »'; } catch (Throwable $e) {}
    try { foreach (db()->query('SELECT id,title,body FROM site_news') as $r) if (strpos((string)$r['body'], $name) !== false) $u[] = 'Actu #' . $r['id'] . ' « ' . mb_strimwidth((string)$r['title'], 0, 24, '…') . ' »'; } catch (Throwable $e) {}
    try { foreach (db()->query('SELECT page,slot,image FROM site_carousel') as $r) if (strpos((string)$r['image'], $name) !== false) $u[] = 'Carrousel ' . $r['page'] . ' #' . $r['slot']; } catch (Throwable $e) {}
    return $u;
}
function sitemgr_media(): void {
    $dir = dirname(__DIR__) . '/web-gallery/v2/pages_img';
    $url = '/web-gallery/v2/pages_img';
    echo '<div class="panel"><div class="ph"><h3>🖼️ Importer une image</h3></div>';
    echo '<form method="post" class="js" data-reload enctype="multipart/form-data"><div class="row">' . csrf_field() . '<input type="hidden" name="action" value="media_upload">'
       . '<input type="file" name="file" accept="image/*" required><button>⬆️ Importer</button></div>'
       . '<p class="muted sm" style="margin-top:4px">Hébergé dans le projet (<code>web-gallery/v2/pages_img/</code>). GIF animés et dimensions natives conservés (aucune conversion).</p></div>';

    $q = trim((string)($_GET['q'] ?? ''));
    $files = is_dir($dir) ? array_values(array_filter(scandir($dir), fn($f) => !in_array($f, ['.', '..'], true) && is_file($dir . '/' . $f))) : [];
    if ($q !== '') $files = array_values(array_filter($files, fn($f) => stripos($f, $q) !== false));
    $nbUsed = 0; $nbFree = 0;
    echo '<div class="panel"><div class="ph"><h3>Médiathèque</h3><span class="muted sm" style="margin-left:auto">' . count($files) . ' fichier' . (count($files) > 1 ? 's' : '') . '</span></div>';
    echo '<form method="get" class="row" style="margin-bottom:8px;gap:6px"><input type="hidden" name="p" value="sitemgr"><input type="hidden" name="sec" value="media"><input name="q" value="' . h($q) . '" placeholder="Filtrer par nom de fichier…" style="flex:1;min-width:160px"><button class="mini">Filtrer</button>' . ($q !== '' ? ' <a class="mini ghost" href="?p=sitemgr&sec=media">Tout</a>' : '') . '</form>';
    if (!$files) echo '<p class="muted" style="padding:4px 0">' . ($q !== '' ? 'Aucun fichier ne correspond.' : 'Aucun fichier importé pour l\'instant.') . '</p>';
    else {
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;padding:4px 0">';
        foreach ($files as $f) {
            $uses = media_usage($f);
            $used = (bool)$uses; $used ? $nbUsed++ : $nbFree++;
            $sz = @getimagesize($dir . '/' . $f);
            $dim = $sz ? $sz[0] . '×' . $sz[1] : '?';
            $ko = @filesize($dir . '/' . $f); $ko = $ko ? round($ko / 1024) . ' Ko' : '?';
            echo '<div style="width:150px;border:1px solid var(--line2);border-radius:8px;padding:6px;text-align:center">'
               . '<div style="height:66px;display:flex;align-items:center;justify-content:center;background:var(--bg);border-radius:5px;overflow:hidden"><img src="' . $url . '/' . h($f) . '" alt="" style="max-width:100%;max-height:64px;object-fit:contain;image-rendering:pixelated"></div>'
               . '<div class="sm" style="word-break:break-all;margin-top:4px;font-weight:600">' . h($f) . '</div>'
               . '<div class="muted sm">' . $dim . ' · ' . $ko . '</div>';
            if ($used) {
                echo '<details style="margin-top:3px;text-align:left"><summary class="sm" style="color:var(--green);cursor:pointer">✅ utilisée (' . count($uses) . ')</summary><div class="muted sm" style="margin-top:3px">' . implode('<br>', array_map('h', $uses)) . '</div></details>';
                echo '<div class="muted sm" title="Suppression bloquée tant qu\'elle est utilisée" style="margin-top:4px">🔒 utilisée</div>';
            } else {
                echo '<div class="muted sm" style="margin-top:3px">non utilisée</div>';
                echo '<form method="post" class="js" data-reload data-confirm="Supprimer définitivement ' . h($f) . ' ? (non utilisée)" style="margin-top:4px"><input type="hidden" name="action" value="media_delete">' . csrf_field() . '<input type="hidden" name="file" value="' . h($f) . '"><button class="mini ghost">🗑️ Supprimer</button></form>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '<p class="muted sm" style="margin-top:8px">' . $nbUsed . ' utilisée(s) · ' . $nbFree . ' libre(s). 🔒 Une image utilisée ne peut pas être supprimée (garde-fou serveur : on ne casse jamais un contenu).</p>';
    }
    echo '</div>';
}
function sitemgr_apparence(): void {
    $bgdir = dirname(__DIR__) . '/web-gallery/v2/images/bg_patterns';
    $bgurl = '/web-gallery/v2/images/bg_patterns';
    if (!is_dir($bgdir)) @mkdir($bgdir, 0777, true);
    $color = site_setting_get('site.bg_color', '#083940');
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $color = '#083940';
    $mode = site_setting_get('site.bg_mode', 'repeat');
    if (!in_array($mode, ['repeat', 'center', 'cover'], true)) $mode = 'repeat';
    $curName = ($img = site_setting_get('site.bg_image', '')) !== '' ? basename($img) : '';
    $files = is_dir($bgdir) ? array_values(array_filter(scandir($bgdir), fn($f) => !in_array($f, ['.', '..'], true) && is_file($bgdir . '/' . $f) && preg_match('/\.(gif|png|jpe?g)$/i', $f))) : [];

    page_title('Apparence — Fond du site', 'Change l\'image ou la couleur visible derrière et sur les côtés du site. Le cadre blanc, les colonnes, les boutons et les dimensions ne changent pas.');

    echo '<form method="post" class="js" data-reload id="bgform">' . csrf_field() . '<input type="hidden" name="action" value="site_bg_apply"><input type="hidden" name="image" id="bgImage" value="' . h($curName) . '">';

    // Aperçu live (le cadre blanc est figuré, non modifiable)
    echo '<div class="panel"><div class="ph"><h3>👁️ Aperçu</h3><span class="muted sm" style="margin-left:auto">rendu derrière le cadre blanc</span></div>';
    echo '<div id="bgPreview" data-baseurl="' . $bgurl . '" style="border:1px solid var(--line2);border-radius:6px;height:190px;display:flex;align-items:center;justify-content:center;overflow:hidden">'
       . '<div style="width:70%;max-width:360px;height:120px;background:#fff;border:1px solid #06252b;border-radius:0 0 9px 9px;box-shadow:0 2px 8px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;color:#9aa;font-size:11px">cadre du site (inchangé)</div></div></div>';

    // Couleur + mode
    echo '<div class="panel"><div class="ph"><h3>🎨 Couleur & affichage</h3></div><div class="row" style="gap:18px;flex-wrap:wrap;align-items:flex-end">';
    echo '<label>Couleur de fond<br><input type="color" id="bgColor" name="color" value="' . h($color) . '" style="width:56px;height:34px;padding:0;border:1px solid var(--line2);border-radius:5px;vertical-align:middle"> <input type="text" id="bgColorHex" value="' . h($color) . '" maxlength="7" style="width:92px;font-family:var(--mono)"></label>';
    echo '<label>Affichage<br><select id="bgMode" name="mode">'
       . '<option value="repeat"' . ($mode === 'repeat' ? ' selected' : '') . '>Motif répété (pixels nets)</option>'
       . '<option value="center"' . ($mode === 'center' ? ' selected' : '') . '>Image centrée (taille d\'origine)</option>'
       . '<option value="cover"' . ($mode === 'cover' ? ' selected' : '') . '>Couvrir l\'écran</option></select></label></div></div>';

    // Galerie
    echo '<div class="panel"><div class="ph"><h3>🖼️ Galerie de fonds</h3><span class="muted sm" style="margin-left:auto">' . count($files) . ' fond(s)</span></div>';
    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;padding:4px 0">';
    echo '<div class="bgtile" data-name="" onclick="bgPick(this)" style="width:110px;height:84px;border:1px solid var(--line2);border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;text-align:center;font-size:10px;color:#eee;background:' . h($color) . ';' . ($curName === '' ? 'outline:3px solid var(--green)' : '') . '">Couleur seule<br>(aucune image)</div>';
    foreach ($files as $f) {
        $sz = @getimagesize($bgdir . '/' . $f); $dim = $sz ? $sz[0] . '×' . $sz[1] : '?';
        echo '<div class="bgtile" data-name="' . h($f) . '" onclick="bgPick(this)" title="' . h($f . ' · ' . $dim) . '" style="width:110px;height:84px;border:1px solid var(--line2);border-radius:6px;cursor:pointer;background:#20303a url(\'' . $bgurl . '/' . h($f) . '\') repeat;image-rendering:pixelated;' . ($f === $curName ? 'outline:3px solid var(--green)' : '') . '"></div>';
    }
    echo '</div></div>';

    echo '<div class="sm-actions"><button class="sm-btn primary" type="submit">✅ Appliquer</button><a class="sm-btn" href="?p=sitemgr&sec=apparence">↩️ Annuler</a></div></form>';

    // Import
    echo '<div class="panel"><div class="ph"><h3>⬆️ Importer un fond</h3></div>';
    echo '<form method="post" class="js" data-reload enctype="multipart/form-data"><div class="row">' . csrf_field() . '<input type="hidden" name="action" value="site_bg_upload"><input type="file" name="file" accept="image/png,image/jpeg,image/gif" required><button>⬆️ Importer</button></div>';
    echo '<p class="muted sm" style="margin-top:4px">PNG, JPG ou GIF (animation conservée). Max 3 Mo, 2000×2000 px. Stocké dans <code>web-gallery/v2/images/bg_patterns/</code>. Pixel-art : garde sa taille d\'origine en mode « Motif répété ».</p></form></div>';

    // Restaurer
    echo '<form method="post" class="js" data-reload style="margin-top:8px"><div class="panel"><div class="ph"><h3>↩️ Fond par défaut</h3></div>' . csrf_field() . '<input type="hidden" name="action" value="site_bg_reset">';
    echo '<p class="muted sm">Restaure le fond d\'origine : couleur <code>#083940</code>, motif répété, sans image.</p><button class="sm-btn" onclick="return confirm(\'Restaurer le fond par défaut ?\')">Restaurer</button></div></form>';

    echo <<<'JS'
<script>
(function(){
 var pv=document.getElementById('bgPreview'); if(!pv) return;
 var base=pv.getAttribute('data-baseurl');
 function render(){
   var color=document.getElementById('bgColor').value;
   var mode=document.getElementById('bgMode').value;
   var name=document.getElementById('bgImage').value;
   pv.style.backgroundSize=''; pv.style.imageRendering='pixelated';
   if(name){
     var u="url('"+base+'/'+name+"')";
     if(mode==='cover'){pv.style.background=color+' '+u+' no-repeat center center';pv.style.backgroundSize='cover';}
     else if(mode==='center'){pv.style.background=color+' '+u+' no-repeat center center';}
     else{pv.style.background=color+' '+u+' repeat';}
   } else { pv.style.background=color; }
 }
 window.bgPick=function(el){
   document.getElementById('bgImage').value=el.getAttribute('data-name')||'';
   var ts=document.querySelectorAll('.bgtile'); for(var i=0;i<ts.length;i++){ts[i].style.outline='';}
   el.style.outline='3px solid var(--green)'; render();
 };
 document.getElementById('bgColor').addEventListener('input',function(){document.getElementById('bgColorHex').value=this.value;render();});
 document.getElementById('bgColorHex').addEventListener('input',function(){var v=this.value.trim();if(/^#?[0-9A-Fa-f]{6}$/.test(v)){if(v[0]!=='#')v='#'+v;document.getElementById('bgColor').value=v;render();}});
 document.getElementById('bgMode').addEventListener('change',render);
 render();
})();
</script>
JS;
}
function sitemgr_settings(): void {
    echo '<div class="panel"><div class="ph"><h3>⚙️ Réglages du site</h3></div>';
    $settings = [
        ['site.name', 'Nom du rétro', 'Habbo'],
        ['site.contact', 'Contact (email/lien)', ''],
        ['site.footer', 'Texte du pied de page', ''],
    ];
    foreach ($settings as $cfg) {
        echo '<form method="post" class="js row" data-reload style="margin-bottom:6px">' . csrf_field() . '<input type="hidden" name="action" value="site_setting"><input type="hidden" name="key" value="' . h($cfg[0]) . '">';
        echo '<label style="flex:1">' . h($cfg[1]) . '<input name="value" value="' . h(site_setting_get($cfg[0], $cfg[2])) . '" maxlength="400"></label><button style="align-self:flex-end">💾</button></form>';
    }
    echo '<p class="muted sm">La maintenance et l\'accès pendant maintenance se gèrent dans l\'onglet <a href="?p=server">Serveur</a>. Aucun éditeur de code ici (point 11).</p></div>';
}
function sitemgr_history(): void {
    echo '<div class="panel"><div class="ph"><h3>🕑 Historique des modifications du site</h3></div>';
    ensure_admin_log();
    $rows = db()->query("SELECT author,action,detail,created_at FROM admin_log WHERE action IN ('sitepage_save','carousel_save','site_setting','nav_tab_save','nav_item_save','nav_move','nav_flag','media_upload','media_delete','site_bg','site_bg_upload','news_add','news_update','news_delete') ORDER BY id DESC LIMIT 40")->fetchAll();
    if (!$rows) echo '<p class="muted" style="padding:4px 0">Aucune action enregistrée.</p>';
    else { echo '<table style="width:100%;border-collapse:collapse;font-size:13px"><tr class="muted sm"><th style="text-align:left">Date</th><th style="text-align:left">Staff</th><th style="text-align:left">Action</th><th style="text-align:left">Détail</th></tr>';
        foreach ($rows as $r) echo '<tr><td style="padding:3px 0">' . h((string)$r['created_at']) . '</td><td>' . h((string)$r['author']) . '</td><td>' . h((string)$r['action']) . '</td><td class="muted sm">' . h((string)$r['detail']) . '</td></tr>';
        echo '</table>'; }
    echo '<p class="muted sm" style="margin-top:6px">⬜ À venir (point 14) : versions complètes des contenus + restauration + anti-écrasement concurrent.</p></div>';
}
/* Carte action POST -> onglet propriétaire (le plus PERMISSIF où le bouton est exposé),
   pour contrôler le droit AVANT exécution sans jamais verrouiller un accès existant. */
function action_tab(string $a): string {
    static $map = [
        // Gestion du Site (v2)
        'carousel_save' => 'sitemgr', 'site_setting' => 'sitemgr', 'sitepage_save' => 'sitemgr', 'sitepage_block_save' => 'sitemgr', 'sitepage_arrange' => 'sitemgr',
        'nav_tab_save' => 'sitemgr', 'nav_item_save' => 'sitemgr', 'nav_move' => 'sitemgr', 'nav_flag' => 'sitemgr', 'media_upload' => 'sitemgr', 'media_delete' => 'sitemgr', 'site_bg_apply' => 'sitemgr', 'site_bg_reset' => 'sitemgr', 'site_bg_upload' => 'sitemgr',
        'page_pin' => 'sitemgr', 'site_page_create' => 'sitemgr', 'site_news_save' => 'news', 'site_news_delete' => 'news',
        'hm_report_status' => 'homemod', 'hm_home_hide' => 'homemod', 'hm_home_lock' => 'homemod', 'hm_gb_hide' => 'homemod', 'hm_item_del' => 'homemod', 'hm_asset_status' => 'homemod', 'hm_asset_bulk' => 'homemod',
        // Joueurs
        'user_update' => 'users', 'user_details' => 'users', 'user_rank' => 'users', 'credits_all' => 'users',
        'user_password' => 'users', 'user_create' => 'users', 'user_credits' => 'users', 'user_hc' => 'users',
        'user_delete' => 'users', 'clear_hand' => 'users', 'note_add' => 'users', 'note_del' => 'users',
        // Badges / rangs
        'badge_add' => 'badges', 'badge_remove' => 'badges', 'badge_all' => 'badges',
        'rank_badge_add' => 'ranks', 'rank_badge_remove' => 'ranks',
        // Bots / jeux / événements
        'bot_update' => 'bots', 'game_rank_update' => 'games', 'game_points' => 'games',
        'gmap_update' => 'gamemaps', 'event_delete' => 'events',
        // Salles & décors (le bouton « Redémarrer l'ému » vit ici, rang 5)
        'room_update' => 'rooms', 'room_decor' => 'rooms', 'room_toggle' => 'rooms',
        'decor_season' => 'rooms', 'srv_restart' => 'rooms', 'model_update' => 'models',
        'srv_rk_start' => 'server', 'srv_rk_cancel' => 'server', // Redémarrage Kepler : droit « Technique → Serveur » (rang 7)
        // Catalogue & mobis
        'cat_item' => 'catalogue', 'cat_page' => 'catalogue', 'cat_page_move' => 'catalogue', 'cat_bulk' => 'catalogue',
        'navcat_update' => 'navcats', 'navcat_move' => 'navcats',
        'pkg_add' => 'packages', 'pkg_update' => 'packages', 'pkg_delete' => 'packages',
        'def_update' => 'furni', 'furni_def_save' => 'furni', 'furni_def_restore' => 'furni', 'furni_name_sync' => 'furni', 'furni_all' => 'furni', 'furni_give_me' => 'furni',
        'wf_add' => 'wordfilter', 'wf_del' => 'wordfilter', 'wf_toggle' => 'wordfilter', 'song_update' => 'trax', 'song_delete' => 'trax',
        'furni_convert' => 'convert', 'furni_decompile' => 'convert',
        // Communauté / modération
        'news_add' => 'news', 'news_update' => 'news', 'news_delete' => 'news',
        'ban_add' => 'moderation', 'ban_remove' => 'moderation', 'bus_type' => 'bus',
        'cmd_fav_toggle' => 'commandes',
        'pwreset_do' => 'messages', 'pwreset_reject' => 'messages',
        'cmsg_handle' => 'messages', 'cmsg_delete' => 'messages', 'umsg_send' => 'users',
        'smsg_post' => 'messages', 'smsg_delete' => 'messages',
        'conv_reply' => 'messages', 'conv_status' => 'messages', 'conv_new' => 'messages', 'conv_reprise' => 'messages',
        'conv_assign' => 'messages', 'conv_note' => 'messages', 'conv_category' => 'messages', 'conv_ack_set' => 'messages',
        'conv_broadcast' => 'messages', 'conv_merge' => 'messages', 'conv_staff_box' => 'messages', 'conv_hide' => 'messages',
        // Jeux : codes & recycleur
        'voucher_add' => 'vouchers', 'voucher_delete' => 'vouchers',
        'recy_add' => 'recycler', 'recy_update' => 'recycler', 'recy_delete' => 'recycler',
        // Système (rang 7 par défaut)
        'setting_update' => 'settings', 'xtext_update' => 'textes', 'tabperm_update' => 'access',
        'srv_start' => 'server', 'srv_stop' => 'server', 'set_entry_bg' => 'server', 'hotel_alert' => 'server',
        'maint_on' => 'server', 'maint_off' => 'server',
        'db_backup' => 'mysql',
    ];
    return $map[$a] ?? '';
}

/* ---- Journal d'actions admin ---- */
function ensure_admin_log(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS admin_log (id INT AUTO_INCREMENT PRIMARY KEY, author VARCHAR(64) NOT NULL, action VARCHAR(64) NOT NULL, detail VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function admin_log(string $action, string $detail = ''): void {
    try { ensure_admin_log(); db()->prepare('INSERT INTO admin_log (author,action,detail) VALUES (?,?,?)')->execute([$_SESSION['admin']['username'] ?? '?', $action, mb_substr($detail, 0, 255)]); } catch (Throwable $e) {}
}
/* ---- Historique par joueur (journalise les actions visant un compte) ---- */
function ensure_user_history(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS user_history (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, author VARCHAR(64) NOT NULL DEFAULT '', action VARCHAR(64) NOT NULL DEFAULT '', detail VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function user_log(int $uid, string $action, string $detail = ''): void {
    if ($uid <= 0) return;
    try { ensure_user_history(); db()->prepare('INSERT INTO user_history (user_id,author,action,detail) VALUES (?,?,?,?)')->execute([$uid, $_SESSION['admin']['username'] ?? '?', $action, mb_substr($detail, 0, 255)]); } catch (Throwable $e) {}
}
/* Journalise une action POST si elle vise un joueur (jamais de secret). */
function user_history_maybe(string $action): void {
    static $watched = ['user_update' => 1, 'user_details' => 1, 'user_credits' => 1, 'user_hc' => 1, 'user_rank' => 1, 'user_password' => 1, 'badge_add' => 1, 'badge_remove' => 1, 'ban_add' => 1, 'ban_remove' => 1, 'clear_hand' => 1, 'note_add' => 1, 'umsg_send' => 1];
    if (!isset($watched[$action])) return;
    $uid = 0;
    if (isset($_POST['id'])) $uid = (int)$_POST['id'];
    elseif (isset($_POST['user_id'])) $uid = (int)$_POST['user_id'];
    elseif (isset($_POST['username'])) { try { $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([trim((string)$_POST['username'])]); $uid = (int)$st->fetchColumn(); } catch (Throwable $e) {} }
    elseif ($action === 'ban_remove' && isset($_POST['value'])) $uid = (int)$_POST['value'];
    if ($uid <= 0) return;
    switch ($action) {
        case 'user_password': $d = 'Mot de passe modifié'; break;
        case 'user_credits': $cd = (int)($_POST['delta'] ?? 0); $d = 'Crédits ' . ($cd >= 0 ? '+' : '') . $cd; break;
        case 'user_hc': $d = 'Club Habbo ' . (int)($_POST['days'] ?? 0) . ' j'; break;
        case 'user_rank': $d = 'Rang → ' . (int)($_POST['rank'] ?? 0); break;
        case 'ban_add': $d = 'Banni (' . (int)($_POST['days'] ?? 0) . ' j) — ' . mb_substr(trim((string)($_POST['message'] ?? '')), 0, 80); break;
        case 'ban_remove': $d = 'Bannissement levé'; break;
        case 'clear_hand': $d = 'Inventaire (main) vidé'; break;
        case 'badge_add': $d = 'Badge ajouté : ' . mb_substr(strtoupper(trim((string)($_POST['badge'] ?? ''))), 0, 3); break;
        case 'badge_remove': $d = 'Badge retiré'; break;
        case 'note_add': $d = 'Note staff ajoutée'; break;
        case 'umsg_send': $d = 'Message envoyé au joueur'; break;
        case 'user_update': $d = 'Rang / crédits mis à jour'; break;
        case 'user_details': $d = 'Infos (e-mail, mission…) mises à jour'; break;
        default: $d = $action;
    }
    user_log($uid, $action, $d);
}
/* ---- Notes staff sur un joueur ---- */
function ensure_admin_notes(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS admin_notes (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, author VARCHAR(64) NOT NULL, note VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/* ---- Demandes de réinitialisation de mot de passe (file traitée par le staff) ---- */
function ensure_pw_resets(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL, message VARCHAR(500) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '', status ENUM('pending','done','rejected') NOT NULL DEFAULT 'pending', handled_by VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, handled_at DATETIME NULL, INDEX(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/* ---- Messages du formulaire Contact (boîte de réception) ---- */
function ensure_contact_msgs(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS contact_messages (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL DEFAULT '', subject VARCHAR(120) NOT NULL DEFAULT '', message VARCHAR(1000) NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT '', status ENUM('new','handled') NOT NULL DEFAULT 'new', handled_by VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, handled_at DATETIME NULL, INDEX(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/* ---- Messages Staff -> joueur (lus sur le site) ---- */
function ensure_user_msgs(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS user_messages (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, from_staff VARCHAR(64) NOT NULL DEFAULT '', subject VARCHAR(120) NOT NULL DEFAULT '', body VARCHAR(2000) NOT NULL, read_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), INDEX(read_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/* Total d'éléments en attente dans la boîte de réception (messages Contact + demandes MDP). */
function inbox_pending(): int {
    $n = 0;
    try { ensure_contact_msgs(); ensure_pw_resets();
        $n = (int)db()->query("SELECT (SELECT COUNT(*) FROM contact_messages WHERE status='new') + (SELECT COUNT(*) FROM password_resets WHERE status='pending')")->fetchColumn();
    } catch (Throwable $e) {}
    // Conversations avec un message joueur non lu par le staff (nouveau système).
    if (function_exists('conv_unread_staff')) { try { $n += conv_unread_staff(); } catch (Throwable $e) {} }
    return $n;
}
/* ---- Discussion interne de l'équipe (staff <-> staff) ---- */
function ensure_staff_msgs(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS staff_messages (id INT AUTO_INCREMENT PRIMARY KEY, author VARCHAR(64) NOT NULL DEFAULT '', body VARCHAR(2000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS staff_seen (username VARCHAR(64) NOT NULL PRIMARY KEY, last_id INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/* Nb de messages staff non lus par $me (postés par d'autres après sa dernière visite). */
function staff_unread(string $me): int {
    try { ensure_staff_msgs();
        $st = db()->prepare('SELECT last_id FROM staff_seen WHERE username=?'); $st->execute([$me]); $last = (int)$st->fetchColumn();
        $c = db()->prepare('SELECT COUNT(*) FROM staff_messages WHERE id>? AND author<>?'); $c->execute([$last, $me]); return (int)$c->fetchColumn();
    } catch (Throwable $e) { return 0; }
}
/* Badge global de l'onglet Messagerie = éléments joueurs en attente + messages staff non lus. */
function messages_badge(): int { return inbox_pending() + staff_unread((string)($_SESSION['admin']['username'] ?? '')); }
/* ---- Saison courante (pour décors auto) ---- */
function current_season(): string {
    $m = (int)date('n'); $d = (int)date('j');
    if ($m === 12 || ($m === 1 && $d <= 6)) return 'xmas';
    if ($m === 10) return 'halloween';
    if ($m === 3 || ($m === 4 && $d <= 15)) return 'easter';
    if ($m >= 6 && $m <= 8) return 'summer';
    if ($m === 1 || $m === 2) return 'winter';
    return '';
}
function season_label(string $s): string { return DECOR_LABELS[$s] ?? '🏛️ Normal'; }

/* ================= PAGES ================= */
function ensure_news(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS site_news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        category VARCHAR(40) NOT NULL DEFAULT 'À la une',
        color VARCHAR(10) NOT NULL DEFAULT 'blue',
        body TEXT NOT NULL,
        author VARCHAR(255) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}
function page_news(): void {
    // ?p=news est normalement redirigé AVANT l'affichage (voir en tête du script).
    // Repli CÔTÉ CLIENT si jamais atteint ici (pas d'en-tête HTTP possible après rendu).
    $to = '?p=sitemgr&sec=news';
    echo '<meta http-equiv="refresh" content="0;url=' . h($to) . '"><p class="muted">Redirection vers l\'éditeur d\'actualités… <a href="' . h($to) . '">Clique ici</a> si rien ne se passe.</p>';
}

function page_vouchers(): void {
    page_title('Codes promo', 'Génère des codes que les joueurs saisissent en jeu pour recevoir crédits et objets');
    $rows = db()->query('SELECT v.voucher_code,v.credits,v.expiry_date,v.is_single_use,(SELECT GROUP_CONCAT(catalogue_sale_code SEPARATOR ", ") FROM vouchers_items i WHERE i.voucher_code=v.voucher_code) AS items FROM vouchers v ORDER BY v.voucher_code')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🎁 Nouveau code promo</h3></div><form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="voucher_add">';
    echo '<div class="row"><label style="flex:1">Code<input name="code" required placeholder="PROMO2026"></label>';
    echo '<label>Crédits<input type="number" name="credits" value="100" style="width:110px"></label>';
    echo '<label>Expire le (optionnel)<input type="date" name="expiry"></label>';
    echo '<label style="flex-direction:row;align-items:center"><input type="checkbox" name="single" checked> Usage unique</label>';
    echo '<button type="button" class="mini ghost" onclick="this.form.code.value=\'PROMO\'+Math.random().toString(36).slice(2,7).toUpperCase()">🎲 Générer</button></div>';
    echo '<label style="margin-top:8px">Objets offerts — sale codes séparés par des virgules (optionnel)<input name="items" placeholder="ex: throne, hc_lmp"></label>';
    echo '<button style="margin-top:10px">🎁 Créer le code</button></form></div>';
    echo '<div class="panel"><div class="ph"><h3>Codes existants (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<div class="empty">Aucun code promo. Crée le premier ci-dessus.</div>';
    else { echo '<table class="clean"><tr><th>Code</th><th>Crédits</th><th>Objets</th><th>Expire</th><th>Unique</th><th></th></tr>';
        foreach ($rows as $v) echo '<tr><td><code>' . h($v['voucher_code']) . '</code></td><td>' . (int)$v['credits'] . '</td><td class="sm muted">' . h((string)($v['items'] ?? '-')) . '</td><td class="sm">' . ($v['expiry_date'] ? h(date('d/m/Y', strtotime((string)$v['expiry_date']))) : '♾️') . '</td><td>' . ($v['is_single_use'] ? '✓' : '—') . '</td><td><form method="post" class="js" data-reload data-confirm="Supprimer ce code ?"><input type="hidden" name="action" value="voucher_delete">' . csrf_field() . '<input type="hidden" name="code" value="' . h($v['voucher_code']) . '"><button class="mini warn">Suppr.</button></form></td></tr>';
        echo '</table>'; }
    echo '</div>';
}

function page_packages(): void {
    page_title('Packs catalogue', 'Lots de meubles associés à un article du catalogue');
    $edit = null; if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM catalogue_packages WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; }
    $rows = db()->query('SELECT * FROM catalogue_packages ORDER BY salecode')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>' . ($edit ? '✏️ Modifier le pack' : '📦 Nouveau pack') . '</h3>' . ($edit ? '<a class="lnk" href="?p=packages">+ Nouveau</a>' : '') . '</div><form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="' . ($edit ? 'pkg_update' : 'pkg_add') . '">' . ($edit ? '<input type="hidden" name="id" value="' . (int)$edit['id'] . '">' : '');
    echo '<div class="row"><label style="flex:1">Sale code (du catalogue)<input name="salecode" required value="' . h($edit['salecode'] ?? '') . '"></label>';
    echo '<label>ID définition<input type="number" name="definition_id" value="' . (int)($edit['definition_id'] ?? 0) . '" style="width:120px"></label>';
    echo '<label>Sprite spécial<input type="number" name="special_sprite_id" value="' . (int)($edit['special_sprite_id'] ?? 0) . '" style="width:110px"></label>';
    echo '<label>Quantité<input type="number" name="amount" value="' . (int)($edit['amount'] ?? 1) . '" style="width:90px"></label></div>';
    echo '<button style="margin-top:10px">' . ($edit ? '💾 Enregistrer' : '📦 Ajouter') . '</button></form></div>';
    echo '<div class="panel"><div class="ph"><h3>Packs (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<div class="empty">Aucun pack.</div>';
    else { echo '<table class="clean"><tr><th>Sale code</th><th>Déf.</th><th>Sprite</th><th>Qté</th><th></th></tr>';
        foreach ($rows as $r) echo '<tr><td><code>' . h((string)$r['salecode']) . '</code></td><td>' . (int)$r['definition_id'] . '</td><td>' . (int)$r['special_sprite_id'] . '</td><td>' . (int)$r['amount'] . '</td><td class="ta-r"><a class="mini ghost" href="?p=packages&edit=' . (int)$r['id'] . '">Éditer</a> <form method="post" class="js" data-reload data-confirm="Supprimer ?" style="display:inline"><input type="hidden" name="action" value="pkg_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="mini warn">Suppr.</button></form></td></tr>';
        echo '</table>'; }
    echo '</div>';
}

function page_recycler(): void {
    page_title('Recycleur (Ecotron)', 'Récompenses obtenues en recyclant des meubles');
    $edit = null; if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM recycler_rewards WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; }
    $rows = db()->query('SELECT * FROM recycler_rewards ORDER BY id')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>' . ($edit ? '✏️ Modifier la récompense' : '♻️ Nouvelle récompense') . '</h3>' . ($edit ? '<a class="lnk" href="?p=recycler">+ Nouvelle</a>' : '') . '</div><form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="' . ($edit ? 'recy_update' : 'recy_add') . '">';
    echo '<div class="row"><label>ID<input type="number" name="id" ' . ($edit ? 'readonly' : '') . ' value="' . (string)($edit['id'] ?? '') . '" style="width:80px" required></label>';
    echo '<label style="flex:1">Sale code récompense<input name="sale_code" required value="' . h($edit['sale_code'] ?? '') . '"></label>';
    echo '<label>Coût (nb meubles)<input type="number" name="item_cost" value="' . (int)($edit['item_cost'] ?? 10) . '" style="width:130px"></label></div>';
    echo '<div class="row"><label>Durée session (s)<input type="number" name="session_time" value="' . (int)($edit['recycling_session_time_seconds'] ?? 0) . '" style="width:150px"></label>';
    echo '<label>Délai collecte (s)<input type="number" name="collection_time" value="' . (int)($edit['collection_time_seconds'] ?? 0) . '" style="width:150px"></label></div>';
    echo '<button style="margin-top:10px">' . ($edit ? '💾 Enregistrer' : '♻️ Ajouter') . '</button></form></div>';
    echo '<div class="panel"><div class="ph"><h3>Récompenses (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<div class="empty">Aucune récompense.</div>';
    else { echo '<table class="clean"><tr><th>ID</th><th>Sale code</th><th>Coût</th><th>Session</th><th>Collecte</th><th></th></tr>';
        foreach ($rows as $r) echo '<tr><td>' . (int)$r['id'] . '</td><td><code>' . h((string)$r['sale_code']) . '</code></td><td>' . (int)$r['item_cost'] . '</td><td class="sm">' . (int)$r['recycling_session_time_seconds'] . 's</td><td class="sm">' . (int)$r['collection_time_seconds'] . 's</td><td class="ta-r"><a class="mini ghost" href="?p=recycler&edit=' . (int)$r['id'] . '">Éditer</a> <form method="post" class="js" data-reload data-confirm="Supprimer ?" style="display:inline"><input type="hidden" name="action" value="recy_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="mini warn">Suppr.</button></form></td></tr>';
        echo '</table>'; }
    echo '</div>';
}

function page_events(): void {
    page_title('Événements', 'Événements de salles en cours');
    $rows = db()->query('SELECT e.room_id,e.name,e.description,e.expire_time,r.name AS room,u.username FROM rooms_events e LEFT JOIN rooms r ON r.id=e.room_id LEFT JOIN users u ON u.id=e.user_id ORDER BY e.expire_time DESC')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🎉 Événements en cours (' . count($rows) . ')</h3></div>';
    echo '<p class="muted sm">Les événements sont lancés par les joueurs en jeu. Ici tu peux les consulter et y mettre fin.</p>';
    if (!$rows) echo '<div class="empty">Aucun événement en cours.</div>';
    else { echo '<table class="clean"><tr><th>Salle</th><th>Nom</th><th>Description</th><th>Par</th><th>Expire</th><th></th></tr>';
        foreach ($rows as $e) { $exp = (int)$e['expire_time']; $d = $exp > 0 ? date('d/m H:i', (int)($exp > 2000000000 ? $exp / 1000 : $exp)) : '—';
            echo '<tr><td>' . h((string)($e['room'] ?? ('#' . $e['room_id']))) . '</td><td><b>' . h($e['name']) . '</b></td><td class="sm muted">' . h($e['description']) . '</td><td class="sm">' . h((string)($e['username'] ?? '?')) . '</td><td class="sm">' . $d . '</td><td><form method="post" class="js" data-reload data-confirm="Terminer cet événement ?"><input type="hidden" name="action" value="event_delete">' . csrf_field() . '<input type="hidden" name="room_id" value="' . (int)$e['room_id'] . '"><button class="mini warn">Terminer</button></form></td></tr>'; }
        echo '</table>'; }
    echo '</div>';
}

function page_trax(): void {
    page_title('Trax', 'Musiques de la SoundMachine');
    $rows = db()->query('SELECT s.id,s.title,s.length,u.username FROM soundmachine_songs s LEFT JOIN users u ON u.id=s.user_id ORDER BY s.id DESC')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🎵 Musiques (' . count($rows) . ')</h3></div>';
    echo '<p class="muted sm">Les musiques sont <b>composées par les joueurs en jeu</b> avec la SoundMachine (les samples sonores sont installés dans <code>dcr/sound/</code>). Cette liste se remplit dès qu\'un joueur enregistre une piste. Ici tu peux les renommer ou les supprimer.</p>';
    if (!$rows) echo '<div class="empty">Aucune musique pour le moment — normal tant qu\'aucun joueur n\'en a composé. Achète une SoundMachine au catalogue, compose une piste en jeu, et elle apparaîtra ici.</div>';
    else { echo '<table class="clean"><tr><th>#</th><th>Titre</th><th>Auteur</th><th>Durée</th><th></th></tr>';
        foreach ($rows as $s) echo '<tr><td>' . (int)$s['id'] . '</td><td><form method="post" class="js" data-reload style="display:flex;gap:6px;align-items:center"><input type="hidden" name="action" value="song_update">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$s['id'] . '"><input name="title" value="' . h((string)$s['title']) . '" style="flex:1"><button class="mini">💾</button></form></td><td class="sm">' . h((string)($s['username'] ?? '?')) . '</td><td class="sm muted">' . (int)$s['length'] . 's</td><td><form method="post" class="js" data-reload data-confirm="Supprimer ?"><input type="hidden" name="action" value="song_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$s['id'] . '"><button class="mini warn">Suppr.</button></form></td></tr>';
        echo '</table>'; }
    echo '</div>';
}

/* ===================== Gestionnaire de meubles — helpers (Lot 1) ===================== */
/* Dictionnaire des comportements RÉELS exposés par Kepler (vérifiés en base).
   On n'invente aucun réglage : on se contente de rendre lisibles les flags existants. */
function furni_behaviour_dict(): array {
    return [
        'solid' => 'Solide (occupe la case, bloque le passage)',
        'can_sit_on_top' => 'Assise possible',
        'can_lay_on_top' => 'Couchage possible',
        'can_stand_on_top' => 'On peut se tenir dessus',
        'can_stack_on_top' => 'Empilable (objets posés par-dessus)',
        'requires_rights_for_interaction' => 'Interaction réservée (droits du propriétaire)',
        'requires_touching_for_interaction' => 'Interaction au contact (case adjacente)',
        'gate' => 'Portail (ouverture/fermeture du passage)',
        'roller' => 'Rouleau (tapis roulant)',
        'teleporter' => 'Téléporteur',
        'prize_trophy' => 'Trophée (texte gravé)',
        'present' => 'Cadeau emballé',
        'redeemable' => 'Échangeable contre des crédits',
        'dice' => 'Dé',
        'sound_machine_sample_set' => 'Échantillon sonore (Trax)',
        'wall_item' => 'Objet mural',
        'custom_data_true_false' => 'État vrai/faux',
        'custom_data_on_off' => 'État on/off',
        'custom_data_numeric_on_off' => 'État numérique on/off',
        'custom_data_numeric_state' => 'États numériques multiples',
        'sound_machine' => 'Trax (machine à sons)',
        'song_disk' => 'Disque de chanson',
        'jukebox' => 'Jukebox',
        'post_it' => 'Post-it (pense-bête)',
        'photo' => 'Photo',
        'decoration' => 'Décoration (non interactif)',
        'wheel_of_fortune' => 'Roue de la fortune',
        'roomdimmer' => 'Variateur d\'ambiance (Moodlight)',
        'place_roller_on_top' => 'Peut recevoir un rouleau par-dessus',
    ];
}
/* Aide courte (effet concret) par comportement — affichée au survol et dans le résumé. */
function furni_behaviour_help(): array {
    return [
        'solid' => 'Bloque la case : on ne peut pas marcher dessus ni traverser.',
        'can_sit_on_top' => 'L\'avatar s\'assoit en montant dessus (hauteur = top_height). À coupler avec Interacteur « chair ».',
        'can_lay_on_top' => 'L\'avatar s\'allonge dessus (lit). À coupler avec Interacteur « bed ».',
        'can_stand_on_top' => 'On peut marcher/se tenir dessus (sol praticable).',
        'can_stack_on_top' => 'On peut poser d\'autres meubles par-dessus (à la hauteur top_height).',
        'place_roller_on_top' => 'Accepte un rouleau (tapis roulant) posé dessus.',
        'requires_rights_for_interaction' => 'Seul le propriétaire (ou qui a les droits) peut l\'actionner.',
        'requires_touching_for_interaction' => 'Il faut être sur une case adjacente pour l\'actionner.',
        'gate' => 'Barrière/porte : s\'ouvre et se ferme pour laisser passer.',
        'roller' => 'Rouleau : déplace automatiquement ce qui est dessus.',
        'teleporter' => 'Téléporteur. ⚠️ Nécessite un vrai sprite de téléporteur + une paire liée : la case seule ne suffit pas.',
        'prize_trophy' => 'Trophée avec texte gravé (coupe).',
        'present' => 'Paquet cadeau que l\'on déballe.',
        'redeemable' => 'Peut être échangé contre des crédits.',
        'dice' => 'Dé que l\'on lance.',
        'sound_machine' => 'Machine à sons (Trax). ⚠️ Dépend du sprite/logique Trax côté client.',
        'sound_machine_sample_set' => 'Fournit un jeu d\'échantillons pour la Trax.',
        'song_disk' => 'Disque de chanson pour la Trax.',
        'jukebox' => 'Jukebox. ⚠️ Nécessite le sprite/logique jukebox : cocher ne suffit pas.',
        'photo' => 'Photo prise avec l\'appareil.',
        'post_it' => 'Pense-bête collant (note).',
        'decoration' => 'Pure décoration, aucune interaction.',
        'wheel_of_fortune' => 'Roue de la fortune (donne un résultat aléatoire).',
        'roomdimmer' => 'Variateur d\'ambiance (Moodlight) : règle la lumière de la salle.',
        'wall_item' => 'Se pose sur le MUR (et non au sol).',
        'custom_data_true_false' => 'A deux états vrai/faux (ex. allumé/éteint).',
        'custom_data_on_off' => 'A deux états on/off.',
        'custom_data_numeric_on_off' => 'État on/off stocké en nombre.',
        'custom_data_numeric_state' => 'Plusieurs états numériques (ex. plusieurs visuels).',
    ];
}
/* Décompose la chaîne behaviour en flags connus / inconnus. */
function furni_behaviour_parse(string $b): array {
    $flags = array_values(array_filter(array_map('trim', explode(',', $b)), fn($x) => $x !== ''));
    $dict = furni_behaviour_dict(); $known = []; $unknown = [];
    foreach ($flags as $f) {
        if (isset($dict[$f])) $known[$f] = $dict[$f];
        elseif (preg_match('/^redirect_rotation_(\d)$/', $f, $m)) $known[$f] = 'Téléporteur : sortie orientée ' . $m[1];
        else $unknown[] = $f;
    }
    return ['flags' => $flags, 'known' => $known, 'unknown' => $unknown];
}
/* Rendu en puces lisibles (max = nombre de puces avant « +N »). */
function furni_behaviour_chips(string $b, int $max = 0): string {
    $p = furni_behaviour_parse($b);
    if (!$p['flags']) return '<span class="muted sm">—</span>';
    $chip = 'display:inline-block;padding:1px 7px;margin:1px 3px 1px 0;border-radius:9px;font-size:11px;background:rgba(90,130,170,.18)';
    $bad  = 'display:inline-block;padding:1px 7px;margin:1px 3px 1px 0;border-radius:9px;font-size:11px;background:#5a2d2d;color:#ffd7d7';
    $o = ''; $n = 0;
    foreach ($p['known'] as $f => $lbl) { if ($max && $n >= $max) break; $o .= '<span style="' . $chip . '" title="' . h($f) . '">' . h($lbl) . '</span>'; $n++; }
    foreach ($p['unknown'] as $f) { if ($max && $n >= $max) break; $o .= '<span style="' . $bad . '" title="comportement non reconnu">⚠ ' . h($f) . '</span>'; $n++; }
    $rest = count($p['flags']) - $n;
    if ($rest > 0) $o .= '<span class="muted sm">+' . $rest . '</span>';
    return $o;
}
/* Nombre d'offres catalogue par définition (une requête, mis en cache). */
function furni_catalogue_counts(): array {
    static $m = null;
    if ($m !== null) return $m;
    $m = [];
    try { foreach (db()->query('SELECT definition_id, COUNT(*) n FROM catalogue_items WHERE definition_id IS NOT NULL GROUP BY definition_id') as $r) $m[(int)$r['definition_id']] = (int)$r['n']; }
    catch (Throwable $e) {}
    return $m;
}
/* Querystring des paramètres de liste actifs (pour conserver recherche/filtres/tri/page au retour). */
function furni_list_qs(): string {
    $keep = [];
    foreach (['q', 'type', 'beh', 'cat', 'img', 'sort', 'dir', 'pg'] as $k) {
        $v = (string)($_GET[$k] ?? '');
        if ($v !== '') $keep[$k] = $v;
    }
    return http_build_query($keep);
}

/* --- Lot 2 : édition validée, versionnée, anti-concurrence --- */
/* Empreinte des champs éditables (détecte une modif concurrente entre ouverture et save). */
function furni_def_hash(array $r): string {
    return sha1(implode('|', [
        (string)$r['name'], (string)$r['description'], (string)$r['length'], (string)$r['width'],
        (string)$r['top_height'], (string)$r['behaviour'], (string)$r['interactor'],
        (string)$r['colour'], (string)$r['max_status'], (string)$r['is_tradable'], (string)$r['is_recyclable'],
    ]));
}
/* Flags autorisés = dictionnaire connu + rotations téléporteur + flags DÉJÀ présents sur ce meuble (on préserve les valeurs particulières). */
function furni_behaviour_allowed(string $orig): array {
    $a = array_keys(furni_behaviour_dict());
    foreach (['redirect_rotation_0', 'redirect_rotation_2', 'redirect_rotation_4', 'redirect_rotation_6'] as $r) $a[] = $r;
    foreach (array_filter(array_map('trim', explode(',', $orig))) as $f) $a[] = $f;
    return array_values(array_unique($a));
}
/* Reconstruit la chaîne behaviour à partir des cases cochées, en préservant l'ordre d'origine
   et les flags particuliers déjà présents (validation serveur = liste blanche). */
function furni_behaviour_rebuild(string $orig, array $checked): string {
    $allowed = furni_behaviour_allowed($orig);
    $checked = array_values(array_intersect(array_map('strval', $checked), $allowed));
    $origFlags = array_values(array_filter(array_map('trim', explode(',', $orig)), fn($x) => $x !== ''));
    $out = [];
    foreach ($origFlags as $f) if (in_array($f, $checked, true)) $out[] = $f;     // garde l'ordre d'origine
    foreach ($checked as $f) if (!in_array($f, $out, true)) $out[] = $f;          // ajoute les nouveaux à la fin
    return implode(',', $out);
}
/* Différences champ par champ (comparaison en chaîne). */
function furni_diff(array $cur, array $new): array {
    $ch = [];
    foreach ($new as $k => $v) if ((string)($cur[$k] ?? '') !== (string)$v) $ch[$k] = ['old' => (string)($cur[$k] ?? ''), 'new' => (string)$v];
    return $ch;
}
function furni_field_label(string $f): string {
    return ['name' => 'Nom', 'description' => 'Description', 'length' => 'Longueur', 'width' => 'Largeur', 'top_height' => 'Hauteur', 'behaviour' => 'Comportement', 'interactor' => 'Interacteur', 'colour' => 'Couleur', 'max_status' => 'max_status', 'is_tradable' => 'Échangeable', 'is_recyclable' => 'Recyclable'][$f] ?? $f;
}
/* Journal des modifications de meubles (migration reproductible, SQL standard). */
function ensure_furni_log(): void {
    static $done = false; if ($done) return; $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS furni_edit_log (id INT AUTO_INCREMENT PRIMARY KEY, definition_id INT NOT NULL, author VARCHAR(64) NOT NULL DEFAULT '', changes MEDIUMTEXT NOT NULL, reason VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(definition_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function furni_log_change(int $defId, array $changes, string $reason): void {
    ensure_furni_log();
    db()->prepare('INSERT INTO furni_edit_log (definition_id,author,changes,reason) VALUES (?,?,?,?)')
        ->execute([$defId, (string)($_SESSION['admin']['username'] ?? '?'), json_encode($changes, JSON_UNESCAPED_UNICODE), substr($reason, 0, 255)]);
}
function furni_edit_history(int $defId, int $limit = 50): array {
    ensure_furni_log();
    $st = db()->prepare('SELECT * FROM furni_edit_log WHERE definition_id=? ORDER BY id DESC LIMIT ' . (int)$limit);
    $st->execute([$defId]); return $st->fetchAll();
}

/* --- Lot 3 : synchronisation noms/descriptions (définition / offres catalogue / textes client) --- */
/* Fichier des textes réellement chargés par le client (CP1252 + CRLF). */
function client_texts_path(): string { return DCR_DIR . '/external_texts.txt'; }
/* Clés client pour un sprite. Null si cas spécial (poster, sprite vide) qu'on ne déduit PAS du sprite. */
function furni_text_keys(string $sprite): ?array {
    $sprite = trim($sprite);
    if ($sprite === '' || strncmp($sprite, 'poster', 6) === 0) return null;
    return ['name' => 'furni_' . $sprite . '_name', 'desc' => 'furni_' . $sprite . '_desc'];
}
/* Valeur courante d'une clé (CP1252 -> UTF-8), ou null si absente. Remonte le nombre de doublons. */
function client_text_get(string $path, string $key, ?int &$dupes = null): ?string {
    $dupes = 0;
    $raw = @file_get_contents($path); if ($raw === false) return null;
    $val = null;
    foreach (explode("\n", $raw) as $ln) {
        $ln = rtrim($ln, "\r");
        if (strncmp($ln, $key . '=', strlen($key) + 1) === 0) { $dupes++; if ($val === null) $val = substr($ln, strlen($key) + 1); }
    }
    return $val === null ? null : mb_convert_encoding($val, 'UTF-8', 'Windows-1252');
}
/* Écrit une clé (UTF-8 -> CP1252), sauvegarde avant, vérifie après. Rapport honnête. CRLF préservé. */
function client_text_set(string $path, string $key, string $valueUtf8): array {
    $raw = @file_get_contents($path);
    if ($raw === false) return ['ok' => false, 'status' => 'fichier illisible'];
    $bak = $path . '.' . date('Ymd_His') . '.bak';
    if (@file_put_contents($bak, $raw) === false) return ['ok' => false, 'status' => 'sauvegarde impossible'];
    $cp = mb_convert_encoding($valueUtf8, 'Windows-1252', 'UTF-8');
    $lines = explode("\n", $raw);
    $found = false; $dupes = 0;
    foreach ($lines as $i => $ln) {
        $hasCr = (substr($ln, -1) === "\r"); $bare = $hasCr ? substr($ln, 0, -1) : $ln;
        if (strncmp($bare, $key . '=', strlen($key) + 1) === 0) { $dupes++; if (!$found) { $lines[$i] = $key . '=' . $cp . ($hasCr ? "\r" : ''); $found = true; } }
    }
    if (!$found) $out = rtrim($raw, "\r\n") . "\r\n" . $key . '=' . $cp . "\r\n";
    else $out = implode("\n", $lines);
    if (@file_put_contents($path, $out, LOCK_EX) === false) return ['ok' => false, 'status' => 'écriture échouée', 'backup' => basename($bak)];
    $check = @file_get_contents($path);
    $verified = $check !== false && strpos($check, "\n" . $key . '=' . $cp) !== false || ($check !== false && strncmp($check, $key . '=' . $cp, strlen($key . '=' . $cp)) === 0);
    return ['ok' => true, 'status' => $found ? 'mis à jour' : 'ajouté', 'verified' => (bool)$verified, 'dupes' => $dupes, 'backup' => basename($bak)];
}

function page_furni(): void {
    page_title('Meubles (définitions)', 'Gestionnaire de mobis — retrouver, consulter et régler un meuble');

    /* ================= FICHE (pleine largeur, à onglets) ================= */
    $fid = (int)($_GET['fiche'] ?? $_GET['edit'] ?? 0);
    if ($fid > 0) { furni_fiche($fid); return; }

    /* ================= LISTE ================= */
    $q    = trim((string)($_GET['q'] ?? ''));
    $type = in_array($_GET['type'] ?? '', ['floor', 'wall'], true) ? (string)$_GET['type'] : '';
    $cat  = in_array($_GET['cat'] ?? '', ['yes', 'no'], true) ? (string)$_GET['cat'] : '';
    $imgf = ($_GET['img'] ?? '') === 'missing' ? 'missing' : '';
    $beh  = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_GET['beh'] ?? '')));
    $sort = in_array($_GET['sort'] ?? '', ['id', 'name', 'sprite', 'size'], true) ? (string)$_GET['sort'] : 'id';
    $dir  = ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc';
    $pg   = max(1, (int)($_GET['pg'] ?? 1)); $off = ($pg - 1) * PER_PAGE;

    $where = []; $args = [];
    if ($q !== '') {
        if (ctype_digit($q)) { $where[] = '(name LIKE ? OR sprite LIKE ? OR id=?)'; $args[] = "%$q%"; $args[] = "%$q%"; $args[] = (int)$q; }
        else { $where[] = '(name LIKE ? OR sprite LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
    }
    if ($type === 'wall')  $where[] = "FIND_IN_SET('wall_item', behaviour)";
    if ($type === 'floor') $where[] = "NOT FIND_IN_SET('wall_item', behaviour)";
    if ($beh !== '')       { $where[] = 'FIND_IN_SET(?, behaviour)'; $args[] = $beh; }
    if ($cat === 'yes')    $where[] = 'id IN (SELECT definition_id FROM catalogue_items WHERE definition_id IS NOT NULL)';
    if ($cat === 'no')     $where[] = 'id NOT IN (SELECT definition_id FROM catalogue_items WHERE definition_id IS NOT NULL)';
    $wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $orderCol = ['id' => 'id', 'name' => 'name', 'sprite' => 'sprite', 'size' => '(length*width)'][$sort];

    $stmt = db()->prepare("SELECT id,sprite,name,length,width,top_height,behaviour FROM items_definitions $wsql ORDER BY $orderCol $dir, id $dir");
    $stmt->execute($args);
    $all = $stmt->fetchAll();
    if ($imgf === 'missing') $all = array_values(array_filter($all, fn($r) => furni_icon((string)$r['sprite']) === ''));
    $tot = count($all);
    $rows = array_slice($all, $off, PER_PAGE);
    $catCounts = furni_catalogue_counts();

    /* --- Barre de recherche + filtres (GET, conserve tout) --- */
    echo '<div class="panel"><div class="ph"><h3>🪑 Meubles <span class="muted sm">(' . $tot . ')</span></h3></div>';
    echo '<form method="get" class="row" style="gap:10px;flex-wrap:wrap;align-items:flex-end"><input type="hidden" name="p" value="furni">';
    echo '<label style="flex:1;min-width:180px">Recherche (nom, sprite, ou #ID)<input name="q" value="' . h($q) . '" placeholder="🔍 ex. tapis, carpet, 42"></label>';
    echo '<label>Type<select name="type"><option value=""' . ($type === '' ? ' selected' : '') . '>Tous</option><option value="floor"' . ($type === 'floor' ? ' selected' : '') . '>Sol</option><option value="wall"' . ($type === 'wall' ? ' selected' : '') . '>Mur</option></select></label>';
    echo '<label>Comportement<select name="beh"><option value="">Tous</option>';
    foreach (furni_behaviour_dict() as $f => $lbl) echo '<option value="' . h($f) . '"' . ($beh === $f ? ' selected' : '') . '>' . h($lbl) . '</option>';
    echo '</select></label>';
    echo '<label>Catalogue<select name="cat"><option value=""' . ($cat === '' ? ' selected' : '') . '>Tous</option><option value="yes"' . ($cat === 'yes' ? ' selected' : '') . '>Présent</option><option value="no"' . ($cat === 'no' ? ' selected' : '') . '>Absent</option></select></label>';
    echo '<label>Image<select name="img"><option value="">Toutes</option><option value="missing"' . ($imgf === 'missing' ? ' selected' : '') . '>Manquante</option></select></label>';
    echo '<input type="hidden" name="sort" value="' . h($sort) . '"><input type="hidden" name="dir" value="' . h($dir) . '">';
    echo '<button class="mini">Filtrer</button> <a class="mini ghost" href="?p=furni">Réinitialiser</a>';
    echo '</form>';

    /* --- En-têtes triables --- */
    $baseNoSort = array_filter(['q' => $q, 'type' => $type, 'beh' => $beh, 'cat' => $cat, 'img' => $imgf]);
    $sortLink = function (string $col, string $label) use ($baseNoSort, $sort, $dir) {
        $nd = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
        $arrow = $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
        return '<a class="lnk" href="?' . h(http_build_query($baseNoSort + ['p' => 'furni', 'sort' => $col, 'dir' => $nd])) . '">' . h($label) . $arrow . '</a>';
    };
    echo '<table class="clean"><tr><th>' . $sortLink('id', '#') . '</th><th>Image</th><th>' . $sortLink('name', 'Nom') . '</th><th>' . $sortLink('sprite', 'Sprite') . '</th><th>' . $sortLink('size', 'Dim.') . '</th><th>Comportement</th><th>Catalogue</th><th>Anomalies</th></tr>';
    $qsFiche = furni_list_qs();
    foreach ($rows as $r) {
        $id = (int)$r['id']; $sprite = (string)$r['sprite'];
        $hasImg = furni_icon($sprite) !== '';
        $parse = furni_behaviour_parse((string)$r['behaviour']);
        $nOffers = $catCounts[$id] ?? 0;
        $fiche = '?p=furni&fiche=' . $id . ($qsFiche !== '' ? '&ret=' . rawurlencode($qsFiche) : '');
        $anoms = [];
        if (!$hasImg) $anoms[] = '<span title="aucune miniature trouvée dans le pack" style="color:#e0a020">🖼️ image manquante</span>';
        if ($parse['unknown']) $anoms[] = '<span title="' . h(implode(', ', $parse['unknown'])) . '" style="color:#e06a5b">⚠ comportement inconnu</span>';
        if ((int)$r['length'] <= 0 || (int)$r['width'] <= 0) $anoms[] = '<span style="color:#e0a020">📐 dimension nulle</span>';
        echo '<tr>'
            . '<td class="sm muted">' . $id . '</td>'
            . '<td><a href="' . h($fiche) . '">' . furni_icon_img($sprite, 32) . '</a></td>'
            . '<td><a class="lnk" href="' . h($fiche) . '"><b>' . h((string)$r['name']) . '</b></a></td>'
            . '<td class="sm muted">' . h($sprite) . '</td>'
            . '<td class="sm">' . (int)$r['length'] . '×' . (int)$r['width'] . ($r['top_height'] !== null ? ' · h' . rtrim(rtrim(number_format((float)$r['top_height'], 2, '.', ''), '0'), '.') : '') . '</td>'
            . '<td>' . furni_behaviour_chips((string)$r['behaviour'], 2) . '</td>'
            . '<td class="sm">' . ($nOffers > 0 ? '<span style="color:#2bbf5b">✓ ' . $nOffers . ' offre' . ($nOffers > 1 ? 's' : '') . '</span>' : '<span class="muted">—</span>') . '</td>'
            . '<td class="sm">' . ($anoms ? implode(' ', $anoms) : '<span class="muted">—</span>') . '</td>'
            . '</tr>';
    }
    echo '</table>';
    pager($tot, $pg, '?p=furni' . ($baseNoSort ? '&' . http_build_query($baseNoSort) : '') . '&sort=' . $sort . '&dir=' . $dir);
    echo '<p class="hint">💡 Clique sur l\'image ou le nom pour ouvrir la fiche (l\'action « Donner à tous les joueurs » s\'y trouve désormais, par meuble). « Catalogue absent » ne veut pas forcément dire « à ajouter » : beaucoup d\'objets système/récompenses ne sont pas en vente.</p>';
    echo '</div>';
}

/* ================= Fiche meuble (pleine largeur, onglets) ================= */
function furni_fiche(int $id): void {
    $st = db()->prepare('SELECT * FROM items_definitions WHERE id=?'); $st->execute([$id]); $d = $st->fetch() ?: null;
    $ret = (string)($_GET['ret'] ?? '');
    $retUrl = '?p=furni' . ($ret !== '' ? '&' . $ret : '');
    if (!$d) {
        echo '<div class="panel"><div class="empty">Définition #' . $id . ' introuvable. <a class="lnk" href="' . h($retUrl) . '">← Retour</a></div></div>';
        return;
    }
    $sprite = (string)$d['sprite'];
    $hasImg = furni_icon($sprite) !== '';
    $offers = db()->prepare('SELECT * FROM catalogue_items WHERE definition_id=? ORDER BY is_hidden, id'); $offers->execute([$id]); $offers = $offers->fetchAll();
    $parse = furni_behaviour_parse((string)$d['behaviour']);

    /* --- En-tête --- */
    echo '<div class="panel"><div class="ph"><h3>' . furni_icon_img($sprite, 48) . ' ' . h((string)$d['name']) . ' <span class="muted sm">#' . $id . '</span></h3><a class="lnk" href="' . h($retUrl) . '" style="margin-left:auto">← Retour à la liste</a></div>';
    echo '<div class="row" style="gap:16px;align-items:flex-start">';
    echo '<div style="flex:0 0 auto;text-align:center">';
    if ($hasImg) echo '<img src="' . h(furni_icon($sprite)) . '" alt="" style="width:96px;height:96px;object-fit:contain;image-rendering:pixelated;background:rgba(128,128,128,.1);border-radius:8px;padding:6px">';
    else echo '<div style="width:96px;height:96px;display:grid;place-items:center;background:rgba(128,128,128,.1);border-radius:8px;color:#999;font-size:11px;text-align:center">Visuel<br>indisponible</div>';
    echo '<div class="muted sm" style="margin-top:4px">icône catalogue</div></div>';
    echo '<div style="flex:1;min-width:220px">';
    echo '<p style="margin:0 0 6px">' . (trim((string)$d['description']) !== '' ? h((string)$d['description']) : '<span class="muted">(pas de description)</span>') . '</p>';
    echo '<p class="sm muted" style="margin:0">sprite : <code>' . h($sprite) . '</code> · sprite_id : ' . (int)$d['sprite_id'] . '</p>';
    echo '<p class="hint" style="margin-top:8px">⚠️ <b>Définition commune</b> : modifier ces valeurs peut affecter <b>tous les exemplaires</b> de ce meuble (posés et en inventaire).</p>';
    echo '</div></div>';

    /* --- Actions → Distribuer (repliées : moins mises en avant sur une fiche technique) --- */
    $nbUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo '<details style="margin-top:10px;border-top:1px dashed rgba(128,128,128,.25);padding-top:8px"><summary class="sm" style="cursor:pointer;font-weight:600">⚙️ Actions → Distribuer ce meuble</summary>';
    echo '<div style="margin-top:8px;display:flex;flex-direction:column;gap:8px">';
    echo '<form method="post" class="js" data-reload style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">' . csrf_field() . '<input type="hidden" name="action" value="furni_give_me"><input type="hidden" name="definition_id" value="' . $id . '">' . furni_icon_img($sprite, 24) . '<button class="mini">🎁 M\'en donner un exemplaire (mon compte)</button><span class="muted sm">pour tester — dans ta main, reconnexion requise</span></form>';
    echo '<form method="post" class="js" data-reload data-confirm="Distribuer « ' . h((string)$d['name']) . ' » à ' . $nbUsers . ' joueur(s) ? Chacun le recevra dans sa main (reconnexion requise)." style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">' . csrf_field() . '<input type="hidden" name="action" value="furni_all"><input type="hidden" name="definition_id" value="' . $id . '">' . furni_icon_img($sprite, 24) . '<button class="mini">🎁 Donner à TOUS les joueurs (' . $nbUsers . ')</button></form>';
    echo '</div></details>';

    /* --- Onglets --- */
    $tabs = ['carac' => '🔧 Caractéristiques', 'cata' => '🛒 Catalogue & variantes', 'exemplaires' => '📦 Exemplaires', 'diag' => '🩺 Diagnostic & tests', 'hist' => '🕑 Historique'];
    echo '<div class="ftabs" style="display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid rgba(128,128,128,.25);margin:12px 0 0">';
    foreach ($tabs as $k => $lbl) echo '<button type="button" class="ftab' . ($k === 'carac' ? ' on' : '') . '" data-ft="' . $k . '" style="border:0;background:none;color:inherit;padding:8px 12px;cursor:pointer;font:inherit;font-weight:600;white-space:nowrap;border-bottom:2px solid transparent">' . h($lbl) . '</button>';
    echo '</div>';

    /* Onglet Caractéristiques — éditeur unique (plus de tableau en double), aide à côté des réglages */
    echo '<div class="ftp" data-ftp="carac">';
    /* --- Éditeur complet (Lot 2) : validé, versionné, anti-concurrence --- */
    $origHash = furni_def_hash($d);
    $dict = furni_behaviour_dict();
    $curFlags = array_values(array_filter(array_map('trim', explode(',', (string)$d['behaviour'])), fn($x) => $x !== ''));
    $nbEx = 0; try { $ex = db()->prepare('SELECT COUNT(*) FROM items WHERE definition_id=?'); $ex->execute([$id]); $nbEx = (int)$ex->fetchColumn(); } catch (Throwable $e) {}
    $origData = h(json_encode([
        'name' => (string)$d['name'], 'description' => (string)$d['description'],
        'length' => (string)(int)$d['length'], 'width' => (string)(int)$d['width'],
        'top_height' => rtrim(rtrim(number_format((float)$d['top_height'], 2, '.', ''), '0'), '.'),
        'interactor' => (string)$d['interactor'], 'colour' => (string)$d['colour'], 'max_status' => (string)$d['max_status'],
        'is_tradable' => $d['is_tradable'] ? '1' : '0', 'is_recyclable' => $d['is_recyclable'] ? '1' : '0',
        'behaviour' => implode(',', $curFlags),
    ], JSON_UNESCAPED_UNICODE));
    echo '<form method="post" class="js" data-reload id="furniform" data-orig="' . $origData . '" style="margin-top:6px">' . csrf_field();
    echo '<input type="hidden" name="action" value="furni_def_save"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="orig_hash" value="' . h($origHash) . '">';
    echo '<h4 style="margin:0 0 8px">✏️ Modifier ce meuble</h4>';
    echo '<label>Nom<input name="name" value="' . h((string)$d['name']) . '" required></label>';
    echo '<label style="margin-top:8px">Description<input name="description" value="' . h((string)$d['description']) . '"></label>';
    echo '<div class="row" style="margin-top:8px"><label>Longueur (cases)<input type="number" min="0" name="length" value="' . (int)$d['length'] . '" style="width:120px"></label><label>Largeur (cases)<input type="number" min="0" name="width" value="' . (int)$d['width'] . '" style="width:120px"></label><label>Hauteur (top_height)<input type="number" step="0.01" min="0" name="top_height" value="' . h(rtrim(rtrim(number_format((float)$d['top_height'], 2, '.', ''), '0'), '.')) . '" style="width:130px"></label></div>';
    echo '<p class="sm muted" style="margin:4px 0 0">📏 Avatar mal posé (assis/couché enfoncé ou flottant) ? La bonne hauteur est sur <a href="https://habbohistory.com/furniture" target="_blank" rel="noopener">habbohistory.com/furniture</a> (ligne « Height: X.XX ») → recopie-la ici.</p>';
    echo '<p class="sm muted" style="margin:12px 0 4px">Comportement — <b>coche ce qui s\'applique</b> (survole une case pour son effet). Le résumé en clair est juste en dessous.</p>';
    $help = furni_behaviour_help();
    $groups = [
        '🚶 Position & praticabilité' => ['solid', 'can_sit_on_top', 'can_lay_on_top', 'can_stand_on_top', 'can_stack_on_top', 'place_roller_on_top'],
        '🖐️ Interaction' => ['requires_rights_for_interaction', 'requires_touching_for_interaction', 'gate', 'custom_data_true_false', 'custom_data_on_off', 'custom_data_numeric_on_off', 'custom_data_numeric_state'],
        '✨ Objets spéciaux' => ['roller', 'teleporter', 'prize_trophy', 'present', 'redeemable', 'dice', 'wheel_of_fortune'],
        '🎵 Son, média & mur' => ['sound_machine', 'sound_machine_sample_set', 'song_disk', 'jukebox', 'photo', 'post_it', 'decoration', 'roomdimmer', 'wall_item'],
    ];
    $rendered = [];
    $cb = function (string $f, string $lbl, bool $checked, bool $special = false) use ($help) {
        $tip = $help[$f] ?? ($special ? 'Comportement particulier conservé tel quel.' : '');
        return '<label title="' . h($tip) . '" style="display:flex;flex-direction:row;align-items:center;gap:6px;padding:2px 0;font-weight:normal;cursor:help"><input type="checkbox" name="beh[]" value="' . h($f) . '"' . ($checked ? ' checked' : '') . '> <span>' . ($special ? '⚙ ' : '') . h($lbl) . ' <span class="muted sm">(' . h($f) . ')</span></span></label>';
    };
    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px 18px">';
    foreach ($groups as $gname => $flags) {
        echo '<div style="border:1px solid rgba(128,128,128,.18);border-radius:8px;padding:8px 10px"><div class="sm" style="font-weight:700;margin-bottom:4px;opacity:.8">' . h($gname) . '</div>';
        foreach ($flags as $f) { if (!isset($dict[$f])) continue; $rendered[$f] = 1; echo $cb($f, $dict[$f], in_array($f, $curFlags, true)); }
        echo '</div>';
    }
    $extra = '';
    foreach ($dict as $f => $lbl) { if (isset($rendered[$f])) continue; $rendered[$f] = 1; $extra .= $cb($f, $lbl, in_array($f, $curFlags, true)); }
    foreach ($curFlags as $f) { if (isset($dict[$f])) continue; $extra .= $cb($f, $f, true, true); }
    if ($extra !== '') echo '<div style="border:1px solid rgba(128,128,128,.18);border-radius:8px;padding:8px 10px"><div class="sm" style="font-weight:700;margin-bottom:4px;opacity:.8">🔩 Autres / spécifiques</div>' . $extra . '</div>';
    echo '</div>';
    /* Résumé « ce que fait ce meuble » (rempli en direct par JS) + données d'aide */
    echo '<div id="behsummary" data-help=\'' . h(json_encode($help, JSON_UNESCAPED_UNICODE)) . '\' style="margin-top:8px;padding:8px 10px;background:rgba(90,130,170,.10);border-radius:8px;font-size:12px"></div>';
    echo '<p class="hint">⚠️ Cocher <b>Téléporteur</b>, <b>Jukebox</b>, <b>Trax</b>… ne suffit <b>pas</b> à rendre un meuble fonctionnel : il faut aussi le bon sprite côté client. À <b>tester en jeu</b>.</p>';
    echo '<div class="row" style="margin-top:4px"><label style="flex-direction:row;align-items:center"><input type="checkbox" name="is_tradable"' . ($d['is_tradable'] ? ' checked' : '') . '> Échangeable</label><label style="flex-direction:row;align-items:center"><input type="checkbox" name="is_recyclable"' . ($d['is_recyclable'] ? ' checked' : '') . '> Recyclable</label></div>';
    echo '<details style="margin-top:8px"><summary class="sm muted">⚙️ Paramètres techniques avancés</summary><div class="row" style="margin-top:6px"><label>Interacteur<input name="interactor" value="' . h((string)$d['interactor']) . '" style="width:170px"></label><label>Couleur (RVB)<input name="colour" value="' . h((string)$d['colour']) . '" style="width:130px"></label><label>max_status<input name="max_status" value="' . h((string)$d['max_status']) . '" style="width:90px"></label></div><p class="hint">Champs techniques : ne change que si tu sais ce que tu fais.</p></details>';
    echo '<label style="margin-top:8px">Motif de la modification (facultatif)<input name="reason" maxlength="255" placeholder="ex. correction de la hauteur d\'assise"></label>';
    echo '<div style="margin-top:10px;padding:8px 10px;background:rgba(224,160,32,.12);border-radius:6px;font-size:12px">⚠️ <b>Impact</b> : définition partagée par <b>' . $nbEx . '</b> exemplaire(s) (posés + inventaire) et <b>' . count($offers) . '</b> offre(s) catalogue. Les changements s\'appliquent à <b>tous</b>.</div>';
    echo '<div id="furnidiffbox" style="display:none;margin-top:10px;padding:8px 10px;border:1px solid rgba(90,130,170,.4);border-radius:8px;font-size:12px"></div>';
    echo '<div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
    echo '<button type="button" id="furnidiffbtn" class="mini ghost">👁 Voir les modifications</button>';
    echo '<button>💾 Enregistrer</button>';
    echo '<span class="muted sm">Enregistré en base + historique. ⚙️ Redémarrage émulateur pour l\'affichage en jeu. Le nom affiché en jeu se synchronise dans l\'onglet « Catalogue & variantes ».</span>';
    echo '</div></form>';
    echo '</div>';

    /* Onglet Catalogue & variantes */
    echo '<div class="ftp" data-ftp="cata" style="display:none">';
    /* --- Noms & textes (Lot 3) : synchro définition / offres / texte client --- */
    $tkeys = furni_text_keys($sprite);
    $txtPath = client_texts_path();
    $dupN = 0; $dupD = 0;
    $curTxtName = $tkeys ? client_text_get($txtPath, $tkeys['name'], $dupN) : null;
    $curTxtDesc = $tkeys ? client_text_get($txtPath, $tkeys['desc'], $dupD) : null;
    echo '<div style="margin-top:10px;border:1px solid rgba(128,128,128,.25);border-radius:8px;padding:10px">';
    echo '<h4 style="margin:0 0 6px">✏️ Nom &amp; description — synchronisation</h4>';
    echo '<p class="sm muted" style="margin:0 0 8px">Le nom existe à 3 endroits distincts. Choisis la portée : renommer l\'offre ne change pas le meuble posé, etc.</p>';
    echo '<table class="clean"><tr><th>Emplacement</th><th>Nom actuel</th><th>Description actuelle</th></tr>';
    echo '<tr><td>Définition interne</td><td>' . h((string)$d['name']) . '</td><td class="sm muted">' . h((string)$d['description']) . '</td></tr>';
    foreach ($offers as $o) echo '<tr><td>Offre #' . (int)$o['id'] . '</td><td>' . h((string)$o['name']) . '</td><td class="sm muted">' . h((string)$o['description']) . '</td></tr>';
    if ($tkeys) echo '<tr><td>Texte client<br><code class="sm">' . h($tkeys['name']) . '</code></td><td>' . ($curTxtName !== null ? h($curTxtName) : '<span class="muted">(clé absente)</span>') . ($dupN > 1 ? ' <span style="color:#e06a5b">⚠' . $dupN . ' doublons</span>' : '') . '</td><td class="sm muted">' . ($curTxtDesc !== null ? h($curTxtDesc) : '(absente)') . '</td></tr>';
    else echo '<tr><td>Texte client</td><td colspan="2" class="sm muted">⚠️ Poster / sprite spécial : la clé client (<code>poster_&lt;numéro&gt;</code>) ne se déduit pas du sprite — à gérer à la main.</td></tr>';
    echo '</table>';
    echo '<form method="post" class="js" data-reload data-confirm="Appliquer le nom/description aux emplacements cochés ?" style="margin-top:10px">' . csrf_field() . '<input type="hidden" name="action" value="furni_name_sync"><input type="hidden" name="id" value="' . $id . '">';
    echo '<div class="row"><label style="flex:1">Nouveau nom<input name="new_name" value="' . h((string)$d['name']) . '"></label><label style="flex-direction:row;align-items:center;margin-top:18px;gap:5px"><input type="checkbox" name="apply_name" checked> appliquer le nom</label></div>';
    echo '<div class="row" style="margin-top:6px"><label style="flex:1">Nouvelle description<input name="new_desc" value="' . h((string)$d['description']) . '"></label><label style="flex-direction:row;align-items:center;margin-top:18px;gap:5px"><input type="checkbox" name="apply_desc"> appliquer la description</label></div>';
    echo '<p class="sm muted" style="margin:8px 0 4px">Portée (où appliquer) :</p><div style="display:flex;flex-wrap:wrap;gap:6px 16px">';
    echo '<label style="flex-direction:row;align-items:center;gap:5px"><input type="checkbox" name="sc_def" checked> Définition interne</label>';
    foreach ($offers as $o) echo '<label style="flex-direction:row;align-items:center;gap:5px"><input type="checkbox" name="sc_offer[]" value="' . (int)$o['id'] . '" checked> Offre #' . (int)$o['id'] . '</label>';
    if ($tkeys) echo '<label style="flex-direction:row;align-items:center;gap:5px"><input type="checkbox" name="sc_text" checked> Texte client (nom affiché en jeu)</label>';
    echo '</div>';
    echo '<button style="margin-top:10px">🔗 Appliquer aux emplacements cochés</button></form>';
    echo '<p class="hint">💾 Le texte client est <b>sauvegardé</b> avant modif et <b>vérifié</b> après écriture (CP1252 préservé). En jeu : <b>vide le cache Basilisk</b> côté joueur (pas de redémarrage émulateur).</p>';
    echo '</div>';
    echo '<h4 style="margin:16px 0 4px">🛒 Offres au catalogue</h4>';
    if (!$offers) echo '<div class="empty" style="margin-top:10px">Ce meuble n\'a aucune offre au catalogue. (Ce n\'est pas forcément une anomalie : objets système, récompenses…)</div>';
    else {
        echo '<table class="clean" style="margin-top:10px"><tr><th>#</th><th>Nom de l\'offre</th><th>Page</th><th>Prix</th><th>Qté</th><th>Visible</th><th>sale_code</th><th></th></tr>';
        foreach ($offers as $o) {
            echo '<tr><td class="sm muted">' . (int)$o['id'] . '</td><td><b>' . h((string)$o['name']) . '</b>' . (trim((string)$o['description']) !== '' ? '<div class="sm muted">' . h((string)$o['description']) . '</div>' : '') . '</td>'
                . '<td class="sm">' . h((string)$o['page_id']) . '</td><td class="sm">' . (int)$o['price'] . '</td><td class="sm">' . (int)$o['amount'] . '</td>'
                . '<td class="sm">' . ($o['is_hidden'] ? '<span class="muted">caché</span>' : '✅') . '</td>'
                . '<td class="sm muted"><code>' . h((string)$o['sale_code']) . '</code></td>'
                . '<td><a class="mini ghost" href="?p=catalogue">Catalogue</a></td></tr>';
        }
        echo '</table>';
    }
    echo '<p class="hint">Les offres partagent cette définition. Renommer l\'offre (catalogue) ≠ renommer la définition ≠ renommer le texte client. La synchronisation guidée arrive au Lot 3.</p>';
    echo '</div>';

    /* Onglet Exemplaires (Lot 4) : inventaire (room_id=0) vs posés (room_id<>0) */
    echo '<div class="ftp" data-ftp="exemplaires" style="display:none">';
    $exTot = 0; $exInv = 0; $exPlaced = 0; $exRows = [];
    try {
        $exTot = (int)(function () use ($id) { $s = db()->prepare('SELECT COUNT(*) FROM items WHERE definition_id=?'); $s->execute([$id]); return $s->fetchColumn(); })();
        $s = db()->prepare('SELECT COUNT(*) FROM items WHERE definition_id=? AND room_id=0'); $s->execute([$id]); $exInv = (int)$s->fetchColumn();
        $exPlaced = $exTot - $exInv;
        $s = db()->prepare('SELECT i.room_id, i.user_id, u.username, r.name AS roomname FROM items i LEFT JOIN users u ON u.id=i.user_id LEFT JOIN rooms r ON r.id=i.room_id WHERE i.definition_id=? ORDER BY i.room_id LIMIT 50');
        $s->execute([$id]); $exRows = $s->fetchAll();
    } catch (Throwable $e) {}
    echo '<div class="grid" style="margin-top:10px">';
    echo '<div class="stat blue"><div class="ic">📦</div><div><div class="num">' . $exTot . '</div><div class="lbl">Exemplaires au total</div></div></div>';
    echo '<div class="stat blue"><div class="ic">🎒</div><div><div class="num">' . $exInv . '</div><div class="lbl">En inventaire / main</div></div></div>';
    echo '<div class="stat blue"><div class="ic">🏠</div><div><div class="num">' . $exPlaced . '</div><div class="lbl">Posés en appartement</div></div></div>';
    echo '</div>';
    if (!$exRows) echo '<div class="empty" style="margin-top:8px">Aucun exemplaire de ce meuble en jeu.</div>';
    else {
        echo '<table class="clean" style="margin-top:8px"><tr><th>Propriétaire</th><th>Emplacement</th></tr>';
        foreach ($exRows as $r) {
            $loc = ((int)$r['room_id'] === 0) ? '<span class="muted">Inventaire / main</span>' : ('🏠 ' . h((string)($r['roomname'] ?? '')) . ' <span class="muted sm">#' . (int)$r['room_id'] . '</span>');
            echo '<tr><td>' . h((string)($r['username'] ?? ('#' . (int)$r['user_id']))) . '</td><td>' . $loc . '</td></tr>';
        }
        echo '</table>';
        if ($exTot > 50) echo '<p class="hint">Affiche les 50 premiers sur ' . $exTot . '.</p>';
    }
    echo '<p class="hint">⚠️ Changer la <b>définition</b> affecte tous ces exemplaires. Déplacer/supprimer un <b>exemplaire précis</b> se fait depuis la salle/le joueur concerné — un changement de définition ne déplace jamais les objets existants.</p>';
    echo '</div>';

    /* Onglet Diagnostic (auto, honnête) */
    echo '<div class="ftp" data-ftp="diag" style="display:none">';
    $checks = [];
    $checks[] = $hasImg ? ['ok', 'Miniature présente dans le pack.'] : ['warn', 'Aucune miniature trouvée (icône catalogue). À vérifier dans le pack.'];
    $checks[] = $parse['unknown'] ? ['warn', 'Comportement(s) non reconnu(s) : ' . h(implode(', ', $parse['unknown'])) . '.'] : ['ok', 'Tous les comportements sont reconnus.'];
    $checks[] = ((int)$d['length'] > 0 && (int)$d['width'] > 0) ? ['ok', 'Dimensions renseignées.'] : ['warn', 'Dimension nulle (length/width = 0) — à contrôler.'];
    $checks[] = $offers ? ['ok', count($offers) . ' offre(s) au catalogue.'] : ['info', 'Aucune offre au catalogue (normal pour objets système/récompenses).'];
    /* Cohérence de la traduction client (nom affiché en jeu) */
    $tkDiag = furni_text_keys($sprite);
    if ($tkDiag === null) $checks[] = ['info', 'Poster/sprite spécial : traduction client à gérer à la main (clé poster_&lt;numéro&gt;).'];
    else {
        $ctName = client_text_get(client_texts_path(), $tkDiag['name']);
        if ($ctName === null) $checks[] = ['warn', 'Traduction client absente (clé <code>' . h($tkDiag['name']) . '</code> introuvable) : le nom affiché en jeu peut différer de la définition.'];
        elseif (trim($ctName) !== trim((string)$d['name'])) $checks[] = ['warn', 'Nom incohérent : définition « ' . h((string)$d['name']) . ' » ≠ texte client « ' . h($ctName) . ' » (onglet Catalogue → synchroniser).'];
        else $checks[] = ['ok', 'Nom cohérent entre la définition et le texte client.'];
    }
    /* Indice hauteur vs type (HYPOTHÈSE à vérifier en jeu, pas un bug confirmé) */
    $isFloorWalkable = in_array('can_stand_on_top', $parse['flags'], true) && !in_array('can_sit_on_top', $parse['flags'], true) && !in_array('can_lay_on_top', $parse['flags'], true);
    if ($isFloorWalkable && (float)$d['top_height'] > 0) $checks[] = ['warn', 'Objet de sol praticable avec une hauteur de ' . h(rtrim(rtrim(number_format((float)$d['top_height'], 2, '.', ''), '0'), '.')) . ' > 0 : <b>peut</b> créer une « marche » en jeu (à vérifier — un tapis au sol est généralement à 0). Compare avec un tapis de référence avant de changer.'];
    echo '<ul style="list-style:none;padding:0;margin:10px 0">';
    foreach ($checks as $c) { $ic = $c[0] === 'ok' ? '✅' : ($c[0] === 'warn' ? '⚠️' : 'ℹ️'); echo '<li style="padding:4px 0">' . $ic . ' ' . $c[1] . '</li>'; }
    echo '</ul>';
    echo '<p class="hint">🔎 Ce sont des <b>contrôles automatiques</b> (sur les données), <b>pas</b> une validation en jeu. Les tests en salle (aperçu → achat → pose → rotations → interaction) viendront au Lot 4.</p>';
    echo '</div>';

    /* Onglet Historique (Lot 2) */
    echo '<div class="ftp" data-ftp="hist" style="display:none">';
    $hist = furni_edit_history($id);
    if (!$hist) echo '<div class="empty" style="margin-top:10px">Aucune modification enregistrée pour ce meuble.</div>';
    else {
        echo '<table class="clean" style="margin-top:10px"><tr><th>Date</th><th>Par</th><th>Modifications</th><th>Motif</th><th></th></tr>';
        foreach ($hist as $lg) {
            $chg = json_decode((string)$lg['changes'], true) ?: [];
            $lines = [];
            foreach ($chg as $f => $ab) $lines[] = '<b>' . h(furni_field_label($f)) . '</b> : <span class="muted" style="text-decoration:line-through">' . h(mb_strimwidth((string)($ab['old'] ?? ''), 0, 40, '…')) . '</span> → ' . h(mb_strimwidth((string)($ab['new'] ?? ''), 0, 40, '…'));
            echo '<tr><td class="sm muted">' . h(date('d/m/Y H:i', strtotime((string)$lg['created_at']))) . '</td><td class="sm">' . h((string)$lg['author']) . '</td><td class="sm">' . implode('<br>', $lines) . '</td><td class="sm muted">' . h((string)$lg['reason']) . '</td>';
            echo '<td><form method="post" class="js" data-reload data-confirm="Restaurer les valeurs AVANT cette modification ?" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="furni_def_restore"><input type="hidden" name="log_id" value="' . (int)$lg['id'] . '"><button class="mini ghost">↩️ Restaurer</button></form></td></tr>';
        }
        echo '</table>';
        echo '<p class="hint">« Restaurer » remet les valeurs <b>d\'avant</b> la modification choisie et crée une nouvelle entrée d\'historique. ⚙️ Redémarre l\'émulateur pour appliquer en jeu.</p>';
    }
    echo '</div>';

    echo '</div>'; /* .panel */
    ?>
<script>
(function(){
  var tabs=document.querySelectorAll('.ftab'), panes=document.querySelectorAll('.ftp');
  for(var i=0;i<tabs.length;i++){ tabs[i].onclick=function(){
    var k=this.getAttribute('data-ft');
    for(var j=0;j<tabs.length;j++){ tabs[j].classList.remove('on'); tabs[j].style.borderBottomColor='transparent'; }
    this.classList.add('on'); this.style.borderBottomColor='#6fb1ff';
    for(var p=0;p<panes.length;p++){ panes[p].style.display = panes[p].getAttribute('data-ftp')===k ? '' : 'none'; }
  }; }
  var first=document.querySelector('.ftab.on'); if(first) first.style.borderBottomColor='#6fb1ff';
})();
(function(){
  var f=document.getElementById('furniform'); if(!f) return;
  function esc(s){return String(s).replace(/[&<>]/g,function(c){return c==='&'?'&amp;':c==='<'?'&lt;':'&gt;';});}
  var sumEl=document.getElementById('behsummary'), help={};
  try{ help=JSON.parse((sumEl&&sumEl.getAttribute('data-help'))||'{}'); }catch(e){}
  function updSummary(){
    if(!sumEl) return;
    var ch=[].slice.call(f.querySelectorAll('input[name="beh[]"]:checked'));
    if(!ch.length){ sumEl.innerHTML='<b>Ce meuble ne fait rien de spécial</b> (aucun comportement coché).'; return; }
    var s='<b>Ce meuble fait :</b><ul style="margin:4px 0 0;padding-left:18px">';
    ch.forEach(function(c){ s+='<li>'+(help[c.value]?esc(help[c.value]):esc(c.value))+'</li>'; });
    sumEl.innerHTML=s+'</ul>';
  }
  f.addEventListener('change',function(e){ if(e.target&&e.target.name==='beh[]') updSummary(); });
  updSummary();
  var btn=document.getElementById('furnidiffbtn'), box=document.getElementById('furnidiffbox');
  var L={name:'Nom',description:'Description',length:'Longueur',width:'Largeur',top_height:'Hauteur',interactor:'Interacteur',colour:'Couleur',max_status:'max_status',is_tradable:'Échangeable',is_recyclable:'Recyclable',behaviour:'Comportement'};
  if(btn){ btn.onclick=function(){
    var orig={}; try{ orig=JSON.parse(f.getAttribute('data-orig')||'{}'); }catch(e){}
    var ch=[];
    ['name','description','length','width','top_height','interactor','colour','max_status'].forEach(function(n){
      var el=f.querySelector('[name="'+n+'"]'); if(!el) return; var v=String(el.value).trim();
      if(String(orig[n]==null?'':orig[n])!==v) ch.push([L[n],orig[n],v]);
    });
    ['is_tradable','is_recyclable'].forEach(function(n){
      var el=f.querySelector('input[name="'+n+'"]'); if(!el) return; var v=el.checked?'1':'0';
      if(String(orig[n]==null?'0':orig[n])!==v) ch.push([L[n],(orig[n]==='1'?'oui':'non'),(v==='1'?'oui':'non')]);
    });
    var curArr=[].slice.call(f.querySelectorAll('input[name="beh[]"]:checked')).map(function(x){return x.value;});
    var curSet=curArr.slice().sort().join(','), origSet=String(orig.behaviour||'').split(',').filter(Boolean).sort().join(',');
    if(curSet!==origSet) ch.push([L.behaviour,(orig.behaviour||'(aucun)'),(curArr.join(',')||'(aucun)')]);
    box.style.display='';
    if(!ch.length){ box.innerHTML='Aucune modification pour l\'instant.'; return; }
    var html='<b>Modifications à enregistrer ('+ch.length+') :</b><table style="width:100%;margin-top:4px;border-collapse:collapse">';
    ch.forEach(function(c){ html+='<tr><td style="padding:2px 6px;vertical-align:top"><b>'+esc(c[0])+'</b></td><td style="padding:2px 6px;color:#c0392b;text-decoration:line-through">'+esc(c[1]||'(vide)')+'</td><td style="padding:2px 6px">→ <span style="color:#2b8a3e">'+esc(c[2]||'(vide)')+'</span></td></tr>'; });
    box.innerHTML=html+'</table>';
  }; }
})();
</script>
    <?php
}

function page_gamemaps(): void {
    page_title('Cartes de jeux', 'Arènes BattleBall / SnowStorm — avancé (redémarre l\'émulateur après modif)');
    if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM games_maps WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null;
        if ($edit) {
            $sp = db()->prepare('SELECT COUNT(*) FROM games_player_spawns WHERE map_id=? AND type=?'); $sp->execute([(int)$edit['map_id'], $edit['game_type']]);
            echo '<div class="panel"><div class="ph"><h3>✏️ ' . h($edit['game_type']) . ' — carte ' . h($edit['map_id']) . '</h3><a class="lnk" href="?p=gamemaps">← Retour</a></div>';
            echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="gmap_update"><input type="hidden" name="id" value="' . (int)$edit['id'] . '">';
            echo '<label>Heightmap (une rangée séparée par | )<textarea name="heightmap" rows="8" style="font-family:var(--mono);font-size:11px">' . h($edit['heightmap']) . '</textarea></label>';
            echo '<label style="margin-top:8px">Tile map<textarea name="tile_map" rows="8" style="font-family:var(--mono);font-size:11px">' . h($edit['tile_map']) . '</textarea></label>';
            echo '<button style="margin-top:10px">💾 Enregistrer</button></form><p class="hint">' . (int)$sp->fetchColumn() . ' points d\'apparition pour cette carte. ⚠️ Une carte mal formée peut empêcher le jeu de démarrer.</p></div>';
            return;
        }
    }
    $rows = db()->query('SELECT id,game_type,map_id FROM games_maps ORDER BY game_type,map_id')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🗺️ Cartes (' . count($rows) . ')</h3></div>';
    echo '<p class="muted sm">SnowStorm charge aussi des cartes depuis des fichiers ; ces cartes-ci concernent surtout BattleBall.</p>';
    echo '<table class="clean"><tr><th>Jeu</th><th>Carte</th><th></th></tr>';
    foreach ($rows as $r) echo '<tr><td>' . h($r['game_type']) . '</td><td>' . h($r['map_id']) . '</td><td><a class="mini ghost" href="?p=gamemaps&edit=' . (int)$r['id'] . '">Éditer</a></td></tr>';
    echo '</table></div>';
}

function page_models(): void {
    page_title('Modèles de salles', 'Plans des salles (porte + trigger) — avancé');
    if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM rooms_models WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null;
        if ($edit) {
            $triggers = ['flat_trigger', 'battleball_lobby_trigger', 'snowstorm_lobby_trigger', 'space_cafe_trigger', 'habbo_lido_trigger', 'rooftop_rumble_trigger', 'diving_deck_trigger', 'infobus_park', 'infobus_poll', 'none'];
            echo '<div class="panel"><div class="ph"><h3>✏️ ' . h((string)$edit['model_id']) . '</h3><a class="lnk" href="?p=models">← Retour</a></div><form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="model_update"><input type="hidden" name="id" value="' . (int)$edit['id'] . '">';
            echo '<div class="row"><label style="flex:1">Nom<input name="model_name" value="' . h((string)$edit['model_name']) . '"></label><label>Porte X<input type="number" name="door_x" value="' . (int)$edit['door_x'] . '" style="width:80px"></label><label>Porte Y<input type="number" name="door_y" value="' . (int)$edit['door_y'] . '" style="width:80px"></label><label>Porte Z<input name="door_z" value="' . h((string)$edit['door_z']) . '" style="width:80px"></label><label>Dir<input type="number" name="door_dir" value="' . (int)$edit['door_dir'] . '" style="width:70px"></label></div>';
            echo '<label style="margin-top:8px">Trigger<select name="trigger_class">'; foreach ($triggers as $t) echo '<option' . ($t === $edit['trigger_class'] ? ' selected' : '') . '>' . $t . '</option>'; echo '</select></label>';
            echo '<label style="margin-top:8px">Heightmap (lecture seule)<textarea rows="6" readonly style="font-family:var(--mono);font-size:11px">' . h((string)$edit['heightmap']) . '</textarea></label>';
            echo '<button style="margin-top:10px">💾 Enregistrer</button></form><p class="hint">Porte, direction, trigger et nom sont modifiables. La heightmap n\'est pas modifiée ici (édition risquée).</p></div>';
            return;
        }
    }
    $rows = db()->query('SELECT id,model_id,model_name,door_x,door_y,door_dir,trigger_class FROM rooms_models ORDER BY model_id')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🏗️ Modèles (' . count($rows) . ')</h3></div>';
    echo '<table class="clean"><tr><th>Model ID</th><th>Nom</th><th>Porte</th><th>Trigger</th><th></th></tr>';
    foreach ($rows as $r) echo '<tr><td><code>' . h((string)$r['model_id']) . '</code></td><td>' . h((string)$r['model_name']) . '</td><td class="sm">' . (int)$r['door_x'] . ',' . (int)$r['door_y'] . ' dir' . (int)$r['door_dir'] . '</td><td class="sm muted">' . h((string)$r['trigger_class']) . '</td><td><a class="mini ghost" href="?p=models&edit=' . (int)$r['id'] . '">Éditer</a></td></tr>';
    echo '</table></div>';
}

function page_dashboard(): void {
    $d = db();
    $users = (int)$d->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $rooms = (int)$d->query("SELECT COUNT(*) FROM rooms WHERE owner_id='0'")->fetchColumn();
    $priv = (int)$d->query("SELECT COUNT(*) FROM rooms WHERE owner_id<>'0'")->fetchColumn();
    $items = (int)$d->query('SELECT COUNT(*) FROM catalogue_items')->fetchColumn();
    $bans = (int)$d->query('SELECT COUNT(*) FROM users_bans')->fetchColumn();
    $economy = (int)$d->query('SELECT COALESCE(SUM(credits),0) FROM users')->fetchColumn();
    $online = $d->query("SELECT value FROM settings WHERE setting='players.online'")->fetchColumn();
    $emu = emu_running();
    page_title('Tableau de bord', 'Vue d\'ensemble de ton hôtel');

    /* --- Alerte : boîte de réception (messages joueurs + demandes MDP) --- */
    $inbox = inbox_pending();
    if ($inbox > 0) {
        echo '<a class="alertbar" href="?p=messages">💬 <b>' . $inbox . ' élément' . ($inbox > 1 ? 's' : '') . '</b> en attente dans la messagerie (messages / demandes de mot de passe) — clique pour traiter &raquo;</a>';
    }

    /* --- À TRAITER (en premier : ce qui demande une action du staff) --- */
    $todo = [];
    if (tab_allowed('homemod')) { try { $todo[] = ['🚩', (int)$d->query("SELECT COUNT(*) FROM home_reports WHERE status='new'")->fetchColumn(), 'Signalements Homes', '?p=homemod&sec=reports']; } catch (Throwable $e) {} }
    if (tab_allowed('messages') && function_exists('conv_unread_staff')) { try { $todo[] = ['💬', conv_unread_staff(), 'Conversations à répondre', '?p=messages&vue=assistance']; } catch (Throwable $e) {} }
    if (tab_allowed('messages')) { try { $todo[] = ['🔑', (int)$d->query("SELECT COUNT(*) FROM password_resets WHERE status='pending'")->fetchColumn(), 'Récupération de compte', '?p=messages&vue=recovery']; } catch (Throwable $e) {} }
    if (tab_allowed('messages')) { try { $todo[] = ['📨', (int)$d->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn(), "Anciens messages à reprendre", '?p=messages&vue=recovery']; } catch (Throwable $e) {} }
    // On ne garde que ce qui demande réellement une action (compteur > 0), le plus urgent d'abord.
    $todo = array_values(array_filter($todo, fn($t) => (int)$t[1] > 0));
    usort($todo, fn($a, $b) => (int)$b[1] - (int)$a[1]);
    echo '<h3 class="sec" style="margin:2px 0 10px">À traiter</h3>';
    if (!$todo) echo '<div class="panel"><div class="empty" style="padding:14px">✅ Rien à traiter pour le moment — tout est à jour 🎉</div></div>';
    else {
        echo '<div class="todo">';
        foreach ($todo as $t) echo '<a class="todocard urgent" href="' . $t[3] . '"><span class="tn">' . (int)$t[1] . '</span><span class="tl">' . $t[0] . ' ' . h($t[2]) . '</span></a>';
        echo '</div>';
    }

    /* --- Indicateurs utiles d'abord --- */
    echo '<div class="grid">';
    stat_box('🟢', $online !== false ? $online : '0', 'En ligne', 'green');
    stat_box('👥', $users, 'Comptes', 'blue');
    stat_box('🏠', $priv, 'Appartements', 'blue');
    stat_box('🏛️', $rooms, 'Salles publiques', 'blue');
    stat_box('💰', number_format($economy, 0, ',', ' '), 'Crédits en circulation', 'gold');
    stat_box($emu ? '🟢' : '🔴', $emu ? 'En marche' : 'Arrêté', 'Émulateur', $emu ? 'green' : 'red');
    echo '</div>';

    /* --- Activité (large) + Actions fréquentes (côté) --- */
    echo '<div class="dcols">';
    echo '<div class="panel"><div class="ph"><h3>🕓 Activité récente</h3><span class="muted sm" style="margin-left:auto">' . ($online !== false ? (int)$online : 0) . ' en ligne</span></div><table class="clean"><tr><th>Joueur</th><th>Rang</th><th>Dernière connexion</th></tr>';
    foreach ($d->query('SELECT username,rank,last_online FROM users WHERE last_online>0 ORDER BY last_online DESC LIMIT 10') as $u) {
        $ago = (int)$u['last_online']; $when = $ago > 0 ? date('d/m/Y H:i', $ago) : '—'; $recent = $ago > (time() - 300);
        echo '<tr><td><b>' . h($u['username']) . '</b>' . ($recent ? ' <span class="rk" style="background:#2bbf5b">actif</span>' : '') . '</td><td>' . rank_badge((int)$u['rank']) . '</td><td class="sm muted">' . $when . '</td></tr>';
    }
    echo '</table></div>';

    echo '<div class="panel"><div class="ph"><h3>⚡ Actions fréquentes</h3></div><div class="qa">';
    echo '<a class="qabtn" href="?p=users">➕<span>Créer un compte</span></a>';
    echo '<a class="qabtn" href="?p=search">🔍<span>Rechercher un joueur</span></a>';
    echo '<a class="qabtn" href="?p=sitemgr&sec=news&new=1">📰<span>Publier une actualité</span></a>';
    echo '<a class="qabtn" href="?p=alerts&sec=send">📢<span>Alerte à l\'hôtel</span></a>';
    echo '<a class="qabtn" href="?p=server">💾<span>Sauvegarder la base</span></a>';
    echo '</div>';
    echo '<form method="post" class="js" data-reload data-confirm="Donner des crédits à TOUS les joueurs ?" style="margin-top:12px;display:flex;align-items:flex-end">' . csrf_field() . '<input type="hidden" name="action" value="credits_all"><label style="flex:1">💰 Crédits à tous les joueurs<input type="number" name="delta" value="500"></label><button class="mini" style="margin-left:8px">Distribuer</button></form>';
    echo '</div></div>';

    /* --- Journal admin + bannissements --- */
    echo '<div class="cols">';
    // Aperçu du journal admin : SOUS le même contrôle d'accès que l'onglet Journal.
    if (tab_allowed('audit')) {
        echo '<div class="panel"><div class="ph"><h3>📜 Dernières actions admin</h3><a class="lnk" href="?p=audit">Voir tout →</a></div>';
        try {
            ensure_admin_log();
            $logs = $d->query('SELECT author,action,detail,created_at FROM admin_log ORDER BY id DESC LIMIT 8')->fetchAll();
            if (!$logs) echo '<div class="empty">Aucune action enregistrée.</div>';
            else { echo '<table class="clean"><tr><th>Quand</th><th>Qui</th><th>Action</th></tr>'; foreach ($logs as $l) echo '<tr><td class="sm muted" style="white-space:nowrap">' . h(date('d/m H:i', strtotime((string)$l['created_at']))) . '</td><td class="sm"><b>' . h($l['author']) . '</b></td><td class="sm">' . h(($l['detail'] ?? '') !== '' ? $l['detail'] : $l['action']) . '</td></tr>'; echo '</table>'; }
        } catch (Throwable $e) { echo '<div class="empty">Journal indisponible.</div>'; }
        echo '</div>';
    }
    echo '<div class="panel"><div class="ph"><h3>🚫 Bannissements récents</h3><a class="lnk" href="?p=moderation">Gérer →</a></div>';
    $br = $d->query('SELECT b.banned_value,b.message,b.ban_type,u.username FROM users_bans b LEFT JOIN users u ON (b.ban_type=\'USER_ID\' AND u.id=b.banned_value) ORDER BY b.banned_until DESC LIMIT 6')->fetchAll();
    if (!$br) echo '<div class="empty">Aucun bannissement 🎉</div>';
    else { echo '<table class="clean"><tr><th>Cible</th><th>Motif</th></tr>'; foreach ($br as $b) echo '<tr><td>' . h($b['ban_type'] === 'USER_ID' ? ('👤 ' . ($b['username'] ?? $b['banned_value'])) : $b['banned_value']) . '</td><td class="muted sm">' . h($b['message']) . '</td></tr>'; echo '</table>'; }
    echo '</div></div>';

    /* --- Classements (relégués après l'utile) --- */
    echo '<div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>🆕 Derniers inscrits</h3><a class="lnk" href="?p=users">Voir tout →</a></div><table class="clean"><tr><th>Nom</th><th>Rang</th><th>Crédits</th></tr>';
    foreach ($d->query('SELECT username,rank,credits FROM users ORDER BY id DESC LIMIT 6') as $u) echo '<tr><td><b>' . h($u['username']) . '</b></td><td>' . rank_badge((int)$u['rank']) . '</td><td>' . (int)$u['credits'] . '</td></tr>';
    echo '</table></div>';
    echo '<div class="panel"><div class="ph"><h3>🏆 Joueurs les plus riches</h3></div><table class="clean"><tr><th>Nom</th><th>Rang</th><th>Crédits</th></tr>';
    foreach ($d->query('SELECT username,rank,credits FROM users ORDER BY credits DESC LIMIT 6') as $u) echo '<tr><td><b>' . h($u['username']) . '</b></td><td>' . rank_badge((int)$u['rank']) . '</td><td>' . number_format((int)$u['credits'], 0, ',', ' ') . '</td></tr>';
    echo '</table></div></div>';
}

function page_rooms(): void {
    page_title('Salles & décors', 'Change les décors, active/désactive et édite les salles publiques');
    echo '<div class="warn" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">⚠️ Après un changement (décor / activation), <b>redémarre l\'émulateur</b> pour l\'appliquer en jeu.
        <form method="post" class="js" data-reload data-confirm="Redémarrer l\'émulateur maintenant ?" style="margin-left:auto"><input type="hidden" name="action" value="srv_restart">' . csrf_field() . '<button class="bigwarn">🔄 Redémarrer l\'émulateur</button></form></div>';
    $casts = available_casts();
    $cats = []; foreach (db()->query('SELECT id,name FROM rooms_categories') as $c) $cats[(int)$c['id']] = $c['name'];
    $rooms = db()->query("SELECT id,name,description,ccts,is_hidden,visitors_max,model,category FROM rooms WHERE owner_id='0' ORDER BY name")->fetchAll();

    // Décors de saison (item 8) — applique à toutes les salles en 1 clic
    $cur = current_season();
    echo '<div class="panel"><div class="ph"><h3>🗓️ Décors de saison</h3><span class="muted sm" style="margin-left:auto">Saison détectée : <b>' . season_label($cur) . '</b></span></div>';
    echo '<p class="muted sm">Applique le décor choisi à <b>toutes</b> les salles qui ont des variantes, en un clic. Redémarre l\'émulateur ensuite.</p><div class="seasons">';
    $seasons = ['' => '🏛️ Normal', 'xmas' => DECOR_LABELS['xmas'] ?? '🎄 Noël', 'halloween' => DECOR_LABELS['halloween'] ?? '🎃 Halloween', 'easter' => DECOR_LABELS['easter'] ?? '🐣 Pâques', 'summer' => DECOR_LABELS['summer'] ?? '☀️ Été', 'winter' => DECOR_LABELS['winter'] ?? '❄️ Hiver'];
    foreach ($seasons as $s => $lbl) {
        $hot = ($s === $cur) ? ' style="border-color:var(--acc);background:var(--soft)"' : '';
        echo '<form method="post" class="js" data-reload data-confirm="Appliquer « ' . h($lbl) . ' » à toutes les salles ?" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="decor_season"><input type="hidden" name="season" value="' . h($s) . '"><button class="season"' . $hot . '>' . h($lbl) . ($s === $cur ? ' • auto' : '') . '</button></form>';
    }
    echo '</div></div>';

    echo '<h3 class="sec">🎨 Salles à plusieurs décors — clic = appliqué (sans recharger)</h3><div class="decorwrap">';
    $any = false;
    foreach ($rooms as $r) {
        $base = explode(',', (string)$r['ccts'])[0]; $extra = substr((string)$r['ccts'], strlen($base));
        $variants = decor_variants($casts, $base);
        if (count($variants) <= 1) continue;
        $any = true;
        echo '<div class="panel"><div class="ph"><h3>🏛️ ' . h($r['name']) . '</h3><span class="tag">#' . (int)$r['id'] . '</span></div><div class="seasons">';
        foreach ($variants as $suffix => $cast) {
            $active = ($cast === $base) ? ' active' : '';
            echo '<form method="post" class="js" data-decor style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="room_decor"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="ccts" value="' . h($cast . $extra) . '"><button class="season' . $active . '"' . ($active ? ' disabled' : '') . '>' . h(decor_label($suffix)) . '</button></form>';
        }
        echo '</div></div>';
    }
    if (!$any) echo '<div class="empty">Aucune salle à décors multiples.</div>';
    echo '</div>';

    echo '<h3 class="sec">🏠 Toutes les salles publiques (' . count($rooms) . ')</h3>';
    echo '<div class="panel"><div class="ph"><h3>Liste</h3><input class="filt" style="margin-left:auto;min-width:220px" oninput="filt(this,\'roomtbl\')" placeholder="🔍 Filtrer une salle..."></div>';
    echo '<table class="clean" id="roomtbl"><tr><th>#</th><th>Nom</th><th>Modèle</th><th>Catégorie</th><th>État</th><th>Max</th><th style="text-align:right">Actions</th></tr>';
    foreach ($rooms as $r) {
        $hidden = (int)$r['is_hidden'] === 1;
        echo '<tr class="frow">
            <td class="muted">' . (int)$r['id'] . '</td>
            <td><b>' . h($r['name']) . '</b><div class="muted sm">' . h($r['description']) . '</div></td>
            <td class="muted sm"><code>' . h($r['model']) . '</code></td>
            <td class="muted sm">' . h($cats[(int)$r['category']] ?? '—') . '</td>
            <td><span class="rk ' . ($hidden ? 'red' : 'green') . '" id="st' . (int)$r['id'] . '">' . ($hidden ? 'Désactivée' : 'Active') . '</span></td>
            <td>' . (int)$r['visitors_max'] . '</td>
            <td style="text-align:right;white-space:nowrap">
                <form method="post" class="js" data-toggle="' . (int)$r['id'] . '" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="room_toggle"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="mini ' . ($hidden ? 'ok' : 'warn') . '">' . ($hidden ? 'Activer' : 'Désactiver') . '</button></form>
                <button class="mini ghost" onclick="tgl(\'re' . (int)$r['id'] . '\')">Éditer</button>
            </td></tr>
            <tr id="re' . (int)$r['id'] . '" class="drawer" style="display:none"><td colspan="7">
                <form method="post" class="js row">' . csrf_field() . '<input type="hidden" name="action" value="room_update"><input type="hidden" name="id" value="' . (int)$r['id'] . '">
                <label style="flex:1">Nom<input name="name" value="' . h($r['name']) . '"></label>
                <label style="flex:2">Description<input name="description" value="' . h($r['description']) . '"></label>
                <label>Max<input type="number" name="visitors_max" value="' . (int)$r['visitors_max'] . '" style="width:80px"></label>
                <button>💾 Enregistrer</button></form></td></tr>';
    }
    echo '</table></div>';
}

// Images d'en-tête disponibles dans le client (cast catalogue)
function headline_options(string $current): string {
    $list = ['', 'catal_fp_header', 'catalog_rares_headline1', 'catalog_spaces_headline1', 'catalog_bank_headline1', 'catalog_roller_headline1', 'catalog_doors_headline1', 'catalog_pet_headline1', 'catalog_pet_headline2', 'catalog_area_headline1', 'catalog_gothic_headline1', 'catalog_djshop_headline1', 'catalog_candy_headline1', 'catalog_asian_headline1', 'catalog_iced_headline1', 'catalog_lodge_headline1', 'catalog_plasto_headline1', 'catalog_pura_headline1', 'catalog_mode_headline1', 'catalog_extra_headline1', 'catalog_bath_headline1', 'catalog_plants_headline1', 'catalog_sports_headline1', 'catalog_rugs_headline1', 'catalog_gallery_headline1', 'catalog_flags_headline1', 'catalog_trophies_headline1', 'catalog_club_headline1', 'catalog_camera_headline1', 'catalog_exe_headline1', 'catalog_alh_headline2', 'catalog_romantique_headline1', 'catalog_gru_headline1', 'catalog_trx_header1', 'catalog_trx_header2', 'catalog_trx_header3', 'catalog_trx_header4', 'catalog_trx_header5', 'catalog_gifts_headline1', 'catalog_recycler_headline1'];
    if ($current !== '' && !in_array($current, $list, true)) $list[] = $current;
    $o = '';
    foreach ($list as $v) { $lbl = $v === '' ? '— aucune —' : $v; $o .= '<option value="' . h($v) . '"' . ($v === $current ? ' selected' : '') . '>' . h($lbl) . '</option>'; }
    return $o;
}
function page_select(string $name, int $current): string {
    $o = '<select name="' . h($name) . '" style="max-width:170px">';
    foreach (db()->query('SELECT id,name FROM catalogue_pages ORDER BY order_id,id') as $cp)
        $o .= '<option value="' . (int)$cp['id'] . '"' . ((int)$cp['id'] === $current ? ' selected' : '') . '>' . h($cp['name']) . ' (#' . (int)$cp['id'] . ')</option>';
    return $o . '</select>';
}
function page_catalogue(): void {
    page_title('Catalogue', 'Meubles, raretés, pages Club HC — noms, descriptions, ordre, en-têtes');

    // --- Éditeur complet d'une page ---
    if (isset($_GET['editpage'])) {
        $st = db()->prepare('SELECT * FROM catalogue_pages WHERE id=?'); $st->execute([(int)$_GET['editpage']]); $ep = $st->fetch() ?: null;
        if ($ep) {
            echo '<div class="panel"><div class="ph"><h3>✏️ Page : ' . h($ep['name']) . ' (#' . (int)$ep['id'] . ')</h3><a class="lnk" href="?p=catalogue">← Retour</a></div>';
            echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="cat_page"><input type="hidden" name="id" value="' . (int)$ep['id'] . '">';
            echo '<div class="row"><label style="flex:1">Nom (onglet)<input name="name" value="' . h($ep['name']) . '"></label><label>Ordre<input type="number" name="order_id" value="' . (int)$ep['order_id'] . '" style="width:80px"></label><label>Rang mini<input type="number" name="min_role" value="' . (int)$ep['min_role'] . '" style="width:70px"></label></div>';
            echo '<label style="margin-top:8px">Image d\'en-tête<select name="image_headline">' . headline_options((string)$ep['image_headline']) . '</select></label>';
            echo '<label style="margin-top:8px">Description (en-tête de la page)<textarea name="body" rows="5">' . h((string)$ep['body']) . '</textarea></label>';
            echo '<div class="row" style="margin-top:8px"><label style="flex-direction:row;align-items:center"><input type="checkbox" name="index_visible"' . ((int)$ep['index_visible'] ? ' checked' : '') . '> Visible au catalogue</label><label style="flex-direction:row;align-items:center"><input type="checkbox" name="is_club_only"' . ((int)$ep['is_club_only'] ? ' checked' : '') . '> Réservé HC</label></div>';
            echo '<button style="margin-top:10px">💾 Enregistrer</button></form><p class="hint">⚠️ Redémarre l\'émulateur après pour voir les changements en jeu (le catalogue est mis en cache au démarrage).</p></div>';
            return;
        }
    }

    // --- Éditeur d'un article ---
    if (isset($_GET['edititem'])) {
        $st = db()->prepare('SELECT * FROM catalogue_items WHERE id=?'); $st->execute([(int)$_GET['edititem']]); $ei = $st->fetch() ?: null;
        if ($ei) {
            $lbl = $ei['name'] !== '' ? $ei['name'] : $ei['sale_code'];
            echo '<div class="panel"><div class="ph"><h3>' . furni_icon_img((string)$ei['sale_code'], 48) . ' ' . h((string)$lbl) . '</h3><a class="lnk" href="?p=catalogue">← Retour</a></div>';
            echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="cat_item"><input type="hidden" name="id" value="' . (int)$ei['id'] . '">';
            echo '<div class="row"><label style="flex:1">Nom<input name="name" value="' . h((string)$ei['name']) . '"></label><label style="flex:1">Page' . page_select('page_id', (int)$ei['page_id']) . '</label></div>';
            echo '<label style="margin-top:8px">Description<input name="description" value="' . h((string)$ei['description']) . '"></label>';
            echo '<div class="row" style="margin-top:8px;align-items:flex-end"><label>Prix (crédits)<input type="number" name="price" value="' . (int)$ei['price'] . '" style="width:110px"></label><label>Quantité<input type="number" name="amount" value="' . (int)$ei['amount'] . '" style="width:90px"></label><label style="flex-direction:row;align-items:center"><input type="checkbox" name="is_hidden"' . ((int)$ei['is_hidden'] ? ' checked' : '') . '> Masqué au catalogue</label></div>';
            echo '<button style="margin-top:12px">💾 Enregistrer</button></form><p class="hint">sale_code : <code>' . h((string)$ei['sale_code']) . '</code> · ⚠️ Redémarre l\'émulateur pour voir les changements en jeu.</p></div>';
            return;
        }
    }

    echo '<details class="panel"><summary><b>📄 Pages du catalogue (' . (int)db()->query('SELECT COUNT(*) FROM catalogue_pages')->fetchColumn() . ')</b></summary>';
    echo '<p class="muted sm">Utilise ↑ ↓ pour <b>remonter/descendre</b> une catégorie · « Éditer » pour le nom, la description, l\'image d\'en-tête · « Visible » = affichée dans le catalogue.</p>';
    echo '<input class="filt" style="margin-bottom:10px;min-width:220px" oninput="filt(this,\'pagetbl\')" placeholder="🔍 Filtrer une page...">';
    echo '<table class="clean" id="pagetbl"><tr><th>Ordre</th><th>#</th><th>Nom</th><th>En-tête</th><th>Rang</th><th>Club</th><th>Visible</th><th></th></tr>';
    foreach (db()->query('SELECT id,name,order_id,min_role,is_club_only,index_visible,image_headline FROM catalogue_pages ORDER BY order_id,id') as $pg) {
        $pid = (int)$pg['id'];
        $up = '<form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="cat_page_move"><input type="hidden" name="id" value="' . $pid . '"><input type="hidden" name="dir" value="up"><button class="mini ghost" title="Monter">↑</button></form>';
        $dn = '<form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="cat_page_move"><input type="hidden" name="id" value="' . $pid . '"><input type="hidden" name="dir" value="down"><button class="mini ghost" title="Descendre">↓</button></form>';
        echo '<tr class="frow"><td class="sm muted" style="white-space:nowrap"><b style="display:inline-block;min-width:24px">' . (int)$pg['order_id'] . '</b>' . $up . $dn . '</td>'
            . '<td class="muted">' . $pid . '</td>'
            . '<td><b>' . h($pg['name']) . '</b></td>'
            . '<td style="text-align:center">' . ((string)$pg['image_headline'] !== '' ? '<span style="color:#3a7afe;font-size:15px" title="' . h((string)$pg['image_headline']) . '">●</span>' : '<span class="muted">—</span>') . '</td>'
            . '<td class="sm">' . (int)$pg['min_role'] . '</td>'
            . '<td style="text-align:center">' . ((int)$pg['is_club_only'] ? '⭐' : '') . '</td>'
            . '<td style="text-align:center">' . ((int)$pg['index_visible'] ? '✅' : '🚫') . '</td>'
            . '<td><a class="mini ghost" href="?p=catalogue&editpage=' . $pid . '">Éditer</a></td></tr>';
    }
    echo '</table></details>';

    // --- Édition en masse ---
    echo '<details class="panel"><summary><b>⚡ Édition en masse</b></summary>';
    echo '<p class="muted sm">Applique une action à TOUS les articles d\'une page (ou de tout le catalogue).</p>';
    $pgSel = '<select name="page_id"><option value="0">Tout le catalogue</option>';
    foreach (db()->query('SELECT id,name FROM catalogue_pages ORDER BY order_id,id') as $cp) $pgSel .= '<option value="' . (int)$cp['id'] . '">' . h($cp['name']) . '</option>';
    $pgSel .= '</select>';
    echo '<form method="post" class="js" data-reload data-confirm="Appliquer cette action en masse ?"><div class="row" style="align-items:flex-end">' . csrf_field() . '<input type="hidden" name="action" value="cat_bulk">';
    echo '<label>Cible' . $pgSel . '</label>';
    echo '<label>Action<select name="what"><option value="show">Rendre visible</option><option value="hide">Masquer</option><option value="price">Fixer le prix à…</option><option value="mult">Multiplier le prix par…</option></select></label>';
    echo '<label>Valeur<input name="value" value="1" style="width:90px"></label>';
    echo '<div><button class="mini">Appliquer</button></div></div></form>';
    echo '<p class="hint">« Fixer le prix » et « Multiplier » utilisent le champ Valeur (ex. 0.5 pour −50 %). Visible/Masquer ignorent la valeur.</p></details>';

    $q = trim($_GET['q'] ?? ''); $pg = cur_page(); $off = ($pg - 1) * PER_PAGE;
    echo '<div class="panel"><div class="ph"><h3>🪑 Articles</h3><form method="get" class="srch"><input type="hidden" name="p" value="catalogue"><input name="q" value="' . h($q) . '" placeholder="🔍 Rechercher..."><button class="mini">OK</button></form></div>';
    if ($q !== '') {
        $c = db()->prepare('SELECT COUNT(*) FROM catalogue_items WHERE name LIKE ? OR sale_code LIKE ?'); $c->execute(['%' . $q . '%', '%' . $q . '%']); $total = (int)$c->fetchColumn();
        $s = db()->prepare('SELECT ci.id,ci.name,ci.description,ci.sale_code,ci.price,ci.is_hidden,cp.name AS page FROM catalogue_items ci LEFT JOIN catalogue_pages cp ON cp.id=ci.page_id WHERE ci.name LIKE ? OR ci.sale_code LIKE ? ORDER BY ci.name LIMIT ' . PER_PAGE . ' OFFSET ' . $off); $s->execute(['%' . $q . '%', '%' . $q . '%']);
    } else { $total = (int)db()->query('SELECT COUNT(*) FROM catalogue_items')->fetchColumn();
        $s = db()->query('SELECT ci.id,ci.name,ci.description,ci.sale_code,ci.price,ci.is_hidden,cp.name AS page FROM catalogue_items ci LEFT JOIN catalogue_pages cp ON cp.id=ci.page_id ORDER BY ci.id LIMIT ' . PER_PAGE . ' OFFSET ' . $off); }
    echo '<p class="muted sm">Clique sur <b>Éditer</b> pour changer le nom, la description, la page ou le prix d\'un article. (Le nom du meuble en salle/inventaire se change dans « Meubles (défs) ».)</p>';
    echo '<table class="clean"><tr><th></th><th>Nom</th><th>Page</th><th>Prix</th><th>Visible</th><th></th></tr>';
    foreach ($s as $it) {
        $label = $it['name'] !== '' ? $it['name'] : '<span class="muted">(' . h((string)$it['sale_code']) . ')</span>';
        $desc = (string)$it['description'] !== '' ? '<div class="sm muted" style="max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . h((string)$it['description']) . '</div>' : '';
        echo '<tr class="frow"><td>' . furni_icon_img((string)$it['sale_code']) . '</td><td><b>' . $label . '</b>' . $desc . '</td>'
            . '<td class="sm muted">' . h((string)$it['page']) . '</td>'
            . '<td class="sm" style="white-space:nowrap">' . (int)$it['price'] . ' cr</td>'
            . '<td style="text-align:center">' . ((int)$it['is_hidden'] ? '🚫' : '✅') . '</td>'
            . '<td><a class="mini ghost" href="?p=catalogue&edititem=' . (int)$it['id'] . '">Éditer</a></td></tr>';
    }
    echo '</table>'; pager($total, $pg, '?p=catalogue' . ($q !== '' ? '&q=' . urlencode($q) : '')); echo '</div>';
}

function page_convert(): void {
    page_title('🔧 Convertir un meuble (SWF → CCT)', 'Récupère un meuble Flash officiel de Habbo et le convertit pour ton client Shockwave v14');
    $kit = 'C:\\laragon\\www\\HabboretroV14\\tools\\elias';
    $ready = file_exists($kit . '\\EliasApp.exe') && file_exists($kit . '\\ffdec\\ffdec-cli.exe');

    echo '<div class="panel"><p class="muted sm">Cet outil récupère le fichier Flash officiel d\'un meuble depuis <code>images.habbo.com</code>, le convertit en <code>.cct</code> (format de ton client) et l\'installe dans <code>hof_furni</code>. Souvent utile pour un meuble qui apparaît en <b>cube</b>.</p>';
    echo '<p class="muted sm">⚠️ À savoir : un fichier <code>.cct</code> <b>absent</b> ne signifie pas toujours « cube » (certaines ressources sont intégrées à un cast commun ou regroupées) ; un <code>.cct</code> <b>présent</b> ne prouve pas que le meuble s\'affiche/fonctionne ; et une <b>conversion réussie</b> = fichier généré, <b>pas</b> une compatibilité V14 validée. Seul un <b>test en jeu</b> confirme.</p>';
    echo $ready ? '<p class="rk green">✅ Convertisseur prêt (' . h($kit) . ')</p>' : '<p class="rk red">❌ Convertisseur introuvable dans ' . h($kit) . '</p>';
    echo '</div>';

    // Liste des cubes actuels
    $hof = dirname(__DIR__) . '/dcr/hof_furni';
    $special = ['poster', 'photo', 'post.it', 'floor', 'wallpaper', 'door', 'doorB', 'doorC', 'doorD', 'roomdimmer', 'pets0', 'pets1', 'pets2', 'nest', 'petfood1', 'petfood2', 'petfood3', 'petfood4', 'waterbowl', 'film', 'goodie1', 'goodie2'];
    $cubes = [];
    foreach (db()->query("SELECT DISTINCT sprite FROM items_definitions WHERE sprite IS NOT NULL AND sprite<>''") as $r) {
        $base = explode('*', (string)$r['sprite'])[0];
        if (in_array($base, $special, true)) continue;
        if (!file_exists($hof . '/hh_furni_xx_' . $base . '.cct')) $cubes[$base] = true;
    }
    echo '<details class="panel"' . (count($cubes) ? '' : ' ') . '><summary><b>🧊 Meubles sans fichier .cct individuel (' . count($cubes) . ')</b> — contrôle automatique</summary>';
    if ($cubes) { echo '<p class="muted sm"><b>Contrôlé automatiquement</b> : ces sprites n\'ont pas de <code>.cct</code> dédié dans <code>hof_furni</code>. Ça ne veut <b>pas</b> dire à coup sûr « cube » (la ressource peut être intégrée/regroupée ailleurs) — <b>à vérifier en jeu</b>. Si c\'est bien un cube, cherche le sprite sur <a href="https://images.habbo.com" target="_blank">images.habbo.com</a> et convertis-le ci-dessous.</p><div style="font-family:var(--mono);font-size:12px;line-height:1.8">' . h(implode('  ·  ', array_keys($cubes))) . '</div>'; }
    else { echo '<div class="empty">Tous les meubles ont un <code>.cct</code> individuel. ⚠️ Présence du fichier ≠ meuble fonctionnel en jeu (à tester).</div>'; }
    echo '</details>';

    // Formulaire de conversion
    echo '<div class="panel"><div class="ph"><h3>Convertir un meuble</h3></div>';
    echo '<form method="post" class="js" data-reload data-confirm="Lancer la conversion ? (quelques secondes)">' . csrf_field() . '<input type="hidden" name="action" value="furni_convert">';
    echo '<div class="row"><label style="flex:1">Sprite (nom exact du meuble)<input name="sprite" placeholder="ex : exe_wfall" required></label><label>Révision<input name="revision" placeholder="ex : 56746" required style="width:120px"></label></div>';
    echo '<div class="row" style="margin-top:8px"><label>Type<select name="ftype"><option value="s">Sol (au sol)</option><option value="i">Mural (au mur)</option></select></label><label>Longueur<input type="number" name="len" value="1" style="width:80px"></label><label>Largeur<input type="number" name="wid" value="1" style="width:80px"></label><label style="flex-direction:row;align-items:center;margin-top:20px"><input type="checkbox" name="mkdef"> Créer la définition si absente</label></div>';
    echo '<button style="margin-top:12px">🔧 Convertir & installer</button></form>';
    echo '<p class="hint">💡 La <b>révision</b> se trouve dans la furnidata de Habbo. URL testée : <code>https://images.habbo.com/dcr/hof_furni/&lt;révision&gt;/&lt;sprite&gt;.swf</code><br>⚠️ Une <b>conversion réussie</b> = le <code>.cct</code> a été généré et installé — ce n\'est <b>pas</b> une garantie que le meuble s\'affiche ou fonctionne en V14. <b>Teste en jeu</b> (vide d\'abord le cache de Basilisk). Certains meubles animés spéciaux (téléporteurs) s\'affichent mais gardent leurs limites de comportement côté client.</p></div>';

    // --- Décompilateur ProjectorRays (CCT -> CST editable pour Adobe Director) ---
    $pr = 'C:\\laragon\\www\\HabboretroV14\\tools\\projectorrays';
    $prReady = file_exists($pr . '\\projectorrays.exe');
    echo '<div class="panel"><div class="ph"><h3>🔬 Décompiler un meuble (CCT → CST pour Adobe Director)</h3></div>';
    echo '<p class="muted sm">Transforme un <code>.cct</code> (protégé) en <code>.cst</code> <b>éditable</b> avec le code Lingo restauré, à ouvrir dans <b>Adobe Director</b>. Utile pour ajouter/copier des comportements (ex. logique de téléporteur).</p>';
    echo $prReady ? '<p class="rk green">✅ ProjectorRays prêt</p>' : '<p class="rk red">❌ ProjectorRays introuvable dans ' . h($pr) . '</p>';
    echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="furni_decompile">';
    echo '<div class="row"><label style="flex:1">Sprite du meuble à décompiler<input name="dsprite" placeholder="ex : teleport_door" required></label><button style="align-self:flex-end">🔬 Décompiler</button></div></form>';
    // liste des .cst deja decompiles (telechargeables)
    $decDir = __DIR__ . '/_decompiled';
    $csts = is_dir($decDir) ? glob($decDir . '/*.cst') : [];
    if ($csts) {
        echo '<h3 class="sec">Fichiers décompilés (clic = télécharger)</h3><div class="perms">';
        foreach ($csts as $c) { $bn = basename($c); echo '<a class="tag blue lnkbtn" href="/admin/_decompiled/' . rawurlencode($bn) . '" download style="text-decoration:none">⬇️ ' . h($bn) . ' (' . round(filesize($c) / 1024) . ' Ko)</a>'; }
        echo '</div>';
    }
    echo '<p class="hint">Le <code>.cst</code> s\'ouvre dans Adobe Director. ProjectorRays décompile seulement — pour reconstruire un <code>.cct</code>, il faut Director.</p></div>';
}

function page_navcats(): void {
    page_title('Catégories du navigateur', 'Catégories affichées dans le navigateur de salles (publiques & appartements)');
    echo '<p class="muted sm">Édite le <b>nom</b> et l\'<b>ordre</b> (plus le nombre est petit, plus la catégorie est haute), le <b>rang mini</b> pour y accéder et l\'autorisation d\'échange. ⚠️ Redémarre l\'émulateur après pour voir les changements en jeu.</p>';
    $groups = [3 => '🏛️ Sous-catégories « Salles publiques »', 4 => '🏠 Sous-catégories « Appartements »', 0 => '📁 Catégories racines'];
    foreach ($groups as $parent => $title) {
        $st = db()->prepare('SELECT * FROM rooms_categories WHERE parent_id=? ORDER BY order_id,id'); $st->execute([$parent]); $rows = $st->fetchAll();
        if (!$rows) continue;
        echo '<div class="panel"><div class="ph"><h3>' . $title . '</h3></div>';
        echo '<table class="clean"><tr><th>Ordre</th><th>#</th><th>Nom</th><th>Rang mini</th><th>Échange</th><th></th></tr>';
        foreach ($rows as $c) {
            echo '<tr><form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="navcat_update"><input type="hidden" name="id" value="' . (int)$c['id'] . '">'
                . '<td><input type="number" name="order_id" value="' . (int)$c['order_id'] . '" style="width:64px"></td>'
                . '<td class="muted">' . (int)$c['id'] . '</td>'
                . '<td><input name="name" value="' . h((string)$c['name']) . '" style="width:230px"></td>'
                . '<td><input type="number" name="minrole_access" value="' . (int)$c['minrole_access'] . '" style="width:60px"></td>'
                . '<td style="text-align:center"><input type="checkbox" name="allow_trading"' . ((int)$c['allow_trading'] ? ' checked' : '') . '></td>'
                . '<td><button class="mini">OK</button></td></form></tr>';
        }
        echo '</table></div>';
    }
}

function page_user(): void {
    $id = (int)($_GET['id'] ?? 0);
    $st = db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$id]); $u = $st->fetch();
    if (!$u) { page_title('Joueur introuvable'); echo '<div class="panel"><div class="empty">Aucun joueur #' . $id . '</div></div><a class="qbtn" href="?p=users">← Retour aux joueurs</a>'; return; }
    $back = '?p=user&id=' . $id;
    $seen = (int)$u['last_online'] > 0 ? date('d/m/Y H:i', (int)$u['last_online']) : 'jamais';
    $created = $u['created_at'] ? date('d/m/Y', strtotime((string)$u['created_at'])) : '?';
    $hcActive = (int)$u['club_expiration'] > time(); // UNIX en SECONDES (comme le JAR)
    $hc = $hcActive ? ('actif jusqu\'au ' . date('d/m/Y', (int)$u['club_expiration'])) : 'non membre';
    $bs = db()->prepare("SELECT message,banned_until FROM users_bans WHERE ban_type='USER_ID' AND banned_value=?"); $bs->execute([(string)$id]); $ban = $bs->fetch(); $isBan = (bool)$ban;
    $rb = []; foreach (db()->query('SELECT badge FROM rank_badges WHERE rank=' . (int)$u['rank']) as $r) $rb[] = $r['badge'];
    $ob = []; $obs = db()->prepare('SELECT badge FROM users_badges WHERE user_id=?'); $obs->execute([$id]); foreach ($obs as $r) $ob[] = $r['badge'];

    $uname = (string)$u['username']; $rankSelf = (int)($_SESSION['admin']['rank'] ?? 0);
    $homeUrl = '/v2/?p=home/' . rawurlencode($uname);
    echo '<div style="margin-bottom:10px"><a class="mini ghost lnkbtn" href="?p=users">← Retour aux joueurs</a></div>';
    page_title('👤 ' . $uname, 'Fiche complète du joueur · ID ' . $id);
    // ===== Bandeau joueur (gauche: identité · centre: état/mission/activité · droite: actions) =====
    echo '<div class="ubanner">';
    echo '<div class="ub-id"><img class="ub-av" src="/avatar.php?user=' . h(rawurlencode($uname)) . '&size=b&v=2" alt="Avatar de ' . h($uname) . '" width="58" height="100" loading="lazy"><div><div class="ub-name">' . h($uname) . '</div><div class="muted sm">ID #' . $id . '</div></div></div>';
    echo '<div class="ub-meta">' . rank_badge((int)$u['rank']) . ' ' . ($isBan ? '<span class="rk red">🚫 Banni</span>' : '<span class="rk green">Compte actif</span>')
       . '<div class="muted sm" style="margin-top:6px">Mission : <b>' . h($u['motto'] !== '' ? $u['motto'] : '—') . '</b></div>'
       . '<div class="muted sm">Dernière activité : ' . h($seen) . ' · Club : ' . ($hcActive ? h($hc) : 'non membre') . '</div></div>';
    echo '<div class="ub-act"><a class="mini" href="' . h($homeUrl) . '" target="_blank">🏠 Voir sa Home</a>'
       . '<button type="button" class="mini ghost" onclick="utShow(\'ut-msg\')">✉️ Message</button>'
       . '<details class="ub-menu"><summary class="mini ghost">⚙️ Actions</summary><div class="ub-menu-pop">'
       . '<a class="mini ghost" href="?p=homemod">🛡️ Modération des Homes</a>';
    if ($rankSelf >= 7) echo '<form method="post" action="?p=user&id=' . $id . '" target="_blank" onsubmit="return confirm(\'Ouvrir le jeu connecté en tant que ' . h(addslashes($uname)) . ' ?\')">' . csrf_field() . '<input type="hidden" name="action" value="loginas"><input type="hidden" name="id" value="' . $id . '"><button class="mini ghost">🎭 Se connecter en tant que…</button></form>';
    echo '</div></details></div>';
    echo '</div>';

    echo '<div class="grid">';
    stat_box('💰', number_format((int)$u['credits'], 0, ',', ' '), 'Crédits', 'gold');
    stat_box('🎟️', (int)$u['tickets'], 'Tickets', 'blue');
    stat_box('⭐', $hcActive ? date('d/m/Y', (int)$u['club_expiration']) : 'Non', $hcActive ? 'Club Habbo (expire le)' : 'Club Habbo', 'gold');
    stat_box('📅', $created, 'Inscrit le', 'blue');
    stat_box('🕑', $seen, 'Dernière activité', 'green');
    echo '</div>';

    // ===== Onglets internes (fail-safe : toutes les sections restent visibles sans JS) =====
    echo '<div class="uptabs" id="uptabs"><div class="utabbar">'
       . '<button type="button" data-t="ut-overview" class="on">📋 Vue d\'ensemble</button>'
       . '<button type="button" data-t="ut-compte">👤 Compte</button>'
       . '<button type="button" data-t="ut-home">🏠 Habbo Home</button>'
       . '<button type="button" data-t="ut-inv">📦 Inventaire</button>'
       . '<button type="button" data-t="ut-appts">🏠 Appartements</button>'
       . '<button type="button" data-t="ut-badges">📛 Badges</button>'
       . '<button type="button" data-t="ut-msg">✉️ Messages &amp; notes</button>'
       . '<button type="button" data-t="ut-mod">⚠️ Modération</button>'
       . '<button type="button" data-t="ut-history">🕑 Historique</button>'
       . '</div>';

    // ===== Onglet Vue d'ensemble (cartes résumé + liens « Voir tout » vers les onglets) =====
    echo '<section class="utab on" id="ut-overview">';
    $ovRooms = (int)db()->query('SELECT COUNT(*) FROM rooms WHERE owner_id=\'' . (int)$id . '\'')->fetchColumn();
    ensure_admin_notes();
    $ovNotes = db()->prepare('SELECT author,note,created_at FROM admin_notes WHERE user_id=? ORDER BY id DESC LIMIT 4'); $ovNotes->execute([$id]); $ovNoteList = $ovNotes->fetchAll();
    $ovLnk = 'style="margin-left:auto;background:none;border:0;cursor:pointer;color:var(--blue);font-weight:600;font-size:13px"';
    echo '<div class="dcols">';
    echo '<div class="panel"><div class="ph"><h3>📊 Résumé &amp; activité</h3><button type="button" onclick="utShow(\'ut-compte\')" ' . $ovLnk . '>Compte →</button></div>'
       . '<table class="clean"><tr><td class="muted">Rang</td><td>' . rank_badge((int)$u['rank']) . '</td></tr>'
       . '<tr><td class="muted">État</td><td>' . ($isBan ? '<span class="rk red">🚫 Banni</span>' : '<span class="rk green">Compte actif</span>') . '</td></tr>'
       . '<tr><td class="muted">Crédits</td><td><b>' . number_format((int)$u['credits'], 0, ',', ' ') . '</b> · ' . (int)$u['tickets'] . ' tickets</td></tr>'
       . '<tr><td class="muted">Mission</td><td>' . h($u['motto'] !== '' ? $u['motto'] : '—') . '</td></tr>'
       . '<tr><td class="muted">Inscrit le</td><td>' . h($created) . '</td></tr>'
       . '<tr><td class="muted">Dernière activité</td><td>' . h($seen) . '</td></tr></table></div>';
    echo '<div class="panel"><div class="ph"><h3>🏠 Habbo Home</h3><button type="button" onclick="utShow(\'ut-home\')" ' . $ovLnk . '>Détails →</button></div>'
       . '<div class="row" style="gap:8px;margin-bottom:8px"><a class="mini" href="' . h($homeUrl) . '" target="_blank">↗ Ouvrir sa Home</a><button type="button" class="mini ghost" onclick="utCopy(\'' . h($homeUrl) . '\')">📋 Copier le lien</button></div>'
       . '<p class="muted sm">Statut, signalements et livre d\'or dans <a href="?p=homemod">Modération des Homes</a>.</p></div>';
    echo '</div>';
    echo '<div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>🏠 Appartements</h3><button type="button" onclick="utShow(\'ut-appts\')" ' . $ovLnk . '>Voir tout →</button></div><p><b>' . $ovRooms . '</b> appartement(s).</p></div>';
    echo '<div class="panel"><div class="ph"><h3>📛 Badges &amp; Club</h3><button type="button" onclick="utShow(\'ut-badges\')" ' . $ovLnk . '>Voir tout →</button></div><p><b>' . count($ob) . '</b> badge(s) possédé(s). Club Habbo : <b>' . ($hcActive ? h($hc) : 'non membre') . '</b>.</p></div>';
    echo '</div>';
    echo '<div class="panel"><div class="ph"><h3>🕑 Dernières interventions du staff</h3><button type="button" onclick="utShow(\'ut-msg\')" ' . $ovLnk . '>Notes →</button></div>';
    if (!$ovNoteList) echo '<div class="empty">Aucune note staff pour ce joueur.</div>';
    else { echo '<table class="clean"><tr><th>Quand</th><th>Par</th><th>Note</th></tr>'; foreach ($ovNoteList as $n) echo '<tr><td class="sm muted">' . h(date('d/m/Y H:i', strtotime((string)$n['created_at']))) . '</td><td class="sm"><b>' . h($n['author']) . '</b></td><td>' . h($n['note']) . '</td></tr>'; echo '</table>'; }
    echo '</div></section>';

    echo '<section class="utab" id="ut-compte"><div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>✏️ Réglages du compte</h3></div>
        <form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="user_update"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '">
        <label>Rang<select name="rank">' . rank_options((int)$u['rank']) . '</select></label>
        <label>Crédits<input type="number" name="credits" value="' . (int)$u['credits'] . '" style="width:130px"></label>
        <input type="hidden" name="motto" value="' . h($u['motto']) . '"><button>💾 Enregistrer</button></form>
        <form method="post" class="js row" style="margin-top:8px" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="user_password"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '">
        <label style="flex:1">🔑 Nouveau mot de passe<input name="newpass"></label><button class="ghost">Changer</button></form>
        <div class="row" style="margin-top:8px;gap:6px">' . uquick($id, 'user_credits', 'delta', 1000, '💰 +1 000') . uquick($id, 'user_credits', 'delta', 10000, '💰 +10 000') . uquick($id, 'user_hc', 'days', 30, '⭐ HC 30j') . uquick($id, 'user_hc', 'days', 0, '⭐ Retirer HC') . '</div>
        <div class="row" style="margin-top:6px;gap:6px;align-items:center"><span class="muted sm">Rang rapide :</span>' . uquick($id, 'user_rank', 'rank', 1, '👤 Joueur') . uquick($id, 'user_rank', 'rank', 6, '🛡️ Modérateur') . uquick($id, 'user_rank', 'rank', 7, '⭐ Admin') . '</div></div>';
    echo '<div class="panel"><div class="ph"><h3>ℹ️ Détails (modifiables)</h3></div>
        <form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="user_details"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '">
        <div class="row"><label style="flex:1">E-mail<input name="email" value="' . h($u['email']) . '"></label><label>Naissance<input name="birthday" placeholder="JJ/MM/AAAA" value="' . h(fr_date($u['birthday'])) . '"></label><label>Sexe<select name="sex"><option value="M"' . ($u['sex'] !== 'F' ? ' selected' : '') . '>Garçon</option><option value="F"' . ($u['sex'] === 'F' ? ' selected' : '') . '>Fille</option></select></label></div>
        <label style="margin-top:8px">Mission<input name="motto" value="' . h($u['motto']) . '"></label>
        <button style="margin-top:10px">💾 Enregistrer les détails</button></form>
        <table class="clean" style="margin-top:12px">
        <tr><td class="muted">Club Habbo</td><td>' . h($hc) . '</td></tr>
        <tr><td class="muted">Points BattleBall</td><td>' . (int)$u['battleball_points'] . '</td></tr>
        <tr><td class="muted">Points SnowStorm</td><td>' . (int)$u['snowstorm_points'] . '</td></tr>
        <tr><td class="muted">Figure</td><td class="sm"><code>' . h($u['figure']) . '</code></td></tr>
        </table></div></div>';
    echo '</section>';

    // ===== Onglet Habbo Home (relie aux outils existants, pas de 2e éditeur) =====
    echo '<section class="utab" id="ut-home"><div class="panel"><div class="ph"><h3>🏠 Habbo Home de ' . h($uname) . '</h3></div>';
    echo '<div class="row" style="gap:8px;margin-bottom:8px"><a class="mini" href="' . h($homeUrl) . '" target="_blank">↗ Ouvrir sa Home</a>'
       . '<button type="button" class="mini ghost" onclick="utCopy(\'' . h($homeUrl) . '\')">📋 Copier le lien public</button>'
       . '<a class="mini ghost" href="?p=homemod">🛡️ Modération des Homes</a></div>';
    echo '<p class="muted sm">Le <b>statut</b> (publiée / masquée / édition bloquée), les <b>signalements</b> en attente, le <b>livre d\'or</b> et les actions de modération (masquer / rendre visible / bloquer — avec motif et permissions) sont gérés dans <a href="?p=homemod">Modération des Homes</a> : on ne duplique pas l\'éditeur ici. ⚠️ Un message du livre d\'or publié par <b>un autre joueur</b> n\'est pas une faute du propriétaire.</p>';
    echo '</div></section>';

    // ===== Onglet Inventaire (consultation + filtre ; suppression ciblée = étape suivante sur base de test) =====
    echo '<section class="utab" id="ut-inv"><div class="panel"><div class="ph"><h3>📦 Inventaire (objets en main)</h3></div>';
    try {
        $inv = db()->prepare('SELECT i.id,i.definition_id,i.custom_data,i.created_at,d.name,d.sprite FROM items i LEFT JOIN items_definitions d ON d.id=i.definition_id WHERE i.user_id=? AND i.room_id=0 ORDER BY i.id DESC');
        $inv->execute([$id]); $invRows = $inv->fetchAll();
        $placed = (int)db()->query('SELECT COUNT(*) FROM items WHERE user_id=' . (int)$id . ' AND room_id<>0')->fetchColumn();
        $invq = trim((string)($_GET['invq'] ?? ''));
        echo '<p class="muted sm">Objets <b>non posés</b> (en main) : <b>' . count($invRows) . '</b> · objets <b>posés</b> en salle : <b>' . $placed . '</b>.</p>';
        echo '<form method="get" class="srch" style="margin:6px 0"><input type="hidden" name="p" value="user"><input type="hidden" name="id" value="' . $id . '"><input name="invq" value="' . h($invq) . '" placeholder="🔍 Filtrer par nom ou sprite…"><button class="mini">OK</button></form>';
        if (!$invRows) echo '<div class="empty">Aucun objet en main.</div>';
        else {
            echo '<table class="clean"><tr><th>#</th><th>Objet</th><th>Sprite</th><th>Déf.</th><th>Données</th><th>Reçu</th></tr>';
            foreach ($invRows as $it) {
                $nm = (string)($it['name'] ?? ''); $sp = (string)($it['sprite'] ?? '');
                if ($invq !== '' && stripos($nm . ' ' . $sp . ' ' . $it['id'], $invq) === false) continue;
                echo '<tr><td class="muted">' . (int)$it['id'] . '</td><td><b>' . h($nm !== '' ? $nm : '(sans nom)') . '</b></td><td class="sm muted">' . h($sp) . '</td><td class="sm muted">' . (int)$it['definition_id'] . '</td><td class="sm muted">' . h(mb_strimwidth((string)($it['custom_data'] ?? ''), 0, 30, '…')) . '</td><td class="sm muted">' . h(date('d/m/Y', strtotime((string)$it['created_at']))) . '</td></tr>';
            }
            echo '</table>';
        }
        echo '<p class="muted sm" style="margin-top:10px">🔧 <b>Suppression ciblée d\'un objet</b> (au lieu de « Vider TOUT ») : étape suivante — testée d\'abord sur une <b>base de test distincte</b>, sans toucher à l\'ému en fonctionnement.</p>';
    } catch (Throwable $e) { echo '<div class="empty">Inventaire indisponible.</div>'; }
    echo '</div></section>';

    // ===== Onglet Badges =====
    echo '<section class="utab" id="ut-badges">';
    echo '<div class="panel"><div class="ph"><h3>📛 Badges</h3></div>
        <p class="muted sm">🎖️ De rang (automatiques) :</p><div class="badgegrid" style="margin-bottom:10px">' . rank_badges_html($rb) . '</div>
        <p class="muted sm">📛 Possédés :</p><div class="badgegrid">' . user_badges_html($id, $ob) . '</div>
        <form method="post" class="js row" data-reload style="margin-top:10px">' . csrf_field() . '<input type="hidden" name="action" value="badge_add"><input type="hidden" name="username" value="' . h($u['username']) . '"><input type="hidden" name="back" value="' . h($back) . '"><label>Ajouter un badge<input name="badge" maxlength="3" style="width:90px;text-transform:uppercase"></label><button>+ Ajouter</button><a class="mini ghost lnkbtn" href="?p=badges" style="align-self:flex-end">Galerie</a></form></div>';
    echo '</section>';

    // ===== Onglet Appartements (salles + IP techniques) =====
    echo '<section class="utab" id="ut-appts"><div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>🏠 Salles du joueur</h3></div>';
    $rms = db()->prepare('SELECT id,name FROM rooms WHERE owner_id=?'); $rms->execute([(string)$id]); $rml = $rms->fetchAll();
    if (!$rml) echo '<div class="empty">Aucune salle.</div>';
    else { echo '<table class="clean"><tr><th>#</th><th>Nom</th></tr>'; foreach ($rml as $r) echo '<tr><td class="muted">' . (int)$r['id'] . '</td><td>' . h($r['name']) . '</td></tr>'; echo '</table>'; }
    echo '</div>';
    echo '<div class="panel"><div class="ph"><h3>🌐 Dernières IP</h3></div>';
    $ips = db()->prepare('SELECT ip_address,created_at FROM users_ip_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 8'); $ips->execute([$id]); $ipl = $ips->fetchAll();
    if (!$ipl) echo '<div class="empty">Aucune IP enregistrée.</div>';
    else { echo '<table class="clean"><tr><th>IP</th><th>Date</th></tr>'; foreach ($ipl as $r) echo '<tr><td><code>' . h($r['ip_address']) . '</code></td><td class="sm muted">' . h(date('d/m/Y H:i', strtotime((string)$r['created_at']))) . '</td></tr>'; echo '</table>'; }
    echo '</div></div>';
    // Comptes liés (même IP) — réservé rang 7 ; une IP commune est un indice, pas une preuve.
    if ($rankSelf >= 7) {
        try {
            $alts = db()->prepare('SELECT DISTINCT u.id,u.username,u.rank FROM users_ip_logs l JOIN users_ip_logs l2 ON l2.ip_address=l.ip_address JOIN users u ON u.id=l2.user_id WHERE l.user_id=? AND u.id<>? ORDER BY u.username LIMIT 20');
            $alts->execute([$id, $id]); $altRows = $alts->fetchAll();
            echo '<div class="panel"><div class="ph"><h3>🕵️ Comptes liés (même IP)</h3><span class="muted sm" style="margin-left:auto">réservé admin</span></div>';
            echo '<p class="muted sm">Comptes ayant partagé une adresse IP avec ce joueur. ⚠️ Une IP commune est un <b>indice</b>, pas une preuve (connexion partagée, lieu public…).</p>';
            if (!$altRows) echo '<div class="empty">Aucun compte lié détecté.</div>';
            else { echo '<table class="clean"><tr><th>Joueur</th><th>Rang</th><th></th></tr>'; foreach ($altRows as $al) echo '<tr><td><b>' . h((string)$al['username']) . '</b></td><td>' . rank_badge((int)$al['rank']) . '</td><td><a class="mini ghost" href="?p=user&id=' . (int)$al['id'] . '">Fiche →</a></td></tr>'; echo '</table>'; }
            echo '</div>';
        } catch (Throwable $e) {}
    }
    echo '</section>';

    // ===== Onglet Messages & notes (échanges visibles du joueur + notes privées staff) =====
    echo '<section class="utab" id="ut-msg">';

    // Conversations du joueur (nouvelle messagerie)
    if (function_exists('ensure_conversations')) {
        ensure_conversations();
        $cvs = []; try { $qc = db()->prepare("SELECT c.id,c.subject,c.category,c.status,c.updated_at,(SELECT COUNT(*) FROM conversation_messages m WHERE m.conv_id=c.id AND m.sender='player' AND m.read_staff=0) AS unread FROM conversations c WHERE c.player_id=? ORDER BY c.updated_at DESC LIMIT 20"); $qc->execute([$id]); $cvs = $qc->fetchAll(); } catch (Throwable $e) {}
        $stMap = ['waiting_staff' => '🔴 À répondre', 'waiting_player' => '⏳ Attente joueur', 'resolved' => '✅ Résolu'];
        echo '<div class="panel"><div class="ph"><h3>💬 Conversations</h3><a class="mini ghost" style="margin-left:auto" href="?p=messages&pl=' . $id . '">Gérer dans l\'assistance →</a></div>';
        if (!$cvs) echo '<div class="empty">Aucune conversation. Tu peux en démarrer une depuis l\'assistance ou via le message ci-dessous.</div>';
        else {
            echo '<table class="clean"><tr><th>Sujet</th><th>Catégorie</th><th>Statut</th><th>Maj</th><th></th></tr>';
            foreach ($cvs as $c) {
                echo '<tr><td><a class="lnk" href="?p=messages&c=' . (int)$c['id'] . '"><b>' . h((string)($c['subject'] ?: 'Sans sujet')) . '</b></a>'
                   . ((int)$c['unread'] ? ' <span class="rk red">' . (int)$c['unread'] . '</span>' : '') . '</td>'
                   . '<td class="sm muted">' . h((string)$c['category']) . '</td>'
                   . '<td class="sm">' . h($stMap[(string)$c['status']] ?? (string)$c['status']) . '</td>'
                   . '<td class="sm muted">' . h(date('d/m H:i', strtotime((string)$c['updated_at']))) . '</td>'
                   . '<td><a class="mini ghost" href="?p=messages&c=' . (int)$c['id'] . '">Ouvrir</a></td></tr>';
            }
            echo '</table>';
        }
        echo '</div>';
    }

    // Notes staff (item 4)
    ensure_admin_notes();
    $notes = db()->prepare('SELECT * FROM admin_notes WHERE user_id=? ORDER BY id DESC'); $notes->execute([$id]); $noteList = $notes->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>📝 Notes internes (staff)</h3></div>';
    echo '<form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="note_add"><input type="hidden" name="user_id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '"><label style="flex:1">Nouvelle note<input name="note" placeholder="Ex. Prévenu pour spam le…" required></label><button>+ Ajouter</button></form>';
    if (!$noteList) echo '<div class="empty">Aucune note.</div>';
    else { echo '<table class="clean" style="margin-top:10px"><tr><th>Quand</th><th>Par</th><th>Note</th><th></th></tr>';
        foreach ($noteList as $n) echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$n['created_at']))) . '</td><td class="sm"><b>' . h($n['author']) . '</b></td><td>' . h($n['note']) . '</td><td><form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="note_del"><input type="hidden" name="id" value="' . (int)$n['id'] . '"><input type="hidden" name="back" value="' . h($back) . '"><button class="mini ghost">✕</button></form></td></tr>';
        echo '</table>'; }
    echo '</div>';

    // Message au joueur (Staff -> joueur, lu sur le site)
    ensure_user_msgs();
    echo '<div class="panel"><div class="ph"><h3>✉️ Envoyer un message à ' . h($u['username']) . '</h3></div>';
    echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="umsg_send"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '">'
       . '<label>Sujet<input name="subject" maxlength="100" placeholder="Ex : Réponse à ta demande"></label>'
       . '<label style="margin-top:6px">Message<textarea name="body" rows="3" maxlength="2000" required placeholder="Ton message pour le joueur (il le lira sur le site)."></textarea></label>'
       . '<button style="margin-top:8px">✉️ Envoyer</button></form>';
    $sent = db()->prepare('SELECT subject,body,from_staff,read_at,created_at FROM user_messages WHERE user_id=? ORDER BY id DESC LIMIT 6'); $sent->execute([$id]); $sentList = $sent->fetchAll();
    if ($sentList) {
        echo '<table class="clean" style="margin-top:12px"><tr><th>Envoyé</th><th>Par</th><th>Sujet</th><th>Lu</th></tr>';
        foreach ($sentList as $sm) echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$sm['created_at']))) . '</td><td class="sm"><b>' . h((string)$sm['from_staff']) . '</b></td><td>' . h((string)($sm['subject'] ?: '(sans sujet)')) . '</td><td class="sm">' . ($sm['read_at'] ? '✅ ' . h(date('d/m H:i', strtotime((string)$sm['read_at']))) : '<span class="muted">non lu</span>') . '</td></tr>';
        echo '</table>';
    }
    echo '</div>';
    echo '</section>';

    // ===== Onglet Modération =====
    echo '<section class="utab" id="ut-mod">';
    // Zone sensible : modération + actions irréversibles, isolées du reste de la fiche.
    echo '<div class="panel" style="border-color:#6b2c2c"><div class="ph"><h3 style="color:var(--red)">⚠️ Zone sensible — actions à fort impact</h3></div>';
    echo '<p class="muted sm" style="margin:-4px 0 12px">Modération et actions <b>irréversibles</b>, regroupées ici et séparées du reste de la fiche.</p>';
    if ($isBan) echo '<p>🚫 Banni : <b>' . h($ban['message']) . '</b> (' . ((int)$ban['banned_until'] >= BAN_PERMANENT ? '♾️ définitif' : 'jusqu\'au ' . date('d/m/Y', (int)($ban['banned_until'] / 1000))) . ') <form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="ban_remove">' . csrf_field() . '<input type="hidden" name="value" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '"><button class="mini ghost">Lever le ban</button></form></p>';
    else echo '<form method="post" class="js" data-reload style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;max-width:760px"><input type="hidden" name="action" value="ban_add"><input type="hidden" name="type" value="USER_ID"><input type="hidden" name="value" value="' . h($u['username']) . '"><input type="hidden" name="back" value="' . h($back) . '">' . csrf_field() . '<label>Durée (j · 0 = définitif)<input type="number" name="days" value="0" style="width:150px"></label><label style="flex:1;min-width:220px">Motif<input name="message" value="Comportement inapproprié"></label><button style="background:var(--red)">🚫 Bannir</button></form>';
    echo '<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:16px;border-top:1px solid var(--line2);padding-top:14px">';
    echo '<form method="post" class="js" data-reload data-confirm="⚠️ SUPPRIMER TOUT l\'inventaire (tous les objets non posés) de ' . h($u['username']) . ' ? Irréversible." style="margin:0">' . csrf_field() . '<input type="hidden" name="action" value="clear_hand"><input type="hidden" name="username" value="' . h($u['username']) . '"><input type="hidden" name="back" value="' . h($back) . '"><button style="background:#c2603a">🧹 Vider TOUT l\'inventaire</button></form>';
    echo '<form method="post" class="js" data-reload data-confirm="Supprimer définitivement le compte ' . h($u['username']) . ' ? Irréversible." style="margin:0"><input type="hidden" name="action" value="user_delete"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="?p=users">' . csrf_field() . '<button style="background:var(--red)">🗑️ Supprimer ce compte</button></form>';
    echo '</div></div>';
    echo '</section>';

    // ===== Onglet Historique (journal des actions visant ce compte) =====
    echo '<section class="utab" id="ut-history"><div class="panel"><div class="ph"><h3>🕑 Historique du compte</h3></div>';
    echo '<p class="muted sm">Interventions du staff sur ce compte (crédits, rang, badges, sanctions, inventaire, messages…). Le journal se remplit <b>à partir de maintenant</b> : les actions antérieures à sa mise en place ne peuvent pas être reconstituées.</p>';
    try {
        ensure_user_history();
        $hist = db()->prepare('SELECT author,action,detail,created_at FROM user_history WHERE user_id=? ORDER BY id DESC LIMIT 100'); $hist->execute([$id]); $histRows = $hist->fetchAll();
        if (!$histRows) echo '<div class="empty">Aucune action enregistrée pour l\'instant.</div>';
        else { echo '<table class="clean"><tr><th>Quand</th><th>Par</th><th>Action</th><th>Détail</th></tr>'; foreach ($histRows as $hR) echo '<tr><td class="sm muted" style="white-space:nowrap">' . h(date('d/m/Y H:i', strtotime((string)$hR['created_at']))) . '</td><td class="sm"><b>' . h((string)$hR['author']) . '</b></td><td><code class="sm">' . h((string)$hR['action']) . '</code></td><td class="sm">' . h((string)$hR['detail']) . '</td></tr>'; echo '</table>'; }
    } catch (Throwable $e) { echo '<div class="empty">Historique indisponible.</div>'; }
    echo '</div></section>';
    echo '</div>'; // fin conteneur d'onglets
}

function page_bots(): void {
    page_title('Bots', 'Noms, mottos et dialogues des bots du jeu');
    echo '<div class="warn">⚠️ Après modification d\'un bot, <b>redémarre l\'émulateur</b> (onglet Serveur) pour l\'appliquer en jeu. Une phrase par ligne ; suffixe <code>#SHOUT</code> = crie, <code>#WHISPER</code> = chuchote. Jetons : <code>%drink%</code>, <code>%lowercaseDrink%</code>.</div>';
    $rooms = []; foreach (db()->query("SELECT id,name FROM rooms") as $r) $rooms[(int)$r['id']] = $r['name'];
    $bots = db()->query('SELECT id,name,mission,room_id,speech,response,unrecognised_response FROM rooms_bots ORDER BY name')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🤖 Bots (' . count($bots) . ')</h3><input class="filt" style="margin-left:auto;min-width:220px" oninput="filt(this,\'bottbl\')" placeholder="🔍 Filtrer un bot..."></div>';
    echo '<table class="clean" id="bottbl"><tr><th>#</th><th>Nom</th><th>Motto</th><th>Salle</th><th>Dialogues</th><th style="text-align:right"></th></tr>';
    foreach ($bots as $b) {
        $nb = count(array_filter([$b['speech'], $b['response'], $b['unrecognised_response']], fn($x) => trim((string)$x) !== ''));
        $toTA = fn($s) => h(str_replace('|', "\n", (string)$s));
        echo '<tr class="frow">
            <td class="muted">' . (int)$b['id'] . '</td>
            <td><b>' . h($b['name']) . '</b></td>
            <td class="muted sm">' . h($b['mission']) . '</td>
            <td class="muted sm">' . h($rooms[(int)$b['room_id']] ?? ('#' . $b['room_id'])) . '</td>
            <td>' . ($b['speech'] !== '' || $b['response'] !== '' || $b['unrecognised_response'] !== '' ? '<span class="rk green">parle</span>' : '<span class="muted sm">silencieux</span>') . '</td>
            <td style="text-align:right"><button class="mini ghost" onclick="tgl(\'be' . (int)$b['id'] . '\')">Éditer</button></td></tr>
            <tr id="be' . (int)$b['id'] . '" class="drawer" style="display:none"><td colspan="6">
                <form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="bot_update"><input type="hidden" name="id" value="' . (int)$b['id'] . '">
                <div class="row"><label>Nom<input name="name" value="' . h($b['name']) . '" maxlength="25"></label>
                    <label style="flex:1">Motto<input name="mission" value="' . h($b['mission']) . '"></label></div>
                <div class="row" style="align-items:stretch;margin-top:8px">
                    <label style="flex:1">💬 Phrases (aléatoires)<textarea name="speech" rows="6">' . $toTA($b['speech']) . '</textarea></label>
                    <label style="flex:1">🗨️ Réponses (quand on lui parle)<textarea name="response" rows="6">' . $toTA($b['response']) . '</textarea></label>
                    <label style="flex:1">❓ Réponses par défaut<textarea name="unrecognised_response" rows="6">' . $toTA($b['unrecognised_response']) . '</textarea></label>
                </div>
                <div style="margin-top:8px"><button>💾 Enregistrer</button></div></form></td></tr>';
    }
    echo '</table></div>';
}

function page_users(): void {
    $q = trim($_GET['q'] ?? ''); $pg = cur_page(); $off = ($pg - 1) * PER_PAGE;
    page_title('Joueurs', 'Gère les comptes, rangs, crédits et mots de passe');
    echo '<div class="panel"><div class="ph"><h3>➕ Créer un compte</h3></div><form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="user_create">
        <label>Nom<input name="username" required></label><label>Mot de passe<input name="newpass" required></label><label>Anniversaire<input name="birthday" placeholder="JJ/MM/AAAA" value="01/01/1990"></label><label>Rang<select name="rank">' . rank_options(1) . '</select></label><button>Créer</button>
        <span class="muted sm" style="align-self:center">Rangs 1→7 (5 = Super Hobba, 6 = Modérateur, 7 = Administrateur). Kepler n\'a pas de rang « HabboX ».</span></form></div>';

    echo '<div class="panel"><div class="ph"><h3>👥 Comptes</h3><form method="get" class="srch"><input type="hidden" name="p" value="users"><input name="q" list="dl_pseudos" autocomplete="off" value="' . h($q) . '" placeholder="🔍 Nom du joueur..."><button class="mini">OK</button></form></div>';
    $bans = []; foreach (db()->query("SELECT banned_value FROM users_bans WHERE ban_type='USER_ID'") as $b) $bans[(int)$b['banned_value']] = true;
    if ($q !== '') { $st = db()->prepare('SELECT COUNT(*) FROM users WHERE username LIKE ?'); $st->execute(['%' . $q . '%']); $total = (int)$st->fetchColumn();
        $st = db()->prepare('SELECT id,username,rank,credits,motto,last_online FROM users WHERE username LIKE ? ORDER BY username LIMIT ' . PER_PAGE . ' OFFSET ' . $off); $st->execute(['%' . $q . '%']);
    } else { $total = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(); $st = db()->query('SELECT id,username,rank,credits,motto,last_online FROM users ORDER BY id DESC LIMIT ' . PER_PAGE . ' OFFSET ' . $off); }
    $list = $st->fetchAll();
    $ubadges = [];
    if ($list) {
        $ids = implode(',', array_map(fn($r) => (int)$r['id'], $list));
        foreach (db()->query('SELECT user_id,badge FROM users_badges WHERE user_id IN (' . $ids . ')') as $b) $ubadges[(int)$b['user_id']][] = $b['badge'];
    }
    $rankBadges = []; foreach (db()->query('SELECT rank,badge FROM rank_badges') as $r) $rankBadges[(int)$r['rank']][] = $r['badge'];
    echo '<table class="clean"><tr><th>#</th><th>Nom</th><th>Rang</th><th>Crédits</th><th>Badges</th><th>Statut</th><th style="text-align:right">Actions</th></tr>';
    foreach ($list as $u) {
        $ub = $ubadges[(int)$u['id']] ?? [];
        $rb = $rankBadges[(int)$u['rank']] ?? [];
        $seen = (int)$u['last_online'] > 0 ? date('d/m/Y H:i', (int)$u['last_online']) : '<span class="muted">jamais</span>';
        $isBan = isset($bans[(int)$u['id']]);
        echo '<tr>
            <td class="muted">' . (int)$u['id'] . '</td><td><div style="display:flex;align-items:center;gap:8px"><span style="flex:0 0 auto;width:28px;height:34px;overflow:hidden;background:#f3eecf;border:1px solid #d8cfa6;border-radius:3px;display:flex;justify-content:center"><img src="/avatar.php?user=' . h(rawurlencode((string)$u['username'])) . '&size=s&head=1&v=2" alt="" width="32" height="55" loading="lazy" style="image-rendering:pixelated;image-rendering:crisp-edges"></span><span><a class="ulink" href="?p=user&id=' . (int)$u['id'] . '">' . h($u['username']) . '</a><div class="muted sm">' . h($u['motto']) . '</div></span></div></td>
            <td>' . rank_badge((int)$u['rank']) . '</td><td>' . (int)$u['credits'] . '</td><td>' . badge_preview(array_merge($rb, $ub)) . '</td>
            <td>' . ($isBan ? '<span class="rk red">🚫 Banni</span>' : '<span class="rk green">OK</span>') . '</td>
            <td style="text-align:right"><button class="mini ghost" onclick="tgl(\'ue' . (int)$u['id'] . '\')">Éditer</button>
                <a class="mini ghost lnkbtn" href="?p=moderation&ban=' . h(rawurlencode($u['username'])) . '">Bannir</a></td></tr>
            <tr id="ue' . (int)$u['id'] . '" class="drawer" style="display:none"><td colspan="7">
                <form method="post" class="js row">' . csrf_field() . '<input type="hidden" name="action" value="user_update"><input type="hidden" name="id" value="' . (int)$u['id'] . '">
                    <label>Rang<select name="rank">' . rank_options((int)$u['rank']) . '</select></label>
                    <label>Crédits<input type="number" name="credits" value="' . (int)$u['credits'] . '" style="width:120px"></label>
                    <label style="flex:1">Motto<input name="motto" value="' . h($u['motto']) . '"></label>
                    <button>💾 Enregistrer</button></form>
                <form method="post" class="js row" style="margin-top:8px">' . csrf_field() . '<input type="hidden" name="action" value="user_password"><input type="hidden" name="id" value="' . (int)$u['id'] . '">
                    <label style="flex:1">🔑 Nouveau mot de passe<input name="newpass" placeholder="laisser vide = inchangé"></label><button class="ghost">Changer</button></form>
                <div class="row" style="margin-top:8px;gap:6px;align-items:center">
                    <span class="muted sm">Actions rapides :</span>' . uquick($u['id'], 'user_credits', 'delta', 1000, '💰 +1 000') . uquick($u['id'], 'user_credits', 'delta', 10000, '💰 +10 000') . uquick($u['id'], 'user_hc', 'days', 30, '⭐ HC 30 j') . uquick($u['id'], 'user_hc', 'days', 0, '⭐ Retirer HC') . '
                    <form method="post" class="js" data-reload data-confirm="Supprimer définitivement le compte ' . h($u['username']) . ' ?" style="margin-left:auto">' . csrf_field() . '<input type="hidden" name="action" value="user_delete"><input type="hidden" name="id" value="' . (int)$u['id'] . '"><button class="mini" style="background:var(--red)">🗑️ Supprimer</button></form>
                </div>
                <div class="row" style="margin-top:10px;gap:7px;align-items:center;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:10px">
                    <span class="muted sm">🎖️ Badges de rang (auto) :</span>' . rank_badges_html($rb) . '<span class="muted sm">(gérés dans l\'onglet Badges → par rang)</span>
                </div>
                <div class="row" style="margin-top:8px;gap:7px;align-items:center;flex-wrap:wrap">
                    <span class="muted sm">📛 Badges possédés :</span>' . user_badges_html((int)$u['id'], $ub) . '
                    <form method="post" class="js" data-reload style="display:flex;gap:5px;align-items:center;margin-left:8px">' . csrf_field() . '<input type="hidden" name="action" value="badge_add"><input type="hidden" name="username" value="' . h($u['username']) . '"><input name="badge" maxlength="3" placeholder="code" style="width:74px;text-transform:uppercase"><button class="mini ghost">+ Ajouter</button></form>
                    <a class="mini ghost lnkbtn" href="?p=badges&u=' . h(rawurlencode($u['username'])) . '">Voir la galerie</a>
                    <span class="muted sm" style="margin-left:auto">Dernière connexion : ' . $seen . '</span>
                </div>
            </td></tr>';
    }
    echo '</table>'; pager($total, $pg, '?p=users' . ($q !== '' ? '&q=' . urlencode($q) : '')); echo '</div>';
}

function page_badges(): void {
    page_title('Badges', 'Attribue des badges aux joueurs et par rang');
    $codes = badge_codes();
    $u = trim($_GET['u'] ?? '');
    echo '<div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>📛 Attribuer un badge à un joueur</h3></div>
        <form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="badge_add">
        <label style="flex:1">Joueur<input name="username" list="dl_pseudos" autocomplete="off" value="' . h($u) . '" required></label>
        <label>Code badge<input name="badge" id="badgecode" maxlength="3" placeholder="ADM" required style="width:90px;text-transform:uppercase"></label>
        <button>Attribuer</button></form>
        <p class="muted sm">Astuce : clique un badge dans la galerie ci-dessous pour remplir le code automatiquement.</p></div>';
    echo '<div class="panel"><div class="ph"><h3>🎁 Donner un badge à TOUS les joueurs</h3></div>
        <form method="post" class="js row" data-reload data-confirm="Donner ce badge à tous les joueurs ?">' . csrf_field() . '<input type="hidden" name="action" value="badge_all"><label>Code badge<input name="badge" maxlength="3" placeholder="EVT" required style="width:100px;text-transform:uppercase"></label><button>🎁 Distribuer à tous</button><span class="muted sm" style="align-self:center">Parfait pour un event / cadeau.</span></form></div>';
    echo '<div class="panel"><div class="ph"><h3>🎖️ Badges automatiques par rang</h3></div>';
    $rb = []; foreach (db()->query('SELECT rank,badge FROM rank_badges ORDER BY rank') as $r) $rb[(int)$r['rank']][] = $r['badge'];
    echo '<form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="rank_badge_add">
        <label>Rang<select name="rank">' . rank_options(6) . '</select></label>
        <label>Code<input name="badge" maxlength="3" required style="width:90px;text-transform:uppercase"></label><button>Ajouter</button></form>';
    echo '<table class="clean" style="margin-top:10px"><tr><th>Rang</th><th>Badges</th></tr>';
    foreach (RANKS as $n => $lbl) {
        echo '<tr><td>' . rank_badge($n) . '</td><td style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
        if (empty($rb[$n])) echo '<span class="muted">—</span>';
        else foreach ($rb[$n] as $bc) echo '<span class="bchip">' . badge_img($bc) . '<form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="rank_badge_remove"><input type="hidden" name="rank" value="' . $n . '"><input type="hidden" name="badge" value="' . h($bc) . '">' . csrf_field() . '<button class="mini ghost" title="Retirer">✕</button></form></span>';
        echo '</td></tr>';
    }
    echo '</table></div></div>';

    echo '<div class="panel"><div class="ph"><h3>👤 Badges d\'un joueur</h3><form method="get" class="srch"><input type="hidden" name="p" value="badges"><input name="u" list="dl_pseudos" autocomplete="off" value="' . h($u) . '" placeholder="Nom du joueur..."><button class="mini">Voir</button></form></div>';
    if ($u !== '') {
        $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([$u]); $uid = $st->fetchColumn();
        if (!$uid) echo '<div class="empty">Joueur « ' . h($u) . ' » introuvable.</div>';
        else {
            $bs = db()->prepare('SELECT badge FROM users_badges WHERE user_id=?'); $bs->execute([$uid]); $rows = $bs->fetchAll();
            if (!$rows) echo '<div class="empty">Ce joueur n\'a aucun badge.</div>';
            else { echo '<div class="badgegrid">'; foreach ($rows as $b) echo '<div class="bcell">' . badge_img($b['badge']) . '<span>' . h($b['badge']) . '</span><form method="post" class="js" data-reload><input type="hidden" name="action" value="badge_remove"><input type="hidden" name="uid" value="' . (int)$uid . '"><input type="hidden" name="badge" value="' . h($b['badge']) . '">' . csrf_field() . '<button class="mini ghost">✕ retirer</button></form></div>'; echo '</div>'; }
        }
    } else echo '<p class="muted">Cherche un joueur pour voir et retirer ses badges.</p>';
    echo '</div>';

    $q = strtoupper(trim($_GET['q'] ?? ''));
    $filtered = $q !== '' ? array_values(array_filter($codes, fn($c) => strpos($c, $q) !== false)) : array_values($codes);
    $tot = count($filtered);
    $pg = max(1, (int)($_GET['pg'] ?? 1)); $per = 120; $off = ($pg - 1) * $per;
    $slice = array_slice($filtered, $off, $per);
    echo '<div class="panel"><div class="ph"><h3>🖼️ Galerie des badges (' . $tot . ')</h3><form method="get" class="srch"><input type="hidden" name="p" value="badges">' . ($u !== '' ? '<input type="hidden" name="u" value="' . h($u) . '">' : '') . '<input name="q" value="' . h($q) . '" placeholder="Filtrer (ex. AD)"><button class="mini">OK</button></form></div>';
    echo '<div class="badgegrid">';
    foreach ($slice as $c) echo '<div class="bcell" onclick="pickBadge(\'' . h($c) . '\')" title="Cliquer pour choisir ' . h($c) . '">' . badge_img($c) . '<span>' . h($c) . '</span></div>';
    echo '</div>';
    $pages = (int)ceil($tot / $per);
    if ($pages > 1) {
        echo '<div class="pager"><span class="muted sm">' . $tot . ' badges · page ' . $pg . '/' . $pages . '</span><div>';
        if ($pg > 1) echo '<a class="mini ghost" href="?p=badges' . ($u !== '' ? '&u=' . urlencode($u) : '') . ($q !== '' ? '&q=' . urlencode($q) : '') . '&pg=' . ($pg - 1) . '">← Précédent</a>';
        if ($pg < $pages) echo '<a class="mini ghost" href="?p=badges' . ($u !== '' ? '&u=' . urlencode($u) : '') . ($q !== '' ? '&q=' . urlencode($q) : '') . '&pg=' . ($pg + 1) . '">Suivant →</a>';
        echo '</div></div>';
    }
    echo '</div>';
}

function page_games(): void {
    page_title('Jeux', 'Classements, rangs et réglages (BattleBall & SnowStorm)');
    echo '<div class="cols">';
    foreach (['battleball' => '⚽ BattleBall', 'snowstorm' => '❄️ SnowStorm'] as $g => $label) {
        $col = $g === 'snowstorm' ? 'snowstorm_points' : 'battleball_points';
        echo '<div class="panel"><div class="ph"><h3>' . $label . ' — Top 10</h3></div><table class="clean"><tr><th>#</th><th>Joueur</th><th>Points</th><th></th></tr>';
        $i = 1;
        foreach (db()->query("SELECT id,username,$col AS pts FROM users ORDER BY $col DESC LIMIT 10") as $u) {
            echo '<tr><form method="post" class="js"><td class="muted">' . ($i++) . '</td><td><b>' . h($u['username']) . '</b></td>' . csrf_field() . '<input type="hidden" name="action" value="game_points"><input type="hidden" name="game" value="' . $g . '"><input type="hidden" name="id" value="' . (int)$u['id'] . '"><td><input type="number" name="points" value="' . (int)$u['pts'] . '" style="width:100px"></td><td><button class="mini">OK</button></td></form></tr>';
        }
        echo '</table></div>';
    }
    echo '</div>';
    echo '<div class="panel"><div class="ph"><h3>🏆 Rangs des jeux (paliers de points)</h3></div><table class="clean"><tr><th>Jeu</th><th>Rang</th><th>Pts min</th><th>Pts max</th><th></th></tr>';
    foreach (db()->query('SELECT id,type,title,min_points,max_points FROM games_ranks ORDER BY type,min_points') as $r) {
        echo '<tr><form method="post" class="js"><td class="muted sm">' . h($r['type']) . '</td>' . csrf_field() . '<input type="hidden" name="action" value="game_rank_update"><input type="hidden" name="id" value="' . (int)$r['id'] . '">
            <td><input name="title" value="' . h($r['title']) . '"></td>
            <td><input type="number" name="min_points" value="' . (int)$r['min_points'] . '" style="width:100px"></td>
            <td><input type="number" name="max_points" value="' . (int)$r['max_points'] . '" style="width:100px"></td>
            <td><button class="mini">OK</button></td></form></tr>';
    }
    echo '</table><p class="hint">Pts max = 0 pour le dernier palier (illimité).</p></div>';
    echo '<div class="panel"><div class="ph"><h3>⚙️ Réglages des jeux</h3></div>';
    $all = []; foreach (db()->query('SELECT setting,value FROM settings') as $r) $all[$r['setting']] = $r['value'];
    foreach (['battleball.create.game.enabled', 'battleball.ticket.charge', 'snowstorm.create.game.enabled', 'snowstorm.ticket.charge'] as $k) if (isset($all[$k])) settings_row($k, $all[$k]);
    echo '</div>';
}

function page_ranks(): void {
    page_title('Rangs & permissions', 'Ce que débloque chaque rang dans Kepler');
    echo '<div class="warn">Rangs <b>1 à 7</b> (5 = Super Hobba, 6 = Modérateur, 7 = Administrateur, le maximum — comme l\'onglet « Accès admin »). Il n\'existe pas de rang « HabboX » séparé dans cet émulateur.</div>';
    $fr = [];
    foreach (db()->query('SELECT DISTINCT min_rank,fuseright FROM rank_fuserights ORDER BY min_rank') as $r) $fr[(int)$r['min_rank']][] = $r['fuseright'];
    $counts = [];
    foreach (db()->query('SELECT rank,COUNT(*) c FROM users GROUP BY rank') as $r) $counts[(int)$r['rank']] = (int)$r['c'];
    foreach (RANKS as $n => $lbl) {
        echo '<div class="panel"><div class="ph"><h3>' . rank_badge($n) . ' ' . h($lbl) . '</h3><span class="muted" style="margin-left:auto">' . ($counts[$n] ?? 0) . ' joueur(s)</span></div>';
        echo '<p class="muted sm">Débloque à ce niveau :</p><div class="perms">';
        $list = array_values(array_unique($fr[$n] ?? []));
        if (!$list) echo '<span class="muted">— (hérite des rangs inférieurs)</span>';
        else foreach ($list as $f) echo '<span class="tag">' . h(fuse_label($f)) . '</span>';
        echo '</div></div>';
    }
}

/* === Assistance : conversations joueur ↔ staff (3 colonnes, à vues) ======== */
function conv_cats(): array { return ['question' => '❓ Question', 'bug' => '🐞 Bug', 'signalement' => '🚩 Signalement', 'compte' => '👤 Compte', 'contact' => '✉️ Contact', 'staff' => '📣 Équipe', 'autre' => '• Autre']; }
function conv_stat_labels(): array { return ['waiting_staff' => '🔴 À répondre', 'waiting_player' => '⏳ Attente joueur', 'resolved' => '✅ Résolu']; }

/** Barre d'outils de l'assistance (nouveau message, reprise, accusé, annonce). */
function conv_tools_bar(string $me): void {
    $plName = ''; $plFilter = (int)($_GET['pl'] ?? 0);
    if ($plFilter > 0) { try { $qp = db()->prepare('SELECT username FROM users WHERE id=?'); $qp->execute([$plFilter]); $plName = (string)$qp->fetchColumn(); } catch (Throwable $e) {} }
    echo '<div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-start;margin-bottom:8px">';
    // Action principale : nouveau message
    echo '<details style="flex:1;min-width:260px"' . ($plFilter > 0 ? ' open' : '') . '><summary class="mini" style="font-weight:700">✉️ Nouveau message</summary>'
       . '<form method="post" class="js" data-reload style="margin-top:8px"><input type="hidden" name="action" value="conv_new">' . csrf_field()
       . '<div class="row"><label style="flex:1">Destinataire<input name="username" list="dl_pseudos" autocomplete="off" placeholder="Pseudo…" value="' . h($plName) . '" required></label>'
       . '<label style="flex:1">Sujet<input name="subject" maxlength="150" placeholder="Ex : Réponse à ta demande"></label></div>'
       . '<label style="margin-top:6px">Message<textarea name="body" rows="3" maxlength="4000" required placeholder="Ton message pour le joueur."></textarea></label>'
       . '<button style="margin-top:8px">✉️ Créer la conversation</button></form></details>';
    // Outils regroupés (accusé, annonce, reprise) — hors du flux quotidien
    $ackVal = ''; try { $qa = db()->prepare('SELECT value FROM settings WHERE setting=?'); $qa->execute(['conv.ack']); $v = $qa->fetchColumn(); if ($v !== false) $ackVal = (string)$v; } catch (Throwable $e) {}
    // Reste-t-il d'anciens messages à reprendre ?
    $legacyLeft = 0;
    try { $legacyLeft = (int)db()->query("SELECT (SELECT COUNT(*) FROM contact_messages cm WHERE NOT EXISTS (SELECT 1 FROM conversations c WHERE c.legacy_ref=CONCAT('cm:',cm.id))) + (SELECT COUNT(*) FROM user_messages um WHERE NOT EXISTS (SELECT 1 FROM conversations c2 WHERE c2.legacy_ref=CONCAT('um:',um.id)))")->fetchColumn(); } catch (Throwable $e) { $legacyLeft = 0; }
    echo '<details style="min-width:240px"><summary class="mini">⚙️ Outils d\'assistance</summary><div style="margin-top:8px;display:flex;flex-direction:column;gap:12px">';
    // Accusé
    echo '<form method="post" class="js" data-reload><input type="hidden" name="action" value="conv_ack_set">' . csrf_field()
       . '<div class="sm muted" style="margin-bottom:3px">Accusé de réception automatique (vide = désactivé)</div>'
       . '<textarea name="body" rows="2" maxlength="1000" placeholder="Laisser vide pour désactiver" style="width:100%">' . h($ackVal) . '</textarea>'
       . '<button class="mini" style="margin-top:4px">💾 Enregistrer l\'accusé</button></form>';
    // Annonce collective → raccourci vers le hub « Alertes & annonces » (logique unique)
    echo '<div><div class="sm muted" style="margin-bottom:3px">Annonce collective</div>'
       . '<a class="mini ghost" href="?p=alerts&sec=announce">📨 Ouvrir dans « Alertes &amp; annonces »</a>'
       . '<p class="muted sm" style="margin:4px 0 0">Diffusion persistante (une conversation par destinataire), avec historique.</p></div>';
    // Reprise — seulement s'il reste des anciens messages
    if ($legacyLeft > 0) {
        echo '<form method="post" class="js" data-reload data-confirm="Importer ' . $legacyLeft . ' ancien(s) message(s) en conversations ? Sans doublon, relançable sans risque."><input type="hidden" name="action" value="conv_reprise">' . csrf_field()
           . '<div class="sm muted" style="margin-bottom:3px">Migration des anciens messages</div>'
           . '<button class="mini ghost">📥 Reprendre ' . $legacyLeft . ' ancien(s) message(s)</button>'
           . '<p class="muted sm" style="margin:4px 0 0">Convertit les anciens messages (contact + équipe) une seule fois.</p></form>';
    } else {
        echo '<div class="sm muted">📥 Reprise des anciens messages : <b>terminée</b> (rien à importer).</div>';
    }
    echo '</div></details>';
    echo '</div>';
}

/** Panneau droit : informations du joueur. */
function conv_info_panel(array $conv): void {
    $pid = (int)$conv['player_id'];
    echo '<div class="ph"><h3>👤 Joueur</h3></div>';
    $info = null;
    if ($pid > 0) { try { $qi = db()->prepare('SELECT username,`rank`,credits,motto,last_online FROM users WHERE id=?'); $qi->execute([$pid]); $info = $qi->fetch() ?: null; } catch (Throwable $e) {} }
    if (!$info) echo '<p class="muted sm">Joueur introuvable (compte supprimé ou message repris sans compte).</p>';
    else {
        echo '<p style="margin:0 0 4px"><b>' . h((string)$info['username']) . '</b> ' . rank_badge((int)$info['rank']) . '</p>';
        if (trim((string)$info['motto']) !== '') echo '<p class="sm muted" style="margin:0 0 3px">💬 ' . h((string)$info['motto']) . '</p>';
        echo '<p class="sm muted" style="margin:0 0 3px">🪙 ' . number_format((int)$info['credits'], 0, ',', ' ') . ' crédits</p>';
        echo '<p class="sm muted" style="margin:0 0 8px">🕑 ' . ((int)$info['last_online'] > 0 ? h(date('d/m/Y H:i', (int)$info['last_online'])) : '—') . '</p>';
        echo '<div style="display:flex;flex-direction:column;gap:6px">'
           . '<a class="mini ghost" href="?p=user&id=' . $pid . '">👤 Fiche complète</a>'
           . '<a class="mini ghost" href="/?p=home/' . h(rawurlencode((string)$info['username'])) . '" target="_blank">🏠 Habbo Home ↗</a>'
           . '<a class="mini ghost" href="?p=messages&pl=' . $pid . '">💬 Ses conversations</a>'
           . '<a class="mini ghost" href="?p=homemod&sec=reports">🚩 Dossiers Homes</a>'
           . '</div>';
    }
    echo '<div class="ph" style="margin-top:12px"><h3>📌 Responsable</h3></div>';
    echo '<p class="sm" style="margin:0">' . ((string)$conv['assigned_to'] !== '' ? '🙋 ' . h((string)$conv['assigned_to']) : '<span class="muted">non attribuée</span>') . '</p>';
}

/** Panneau central : fil + actions d'une conversation. */
function conv_detail_panel(array $conv, int $cid, string $me, array $CATS, array $statLabel, string $scope): void {
    $name = trim((string)($conv['uname'] ?? '')) ?: '—';
    $assignedTo = (string)($conv['assigned_to'] ?? '');
    $curCat = (string)$conv['category']; if (!isset($CATS[$curCat])) $curCat = 'autre';

    // --- En-tête compact : sujet + statut, puis joueur, puis catégorie + attribution ---
    echo '<div style="border:1px solid var(--line2);border-radius:10px;padding:10px 12px;margin-bottom:10px">';
    echo '<div class="row" style="align-items:center;gap:8px;flex-wrap:wrap"><h3 style="margin:0;flex:1;min-width:0">' . h((string)($conv['subject'] ?: 'Sans sujet')) . '</h3>'
       . '<span class="rk">' . h($statLabel[(string)$conv['status']] ?? (string)$conv['status']) . '</span></div>';
    echo '<div class="sm muted" style="margin-top:4px">👤 <b>' . h($name) . '</b>'
       . ((int)$conv['player_id'] > 0 ? ' · <a class="lnk" href="?p=user&id=' . (int)$conv['player_id'] . '">fiche</a>' : '')
       . ($conv['legacy_ref'] ? ' · <span title="Repris de l\'ancienne messagerie">repris</span>' : '') . '</div>';
    echo '<div class="row" style="gap:12px;flex-wrap:wrap;align-items:center;margin-top:8px">';
    // Catégorie
    echo '<form method="post" class="js" data-reload style="display:flex;align-items:center;gap:4px">' . csrf_field()
       . '<input type="hidden" name="action" value="conv_category"><input type="hidden" name="cid" value="' . $cid . '">'
       . '<span class="sm muted">🏷️</span><select name="category" class="mini">';
    foreach ($CATS as $ck => $cl) echo '<option value="' . h($ck) . '"' . ($curCat === $ck ? ' selected' : '') . '>' . h($cl) . '</option>';
    echo '</select><button class="mini ghost">OK</button></form>';
    // Attribution
    echo '<form method="post" class="js" data-reload style="display:flex;align-items:center;gap:6px">' . csrf_field()
       . '<input type="hidden" name="action" value="conv_assign"><input type="hidden" name="cid" value="' . $cid . '">';
    if ($assignedTo === '') echo '<span class="sm muted">Responsable : aucun</span> <button class="mini" name="to" value="me">🙋 M\'attribuer</button>';
    elseif ($assignedTo === $me) echo '<span class="sm">Responsable : <b>toi</b></span> <button class="mini ghost" name="to" value="">↩︎ Libérer</button>';
    else echo '<span class="sm" style="color:#b5651d">Responsable : <b>' . h($assignedTo) . '</b></span> <button class="mini ghost" name="to" value="me" data-confirm="Déjà pris par ' . h($assignedTo) . '. Reprendre ?">Reprendre</button>';
    echo '</form>';
    echo '</div></div>';

    // Fil
    echo '<div class="convthread">';
    foreach (conv_messages($cid, true) as $m) {
        $snd = (string)$m['sender'];
        $mid = (int)$m['id'];
        if ($snd === 'system') {
            echo '<div class="cmsg2 cm-system"><div class="sm muted">ℹ️ Message automatique · ' . h(date('d/m H:i', strtotime((string)$m['created_at']))) . '</div><div class="cm-body">' . nl2br(h((string)$m['body'])) . '</div></div>';
            continue;
        }
        $staff = ($snd !== 'player');
        $internal = (int)$m['is_internal'];
        $cls = $staff ? 'cm-staff' : 'cm-player';
        if ($internal) $cls .= ' cm-internal';
        $role = $internal ? '🔒 Note interne (staff)' : ($staff ? '👮 Staff' : '🙂 Joueur');
        $head = '<span style="display:inline-block;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;background:var(--soft);border-radius:4px;padding:0 4px;margin-right:4px">' . h($role) . '</span>'
             . h((string)($m['sender_name'] ?: ($staff ? 'Équipe' : $name))) . ' · ' . h(date('d/m H:i', strtotime((string)$m['created_at'])));
        echo '<div class="cmsg2 ' . $cls . '"><div class="sm muted">' . $head . '</div>';
        if ((int)$m['moderated']) {
            echo '<div class="cm-body" style="font-style:italic;color:#a33">🚫 Message retiré par la modération'
               . ((string)$m['moderated_reason'] !== '' ? ' — ' . h((string)$m['moderated_reason']) : '') . '</div>'
               . '<details style="margin-top:4px"><summary class="mini">👁️ Voir le contenu masqué (réservé staff)</summary>'
               . '<div class="cm-body" style="margin-top:4px;opacity:.75">' . nl2br(h((string)$m['body'])) . '</div>'
               . '<div class="sm muted">masqué par ' . h((string)$m['moderated_by']) . ($m['moderated_at'] ? ' le ' . h(date('d/m H:i', strtotime((string)$m['moderated_at']))) : '') . '</div>'
               . '<form method="post" class="js" data-reload style="margin-top:4px"><input type="hidden" name="action" value="conv_hide">' . csrf_field()
               . '<input type="hidden" name="cid" value="' . $cid . '"><input type="hidden" name="mid" value="' . $mid . '"><input type="hidden" name="on" value="0"><button class="mini ghost">✅ Rétablir le message</button></form></details>';
        } else {
            echo '<div class="cm-body">' . nl2br(h((string)$m['body'])) . '</div>';
            if (!$internal) {
                echo '<details style="margin-top:4px"><summary class="mini" style="font-size:10px">🚫 Masquer (modération)</summary>'
                   . '<form method="post" class="js" data-reload data-confirm="Masquer ce message aux participants ?" style="margin-top:4px"><input type="hidden" name="action" value="conv_hide">' . csrf_field()
                   . '<input type="hidden" name="cid" value="' . $cid . '"><input type="hidden" name="mid" value="' . $mid . '"><input type="hidden" name="on" value="1">'
                   . '<input name="reason" maxlength="255" placeholder="Motif (obligatoire)" required style="width:100%;margin-bottom:4px"><button class="mini">Masquer</button></form></details>';
            }
        }
        echo '</div>';
    }
    echo '</div>';

    // Réponse + réponses types
    $CANNED = [
        'Bonjour, merci pour ton message ! ',
        'Bonjour, merci de ton message. Peux-tu nous donner plus de détails (pseudo concerné, heure, salle) ?',
        'C\'est corrigé de notre côté, n\'hésite pas si le souci revient. Bon jeu ! 😊',
        'Ta demande a bien été transmise à l\'équipe concernée, on revient vers toi dès que possible.',
        'Pour des raisons de sécurité, nous ne demandons jamais ton mot de passe. Ne le communique à personne.',
        'Nous ne pouvons pas donner suite à cette demande. Merci de ta compréhension.',
    ];
    $rid = 'cvbody' . $cid;
    echo '<form method="post" class="js" data-reload style="margin-top:10px"><input type="hidden" name="action" value="conv_reply">' . csrf_field()
       . '<input type="hidden" name="cid" value="' . $cid . '">'
       . '<select class="mini" style="margin-bottom:6px" onchange="if(this.value){var t=document.getElementById(\'' . $rid . '\');t.value=(t.value?t.value+\'\n\':\'\')+this.value;t.focus();this.selectedIndex=0;}">'
       . '<option value="">💬 Insérer une réponse type…</option>';
    foreach ($CANNED as $txt) echo '<option value="' . h($txt) . '">' . h(mb_strimwidth($txt, 0, 60, '…')) . '</option>';
    echo '</select>'
       . '<textarea id="' . $rid . '" name="body" rows="3" maxlength="4000" required placeholder="Ta réponse au joueur…" style="width:100%"></textarea>'
       . '<div class="row" style="margin-top:8px;gap:8px;align-items:center"><button name="resolve" value="0">✉️ Répondre</button>'
       . '<button class="ghost" name="resolve" value="1">✅ Répondre et résoudre</button></div></form>';

    // Note interne
    echo '<details style="margin-top:10px"><summary class="mini">🔒 Note interne (non visible du joueur)</summary>'
       . '<form method="post" class="js" data-reload style="margin-top:8px"><input type="hidden" name="action" value="conv_note">' . csrf_field()
       . '<input type="hidden" name="cid" value="' . $cid . '"><textarea name="body" rows="2" maxlength="4000" required placeholder="Note visible uniquement par l\'équipe…" style="width:100%"></textarea>'
       . '<button class="mini" style="margin-top:6px">🔒 Enregistrer la note</button></form></details>';

    // Statut + archivage/corbeille du dossier
    echo '<div class="row" style="margin-top:10px;gap:6px;flex-wrap:wrap;align-items:center">';
    $cur = (string)$conv['status'];
    echo '<form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="conv_status"><input type="hidden" name="cid" value="' . $cid . '">'
       . '<span class="sm muted">Statut :</span> '
       . '<button class="mini' . ($cur === 'waiting_staff' ? '' : ' ghost') . '" name="status" value="waiting_staff">🔴 À traiter</button> '
       . '<button class="mini' . ($cur === 'waiting_player' ? '' : ' ghost') . '" name="status" value="waiting_player">⏳ En attente du joueur</button> '
       . '<button class="mini' . ($cur === 'resolved' ? '' : ' ghost') . '" name="status" value="resolved">✅ Résolu</button></form>';
    echo ' <span class="sm muted">·</span> ';
    if ($scope === 'archives' || $scope === 'trash') {
        echo '<form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="conv_staff_box"><input type="hidden" name="cid" value="' . $cid . '"><input type="hidden" name="op" value="restore"><button class="mini ghost">↩︎ Restaurer</button></form>';
    } else {
        echo '<form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="conv_staff_box"><input type="hidden" name="cid" value="' . $cid . '"><input type="hidden" name="op" value="archive"><button class="mini ghost" title="Archiver un dossier traité">🗂 Archiver</button></form> ';
        echo '<form method="post" class="js" data-reload style="display:inline" data-confirm="Mettre ce dossier à la corbeille ?">' . csrf_field() . '<input type="hidden" name="action" value="conv_staff_box"><input type="hidden" name="cid" value="' . $cid . '"><input type="hidden" name="op" value="trash"><button class="mini ghost">🗑 Corbeille</button></form>';
    }
    echo '</div>';

    // Fusion de doublons
    $pid = (int)$conv['player_id'];
    if ($pid > 0) {
        $dups = []; try { $qd = db()->prepare('SELECT id,subject,updated_at FROM conversations WHERE player_id=? AND id<>? ORDER BY updated_at DESC LIMIT 50'); $qd->execute([$pid, $cid]); $dups = $qd->fetchAll(); } catch (Throwable $e) {}
        if ($dups) {
            echo '<details style="margin-top:10px"><summary class="mini">🔗 Fusionner un doublon ici</summary>'
               . '<form method="post" class="js" data-reload data-confirm="Fusionner : les messages de la conversation choisie seront déplacés ici, puis elle sera supprimée. Continuer ?" style="margin-top:8px"><input type="hidden" name="action" value="conv_merge">' . csrf_field()
               . '<input type="hidden" name="cid" value="' . $cid . '"><select name="src" class="mini" required><option value="">Conversation à absorber…</option>';
            foreach ($dups as $d) echo '<option value="' . (int)$d['id'] . '">#' . (int)$d['id'] . ' · ' . h(mb_strimwidth((string)($d['subject'] ?: 'Sans sujet'), 0, 40, '…')) . '</option>';
            echo '</select> <button class="mini">🔗 Fusionner</button></form></details>';
        }
    }

    // Historique des actions
    $evs = function_exists('conv_events') ? conv_events($cid, 40) : [];
    echo '<details style="margin-top:10px"><summary class="mini">📜 Historique des actions</summary>';
    if (!$evs) echo '<p class="muted sm" style="margin-top:6px">Aucune action enregistrée.</p>';
    else {
        echo '<table class="clean" style="margin-top:6px"><tr><th>Quand</th><th>Par</th><th>Action</th></tr>';
        foreach ($evs as $e) echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$e['created_at']))) . '</td><td class="sm">' . h((string)$e['actor']) . '</td><td class="sm">' . h((string)$e['action']) . ((string)$e['detail'] !== '' ? ' — ' . h((string)$e['detail']) : '') . '</td></tr>';
        echo '</table>';
    }
    echo '</details>';
}

function conv_assistance_view(string $scope = 'assistance'): void {
    ensure_conversations();
    $me = (string)($_SESSION['admin']['username'] ?? '?');
    $CATS = conv_cats(); $statLabel = conv_stat_labels();
    $catLabel = function (string $c) use ($CATS) { return $CATS[$c] ?? ('• ' . $c); };

    // Détail ouvert ?
    $cid = (int)($_GET['c'] ?? 0);
    $conv = null;
    if ($cid > 0) {
        $st = db()->prepare('SELECT c.*, u.username AS uname FROM conversations c LEFT JOIN users u ON u.id=c.player_id WHERE c.id=?');
        $st->execute([$cid]); $conv = $st->fetch() ?: null;
        if ($conv) conv_mark_read_staff($cid);
    }

    // Filtres + recherche
    $filter = in_array(($_GET['f'] ?? ''), ['mine', 'unassigned'], true) ? (string)$_GET['f'] : 'all';
    $catFilter = (isset($_GET['cat']) && isset($CATS[(string)$_GET['cat']])) ? (string)$_GET['cat'] : '';
    $plFilter = (int)($_GET['pl'] ?? 0);
    $q = trim((string)($_GET['q'] ?? ''));
    $plName = ''; if ($plFilter > 0) { try { $qp = db()->prepare('SELECT username FROM users WHERE id=?'); $qp->execute([$plFilter]); $plName = (string)$qp->fetchColumn(); } catch (Throwable $e) {} }

    // Périmètre (scope)
    $conds = []; $args = [];
    if ($scope === 'archives') $conds[] = "c.staff_trashed=0 AND (c.staff_archived=1 OR c.status='resolved')";
    elseif ($scope === 'trash') $conds[] = 'c.staff_trashed=1';
    elseif ($scope === 'sent') $conds[] = "c.staff_trashed=0 AND c.category='staff'";
    else { $scope = 'assistance'; $conds[] = "c.staff_trashed=0 AND c.staff_archived=0 AND c.status<>'resolved'"; }
    if ($filter === 'mine') { $conds[] = 'c.assigned_to=?'; $args[] = $me; }
    elseif ($filter === 'unassigned') { $conds[] = "c.assigned_to=''"; }
    if ($catFilter !== '') { $conds[] = 'c.category=?'; $args[] = $catFilter; }
    if ($plFilter > 0) { $conds[] = 'c.player_id=?'; $args[] = $plFilter; }
    if ($q !== '') { $conds[] = '(c.subject LIKE ? OR u.username LIKE ?)'; $args[] = '%' . $q . '%'; $args[] = '%' . $q . '%'; }
    $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

    // Pagination
    $pg = max(1, (int)($_GET['pg'] ?? 1)); $per = 40; $off = ($pg - 1) * $per;
    $rows = []; $total = 0;
    try {
        $cs = db()->prepare("SELECT COUNT(*) FROM conversations c LEFT JOIN users u ON u.id=c.player_id $where"); $cs->execute($args); $total = (int)$cs->fetchColumn();
        $sql = "SELECT c.id,c.subject,c.category,c.status,c.player_id,c.legacy_ref,c.assigned_to,c.updated_at,
                u.username AS uname,
                (SELECT COUNT(*) FROM conversation_messages m WHERE m.conv_id=c.id AND m.sender='player' AND m.read_staff=0) AS unread,
                (SELECT IF(m2.moderated=1,'[message modéré]',m2.body) FROM conversation_messages m2 WHERE m2.conv_id=c.id AND m2.is_internal=0 ORDER BY m2.id DESC LIMIT 1) AS last_body
            FROM conversations c LEFT JOIN users u ON u.id=c.player_id
            $where ORDER BY (c.status='resolved') ASC, c.updated_at DESC LIMIT $per OFFSET $off";
        $qs = db()->prepare($sql); $qs->execute($args); $rows = $qs->fetchAll();
    } catch (Throwable $e) { $rows = []; }

    $titles = ['assistance' => '🎧 Assistance', 'sent' => '📤 Envoyés', 'archives' => '🗂 Archives', 'trash' => '🗑 Corbeille'];
    $base = '?p=messages&vue=' . $scope . ($plFilter > 0 ? '&pl=' . $plFilter : '');

    echo '<div class="panel">';
    echo '<div class="ph"><h3>' . ($titles[$scope] ?? '🎧 Assistance') . '</h3>'
       . '<span class="muted sm" style="margin-left:auto">' . $total . ' conversation' . ($total > 1 ? 's' : '') . '</span> '
       . '<button class="mini ghost" type="button" onclick="document.getElementById(\'cvwrap\').classList.toggle(\'noinfo\')" title="Afficher/masquer les infos joueur">↔ Infos</button></div>';
    if ($plFilter > 0) echo '<div class="note" style="margin:0 0 8px">👤 Conversations de <b>' . h($plName !== '' ? $plName : ('#' . $plFilter)) . '</b> · <a class="lnk" href="?p=messages&vue=' . $scope . '">voir toutes</a></div>';

    // Recherche + filtres
    echo '<form method="get" class="row" style="gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:8px"><input type="hidden" name="p" value="messages"><input type="hidden" name="vue" value="' . $scope . '">' . ($plFilter > 0 ? '<input type="hidden" name="pl" value="' . $plFilter . '">' : '')
       . '<input name="q" value="' . h($q) . '" placeholder="Rechercher sujet ou joueur…" style="flex:1;min-width:150px">'
       . '<select name="f"><option value="all"' . ($filter === 'all' ? ' selected' : '') . '>Toutes</option><option value="unassigned"' . ($filter === 'unassigned' ? ' selected' : '') . '>Non attribuées</option><option value="mine"' . ($filter === 'mine' ? ' selected' : '') . '>Les miennes</option></select>'
       . '<select name="cat"><option value="">Catégorie…</option>';
    foreach ($CATS as $ck => $cl) echo '<option value="' . h($ck) . '"' . ($catFilter === $ck ? ' selected' : '') . '>' . h($cl) . '</option>';
    echo '</select><button class="mini">Filtrer</button></form>';

    if ($scope === 'assistance') conv_tools_bar($me);

    // Layout 3 colonnes
    echo '<div class="cvwrap" id="cvwrap">';
    // Gauche : liste
    echo '<div class="cvlist">';
    if (!$rows) echo '<div class="empty">Aucune conversation ici.</div>';
    else foreach ($rows as $r) {
        $nm = trim((string)($r['uname'] ?: '')) ?: ('#' . (int)$r['player_id']);
        $sel = ($cid === (int)$r['id']) ? ' sel' : '';
        $unread = (int)$r['unread'];
        $snip = mb_substr(trim(preg_replace('/\s+/', ' ', (string)$r['last_body'])), 0, 54);
        echo '<a class="hmrow' . $sel . '" href="' . $base . '&c=' . (int)$r['id'] . '">'
           . '<div class="hmrow-top"><b>' . h((string)($r['subject'] ?: 'Sans sujet')) . '</b>' . ($unread ? '<span class="rk red" style="margin-left:auto">' . $unread . '</span>' : '') . '</div>'
           . '<div class="sm muted">' . h($catLabel((string)$r['category'])) . ' · ' . h($statLabel[(string)$r['status']] ?? '') . ((string)$r['assigned_to'] !== '' ? ' · 🙋 ' . h((string)$r['assigned_to']) : '') . '</div>'
           . '<div class="sm muted" style="opacity:.8">' . h($nm) . ' · ' . h(date('d/m H:i', strtotime((string)$r['updated_at']))) . '</div>'
           . '<div class="sm muted" style="opacity:.7">' . h($snip) . '</div></a>';
    }
    $pages = (int)ceil($total / $per);
    if ($pages > 1) {
        $flt = ($q !== '' ? '&q=' . urlencode($q) : '') . ($filter !== 'all' ? '&f=' . $filter : '') . ($catFilter !== '' ? '&cat=' . urlencode($catFilter) : '');
        echo '<div class="pager" style="margin-top:8px"><span class="muted sm">p.' . $pg . '/' . $pages . '</span><div>';
        if ($pg > 1) echo '<a class="mini ghost" href="' . $base . $flt . '&pg=' . ($pg - 1) . '">←</a> ';
        if ($pg < $pages) echo '<a class="mini ghost" href="' . $base . $flt . '&pg=' . ($pg + 1) . '">→</a>';
        echo '</div></div>';
    }
    echo '</div>'; // cvlist
    // Centre : fil
    echo '<div class="cvmain">';
    if (!$conv) echo '<div class="empty">← Choisis une conversation dans la liste.</div>';
    else conv_detail_panel($conv, $cid, $me, $CATS, $statLabel, $scope);
    echo '</div>';
    // Droite : infos joueur
    echo '<div class="cvinfo">';
    if ($conv) conv_info_panel($conv); else echo '<div class="muted sm">Sélectionne une conversation pour voir les infos du joueur.</div>';
    echo '</div>';
    echo '</div>'; // cvwrap
    echo '</div>'; // panel
}

/** Vue : discussion interne de l'équipe (staff ↔ staff). */
function conv_view_team(): void {
    ensure_staff_msgs();
    $me = (string)($_SESSION['admin']['username'] ?? '?');
    $feed = db()->query('SELECT id,author,body,created_at FROM staff_messages ORDER BY id DESC LIMIT 60')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>👥 Discussion de l\'équipe</h3><span class="muted sm" style="margin-left:auto">visible par tout le staff</span></div>';
    echo '<form method="post" class="js" data-reload><input type="hidden" name="action" value="smsg_post">' . csrf_field() . '<textarea name="body" rows="2" maxlength="2000" required placeholder="Écris un message à l\'équipe…" style="width:100%"></textarea><button style="margin-top:8px">💬 Publier</button></form>';
    if (!$feed) echo '<div class="empty">Aucun message pour l\'instant. Lance la discussion !</div>';
    else {
        echo '<div class="stafffeed">';
        foreach ($feed as $m) {
            $mine = ($m['author'] === $me);
            echo '<div class="smsg' . ($mine ? ' mine' : '') . '"><div class="sm-head"><b>' . h((string)$m['author']) . '</b><span class="sm-date">' . h(date('d/m H:i', strtotime((string)$m['created_at']))) . '</span>';
            if ($mine || my_rank() >= 7) echo '<form method="post" class="js" data-reload style="display:inline;margin-left:8px" data-confirm="Supprimer ce message ?"><input type="hidden" name="action" value="smsg_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$m['id'] . '"><button class="mini ghost">✕</button></form>';
            echo '</div><div class="sm-body">' . nl2br(h((string)$m['body'])) . '</div></div>';
        }
        echo '</div>';
    }
    echo '</div>';
    if ($feed) { $maxId = (int)$feed[0]['id']; db()->prepare('INSERT INTO staff_seen (username,last_id) VALUES (?,?) ON DUPLICATE KEY UPDATE last_id=GREATEST(last_id,VALUES(last_id))')->execute([$me, $maxId]); }
}

/** Vue : récupération de compte (demandes MDP + anciens messages contact non repris). */
function conv_view_recovery(): void {
    ensure_pw_resets(); ensure_contact_msgs();
    $reqs = db()->query("SELECT id,username,message,ip,created_at FROM password_resets WHERE status='pending' ORDER BY created_at ASC")->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🔑 Demandes de mot de passe</h3>' . ($reqs ? '<span class="rk red" style="margin-left:auto">' . count($reqs) . ' en attente</span>' : '') . '</div>';
    echo '<p class="muted sm" style="margin:-2px 0 8px">Vérifie l\'identité (indice fourni) avant de réinitialiser. Accès réservé au staff autorisé. Aucun mot de passe n\'est transmis par la messagerie.</p>';
    if (!$reqs) echo '<div class="empty">Aucune demande en attente 🎉</div>';
    else {
        echo '<table class="clean"><tr><th>Reçue</th><th>Pseudo</th><th>Indice</th><th>IP</th><th style="width:230px">Action</th></tr>';
        foreach ($reqs as $r) {
            echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$r['created_at']))) . '</td>'
               . '<td><b>' . h((string)$r['username']) . '</b></td><td class="muted sm">' . h((string)$r['message']) . '</td><td class="sm muted">' . h((string)$r['ip']) . '</td>'
               . '<td><form method="post" style="display:inline" onsubmit="return confirm(\'Réinitialiser le mot de passe de « ' . h(addslashes((string)$r['username'])) . ' » ? Un mot de passe provisoire sera généré.\')">' . csrf_field() . '<input type="hidden" name="action" value="pwreset_do"><input type="hidden" name="rid" value="' . (int)$r['id'] . '"><button class="mini">🔑 Traiter</button></form> '
               . '<form method="post" class="js" data-reload style="display:inline" data-confirm="Rejeter cette demande ?"><input type="hidden" name="action" value="pwreset_reject">' . csrf_field() . '<input type="hidden" name="rid" value="' . (int)$r['id'] . '"><button class="mini ghost">Rejeter</button></form></td></tr>';
        }
        echo '</table>';
    }
    echo '</div>';

    // Anciens messages du formulaire contact non encore repris (accès préservé)
    $new = db()->query("SELECT id,username,subject,message,ip,created_at FROM contact_messages WHERE status='new' ORDER BY created_at ASC")->fetchAll();
    if ($new) {
        echo '<div class="panel"><div class="ph"><h3>📥 Anciens messages du formulaire contact</h3><span class="rk red" style="margin-left:auto">' . count($new) . '</span></div>';
        echo '<p class="muted sm" style="margin:-2px 0 8px">Messages de l\'ancien formulaire. Utilise « Reprendre les anciens messages » dans l\'Assistance pour les convertir en conversations.</p>';
        echo '<table class="clean"><tr><th>Reçu</th><th>De</th><th>Message</th><th style="width:160px"></th></tr>';
        foreach ($new as $m) {
            echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$m['created_at']))) . '</td><td><b>' . h((string)($m['username'] ?: '—')) . '</b></td>'
               . '<td>' . (trim((string)$m['subject']) !== '' ? '<b>' . h((string)$m['subject']) . '</b><br>' : '') . '<span class="muted">' . nl2br(h((string)$m['message'])) . '</span></td>'
               . '<td><form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="cmsg_handle">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$m['id'] . '"><button class="mini">✅ Traité</button></form> '
               . '<form method="post" class="js" data-reload style="display:inline" data-confirm="Supprimer ?"><input type="hidden" name="action" value="cmsg_delete">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$m['id'] . '"><button class="mini ghost">✕</button></form></td></tr>';
        }
        echo '</table></div>';
    }
}

function page_messages(): void {
    page_title('Messagerie', 'Assistance : conversations joueurs ↔ équipe');
    ensure_contact_msgs(); ensure_pw_resets(); ensure_user_msgs();
    if (!function_exists('ensure_conversations')) { echo '<div class="empty">Module de conversations indisponible.</div>'; return; }
    ensure_conversations();
    $me = (string)($_SESSION['admin']['username'] ?? '?');
    $vue = (string)($_GET['vue'] ?? 'assistance');
    if (!in_array($vue, ['assistance', 'sent', 'team', 'recovery', 'archives', 'trash'], true)) $vue = 'assistance';

    // Compteurs d'onglets
    $cWait = function_exists('conv_unread_staff') ? conv_unread_staff() : 0;
    $cRecov = 0; try { $cRecov = (int)db()->query("SELECT COUNT(*) FROM password_resets WHERE status='pending'")->fetchColumn(); } catch (Throwable $e) {}
    $cTeam = function_exists('staff_unread') ? staff_unread($me) : 0;

    $tabs = [
        'assistance' => '🎧 Assistance' . ($cWait ? ' (' . $cWait . ')' : ''),
        'sent' => '📤 Envoyés',
        'team' => '👥 Discussion équipe' . ($cTeam ? ' (' . $cTeam . ')' : ''),
        'recovery' => '🔑 Récupération' . ($cRecov ? ' (' . $cRecov . ')' : ''),
        'archives' => '🗂 Archives',
        'trash' => '🗑 Corbeille',
    ];
    echo '<div class="row" style="gap:4px;flex-wrap:wrap;margin-bottom:12px;border-bottom:2px solid var(--line2);padding-bottom:6px">';
    foreach ($tabs as $k => $lbl) echo '<a class="mini' . ($vue === $k ? '' : ' ghost') . '" href="?p=messages&vue=' . $k . '">' . $lbl . '</a>';
    echo '</div>';

    if ($vue === 'team') conv_view_team();
    elseif ($vue === 'recovery') conv_view_recovery();
    else conv_assistance_view($vue);
}

function page_moderation(): void {
    page_title('Modération', 'Bannissements et journal des actions');
    $prefill = trim($_GET['ban'] ?? '');
    echo '<div class="panel"><div class="ph"><h3>🚫 Bannir</h3></div><form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="ban_add">
        <label>Type<select name="type"><option value="USER_ID">Joueur (nom)</option><option value="IP_ADDRESS">Adresse IP</option><option value="MACHINE_ID">Machine ID</option></select></label>
        <label style="flex:1">Cible (nom ou IP)<input name="value" list="dl_pseudos" autocomplete="off" value="' . h($prefill) . '" required></label>
        <label>Durée (jours · 0 = définitif)<input type="number" name="days" value="0" style="width:110px"></label>
        <label style="flex:1">Motif<input name="message" value="Comportement inapproprié"></label><button>🚫 Bannir</button></form></div>';
    echo '<div class="panel"><div class="ph"><h3>Bannissements actifs</h3></div>';
    // Actifs = échéance encore dans le futur (banned_until en millisecondes). Les bans
    // expirés ne sont plus listés ici comme « actifs ».
    $nowMs = time() * 1000;
    $st = db()->prepare('SELECT b.ban_type,b.banned_value,b.message,b.banned_until,u.username FROM users_bans b LEFT JOIN users u ON (b.ban_type=\'USER_ID\' AND u.id=b.banned_value) WHERE b.banned_until > ? ORDER BY b.banned_until DESC');
    $st->execute([$nowMs]); $rows = $st->fetchAll();
    if (!$rows) echo '<div class="empty">Aucun bannissement actif 🎉</div>';
    else { echo '<table class="clean"><tr><th>Type</th><th>Cible</th><th>Motif</th><th>Jusqu\'à</th><th></th></tr>';
        foreach ($rows as $b) { $until = (int)$b['banned_until'] >= BAN_PERMANENT ? '♾️ Définitif' : date('d/m/Y H:i', (int)($b['banned_until'] / 1000)); $cible = $b['ban_type'] === 'USER_ID' ? ('👤 ' . h($b['username'] ?? ('#' . $b['banned_value']))) : h($b['banned_value']);
            echo '<tr><td class="muted sm">' . h($b['ban_type']) . '</td><td>' . $cible . '</td><td class="muted">' . h($b['message']) . '</td><td class="sm">' . $until . '</td><td><form method="post" class="js" data-reload data-confirm="Lever ce bannissement ?"><input type="hidden" name="action" value="ban_remove">' . csrf_field() . '<input type="hidden" name="value" value="' . h($b['banned_value']) . '"><button class="mini ghost">Lever</button></form></td></tr>'; }
        echo '</table>'; }
    echo '</div>';
    echo '<div class="panel"><div class="ph"><h3>📜 Journal des actions (en jeu)</h3></div>';
    $al = db()->query('SELECT a.action,a.message,a.created_at,s.username AS staff,t.username AS cible FROM housekeeping_audit_log a LEFT JOIN users s ON s.id=a.user_id LEFT JOIN users t ON t.id=a.target_id ORDER BY a.created_at DESC LIMIT 25')->fetchAll();
    if (!$al) echo '<div class="empty">Aucune action enregistrée pour le moment.</div>';
    else {
        $lbl = ['alert_user' => '💬 Alerte', 'kick_user' => '👢 Kick', 'ban_user' => '🚫 Ban', 'room_alert' => '📢 Alerte salle', 'room_kick' => '👢 Kick salle'];
        echo '<table class="clean"><tr><th>Date</th><th>Action</th><th>Staff</th><th>Cible</th><th>Message</th></tr>';
        foreach ($al as $a) echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$a['created_at']))) . '</td><td>' . h($lbl[$a['action']] ?? $a['action']) . '</td><td>' . h((string)($a['staff'] ?? '?')) . '</td><td>' . h((string)($a['cible'] ?? '-')) . '</td><td class="muted sm">' . h((string)$a['message']) . '</td></tr>';
        echo '</table>';
    }
    echo '</div>';
    // --- Logs de chat ---
    $cu = trim($_GET['cu'] ?? '');
    echo '<div class="panel"><div class="ph"><h3>💬 Logs de chat</h3><form method="get" class="srch"><input type="hidden" name="p" value="moderation"><input name="cu" list="dl_pseudos" autocomplete="off" value="' . h($cu) . '" placeholder="🔍 Filtrer par joueur..."><button class="mini">OK</button></form></div>';
    $sql = 'SELECT c.timestamp,c.chat_type,c.message,u.username,r.name AS room FROM room_chatlogs c LEFT JOIN users u ON u.id=c.user_id LEFT JOIN rooms r ON r.id=c.room_id';
    if ($cu !== '') { $st = db()->prepare($sql . ' WHERE u.username LIKE ? ORDER BY c.timestamp DESC LIMIT 100'); $st->execute(['%' . $cu . '%']); $logs = $st->fetchAll(); }
    else { $logs = db()->query($sql . ' ORDER BY c.timestamp DESC LIMIT 100')->fetchAll(); }
    if (!$logs) echo '<div class="empty">Aucun message enregistré' . ($cu !== '' ? ' pour ce joueur' : '') . '.</div>';
    else {
        $ct = [1 => '🗣️', 2 => '🤫'];
        echo '<table class="clean"><tr><th>Quand</th><th>Joueur</th><th>Salle</th><th>Message</th></tr>';
        foreach ($logs as $l) { $ts = (int)$l['timestamp']; $d = $ts > 0 ? date('d/m H:i', (int)($ts > 2000000000 ? $ts / 1000 : $ts)) : '—';
            echo '<tr><td class="sm muted">' . $d . '</td><td>' . h((string)($l['username'] ?? '?')) . '</td><td class="sm muted">' . h((string)($l['room'] ?? '-')) . '</td><td>' . ($ct[(int)$l['chat_type']] ?? '') . ' ' . h((string)$l['message']) . '</td></tr>'; }
        echo '</table>';
    }
    echo '</div>';

    // --- Multi-comptes (même IP) ---
    echo '<div class="panel"><div class="ph"><h3>🕵️ Multi-comptes (même IP)</h3></div>';
    $alts = db()->query('SELECT l.ip_address, COUNT(DISTINCT l.user_id) n, GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ", ") AS users FROM users_ip_logs l JOIN users u ON u.id=l.user_id GROUP BY l.ip_address HAVING n>1 ORDER BY n DESC LIMIT 40')->fetchAll();
    if (!$alts) echo '<div class="empty">Aucun multi-compte détecté.</div>';
    else {
        echo '<p class="muted sm">Plusieurs comptes ayant utilisé la même adresse IP — indice possible de multi-comptes.</p>';
        echo '<table class="clean"><tr><th>Adresse IP</th><th>Comptes concernés</th><th>Nb</th></tr>';
        foreach ($alts as $a) echo '<tr><td class="sm">' . h((string)$a['ip_address']) . '</td><td>' . h((string)$a['users']) . '</td><td><b>' . (int)$a['n'] . '</b></td></tr>';
        echo '</table>';
    }
    echo '</div>';

    // --- Débloquer l'inventaire ---
    echo '<div class="panel"><div class="ph"><h3>🧹 Débloquer un inventaire</h3></div>';
    echo '<p class="muted sm">⚠️ Attention : cette action <b>SUPPRIME TOUT l\'inventaire</b> du joueur (tous les objets non posés, pas seulement l\'objet coincé) et est <b>irréversible</b>. À n\'utiliser qu\'en dernier recours ; le joueur devra <b>se reconnecter</b> ensuite. <i>(Une suppression ciblée objet par objet est prévue — voir l\'inventaire du joueur.)</i></p>';
    echo '<form method="post" class="js row" data-reload data-confirm="⚠️ SUPPRIMER TOUT l\'inventaire (tous les objets non posés) de ce joueur ? Irréversible."><input type="hidden" name="action" value="clear_hand">' . csrf_field() . '<label style="flex:1">Nom du joueur<input name="username" list="dl_pseudos" autocomplete="off" required></label><button class="mini warn">🧹 Vider TOUT l\'inventaire</button></form></div>';

    echo '<div class="panel"><div class="ph"><h3>💬 Alertes en jeu</h3></div><p class="muted">Les annonces aux joueurs connectés se font <b>en jeu</b> avec ton compte admin : tape <code>:alert Ton message</code> dans le chat, ou utilise l\'outil de modération intégré.</p></div>';
}

function page_settings(): void {
    page_title('Réglages', 'Tous les paramètres du jeu, expliqués');
    $all = []; foreach (db()->query('SELECT setting,value FROM settings') as $r) $all[$r['setting']] = $r['value'];
    echo '<div class="panel"><div class="ph"><h3>⭐ Réglages courants</h3></div>';
    foreach (FRIENDLY_KEYS as $k) if (array_key_exists($k, $all)) settings_row($k, $all[$k]);
    echo '</div>';
    echo '<details class="panel"><summary><b>🔧 Tous les réglages (' . count($all) . ')</b></summary>';
    $filter = trim($_GET['q'] ?? '');
    echo '<form method="get" class="srch" style="margin:10px 0"><input type="hidden" name="p" value="settings"><input name="q" value="' . h($filter) . '" placeholder="🔍 Filtrer..."><button class="mini">OK</button></form>';
    ksort($all); foreach ($all as $k => $v) { if ($filter !== '' && stripos($k, $filter) === false) continue; settings_row($k, $v); }
    echo '</details>';
}
function settings_row(string $k, string $v): void {
    $desc = SETTING_DESC[$k] ?? '';
    echo '<form method="post" class="js setting">' . csrf_field() . '<input type="hidden" name="action" value="setting_update"><input type="hidden" name="setting" value="' . h($k) . '">
        <div class="srow"><code>' . h($k) . '</code><input name="value" value="' . h($v) . '"><button class="mini">💾</button></div>' . ($desc ? '<div class="hint">' . h($desc) . '</div>' : '') . '</form>';
}

function page_bus(): void {
    page_title('Bus (Infobus)', 'Se pilote en jeu — guide + mémo');

    // Trouver la salle du bus (modèle avec trigger infobus)
    $busRoom = db()->query("SELECT r.name FROM rooms r JOIN rooms_models m ON m.model_id=r.model WHERE m.trigger_class IN ('infobus_park','infobus_poll') AND r.owner_id='0' ORDER BY r.id LIMIT 1")->fetchColumn();
    $busRoom = $busRoom !== false ? $busRoom : 'la salle du bus';

    // Guide pas-à-pas
    echo '<div class="panel"><div class="ph"><h3>🚌 Ouvrir le bus — mode d\'emploi</h3></div>';
    echo '<ol style="line-height:1.9;margin:4px 0 12px;padding-left:22px">';
    echo '<li>Connecte-toi en jeu avec un compte <b>administrateur</b> (rang 7).</li>';
    echo '<li>Va dans la salle <b>« ' . h((string)$busRoom) . ' »</b> (Navigateur → Salles publiques).</li>';
    echo '<li>Tape cette commande dans le chat :</li>';
    echo '</ol>';
    echo '<div class="row" style="align-items:center;gap:8px;max-width:420px"><input id="buscmd" value=":infobus open" readonly style="flex:1;font-family:var(--mono);font-weight:700;font-size:15px"><button class="mini" type="button" onclick="var i=document.getElementById(\'buscmd\');i.select();document.execCommand(\'copy\');(window.toast?toast(\'Commande copiée !\'):0)">📋 Copier</button></div>';
    echo '<p class="hint">Tu verras le chuchotement « Info-bus door opened. », puis clique sur le bus pour embarquer. Pour fermer : <code>:infobus close</code>.</p></div>';

    echo '<details class="panel"><summary><b>📋 Toutes les commandes du bus</b></summary>';
    echo '<p class="hint" style="font-size:13px">L\'état du bus vit <b>en mémoire</b> dans l\'émulateur. Nécessite le droit <code>fuse_administrator</code> (rang 7). Alias : <code>:bus</code> = <code>:infobus</code>.</p>';
    echo '<table class="clean"><tr><th>Commande</th><th>Effet</th></tr>';
    $bus = [
        [':infobus open', 'Ouvre le bus (les joueurs peuvent monter)'],
        [':infobus close', 'Ferme le bus (plus personne ne monte)'],
        [':infobus question &lt;texte&gt;', 'Définit la question posée aux passagers'],
        [':infobus option add &lt;texte&gt;', 'Ajoute une réponse possible'],
        [':infobus option remove &lt;n°&gt;', 'Retire la réponse n°n'],
        [':infobus status', 'Affiche l\'état courant du bus'],
        [':infobus reset', 'Réinitialise la question et les options'],
    ];
    foreach ($bus as $b) echo '<tr><td><code>' . $b[0] . '</code></td><td>' . $b[1] . '</td></tr>';
    echo '</table></details>';

    // Type du bus (park = info/FRANK, poll = sondage/vote) — sur les modèles infobus
    $models = db()->query("SELECT id,model_id,model_name,trigger_class FROM rooms_models WHERE trigger_class IN ('infobus_park','infobus_poll') ORDER BY model_id")->fetchAll();
    if ($models) {
        // quelle salle utilise chaque modèle ?
        $usedBy = [];
        $mids = array_column($models, 'model_id');
        $stU = db()->prepare('SELECT name,model FROM rooms WHERE model IN (' . implode(',', array_fill(0, count($mids), '?')) . ')');
        $stU->execute($mids);
        foreach ($stU as $r) $usedBy[$r['model']][] = $r['name'];
        echo '<div class="panel"><div class="ph"><h3>🔀 Type de bus par salle</h3></div>';
        echo '<p class="hint" style="font-size:13px"><b>Info (FRANK)</b> = le bus affiche un message d\'information (texte anglais d\'origine du client). <b>Sondage / Vote</b> = le bus pose une question et les passagers votent (pilotable avec <code>:infobus question/option</code>). Après changement : <b>redémarre l\'émulateur</b> (onglet Serveur).</p>';
        echo '<table class="clean"><tr><th>Modèle</th><th>Salle(s)</th><th>Type</th></tr>';
        foreach ($models as $m) {
            $salles = isset($usedBy[$m['model_id']]) ? implode(', ', array_map('htmlspecialchars', $usedBy[$m['model_id']])) : '<span class="muted">—</span>';
            echo '<tr><td><code>' . h((string)$m['model_id']) . '</code></td><td class="sm">' . $salles . '</td><td><form method="post" class="js" data-reload style="display:flex;gap:6px;margin:0">' . csrf_field() . '<input type="hidden" name="action" value="bus_type"><input type="hidden" name="id" value="' . (int)$m['id'] . '"><select name="trigger_class"><option value="infobus_park"' . ($m['trigger_class'] === 'infobus_park' ? ' selected' : '') . '>ℹ️ Info (FRANK)</option><option value="infobus_poll"' . ($m['trigger_class'] === 'infobus_poll' ? ' selected' : '') . '>🗳️ Sondage / Vote</option></select><button class="mini">💾</button></form></td></tr>';
        }
        echo '</table></div>';
    }

}

/* ===== Manuel des commandes en jeu =========================================
 * Données SERVEUR : admin/data/commands.generated.json (via gen_commands.php).
 * Docs FR MANUELLES : admin/data/commands.docs.php (jamais écrasées).
 * Cette page ne modifie AUCUNE permission et n'exécute AUCUNE commande.
 */
function ensure_command_favorites(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS admin_command_favorites (user_id INT NOT NULL, command VARCHAR(64) NOT NULL, PRIMARY KEY (user_id, command)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function command_favorites(): array {
    ensure_command_favorites();
    $uid = (int)($_SESSION['admin']['id'] ?? 0);
    if ($uid <= 0) return [];
    $st = db()->prepare('SELECT command FROM admin_command_favorites WHERE user_id=?');
    $st->execute([$uid]);
    return $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
/** Charge et fusionne données extraites + docs FR. Retourne [meta, commands, unregistered, themes]. */
function commands_inventory(): array {
    $gen = @json_decode((string)@file_get_contents(__DIR__ . '/data/commands.generated.json'), true);
    $docsFile = __DIR__ . '/data/commands.docs.php';
    $docs = []; $themes = []; $proposals = [];
    if (is_file($docsFile)) {
        require_once $docsFile;
        if (function_exists('commands_docs')) $docs = commands_docs();
        if (function_exists('commands_doc_themes')) $themes = commands_doc_themes();
        if (function_exists('commands_doc_proposals')) $proposals = commands_doc_proposals();
    }
    $commands = [];
    if (is_array($gen) && !empty($gen['commands'])) {
        foreach ($gen['commands'] as $c) {
            $doc = $docs[$c['name']] ?? [];
            $commands[] = array_merge($c, ['doc' => $doc]);
        }
    }
    return [
        'meta'    => is_array($gen) ? ($gen + ['proposals' => $proposals]) : ['proposals' => $proposals],
        'commands'=> $commands,
        'unreg'   => (is_array($gen) && !empty($gen['unregistered'])) ? $gen['unregistered'] : [],
        'themes'  => $themes,
        'proposals' => $proposals,
    ];
}
function rank_fr(int $id): string {
    $n = [0 => 'Tous', 1 => 'Joueur', 2 => 'Community M.', 3 => 'Guide', 4 => 'Hobba', 5 => 'Super Hobba', 6 => 'Modérateur', 7 => 'Admin'];
    return $n[$id] ?? ('rang ' . $id);
}

function cmd_fold(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $from = ['à','á','â','ä','ã','å','ç','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','ö','õ','ù','ú','û','ü','ý','œ','æ'];
    $to   = ['a','a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','oe','ae'];
    return str_replace($from, $to, $s);
}

function page_commandes(): void {
    page_title('Commandes du jeu', 'Cherche une action, remplis les champs, copie la commande à coller dans le chat.');
    $inv = commands_inventory();
    $meta = $inv['meta']; $cmds = $inv['commands']; $themes = $inv['themes'];
    $ui = function_exists('commands_ui') ? commands_ui() : [];
    $favs = command_favorites();
    $myRank = (int)($_SESSION['admin']['rank'] ?? 1);

    if (!$cmds) {
        echo '<div class="panel"><div class="ph"><h3>⚠️ Inventaire absent</h3></div><div style="padding:12px">';
        echo '<p>Le fichier <code>admin/data/commands.generated.json</code> est manquant ou vide. Génère-le :</p>';
        echo '<pre style="background:#0d1b24;color:#cfe;padding:10px;border-radius:6px"><code>php admin/tools/gen_commands.php</code></pre></div></div>';
        return;
    }

    // ----- Fusion données + UI + regroupement par thème -----
    $byTheme = [];        // theme => [name,…] (ordre d'origine)
    $data = [];           // name => objet complet pour le JS
    foreach ($cmds as $c) {
        $name = (string)$c['name'];
        $doc = $c['doc'] ?? [];
        $u = $ui[$name] ?? [];
        $theme = (string)($u['theme'] ?? ($doc['theme'] ?? 'infos'));
        $label = (string)($u['label'] ?? $name);
        $minRank = $c['minRankId'];

        $searchParts = [$label, $name, implode(' ', $c['aliases']), implode(' ', $u['syn'] ?? []), (string)($doc['descFr'] ?? '')];
        foreach (($doc['subcommands'] ?? []) as $s) $searchParts[] = (string)$s[0] . ' ' . (string)$s[1];
        foreach (($u['actions'] ?? []) as $a) $searchParts[] = (string)$a['label'];
        $search = cmd_fold(implode(' ', $searchParts));

        $byTheme[$theme][] = $name;
        $data[$name] = [
            'name' => $name,
            'label' => $label,
            'aliases' => $c['aliases'],
            'cmd' => ':' . $name,
            'minRankId' => $minRank,
            'who' => ($minRank === null) ? 'Membres du Habbo Club' : (($minRank >= 7) ? 'Administrateurs' : (($minRank <= 1) ? 'Tous les joueurs' : rank_fr((int)$minRank) . ' et plus')),
            'clientSide' => !empty($c['clientSide']),
            'sensitive' => !empty($doc['sensitive']),
            'descFr' => (string)($doc['descFr'] ?? ($c['descriptionEn'] ?? '')),
            'effects' => (string)($doc['effects'] ?? ''),
            'scope' => (string)($doc['scope'] ?? ''),
            'undo' => (string)($doc['undo'] ?? ''),
            'conditions' => array_values($doc['conditions'] ?? []),
            'failures' => array_values($doc['failures'] ?? []),
            'examples' => array_values($doc['examples'] ?? []),
            'subcommands' => array_map(fn($s) => [(string)$s[0], (string)$s[1]], $doc['subcommands'] ?? []),
            'notes' => (string)($doc['notes'] ?? ''),
            'permissions' => $c['permissions'],
            'fields' => $u['fields'] ?? [],
            'actions' => $u['actions'] ?? [],
            'template' => (string)($u['template'] ?? (':' . $name)),
            'summary' => (string)($u['summary'] ?? ($doc['descFr'] ?? '')),
            'search' => $search,
            'fav' => in_array($name, $favs, true),
        ];
    }

    // ----- Barre d'outils -----
    echo '<div id="cmdapp">';
    echo '<div class="panel cmd-toolbar"><div class="ctb">';
    echo '<input id="cmdq" type="search" placeholder="Que veux-tu faire ? Ex. crédits, bus, appartement…" autocomplete="off">';
    echo '<label class="ctb-f">Accessibles à <select id="cmdrole">';
    $roleOpts = [0 => 'Tous', 1 => 'Joueur', 4 => 'Hobba', 5 => 'Super Hobba', 6 => 'Modérateur', 7 => 'Administrateur'];
    foreach ($roleOpts as $rid => $rl) echo '<option value="' . $rid . '">' . h($rl) . '</option>';
    echo '</select></label>';
    echo '<label class="ctb-f"><input type="checkbox" id="cmdfav"> ⭐ Mes favoris</label>';
    echo '<button type="button" id="cmdclear" class="cbtn-ghost">Effacer les filtres</button>';
    echo '</div></div>';

    // ----- Informations techniques (repliées) -----
    $jar = $meta['jar'] ?? []; $cnt = $meta['counts'] ?? [];
    echo '<details class="panel cmd-tech"><summary>🧾 Informations techniques</summary><div class="cmd-tech-body">';
    if ($jar) {
        echo '<p><b>Source</b> : <code>Server/www/kepler.jar</code> · ' . number_format((int)($jar['size'] ?? 0), 0, ',', ' ') . ' octets · généré le ' . h(substr((string)($meta['generatedAt'] ?? ''), 0, 19)) . '</p>';
        echo '<p class="mono-sm">SHA-256 : ' . h((string)($jar['sha256'] ?? '?')) . '</p>';
    }
    echo '<p>Inventaire : <b>' . (int)($cnt['commands'] ?? count($cmds)) . '</b> commandes · <b>' . (int)($cnt['aliasesTotal'] ?? 0) . '</b> alias · ' . (int)($cnt['clientSide'] ?? 0) . ' client-side · ' . (int)($cnt['unregistered'] ?? 0) . ' non enregistrées.</p>';
    echo '<p>Régénérer (CLI, hors HTTP, sans exécuter le JAR) : <code>php admin/tools/gen_commands.php</code>. Données extraites dans <code>admin/data/commands.generated.json</code> ; descriptions FR dans <code>admin/data/commands.docs.php</code> (conservées à la régénération).</p>';
    if (!empty($meta['limits'])) { echo '<p><b>Limites</b></p><ul>'; foreach ($meta['limits'] as $l) echo '<li>' . h($l) . '</li>'; echo '</ul>'; }
    if (!empty($meta['warnings'])) echo '<p style="color:#b8731b">⚠ ' . h(implode(' · ', $meta['warnings'])) . '</p>';
    // Propositions (dormantes)
    if (!empty($inv['unreg'])) {
        $prop = $inv['proposals'] ?? [];
        echo '<p><b>Propositions</b> — classes présentes dans le JAR mais non enregistrées (inutilisables en jeu, aucun développement engagé) :</p><ul>';
        foreach ($inv['unreg'] as $uu) { $note = $prop[$uu['class']] ?? ($uu['descriptionEn'] ?: '—'); echo '<li><code>' . h((string)$uu['class']) . '</code> — ' . h((string)$note) . '</li>'; }
        echo '</ul>';
    }
    echo '<p><b>Aide générale « pourquoi ça ne marche pas »</b></p><ul>';
    echo '<li>Rang insuffisant (les commandes admin exigent le rang Administrateur).</li>';
    echo '<li>Pas propriétaire de la salle (pour Ramasser les meubles / Gradateur / OVNIs).</li>';
    echo '<li>Arguments manquants, cible hors ligne, ou hors d\'une salle.</li>';
    echo '<li>Habbo Club requis pour la liste des joueurs / des meubles.</li></ul>';
    echo '</div></details>';

    // ----- Maître / détail -----
    echo '<div class="cmd-wrap">';
    // Liste à gauche
    echo '<div class="cmd-list" id="cmdlist">';
    foreach ($themes as $tk => $t) {
        if (empty($byTheme[$tk])) continue;
        echo '<div class="cl-group" data-theme="' . h($tk) . '"><div class="cl-head">' . h($t[0] . ' ' . $t[1]) . '</div>';
        foreach ($byTheme[$tk] as $name) {
            $d = $data[$name];
            echo '<button type="button" class="cmd-line" data-name="' . h($name) . '" data-search="' . h($d['search']) . '" data-minrank="' . (int)($d['minRankId'] ?? 0) . '" data-fav="' . ($d['fav'] ? '1' : '0') . '">'
               . '<span class="cl-star" data-cmd="' . h($name) . '" role="button" title="Favori">' . ($d['fav'] ? '⭐' : '☆') . '</span>'
               . '<span class="cl-main"><span class="cl-label">' . h($d['label']) . '</span><span class="cl-cmd">' . h($d['cmd']) . '</span></span>'
               . '</button>';
        }
        echo '</div>';
    }
    echo '<p id="cmdnores" class="cl-nores" style="display:none">Aucune commande ne correspond.<br><button type="button" class="cbtn-ghost" id="cmdclear2">Effacer les filtres</button></p>';
    echo '</div>';

    // Détail à droite
    echo '<div class="cmd-detail" id="cmddetail"><div class="cd-empty" id="cdEmpty">← Choisis une commande dans la liste pour la préparer.</div><div id="cdCard" style="display:none"></div></div>';
    echo '</div>'; // .cmd-wrap

    // ----- Formulaire favoris (AJAX) -----
    echo '<form id="favform" method="post" style="display:none"><input type="hidden" name="action" value="cmd_fav_toggle"><input type="hidden" name="ajax" value="1"><input type="hidden" name="command" id="favcmd">' . csrf_field() . '</form>';

    cmd_styles();
    echo '<script>window.__CMD=' . json_encode($data, JSON_UNESCAPED_UNICODE) . ';</script>';
    cmd_script();
    echo '</div>'; // #cmdapp
}

function cmd_styles(): void {
    echo <<<'CSS'
<style>
#cmdapp{--cp:#2f6f9f;--cp-d:#245a80;--danger:#c0392b;font-size:15px;color:#223}
#cmdapp .panel{background:#fff}
#cmdapp .cmd-toolbar{padding:0}
#cmdapp .ctb{display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:12px 14px}
#cmdapp #cmdq{flex:1;min-width:240px;font-size:16px;padding:11px 14px;border:1px solid #c3d0d8;border-radius:8px}
#cmdapp #cmdq:focus{outline:2px solid var(--cp);border-color:var(--cp)}
#cmdapp .ctb-f{font-size:14px;display:flex;align-items:center;gap:6px;white-space:nowrap}
#cmdapp .ctb-f select{font-size:14px;padding:7px 9px;border:1px solid #c3d0d8;border-radius:7px}
#cmdapp .cbtn-ghost{font-size:13px;background:none;border:1px solid #c3d0d8;border-radius:7px;padding:7px 11px;cursor:pointer;color:#456}
#cmdapp .cbtn-ghost:hover{background:#f1f5f8}
#cmdapp .cmd-tech{margin-top:10px}
#cmdapp .cmd-tech>summary{cursor:pointer;padding:11px 14px;font-weight:700;font-size:14px}
#cmdapp .cmd-tech-body{padding:0 16px 14px;font-size:13px;color:#4a5a6a;line-height:1.6}
#cmdapp .cmd-tech-body ul{margin:4px 0 10px 18px}
#cmdapp .mono-sm{font-family:var(--mono);font-size:11px;color:#6b8296;word-break:break-all}
#cmdapp .cmd-wrap{display:flex;gap:16px;align-items:flex-start;margin-top:12px}
#cmdapp .cmd-list{flex:0 0 32%;max-width:360px;min-width:250px;background:#fff;border:1px solid #e1e8ee;border-radius:10px;padding:6px;max-height:72vh;overflow:auto}
#cmdapp .cl-group{margin-bottom:6px}
#cmdapp .cl-head{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#7a8a99;padding:10px 10px 4px}
#cmdapp .cmd-line{display:flex;align-items:center;gap:8px;width:100%;text-align:left;background:none;border:0;border-radius:8px;padding:9px 10px;cursor:pointer;font-size:15px;color:#223}
#cmdapp .cmd-line:hover{background:#f1f5f8}
#cmdapp .cmd-line.sel{background:var(--cp);color:#fff}
#cmdapp .cmd-line.sel .cl-cmd{color:#dbeafe}
#cmdapp .cl-star{font-size:16px;line-height:1;flex:0 0 auto;color:#e0a500}
#cmdapp .cl-main{display:flex;flex-direction:column;min-width:0}
#cmdapp .cl-label{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#cmdapp .cl-cmd{font-family:var(--mono);font-size:12px;color:#7a8a99}
#cmdapp .cl-nores{padding:18px 12px;text-align:center;color:#7a8a99}
#cmdapp .cmd-detail{flex:1;min-width:0}
#cmdapp .cd-empty{background:#fff;border:1px dashed #c3d0d8;border-radius:10px;padding:40px 20px;text-align:center;color:#7a8a99;font-size:16px}
#cmdapp .cd-card{background:#fff;border:1px solid #e1e8ee;border-radius:10px;padding:18px 20px}
#cmdapp .cd-back{display:none;margin-bottom:10px}
#cmdapp .cd-title{font-size:22px;font-weight:700;margin:0 0 2px}
#cmdapp .cd-cmd{font-family:var(--mono);font-size:14px;color:#7a8a99;margin:0 0 10px}
#cmdapp .cd-desc{font-size:15px;line-height:1.55;margin:0 0 12px}
#cmdapp .cd-who{display:inline-block;font-size:14px;background:#eef3f7;border-radius:6px;padding:4px 10px;margin:0 0 14px}
#cmdapp .cd-conseq{background:#fbecea;border-left:4px solid var(--danger);color:#8a2b20;padding:9px 12px;border-radius:6px;font-size:14px;margin:0 0 14px}
#cmdapp .cd-conseq b{color:#7a2318}
#cmdapp .cd-field{margin:0 0 12px}
#cmdapp .cd-field label{display:block;font-weight:600;font-size:14px;margin:0 0 4px}
#cmdapp .cd-field .opt{font-weight:400;color:#8a97a3;font-size:13px}
#cmdapp .cd-field input,#cmdapp .cd-field select{width:100%;max-width:420px;font-size:15px;padding:9px 11px;border:1px solid #c3d0d8;border-radius:8px}
#cmdapp .cd-field input:focus,#cmdapp .cd-field select:focus{outline:2px solid var(--cp);border-color:var(--cp)}
#cmdapp .cd-field.err input{border-color:var(--danger)}
#cmdapp .cd-ex{font-size:12px;color:#8a97a3;margin:4px 0 0}
#cmdapp .cd-fielderr{font-size:13px;color:var(--danger);margin:4px 0 0;display:none}
#cmdapp .cd-field.err .cd-fielderr{display:block}
#cmdapp .cd-summary{font-size:15px;background:#eef3f7;border-radius:8px;padding:11px 13px;margin:14px 0 10px}
#cmdapp .cd-out-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
#cmdapp .cd-out{flex:1;min-width:220px;font-family:var(--mono);font-weight:700;font-size:16px;padding:11px 13px;border:1px solid #c3d0d8;border-radius:8px;background:#f8fbfd}
#cmdapp .cd-copy{font-size:16px;font-weight:700;color:#fff;background:var(--cp);border:0;border-radius:8px;padding:12px 20px;cursor:pointer}
#cmdapp .cd-copy:hover{background:var(--cp-d)}
#cmdapp .cd-copy:disabled{background:#b6c4cf;cursor:not-allowed}
#cmdapp .cd-paste{font-size:13px;color:#5a6b7a;margin:8px 0 0}
#cmdapp .cd-copied{font-size:14px;color:#1f7a3d;margin:8px 0 0;display:none}
#cmdapp .cd-more{margin-top:16px;border-top:1px solid #eef2f5;padding-top:10px}
#cmdapp .cd-more>summary{cursor:pointer;font-weight:700;font-size:14px;padding:4px 0}
#cmdapp .cd-more ul{margin:6px 0 10px 18px;font-size:14px;line-height:1.55}
#cmdapp .cd-more .ex-chip{font-family:var(--mono);background:#0d1b24;color:#bfe;display:inline-block;padding:3px 8px;border-radius:5px;margin:0 6px 5px 0;font-size:13px}
@media(max-width:760px){
  #cmdapp .cmd-wrap{flex-direction:column}
  #cmdapp .cmd-list,#cmdapp .cmd-detail{width:100%;max-width:none;flex:none}
  #cmdapp .cmd-list{max-height:none}
  #cmdapp.detail-open .cmd-list{display:none}
  #cmdapp:not(.detail-open) .cmd-detail{display:none}
  #cmdapp.detail-open .cd-back{display:inline-block}
}
</style>
CSS;
}

function cmd_script(): void {
    echo <<<'JS'
<script>
(function(){
  var app=document.getElementById('cmdapp');
  var q=document.getElementById('cmdq'), role=document.getElementById('cmdrole'), favOnly=document.getElementById('cmdfav');
  var list=document.getElementById('cmdlist'), nores=document.getElementById('cmdnores');
  var lines=[].slice.call(document.querySelectorAll('.cmd-line'));
  var empty=document.getElementById('cdEmpty'), cardBox=document.getElementById('cdCard');
  var KEY='cmd_ui_state_v1';
  function fold(s){try{return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();}catch(e){return s.toLowerCase();}}
  function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

  // ---- État persistant (hors valeurs de champs) ----
  function loadState(){try{return JSON.parse(localStorage.getItem(KEY))||{};}catch(e){return {};}}
  function saveState(){try{localStorage.setItem(KEY,JSON.stringify({q:q.value,role:role.value,fav:favOnly.checked,sel:current}));}catch(e){}}
  var st=loadState(); var current=st.sel||null;
  if(st.q)q.value=st.q; if(st.role!=null)role.value=st.role; if(st.fav)favOnly.checked=true;

  // ---- Filtrage ----
  function applyFilter(){
    var toks=fold(q.value.trim()).split(/\s+/).filter(Boolean);
    var r=parseInt(role.value,10)||0, fav=favOnly.checked, shown=0;
    lines.forEach(function(l){
      var s=l.dataset.search, ok=true;
      for(var i=0;i<toks.length;i++){ if(s.indexOf(toks[i])<0){ok=false;break;} }
      if(ok && r>0 && parseInt(l.dataset.minrank,10)>r) ok=false;
      if(ok && fav && l.dataset.fav!=='1') ok=false;
      l.style.display=ok?'':'none'; if(ok)shown++;
    });
    [].slice.call(document.querySelectorAll('.cl-group')).forEach(function(g){
      var any=[].slice.call(g.querySelectorAll('.cmd-line')).some(function(l){return l.style.display!=='none';});
      g.style.display=any?'':'none';
    });
    nores.style.display=shown?'none':'block';
    saveState();
  }
  [q,role,favOnly].forEach(function(el){el.addEventListener('input',applyFilter);el.addEventListener('change',applyFilter);});
  function clearFilters(){q.value='';role.value='0';favOnly.checked=false;applyFilter();q.focus();}
  document.getElementById('cmdclear').addEventListener('click',clearFilters);
  var c2=document.getElementById('cmdclear2'); if(c2)c2.addEventListener('click',clearFilters);

  // ---- Favoris (étoile) ----
  function toggleFav(name,starEl){
    document.getElementById('favcmd').value=name;
    var fd=new FormData(document.getElementById('favform'));
    fetch('?p=commandes',{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}})
      .then(function(r){return r.json();}).then(function(j){
        if(!j.ok){if(window.toast)toast(j.msg||'Erreur',false);return;}
        var on=(j.msg==='ajouté');
        [].slice.call(document.querySelectorAll('.cmd-line[data-name="'+name+'"] .cl-star')).forEach(function(s){s.textContent=on?'⭐':'☆';});
        var ln=document.querySelector('.cmd-line[data-name="'+name+'"]'); if(ln)ln.dataset.fav=on?'1':'0';
        if(window.__CMD[name])window.__CMD[name].fav=on;
        if(current===name)renderCard(name);
        applyFilter();
      }).catch(function(){if(window.toast)toast('Erreur réseau',false);});
  }

  // ---- Sélection + clics liste ----
  lines.forEach(function(l){
    l.addEventListener('click',function(e){
      if(e.target.classList.contains('cl-star')){e.stopPropagation();toggleFav(l.dataset.name,e.target);return;}
      select(l.dataset.name);
    });
  });
  function select(name){
    current=name; saveState();
    lines.forEach(function(l){l.classList.toggle('sel',l.dataset.name===name);});
    renderCard(name);
    app.classList.add('detail-open');
    var card=document.getElementById('cdCard'); if(card)card.scrollIntoView({block:'nearest'});
  }

  // ---- Rendu de la fiche ----
  function buildField(f,idx){
    var req=!f.opt;
    var h='<div class="cd-field" data-key="'+esc(f.key)+'" data-rule="'+esc(f.rule||'')+'" data-opt="'+(f.opt?1:0)+'">';
    h+='<label>'+esc(f.label)+(f.opt?' <span class="opt">(facultatif)</span>':'')+'</label>';
    if(f.choices && f.choices.length){
      h+='<select class="cd-input">';
      if(f.opt)h+='<option value="">—</option>';
      f.choices.forEach(function(ch){h+='<option value="'+esc(ch[0])+'">'+esc(ch[1])+'</option>';});
      h+='</select>';
    }else{
      h+='<input type="text" class="cd-input" placeholder="'+esc(f.ph||'')+'">';
    }
    if(f.ex)h+='<p class="cd-ex">Exemple : '+esc(f.ex)+'</p>';
    h+='<p class="cd-fielderr"></p></div>';
    return h;
  }
  function renderCard(name){
    var d=window.__CMD[name]; if(!d){return;}
    empty.style.display='none'; cardBox.style.display='';
    var hasActions=d.actions && d.actions.length;
    var html='<div class="cd-card">';
    html+='<button type="button" class="cbtn-ghost cd-back" id="cdBack">← Retour aux commandes</button>';
    html+='<h2 class="cd-title">'+esc(d.label)+'</h2>';
    html+='<p class="cd-cmd">'+esc(d.cmd)+(d.aliases.length>1?'  ·  alias '+esc(d.aliases.slice(1).map(function(a){return ':'+a;}).join(', ')):'')+'</p>';
    if(d.descFr)html+='<p class="cd-desc">'+esc(d.descFr)+'</p>';
    html+='<div class="cd-who">👤 '+esc(d.who)+'</div>';
    // Conséquence sensible visible
    if(d.sensitive){
      var cz=d.scope?('Portée : '+d.scope):'Action sensible.';
      var uz=d.undo?(' — Annulation : '+d.undo):'';
      html+='<div class="cd-conseq"><b>⚠ Attention</b> — '+esc(cz+uz)+'</div>';
    }
    // Sélecteur d'action (sous-commandes)
    if(hasActions){
      html+='<div class="cd-field"><label>Action</label><select id="cdAction" class="cd-input">';
      d.actions.forEach(function(a,i){html+='<option value="'+i+'">'+esc(a.label)+'</option>';});
      html+='</select></div>';
    }
    html+='<div id="cdFields"></div>';
    html+='<div class="cd-summary" id="cdSummary"></div>';
    html+='<div class="cd-out-row"><input id="cdOut" class="cd-out" readonly onclick="this.select()" aria-label="Commande à copier"><button type="button" id="cdCopy" class="cd-copy">📋 Copier la commande</button></div>';
    html+='<p class="cd-paste">À coller dans le chat du jeu.</p><p class="cd-copied" id="cdCopied">Commande copiée — colle-la dans le chat du jeu.</p>';
    // Détails repliables
    var more='';
    if(d.conditions.length){more+='<p><b>Conditions</b></p><ul>';d.conditions.forEach(function(x){more+='<li>'+esc(x)+'</li>';});more+='</ul>';}
    if(d.failures.length){more+='<p><b>Si ça ne marche pas</b></p><ul>';d.failures.forEach(function(x){more+='<li>'+esc(x)+'</li>';});more+='</ul>';}
    if(!d.sensitive && d.effects){more+='<p><b>Effet</b> : '+esc(d.effects)+'</p>';}
    if(!d.sensitive && d.scope){more+='<p><b>Portée</b> : '+esc(d.scope)+'</p>';}
    if(d.examples.length){more+='<p><b>Exemples</b></p>';d.examples.forEach(function(x){more+='<span class="ex-chip">'+esc(x)+'</span>';});}
    if(d.subcommands.length){more+='<p><b>Toutes les sous-commandes</b></p><ul>';d.subcommands.forEach(function(s){more+='<li><code>'+esc(s[0])+'</code> — '+esc(s[1])+'</li>';});more+='</ul>';}
    if(d.notes)more+='<p>ℹ '+esc(d.notes)+'</p>';
    if(more)html+='<details class="cd-more"><summary>Conditions et aide</summary>'+more+'</details>';
    html+='</div>';
    cardBox.innerHTML=html;

    var bk=document.getElementById('cdBack'); if(bk)bk.addEventListener('click',function(){app.classList.remove('detail-open');});

    // Champs selon action ou champs directs
    var fieldsBox=document.getElementById('cdFields');
    function curFields(){ if(hasActions){var i=parseInt(document.getElementById('cdAction').value,10)||0;return d.actions[i].fields||[];} return d.fields||[]; }
    function curTemplate(){ if(hasActions){var i=parseInt(document.getElementById('cdAction').value,10)||0;return d.actions[i].template;} return d.template; }
    function curSummary(){ if(hasActions){var i=parseInt(document.getElementById('cdAction').value,10)||0;return d.actions[i].summary;} return d.summary; }
    function renderFields(){
      var fs=curFields(); fieldsBox.innerHTML=fs.map(buildField).join('');
      [].slice.call(fieldsBox.querySelectorAll('.cd-input')).forEach(function(inp){inp.addEventListener('input',update);inp.addEventListener('change',update);});
      update();
    }
    function validateField(fe){
      var inp=fe.querySelector('.cd-input'), v=(inp.value||'').trim(), opt=fe.dataset.opt==='1', rule=fe.dataset.rule, err='';
      if(!v){ if(!opt)err='Ce champ est obligatoire.'; }
      else if(rule==='int'){ if(!/^-?\d+$/.test(v))err='Entre un nombre entier.'; }
      else if(rule==='badge'){ if(!/^[A-Z0-9]{3}$/.test(v))err='3 caractères en MAJUSCULES ou chiffres (ex. NL1).'; }
      fe.classList.toggle('err',!!err);
      fe.querySelector('.cd-fielderr').textContent=err;
      return {ok:!err, filled:!!v, val:v};
    }
    function update(){
      var tpl=curTemplate(), summary=curSummary(), allOk=true;
      [].slice.call(fieldsBox.querySelectorAll('.cd-field')).forEach(function(fe){
        var key=fe.dataset.key, r=validateField(fe);
        if(!r.ok)allOk=false;
        var token='{'+key+'}';
        if(r.filled){ tpl=tpl.replace(token,r.val); summary=summary.replace(token,r.val); }
        else{ tpl=tpl.replace(new RegExp('\\s*'+token.replace(/[{}]/g,'\\$&')),''); summary=summary.replace(token,'…'); }
      });
      tpl=tpl.replace(/\s+/g,' ').trim();
      document.getElementById('cdOut').value=tpl;
      document.getElementById('cdSummary').innerHTML='<b>Résumé :</b> '+esc(summary);
      var copy=document.getElementById('cdCopy'); copy.disabled=!allOk;
      document.getElementById('cdCopied').style.display='none';
    }
    if(hasActions){document.getElementById('cdAction').addEventListener('change',renderFields);}
    renderFields();

    // Copier (Chrome + Basilisk) + repli sélection manuelle
    document.getElementById('cdCopy').addEventListener('click',function(){
      var out=document.getElementById('cdOut'), text=out.value, done=false;
      out.focus(); out.select(); try{out.setSelectionRange(0,text.length);}catch(e){}
      try{ if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(text); done=true; } }catch(e){}
      if(!done){ try{ done=document.execCommand('copy'); }catch(e){ done=false; } }
      var c=document.getElementById('cdCopied');
      if(done){ c.textContent='Commande copiée — colle-la dans le chat du jeu.'; c.style.color='#1f7a3d'; c.style.display='block'; if(window.toast)toast('Commande copiée !',true); }
      else{ c.textContent='Copie automatique indisponible : le texte est sélectionné, fais Ctrl+C pour le copier.'; c.style.color='#b8731b'; c.style.display='block'; }
    });
  }

  applyFilter();
  if(current && window.__CMD[current]){ select(current); }
})();
</script>
JS;
}
function entry_bg_countries(): array {
    return ['fr'=>'🇫🇷 France','us'=>'🇺🇸 USA','uk'=>'🇬🇧 Royaume-Uni','de'=>'🇩🇪 Allemagne','es'=>'🇪🇸 Espagne','it'=>'🇮🇹 Italie','br'=>'🇧🇷 Brésil','nl'=>'🇳🇱 Pays-Bas','jp'=>'🇯🇵 Japon','dk'=>'🇩🇰 Danemark','fi'=>'🇫🇮 Finlande','no'=>'🇳🇴 Norvège','se'=>'🇸🇪 Suède','ch'=>'🇨🇭 Suisse','ca'=>'🇨🇦 Canada','cn'=>'🇨🇳 Chine','ru'=>'🇷🇺 Russie','sg'=>'🇸🇬 Singapour','at'=>'🇦🇹 Autriche','au'=>'🇦🇺 Australie','pl'=>'🇵🇱 Pologne'];
}
function current_entry_bg(): string {
    $ev = @file_get_contents(DCR_DIR . '/external_variables.txt');
    if ($ev !== false && preg_match('/^cast\.entry\.16=hh_entry_([a-z_]+)/m', $ev, $m)) return $m[1];
    return '';
}
function hotel_alert_presets(): array {
    // Pas d'emoji : le client Habbo (Shockwave) ne les affiche pas (ils seraient retirés à l'envoi).
    return [
        'Maintenance dans 10 minutes - pense à ranger tes mobis, déconnexion imminente !',
        'Redémarrage du serveur dans 5 minutes.',
        'Un événement vient de commencer dans les salles publiques - rejoins-nous !',
        'De nouveaux mobis sont disponibles au Catalogue !',
        'Distribution de crédits en cours, profites-en !',
        'Bienvenue sur Habbo - amuse-toi bien !',
        'Merci de respecter les autres joueurs. Les insultes = ban.',
    ];
}
/* Service d'avatars Minerva (port local 5123). */
function minerva_running(): bool { $c = @fsockopen('127.0.0.1', 5123, $e, $s, 0.6); if ($c) { fclose($c); return true; } return false; }

/* ===== Hub « Alertes & annonces » ===== */
function page_alerts(): void {
    page_title('Alertes & annonces', 'Appels à l\'aide, alertes en jeu et annonces — regroupés');
    $sec = (string)($_GET['sec'] ?? 'send');
    if (!in_array($sec, ['cfh', 'send', 'announce', 'history'], true)) $sec = 'send';
    $tabs = ['cfh' => '🆘 Appels à l\'aide', 'send' => '📢 Alerte en jeu', 'announce' => '📨 Annonce messagerie', 'history' => '🕑 Historique'];
    echo '<div class="row" style="gap:4px;flex-wrap:wrap;margin-bottom:12px;border-bottom:2px solid var(--line2);padding-bottom:6px">';
    foreach ($tabs as $k => $lbl) echo '<a class="mini' . ($sec === $k ? '' : ' ghost') . '" href="?p=alerts&sec=' . $k . '">' . $lbl . '</a>';
    echo '</div>';
    if ($sec === 'cfh') alerts_sec_cfh();
    elseif ($sec === 'announce') alerts_sec_announce();
    elseif ($sec === 'history') alerts_sec_history();
    else alerts_sec_send();
}
function alerts_sec_cfh(): void {
    echo '<div class="panel"><div class="ph"><h3>🆘 Appels à l\'aide (jeu)</h3><span class="rk red" style="margin-left:auto">⚠️ Non raccordé</span></div>';
    echo '<div class="note" style="margin:0 0 10px">⚠️ <b>Réception des appels du jeu non raccordée.</b> (Ce n\'est pas « aucun appel » : la liaison avec l\'émulateur reste à développer.)</div>';
    echo '<p class="muted sm">Dans Kepler, les appels à l\'aide (Call For Help) sont gérés <b>en mémoire</b> et diffusés aux modérateurs connectés en jeu — ils ne sont pas enregistrés en base. Pour les traiter ici, il faut :</p>';
    echo '<ol class="muted sm" style="margin:6px 0 0 18px"><li>Patch Kepler : persister chaque appel dans une table <code>calls_for_help</code> (à la soumission, la prise en charge, le changement de catégorie, la clôture).</li><li>Recompiler le <code>kepler.jar</code> et jouer la migration.</li><li>Brancher ici la file (prise en charge, note interne, réponse si possible, clôture motivée, réouverture) + le bouton d\'en-tête avec compteur des appels <b>non pris en charge</b>.</li></ol>';
    echo '<p class="muted sm" style="margin-top:8px">Tant que ce n\'est pas fait, le bouton d\'alerte de l\'en-tête ouvre cet onglet sans afficher de compteur (état honnête, pas un faux zéro). <b>Chantier prévu.</b></p>';
    echo '</div>';
}
function alerts_sec_send(): void {
    if (!tab_allowed('server')) { echo '<div class="panel"><div class="empty">🔒 Ton rang n\'autorise pas l\'envoi d\'alertes en jeu.</div></div>'; return; }
    $up = emu_running();
    echo '<div class="panel"><div class="ph"><h3>📢 Alerte en jeu</h3><span class="rk' . ($up ? '' : ' red') . '" style="margin-left:auto">' . ($up ? '🟢 Émulateur en ligne' : '🔴 Émulateur hors ligne') . '</span></div>';
    echo '<p class="sub">Message pop-up envoyé aux <b>joueurs actuellement connectés</b> (éphémère, non conservé). Seule destination prise en charge aujourd\'hui : <b>tout l\'hôtel</b>. Les alertes par appartement ou par joueur se font en jeu (<code>:alert</code>). ℹ️ Les <b>emoji ne sont pas affichés</b> par le client (retirés automatiquement) ; les accents, eux, passent bien.</p>';
    if (!$up) echo '<div class="note" style="margin:0 0 10px">🔴 Émulateur hors ligne : l\'envoi <b>échouera</b> (aucune mise en file automatique). Démarre-le d\'abord.</div>';
    $presetOpts = '<option value="">— Message prédéfini —</option>';
    foreach (hotel_alert_presets() as $pm) $presetOpts .= '<option value="' . h($pm) . '">' . h(mb_strlen($pm) > 64 ? mb_substr($pm, 0, 64) . '…' : $pm) . '</option>';
    echo '<form method="post" class="js" data-reload onsubmit="var b=this.querySelector(\'button[type=submit]\');if(b){b.disabled=true;setTimeout(function(){b.disabled=false;},4000);}return confirm(\'Envoyer cette alerte à TOUS les joueurs connectés ?\');">' . csrf_field()
       . '<input type="hidden" name="action" value="hotel_alert">'
       . '<div class="row" style="margin-bottom:8px"><label style="flex:1">📋 Modèles<select onchange="if(this.value){document.getElementById(\'ha_msg\').value=this.value;}">' . $presetOpts . '</select></label></div>'
       . '<div class="row"><label style="flex:2">Message<input id="ha_msg" name="message" maxlength="200" placeholder="Ex. Maintenance dans 10 minutes !" required></label><label style="flex:1">Signature (option)<input name="sender" placeholder="L\'équipe"></label></div>'
       . '<div style="margin-top:8px"><button>📢 Envoyer à tout l\'hôtel</button> <span class="muted sm">Portée : joueurs connectés · résultat tracé dans l\'historique</span></div></form>';
    echo '</div>';
}
function alerts_sec_announce(): void {
    if (!tab_allowed('messages')) { echo '<div class="panel"><div class="empty">🔒 Ton rang n\'autorise pas les annonces de messagerie.</div></div>'; return; }
    echo '<div class="panel"><div class="ph"><h3>📨 Annonce dans la messagerie</h3><span class="muted sm" style="margin-left:auto">persistant</span></div>';
    echo '<p class="sub">Crée une conversation par destinataire : <b>conservée et consultable</b> ensuite par le joueur (contrairement à l\'alerte en jeu). Le joueur peut répondre ; sa réponse est <b>privée</b> (jamais transmise aux autres destinataires).</p>';
    echo '<form method="post" class="js" data-reload data-confirm="Envoyer cette annonce (une conversation par destinataire) ?" onsubmit="var b=this.querySelector(\'button[type=submit]\');if(b){b.disabled=true;setTimeout(function(){b.disabled=false;},4000);}return true;"><input type="hidden" name="action" value="conv_broadcast">' . csrf_field()
       . '<div class="sm" style="margin-bottom:4px"><label style="margin-right:12px"><input type="radio" name="mode" value="list" checked> Liste de pseudos</label><label><input type="radio" name="mode" value="rank"> Par rang minimum</label></div>'
       . '<textarea name="usernames" rows="2" placeholder="Pseudos séparés par virgule ou espace (max 500)" style="width:100%"></textarea>'
       . '<label class="sm" style="display:block;margin-top:4px">Rang minimum (si « par rang ») <input type="number" name="min_rank" value="5" min="1" max="7" style="width:70px"></label>'
       . '<input name="subject" maxlength="150" placeholder="Sujet" style="width:100%;margin-top:6px">'
       . '<textarea name="body" rows="3" maxlength="4000" required placeholder="Texte de l\'annonce…" style="width:100%;margin-top:6px"></textarea>'
       . '<button style="margin-top:8px">📨 Envoyer l\'annonce</button> <span class="muted sm">Cible + nombre de destinataires affichés dans le résultat</span></form>';
    echo '</div>';
}
function alerts_sec_history(): void {
    ensure_alert_log();
    $rows = []; try { $rows = db()->query('SELECT * FROM alert_log ORDER BY id DESC LIMIT 100')->fetchAll(); } catch (Throwable $e) {}
    echo '<div class="panel"><div class="ph"><h3>🕑 Historique des diffusions</h3></div>';
    echo '<p class="muted sm" style="margin:-2px 0 8px">⚠️ Un envoi RCON « accepté » signifie « transmis au serveur », pas que tous les joueurs l\'ont lu.</p>';
    if (!$rows) echo '<div class="empty">Aucune diffusion enregistrée pour l\'instant.</div>';
    else {
        echo '<table class="clean"><tr><th>Quand</th><th>Par</th><th>Canal</th><th>Cible</th><th>Message</th><th>Résultat</th></tr>';
        foreach ($rows as $r) echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$r['created_at']))) . '</td><td class="sm">' . h((string)$r['author']) . '</td><td class="sm">' . h((string)$r['channel']) . '</td><td class="sm">' . h((string)$r['target']) . '</td><td class="sm">' . h(mb_strimwidth((string)$r['content'], 0, 80, '…')) . '</td><td class="sm">' . h((string)$r['result']) . '</td></tr>';
        echo '</table>';
    }
    echo '</div>';
}

/* ================= Journal en direct (activité du site/serveur) ================= */
/* Trouve le fichier access_log d'Apache (Laragon). Réglage 'journal.access_log'
   prioritaire, sinon détection auto du dossier httpd-* le plus récent. */
function journal_access_log(): string {
    $cfg = trim(site_setting_get('journal.access_log', ''));
    if ($cfg !== '' && is_file($cfg)) return $cfg;
    $cands = [];
    foreach (glob('C:/laragon/bin/apache/httpd-*/logs/access_log') ?: [] as $g) $cands[] = $g;
    foreach (glob('C:/laragon/bin/apache/httpd-*/logs/access.log') ?: [] as $g) $cands[] = $g;
    // le plus récemment modifié = le serveur actif
    usort($cands, fn($a, $b) => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));
    return $cands[0] ?? '';
}
/* Lit la fin d'un fichier (sans tout charger). */
function journal_tail_bytes(string $f, int $bytes = 160000): string {
    if ($f === '' || !is_file($f)) return '';
    $sz = filesize($f); $fp = @fopen($f, 'rb'); if (!$fp) return '';
    fseek($fp, max(0, $sz - $bytes)); $d = fread($fp, $bytes + 8); fclose($fp);
    return (string)$d;
}
/* Endpoint AJAX : renvoie les dernières requêtes HTTP en JSON. */
function journal_ajax(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $f    = journal_access_log();
    $ipf  = preg_replace('/[^0-9A-Fa-f:.]/', '', (string)($_GET['ip'] ?? ''));
    $mode = (string)($_GET['mode'] ?? 'pages'); // pages | all | errors
    if (!in_array($mode, ['pages', 'all', 'errors'], true)) $mode = 'pages';
    $raw = journal_tail_bytes($f);
    if ($raw === '') { echo json_encode(['ok' => false, 'msg' => 'Journal Apache introuvable ou illisible.', 'path' => $f, 'emu' => emu_running(), 'ts' => date('H:i:s')]); return; }
    $assetRe = '#\.(cct|dcr|cst|cxt|swf|png|gif|jpe?g|webp|css|js|ico|woff2?|ttf|map|mp3|wav)(\?|$)#i';
    $rows = [];
    foreach (preg_split('/\r\n|\n/', $raw) as $ln) {
        if (!preg_match('/^(\S+)\s+\S+\s+\S+\s+\[([^\]]+)\]\s+"(\S+)\s+([^"]*?)\s+[^"]*"\s+(\d{3})\s+(\S+)/', $ln, $m)) continue;
        $ip = $m[1]; $dt = $m[2]; $method = $m[3]; $path = $m[4]; $status = (int)$m[5]; $size = $m[6];
        if ($ipf !== '' && strpos($ip, $ipf) === false) continue;
        $isAsset = (bool)preg_match($assetRe, $path) || (bool)preg_match('#/avatar\.php#i', $path);
        if ($mode === 'pages' && $isAsset) continue;
        if ($mode === 'errors' && $status < 400) continue;
        $t = (preg_match('#:(\d\d:\d\d:\d\d)#', $dt, $mm)) ? $mm[1] : '';
        $rows[] = ['ip' => $ip, 't' => $t, 'm' => $method, 'u' => mb_substr($path, 0, 120), 's' => $status, 'sz' => $size];
    }
    $rows = array_slice($rows, -200);
    echo json_encode(['ok' => true, 'rows' => $rows, 'emu' => emu_running(), 'ts' => date('H:i:s'), 'count' => count($rows)]);
}
/* ================= Filtre de mots (bobba) ================= */
function ensure_wordfilter(): void {
    static $done = false; if ($done) return; $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS wordfilter (id INT AUTO_INCREMENT PRIMARY KEY, word VARCHAR(64) NOT NULL, replacement VARCHAR(64) NOT NULL DEFAULT 'bobba', enabled TINYINT(1) NOT NULL DEFAULT 1, UNIQUE KEY uniq_word (word)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function page_wordfilter(): void {
    page_title('Filtre de mots (bobba)', 'Mots remplacés automatiquement dans le chat en jeu');
    ensure_wordfilter();
    echo '<div class="panel"><p class="sub">Les mots de cette liste sont remplacés (mot <b>entier</b>, insensible à la casse/accents) dans le chat en salle. Ajoute/retire ce que tu veux.</p>';
    echo '<form method="post" class="js" data-reload style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">' . csrf_field() . '<input type="hidden" name="action" value="wf_add">';
    echo '<label>Mot interdit<input name="word" maxlength="64" required placeholder="ex. un gros mot" style="width:200px"></label>';
    echo '<label>Remplacé par<input name="replacement" maxlength="64" value="bobba" style="width:120px"></label>';
    echo '<button class="mini">＋ Ajouter</button></form>';
    echo '<p class="hint">⚙️ Après chaque modif, <b>redémarre l\'émulateur</b> pour recharger la liste (elle est lue au démarrage du serveur de jeu).</p></div>';
    $rows = db()->query('SELECT * FROM wordfilter ORDER BY word')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🤬 Mots filtrés <span class="muted sm">(' . count($rows) . ')</span></h3></div>';
    if (!$rows) echo '<div class="empty">Liste vide.</div>';
    else {
        echo '<table class="clean"><tr><th>Mot</th><th>Remplacé par</th><th>Actif</th><th></th></tr>';
        foreach ($rows as $r) {
            echo '<tr><td><b>' . h((string)$r['word']) . '</b></td><td class="sm muted">' . h((string)$r['replacement']) . '</td>'
                . '<td>' . ($r['enabled'] ? '✅' : '<span class="muted">non</span>') . '</td><td>'
                . '<form method="post" class="js" data-reload style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="wf_toggle"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="mini ghost">' . ($r['enabled'] ? 'Désactiver' : 'Activer') . '</button></form> '
                . '<form method="post" class="js" data-reload data-confirm="Retirer « ' . h((string)$r['word']) .' » ?" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="wf_del"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="mini ghost">🗑️</button></form>'
                . '</td></tr>';
        }
        echo '</table>';
    }
    echo '<p class="hint">ℹ️ Le filtre s\'exécute côté <b>serveur de jeu (kepler.jar)</b> : il agit seulement une fois le jar « filtre » déployé. Sinon la liste est juste stockée ici.</p></div>';
}

function page_journal(): void {
    page_title('Journal en direct', 'Vois en temps réel les requêtes reçues par le site (qui se connecte, quelle page, quel code)');
    $f = journal_access_log();
    echo '<div class="panel"><div class="ph"><h3>📡 Activité en direct</h3><span id="jstatus" class="muted sm" style="margin-left:auto">—</span></div>';
    if ($f === '') {
        echo '<p class="sub">⚠️ Le journal d\'accès Apache est introuvable automatiquement. Indique son chemin dans Réglages (clé <code>journal.access_log</code>), par ex. <code>C:/laragon/bin/apache/httpd-XXXX/logs/access_log</code>.</p>';
    } else {
        echo '<p class="sub">Source : <code>' . h($f) . '</code></p>';
    }
    echo '<div class="row" style="gap:10px;flex-wrap:wrap;align-items:flex-end">';
    echo '<label>Filtre IP<input id="jip" placeholder="ex. 25.5.54.90" style="width:150px"></label>';
    echo '<label>Affichage<select id="jmode"><option value="pages">Pages (sans images/casts)</option><option value="all">Tout (y compris jeu/assets)</option><option value="errors">Erreurs uniquement (4xx/5xx)</option></select></label>';
    echo '<label style="display:flex;align-items:center;gap:5px"><input type="checkbox" id="jauto" checked> Auto (2s)</label>';
    echo '<button class="mini" type="button" id="jrefresh">🔄 Rafraîchir</button>';
    echo '</div>';
    echo '<pre class="logbox" id="jlog" style="max-height:470px;overflow:auto;margin-top:10px;white-space:pre-wrap;word-break:break-all">Chargement…</pre>';
    echo '<p class="hint">💡 Une ligne = une requête reçue : <b>heure · IP · méthode · page · code</b>. Les <b style="color:#caa300">POST</b> (connexion, formulaires) sont en jaune, les <b style="color:#c0392b">erreurs 4xx/5xx</b> en rouge. Parfait pour voir en direct ce que fait un joueur à distance (mets son IP dans « Filtre IP »).</p>';
    echo '</div>';
    ?>
<script>
(function(){
  var logEl=document.getElementById('jlog'), stEl=document.getElementById('jstatus');
  var ipEl=document.getElementById('jip'), modeEl=document.getElementById('jmode'), autoEl=document.getElementById('jauto');
  var timer=null, busy=false;
  function esc(s){return String(s).replace(/[&<>]/g,function(c){return c==='&'?'&amp;':c==='<'?'&lt;':'&gt;';});}
  function render(d){
    if(!d.ok){ stEl.innerHTML='⚠️ '+esc(d.msg||'erreur'); logEl.textContent=(d.msg||'Journal indisponible.'); return; }
    var atBottom = logEl.scrollTop+logEl.clientHeight >= logEl.scrollHeight-30;
    var rows=d.rows||[], out='';
    for(var i=0;i<rows.length;i++){
      var r=rows[i], col='', st=r.s;
      if(st>=400) col='color:#e06a5b;font-weight:bold';
      else if(r.m==='POST') col='color:#e8c34a';
      else col='color:#9fb4c6';
      out+='<span style="'+col+'">'+esc(r.t)+'  '+esc(r.ip)+'  '+esc(r.m)+'  '+esc(r.u)+'  → '+st+'</span>\n';
    }
    logEl.innerHTML = out || '<span class="muted">Aucune requête (selon le filtre).</span>';
    if(atBottom) logEl.scrollTop=logEl.scrollHeight;
    stEl.innerHTML = (d.emu?'🟢 Émulateur en marche':'🔴 Émulateur arrêté')+' · '+rows.length+' lignes · maj '+esc(d.ts);
  }
  function poll(){
    if(busy) return; busy=true;
    var url='?p=journal&tail=1&mode='+encodeURIComponent(modeEl.value)+'&ip='+encodeURIComponent(ipEl.value||'');
    var x=new XMLHttpRequest();
    x.open('GET',url,true);
    x.onreadystatechange=function(){ if(x.readyState===4){ busy=false; try{ render(JSON.parse(x.responseText)); }catch(e){ stEl.innerHTML='⚠️ réponse illisible'; } } };
    x.onerror=function(){ busy=false; stEl.innerHTML='⚠️ erreur réseau'; };
    x.send();
  }
  function schedule(){ if(timer){clearInterval(timer);timer=null;} if(autoEl.checked){ timer=setInterval(poll,2000); } }
  document.getElementById('jrefresh').onclick=poll;
  autoEl.onchange=schedule; modeEl.onchange=poll; ipEl.onchange=poll;
  ipEl.onkeyup=function(e){ if(e.keyCode===13) poll(); };
  poll(); schedule();
})();
</script>
    <?php
}

function page_server(): void {
    page_title('Serveur', 'État des services, contrôle de l\'émulateur et diagnostics');
    $up = emu_running();
    $mav = minerva_running();
    $online = db()->query("SELECT value FROM settings WHERE setting='players.online'")->fetchColumn();
    $dbver = ''; try { $dbver = (string)db()->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) {}
    $dbShort = $dbver !== '' ? (stripos($dbver, 'maria') !== false ? 'MariaDB' : 'MySQL') . ' ' . preg_replace('/[-+].*$/', '', $dbver) : 'Active';

    /* --- État des services (réel) --- */
    echo '<div class="grid">';
    echo '<div class="stat ' . ($up ? 'green' : 'red') . '"><div class="ic">' . ($up ? '🟢' : '🔴') . '</div><div><div class="num" style="font-size:17px">' . ($up ? 'En marche' : 'Arrêté') . '</div><div class="lbl">Émulateur · port 12321</div></div></div>';
    echo '<div class="stat green"><div class="ic">🗄️</div><div><div class="num" style="font-size:15px">' . h($dbShort) . '</div><div class="lbl">Base de données · 3306</div></div></div>';
    echo '<div class="stat green"><div class="ic">🌐</div><div><div class="num" style="font-size:15px">PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '</div><div class="lbl">Site web · Apache · 80</div></div></div>';
    echo '<div class="stat ' . ($mav ? 'green' : 'red') . '"><div class="ic">' . ($mav ? '🎨' : '🔴') . '</div><div><div class="num" style="font-size:17px">' . ($mav ? 'En marche' : 'Arrêté') . '</div><div class="lbl">Avatars · Minerva · 5123</div></div></div>';
    echo '<div class="stat blue"><div class="ic">👥</div><div><div class="num">' . ($up ? ($online !== false ? $online : '0') : '—') . '</div><div class="lbl">Joueurs en ligne</div></div></div>';
    echo '</div>';
    if (!$mav) echo '<div class="panel"><p class="sub" style="padding:10px 12px;margin:0">🎨 <b>Service d\'avatars (Minerva) arrêté</b> — les avatars déjà en cache restent affichés, les autres montrent une silhouette. Démarre-le avec <code>tools\\minerva-bin\\start-minerva.bat</code> (ou relance le serveur via <code>START-HABBORETROV14.bat</code>).</p></div>';

    /* --- Contrôle de l'émulateur --- */
    echo '<div class="panel"><div class="ph"><h3>🎮 Contrôle de l\'émulateur</h3><a class="mini ghost lnkbtn" href="?p=server" style="margin-left:auto">🔄 Rafraîchir l\'état</a></div>';
    echo '<p class="sub">' . ($up ? '✅ Le jeu est accessible, les joueurs peuvent se connecter.' : '⛔ L\'émulateur est arrêté — personne ne peut jouer. Clique sur Démarrer.') . '</p>';
    echo '<div class="srv">';
    echo '<form method="post" class="js" data-reload><input type="hidden" name="action" value="srv_start">' . csrf_field() . '<button class="bigok"' . ($up ? ' disabled' : '') . '>▶️ Démarrer</button></form>';
    echo '<form method="post" class="js" data-reload data-confirm="Arrêter l\'émulateur ? Les joueurs seront déconnectés."><input type="hidden" name="action" value="srv_stop">' . csrf_field() . '<button class="bigred"' . ($up ? '' : ' disabled') . '>⏹️ Arrêter</button></form>';
    echo '</div><p class="hint">💡 Démarrer / Arrêter agissent sur l\'émulateur. Pour appliquer des changements <b>sans couper les autres services</b>, utilise le redémarrage ciblé ci-dessous.</p></div>';

    /* --- Redémarrage « Kepler uniquement » (arrêt propre via pack.ps1, détaché, suivi JSON) --- */
    echo '<div class="panel"><div class="ph"><h3>🔁 Redémarrer le serveur de jeu — Kepler uniquement</h3></div>';
    echo '<p class="sub">MariaDB, Apache, le site et Minerva <b>restent en marche</b>. Arrêt <b>propre</b> (sauvegarde via Ctrl+C) puis redémarrage, avec contrôle de disponibilité réelle. <b>Aucun arrêt forcé.</b></p>';
    echo '<div class="srv">';
    echo '<form method="post" class="js" data-reload data-confirm="Redémarrer Kepler maintenant ? Les joueurs connectés seront déconnectés."><input type="hidden" name="action" value="srv_rk_start"><input type="hidden" name="when" value="now">' . csrf_field() . '<button class="bigwarn">🔁 Redémarrer maintenant</button></form>';
    echo '<form method="post" class="js" data-reload><input type="hidden" name="action" value="srv_rk_start"><input type="hidden" name="when" value="later">' . csrf_field() . '<button class="bigok">⏲️ Dans 1 min (annonce aux joueurs)</button></form>';
    echo '<form method="post" class="js" data-reload><input type="hidden" name="action" value="srv_rk_cancel">' . csrf_field() . '<button>✋ Annuler (avant l\'arrêt)</button></form>';
    echo '</div>';
    echo '<div id="rkprog" style="margin-top:10px;display:none;padding:10px 12px;border:1px solid var(--line2);border-radius:10px"><b>Progression :</b> <span id="rkprog-label">—</span><div id="rkprog-detail" class="muted sm" style="margin-top:4px"></div></div>';
    echo '<p class="hint">💡 « Redémarrage de Kepler uniquement ». L\'opération continue même si tu fermes cette page ; « 🔄 Rafraîchir l\'état » (en haut) ne fait que consulter.</p></div>';
    echo '<script>(function(){var b=document.getElementById("rkprog"),l=document.getElementById("rkprog-label"),d=document.getElementById("rkprog-detail");if(!b)return;function p(){fetch("?p=server&rkstatus=1",{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(s){if(!s||s.state==="idle")return;b.style.display="block";var m={error:" ❌",ok:" ✅",cancelled:" ✋"};l.textContent=(s.label||"")+(s.dry?" (simulation)":"")+(m[s.state]||"");d.textContent=s.detail||"";if(s.state==="running")setTimeout(p,1500);}).catch(function(){});}p();})();</script>';

    /* --- Mode maintenance --- */
    $mf = dirname(__DIR__) . '/maintenance.json';
    $mData = is_file($mf) ? (json_decode((string)@file_get_contents($mf), true) ?: []) : [];
    $mOn = !empty($mData['on']);
    echo '<div class="panel"><div class="ph"><h3>🚧 Mode maintenance</h3><span class="rk ' . ($mOn ? 'red' : 'green') . '" style="margin-left:auto">' . ($mOn ? 'ACTIVÉ' : 'Inactif') . '</span></div>';
    echo '<p class="sub">Ferme le site public <b>et le jeu</b> (<code>/client.php</code>) avec une page « Hôtel en maintenance » (503). Le staff (rang 5+) et l\'admin gardent l\'accès. Fonctionne même si l\'émulateur ou MySQL sont arrêtés.</p>';
    if ($mOn) {
        echo '<p class="hint">Activé par <b>' . h((string)($mData['by'] ?? '?')) . '</b>' . (!empty($mData['message']) ? ' · « ' . h((string)$mData['message']) . ' »' : '') . (!empty($mData['eta']) ? ' · retour estimé : ' . h((string)$mData['eta']) : '') . '</p>';
        echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="maint_off"><button class="bigok">✅ Rouvrir le site</button></form>';
    } else {
        echo '<form method="post" class="js" data-reload data-confirm="Activer le mode maintenance ? Le site public sera fermé aux joueurs.">' . csrf_field() . '<input type="hidden" name="action" value="maint_on">';
        echo '<div class="row"><label style="flex:2">Message affiché<input name="message" placeholder="Ex. Petite mise à jour en cours, on revient vite !"></label><label style="flex:1">Retour estimé<input name="eta" placeholder="Ex. 18h30"></label></div>';
        echo '<button class="bigwarn" style="margin-top:10px">🚧 Activer la maintenance</button></form>';
    }
    echo '</div>';

    /* --- Diagnostics : dernières erreurs du serveur --- */
    $errs = server_log_errors(14);
    echo '<div class="panel"><div class="ph"><h3>🩺 Diagnostics — dernières erreurs</h3><span class="muted sm" style="margin-left:auto">server.log</span></div>';
    if (!$errs) echo '<div class="empty">Aucune erreur récente dans le log 🎉</div>';
    else echo '<pre class="logbox">' . h(implode("\n", $errs)) . '</pre><p class="hint">Extrait des dernières lignes ERROR/WARN/exception du log de l\'émulateur (sans secrets).</p>';
    echo '</div>';

    // Alerte hôtel : l'interface a été déplacée vers « Alertes & annonces ». On garde ici un simple raccourci.
    echo '<div class="panel"><div class="ph"><h3>📢 Alerte à tout l\'hôtel</h3></div>';
    echo '<p class="sub">L\'envoi d\'alertes en jeu a été déplacé vers la rubrique dédiée <b>Alertes &amp; annonces</b> (avec historique, modèles et annonces persistantes).</p>';
    echo '<a class="qabtn" style="display:inline-flex;max-width:280px" href="?p=alerts&sec=send">📢 <span>Ouvrir Alertes &amp; annonces</span></a>';
    echo '</div>';

    // Fond de connexion / décor du client
    $curBg = current_entry_bg();
    $bgOpts = '';
    foreach (entry_bg_countries() as $cc => $lbl) {
        if (!is_file(DCR_DIR . '/hh_entry_' . $cc . '.cct')) continue;
        $bgOpts .= '<option value="' . $cc . '"' . ($cc === $curBg ? ' selected' : '') . '>' . $lbl . ($cc === $curBg ? ' — actuel' : '') . '</option>';
    }
    echo '<div class="panel"><div class="ph"><h3>🎨 Fond de connexion (décor du client)</h3></div>';
    echo '<p class="sub">Change le décor de l\'écran d\'entrée/chargement du client (hôtel Habbo par pays).</p>';
    echo '<form method="post" class="js"><div class="row">' . csrf_field() . '<input type="hidden" name="action" value="set_entry_bg"><label style="flex:1">Pays / décor<select name="country">' . $bgOpts . '</select></label><div style="align-self:flex-end"><button>🎨 Appliquer</button></div></div></form>';
    echo '<p class="hint">💡 Après changement, <b>vide le cache Basilisk</b> côté client pour voir le nouveau fond (pas besoin de redémarrer l\'émulateur).</p></div>';

    // Sauvegarde base
    echo '<div class="panel"><div class="ph"><h3>💾 Sauvegarde de la base</h3>
        <form method="post" class="js" data-reload style="margin-left:auto">' . csrf_field() . '<input type="hidden" name="action" value="db_backup"><button class="mini">＋ Sauvegarder maintenant</button></form></div>';
    echo '<p class="sub">Copie complète de la base <code>' . h(DB_NAME) . '</code> (rotation : 15 dernières conservées).</p>';
    $bk = backup_list();
    if (!$bk) echo '<div class="empty">Aucune sauvegarde. Clique sur « Sauvegarder maintenant ».</div>';
    else { echo '<table class="clean"><tr><th>Fichier</th><th>Date</th><th>Taille</th><th></th></tr>';
        foreach ($bk as $f) { $n = basename($f);
            echo '<tr><td><code>' . h($n) . '</code></td><td class="sm muted">' . date('d/m/Y H:i', filemtime($f)) . '</td><td class="sm">' . mysql_human_size((int)filesize($f)) . '</td><td><a class="mini ghost" href="?p=server&dlbackup=' . urlencode($n) . '">⬇ Télécharger</a></td></tr>';
        }
        echo '</table>'; }
    echo '</div>';
}

/* ================= UI helpers ================= */
function uquick($id, string $action, string $field, int $val, string $label): string {
    return '<form method="post" class="js" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="' . h($action) . '"><input type="hidden" name="id" value="' . (int)$id . '"><input type="hidden" name="' . h($field) . '" value="' . $val . '"><button class="mini ghost">' . h($label) . '</button></form>';
}
function badge_codes(): array { static $c = null; if ($c === null) { $c = []; foreach (glob(BADGE_DIR . '/*.gif') ?: [] as $f) { $b = basename($f, '.gif'); if (strlen($b) >= 1 && strlen($b) <= 3) $c[] = $b; } sort($c); } return $c; }
function badge_img(string $code): string { return '<img class="badge" src="/c_images/badges/' . h($code) . '.gif" alt="' . h($code) . '" loading="lazy" onerror="this.style.opacity=.2">'; }
function badge_preview(array $codes): string {
    if (!$codes) return '<span class="muted sm">—</span>';
    $o = '<span class="bprev">';
    foreach (array_slice($codes, 0, 6) as $c) $o .= '<img class="badge sm" src="/c_images/badges/' . h($c) . '.gif" alt="' . h($c) . '" title="' . h($c) . '" loading="lazy" onerror="this.style.display=\'none\'">';
    if (count($codes) > 6) $o .= '<span class="muted sm">+' . (count($codes) - 6) . '</span>';
    return $o . '</span>';
}
function rank_badges_html(array $codes): string {
    if (!$codes) return '<span class="muted sm">aucun</span>';
    $o = ''; foreach ($codes as $c) $o .= '<span class="bchip">' . badge_img($c) . '</span>';
    return $o;
}
function user_badges_html(int $uid, array $codes): string {
    if (!$codes) return '<span class="muted sm">aucun</span>';
    $o = '';
    foreach ($codes as $c) $o .= '<span class="bchip">' . badge_img($c) . '<form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="badge_remove"><input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="badge" value="' . h($c) . '">' . csrf_field() . '<button class="mini ghost" title="Retirer ' . h($c) . '">✕</button></form></span>';
    return $o;
}
function emu_running(): bool { $c = @fsockopen('127.0.0.1', 12321, $e, $s, 0.8); if ($c) { fclose($c); return true; } return false; }
/* Dernières lignes d'erreur du log émulateur (server.log), sans secrets. */
function server_log_errors(int $max = 14): array {
    $f = dirname(__DIR__) . '/server.log';
    if (!is_file($f)) return [];
    $sz = filesize($f); $fp = @fopen($f, 'rb'); if (!$fp) return [];
    fseek($fp, max(0, $sz - 140000)); $data = fread($fp, 140000); fclose($fp);
    $out = [];
    foreach (preg_split('/\r\n|\n/', (string)$data) as $ln) {
        $ln = rtrim($ln);
        if ($ln === '') continue;
        if (stripos($ln, 'password') !== false || stripos($ln, 'jdbc:') !== false) continue;
        if (preg_match('/\bERROR\b|\bWARN\b|Exception|Caused by|\bat [a-z0-9_.$]+\(/i', $ln)) $out[] = $ln;
    }
    return array_slice($out, -$max);
}
/* ⚠️ Sous Apache (Laragon) le PATH ne contient PAS powershell → chemin complet obligatoire. */
function ps_exe(): string {
    foreach ([(getenv('SystemRoot') ?: 'C:\\Windows') . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'] as $c) {
        if (is_file($c)) return '"' . $c . '"';
    }
    return 'powershell';
}
function srv_stop(): array { $o = []; $c = -1; @exec(ps_exe() . ' -NoProfile -ExecutionPolicy Bypass -File "' . __DIR__ . '\\_emu_stop.ps1" 2>&1', $o, $c); return ['code' => $c, 'out' => $o]; }
function srv_start(): array { $o = []; $c = -1; @exec(ps_exe() . ' -NoProfile -ExecutionPolicy Bypass -File "' . __DIR__ . '\\_emu_start.ps1" 2>&1', $o, $c); return ['code' => $c, 'out' => $o]; }

/* ---- Redémarrage « Kepler uniquement » (réutilise scripts/pack.ps1, opération détachée + suivi JSON) ---- */
/* Racine du dépôt = 3 niveaux au-dessus de admin/ (admin -> www -> Server -> racine),
   là où se trouvent scripts/pack.ps1 et _runtime/. dirname(__DIR__,2) pointait par erreur
   sur Server/ -> « pack.ps1 introuvable » + _runtime créé au mauvais endroit. */
function rk_status_path(): string { $d = dirname(__DIR__, 3) . '/_runtime'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d . '/restart_kepler.json'; }
function rk_pack_script(): string { return dirname(__DIR__, 3) . '/scripts/pack.ps1'; }
/* Lance pack.ps1 -Action restart-kepler en PROCESS DÉTACHÉ (survit à la fermeture de la page). */
function rk_launch(int $delay): bool {
    $status = rk_status_path();
    @unlink($status . '.cancel');
    @file_put_contents($status, json_encode(['step' => 'launch', 'state' => 'running', 'label' => 'Lancement de l\'opération…', 'detail' => $delay > 0 ? 'Redémarrage différé (annonce envoyée).' : 'Démarrage immédiat.', 'at' => date('c')], JSON_UNESCAPED_UNICODE), LOCK_EX);
    $pack = rk_pack_script();
    if (!is_file($pack)) { @file_put_contents($status, json_encode(['step' => 'launch', 'state' => 'error', 'label' => 'Script introuvable', 'detail' => 'scripts/pack.ps1 absent.', 'at' => date('c')], JSON_UNESCAPED_UNICODE), LOCK_EX); return false; }
    // 'start /B' détache le process du worker Apache -> l'opération continue si la page est fermée.
    $cmd = 'start "rk" /B ' . ps_exe() . ' -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' . $pack . '" -Action restart-kepler -StatusFile "' . $status . '" -DelaySeconds ' . (int)$delay . ' > NUL 2>&1';
    @pclose(@popen($cmd, 'r'));
    return true;
}

/* ---- RCON (alerte hôtel, etc.) ---- */
/* Prépare un texte pour le client Habbo (Shockwave) : retire les emoji (non rendus)
   puis convertit UTF-8 -> CP1252 (le client affiche en Windows-1252 ; corrige aussi
   la longueur des chaînes du protocole RCON, faussée par les accents UTF-8 multi-octets). */
function kepler_text(string $s): string {
    // Retire emoji & symboles hors CP1252 (pictogrammes, flèches décoratives, variation selectors)
    $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{20D0}-\x{20FF}\x{2190}-\x{21FF}]/u', '', $s);
    $s = trim(preg_replace('/[ \t]{2,}/', ' ', $s));
    $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    return $out === false ? $s : $out;
}
function rcon_str(string $s): string { return pack('N', strlen($s)) . $s; }
function rcon_send(string $header, array $params = []): bool {
    $c = @fsockopen('127.0.0.1', 12309, $e, $s, 1.5);
    if (!$c) return false;
    $body = rcon_str($header) . pack('N', count($params));
    foreach ($params as $k => $v) $body .= rcon_str((string)$k) . rcon_str((string)$v);
    fwrite($c, pack('N', strlen($body)) . $body);
    fclose($c);
    return true;
}
/* ---- Historique des diffusions (alertes & annonces) ---- */
function ensure_alert_log(): void {
    static $d = false; if ($d) return; $d = true;
    try { db()->exec("CREATE TABLE IF NOT EXISTS alert_log (id INT AUTO_INCREMENT PRIMARY KEY, author VARCHAR(64) NOT NULL DEFAULT '', channel VARCHAR(40) NOT NULL DEFAULT '', target VARCHAR(160) NOT NULL DEFAULT '', content TEXT NOT NULL, result VARCHAR(160) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
}
function alert_log_add(string $channel, string $target, string $content, string $result): void {
    ensure_alert_log();
    try { db()->prepare('INSERT INTO alert_log (author,channel,target,content,result) VALUES (?,?,?,?,?)')
        ->execute([(string)($_SESSION['admin']['username'] ?? '?'), mb_substr($channel, 0, 40), mb_substr($target, 0, 160), mb_substr($content, 0, 4000), mb_substr($result, 0, 160)]); } catch (Throwable $e) {}
}

/* ---- Sauvegarde base ---- */
function backup_dir(): string { $d = __DIR__ . '/../../../_backups'; if (!is_dir($d)) @mkdir($d, 0777, true); return $d; }
function backup_list(): array {
    $files = glob(backup_dir() . '/v14_*.sql') ?: [];
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files;
}
function db_backup(): bool {
    $dump = realpath(__DIR__ . '/../../../MariaDB/bin/mysqldump.exe');
    if (!$dump) { $GLOBALS['backup_err'] = 'mysqldump.exe introuvable'; return false; }
    $file = backup_dir() . '/v14_' . date('Ymd_His') . '.sql';
    $out = []; $code = -1;
    @exec('"' . $dump . '" -u ' . DB_USER . ' -P ' . DB_PORT . ' -h ' . DB_HOST . ' --default-character-set=utf8mb4 ' . DB_NAME . ' --result-file="' . $file . '" 2>&1', $out, $code);
    // Vérifie le code de sortie ET que le dump se termine proprement (pas de sauvegarde partielle)
    $tail = is_file($file) ? @file_get_contents($file, false, null, max(0, filesize($file) - 200)) : '';
    $okDump = ($code === 0) && is_file($file) && filesize($file) > 100 && strpos((string)$tail, 'Dump completed') !== false;
    if (!$okDump) {
        $GLOBALS['backup_err'] = 'code ' . $code . ($out ? ' — ' . implode(' | ', array_slice($out, 0, 2)) : '');
        if (is_file($file)) @unlink($file); // supprime la sauvegarde partielle/corrompue
        return false;
    }
    foreach (array_slice(backup_list(), 15) as $old) @unlink($old); // garder 15
    return true;
}
function page_title(string $t, string $sub = ''): void { echo '<div class="pagehead"><h1>' . h($t) . '</h1>' . ($sub ? '<p class="sub">' . h($sub) . '</p>' : '') . '</div>'; }
function stat_box(string $icon, $val, string $label, string $col = 'blue'): void { echo '<div class="stat ' . $col . '"><div class="ic">' . $icon . '</div><div><div class="num">' . h((string)$val) . '</div><div class="lbl">' . h($label) . '</div></div></div>'; }
function rank_badge(int $r): string { $n = [1 => 'Joueur', 2 => 'Community M.', 3 => 'Guide', 4 => 'Hobba', 5 => 'Super Hobba', 6 => 'Modérateur', 7 => 'Admin']; $c = $r >= 7 ? '#ff5b5b' : ($r >= 6 ? '#ffb033' : ($r >= 3 ? '#3a7afe' : '#5a6b85')); return '<span class="rk" style="background:' . $c . '">' . h($n[$r] ?? (string)$r) . '</span>'; }
function rank_options(int $c): string { $o = ''; foreach (RANKS as $n => $l) $o .= '<option value="' . $n . '"' . ($n === $c ? ' selected' : '') . '>' . h($l) . '</option>'; return $o; }
function available_casts(): array { $o = []; foreach (glob(DCR_DIR . '/hh_room_*.cct') ?: [] as $f) $o[] = basename($f, '.cct'); return $o; }
function decor_variants(array $casts, string $base): array {
    if ($base === '') return []; $root = $base;
    foreach (array_keys(DECOR_LABELS) as $s) if ($s !== '' && str_ends_with($base, '_' . $s)) { $root = substr($base, 0, -(strlen($s) + 1)); break; }
    $out = ['' => $root]; foreach ($casts as $c) if (strpos($c, $root . '_') === 0) $out[substr($c, strlen($root) + 1)] = $c;
    $ord = ['' => $out['']]; foreach (DECOR_LABELS as $s => $_) if ($s !== '' && isset($out[$s])) $ord[$s] = $out[$s]; foreach ($out as $s => $c) if (!isset($ord[$s])) $ord[$s] = $c; return $ord;
}
function decor_label(string $s): string { return DECOR_LABELS[$s] ?? ('⭐ ' . ucfirst($s)); }
function fuse_label(string $f): string {
    static $m = [
        'default' => 'Accès de base', 'fuse_buy_credits' => 'Acheter des crédits', 'fuse_login' => 'Se connecter',
        'fuse_room_queue_default' => "File d'attente standard", 'fuse_trade' => 'Échanger',
        'fuse_enter_full_rooms' => 'Entrer dans les salles pleines', 'fuse_room_alert' => 'Alerter dans sa salle',
        'fuse_enter_locked_rooms' => 'Entrer dans les salles verrouillées', 'fuse_kick' => 'Expulser (kick)',
        'fuse_mute' => 'Rendre muet', 'fuse_ban' => 'Bannir', 'fuse_receive_calls_for_help' => "Recevoir les appels à l'aide",
        'fuse_remove_stickies' => 'Retirer les post-its', 'fuse_room_kick' => "Expulser d'une salle", 'fuse_room_mute' => 'Rendre muet dans une salle',
        'fuse_any_room_controller' => "Contrôler n'importe quelle salle", 'fuse_credits' => 'Gérer les crédits',
        'fuse_ignore_room_owner' => 'Ignorer les droits du proprio', 'fuse_mod' => 'Modération', 'fuse_moderator_access' => 'Accès outils modérateur',
        'fuse_pick_up_any_furni' => "Ramasser n'importe quel meuble", 'fuse_superban' => 'Superban',
        'fuse_administrator_access' => 'Accès administrateur complet', 'fuse_see_flat_ids' => 'Voir les IDs de salle',
    ];
    return $m[$f] ?? $f;
}
function pager(int $total, int $pg, string $base): void {
    $pages = (int)ceil($total / PER_PAGE); if ($pages <= 1) return;
    echo '<div class="pager"><span class="muted sm">' . $total . ' résultats · page ' . $pg . '/' . $pages . '</span><div>';
    if ($pg > 1) echo '<a class="mini ghost" href="' . h($base) . '&pg=' . ($pg - 1) . '">← Précédent</a>';
    if ($pg < $pages) echo '<a class="mini ghost" href="' . h($base) . '&pg=' . ($pg + 1) . '">Suivant →</a>';
    echo '</div></div>';
}

function page_access(): void {
    page_title('Accès admin', 'Qui fait partie du staff, et quel rang minimum pour chaque onglet');

    /* --- Comptes du staff --- */
    $staff = db()->query('SELECT id,username,`rank`,last_online FROM users WHERE `rank`>=5 ORDER BY `rank` DESC, username')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>👮 Comptes du staff (' . count($staff) . ')</h3><a class="lnk" href="?p=users">Gérer les rangs →</a></div>';
    if (!$staff) echo '<div class="empty">Aucun compte staff (rang 5+).</div>';
    else {
        echo '<table class="clean"><tr><th>Joueur</th><th>Rang</th><th>Dernière connexion</th><th></th></tr>';
        foreach ($staff as $s) { $seen = (int)$s['last_online'] > 0 ? date('d/m/Y H:i', (int)$s['last_online']) : '—'; echo '<tr><td><b>' . h($s['username']) . '</b></td><td>' . rank_badge((int)$s['rank']) . '</td><td class="sm muted">' . $seen . '</td><td><a class="mini ghost" href="?p=user&id=' . (int)$s['id'] . '">Fiche →</a></td></tr>'; }
        echo '</table>';
    }
    echo '</div>';

    /* --- Accès par onglet, groupé par section --- */
    echo '<p class="hint" style="font-size:13px">Rang minimum requis pour chaque onglet. <b>5</b> = Super Hobba · <b>6</b> = Modérateur · <b>7</b> = Administrateur. « Accueil » et « Recherche » restent toujours accessibles. Page réservée au rang 7.</p>';
    echo '<form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="tabperm_update">';
    $nav = admin_nav();
    $gicons = ['Site & contenus' => '🌐', 'Joueurs' => '👥', 'Alertes & annonces' => '📢', 'Modération & assistance' => '🛡️', 'Hôtel & appartements' => '🏨', 'Catalogue & économie' => '🛋️', 'Animations' => '🎉', 'Technique' => '⚙️'];
    foreach (nav_groups() as $gl => $keys) {
        if ($gl === '') continue;
        echo '<div class="panel"><div class="ph"><h3>' . ($gicons[$gl] ?? '📁') . ' ' . h($gl) . '</h3></div><table class="clean"><tr><th>Onglet</th><th style="width:280px">Rang minimum</th></tr>';
        foreach ($keys as $k) {
            if (!isset($nav[$k])) continue; [$ic, $lbl] = $nav[$k]; $cur = tab_min($k);
            echo '<tr><td>' . $ic . ' ' . h($lbl) . ' <code class="sm muted">' . h($k) . '</code></td><td><select name="rank_' . h($k) . '">';
            foreach ([5 => '5 · Super Hobba et +', 6 => '6 · Modérateur et +', 7 => '7 · Administrateur seulement'] as $rv => $rl)
                echo '<option value="' . $rv . '"' . ($cur === $rv ? ' selected' : '') . '>' . $rl . '</option>';
            echo '</select></td></tr>';
        }
        echo '</table></div>';
    }
    echo '<button style="margin-bottom:20px">💾 Enregistrer les accès</button></form>';
}

function page_audit(): void {
    page_title('Journal admin', 'Historique des actions effectuées dans le panel');
    ensure_admin_log();
    $pg = max(1, (int)($_GET['pg'] ?? 1)); $off = ($pg - 1) * PER_PAGE;
    $total = (int)db()->query('SELECT COUNT(*) FROM admin_log')->fetchColumn();
    echo '<div class="panel"><div class="ph"><h3>📜 Dernières actions (' . $total . ')</h3></div>';
    if ($total === 0) { echo '<div class="empty">Aucune action enregistrée pour l\'instant.</div></div>'; return; }
    echo '<table class="clean"><tr><th>Quand</th><th>Admin</th><th>Action</th><th>Détail</th></tr>';
    $st = db()->query('SELECT author,action,detail,created_at FROM admin_log ORDER BY id DESC LIMIT ' . PER_PAGE . ' OFFSET ' . $off);
    foreach ($st as $r) echo '<tr><td class="sm muted">' . h(date('d/m H:i', strtotime((string)$r['created_at']))) . '</td><td><b>' . h($r['author']) . '</b></td><td><code>' . h($r['action']) . '</code></td><td class="sm">' . h($r['detail']) . '</td></tr>';
    echo '</table>';
    pager($total, $pg, '?p=audit');
    echo '</div>';
}

function page_search(): void {
    $q = trim($_GET['q'] ?? '');
    page_title('Recherche globale', 'Joueurs · Catalogue · Meubles · Salles');
    echo '<div class="panel"><form method="get" class="srch" style="margin:0"><input type="hidden" name="p" value="search"><input name="q" value="' . h($q) . '" placeholder="🔍 Rechercher partout..." autofocus style="min-width:320px"><button class="mini">Chercher</button></form></div>';
    if ($q === '') { echo '<p class="hint">Tape le nom d\'un joueur, d\'un meuble, d\'une salle ou d\'un article.</p>'; return; }
    $like = '%' . $q . '%';
    // Joueurs
    $st = db()->prepare('SELECT id,username,rank,credits FROM users WHERE username LIKE ? ORDER BY username LIMIT 12'); $st->execute([$like]); $rows = $st->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>👥 Joueurs (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<div class="empty">Aucun joueur.</div>'; else { echo '<table class="clean"><tr><th>Nom</th><th>Rang</th><th>Crédits</th><th></th></tr>';
        foreach ($rows as $r) echo '<tr><td><b>' . h($r['username']) . '</b></td><td>' . rank_badge((int)$r['rank']) . '</td><td>' . number_format((int)$r['credits'], 0, ',', ' ') . '</td><td><a class="mini ghost" href="?p=user&id=' . (int)$r['id'] . '">Ouvrir →</a></td></tr>'; echo '</table>'; }
    echo '</div>';
    // Catalogue
    $st = db()->prepare('SELECT id,name,sale_code,price FROM catalogue_items WHERE name LIKE ? OR sale_code LIKE ? ORDER BY name LIMIT 12'); $st->execute([$like, $like]); $rows = $st->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🛋️ Catalogue (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<div class="empty">Aucun article.</div>'; else { echo '<table class="clean"><tr><th>Nom</th><th>Code</th><th>Prix</th></tr>';
        foreach ($rows as $r) echo '<tr><td><b>' . h($r['name'] !== '' ? $r['name'] : $r['sale_code']) . '</b></td><td class="sm muted">' . h((string)$r['sale_code']) . '</td><td>' . (int)$r['price'] . '</td></tr>'; echo '</table><p class="hint"><a href="?p=catalogue&q=' . urlencode($q) . '">Éditer dans le catalogue →</a></p>'; }
    echo '</div>';
    // Meubles
    $st = db()->prepare('SELECT id,name,sprite FROM items_definitions WHERE name LIKE ? OR sprite LIKE ? ORDER BY name LIMIT 12'); $st->execute([$like, $like]); $rows = $st->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🪑 Meubles (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<div class="empty">Aucun meuble.</div>'; else { echo '<table class="clean"><tr><th>Nom</th><th>Sprite</th><th></th></tr>';
        foreach ($rows as $r) echo '<tr><td><b>' . h($r['name']) . '</b></td><td class="sm muted">' . h((string)$r['sprite']) . '</td><td><a class="mini ghost" href="?p=furni&edit=' . (int)$r['id'] . '">Éditer →</a></td></tr>'; echo '</table>'; }
    echo '</div>';
    // Salles
    $st = db()->prepare('SELECT id,name,owner_id FROM rooms WHERE name LIKE ? ORDER BY name LIMIT 12'); $st->execute([$like]); $rows = $st->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>🏛️ Salles (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<div class="empty">Aucune salle.</div>'; else { echo '<table class="clean"><tr><th>Nom</th><th>Type</th></tr>';
        foreach ($rows as $r) echo '<tr><td><b>' . h($r['name']) . '</b></td><td class="sm muted">' . ((int)$r['owner_id'] === 0 ? 'Publique' : 'Privée') . '</td></tr>'; echo '</table>'; }
    echo '</div>';
}

function page_textes(): void {
    page_title('Textes du jeu', 'Messages envoyés par le serveur (table external_texts) — traduisibles en FR');
    echo '<p class="hint" style="font-size:13px">Ce sont les textes que <b>l\'émulateur</b> envoie au client (noms des boissons, messages système, bus…). Après modification, <b>redémarre l\'émulateur</b> (onglet Serveur) pour appliquer. Les textes de l\'interface du client (menus, boutons) sont dans <code>dcr/14.1_b8/external_texts.txt</code>, déjà traduits.</p>';
    $rows = db()->query('SELECT entry,text FROM external_texts ORDER BY entry')->fetchAll();
    echo '<div class="panel"><div class="ph"><h3>💬 ' . count($rows) . ' textes</h3></div>';
    foreach ($rows as $r) {
        echo '<form method="post" class="js setting">' . csrf_field() . '<input type="hidden" name="action" value="xtext_update"><input type="hidden" name="entry" value="' . h($r['entry']) . '">
            <div class="srow"><code title="' . h($r['entry']) . '">' . h($r['entry']) . '</code><input name="value" value="' . h($r['text']) . '"><button class="mini">💾</button></div></form>';
    }
    echo '</div>';
}

function page_mysql(): void {
    // liste blanche des tables (securite anti-injection)
    $tables = [];
    foreach (db()->query('SHOW TABLES') as $r) { $tables[] = array_values($r)[0]; }

    $sel = $_GET['t'] ?? '';
    if ($sel !== '' && !in_array($sel, $tables, true)) $sel = '';

    // ---------- Vue d'ensemble : toutes les tables ----------
    if ($sel === '') {
        page_title('Base MySQL', 'Base « ' . DB_NAME . ' » · ' . count($tables) . ' tables · lecture');
        $status = [];
        foreach (db()->query('SHOW TABLE STATUS') as $s) $status[$s['Name']] = $s;
        $totalRows = 0; $totalSize = 0;
        echo '<div class="panel"><div class="ph"><h3>🗄️ Tables de la base ' . h(DB_NAME) . '</h3></div>';
        echo '<table class="clean"><tr><th>Table</th><th>Lignes</th><th>Taille</th><th>Moteur</th><th></th></tr>';
        foreach ($tables as $t) {
            $st = $status[$t] ?? [];
            $cnt = (int)db()->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
            $size = (int)($st['Data_length'] ?? 0) + (int)($st['Index_length'] ?? 0);
            $totalRows += $cnt; $totalSize += $size;
            echo '<tr><td><code>' . h($t) . '</code></td><td>' . number_format($cnt, 0, ',', ' ') . '</td><td class="sm muted">' . mysql_human_size($size) . '</td><td class="sm muted">' . h((string)($st['Engine'] ?? '')) . '</td><td><a class="mini ghost" href="?p=mysql&t=' . urlencode($t) . '">Ouvrir →</a></td></tr>';
        }
        echo '<tr><td><b>TOTAL</b></td><td><b>' . number_format($totalRows, 0, ',', ' ') . '</b></td><td class="sm"><b>' . mysql_human_size($totalSize) . '</b></td><td></td><td></td></tr>';
        echo '</table></div>';
        echo '<p class="hint">Explorateur en lecture seule. Pour modifier des données, utilise les onglets dédiés (Joueurs, Catalogue, Réglages…).</p>';
        return;
    }

    // ---------- Vue d'une table ----------
    $cnt = (int)db()->query('SELECT COUNT(*) FROM `' . $sel . '`')->fetchColumn();
    page_title('Base MySQL · ' . $sel, $cnt . ' ligne(s)');
    echo '<div style="margin-bottom:10px"><a class="mini ghost lnkbtn" href="?p=mysql">← Toutes les tables</a></div>';

    // structure
    $cols = [];
    echo '<details class="panel"><summary><b>🏗️ Structure (' . count(iterator_to_array(db()->query('SHOW COLUMNS FROM `' . $sel . '`'))) . ' colonnes)</b></summary>';
    echo '<table class="clean"><tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>';
    foreach (db()->query('SHOW COLUMNS FROM `' . $sel . '`') as $c) {
        $cols[] = $c['Field'];
        echo '<tr><td><code>' . h($c['Field']) . '</code></td><td class="sm muted">' . h($c['Type']) . '</td><td class="sm">' . h($c['Null']) . '</td><td class="sm">' . h((string)$c['Key']) . '</td><td class="sm muted">' . h((string)$c['Default']) . '</td></tr>';
    }
    echo '</table></details>';

    // données paginées
    $pg = max(1, (int)($_GET['pg'] ?? 1));
    $off = ($pg - 1) * PER_PAGE;
    echo '<div class="panel"><div class="ph"><h3>📄 Données</h3></div><div style="overflow-x:auto">';
    echo '<table class="clean"><tr>';
    foreach ($cols as $c) echo '<th style="white-space:nowrap">' . h($c) . '</th>';
    echo '</tr>';
    $rows = db()->query('SELECT * FROM `' . $sel . '` LIMIT ' . PER_PAGE . ' OFFSET ' . $off);
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($cols as $c) {
            $v = (string)($row[$c] ?? '');
            // SEC04 : ne jamais exposer secrets / hashes (ni en clair dans la cellule, ni dans l'attribut title)
            if ($v !== '' && preg_match('/pass|ticket|hash|secret|token|sso|auth|salt/i', $c)) {
                echo '<td class="sm muted" title="(masqué)" style="max-width:340px">••••••</td>';
                continue;
            }
            $short = mb_strlen($v) > 90 ? mb_substr($v, 0, 90) . '…' : $v;
            echo '<td class="sm" title="' . h($v) . '" style="max-width:340px;overflow:hidden">' . h($short) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></div>';
    pager($cnt, $pg, '?p=mysql&t=' . urlencode($sel));
    echo '</div>';
}
function mysql_human_size(int $b): string {
    if ($b >= 1073741824) return round($b / 1073741824, 1) . ' Go';
    if ($b >= 1048576) return round($b / 1048576, 1) . ' Mo';
    if ($b >= 1024) return round($b / 1024, 1) . ' Ko';
    return $b . ' o';
}

/* ================= LAYOUT ================= */
function render_header(string $p): void {
    $nav = admin_nav();
    $flash = flash();
    $tk = current_theme_key(); $t = THEMES[$tk]; $acc = $t[7]; $rgb = hexrgb($acc);
    ?><!doctype html><html lang="fr" data-mode="<?= $t[2] ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Habbo · Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"><style>
:root{--bg:<?= $t[3] ?>;--panel:<?= $t[4] ?>;--panel2:<?= $t[5] ?>;--sidebar:<?= $t[6] ?>;--line:<?= $t[10] ?>;--line2:<?= $t[11] ?>;--txt:<?= $t[8] ?>;--mut:<?= $t[9] ?>;--acc:<?= $acc ?>;--blue:<?= $acc ?>;--soft:rgba(<?= $rgb ?>,.13);--glow:rgba(<?= $rgb ?>,.32);--green:#12a85a;--red:#e5484d;--font:'Plus Jakarta Sans',system-ui,Segoe UI,Roboto,sans-serif;--mono:'JetBrains Mono',monospace}
*{box-sizing:border-box}html,body{margin:0}body{font:14px/1.55 var(--font);background:var(--bg);color:var(--txt);display:flex;min-height:100vh}
a{color:inherit}
.side{width:238px;flex:0 0 238px;background:var(--sidebar);border-right:1px solid var(--line2);display:flex;flex-direction:column;position:sticky;top:0;height:100vh}
.side .brand{padding:20px 18px;font-weight:900;color:var(--acc);font-size:17px;letter-spacing:.5px;border-bottom:1px solid var(--line2)}
.side .brand small{display:block;color:var(--mut);font-weight:600;font-size:11px;letter-spacing:2px;margin-top:2px}
.side nav{padding:12px 10px;display:flex;flex-direction:column;flex:1;overflow-y:auto;min-height:0}
.side nav a{display:flex;align-items:center;padding:11px 13px;border-radius:11px;text-decoration:none;color:var(--mut);font-weight:600}
.side nav a .i{font-size:17px;width:22px;text-align:center}
.side nav .grp{display:flex;align-items:center;width:100%;background:none;border:0;border-top:1px solid var(--line2);cursor:pointer;font-family:inherit;text-align:left;font-size:10px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--mut);opacity:.75;padding:11px 13px 6px;margin-top:8px}
.side nav .grp:hover{opacity:1}
.side nav .grp .car{margin-left:auto;font-size:11px;opacity:.8;transition:transform .15s}
.navgrp{margin-top:2px}
.navgrp.open>.grp .car{transform:rotate(90deg)}
.navgrp>.grpitems{display:none}
.navgrp.open>.grpitems{display:block}
.navgrp:first-of-type>.grp{border-top:0;margin-top:2px}
.side nav a:hover{background:var(--panel);color:var(--txt)}
.side nav a.on{background:var(--blue);color:#fff;box-shadow:0 6px 16px var(--glow)}
.side .foot{border-top:1px solid var(--line2);padding:14px 16px;font-size:13px}
.side .foot .who{color:var(--txt);font-weight:700}.side .foot a{color:var(--mut);text-decoration:none;display:inline-block;margin-top:6px;margin-right:12px}
.side .foot a:hover{color:var(--txt)}
.main{flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{height:0}
.content{padding:24px 28px 92px;max-width:none;width:100%;margin:0}
.pagehead h1{font-size:25px;margin:0}.pagehead .sub{color:var(--mut);margin:4px 0 18px}
.sub{color:var(--mut)}.muted{color:var(--mut)}.sm{font-size:12px}
h3{font-size:15px;margin:0}h3.sec{margin:22px 0 10px;color:var(--mut);font-size:13px;text-transform:uppercase;letter-spacing:1px}
code{background:var(--bg);padding:2px 7px;border-radius:6px;color:var(--acc);font-size:12px;border:1px solid var(--line);font-family:var(--mono)}
.alertbar{display:block;background:linear-gradient(#f7a838,#ef8f13);color:#fff;font-size:13.5px;font-weight:600;padding:13px 18px;border-radius:14px;margin-bottom:16px;box-shadow:0 3px 10px rgba(239,143,19,.28);text-shadow:0 1px 0 rgba(0,0,0,.15)}
.alertbar:hover{filter:brightness(1.05);text-decoration:none}
.alertbar b{font-weight:800}
.todo{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:18px}
.todocard{display:flex;flex-direction:column;background:var(--panel);border:1px solid var(--line2);border-radius:14px;padding:14px 16px;text-decoration:none;color:var(--txt);transition:transform .12s,border-color .15s}
.todocard .tn{font-size:26px;font-weight:800;line-height:1}
.todocard .tl{color:var(--mut);font-size:12.5px;font-weight:600;margin-top:4px}
.todocard:hover{border-color:var(--acc);transform:translateY(-2px)}
.todocard.urgent{border-color:#ef8f13;background:linear-gradient(180deg,rgba(239,143,19,.10),transparent)}
.todocard.urgent .tn{color:#ef8f13}
/* Modération Homes : dossier maître-détail (liste gauche / dossier droite) */
.hmwrap{display:grid;grid-template-columns:320px minmax(0,1fr);gap:16px;align-items:start}
@media(max-width:900px){.hmwrap{grid-template-columns:1fr}}
.hmlist{display:flex;flex-direction:column;gap:8px;max-height:74vh;overflow:auto}
.hmitem{display:block;background:var(--panel);border:1px solid var(--line2);border-radius:12px;padding:10px 12px;text-decoration:none;color:var(--txt)}
.hmitem:hover{border-color:var(--acc)}
.hmitem.on{border-color:var(--acc);background:var(--soft)}
.hmi-top{display:flex;justify-content:space-between;align-items:center}
.hmdetail{min-width:0}
.hmrow{display:block;background:var(--panel);border:1px solid var(--line2);border-radius:12px;padding:9px 12px;text-decoration:none;color:var(--txt)}
.hmrow:hover{border-color:var(--acc)}
.hmrow.sel{border-color:var(--acc);background:var(--soft)}
.hmrow-top{display:flex;justify-content:space-between;align-items:center;gap:6px}
.convthread{display:flex;flex-direction:column;gap:10px;max-height:52vh;overflow:auto;padding:4px 2px}
.cmsg2{border:1px solid var(--line2);border-radius:12px;padding:8px 12px;max-width:85%}
.cmsg2 .cm-body{margin-top:3px;white-space:pre-wrap;word-break:break-word}
.cm-player{align-self:flex-start;background:var(--panel)}
.cm-staff{align-self:flex-end;background:var(--soft);border-color:var(--acc)}
.cm-internal{align-self:stretch;max-width:100%;background:#fff6d8;border-color:#e3c65a}
.cm-system{align-self:center;max-width:100%;background:var(--soft);border-style:dashed;text-align:center}
.cvwrap{display:grid;grid-template-columns:minmax(220px,26%) minmax(0,1fr) minmax(190px,22%);gap:14px;align-items:start;margin-top:10px}
.cvwrap.noinfo{grid-template-columns:minmax(220px,26%) minmax(0,1fr)}
.cvwrap.noinfo .cvinfo{display:none}
.cvlist{display:flex;flex-direction:column;gap:8px;max-height:72vh;overflow:auto}
.cvmain{min-width:0}
.cvinfo{min-width:0;background:var(--panel);border:1px solid var(--line2);border-radius:12px;padding:12px}
@media(max-width:1150px){.cvwrap,.cvwrap.noinfo{grid-template-columns:minmax(200px,32%) minmax(0,1fr)}.cvinfo{display:none}}
@media(max-width:780px){.cvwrap,.cvwrap.noinfo{grid-template-columns:1fr}.cvlist{max-height:none}}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));grid-gap:14px;gap:14px;margin-bottom:18px}
.stat{display:flex;align-items:center;background:var(--panel);border:1px solid var(--line2);border-radius:16px;padding:16px 18px}
.stat .ic{font-size:24px;width:44px;height:44px;display:grid;place-items:center;border-radius:12px;background:var(--soft)}
.stat.blue .ic{color:var(--blue)}.stat.green .ic{color:var(--green)}.stat.gold .ic{color:var(--acc)}.stat.red .ic{color:var(--red)}
.stat .num{font-size:23px;font-weight:800;line-height:1}.stat .lbl{color:var(--mut);font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
.cols{display:grid;grid-template-columns:1fr 1fr;grid-gap:16px;gap:16px}@media(max-width:820px){.cols{grid-template-columns:1fr}}
.panel{background:var(--panel);border:1px solid var(--line2);border-radius:16px;padding:16px 18px;margin:12px 0}
.ph{display:flex;align-items:center;margin-bottom:12px}.ph h3{flex:0 0 auto}.ph .srch{margin-left:auto}
.lnk{margin-left:auto;color:var(--blue);text-decoration:none;font-size:13px;font-weight:600}
table.clean{width:100%;border-collapse:collapse}table.clean th{text-align:left;color:var(--mut);font-size:11px;text-transform:uppercase;letter-spacing:.5px;padding:8px 10px;border-bottom:1px solid var(--line2)}
table.clean td{padding:9px 10px;border-bottom:1px solid var(--line)}table.clean tr:hover td{background:var(--panel2)}
.drawer td{background:var(--panel2)}.drawer:hover td{background:var(--panel2)}
input,select,button{font:inherit}input,select{background:var(--bg);border:1px solid var(--line2);color:var(--txt);border-radius:9px;padding:8px 10px}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--blue)}
textarea{background:var(--bg);border:1px solid var(--line2);color:var(--txt);border-radius:9px;padding:8px 10px;font:inherit;resize:vertical;width:100%}
button{background:var(--blue);color:#fff;border:0;border-radius:9px;padding:9px 15px;cursor:pointer;font-weight:700}button:hover{filter:brightness(1.1)}
button.ghost,a.ghost{background:transparent;border:1px solid var(--line2);color:var(--mut)}
button.mini,a.mini{padding:6px 11px;font-size:13px;border-radius:8px}a.lnkbtn{text-decoration:none;display:inline-block}
button.mini.ok{background:var(--green)}button.mini.warn{background:#c2603a}
.row{display:flex;align-items:flex-end;flex-wrap:wrap}
label{display:flex;flex-direction:column;font-size:12px;color:var(--mut)}label input,label select{color:var(--txt)}
.srch{display:flex}.srch input{padding:7px 11px}
input.filt{padding:8px 12px;border-radius:9px}
.tag{background:var(--bg);border:1px solid var(--line2);border-radius:6px;padding:2px 8px;color:var(--mut);font-size:12px}
.tag.blue{background:#22406a;border-color:#2f5da0;color:#cfe0ff}.tag.green{background:#234a2e;border-color:#356b45;color:#c6efd4}
.tag.purple{background:#3d2a55;border-color:#5b3f80;color:#e2d2f6}.tag.orange{background:#5a3410;border-color:#8a5216;color:#ffd9a8}
.rk{color:#fff;border-radius:6px;padding:3px 9px;font-size:11px;font-weight:700}.rk.green{background:#2e7d46}.rk.red{background:#8a3030}
.decorwrap{display:grid;grid-template-columns:1fr;grid-gap:12px;gap:12px}
.seasons{display:flex;flex-wrap:wrap}
button.season{background:var(--panel2);border:1px solid var(--line2);color:var(--txt);padding:11px 16px;border-radius:11px;font-weight:700}
button.season:hover{border-color:var(--acc);background:var(--soft)}button.season.active{background:var(--acc);color:#fff;border-color:var(--acc);cursor:default}
.warn{background:var(--soft);border:1px solid var(--line2);color:var(--txt);padding:12px 16px;border-radius:12px;margin:12px 0}
.empty{color:var(--mut);text-align:center;padding:22px}
details.panel summary{cursor:pointer;font-size:15px;font-weight:700}details[open].panel summary{margin-bottom:10px}
.setting{border-top:1px solid var(--line);padding:9px 0}.setting:first-of-type{border-top:0}
.srow{display:flex;align-items:center}.srow code{flex:0 0 290px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.srow input{flex:1}
.hint{color:var(--mut);font-size:12px;margin-top:4px;padding-left:2px}
.thsel{display:flex;flex-direction:column;font-size:12px;color:var(--mut);margin-bottom:12px}.thsel select{width:100%}
.srv{display:flex;flex-wrap:wrap;margin-top:6px}
.srv button{padding:12px 18px;font-size:14px;border-radius:11px}
button.bigok{background:var(--green)}button.bigred{background:var(--red)}button.bigwarn{background:#e0792b}
.pager{display:flex;align-items:center;justify-content:space-between;margin-top:12px}.pager a{text-decoration:none;margin-left:6px}
.perms{display:flex;flex-wrap:wrap}.perms .tag{padding:5px 10px}
.badgegrid{display:flex;flex-wrap:wrap}
.bcell{display:flex;flex-direction:column;align-items:center;background:var(--bg);border:1px solid var(--line2);border-radius:10px;padding:8px 6px;min-width:58px;cursor:pointer}
.bcell:hover{border-color:var(--acc)}.bcell span{font-size:10px;color:var(--mut);font-family:var(--mono)}
.bcell form{margin:0}img.badge{width:40px;height:40px;object-fit:contain}
.bchip{display:inline-flex;align-items:center;gap:2px;background:var(--bg);border:1px solid var(--line2);border-radius:8px;padding:3px 6px}
.bprev{display:inline-flex;align-items:center;gap:3px}img.badge.sm{width:22px;height:22px}
#toasts{position:fixed;right:18px;bottom:18px;display:flex;flex-direction:column;z-index:50}
.toast{background:#12331f;border:1px solid #2c6b3f;color:#c7f5d6;padding:12px 16px;border-radius:11px;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,.4);animation:sl .25s ease}
.toast.err{background:#331414;border-color:#6b2c2c;color:#f4c0c0}
@keyframes sl{from{transform:translateY(10px);opacity:0}to{transform:none;opacity:1}}
@media(max-width:760px){.side{width:64px;flex-basis:64px}.side .brand small,.side nav a span,.side .foot .who,.side .foot a{display:none}.side nav a{justify-content:center}.content{padding:18px}}
/* --- Cadre commun 2026-10 : pleine largeur + repli barre latérale + barre d'actions collante --- */
.sbtoggle{background:none;border:0;color:var(--mut);cursor:pointer;font-size:18px;padding:6px 10px;border-radius:9px;line-height:1;margin-right:6px}
.sbtoggle:hover{background:var(--panel2);color:var(--txt)}
body.sb-collapsed .side{width:64px;flex:0 0 64px}
body.sb-collapsed .side .brand small,body.sb-collapsed .side nav a span,body.sb-collapsed .side nav .grp span,body.sb-collapsed .side nav .grp .car,body.sb-collapsed .side .foot .who,body.sb-collapsed .side .foot a{display:none}
body.sb-collapsed .side nav a{justify-content:center}
body.sb-collapsed .side nav .grp{justify-content:center;padding-left:0;padding-right:0}
body.sb-collapsed .side .brand{font-size:0;padding:20px 0;text-align:center}
body.sb-collapsed .side .brand::after{content:'H';font-size:18px}
/* Barre d'actions collante : Enregistrer / Annuler toujours accessibles */
.savebar{position:sticky;bottom:0;z-index:15;display:flex;align-items:center;margin:18px -28px -92px;padding:12px 28px;background:var(--panel);border-top:1px solid var(--line2);box-shadow:0 -4px 14px rgba(0,0,0,.08)}
.savebar>*+*{margin-left:10px}.savebar .spacer{flex:1}.savebar .hint{margin:0}
/* Éditeur : zone principale large + colonne latérale repliable (sticky) */
.editcols{display:grid;grid-template-columns:minmax(0,1fr) 320px;grid-gap:18px;gap:18px;align-items:start}
@media(max-width:1100px){.editcols{grid-template-columns:1fr}}
.aside-panel{position:sticky;top:76px}
/* Fiche joueur : bandeau + onglets internes (fail-safe) */
.ubanner{display:flex;align-items:center;gap:18px;flex-wrap:wrap;background:var(--panel);border:1px solid var(--line2);border-radius:16px;padding:14px 18px;margin:-6px 0 14px}
.ubanner>*+*{margin-left:18px}
.ub-id{display:flex;align-items:center;gap:12px}.ub-id>*+*{margin-left:12px}
.ub-av{image-rendering:pixelated;background:#f3eecf;border:1px solid #d8cfa6;border-radius:6px;vertical-align:bottom}
.ub-name{font-size:19px;font-weight:800}
.ub-meta{flex:1;min-width:200px}
.ub-act{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.ub-act>*+*{margin-left:8px}
.ub-menu{position:relative}
.ub-menu>summary{list-style:none;cursor:pointer}.ub-menu>summary::-webkit-details-marker{display:none}
.ub-menu-pop{position:absolute;right:0;top:calc(100% + 6px);background:var(--panel);border:1px solid var(--line2);border-radius:12px;padding:8px;display:flex;flex-direction:column;gap:6px;min-width:240px;z-index:30;box-shadow:0 10px 30px rgba(0,0,0,.3)}
.ub-menu-pop>*{margin:0}.ub-menu-pop>*+*{margin-top:6px}
.uptabs .utabbar{display:flex;flex-wrap:wrap;gap:8px;margin:4px 0 16px;border-bottom:1px solid var(--line2);padding-bottom:12px;position:sticky;top:60px;background:var(--bg);z-index:10}
.uptabs .utabbar>*{margin:0 8px 8px 0}
.uptabs .utabbar button{padding:9px 16px;border-radius:10px;background:var(--panel);border:1px solid var(--line2);color:var(--mut);font-weight:700;font-size:13px;cursor:pointer}
.uptabs .utabbar button.on{background:var(--acc);border-color:var(--acc);color:#fff}
.uptabs.js .utab{display:none}.uptabs.js .utab.on{display:block}
.side nav .grp .i{font-size:16px;width:22px;text-align:center}
.side nav .grpitems a{padding-left:18px;font-size:13.5px}
.side nav .grpitems a .i{font-size:15px}
body.sb-collapsed .side nav .grpitems a{padding-left:13px}
/* --- Raffinements visuels 2026-09 --- */
.side nav a,button,.stat,.panel,button.season,a.mini,button.mini,a.ghost{transition:background .15s ease,color .15s ease,border-color .15s ease,transform .12s ease,box-shadow .15s ease}
.side nav a:hover{transform:translateX(2px)}
.stat{box-shadow:0 1px 2px rgba(0,0,0,.05)}.stat:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.10)}
.panel{box-shadow:0 1px 2px rgba(0,0,0,.05)}
.pagehead h1{font-weight:800;letter-spacing:-.4px}
.ph h3{font-weight:800}
table.clean tr:hover td{transition:background .1s ease}
button.mini,a.mini{font-weight:700}
.side nav .grp:first-child{padding-top:4px}
details.panel summary{list-style:none}details.panel summary::-webkit-details-marker{display:none}
details.panel summary::before{content:'▸';color:var(--mut);margin-right:8px;font-size:12px;display:inline-block;transition:transform .15s}
details[open].panel summary::before{transform:rotate(90deg)}
/* --- Compat Basilisk/Goanna : remplace gap flex par des marges --- */
.side nav a{margin-top:3px}
.side nav a>*+*{margin-left:11px}
.stat>*+*{margin-left:13px}
.ph>*+*{margin-left:10px}
.row>*+*{margin-left:10px}
.srch>*+*{margin-left:6px}
.seasons>*{margin:0 9px 9px 0}
.srow>*+*{margin-left:8px}
.thsel>*+*{margin-top:5px}
.srv>*{margin:0 10px 10px 0}
.perms>*{margin:0 7px 7px 0}
.badgegrid>*{margin:0 8px 8px 0}
.bcell>*+*{margin-top:4px}
label>input,label>select,label>textarea{margin-top:4px}
input[type=checkbox]{margin-top:0;margin-right:6px}
#toasts>*+*{margin-top:8px}
/* Barre supérieure */
.topbar{display:flex;flex-wrap:wrap;row-gap:8px;align-items:center;min-height:60px;flex:0 0 auto;padding:0 36px;border-bottom:1px solid var(--line2);background:var(--panel);position:sticky;top:0;z-index:20}
.tb-crumb{font-size:14px;color:var(--mut);white-space:nowrap}
.tb-crumb b{color:var(--txt)}
.tb-sec{opacity:.85}
.tb-sep{margin:0 8px;opacity:.5}
.tb-search{display:flex;align-items:center;margin:0 auto;flex:1;max-width:440px}
.tb-search input{flex:1;min-width:0;padding:9px 12px;border-radius:9px 0 0 9px;border:1px solid var(--line2);border-right:0;background:var(--bg);color:var(--txt);font:inherit;font-size:13px}
.tb-search button{padding:9px 13px;border-radius:0 9px 9px 0;border:1px solid var(--acc);background:var(--acc);color:#fff;cursor:pointer;font-size:14px}
.tb-status{display:flex;align-items:center;text-decoration:none;font-weight:700;font-size:12.5px;padding:7px 12px;border-radius:9px;border:1px solid var(--line2);white-space:nowrap}
.tb-status .dot{width:9px;height:9px;border-radius:50%;margin-right:7px}
.tb-status.up{color:var(--green)}.tb-status.up .dot{background:var(--green)}
.tb-status.down{color:var(--red)}.tb-status.down .dot{background:var(--red)}
@media(max-width:760px){.topbar{padding:10px 16px}.tb-crumb{display:none}}
/* Sous-onglets de section (style Cadurix) */
.subtabs{display:flex;flex-wrap:wrap;margin:0 0 20px;padding-bottom:14px;border-bottom:1px solid var(--line2)}
.subtabs>a{margin:0 8px 8px 0;padding:8px 15px;border-radius:10px;background:var(--panel);border:1px solid var(--line2);color:var(--mut);font-weight:700;font-size:12.5px;text-decoration:none;white-space:nowrap}
.subtabs>a:hover{color:var(--text);border-color:var(--acc)}
.subtabs>a.on{background:var(--acc);border-color:var(--acc);color:#fff}
.subtabs .nbadge{display:inline-block;background:#e5484d;color:#fff;font-size:10.5px;font-weight:800;min-width:16px;text-align:center;padding:1px 5px;border-radius:9px;margin-left:2px}
.side nav a .sidebadge{margin-left:auto;background:#e5484d;color:#fff;font-size:10.5px;font-weight:800;min-width:18px;text-align:center;padding:1px 6px;border-radius:9px}
.stafffeed{margin-top:14px;max-height:420px;overflow-y:auto}
.stafffeed .smsg{border:1px solid var(--line2);border-radius:10px;padding:9px 12px;margin-bottom:8px;background:var(--panel)}
.stafffeed .smsg.mine{border-left:3px solid var(--acc);background:var(--soft)}
.stafffeed .sm-head{display:flex;align-items:center;font-size:11.5px;color:var(--mut)}
.stafffeed .sm-head b{color:var(--txt)}
.stafffeed .sm-date{margin-left:8px}
.stafffeed .sm-body{margin-top:4px;font-size:13px;color:var(--txt);line-height:1.5;white-space:pre-wrap}
/* Tableau de bord : colonnes asymétriques + actions rapides */
.dcols{display:grid;grid-template-columns:1.7fr 1fr;grid-gap:16px;gap:16px}
@media(max-width:900px){.dcols{grid-template-columns:1fr}}
.qa{display:flex;flex-direction:column}
.qa .qabtn{display:flex;align-items:center;padding:11px 13px;border-radius:10px;background:var(--bg);border:1px solid var(--line2);color:var(--txt);text-decoration:none;font-weight:600;font-size:13px;margin-bottom:7px}
.qa .qabtn:last-child{margin-bottom:0}
.qa .qabtn span{margin-left:10px}
.qa .qabtn:hover{border-color:var(--acc);color:var(--acc)}
.logbox{max-height:230px;overflow:auto;background:var(--bg);border:1px solid var(--line2);border-radius:10px;padding:12px 14px;font:12px/1.5 var(--mono);color:var(--mut);white-space:pre-wrap;word-break:break-word;margin:0}
</style></head><body>
<aside class="side"><div class="brand"><img src="/c_images/WebLogos/habbo_logo_nourl.gif" alt="Habbo" style="width:100%;max-width:180px;height:auto;display:block;margin:0 auto 4px;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated"><small>ADMINISTRATION</small></div><nav><?php
    $gicons = ['Site & contenus' => '🌐', 'Joueurs' => '👥', 'Alertes & annonces' => '📢', 'Modération & assistance' => '🛡️', 'Hôtel & appartements' => '🏨', 'Catalogue & économie' => '🛋️', 'Animations' => '🎉', 'Technique' => '⚙️'];
    foreach (nav_groups() as $grpLabel => $keys) {
        $visible = array_filter($keys, fn($k) => isset($nav[$k]) && tab_allowed($k));
        if (!$visible) continue;
        if ($grpLabel === '') {
            foreach ($visible as $k) { [$ic, $lbl] = $nav[$k]; echo '<a class="' . ($p === $k ? 'on' : '') . '" href="?p=' . $k . '"><span class="i">' . $ic . '</span><span>' . h($lbl) . '</span></a>'; }
            continue;
        }
        $cur = in_array($p, $visible, true);
        $gb = in_array('messages', $keys, true) ? messages_badge() : 0;
        echo '<div class="navgrp' . ($cur ? ' open' : '') . '"' . ($cur ? ' data-cur="1"' : '') . ' data-g="' . h($grpLabel) . '">';
        echo '<button type="button" class="grp" onclick="toggleGrp(this)"><span class="i">' . ($gicons[$grpLabel] ?? '📁') . '</span><span>' . h($grpLabel) . '</span>' . ($gb > 0 ? '<span class="sidebadge">' . $gb . '</span>' : '') . '<span class="car">▸</span></button>';
        echo '<div class="grpitems">';
        foreach ($visible as $k) { [$ic, $lbl] = $nav[$k]; $tb = ($k === 'messages') ? messages_badge() : 0; echo '<a class="' . ($p === $k ? 'on' : '') . '" href="?p=' . $k . '"><span class="i">' . $ic . '</span><span>' . h($lbl) . '</span>' . ($tb > 0 ? '<span class="sidebadge">' . $tb . '</span>' : '') . '</a>'; }
        echo '</div></div>';
    }
    ?></nav><div class="foot">
    <label class="thsel">🎨 Thème<select onchange="setTheme(this.value)"><?php foreach (THEMES as $k => $tt) echo '<option value="' . $k . '"' . ($k === $tk ? ' selected' : '') . '>' . $tt[1] . ' ' . h($tt[0]) . '</option>'; ?></select></label>
    <div class="who">👤 <?= h($_SESSION['admin']['username']) ?></div><a href="/" target="_blank">▶️ Jeu</a><a href="?p=logout">Déconnexion</a></div></aside>
<div class="main"><?php
    $secLabel = ''; foreach (nav_groups() as $gl => $keys) { if ($gl !== '' && in_array($p, $keys, true)) { $secLabel = $gl; break; } }
    $pageLabel = isset($nav[$p]) ? $nav[$p][1] : 'Tableau de bord';
    $emuUp = emu_running();
    $sq = ($p === 'search') ? h(trim($_GET['q'] ?? '')) : '';
?><header class="topbar">
  <button type="button" class="sbtoggle" onclick="toggleSidebar()" title="Replier / déplier le menu" aria-label="Replier le menu">☰</button>
  <div class="tb-crumb"><?php if ($secLabel !== '') echo '<span class="tb-sec">' . h($secLabel) . '</span><span class="tb-sep">›</span>'; ?><b><?= h($pageLabel) ?></b></div>
  <form class="tb-search" method="get" action="?"><input type="hidden" name="p" value="search"><input name="q" placeholder="Rechercher un joueur, un mobi, une salle…" value="<?= $sq ?>"><button type="submit" title="Rechercher">🔍</button></form>
  <?php if (tab_allowed('alerts')) { ?><a class="tb-status" href="?p=alerts&sec=cfh" title="Alertes &amp; annonces — appels à l'aide du jeu (réception non raccordée pour l'instant)" style="margin-right:10px">📢 Alertes</a><?php } ?>
  <a class="tb-status <?= $emuUp ? 'up' : 'down' ?>" href="?p=server" title="État de l'émulateur — ouvrir la page Serveur"><span class="dot"></span><?= $emuUp ? 'En marche' : 'Arrêté' ?></a>
</header><div class="content"><?php
    // Plus de barre d'onglets horizontale pour le groupe courant : ses onglets sont
    // désormais dans le menu latéral (groupe dépliable). Les sous-sections propres à
    // une page (Gestion du Site « sec= », Homes…) gardent leur propre sous-navigation.
    if ($flash) echo '<script>window.__flash=' . json_encode($flash) . ';</script>';
    // Liste d'autocomplétion des pseudos, disponible pour tous les champs (list="dl_pseudos")
    echo pseudo_datalist();
}
/* Datalist des pseudos joueurs (rendu une seule fois par page). */
function pseudo_datalist(): string {
    static $done = false; if ($done) return ''; $done = true;
    try { $o = ''; foreach (db()->query("SELECT username FROM users ORDER BY username LIMIT 500") as $r) $o .= '<option value="' . h((string)$r['username']) . '">'; return '<datalist id="dl_pseudos">' . $o . '</datalist>'; }
    catch (Throwable $e) { return ''; }
}
function render_footer(): void { ?>
</div></div><div id="toasts"></div>
<script>
function toast(msg,ok){var t=document.createElement('div');t.className='toast'+(ok===false?' err':'');t.textContent=msg;document.getElementById('toasts').appendChild(t);setTimeout(function(){t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(function(){t.remove()},400)},3200);}
function tgl(id){var e=document.getElementById(id);if(e)e.style.display=e.style.display==='none'?'table-row':'none';}
function setTheme(v){document.cookie='r14theme='+v+';path=/;max-age=31536000';location.reload();}
function pickBadge(c){var e=document.getElementById('badgecode');if(e){e.value=c;e.scrollIntoView({block:'center'});e.focus();toast('Badge '+c+' sélectionné',true);}}
/* Menu latéral en accordéon : ouvrir une catégorie referme les autres (au chargement, seule la catégorie de la page courante reste ouverte, rendue côté serveur). */
function toggleGrp(btn){var g=btn.parentNode;var open=!g.classList.contains('open');var l=document.querySelectorAll('.navgrp');for(var i=0;i<l.length;i++)l[i].classList.remove('open');if(open)g.classList.add('open');}
/* Pagination : rester à la même hauteur après « Précédent / Suivant » (on ne remonte plus en haut). */
(function(){var cp=(new URLSearchParams(location.search)).get('p')||'';try{var s=JSON.parse(sessionStorage.getItem('r14pg')||'null');if(s&&s.p===cp){window.scrollTo(0,s.y||0);}sessionStorage.removeItem('r14pg');}catch(e){}document.addEventListener('click',function(ev){var a=ev.target.closest?ev.target.closest('a[href*="pg="]'):null;if(!a)return;try{sessionStorage.setItem('r14pg',JSON.stringify({p:cp,y:window.scrollY||window.pageYOffset||0}));}catch(e){}});})();
function toggleSidebar(){var c=document.body.classList.toggle('sb-collapsed');try{localStorage.setItem('r14sb',c?'1':'0');}catch(e){}}
(function(){try{if(localStorage.getItem('r14sb')==='1')document.body.classList.add('sb-collapsed');}catch(e){}})();
/* Mémorise la position de défilement du menu latéral (reste au même endroit après navigation) */
(function(){var n=document.querySelector('.side nav');if(!n)return;try{var y=sessionStorage.getItem('r14navscroll');if(y)n.scrollTop=parseInt(y,10)||0;}catch(e){}n.addEventListener('scroll',function(){try{sessionStorage.setItem('r14navscroll',String(n.scrollTop));}catch(e){}});})();
/* Onglets internes de la fiche joueur (fail-safe : sans JS, toutes les sections restent visibles) */
function utShow(id){var w=document.getElementById('uptabs');if(!w)return;var t=w.querySelectorAll('.utab');for(var i=0;i<t.length;i++)t[i].classList.toggle('on',t[i].id===id);var b=w.querySelectorAll('.utabbar button');for(var j=0;j<b.length;j++)b[j].classList.toggle('on',b[j].getAttribute('data-t')===id);try{sessionStorage.setItem('r14utab',id);}catch(e){}}
function utCopy(path){var u=location.origin+path;try{if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u);}else{var ta=document.createElement('textarea');ta.value=u;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');}catch(e2){}ta.remove();}toast('Lien copié : '+u,true);}catch(e){toast('Copie impossible — '+u,false);}}
(function(){var w=document.getElementById('uptabs');if(!w)return;w.classList.add('js');var b=w.querySelectorAll('.utabbar button');for(var j=0;j<b.length;j++)b[j].addEventListener('click',function(){utShow(this.getAttribute('data-t'));});var saved='';try{saved=sessionStorage.getItem('r14utab')||'';}catch(e){}var t=w.querySelectorAll('.utab');var start=t.length?t[0].id:'';if(saved){for(var k=0;k<t.length;k++){if(t[k].id===saved){start=saved;break;}}}if(start)utShow(start);})();
if(window.__flash)toast(window.__flash,true);
/* Avertissement si un formulaire a des changements non enregistrés (exclut recherche/filtres) */
var __dirty=false;
function __chk(el){var f=el&&el.closest?el.closest('form'):null;if(!f)return false;if(f.classList.contains('srch')||f.classList.contains('tb-search'))return false;if(el.classList&&el.classList.contains('filt'))return false;return true;}
document.addEventListener('input',function(e){if(__chk(e.target))__dirty=true;});
document.addEventListener('change',function(e){if(__chk(e.target))__dirty=true;});
document.addEventListener('submit',function(){__dirty=false;},true);
window.addEventListener('beforeunload',function(e){if(__dirty){e.preventDefault();e.returnValue='';}});
document.addEventListener('submit',function(e){
  var f=e.target;if(!f.classList.contains('js'))return;e.preventDefault();
  if(f.dataset.confirm!==undefined&&!confirm(f.dataset.confirm))return;
  var fd=new FormData(f);fd.set('ajax','1');
  var btn=f.querySelector('button');if(btn){btn.disabled=true;}
  fetch(location.href,{method:'POST',body:fd,headers:{'X-Requested-With':'fetch'}}).then(function(r){return r.json()}).then(function(d){
    toast(d.msg,d.ok);
    if(btn)btn.disabled=false;
    if(!d.ok)return;
    if(f.dataset.decor!==undefined){var box=f.closest('.seasons');if(box){box.querySelectorAll('button.season').forEach(function(b){b.classList.remove('active');b.disabled=false;});if(btn){btn.classList.add('active');btn.disabled=true;}}}
    else if(f.dataset.toggle!==undefined){var rid=f.dataset.toggle;var bg=document.getElementById('st'+rid);if(bg){var on=bg.classList.contains('green');bg.className='rk '+(on?'red':'green');bg.textContent=on?'Désactivée':'Active';if(btn){btn.textContent=on?'Activer':'Désactiver';btn.className='mini '+(on?'ok':'warn');}}}
    else if(f.dataset.reload!==undefined){setTimeout(function(){location.reload()},700);}
  }).catch(function(){toast('Erreur réseau',false);if(btn)btn.disabled=false;});
});
function filt(inp,tblId){var q=inp.value.toLowerCase();var t=document.getElementById(tblId);if(!t)return;t.querySelectorAll('tr.frow').forEach(function(r){var txt=r.textContent.toLowerCase();r.querySelectorAll('input').forEach(function(i){txt+=' '+(i.value||'').toLowerCase();});r.style.display=txt.indexOf(q)>-1?'':'none';var nx=r.nextElementSibling;if(nx&&nx.classList.contains('drawer'))nx.style.display='none';});}
</script></body></html><?php
}
function render_login(?string $err): void {
    $t = THEMES[current_theme_key()]; $acc = $t[7];
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Habbo · Connexion</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"><style>
body{margin:0;font:14px/1.5 'Plus Jakarta Sans',system-ui,Segoe UI,sans-serif;background:<?= $t[3] ?>;color:<?= $t[8] ?>;display:grid;place-items:center;height:100vh}
.box{background:<?= $t[4] ?>;border:1px solid <?= $t[11] ?>;border-radius:20px;padding:34px;width:350px;box-shadow:0 24px 70px rgba(0,0,0,.18)}
h1{color:<?= $acc ?>;margin:0 0 4px;font-size:23px}.m{color:<?= $t[9] ?>;font-size:13px;margin:0 0 22px}
input{width:100%;background:<?= $t[3] ?>;border:1px solid <?= $t[11] ?>;color:<?= $t[8] ?>;border-radius:11px;padding:12px;margin:6px 0}
button{width:100%;background:<?= $acc ?>;color:#fff;border:0;border-radius:11px;padding:13px;font-weight:800;cursor:pointer;margin-top:12px}
.err{background:#fde8e8;border:1px solid #f5b5b5;color:#a33;padding:10px 12px;border-radius:10px;font-size:13px;margin-bottom:10px}
</style></head><body><form class="box" method="post"><h1>Habbo · Admin</h1><p class="m">Housekeeping — connexion (rang 5+)</p>
<?php if ($err) echo '<div class="err">' . h($err) . '</div>'; ?>
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input name="username" placeholder="Nom Habbo" autofocus required>
<input name="password" type="password" placeholder="Mot de passe" required><button>Se connecter</button></form></body></html><?php
}
