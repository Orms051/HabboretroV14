<?php
/**
 * Retire tout <script>/<style>/<iframe>/<object> et les attributs on* des contenus importés
 * (site_pages.body_html + body_draft). Idempotent.
 * Usage : php v2/tools/clean_scripts.php
 */
declare(strict_types=1);
chdir(dirname(__DIR__, 2));
require dirname(__DIR__, 2) . '/v2/inc/boot.php';
ensure_site_pages();

function strip_scripts(string $html): string {
    if (trim($html) === '') return $html;
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);
    foreach (iterator_to_array($xp->query('//script | //style | //iframe | //object | //embed | //noscript')) as $n) $n->parentNode->removeChild($n);
    foreach (iterator_to_array($xp->query('//@*')) as $a) { if (stripos($a->name, 'on') === 0) $a->ownerElement->removeAttribute($a->name); }
    $root = $doc->getElementById('__r'); $out = '';
    if ($root) foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}

$n = 0; $changed = 0;
foreach (db()->query('SELECT slug,body_html,body_draft FROM site_pages') as $r) {
    $n++;
    $nh = strip_scripts((string)$r['body_html']);
    $nd = $r['body_draft'] !== null ? strip_scripts((string)$r['body_draft']) : null;
    if ($nh !== (string)$r['body_html'] || $nd !== $r['body_draft']) {
        db()->prepare('UPDATE site_pages SET body_html=?, body_draft=? WHERE slug=?')->execute([$nh, $nd, $r['slug']]);
        $changed++; echo "nettoyé : " . $r['slug'] . "\n";
    }
}
echo "Pages examinées=$n, modifiées=$changed.\n";
