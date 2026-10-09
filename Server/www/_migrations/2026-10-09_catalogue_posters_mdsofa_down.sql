-- Rollback ciblé (supprime UNIQUEMENT les ajouts de cette migration ; ne touche pas aux autres lignes).

DELETE FROM catalogue_pages WHERE id=214;
DELETE FROM catalogue_pages WHERE id=215;
DELETE FROM items_definitions WHERE id=2267 AND sprite='md_sofa';
DELETE FROM catalogue_items WHERE id=2266;
DELETE FROM catalogue_items WHERE id=2267;
DELETE FROM catalogue_items WHERE id=2268;
DELETE FROM catalogue_items WHERE id=2269;
DELETE FROM catalogue_items WHERE id=2270;
DELETE FROM catalogue_items WHERE id=2271;
DELETE FROM catalogue_items WHERE id=2272;
DELETE FROM catalogue_items WHERE id=2273;
DELETE FROM catalogue_items WHERE id=2274;
DELETE FROM catalogue_items WHERE id=2275;
DELETE FROM catalogue_items WHERE id=2276;
DELETE FROM catalogue_items WHERE id=2277;
DELETE FROM catalogue_items WHERE id=2278;
DELETE FROM catalogue_items WHERE id=2279;
DELETE FROM catalogue_items WHERE id=2280;
DELETE FROM catalogue_items WHERE id=2281;
DELETE FROM catalogue_items WHERE id=2282;
DELETE FROM catalogue_items WHERE id=2283;
DELETE FROM catalogue_items WHERE id=2284;
DELETE FROM catalogue_items WHERE id=2285;
DELETE FROM catalogue_items WHERE id=2286;
DELETE FROM catalogue_items WHERE id=2287;
-- (Traductions de noms non annulées : cosmétique, non destructif.)
