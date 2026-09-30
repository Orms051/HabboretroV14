<?php
/**
 * HabboretroV14 — Panneau d'administration PRO (housekeeping)
 * Kepler v14 · PHP 8.3 (Laragon) · base v14 sur 127.0.0.1:3306
 * Accès : http://localhost/admin/  — rang >= 5 requis.
 */
declare(strict_types=1);
session_start();
mb_internal_encoding('UTF-8');

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'v14';
const DB_USER = 'root';
const DB_PASS = '';
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
function csrf_check(): void { if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(400); exit('CSRF invalide.'); } }
function flash(?string $m = null): ?string { if ($m !== null) { $_SESSION['flash'] = $m; return null; } $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function require_login(): void { if (empty($_SESSION['admin'])) { header('Location: /?p=login'); exit; } }
function make_hash(string $pw): string { return password_hash($pw, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 2, 'threads' => 1]); }
function redirect(string $to): void { header('Location: ' . $to); exit; }
function is_ajax(): bool { return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') || !empty($_POST['ajax']); }
function cur_page(): int { return max(1, (int)($_GET['pg'] ?? 1)); }
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
if ($p === 'logout') { session_destroy(); redirect('?p=login'); }
if ($p === 'login') {
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $st = db()->prepare('SELECT id,username,password,rank FROM users WHERE username=?'); $st->execute([trim($_POST['username'] ?? '')]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password'])) {
            if ((int)$u['rank'] >= MIN_RANK) { $_SESSION['admin'] = ['id' => $u['id'], 'username' => $u['username'], 'rank' => (int)$u['rank']]; redirect('?p=dashboard'); }
            $err = "Ce compte n'a pas les droits (rang " . MIN_RANK . "+ requis).";
        } else $err = 'Nom ou mot de passe incorrect.';
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

/* ---------- Connexion « en tant que » un joueur (item 1) ---------- */
if (isset($_GET['loginas']) && (int)($_SESSION['admin']['rank'] ?? 0) >= 7) {
    $uid = (int)$_GET['loginas'];
    $st = db()->prepare('SELECT username FROM users WHERE id=?'); $st->execute([$uid]); $uname = $st->fetchColumn();
    if ($uname !== false) {
        $ticket = bin2hex(random_bytes(16));
        db()->prepare('UPDATE users SET sso_ticket=? WHERE id=?')->execute([$ticket, $uid]);
        admin_log('login_as', 'Connexion en tant que ' . $uname . ' (#' . $uid . ')');
        header('Location: /client.php?sso=' . $ticket);
        exit;
    }
}

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $a = $_POST['action'] ?? ''; $back = $_POST['back'] ?? ('?p=' . $p); $ok = true; $msg = '';
    /* ---- Contrôle du droit PAR action, AVANT exécution (ferme le contournement POST) ---- */
    $reqTab = action_tab($a);
    if ($a !== '' && $reqTab === '') $reqTab = $p; // action non cartographiée : au moins l'onglet courant
    if ($reqTab !== '' && !tab_allowed($reqTab)) {
        admin_log('denied', 'Action refusée: ' . $a . ' (onglet ' . $reqTab . ', rang ' . (int)($_SESSION['admin']['rank'] ?? 0) . ')');
        $deny = '🔒 Accès refusé : ton rang n\'autorise pas cette action.';
        if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $deny]); exit; }
        flash($deny); redirect($back);
    }
    try {
        switch ($a) {
            case 'user_update':
                $uid = (int)$_POST['id']; $nr = max(1, min(7, (int)$_POST['rank'])); $nc = (int)$_POST['credits']; $nm = trim($_POST['motto'] ?? '');
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
                db()->prepare('UPDATE users SET rank=? WHERE id=?')->execute([max(1, min(7, (int)$_POST['rank'])), (int)$_POST['id']]);
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
                db()->prepare('INSERT INTO users (username,password,rank,credits,email,birthday,motto,sex) VALUES (?,?,?,?,?,?,?,?)')->execute([$u, make_hash($np), max(1, min(7, (int)($_POST['rank'] ?? 1))), 100, $u . '@retro14.local', $bd, 'Nouveau Habbo', 'M']);
                $msg = '✅ Compte "' . $u . '" créé.'; break;
            case 'user_credits':
                db()->prepare('UPDATE users SET credits = credits + ? WHERE id=?')->execute([(int)$_POST['delta'], (int)$_POST['id']]);
                $msg = '💰 ' . ((int)$_POST['delta'] >= 0 ? '+' : '') . (int)$_POST['delta'] . ' crédits.'; break;
            case 'user_hc':
                $days = (int)$_POST['days']; $now = time() * 1000;
                if ($days > 0) db()->prepare('UPDATE users SET club_subscribed=?, club_expiration=? WHERE id=?')->execute([$now, $now + $days * 86400000, (int)$_POST['id']]);
                else db()->prepare('UPDATE users SET club_subscribed=0, club_expiration=0 WHERE id=?')->execute([(int)$_POST['id']]);
                $msg = $days > 0 ? '⭐ Club Habbo accordé (' . $days . ' j).' : '⭐ Club Habbo retiré.'; break;
            case 'user_delete':
                $uid = (int)$_POST['id'];
                foreach ([
                    'DELETE FROM messenger_requests WHERE from_id=? OR to_id=?',
                    'DELETE FROM messenger_friends WHERE from_id=? OR to_id=?',
                    'DELETE FROM users_badges WHERE user_id=?',
                    'DELETE FROM users_bans WHERE ban_type=\'USER_ID\' AND banned_value=?',
                    'DELETE FROM users WHERE id=?',
                ] as $sql) {
                    $params = substr_count($sql, '?') === 2 ? [$uid, $uid] : [$uid];
                    try { db()->prepare($sql)->execute($params); } catch (Throwable $e) {}
                }
                $msg = '🗑️ Compte supprimé (et ses demandes/amis/badges nettoyés).'; break;
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
                    ->execute([substr(trim($_POST['title'] ?? ''), 0, 150), substr(trim($_POST['category'] ?? 'À la une'), 0, 40), $col, trim($_POST['body'] ?? ''), (string)$_SESSION['admin']['username']]);
                $msg = '📰 Actualité publiée.'; break;
            case 'news_update':
                ensure_news();
                $col = in_array($_POST['color'] ?? '', ['blue', 'green', 'purple', 'orange'], true) ? $_POST['color'] : 'blue';
                db()->prepare('UPDATE site_news SET title=?, category=?, color=?, body=? WHERE id=?')
                    ->execute([substr(trim($_POST['title'] ?? ''), 0, 150), substr(trim($_POST['category'] ?? 'À la une'), 0, 40), $col, trim($_POST['body'] ?? ''), (int)$_POST['id']]);
                $msg = '✅ Actualité mise à jour.'; break;
            case 'news_delete':
                ensure_news();
                db()->prepare('DELETE FROM site_news WHERE id=?')->execute([(int)$_POST['id']]); $msg = '🗑️ Actualité supprimée.'; break;
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
                db()->prepare('UPDATE items_definitions SET name=?,description=?,behaviour=?,is_tradable=?,is_recyclable=?,length=?,width=? WHERE id=?')
                    ->execute([substr(trim($_POST['name'] ?? ''), 0, 255), substr(trim($_POST['description'] ?? ''), 0, 255), substr(trim($_POST['behaviour'] ?? ''), 0, 150), isset($_POST['is_tradable']) ? 1 : 0, isset($_POST['is_recyclable']) ? 1 : 0, (int)$_POST['length'], (int)$_POST['width'], (int)$_POST['id']]); $msg = '🪑 Meuble mis à jour.'; break;
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
            case 'maint_on':
                $mj = ['on' => true, 'message' => mb_substr(trim((string)($_POST['message'] ?? '')), 0, 300), 'eta' => mb_substr(trim((string)($_POST['eta'] ?? '')), 0, 60), 'by' => (string)($_SESSION['admin']['username'] ?? ''), 'at' => date('c')];
                file_put_contents(dirname(__DIR__) . '/maintenance.json', json_encode($mj, JSON_UNESCAPED_UNICODE));
                $msg = '🚧 Mode maintenance ACTIVÉ — le site public affiche la page de fermeture (le staff garde l\'accès).'; break;
            case 'maint_off':
                @unlink(dirname(__DIR__) . '/maintenance.json');
                $msg = '✅ Mode maintenance désactivé — le site est de nouveau ouvert.'; break;
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
                $params = ['message' => $txt];
                if (trim((string)($_POST['sender'] ?? '')) !== '') $params['sender'] = trim((string)$_POST['sender']);
                $ok2 = rcon_send('hotel_alert', $params);
                $ok = $ok2; $msg = $ok2 ? '📢 Alerte envoyée à tous les joueurs connectés.' : '❌ Émulateur injoignable (RCON). Est-il démarré ?'; break;
            case 'db_backup':
                $ok = db_backup(); $msg = $ok ? '💾 Sauvegarde créée.' : '❌ Échec de la sauvegarde.'; break;
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
                $chk = db()->prepare('SELECT id FROM items_definitions WHERE id=?'); $chk->execute([$def]);
                if (!$chk->fetch()) { $ok = false; $msg = 'Définition de meuble introuvable.'; break; }
                db()->prepare("INSERT INTO items (user_id,room_id,definition_id,x,y,z,wall_position,custom_data) SELECT id,0,?,0,0,0,'','' FROM users")->execute([$def]);
                $msg = '🎁 Meuble distribué à tous (dans leur main). Ils doivent se reconnecter.'; break;
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
            default: $ok = false; $msg = 'Action inconnue.';
        }
    } catch (Throwable $e) { $ok = false; $msg = '❌ Erreur : ' . $e->getMessage(); }
    if ($ok && !in_array($a, ['note_add', 'note_del'], true)) admin_log($a, strip_tags($msg));
    if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok' => $ok, 'msg' => $msg]); exit; }
    flash($msg); redirect($back);
}

