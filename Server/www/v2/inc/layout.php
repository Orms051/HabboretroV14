<?php
/**
 * Layout partagé du site v2 — chrome fidèle 2007 (dimensions d'origine).
 * #top 928×216, fond view_fr.gif (928×154) à background-position 0 37px (taille native).
 * #topbar, #habbologo, #tabmenu (3 contenus), #enter-hotel (enterHH_fr.gif) aux positions d'origine.
 */

function nav_tabs_default(): array {
    return [
        'home'      => ['Bienvenue', 'Accueil', 'tab_icon_01_home.gif'],
        'register'  => ['Nouveau ?', 'Créer un compte', 'tab_icon_02_hotel.gif'],
        'community' => ['Communauté', 'Communauté', 'tab_icon_03_community.gif'],
        'events'    => ['Events', 'Événements', 'tab_icon_05_fun.gif'],
        'games'     => ['Jeux', 'Jeux', 'tab_icon_04_games.gif'],
        'shop'      => ['Boutique', 'Boutique', 'tab_icon_07_shop.gif'],
        'mobile'    => ['Mobile', 'Mobile', 'tab_icon_06_mobile.gif'],
        'credits'   => ['Crédits', 'Crédits', 'tab_icon_10_coins.gif'],
        'club'      => ['HC', 'Habbo Club', 'tab_icon_09_hc.gif'],
        'help'      => ['Aide', 'Aide', 'tab_icon_08_help.gif'],
    ];
}
/** Onglets de la nav : base (éditable) sinon défaut codé. */
function nav_tabs(): array {
    try {
        $rows = db()->query("SELECT tab_key,label,icon FROM site_nav_tabs WHERE visible=1 AND archived=0 ORDER BY ord,tab_key")->fetchAll();
        if ($rows) { $o = []; foreach ($rows as $r) $o[$r['tab_key']] = [$r['label'], $r['label'], $r['icon']]; return $o; }
    } catch (Throwable $e) {}
    return nav_tabs_default();
}
/**
 * Sous-menus de la barre jaune, par onglet principal — reproduits à l'identique
 * depuis l'ancien site (mangetoica.com/habbo2007). Chaque onglet : [section, items].
 * Chaque item : [libellé, route locale (?p=…) ou null = libellé de section, cible].
 */
/** Sous-menus : base (éditable) sinon défaut codé. */
function sub_menus(): array {
    try {
        $tabs = db()->query("SELECT tab_key,section_label FROM site_nav_tabs ORDER BY ord,tab_key")->fetchAll();
        if ($tabs) {
            $byTab = [];
            foreach (db()->query("SELECT tab_key,label,route,target FROM site_nav_items WHERE visible=1 AND archived=0 ORDER BY ord,id") as $it) {
                $byTab[$it['tab_key']][] = [$it['label'], $it['route'] !== '' ? $it['route'] : null, $it['target'] ?? ''];
            }
            $o = [];
            foreach ($tabs as $t) {
                $arr = [[$t['section_label'], null]];
                foreach ($byTab[$t['tab_key']] ?? [] as $x) $arr[] = $x;
                $o[$t['tab_key']] = [$t['section_label'], $arr];
            }
            return $o;
        }
    } catch (Throwable $e) {}
    return sub_menus_default();
}
function sub_menus_default(): array {
    return [
        'home' => ['Accueil', [
            ['Accueil', null],
            ['À propos', '?p=apropos'],
            ['Retourner sur MTC', 'https://mangetoica.com', '_blank'],
        ]],
        'register' => ['Habbo Hotel', [
            ['Habbo Hotel', null],
            ['Bienvenue à Habbo Hotel', '?p=welcome'],
            ['Les staffs', '?p=staff'],
            ['Les SOS', '?p=mods'],
            ['Mobilier', '?p=furniture'],
            ['Ecotron', '?p=ecotron'],
            ['Animaux', '?p=pets'],
            ['Habbo Home', '?p=home_info'],
            ['Habbo Clans', '?p=clans'],
            ['Trax', '?p=trax'],
            ['Nouveautés !', '?p=new'],
        ]],
        'community' => ['Communauté', [
            ['Communauté', null],
            ['Sites de Fan', '?p=fansite'],
            ['L\'appart de la semaine', '?p=apparts'],
            ['A ne pas manquer!', '?p=anepasmanquer'],
            ['Fais connaître ta Habbo Home!', '?p=homefriends'],
            ['Invite un ami !', '?p=invite'],
            ['Fil Santé Jeunes', '?p=filsante'],
        ]],
        'events' => ['Evénement', [
            ['Evénement', null],
            ['VIP Music Room', '?p=tchatvip'],
            ['Bobbaweek', '?p=bobbaweek'],
            ['Habboscope', '?p=habboscope'],
        ]],
        'games' => ['Jeux', [
            ['Jeux', null],
            ['Battle Ball', '?p=battleball'],
            ['Rodéau', '?p=rodeau'],
            ['Le Grand Plongeon', '?p=plongeoir'],
            ['Le jeu Habbo de la Semaine', '?p=gamesweek'],
            ['Snowstorm', '?p=snowstorm'],
        ]],
        'shop' => ['Boutique', [
            ['Boutique', null],
            ['Les Habbos Minicartes', '?p=minicartes'],
            ['T-Shirt', '?p=tshirt'],
        ]],
        'mobile' => ['Mobile', [
            ['Mobile', null],
            ['Sonneries', '?p=sonneries'],
            ['Fond d\'écran', '?p=fondecran'],
            ['Habbo Imager', '?p=imager'],
        ]],
        'credits' => ['Crédits Habbo', [
            ['Crédits Habbo', null],
            ['France', '?p=coinsfr'],
            ['Belgique', '?p=coinsbe'],
            ['Suisse', '?p=coinsch'],
            ['Canada', '?p=coinsca'],
            ['Autres pays', '?p=coinsother'],
            ['Comment utiliser les crédits', '?p=creditshelp'],
        ]],
        'club' => ['Habbo Club', [
            ['Habbo Club', null],
            ['Les avantages Habbo Club', '?p=club'],
            ['Rejoins le HC ou prolonge ton adhésion', '?p=hcjoin'],
            ['Club Shop', '?p=hcshop'],
        ]],
        'help' => ['Aide et Sécurité', [
            ['Aide et Sécurité', null],
            ['Contacte-nous', '?p=contact'],
            ['FAQs', '?p=faqs'],
            ['Guide des parents', '?p=parents_guide'],
            ['Habbo Attitude', '?p=habbo_way'],
            ['Vérifie ton email!', '?p=email'],
            ['Habbo X', '?p=habbo_x'],
            ['HabboRédac', '?p=habboredac'],
            ['Conseils de sécurité', '?p=account_security'],
        ]],
    ];
}

