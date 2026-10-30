<?php
/**
 * Modèle de conversation joueur ↔ staff — PARTAGÉ entre le site (v2) et l'admin.
 * Additif et non destructif : n'altère ni ne supprime les anciennes tables
 * (contact_messages, user_messages). Les anciens échanges sont repris à la demande
 * (conv_reprise) sans doublons et sans inventer de liens entre messages indépendants.
 *
 * Utilise db() (défini dans chaque contexte : v2/inc/boot.php et admin/index.php).
 */

/** Ajoute une colonne si absente (portable MySQL 8 / MariaDB, sans IF NOT EXISTS). */
function conv_ensure_col(string $table, string $col, string $ddl): void
{
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $st->execute([$table, $col]);
        if ((int)$st->fetchColumn() === 0) db()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
    } catch (Throwable $e) {}
}

function ensure_conversations(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_id INT NOT NULL DEFAULT 0,
            subject VARCHAR(150) NOT NULL DEFAULT '',
            category VARCHAR(40) NOT NULL DEFAULT 'question',
            status VARCHAR(16) NOT NULL DEFAULT 'waiting_staff',
            assigned_to VARCHAR(64) NOT NULL DEFAULT '',
            player_archived TINYINT NOT NULL DEFAULT 0,
            player_trashed TINYINT NOT NULL DEFAULT 0,
            player_deleted TINYINT NOT NULL DEFAULT 0,
            staff_archived TINYINT NOT NULL DEFAULT 0,
            staff_trashed TINYINT NOT NULL DEFAULT 0,
            legacy_ref VARCHAR(24) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(player_id), INDEX(status), UNIQUE KEY uniq_legacy (legacy_ref)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS conversation_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conv_id INT NOT NULL,
            sender VARCHAR(8) NOT NULL DEFAULT 'player',
            sender_name VARCHAR(64) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            is_internal TINYINT NOT NULL DEFAULT 0,
            read_player TINYINT NOT NULL DEFAULT 0,
            read_staff TINYINT NOT NULL DEFAULT 0,
            hidden_player TINYINT NOT NULL DEFAULT 0,
            moderated TINYINT NOT NULL DEFAULT 0,
            moderated_by VARCHAR(64) NOT NULL DEFAULT '',
            moderated_at DATETIME NULL,
            moderated_reason VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(conv_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS conv_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conv_id INT NOT NULL,
            actor VARCHAR(64) NOT NULL DEFAULT '',
            action VARCHAR(40) NOT NULL DEFAULT '',
            detail VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(conv_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Montées de version idempotentes (bases créées avant ces colonnes)
        conv_ensure_col('conversations', 'player_deleted', 'TINYINT NOT NULL DEFAULT 0');
        conv_ensure_col('conversations', 'staff_archived', 'TINYINT NOT NULL DEFAULT 0');
        conv_ensure_col('conversations', 'staff_trashed', 'TINYINT NOT NULL DEFAULT 0');
        conv_ensure_col('conversation_messages', 'hidden_player', 'TINYINT NOT NULL DEFAULT 0');
        conv_ensure_col('conversation_messages', 'moderated', 'TINYINT NOT NULL DEFAULT 0');
        conv_ensure_col('conversation_messages', 'moderated_by', "VARCHAR(64) NOT NULL DEFAULT ''");
        conv_ensure_col('conversation_messages', 'moderated_at', 'DATETIME NULL');
        conv_ensure_col('conversation_messages', 'moderated_reason', "VARCHAR(255) NOT NULL DEFAULT ''");
    } catch (Throwable $e) {}
}

/** Journalise une action sur une conversation (historique du dossier). */
function conv_event_log(int $convId, string $actor, string $action, string $detail = ''): void
{
    if ($convId <= 0) return;
    try { db()->prepare('INSERT INTO conv_events (conv_id,actor,action,detail) VALUES (?,?,?,?)')
        ->execute([$convId, mb_substr($actor, 0, 64), mb_substr($action, 0, 40), mb_substr($detail, 0, 255)]); } catch (Throwable $e) {}
}

/** Événements d'une conversation (plus récents d'abord). */
function conv_events(int $convId, int $limit = 50): array
{
    try { $st = db()->prepare('SELECT * FROM conv_events WHERE conv_id=? ORDER BY id DESC LIMIT ' . (int)$limit); $st->execute([$convId]); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}

/** Crée une conversation + son 1er message. Retourne l'id (0 si échec). */
function conv_create(int $playerId, string $subject, string $category, string $sender, string $senderName, string $body): int
{
    ensure_conversations();
    $body = trim($body);
    if ($body === '') return 0;
    try {
        // C01 : conversation + 1er message dans une MÊME transaction. Si le 1er message
        // échoue, on annule tout (pas de conversation orpheline ni de faux succès).
        // (ensure_conversations() a sa garde statique -> aucun DDL dans la transaction.)
        db()->beginTransaction();
        $status = ($sender === 'player') ? 'waiting_staff' : 'waiting_player';
        $st = db()->prepare('INSERT INTO conversations (player_id,subject,category,status) VALUES (?,?,?,?)');
        $st->execute([$playerId, mb_substr(trim($subject), 0, 150), mb_substr(trim($category), 0, 40), $status]);
        $cid = (int)db()->lastInsertId();
        if (!conv_add_message($cid, $sender, $senderName, $body, 0)) {
            db()->rollBack();
            return 0;
        }
        db()->commit();
        return $cid;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        return 0;
    }
}

/** C05 : anti-spam SERVEUR. Vrai si le joueur a déjà envoyé un message il y a moins de
 *  $cooldown secondes (création OU réponse) — à vérifier avant d'enregistrer. Le bouton
 *  désactivé côté client ne suffit pas (contournable). */
function conv_rate_limited(int $playerId, int $cooldown = 12): bool
{
    if ($playerId <= 0) return false;
    ensure_conversations();
    try {
        // Ancienneté calculée côté SQL (TIMESTAMPDIFF) : robuste à tout décalage de fuseau PHP/MySQL.
        $st = db()->prepare("SELECT TIMESTAMPDIFF(SECOND, MAX(m.created_at), NOW()) FROM conversation_messages m JOIN conversations c ON c.id=m.conv_id WHERE c.player_id=? AND m.sender='player'");
        $st->execute([$playerId]);
        $age = $st->fetchColumn();
        return $age !== null && $age !== false && (int)$age < $cooldown; // pas de message -> NULL -> non limité
    } catch (Throwable $e) { return false; }
}

/** Ajoute un message à une conversation ; met à jour le statut (sauf note interne). */
function conv_add_message(int $convId, string $sender, string $senderName, string $body, int $isInternal = 0): bool
{
    ensure_conversations();
    $body = trim($body);
    if ($convId <= 0 || $body === '') return false;
    $sender = in_array($sender, ['player', 'staff', 'system'], true) ? $sender : 'player';
    try {
        db()->prepare('INSERT INTO conversation_messages (conv_id,sender,sender_name,body,is_internal,read_player,read_staff) VALUES (?,?,?,?,?,?,?)')
            ->execute([$convId, $sender, mb_substr(trim($senderName), 0, 64), mb_substr($body, 0, 4000), $isInternal ? 1 : 0, $sender === 'player' ? 1 : 0, ($sender === 'staff' || $sender === 'system') ? 1 : 0]);
        if ($isInternal) {
            // Une note interne ne change ni le statut ni les boîtes du joueur.
            db()->prepare('UPDATE conversations SET updated_at=NOW() WHERE id=?')->execute([$convId]);
        } else {
            // Un message public (re)met la conversation dans la file de l'autre partie
            // et la fait ressortir de l'archive / corbeille du joueur, quel que soit l'expéditeur.
            $newStatus = ($sender === 'player') ? 'waiting_staff' : 'waiting_player';
            if ($sender === 'player') {
                // C02 : une réponse du joueur doit AUSSI ressortir des dossiers écartés côté
                // staff (archive/corbeille) et revenir dans la réception « assistance »
                // (filtre admin : staff_trashed=0 AND staff_archived=0 AND status<>'resolved').
                // Sinon la réponse reste invisible dans un dossier que le staff avait rangé.
                db()->prepare('UPDATE conversations SET status=?, updated_at=NOW(), player_trashed=0, player_archived=0, player_deleted=0, staff_archived=0, staff_trashed=0 WHERE id=?')
                    ->execute([$newStatus, $convId]);
            } else {
                // Message staff/système -> revient dans la boîte du joueur (comportement existant).
                db()->prepare('UPDATE conversations SET status=?, updated_at=NOW(), player_trashed=0, player_archived=0, player_deleted=0 WHERE id=?')
                    ->execute([$newStatus, $convId]);
            }
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Ajoute un message « système » (accusé de réception, info automatique) SANS
 * changer le statut de la conversation ni les boîtes du joueur. Déjà marqué lu
 * des deux côtés (purement informatif). Idempotent par conversation via $once.
 */
function conv_system_notice(int $convId, string $body, bool $once = true): bool
{
    ensure_conversations();
    $body = trim($body);
    if ($convId <= 0 || $body === '') return false;
    try {
        if ($once) {
            $chk = db()->prepare("SELECT 1 FROM conversation_messages WHERE conv_id=? AND sender='system' LIMIT 1");
            $chk->execute([$convId]);
            if ($chk->fetchColumn()) return false;
        }
        db()->prepare("INSERT INTO conversation_messages (conv_id,sender,sender_name,body,is_internal,read_player,read_staff) VALUES (?,?,?,?,0,1,1)")
            ->execute([$convId, 'system', 'Hôtel', mb_substr($body, 0, 4000)]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Marque les messages d'une conversation comme lus pour le joueur (à l'ouverture). */
function conv_mark_read_player(int $convId, int $playerId): void
{
    // C05 : le helper vérifie lui-même la propriété (player_id) au lieu d'ignorer $playerId —
    // un (convId, playerId) incohérent ne peut plus marquer « lus » les messages d'autrui.
    try { db()->prepare("UPDATE conversation_messages m JOIN conversations c ON c.id=m.conv_id SET m.read_player=1 WHERE m.conv_id=? AND c.player_id=? AND m.is_internal=0 AND m.sender<>'player' AND m.read_player=0")->execute([$convId, $playerId]); } catch (Throwable $e) {}
}

/** Marque les messages d'une conversation comme lus pour le staff (à l'ouverture). */
function conv_mark_read_staff(int $convId): void
{
    try { db()->prepare("UPDATE conversation_messages SET read_staff=1 WHERE conv_id=? AND sender='player' AND read_staff=0")->execute([$convId]); } catch (Throwable $e) {}
}

/** Nombre de conversations non lues côté joueur (hors corbeille). */
function conv_unread_player(int $playerId): int
{
    ensure_conversations();
    try {
        $st = db()->prepare("SELECT COUNT(DISTINCT c.id) FROM conversations c JOIN conversation_messages m ON m.conv_id=c.id WHERE c.player_id=? AND c.player_trashed=0 AND c.player_deleted=0 AND m.is_internal=0 AND m.sender<>'player' AND m.read_player=0 AND m.hidden_player=0");
        $st->execute([$playerId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Nombre de conversations avec un message joueur non lu par le staff, dans la file
 *  « assistance ». C02 : MÊME règle que la vue assistance (staff_trashed=0 AND
 *  staff_archived=0 AND status<>'resolved') pour que le badge ne compte pas des
 *  conversations rangées/résolues invisibles dans la file -> badge == contenu réel. */
function conv_unread_staff(): int
{
    ensure_conversations();
    try {
        return (int)db()->query("SELECT COUNT(DISTINCT c.id) FROM conversations c JOIN conversation_messages m ON m.conv_id=c.id WHERE m.sender='player' AND m.read_staff=0 AND c.staff_trashed=0 AND c.staff_archived=0 AND c.status<>'resolved'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Les messages d'une conversation (triés). $includeInternal=false masque les notes staff (côté joueur). */
function conv_messages(int $convId, bool $includeInternal = true): array
{
    ensure_conversations();
    try {
        // Côté joueur (includeInternal=false) : on masque aussi les messages qu'il a retirés de sa vue.
        // C04 : tri chronologique réel (created_at, puis id en départage) — un message importé/fusionné
        // peut porter une date ancienne ; trier par id seul le placerait à tort en fin de fil.
        $sql = 'SELECT * FROM conversation_messages WHERE conv_id=?' . ($includeInternal ? '' : ' AND is_internal=0 AND hidden_player=0') . ' ORDER BY created_at ASC, id ASC';
        $st = db()->prepare($sql); $st->execute([$convId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Annonce collective : crée une conversation (staff → joueur) pour chaque id donné,
 * avec le même sujet + corps. Retourne le nombre de conversations créées.
 * Limite dure de sécurité ($max) pour éviter un envoi massif accidentel.
 */
function conv_broadcast(array $playerIds, string $subject, string $body, string $senderName, int $max = 500): int
{
    ensure_conversations();
    $body = trim($body);
    if ($body === '') return 0;
    $ids = array_values(array_unique(array_filter(array_map('intval', $playerIds), fn($v) => $v > 0)));
    if (!$ids || count($ids) > $max) return 0;
    $subject = mb_substr(trim($subject), 0, 150); if ($subject === '') $subject = 'Message de l\'équipe';
    $n = 0;
    foreach ($ids as $pid) {
        if (conv_create($pid, $subject, 'staff', 'staff', $senderName, $body)) $n++;
    }
    return $n;
}

/**
 * Fusion de deux conversations du MÊME joueur : déplace les messages ET l'historique
 * (conv_events) de $source vers $target, puis supprime la source — le tout dans une
 * TRANSACTION (C04) pour éviter un état partiel ou une conversation orpheline si une
 * étape échoue. L'ordre chronologique est assuré à l'affichage par conv_messages
 * (tri created_at, id). Refus si joueurs différents, ids identiques, ou player_id=0.
 * Retourne true si la fusion a eu lieu.
 */
function conv_merge(int $target, int $source): bool
{
    ensure_conversations();
    if ($target <= 0 || $source <= 0 || $target === $source) return false;
    try {
        $st = db()->prepare('SELECT id,player_id FROM conversations WHERE id IN (?,?)');
        $st->execute([$target, $source]);
        $rows = $st->fetchAll();
        if (count($rows) !== 2) return false;
        $byId = []; foreach ($rows as $r) $byId[(int)$r['id']] = (int)$r['player_id'];
        if (!isset($byId[$target], $byId[$source])) return false;
        if ($byId[$target] === 0 || $byId[$source] === 0 || $byId[$target] !== $byId[$source]) return false;
        db()->beginTransaction();
        db()->prepare('UPDATE conversation_messages SET conv_id=? WHERE conv_id=?')->execute([$target, $source]);
        db()->prepare('UPDATE conv_events SET conv_id=? WHERE conv_id=?')->execute([$target, $source]); // historique conservé
        db()->prepare('UPDATE conversations SET updated_at=NOW() WHERE id=?')->execute([$target]);
        db()->prepare('DELETE FROM conversations WHERE id=?')->execute([$source]);
        db()->commit();
        return true;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        return false;
    }
}

/** Joueur : retire UN message de sa propre vue (sans l'effacer chez le staff). */
function conv_hide_msg_player(int $msgId, int $playerId): bool
{
    if ($msgId <= 0 || $playerId <= 0) return false;
    try {
        $st = db()->prepare('UPDATE conversation_messages m JOIN conversations c ON c.id=m.conv_id SET m.hidden_player=1 WHERE m.id=? AND c.player_id=?');
        $st->execute([$msgId, $playerId]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) { return false; }
}

/** Joueur : supprime définitivement la conversation de SA boîte (le dossier staff reste intact). */
function conv_purge_player(int $convId, int $playerId): bool
{
    if ($convId <= 0 || $playerId <= 0) return false;
    try {
        // Vérifie la propriété
        $chk = db()->prepare('SELECT 1 FROM conversations WHERE id=? AND player_id=?'); $chk->execute([$convId, $playerId]);
        if (!$chk->fetchColumn()) return false;
        // Cache tous les messages pour le joueur + marque la conversation supprimée de sa boîte
        db()->prepare('UPDATE conversation_messages SET hidden_player=1 WHERE conv_id=?')->execute([$convId]);
        db()->prepare('UPDATE conversations SET player_deleted=1, player_trashed=0, player_archived=0 WHERE id=?')->execute([$convId]);
        return true;
    } catch (Throwable $e) { return false; }
}

/** Staff : masque un message aux participants (motif + auteur + date conservés). */
function conv_moderate_msg(int $msgId, string $by, string $reason, bool $on = true): bool
{
    if ($msgId <= 0) return false;
    try {
        if ($on) db()->prepare('UPDATE conversation_messages SET moderated=1, moderated_by=?, moderated_at=NOW(), moderated_reason=? WHERE id=?')
            ->execute([mb_substr($by, 0, 64), mb_substr($reason, 0, 255), $msgId]);
        else db()->prepare('UPDATE conversation_messages SET moderated=0 WHERE id=?')->execute([$msgId]);
        return true;
    } catch (Throwable $e) { return false; }
}

/**
 * Reprise des anciens messages dans le modèle conversation — IDEMPOTENTE (legacy_ref unique),
 * SANS doublons, SANS inventer de liens : chaque ancien message devient sa propre conversation.
 * Ne supprime rien : les tables d'origine restent intactes. Retourne le nombre de reprises.
 */
function conv_reprise(): int
{
    ensure_conversations();
    $n = 0;
    // contact_messages (joueur -> staff)
    try {
        foreach (db()->query("SELECT cm.id,cm.username,cm.subject,cm.message,cm.status,cm.created_at,u.id AS uid FROM contact_messages cm LEFT JOIN users u ON u.username=cm.username") as $m) {
            $ref = 'cm:' . (int)$m['id'];
            $chk = db()->prepare('SELECT id FROM conversations WHERE legacy_ref=?'); $chk->execute([$ref]);
            if ($chk->fetchColumn()) continue;
            $status = ((string)$m['status'] === 'handled') ? 'resolved' : 'waiting_staff';
            $subj = trim((string)$m['subject']) !== '' ? (string)$m['subject'] : 'Demande à l\'équipe';
            db()->prepare('INSERT INTO conversations (player_id,subject,category,status,legacy_ref,created_at,updated_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([(int)($m['uid'] ?? 0), mb_substr($subj, 0, 150), 'contact', $status, $ref, (string)$m['created_at'], (string)$m['created_at']]);
            $cid = (int)db()->lastInsertId();
            db()->prepare('INSERT INTO conversation_messages (conv_id,sender,sender_name,body,read_player,read_staff,created_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$cid, 'player', (string)($m['username'] ?? ''), (string)$m['message'], 1, $status === 'resolved' ? 1 : 0, (string)$m['created_at']]);
            $n++;
        }
    } catch (Throwable $e) {}
    // user_messages (staff -> joueur)
    try {
        foreach (db()->query("SELECT id,user_id,from_staff,subject,body,read_at,created_at FROM user_messages") as $m) {
            $ref = 'um:' . (int)$m['id'];
            $chk = db()->prepare('SELECT id FROM conversations WHERE legacy_ref=?'); $chk->execute([$ref]);
            if ($chk->fetchColumn()) continue;
            $subj = trim((string)$m['subject']) !== '' ? (string)$m['subject'] : 'Message de l\'équipe';
            db()->prepare('INSERT INTO conversations (player_id,subject,category,status,legacy_ref,created_at,updated_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([(int)$m['user_id'], mb_substr($subj, 0, 150), 'staff', 'waiting_player', $ref, (string)$m['created_at'], (string)$m['created_at']]);
            $cid = (int)db()->lastInsertId();
            db()->prepare('INSERT INTO conversation_messages (conv_id,sender,sender_name,body,read_player,read_staff,created_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$cid, 'staff', (string)($m['from_staff'] ?? 'Équipe'), (string)$m['body'], !empty($m['read_at']) ? 1 : 0, 1, (string)$m['created_at']]);
            $n++;
        }
    } catch (Throwable $e) {}
    return $n;
}
