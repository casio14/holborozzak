-- Migráció 004 — Facebook-esemény link az eseményekhez
-- holborozzak.hu
--
-- A részletoldalon „Facebook-esemény" gomb; a beküldő/admin űrlapon megadható.
--
-- FIGYELEM: egyszer futtatandó (MySQL-ben nincs ADD COLUMN IF NOT EXISTS).
-- Futtatás: mysql -h mysql.rackhost.hu -u c105746patrik -p c105746holborozzak < db/migrations/004_add_facebook_url.sql

SET NAMES utf8mb4;

ALTER TABLE events
  ADD COLUMN facebook_url VARCHAR(500) DEFAULT NULL COMMENT 'Facebook-esemény URL' AFTER website_url;
