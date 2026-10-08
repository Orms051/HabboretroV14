<?php
/**
 * Importateur fidèle des pages de l'ancien site (mangetoica.com/habbo2007) → table site_pages.
 * - Récupère #main-content (texte VERBATIM + structure .v3box d'origine), retire le fil d'Ariane d'origine.
 * - Remappe les images : template mangetoica = téléchargé en local ; c_images = local si présent ;
 *   avatars/personnages Habbo (liens morts) = placeholder « indisponible » + NOTE (jamais recréés).
 * - Remappe les liens internes .php et habbo.fr/home vers nos routes locales ; externes conservés.
 * Usage (CLI) :  php v2/tools/import_pages.php [slug1 slug2 ...]   (sans argument = tout)
 */
declare(strict_types=1);
$ROOT = dirname(__DIR__, 2);                 // .../Server/www
chdir($ROOT);
require $ROOT . '/v2/inc/boot.php';
ensure_site_pages();

$BASE = 'https://mangetoica.com/habbo2007/';
$IMGDIR = $ROOT . '/web-gallery/v2/pages_img';
if (!is_dir($IMGDIR)) mkdir($IMGDIR, 0777, true);
$IMGURL = '/web-gallery/v2/pages_img';
$ctx = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'Mozilla/5.0'], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);

/* slug => [référence .php, onglet parent, historique?] */
$PAGES = [
    // Habbo Hotel (Nouveau?)
    'welcome'=>['welcome.php','register',0],'staff'=>['staff.php','register',0],'mods'=>['mods.php','register',0],
    'furniture'=>['furniture.php','register',0],'ecotron'=>['ecotron.php','register',0],'pets'=>['pets.php','register',0],
    'home_info'=>['habbo_home.php','register',0],'clans'=>['clan.php','register',0],'trax'=>['trax.php','register',0],'new'=>['new.php','register',1],
    // Communauté
    'fansite'=>['fansite.php','community',0],'apparts'=>['apparts.php','community',1],'anepasmanquer'=>['events.php','community',1],
    'homefriends'=>['homefriends.php','community',0],'invite'=>['invite.php','community',0],'filsante'=>['filsantejeune.php','community',0],
    // Events
    'tchatvip'=>['tchatvip.php','events',1],'bobbaweek'=>['bobbaweek.php','events',1],'habboscope'=>['habboscope.php','events',1],
    // Jeux
    'battleball'=>['battleball.php','games',0],'rodeau'=>['rodeau.php','games',0],'plongeoir'=>['plongeoir.php','games',0],
    'gamesweek'=>['gamesweek.php','games',1],'snowstorm'=>['snowstorm.php','games',0],
    // Boutique
    'minicartes'=>['shop.php','shop',1],'tshirt'=>['tshirt.php','shop',1],
    // Crédits
    'coinsfr'=>['coinsfr.php','credits',1],'coinsbe'=>['coinsbe.php','credits',1],'coinsch'=>['coinsch.php','credits',1],
    'coinsca'=>['coinsca.php','credits',1],'coinsother'=>['coinsother.php','credits',1],'creditshelp'=>['creditshelp.php','credits',0],
    // HC
    'hcjoin'=>['hcjoin.php','club',1],'hcshop'=>['hcshop.php','club',1],
    // Aide
    'contact'=>['contact.php','help',0],'faqs'=>['faqs.php','help',0],'parents_guide'=>['parents_guide.php','help',0],
    'habbo_way'=>['habbo_way.php','help',0],'email'=>['email.php','help',0],'habbo_x'=>['habbo_x.php','help',0],
    'habboredac'=>['HabboRedac.php','help',0],'account_security'=>['Account_security.php','help',0],
    // À propos
    'apropos'=>['apropos.php','home',0],
];

