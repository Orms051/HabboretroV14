-- Apparence (vêtements & couleurs) — étape 2 : brouillons d'édition.
-- Un brouillon par admin : COPIE DE TRAVAIL du texte figuredata.txt sur
-- laquelle on applique ajout / remplacement / retrait / réordonnancement de
-- couleurs AVANT publication. figuredata.txt LIVE n'est jamais écrit à ce stade.
-- Additif, aucun impact sur les tables de jeu. Également auto-créée à la volée
-- par looks_ensure_draft_table() (CREATE TABLE IF NOT EXISTS).
CREATE TABLE IF NOT EXISTS looks_drafts (
  id         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT NOT NULL,
  data       LONGTEXT NOT NULL,
  note       VARCHAR(190) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
