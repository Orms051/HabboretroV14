-- Apparence — correctif A : détection des modifications LIVE depuis l'ouverture
-- du brouillon + sauvegardes de brouillon + traçabilité des publications forcées.
-- ---------------------------------------------------------------------------
-- IDEMPOTENT & réexécutable sous MariaDB 10.0+/11.8 (ADD COLUMN IF NOT EXISTS et
-- CREATE TABLE IF NOT EXISTS supportés). À jouer MANUELLEMENT (phpMyAdmin
-- o2switch), JAMAIS auto-créé par le code à l'ouverture de l'onglet Apparence.
-- Additif pur : aucune colonne existante modifiée, aucune donnée touchée. Les
-- lignes de brouillon déjà présentes (ex. draft#1 d'Orms) voient leurs colonnes
-- base_* initialisées à NULL = « baseline non rattachée » (détecté et géré par
-- le moteur, qui ne présume alors AUCUNE sécurité).

-- 1) Références de baseline sur le brouillon : empreintes des 2 fichiers
--    figuredata + valeur ET présence des 2 réglages HC au moment de l'ouverture.
ALTER TABLE looks_drafts
  ADD COLUMN IF NOT EXISTS base_fig_gm       CHAR(32)  NULL,  -- md5 gamedata à l'ouverture
  ADD COLUMN IF NOT EXISTS base_fig_dcr      CHAR(32)  NULL,  -- md5 dcr à l'ouverture
  ADD COLUMN IF NOT EXISTS base_hc_def       LONGTEXT  NULL,  -- valeur HC default à l'ouverture
  ADD COLUMN IF NOT EXISTS base_hc_club      LONGTEXT  NULL,  -- valeur HC club à l'ouverture
  ADD COLUMN IF NOT EXISTS base_hc_def_pres  TINYINT   NULL,  -- 1 = ligne settings présente, 0 = absente
  ADD COLUMN IF NOT EXISTS base_hc_club_pres TINYINT   NULL,
  ADD COLUMN IF NOT EXISTS base_captured_at  TIMESTAMP NULL;  -- NULL = baseline non rattachée

-- 2) Sauvegardes INTÉGRALES de brouillon (avant rechargement/réinitialisation).
--    Jamais purgées automatiquement. Permettent une restauration complète.
CREATE TABLE IF NOT EXISTS looks_draft_backups (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  admin_id          INT NOT NULL,
  data              LONGTEXT NOT NULL,       -- copie de travail couleurs
  hc_data           LONGTEXT NULL,           -- copie de travail HC (JSON)
  base_fig_gm       CHAR(32) NULL,
  base_fig_dcr      CHAR(32) NULL,
  base_hc_def       LONGTEXT NULL,
  base_hc_club      LONGTEXT NULL,
  base_hc_def_pres  TINYINT NULL,
  base_hc_club_pres TINYINT NULL,
  base_captured_at  TIMESTAMP NULL,
  draft_created_at  TIMESTAMP NULL,
  draft_updated_at  TIMESTAMP NULL,
  reason            VARCHAR(40) NOT NULL DEFAULT 'manual', -- manual | reseed | pre_restore
  note              VARCHAR(190) NOT NULL DEFAULT '',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Journal de traçabilité des PUBLICATIONS (normales ET forcées).
--    Une publication forcée conserve : état LIVE initial capturé sous verrou,
--    différences baseline<->LIVE détectées, identité de l'admin, confirmation
--    explicite du forçage, snapshot préalable associé et résultat.
CREATE TABLE IF NOT EXISTS looks_publish_log (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  admin_id        INT NOT NULL,
  mode            VARCHAR(12) NOT NULL DEFAULT 'normal', -- normal | force
  live_fig_gm     CHAR(32) NULL,   -- md5 gamedata LIVE au moment de la publication (sous verrou)
  live_fig_dcr    CHAR(32) NULL,
  base_fig_gm     CHAR(32) NULL,   -- rappel de la baseline du brouillon
  base_fig_dcr    CHAR(32) NULL,
  diff_json       LONGTEXT NULL,   -- différences baseline<->LIVE (JSON)
  confirmed       TINYINT NOT NULL DEFAULT 0, -- 1 = forçage explicitement confirmé
  snapshot_id     INT NULL,        -- snapshot pré-publication associé
  result          VARCHAR(20) NOT NULL DEFAULT '', -- ok | refused | conflict | error
  err             VARCHAR(190) NOT NULL DEFAULT '',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
