-- holborozzak.hu — minta események (seed)
-- Futtatás a séma (schema.sql) UTÁN:
--   mysql -h mysql.rackhost.hu -u c105746patrik -p c105746holborozzak < db/seed.sql
--   vagy phpMyAdmin → Importálás.
-- Újra lefuttatható (INSERT IGNORE a slug egyediségére).
-- A kép most placeholder (assets/hero.jpg); valódi képek később.

SET NAMES utf8mb4;

-- A) Most zajló, ingyenes esemény
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, is_featured, status)
SELECT 'balatoni-rose-napok-2026', 'Balatoni Rosé Napok',
       'Könnyű nyári rosék és naplemente a Balaton partján.',
       '2026-06-20 16:00:00', '2026-06-28 22:00:00', 'Platán sétány', 'Platán sétány 1.', 'Balatonboglár',
       (SELECT id FROM wine_regions WHERE slug='balatonboglari'), 46.7756000, 17.6489000,
       'assets/hero.jpg', 'Balatoni Rosé Napok', 1, 0, 'published';

-- B) Hamarosan (egynapos, fizetős)
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, price_info, is_featured, status)
SELECT 'pannonhalmi-borvacsora-2026', 'Pannonhalmi Borvacsora',
       'Többfogásos vacsora a főapátság boraival párosítva.',
       '2026-06-27 18:00:00', NULL, 'Pannonhalmi Főapátság', 'Vár 1.', 'Pannonhalma',
       (SELECT id FROM wine_regions WHERE slug='pannonhalmi'), 47.5536000, 17.7556000,
       'assets/hero.jpg', 'Pannonhalmi Borvacsora', 0, 'Jegy 12 900 Ft', 0, 'published';

-- C) Júliusi, ingyenes
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, is_featured, status)
SELECT 'szent-gyorgy-hegy-hajnalig-2026', 'Szent György-hegy Hajnalig',
       'Esti pincebejárás és borkóstoló a Szent György-hegyen, hajnalig.',
       '2026-07-18 17:00:00', NULL, 'Szent György-hegy', NULL, 'Raposka',
       (SELECT id FROM wine_regions WHERE slug='badacsonyi'), 46.8500000, 17.4500000,
       'assets/hero.jpg', 'Szent György-hegy Hajnalig', 1, 0, 'published';

-- D) Júliusi, többnapos, fizetős
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, price_info, is_featured, status)
SELECT 'egri-bikaver-unnep-2026', 'Egri Bikavér Ünnep',
       'Az egri borvidék legjobb bikavérei a belváros szívében.',
       '2026-07-25 10:00:00', '2026-07-27 22:00:00', 'Dobó tér', 'Dobó István tér', 'Eger',
       (SELECT id FROM wine_regions WHERE slug='egri'), 47.9028000, 20.3719000,
       'assets/hero.jpg', 'Egri Bikavér Ünnep', 0, 'Belépő 2 500 Ft-tól', 0, 'published';

-- E) Augusztusi
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, price_info, is_featured, status)
SELECT 'soproni-borfesztival-2026', 'Soproni Borfesztivál',
       'A soproni borvidék ünnepe a történelmi Fő téren.',
       '2026-08-15 11:00:00', '2026-08-17 22:00:00', 'Fő tér', 'Fő tér', 'Sopron',
       (SELECT id FROM wine_regions WHERE slug='soproni'), 47.6817000, 16.5845000,
       'assets/hero.jpg', 'Soproni Borfesztivál', 0, 'Belépő 1 900 Ft-tól', 0, 'published';

-- F) Szeptemberi, KIEMELT
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, price_info, is_featured, featured_until, status)
SELECT 'budapesti-borfesztival-2026', 'Budapesti Borfesztivál',
       'Magyarország legnagyobb borünnepe a Budai Várban, páratlan panorámával.',
       '2026-09-10 12:00:00', '2026-09-13 22:00:00', 'Budai Vár', 'Szent György tér', 'Budapest',
       (SELECT id FROM wine_regions WHERE slug='etyek-budai'), 47.4961000, 19.0398000,
       'assets/hero.jpg', 'Budapesti Borfesztivál a Budai Várban', 0, 'Napijegy 4 900 Ft', 1, '2026-09-13', 'published';

-- G) Októberi, ingyenes, szüret
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, is_featured, status)
SELECT 'tokaji-szureti-napok-2026', 'Tokaji Szüreti Napok',
       'Szüreti felvonulás és aszúkóstoló a világörökségi Tokajban.',
       '2026-10-03 09:00:00', '2026-10-05 20:00:00', 'Fő tér', 'Fő tér', 'Tokaj',
       (SELECT id FROM wine_regions WHERE slug='tokaji'), 48.1167000, 21.4097000,
       'assets/hero.jpg', 'Tokaji Szüreti Napok', 1, 0, 'published';

-- H) Októberi, KIEMELT
INSERT IGNORE INTO events
  (slug, title, short_description, start_datetime, end_datetime, venue_name, address, city,
   region_id, latitude, longitude, image_url, image_alt, is_free, price_info, is_featured, featured_until, status)
SELECT 'villanyi-vorosbor-fesztival-2026', 'Villányi Vörösbor Fesztivál',
       'A villányi vörösborok ünnepe koncertekkel és gasztrosátrakkal.',
       '2026-10-09 12:00:00', '2026-10-11 22:00:00', 'Szoborpark', 'Baross Gábor utca', 'Villány',
       (SELECT id FROM wine_regions WHERE slug='villanyi'), 45.8694000, 18.4533000,
       'assets/hero.jpg', 'Villányi Vörösbor Fesztivál', 0, 'Belépő 3 000 Ft', 1, '2026-10-11', 'published';

-- ----- Címke (kategória) hozzárendelések -----
INSERT IGNORE INTO event_categories (event_id, category_id)
SELECT e.id, c.id FROM events e JOIN categories c
WHERE (e.slug='balatoni-rose-napok-2026'        AND c.slug IN ('borvideki-program','kostolo'))
   OR (e.slug='pannonhalmi-borvacsora-2026'     AND c.slug IN ('kostolo','gasztronomia'))
   OR (e.slug='szent-gyorgy-hegy-hajnalig-2026' AND c.slug IN ('borvideki-program','kostolo'))
   OR (e.slug='egri-bikaver-unnep-2026'         AND c.slug IN ('borfesztival','gasztronomia'))
   OR (e.slug='soproni-borfesztival-2026'       AND c.slug IN ('borfesztival'))
   OR (e.slug='budapesti-borfesztival-2026'     AND c.slug IN ('borfesztival','kostolo'))
   OR (e.slug='tokaji-szureti-napok-2026'       AND c.slug IN ('szureti-rendezveny','borvideki-program'))
   OR (e.slug='villanyi-vorosbor-fesztival-2026' AND c.slug IN ('borfesztival','koncert'));
