-- ============================================================================
-- Projecteur disco multicolore pour HabboretroV14
--   party_discol  → « Spotlight »          (cône de lumière, 4 couleurs)
--   party_beamer  → « Dance Floor Beamer »  (faisceaux, 7 états)
-- Crée la DEFINITION du mobi (items_definitions) + l'entrée CATALOGUE
-- (page 202 « Divers », achetable par tous). Valeurs reprises du dump officiel
-- update.1.17.sql. Idempotent (DELETE puis INSERT).
--
-- ⚠️ PRÉREQUIS GRAPHISME : le client télécharge hof_furni/hh_furni_xx_%typeid%.cct.
--    Il faut donc déposer dans Server/www/dcr/hof_furni/ :
--       hh_furni_xx_party_discol.cct   (+ hh_furni_xx_s_party_discol.cct  = icône)
--       hh_furni_xx_party_beamer.cct   (+ hh_furni_xx_s_party_beamer.cct)
--    → via l'outil admin  ?p=convert  (révision officielle : party_discol = 13603).
--    Sans le .cct, le mobi apparaît en CUBE.
--
-- ⚠️ Après avoir joué cette migration : REDÉMARRER l'émulateur Kepler
--    (les définitions + le catalogue sont chargés au boot).
--
-- Comportement couleurs : behaviour 'requires_rights_for_interaction' + max_status
--    (4 / 7) → le propriétaire (ou un rôle avec droits) CLIQUE le mobi pour faire
--    défiler les états = les couleurs.
-- ============================================================================

DELETE FROM catalogue_items   WHERE sale_code IN ('party_discol', 'party_beamer');
DELETE FROM items_definitions WHERE sprite    IN ('party_discol', 'party_beamer');

INSERT INTO items_definitions
  (id, sprite, sprite_id, name, description, colour, length, width, top_height, max_status, behaviour, interactor, is_tradable, is_recyclable, drink_ids)
VALUES
  (2268, 'party_discol', 1447, 'Spotlight',          'Focus your attention or dance within its glow!', '0,0,0', 1, 1, 0, '4', 'solid,requires_rights_for_interaction', 'default', 1, 1, ''),
  (2269, 'party_beamer', 1443, 'Dance Floor Beamer', 'Get some lights to match your dancing shapes!',  '0,0,0', 1, 1, 0, '7', 'solid,requires_rights_for_interaction', 'default', 1, 1, '');

INSERT INTO catalogue_items
  (id, sale_code, page_id, order_id, price, is_hidden, amount, definition_id, item_specialspriteid, name, description, is_package, package_name, package_description)
VALUES
  (2287, 'party_discol', '202', 50, 2, 0, 1, 2268, 0, 'Spotlight (projecteur disco)', 'Projecteur qui change de couleur — clique dessus pour faire defiler les couleurs.', 0, NULL, NULL),
  (2288, 'party_beamer', '202', 51, 4, 0, 1, 2269, 0, 'Dance Floor Beamer',           'Faisceaux lumineux pour la piste de danse.',                                      0, NULL, NULL);
