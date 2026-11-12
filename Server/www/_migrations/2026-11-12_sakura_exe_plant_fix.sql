-- ============================================================================
-- Sakura (exe_plant, def #736) — correctif « inventaire bloqué + erreur ».
--
-- Cause RÉELLE : la définition est un meuble À ÉTATS
-- ('solid,custom_data_numeric_state,requires_rights_for_interaction' — clic =
-- basculer l'aspect de la plante, 2 états, exactement comme exe_artlamp). Cette
-- définition est CORRECTE et d'origine, on n'y touche pas.
-- Le bug venait de l'EXEMPLAIRE dans l'inventaire : son items.custom_data était
-- VIDE. Un meuble à états avec un custom_data vide fait planter le client au
-- chargement de l'inventaire (→ inventaire bloqué + erreur à la pose).
--
-- Fix : donner l'état initial '0' (valide, jamais vide) à tout exemplaire de
-- Sakura dont le custom_data est vide.
--
-- NB : aucune définition modifiée ici → une simple RECONNEXION du joueur suffit
--      (l'ému relit l'inventaire au login). Pas besoin de redémarrer l'ému.
-- ============================================================================

UPDATE items
   SET custom_data = '0'
 WHERE definition_id = (SELECT id FROM items_definitions WHERE sprite = 'exe_plant')
   AND (custom_data = '' OR custom_data IS NULL);
