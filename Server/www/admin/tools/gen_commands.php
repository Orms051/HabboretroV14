<?php
/**
 * gen_commands.php — Générateur de l'inventaire des commandes en jeu.
 *
 * ANALYSE STATIQUE UNIQUEMENT. Ne lance JAMAIS le JAR : il est seulement
 * ouvert comme archive ZIP pour lister les classes compilées et calculer son
 * empreinte. Les détails (alias, permissions, arguments, description serveur)
 * sont lus dans les SOURCES Java qui correspondent à ce JAR (vérification
 * croisée des classes). Produit admin/data/commands.generated.json.
 *
 * Usage (CLI uniquement, hors HTTP) :
 *   php admin/tools/gen_commands.php
 *
 * Séparation des données : ce fichier ne produit QUE des données extraites du
 * serveur. Les descriptions françaises sont dans admin/data/commands.docs.php
 * (écrit à la main) et ne sont jamais touchées par ce générateur.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("403 — gen_commands.php est un outil en ligne de commande (CLI), pas accessible en HTTP.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ---- Localisation des fichiers -------------------------------------------
$ADMIN_DIR = dirname(__DIR__);                 // .../Server/www/admin
$WWW_DIR   = dirname($ADMIN_DIR);              // .../Server/www
$REPO_ROOT = dirname(dirname($WWW_DIR));       // .../HabboretroV14
$JAR       = $WWW_DIR . '/kepler.jar';
$SRC_BASE  = $REPO_ROOT . '/_archive/dev_sources/Kepler-Server/src/main/java/org/alexdev/kepler/game';
$CMD_SRC   = $SRC_BASE . '/commands';
$OUT       = $ADMIN_DIR . '/data/commands.generated.json';

$warnings = [];
function warn(string $m): void { global $warnings; $warnings[] = $m; fwrite(STDERR, "⚠ $m\n"); }
function readfile_s(string $p): ?string { $c = @file_get_contents($p); return $c === false ? null : str_replace("\r\n", "\n", $c); }

if (!is_file($JAR))      { fwrite(STDERR, "ERREUR : kepler.jar introuvable ($JAR)\n"); exit(1); }
if (!is_dir($CMD_SRC))   { fwrite(STDERR, "ERREUR : sources des commandes introuvables ($CMD_SRC)\n"); exit(1); }

// ---- 1) Empreinte du JAR --------------------------------------------------
$jarInfo = [
    'file'   => 'Server/www/kepler.jar',
    'size'   => filesize($JAR),
    'sha256' => hash_file('sha256', $JAR),
    'mtime'  => date('c', filemtime($JAR)),
];

// ---- 2) Classes de commandes compilées dans le JAR (sans exécuter le JAR) --
/**
 * Liste les noms de fichiers d'un ZIP/JAR en lisant sa « central directory »,
 * sans l'extension zip et sans exécuter l'archive. Repli : balayage des
 * en-têtes locaux si la central directory est introuvable.
 */
function zip_list_names(string $path): array {
    if (class_exists('ZipArchive')) {
        $z = new ZipArchive();
        if ($z->open($path) === true) {
            $names = [];
            for ($i = 0; $i < $z->numFiles; $i++) $names[] = $z->getNameIndex($i);
            $z->close();
            return $names;
        }
    }
    $data = file_get_contents($path);
    if ($data === false) return [];
    $names = [];
    // EOCD : signature PK\x05\x06, recherchée dans la fin du fichier.
    $eocd = strrpos($data, "PK\x05\x06");
    if ($eocd !== false) {
        $cdOffset = unpack('V', substr($data, $eocd + 16, 4))[1];
        $total    = unpack('v', substr($data, $eocd + 10, 2))[1];
        if ($cdOffset !== 0xFFFFFFFF && $cdOffset < strlen($data)) {
            $p = $cdOffset;
            for ($n = 0; $n < $total; $n++) {
                if (substr($data, $p, 4) !== "PK\x01\x02") break;
                $fnlen = unpack('v', substr($data, $p + 28, 2))[1];
                $eflen = unpack('v', substr($data, $p + 30, 2))[1];
                $cmlen = unpack('v', substr($data, $p + 32, 2))[1];
                $names[] = substr($data, $p + 46, $fnlen);
                $p += 46 + $fnlen + $eflen + $cmlen;
            }
            if ($names) return $names;
        }
    }
    // Repli : en-têtes locaux PK\x03\x04
    $off = 0;
    while (($pos = strpos($data, "PK\x03\x04", $off)) !== false) {
        $fnlen = unpack('v', substr($data, $pos + 26, 2))[1];
        $names[] = substr($data, $pos + 30, $fnlen);
        $off = $pos + 4;
    }
    return $names;
}

