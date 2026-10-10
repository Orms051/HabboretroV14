<?php
/**
 * HabboretroV14 — Site v2 (refonte fidèle Habbo FR 2007) — ROUTEUR
 * http://localhost/v2/ · site actuel (/) intact.
 */
require __DIR__ . '/inc/boot.php';
require __DIR__ . '/inc/layout.php';

/* ---- Mode maintenance : page de fermeture pour les non-staff (le staff garde l'accès) ---- */
if (maintenance_active() && !is_staff()) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>' . h(HOTEL) . ' — Maintenance</title>'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<style>body{font:13px Verdana,Arial,sans-serif;background:#083940;color:#fff;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center}'
       . '.box{background:#fff;color:#30384a;max-width:420px;padding:28px 26px;border-radius:10px;border:1px solid #06252b}.box h1{font-size:18px;margin:0 0 10px}.box p{margin:6px 0;color:#5a6672}</style></head>'
       . '<body><div class="box"><h1>&#128679; L\'H&ocirc;tel est ferm&eacute;</h1><p>' . h(HOTEL) . ' est en maintenance. Reviens dans quelques instants&nbsp;!</p>'
       . '<p style="font-size:11px;color:#8a97a3">Merci de ta patience.</p></div></body></html>';
    exit;
}

$p = $_GET['p'] ?? 'home';
$err = null;

