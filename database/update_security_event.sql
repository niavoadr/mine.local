-- =====================================================================
-- Migration : ajout du nombre de tentatives à la table `security_event`
-- ---------------------------------------------------------------------
-- L'adresse IP est déjà stockée dans la colonne existante `source_ip`.
-- Cette migration ajoute UNIQUEMENT une colonne pour compter les
-- tentatives (utilisée par blacklist.php pour la colonne "tentatives").
--
-- À exécuter sur la base PostgreSQL `radius`, par exemple :
--   psql -U admin -d radius -h localhost -f database/update_security_event.sql
-- =====================================================================

ALTER TABLE security_event
    ADD COLUMN IF NOT EXISTS attempts INTEGER NOT NULL DEFAULT 1;