$jarClasses = []; // nom simple => chemin interne
foreach (zip_list_names($JAR) as $name) {
    if (preg_match('#org/alexdev/kepler/game/commands/(registered|clientside)/([A-Za-z0-9_]+)\.class$#', $name, $m)) {
        if (strpos($m[2], '$') !== false) continue; // classes internes (ex. GiveCreditsCommand$1)
        $jarClasses[$m[2]] = ['pkg' => $m[1], 'path' => $name];
    }
}
if (!$jarClasses) warn("Aucune classe de commande détectée dans le JAR (lecture ZIP) — vérification croisée désactivée.");

// ---- 3) Mapping Fuseright -> rang minimum (depuis Fuseright.java) ----------
$fuseMap = []; // NAME => ['rank'=>?string, 'club'=>bool]
$fuseSrc = readfile_s($SRC_BASE . '/fuserights/Fuseright.java');
if ($fuseSrc === null) { warn("Fuseright.java illisible — mapping des droits incomplet."); }
else {
    foreach (explode("\n", $fuseSrc) as $line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\(\s*"[^"]*"\s*,\s*PlayerRank\.([A-Z_]+)\s*\)/', $line, $m)) {
            $fuseMap[$m[1]] = ['rank' => $m[2], 'club' => false];
        } elseif (preg_match('/^\s*([A-Z0-9_]+)\(\s*"[^"]*"\s*,\s*(true|false)\s*\)/', $line, $m)) {
            $fuseMap[$m[1]] = ['rank' => null, 'club' => ($m[2] === 'true')];
        }
    }
}

// ---- 4) Hiérarchie des rangs (depuis PlayerRank.java) ---------------------
$rankMap = []; // NAME => id
$rankSrc = readfile_s($SRC_BASE . '/player/PlayerRank.java');
if ($rankSrc === null) { warn("PlayerRank.java illisible — ids de rang inconnus."); }
else {
    foreach (explode("\n", $rankSrc) as $line) {
        if (preg_match('/^\s*([A-Z_]+)\(\s*(\d+)\s*\)/', $line, $m)) $rankMap[$m[1]] = (int)$m[2];
    }
}

// ---- 5) Table d'enregistrement (depuis CommandManager.java) ---------------
$cmSrc = readfile_s($CMD_SRC . '/CommandManager.java');
if ($cmSrc === null) { fwrite(STDERR, "ERREUR : CommandManager.java illisible.\n"); exit(1); }

$registered = []; // ordre d'enregistrement : [aliases[], class]
if (preg_match_all('/commands\.put\(\s*new\s+String\[\]\s*\{([^}]*)\}\s*,\s*new\s+(\w+)\(\)\s*\)/', $cmSrc, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $m) {
        preg_match_all('/"([^"]+)"/', $m[1], $am);
        $registered[] = ['aliases' => $am[1], 'class' => $m[2]];
    }
}
if (!$registered) { fwrite(STDERR, "ERREUR : aucune commande trouvée dans CommandManager.java.\n"); exit(1); }