/* ---------- Actions ---------- */
if ($p === 'logout') { unset($_SESSION['site_user'], $_SESSION['admin']); session_regenerate_id(true); redirect('?p=home'); }
if ($p === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $wait = login_lock_remaining();
    if ($wait > 0) { $err = 'Trop de tentatives de connexion. Réessaie dans ' . (int)ceil($wait / 60) . ' min.'; }
    elseif (!csrf_ok()) { $err = 'Session expirée, réessaie.'; }
    else {
        $st = db()->prepare('SELECT id,username,password FROM users WHERE username=?'); $st->execute([trim($_POST['username'] ?? '')]);
        $row = $st->fetch();
        if ($row && password_verify((string)($_POST['password'] ?? ''), $row['password'])) {
            login_register_success();
            session_regenerate_id(true); unset($_SESSION['admin']);
            $_SESSION['site_user'] = ['id' => (int)$row['id'], 'username' => $row['username']];
            redirect('?p=home');
        }
        login_register_fail();
        $err = 'Nom ou mot de passe incorrect.';
    }
    $p = 'home';
}
/* ---- Inscription (Nouveau ?) ---- */
$reg_err = null;
if ($p === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $reg_err = 'Session expirée, réessaie.'; }
    else {
        $ru = trim($_POST['username'] ?? ''); $rpw = (string)($_POST['password'] ?? ''); $rsex = ($_POST['sex'] ?? 'M') === 'F' ? 'F' : 'M';
        $rbd = parse_fr_date($_POST['birthday'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_\-=?!@:.,]{3,20}$/', $ru)) $reg_err = 'Nom invalide (3 à 20 caractères : lettres, chiffres).';
        elseif (strlen($rpw) < 4) $reg_err = 'Mot de passe trop court (4 caractères minimum).';
        else {
            $st = db()->prepare('SELECT id FROM users WHERE username=?'); $st->execute([$ru]);
            if ($st->fetch()) $reg_err = 'Ce nom est déjà pris.';
            else {
                db()->prepare('INSERT INTO users (username,password,figure,sex,motto,credits,email,birthday) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$ru, hash_pw($rpw), DEFAULT_FIGURE, $rsex, 'Nouveau sur ' . HOTEL . ' !', 100, $ru . '@' . HOTEL . '.local', $rbd]);
                $id = (int)db()->lastInsertId();
                session_regenerate_id(true); unset($_SESSION['admin']);
                $_SESSION['site_user'] = ['id' => $id, 'username' => $ru];
                redirect('?p=home&welcome=1');
            }
        }
    }
}

/* ---------- Données ---------- */
function setting_get(string $key, string $default = ''): string {
    try { $st = db()->prepare('SELECT value FROM settings WHERE setting=?'); $st->execute([$key]); $v = $st->fetchColumn(); return $v === false ? $default : (string)$v; }
    catch (Throwable $e) { return $default; }
}
function news_items(int $n = 6): array {
    // Colonnes récentes (summary/status/image) ajoutées par l'admin ; repli si absentes.
    foreach ([
        "SELECT id,title,summary,body,image,created_at FROM site_news WHERE status='published' ORDER BY created_at DESC, id DESC LIMIT ?",
        'SELECT id,title,body,created_at FROM site_news ORDER BY created_at DESC, id DESC LIMIT ?',
        'SELECT title,body,created_at FROM site_news ORDER BY created_at DESC, id DESC LIMIT ?',
    ] as $sql) {
        try { $st = db()->prepare($sql); $st->bindValue(1, $n, PDO::PARAM_INT); $st->execute(); return $st->fetchAll(); }
        catch (Throwable $e) {}
    }
    return [];
}
function news_one(int $id): ?array {
    try { $st = db()->prepare('SELECT * FROM site_news WHERE id=?'); $st->execute([$id]); $r = $st->fetch(); return $r ?: null; }
    catch (Throwable $e) { return null; }
}
function board_top(string $col, int $n = 5): array {
    try { $st = db()->prepare("SELECT username,$col AS pts FROM users WHERE $col>0 ORDER BY $col DESC LIMIT ?"); $st->bindValue(1, $n, PDO::PARAM_INT); $st->execute(); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function recent_players(int $n = 6): array {
    try { $st = db()->prepare('SELECT username,sex,figure FROM users ORDER BY last_online DESC LIMIT ?'); $st->bindValue(1, $n, PDO::PARAM_INT); $st->execute(); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function popular_rooms(int $n = 6): array {
    try { $st = db()->prepare("SELECT r.name, COUNT(f.room_id) v FROM rooms r LEFT JOIN users_room_favourites f ON f.room_id=r.id WHERE r.owner_id='0' GROUP BY r.id, r.name ORDER BY v DESC, r.id LIMIT ?"); $st->bindValue(1, $n, PDO::PARAM_INT); $st->execute(); $x = $st->fetchAll(); if ($x) return $x; }
    catch (Throwable $e) {}
    try { $st = db()->prepare("SELECT name, 0 v FROM rooms WHERE owner_id='0' ORDER BY id LIMIT ?"); $st->bindValue(1, $n, PDO::PARAM_INT); $st->execute(); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
/* Portrait : figure stylisée ORIGINALE (pas l'avatar Habbo réel — imager V14 à décider, cf. compte rendu). */
function av_svg(string $name, string $sex): string {
    $x = abs(crc32(mb_strtolower($name)));
    $skin = ['#f6cfa8','#eebd93','#dda475','#c48a5a'][$x % 4];
    $hair = ['#2c1e12','#5a3a1e','#8a5a2a','#c98a3a','#141414','#b23b2e'][intdiv($x,4) % 6];
    $shirt = ['#4a92c8','#8fc94a','#e5820c','#a87fce','#e0577a','#e6c23a'][intdiv($x,24) % 6];
    return '<svg viewBox="0 0 64 64" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">'
        . '<rect x="14" y="53" width="36" height="15" rx="8" fill="' . $shirt . '"/><rect x="28" y="44" width="8" height="11" rx="3" fill="' . $skin . '"/>'
        . '<rect x="18" y="13" width="28" height="34" rx="12" fill="' . $skin . '"/>'
        . '<path d="M18 27 v-2 a14 14 0 0 1 28 0 v2 c-5 -5 -10 -6 -14 -6 s-9 1 -14 6 z" fill="' . $hair . '"/>'
        . '<circle cx="27" cy="31" r="2.3" fill="#3a2a1e"/><circle cx="37" cy="31" r="2.3" fill="#3a2a1e"/></svg>';
}
function lb_table(array $rows): string {
    if (!$rows) return '<p class="muted">Aucun score pour l\'instant.</p>';
    $h = '<table style="width:100%;border-collapse:collapse;font-size:11px"><tr class="muted"><th style="text-align:left">#</th><th style="text-align:left">Habbo</th><th style="text-align:right">Score</th></tr>';
    $i = 0; foreach ($rows as $r) { $i++; $h .= '<tr><td>' . $i . '.</td><td>' . h((string)$r['username']) . '</td><td style="text-align:right">' . number_format((int)$r['pts'], 0, ',', ' ') . '</td></tr>'; }
    return $h . '</table>';
}

/* ---------- Widgets de Habbo Home (consultation, vraies données) ---------- */
/* CSS du skin d'origine « HabboHomes » (9-slice) + avatar de repli neutre. */
function home_skins(): array {
    // clé => préfixe de fichier (web-gallery/images/skins/Skin_<préfixe>_*.png), même structure 4-coins.
    return ['habbohomes' => 'HabboHomes', 'metal' => 'Metal', 'hcmachine' => 'HCmachine', 'notepad' => 'Notepad', 'speechbubble' => 'Speechbubble', 'stickienote' => 'Stickienote'];
}
/** Clé de skin interne -> classe d'origine Habbo + préfixe d'images. */
function home_skin_class(string $key): string {
    $m = ['habbohomes' => 'w_skin_defaultskin', 'metal' => 'w_skin_metalskin', 'hcmachine' => 'w_skin_hc_machineskin', 'notepad' => 'w_skin_notepadskin', 'speechbubble' => 'w_skin_speechbubbleskin', 'stickienote' => 'w_skin_noteitskin'];
    return $m[$key] ?? 'w_skin_defaultskin';
}
function home_css(): string {
    // Vrais skins Habbo Home (images d'origine web-gallery/images/skins/), structure headline/corner + content/body.
    $S = '/web-gallery/images/skins';
    return '<style>
.hw{position:relative;font:11px/1.4 Verdana,Arial,sans-serif}
.hw div.widget-headline h3{margin:0}
.hw div.widget-content{min-width:150px}
.hw div.widget-content a{color:#2f6f9f}
.hw .hwb table{font-size:11px}
.av-ini{display:inline-block;color:#fff;font:bold 13px Verdana,Arial,sans-serif;text-align:center;text-shadow:0 1px 1px rgba(0,0,0,.3);border:1px solid rgba(0,0,0,.2);border-radius:4px}
/* default (HabboHomes) */
.hw.w_skin_defaultskin div.widget-content{background:url(' . $S . '/Skin_HabboHomes_btmL.png) no-repeat bottom left,url(' . $S . '/Skin_HabboHomes_btmR.png) no-repeat bottom right;padding:4px 16px 19px 18px;color:#000}
.hw.w_skin_defaultskin div.widget-body{padding:0}
.hw.w_skin_defaultskin div.widget-headline{background:url(' . $S . '/Skin_HabboHomes_topLh.png) no-repeat top left,url(' . $S . '/Skin_HabboHomes_topRh.png) no-repeat top right;height:41px}
.hw.w_skin_defaultskin div.widget-corner{padding-left:41px;height:41px}
.hw.w_skin_defaultskin div.widget-headline h3{text-align:left;font-size:11px;font-weight:normal;color:#fff;position:relative;padding-top:12px;left:-33px}
/* metal */
.hw.w_skin_metalskin div.widget-content{background:url(' . $S . '/Skin_Metal_btmL.png) no-repeat bottom left,url(' . $S . '/Skin_Metal_btmR.png) no-repeat bottom right;padding:1px 26px 23px 29px;color:#000}
.hw.w_skin_metalskin div.widget-body{padding:0}
.hw.w_skin_metalskin div.widget-headline{background:url(' . $S . '/Skin_Metal_topLh.png) no-repeat top left,url(' . $S . '/Skin_Metal_topRh.png) no-repeat top right;height:33px}
.hw.w_skin_metalskin div.widget-corner{padding-left:35px;height:33px}
.hw.w_skin_metalskin div.widget-headline h3{text-align:center;font-size:11px;position:relative;top:3px;color:#41404a;padding-top:4px;margin:2px 40px 0 0}
/* HC machine */
.hw.w_skin_hc_machineskin div.widget-content{background:url(' . $S . '/Skin_HCmachine_btmL.png) no-repeat bottom left,url(' . $S . '/Skin_HCmachine_btmR.png) no-repeat bottom right;padding:0 46px 31px 49px;color:#fff}
.hw.w_skin_hc_machineskin div.widget-content a{color:#8fe84a}
.hw.w_skin_hc_machineskin div.widget-body{padding:0}
.hw.w_skin_hc_machineskin div.widget-headline{background:url(' . $S . '/Skin_HCmachine_topLh.png) no-repeat top left,url(' . $S . '/Skin_HCmachine_topRh.png) no-repeat top right;height:42px}
.hw.w_skin_hc_machineskin div.widget-corner{padding-left:61px;height:42px}
.hw.w_skin_hc_machineskin div.widget-headline h3{text-align:center;font-size:11px;position:relative;top:3px;color:#fff;padding-top:6px;margin:2px 61px 0 0}
/* notepad */
.hw.w_skin_notepadskin div.widget-content{background:url(' . $S . '/Skin_Notepad_btmL.png) no-repeat bottom left,url(' . $S . '/Skin_Notepad_btmR.png) no-repeat bottom right;padding:2px 7px 18px 29px;color:#000}
.hw.w_skin_notepadskin div.widget-body{padding:0}
.hw.w_skin_notepadskin div.widget-headline{background:url(' . $S . '/Skin_Notepad_topLh.png) no-repeat top left,url(' . $S . '/Skin_Notepad_topRh.png) no-repeat top right;height:29px}
.hw.w_skin_notepadskin div.widget-corner{padding-left:27px;height:29px}
.hw.w_skin_notepadskin div.widget-headline h3{text-align:left;font-size:12px;position:relative;padding-top:9px;left:-4px;color:#4c3a41}
/* stickienote (noteit) */
.hw.w_skin_noteitskin div.widget-content{background:url(' . $S . '/Skin_Stickienote_btmL.png) no-repeat bottom left,url(' . $S . '/Skin_Stickienote_btmR.png) no-repeat bottom right;padding:7px 9px 30px 10px;color:#000}
.hw.w_skin_noteitskin div.widget-body{padding:0}
.hw.w_skin_noteitskin div.widget-headline{background:url(' . $S . '/Skin_Stickienote_topL.png) no-repeat top left,url(' . $S . '/Skin_Stickienote_topR.png) no-repeat top right;height:30px}
.hw.w_skin_noteitskin div.widget-corner{padding-left:5px;height:30px}
.hw.w_skin_noteitskin div.widget-headline h3{text-align:left;font-size:11px;position:relative;padding-top:9px;left:-4px;color:#202933}
/* speechbubble */
.hw.w_skin_speechbubbleskin div.widget-content{background:url(' . $S . '/Skin_Speechbubble_btmL.png) no-repeat bottom left,url(' . $S . '/Skin_Speechbubble_btmR.png) no-repeat bottom right;padding:4px 10px 22px 10px;color:#000}
.hw.w_skin_speechbubbleskin div.widget-body{padding:0}
.hw.w_skin_speechbubbleskin div.widget-headline{background:url(' . $S . '/Skin_Speechbubble_topL.png) no-repeat top left,url(' . $S . '/Skin_Speechbubble_topR.png) no-repeat top right;height:12px}
.hw.w_skin_speechbubbleskin div.widget-corner{padding-left:10px;height:12px}
.hw.w_skin_speechbubbleskin div.widget-headline h3{text-align:left;font-size:12px;position:relative;padding-top:2px;left:4px;color:#666}
/* Contenu du ProfileWidget — dimensions/marges reprises de myhabbo.css d\'origine (infos à gauche, figure à droite) */
.hw .profile-info{width:90px;float:left;font-size:11px;line-height:1.35}
.hw .profile-info .name-text{font-weight:bold;text-decoration:underline;color:#2f6f9f;font-size:12px}
.hw .profile-status{margin:4px 0}
.hw .profile-since{font-size:10px;color:#5a6672;margin-top:3px}
.hw .profile-motto-inline{font-style:italic;color:#5a6672;font-size:10px;margin-top:4px}
.hw .profile-figure{float:left;margin-left:5px;text-align:center}
.hw .av-figure{display:block;width:50px;height:90px;line-height:90px;color:#fff;font:bold 22px Verdana,Arial,sans-serif;text-align:center;text-shadow:0 1px 1px rgba(0,0,0,.3);border:1px solid rgba(0,0,0,.2);border-radius:4px}
.hw .av-cap{display:block;font-size:9px;color:#8a97a3;margin-top:2px}
.hw .profile-friend{clear:both;margin-top:6px}
.hw .profile-tags{clear:both;margin-top:6px;padding-top:6px;border-top:1px solid #d9d2b8}
.hw .pt-band{background:url(/web-gallery/images/myhabbo/widgets/raster_selected.gif);border:1px solid #fff;padding:3px 6px;font-size:10px;color:#777}
</style>';
}
/* Avatar de repli ORIGINAL et neutre (initiale colorée) — pas le personnage Habbo (imager non dispo côté web). */
function av_initial(string $name, int $size = 44): string {
    $x = abs(crc32(mb_strtolower($name)));
    $cols = ['#4a92c8', '#8fc94a', '#e5820c', '#a87fce', '#e0577a', '#3fb0a6', '#e6b800'];
    $bg = $cols[$x % count($cols)];
    $ch = mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1));
    $fs = max(11, (int)round($size * 0.42));
    return '<span class="av-ini" style="width:' . $size . 'px;height:' . $size . 'px;line-height:' . $size . 'px;font-size:' . $fs . 'px;background:' . $bg . '">' . h($ch) . '</span>';
}
function hw_box(string $title, string $body, int $w = 280, string $hc = 'b', string $skin = ''): string {
    $cls = 'hw ' . home_skin_class($skin !== '' ? $skin : 'habbohomes');
    return '<div class="' . $cls . '" style="width:' . $w . 'px">'
        . '<div class="widget-headline"><div class="widget-corner"><h3>' . h($title) . '</h3></div></div>'
        . '<div class="widget-content"><div class="widget-body">' . $body . '</div></div>'
        . '</div>';
}
function home_report_form(string $type, int $tid): string {
    $reasons = ['Contenu choquant', 'Insultes / harcèlement', 'Spam / publicité', 'Usurpation d\'identité', 'Autre'];
    $h = '<form method="post" action="?p=home_report" style="margin-top:6px;background:#fff;border:1px solid #e0c0c6;border-radius:4px;padding:8px;max-width:420px">'
       . '<input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="type" value="' . h($type) . '"><input type="hidden" name="tid" value="' . (int)$tid . '">'
       . '<div style="margin-bottom:5px"><b style="font-size:11px">Motif</b><br><select name="reason" style="border:1px solid #c3b45a;padding:3px;font:11px Verdana">';
    foreach ($reasons as $r) $h .= '<option value="' . h($r) . '">' . h($r) . '</option>';
    $h .= '</select></div>'
       . '<textarea name="detail" maxlength="400" placeholder="Explication (facultatif)…" style="width:100%;height:40px;border:1px solid #c3b45a;padding:4px;font:11px Verdana;resize:none"></textarea>'
       . '<div style="text-align:right;margin-top:4px"><button class="new-button" type="submit"><b>Envoyer le signalement</b><i></i></button></div>'
       . '<p class="muted" style="font-size:9px;margin-top:3px">Ton signalement reste anonyme pour les autres joueurs.</p></form>';
    return $h;
}
function home_widget_html(string $type, array $owner, int $oid, string $resource, string $content, string $ctx = 'view', string $skin = ''): string {
    switch ($type) {
        case 'sticker':
            $f = basename($resource);
            if ($f !== '' && is_file(dirname(__DIR__) . '/c_images/myhabbo/stickers/' . $f)) return '<img src="/c_images/myhabbo/stickers/' . h($f) . '" alt="" style="display:block">';
            return '<span class="missing-img" style="display:inline-block;padding:6px">sticker indisponible</span>';
        case 'widget_profile':
            $cr = ''; $lo = 0;
            try { $pr = db()->prepare('SELECT created_at,last_online FROM users WHERE id=?'); $pr->execute([$oid]); if ($row = $pr->fetch()) { $cr = (string)$row['created_at']; $lo = (int)$row['last_online']; } } catch (Throwable $e) {}
            $online = $lo > 0 && (time() - $lo) < 300;
            $status = $online
                ? '<span style="display:inline-block;background:#6ba62f;color:#fff;font:bold 8px Verdana;padding:1px 5px;border-radius:2px;letter-spacing:.5px">EN LIGNE</span>'
                : '<span style="display:inline-block;background:#9aa6ae;color:#fff;font:bold 8px Verdana;padding:1px 5px;border-radius:2px;letter-spacing:.5px">HORS LIGNE</span>';
            // Avatar : emplacement (slot) prévu pour le vrai personnage Habbo plein-corps — image provisoire (initiale) en attendant l'imager.
            $un = (string)$owner['username'];
            $cx = abs(crc32(mb_strtolower($un)));
            $cols = ['#4a92c8', '#8fc94a', '#e5820c', '#a87fce', '#e0577a', '#3fb0a6', '#e6b800'];
            $fbg = $cols[$cx % count($cols)];
            $fch = h(mb_strtoupper(mb_substr($un !== '' ? $un : '?', 0, 1)));
            $nameUrl = h(rawurlencode($un));
            // Infos (colonne 90px à gauche) + figure (à droite), repris de .profile-info / .profile-figure d'origine
            $motto = (string)$owner['motto'];
            $b = '<div class="profile-info">'
               . '<a class="name-text" href="?p=home/' . $nameUrl . '">' . h($un) . '</a>'
               . '<div class="profile-status">' . $status . '</div>'
               . '<div class="profile-since">Habbo créé le :<br><b>' . ($cr !== '' ? h(date('d/m/Y', strtotime($cr))) : '—') . '</b></div>'
               . ($motto !== '' ? '<div class="profile-motto-inline">« ' . h($motto) . ' »</div>' : '')
               . '</div>'
               . '<div class="profile-figure"><span class="av-figure" style="background:' . $fbg . '">' . $fch . '</span><span class="av-cap">avatar en jeu</span></div>';
            $viewer = me(); $isOwner = $viewer && (int)$viewer['id'] === $oid;
            if ($viewer && !$isOwner && $ctx === 'view') {
                $isFriend = false; try { $fq = db()->prepare('SELECT COUNT(*) FROM messenger_friends WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?)'); $fq->execute([(int)$viewer['id'], $oid, $oid, (int)$viewer['id']]); $isFriend = (int)$fq->fetchColumn() > 0; } catch (Throwable $e) {}
                if ($isFriend) $b .= '<div class="profile-friend" style="font-size:10px;color:#6ba62f">✔ Dans tes amis</div>';
                else $b .= '<form class="profile-friend" method="post" action="?p=home_addfriend"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="fid" value="' . $oid . '"><button class="new-button" type="submit"><b>Ajouter en ami</b><i></i></button></form>';
            }
            $b .= '<div class="profile-tags"><div class="pt-band">Aucun tag.</div></div>';
            return hw_box('Profil', $b, 190, 'b', $skin);
        case 'widget_friends':
            $rows = [];
            try { $st = db()->prepare('SELECT CASE WHEN from_id=? THEN to_id ELSE from_id END AS fid FROM messenger_friends WHERE from_id=? OR to_id=? LIMIT 30'); $st->execute([$oid, $oid, $oid]); $ids = array_column($st->fetchAll(), 'fid'); }
            catch (Throwable $e) { $ids = []; }
            if ($ids) { $in = implode(',', array_map('intval', array_slice($ids, 0, 18))); try { $rows = db()->query('SELECT username,sex FROM users WHERE id IN (' . $in . ')')->fetchAll(); } catch (Throwable $e) {} }
            if (!$rows) $b = '<p class="muted" style="font-size:10px">Aucun ami pour le moment.</p>';
            else { $b = '<div style="display:flex;flex-wrap:wrap">'; foreach ($rows as $r) $b .= '<a href="?p=home/' . h(rawurlencode((string)$r['username'])) . '" title="' . h((string)$r['username']) . '" style="width:46px;text-align:center;margin:0 3px 6px 0;font-size:8px;color:#30384a;text-decoration:none"><span style="display:block;margin:0 auto 2px">' . av_initial((string)$r['username'], 38) . '</span>' . h(mb_strimwidth((string)$r['username'], 0, 7, '…')) . '</a>'; $b .= '</div>'; }
            return hw_box('Mes amis', $b, 300, 'g', $skin);
        case 'widget_badges':
            try { $st = db()->prepare('SELECT badge FROM users_badges WHERE user_id=? LIMIT 24'); $st->execute([$oid]); $bd = array_column($st->fetchAll(), 'badge'); }
            catch (Throwable $e) { $bd = []; }
            if (!$bd) $b = '<p class="muted" style="font-size:10px">Aucun badge.</p>';
            else {
                $bdir = dirname(__DIR__) . '/c_images/badges';
                $b = '<div style="display:flex;flex-wrap:wrap;gap:4px">';
                foreach ($bd as $code) {
                    $code = (string)$code;
                    if (is_file($bdir . '/' . $code . '.gif')) $b .= '<img src="/c_images/badges/' . h($code) . '.gif" title="' . h($code) . '" alt="' . h($code) . '" style="width:40px;height:40px;object-fit:contain">';
                    else $b .= '<span title="' . h($code) . '" style="display:inline-block;min-width:34px;height:40px;line-height:40px;text-align:center;background:#e9eef2;border:1px solid #c3d0d8;border-radius:3px;font:bold 9px Verdana">' . h($code) . '</span>';
                }
                $b .= '</div>';
            }
            return hw_box('Mes badges', $b, 280, 'o', $skin);
        case 'widget_rooms':
            try { $st = db()->prepare('SELECT id,name FROM rooms WHERE owner_id=? ORDER BY id LIMIT 12'); $st->execute([(string)$oid]); $rm = $st->fetchAll(); }
            catch (Throwable $e) { $rm = []; }
            if (!$rm) $b = '<p class="muted" style="font-size:10px">Aucun appartement.</p>';
            else { $b = ''; foreach ($rm as $r) $b .= '<div style="padding:3px 0;border-bottom:1px dotted #e0e0e0;font-size:11px">🚪 ' . h((string)$r['name']) . '</div>'; }
            return hw_box('Mes apparts', $b, 240, 'p', $skin);
        case 'widget_guestbook':
            if ($ctx === 'edit') return hw_box('Livre d\'or', '<p class="muted" style="font-size:10px">Les messages des visiteurs s\'afficheront ici.</p>', 320, 'y', $skin);
            $viewer = me(); $isOwner = $viewer && (int)$viewer['id'] === $oid;
            $per = 6; $page = max(0, (int)($_GET['gbp'] ?? 0));
            $total = home_guestbook_count($oid);
            $msgs = home_guestbook($oid, $per, $page * $per);
            $b = '';
            // Formulaire de signature (connecté)
            if ($viewer) {
                $b .= '<form method="post" action="?p=home_sign" style="margin-bottom:7px">'
                    . '<input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="owner_id" value="' . $oid . '"><input type="hidden" name="owner_name" value="' . h((string)$owner['username']) . '">'
                    . '<textarea name="message" maxlength="255" placeholder="Laisse un message…" style="width:100%;height:38px;border:1px solid #c3b45a;padding:4px;font:11px Verdana;resize:none"></textarea>'
                    . '<div style="text-align:right;margin-top:3px"><button class="new-button" type="submit"><b>Signer</b><i></i></button></div></form>';
            } else {
                $b .= '<p class="muted" style="font-size:10px">' . '<a href="?p=register">Connecte-toi</a> pour signer le livre d\'or.</p>';
            }
            if (!$msgs) $b .= '<p class="muted" style="font-size:10px">Aucun message. Sois le premier à signer !</p>';
            else {
                foreach ($msgs as $m) {
                    $b .= '<div style="border-bottom:1px dotted #e0e0e0;padding:5px 0"><div style="font-size:11px">' . h((string)$m['message']) . '</div>'
                        . '<div class="muted" style="font-size:9px;margin-top:1px">— <a href="?p=home/' . h(rawurlencode((string)$m['author_name'])) . '">' . h((string)$m['author_name']) . '</a> · ' . h(date('d/m/Y', strtotime((string)$m['created_at'])));
                    if ($isOwner || is_staff()) $b .= ' · <form method="post" action="?p=home_gb_delete" style="display:inline"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="mid" value="' . (int)$m['id'] . '"><button type="submit" onclick="return confirm(\'Supprimer ce message ?\')" style="border:0;background:none;color:#a33;cursor:pointer;font-size:9px;padding:0;text-decoration:underline">supprimer</button></form>';
                    if ($viewer && !$isOwner) $b .= ' · <a href="?p=home/' . h(rawurlencode((string)$owner['username'])) . '&report=gb:' . (int)$m['id'] . '#gb" style="color:#a33;font-size:9px">signaler</a>';
                    $b .= '</div></div>';
                }
                // Pagination
                $pages = (int)ceil($total / $per);
                if ($pages > 1) {
                    $nav = '<div style="margin-top:6px;font-size:10px;text-align:center">';
                    if ($page > 0) $nav .= '<a href="?p=home/' . h(rawurlencode((string)$owner['username'])) . '&gbp=' . ($page - 1) . '#gb">« récents</a> ';
                    $nav .= '<span class="muted">page ' . ($page + 1) . '/' . $pages . '</span>';
                    if ($page < $pages - 1) $nav .= ' <a href="?p=home/' . h(rawurlencode((string)$owner['username'])) . '&gbp=' . ($page + 1) . '#gb">anciens »</a>';
                    $b .= $nav . '</div>';
                }
            }
            return '<a name="gb"></a>' . hw_box('Livre d\'or (' . $total . ')', $b, 330, 'y', $skin);
        case 'widget_notes':
            return hw_box('Pense-bête', '<div style="font-size:11px;white-space:pre-wrap">' . h($content) . '</div>', 240, 'y', $skin);
        default:
            return hw_box('Widget', '<p class="muted" style="font-size:10px">Widget inconnu.</p>', 200, 'b', $skin);
    }
}

/* ---------- Pages internes ---------- */
$STUBS = [
    'register'  => ['Nouveau ? — Créer ton Habbo', 'L\'inscription fidèle 2007 (guide + formulaire) arrive ici.'],
    'community' => ['Communauté', 'Clans actifs, Habbo Homes, staff et recherche — bientôt, avec nos données réelles.'],
    'events'    => ['Events', 'Programme des animations et de l\'Infobus — bientôt.'],
    'games'     => ['Jeux', 'BattleBall, SnowStorm et leurs classements réels + programme — bientôt.'],
    'shop'      => ['Boutique', 'La boutique arrive ici.'],
    'mobile'    => ['Mobile', 'Rubrique en construction.'],
    'credits'   => ['Crédits', 'Ton solde réel de crédits et les moyens d\'en obtenir — bientôt.'],
    'club'      => ['Habbo Club', 'Avantages HC, ton statut réel et les pages du Club — bientôt.'],
    'help'      => ['Aide', 'Le centre d\'aide sera repris ici fidèlement.'],
    'me'        => ['Mon Habbo', 'L\'accueil de ton compte (distinct de ta Home publique) — bientôt.'],
];

/* ============================================================ */
if ($p === 'home') {
    render_head('home', $err);
    $u = me(); $online = online_count();
    $slogan = setting_get('site.safety_slogan', 'Pour vérifier ton e-mail, clique sur le lien reçu — ne le communique à personne !');
    $infobus = setting_get('site.infobus', 'Dans les Jardins Habbos ! Mercredi 16h30–17h30 et Vendredi 17h–18h.');
    $clans = [];
    foreach (['SELECT name FROM groups ORDER BY id DESC LIMIT 5'] as $sql) { try { $clans = db()->query($sql)->fetchAll(); if ($clans) break; } catch (Throwable $e) {} }
    ?>
    <!-- HAUT : grand carrousel à gauche + actualités (plus étroit) à droite -->
    <div class="hometop">
      <div class="htcar">
        <?php
        $slides = carousel_slides();
        $cnums = '';
        foreach ($slides as $i => $s) $cnums .= '<span class="cnum' . ($i === 0 ? ' on' : '') . '" id="cnum' . $i . '" onclick="carGo(' . $i . ')">' . ($i + 1) . '</span>';
        box_open('À ne pas manquer <span class="cnums">' . $cnums . '</span>', 'k');
        if (!$slides) echo '<p class="muted">Aucune mise en avant.</p>';
        else {
            echo '<div class="carousel" id="carousel" data-n="' . count($slides) . '">';
            foreach ($slides as $i => $s) {
                $ext = (strpos((string)$s['link'], '/client.php') === 0);
                $tgt = $ext ? ' target="_blank" rel="noopener"' : '';
                echo '<div class="cslide' . ($i === 0 ? ' on' : '') . '" id="cslide' . $i . '">';
                echo '<a class="cstage"' . ($s['link'] ? ' href="' . h((string)$s['link']) . '"' . $tgt : '') . ' style="background-image:url(' . h((string)$s['image']) . ')"><span class="ccap">' . h((string)$s['title']) . '</span></a>';
                echo '<div class="crow"><span class="ctext">' . h((string)$s['body']) . '</span>'
                   . '<span class="cbtns">' . clink('Entrer !', (string)($s['link'] ?: '/client.php'), $ext ? '_blank' : '') . tlink('En savoir plus »', (string)($s['link'] ?: '?p=help'), $ext ? '_blank' : '') . '</span></div>';
                echo '</div>';
            }
            echo '</div>';
        }
        box_close();
        ?>
      </div>
      <div class="htnews">
        <div class="newsbox"><div class="nb-head">Quoi de neuf ?</div><div class="nb-body">
          <?php $news = news_items(4); if (!$news): ?><p style="color:#bcd6e2">Pas encore d'actualité.</p>
          <?php else: foreach ($news as $n): $nid = (int)($n['id'] ?? 0); $resume = mb_strimwidth(trim((string)($n['summary'] ?? '')) !== '' ? (string)$n['summary'] : strip_tags((string)$n['body']), 0, 52, '…'); ?>
            <div class="nb-item"><span class="nb-date">[<?= h(date('d/m/y', strtotime((string)$n['created_at']))) ?>]</span>
              <?php if ($nid): ?><a class="nb-title" href="?p=actu&id=<?= $nid ?>"><?= h((string)$n['title']) ?></a><?php else: ?><b class="nb-title"><?= h((string)$n['title']) ?></b><?php endif; ?>
              <div class="nb-sum"><?= h($resume) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div><div class="nb-foot"><?= clink('Toutes les actus', '?p=events') ?></div></div>
      </div>
    </div>

    <!-- BAS : 3 colonnes (gauche étroite / centre / droite plus large) -->
    <div class="home3">
      <div class="hcol hcol-l">
        <?php box_open('Besoin d\'aide ?', 'o'); ?><p class="muted"><?= h(setting_get('site.home_besoin', "Un bug, un souci ? L'équipe de l'hôtel est là pour t'aider.")) ?> <a href="?p=help">Clique ici »</a></p><?php box_close(); ?>
        <?php box_open('Bienvenue à Habbo', 'o'); ?>
          <img src="/web-gallery/v2/images/myhabbo_frank.gif" alt="" width="47" height="85" style="float:left;margin:0 8px 4px 0">
          <p class="muted" style="margin-bottom:8px"><?= h(setting_get('site.home_bienvenue', "Habbo est une communauté virtuelle où tu rencontres tes amis, participes à des jeux et décores ton propre appart. L'entrée est gratuite !")) ?></p>
          <?= clink('Entre maintenant !', '/client.php', '_blank') ?>
        <?php box_close(); ?>
        <?php box_open('La sécurité sur Habbo', 'o'); ?>
          <p class="muted"><?= h(setting_get('site.home_securite', "Ne partage jamais ton mot de passe, même avec quelqu'un se présentant comme membre du staff.")) ?> <a href="?p=help">Conseils »</a></p>
        <?php box_close(); ?>
        <?php box_open('Slogan sécu de la semaine', 'y'); ?><p class="muted" style="font-style:italic">« <?= h($slogan) ?> »</p><?php box_close(); ?>
      </div>

      <div class="hcol hcol-c">
        <?php box_open('Trax', 'g'); ?><p class="muted"><?= h(setting_get('site.home_trax', "Le son de Habbo ! Compose tes propres mixes avec la Trax Machine directement en jeu.")) ?></p><div style="margin-top:6px"><?= clink('Écouter en jeu', '/client.php', '_blank') ?> <?= tlink('En savoir plus »', '?p=trax') ?></div><?php box_close(); ?>
        <?php box_open('Habbo Club', 'g'); ?><div style="text-align:center;margin-bottom:5px"><img src="/web-gallery/v2/images/habboclub/hc_banner.png" alt="Habbo Club" width="174" height="150" style="max-width:100%"></div><p class="muted"><?= h(setting_get('site.home_club', "Mobis exclusifs, cadeaux et badge doré avec le Habbo Club !")) ?></p><div style="margin-top:6px"><?= clink('Rejoindre', '?p=club') ?></div><?php box_close(); ?>
        <?php box_open('Battle Ball', 'g'); ?><div style="text-align:center;margin-bottom:5px"><img src="/web-gallery/v2/images/games/battleball.png" alt="Battle Ball" width="450" height="99" style="max-width:100%;height:auto"></div><p class="muted" style="margin-bottom:5px">Le jeu d'équipe le plus fun de l'hôtel ! Les meilleurs Battle Ballers du moment :</p><?= lb_table(board_top('battleball_points', 5)) ?><div style="margin-top:6px"><?= tlink('En savoir plus »', '?p=battleball') ?></div><?php box_close(); ?>
        <?php box_open('L\'Infobus', 'g'); ?><p class="muted"><?= h($infobus) ?></p><?php box_close(); ?>
      </div>

      <div class="hcol hcol-r">
        <?php box_open('Publicité', 'b'); ?>
          <div style="height:250px;display:flex;align-items:center;justify-content:center;text-align:center;background:#eef2f4;border:1px dashed #b9c6ce;color:#8a97a3;font-size:10px;line-height:1.5">Emplacement publicitaire<br>300 × 250<br><span style="font-size:9px">(image à configurer dans l'admin)</span></div>
        <?php box_close(); ?>
        <?php box_open('Les clans les plus actifs', 'b'); ?>
          <?php if (!$clans): ?><p class="muted">Aucun clan pour le moment.</p>
          <?php else: $ci = 0; foreach ($clans as $c): $ci++; ?><div style="padding:3px 0;border-bottom:1px dotted #eee"><b><?= $ci ?>.</b> <?= h((string)$c['name']) ?></div><?php endforeach; endif; ?>
        <?php box_close(); ?>
        <?php box_open('Habbo Homes', 'b'); ?>
          <p class="muted" style="margin-bottom:6px"><?= h(setting_get('site.home_homes', "Construis ta page perso : avatar, badges, amis et salles sur ta Habbo Home.")) ?></p>
          <?php $rh = recent_players(5); if ($rh): ?>
            <div class="muted" style="font-size:10px">Dernières Homes : <?php $lnk = []; foreach ($rh as $r) { $lnk[] = '<a href="?p=home/' . h(rawurlencode((string)$r['username'])) . '">' . h((string)$r['username']) . '</a>'; } echo implode(', ', $lnk); ?></div>
          <?php endif; ?>
          <div style="margin-top:6px"><?= clink('Voir les Homes', '?p=community') ?></div>
        <?php box_close(); ?>
        <?php box_open('Jeux Habbo', 'b'); ?>
          <div style="text-align:center;margin-bottom:5px"><img src="/web-gallery/v2/images/games_illustration.png" alt="Jeux Habbo" width="153" height="169" style="max-width:100%"></div>
          <p class="muted" style="font-weight:700;margin-bottom:3px">SnowStorm</p><?= lb_table(board_top('snowstorm_points', 3)) ?>
          <div style="margin-top:6px"><?= clink('Les jeux', '?p=games') ?></div>
        <?php box_close(); ?>
        <?php box_open('Activation de l\'adresse e-mail', 'b'); ?>
          <p class="muted"><?= h(setting_get('site.home_activation', "Vérifie ton adresse e-mail pour sécuriser ton compte et récupérer ton mot de passe.")) ?></p>
          <p class="muted" style="font-style:italic;color:#8a97a3;margin-top:4px">(Fonctionnalité à développer sur notre rétro.)</p>
        <?php box_close(); ?>
      </div>
    </div>
    <?php
    render_foot();
    exit;
}

/* ========================= PAGES DE CONTENU ========================= */
$u = me();

/* ---- Nouveau ? (inscription + guide) ---- */
if ($p === 'register') {
    render_head('register', null);
    echo '<div class="layout"><div class="maincol"><div class="mc2">';
    echo '<div class="mcol">';
    box_open('Crée ton Habbo — c\'est gratuit !', 'g');
    if ($u) echo '<p class="muted">Tu es déjà connecté en tant que <b>' . h($u['username']) . '</b>.</p><div style="margin-top:6px">' . nbtn('Entre dans l\'Hôtel', '/client.php', '_blank') . '</div>';
    else {
        if ($reg_err) echo '<div class="flash err">' . h($reg_err) . '</div>';
        echo '<form method="post" action="?p=register"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        echo '<p style="margin-bottom:3px"><b>Nom Habbo</b></p><input type="text" name="username" maxlength="20" required style="width:100%;border:2px solid #7f7f7f;padding:4px 6px;margin-bottom:6px">';
        echo '<p style="margin-bottom:3px"><b>Mot de passe</b></p><input type="password" name="password" required style="width:100%;border:2px solid #7f7f7f;padding:4px 6px;margin-bottom:6px">';
        echo '<p style="margin-bottom:3px"><b>Sexe</b></p><select name="sex" style="margin-bottom:6px"><option value="M">Garçon</option><option value="F">Fille</option></select>';
        echo '<p style="margin-bottom:3px"><b>Date de naissance</b> (JJ/MM/AAAA)</p><input type="text" name="birthday" placeholder="01/01/2000" style="width:100%;border:2px solid #7f7f7f;padding:4px 6px;margin-bottom:8px">';
        echo '<button class="new-button" type="submit"><b>Créer mon Habbo</b><i></i></button></form>';
    }
    box_close();
    box_open('Nouveau venu ? Jette un œil à ça !', 'o');
    echo '<ol style="padding-left:18px;color:#5b5b5b"><li style="margin:5px 0">Rends-toi dans l\'hôtel</li><li style="margin:5px 0">Fais ton Habbo (choisis ton look)</li><li style="margin:5px 0">Entre dans une pièce</li><li style="margin:5px 0">Fais ta Habbo Home !</li></ol>';
    box_close();
    echo '</div><div class="mcol">';
    box_open('Guide de bienvenue', 'b');
    echo '<ol class="hcsteps" style="padding-left:18px;color:#5b5b5b"><li style="margin:5px 0">Choisis un <b>pseudo</b> et un mot de passe.</li><li style="margin:5px 0">Connecte-toi, puis clique <b>« Entre dans l\'Hôtel »</b>.</li><li style="margin:5px 0">Le jeu s\'ouvre dans <b>Basilisk</b> (client d\'époque).</li><li style="margin:5px 0">Explore les salles, ajoute des amis, décore ton appart !</li></ol>';
    box_close();
    box_open('Kézako ? Les icônes du navigateur', 'b');
    echo '<ul style="list-style:none;color:#5b5b5b"><li style="padding:3px 0"><b>Habbo Console</b> — reste en contact avec tes amis.</li><li style="padding:3px 0"><b>Habbo Compte</b> — garde tes crédits au chaud.</li><li style="padding:3px 0"><b>Navigateur</b> — change de salle à tout moment.</li><li style="padding:3px 0"><b>On t\'ennuie ?</b> — appelle un modérateur à l\'aide.</li><li style="padding:3px 0"><b>Catalogue</b> — découvre et achète du mobi.</li></ul>';
    box_close();
    box_open('La sécurité d\'abord', 'o');
    echo '<p class="muted">Ne partage <b>jamais</b> ton mot de passe. Le staff ne te le demandera jamais.</p>';
    box_close();
    echo '</div></div></div>';
    echo '<div class="sidecol">';
    box_open('En ce moment', 'g'); echo '<div style="text-align:center"><img src="/web-gallery/v2/images/habbo_online_anim.gif" alt="" style="height:18px;vertical-align:middle;margin-right:5px"><b style="color:#3f6b16;font-size:14px">' . online_count() . '</b> <span class="muted">en ligne</span></div>'; box_close();
    box_open('Pas sûr d\'où aller ?', 'p');
    echo '<div style="padding:4px 0;border-bottom:1px dotted #eee"><b>La Réception</b><div class="muted">Les Habbo X t\'accueillent et te présentent l\'hôtel.</div></div>';
    echo '<div style="padding:4px 0;border-bottom:1px dotted #eee"><b>Le Habbo Lido</b><div class="muted">La piscine plein air, chauffée 24h/24.</div></div>';
    echo '<div style="padding:4px 0"><b>Battle Ball</b><div class="muted">Défends tes couleurs dans l\'arène !</div></div>';
    echo '<div style="margin-top:7px">' . nbtn('Entrer dans l\'Hôtel', '/client.php', '_blank') . '</div>';
    box_close();
    box_open('Habbo Club', 'p'); echo '<p class="muted">Adhère au Habbo Club et profite d\'avantages exclusifs !</p><div style="margin-top:6px">' . nbtn('En savoir plus', '?p=club') . '</div>'; box_close();
    echo '</div></div>';
    render_foot(); exit;
}

/* ---- Crédits ---- */
if ($p === 'credits') {
    render_head('credits', null);
    box_open('Mes Crédits', 'y');
    if ($u) { $c = 0; try { $c = (int) db()->query('SELECT credits FROM users WHERE id=' . (int)$u['id'])->fetchColumn(); } catch (Throwable $e) {}
        echo '<p style="font-size:14px">Solde actuel : <b style="color:#c98a00">' . number_format($c, 0, ',', ' ') . '</b> crédits.</p>'; }
    else echo '<p class="muted">Connecte-toi pour voir ton solde de crédits.</p>';
    echo '<p class="muted" style="margin-top:8px">Les crédits sont la monnaie de l\'hôtel : ils servent à acheter des <b>mobis</b>, des animaux, des vêtements et l\'abonnement au <b>Habbo Club</b> dans le Catalogue.</p>';
    box_close();
    box_open('Comment obtenir des crédits ?', 'b');
    echo '<p class="muted">Sur ce rétro, les crédits sont attribués par le <b>staff</b> et lors d\'événements en jeu. Participe aux animations et aux jeux pour en gagner !</p>';
    box_close();
    render_foot(); exit;
}

/* ---- Habbo Club ---- */
if ($p === 'club') {
    render_head('club', null);
    $hc = false; $exp = 0;
    if ($u) { try { $exp = (int) db()->query('SELECT club_expiration FROM users WHERE id=' . (int)$u['id'])->fetchColumn(); $hc = $exp > time(); } catch (Throwable $e) {} }
    echo '<div class="layout"><div class="maincol">';
    box_open('Le Habbo Club', 'p');
    echo '<p class="muted" style="margin-bottom:8px">Le <b>Habbo Club</b>, c\'est le cercle VIP de l\'hôtel : mobis exclusifs, cadeaux réguliers, vêtements et coiffures réservés, et un <b>badge doré</b> sur ta page perso.</p>';
    echo '<ul style="list-style:none;color:#5b5b5b"><li style="padding:3px 0">🛋️ Mobis exclusifs réservés aux membres</li><li style="padding:3px 0">🎁 Un cadeau à chaque période d\'abonnement</li><li style="padding:3px 0">⭐ Badge doré + vêtements club</li><li style="padding:3px 0">🏠 Agencements d\'apparts spéciaux</li></ul>';
    box_close();
    box_open('Comment devenir membre ?', 'o');
    echo '<ol class="hcsteps" style="padding-left:18px;color:#5b5b5b"><li>Entre dans l\'hôtel et ouvre le <b>Catalogue</b>.</li><li>Va dans la rubrique <b>Habbo Club</b>.</li><li>Choisis ta durée et paie en <b>crédits</b>.</li></ol>';
    box_close();
    echo '</div><div class="sidecol">';
    box_open('Ton statut', 'p');
    if ($hc) echo '<p style="text-align:center"><b style="color:#7d52a8">★ Membre HC</b><br><span class="muted">jusqu\'au ' . h(date('d/m/Y', $exp)) . '</span></p>';
    elseif ($u) echo '<p class="muted">Tu n\'es pas encore membre.</p>';
    else echo '<p class="muted">Connecte-toi pour voir ton statut.</p>';
    box_close();
    echo '</div></div>';
    render_foot(); exit;
}

/* ---- Jeux ---- */
if ($p === 'games') {
    render_head('games', null);
    echo '<div class="layout"><div class="maincol"><div class="mc2">';
    echo '<div class="mcol">';
    box_open('BattleBall', 'o');
    echo '<p class="muted" style="margin-bottom:6px">Colore un max de cases pour ton équipe ! Top joueurs :</p>' . lb_table(board_top('battleball_points', 10));
    box_close();
    echo '</div><div class="mcol">';
    box_open('SnowStorm', 'b');
    echo '<p class="muted" style="margin-bottom:6px">Bataille de boules de neige ! Top joueurs :</p>' . lb_table(board_top('snowstorm_points', 10));
    box_close();
    echo '</div></div></div><div class="sidecol">';
    box_open('Programme', 'g');
    echo '<p class="muted">' . h(setting_get('site.games_prog', 'Des tournois sont organisés régulièrement en jeu. Surveille les actualités !')) . '</p>';
    box_close();
    echo '</div></div>';
    render_foot(); exit;
}

/* ---- Messagerie : messages reçus du staff (intégration de la messagerie existante) ---- */
if ($p === 'messages') {
    render_head('home', null);
    $u = me();
    if (!$u) {
        box_open('Mes messages', 'b');
        echo '<p class="muted">Connecte-toi pour voir tes messages. <a href="?p=register">Se connecter / s\'inscrire</a></p>';
        box_close(); render_foot(); exit;
    }
    ensure_user_msgs();
    $list = db()->prepare('SELECT id,from_staff,subject,body,read_at,created_at FROM user_messages WHERE user_id=? ORDER BY id DESC'); $list->execute([(int)$u['id']]); $msgs = $list->fetchAll();
    try { db()->prepare('UPDATE user_messages SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([(int)$u['id']]); } catch (Throwable $e) {}
    box_open('✉ Mes messages', 'b');
    echo '<p class="muted" style="margin-bottom:8px">Les messages de l\'équipe de l\'hôtel.</p>';
    if (!$msgs) echo '<p class="muted">📭 Tu n\'as aucun message pour le moment.</p>';
    else foreach ($msgs as $m) {
        $new = empty($m['read_at']);
        echo '<div style="border:1px solid #d9d2b8;border-radius:4px;padding:8px;margin-bottom:6px;background:' . ($new ? '#fff7e6' : '#fff') . '">'
           . '<div style="font-size:10px;color:#8a97a3;margin-bottom:3px"><b style="color:#5b5b5b">👤 ' . h((string)($m['from_staff'] ?: 'Équipe')) . '</b>'
           . ($new ? ' <span style="background:#e5820c;color:#fff;padding:0 5px;border-radius:2px;font-weight:bold">Nouveau</span>' : '')
           . ' · ' . h(date('d/m/Y H:i', strtotime((string)$m['created_at']))) . '</div>';
        if (trim((string)$m['subject']) !== '') echo '<div style="font-weight:bold;font-size:12px;margin-bottom:2px;color:#30384a">' . h((string)$m['subject']) . '</div>';
        echo '<div style="font-size:11px;color:#5b5b5b">' . nl2br(h((string)$m['body'])) . '</div></div>';
    }
    box_close();
    render_foot(); exit;
}

/* ---- Communauté ---- */
if ($p === 'community') {
    render_head('community', null);
    $q = trim($_GET['q'] ?? '');
    $staff = []; $found = [];
    try { $staff = db()->query('SELECT username,sex,`rank` FROM users WHERE `rank`>=5 ORDER BY `rank` DESC, username')->fetchAll(); } catch (Throwable $e) {}
    if ($q !== '') { try { $st = db()->prepare('SELECT username,sex FROM users WHERE username LIKE ? ORDER BY username LIMIT 24'); $st->execute(['%' . $q . '%']); $found = $st->fetchAll(); } catch (Throwable $e) {} }
    // Clans réels (table groups Kepler) + Homes récentes
    $clans = [];
    foreach (['SELECT name, created AS c FROM groups ORDER BY id DESC LIMIT 5',
              'SELECT name, 0 AS c FROM groups ORDER BY id DESC LIMIT 5'] as $sql) {
        try { $clans = db()->query($sql)->fetchAll(); if ($clans) break; } catch (Throwable $e) {}
    }
    echo '<div class="layout"><div class="maincol">';
    box_open('La section Communauté', 'b');
    echo '<p class="muted">Ici tu retrouves tout ce qui fait vivre l\'hôtel : les <b>clans</b>, les <b>Habbo Homes</b>, le <b>staff</b>, et de quoi rechercher n\'importe quel Habbo. Implique-toi dans ta communauté !</p>';
    box_close();
    box_open('Rechercher un Habbo', 'b');
    echo '<form method="get" action="?"><input type="hidden" name="p" value="community"><input type="text" name="q" value="' . h($q) . '" placeholder="Nom d\'un Habbo…" style="border:2px solid #7f7f7f;padding:4px 6px"> <button class="new-button" type="submit"><b>Chercher</b><i></i></button></form>';
    if ($q !== '') {
        echo '<div style="display:flex;flex-wrap:wrap;margin-top:10px">';
        if (!$found) echo '<p class="muted">Aucun Habbo trouvé pour « ' . h($q) . ' ».</p>';
        else foreach ($found as $r) echo '<a href="?p=home/' . h(rawurlencode((string)$r['username'])) . '" style="width:60px;text-align:center;margin:0 6px 8px 0;font-size:9px;color:#30384a"><span style="display:block;width:48px;height:48px;background:#f3eecf;border:1px solid #d8cfa6;margin:0 auto 2px;overflow:hidden">' . av_svg((string)$r['username'], (string)$r['sex']) . '</span>' . h(mb_strimwidth((string)$r['username'], 0, 9, '…')) . '</a>';
        echo '</div>';
    }
    box_close();
    box_open('Les clans les plus actifs', 'g');
    if (!$clans) echo '<p class="muted">Aucun clan pour l\'instant.</p>';
    else { $i = 0; foreach ($clans as $c) { $i++; echo '<div style="padding:4px 0;border-bottom:1px dotted #eee"><b>' . $i . '. ' . h((string)$c['name']) . '</b></div>'; } }
    box_close();
    echo '</div><div class="sidecol">';
    box_open('L\'équipe', 'p');
    if (!$staff) echo '<p class="muted">Aucun membre du staff.</p>';
    else { echo '<div style="display:flex;flex-wrap:wrap">'; foreach ($staff as $s) echo '<a href="?p=home/' . h(rawurlencode((string)$s['username'])) . '" title="' . h((string)$s['username']) . '" style="width:56px;text-align:center;margin:0 4px 8px 0;font-size:9px;color:#30384a"><span style="display:block;width:44px;height:44px;background:#f3eecf;border:1px solid #d8cfa6;margin:0 auto 2px;overflow:hidden">' . av_svg((string)$s['username'], (string)$s['sex']) . '</span>' . h(mb_strimwidth((string)$s['username'], 0, 8, '…')) . '</a>'; echo '</div>'; }
    box_close();
    box_open('Habbo Homes', 'p');
    echo '<p class="muted" style="margin-bottom:7px">Découvre les pages perso des Habbos !</p><div style="display:flex;flex-wrap:wrap">';
    foreach (recent_players(6) as $r) echo '<a href="?p=home/' . h(rawurlencode((string)$r['username'])) . '" title="' . h((string)$r['username']) . '" style="width:50px;text-align:center;margin:0 4px 8px 0;font-size:9px;color:#30384a"><span style="display:block;width:44px;height:44px;background:#f3eecf;border:1px solid #d8cfa6;margin:0 auto 2px;overflow:hidden">' . av_svg((string)$r['username'], (string)$r['sex']) . '</span>' . h(mb_strimwidth((string)$r['username'], 0, 8, '…')) . '</a>';
    echo '</div>';
    box_close();
    echo '</div></div>';
    render_foot(); exit;
}

/* ---- Events ---- */
if ($p === 'events') {
    render_head('events', null);
    echo '<div class="layout"><div class="maincol">';
    box_open('Événements & animations', 'o');
    echo '<p class="muted">Retrouve les dernières annonces de l\'hôtel :</p>';
    foreach (news_items(8) as $n) { $nid = (int)($n['id'] ?? 0); $resume = trim((string)($n['summary'] ?? '')) !== '' ? (string)$n['summary'] : mb_strimwidth(strip_tags((string)$n['body']), 0, 160, '…'); echo '<div style="border-bottom:1px dotted #ddd6c2;padding:6px 0"><span style="color:#b23b2e;font-weight:700">[' . h(date('d/m/y', strtotime((string)$n['created_at']))) . ']</span> ' . ($nid ? '<a href="?p=actu&id=' . $nid . '" style="font-weight:700">' . h((string)$n['title']) . '</a>' : '<b>' . h((string)$n['title']) . '</b>') . '<div class="muted">' . h($resume) . '</div></div>'; }
    box_close();
    echo '</div><div class="sidecol">';
    box_open('Infobus', 'g');
    echo '<p class="muted">' . h(setting_get('site.infobus', 'Dans les Jardins Habbos ! Mercredi 16h30–17h30 et Vendredi 17h–18h.')) . '</p>';
    box_close();
    echo '</div></div>';
    render_foot(); exit;
}

/* ---- Aide ---- */
if ($p === 'help') {
    render_head('help', null);
    $faq = [
        ['Comment entrer dans l\'hôtel ?', 'Connecte-toi, puis clique « Entre dans l\'Hôtel ». Le jeu s\'ouvre dans Basilisk.'],
        ['J\'ai oublié mon mot de passe', 'La récupération se fait à la main : contacte un membre du staff (modérateur/admin).'],
        ['À quoi servent les crédits ?', 'À acheter des mobis, animaux, vêtements et l\'abonnement Habbo Club dans le Catalogue.'],
        ['Qu\'est-ce que le Habbo Club ?', 'Un abonnement VIP : mobis et vêtements exclusifs, cadeaux et badge doré.'],
        ['Comment protéger mon compte ?', 'Ne partage jamais ton mot de passe, même avec quelqu\'un se disant du staff.'],
    ];
    box_open('Centre d\'aide', 'b');
    foreach ($faq as $qa) echo '<div style="border-bottom:1px dotted #ddd6c2;padding:7px 0"><b style="color:#2f6f9f">' . h($qa[0]) . '</b><div class="muted" style="margin-top:2px">' . h($qa[1]) . '</div></div>';
    box_close();
    render_foot(); exit;
}

/* ---- Mon Habbo (accueil compte) ---- */
if ($p === 'me') {
    render_head('home', null); // Mon Habbo = raccourci haut (pas d'onglet nav dédié)
    if (!$u) { box_open('Mon Habbo', 'b'); echo '<p class="muted">Connecte-toi pour accéder à ton compte.</p>'; box_close(); render_foot(); exit; }
    $me = null; try { $me = db()->query('SELECT username,motto,credits,club_expiration FROM users WHERE id=' . (int)$u['id'])->fetch(); } catch (Throwable $e) {}
    box_open('Salut ' . h($u['username']) . ' !', 'g');
    if ($me) echo '<p class="muted">Mission : <b>' . h((string)$me['motto']) . '</b><br>Crédits : <b>' . number_format((int)$me['credits'], 0, ',', ' ') . '</b> · Club : ' . ((int)$me['club_expiration'] > time() ? 'actif' : 'non membre') . '</p>';
    echo '<div style="margin-top:8px">' . nbtn('Entre dans l\'Hôtel', '/client.php', '_blank') . nbtn('Ma Home publique', '?p=home/' . rawurlencode((string)$u['username'])) . '</div>';
    box_close();
    render_foot(); exit;
}

/* ---- Boutique / Mobile : état explicite ---- */
if (isset($STUBS[$p])) {
    [$title, $msg] = $STUBS[$p];
    render_head($p);
    wip_box($title, $msg);
    render_foot(); exit;
}

/* ---- Article d'actualité complet : ?p=actu&id=N ---- */
if ($p === 'actu') {
    $id = (int)($_GET['id'] ?? 0);
    $n = news_one($id);
    $preview = isset($_GET['preview']) && is_staff();
    $published = $n && (($n['status'] ?? 'published') === 'published' || $preview);
    render_head('events', null);
    if (!$n || !$published) {
        box_open('Actualité introuvable', 'o');
        echo '<div class="wip"><span class="ico">📰</span><h3>Cette actualité n\'est pas disponible</h3><p><a href="?p=events">Voir toutes les actualités »</a></p></div>';
        box_close(); render_foot(); exit;
    }
    if ($preview && ($n['status'] ?? 'published') !== 'published') echo '<div class="hist-note" style="background:#e7f0ff;border-color:#9fbce8;color:#234">👁️ <b>Aperçu staff</b> — brouillon non publié. Non visible par les visiteurs.</div>';
    echo '<div class="layout"><div class="maincol">';
    box_open(h((string)$n['title']), 'b');
    echo '<div class="muted" style="font-size:10px;margin-bottom:8px">' . h(date('d/m/Y', strtotime((string)$n['created_at']))) . (($n['author'] ?? '') !== '' ? ' · par ' . h((string)$n['author']) : '') . '</div>';
    if (($n['image'] ?? '') !== '') echo '<img src="' . h((string)$n['image']) . '" alt="" style="max-width:100%;border-radius:3px;margin-bottom:8px">';
    echo '<div class="imported">' . ($n['body'] ?? '') . '</div>';
    echo '<div style="margin-top:10px">' . nbtn('Toutes les actus »', '?p=events') . '</div>';
    box_close();
    echo '</div><div class="sidecol">';
    box_open('À ne pas manquer', 'o');
    foreach (news_items(5) as $o) { if ((int)($o['id'] ?? 0) === $id) continue; echo '<div style="padding:4px 0;border-bottom:1px dotted #eee"><a href="?p=actu&id=' . (int)($o['id'] ?? 0) . '">' . h((string)$o['title']) . '</a></div>'; }
    box_close();
    echo '</div></div>';
    render_foot(); exit;
}

/* ================= SOUS-PAGES (sous-menus barre jaune) ================= */

/* --- Pages importées fidèlement (table site_pages, éditables dans l'admin) — PRIORITAIRES --- */
$spage = site_page_get($p);
if ($spage) {
    $status  = $spage['status'] ?? 'published';
    $preview = isset($_GET['preview']) && is_staff();
    $body    = $preview ? (trim((string)($spage['body_draft'] ?? '')) !== '' ? (string)$spage['body_draft'] : (string)$spage['body_html']) : (string)$spage['body_html'];
    $published = ($status === 'published') || $preview;
    if ($published && trim($body) !== '') {
        render_head((string)($spage['parent_tab'] ?: 'home'), null);
        if ($preview) echo '<div class="hist-note" style="background:#e7f0ff;border-color:#9fbce8;color:#234">👁️ <b>Aperçu staff</b> — ' . ((trim((string)($spage['body_draft'] ?? '')) !== '') ? 'brouillon non publié' : 'version publiée') . '. Non visible par les visiteurs.</div>';
        if ((int)$spage['is_historical']) echo '<div class="hist-note">📅 <b>Contenu d\'archive.</b> Ce texte reproduit une page d\'époque (2006-2007) : l\'offre ou l\'événement décrit n\'est pas actif sur ce rétro, le texte est conservé à l\'identique pour fidélité.</div>';
        render_carousel($p); // carrousel propre à cette page (si configuré dans l'admin)
        echo '<div class="imported">' . $body . '</div>';
        render_foot(); exit;
    }
    if ($status !== 'published') {
        render_head((string)($spage['parent_tab'] ?: 'home'), null);
        box_open('Page non disponible', 'o');
        echo '<div class="wip"><span class="ico">🚧</span><h3>Cette page n\'est pas publiée</h3><p>Elle est momentanément indisponible. Reviens bientôt !</p></div>';
        box_close();
        render_foot(); exit;
    }
}

/* --- Autres sous-pages : accessibles, état honnête (reproduction à faire) --- */
$SUBREF = [
    // Habbo Hotel (register)
    'staff'=>['Les staffs','register','staff.php'], 'mods'=>['Les SOS','register','mods.php'],
    'furniture'=>['Mobilier','register','furniture.php'], 'ecotron'=>['Ecotron','register','ecotron.php'],
    'pets'=>['Animaux','register','pets.php'], 'home_info'=>['Habbo Home','register','habbo_home.php'],
    'clans'=>['Habbo Clans','register','clan.php'], 'new'=>['Nouveautés !','register','new.php'],
    // Communauté
    'fansite'=>['Sites de Fan','community','fansite.php'], 'apparts'=>['L\'appart de la semaine','community','apparts.php'],
    'anepasmanquer'=>['A ne pas manquer !','community','events.php'], 'homefriends'=>['Fais connaître ta Habbo Home !','community','homefriends.php'],
    'invite'=>['Invite un ami !','community','invite.php'], 'filsante'=>['Fil Santé Jeunes','community','filsantejeune.php'],
    // Events
    'tchatvip'=>['VIP Music Room','events','tchatvip.php'], 'bobbaweek'=>['Bobbaweek','events','bobbaweek.php'], 'habboscope'=>['Habboscope','events','habboscope.php'],
    // Jeux
    'rodeau'=>['Rodéau','games','rodeau.php'], 'plongeoir'=>['Le Grand Plongeon','games','plongeoir.php'], 'gamesweek'=>['Le jeu Habbo de la Semaine','games','gamesweek.php'],
    // Boutique
    'minicartes'=>['Les Habbos Minicartes','shop','shop.php'], 'tshirt'=>['T-Shirt','shop','tshirt.php'],
    // Mobile
    'sonneries'=>['Sonneries','mobile','habbomobile.php'], 'fondecran'=>['Fond d\'écran','mobile','habbomobile.php'], 'imager'=>['Habbo Imager','mobile','habbomobile.php'],
    // Crédits
    'coinsfr'=>['Crédits — France','credits','coinsfr.php'], 'coinsbe'=>['Crédits — Belgique','credits','coinsbe.php'], 'coinsch'=>['Crédits — Suisse','credits','coinsch.php'],
    'coinsca'=>['Crédits — Canada','credits','coinsca.php'], 'coinsother'=>['Crédits — Autres pays','credits','coinsother.php'], 'creditshelp'=>['Comment utiliser les crédits','credits','creditshelp.php'],
    // HC
    'hcjoin'=>['Rejoins le HC','club','hcjoin.php'], 'hcshop'=>['Club Shop','club','hcshop.php'],
    // Aide
    'contact'=>['Contacte-nous','help','contact.php'], 'faqs'=>['FAQs','help','faqs.php'], 'parents_guide'=>['Guide des parents','help','parents_guide.php'],
    'habbo_way'=>['Habbo Attitude','help','habbo_way.php'], 'email'=>['Vérifie ton email !','help','email.php'], 'habbo_x'=>['Habbo X','help','habbo_x.php'],
    'habboredac'=>['HabboRédac','help','HabboRedac.php'], 'account_security'=>['Conseils de sécurité','help','Account_security.php'],
];
if (isset($SUBREF[$p])) {
    [$title, $parent, $ref] = $SUBREF[$p];
    render_head($parent, null);
    box_open(mb_strtoupper($title), 'o');
    echo '<div class="wip"><span class="ico">🚧</span><h3>Reproduction en cours</h3>'
       . '<p>Cette sous-page existe et son lien fonctionne. Sa reproduction fidèle (contenu, illustrations, mise en page de l\'original) reste à faire.</p>'
       . '<p class="muted" style="margin-top:6px;font-size:10px">Référence d\'origine : <code>' . h($ref) . '</code></p></div>';
    box_close();
    render_foot(); exit;
}

/* ---- Signer le livre d'or ---- */
if ($p === 'home_sign') {
    $u = me();
    $backName = trim((string)($_POST['owner_name'] ?? ''));
    $back = '?p=home/' . rawurlencode($backName);
    if (!$u) redirect($back . '&need=login');
    if (!csrf_ok()) redirect($back);
    $ownerId = (int)($_POST['owner_id'] ?? 0);
    $owner = null; try { $o = db()->prepare('SELECT id,username FROM users WHERE id=?'); $o->execute([$ownerId]); $owner = $o->fetch(); } catch (Throwable $e) {}
    if (!$owner) redirect('?p=community');
    $msg = trim(strip_tags((string)($_POST['message'] ?? '')));
    $msg = mb_substr($msg, 0, 255);
    if ($msg === '') redirect($back);
    ensure_home();
    // Anti-spam : pas 2 messages du même auteur sur la même Home à moins de 30 s
    try {
        $t = db()->prepare('SELECT created_at FROM home_guestbook WHERE owner_id=? AND author_id=? ORDER BY id DESC LIMIT 1');
        $t->execute([$ownerId, (int)$u['id']]);
        $last = $t->fetchColumn();
        if ($last && (time() - strtotime((string)$last)) < 30) redirect($back . '&done=slow');
        db()->prepare('INSERT INTO home_guestbook (owner_id,author_id,author_name,message) VALUES (?,?,?,?)')
            ->execute([$ownerId, (int)$u['id'], (string)$u['username'], $msg]);
    } catch (Throwable $e) {}
    redirect($back . '&done=signed#gb');
}
/* ---- Supprimer un message du livre d'or (propriétaire de la Home, ou staff) ---- */
if ($p === 'home_gb_delete') {
    $u = me();
    if (!$u || !csrf_ok()) redirect('?p=community');
    $mid = (int)($_POST['mid'] ?? 0);
    ensure_home();
    $row = null; try { $r = db()->prepare('SELECT g.id,g.owner_id,o.username FROM home_guestbook g JOIN users o ON o.id=g.owner_id WHERE g.id=?'); $r->execute([$mid]); $row = $r->fetch(); } catch (Throwable $e) {}
    if (!$row) redirect('?p=community');
    $back = '?p=home/' . rawurlencode((string)$row['username']);
    if ((int)$row['owner_id'] !== (int)$u['id'] && !is_staff()) redirect($back); // seul le proprio (ou staff)
    try { db()->prepare('UPDATE home_guestbook SET hidden=1 WHERE id=?')->execute([$mid]); } catch (Throwable $e) {}
    redirect($back . '&done=gbdel#gb');
}
/* ---- Signaler une Home ou un message ---- */
if ($p === 'home_report') {
    $u = me();
    $type = ($_POST['type'] ?? '') === 'gb' ? 'gb' : 'home';
    $tid  = (int)($_POST['tid'] ?? 0);
    ensure_home();
    // Résoudre owner + lien retour
    $ownerId = 0; $ownerName = '';
    if ($type === 'home') { try { $o = db()->prepare('SELECT id,username FROM users WHERE id=?'); $o->execute([$tid]); if ($r = $o->fetch()) { $ownerId = (int)$r['id']; $ownerName = (string)$r['username']; } } catch (Throwable $e) {} }
    else { try { $o = db()->prepare('SELECT g.owner_id,usr.username FROM home_guestbook g JOIN users usr ON usr.id=g.owner_id WHERE g.id=?'); $o->execute([$tid]); if ($r = $o->fetch()) { $ownerId = (int)$r['owner_id']; $ownerName = (string)$r['username']; } } catch (Throwable $e) {} }
    $back = $ownerName !== '' ? '?p=home/' . rawurlencode($ownerName) : '?p=community';
    if (!$u) redirect($back . '&need=login');
    if (!csrf_ok()) redirect($back);
    $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 40);
    $detail = mb_substr(trim(strip_tags((string)($_POST['detail'] ?? ''))), 0, 400);
    if ($reason === '') redirect($back . '&done=needreason');
    try {
        // Anti-spam : même signaleur, même cible, < 24 h → ignorer
        $c = db()->prepare('SELECT COUNT(*) FROM home_reports WHERE target_type=? AND target_id=? AND reporter_id=? AND created_at > (NOW() - INTERVAL 1 DAY)');
        $c->execute([$type, $tid, (int)$u['id']]);
        if ((int)$c->fetchColumn() === 0)
            db()->prepare('INSERT INTO home_reports (target_type,target_id,owner_id,reporter_id,reporter_name,reason,detail) VALUES (?,?,?,?,?,?,?)')
                ->execute([$type, $tid, $ownerId, (int)$u['id'], (string)$u['username'], $reason, $detail]);
    } catch (Throwable $e) {}
    redirect($back . '&done=reported');
}

/* ---- Ajouter en ami depuis une Home (session + CSRF) ---- */
if ($p === 'home_addfriend') {
    $u = me();
    $fid = (int)($_POST['fid'] ?? 0);
    $oname = ''; try { $o = db()->prepare('SELECT username FROM users WHERE id=?'); $o->execute([$fid]); $oname = (string)$o->fetchColumn(); } catch (Throwable $e) {}
    $back = $oname !== '' ? '?p=home/' . rawurlencode($oname) : '?p=community';
    if (!$u) redirect($back . '&need=login');
    if (!csrf_ok() || $fid <= 0 || $fid === (int)$u['id']) redirect($back);
    try {
        $c = db()->prepare('SELECT COUNT(*) FROM messenger_friends WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?)');
        $c->execute([(int)$u['id'], $fid, $fid, (int)$u['id']]);
        if ((int)$c->fetchColumn() === 0) db()->prepare('INSERT INTO messenger_friends (from_id,to_id) VALUES (?,?)')->execute([(int)$u['id'], $fid]);
    } catch (Throwable $e) {}
    redirect($back . '&done=friend');
}

/* ---- Sauvegarde de MA Home (propriétaire via session, jamais via id de requête) ---- */
if ($p === 'home_save') {
    header('Content-Type: application/json');
    $u = me();
    if (!$u) { echo json_encode(['ok' => false, 'err' => 'auth']); exit; }
    if (!csrf_ok()) { echo json_encode(['ok' => false, 'err' => 'csrf']); exit; }
    $oid = (int)$u['id'];
    $home = home_for_user($oid);
    if ((int)($home['edit_locked'] ?? 0) === 1) { echo json_encode(['ok' => false, 'err' => 'locked']); exit; }
    $data = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($data)) { echo json_encode(['ok' => false, 'err' => 'data']); exit; }
    $bg = (string)($data['background'] ?? '');
    if ($bg !== '' && !home_asset_ok('bg', $bg)) $bg = '';
    else $bg = $bg === '' ? '' : basename($bg);
    $allowed = ['widget_profile', 'widget_friends', 'widget_badges', 'widget_rooms', 'widget_guestbook', 'widget_notes', 'sticker'];
    $skins = home_skins();
    $rows = [];
    foreach ((array)($data['items'] ?? []) as $it) {
        $type = (string)($it['type'] ?? '');
        if (!in_array($type, $allowed, true)) continue;
        $res = '';
        if ($type === 'sticker') { $res = basename((string)($it['resource'] ?? '')); if (!home_asset_ok('sticker', $res)) continue; }
        $x = max(0, min(920, (int)($it['x'] ?? 0)));
        $y = max(0, min(1360, (int)($it['y'] ?? 0)));
        $z = max(0, min(999, (int)($it['z'] ?? 1)));
        $style = (string)($it['style'] ?? '');
        if ($type === 'sticker' || !isset($skins[$style])) $style = '';
        $content = $type === 'widget_notes' ? mb_substr(trim(strip_tags((string)($it['content'] ?? ''))), 0, 600) : null;
        $rows[] = [$type, $res, $x, $y, $z, $content, $style];
        if (count($rows) >= 80) break;
    }
    try {
        db()->beginTransaction();
        db()->prepare('DELETE FROM home_items WHERE user_id=?')->execute([$oid]);
        $st = db()->prepare('INSERT INTO home_items (user_id,type,resource,x,y,z,content,style) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) $st->execute([$oid, $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]]);
        db()->prepare('INSERT INTO home_pages (user_id,background,published) VALUES (?,?,1) ON DUPLICATE KEY UPDATE background=VALUES(background),published=1')->execute([$oid, $bg]);
        db()->commit();
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); echo json_encode(['ok' => false, 'err' => 'db']); }
    exit;
}

/* ---- Éditeur de MA Home (propriétaire uniquement) ---- */
if ($p === 'home_edit') {
    $u = me();
    if (!$u) { redirect('?p=home'); }
    $oid = (int)$u['id'];
    $owner = ['id' => $oid, 'username' => $u['username'], 'sex' => 'M', 'motto' => ''];
    try { $r = db()->prepare('SELECT username,sex,motto FROM users WHERE id=?'); $r->execute([$oid]); if ($row = $r->fetch()) $owner = array_merge($owner, $row); } catch (Throwable $e) {}
    $home = home_for_user($oid);
    $locked = (int)($home['edit_locked'] ?? 0) === 1;
    $items = home_items($oid);
    // Pré-rendu des widgets (vraies données du propriétaire) pour l'ajout en direct
    $types = ['widget_profile', 'widget_friends', 'widget_badges', 'widget_rooms', 'widget_guestbook', 'widget_notes'];
    $wmap = [];
    foreach ($types as $t) $wmap[$t] = home_widget_html($t, $owner, $oid, '', $t === 'widget_notes' ? '{{NOTES}}' : '', 'edit');
    $labels = ['widget_profile' => 'Profil', 'widget_friends' => 'Mes amis', 'widget_badges' => 'Mes badges', 'widget_rooms' => 'Mes apparts', 'widget_guestbook' => 'Livre d\'or', 'widget_notes' => 'Pense-bête'];
    $jsItems = [];
    foreach ($items as $it) $jsItems[] = ['type' => $it['type'], 'resource' => (string)$it['resource'], 'x' => (int)$it['x'], 'y' => (int)$it['y'], 'z' => (int)$it['z'], 'content' => (string)($it['content'] ?? ''), 'style' => (string)($it['style'] ?? '')];
    $bgList = home_asset_list('bg');
    $stList = home_asset_list('sticker');
    render_head('community');
    if ($locked) { box_open('Édition bloquée', 'o'); echo '<p class="muted">L\'édition de ta Home est momentanément bloquée par la modération. Tu peux toujours la consulter.</p><div style="margin-top:8px">' . nbtn('Voir ma Home', '?p=home/' . rawurlencode((string)$owner['username'])) . '</div>'; box_close(); render_foot(); exit; }
    echo home_css();
    $ech = 460; foreach ($items as $it) $ech = max($ech, (int)$it['y'] + 210);
    if ((string)$home['background'] !== '' && home_asset_ok('bg', (string)$home['background'])) { $sz = @getimagesize(home_asset_dir('bg') . '/' . basename((string)$home['background'])); if ($sz && $sz[1] > 0) $ech = max($ech, (int)$sz[1]); }
    $ech = min($ech, 1360);
    ?>
    <div id="hedit-bar" style="display:flex;align-items:center;gap:8px;background:#2f6f9f;color:#fff;padding:6px 10px;border:1px solid #06252b;border-bottom:0">
      <b style="font-size:12px">✎ Personnalisation de ta Home</b>
      <span id="hedit-dirty" style="font-size:10px;background:#e3b505;color:#5a4600;padding:2px 7px;border-radius:10px;display:none">● modifications non publiées</span>
      <span style="flex:1"></span>
      <a class="new-button" href="#" onclick="return hePalette('bg')"><b>Fond</b><i></i></a>
      <a class="new-button" href="#" onclick="return hePalette('sticker')"><b>Stickers</b><i></i></a>
      <a class="new-button" href="#" onclick="return hePalette('widget')"><b>Widgets</b><i></i></a>
      <a class="new-button" href="#" onclick="return heSave()"><b>💾 Enregistrer</b><i></i></a>
      <a class="new-button" href="#" onclick="return heCancel()"><b>Annuler</b><i></i></a>
      <a class="new-button" href="?p=home/<?= h(rawurlencode((string)$owner['username'])) ?>"><b>Voir ↗</b><i></i></a>
    </div>
    <div id="hedit-canvas" style="position:relative;width:928px;max-width:100%;height:<?= $ech ?>px;border:1px solid #06252b;background:#c9dbe6;overflow:hidden;user-select:none"></div>

    <!-- Palette -->
    <div id="hepal" style="display:none;position:fixed;left:50%;top:90px;transform:translateX(-50%);width:420px;max-width:92vw;max-height:72vh;overflow:auto;background:#fff;border:2px solid #06252b;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.4);z-index:9999;padding:10px">
      <div style="display:flex;align-items:center;margin-bottom:6px"><b id="hepal-title" style="flex:1">Palette</b><a href="#" onclick="document.getElementById('hepal').style.display='none';return false" style="font-weight:bold">✕</a></div>
      <input id="hepal-q" type="text" placeholder="Rechercher…" oninput="heRenderPal()" style="width:100%;border:1px solid #9ab;padding:4px 6px;margin-bottom:6px;display:none">
      <div id="hepal-body"></div>
      <div id="hepal-more" style="text-align:center;margin-top:6px"></div>
    </div>

    <script>
    var HE = {
      owner: <?= json_encode((string)$owner['username']) ?>,
      bg: <?= json_encode((string)$home['background']) ?>,
      items: <?= json_encode($jsItems) ?>,
      widgets: <?= json_encode($wmap) ?>,
      labels: <?= json_encode($labels) ?>,
      bgList: <?= json_encode($bgList) ?>,
      stList: <?= json_encode($stList) ?>,
      skins: <?= json_encode(array_keys(home_skins())) ?>,
      skinCls: <?= json_encode(array_map('home_skin_class', array_combine(array_keys(home_skins()), array_keys(home_skins())))) ?>,
      csrf: <?= json_encode(csrf()) ?>,
      dirty: false, palKind: '', palShown: 48
    };
    function heBgUrl(f){return '/c_images/myhabbo/backgrounds2/'+encodeURIComponent(f);}
    function heStUrl(f){return '/c_images/myhabbo/stickers/'+encodeURIComponent(f);}
    function heMarkDirty(){HE.dirty=true;document.getElementById('hedit-dirty').style.display='inline-block';}
    function heApplyBg(){var c=document.getElementById('hedit-canvas');c.style.background=HE.bg?('#c9dbe6 url('+heBgUrl(HE.bg)+') no-repeat top center'):'#c9dbe6';}
    function heMaxZ(){var m=0;HE.items.forEach(function(it){if(it.z>m)m=it.z;});return m;}
    function heRender(){
      var c=document.getElementById('hedit-canvas');c.innerHTML='';heApplyBg();
      HE.items.forEach(function(it,idx){
        var el=document.createElement('div');el.className='heditem';el.style.cssText='position:absolute;left:'+it.x+'px;top:'+it.y+'px;z-index:'+it.z+';cursor:move';
        var inner='', isW=(it.type!=='sticker');
        if(!isW){inner='<img src="'+heStUrl(it.resource)+'" alt="" style="display:block;pointer-events:none">';}
        else{var w=HE.widgets[it.type]||'';if(it.type==='widget_notes'){w=w.replace('{{NOTES}}',(it.content||'').replace(/</g,'&lt;'));}inner='<div style="pointer-events:none">'+w+'</div>';}
        var btn='cursor:pointer;border:1px solid #06252b;background:#fff;border-radius:3px;font-size:10px;padding:1px 4px';
        el.innerHTML='<div class="hetools" style="position:absolute;top:3px;right:4px;z-index:5;display:flex;gap:2px">'
          +(isW?'<button title="Changer le skin du widget" onclick="heSkin('+idx+')" style="'+btn+'">🎨</button>':'')
          +'<button title="Au premier plan" onclick="heFront('+idx+')" style="'+btn+'">⤒</button>'
          +(it.type==='widget_notes'?'<button title="Modifier le texte" onclick="heNote('+idx+')" style="'+btn+'">✎</button>':'')
          +'<button title="Retirer cet élément" onclick="heDel('+idx+')" style="cursor:pointer;border:1px solid #a33;background:#fdeaee;color:#a33;border-radius:3px;font-size:10px;padding:1px 4px">✕</button></div>'+inner;
        if(isW){var hw=el.querySelector('.hw');if(hw){var k=(it.style&&HE.skins.indexOf(it.style)>-1)?it.style:'habbohomes';hw.className='hw '+(HE.skinCls[k]||'w_skin_defaultskin');}}
        heDrag(el,idx);c.appendChild(el);
      });
    }
    function heDrag(el,idx){
      el.addEventListener('mousedown',function(ev){
        if(ev.target.tagName==='BUTTON')return;
        ev.preventDefault();var c=document.getElementById('hedit-canvas');var r=c.getBoundingClientRect();
        var it=HE.items[idx];var ox=ev.clientX-r.left-it.x, oy=ev.clientY-r.top-it.y;
        function mv(e){var nx=e.clientX-r.left-ox, ny=e.clientY-r.top-oy;var ew=el.offsetWidth,eh=el.offsetHeight;nx=Math.max(0,Math.min(c.clientWidth-ew,nx));ny=Math.max(0,Math.min(c.clientHeight-eh,ny));it.x=Math.round(nx);it.y=Math.round(ny);el.style.left=it.x+'px';el.style.top=it.y+'px';}
        function up(){document.removeEventListener('mousemove',mv);document.removeEventListener('mouseup',up);heMarkDirty();}
        document.addEventListener('mousemove',mv);document.addEventListener('mouseup',up);
      });
    }
    function heFront(idx){HE.items[idx].z=heMaxZ()+1;heMarkDirty();heRender();}
    function heSkin(idx){var cur=HE.items[idx].style||HE.skins[0];var i=HE.skins.indexOf(cur);HE.items[idx].style=HE.skins[(i+1)%HE.skins.length];heMarkDirty();heRender();}
    function heDel(idx){if(!confirm('Retirer cet élément ?'))return;HE.items.splice(idx,1);heMarkDirty();heRender();}
    function heNote(idx){var t=prompt('Texte du pense-bête :',HE.items[idx].content||'');if(t!==null){HE.items[idx].content=t;heMarkDirty();heRender();}}
    function heAddWidget(type){
      if(type!=='widget_notes' && HE.items.some(function(it){return it.type===type;})){alert('Ce widget est déjà sur ta Home.');return;}
      HE.items.push({type:type,resource:'',x:40,y:40,z:heMaxZ()+1,content:type==='widget_notes'?'Nouvelle note':''});heMarkDirty();heRender();document.getElementById('hepal').style.display='none';
    }
    function heAddSticker(f){HE.items.push({type:'sticker',resource:f,x:60,y:60,z:heMaxZ()+1,content:''});heMarkDirty();heRender();}
    function heSetBg(f){HE.bg=f;heMarkDirty();heApplyBg();}
    function hePalette(kind){HE.palKind=kind;HE.palShown=48;document.getElementById('hepal').style.display='block';
      document.getElementById('hepal-title').textContent=kind==='bg'?'Fonds':(kind==='sticker'?'Stickers':'Widgets');
      document.getElementById('hepal-q').style.display=(kind==='widget')?'none':'block';document.getElementById('hepal-q').value='';heRenderPal();return false;}
    function heRenderPal(){
      var k=HE.palKind,b=document.getElementById('hepal-body'),more=document.getElementById('hepal-more');b.innerHTML='';more.innerHTML='';
      if(k==='widget'){
        Object.keys(HE.labels).forEach(function(t){var used=HE.items.some(function(it){return it.type===t;})&&t!=='widget_notes';
          b.innerHTML+='<a href="#" onclick="heAddWidget(\''+t+'\');return false" style="display:block;padding:7px 9px;border:1px solid #cdd;border-radius:5px;margin-bottom:5px;text-decoration:none;color:'+(used?'#aaa':'#235')+'">'+(used?'✓ ':'➕ ')+HE.labels[t]+(used?' (déjà placé)':'')+'</a>';});
        return;
      }
      var list=(k==='bg')?HE.bgList:HE.stList;var q=(document.getElementById('hepal-q').value||'').toLowerCase();
      if(q)list=list.filter(function(f){return f.toLowerCase().indexOf(q)>-1;});
      var grid=document.createElement('div');grid.style.cssText='display:flex;flex-wrap:wrap;gap:5px';
      var show=list.slice(0,HE.palShown);
      show.forEach(function(f){var u=(k==='bg')?heBgUrl(f):heStUrl(f);
        var cell=document.createElement('div');cell.style.cssText='width:'+(k==='bg'?'100px':'52px')+';text-align:center;cursor:pointer;border:1px solid #cdd;border-radius:4px;padding:2px;background:#f7f9fa';
        cell.title=f;cell.innerHTML='<img src="'+u+'" loading="lazy" style="width:100%;height:'+(k==='bg'?'70px':'46px')+';object-fit:contain">';
        cell.onclick=function(){if(k==='bg')heSetBg(f);else heAddSticker(f);};grid.appendChild(cell);
      });
      b.appendChild(grid);
      b.insertAdjacentHTML('afterbegin','<div style="font-size:10px;color:#789;margin-bottom:4px">'+list.length+' éléments'+(k==='bg'?' · clique pour appliquer le fond':' · clique pour ajouter')+'</div>');
      if(list.length>HE.palShown)more.innerHTML='<a href="#" onclick="HE.palShown+=48;heRenderPal();return false">Voir plus ('+(list.length-HE.palShown)+')</a>';
    }
    function heSave(){
      var fd=new FormData();fd.append('csrf',HE.csrf);fd.append('payload',JSON.stringify({background:HE.bg,items:HE.items}));
      fetch('?p=home_save',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
        if(j.ok){HE.dirty=false;document.getElementById('hedit-dirty').style.display='none';alert('✅ Ta Home est enregistrée et publiée !');}
        else alert('Erreur : '+(j.err||'inconnue')+(j.err==='locked'?' (édition bloquée par la modération)':''));
      }).catch(function(){alert('Erreur réseau.');});return false;
    }
    function heCancel(){if(HE.dirty&&!confirm('Annuler toutes les modifications non enregistrées ?'))return false;location.reload();return false;}
    window.addEventListener('beforeunload',function(e){if(HE.dirty){e.preventDefault();e.returnValue='';}});
    heRender();
    </script>
    <?php
    render_foot(); exit;
}

/* ---- Habbo Home publique : ?p=home/<pseudo> ---- */
if (strpos($p, 'home/') === 0) {
    $who = rawurldecode(substr($p, 5));
    $owner = user_by_name($who);
    render_head('community');
    if (!$owner) {
        box_open('Habbo Home introuvable', 'o');
        echo '<div class="wip"><span class="ico">🏠</span><h3>Cette Habbo Home n\'existe pas</h3><p>Le Habbo « ' . h($who) . ' » est introuvable. <a href="?p=community">Rechercher un Habbo »</a></p></div>';
        box_close(); render_foot(); exit;
    }
    $oid = (int)$owner['id'];
    $home = home_for_user($oid);
    $viewer = me();
    $isOwner = $viewer && (int)$viewer['id'] === $oid;
    if ((int)($home['hidden'] ?? 0) === 1 && !$isOwner && !is_staff()) {
        box_open('Habbo Home indisponible', 'o');
        echo '<div class="wip"><span class="ico">🚫</span><h3>Cette Habbo Home n\'est pas disponible</h3><p>Elle est momentanément masquée. Reviens plus tard !</p></div>';
        box_close(); render_foot(); exit;
    }
    $items = home_items($oid);
    if ((int)($home['hidden'] ?? 0) === 1) echo '<div class="hist-note">🚫 <b>Cette Home est actuellement masquée par la modération.</b> ' . ($isOwner ? 'Elle n\'est pas visible par les autres joueurs.' : 'Visible uniquement par toi (staff) et son propriétaire.') . '</div>';
    if ($isOwner && (int)($home['edit_locked'] ?? 0) === 1) echo '<div class="hist-note">🔒 <b>L\'édition de ta Home est temporairement bloquée par la modération.</b></div>';
    // Message de confirmation
    $flash = ['signed' => '✅ Merci, ton message a été ajouté au livre d\'or !', 'slow' => '⏳ Patiente un peu avant de re-signer ce livre d\'or.', 'reported' => '✅ Merci, ton signalement a été transmis à la modération.', 'gbdel' => '🗑️ Message supprimé.', 'needreason' => '⚠️ Choisis un motif pour envoyer le signalement.', 'friend' => '✅ Ajouté à tes amis !'];
    $done = (string)($_GET['done'] ?? '');
    if (isset($flash[$done])) echo '<div class="hist-note" style="background:#e7f6ea;border-color:#9ed3ad;color:#1c5a2e">' . h($flash[$done]) . '</div>';
    if (($_GET['need'] ?? '') === 'login') echo '<div class="hist-note">🔑 Connecte-toi pour effectuer cette action. <a href="?p=register">Se connecter / s\'inscrire</a></div>';
    // En-tête propriétaire + actions
    echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">';
    echo '<div style="width:34px;height:34px;background:#f3eecf;border:1px solid #d8cfa6;border-radius:3px;overflow:hidden">' . av_svg((string)$owner['username'], (string)$owner['sex']) . '</div>';
    echo '<div style="flex:1"><b style="font-size:13px">La Habbo Home de ' . h((string)$owner['username']) . '</b><div class="muted" style="font-size:10px">' . h((string)$owner['motto']) . '</div></div>';
    $url = 'http://' . h($_SERVER['HTTP_HOST'] ?? 'localhost') . '/v2/?p=home/' . h(rawurlencode((string)$owner['username']));
    echo '<input readonly value="' . $url . '" onclick="this.select()" style="width:230px;border:1px solid #b9c7cf;padding:3px 5px;font-size:10px" title="Copier le lien de cette Home">';
    if ($isOwner) echo ' ' . nbtn('✎ Personnaliser ma Home', '?p=home_edit');
    echo '</div>';
    // Signaler cette Home (visiteur connecté, pas le proprio)
    if ($viewer && !$isOwner) {
        echo '<details style="margin-bottom:6px"><summary style="cursor:pointer;color:#a33;font-size:11px">⚠ Signaler cette Home</summary>'
           . home_report_form('home', $oid);
        echo '</details>';
    }
    // Formulaire de signalement d'un message du livre d'or (depuis le lien « signaler »)
    if ($viewer && !$isOwner && preg_match('/^gb:(\d+)$/', (string)($_GET['report'] ?? ''), $rm))
        echo '<div class="hist-note" style="background:#fff">Signaler le message #' . (int)$rm[1] . ' du livre d\'or :' . home_report_form('gb', (int)$rm[1]) . '</div>';
    // Canvas — hauteur selon le contenu (ou la hauteur réelle du fond), pas une valeur arbitraire
    echo home_css();
    $hasBg = (string)$home['background'] !== '' && home_asset_ok('bg', (string)$home['background']);
    $bgCss = $hasBg ? ' url(' . home_asset_url('bg', (string)$home['background']) . ') no-repeat top center' : '';
    $ch = 420;
    foreach ($items as $it) $ch = max($ch, (int)$it['y'] + 200);
    if ($hasBg) { $sz = @getimagesize(home_asset_dir('bg') . '/' . basename((string)$home['background'])); if ($sz && $sz[1] > 0) $ch = max($ch, (int)$sz[1]); }
    $ch = min($ch, 1360);
    echo '<div class="homecanvas" style="position:relative;width:928px;max-width:100%;height:' . $ch . 'px;border:1px solid #06252b;background:#c9dbe6' . $bgCss . ';overflow:hidden">';
    foreach ($items as $it) {
        $x = (int)$it['x']; $y = (int)$it['y']; $z = (int)$it['z'];
        echo '<div style="position:absolute;left:' . $x . 'px;top:' . $y . 'px;z-index:' . $z . '">';
        echo home_widget_html((string)$it['type'], $owner, $oid, (string)($it['resource'] ?? ''), (string)($it['content'] ?? ''), 'view', (string)($it['style'] ?? ''));
        echo '</div>';
    }
    echo '</div>';
    render_foot(); exit;
}

/* ---------- 404 ---------- */
render_head('home');
box_open('Page introuvable', 'o');
echo '<div class="wip"><span class="ico">❓</span><h3>Cette page n\'existe pas</h3><p>Le lien « ' . h($p) . ' » ne correspond à aucune page.</p><p style="margin-top:8px">' . nbtn('Retour à l\'accueil', '?p=home') . '</p></div>';
box_close();
render_foot();
