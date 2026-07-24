-- =====================================================================
-- Migration : enrichissement de la table `security_event`
-- ---------------------------------------------------------------------
-- Objectif : permettre à la liste noire (blacklist.php) d'afficher,
--   pour chaque adresse MAC bloquée :
--     - l'adresse IP associée (source_ip)
--     - le nombre de tentatives (attempts / nombre d'événements)
--
-- À exécuter sur la base PostgreSQL `radius`, par exemple :
--   psql -U admin -d radius -h localhost -f database/update_security_event.sql
--
-- Sécurité : l'ensemble est encapsulé dans une transaction et utilise
--   IF NOT EXISTS => ré-exécutable sans erreur.
-- =====================================================================

BEGIN;

-- 1) Compteur de tentatives porté par chaque événement de sécurité.
--    Un événement isolé vaut 1 ; les agrégations (ex. force brute sur
--    une fenêtre) peuvent incrémenter cette valeur.
ALTER TABLE security_event
    ADD COLUMN IF NOT EXISTS attempts INTEGER NOT NULL DEFAULT 1;

-- 2) Index pour accélérer les recherches par MAC / IP / date
--    (notamment les jointures blacklist <-> security_event).
CREATE INDEX IF NOT EXISTS idx_security_event_mac_address
    ON security_event (mac_address);

CREATE INDEX IF NOT EXISTS idx_security_event_source_ip
    ON security_event (source_ip);

CREATE INDEX IF NOT EXISTS idx_security_event_created_at
    ON security_event (created_at DESC);

-- 3) Vue pratique : dernière IP + total de tentatives par MAC.
--    Utilisée par blacklist.php pour remplir les colonnes IP / tentatives.
CREATE OR REPLACE VIEW v_security_event_by_mac AS
SELECT
    mac_address,
    MAX(source_ip)   AS last_source_ip,
    COUNT(*)         AS event_count,
    SUM(attempts)    AS total_attempts,
    MAX(created_at)  AS last_event_at
FROM security_event
WHERE mac_address IS NOT NULL
GROUP BY mac_address;

COMMIT;

-- ---------------------------------------------------------------------
-- Vérification rapide (optionnelle) :
--   SELECT * FROM v_security_event_by_mac ORDER BY total_attempts DESC LIMIT 10;
--   \d security_event
-- =====================================================================
