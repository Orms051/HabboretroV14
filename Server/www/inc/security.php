<?php
/**
 * HabboretroV14 — Système de sécurité du SITE (porté de Cadurix v5)
 * Surveillance des IP, scoring, auto-ban, lockdown (mode protection), événements.
 *
 * ⚠️ Protège le SITE WEB (site public + loader du jeu), PAS l'émulateur Kepler
 *    (le jeu passe par socket, pas par PHP).
 *
 * Réutilise les helpers déjà définis par le contexte appelant :
 *   - db()  : PDO partagé (défini dans v2/inc/boot.php ET admin/index.php)
 *   - h()   : échappement HTML
 *   - is_staff() : rang >= 5 (défini dans boot.php ; en admin on retombe sur la session)
 *
 * Config stockée dans la table `settings` (clé `sec.*` / valeur), éditée depuis
 * l'admin (onglet Sécurité). Tables créées à la demande (CREATE IF NOT EXISTS,
 * SQL standard compatible MySQL 8.4 + MariaDB 11.4).
 *
 * GARDE-FOU ANTI-VERROUILLAGE (impossible de se bloquer soi-même) :
 *   - localhost (127.0.0.1 / ::1) : jamais bloqué ;
 *   - staff connecté (rang >= 5)  : jamais bloqué ;
 *   - IP en liste blanche         : jamais bloquée ;
 *   - IP qui déclenche un lockdown : auto-whitelistée sur ce lockdown ;
 *   - token d'urgence             : /security/emergency_unlock.php?token=...
 */

