<?php
/**
 * HabboretroV14 — Déblocage d'urgence par token
 * Autonome : N'INCLUT PAS boot.php, donc JAMAIS bloqué par le lockdown.
 * Le token est généré au déclenchement du lockdown (voir inc/security.php).
 * URL : /security/emergency_unlock.php?token=<token>
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

// Mêmes identifiants que le socle (surcharge par l'environnement, repli local Laragon).
$DB_HOST = getenv('V14_DB_HOST') ?: '127.0.0.1';
$DB_PORT = (int)(getenv('V14_DB_PORT') ?: 3306);
$DB_NAME = getenv('V14_DB_NAME') ?: 'v14';
$DB_USER = getenv('V14_DB_USER') ?: 'root';
$DB_PASS = getenv('V14_DB_PASS') !== false ? getenv('V14_DB_PASS') : '';

$token = preg_replace('/[^a-f0-9]/i', '', (string)($_GET['token'] ?? ''));
$success = false;
$error = '';

if (strlen($token) < 32) {
    http_response_code(403);
    $error = 'Token invalide ou manquant.';
} else {
    try {
        $pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $st = $pdo->prepare("SELECT id FROM security_lockdown WHERE token_urgence=? AND actif=1");
        $st->execute([$token]);
        $row = $st->fetch();
        if (!$row) {
            http_response_code(403);
            $error = 'Token invalide ou lockdown déjà levé.';
        } else {
            $pdo->prepare("UPDATE security_lockdown SET actif=0, debloque_le=NOW(), debloque_par=NULL WHERE id=?")->execute([(int)$row['id']]);
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $pdo->prepare("INSERT INTO security_events (type,ip,details) VALUES ('unlock',?,'Déblocage urgence via token')")->execute([$ip]);
            $success = true;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        $error = 'Erreur serveur.';
    }
}
$H = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Habbo — Déblocage d'urgence</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,Segoe UI,Roboto,system-ui,sans-serif;background:#030508;color:#f0fff8;min-height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:20px}
.bg{position:fixed;inset:0;z-index:0;pointer-events:none;background:radial-gradient(ellipse 600px 500px at 50% 60%,<?= $success ? 'rgba(16,185,129,.08)' : 'rgba(239,68,68,.06)' ?>,transparent 70%)}
.wrap{position:relative;z-index:1;width:100%;max-width:420px;text-align:center;animation:ap .6s ease both}
@keyframes ap{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.core{width:100px;height:100px;margin:0 auto 1.6rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.8rem;border:2px solid <?= $success ? 'rgba(16,185,129,.5)' : 'rgba(239,68,68,.4)' ?>;background:radial-gradient(circle,<?= $success ? 'rgba(16,185,129,.2)' : 'rgba(239,68,68,.12)' ?>,transparent);box-shadow:0 0 40px <?= $success ? 'rgba(16,185,129,.3)' : 'rgba(239,68,68,.2)' ?>}
h1{font-size:1.5rem;font-weight:800;margin-bottom:.6rem;color:<?= $success ? '#34d399' : '#f87171' ?>}
p{color:rgba(240,255,248,.5);font-size:.9rem;line-height:1.6;margin-bottom:1.8rem}
.card{background:rgba(255,255,255,.03);border:1px solid <?= $success ? 'rgba(16,185,129,.2)' : 'rgba(239,68,68,.2)' ?>;border-radius:16px;padding:1.2rem 1.6rem;margin-bottom:1.6rem}
.row{display:flex;justify-content:space-between;font-size:.8rem;padding:.5rem 0;border-bottom:1px solid rgba(255,255,255,.05)}
.row:last-child{border:none}.row .l{color:rgba(240,255,248,.45)}.row .v{font-weight:600;font-family:monospace}
.btn{display:inline-block;padding:.75rem 1.6rem;border-radius:12px;text-decoration:none;font-weight:700;font-size:.9rem;<?= $success ? 'background:#10b981;color:#fff' : 'background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)' ?>}
.foot{margin-top:1.4rem;font-size:.7rem;color:rgba(255,255,255,.15);letter-spacing:1.5px;text-transform:uppercase}
</style></head><body>
<div class="bg"></div>
<div class="wrap">
  <div class="core"><?= $success ? '✓' : '✕' ?></div>
  <?php if ($success): ?>
  <h1>Site déverrouillé</h1>
  <p>Le mode protection a été levé. Le site est de nouveau accessible à tous.</p>
  <div class="card">
    <div class="row"><span class="l">Statut</span><span class="v" style="color:#34d399">✓ Lockdown levé</span></div>
    <div class="row"><span class="l">Heure</span><span class="v"><?= $H(date('d/m/Y H:i:s')) ?></span></div>
    <div class="row"><span class="l">IP</span><span class="v"><?= $H($_SERVER['REMOTE_ADDR'] ?? '—') ?></span></div>
  </div>
  <a href="/" class="btn">→ Retour au site</a>
  <?php else: ?>
  <h1>Échec</h1>
  <p><?= $H($error ?: 'Token invalide.') ?></p>
  <div class="card">
    <div class="row"><span class="l">Statut</span><span class="v" style="color:#f87171">✕ Échec</span></div>
    <div class="row"><span class="l">Heure</span><span class="v"><?= $H(date('d/m/Y H:i:s')) ?></span></div>
    <div class="row"><span class="l">IP</span><span class="v"><?= $H($_SERVER['REMOTE_ADDR'] ?? '—') ?></span></div>
  </div>
  <a href="/" class="btn">Retour au site</a>
  <?php endif; ?>
  <div class="foot">Habbo · Sécurité · <?= date('Y') ?></div>
</div>
</body></html>
