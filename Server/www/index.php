<?php
/**
 * HabboretroV14 — Site public (accueil, inscription, connexion SSO, profil)
 * Look « Habbo 2007 » d'origine. Servi par Apache/Laragon (PHP 8.3).
 * Le jeu (client Shockwave) est chargé via /client.php?sso=<ticket>.
 */
declare(strict_types=1);
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => (($_SERVER['HTTPS'] ?? '') !== '')]);
session_start();
mb_internal_encoding('UTF-8');

const DB_HOST = '127.0.0.1', DB_PORT = 3306, DB_NAME = 'v14', DB_USER = 'root', DB_PASS = '';
const HOTEL = 'Habbo';
const GAME_URL = '/client.php';               // loader du client (racine)
const DEFAULT_FIGURE = '1000118001270012900121001';

function db(): PDO {
    static $p = null;
    if ($p === null) $p = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2,          // échoue vite si la base est éteinte (plus de « ça rame »)
    ]);
    return $p;
}
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/* Date de naissance à la française (JJ/MM/AAAA) — type=date non géré par Basilisk/Goanna */
function parse_fr_date(?string $s): string { $s = trim((string)$s); if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]); if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s; return '1990-01-01'; }
function csrf(): string { if (empty($_SESSION['scsrf'])) $_SESSION['scsrf'] = bin2hex(random_bytes(16)); return $_SESSION['scsrf']; }
function csrf_ok(): bool { $t = (string)($_POST['csrf'] ?? ''); return $t !== '' && !empty($_SESSION['scsrf']) && hash_equals((string)$_SESSION['scsrf'], $t); }
function hash_pw(string $pw): string { return password_hash($pw, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 2, 'threads' => 1]); }
/* ---- Anti-bruteforce connexion (fichier, par IP+contexte ; 5 échecs / 15 min => blocage 10 min) ---- */
function throttle_file(string $ctx): string { return sys_get_temp_dir() . '/retro14_thr_' . preg_replace('/[^a-z]/', '', $ctx) . '_' . md5((string)($_SERVER['REMOTE_ADDR'] ?? 'cli')) . '.json'; }
function throttle_blocked(string $ctx): int { $d = @json_decode((string)@file_get_contents(throttle_file($ctx)), true); $u = (int)($d['until'] ?? 0); $now = time(); return $u > $now ? $u - $now : 0; }
function throttle_fail(string $ctx): void { $f = throttle_file($ctx); $now = time(); $d = @json_decode((string)@file_get_contents($f), true) ?: []; if ((int)($d['first'] ?? 0) < $now - 900) $d = ['first' => $now, 'fails' => 0, 'until' => 0]; $d['fails'] = (int)($d['fails'] ?? 0) + 1; if ($d['fails'] >= 5) $d['until'] = $now + 600; @file_put_contents($f, json_encode($d), LOCK_EX); }
function throttle_ok(string $ctx): void { @unlink(throttle_file($ctx)); }
/* ---- File des demandes de réinitialisation de mot de passe (traitée par le staff dans l'admin) ---- */
function ensure_pw_resets(): void { db()->exec("CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL, message VARCHAR(500) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '', status ENUM('pending','done','rejected') NOT NULL DEFAULT 'pending', handled_by VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, handled_at DATETIME NULL, INDEX(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
/* ---- Messages du formulaire Contact (boîte de réception admin) ---- */
function ensure_contact_msgs(): void { db()->exec("CREATE TABLE IF NOT EXISTS contact_messages (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL DEFAULT '', subject VARCHAR(120) NOT NULL DEFAULT '', message VARCHAR(1000) NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT '', status ENUM('new','handled') NOT NULL DEFAULT 'new', handled_by VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, handled_at DATETIME NULL, INDEX(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
/* ---- Messages reçus par le joueur (envoyés par le staff) ---- */
function ensure_user_msgs(): void { db()->exec("CREATE TABLE IF NOT EXISTS user_messages (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, from_staff VARCHAR(64) NOT NULL DEFAULT '', subject VARCHAR(120) NOT NULL DEFAULT '', body VARCHAR(2000) NOT NULL, read_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), INDEX(read_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
function unread_msgs(int $uid): int { if ($uid <= 0) return 0; try { ensure_user_msgs(); $st = db()->prepare('SELECT COUNT(*) FROM user_messages WHERE user_id=? AND read_at IS NULL'); $st->execute([$uid]); return (int)$st->fetchColumn(); } catch (Throwable $e) { return 0; } }
function me(): ?array { return $_SESSION['site_user'] ?? null; }
function redirect(string $to) { header('Location: ' . $to); exit; }

$p = $_GET['p'] ?? 'home';
$err = null; $ok = null;

/* ---- Déconnexion ---- */
if ($p === 'logout') { unset($_SESSION['site_user'], $_SESSION['admin']); session_regenerate_id(true); redirect('?p=home'); }

/* ---- Mode maintenance (fichier, marche même si MySQL est éteint) ----
   Le staff (rang >= 5) et la page de connexion passent outre pour pouvoir désactiver. */
$maint = maintenance_state();
if (!empty($maint['on'])) {
    $isStaff = false;
    if (!empty($_SESSION['site_user']['id'])) {
        try { $st = db()->prepare('SELECT `rank` FROM users WHERE id=?'); $st->execute([(int)$_SESSION['site_user']['id']]); $isStaff = ((int)$st->fetchColumn()) >= 5; } catch (Throwable $e) {}
    }
    if (!$isStaff && $p !== 'login') { maintenance_habbo($maint); exit; }
}

try { // ---- si la base est éteinte, on affiche une page « maintenance » propre (pas d'erreur fatale)

/* ---- Inscription ---- */
if ($p === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $err = 'Session expirée, réessaie.'; }
    else {
        $u = trim($_POST['username'] ?? ''); $pw = (string)($_POST['password'] ?? ''); $sex = ($_POST['sex'] ?? 'M') === 'F' ? 'F' : 'M';
        $bd = parse_fr_date($_POST['birthday'] ?? '');
        if ((int)($_POST['captcha'] ?? -1) !== (int)($_SESSION['reg_captcha'] ?? -2)) $err = 'Réponse au calcul anti-robot incorrecte.';
        elseif (!preg_match('/^[A-Za-z0-9_\-=?!@:.,]{3,20}$/', $u)) $err = 'Nom invalide (3-20 caractères, lettres/chiffres).';
        elseif (strlen($pw) < 4) $err = 'Mot de passe trop court (4 min).';
        else {
            $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([$u]);
            if ($st->fetch()) $err = 'Ce nom est déjà pris.';
            else {
                db()->prepare('INSERT INTO users (username,password,figure,sex,motto,credits,email,birthday) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$u, hash_pw($pw), DEFAULT_FIGURE, $sex, 'Nouveau sur ' . HOTEL . ' !', 100, $u . '@' . HOTEL . '.local', $bd]);
                $id = (int)db()->lastInsertId();
                session_regenerate_id(true);
                unset($_SESSION['admin']); // purge une éventuelle identité admin résiduelle
                $_SESSION['site_user'] = ['id' => $id, 'username' => $u];
                redirect('?p=home&welcome=1');
            }
        }
    }
    $p = 'register';
}

/* ---- Connexion ---- */
if ($p === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $err = 'Session expirée, réessaie.'; }
    elseif (($w = throttle_blocked('site')) > 0) { $err = 'Trop de tentatives. Réessaie dans ' . (int)ceil($w / 60) . ' min.'; }
    else {
        $st = db()->prepare('SELECT id,username,password FROM users WHERE username=?'); $st->execute([trim($_POST['username'] ?? '')]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password'])) {
            throttle_ok('site');
            session_regenerate_id(true);
            unset($_SESSION['admin']); // purge une éventuelle identité admin d'un autre compte
            $_SESSION['site_user'] = ['id' => (int)$u['id'], 'username' => $u['username']];
            redirect('?p=home');
        }
        throttle_fail('site');
        $err = 'Nom ou mot de passe incorrect.';
    }
    $p = 'home';
}

/* ---- Demande de réinitialisation de mot de passe (file traitée par le staff) ---- */
if ($p === 'forgot' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $_SESSION['forgot_err'] = 'Session expirée, réessaie.'; }
    elseif (($w = throttle_blocked('forgot')) > 0) { $_SESSION['forgot_err'] = 'Trop de demandes. Réessaie dans ' . (int)ceil($w / 60) . ' min.'; }
    else {
        $ru = trim($_POST['username'] ?? '');
        $rm = mb_substr(trim($_POST['message'] ?? ''), 0, 500);
        if (!preg_match('/^[A-Za-z0-9_\-=?!@:.,]{3,20}$/', $ru)) { $_SESSION['forgot_err'] = 'Indique un pseudo valide (3 à 20 caractères).'; }
        else {
            try { ensure_pw_resets(); db()->prepare('INSERT INTO password_resets (username,message,ip) VALUES (?,?,?)')->execute([$ru, $rm, (string)($_SERVER['REMOTE_ADDR'] ?? '')]); } catch (Throwable $e) {}
            throttle_fail('forgot');           // limite l'envoi en masse (5 / 15 min)
            $_SESSION['forgot_done'] = true;    // message générique : ne révèle pas si le compte existe
        }
    }
    redirect('?p=forgot');
}

/* ---- Message via le formulaire Contact (boîte de réception staff) ---- */
if ($p === 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $_SESSION['contact_err'] = 'Session expirée, réessaie.'; }
    elseif (($w = throttle_blocked('contact')) > 0) { $_SESSION['contact_err'] = 'Trop de messages envoyés. Réessaie dans ' . (int)ceil($w / 60) . ' min.'; }
    else {
        $cu = trim($_POST['username'] ?? (me()['username'] ?? ''));
        $csub = mb_substr(trim($_POST['subject'] ?? ''), 0, 120);
        $cmsg = mb_substr(trim($_POST['message'] ?? ''), 0, 1000);
        if (mb_strlen($cmsg) < 5) { $_SESSION['contact_err'] = 'Ton message est trop court.'; }
        else {
            try { ensure_contact_msgs(); db()->prepare('INSERT INTO contact_messages (username,subject,message,ip) VALUES (?,?,?,?)')->execute([mb_substr($cu, 0, 64), $csub, $cmsg, (string)($_SERVER['REMOTE_ADDR'] ?? '')]); } catch (Throwable $e) {}
            throttle_fail('contact');       // limite l'envoi en masse (5 / 15 min)
            $_SESSION['contact_done'] = true;
        }
    }
    redirect('?p=contact');
}

/* ---- Modifier son motto depuis sa page perso ---- */
if ($p === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST' && me()) {
    if (csrf_ok()) {
        $m = mb_substr(trim((string)($_POST['motto'] ?? '')), 0, 100);
        db()->prepare('UPDATE users SET motto=?, console_motto=? WHERE id=?')->execute([$m, $m, (int)me()['id']]);
    }
    redirect('?p=profile&u=' . rawurlencode((string)me()['username']) . '&saved=1');
}

/* ---- Entrer dans l'hôtel (ouvre le client — login natif) ----
   NB : ce client Shockwave v14 ne sauvegarde PAS la tenue/mission quand il est
   auto-connecté par ticket SSO. On ouvre donc le client avec son écran de login
   natif (fiable, la sauvegarde fonctionne). Le SSO reste supporté par client.php
   (?sso=) mais n'est plus utilisé par défaut. */
if ($p === 'play') {
    redirect(GAME_URL);
}

render($p, $err);

} catch (PDOException $e) {
    maintenance_page();
}

/* =========================== VUES =========================== */
function csrf_f(): string { return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">'; }
function ranks_fr(): array { return [1 => 'Habbo', 2 => 'Community Manager', 3 => 'Guide', 4 => 'Hobba', 5 => 'Super Hobba', 6 => 'Modérateur', 7 => 'Administrateur']; }

function maintenance_page(): void {
    if (!headers_sent()) http_response_code(503);
    head('home');
    echo '<div class="cols one"><div class="panel">';
    echo '  <div class="panel-h orange">L\'hôtel démarre…</div>';
    echo '  <div class="panel-b center">';
    echo '    <p>La base de données n\'est pas encore lancée.</p>';
    echo '    <p class="tip">Lance le serveur (fichier <b>START</b> dans le dossier du jeu), attends « Base prête ! », puis <a href="?p=home">recharge la page</a>.</p>';
    echo '  </div>';
    echo '</div></div>';
    foot();
}

/* ---- Mode maintenance (fichier maintenance.json à la racine du site) ---- */
function maintenance_state(): array {
    $f = __DIR__ . '/maintenance.json';
    if (!is_file($f)) return ['on' => false];
    $j = json_decode((string)@file_get_contents($f), true);
    return is_array($j) ? array_merge(['on' => false], $j) : ['on' => false];
}
function maintenance_habbo(array $m): void {
    if (!headers_sent()) { http_response_code(503); header('Retry-After: 3600'); }
    $msg = trim((string)($m['message'] ?? '')); if ($msg === '') $msg = "L'Hôtel est momentanément fermé pour une petite mise à jour. Reviens très vite !";
    $eta = trim((string)($m['eta'] ?? ''));
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Habbo — Maintenance</title><link rel="icon" href="/web-gallery/v2/favicon.ico"><style>
*{box-sizing:border-box;margin:0;padding:0}
body{font:14px/1.6 Verdana,Geneva,Arial,sans-serif;color:#4a4a4a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:#cfe3f2;background-image:linear-gradient(#cfe3f2,#e9e4d6)}
.mbox{width:520px;max-width:100%;text-align:center}
.mlogo{margin:0 auto 18px;height:70px;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated}
.mcard{background:#fff;border:1px solid #cfc6ad;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.15);overflow:hidden}
.mcard .h{background:linear-gradient(#f7a838,#ef8f13);color:#fff;font-weight:700;font-size:16px;padding:14px;text-shadow:0 1px 0 rgba(0,0,0,.2)}
.mcard .b{padding:26px}
.mimg{width:90px;height:auto;margin:0 auto 14px;display:block;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated}
.mcard p{color:#5b5b5b;font-size:14px;margin:8px 0}
.meta{display:inline-block;margin-top:14px;background:#eef4fa;border:1px solid #cfe0f0;color:#2f6f9f;font-weight:700;font-size:13px;padding:8px 14px;border-radius:9px}
.mfoot{margin-top:16px;color:#8a857a;font-size:11px}
</style></head><body>
<div class="mbox">
  <img class="mlogo" src="/c_images/WebLogos/habbo_logo_nourl.gif" alt="Habbo">
  <div class="mcard">
    <div class="h">🛠️ L'Hôtel est fermé pour maintenance</div>
    <div class="b">
      <img class="mimg" src="/web-gallery/v2/images/hotel-button-hotelclosed.png" alt="" onerror="this.style.display='none'">
      <p><?= h($msg) ?></p>
      <?php if ($eta !== '') echo '<div class="meta">⏱️ Retour estimé : ' . h($eta) . '</div>'; ?>
      <p style="margin-top:16px"><b>Merci de ta patience — reviens bientôt !</b></p>
    </div>
  </div>
  <div class="mfoot">Habbo — rétro v14 (2007). Habbo est une marque de Sulake. Projet privé, non affilié.</div>
</div>
</body></html><?php
}

function render(string $p, ?string $err): void {
    head($p);
    switch ($p) {
        case 'register':  view_register($err); break;
        case 'profile':   view_profile();      break;
        case 'news':      view_news();         break;
        case 'games':     view_games();        break;
        case 'club':      view_club();         break;
        case 'community': view_community();     break;
        case 'help':      view_help();         break;
        case 'contact':   view_contact();      break;
        case 'forgot':    view_forgot();       break;
        case 'messages':  view_messages();     break;
        default:          view_home($err);
    }
    foot();
}

/* -------- Accueil -------- */
function view_home(?string $err): void {
    $u = me();
    echo '<div class="cols">';

    /* Colonne principale : l'hôtel */
    echo '<div class="main">';
    echo '<div class="panel hotel">';
    echo '  <div class="panel-h orange">Bienvenue à l\'Hôtel ' . HOTEL . ' !</div>';
    echo '  <div class="panel-b">';
    echo '    <img class="hotelimg" src="/c_images/Frontpage_images/front_page_hotel_with_habbos.png" alt="Hôtel ' . HOTEL . '">';
    echo '    <p class="pitch">Crée ton Habbo, décore ta chambre, retrouve tes amis et amuse-toi dans les salles publiques. Le vrai Habbo de 2007 !</p>';
    if ($u) echo '    <a class="hbtn green big" href="?p=play">Entre dans l\'Hôtel &raquo;</a>';
    else    echo '    <a class="hbtn green big" href="?p=register">Crée ton Habbo &raquo;</a>';
    echo '  </div>';
    echo '</div>';

    /* Actualités de l'hôtel — une à la une + liste, façon news Habbo */
    $rows = site_news_rows(5);
    echo '<div class="panel newspanel"><div class="panel-h orange">📰 Actualités de l\'Hôtel</div><div class="panel-b">';
    if ($rows) {
        news_feature(array_shift($rows));
        if ($rows) { echo '<div class="newsmini">'; foreach ($rows as $n) news_row_mini($n); echo '</div>'; }
    }
    echo '<a class="newsall" href="?p=news">Toutes les actualités &raquo;</a>';
    echo '</div></div>';
    echo '</div>'; // .main

    /* Colonne latérale : connexion / profil */
    echo '<div class="side">';
    if ($u) {
        echo '<div class="panel">';
        echo '  <div class="panel-h green">Salut ' . h($u['username']) . ' !</div>';
        echo '  <div class="panel-b center">';
        if (isset($_GET['welcome'])) echo '<div class="flash ok">Ton Habbo est créé, bienvenue !</div>';
        echo '    <a class="hbtn green big" href="?p=play">Entre dans l\'Hôtel</a>';
        echo '    <a class="hbtn" href="?p=profile&u=' . h(rawurlencode($u['username'])) . '">Ma page perso</a>';
        echo '    <a class="hbtn grey" href="?p=logout">Déconnexion</a>';
        echo '    <p class="tip">Ouvre le site dans <b>Basilisk</b> pour que « Entre dans l\'Hôtel » lance le jeu.</p>';
        echo '  </div>';
        echo '</div>';
    } else {
        echo '<div class="panel">';
        echo '  <div class="panel-h blue">Connecte-toi</div>';
        echo '  <div class="panel-b">';
        if ($err) echo '<div class="flash err">' . h($err) . '</div>';
        echo '    <form method="post" action="?p=login">' . csrf_f();
        echo '      <label>Nom Habbo</label><input name="username" required autofocus>';
        echo '      <label>Mot de passe</label><input name="password" type="password" required>';
        echo '      <button class="hbtn green">C\'est parti !</button>';
        echo '      <a class="forgot-link" href="?p=forgot">🔑 Mot de passe oublié&nbsp;?</a>';
        echo '    </form>';
        echo '  </div>';
        echo '</div>';
        echo '<div class="panel">';
        echo '  <div class="panel-h orange">Nouveau ?</div>';
        echo '  <div class="panel-b center">';
        echo '    <p class="tip">Pas encore de Habbo ? C\'est gratuit et ça prend 10 secondes.</p>';
        echo '    <a class="hbtn orange" href="?p=register">Crée ton Habbo</a>';
        echo '  </div>';
        echo '</div>';
    }
    rightnow_box();
    stats_box();
    latest_box();
    echo '</div>'; // .side

    echo '</div>'; // .cols
}

function latest_box(): void {
    $rows = db()->query('SELECT username,sex FROM users ORDER BY id DESC LIMIT 9')->fetchAll();
    if (!$rows) return;
    echo '<div class="panel"><div class="panel-h purple">Derniers inscrits</div><div class="panel-b"><div class="friends">';
    foreach ($rows as $r) echo av_mini((string)$r['username'], (string)$r['sex']);
    echo '</div></div></div>';
}

function news_col(?string $c): string { return in_array($c, ['blue', 'green', 'purple', 'orange'], true) ? (string)$c : 'blue'; }
function news_date(?string $d): string { return !empty($d) ? date('d/m/Y', strtotime((string)$d)) : ''; }
function news_feature(array $n): void {
    $c = news_col($n['color'] ?? '');
    echo '<div class="nfeat ' . $c . '">';
    echo '<div class="nfeat-h"><span class="ntag ' . $c . '">' . h((string)$n['category']) . '</span>';
    $d = news_date($n['date'] ?? null); if ($d) echo '<span class="ndate">' . $d . '</span>';
    echo '</div>';
    echo '<h3>' . h((string)$n['title']) . '</h3><p>' . h(mb_strimwidth((string)$n['body'], 0, 165, '…')) . '</p>';
    echo '</div>';
}
function news_row_mini(array $n): void {
    $c = news_col($n['color'] ?? '');
    echo '<a class="nmini" href="?p=news"><span class="ndot ' . $c . '"></span><span class="nmini-t">' . h((string)$n['title']) . '</span>';
    $d = news_date($n['date'] ?? null); if ($d) echo '<span class="ndate">' . $d . '</span>';
    echo '</a>';
}
/* Actus : lues depuis l'admin (table site_news) ; repli sur des actus par défaut si vide/absente */
function site_news_rows(int $limit = 0): array {
    $def = [
        ['color' => 'blue',   'category' => 'À la une',  'title' => 'Nouvel hôtel rétro !', 'body' => 'Habbo rouvre ses portes façon 2007 : mobis, salles publiques, Trax et jeux d\'origine.', 'date' => null],
        ['color' => 'green',  'category' => 'Astuce',    'title' => 'Décore ta chambre',    'body' => 'File au Catalogue, achète des mobis et arrange ta chambre comme tu veux.', 'date' => null],
        ['color' => 'purple', 'category' => 'Communauté','title' => 'Retrouve tes amis',    'body' => 'Ajoute des amis, discute par la console et donne-toi rendez-vous dans les salles.', 'date' => null],
    ];
    try {
        $sql = 'SELECT title,category,color,body,created_at FROM site_news ORDER BY created_at DESC, id DESC';
        if ($limit > 0) $sql .= ' LIMIT ' . (int)$limit;
        $rows = db()->query($sql)->fetchAll();
        if ($rows) return array_map(fn($r) => ['color' => $r['color'], 'category' => $r['category'], 'title' => $r['title'], 'body' => $r['body'], 'date' => $r['created_at']], $rows);
    } catch (Throwable $e) {}
    return $limit > 0 ? array_slice($def, 0, $limit) : $def;
}

function rightnow_box(): void {
    $online = db()->query("SELECT value FROM settings WHERE setting='players.online'")->fetchColumn();
    $on = $online !== false ? (int)$online : 0;
    $room = db()->query("SELECT name,visitors_now,visitors_max FROM rooms WHERE owner_id='0' AND is_hidden=0 ORDER BY visitors_now DESC, RAND() LIMIT 1")->fetch();
    echo '<div class="panel"><div class="panel-h blue">En ce moment dans l\'Hôtel</div><div class="panel-b">';
    echo '<div class="rn-online"><img src="/web-gallery/v2/images/habbo_online_anim.gif" alt="" onerror="this.style.display=\'none\'"><span><b>' . $on . '</b> Habbo' . ($on > 1 ? 's' : '') . ' connecté' . ($on > 1 ? 's' : '') . '</span></div>';
    if ($room) {
        echo '<div class="rn-room"><span class="rn-star">★</span><div class="rinfo"><b>' . h((string)$room['name']) . '</b><span class="rm">👤 ' . (int)$room['visitors_now'] . ' / ' . (int)$room['visitors_max'] . ' Habbos</span></div></div>';
    }
    echo '</div></div>';
}
function stats_box(): void {
    $d = db();
    $users  = (int)$d->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $online = $d->query("SELECT value FROM settings WHERE setting='players.online'")->fetchColumn();
    $rooms  = (int)$d->query("SELECT COUNT(*) FROM rooms WHERE owner_id='0'")->fetchColumn();
    echo '<div class="panel"><div class="panel-h grey">L\'hôtel en chiffres</div><div class="panel-b">';
    echo '<ul class="statl">';
    echo '<li><b>' . $users . '</b> Habbos inscrits</li>';
    echo '<li><b>' . ($online !== false ? (int)$online : 0) . '</b> en ligne</li>';
    echo '<li><b>' . $rooms . '</b> salles publiques</li>';
    echo '</ul></div></div>';
}

/* -------- Inscription -------- */
function view_register(?string $err): void {
    echo '<div class="cols one">';
    echo '<div class="panel">';
    echo '  <div class="panel-h orange">Crée ton Habbo</div>';
    echo '  <div class="panel-b">';
    if ($err) echo '<div class="flash err">' . h($err) . '</div>';
    echo '<form method="post" action="?p=register" class="reg">' . csrf_f();
    echo '  <label>Nom Habbo</label><input name="username" maxlength="20" placeholder="Ton pseudo" required autofocus>';
    echo '  <label>Mot de passe</label><input name="password" type="password" placeholder="4 caractères minimum" required>';
    echo '  <label>Ton avatar</label>';
    echo '  <div class="sexpick">';
    echo '    <label class="sx"><input type="radio" name="sex" value="M" checked><span>Garçon</span></label>';
    echo '    <label class="sx"><input type="radio" name="sex" value="F"><span>Fille</span></label>';
    echo '  </div>';
    echo '  <label>Date de naissance</label><input name="birthday" placeholder="JJ/MM/AAAA" value="01/01/1990" required>';
    $a = random_int(1, 9); $b = random_int(1, 9); $_SESSION['reg_captcha'] = $a + $b;
    echo '  <label>Anti-robot : combien font ' . $a . ' + ' . $b . ' ?</label><input name="captcha" type="number" inputmode="numeric" placeholder="Ta réponse" required>';
    echo '  <button class="hbtn green big">Créer et jouer &raquo;</button>';
    echo '</form>';
    echo '<p class="tip"><a href="?p=home">&laquo; J\'ai déjà un Habbo</a></p>';
    echo '  </div>';
    echo '</div></div>';
}

/* -------- Actualités -------- */
function view_news(): void {
    $rows = site_news_rows();
    echo '<div class="cols one2">';
    echo '<div class="panel"><div class="panel-h orange">Actualités de l\'hôtel</div><div class="panel-b"><div class="newslist">';
    foreach ($rows as $n) {
        $col = in_array($n['color'], ['blue', 'green', 'purple', 'orange'], true) ? $n['color'] : 'blue';
        echo '<div class="nrow"><span class="ntag ' . $col . '">' . h($n['category']) . '</span>';
        if (!empty($n['date'])) echo '<span class="ndate">' . h(date('d/m/Y', strtotime((string)$n['date']))) . '</span>';
        echo '<h3 class="nt">' . h($n['title']) . '</h3>';
        echo '<p>' . nl2br(h((string)$n['body'])) . '</p></div>';
    }
    echo '</div></div></div>';
    echo '</div>';
}

/* -------- Jeux -------- */
function view_games(): void {
    $bb = db()->query('SELECT username,battleball_points FROM users WHERE battleball_points>0 ORDER BY battleball_points DESC LIMIT 10')->fetchAll();
    $sn = db()->query('SELECT username,snowstorm_points FROM users WHERE snowstorm_points>0 ORDER BY snowstorm_points DESC LIMIT 10')->fetchAll();
    echo '<div class="cols">';
    echo '<div class="main">';
    echo '<div class="panel"><div class="panel-h purple">Les jeux de Habbo</div><div class="panel-b">';
    echo '<div class="gamelist">';
    echo '<div class="grow"><span class="gi">🏐</span><div><b>BattleBall</b><p>En équipe, colore un maximum de cases en marchant dessus. Le plus rapide gagne !</p></div></div>';
    echo '<div class="grow"><span class="gi">❄️</span><div><b>SnowStorm</b><p>Bataille de boules de neige : vise tes adversaires et esquive pour marquer des points.</p></div></div>';
    echo '<div class="grow"><span class="gi">♟️</span><div><b>Jeux de salon</b><p>Échecs, dames, backgammon… disponibles via les plateaux de jeu dans les chambres.</p></div></div>';
    echo '</div></div></div>';
    echo '</div>';
    echo '<div class="side">';
    lead_board('🏐 Top BattleBall', $bb, 'battleball_points');
    lead_board('❄️ Top SnowStorm', $sn, 'snowstorm_points');
    echo '</div>';
    echo '</div>';
}
function lead_board(string $title, array $rows, string $col): void {
    echo '<div class="panel"><div class="panel-h blue">' . h($title) . '</div><div class="panel-b">';
    if (!$rows) echo '<p class="tip">Aucun score pour le moment. À toi de jouer !</p>';
    else {
        echo '<ol class="lb">';
        foreach ($rows as $i => $r) echo '<li><span class="rk">' . ($i + 1) . '</span><a href="?p=profile&u=' . h(rawurlencode((string)$r['username'])) . '">' . h($r['username']) . '</a><b>' . (int)$r[$col] . '</b></li>';
        echo '</ol>';
    }
    echo '</div></div>';
}

/* -------- Habbo Club -------- */
function perk(string $i, string $t, string $d): void {
    echo '<div class="perk"><span class="pi">' . $i . '</span><div><b>' . h($t) . '</b><p>' . h($d) . '</p></div></div>';
}
/* Vraies images des mobis HC (c_images/furni/HC) + jolis noms FR */
function hc_gift_images(): array {
    $labels = [
        'hc_throne_sofa' => 'Trône', 'hc_majestic' => 'Trône majestueux', 'hc_sofa' => 'Sofa HC',
        'hc_table' => 'Table de banquet', 'hc_desk' => 'Pupitre', 'hc_bookcase' => 'Bibliothèque',
        'hc_fireplace' => 'Cheminée HC', 'hc_carpet' => 'Tapis persan', 'hc_curtain' => 'Rideaux antiques',
        'hc_grammo' => 'Phonogramme', 'hc_lamp' => 'Lampe HC', 'hc_lantern' => 'Lanterne',
        'hc_oil' => 'Lampe à huile', 'hc_machine' => 'Calculatron', 'hc_xray' => 'Écran Rayons-X',
        'hc_butler' => 'Méca-Majordome', 'hc_trolley' => 'Chariot repas', 'hc_tub' => 'Baignoire géante',
        'hc_tv' => 'Meuble Télé', 'hc_tele' => 'Télé HC', 'hc_tele_set' => 'Ensemble Télé',
        'hc_dice' => 'Dé magique', 'hc_roller' => 'Rollers HC', 'hc_mocha' => 'Machine à café',
        'hc_plastic_chair' => 'Chaise HC', 'hc_plastic_table' => 'Table HC', 'hc_plastic_set' => 'Salon HC',
    ];
    $dir = __DIR__ . '/c_images/furni/HC';
    $out = [];
    foreach (glob($dir . '/*.gif') ?: [] as $f) {
        $base = pathinfo($f, PATHINFO_FILENAME);
        $out[] = ['url' => '/c_images/furni/HC/' . rawurlencode(basename($f)), 'label' => $labels[$base] ?? ucwords(str_replace(['hc_', '_'], ['', ' '], $base))];
    }
    return $out;
}
function view_club(): void {
    $u = me(); $hc = false; $exp = 0;
    if ($u) { try { $s = db()->prepare('SELECT club_expiration FROM users WHERE id=?'); $s->execute([(int)$u['id']]); $exp = (int)$s->fetchColumn(); $hc = $exp > time(); } catch (Throwable $e) {} }
    $gifts = hc_gift_images();

    echo '<div class="cols">';

    /* Colonne principale */
    echo '<div class="main">';
    echo '<div class="panel"><div class="panel-h hcdark"><img class="hclogo" src="/web-gallery/images/habbo_club.png" alt="HC"><span class="hcgold">Habbo Club</span></div><div class="panel-b">';
    echo '<p class="pitch" style="padding:0 0 12px">Le <b>Habbo Club</b>, c\'est le cercle VIP de l\'hôtel. Les membres profitent de mobis exclusifs, de cadeaux réguliers, d\'un badge doré et de petits privilèges en jeu.</p>';
    echo '<div class="hcperks">';
    perk('🛋️', 'Mobis exclusifs', 'Des meubles réservés aux membres, introuvables ailleurs dans le Catalogue.');
    perk('🎁', 'Cadeaux réguliers', 'Un nouveau cadeau Habbo Club à chaque période d\'abonnement.');
    perk('⭐', 'Badge doré', 'Un badge Habbo Club s\'affiche fièrement sur ta page perso.');
    perk('👑', 'Look & privilèges', 'Vêtements club et petits bonus pour te démarquer.');
    echo '</div></div></div>';

    echo '<div class="panel"><div class="panel-h purple">Les mobis du Club (' . count($gifts) . ')</div><div class="panel-b">';
    if (!$gifts) echo '<p class="tip">La collection arrive bientôt !</p>';
    else {
        echo '<div class="hcgifts">';
        foreach ($gifts as $g) {
            echo '<div class="hcgift" title="' . h($g['label']) . '"><div class="hcgift-img"><img src="' . h($g['url']) . '" alt="' . h($g['label']) . '" loading="lazy"></div><span>' . h($g['label']) . '</span></div>';
        }
        echo '</div>';
    }
    echo '</div></div>';
    echo '</div>'; // .main

    /* Colonne latérale */
    echo '<div class="side">';
    echo '<div class="panel"><div class="panel-h hcdark"><img class="hclogo" src="/web-gallery/images/habbo_club.png" alt="HC"><span class="hcgold">Ton statut</span></div><div class="panel-b center">';
    if ($hc) {
        echo '<div class="hcbadge" style="font-size:13px">★ Membre Habbo Club</div>';
        echo '<p class="tip">Abonnement actif jusqu\'au <b>' . h(date('d/m/Y', $exp)) . '</b>.</p>';
    } elseif ($u) {
        echo '<p class="tip">Tu n\'es pas encore membre du Club.</p><a class="hbtn orange" href="?p=play">Rejoindre au Catalogue</a>';
    } else {
        echo '<p class="tip">Connecte-toi pour voir ton statut Club.</p><a class="hbtn green" href="?p=register">Crée ton Habbo</a>';
    }
    echo '</div></div>';

    echo '<div class="panel"><div class="panel-h orange">Comment devenir membre ?</div><div class="panel-b"><ol class="hcsteps">';
    echo '<li>Entre dans l\'hôtel et ouvre le <b>Catalogue</b>.</li>';
    echo '<li>Va dans la rubrique <b>Habbo Club</b>.</li>';
    echo '<li>Choisis ta durée et paie en <b>crédits</b>.</li>';
    echo '<li>Profite tout de suite de tes avantages !</li>';
    echo '</ol></div></div>';
    echo '</div>'; // .side

    echo '</div>';
}

/* -------- Communauté -------- */
function view_community(): void {
    $q = trim($_GET['q'] ?? '');
    $staff  = db()->query('SELECT username,sex,`rank` FROM users WHERE `rank`>=5 ORDER BY `rank` DESC, username')->fetchAll();
    $total  = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $online = db()->query("SELECT value FROM settings WHERE setting='players.online'")->fetchColumn();
    $recent = db()->query('SELECT username,sex FROM users WHERE last_online>0 ORDER BY last_online DESC LIMIT 12')->fetchAll();
    // Hall of Fame
    $topCred = db()->query('SELECT username,credits FROM users ORDER BY credits DESC LIMIT 5')->fetchAll();
    $topFri  = db()->query('SELECT u.username,(SELECT COUNT(*) FROM messenger_friends f WHERE f.from_id=u.id OR f.to_id=u.id) c FROM users u ORDER BY c DESC LIMIT 5')->fetchAll();
    $topBB   = db()->query('SELECT username,battleball_points FROM users WHERE battleball_points>0 ORDER BY battleball_points DESC LIMIT 5')->fetchAll();
    $topSN   = db()->query('SELECT username,snowstorm_points FROM users WHERE snowstorm_points>0 ORDER BY snowstorm_points DESC LIMIT 5')->fetchAll();
    // Salles publiques (on masque les salles réservées au staff aux visiteurs non-staff)
    $viewerRank = 0;
    if ($mu = me()) { try { $rs = db()->prepare('SELECT `rank` FROM users WHERE id=?'); $rs->execute([(int)$mu['id']]); $viewerRank = (int)$rs->fetchColumn(); } catch (Throwable $e) {} }
    $roomSql = "SELECT r.name,r.description,r.visitors_now,r.visitors_max
                FROM rooms r LEFT JOIN rooms_categories c ON c.id=r.category
                WHERE r.owner_id='0' AND r.is_hidden=0";
    if ($viewerRank < 5) $roomSql .= " AND COALESCE(c.minrole_access,1) <= 1";
    $roomSql .= " ORDER BY r.visitors_now DESC, r.name";
    $rooms = db()->query($roomSql)->fetchAll();

    // Barre de recherche
    echo '<div class="panel"><div class="panel-h blue">Trouver un Habbo</div><div class="panel-b">';
    echo '<form method="get" class="hsearch"><input type="hidden" name="p" value="community"><input name="q" value="' . h($q) . '" placeholder="Nom d\'un Habbo…" autofocus><button class="hbtn green sm2">Rechercher</button></form>';
    if ($q !== '') {
        $st = db()->prepare('SELECT username,sex FROM users WHERE username LIKE ? ORDER BY username LIMIT 30');
        $st->execute(['%' . $q . '%']);
        $res = $st->fetchAll();
        if (!$res) echo '<p class="tip">Aucun Habbo trouvé pour « ' . h($q) . ' ».</p>';
        else { echo '<div class="friends" style="margin-top:12px">'; foreach ($res as $r) echo av_mini((string)$r['username'], (string)$r['sex']); echo '</div>'; }
    }
    echo '</div></div>';

    echo '<div class="cols">';

    // Colonne principale : Hall of Fame + salles
    echo '<div class="main">';
    echo '<div class="panel"><div class="panel-h orange">🏆 Hall of Fame</div><div class="panel-b"><div class="hof">';
    lead_board('💰 Plus riches', $topCred, 'credits');
    lead_board('💬 Plus d\'amis', $topFri, 'c');
    lead_board('🏐 BattleBall', $topBB, 'battleball_points');
    lead_board('❄️ SnowStorm', $topSN, 'snowstorm_points');
    echo '</div></div></div>';

    echo '<div class="panel"><div class="panel-h green">Salles publiques (' . count($rooms) . ')</div><div class="panel-b">';
    if (!$rooms) echo '<p class="tip">Aucune salle publique visible.</p>';
    else {
        echo '<div class="rooms">';
        foreach ($rooms as $r) {
            echo '<div class="rcard"><div class="ricon">🏛️</div><div class="rinfo"><b>' . h($r['name']) . '</b>';
            if (trim((string)$r['description']) !== '') echo '<span class="rd">' . h($r['description']) . '</span>';
            echo '<span class="rm">👤 ' . (int)$r['visitors_now'] . ' / ' . (int)$r['visitors_max'] . '</span></div></div>';
        }
        echo '</div>';
    }
    echo '</div></div>';
    echo '</div>';

    // Colonne latérale : staff, récemment vus, règles
    echo '<div class="side">';
    echo '<div class="panel"><div class="panel-h blue">Équipe de l\'hôtel</div><div class="panel-b">';
    if (!$staff) echo '<p class="tip">Aucun staff.</p>';
    else { echo '<div class="friends">'; foreach ($staff as $s) echo av_mini((string)$s['username'], (string)$s['sex']); echo '</div>'; }
    echo '</div></div>';

    echo '<div class="panel"><div class="panel-h purple">Récemment vus</div><div class="panel-b">';
    echo '<p class="tip" style="margin:0 0 8px"><b>' . $total . '</b> Habbos inscrits · <b>' . ($online !== false ? (int)$online : 0) . '</b> en ligne</p>';
    if ($recent) { echo '<div class="friends">'; foreach ($recent as $r) echo av_mini((string)$r['username'], (string)$r['sex']); echo '</div>'; }
    echo '</div></div>';

    echo '<div class="panel"><div class="panel-h orange">Bien vivre ensemble</div><div class="panel-b"><ul class="rules">';
    echo '<li>Respecte les autres Habbos.</li><li>Pas d\'insultes ni de spam.</li><li>Ne partage jamais ton mot de passe.</li><li>Amuse-toi et fais-toi des amis !</li>';
    echo '</ul></div></div>';
    echo '</div>';

    echo '</div>';
}

/* -------- Aide : centre d'aide type Habbo -------- */
function help_cats(): array {
    return [
        ['debut', '🚀', 'Premiers pas', [
            ['Comment créer mon Habbo ?', "Clique sur <b>« Crée ton Habbo »</b>, choisis un pseudo, un mot de passe et l'apparence (garçon ou fille). C'est gratuit et immédiat : tu reçois même 100 crédits de bienvenue pour démarrer."],
            ['Comment entrer dans l\'hôtel ?', "Connecte-toi sur le site puis clique sur <b>« Entre dans l'Hôtel »</b>. Le jeu s'ouvre dans le navigateur <b>Basilisk</b>, qui prend en charge le client de l'époque. Pense à ouvrir le site en <b>http://</b>."],
            ['Je débute, par où commencer ?', "Fais un tour dans les <b>salles publiques</b> pour rencontrer du monde, ajoute des amis, puis crée ta première chambre et décore-la avec des meubles achetés au Catalogue."],
        ]],
        ['compte', '👤', 'Mon compte', [
            ['Comment changer mon motto ?', "Va sur ta <b>page perso</b> (menu en haut) : tu peux modifier ton motto directement dans l'encart « Ma mission », puis clique sur Enregistrer."],
            ['Où voir ma page perso ?', "Clique sur <b>« Ma page »</b> en haut à droite quand tu es connecté. Tu y retrouves ton avatar, ton rang, tes badges, tes amis, tes chambres et tes scores."],
            ['J\'ai oublié mon mot de passe', "Sur cet hôtel privé, la récupération se fait à la main : contacte un <b>membre du staff</b> (rang Modérateur ou Administrateur) qui pourra réinitialiser ton mot de passe."],
        ]],
        ['credits', '💰', 'Crédits & Catalogue', [
            ['À quoi servent les crédits ?', "Les crédits sont la monnaie de l'hôtel. Ils te permettent d'acheter des <b>meubles (mobis)</b>, des animaux, des vêtements et l'abonnement au Habbo Club dans le Catalogue."],
            ['Comment acheter des meubles ?', "Ouvre le <b>Catalogue</b> en jeu, parcours les rubriques, clique sur un article puis sur « Acheter ». Le meuble arrive dans ton inventaire (la « main »), prêt à être posé dans ta chambre."],
            ['Qu\'est-ce que le Habbo Club ?', "Le <b>Habbo Club</b> est un abonnement qui donne accès à des meubles et vêtements exclusifs, des commandes bonus et un badge spécial affiché sur ta page perso."],
        ]],
        ['chambres', '🏠', 'Chambres & mobis', [
            ['Comment créer ma chambre ?', "Ouvre le <b>Navigateur</b>, va dans « Mes chambres » et crée une nouvelle chambre : choisis un nom, un modèle et c'est parti. Elle apparaît ensuite sur ta page perso."],
            ['Comment déplacer ou tourner un meuble ?', "Prends un meuble depuis ton inventaire et pose-le. Clique dessus pour le sélectionner : tu peux le <b>déplacer</b> puis le <b>tourner</b> avec le bouton prévu à cet effet."],
            ['Comment inviter des amis chez moi ?', "Depuis la console, sélectionne un ami connecté et invite-le, ou donne-lui le nom de ta chambre pour qu'il te rejoigne via le Navigateur."],
        ]],
        ['amis', '💬', 'Amis & messagerie', [
            ['Comment ajouter un ami ?', "Clique sur l'avatar d'un autre Habbo puis sur <b>« Ajouter comme ami »</b>. Une fois qu'il accepte, il apparaît dans ta console et sur ta page perso."],
            ['Comment envoyer un message ?', "Ouvre la <b>console</b> (l'icône messagerie), choisis un ami et écris-lui : il recevra ton message même s'il change de chambre."],
            ['Comment ignorer quelqu\'un ?', "Clique sur l'avatar de la personne puis sur <b>« Ignorer »</b> : tu ne verras plus ses messages. Tu peux annuler à tout moment avec « Ne plus ignorer »."],
        ]],
        ['jeux', '🎮', 'Jeux', [
            ['Comment jouer à BattleBall ?', "Rejoins une salle de <b>BattleBall</b>, place-toi dans une équipe et lance la partie : marche sur les cases pour les colorer à ta couleur. L'équipe qui en contrôle le plus gagne."],
            ['Comment jouer à SnowStorm ?', "Dans <b>SnowStorm</b>, ramasse ou fabrique des boules de neige et vise les adversaires tout en esquivant les leurs. Chaque touche rapporte des points."],
            ['Où voir les classements ?', "Rendez-vous sur l'onglet <b>Jeux</b> du site : les meilleurs joueurs de BattleBall et SnowStorm y sont classés, avec leurs points."],
        ]],
        ['securite', '🛡️', 'Conseils de sécurité', [
            ['Comment protéger mon compte ?', "Ne partage <b>jamais</b> ton mot de passe, même avec quelqu'un qui se présente comme modérateur. Le staff ne te demandera <b>jamais</b> ton mot de passe."],
            ['Comment signaler un abus ?', "Préviens un <b>membre du staff</b> présent en jeu, ou utilise le bouton d'aide en jeu. Décris précisément qui, où et ce qui s'est passé."],
            ['On me demande mes informations personnelles', "Ne donne jamais ton nom, ton adresse, ton numéro de téléphone ni ton mot de passe à un autre Habbo. En cas de doute, préviens le staff."],
        ]],
        ['regles', '📜', 'Règles de l\'hôtel', [
            ['Le règlement en bref', "Respecte les autres, <b>pas d'insultes</b>, pas de spam, pas d'arnaque. Amuse-toi et aide les nouveaux : un hôtel sympa, c'est grâce à toi !"],
            ['Ce qui est interdit', "Insultes et harcèlement, contenu choquant, usurpation d'identité (staff compris), publicité pour d'autres hôtels, triche et arnaques aux mobis/crédits."],
            ['Que risque-t-on ?', "Selon la gravité : avertissement, exclusion temporaire (mute/kick) puis <b>bannissement</b>. Les décisions du staff s'appliquent à tout l'hôtel."],
        ]],
    ];
}
function view_help(): void {
    $cats = help_cats();
    $palette = ['blue', 'purple', 'orange', 'green', 'blue', 'purple', 'green', 'orange'];

    // Hero
    echo '<div class="help-hero"><div class="hh-ico">?</div><div class="hh-txt"><h1>Centre d\'aide</h1><p>Trouve vite une réponse : choisis un thème ci-dessous, ou parcours les questions fréquentes.</p></div></div>';

    // Cartes de catégories
    echo '<div class="help-cards">';
    foreach ($cats as $i => $c) {
        $col = $palette[$i % count($palette)];
        $nb = count($c[3]);
        echo '<a class="hcard ' . $col . '" href="#' . $c[0] . '"><span class="hc-ico">' . $c[1] . '</span>'
           . '<span class="hc-body"><b>' . h($c[2]) . '</b><small>' . $nb . ' article' . ($nb > 1 ? 's' : '') . '</small></span></a>';
    }
    echo '</div>';

    // Sections d'articles
    foreach ($cats as $i => $c) {
        $col = $palette[$i % count($palette)];
        echo '<section class="help-sec" id="' . $c[0] . '"><div class="hs-head ' . $col . '"><span class="hs-badge">' . $c[1] . '</span><h2>' . h($c[2]) . '</h2></div><div class="hs-body">';
        foreach ($c[3] as $qa) {
            echo '<details class="faqd"><summary>' . h($qa[0]) . '</summary><div class="faqa">' . $qa[1] . '</div></details>';
        }
        echo '</div></section>';
    }

    // Pied : encore besoin d'aide ?
    echo '<div class="help-foot"><div><b>Tu n\'as pas trouvé ta réponse ?</b><p>L\'équipe de l\'hôtel est là pour t\'aider.</p></div>'
       . '<div class="hf-btns"><a class="hbtn" href="?p=contact">Contacter l\'équipe</a><a class="hbtn grey" href="?p=forgot">Mot de passe oublié</a></div></div>';
}

/* -------- Contacter l'équipe -------- */
function view_contact(): void {
    $staff = [];
    try { $staff = db()->query('SELECT username,sex,`rank` FROM users WHERE `rank`>=5 ORDER BY `rank` DESC, username')->fetchAll(); } catch (Throwable $e) {}
    $done = !empty($_SESSION['contact_done']); unset($_SESSION['contact_done']);
    $cerr = (string)($_SESSION['contact_err'] ?? ''); unset($_SESSION['contact_err']);
    $meName = me()['username'] ?? '';
    echo '<div class="help-hero"><div class="hh-ico">✉</div><div class="hh-txt"><h1>Contacter l\'équipe</h1><p>Besoin d\'aide ? Écris-nous, ou joins l\'équipe en jeu.</p></div></div>';

    if ($done) echo '<div class="fmsg ok">✅ <b>Message envoyé !</b> L\'équipe le lira depuis l\'administration et te répondra en jeu dès que possible.</div>';
    elseif ($cerr !== '') echo '<div class="fmsg err">⚠️ ' . h($cerr) . '</div>';

    echo '<div class="cols">';

    echo '<div class="main">';
    // Formulaire d'envoi de message
    echo '<div class="panel"><div class="panel-h blue">✍️ Écrire à l\'équipe</div><div class="panel-b">';
    echo '<form method="post" action="?p=contact"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
    echo '<label>Ton pseudo</label><input name="username" maxlength="20" value="' . h($meName) . '" placeholder="Ton pseudo Habbo"' . ($meName !== '' ? ' readonly' : ' required') . '>';
    echo '<label>Sujet (facultatif)</label><input name="subject" maxlength="100" placeholder="Ex : problème dans une salle, question sur le Club…">';
    echo '<label>Ton message</label><textarea name="message" rows="4" maxlength="1000" required placeholder="Explique ta demande le plus clairement possible."></textarea>';
    echo '<button class="hbtn blue big" type="submit">Envoyer le message</button>';
    echo '</form>';
    echo '</div></div>';

    echo '<div class="panel"><div class="panel-h green">🆘 De l\'aide tout de suite</div><div class="panel-b">';
    echo '<ol class="hcsteps">';
    echo '<li>En jeu, clique sur le <b>point d\'interrogation</b> pour ouvrir le menu d\'aide.</li>';
    echo '<li>Utilise <b>« Obtenir de l\'aide en direct »</b> pour appeler un membre de l\'équipe.</li>';
    echo '<li>Ou adresse-toi directement à un <b>modérateur/administrateur</b> présent dans une salle.</li>';
    echo '</ol>';
    echo '<p class="tip">Tu peux aussi consulter le <a href="?p=help">Centre d\'aide</a> : la plupart des questions y trouvent une réponse.</p>';
    echo '</div></div>';

    echo '<div class="panel"><div class="panel-h orange">🔑 Mot de passe oublié</div><div class="panel-b">';
    echo '<p class="tip">Sur cet hôtel privé, la récupération se fait <b>à la main</b> : contacte un membre du staff (rang Modérateur ou Administrateur) qui pourra réinitialiser ton mot de passe. Ne communique jamais ton mot de passe à qui que ce soit.</p>';
    echo '</div></div>';
    echo '</div>'; // .main

    echo '<div class="side"><div class="panel"><div class="panel-h purple">L\'équipe</div><div class="panel-b">';
    if (!$staff) echo '<p class="tip">Aucun membre du staff pour le moment.</p>';
    else { echo '<div class="hcgifts" style="grid-template-columns:1fr">'; foreach ($staff as $s) echo av_mini((string)$s['username'], (string)$s['sex']); echo '</div>'; }
    echo '</div></div></div>';
    echo '</div>';
}

/* -------- Mot de passe oublié -------- */
function view_forgot(): void {
    $staff = [];
    try { $staff = db()->query('SELECT username,sex,`rank` FROM users WHERE `rank`>=5 ORDER BY `rank` DESC, username')->fetchAll(); } catch (Throwable $e) {}
    $done = !empty($_SESSION['forgot_done']); unset($_SESSION['forgot_done']);
    $ferr = (string)($_SESSION['forgot_err'] ?? ''); unset($_SESSION['forgot_err']);
    echo '<div class="help-hero orange"><div class="hh-ico">🔑</div><div class="hh-txt"><h1>Mot de passe oublié ?</h1><p>Pas de panique ! Sur ' . HOTEL . ', la réinitialisation se fait <b>à la main</b> par l\'équipe.</p></div></div>';

    if ($done) echo '<div class="fmsg ok">✅ <b>Demande envoyée !</b> Un membre de l\'équipe va la traiter et te communiquera un mot de passe provisoire. Repasse voir le staff en jeu ou via la page Contact.</div>';
    elseif ($ferr !== '') echo '<div class="fmsg err">⚠️ ' . h($ferr) . '</div>';

    echo '<div class="cols">';

    echo '<div class="main">';
    // Formulaire de demande
    echo '<div class="panel"><div class="panel-h orange">Demander une réinitialisation</div><div class="panel-b">';
    echo '<form method="post" action="?p=forgot"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
    echo '<label>Ton pseudo Habbo</label><input name="username" maxlength="20" placeholder="Ton pseudo exact" required>';
    echo '<label>Un indice pour l\'équipe (facultatif)</label><input name="message" maxlength="200" placeholder="Ex : date de création, dernier mobi acheté, un ami…">';
    echo '<button class="hbtn orange big" type="submit">Envoyer ma demande</button>';
    echo '</form>';
    echo '<p class="tip" style="margin-top:10px">Donne un indice qui prouve que le compte est bien le tien : ça accélère le traitement par le staff.</p>';
    echo '</div></div>';

    echo '<div class="panel"><div class="panel-h green">Comment ça marche</div><div class="panel-b"><ol class="hcsteps">';
    echo '<li>Tu envoies ta demande avec ton <b>pseudo exact</b> ci-dessus.</li>';
    echo '<li>Un <b>modérateur ou administrateur</b> vérifie et te fixe un <b>mot de passe provisoire</b>.</li>';
    echo '<li>Tu te connectes avec ce mot de passe, puis tu le <b>changes aussitôt</b>.</li>';
    echo '</ol>';
    echo '<p class="tip">Tu peux aussi joindre l\'équipe en jeu (bouton d\'aide) ou via la page <a href="?p=contact">Contact</a>.</p>';
    echo '</div></div>';

    echo '<div class="panel"><div class="panel-h red">⚠️ Sécurité</div><div class="panel-b">';
    echo '<p class="tip">Un membre de l\'équipe ne te demandera <b>jamais</b> ton mot de passe. Ne le communique à personne, même à quelqu\'un qui prétend faire partie du staff.</p>';
    echo '</div></div>';
    echo '</div>'; // .main

    echo '<div class="side"><div class="panel"><div class="panel-h purple">L\'équipe</div><div class="panel-b">';
    if (!$staff) echo '<p class="tip">Aucun membre du staff pour le moment.</p>';
    else { echo '<div class="hcgifts" style="grid-template-columns:1fr">'; foreach ($staff as $s) echo av_mini((string)$s['username'], (string)$s['sex']); echo '</div>'; }
    echo '</div></div>';
    echo '<div class="panel"><div class="panel-h orange">Déjà ton mot de passe ?</div><div class="panel-b center"><a class="hbtn green" href="?p=login">Se connecter</a></div></div>';
    echo '</div>';
    echo '</div>';
}

/* -------- Mes messages (reçus du staff) -------- */
function view_messages(): void {
    $u = me();
    echo '<div class="help-hero"><div class="hh-ico">✉</div><div class="hh-txt"><h1>Mes messages</h1><p>Les messages de l\'équipe de l\'hôtel.</p></div></div>';
    if (!$u) { echo '<div class="panel"><div class="panel-b center"><p class="tip">Connecte-toi pour voir tes messages.</p><a class="hbtn green" href="?p=home">Se connecter</a></div></div>'; return; }
    ensure_user_msgs();
    $list = db()->prepare('SELECT id,from_staff,subject,body,read_at,created_at FROM user_messages WHERE user_id=? ORDER BY id DESC'); $list->execute([(int)$u['id']]); $msgs = $list->fetchAll();
    try { db()->prepare('UPDATE user_messages SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([(int)$u['id']]); } catch (Throwable $e) {}
    if (!$msgs) { echo '<div class="panel"><div class="panel-b center"><p class="tip">📭 Tu n\'as aucun message pour le moment.</p></div></div>'; return; }
    echo '<div class="msglist">';
    foreach ($msgs as $m) {
        $new = empty($m['read_at']);
        echo '<div class="msgcard' . ($new ? ' unread' : '') . '"><div class="mc-head"><span class="mc-from">👤 ' . h((string)($m['from_staff'] ?: 'Équipe')) . '</span>' . ($new ? '<span class="mc-new">Nouveau</span>' : '') . '<span class="mc-date">' . h(date('d/m/Y H:i', strtotime((string)$m['created_at']))) . '</span></div>';
        if (trim((string)$m['subject']) !== '') echo '<div class="mc-subj">' . h((string)$m['subject']) . '</div>';
        echo '<div class="mc-body">' . nl2br(h((string)$m['body'])) . '</div></div>';
    }
    echo '</div>';
}

/* -------- Page perso : Habbo Home complet -------- */
/* Avatar « façon Habbo » en SVG (déterministe selon le pseudo — pas de dépendance, pas d'imager) */
function av_palette(string $name): array {
    $x = abs(crc32(mb_strtolower($name)));
    $skin  = ['#f6cfa8', '#eebd93', '#dda475', '#c48a5a', '#a06a3f'];
    $hair  = ['#2c1e12', '#5a3a1e', '#8a5a2a', '#c98a3a', '#e0c060', '#141414', '#b23b2e', '#7a4a8a'];
    $shirt = ['#4a92c8', '#8fc94a', '#e5820c', '#a87fce', '#e0577a', '#38b2a3', '#e6c23a', '#5a6bd8'];
    return ['skin' => $skin[$x % 5], 'hair' => $hair[intdiv($x, 5) % 8], 'shirt' => $shirt[intdiv($x, 40) % 8]];
}
function av_svg(string $name, string $sex): string {
    $p = av_palette($name); $sk = $p['skin']; $hr = $p['hair']; $sh = $p['shirt'];
    $side = ($sex === 'F') ? '<rect x="16" y="25" width="5.5" height="20" rx="2.7" fill="' . $hr . '"/><rect x="42.5" y="25" width="5.5" height="20" rx="2.7" fill="' . $hr . '"/>' : '';
    return '<svg viewBox="0 0 64 64" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">'
        . '<rect x="14" y="53" width="36" height="15" rx="8" fill="' . $sh . '"/>'
        . '<rect x="28" y="44" width="8" height="11" rx="3" fill="' . $sk . '"/>'
        . $side
        . '<circle cx="17" cy="32" r="3" fill="' . $sk . '"/><circle cx="47" cy="32" r="3" fill="' . $sk . '"/>'
        . '<rect x="18" y="13" width="28" height="34" rx="12" fill="' . $sk . '"/>'
        . '<path d="M18 27 v-2 a14 14 0 0 1 28 0 v2 c-5 -5 -10 -6 -14 -6 s-9 1 -14 6 z" fill="' . $hr . '"/>'
        . '<circle cx="27" cy="31" r="2.3" fill="#3a2a1e"/><circle cx="37" cy="31" r="2.3" fill="#3a2a1e"/>'
        . '<path d="M28 38 q4 3 8 0" stroke="#c07a5e" stroke-width="1.8" fill="none" stroke-linecap="round"/>'
        . '</svg>';
}
function av_mini(string $name, string $sex): string {
    return '<a class="friend" href="?p=profile&u=' . h(rawurlencode($name)) . '"><span class="av mini hd">' . av_svg($name, $sex) . '</span><span class="fn">' . h($name) . '</span></a>';
}
function stars(int $n): string { $n = max(0, min(5, $n)); return '<span class="stars">' . str_repeat('★', $n) . str_repeat('☆', 5 - $n) . '</span>'; }

function view_profile(): void {
    $cols = 'id,username,motto,figure,sex,`rank`,last_online,created_at,credits,tickets,film,badge,badge_active,club_expiration,battleball_points,snowstorm_points';
    if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {   // lien "page perso" du jeu : ?id=%ID%
        $st = db()->prepare('SELECT ' . $cols . ' FROM users WHERE id=?');
        $st->execute([(int)$_GET['id']]);
    } else {
        $name = trim($_GET['u'] ?? (me()['username'] ?? ''));
        $st = db()->prepare('SELECT ' . $cols . ' FROM users WHERE username=?');
        $st->execute([$name]);
    }
    $u = $st->fetch();
    $name = $u['username'] ?? ($name ?? '');
    if (!$u) { echo '<div class="cols one"><div class="panel"><div class="panel-h blue">Page perso</div><div class="panel-b"><p class="tip">Le Habbo « ' . h($name) . ' » est introuvable. <a href="?p=home">Retour à l\'accueil</a></p></div></div></div>'; return; }

    $me    = me();
    $isOwn = $me && $me['username'] === $u['username'];
    $ranks = ranks_fr();
    $rank  = (int)$u['rank'];
    $sexc  = $u['sex'] === 'F' ? 'f' : 'm';

    $seen  = (int)$u['last_online'] > 0 ? date('d/m/Y à H\hi', (int)$u['last_online']) : '—';
    $since = $u['created_at'] ? date('d/m/Y', strtotime((string)$u['created_at'])) : '—';
    $days  = $u['created_at'] ? max(0, (int)floor((time() - strtotime((string)$u['created_at'])) / 86400)) : 0;
    $hc    = (int)$u['club_expiration'] > time();

    // badges
    $rankB = []; $rb = db()->prepare('SELECT badge FROM rank_badges WHERE `rank`=?'); $rb->execute([$rank]); foreach ($rb as $b) $rankB[] = $b['badge'];
    $ownB  = []; $bs = db()->prepare('SELECT badge FROM users_badges WHERE user_id=?'); $bs->execute([(int)$u['id']]); foreach ($bs as $b) $ownB[] = $b['badge'];
    $badges = array_values(array_unique(array_merge($rankB, $ownB)));
    $worn   = ($u['badge_active'] && trim((string)$u['badge']) !== '') ? trim((string)$u['badge']) : '';

    // amis
    $fc = db()->prepare('SELECT COUNT(*) FROM messenger_friends WHERE from_id=? OR to_id=?'); $fc->execute([(int)$u['id'], (int)$u['id']]);
    $friendCount = (int)$fc->fetchColumn();
    $friends = [];
    $fq = db()->prepare('SELECT us.username, us.sex FROM messenger_friends f JOIN users us ON us.id = IF(f.from_id=?, f.to_id, f.from_id) WHERE f.from_id=? OR f.to_id=? ORDER BY us.username LIMIT 18');
    $fq->execute([(int)$u['id'], (int)$u['id'], (int)$u['id']]);
    foreach ($fq as $f) $friends[] = $f;

    // salles
    $rq = db()->prepare('SELECT name,description,rating,visitors_max FROM rooms WHERE owner_id=? ORDER BY rating DESC, name');
    $rq->execute([(int)$u['id']]);
    $rooms = $rq->fetchAll();

    echo '<div class="cols">';

    /* ---------- Colonne gauche : carte d'identité ---------- */
    echo '<div class="side">';
    echo '<div class="panel idcard">';
    echo '  <div class="panel-h blue">' . h($u['username']) . '</div>';
    echo '  <div class="panel-b center">';
    echo '    <div class="av big hd">' . av_svg((string)$u['username'], (string)$u['sex']);
    if ($worn) echo '<img class="worn" src="/c_images/badges/' . h($worn) . '.gif" title="Badge porté : ' . h($worn) . '" onerror="this.style.display=\'none\'">';
    echo '    </div>';
    echo '    <div class="rankpill ' . ($rank >= 5 ? 'staff' : '') . '">' . h($ranks[$rank] ?? 'Habbo') . '</div>';
    if ($hc) echo '    <div class="hcbadge">★ Habbo Club</div>';
    echo '  </div>';
    echo '</div>';
    if ($isOwn) {
        echo '<a class="hbtn green big" href="?p=play">Entre dans l\'Hôtel</a>';
    } else {
        echo '<a class="hbtn" href="?p=home">Retour à l\'accueil</a>';
    }
    echo '</div>';

    /* ---------- Colonne principale ---------- */
    echo '<div class="main">';

    // Motto (+ édition si c'est ma page)
    echo '<div class="panel"><div class="panel-h orange">' . ($isOwn ? 'Ma mission' : 'Mission') . '</div><div class="panel-b">';
    if (isset($_GET['saved'])) echo '<div class="flash ok">Ton motto a été mis à jour !</div>';
    echo '<p class="bigmotto">&laquo; ' . (trim((string)$u['motto']) !== '' ? h($u['motto']) : '<i>Pas encore de motto…</i>') . ' &raquo;</p>';
    if ($isOwn) {
        echo '<form method="post" action="?p=profile&u=' . h(rawurlencode($u['username'])) . '" class="mottoform">' . csrf_f();
        echo '<input name="motto" maxlength="100" value="' . h($u['motto']) . '" placeholder="Écris ton motto…">';
        echo '<button class="hbtn green sm2">Enregistrer</button></form>';
    }
    echo '</div></div>';

    // En bref
    echo '<div class="panel"><div class="panel-h blue">En bref</div><div class="panel-b">';
    echo '<ul class="statl wide">';
    echo '<li><span>Habbo depuis</span><b>' . $since . ' (' . $days . ' j)</b></li>';
    echo '<li><span>Dernière visite</span><b>' . $seen . '</b></li>';
    echo '<li><span>Sexe</span><b>' . ($u['sex'] === 'F' ? 'Fille' : 'Garçon') . '</b></li>';
    echo '<li><span>Rang</span><b>' . h($ranks[$rank] ?? 'Habbo') . '</b></li>';
    echo '</ul></div></div>';

    // Le coffre (ma page uniquement)
    if ($isOwn) {
        echo '<div class="panel"><div class="panel-h green">Mon coffre</div><div class="panel-b"><div class="coffre">';
        echo '<div class="coin cr"><b>' . (int)$u['credits'] . '</b><span>Crédits</span></div>';
        echo '<div class="coin tk"><b>' . (int)$u['tickets'] . '</b><span>Tickets</span></div>';
        echo '<div class="coin fl"><b>' . (int)$u['film'] . '</b><span>Pellicules</span></div>';
        echo '</div></div></div>';
    }

    // Jeux
    echo '<div class="panel"><div class="panel-h purple">Scores de jeux</div><div class="panel-b"><div class="games">';
    echo '<div class="gcard bb"><span class="gi">🏐</span><div><b>' . (int)$u['battleball_points'] . '</b> pts<span>BattleBall</span></div></div>';
    echo '<div class="gcard sn"><span class="gi">❄️</span><div><b>' . (int)$u['snowstorm_points'] . '</b> pts<span>SnowStorm</span></div></div>';
    echo '</div></div></div>';

    // Badges
    echo '<div class="panel"><div class="panel-h orange">Badges (' . count($badges) . ')</div><div class="panel-b">';
    if (!$badges) echo '<p class="tip">Aucun badge pour le moment.</p>';
    else {
        echo '<div class="bgrid">';
        foreach ($badges as $b) {
            $hl = ($b === $worn) ? ' worn' : '';
            echo '<div class="bcell' . $hl . '" title="' . h($b) . ($b === $worn ? ' (porté)' : '') . '"><img src="/c_images/badges/' . h($b) . '.gif" alt="' . h($b) . '" onerror="this.parentNode.style.display=\'none\'"></div>';
        }
        echo '</div>';
    }
    echo '</div></div>';

    // Amis
    echo '<div class="panel"><div class="panel-h blue">Amis (' . $friendCount . ')</div><div class="panel-b">';
    if (!$friends) echo '<p class="tip">' . ($isOwn ? 'Tu n\'as pas encore d\'amis — ajoute-les en jeu !' : 'Aucun ami pour le moment.') . '</p>';
    else {
        echo '<div class="friends">';
        foreach ($friends as $f) echo av_mini((string)$f['username'], (string)$f['sex']);
        echo '</div>';
        if ($friendCount > count($friends)) echo '<p class="tip">… et ' . ($friendCount - count($friends)) . ' autre(s).</p>';
    }
    echo '</div></div>';

    // Appartements
    echo '<div class="panel"><div class="panel-h green">Appartements (' . count($rooms) . ')</div><div class="panel-b">';
    if (!$rooms) echo '<p class="tip">' . ($isOwn ? 'Tu n\'as pas encore de chambre — crée-la en jeu !' : 'Aucun appartement.') . '</p>';
    else {
        echo '<div class="rooms">';
        foreach ($rooms as $r) {
            echo '<div class="rcard"><div class="ricon">🚪</div><div class="rinfo"><b>' . h($r['name']) . '</b>';
            if (trim((string)$r['description']) !== '') echo '<span class="rd">' . h($r['description']) . '</span>';
            echo '<span class="rm">' . stars((int)$r['rating']) . ' · ' . (int)$r['visitors_max'] . ' places</span></div></div>';
        }
        echo '</div>';
    }
    echo '</div></div>';

    echo '</div>'; // .main
    echo '</div>'; // .cols
}

/* =========================== GABARIT =========================== */
function head(string $pg): void {
    $u = me();
    $credits = null; $rank = 0;
    if ($u) { try { $st = db()->prepare('SELECT credits,`rank` FROM users WHERE id=?'); $st->execute([(int)$u['id']]); if ($row = $st->fetch()) { $credits = (int)$row['credits']; $rank = (int)$row['rank']; } } catch (Throwable $e) {} }
    $isStaff = $rank >= 5; // modérateurs + admins
    $unread = $u ? unread_msgs((int)$u['id']) : 0;
    $tabs = [
        'home'     => ['Accueil', '?p=home'],
        'play'     => ['Hôtel', '?p=play'],
        'news'     => ['Actualités', '?p=news'],
        'games'    => ['Jeux', '?p=games'],
        'club'     => ['⭐ Club', '?p=club'],
        'community'=> ['Communauté', '?p=community'],
        'help'     => ['Aide', '?p=help'],
    ];
    if ($isStaff) $tabs['admin'] = ['🛠 Admin', '/admin/'];
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= HOTEL ?> — l'Hôtel où on se retrouve</title>
<link rel="icon" href="/web-gallery/v2/favicon.ico">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font:13px/1.5 Verdana,Geneva,Arial,sans-serif;color:#4a4a4a;background:#e9e4d6;
     background-image:linear-gradient(#cfe3f2,#cfe3f2 120px,#e9e4d6 120px,#e9e4d6);}
a{color:#2f6f9f;text-decoration:none}a:hover{text-decoration:underline}
.page{width:800px;max-width:100%;margin:0 auto;padding:0 10px 40px}
/* En-tête */
header.top{display:flex;align-items:center;justify-content:space-between;padding:16px 4px 10px}
.logo{display:block;line-height:1}
.logo-img{display:block;height:80px;width:auto;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated}
.logo small{display:block;font:700 11px Verdana;letter-spacing:.5px;color:#1c5580;margin-top:3px}
.uinfo{background:#fff;border:1px solid #cfc6ad;border-radius:8px;padding:8px 12px;font-size:12px;text-align:right;box-shadow:0 2px 0 rgba(0,0,0,.06)}
.uinfo .cr{color:#c98a00;font-weight:700}
.uinfo a{font-weight:700}
/* Barre d'onglets */
nav.tabs{display:flex;background:#2f6f9f;border-radius:8px 8px 0 0;padding:6px 6px 0;box-shadow:inset 0 -3px 0 rgba(0,0,0,.15)}
nav.tabs a{color:#dcefff;font-weight:700;font-size:12px;padding:9px 16px;border-radius:7px 7px 0 0}
nav.tabs a:hover{background:#3f82b5;text-decoration:none}
nav.tabs a.on{background:#e9e4d6;color:#2f6f9f}
nav.tabs a[href="/admin/"]{background:#e5820c;color:#fff;margin-left:auto}
nav.tabs a[href="/admin/"]:hover{background:#f7a838}
.uinfo .adminlink{color:#c26a00}
/* Zone contenu */
.body{background:#fff;border:1px solid #cfc6ad;border-top:0;border-radius:0 0 8px 8px;padding:16px;box-shadow:0 3px 10px rgba(0,0,0,.12)}
.cols{display:flex;align-items:flex-start}
.cols>*+*{margin-left:16px} /* remplace gap:16px (non supporté par Basilisk/Goanna) */
.cols.one{max-width:460px;margin:0 auto}
.main{flex:1;min-width:0}
.side{width:250px;flex:0 0 250px;min-width:0}
/* Panneaux façon Habbo */
.panel{border:1px solid #d7d0bd;border-radius:9px;overflow:hidden;margin-bottom:16px;background:#fff}
.panel-h{color:#fff;font-weight:700;font-size:13px;padding:8px 12px;text-shadow:0 1px 0 rgba(0,0,0,.2)}
.panel-h.orange{background:linear-gradient(#f7a838,#ef8f13)}
.panel-h.blue{background:linear-gradient(#4a92c8,#2f6f9f)}
.panel-h.green{background:linear-gradient(#8fc94a,#6ba62f)}
.panel-h.purple{background:linear-gradient(#a87fce,#7d52a8)}
.panel-h.grey{background:linear-gradient(#9a9a9a,#7a7a7a)}
.panel-h.red{background:linear-gradient(#e0577a,#c23a5c)}
.fmsg{border-radius:11px;padding:14px 16px;margin-bottom:16px;font-size:13px;border:1px solid}
.fmsg.ok{background:#eef7e2;border-color:#bfe08f;color:#3f6b16}
.fmsg.err{background:#fdeaee;border-color:#f0b6c2;color:#a33}
.forgot-link{display:block;text-align:center;margin-top:9px;font-size:11.5px;font-weight:700;color:#8a857a}
.forgot-link:hover{color:#2f6f9f;text-decoration:underline}
.msgbadge{display:inline-block;background:#e0577a;color:#fff;font-size:10px;font-weight:800;min-width:15px;text-align:center;padding:0 5px;border-radius:9px;vertical-align:1px}
.msglist>*+*{margin-top:12px}
.msgcard{border:1px solid #e2ddcd;border-radius:11px;background:#fff;padding:14px 16px;box-shadow:0 2px 6px rgba(0,0,0,.05)}
.msgcard.unread{border-left:4px solid #4a92c8;background:#f4f9fd}
.msgcard .mc-head{display:flex;align-items:center;font-size:12px;color:#8a857a}
.msgcard .mc-head>*+*{margin-left:8px}
.msgcard .mc-from{font-weight:700;color:#2f6f9f}
.msgcard .mc-new{background:#4a92c8;color:#fff;font-weight:700;font-size:10px;padding:1px 7px;border-radius:8px}
.msgcard .mc-date{margin-left:auto}
.msgcard .mc-subj{font-weight:700;color:#3a3a3a;margin-top:6px}
.msgcard .mc-body{color:#5b5b5b;font-size:13px;margin-top:5px;line-height:1.5}
.panel-b{padding:14px}
.panel-b.center{text-align:center}
.hotel .panel-b{padding:0}
.hotelimg{display:block;width:100%;height:auto;background:#bfe3f5}
.pitch{padding:14px 16px 4px;color:#5b5b5b}
.hotel .hbtn{margin:14px 16px 18px}
/* Boutons */
.hbtn{display:block;text-align:center;font-weight:700;font-size:13px;color:#fff;padding:11px 14px;border-radius:8px;border:0;width:100%;cursor:pointer;margin-top:10px;
      background:linear-gradient(#4a92c8,#2f6f9f);box-shadow:0 2px 0 rgba(0,0,0,.18);font-family:inherit}
.hbtn:hover{filter:brightness(1.06);text-decoration:none}
.hbtn[href="?p=play"]::before{content:"";display:inline-block;width:20px;height:20px;margin-right:8px;vertical-align:-5px;background:url(/web-gallery/v2/images/hotel-button-hotelopen.png) no-repeat center;background-size:contain}
.hbtn.green{background:linear-gradient(#8fc94a,#5f9a27)}
.hbtn.orange{background:linear-gradient(#f7a838,#e5820c)}
.hbtn.grey{background:linear-gradient(#b7b7b7,#8f8f8f)}
.hbtn.big{font-size:15px;padding:13px}
/* Formulaires */
label{display:block;font-weight:700;color:#6b6b6b;margin:10px 0 3px;font-size:12px}
input[type=text],input:not([type]),input[type=password],textarea{width:100%;border:1px solid #cfc6ad;background:#fbfaf5;border-radius:7px;padding:9px 10px;font:inherit}
textarea{resize:vertical;min-height:84px}
input:focus,textarea:focus{outline:none;border-color:#4a92c8;background:#fff}
.sexpick{display:flex}
.sx{flex:1;border:1px solid #cfc6ad;border-radius:7px;padding:9px;text-align:center;cursor:pointer;font-weight:700;color:#5b5b5b;margin:0}
.sx input{margin-right:5px}
/* Actus */
.news{display:flex;flex-wrap:wrap}
.ncard{flex:1;min-width:150px;border:1px solid #e2ddcd;border-radius:9px;padding:12px;background:#fbfaf5}
.ncard h3{font-size:13px;color:#3a3a3a;margin:8px 0 4px}
.ncard p{font-size:11.5px;color:#6b6b6b}
.ntag{display:inline-block;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px}
.ntag.blue{background:#2f6f9f}.ntag.green{background:#6ba62f}.ntag.purple{background:#7d52a8}.ntag.orange{background:#e5820c}
.nrow .nt{font-size:14px;color:#3a3a3a;margin:6px 0 3px}
/* Bloc actus accueil (à la une + mini) */
.newspanel .panel-b{padding:12px}
.nfeat{border:1px solid #e2ddcd;border-left-width:5px;border-radius:8px;padding:12px 14px;background:#fbfaf5}
.nfeat.blue{border-left-color:#2f6f9f}.nfeat.green{border-left-color:#6ba62f}.nfeat.purple{border-left-color:#7d52a8}.nfeat.orange{border-left-color:#e5820c}
.nfeat-h{display:flex;align-items:center;margin-bottom:6px}
.nfeat-h>*+*{margin-left:8px}
.nfeat h3{font-size:15px;color:#33475b;margin:2px 0 4px}
.nfeat p{font-size:12px;color:#5b5b5b;line-height:1.55}
.ndate{font-size:10.5px;color:#a59f8d;font-weight:700}
.newsmini{margin-top:10px;border-top:1px dotted #e2ddcd;padding-top:8px}
.nmini{display:flex;align-items:center;padding:7px 4px;border-bottom:1px dotted #ece7d7;color:#3a3a3a}
.nmini>*+*{margin-left:8px}
.nmini:last-child{border-bottom:0}
.nmini:hover{background:#f6f2e6;text-decoration:none;border-radius:6px}
.nmini-t{flex:1;font-size:12px;font-weight:700;color:#33475b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ndot{width:9px;height:9px;border-radius:50%;flex:0 0 auto}
.ndot.blue{background:#2f6f9f}.ndot.green{background:#6ba62f}.ndot.purple{background:#7d52a8}.ndot.orange{background:#e5820c}
.newsall{display:inline-block;margin-top:10px;font-weight:700;font-size:12px;color:#e5820c}
/* Divers */
.flash{padding:9px 11px;border-radius:7px;margin-bottom:10px;font-size:12px}
.flash.err{background:#fbe3e3;border:1px solid #e6a9a9;color:#b23}
.flash.ok{background:#e6f6ea;border:1px solid #a6d8b4;color:#2c7a44}
.tip{font-size:11.5px;color:#8a8a7a;margin-top:10px}
.statl{list-style:none}
.statl li{padding:6px 0;border-bottom:1px dotted #e2ddcd;font-size:12px}
.statl li:last-child{border:0}
.statl b{color:#2f6f9f}
.statl.wide li{display:flex;justify-content:space-between}
.statl.wide span{color:#8a8a7a}
/* En ce moment dans l'hôtel */
.rn-online{display:flex;align-items:center;font-size:12px;color:#4a4a4a;padding-bottom:9px;border-bottom:1px dotted #e2ddcd;margin-bottom:9px}
.rn-online>*+*{margin-left:8px}
.rn-online img{width:14px;height:14px}
.rn-online b{color:#6ba62f;font-size:15px}
.rn-room{display:flex;align-items:center}
.rn-room>*+*{margin-left:10px}
.rn-star{color:#f7a838;font-size:20px;line-height:1}
.rn-room .rinfo b{font-size:12.5px;color:#33475b}
.rn-room .rm{display:block;font-size:11px;color:#8a8a7a;margin-top:2px}
/* Profil — Habbo Home */
.av{border-radius:12px;display:grid;place-items:center;border:3px solid #fff;box-shadow:0 0 0 1px #cfc6ad;position:relative}
.av span{font-weight:900;color:#fff;text-shadow:1px 1px 0 rgba(0,0,0,.25);font-family:'Trebuchet MS',Arial}
.av.m{background:linear-gradient(#5aa0d6,#2f6f9f)}
.av.f{background:linear-gradient(#e78bc0,#c74f97)}
.av.hd{background:linear-gradient(#eaf5fd,#cfe7f7);overflow:hidden}
.av.hd svg{display:block;width:100%;height:100%}
.av.big{width:96px;height:96px;margin:0 auto 10px}.av.big span{font-size:42px}
.av.mini{width:38px;height:38px;border-width:2px;border-radius:9px}.av.mini span{font-size:17px}
.av .worn{position:absolute;right:-8px;bottom:-8px;width:34px;height:34px;background:#fff;border:1px solid #cfc6ad;border-radius:8px;padding:2px}
.rankpill{display:inline-block;background:#2f6f9f;color:#fff;font-size:11px;font-weight:700;padding:3px 12px;border-radius:11px}
.rankpill.staff{background:linear-gradient(#f7a838,#e5820c)}
.hcbadge{display:inline-block;margin-top:8px;background:linear-gradient(#3a3a3a,#111);color:#ffcf3f;font-size:11px;font-weight:700;padding:3px 12px;border-radius:11px;border:1px solid #ffcf3f}
.bigmotto{font-size:16px;color:#3a3a3a;font-style:italic;text-align:center;padding:6px 0}
.mottoform{display:flex;margin-top:6px}.mottoform input{flex:1}
.hbtn.sm2{width:auto;margin:0;padding:9px 16px;white-space:nowrap}
.coffre{display:flex;text-align:center}
.coin{flex:1;border:1px solid #e2ddcd;border-radius:9px;padding:12px 6px;background:#fbfaf5}
.coin b{display:block;font-size:22px}.coin span{font-size:11px;color:#8a8a7a}
.coin.cr b{color:#c98a00}.coin.tk b{color:#2f6f9f}.coin.fl b{color:#7d52a8}
.games{display:flex}
.gcard{flex:1;display:flex;align-items:center;border:1px solid #e2ddcd;border-radius:9px;padding:10px 12px;background:#fbfaf5}
.gcard .gi{font-size:26px}.gcard b{font-size:18px;color:#2f6f9f}.gcard span{display:block;font-size:11px;color:#8a8a7a}
.friends{display:flex;flex-wrap:wrap}
.friend{width:64px;text-align:center;color:#4a4a4a;margin:0 10px 10px 0}
.friend .fn{display:block;font-size:10px;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.friend .av.mini{margin:0 auto}
.rooms{display:flex;flex-direction:column}
.rcard{display:flex;align-items:center;border:1px solid #e2ddcd;border-radius:9px;padding:10px 12px;background:#fbfaf5}
.ricon{font-size:24px}.rinfo b{font-size:13px}.rinfo .rd{display:block;font-size:11px;color:#8a8a7a}
.rinfo .rm{display:block;font-size:11px;color:#8a8a7a;margin-top:2px}
.stars{color:#f7a838;letter-spacing:1px}
.bgrid{display:flex;flex-wrap:wrap}
.bcell{width:44px;height:44px;border:1px solid #e2ddcd;border-radius:7px;background:#fbfaf5;display:grid;place-items:center;position:relative}
.bcell img{max-width:40px;max-height:40px}
.bcell.worn{border-color:#f7a838;box-shadow:0 0 0 2px #ffe6b8;background:#fff8ec}
/* Pages Actus / Jeux / Communauté / Aide */
.cols.one2{max-width:600px;margin:0 auto;display:block}
.newslist{display:flex;flex-direction:column}
.nrow{border-bottom:1px dotted #e2ddcd;padding-bottom:12px}.nrow:last-child{border:0;padding-bottom:0}
.nrow .ndate{font-size:11px;color:#a59f8d;margin-left:8px}
.nrow p{margin-top:6px;color:#5b5b5b}
.gamelist{display:flex;flex-direction:column}
.grow{display:flex;align-items:flex-start;border:1px solid #e2ddcd;border-radius:9px;padding:12px;background:#fbfaf5}
.grow .gi{font-size:30px;line-height:1}.grow b{font-size:14px}.grow p{font-size:12px;color:#6b6b6b;margin-top:2px}
.lb{list-style:none;counter-reset:none}
.lb li{display:flex;align-items:center;padding:6px 0;border-bottom:1px dotted #e2ddcd;font-size:12px}
.lb li:last-child{border:0}
.lb .rk{width:20px;height:20px;background:#2f6f9f;color:#fff;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:700;flex:0 0 auto}
.lb li:nth-child(1) .rk{background:#f7a838}.lb li:nth-child(2) .rk{background:#9db0c0}.lb li:nth-child(3) .rk{background:#cd7f32}
.lb a{flex:1}.lb b{color:#2f6f9f}
.hsearch{display:flex}.hsearch input{flex:1}
.hof{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.hof .panel{margin:0}
@media(max-width:560px){.hof{grid-template-columns:1fr}}
.rules,.faq{list-style:none}
.rules li{padding:6px 0 6px 22px;position:relative;font-size:12px}
.rules li:before{content:'✓';position:absolute;left:0;color:#6ba62f;font-weight:700}
.faq li{padding:6px 0 6px 18px;position:relative;font-size:12.5px;color:#5b5b5b}
.faq li:before{content:'•';position:absolute;left:4px;color:#4a92c8;font-weight:700}
/* Centre d'aide */
.helpintro{color:#5b5b5b}
.helpnav{list-style:none}
.helpnav li{border-bottom:1px dotted #e2ddcd}.helpnav li:last-child{border:0}
.helpnav a{display:block;padding:9px 4px;font-weight:700;color:#3a3a3a;font-size:12.5px}
.helpnav a:hover{color:#2f6f9f;text-decoration:none}
.helpnav span{display:inline-block;width:20px}
.faqd{border:1px solid #e2ddcd;border-radius:8px;margin-bottom:8px;background:#fbfaf5;overflow:hidden}
.faqd summary{cursor:pointer;padding:11px 14px;font-weight:700;color:#2f6f9f;font-size:12.5px;list-style:none;position:relative}
.faqd summary::-webkit-details-marker{display:none}
.faqd summary:before{content:'＋';position:absolute;right:14px;color:#8a8a7a;font-weight:700}
.faqd[open] summary:before{content:'－'}
.faqd[open] summary{background:#eef4fa;border-bottom:1px solid #e2ddcd}
.faqa{padding:12px 14px;color:#5b5b5b;font-size:12.5px}
.faqa b{color:#3a3a3a}
/* --- Centre d'aide / Contact / Mot de passe : refonte soignée --- */
.help-hero{display:flex;align-items:center;background:linear-gradient(135deg,#4a92c8,#2f6f9f);border-radius:14px;padding:22px 24px;color:#fff;box-shadow:0 4px 14px rgba(47,111,159,.25);margin-bottom:18px}
.help-hero>*+*{margin-left:18px}
.help-hero.green{background:linear-gradient(135deg,#8fc94a,#6ba62f);box-shadow:0 4px 14px rgba(107,166,47,.25)}
.help-hero.orange{background:linear-gradient(135deg,#f7a838,#ef8f13);box-shadow:0 4px 14px rgba(239,143,19,.25)}
.help-hero .hh-ico{flex:0 0 auto;width:58px;height:58px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;box-shadow:inset 0 0 0 2px rgba(255,255,255,.35)}
.help-hero h1{font-size:22px;margin:0 0 4px;text-shadow:0 1px 0 rgba(0,0,0,.15)}
.help-hero p{margin:0;font-size:13px;color:#eef5fb}
.help-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));grid-gap:12px;gap:12px;margin-bottom:22px}
.hcard{display:flex;align-items:center;background:#fff;border:1px solid #e2ddcd;border-left-width:5px;border-radius:11px;padding:12px;box-shadow:0 2px 6px rgba(0,0,0,.06)}
.hcard>*+*{margin-left:12px}
.hcard:hover{text-decoration:none;box-shadow:0 5px 14px rgba(0,0,0,.13);transform:translateY(-1px)}
.hcard .hc-ico{flex:0 0 auto;width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:23px}
.hcard .hc-body{display:flex;flex-direction:column;min-width:0}
.hcard .hc-body b{font-size:13.5px;color:#3a3a3a;line-height:1.2}
.hcard .hc-body small{font-size:11px;color:#8a857a;margin-top:2px}
.hcard.blue{border-left-color:#2f6f9f}.hcard.blue .hc-ico{background:#e7f1f9}
.hcard.purple{border-left-color:#7d52a8}.hcard.purple .hc-ico{background:#f2ecfa}
.hcard.orange{border-left-color:#ef8f13}.hcard.orange .hc-ico{background:#fdefdb}
.hcard.green{border-left-color:#6ba62f}.hcard.green .hc-ico{background:#eef7e2}
.help-sec{border:1px solid #d7d0bd;border-radius:11px;overflow:hidden;margin-bottom:16px;background:#fff}
.help-sec .hs-head{display:flex;align-items:center;padding:11px 14px;color:#fff}
.help-sec .hs-head>*+*{margin-left:10px}
.help-sec .hs-head.blue{background:linear-gradient(#4a92c8,#2f6f9f)}
.help-sec .hs-head.purple{background:linear-gradient(#a87fce,#7d52a8)}
.help-sec .hs-head.orange{background:linear-gradient(#f7a838,#ef8f13)}
.help-sec .hs-head.green{background:linear-gradient(#8fc94a,#6ba62f)}
.help-sec .hs-badge{flex:0 0 auto;width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;font-size:16px}
.help-sec .hs-head h2{font-size:14px;margin:0;text-shadow:0 1px 0 rgba(0,0,0,.2)}
.help-sec .hs-body{padding:14px}
.help-sec .faqd:last-child{margin-bottom:0}
.help-foot{display:flex;align-items:center;justify-content:space-between;background:#fbfaf5;border:1px dashed #cfc6ad;border-radius:11px;padding:16px 18px}
.help-foot>*+*{margin-left:16px}
.help-foot b{font-size:14px;color:#3a3a3a}.help-foot p{margin:2px 0 0;font-size:12px;color:#6b6b6b}
.help-foot .hf-btns{display:flex;flex:0 0 auto}
.help-foot .hf-btns>*+*{margin-left:8px}
.help-foot .hbtn{width:auto;margin:0;white-space:nowrap}
@media(max-width:560px){.help-hero{flex-direction:column;text-align:center}.help-hero>*+*{margin-left:0;margin-top:12px}.help-foot{flex-direction:column;text-align:center}.help-foot>*+*{margin-left:0;margin-top:12px}}
/* Habbo Club */
.panel-h.hcdark{background:linear-gradient(#3a3a3a,#111);display:flex;align-items:center}
.hclogo{height:40px;width:auto;vertical-align:middle;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated}
.hcgold{color:#ffcf3f}
.hcperks{display:grid;grid-template-columns:1fr 1fr;grid-gap:12px;gap:12px}
@media(max-width:560px){.hcperks{grid-template-columns:1fr}}
.perk{display:flex;align-items:flex-start;border:1px solid #e2ddcd;border-radius:9px;padding:12px;background:#fbfaf5}
.perk .pi{font-size:26px;line-height:1}
.perk b{font-size:13px;color:#3a3a3a}.perk p{font-size:11.5px;color:#6b6b6b;margin-top:2px}
.hcgifts{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));grid-gap:10px;gap:10px}
.hcgift{background:#faf7ff;border:1px solid #e6ddf5;border-radius:9px;padding:8px 6px 7px;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;text-align:center}
.hcgift:hover{border-color:#c9b3ec;box-shadow:0 2px 6px rgba(125,82,168,.15)}
.hcgift-img{width:100%;height:58px;display:flex;align-items:center;justify-content:center;margin-bottom:6px}
.hcgift-img img{display:block;margin:0 auto;max-width:78px;max-height:58px;image-rendering:-moz-crisp-edges;image-rendering:crisp-edges;image-rendering:pixelated}
.hcgift span{display:block;width:100%;font-size:11px;font-weight:700;color:#6a4a9a;line-height:1.25;overflow-wrap:anywhere}
.hcsteps{margin:0;padding-left:18px;color:#5b5b5b;font-size:12.5px}
.hcsteps li{margin:6px 0}
/* --- Compat Basilisk/Goanna : espacements sans gap flex (remplacés par marges) --- */
.tabs>*+*{margin-left:3px}
.sexpick .sx+.sx{margin-left:8px}
.news>*+*{margin-left:12px}
.mottoform>*+*{margin-left:8px}
.coffre>*+*{margin-left:10px}
.games>*+*{margin-left:10px}
.gcard>*+*{margin-left:10px}
.rooms>*+*{margin-top:8px}
.rcard>*+*{margin-left:12px}
.bgrid>*{margin:0 8px 8px 0}
.newslist>*+*{margin-top:14px}
.gamelist>*+*{margin-top:12px}
.grow>*+*{margin-left:12px}
.lb li>*+*{margin-left:8px}
.hsearch>*+*{margin-left:8px}
.panel-h.hcdark>*+*{margin-left:8px}
.perk>*+*{margin-left:10px}
/* Pied */
footer.foot{text-align:center;color:#8a857a;font-size:11px;margin-top:16px}
@media(max-width:640px){.cols{flex-direction:column}.cols>*+*{margin-left:0;margin-top:16px}.side{width:100%;flex:none}}
</style></head><body>
<div class="page">
  <header class="top">
    <a href="?p=home" class="logo"><img class="logo-img" src="/c_images/WebLogos/habbo_logo_nourl.gif" alt="Habbo"><small>l'Hôtel où on se retrouve</small></a>
    <?php if ($u): ?>
      <div class="uinfo">
        Salut <b><?= h($u['username']) ?></b><?= $isStaff ? ' &middot; <a class="adminlink" href="/admin/">🛠 Administration</a>' : '' ?><br>
        <span class="cr"><?= $credits !== null ? $credits : 0 ?> crédits</span> &middot;
        <a href="?p=messages">✉ Messages<?= $unread > 0 ? ' <span class="msgbadge">' . $unread . '</span>' : '' ?></a> &middot;
        <a href="?p=profile&u=<?= h(rawurlencode($u['username'])) ?>">Ma page</a> &middot;
        <a href="?p=logout">Déconnexion</a>
      </div>
    <?php else: ?>
      <div class="uinfo">Déjà membre ? <a href="?p=home">Connecte-toi</a><br>Nouveau ? <a href="?p=register">Crée ton Habbo</a></div>
    <?php endif; ?>
  </header>
  <nav class="tabs"><?php foreach ($tabs as $k => $t) { $on = ($k === $pg || ($pg === 'profile' && $k === 'home')) ? ' on' : ''; $tgt = ($k === 'admin') ? ' target="_blank" rel="noopener"' : ''; echo '<a class="' . trim($on) . '" href="' . $t[1] . '"' . $tgt . '>' . h($t[0]) . '</a>'; } ?></nav>
  <div class="body">
<?php }
function foot(): void {
    echo '  </div><footer class="foot">' . HOTEL . ' — rétro Habbo v14 (2007). Habbo est une marque de Sulake. Projet privé, non affilié.</footer></div>';
    // Ouvre le jeu dans une fenêtre séparée à la taille du client (720x540), pour garder le site ouvert
    echo <<<'JS'
<script>
(function(){
  function openGame(url){
    var win=window.open(url,'habbov14game','width=728,height=548,resizable=yes,scrollbars=no,menubar=no,toolbar=no,location=no,status=no');
    if(win){win.focus();}else{window.location=url;} // si popup bloqué, on bascule dans l'onglet
    return false;
  }
  document.addEventListener('click',function(e){
    var a=e.target && e.target.closest ? e.target.closest('a') : null;
    if(!a) return;
    var href=a.getAttribute('href')||'';
    if(/[?&]p=play(\b|&|$)/.test(href)){ e.preventDefault(); openGame(a.href); }
  });
})();
</script>
JS;
    echo '</body></html>';
}
