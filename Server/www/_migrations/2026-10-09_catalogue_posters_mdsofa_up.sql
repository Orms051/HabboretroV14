-- =====================================================================
-- Migration catalogue v14 : posters manquants + posters événementiels + md_sofa (test staff)
-- PORTABLE & SÛRE : clés naturelles, ids auto-attribués (pas d'id codé en dur),
-- pages créées par nom (id calculé), rejouable sans doublon. Généré 2026-10-10 05:50
-- Rollback : *_down.sql (FK-sûr : ne supprime pas la déf si un meuble la possède).
-- =====================================================================

-- 1) Pages (créées seulement si le nom n'existe pas ; id = MAX+1 pour éviter toute collision)
INSERT INTO catalogue_pages (id,order_id,min_role,index_visible,is_club_only,name_index,link_list,name,layout,image_headline,image_teasers,body,label_pick)
SELECT (SELECT m FROM (SELECT COALESCE(MAX(id),0)+1 m FROM catalogue_pages) t),207,1,1,0,'Événementiels','','Posters événementiels','ctlg_layout2','','','Posters spéciaux d''événements et de promotions.','Clique sur le meuble voulu'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_pages WHERE name='Posters événementiels');
INSERT INTO catalogue_pages (id,order_id,min_role,index_visible,is_club_only,name_index,link_list,name,layout,image_headline,image_teasers,body,label_pick)
SELECT (SELECT m FROM (SELECT COALESCE(MAX(id),0)+1 m FROM catalogue_pages) t),999,5,1,0,'TEST','','TEST (staff)','ctlg_layout2','','','Page de test réservée au staff — ne pas rendre publique.','Clique sur le meuble voulu'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_pages WHERE name='TEST (staff)');

-- 2) Définition md_sofa (id auto ; sprite_id interne = MAX+1 ; client rend par classname)
INSERT INTO items_definitions (sprite,sprite_id,name,description,colour,length,width,top_height,max_status,behaviour,interactor,is_tradable,is_recyclable,drink_ids)
SELECT 'md_sofa',(SELECT m FROM (SELECT COALESCE(MAX(sprite_id),0)+1 m FROM items_definitions) t),'Canapé','Canapé de la gamme Bubble Juice.','0,0,0',2,1,1,'1','can_sit_on_top','',1,1,''
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM items_definitions WHERE sprite='md_sofa');

-- 3) Offres posters (déf 251 partagée ; page référencée par NOM ; clé = déf+variante+page)
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 56',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),36,3,0,1,251,56,'Panneau Disco','Fais la fête sur tes murs !',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=56 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 21',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),37,3,0,1,251,21,'Vitrine à papillons','Magnifique reproduction de papillon',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=21 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 22',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),38,3,0,1,251,22,'Collection de papillons bleus','Magnifique reproduction de papillon',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=22 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 46',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),39,3,0,1,251,46,'Petite étoile dorée','Scintille, scintille',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=46 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 47',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),40,3,0,1,251,47,'Petite étoile argentée','Scintille, scintille',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=47 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 48',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),41,3,0,1,251,48,'Grande étoile dorée','Tout ce qui brille...',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=48 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 49',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),42,3,0,1,251,49,'Grande étoile argentée','Tout ce qui brille...',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=49 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 1006',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1),43,3,0,1,251,1006,'Poster de hibou','Ces yeux te suivent...',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=1006 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Galerie' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 25',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1),25,3,0,1,251,25,'Poster de bonhomme de neige','Une nouvelle utilisation des carottes !',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=25 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 26',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1),26,3,0,1,251,26,'Poster d''ange','Regarde cette auréole briller !',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=26 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 27',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1),27,3,0,1,251,27,'Lot de houx','Décore les salles !',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=27 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 28',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1),28,3,0,1,251,28,'Lot de guirlandes argentées','10 guirlandes argentées',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=28 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 29',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1),29,3,0,1,251,29,'Lot de guirlandes dorées','10 guirlandes dorées',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=29 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 30',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1),30,3,0,1,251,30,'Gui','Fais une bise !',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=30 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Noël' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 23',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1),13,3,0,1,251,23,'Poster de chauve-souris','Flap, flap, cri, cri...',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=23 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 42',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1),14,3,0,1,251,42,'Toile d''araignée','Mieux vaut éviter de lui rentrer dedans',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=42 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 43',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1),15,3,0,1,251,43,'Chaînes','Bouge, danse et déchaîne-toi !',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=43 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 45',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1),16,3,0,1,251,45,'Squelette','Il lui faut quelques Habburgers de plus',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=45 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 50',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1),17,3,0,1,251,50,'Poster de chauve-souris','Flap, flap, cri, cri...',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=50 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Halloween' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 2005',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Posters événementiels' LIMIT 1),1,3,0,1,251,2005,'Infobus','',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=2005 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Posters événementiels' LIMIT 1));
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'poster 2008',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Posters événementiels' LIMIT 1),2,3,0,1,251,2008,'Poster Habbo Cola','',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE definition_id=251 AND item_specialspriteid=2008 AND page_id=(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='Posters événementiels' LIMIT 1));

-- 4) Offre md_sofa (page TEST staff, déf référencée par sprite)
INSERT INTO catalogue_items (sale_code,page_id,order_id,price,is_hidden,amount,definition_id,item_specialspriteid,name,description,is_package)
SELECT 'md_sofa',(SELECT CAST(id AS CHAR) FROM catalogue_pages WHERE name='TEST (staff)' LIMIT 1),1,5,0,1,(SELECT id FROM items_definitions WHERE sprite='md_sofa'),0,'Canapé Bubble Juice','Canapé de la gamme Bubble Juice.',0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM catalogue_items WHERE sale_code='md_sofa');

-- 5) Traductions FR des noms catalogue (posters déjà présents avant cette migration)
UPDATE catalogue_items SET name='Plaque Poisson' WHERE definition_id=251 AND item_specialspriteid=3;
UPDATE catalogue_items SET name='Plaque Ours' WHERE definition_id=251 AND item_specialspriteid=4;
UPDATE catalogue_items SET name='Vitrine à marteau' WHERE definition_id=251 AND item_specialspriteid=7;
UPDATE catalogue_items SET name='Barreaux' WHERE definition_id=251 AND item_specialspriteid=16;
UPDATE catalogue_items SET name='Vitrine à papillons 1' WHERE definition_id=251 AND item_specialspriteid=17;
UPDATE catalogue_items SET name='Vitrine à papillons 2' WHERE definition_id=251 AND item_specialspriteid=18;
UPDATE catalogue_items SET name='Barreaux' WHERE definition_id=251 AND item_specialspriteid=20;
