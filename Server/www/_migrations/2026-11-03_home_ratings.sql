-- Migration : widget « My Rating » (Ma note) des Habbo Homes.
-- Une ligne = la note (1-5 étoiles) d'un visiteur sur la Home d'un joueur.
-- Idempotent, compatible MySQL 8 et MariaDB. Les installations neuves créent
-- déjà cette table via ensure_home() (v2/inc/boot.php) ; ceci met à niveau l'existant.

CREATE TABLE IF NOT EXISTS home_ratings (
    owner_id INT NOT NULL,
    voter_id INT NOT NULL,
    stars TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (owner_id, voter_id),
    INDEX(owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rollback (manuel, si besoin) :
--   DROP TABLE IF EXISTS home_ratings;
