-- Migráció 005 — Esemény-jelöltek (automatikus gyűjtés, jóváhagyásra várva)
-- holborozzak.hu
--
-- Az internetről gyűjtött / URL-ből importált események NEM az events-be kerülnek,
-- hanem ide, 'new' státusszal. Az adminban jóváhagyva belőlük draft event készül.
--
-- FIGYELEM: egyszer futtatandó. phpMyAdmin → Importálás, vagy:
--   mysql -h mysql.rackhost.hu -u c105746ptrk -p c105746holborozzak < db/migrations/005_add_event_candidates.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS event_candidates (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_url         VARCHAR(500) DEFAULT NULL,             -- honnan származik
  title              VARCHAR(255) NOT NULL,
  short_description  VARCHAR(500) DEFAULT NULL,
  description        TEXT         DEFAULT NULL,
  start_datetime     DATETIME     DEFAULT NULL,
  end_datetime       DATETIME     DEFAULT NULL,
  venue_name         VARCHAR(255) DEFAULT NULL,
  city               VARCHAR(120) DEFAULT NULL,
  region_name        VARCHAR(120) DEFAULT NULL,             -- borvidék neve (szövegként; approve-nál mappeljük)
  website_url        VARCHAR(500) DEFAULT NULL,
  facebook_url       VARCHAR(500) DEFAULT NULL,
  ticket_url         VARCHAR(500) DEFAULT NULL,
  is_free            TINYINT(1)   NOT NULL DEFAULT 0,
  price_info         VARCHAR(255) DEFAULT NULL,
  image_url          VARCHAR(500) DEFAULT NULL,
  dedup_key          VARCHAR(255) DEFAULT NULL,             -- normalizált cím|nap|város
  status             ENUM('new','approved','rejected','duplicate') NOT NULL DEFAULT 'new',
  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ecnd_status (status),
  KEY idx_ecnd_dedup (dedup_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