/* .php d'origine => route locale */
$PHP2ROUTE = [
    'main.php'=>'?p=home','nouveau.php'=>'?p=register','community.php'=>'?p=community','entertainment.php'=>'?p=events',
    'games.php'=>'?p=games','shop.php'=>'?p=shop','habbomobile.php'=>'?p=mobile','credits.php'=>'?p=credits',
    'club.php'=>'?p=club','help.php'=>'?p=help','apropos.php'=>'?p=apropos',
    'welcome.php'=>'?p=welcome','staff.php'=>'?p=staff','mods.php'=>'?p=mods','furniture.php'=>'?p=furniture',
    'ecotron.php'=>'?p=ecotron','pets.php'=>'?p=pets','habbo_home.php'=>'?p=home_info','clan.php'=>'?p=clans',
    'trax.php'=>'?p=trax','new.php'=>'?p=new','fansite.php'=>'?p=fansite','apparts.php'=>'?p=apparts',
    'events.php'=>'?p=anepasmanquer','homefriends.php'=>'?p=homefriends','invite.php'=>'?p=invite','filsantejeune.php'=>'?p=filsante',
    'tchatvip.php'=>'?p=tchatvip','bobbaweek.php'=>'?p=bobbaweek','habboscope.php'=>'?p=habboscope',
    'battleball.php'=>'?p=battleball','rodeau.php'=>'?p=rodeau','plongeoir.php'=>'?p=plongeoir','gamesweek.php'=>'?p=gamesweek',
    'snowstorm.php'=>'?p=snowstorm','tshirt.php'=>'?p=tshirt','creditshelp.php'=>'?p=creditshelp',
    'coinsfr.php'=>'?p=coinsfr','coinsbe.php'=>'?p=coinsbe','coinsch.php'=>'?p=coinsch','coinsca.php'=>'?p=coinsca','coinsother.php'=>'?p=coinsother',
    'hcjoin.php'=>'?p=hcjoin','hcshop.php'=>'?p=hcshop','contact.php'=>'?p=contact','faqs.php'=>'?p=faqs','faqs2.php'=>'?p=faqs',
    'parents_guide.php'=>'?p=parents_guide','habbo_way.php'=>'?p=habbo_way','email.php'=>'?p=email','habbo_x.php'=>'?p=habbo_x',
    'habboredac.php'=>'?p=habboredac','account_security.php'=>'?p=account_security','infobus.php'=>'?p=events',
];

function dl_image(string $url, string $imgdir, string $imgurl, $ctx): ?string {
    $safe = substr(md5($url), 0, 8) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '', basename(parse_url($url, PHP_URL_PATH) ?: 'img'));
    if ($safe === '' || strlen($safe) > 80) $safe = substr(md5($url), 0, 12) . '.img';
    $dest = $imgdir . '/' . $safe;
    if (is_file($dest)) return $imgurl . '/' . $safe;
    $b = @file_get_contents($url, false, $ctx);
    if ($b === false || strlen($b) < 20) return null;
    file_put_contents($dest, $b);
    return $imgurl . '/' . $safe;
}

$targets = array_slice($argv, 1);
if (!$targets) $targets = array_keys($PAGES);

