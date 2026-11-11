-- Migration : nouveau droit « Mode invisible (Silent) » = fuse_enter_invisible.
-- Permet au staff d'activer/désactiver l'invisibilité en jeu via la commande :invisible.
-- Le même droit sert aussi à VOIR les autres staffs invisibles (surveillance discrète).
--
-- But : accorder fuse_enter_invisible à tous les grades qui possèdent déjà fuse_mod
-- (= le staff, rangs modérateur et plus). L'admin « Gestion du personnel » (?p=staff)
-- peut ensuite l'ajuster grade par grade (ex. le retirer aux CM).
--
-- Idempotent : PRIMARY KEY (rank, fuseright) + INSERT IGNORE => aucun doublon, ré-exécutable.
-- Compatible MySQL 8.x (local) et MariaDB 11.x (prod). Ne touche aucune ligne existante.
--
-- À jouer UNE FOIS sur la base du jeu (v14), PUIS redémarrer l'émulateur (kepler.jar à jour).

INSERT IGNORE INTO rank_fuserights (`rank`, fuseright)
SELECT `rank`, 'fuse_enter_invisible'
FROM   rank_fuserights
WHERE  fuseright = 'fuse_mod';

-- Vérification (optionnelle) :
-- SELECT `rank`, fuseright FROM rank_fuserights
-- WHERE fuseright IN ('fuse_mod','fuse_enter_invisible') ORDER BY `rank`, fuseright;

-- --- ROLLBACK (si besoin) ---
-- 1) D'ABORD restaurer l'ancien kepler.jar et REDÉMARRER l'émulateur.
-- 2) ENFIN nettoyer la base :
-- DELETE FROM rank_fuserights WHERE fuseright = 'fuse_enter_invisible';
