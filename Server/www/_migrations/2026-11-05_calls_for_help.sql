-- Migration : persistance des Appels à l'aide (Call For Help / CFH) du jeu.
-- Une ligne = un appel soumis par un joueur en jeu. Kepler (émulateur) écrit ici
-- à la soumission, la prise en charge, le changement de catégorie et la clôture ;
-- l'admin lit cette table (file + compteur du bouton d'alerte de l'en-tête).
-- Idempotent, compatible MySQL 8 et MariaDB 11.4. L'admin la crée aussi au besoin
-- via ensure_calls_for_help() ; ceci la provisionne sur la base du jeu (v14).
--
-- ⚠️ Doit exister sur la MÊME base que l'émulateur (mysql.database = v14) pour que
--    Kepler puisse y écrire après recompilation du jar.

CREATE TABLE IF NOT EXISTS calls_for_help (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    caller_id    INT NOT NULL,                              -- joueur qui appelle (users.id)
    room_id      INT NOT NULL DEFAULT 0,                    -- salle au moment de l'appel
    room_name    VARCHAR(255) NOT NULL DEFAULT '',          -- nom de la salle (snapshot)
    message      VARCHAR(1000) NOT NULL DEFAULT '',         -- message de l'appel
    category     INT NOT NULL DEFAULT 2,                    -- catégorie CFH (int client)
    status       ENUM('open','picked','closed') NOT NULL DEFAULT 'open',
    picked_up_by INT NULL,                                  -- modérateur qui a pris l'appel (users.id)
    staff_note   VARCHAR(1000) NOT NULL DEFAULT '',         -- note interne (admin)
    close_reason VARCHAR(255) NOT NULL DEFAULT '',          -- motif de clôture
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    picked_at    DATETIME NULL,
    closed_at    DATETIME NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (status),
    INDEX (caller_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rollback (manuel, si besoin) :
--   DROP TABLE IF EXISTS calls_for_help;
