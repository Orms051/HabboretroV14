-- Migration : carrousels indépendants par page (site_carousel.page + PK composite)
-- Idempotent, compatible MySQL 8 et MariaDB (guard via information_schema + SQL dynamique).
-- Les installations neuves créent déjà le bon schéma via ensure_carousel() ; ceci met à niveau les bases existantes.

SET @has_page := (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_carousel' AND COLUMN_NAME = 'page');

SET @sql := IF(@has_page = 0,
    "ALTER TABLE site_carousel ADD COLUMN page VARCHAR(64) NOT NULL DEFAULT 'home', DROP PRIMARY KEY, ADD PRIMARY KEY (page, slot)",
    "DO 0");
PREPARE _m FROM @sql; EXECUTE _m; DEALLOCATE PREPARE _m;

-- Les lignes existantes (avant migration) deviennent le carrousel de l'accueil.
UPDATE site_carousel SET page = 'home' WHERE page = '' OR page IS NULL;

-- Rollback (manuel, si besoin) :
--   ALTER TABLE site_carousel DROP PRIMARY KEY, ADD PRIMARY KEY (slot), DROP COLUMN page;
--   (ne faire que s'il n'existe qu'un seul carrousel 'home', sinon on perdrait les carrousels des autres pages)
