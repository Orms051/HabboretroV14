-- ============================================================================
-- Sakura (exe_plant, def #736) — retirer le flag « custom_data_numeric_state »
-- parasite qui faisait PLANTER le client (inventaire bloqué + erreur à la pose).
--
-- Diagnostic : toutes les autres plantes décoratives (plant_yukka, plant_bonsai,
-- plant_rose, plant_sunflower, plant_pineapple) ont le behaviour
-- 'solid,requires_rights_for_interaction'. Seul exe_plant avait en plus
-- 'custom_data_numeric_state', qui le fait traiter comme un meuble « à données
-- d'état » : le client attend un custom_data numérique et plante s'il est vide.
-- Le meta.json officiel (habboassets, build 376) confirme : exe_plant a
-- "clientScript":null = plante SIMPLE à 2 états, pas un meuble scripté.
--
-- On aligne donc exe_plant sur les autres plantes.
--
-- ⚠️ Redémarrer l'émulateur après (les définitions sont chargées au boot).
-- NB : côté LOCAL, l'exemplaire cassé d'Orms a été supprimé puis recréé propre
--      (custom_data vide). En prod, après cette migration + redémarrage ému,
--      les exemplaires existants fonctionneront aussi (le custom_data est
--      simplement ignoré une fois le flag numeric_state retiré).
-- ============================================================================

UPDATE items_definitions
   SET behaviour = 'solid,requires_rights_for_interaction'
 WHERE sprite = 'exe_plant';
