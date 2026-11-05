-- Journal des mouvements de crédits (porte-monnaie / dialogue « Transactions du compte »).
-- Alimenté par l'ému (CurrencyDao -> CreditLogDao) ET par l'admin (dons de crédits).
-- Lu par le client via le paquet 209 (USER_CREDIT_LOG).
-- SQL standard (compatible MySQL 8.4 / MariaDB 11.4). L'admin et l'ému créent aussi
-- la table au besoin (CREATE TABLE IF NOT EXISTS), ce fichier reste la trace officielle.
CREATE TABLE IF NOT EXISTS credit_logs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  credit_value INT NOT NULL DEFAULT 0,            -- delta signé : + reçu, - dépensé
  real_value  VARCHAR(16) NOT NULL DEFAULT '0',   -- valeur en argent réel (colonne VALEUR)
  currency    VARCHAR(16) NOT NULL DEFAULT '',
  system_name VARCHAR(32) NOT NULL DEFAULT 'web_internal', -- clé d'activité (transaction_system_*)
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
