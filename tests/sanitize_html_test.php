<?php
/**
 * Tests de régression du nettoyeur HTML (sanitize_html) — HabboretroV14.
 *
 * Lancer :  php tests/sanitize_html_test.php
 * Sortie   : liste des cas + "TOUS OK" ; code de sortie 0 si tout passe, 1 sinon.
 *
 * Le nettoyeur vit dans Server/www/admin/admin monolithe ; on en extrait juste la
 * fonction pour la tester en isolation (pas de session/DB nécessaires).
 *
 * Couvre notamment les 2 contournements trouvés à l'audit du 25/10/2026 :
 *   - <svg><script>…            (balise exécutable imbriquée dans une balise inconnue)
 *   - <object><a href=javascript:…>  (enfant dangereux promu au déballage)
 * + imbrication multi-niveaux, variantes d'URL encodées, et préservation du HTML 2007.
 */

$admin = __DIR__ . '/../Server/www/admin/index.php';
$src = @file_get_contents($admin);
if ($src === false) { fwrite(STDERR, "Introuvable: $admin\n"); exit(2); }
if (!preg_match('/function sanitize_html.*?\n\}/s', $src, $m)) { fwrite(STDERR, "sanitize_html introuvable\n"); exit(2); }
eval($m[0]);

$fail = 0; $n = 0;
/** @param string|null $mustGone regex qui NE DOIT PAS matcher la sortie ; @param string|null $mustKeep regex qui DOIT matcher */
function t(string $label, string $in, ?string $mustGone, ?string $mustKeep = null): void {
    global $fail, $n; $n++;
    $out = sanitize_html($in);
    $bad = $mustGone !== null && preg_match($mustGone, $out);
    $missing = $mustKeep !== null && !preg_match($mustKeep, $out);
    if ($bad || $missing) {
        $fail++;
        echo sprintf("  ECHEC #%02d  %s\n            in : %s\n            out: %s\n", $n, $label, $in, $out);
    } else {
        echo sprintf("  ok    #%02d  %s\n", $n, $label);
    }
}

/** Vérifie la SUPPRESSION RÉELLE d'un attribut dangereux (il ne doit plus apparaître du tout dans
 *  la sortie), et optionnellement qu'un attribut sûr est CONSERVÉ. */
function tAttr(string $label, string $in, string $attrGone, ?string $attrKeep = null): void {
    global $fail, $n; $n++;
    $out = sanitize_html($in);
    $goneOk = stripos($out, $attrGone . '=') === false;
    $keepOk = $attrKeep === null || stripos($out, $attrKeep . '=') !== false;
    if (!$goneOk || !$keepOk) {
        $fail++;
        echo sprintf("  ECHEC #%02d  %s (attendu: %s absent%s)\n            out: %s\n", $n, $label, $attrGone, $attrKeep ? ", $attrKeep présent" : '', $out);
    } else {
        echo sprintf("  ok    #%02d  %s\n", $n, $label);
    }
}

echo "== Contournements trouvés à l'audit (doivent être neutralisés) ==\n";
t('svg>script',               '<svg><script>alert(2)</script></svg>',                         '/<script|alert\(2\)/i');
t('object>a javascript',      '<object><a href="javascript:evil()">x</a></object>',           '/javascript:/i');
t('style injection',          '<p>a</p><style>b{background:url(javascript:x)}</style>',         '/<style|javascript:/i');
t('form+input',               '<form action="//evil"><input name=x></form>',                   '/<form|<input/i');

echo "== Imbrication multi-niveaux ==\n";
t('div>font>span>a js (tab)', "<div><font><span><a href=\"  jAvA\tscript:alert(1)\">x</a></span></font></div>", '/javascript:/i', '/<a[^>]*>x<\/a>/i');
t('b>i>svg>script',           '<b><i><svg><script>x</script></svg></i></b>',                   '/<script/i', '/<b><i>/i');
t('onerror sur img',          '<img src=x onerror="alert(1)">',                                '/onerror/i');
t('p>marquee>script',         '<p>ok<marquee><script>bad()</script></marquee></p>',            '/<script|<marquee/i', '/ok/');