if (!defined('SEC_LOADED')) {
    define('SEC_LOADED', 1);

    /* ── IP réelle (derrière proxy éventuel) ───────────────────────────── */
    function sec_real_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', (string)$_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /* ── Config (settings sec.*) + défauts ─────────────────────────────── */
    function sec_defaults(): array {
        return [
            'sec.login_fail'    => '10',  // échecs login (par IP) avant ban auto
            'sec.global_fail'   => '50',  // échecs globaux / heure avant lockdown auto
            'sec.lockdown_min'  => '30',  // durée d'un lockdown auto (minutes)
            'sec.ban_min'       => '60',  // durée d'un ban IP (minutes)
            'sec.auto_lockdown' => '1',   // lockdown automatique au seuil global
            'sec.notify_email'  => '0',   // notifications e-mail (OFF : pas de SMTP en local)
            'sec.alert_email'   => '',    // destinataire des alertes
            'sec.whitelist'     => '',    // IPs jamais bloquées (JSON : ["1.2.3.4"])
        ];
    }
    function sec_cfg(): array {
        static $cfg = null;
        if ($cfg !== null) return $cfg;
        $cfg = sec_defaults();
        try {
            $keys = array_keys($cfg);
            $ph = implode(',', array_fill(0, count($keys), '?'));
            $st = db()->prepare("SELECT setting,value FROM settings WHERE setting IN ($ph)");
            $st->execute($keys);
            foreach ($st->fetchAll() as $r) $cfg[$r['setting']] = (string)$r['value'];
        } catch (Throwable $e) {}
        return $cfg;
    }
    function sec_cfg_get(string $k, $def = '') { $c = sec_cfg(); return $c[$k] ?? $def; }

    /* ── Création des tables (à la demande, une fois par requête) ───────── */
    function sec_ensure(): void {
        static $done = false;
        if ($done) return; $done = true;
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS security_ips (
                ip VARCHAR(45) NOT NULL PRIMARY KEY,
                score INT NOT NULL DEFAULT 0,
                nb_tentatives INT NOT NULL DEFAULT 0,
                raison VARCHAR(120) NOT NULL DEFAULT '',
                statut VARCHAR(12) NOT NULL DEFAULT 'surveille',
                banni_jusqu DATETIME NULL,
                derniere_vue DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX(statut)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            db()->exec("CREATE TABLE IF NOT EXISTS security_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type VARCHAR(24) NOT NULL,
                ip VARCHAR(45) NOT NULL DEFAULT '',
                user_id INT NULL,
                details VARCHAR(255) NOT NULL DEFAULT '',
                user_agent VARCHAR(255) NOT NULL DEFAULT '',
                url VARCHAR(500) NOT NULL DEFAULT '',
                cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX(type), INDEX(cree_le), INDEX(ip)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            db()->exec("CREATE TABLE IF NOT EXISTS security_lockdown (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actif TINYINT NOT NULL DEFAULT 1,
                niveau VARCHAR(12) NOT NULL DEFAULT 'lockdown',
                raison VARCHAR(255) NOT NULL DEFAULT '',
                declenche_par INT NULL,
                declenche_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expire_le DATETIME NULL,
                token_urgence VARCHAR(64) NOT NULL DEFAULT '',
                ip_whitelist TEXT NULL,
                debloque_le DATETIME NULL,
                debloque_par INT NULL,
                INDEX(actif)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {}
    }

    /* ── Exemptions (jamais bloqué) ────────────────────────────────────── */
    function sec_ip_is_local(string $ip): bool {
        return in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true);
    }
    function sec_current_user_id(): ?int {
        $id = (int)($_SESSION['admin']['id'] ?? 0);
        if ($id <= 0 && isset($_SESSION['site_user']['id'])) $id = (int)$_SESSION['site_user']['id'];
        return $id > 0 ? $id : null;
    }
    function sec_is_staff(): bool {
        if (function_exists('is_staff')) { try { return is_staff(); } catch (Throwable $e) {} }
        return (int)($_SESSION['admin']['rank'] ?? 0) >= 5;
    }
    function sec_whitelist(): array {
        $raw = (string)sec_cfg_get('sec.whitelist', '');
        if ($raw === '') return [];
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }
    /** Cette IP est-elle exemptée de tout blocage ? */
    function sec_exempt(?string $ip = null): bool {
        $ip = $ip ?? sec_real_ip();
        if (sec_ip_is_local($ip)) return true;      // dev local : jamais bloqué
        if (sec_is_staff()) return true;            // staff connecté : jamais bloqué
        if (in_array($ip, sec_whitelist(), true)) return true;
        return false;
    }

    /* ── Journal d'événements ──────────────────────────────────────────── */
    function sec_log_event(string $type, string $details = ''): void {
        sec_ensure();
        try {
            db()->prepare("INSERT INTO security_events (type,ip,user_id,details,user_agent,url) VALUES (?,?,?,?,?,?)")
                ->execute([
                    $type, sec_real_ip(), sec_current_user_id(),
                    mb_substr($details, 0, 255),
                    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 500),
                ]);
        } catch (Throwable $e) {}
    }

    /* ── Score IP ──────────────────────────────────────────────────────── */
    function sec_bump_score(int $points, string $raison): void {
        sec_ensure();
        $ip = sec_real_ip();
        try {
            db()->prepare("INSERT INTO security_ips (ip,score,nb_tentatives,raison,derniere_vue)
                VALUES (?,?,1,?,NOW())
                ON DUPLICATE KEY UPDATE score=LEAST(score+?,100), nb_tentatives=nb_tentatives+1, raison=?, derniere_vue=NOW()")
                ->execute([$ip, $points, $raison, $points, $raison]);
        } catch (Throwable $e) {}
    }
    function sec_check_autoban(bool $immediate = false): void {
        $ip = sec_real_ip();
        try {
            $st = db()->prepare("SELECT score,nb_tentatives FROM security_ips WHERE ip=?");
            $st->execute([$ip]); $d = $st->fetch();
            if (!$d) return;
            $threshold = (int)sec_cfg_get('sec.login_fail', 10);
            if ($immediate || (int)$d['score'] >= 70 || (int)$d['nb_tentatives'] >= $threshold) {
                $min = (int)sec_cfg_get('sec.ban_min', 60);
                db()->prepare("UPDATE security_ips SET statut='banni', banni_jusqu=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE ip=?")
                    ->execute([$min, $ip]);
                sec_log_event('ban_ip', "Auto — score {$d['score']}, {$d['nb_tentatives']} tentative(s)");
                sec_notify("🚫 IP bannie : $ip", "Score {$d['score']}, {$d['nb_tentatives']} tentative(s).");
            }
        } catch (Throwable $e) {}
    }
    function sec_check_autolockdown(): void {
        if (!(int)sec_cfg_get('sec.auto_lockdown', 1)) return;
        try {
            $n = (int)db()->query("SELECT COUNT(*) FROM security_events WHERE type IN ('login_fail','brute_force') AND cree_le > DATE_SUB(NOW(),INTERVAL 1 HOUR)")->fetchColumn();
            if ($n >= (int)sec_cfg_get('sec.global_fail', 50)) {
                sec_trigger_lockdown(null, "Seuil automatique atteint ($n tentatives/heure)", 'lockdown');
            }
        } catch (Throwable $e) {}
    }

    /* ── Hooks publics ─────────────────────────────────────────────────── */
    function sec_record_login_fail(string $login = ''): void {
        sec_bump_score(15, 'login_fail');
        sec_log_event('login_fail', "Login: $login");
        sec_check_autoban();
        sec_check_autolockdown();
    }
    function sec_record_suspicious(string $reason = ''): void {
        sec_bump_score(10, 'suspicious');
        sec_log_event('suspicious', $reason);
        sec_check_autoban();
    }

    /* ── Bans IP ───────────────────────────────────────────────────────── */
    function sec_is_ip_banned(): bool {
        if (sec_exempt()) return false;
        $ip = sec_real_ip();
        try {
            $st = db()->prepare("SELECT banni_jusqu FROM security_ips WHERE ip=? AND statut='banni'");
            $st->execute([$ip]); $d = $st->fetch();
            if (!$d) return false;
            if (!empty($d['banni_jusqu']) && strtotime((string)$d['banni_jusqu']) < time()) {
                sec_unban_ip($ip); // ban expiré : on lève
                return false;
            }
            return true;
        } catch (Throwable $e) { return false; }
    }
    function sec_ban_ip(string $ip, int $minutes = 0, string $raison = 'Manuel'): void {
        sec_ensure();
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return;
        $min = $minutes > 0 ? $minutes : (int)sec_cfg_get('sec.ban_min', 60);
        try {
            db()->prepare("INSERT INTO security_ips (ip,score,statut,raison,banni_jusqu,derniere_vue)
                VALUES (?,100,'banni',?,DATE_ADD(NOW(),INTERVAL ? MINUTE),NOW())
                ON DUPLICATE KEY UPDATE statut='banni', score=100, raison=?, banni_jusqu=DATE_ADD(NOW(),INTERVAL ? MINUTE)")
                ->execute([$ip, $raison, $min, $raison, $min]);
            sec_log_event('ban_ip', "Ban manuel : $ip ($raison)");
        } catch (Throwable $e) {}
    }
    function sec_unban_ip(string $ip): void {
        sec_ensure();
        try {
            db()->prepare("UPDATE security_ips SET statut='surveille', banni_jusqu=NULL, score=GREATEST(score-30,0) WHERE ip=?")->execute([$ip]);
            sec_log_event('unban_ip', "IP débannie : $ip");
        } catch (Throwable $e) {}
    }

    /* ── Lockdown (mode protection global) ─────────────────────────────── */
    function sec_is_lockdown(): bool {
        try {
            $row = db()->query("SELECT expire_le, ip_whitelist FROM security_lockdown WHERE actif=1 ORDER BY id DESC LIMIT 1")->fetch();
            if (!$row) return false;
            if (!empty($row['expire_le']) && strtotime((string)$row['expire_le']) < time()) {
                sec_unlock(null, 'Expiration automatique'); // expiré
                return false;
            }
            if (sec_exempt()) return false;
            if (!empty($row['ip_whitelist'])) {
                $wl = json_decode((string)$row['ip_whitelist'], true) ?: [];
                if (in_array(sec_real_ip(), $wl, true)) return false;
            }
            return true;
        } catch (Throwable $e) { return false; }
    }
    function sec_trigger_lockdown(?int $userId, string $raison, string $niveau = 'lockdown'): string {
        sec_ensure();
        try {
            if ((int)db()->query("SELECT COUNT(*) FROM security_lockdown WHERE actif=1")->fetchColumn() > 0) return '';
            $duration = (int)sec_cfg_get('sec.lockdown_min', 30);
            $token = bin2hex(random_bytes(32));
            $wl = json_encode([sec_real_ip()]); // l'IP qui déclenche garde l'accès
            db()->prepare("INSERT INTO security_lockdown (actif,niveau,raison,declenche_par,declenche_le,expire_le,token_urgence,ip_whitelist)
                VALUES (1,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL ? MINUTE),?,?)")
                ->execute([$niveau, $raison, $userId, $duration, $token, $wl]);
            sec_log_event('lockdown', $raison);
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
            sec_notify("🔒 LOCKDOWN ACTIVÉ — " . (defined('HOTEL') ? HOTEL : 'Hôtel'),
                "Raison : $raison\nIP : " . sec_real_ip() . "\nDate : " . date('d/m/Y H:i:s') .
                "\n\nDéblocage d'urgence :\nhttp://$host/security/emergency_unlock.php?token=$token");
            return $token;
        } catch (Throwable $e) { return ''; }
    }
    function sec_unlock(?int $userId, string $raison = 'Manuel'): void {
        try {
            db()->prepare("UPDATE security_lockdown SET actif=0, debloque_le=NOW(), debloque_par=? WHERE actif=1")->execute([$userId]);
            sec_log_event('unlock', $raison);
        } catch (Throwable $e) {}
    }

    /* ── Anti-injection dans l'URL (signatures resserrées, cf. Cadurix) ── */
    function sec_check_injection(): void {
        $hay = (string)($_SERVER['REQUEST_URI'] ?? '') . ' ' . (string)($_SERVER['QUERY_STRING'] ?? '');
        $patterns = [
            'union\s+(all\s+)?select', 'information_schema', 'drop\s+table', 'truncate\s+table',
            ';\s*(drop|delete|update|insert|alter)\s', '(%27|\')\s*(or|and)\s+[\'"0-9]',
            '(sleep|benchmark|pg_sleep)\s*\(', 'into\s+outfile', 'load_file\s*\(',
            '<script', 'javascript:', 'base64_decode', 'eval\s*\(',
        ];
        foreach ($patterns as $p) {
            if (preg_match('/' . $p . '/i', $hay)) {
                if (!sec_exempt()) {
                    sec_bump_score(40, 'sql_inject');
                    sec_log_event('sql_inject', 'URL: ' . substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 200));
                    sec_check_autoban(true);
                    http_response_code(403);
                    die(sec_render_block_page('Requête bloquée', 'Contenu suspect détecté dans la requête.'));
                }
                return; // exempté : on laisse passer sans pénaliser
            }
        }
    }

    /* ── Middleware : à appeler en tête du SITE (boot.php) ──────────────── */
    function sec_guard(): void {
        // Fail-open : toute erreur (tables absentes, base lente…) laisse passer.
        try {
            // Exempté (localhost / staff / liste blanche) : jamais filtré, et AUCUNE requête DB
            // -> le site de Yoann en local ne paie rien tant qu'il n'y a pas de menace.
            if (sec_exempt()) return;
            sec_check_injection();
            if (sec_is_ip_banned()) { http_response_code(403); die(sec_render_block_page()); }
            if (sec_is_lockdown())  { http_response_code(503); header('Retry-After: 1800'); die(sec_render_block_page()); }
        } catch (Throwable $e) {}
    }

    /* ── Stats (dashboard) ─────────────────────────────────────────────── */
    function sec_stats(): array {
        sec_ensure();
        $s = ['lockdown' => false, 'bannies' => 0, 'suspectes' => 0, 'events_24h' => 0, 'fails_1h' => 0];
        try {
            $s['lockdown']   = (bool)db()->query("SELECT COUNT(*) FROM security_lockdown WHERE actif=1")->fetchColumn();
            $s['bannies']    = (int)db()->query("SELECT COUNT(*) FROM security_ips WHERE statut='banni'")->fetchColumn();
            $s['suspectes']  = (int)db()->query("SELECT COUNT(*) FROM security_ips WHERE score>=50 AND statut<>'banni'")->fetchColumn();
            $s['events_24h'] = (int)db()->query("SELECT COUNT(*) FROM security_events WHERE cree_le > DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
            $s['fails_1h']   = (int)db()->query("SELECT COUNT(*) FROM security_events WHERE type='login_fail' AND cree_le > DATE_SUB(NOW(),INTERVAL 1 HOUR)")->fetchColumn();
        } catch (Throwable $e) {}
        return $s;
    }

    /* ── Notification e-mail (best-effort, OFF par défaut) ─────────────── */
    function sec_notify(string $subject, string $body): void {
        if (!(int)sec_cfg_get('sec.notify_email', 0)) return;
        $to = (string)sec_cfg_get('sec.alert_email', '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $headers .= 'From: ' . (defined('HOTEL') ? HOTEL : 'Hotel') . " Securite <noreply@localhost>\r\n";
        @mail($to, $subject, $body, $headers);
    }

    /* ── Page de blocage (lockdown / ban / injection) ──────────────────── */
    function sec_render_block_page(string $title = '', string $msg = ''): string {
        $hotel = defined('HOTEL') ? HOTEL : 'Habbo';
        if ($title === '') $title = 'Accès temporairement restreint';
        if ($msg === '')   $msg   = "Une activité inhabituelle a été détectée. Par mesure de sécurité, l'accès au site est momentanément suspendu.";
        $expire = 'Déverrouillage manuel uniquement';
        try {
            $row = db()->query("SELECT expire_le FROM security_lockdown WHERE actif=1 ORDER BY id DESC LIMIT 1")->fetch();
            if ($row && !empty($row['expire_le'])) {
                $m = max(0, (int)ceil((strtotime((string)$row['expire_le']) - time()) / 60));
                $expire = 'Déverrouillage automatique dans ' . $m . ' min';
            }
        } catch (Throwable $e) {}
        $H = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . $H($hotel) . ' — Accès restreint</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,Segoe UI,Roboto,system-ui,sans-serif;background:#0a0410;color:#fde8e8;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:20px}
.orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0}
.o1{width:560px;height:560px;background:radial-gradient(circle,rgba(239,68,68,.22),transparent 65%);top:-160px;left:-140px}
.o2{width:420px;height:420px;background:radial-gradient(circle,rgba(190,18,60,.16),transparent 65%);bottom:-120px;right:-100px}
.wrap{position:relative;z-index:1;width:100%;max-width:440px;animation:up .7s ease both}
@keyframes up{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
.logo{text-align:center;font-size:1.5rem;font-weight:800;color:#fff;margin-bottom:1.6rem}
.logo em{color:#fca5a5;font-style:normal}
.card{background:rgba(255,255,255,.03);border:1px solid rgba(239,68,68,.25);border-radius:22px;padding:2.4rem 2rem;text-align:center;backdrop-filter:blur(24px);box-shadow:0 30px 70px rgba(0,0,0,.6)}
.ring{width:84px;height:84px;margin:0 auto 1.4rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.2rem;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);position:relative}
.ring::before{content:"";position:absolute;top:-6px;left:-6px;right:-6px;bottom:-6px;border-radius:50%;border:2px solid rgba(239,68,68,.35);border-top-color:transparent;animation:spin 2.4s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.badge{display:inline-flex;align-items:center;gap:7px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:.35rem 1rem;border-radius:99px;font-size:.76rem;font-weight:700;margin-bottom:1.3rem}
.bdot{width:7px;height:7px;border-radius:50%;background:#ef4444;animation:blink 1.4s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
h1{font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:.7rem}
p{font-size:.9rem;color:rgba(253,232,232,.55);line-height:1.6}
.timer{margin-top:1.4rem;padding-top:1.2rem;border-top:1px solid rgba(239,68,68,.15);font-size:.82rem;color:#fca5a5;font-weight:600}
.foot{margin-top:1.3rem;text-align:center;font-size:.7rem;color:rgba(255,255,255,.18);letter-spacing:1.5px;text-transform:uppercase}
</style></head><body>
<div class="orb o1"></div><div class="orb o2"></div>
<div class="wrap">
  <div class="logo">' . $H($hotel) . '<em>.</em></div>
  <div class="card">
    <div class="ring">🔒</div>
    <div class="badge"><span class="bdot"></span>Mode protection activé</div>
    <h1>' . $H($title) . '</h1>
    <p>' . $H($msg) . '</p>
    <div class="timer">🕐 ' . $H($expire) . '</div>
  </div>
  <div class="foot">' . $H($hotel) . ' · Sécurité · ' . date('Y') . '</div>
</div>
</body></html>';
    }
}