/** Table route (?p=slug) → onglet principal, pour activer le bon onglet et surligner le sous-item. */
function route_tab(string $route): string {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (sub_menus() as $tab => [$section, $items]) {
            $map[$tab] = $tab;                       // la clé d'onglet elle-même
            foreach ($items as $it) {
                if (!empty($it[1]) && str_starts_with((string)$it[1], '?p=')) {
                    $map[substr($it[1], 3)] = $tab;  // slug → onglet
                }
            }
        }
    }
    return $map[$route] ?? ($route === '' ? 'home' : 'home');
}

function nbtn(string $label, string $href, string $target = ''): string {
    $t = $target !== '' ? ' target="' . $target . '" rel="noopener"' : '';
    return '<a class="new-button" href="' . h($href) . '"' . $t . '><b>' . h($label) . '</b><i></i></a>';
}
/** Lien-action d'origine (style colorlink : flèche orange). Pour les CTA principaux dans les articles. */
function clink(string $label, string $href, string $target = ''): string {
    $t = $target !== '' ? ' target="' . $target . '" rel="noopener"' : '';
    return '<a class="arrowlink" href="' . h($href) . '"' . $t . '><span>' . h($label) . '</span></a>';
}
/** Simple lien texte souligné (actions secondaires : « En savoir plus », etc.). */
function tlink(string $label, string $href, string $target = ''): string {
    $t = $target !== '' ? ' target="' . $target . '" rel="noopener"' : '';
    return '<a class="alink" href="' . h($href) . '"' . $t . '>' . h($label) . '</a>';
}
function box_open(string $head = '', string $hc = 'o', string $variant = 'welcome'): void {
    echo '<div class="cb ' . $variant . '"><div class="bt"><div></div></div><div class="i1"><div class="i2"><div class="i3">';
    if ($head !== '') echo '<div class="bhead ' . $hc . '">' . $head . '</div>';
    echo '<div class="bbody">';
}
function box_close(): void { echo '</div></div></div></div><div class="bb"><div></div></div></div>'; }

