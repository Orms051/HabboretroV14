-- Rollback ciblé (retire uniquement les offres posters créées).

DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=56 AND sale_code='poster 56';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=21 AND sale_code='poster 21';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=22 AND sale_code='poster 22';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=46 AND sale_code='poster 46';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=47 AND sale_code='poster 47';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=48 AND sale_code='poster 48';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=49 AND sale_code='poster 49';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=1006 AND sale_code='poster 1006';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=25 AND sale_code='poster 25';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=26 AND sale_code='poster 26';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=27 AND sale_code='poster 27';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=28 AND sale_code='poster 28';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=29 AND sale_code='poster 29';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=30 AND sale_code='poster 30';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=23 AND sale_code='poster 23';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=42 AND sale_code='poster 42';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=43 AND sale_code='poster 43';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=45 AND sale_code='poster 45';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=50 AND sale_code='poster 50';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=2005 AND sale_code='poster 2005';
DELETE FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=2008 AND sale_code='poster 2008';
-- Page 'Posters événementiels' : supprimée si devenue vide
DELETE FROM catalogue_pages WHERE name='Posters événementiels' AND NOT EXISTS (SELECT 1 FROM catalogue_items ci WHERE ci.page_id=CAST(catalogue_pages.id AS CHAR));
-- (traductions non annulées : cosmétique)