$report = [];
foreach ($targets as $slug) {
    if (!isset($PAGES[$slug])) { $report[] = "⚠ $slug : slug inconnu"; continue; }
    [$ref, $parent, $hist] = $PAGES[$slug];
    $url = $BASE . $ref;
    $html = @file_get_contents($url, false, $ctx);
    if ($html === false || strlen($html) < 200) { $report[] = "⛔ $slug : page inaccessible ($ref)"; continue; }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);
    $mc = $xp->query('//*[@id="main-content"]')->item(0);
    if (!$mc) { $report[] = "⛔ $slug : #main-content introuvable"; continue; }

    // titre = #page-headline-text d'origine (AVANT de retirer le fil d'Ariane)
    $title = '';
    $t = $xp->query('//*[@id="page-headline-text"]')->item(0);
    if ($t) $title = trim($t->textContent);
    if ($title === '') $title = $slug;
    // retirer le fil d'Ariane d'origine (#page-headline) — on affiche le nôtre
    foreach (iterator_to_array($xp->query('.//*[@id="page-headline"]', $mc)) as $ph) $ph->parentNode->removeChild($ph);

    $missing = [];
    // IMAGES
    foreach (iterator_to_array($xp->query('.//img', $mc)) as $img) {
        $src = $img->getAttribute('src');
        $low = strtolower($src);
        $new = null; $reason = '';
        if (strpos($low, 'habbo-imaging') !== false || strpos($low, '/avatar/') !== false) {
            $reason = 'avatar/personnage Habbo (lien d\'origine mort, non recréé)';
        } elseif (preg_match('#/habbo2007/#', $src)) {
            $abs = (strpos($src, 'http') === 0) ? $src : ('https://mangetoica.com' . (strpos($src, '/') === 0 ? '' : '/habbo2007/') . ltrim($src, '/'));
            if (strpos($src, '/') === 0) $abs = 'https://mangetoica.com' . $src;
            $new = dl_image($abs, $imgdir = $GLOBALS['IMGDIR'], $GLOBALS['IMGURL'], $GLOBALS['ctx']);
            if (!$new) $reason = 'image template mangetoica introuvable';
        } elseif (preg_match('#c_images/(.+)$#', $src, $m)) {
            $local = $GLOBALS['ROOT'] . '/c_images/' . $m[1];
            if (is_file($local)) $new = '/c_images/' . $m[1];
            else $reason = 'c_images absent du pack local : ' . $m[1];
        } elseif (strpos($src, 'mangetoica.com') !== false) {
            $new = dl_image($src, $GLOBALS['IMGDIR'], $GLOBALS['IMGURL'], $GLOBALS['ctx']);
            if (!$new) $reason = 'image mangetoica introuvable';
        } elseif ($src !== '' && !preg_match('#^https?://#i', $src) && $src[0] !== '/') {
            $new = dl_image('https://mangetoica.com/habbo2007/' . ltrim($src, './'), $GLOBALS['IMGDIR'], $GLOBALS['IMGURL'], $GLOBALS['ctx']);
            if (!$new) $reason = 'image template relative introuvable : ' . $src;
        } else {
            $reason = 'image externe non récupérée (' . $src . ')';
        }
        if ($new) { $img->setAttribute('src', $new); }
        else {
            $missing[] = $src . ($reason ? " — $reason" : '');
            // remplacer par un marqueur honnête (pas d'invention)
            $span = $doc->createElement('span', 'image d\'origine indisponible');
            $span->setAttribute('class', 'missing-img');
            $span->setAttribute('title', $src);
            $img->parentNode->replaceChild($span, $img);
        }
    }
    // LIENS
    foreach (iterator_to_array($xp->query('.//a', $mc)) as $a) {
        $href = $a->getAttribute('href'); if ($href === '') continue;
        $base = strtolower(basename(parse_url($href, PHP_URL_PATH) ?: ''));
        if (isset($GLOBALS['PHP2ROUTE'][$base])) { $a->setAttribute('href', $GLOBALS['PHP2ROUTE'][$base]); }
        elseif (preg_match('#habbo\.fr/home/(.+)$#i', $href, $m)) { $a->setAttribute('href', '?p=home/' . rawurlencode(rawurldecode($m[1]))); }
        elseif (preg_match('#habbo\.fr/client#i', $href)) { $a->setAttribute('href', '/client.php'); $a->setAttribute('target', '_blank'); }
        elseif (preg_match('~\.php(?:$|[?\#])~', $base)) { $a->setAttribute('href', '?p=' . preg_replace('/\.php$/', '', $base)); }
        // sinon : lien externe conservé
    }

    // innerHTML de #main-content
    $body = '';
    foreach ($mc->childNodes as $c) $body .= $doc->saveHTML($c);

    db()->prepare('INSERT INTO site_pages (slug,parent_tab,title,body_html,source_ref,missing_note,is_historical)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE parent_tab=VALUES(parent_tab),title=VALUES(title),body_html=VALUES(body_html),source_ref=VALUES(source_ref),missing_note=VALUES(missing_note),is_historical=VALUES(is_historical)')
        ->execute([$slug, $parent, $title, $body, $url, $missing ? implode("\n", $missing) : null, $hist]);

    $report[] = sprintf('✅ %-16s « %s » — %d octets, images manquantes: %d', $slug, mb_strimwidth($title, 0, 40, '…'), strlen($body), count($missing));
}
echo implode("\n", $report) . "\n";
