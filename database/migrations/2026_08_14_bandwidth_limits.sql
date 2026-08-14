-- Limitation de vitesse de connexion — mise en conformité de radgroupreply
--
-- Problème corrigé : seuls les attributs WISPr-Bandwidth-Max-Down/Up étaient
-- publiés. Un NAS qui ne parle pas WISPr (Mikrotik RouterOS, pfSense, certains
-- contrôleurs) les ignore silencieusement : l'utilisateur est authentifié mais
-- aucune limite n'est appliquée. On publie donc les trois dialectes courants ;
-- chaque NAS applique celui qu'il comprend et ignore les autres.
--
-- Les valeurs restent STATIQUES et gérées ici, jamais depuis l'interface web.
--
-- Application : psql -d radius -f database/migrations/2026_08_14_bandwidth_limits.sql

BEGIN;

-- 1. Nettoyage : suppression des doublons éventuels (deux valeurs pour un même
--    couple groupe/attribut font que le NAS en applique une au hasard).
DELETE FROM radgroupreply a
USING radgroupreply b
WHERE a.id > b.id
  AND a.groupname = b.groupname
  AND a.attribute = b.attribute;

-- 2. Un seul enregistrement possible par groupe et par attribut.
CREATE UNIQUE INDEX IF NOT EXISTS radgroupreply_group_attr_uidx
  ON radgroupreply (groupname, attribute);

-- 3. Valeurs de référence (bits/s) et déclinaison dans les trois dialectes.
WITH profils(groupname, down_bps, up_bps) AS (
  VALUES
    ('communication_group', 20000000, 20000000),
    ('daj_group',           20000000, 20000000),
    ('finance_group',       30000000, 30000000),
    ('rh_group',            20000000, 20000000),
    ('sg_group',            50000000, 50000000),
    ('visitor_group',       10000000, 10000000)
),
attributs AS (
  -- WISPr : bits par seconde (CoovaChilli, ChilliSpot, hotspots génériques)
  SELECT groupname, 'WISPr-Bandwidth-Max-Down' AS attribute, down_bps::text AS value FROM profils
  UNION ALL
  SELECT groupname, 'WISPr-Bandwidth-Max-Up', up_bps::text FROM profils
  UNION ALL
  -- Mikrotik : « rx-rate/tx-rate » vu du routeur = upload/download client
  SELECT groupname, 'Mikrotik-Rate-Limit',
         (up_bps / 1000000)::text || 'M/' || (down_bps / 1000000)::text || 'M'
  FROM profils
  UNION ALL
  -- Ascend / RFC 4679 : bits par seconde (pfSense, BRAS divers)
  SELECT groupname, 'Ascend-Data-Rate', down_bps::text FROM profils
  UNION ALL
  SELECT groupname, 'Ascend-Xmit-Rate', up_bps::text FROM profils
)
INSERT INTO radgroupreply (groupname, attribute, op, value)
SELECT groupname::GROUPNAME_ENUM, attribute, ':=', value FROM attributs
ON CONFLICT (groupname, attribute)
DO UPDATE SET value = EXCLUDED.value, op = EXCLUDED.op;

-- 4. L'opérateur ':=' est indispensable : avec '=', FreeRADIUS n'écrase pas une
--    valeur déjà présente dans la réponse et la limite peut être ignorée.
UPDATE radgroupreply
SET op = ':='
WHERE attribute IN ('WISPr-Bandwidth-Max-Down', 'WISPr-Bandwidth-Max-Up',
                    'Mikrotik-Rate-Limit', 'Ascend-Data-Rate', 'Ascend-Xmit-Rate')
  AND trim(op) <> ':=';

COMMIT;

-- Vérification :
--   SELECT groupname, attribute, op, value FROM radgroupreply ORDER BY groupname, attribute;
