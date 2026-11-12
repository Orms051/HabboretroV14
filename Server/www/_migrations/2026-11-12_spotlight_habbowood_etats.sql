-- ============================================================================
-- Habbowood Spotlight (sprite 'spotlight') — débrider ses 6 états (couleurs).
--
-- Le meuble EXISTE DÉJÀ : def #2095 + catalogue #2094 (page 207 « Habbowood »,
-- « Projecteur Habbowood », 5 crédits) ET son graphisme est présent
-- (hh_furni_xx_spotlight.cct + hh_furni_xx_s_spotlight.cct) → pas de cube.
--
-- Problème : sa définition est bridée en ON/OFF (behaviour
-- 'custom_data_numeric_on_off' = le serveur n'accepte que les états 0/1/2) et
-- mal nommée (« Pack TV 2 »). Or le vrai meuble a 6 ÉTATS (cf. habboassets,
-- cast hh_furni_special). On passe donc en 'custom_data_numeric_state' : le
-- serveur accepte 0..99, donc le client peut faire défiler les 6 états (couleurs)
-- au clic (droits requis conservés).
--
-- ⚠️ Redémarrer l'émulateur après (définitions chargées au boot).
-- Les états sont pilotés CLIENT (sprite déjà présent) ; on ne touche qu'au serveur.
-- ============================================================================

UPDATE items_definitions
   SET name       = 'Projecteur Habbowood',
       behaviour  = 'solid,custom_data_numeric_state,requires_rights_for_interaction',
       max_status = '6'
 WHERE sprite = 'spotlight';
