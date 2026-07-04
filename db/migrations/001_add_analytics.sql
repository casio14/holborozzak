-- Migráció 001 — Analitika táblák hozzáadása
-- holborozzak.hu
--
-- Biztonságosan lefuttatható egy MÁR létező adatbázison is (CREATE TABLE IF NOT EXISTS).
-- Futtatás:
--   mysql -h mysql.rackhost.hu -u c105746patrik -p c105746holborozzak < db/migrations/001_add_analytics.sql
-- vagy phpMyAdmin → Importálás.

SET NAMES utf8mb4;

-- Nyers interakció-napló: kattintás (jegy/honlap) + részletoldal-megtekintés.
CREATE TABLE IF NOT EXISTS event_interactions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id    INT UNSIGNED NOT NULL,
  type        ENUM('view','click_website','click_ticket') NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  session_id  VARCHAR(64)  DEFAULT NULL,
  referrer    VARCHAR(255) DEFAULT NULL,
  ip_hash     CHAR(64)     DEFAULT NULL,
  user_agent  VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_ei_event_type_time (event_id, type, created_at),
  KEY idx_ei_time (created_at),
  CONSTRAINT fk_ei_event FOREIGN KEY (event_id)
    REFERENCES events (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lista-megjelenések (impressziók) napi összesítésben.
CREATE TABLE IF NOT EXISTS event_impressions_daily (
  event_id     INT UNSIGNED NOT NULL,
  stat_date    DATE NOT NULL,
  impressions  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (event_id, stat_date),
  CONSTRAINT fk_eid_event FOREIGN KEY (event_id)
    REFERENCES events (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
