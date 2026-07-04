-- Migráció 003 — Beküldő (kapcsolattartó) adatai az eseményekhez
-- holborozzak.hu
--
-- A nyilvános beküldő űrlaphoz (esemeny-bekuldes.php): a draft eseményhez eltároljuk,
-- ki küldte be, hogy a jóváhagyás/kiemelés ügyében elérhessük. NEM jelenik meg publikusan.
--
-- FIGYELEM: ez EGYSZER futtatandó (MySQL-ben nincs ADD COLUMN IF NOT EXISTS).
-- Ha az oszlopok már léteznek, „Duplicate column" hibát ad — ez normális.
--
-- Futtatás:
--   mysql -h mysql.rackhost.hu -u c105746patrik -p c105746holborozzak < db/migrations/003_add_event_submitter.sql
-- vagy phpMyAdmin → Importálás.

SET NAMES utf8mb4;

ALTER TABLE events
  ADD COLUMN submitter_name  VARCHAR(120) DEFAULT NULL COMMENT 'Beküldő neve (nem publikus)' AFTER status,
  ADD COLUMN submitter_email VARCHAR(255) DEFAULT NULL COMMENT 'Beküldő e-mail címe (nem publikus, kapcsolattartás)' AFTER submitter_name;
