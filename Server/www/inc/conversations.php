<?php
/**
 * Modèle de conversation joueur ↔ staff — PARTAGÉ entre le site (v2) et l'admin.
 * Additif et non destructif : n'altère ni ne supprime les anciennes tables
 * (contact_messages, user_messages). Les anciens échanges sont repris à la demande
 * (conv_reprise) sans doublons et sans inventer de liens entre messages indépendants.
 *
 * Utilise db() (défini dans chaque contexte : v2/inc/boot.php et admin/index.php).
 */

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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(conv_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

/** Crée une conversation + son 1er message. Retourne l'id (0 si échec). */
function conv_create(int $playerId, string $subject, string $category, string $sender, string $senderName, string $body): int
{
    ensure_conversations();
    $body = trim($body);
    if ($body === '') return 0;
    try {
        $status = ($sender === 'player') ? 'waiting_staff' : 'waiting_player';
        $st = db()->prepare('INSERT INTO conversations (player_id,subject,category,status) VALUES (?,?,?,?)');
        $st->execute([$playerId, mb_substr(trim($subject), 0, 150), mb_substr(trim($category), 0, 40), $status]);
        $cid = (int)db()->lastInsertId();
        conv_add_message($cid, $sender, $senderName, $body, 0);
        return $cid;
    } catch (Throwable $e) {
        return 0;
    }
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
            db()->prepare('UPDATE conversations SET status=?, updated_at=NOW(), player_trashed=0, player_archived=0 WHERE id=?')
                ->execute([$newStatus, $convId]);
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
    try { db()->prepare("UPDATE conversation_messages SET read_player=1 WHERE conv_id=? AND is_internal=0 AND sender<>'player' AND read_player=0")->execute([$convId]); } catch (Throwable $e) {}
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
        $st = db()->prepare("SELECT COUNT(DISTINCT c.id) FROM conversations c JOIN conversation_messages m ON m.conv_id=c.id WHERE c.player_id=? AND c.player_trashed=0 AND m.is_internal=0 AND m.sender<>'player' AND m.read_player=0");
        $st->execute([$playerId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Nombre de conversations avec un message joueur non lu par le staff. */
function conv_unread_staff(): int
{
    ensure_conversations();
    try {
        return (int)db()->query("SELECT COUNT(DISTINCT c.id) FROM conversations c JOIN conversation_messages m ON m.conv_id=c.id WHERE m.sender='player' AND m.read_staff=0")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Les messages d'une conversation (triés). $includeInternal=false masque les notes staff (côté joueur). */
function conv_messages(int $convId, bool $includeInternal = true): array
{
    ensure_conversations();
    try {
        $sql = 'SELECT * FROM conversation_messages WHERE conv_id=?' . ($includeInternal ? '' : ' AND is_internal=0') . ' ORDER BY id ASC';
        $st = db()->prepare($sql); $st->execute([$convId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
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
