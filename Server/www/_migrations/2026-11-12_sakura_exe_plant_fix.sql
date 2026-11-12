-- ============================================================================
-- Sakura (exe_plant, def #736) — « bloqué dans l'inventaire / erreur ».
--
-- Diagnostic (comparaison complète items_definitions + HabboAssets build 376) :
-- la SEULE vraie différence avec une plante qui fonctionne (plant_yukka #75)
-- était le flag 'custom_data_numeric_state' (meuble à états) + top_height 0.
-- HabboAssets confirme : exe_plant a "clientScript":null = plante SIMPLE,
-- PAS un meuble scripté à états. Le flag numeric_state faisait traiter l'item
-- comme un meuble à données d'état que le client rev401 ne sait pas gérer ici
-- → item bloqué dans l'inventaire.
--
-- Correction : aligner exe_plant EXACTEMENT sur plant_yukka (plante décorative).
--   behaviour  = 'solid,requires_rights_for_interaction'
--   top_height = 1
--
-- ⚠️ Redémarrer l'émulateur après (définitions chargées au boot).
-- Les exemplaires existants : custom_data devient inutile (ignoré) ; en local
-- il a été vidé pour coller aux yukka. Aucun autre mobi ni inventaire touché.
-- ============================================================================

UPDATE items_definitions
   SET behaviour  = 'solid,requires_rights_for_interaction',
       top_height = 1
 WHERE sprite = 'exe_plant';