/** Panneau #tabmenu (3 contenus). */
function tabmenu_html(?string $err): string {
    $u = me();
    $img = '/web-gallery/v2/images';
    $root = dirname(dirname(__DIR__)) . '/web-gallery/v2/images';
    $frank = is_file($root . '/myhabbo_frank.gif') ? '<img src="' . $img . '/myhabbo_frank.gif" alt="" class="tabmenu-image" width="47" height="85">' : '';
    $coins = is_file($root . '/mycredits_coins2.gif') ? '<img src="' . $img . '/mycredits_coins2.gif" alt="" class="tabmenu-image" width="67" height="49">' : '';
    ob_start(); ?>
<div id="tabmenu" onmouseover="holdTab()" onmouseout="scheduleClose()"><div id="tabmenu-content">
  <div id="myhabbo-content" class="tabmenu-inner selected">
    <?php if ($u): ?>
      <?php $isAdmin = function_exists('is_staff') && is_staff(); ?>
      <h3 class="mh-welcome">Salut <?= h($u['username']) ?> !</h3>
      <div class="tabmenu-inner-content mh-conn">
        <?= $frank ?>
        <div class="mh-dest">
          <a href="/client.php" target="_blank" rel="noopener">Entrer dans l'hôtel</a>
          <a href="?p=home/<?= h(rawurlencode((string)$u['username'])) ?>">Voir ma Habbo Home</a>
          <a href="?p=messages">Mes messages<?php $nm = function_exists('unread_msgs') ? unread_msgs((int)$u['id']) : 0; if ($nm > 0): ?> <span style="background:#e5820c;color:#fff;padding:0 4px;border-radius:2px">(<?= (int)$nm ?>)</span><?php endif; ?></a>
          <a href="?p=me">Modifier mes paramètres</a>
          <?php if ($isAdmin): ?><a href="/admin/" class="mh-admin">Administration</a><?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <?= $frank ?><h3>Bienvenue ! Connecte-toi ou inscris-toi !</h3>
      <div class="tabmenu-inner-content">
        <div id="mh-links">
          <a class="colorlink" href="?p=register"><span>L'inscription est gratuite</span></a>
          <a class="colorlink last" href="#" onclick="return showLogin()"><span>Se connecter</span></a>
        </div>
        <div id="mh-login">
          <?php if ($err) echo '<div class="flash err">' . h($err) . '</div>'; ?>
          <form method="post" action="?p=login"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="text" id="login-username" name="username" placeholder="Nom Habbo" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button class="colorlink last" type="submit"><span>Se connecter</span></button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <div id="mycredits-content" class="tabmenu-inner">
    <h3>Tes crédits</h3>
    <div class="tabmenu-inner-content"><?= $coins ?>
      <?php if ($u): try { $c = (int) db()->query('SELECT credits FROM users WHERE id=' . (int)$u['id'])->fetchColumn(); echo '<p style="padding-top:4px"><b>' . $c . '</b> crédits</p>'; } catch (Throwable $e) {}
      else: ?><p style="padding-top:4px">Connecte-toi pour voir tes crédits.</p><?php endif; ?>
      <a class="colorlink last" href="?p=credits"><span>Obtenir des crédits</span></a>
    </div>
  </div>
  <div id="habboclub-content" class="tabmenu-inner">
    <h3>Habbo Club</h3>
    <div class="tabmenu-inner-content"><p>Avantages exclusifs, cadeaux et badge doré.</p>
      <a class="colorlink last" href="?p=club"><span>En savoir plus</span></a>
    </div>
  </div>
</div><div id="tabmenu-bottom"></div></div>
<?php return (string)ob_get_clean();
}