// ---- 6) Analyse de chaque handler -----------------------------------------
function find_src(string $cmdSrc, string $class): ?array {
    foreach (['registered', 'clientside'] as $pkg) {
        $p = "$cmdSrc/$pkg/$class.java";
        if (is_file($p)) return ['pkg' => $pkg, 'path' => $p];
    }
    return null;
}
function strip_comments(string $s): string {
    $s = preg_replace('#/\*.*?\*/#s', '', $s);   // blocs /* ... */
    $s = preg_replace('#//[^\n]*#', '', $s);     // lignes //
    return $s;
}
function parse_handler(string $src): array {
    // description serveur (getDescription -> return "...") : AVANT de retirer les commentaires.
    $desc = null;
    if (preg_match('/getDescription\s*\([^)]*\)\s*\{\s*return\s*"((?:[^"\\\\]|\\\\.)*)"\s*;/s', $src, $dm)) {
        $desc = stripcslashes($dm[1]);
    }
    // Le reste s'analyse sur une version sans commentaires (ex. :shutdown a un
    // arguments.add("minutes") commenté qui ne doit PAS compter comme requis).
    $clean = strip_comments($src);
    // permissions
    $perms = [];
    if (preg_match_all('/permissions\.add\(\s*Fuseright\.([A-Z0-9_]+)\s*\)/', $clean, $pm)) $perms = $pm[1];
    // arguments requis (ordre)
    $args = [];
    if (preg_match_all('/arguments\.add\(\s*"([^"]*)"\s*\)/', $clean, $am)) $args = $am[1];
    // extends Command ? (sinon = stub)
    $extendsCommand = (bool)preg_match('/class\s+\w+\s+extends\s+Command\b/', $clean);
    // le corps du handler est-il non vide ?
    $bodyNonEmpty = false;
    if (preg_match('/handleCommand\s*\([^)]*\)\s*\{(.*?)\n\s*\}\s*(@Override|public|\})/s', $clean, $bm)) {
        $bodyNonEmpty = trim($bm[1]) !== '';
    }
    return compact('perms', 'args', 'desc', 'extendsCommand', 'bodyNonEmpty');
}

function min_rank_for(array $perms, array $fuseMap, array $rankMap, array &$warnings): array {
    // Sémantique serveur : accès si l'entité possède AU MOINS UN des droits (OU).
    // Donc rang minimum = plus petit rang parmi les droits à rang.
    if (!$perms) return ['id' => 0, 'name' => 'RANKLESS', 'clubOnly' => false, 'unknown' => false];
    $best = null; $bestName = null; $club = false; $unknown = false;
    foreach ($perms as $p) {
        if (!isset($fuseMap[$p])) { $unknown = true; $warnings[] = "Droit inconnu dans Fuseright.java : $p"; continue; }
        $f = $fuseMap[$p];
        if ($f['rank'] === null) { if ($f['club']) $club = true; continue; }
        $rid = $rankMap[$f['rank']] ?? null;
        if ($rid === null) { $unknown = true; continue; }
        if ($best === null || $rid < $best) { $best = $rid; $bestName = $f['rank']; }
    }
    if ($best === null) {
        // Aucun droit à rang (que du club) -> pas de rang requis, club éventuellement.
        return ['id' => null, 'name' => null, 'clubOnly' => $club, 'unknown' => $unknown];
    }
    return ['id' => $best, 'name' => $bestName, 'clubOnly' => $club, 'unknown' => $unknown];
}

