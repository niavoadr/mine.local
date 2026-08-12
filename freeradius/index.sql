-- =============================================================================
-- Index pour les vérifications FreeRADIUS (blacklist_check + visitor_one_device)
-- =============================================================================
-- À exécuter sur la base PostgreSQL du RADIUS :
--   psql -U <utilisateur> -d <base> -f index.sql
-- =============================================================================

-- Index sur radcheck pour la recherche rapide des entrées Reject (liste noire)
-- Utilisé par blacklist_check dans la politique FreeRADIUS
CREATE INDEX IF NOT EXISTS idx_radcheck_auth_reject
    ON radcheck (attribute, value)
    WHERE attribute = 'Auth-Type' AND value = 'Reject';

-- Index sur radacct pour la vérification visiteur 1 identifiant = 1 appareil
-- Permet de trouver rapidement les sessions d'un visiteur déjà connecté
-- Utilisé par visitor_one_device dans la politique FreeRADIUS
CREATE INDEX IF NOT EXISTS idx_radacct_visitor_mac
    ON radacct (username, CallingStationId)
    WHERE AcctStartTime IS NOT NULL;