function render_head(string $active, ?string $err = null): void {
    $u = me();
    $tabs = nav_tabs();
    // Route courante (peut être un sous-item) → onglet principal à activer + surlignage.
    $route = (string)($_GET['p'] ?? $active);
    if ($route === '') $route = 'home';
    $tab = route_tab($route);
    if (!isset($tabs[$tab])) $tab = isset($tabs[$active]) ? $active : 'home';
    $subs = sub_menus();
    [$section, $subItems] = $subs[$tab] ?? $subs['home'];
    $I = '/web-gallery/v2/images';
    ?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Habbo — l'Hôtel où on se retrouve</title>
<link rel="icon" href="/web-gallery/v2/favicon.ico">
<style>
*{box-sizing:border-box}
body{font:11px/1.5 Verdana,Geneva,Arial,sans-serif;color:#30384a;background:#083940 url(<?= $I ?>/bg_patterns/habbo.gif) repeat;margin:0;padding:12px 10px}
a{color:#2f6f9f;text-decoration:none}a:hover{text-decoration:underline}
img{border:0}
h1,h2,h3,p,ul,li{margin:0;padding:0}
.wrap{width:936px;max-width:100%;margin:0 auto;position:relative;background:#fff;border:1px solid #06252b;padding:3px;border-radius:0 0 9px 9px}
/* ===== #top : stage d'en-tête, dimensions d'origine ===== */
#top{position:relative;width:928px;height:216px;background:url(<?= $I ?>/view_fr.gif) no-repeat 0 33px}
/* topbar */
#topbar{position:absolute;top:0;left:0;width:928px;height:33px;border-collapse:collapse;background:url(<?= $I ?>/top_bar/stripe_bg.gif) no-repeat;color:#fff;font-size:10px;z-index:15}
#topbar td{padding:8px 16px 0;height:33px;vertical-align:top;text-align:center}
#topbar-count{background:url(<?= $I ?>/top_bar/stripe_right.gif) no-repeat top right;width:240px;text-align:left;color:#cfd2da;font-weight:700}
#topbar-menu{background:url(<?= $I ?>/top_bar/stripe_none_bg.gif) repeat-x;padding:0 !important;text-align:center}
#topbar-status{background:url(<?= $I ?>/top_bar/stripe_left.gif) no-repeat;width:337px;text-align:right;color:#ff6a6a;font-weight:700}
#topbar-status.loggedin{color:#7BEB00}
#topbar-menu ul{list-style:none;display:inline-block}
#topbar-menu li{float:left;padding:0 0 0 5px;height:33px;background:url(<?= $I ?>/top_bar/tab_bg_left.gif) no-repeat;cursor:pointer}
#topbar-menu li.selected,#topbar-menu li:hover{background-position:0 -50px}
#topbar-menu li div{background:url(<?= $I ?>/top_bar/tab_bg.gif) no-repeat 100% 0;height:33px}
#topbar-menu li.selected div,#topbar-menu li:hover div{background-position:100% -50px}
#topbar-menu li a{color:#fff;text-decoration:none;white-space:nowrap;display:block;height:33px;padding:10px 9px 0 33px;background-repeat:no-repeat}
#myhabbo a{background:url(<?= $I ?>/top_bar/icon_myhabbo.gif) no-repeat 3px 3px}
#mycredits a{background:url(<?= $I ?>/top_bar/icon_mycredits.gif) no-repeat 3px 5px}
#habboclub a{background:url(<?= $I ?>/top_bar/icon_habboclub.gif) no-repeat 3px 7px;padding-left:27px}
/* logo + bouton entrée (positions d'origine) */
#habbologo{position:absolute;top:63px;left:35px;background:url(<?= $I ?>/logo.gif) no-repeat;height:66px;width:160px;z-index:12}
#habbologo a{display:block;height:66px;width:160px}
#enter-hotel{position:absolute;top:50px;left:605px;background:url(<?= $I ?>/nav/enterHH_fr.gif) no-repeat;height:106px;width:105px;z-index:12}
#enter-hotel a{display:block;height:106px;width:105px}
/* tabmenu (aligné sous l'onglet Mon Habbo) */
#tabmenu{position:absolute;top:33px;left:260px;width:329px;text-align:center;font-weight:bold;font-size:10px;z-index:30}
#tabmenu-content{background:url(<?= $I ?>/top_bar/menu_bg.png) repeat-y}
#tabmenu-bottom{background:url(<?= $I ?>/top_bar/menu_bg_bottom.png) no-repeat;height:6px;font-size:1%;line-height:0}
.tabmenu-inner{display:none;padding:6px 10px 2px}
.tabmenu-inner.selected{display:block}
.tabmenu-inner h3{margin:0;padding:4px 6px;background:#f3f3f3;font-size:10px;border-radius:5px;color:#333;white-space:nowrap}
.tabmenu-inner-content{border-top:1px dashed #000;margin-top:4px;padding-top:4px;text-align:left;overflow:hidden}
.mh-welcome{margin:0;padding:2px 6px;background:#f3f3f3;font-size:10px;border-radius:5px;color:#333;white-space:nowrap;text-align:center}
.mh-conn{display:flex;align-items:flex-start;gap:7px}
.mh-conn img.tabmenu-image{float:none;margin:-9px 0 0;flex:0 0 auto}
.mh-dest{flex:1;min-width:0;line-height:1.3;padding-top:2px}
.mh-dest a{display:block;font-size:10px;font-weight:bold;color:#2f4a63;text-decoration:underline;white-space:nowrap;margin-bottom:1px}
.mh-dest a:hover{color:#c60}
.mh-dest a.mh-admin{color:#a33}
.mh-signout{flex:0 0 auto;align-self:flex-start;margin:0 !important;float:none !important;clear:none !important}
img.tabmenu-image{float:left;margin:2px 8px 0 0}
.tabmenu-inner input[type=text],.tabmenu-inner input[type=password]{width:100%;border:2px solid #7f7f7f;padding:4px 6px;font:inherit;margin:0 0 5px;background:#fff}
#mh-login{display:none}
a.colorlink,button.colorlink{clear:right;float:right;display:block;height:18px;color:#000;font-size:10px;font-weight:bold;text-decoration:none;background:url(<?= $I ?>/colorlink/orange.gif) no-repeat top right;padding:0 20px 0 0;margin:0 0 6px 10px;white-space:nowrap;border:0;cursor:pointer}
a.colorlink span,button.colorlink span{font-weight:bold;float:left;display:block;height:18px;background:url(<?= $I ?>/colorlink/bg.gif) no-repeat;padding:3px 8px 0 15px;cursor:pointer;color:#000}
a.colorlink.last,button.colorlink.last{margin-bottom:0}
/* Lien-action d'origine inline (flèche orange) pour les articles */
a.arrowlink{display:inline-block;height:18px;color:#000;font-size:10px;font-weight:bold;text-decoration:none;background:url(<?= $I ?>/colorlink/orange.gif) no-repeat top right;padding:0 20px 0 0;margin:3px 5px 2px 0;white-space:nowrap;vertical-align:top}
a.arrowlink span{display:block;height:18px;background:url(<?= $I ?>/colorlink/bg.gif) no-repeat;padding:3px 7px 0 12px;color:#000;font-weight:bold}
a.arrowlink:hover{text-decoration:none}
/* Lien texte souligné (actions secondaires) */
a.alink{color:#d2691e;font-weight:bold;text-decoration:underline;font-size:11px}
a.alink:hover{color:#b85a16}
/* ===== nav orange #mainmenu ===== */
#mainmenu{width:928px;background:url(<?= $I ?>/navi/navi_bar_slice_top.gif) repeat-x bottom left;height:39px;line-height:39px;text-transform:uppercase;color:#fff;font:bold 10px Verdana,Arial,sans-serif;position:relative;z-index:5;margin-top:-61px}
#mainmenu ul{list-style:none;overflow:hidden}
#mainmenu li{float:left;height:39px}
#mainmenu li#leftspacer{width:5px}
#mainmenu li a{float:left;display:block;background:url(<?= $I ?>/navi/tab_mid.gif) repeat-x;height:39px;text-decoration:none;text-shadow:#000 2px 2px 2px;font-weight:bold;color:#fff;line-height:39px;padding:0 8px 0 0}
#mainmenu li a img{vertical-align:middle;padding:0 2px 0 2px}
#mainmenu li#active a{background:url(<?= $I ?>/navi/tab_act_mid.gif) repeat-x;color:#000;text-shadow:none}
#mainmenu li .left{width:5px;float:left;background:url(<?= $I ?>/navi/tab_left.gif) no-repeat;height:39px}
#mainmenu li .right{width:4px;float:left;background:url(<?= $I ?>/navi/tab_right.gif) no-repeat;height:39px}
#mainmenu li#active .left{background-image:url(<?= $I ?>/navi/tab_act_left.gif)}
#mainmenu li#active .right{background-image:url(<?= $I ?>/navi/tab_act_right.gif)}
#mainmenu li.last .right{background:url(<?= $I ?>/navi/tab_end.gif) no-repeat;width:14px;height:39px}
#mainmenu li#active.last .right{background-image:url(<?= $I ?>/navi/tab_act_end.gif)}
/* sous-barre jaune (#submenu) — dimensions d'origine */
#submenu{width:928px;height:22px;background:url(<?= $I ?>/navi/navi_bar_slice_btm.gif) no-repeat;padding:0 0 0 6px;margin:0;position:relative}
#submenu .subnav{color:#000;padding:3px 0 2px 3px;height:16px;text-align:left;text-transform:none;font-size:11px;font-weight:normal;white-space:nowrap;overflow:hidden}
#submenu .subnav a{color:#000;text-decoration:underline;font-size:11px;font-weight:bold}
#submenu .subnav a.cur{color:#930;text-decoration:none}
#submenu .subnav .subsec{color:#930;font-weight:bold;text-decoration:none}
#submenu .subnav .sep{color:#930;text-decoration:none;font-weight:normal;margin:0 4px}
/* fil d'Ariane, sous la barre jaune, sur le fond bleu */
#breadcrumb{width:928px;color:#cfe3ee;font-size:10px;padding:4px 0 2px 8px;background:#47839d}
#breadcrumb a{color:#eaf4fa;text-decoration:none}
#breadcrumb a:hover{text-decoration:underline}
#breadcrumb .bc-sep{color:#9fc4d6;margin:0 3px}
/* corps */
.body{width:928px;margin:0;background:#47839d;border:0;border-radius:0 0 7px 7px;padding:6px}
/* Pied de page DANS le cadre, sur le fond bleu foncé */
.foot{margin:8px 0 2px;padding:8px 6px 4px;text-align:center;color:#bcd2dd;font-size:10px;border-top:1px solid #5a92aa}
.foot a{color:#eaf4fa;text-decoration:underline}
.layout{display:flex;align-items:flex-start}
.maincol{width:740px;flex:0 0 740px;min-width:0}
.sidecol{flex:1;min-width:0;margin-left:8px}
/* Accueil fidèle : haut (carrousel + actus), puis 3 colonnes */
.hometop{display:flex;align-items:stretch;gap:3px;justify-content:flex-start}
.htcar{flex:0 0 429px;min-width:0}
.htnews{flex:0 0 311px;min-width:0;display:flex}
.home3{display:flex;align-items:flex-start;gap:4px;margin-top:0;justify-content:flex-start}
.home3 .hcol{min-width:0}
.home3 .hcol-l{flex:0 0 199px}
.home3 .hcol-c{flex:0 0 223px}
.home3 .hcol-r{flex:0 0 318px}
/* Bloc actualités fidèle : titre noir, corps bleu foncé, liens orange */
.newsbox{border:1px solid #06252b;border-radius:0;overflow:hidden;margin:0;flex:1;display:flex;flex-direction:column}
.newsbox .nb-head{background:#1b1b1b;color:#fff;font:bold 11px Verdana,Arial;padding:4px 8px;text-transform:uppercase}
.newsbox .nb-body{background:#1d4f63;color:#dCEBf2;padding:6px 8px;flex:1}
.newsbox .nb-item{border-bottom:1px dotted #3d7187;padding:4px 0}
.newsbox .nb-item:last-child{border-bottom:0}
.newsbox .nb-date{color:#ffb24d;font-weight:700;font-size:10px}
.newsbox .nb-title{color:#ffd37a;font-weight:700;text-decoration:none}
.newsbox .nb-title:hover{text-decoration:underline}
.newsbox .nb-sum{color:#bcd6e2;font-size:10px;margin-top:1px}
.newsbox .nb-foot{text-align:right;padding:6px 8px;background:#163f50}
@media(max-width:940px){.hometop,.home3{flex-direction:column}.htnews,.home3 .hcol-l,.home3 .hcol-c,.home3 .hcol-r{flex:1 1 auto;width:100%}}
.mc2{display:flex;align-items:flex-start}
.mcol{flex:1;min-width:0}.mcol+.mcol{margin-left:8px}
/* cadres 9-slice */
.cb{margin:0 0 6px 0}
.bt{height:5px;margin:0 0 0 18px;background:no-repeat 100% 0}
.bt div{position:relative;left:-18px;width:18px;height:5px;background:no-repeat 0 0;font-size:0;line-height:0}
.bb{height:9px;margin:0 0 0 8px;background:no-repeat 100% 100%;position:relative}
.bb div{position:absolute;left:-8px;width:8px;height:9px;background:no-repeat 0 100%;font-size:0;line-height:0;display:block}
.i1{padding:0 0 0 1px;background:url(<?= $I ?>/borders.png) repeat-y}
.i2{padding:0 1px 0 0;background:url(<?= $I ?>/borders.png) repeat-y top right}
.i3{display:block;background:#fff}
#content .bt,#content .bt div,#content .bb,#content .bb div{background-image:url(<?= $I ?>/box.png)}
.bhead{font-weight:bold;font-size:11px;color:#fff;padding:4px 9px;text-transform:uppercase}
.bhead.o{background:#ff9110}.bhead.b{background:#219daf}.bhead.g{background:#70af21}.bhead.p{background:#7d52a8}.bhead.y{background:#e3b505;color:#5a4600}.bhead.k{background:#333}
.bbody{padding:8px 10px}
/* carrousel À ne pas manquer */
.cnums{float:right;font-weight:normal}
.cnums .cnum{display:inline-block;width:15px;height:15px;line-height:15px;text-align:center;background:rgba(255,255,255,.35);color:#fff;border-radius:3px;margin-left:3px;cursor:pointer;font-size:10px;font-weight:bold}
.cnums .cnum.on{background:#fff;color:#ef8f13}
.carousel{position:relative}
.cslide{display:none}.cslide.on{display:block}
.cstage{display:block;position:relative;height:178px;background-color:#1b2733;background-repeat:no-repeat;background-position:center;background-size:contain;border-radius:2px;overflow:hidden;text-decoration:none}
.cstage .ccap{position:absolute;left:0;right:0;bottom:0;padding:5px 10px;color:#fff;font-weight:800;font-size:15px;text-shadow:0 1px 3px #000;background:linear-gradient(transparent,rgba(0,0,0,.55))}
.crow{display:flex;align-items:center;margin-top:8px}
.crow .ctext{flex:1;color:#5b5b5b;font-size:11px;padding-right:8px}
.crow .cbtns{flex:0 0 auto;white-space:nowrap}
/* boutons new_button */
a.new-button{position:relative;display:inline-block;height:25px;text-decoration:none;overflow:hidden;vertical-align:top;margin:0 3px 2px 0}
a.new-button b{display:inline;float:left;margin-right:3px;padding:5px 17px 4px 20px;height:17px;font-size:11px;font-weight:bold;color:#000 !important;background:url(<?= $I ?>/new_button.png) no-repeat -3px 0;text-align:center;white-space:nowrap}
a.new-button i{position:absolute;right:0;top:0;width:3px;height:25px;background:url(<?= $I ?>/new_button.png) no-repeat 0 0}
a.new-button:hover b{background-position:-3px -25px;text-decoration:none}
/* ===== Boîtes d'origine .v3box (pages importées fidèlement) ===== */
#content .content-2col,#content .content-1col,#content .content-3col{border-collapse:collapse;width:100%}
#content td.habboPage-col{vertical-align:top}
#content td.habboPage-col div.v3box,#content td.habboPage-col div.v2box,#content td.habboPage-col div.nobox{margin:0 5px 5px 0}
#content td.habboPage-col.rightmost div.v3box{margin-right:0}
#content div.v3box{margin:0 0 5px}
#content div.v3box-top{background:url(<?= $I ?>/boxes-v3/lightgrey-tl.png) no-repeat;padding-left:7px}
#content div.v3box-top h3{background:url(<?= $I ?>/boxes-v3/lightgrey-tr.png) no-repeat 100% 0;font:bold 11px Verdana,Arial,sans-serif;margin:0;padding:9px 6px 5px;text-transform:uppercase}
#content div.v3box-content{border-left:1px solid #000;border-right:1px solid #000;padding:3px 1px 0;background:#fff url(<?= $I ?>/boxes-v3/lightgrey-mid.png) repeat-x}
#content div.v3box-body{border-left:1px solid #e0dedf;border-right:1px solid #e0dedf;padding:6px;color:#4a4a4a;font-size:11px}
#content div.v3box-body p{margin:0;padding-bottom:1em}
#content div.v3box-body a{color:#2f6f9f;font-weight:bold}
#content div.v3box-bottom{background:url(<?= $I ?>/boxes-v3/bl.png) no-repeat 1px 0;padding-left:5px;height:5px;font-size:1%}
#content div.v3box-bottom div{background:url(<?= $I ?>/boxes-v3/br.png) no-repeat 100% 0;height:5px}
<?php foreach (['darkgrey','black','blue','green','yellow','orange'] as $cc): ?>
#content div.v3box.<?= $cc ?> div.v3box-top{background-image:url(<?= $I ?>/boxes-v3/<?= $cc ?>-tl.png)}
#content div.v3box.<?= $cc ?> div.v3box-top h3{background-image:url(<?= $I ?>/boxes-v3/<?= $cc ?>-tr.png)<?= in_array($cc,['darkgrey','black','blue','green','orange'])?';color:#fff':'' ?>}
#content div.v3box.<?= $cc ?> div.v3box-content{background-image:url(<?= $I ?>/boxes-v3/<?= $cc ?>-mid.png)}
<?php endforeach; ?>
#content .imported img{max-width:100%}
#content .missing-img{display:inline-block;min-width:60px;padding:8px;background:repeating-linear-gradient(45deg,#f3f3f3,#f3f3f3 6px,#e8e8e8 6px,#e8e8e8 12px);border:1px dashed #bbb;color:#999;font-size:9px;text-align:center}
.hist-note{background:#fff7d6;border:1px solid #e6c84a;color:#6a5500;padding:5px 8px;margin:0 0 8px;font-size:10px;border-radius:3px}
.muted{color:#7a7a86}
.flash{border-radius:3px;padding:6px 9px;margin-bottom:6px;font-weight:700;font-size:10px}
.flash.err{background:#fdeaee;border:1px solid #f0b6c2;color:#a33}
.lr{display:flex;align-items:center}.lr>*+*{margin-left:6px}
.wip{text-align:center;padding:24px 14px;color:#7a7a86}.wip .ico{font-size:34px;display:block;margin-bottom:8px}.wip h3{color:#50586a;font-size:14px;margin-bottom:4px}
.foot{color:#aab;text-align:center;font-size:10px;margin-top:12px}.foot a{color:#ccd}
@media(max-width:940px){.wrap,#top,#topbar,#mainmenu,.subnav,#submenu,#breadcrumb,.body{width:100%}#top{height:auto}#topbar{position:static}#habbologo,#enter-hotel,#tabmenu{position:static;margin:8px}.layout{flex-direction:column}.sidecol{width:100%;margin-left:0;margin-top:8px}.mc2{flex-direction:column}.mcol+.mcol{margin-left:0}}
</style></head>
<body>
<div class="wrap">
  <div id="top">
    <table id="topbar"><tr>
      <td id="topbar-count">Habbo rétro v14 · 2007</td>
      <td id="topbar-menu"><ul>
        <li id="myhabbo" class="selected" onmouseover="switchTab('myhabbo')" onmouseout="scheduleClose()"><div><a href="?p=me">Mon Habbo</a></div></li>
        <li id="mycredits" onmouseover="switchTab('mycredits')" onmouseout="scheduleClose()"><div><a href="?p=credits">Mes Crédits</a></div></li>
        <li id="habboclub" onmouseover="switchTab('habboclub')" onmouseout="scheduleClose()"><div><a href="?p=club">Habbo Club</a></div></li>
      </ul></td>
      <td id="topbar-status" class="<?= $u ? 'loggedin' : '' ?>"><?= $u ? 'Connecté : ' . h($u['username']) . ' · <a href="?p=logout" style="color:#fff">Déconnexion</a>' : 'Non connecté' ?></td>
    </tr></table>
    <div id="habbologo"><a href="?p=home" title="Accueil"></a></div>
    <div id="enter-hotel"><a href="/client.php" target="_blank" rel="noopener" title="Entre dans l'Hôtel"></a></div>
    <?= tabmenu_html($err) ?>
  </div>

  <div id="mainmenu"><ul>
    <li id="leftspacer">&nbsp;</li>
<?php $keys = array_keys($tabs); $lastKey = end($keys);
    foreach ($tabs as $k => $t) {
        $attr = ($k === $tab ? ' id="active"' : '') . ($k === $lastKey ? ' class="last"' : '');
        echo '<li' . $attr . '><span class="left"></span><a href="?p=' . $k . '"><img src="' . $I . '/navi/' . $t[2] . '" alt="">' . h($t[0]) . '</a><span class="right"></span></li>' . "\n";
    } ?>
  </ul></div>
  <div id="submenu"><div class="subnav"><?php
    $parts = [];
    foreach ($subItems as $it) {
        $lbl = $it[0]; $href = $it[1] ?? null; $tgt = $it[2] ?? '';
        if ($href === null) {                       // libellé de section (texte)
            $parts[] = '<span class="subsec">' . h($lbl) . '</span>';
        } else {
            $cur = str_starts_with($href, '?p=') && substr($href, 3) === $route;
            $t2  = $tgt !== '' ? ' target="' . $tgt . '" rel="noopener"' : '';
            $parts[] = '<a href="' . h($href) . '"' . $t2 . ($cur ? ' class="cur"' : '') . '>' . h($lbl) . '</a>';
        }
    }
    echo implode(' <span class="sep">|</span> ', $parts);
  ?></div></div>
  <?php
    // Fil d'Ariane sous la barre jaune (texte clair sur fond bleu).
    $here = null;
    foreach ($subItems as $it) { if (!empty($it[1]) && str_starts_with((string)$it[1], '?p=') && substr($it[1], 3) === $route) { $here = $it[0]; break; } }
    // Pas de fil d'Ariane sur l'accueil (comme la référence) ; ailleurs : Accueil » Section » Sous-page.
    if (!($tab === 'home' && $route === 'home')) {
        echo '<div id="breadcrumb">';
        echo '<a href="?p=home">Accueil</a> <span class="bc-sep">&raquo;</span> ';
        echo '<a href="?p=' . h($tab) . '">' . h($section) . '</a>';
        if ($here !== null && $here !== $section) echo ' <span class="bc-sep">&raquo;</span> ' . h($here);
        echo '</div>';
    }
  ?>

  <div class="body" id="content">
<?php
}

function render_foot(): void {
    ?>  <div class="foot">Habbo — rétro v14 (2007). Reconstitution privée, non affiliée à Sulake. · <a href="/">ancien site</a></div>
  </div>
</div>
<script>
var closeT=null;
function switchTab(id){if(closeT){clearTimeout(closeT);closeT=null;}var t=['myhabbo','mycredits','habboclub'];for(var i=0;i<t.length;i++){var li=document.getElementById(t[i]);var c=document.getElementById(t[i]+'-content');if(li)li.className=(t[i]===id?'selected':'');if(c)c.className='tabmenu-inner'+(t[i]===id?' selected':'');}return true;}
function holdTab(){if(closeT){clearTimeout(closeT);closeT=null;}}
function scheduleClose(){if(closeT)clearTimeout(closeT);closeT=setTimeout(function(){switchTab('myhabbo');},400);}
function showLogin(){var f=document.getElementById('mh-login'),l=document.getElementById('mh-links');if(f)f.style.display='block';if(l)l.style.display='none';var u=document.getElementById('login-username');if(u)u.focus();return false;}
var carI=0,carT=null;
function carGo(i){var c=document.getElementById('carousel');if(!c)return;var n=parseInt(c.getAttribute('data-n'),10)||1;i=((i%n)+n)%n;for(var j=0;j<n;j++){var s=document.getElementById('cslide'+j),b=document.getElementById('cnum'+j);if(s)s.className='cslide'+(j===i?' on':'');if(b)b.className='cnum'+(j===i?' on':'');}carI=i;if(carT)clearTimeout(carT);carT=setTimeout(function(){carGo(carI+1);},6000);}
(function(){if(document.getElementById('carousel'))carGo(0);})();
</script>
</body></html>
<?php
}

function wip_box(string $title, string $msg): void {
    box_open(mb_strtoupper($title), 'o');
    echo '<div class="wip"><span class="ico">🚧</span><h3>Page en construction</h3><p>' . h($msg) . '</p></div>';
    box_close();
}