$commands = [];
$seenClasses = [];
foreach ($registered as $reg) {
    $class = $reg['class'];
    $seenClasses[$class] = true;
    $srcInfo = find_src($CMD_SRC, $class);
    if (!$srcInfo) { warn("Source introuvable pour $class — ignorée."); continue; }
    $src = readfile_s($srcInfo['path']);
    $h = parse_handler($src);
    $inJar = isset($jarClasses[$class]);
    if (!$inJar) warn("$class enregistrée mais ABSENTE du JAR (source/JAR désynchronisés ?)");
    $mr = min_rank_for($h['perms'], $fuseMap, $rankMap, $warnings);
    $commands[] = [
        'name'          => $reg['aliases'][0],
        'aliases'       => $reg['aliases'],
        'class'         => $class,
        'clientSide'    => $srcInfo['pkg'] === 'clientside',
        'registered'    => true,
        'inJar'         => $inJar,
        'permissions'   => $h['perms'],
        'minRankId'     => $mr['id'],
        'minRankName'   => $mr['name'],
        'clubRelated'   => $mr['clubOnly'],
        'requiredArgs'  => $h['args'],
        'descriptionEn' => $h['desc'],
    ];
}

// ---- 7) Classes compilées mais NON enregistrées (candidats « Propositions ») --
$unregistered = [];
foreach ($jarClasses as $class => $info) {
    if (isset($seenClasses[$class])) continue;
    $srcInfo = find_src($CMD_SRC, $class);
    $stub = true; $desc = null;
    if ($srcInfo) { $h = parse_handler(readfile_s($srcInfo['path'])); $stub = !$h['extendsCommand'] || !$h['bodyNonEmpty']; $desc = $h['desc']; }
    $unregistered[] = [
        'class'         => $class,
        'pkg'           => $info['pkg'],
        'isStub'        => $stub,
        'descriptionEn' => $desc,
    ];
}

// ---- 8) Écriture ----------------------------------------------------------
$aliasCount = 0; foreach ($commands as $c) $aliasCount += count($c['aliases']);
$out = [
    '_note'        => "FICHIER GÉNÉRÉ — ne pas éditer à la main. Régénérer avec: php admin/tools/gen_commands.php. Descriptions FR dans commands.docs.php.",
    'generatedAt'  => date('c'),
    'generator'    => 'gen_commands.php (analyse statique, sans exécution du JAR)',
    'jar'          => $jarInfo,
    'ranks'        => $rankMap,
    'fuserights'   => $fuseMap,
    'counts'       => [
        'commands'      => count($commands),
        'aliasesTotal'  => $aliasCount,
        'clientSide'    => count(array_filter($commands, fn($c) => $c['clientSide'])),
        'unregistered'  => count($unregistered),
        'jarCmdClasses' => count($jarClasses),
    ],
    'limits' => [
        "Les alias, permissions, arguments requis et description serveur sont lus dans les sources Java correspondant au JAR (classes vérifiées présentes dans le JAR). L'analyse n'exécute pas le JAR.",
        "Les sous-commandes, paramètres facultatifs, valeurs par défaut, conditions, effets et causes d'échec ne sont pas tous exprimables mécaniquement : ils sont documentés à la main dans commands.docs.php.",
        "Le nombre d'arguments requis vient de addArguments() ; certains handlers acceptent des arguments facultatifs supplémentaires non déclarés là (ex. :motto, :hotelalert, :talk, :rgb, :shutdown).",
        "L'attribution rang↔droit vient de l'enum Fuseright (minimumRank) : ce build ne lit PAS les droits en base. Tout droit marqué « non confirmé » dans l'enum est signalé.",
    ],
    'warnings'     => array_values(array_unique($warnings)),
    'commands'     => $commands,
    'unregistered' => $unregistered,
];

if (!is_dir(dirname($OUT))) mkdir(dirname($OUT), 0775, true);
file_put_contents($OUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

printf("✅ Écrit %s\n", $OUT);
printf("   JAR  : %s (%d octets)\n   SHA256: %s\n", basename($jarInfo['file']), $jarInfo['size'], $jarInfo['sha256']);
printf("   Commandes: %d · alias: %d · client-side: %d · non enregistrées: %d\n",
    $out['counts']['commands'], $out['counts']['aliasesTotal'], $out['counts']['clientSide'], $out['counts']['unregistered']);
if ($warnings) printf("   ⚠ %d avertissement(s)\n", count(array_unique($warnings)));
