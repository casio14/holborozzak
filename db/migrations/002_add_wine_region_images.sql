-- Migráció 002 — Borvidék-képek (a nyitóoldali „Böngéssz borvidék szerint" csempékhez)
-- holborozzak.hu
--
-- A wine_regions táblához ad kép-mezőket, hogy minden borvidék csempéje mögé
-- saját, homályosított fotó kerülhessen (fallback: borvörös csempe, ha nincs kép).
--
-- FIGYELEM: ez EGYSZER futtatandó (MySQL-ben nincs ADD COLUMN IF NOT EXISTS).
-- Ha újra lefuttatnád és már léteznek az oszlopok, „Duplicate column" hibát ad — ez normális.
--
-- Futtatás:
--   mysql -h mysql.rackhost.hu -u c105746patrik -p c105746holborozzak < db/migrations/002_add_wine_region_images.sql
-- vagy phpMyAdmin → Importálás.

SET NAMES utf8mb4;

ALTER TABLE wine_regions
  ADD COLUMN image_url VARCHAR(500) DEFAULT NULL COMMENT 'Borvidék háttérképe (relatív út pl. assets/borvidek/tokaji.jpg, vagy abszolút URL)' AFTER slug,
  ADD COLUMN image_alt VARCHAR(255) DEFAULT NULL COMMENT 'Kép alt-szövege (akadálymentesség/SEO)' AFTER image_url;

-- Példa kép-feltöltés (a tényleges fájlok/utak megléte után):
--   UPDATE wine_regions SET image_url = 'assets/borvidek/tokaji.jpg',  image_alt = 'Tokaji szőlőskert' WHERE slug = 'tokaji';
--   UPDATE wine_regions SET image_url = 'assets/borvidek/egri.jpg',    image_alt = 'Egri borvidék'      WHERE slug = 'egri';