echo "== Variantes d'URL dangereuses encodées / espacées ==\n";
t('colon en entité',          '<a href="javascript&#58;alert(1)">x</a>',                       '/javascript:/i');
t('tab en entité',            '<a href="java&#9;script:alert(1)">x</a>',                       '/javascript:/i');
t('j encodé',                 '<a href="&#106;avascript:alert(1)">x</a>',                      '/javascript:/i');
t('espaces avant schéma',     '<a href="   javascript:alert(1)">x</a>',                        '/javascript:/i');
t('casse mixte',              '<a href="JaVaScRiPt:alert(1)">x</a>',                           '/javascript:/i');
t('newline interne',          "<a href=\"java\nscript:alert(1)\">x</a>",                       '/javascript:/i');
t('vbscript',                 '<a href="vbscript:msgbox(1)">x</a>',                            '/vbscript:/i');
t('data:text/html',           '<a href="data:text/html,<script>x</script>">y</a>',             '/data:|<script/i');
t('img src javascript',       '<img src="javascript:alert(1)">',                               '/javascript:/i');

echo "== HTML « 2007 » autorisé (doit être CONSERVÉ) ==\n";
t('table complète',           '<table border="1" cellpadding="3"><tr><td colspan="2" style="width:80px">A</td></tr></table>', null, '/<table[^>]*border="1"[^>]*>.*<td[^>]*colspan="2"/is');
t('image http',               '<img src="http://img/x.gif" width="40" alt="x">',               null, '/<img[^>]*src="http:\/\/img\/x.gif"[^>]*width="40"/i');
t('lien _blank -> noopener',  '<a href="http://site" target="_blank">lien</a>',                null, '/<a[^>]*href="http:\/\/site"[^>]*rel="noopener/i');
t('mise en forme',            '<p><strong>gras</strong> <em>ital</em><br><ul><li>x</li></ul></p>', null, '/<strong>gras<\/strong>.*<li>x<\/li>/is');
t('div class/style',          '<div class="v3box" style="padding:4px">z</div>',                null, '/<div class="v3box" style="padding:4px">z<\/div>/i');

echo "== Suppression RÉELLE de l'attribut dangereux (plus présent du tout) ==\n";
tAttr('href javascript retiré',  '<a href="javascript:x">t</a>',                             'href');
tAttr('src javascript retiré',   '<img src="javascript:x" alt="a">',                         'src', 'alt');
tAttr('onclick retiré',          '<b onclick="h()">t</b>',                                   'onclick');
tAttr('onmouseover retiré',      '<span onmouseover="h()">t</span>',                         'onmouseover');
tAttr('style expression retiré', '<p style="x:expression(1)">t</p>',                         'style');
tAttr('style url(js) retiré',    '<p style="width:80px;background:url(javascript:y)">t</p>',  'style');
tAttr('href http CONSERVÉ',      '<a href="http://ok.fr">t</a>',                             'onclick', 'href');
tAttr('style 2007 CONSERVÉ',     '<div style="width:80px;padding:4px">z</div>',              'onclick', 'style');

echo "== CSS obfusqué (échappes / commentaires / IE) refusé ==\n";
tAttr('backslash CSS',           '<div style="x:url(java\\9 script:y)">z</div>',             'style');
tAttr('commentaire CSS',         '<div style="x:/*c*/url(javascript:y)">z</div>',            'style');
t('behavior IE',                 '<p style="behavior:url(#x)">t</p>',                        '/behavior/i');
t('@import',                     '<p style="@import url(//evil)">t</p>',                     '/@import/i');

echo "\n";
echo $fail === 0
    ? "TOUS OK — $n cas de régression passés.\n"
    : "$fail / $n cas ÉCHOUÉS.\n";
exit($fail === 0 ? 0 : 1);