/* ---------- Contrôle d'accès aux onglets par rang ---------- */
if (!tab_allowed($p)) { flash('🔒 Ton rang n\'a pas accès à « ' . $p . ' ».'); $p = 'dashboard'; }

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
    default: page_dashboard(); break;
}
render_footer();

/* ============ ACCÈS AUX ONGLETS PAR RANG ============ */
function admin_nav(): array {
    return ['dashboard' => ['🏠', 'Accueil'], 'search' => ['🔍', 'Recherche'], 'news' => ['📰', 'Actualités'], 'rooms' => ['🏛️', 'Salles & décors'], 'models' => ['🏗️', 'Modèles de salles'], 'navcats' => ['🧭', 'Catégories navigateur'], 'catalogue' => ['🛋️', 'Catalogue'], 'packages' => ['📦', 'Packs catalogue'], 'furni' => ['🪑', 'Meubles (défs)'], 'convert' => ['🔧', 'Convertir furni'], 'bots' => ['🤖', 'Bots'], 'trax' => ['🎵', 'Trax'], 'users' => ['👥', 'Joueurs'], 'badges' => ['📛', 'Badges'], 'ranks' => ['🎖️', 'Rangs'], 'games' => ['🎮', 'Jeux'], 'gamemaps' => ['🗺️', 'Cartes de jeux'], 'events' => ['🎉', 'Événements'], 'recycler' => ['♻️', 'Recycleur'], 'vouchers' => ['🎁', 'Codes promo'], 'moderation' => ['🚫', 'Modération'], 'bus' => ['🚌', 'Bus (Infobus)'], 'commandes' => ['⌨️', 'Commandes en jeu'], 'textes' => ['💬', 'Textes du jeu'], 'settings' => ['⚙️', 'Réglages'], 'access' => ['🔒', 'Accès admin'], 'audit' => ['📜', 'Journal admin'], 'mysql' => ['🗄️', 'Base MySQL'], 'server' => ['🖥️', 'Serveur']];
}
function tab_default_rank(string $tab): int {
    $d = ['mysql' => 7, 'server' => 7, 'settings' => 7, 'ranks' => 7, 'textes' => 7, 'access' => 7, 'audit' => 7, 'convert' => 7];
    return $d[$tab] ?? MIN_RANK;
}
function nav_groups(): array {
    return [
        '' => ['dashboard', 'search'],
        'Joueurs & modération' => ['users', 'badges', 'ranks', 'moderation', 'audit', 'commandes'],
        'Catalogue & mobis' => ['catalogue', 'navcats', 'packages', 'furni', 'convert', 'trax'],
        'Hôtel & animations' => ['rooms', 'models', 'bots', 'games', 'gamemaps', 'events', 'recycler', 'vouchers', 'bus'],
        'Site & contenus' => ['news', 'textes'],
        'Administration' => ['settings', 'access', 'mysql', 'server'],
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
/* Carte action POST -> onglet propriétaire (le plus PERMISSIF où le bouton est exposé),
   pour contrôler le droit AVANT exécution sans jamais verrouiller un accès existant. */
function action_tab(string $a): string {
    static $map = [
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
        // Catalogue & mobis
        'cat_item' => 'catalogue', 'cat_page' => 'catalogue', 'cat_page_move' => 'catalogue', 'cat_bulk' => 'catalogue',
        'navcat_update' => 'navcats', 'navcat_move' => 'navcats',
        'pkg_add' => 'packages', 'pkg_update' => 'packages', 'pkg_delete' => 'packages',
        'def_update' => 'furni', 'furni_all' => 'furni', 'song_update' => 'trax', 'song_delete' => 'trax',
        'furni_convert' => 'convert', 'furni_decompile' => 'convert',
        // Communauté / modération
        'news_add' => 'news', 'news_update' => 'news', 'news_delete' => 'news',
        'ban_add' => 'moderation', 'ban_remove' => 'moderation', 'bus_type' => 'bus',
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
/* ---- Notes staff sur un joueur ---- */
function ensure_admin_notes(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS admin_notes (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, author VARCHAR(64) NOT NULL, note VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
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
    ensure_news();
    $colors = ['blue' => 'Bleu', 'green' => 'Vert', 'purple' => 'Violet', 'orange' => 'Orange'];
    $edit = null;
    if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM site_news WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null; }
    $rows = db()->query('SELECT * FROM site_news ORDER BY created_at DESC, id DESC')->fetchAll();

    // Formulaire (ajout ou édition)
    echo '<div class="panel"><div class="ph"><h3>' . ($edit ? '✏️ Modifier l\'actualité' : '📰 Nouvelle actualité') . '</h3>';
    if ($edit) echo '<a class="lnk" href="?p=news">+ Nouvelle</a>';
    echo '</div><form method="post" class="js" data-reload>' . csrf_field();
    echo '<input type="hidden" name="action" value="' . ($edit ? 'news_update' : 'news_add') . '">';
    if ($edit) echo '<input type="hidden" name="id" value="' . (int)$edit['id'] . '">';
    echo '<div class="row">';
    echo '<label style="flex:2">Titre<input name="title" maxlength="150" required value="' . h($edit['title'] ?? '') . '"></label>';
    echo '<label style="flex:1">Étiquette<input name="category" maxlength="40" placeholder="À la une, Astuce…" value="' . h($edit['category'] ?? 'À la une') . '"></label>';
    echo '<label>Couleur<select name="color">';
    foreach ($colors as $k => $lbl) echo '<option value="' . $k . '"' . (($edit['color'] ?? 'blue') === $k ? ' selected' : '') . '>' . $lbl . '</option>';
    echo '</select></label>';
    echo '</div>';
    echo '<label style="margin-top:10px">Texte<textarea name="body" rows="4" required>' . h($edit['body'] ?? '') . '</textarea></label>';
    echo '<button style="margin-top:12px">' . ($edit ? '💾 Enregistrer' : '📢 Publier') . '</button>';
    echo '</form></div>';

    // Liste
    echo '<div class="panel"><div class="ph"><h3>🗂️ Actualités publiées (' . count($rows) . ')</h3></div>';
    if (!$rows) echo '<p class="muted sm">Aucune actualité. Publie la première ci-dessus — elle apparaîtra sur le site.</p>';
    else {
        echo '<table class="clean"><thead><tr><th>Date</th><th>Étiquette</th><th>Titre</th><th>Auteur</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td class="sm">' . h(date('d/m/Y H:i', strtotime((string)$r['created_at']))) . '</td>';
            echo '<td><span class="tag ' . h($r['color']) . '">' . h($r['category']) . '</span></td>';
            echo '<td><b>' . h($r['title']) . '</b></td>';
            echo '<td class="sm">' . h($r['author']) . '</td>';
            echo '<td class="ta-r"><a class="mini ghost" href="?p=news&edit=' . (int)$r['id'] . '">Éditer</a> ';
            echo '<form method="post" class="js" data-reload data-confirm="Supprimer cette actualité ?" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="news_delete"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="mini warn">Suppr.</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
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

function page_furni(): void {
    page_title('Meubles (définitions)', 'Toutes les définitions de mobis — réglages avancés');
    echo '<details class="panel"><summary><b>🎁 Donner un meuble à TOUS les joueurs</b></summary>';
    echo '<p class="muted sm">Le meuble arrive dans la <b>main</b> de chaque joueur (reconnexion requise). Utilise le <b>#ID</b> de la définition (colonne « # » ci-dessous).</p>';
    echo '<form method="post" class="js row" data-reload data-confirm="Distribuer ce meuble à TOUS les joueurs ?">' . csrf_field() . '<input type="hidden" name="action" value="furni_all"><label>ID définition<input type="number" name="definition_id" placeholder="ex. 42" required style="width:120px"></label><button>🎁 Distribuer à tous</button></form></details>';
    if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM items_definitions WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch() ?: null;
        if ($edit) {
            echo '<div class="panel"><div class="ph"><h3>' . furni_icon_img((string)$edit['sprite'], 48) . ' ' . h($edit['name']) . ' (#' . (int)$edit['id'] . ')</h3><a class="lnk" href="?p=furni">← Retour</a></div><form method="post" class="js" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="def_update"><input type="hidden" name="id" value="' . (int)$edit['id'] . '">';
            echo '<div class="row"><label style="flex:1">Nom<input name="name" value="' . h($edit['name']) . '"></label><label>Longueur<input type="number" name="length" value="' . (int)$edit['length'] . '" style="width:90px"></label><label>Largeur<input type="number" name="width" value="' . (int)$edit['width'] . '" style="width:90px"></label></div>';
            echo '<label style="margin-top:8px">Description<input name="description" value="' . h($edit['description']) . '"></label>';
            echo '<label style="margin-top:8px">Comportement (behaviour)<input name="behaviour" value="' . h($edit['behaviour']) . '"></label>';
            echo '<div class="row" style="margin-top:8px"><label style="flex-direction:row;align-items:center"><input type="checkbox" name="is_tradable" ' . ($edit['is_tradable'] ? 'checked' : '') . '> Échangeable</label><label style="flex-direction:row;align-items:center"><input type="checkbox" name="is_recyclable" ' . ($edit['is_recyclable'] ? 'checked' : '') . '> Recyclable</label></div>';
            echo '<button style="margin-top:10px">💾 Enregistrer</button></form><p class="hint">sprite: ' . h((string)$edit['sprite']) . ' · sprite_id: ' . (int)$edit['sprite_id'] . '</p></div>';
            return;
        }
    }
    $q = trim($_GET['q'] ?? '');
    $pg = max(1, (int)($_GET['pg'] ?? 1)); $off = ($pg - 1) * PER_PAGE;
    echo '<div class="panel"><div class="ph"><h3>🪑 Meubles</h3><form method="get" class="srch"><input type="hidden" name="p" value="furni"><input name="q" value="' . h($q) . '" placeholder="🔍 Nom ou sprite..."><button class="mini">OK</button></form></div>';
    if ($q !== '') {
        $like = '%' . $q . '%';
        $tc = db()->prepare('SELECT COUNT(*) FROM items_definitions WHERE name LIKE ? OR sprite LIKE ?'); $tc->execute([$like, $like]); $tot = (int)$tc->fetchColumn();
        $st = db()->prepare('SELECT id,sprite,name,length,width,behaviour FROM items_definitions WHERE name LIKE ? OR sprite LIKE ? ORDER BY name LIMIT ' . PER_PAGE . ' OFFSET ' . $off); $st->execute([$like, $like]); $rows = $st->fetchAll();
    } else {
        $tot = (int)db()->query('SELECT COUNT(*) FROM items_definitions')->fetchColumn();
        $rows = db()->query('SELECT id,sprite,name,length,width,behaviour FROM items_definitions ORDER BY id LIMIT ' . PER_PAGE . ' OFFSET ' . $off)->fetchAll();
    }
    echo '<table class="clean"><tr><th>#</th><th></th><th>Nom</th><th>Sprite</th><th>Taille</th><th>Comportement</th><th></th></tr>';
    foreach ($rows as $r) echo '<tr><td>' . (int)$r['id'] . '</td><td>' . furni_icon_img((string)$r['sprite']) . '</td><td><b>' . h($r['name']) . '</b></td><td class="sm muted">' . h((string)$r['sprite']) . '</td><td class="sm">' . (int)$r['length'] . '×' . (int)$r['width'] . '</td><td class="sm muted">' . h((string)$r['behaviour']) . '</td><td><a class="mini ghost" href="?p=furni&edit=' . (int)$r['id'] . '">Éditer</a></td></tr>';
    echo '</table>';
    pager($tot, $pg, '?p=furni' . ($q !== '' ? '&q=' . urlencode($q) : ''));
    echo '</div>';
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
    echo '<a class="qabtn" href="?p=news">📰<span>Publier une actualité</span></a>';
    echo '<a class="qabtn" href="?p=server">📢<span>Alerte à l\'hôtel</span></a>';
    echo '<a class="qabtn" href="?p=server">💾<span>Sauvegarder la base</span></a>';
    echo '</div>';
    echo '<form method="post" class="js" data-reload data-confirm="Donner des crédits à TOUS les joueurs ?" style="margin-top:12px;display:flex;align-items:flex-end">' . csrf_field() . '<input type="hidden" name="action" value="credits_all"><label style="flex:1">💰 Crédits à tous les joueurs<input type="number" name="delta" value="500"></label><button class="mini" style="margin-left:8px">Distribuer</button></form>';
    echo '</div></div>';

    /* --- Journal admin + bannissements --- */
    echo '<div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>📜 Dernières actions admin</h3><a class="lnk" href="?p=audit">Voir tout →</a></div>';
    try {
        ensure_admin_log();
        $logs = $d->query('SELECT author,action,detail,created_at FROM admin_log ORDER BY id DESC LIMIT 8')->fetchAll();
        if (!$logs) echo '<div class="empty">Aucune action enregistrée.</div>';
        else { echo '<table class="clean"><tr><th>Quand</th><th>Qui</th><th>Action</th></tr>'; foreach ($logs as $l) echo '<tr><td class="sm muted" style="white-space:nowrap">' . h(date('d/m H:i', strtotime((string)$l['created_at']))) . '</td><td class="sm"><b>' . h($l['author']) . '</b></td><td class="sm">' . h(($l['detail'] ?? '') !== '' ? $l['detail'] : $l['action']) . '</td></tr>'; echo '</table>'; }
    } catch (Throwable $e) { echo '<div class="empty">Journal indisponible.</div>'; }
    echo '</div>';
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

    echo '<div class="panel"><p class="muted sm">Cet outil récupère le fichier Flash officiel d\'un meuble depuis <code>images.habbo.com</code>, le convertit en <code>.cct</code> (format de ton client) et l\'installe dans <code>hof_furni</code>. Idéal pour réparer un meuble qui apparaît en <b>cube</b>.</p>';
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
    echo '<details class="panel"' . (count($cubes) ? '' : ' ') . '><summary><b>🧊 Meubles en cube actuellement (' . count($cubes) . ')</b> — graphisme .cct manquant</summary>';
    if ($cubes) { echo '<p class="muted sm">Ces meubles sont définis mais n\'ont pas de graphisme. Cherche leur sprite sur <a href="https://images.habbo.com" target="_blank">images.habbo.com</a> et convertis-les ci-dessous.</p><div style="font-family:var(--mono);font-size:12px;line-height:1.8">' . h(implode('  ·  ', array_keys($cubes))) . '</div>'; }
    else { echo '<div class="empty">Aucun cube — tous les meubles définis ont leur graphisme ! 🎉</div>'; }
    echo '</details>';

    // Formulaire de conversion
    echo '<div class="panel"><div class="ph"><h3>Convertir un meuble</h3></div>';
    echo '<form method="post" class="js" data-reload data-confirm="Lancer la conversion ? (quelques secondes)">' . csrf_field() . '<input type="hidden" name="action" value="furni_convert">';
    echo '<div class="row"><label style="flex:1">Sprite (nom exact du meuble)<input name="sprite" placeholder="ex : exe_wfall" required></label><label>Révision<input name="revision" placeholder="ex : 56746" required style="width:120px"></label></div>';
    echo '<div class="row" style="margin-top:8px"><label>Type<select name="ftype"><option value="s">Sol (au sol)</option><option value="i">Mural (au mur)</option></select></label><label>Longueur<input type="number" name="len" value="1" style="width:80px"></label><label>Largeur<input type="number" name="wid" value="1" style="width:80px"></label><label style="flex-direction:row;align-items:center;margin-top:20px"><input type="checkbox" name="mkdef"> Créer la définition si absente</label></div>';
    echo '<button style="margin-top:12px">🔧 Convertir & installer</button></form>';
    echo '<p class="hint">💡 La <b>révision</b> se trouve dans la furnidata de Habbo. URL testée : <code>https://images.habbo.com/dcr/hof_furni/&lt;révision&gt;/&lt;sprite&gt;.swf</code><br>⚠️ Après conversion : <b>vide le cache de Basilisk</b> pour voir le meuble. Certains meubles animés spéciaux (téléporteurs) s\'affichent mais gardent leurs limites de comportement côté client.</p></div>';

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
    $hcActive = (int)$u['club_expiration'] > time() * 1000;
    $hc = $hcActive ? ('actif jusqu\'au ' . date('d/m/Y', (int)($u['club_expiration'] / 1000))) : 'non membre';
    $bs = db()->prepare("SELECT message,banned_until FROM users_bans WHERE ban_type='USER_ID' AND banned_value=?"); $bs->execute([(string)$id]); $ban = $bs->fetch(); $isBan = (bool)$ban;
    $rb = []; foreach (db()->query('SELECT badge FROM rank_badges WHERE rank=' . (int)$u['rank']) as $r) $rb[] = $r['badge'];
    $ob = []; $obs = db()->prepare('SELECT badge FROM users_badges WHERE user_id=?'); $obs->execute([$id]); foreach ($obs as $r) $ob[] = $r['badge'];

    echo '<div style="margin-bottom:10px"><a class="mini ghost lnkbtn" href="?p=users">← Retour aux joueurs</a></div>';
    page_title('👤 ' . $u['username'], 'Fiche complète du joueur · ID ' . $id);
    echo '<div style="margin:-10px 0 16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . rank_badge((int)$u['rank']) . ($isBan ? '<span class="rk red">🚫 Banni</span>' : '<span class="rk green">Compte actif</span>');
    if ((int)($_SESSION['admin']['rank'] ?? 0) >= 7) echo '<a class="mini ghost lnkbtn" href="?loginas=' . $id . '" target="_blank" style="margin-left:auto" title="Ouvre le jeu connecté en tant que ce joueur (nouvel onglet)">🎭 Se connecter en tant que…</a>';
    echo '</div>';

    echo '<div class="grid">';
    stat_box('💰', number_format((int)$u['credits'], 0, ',', ' '), 'Crédits', 'gold');
    stat_box('🎟️', (int)$u['tickets'], 'Tickets', 'blue');
    stat_box('⭐', $hcActive ? 'Oui' : 'Non', 'Club Habbo', 'gold');
    stat_box('📅', $created, 'Inscrit le', 'blue');
    stat_box('🕑', $seen, 'Vu', 'green');
    echo '</div>';

    echo '<div class="cols">';
    echo '<div class="panel"><div class="ph"><h3>✏️ Modifier</h3></div>
        <form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="user_update"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '">
        <label>Rang<select name="rank">' . rank_options((int)$u['rank']) . '</select></label>
        <label>Crédits<input type="number" name="credits" value="' . (int)$u['credits'] . '" style="width:120px"></label>
        <label style="flex:1">Motto<input name="motto" value="' . h($u['motto']) . '"></label><button>💾</button></form>
        <form method="post" class="js row" style="margin-top:8px" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="user_password"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '">
        <label style="flex:1">🔑 Nouveau mot de passe<input name="newpass"></label><button class="ghost">Changer</button></form>
        <div class="row" style="margin-top:8px;gap:6px">' . uquick($id, 'user_credits', 'delta', 1000, '💰 +1 000') . uquick($id, 'user_credits', 'delta', 10000, '💰 +10 000') . uquick($id, 'user_hc', 'days', 30, '⭐ HC 30j') . uquick($id, 'user_hc', 'days', 0, '⭐ Retirer HC') . '</div>
        <div class="row" style="margin-top:6px;gap:6px;align-items:center"><span class="muted sm">Rang rapide :</span>' . uquick($id, 'user_rank', 'rank', 1, '👤 Joueur') . uquick($id, 'user_rank', 'rank', 6, '🛡️ Modérateur') . uquick($id, 'user_rank', 'rank', 7, '⭐ Admin') . '<form method="post" class="js" data-reload data-confirm="Vider la main (objets non posés) de ' . h($u['username']) . ' ?" style="display:inline">' . csrf_field() . '<input type="hidden" name="action" value="clear_hand"><input type="hidden" name="username" value="' . h($u['username']) . '"><input type="hidden" name="back" value="' . h($back) . '"><button class="mini ghost">🎒 Vider la main</button></form></div></div>';
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

    echo '<div class="panel"><div class="ph"><h3>📛 Badges</h3></div>
        <p class="muted sm">🎖️ De rang (automatiques) :</p><div class="badgegrid" style="margin-bottom:10px">' . rank_badges_html($rb) . '</div>
        <p class="muted sm">📛 Possédés :</p><div class="badgegrid">' . user_badges_html($id, $ob) . '</div>
        <form method="post" class="js row" data-reload style="margin-top:10px">' . csrf_field() . '<input type="hidden" name="action" value="badge_add"><input type="hidden" name="username" value="' . h($u['username']) . '"><input type="hidden" name="back" value="' . h($back) . '"><label>Ajouter un badge<input name="badge" maxlength="3" style="width:90px;text-transform:uppercase"></label><button>+ Ajouter</button><a class="mini ghost lnkbtn" href="?p=badges" style="align-self:flex-end">Galerie</a></form></div>';

    echo '<div class="cols">';
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

    echo '<div class="panel"><div class="ph"><h3>🚫 Modération</h3></div>';
    if ($isBan) echo '<p>🚫 Banni : <b>' . h($ban['message']) . '</b> (' . ((int)$ban['banned_until'] >= BAN_PERMANENT ? '♾️ définitif' : 'jusqu\'au ' . date('d/m/Y', (int)($ban['banned_until'] / 1000))) . ') <form method="post" class="js" data-reload style="display:inline"><input type="hidden" name="action" value="ban_remove">' . csrf_field() . '<input type="hidden" name="value" value="' . $id . '"><input type="hidden" name="back" value="' . h($back) . '"><button class="mini ghost">Lever le ban</button></form></p>';
    else echo '<form method="post" class="js row" data-reload><input type="hidden" name="action" value="ban_add"><input type="hidden" name="type" value="USER_ID"><input type="hidden" name="value" value="' . h($u['username']) . '"><input type="hidden" name="back" value="' . h($back) . '">' . csrf_field() . '<label>Durée (j · 0 = définitif)<input type="number" name="days" value="0" style="width:120px"></label><label style="flex:1">Motif<input name="message" value="Comportement inapproprié"></label><button style="background:var(--red)">🚫 Bannir</button></form>';
    echo '<form method="post" class="js" data-reload data-confirm="Supprimer définitivement ' . h($u['username']) . ' ?" style="margin-top:10px"><input type="hidden" name="action" value="user_delete"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="?p=users">' . csrf_field() . '<button class="mini" style="background:var(--red)">🗑️ Supprimer ce compte</button></form>';
    echo '</div>';
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
        <span class="muted sm" style="align-self:center">Rangs 1→6 (6 = Admin). Kepler n\'a pas de rang « HabboX ».</span></form></div>';

    echo '<div class="panel"><div class="ph"><h3>👥 Comptes</h3><form method="get" class="srch"><input type="hidden" name="p" value="users"><input name="q" value="' . h($q) . '" placeholder="🔍 Nom du joueur..."><button class="mini">OK</button></form></div>';
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
            <td class="muted">' . (int)$u['id'] . '</td><td><a class="ulink" href="?p=user&id=' . (int)$u['id'] . '">' . h($u['username']) . '</a><div class="muted sm">' . h($u['motto']) . '</div></td>
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
        <label style="flex:1">Joueur<input name="username" value="' . h($u) . '" required></label>
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

    echo '<div class="panel"><div class="ph"><h3>👤 Badges d\'un joueur</h3><form method="get" class="srch"><input type="hidden" name="p" value="badges"><input name="u" value="' . h($u) . '" placeholder="Nom du joueur..."><button class="mini">Voir</button></form></div>';
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
    echo '<div class="warn">Kepler gère les rangs <b>1 à 6</b> (6 = Administrateur, le maximum). Il n\'existe pas de rang « HabboX » séparé dans cet émulateur.</div>';
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

function page_moderation(): void {
    page_title('Modération', 'Bannissements et journal des actions');
    $prefill = trim($_GET['ban'] ?? '');
    echo '<div class="panel"><div class="ph"><h3>🚫 Bannir</h3></div><form method="post" class="js row" data-reload>' . csrf_field() . '<input type="hidden" name="action" value="ban_add">
        <label>Type<select name="type"><option value="USER_ID">Joueur (nom)</option><option value="IP_ADDRESS">Adresse IP</option><option value="MACHINE_ID">Machine ID</option></select></label>
        <label style="flex:1">Cible (nom ou IP)<input name="value" value="' . h($prefill) . '" required></label>
        <label>Durée (jours · 0 = définitif)<input type="number" name="days" value="0" style="width:110px"></label>
        <label style="flex:1">Motif<input name="message" value="Comportement inapproprié"></label><button>🚫 Bannir</button></form></div>';
    echo '<div class="panel"><div class="ph"><h3>Bannissements actifs</h3></div>';
    $rows = db()->query('SELECT b.ban_type,b.banned_value,b.message,b.banned_until,u.username FROM users_bans b LEFT JOIN users u ON (b.ban_type=\'USER_ID\' AND u.id=b.banned_value) ORDER BY b.banned_until DESC')->fetchAll();
    if (!$rows) echo '<div class="empty">Aucun bannissement 🎉</div>';
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
    echo '<div class="panel"><div class="ph"><h3>💬 Logs de chat</h3><form method="get" class="srch"><input type="hidden" name="p" value="moderation"><input name="cu" value="' . h($cu) . '" placeholder="🔍 Filtrer par joueur..."><button class="mini">OK</button></form></div>';
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
    echo '<p class="muted sm">Objet coincé dans la main (jukebox, etc.) impossible à poser/supprimer ? Vide la « main » du joueur. Il devra <b>se reconnecter</b> ensuite.</p>';
    echo '<form method="post" class="js row" data-reload data-confirm="Vider les objets en main de ce joueur ?"><input type="hidden" name="action" value="clear_hand">' . csrf_field() . '<label style="flex:1">Nom du joueur<input name="username" required></label><button class="mini warn">🧹 Vider la main</button></form></div>';

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

function page_commandes(): void {
    page_title('Commandes en jeu', 'Toutes les commandes chat du kepler.jar');
    echo '<p class="hint" style="font-size:13px">À taper dans le chat, précédées de <b>:</b> (deux-points). Réservées aux comptes <b>administrateur</b> (sauf indication).</p>';
    $grp = [
        '🎁 Joueurs / crédits' => [
            [':givecredits &lt;user&gt; &lt;montant&gt;', 'Donne des crédits'],
            [':givebadge &lt;user&gt; &lt;badge&gt;', 'Attribue un badge'],
            [':giveclub &lt;user&gt; &lt;jours&gt;', 'Ajoute des jours de Habbo Club'],
            [':giveitem &lt;user&gt; &lt;sprite&gt;', 'Donne un meuble dans la main'],
            [':givedrink &lt;n°&gt;', 'Donne une boisson au joueur ciblé'],
        ],
        '🛡️ Modération / système' => [
            [':hotelalert &lt;message&gt;', 'Alerte générale à tout l\'hôtel'],
            [':usersonline', 'Nombre de joueurs connectés (alias :whosonline)'],
            [':uptime', 'Durée de fonctionnement de l\'émulateur'],
            [':reload', 'Recharge les données (catalogue, textes…)'],
            [':setconfig &lt;clé&gt; &lt;valeur&gt;', 'Modifie un réglage serveur'],
            [':setprice &lt;prix&gt;', 'Change le prix d\'un article du catalogue'],
            [':shutdown', 'Arrête proprement l\'émulateur'],
        ],
        '🏛️ Appartement / soi-même' => [
            [':about / :info', 'Infos sur l\'émulateur'],
            [':coords', 'Affiche tes coordonnées dans l\'appart'],
            [':sit', 'S\'asseoir sur place'],
            [':afk / :idle', 'Passer absent (et retour)'],
            [':motto &lt;texte&gt;', 'Change ta mission'],
            [':poof / :update', 'Rafraîchir / disparaître'],
            [':pickall', 'Ramasse tous les meubles de l\'appart'],
            [':talk &lt;user&gt; &lt;texte&gt;', 'Fait parler un joueur (bot)'],
            [':ufos', 'Effet OVNI'],
            [':rgb / :rainbow', 'Gradateur arc-en-ciel'],
        ],
        '🚌 Bus' => [
            [':infobus … / :bus …', 'Pilotage du bus (voir l\'onglet Bus)'],
        ],
    ];
    foreach ($grp as $titre => $cmds) {
        echo '<div class="panel"><div class="ph"><h3>' . h($titre) . '</h3></div><table class="clean"><tr><th>Commande</th><th>Effet</th></tr>';
        foreach ($cmds as $c) echo '<tr><td><code>' . $c[0] . '</code></td><td>' . $c[1] . '</td></tr>';
        echo '</table></div>';
    }
    echo '<p class="hint">Liste établie d\'après les commandes présentes dans <code>kepler.jar</code>. Les noms entre &lt;…&gt; sont les paramètres à remplacer.</p>';
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
    return [
        '🔧 Maintenance dans 10 minutes — pense à ranger tes mobis, déconnexion imminente !',
        '🔄 Redémarrage du serveur dans 5 minutes.',
        '🎉 Un événement vient de commencer dans les salles publiques — rejoins-nous !',
        '❄️ De nouveaux mobis sont disponibles au Catalogue !',
        '🎁 Distribution de crédits en cours, profites-en !',
        '👋 Bienvenue sur Habbo — amuse-toi bien !',
        '⚠️ Merci de respecter les autres joueurs. Les insultes = ban.',
    ];
}
function page_server(): void {
    page_title('Serveur', 'État des services, contrôle de l\'émulateur et diagnostics');
    $up = emu_running();
    $online = db()->query("SELECT value FROM settings WHERE setting='players.online'")->fetchColumn();
    $dbver = ''; try { $dbver = (string)db()->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) {}
    $dbShort = $dbver !== '' ? (stripos($dbver, 'maria') !== false ? 'MariaDB' : 'MySQL') . ' ' . preg_replace('/[-+].*$/', '', $dbver) : 'Active';

    /* --- État des services (réel) --- */
    echo '<div class="grid">';
    echo '<div class="stat ' . ($up ? 'green' : 'red') . '"><div class="ic">' . ($up ? '🟢' : '🔴') . '</div><div><div class="num" style="font-size:17px">' . ($up ? 'En marche' : 'Arrêté') . '</div><div class="lbl">Émulateur · port 12321</div></div></div>';
    echo '<div class="stat green"><div class="ic">🗄️</div><div><div class="num" style="font-size:15px">' . h($dbShort) . '</div><div class="lbl">Base de données · 3306</div></div></div>';
    echo '<div class="stat green"><div class="ic">🌐</div><div><div class="num" style="font-size:15px">PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '</div><div class="lbl">Site web · Apache · 80</div></div></div>';
    echo '<div class="stat blue"><div class="ic">👥</div><div><div class="num">' . ($up ? ($online !== false ? $online : '0') : '—') . '</div><div class="lbl">Joueurs en ligne</div></div></div>';
    echo '</div>';

    /* --- Contrôle de l'émulateur --- */
    echo '<div class="panel"><div class="ph"><h3>🎮 Contrôle de l\'émulateur</h3><a class="mini ghost lnkbtn" href="?p=server" style="margin-left:auto">🔄 Rafraîchir l\'état</a></div>';
    echo '<p class="sub">' . ($up ? '✅ Le jeu est accessible, les joueurs peuvent se connecter.' : '⛔ L\'émulateur est arrêté — personne ne peut jouer. Clique sur Démarrer.') . '</p>';
    echo '<div class="srv">';
    echo '<form method="post" class="js" data-reload><input type="hidden" name="action" value="srv_start">' . csrf_field() . '<button class="bigok"' . ($up ? ' disabled' : '') . '>▶️ Démarrer</button></form>';
    echo '<form method="post" class="js" data-reload data-confirm="Arrêter l\'émulateur ? Les joueurs seront déconnectés."><input type="hidden" name="action" value="srv_stop">' . csrf_field() . '<button class="bigred"' . ($up ? '' : ' disabled') . '>⏹️ Arrêter</button></form>';
    echo '<form method="post" class="js" data-reload data-confirm="Redémarrer l\'émulateur ? Les joueurs seront déconnectés ~10 s."><input type="hidden" name="action" value="srv_restart">' . csrf_field() . '<button class="bigwarn">🔄 Redémarrer</button></form>';
    echo '</div><p class="hint">💡 Le <b>redémarrage</b> applique les changements de décor, d\'activation de salle et certains réglages. La base de données et le site web ne sont pas touchés.</p></div>';

    /* --- Mode maintenance --- */
    $mf = dirname(__DIR__) . '/maintenance.json';
    $mData = is_file($mf) ? (json_decode((string)@file_get_contents($mf), true) ?: []) : [];
    $mOn = !empty($mData['on']);
    echo '<div class="panel"><div class="ph"><h3>🚧 Mode maintenance</h3><span class="rk ' . ($mOn ? 'red' : 'green') . '" style="margin-left:auto">' . ($mOn ? 'ACTIVÉ' : 'Inactif') . '</span></div>';
    echo '<p class="sub">Ferme le site public avec une page « Hôtel en maintenance » (503). Le staff (rang 5+) et l\'admin gardent l\'accès. Fonctionne même si l\'émulateur ou MySQL sont arrêtés.</p>';
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

    // Alerte hôtel (RCON) — action LIVE tournée vers les joueurs connectés, sa place naturelle est ici (contrôle serveur).
    echo '<div class="panel"><div class="ph"><h3>📢 Alerte à tout l\'hôtel</h3></div>';
    echo '<p class="sub">Envoie un message pop-up à tous les joueurs connectés (via l\'émulateur).</p>';
    $presetOpts = '<option value="">— Choisir un message prédéfini —</option>';
    foreach (hotel_alert_presets() as $pm) $presetOpts .= '<option value="' . h($pm) . '">' . h(mb_strlen($pm) > 64 ? mb_substr($pm, 0, 64) . '…' : $pm) . '</option>';
    echo '<form method="post" class="js"' . ($up ? '' : ' onsubmit="return false"') . '>' . csrf_field() . '<input type="hidden" name="action" value="hotel_alert">
        <div class="row" style="margin-bottom:8px"><label style="flex:1">📋 Messages prédéfinis<select ' . ($up ? '' : 'disabled') . ' onchange="if(this.value){document.getElementById(\'ha_msg\').value=this.value;}">' . $presetOpts . '</select></label></div>
        <div class="row"><label style="flex:2">Message<input id="ha_msg" name="message" placeholder="Ex. Maintenance dans 10 minutes !" ' . ($up ? '' : 'disabled') . '></label><label style="flex:1">Signature (optionnel)<input name="sender" placeholder="L\'équipe" ' . ($up ? '' : 'disabled') . '></label><div style="align-self:flex-end"><button ' . ($up ? '' : 'disabled') . '>Envoyer</button></div></div></form>';
    echo ($up ? '' : '<p class="hint">Démarre l\'émulateur pour pouvoir envoyer une alerte.</p>') . '</div>';

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

/* ---- RCON (alerte hôtel, etc.) ---- */
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
/* ---- Sauvegarde base ---- */
function backup_dir(): string { $d = __DIR__ . '/../../../_backups'; if (!is_dir($d)) @mkdir($d, 0777, true); return $d; }
function backup_list(): array {
    $files = glob(backup_dir() . '/v14_*.sql') ?: [];
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files;
}
function db_backup(): bool {
    $dump = realpath(__DIR__ . '/../../../MariaDB/bin/mysqldump.exe');
    if (!$dump) return false;
    $file = backup_dir() . '/v14_' . date('Ymd_His') . '.sql';
    @exec('"' . $dump . '" -u ' . DB_USER . ' -P ' . DB_PORT . ' -h ' . DB_HOST . ' --default-character-set=utf8mb4 ' . DB_NAME . ' --result-file="' . $file . '" 2>&1');
    foreach (array_slice(backup_list(), 15) as $old) @unlink($old); // garder 15
    return is_file($file) && filesize($file) > 0;
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
    $gicons = ['Joueurs & modération' => '👥', 'Catalogue & mobis' => '🛋️', 'Hôtel & animations' => '🏨', 'Site & contenus' => '📰', 'Administration' => '⚙️'];
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
.side nav{padding:12px 10px;display:flex;flex-direction:column;flex:1}
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
.content{padding:28px 36px 70px;max-width:1400px;width:100%;margin:0 auto}
.pagehead h1{font-size:25px;margin:0}.pagehead .sub{color:var(--mut);margin:4px 0 18px}
.sub{color:var(--mut)}.muted{color:var(--mut)}.sm{font-size:12px}
h3{font-size:15px;margin:0}h3.sec{margin:22px 0 10px;color:var(--mut);font-size:13px;text-transform:uppercase;letter-spacing:1px}
code{background:var(--bg);padding:2px 7px;border-radius:6px;color:var(--acc);font-size:12px;border:1px solid var(--line);font-family:var(--mono)}
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
.topbar{display:flex;align-items:center;min-height:60px;flex:0 0 auto;padding:0 36px;border-bottom:1px solid var(--line2);background:var(--panel);position:sticky;top:0;z-index:20}
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
    $gicons = ['Joueurs & modération' => '👥', 'Catalogue & mobis' => '🛋️', 'Hôtel & animations' => '🏨', 'Site & contenus' => '📰', 'Administration' => '⚙️'];
    foreach (nav_groups() as $grpLabel => $keys) {
        $visible = array_filter($keys, fn($k) => isset($nav[$k]) && tab_allowed($k));
        if (!$visible) continue;
        if ($grpLabel === '') {
            foreach ($visible as $k) { [$ic, $lbl] = $nav[$k]; echo '<a class="' . ($p === $k ? 'on' : '') . '" href="?p=' . $k . '"><span class="i">' . $ic . '</span><span>' . h($lbl) . '</span></a>'; }
            continue;
        }
        $first = reset($visible);
        $cur = in_array($p, $visible, true);
        echo '<a class="' . ($cur ? 'on' : '') . '" href="?p=' . $first . '"><span class="i">' . ($gicons[$grpLabel] ?? '📁') . '</span><span>' . h($grpLabel) . '</span></a>';
    }
    ?></nav><div class="foot">
    <label class="thsel">🎨 Thème<select onchange="setTheme(this.value)"><?php foreach (THEMES as $k => $tt) echo '<option value="' . $k . '"' . ($k === $tk ? ' selected' : '') . '>' . $tt[1] . ' ' . h($tt[0]) . '</option>'; ?></select></label>
    <div class="who">👤 <?= h($_SESSION['admin']['username']) ?></div><a href="http://localhost/" target="_blank">▶️ Jeu</a><a href="?p=logout">Déconnexion</a></div></aside>
<div class="main"><?php
    $secLabel = ''; foreach (nav_groups() as $gl => $keys) { if ($gl !== '' && in_array($p, $keys, true)) { $secLabel = $gl; break; } }
    $pageLabel = isset($nav[$p]) ? $nav[$p][1] : 'Tableau de bord';
    $emuUp = emu_running();
    $sq = ($p === 'search') ? h(trim($_GET['q'] ?? '')) : '';
?><header class="topbar">
  <div class="tb-crumb"><?php if ($secLabel !== '') echo '<span class="tb-sec">' . h($secLabel) . '</span><span class="tb-sep">›</span>'; ?><b><?= h($pageLabel) ?></b></div>
  <form class="tb-search" method="get" action="?"><input type="hidden" name="p" value="search"><input name="q" placeholder="Rechercher un joueur, un mobi, une salle…" value="<?= $sq ?>"><button type="submit" title="Rechercher">🔍</button></form>
  <a class="tb-status <?= $emuUp ? 'up' : 'down' ?>" href="?p=server" title="État de l'émulateur — ouvrir la page Serveur"><span class="dot"></span><?= $emuUp ? 'En marche' : 'Arrêté' ?></a>
</header><div class="content"><?php
    foreach (nav_groups() as $gl => $keys) {
        if ($gl === '' || !in_array($p, $keys, true)) continue;
        echo '<div class="subtabs">';
        foreach ($keys as $k) { if (!isset($nav[$k]) || !tab_allowed($k)) continue; [$ic, $lbl] = $nav[$k]; echo '<a class="' . ($p === $k ? 'on' : '') . '" href="?p=' . $k . '"><span>' . $ic . '</span> ' . h($lbl) . '</a>'; }
        echo '</div>';
        break;
    }
    if ($flash) echo '<script>window.__flash=' . json_encode($flash) . ';</script>';
}
function render_footer(): void { ?>
</div></div><div id="toasts"></div>
<script>
function toast(msg,ok){var t=document.createElement('div');t.className='toast'+(ok===false?' err':'');t.textContent=msg;document.getElementById('toasts').appendChild(t);setTimeout(function(){t.style.transition='opacity .4s';t.style.opacity='0';setTimeout(function(){t.remove()},400)},3200);}
function tgl(id){var e=document.getElementById(id);if(e)e.style.display=e.style.display==='none'?'table-row':'none';}
function setTheme(v){document.cookie='r14theme='+v+';path=/;max-age=31536000';location.reload();}
function pickBadge(c){var e=document.getElementById('badgecode');if(e){e.value=c;e.scrollIntoView({block:'center'});e.focus();toast('Badge '+c+' sélectionné',true);}}
function toggleGrp(btn){var g=btn.parentNode;g.classList.toggle('open');try{var o=JSON.parse(localStorage.getItem('r14nav')||'{}');o[g.getAttribute('data-g')]=g.classList.contains('open');localStorage.setItem('r14nav',JSON.stringify(o));}catch(e){}}
(function(){try{var o=JSON.parse(localStorage.getItem('r14nav')||'{}');var l=document.querySelectorAll('.navgrp');for(var i=0;i<l.length;i++){var g=l[i];if(g.getAttribute('data-cur'))continue;var k=g.getAttribute('data-g');if(k in o){if(o[k])g.classList.add('open');else g.classList.remove('open');}}}catch(e){}})();
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
