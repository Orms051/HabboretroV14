-- Apparence — étape 4 : publication sécurisée + snapshots + restauration.
-- Table d'historique des versions. Chaque PUBLICATION et chaque RESTAURATION
-- capture l'état LIVE COMPLET avant écriture :
--   fig_gamedata / fig_dcr : contenu des 2 copies de figuredata.txt
--   hc_default / hc_club    : valeurs des 2 réglages HC `settings`
--                             (NULL = la ligne settings était ABSENTE au moment
--                              du snapshot → restaurer = supprimer la ligne, pour
--                              revenir aux défauts codés dans kepler.jar)
-- Permet de revenir en arrière à tout moment. Rien n'est jamais supprimé.
-- Également auto-créée à la volée par looks_ensure_snapshot_table().
CREATE TABLE IF NOT EXISTS looks_snapshots (
  id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id     INT NOT NULL,
  kind         VARCHAR(20) NOT NULL DEFAULT 'pre_publish', -- pre_publish | pre_restore | manual
  note         VARCHAR(190) NOT NULL DEFAULT '',
  fig_gamedata LONGTEXT NOT NULL,
  fig_dcr      LONGTEXT NOT NULL,
  hc_default   LONGTEXT NULL,
  hc_club      LONGTEXT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
