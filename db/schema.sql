-- holborozzak.hu — adatbázis séma
-- MySQL 5.7+ / 8.0 (Rackhost). Karakterkészlet: utf8mb4 (teljes magyar + emoji támogatás).
--
-- Futtatás:
--   - phpMyAdminban: Importálás fül → ezt a fájlt
--   - vagy parancssorból: mysql -h mysql.rackhost.hu -u c105746patrik -p c105746holborozzak < db/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. Borvidékek (segédtábla) — Magyarország 22 hivatalos borvidéke
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wine_regions (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) NOT NULL,
  slug       VARCHAR(120) NOT NULL,
  image_url  VARCHAR(500) DEFAULT NULL,            -- borvidék háttérképe (csempékhez); NULL → borvörös fallback
  image_alt  VARCHAR(255) DEFAULT NULL,            -- alt-szöveg (akadálymentesség/SEO)
  PRIMARY KEY (id),
  UNIQUE KEY uq_wine_regions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Címkék / kategóriák (segédtábla)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name  VARCHAR(120) NOT NULL,
  slug  VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Események (fő tábla)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Azonosítás
  slug               VARCHAR(255) NOT NULL,                 -- SEO URL, pl. budapesti-bor-napok-2026
  title              VARCHAR(255) NOT NULL,                 -- esemény neve

  -- Leírás
  short_description  VARCHAR(500) DEFAULT NULL,             -- rövid leírás (lista-kártya)
  description        TEXT         DEFAULT NULL,             -- hosszú leírás (részletező oldal)

  -- Időpont
  start_datetime     DATETIME     NOT NULL,                 -- kezdés
  end_datetime       DATETIME     DEFAULT NULL,             -- befejezés (több napos esemény)

  -- Helyszín (térképhez)
  venue_name         VARCHAR(255) DEFAULT NULL,             -- pl. „Budai Vár"
  address            VARCHAR(255) DEFAULT NULL,
  city               VARCHAR(120) DEFAULT NULL,
  region_id          INT UNSIGNED DEFAULT NULL,             -- FK -> wine_regions
  latitude           DECIMAL(10,7) DEFAULT NULL,
  longitude          DECIMAL(10,7) DEFAULT NULL,

  -- Média
  image_url          VARCHAR(500) DEFAULT NULL,             -- hivatalos kép
  image_alt          VARCHAR(255) DEFAULT NULL,             -- alt-szöveg (akadálymentesség/SEO)
  image_credit       VARCHAR(255) DEFAULT NULL,             -- kép forrása/jogtulajdonosa

  -- Linkek
  website_url        VARCHAR(500) DEFAULT NULL,             -- hivatalos honlap
  facebook_url       VARCHAR(500) DEFAULT NULL,             -- Facebook-esemény
  ticket_url         VARCHAR(500) DEFAULT NULL,             -- jegyvásárlás

  -- Ár / ingyenesség
  is_free            TINYINT(1)   NOT NULL DEFAULT 0,       -- „Ingyenes" tab
  price_info         VARCHAR(255) DEFAULT NULL,             -- szabad szöveges ár, pl. „Belépő 3 000 Ft-tól"

  -- Kiemelés / állapot
  is_featured        TINYINT(1)   NOT NULL DEFAULT 0,       -- „Kiemelt" tab + nagyobb kártya
  featured_until     DATE         DEFAULT NULL,             -- eddig kiemelt (utána lekerül)
  status             ENUM('draft','published','cancelled') NOT NULL DEFAULT 'draft',

  -- Beküldő (kapcsolattartó) — a nyilvános beküldő űrlapból; NEM publikus
  submitter_name     VARCHAR(120) DEFAULT NULL,
  submitter_email    VARCHAR(255) DEFAULT NULL,

  -- Időbélyegek
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_events_slug (slug),
  KEY idx_events_start (start_datetime),
  KEY idx_events_featured (is_featured),
  KEY idx_events_free (is_free),
  KEY idx_events_status (status),
  KEY idx_events_region (region_id),
  CONSTRAINT fk_events_region FOREIGN KEY (region_id)
    REFERENCES wine_regions (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Esemény ↔ címke kapcsolótábla (több-a-többhöz)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_categories (
  event_id     INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, category_id),
  KEY idx_ec_category (category_id),
  CONSTRAINT fk_ec_event FOREIGN KEY (event_id)
    REFERENCES events (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ec_category FOREIGN KEY (category_id)
    REFERENCES categories (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- 5. Alapadatok: Magyarország 22 hivatalos borvidéke
-- ---------------------------------------------------------------------------
INSERT INTO wine_regions (name, slug) VALUES
  ('Csongrádi',          'csongradi'),
  ('Hajós-Bajai',        'hajos-bajai'),
  ('Kunsági',            'kunsagi'),
  ('Ászár-Neszmélyi',    'aszar-neszmelyi'),
  ('Badacsonyi',         'badacsonyi'),
  ('Balatonboglári',     'balatonboglari'),
  ('Balaton-felvidéki',  'balaton-felvideki'),
  ('Balatonfüred-Csopaki','balatonfured-csopaki'),
  ('Etyek-Budai',        'etyek-budai'),
  ('Móri',               'mori'),
  ('Nagy-Somlói',        'nagy-somloi'),
  ('Pannonhalmi',        'pannonhalmi'),
  ('Pécsi',              'pecsi'),
  ('Soproni',            'soproni'),
  ('Szekszárdi',         'szekszardi'),
  ('Tolnai',             'tolnai'),
  ('Villányi',           'villanyi'),
  ('Bükki',              'bukki'),
  ('Egri',               'egri'),
  ('Mátrai',             'matrai'),
  ('Tokaji',             'tokaji'),
  ('Zalai',              'zalai')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------------
-- 6. Alapadatok: kezdő címkék (bővíthető)
-- ---------------------------------------------------------------------------
INSERT INTO categories (name, slug) VALUES
  ('Borfesztivál',        'borfesztival'),
  ('Szüreti rendezvény',  'szureti-rendezveny'),
  ('Kóstoló',             'kostolo'),
  ('Gasztronómia',        'gasztronomia'),
  ('Koncert',             'koncert'),
  ('Családi program',     'csaladi-program'),
  ('Borvidéki program',   'borvideki-program')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------------
-- 7. Analitika: nyers interakció-napló (kattintás + megtekintés)
--    Soronként egy esemény, időbélyeggel. Ebből bármilyen statisztika,
--    trend és konverzió (megtekintés -> kattintás) kiszámolható.
--    GDPR: nyers IP-t NEM tárolunk, csak hashelt (ip_hash) — bot/abuse szűréshez.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_interactions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id    INT UNSIGNED NOT NULL,
  type        ENUM('view','click_website','click_ticket') NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  session_id  VARCHAR(64)  DEFAULT NULL,   -- egyedi látogató becsléséhez (cookie)
  referrer    VARCHAR(255) DEFAULT NULL,   -- honnan érkezett
  ip_hash     CHAR(64)     DEFAULT NULL,   -- HASH-elt IP (nem visszafejthető)
  user_agent  VARCHAR(255) DEFAULT NULL,   -- bot-szűréshez
  PRIMARY KEY (id),
  KEY idx_ei_event_type_time (event_id, type, created_at),
  KEY idx_ei_time (created_at),
  CONSTRAINT fk_ei_event FOREIGN KEY (event_id)
    REFERENCES events (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 8. Analitika: lista-megjelenések (impressziók) NAPI ÖSSZESÍTÉSBEN
--    Az impresszió nagy volumenű, ezért nem soronként, hanem naponta összegezve
--    tároljuk. Növelés: INSERT ... ON DUPLICATE KEY UPDATE impressions = impressions + 1
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_impressions_daily (
  event_id     INT UNSIGNED NOT NULL,
  stat_date    DATE NOT NULL,
  impressions  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (event_id, stat_date),
  CONSTRAINT fk_eid_event FOREIGN KEY (event_id)
    REFERENCES events (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 9. Hírlevél feliratkozók (a newsletter.php futásidőben is létrehozza)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscribers (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subscribers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 10. Esemény-jelöltek (automatikus gyűjtés / URL-import → jóváhagyásra vár)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_candidates (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_url         VARCHAR(500) DEFAULT NULL,
  title              VARCHAR(255) NOT NULL,
  short_description  VARCHAR(500) DEFAULT NULL,
  description        TEXT         DEFAULT NULL,
  start_datetime     DATETIME     DEFAULT NULL,
  end_datetime       DATETIME     DEFAULT NULL,
  venue_name         VARCHAR(255) DEFAULT NULL,
  city               VARCHAR(120) DEFAULT NULL,
  region_name        VARCHAR(120) DEFAULT NULL,
  website_url        VARCHAR(500) DEFAULT NULL,
  facebook_url       VARCHAR(500) DEFAULT NULL,
  ticket_url         VARCHAR(500) DEFAULT NULL,
  is_free            TINYINT(1)   NOT NULL DEFAULT 0,
  price_info         VARCHAR(255) DEFAULT NULL,
  image_url          VARCHAR(500) DEFAULT NULL,
  dedup_key          VARCHAR(255) DEFAULT NULL,
  status             ENUM('new','approved','rejected','duplicate') NOT NULL DEFAULT 'new',
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ecnd_status (status),
  KEY idx_ecnd_dedup (dedup_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
