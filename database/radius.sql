CREATE TYPE ROLE_ENUM AS ENUM (
	'ADMIN',
	'USER'
);

CREATE TYPE VISITOR_STATUS_ENUM AS ENUM (
	'active',
	'expired'
);

CREATE TYPE USERS_STATUS_ENUM AS ENUM (
	'active',
	'inactive',
	'suspended'
);

CREATE TYPE SECURITY_STATUS_ENUM AS ENUM (
	'info',
	'warning',
	'critical'
);

CREATE TYPE DEPARTMENT_ENUM AS ENUM (
	'Communication',
	'Directeur des Affaires Juridiques',
	'Finance',
	'Ressources Humaines',
	'Secrétariat Général'
);

CREATE TYPE GROUPNAME_ENUM AS ENUM (
	'communication_group',
	'daj_group',
	'finance_group',
	'rh_group',
	'sg_group'
);

CREATE TABLE IF NOT EXISTS radacct (
	RadAcctId		bigserial PRIMARY KEY,
	AcctSessionId		text NOT NULL,
	AcctUniqueId		text NOT NULL UNIQUE,
	UserName		text,
	Realm			text,
	NASIPAddress		inet NOT NULL,
	NASPortId		text,
	NASPortType		text,
	AcctStartTime		timestamp with time zone,
	AcctUpdateTime		timestamp with time zone,
	AcctStopTime		timestamp with time zone,
	AcctInterval		bigint,
	AcctSessionTime		bigint,
	AcctAuthentic		text,
	ConnectInfo_start	text,
	ConnectInfo_stop	text,
	AcctInputOctets		bigint,
	AcctOutputOctets	bigint,
	CalledStationId		text,
	CallingStationId	text,
	AcctTerminateCause	text,
	ServiceType		text,
	FramedProtocol		text,
	FramedIPAddress		inet,
	FramedIPv6Address	inet,
	FramedIPv6Prefix	inet,
	FramedInterfaceId	text,
	DelegatedIPv6Prefix	inet,
	Class			text
);

CREATE TABLE IF NOT EXISTS radcheck (
	id			serial PRIMARY KEY,
	UserName		text NOT NULL DEFAULT '',
	Attribute		text NOT NULL DEFAULT '',
	op			VARCHAR(2) NOT NULL DEFAULT '==',
	Value			text NOT NULL DEFAULT '',
    department        DEPARTMENT_ENUM
);

CREATE TABLE IF NOT EXISTS radgroupreply (
	id			serial PRIMARY KEY,
	GroupName		GROUPNAME_ENUM NOT NULL,
	Attribute		text NOT NULL DEFAULT '',
	op			VARCHAR(2) NOT NULL DEFAULT '=',
	Value			text NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS radreply (
	id			serial PRIMARY KEY,
	UserName		text NOT NULL DEFAULT '',
	Attribute		text NOT NULL DEFAULT '',
	op			VARCHAR(2) NOT NULL DEFAULT '=',
	Value			text NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS radusergroup (
	id			serial PRIMARY KEY,
	UserName		text NOT NULL DEFAULT '',
	GroupName		GROUPNAME_ENUM NOT NULL,
	priority		integer NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS radgroupcheck (
	id			serial PRIMARY KEY,
	GroupName		GROUPNAME_ENUM NOT NULL,
	Attribute		text NOT NULL DEFAULT '',
	op			VARCHAR(2) NOT NULL DEFAULT '==',
	Value			text NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS radpostauth (
	id			bigserial PRIMARY KEY,
	username		text NOT NULL,
	pass			text,
	reply			text,
	authdate		timestamp with time zone NOT NULL default now(),
	Class			text
);

CREATE TABLE IF NOT EXISTS nas (
	id			serial PRIMARY KEY,
	nasname			text NOT NULL,
	shortname		text NOT NULL,
	type			text NOT NULL DEFAULT 'other',
	ports			integer,
	secret			text NOT NULL,
	server			text,
	community		text,
	description		text
);

CREATE TABLE IF NOT EXISTS nasreload (
	NASIPAddress		inet PRIMARY KEY,
	ReloadTime		timestamp with time zone NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
	id BIGSERIAL NOT NULL PRIMARY KEY,
	username VARCHAR(255) NOT NULL UNIQUE,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	department DEPARTMENT_ENUM NOT NULL,
	role ROLE_ENUM NOT NULL,
	status USERS_STATUS_ENUM NOT NULL DEFAULT 'active',
	date_creation TIMESTAMP NOT NULL DEFAULT now(),
	date_modification TIMESTAMP NOT NULL,
	last_login TIMESTAMP
);

CREATE TABLE IF NOT EXISTS visitor (
	id BIGSERIAL NOT NULL PRIMARY KEY,
	username VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	department DEPARTMENT_ENUM NOT NULL,
	created_by BIGINT NOT NULL REFERENCES users(id),
	date_creation TIMESTAMP NOT NULL DEFAULT now(),
	expires_at TIMESTAMP NOT NULL,
	duration INTEGER NOT NULL,
	status VISITOR_STATUS_ENUM NOT NULL DEFAULT 'active',
	mac_address MACADDR NOT NULL,
	nas_ip INET NOT NULL
);

CREATE TABLE IF NOT EXISTS blacklist (
	id BIGSERIAL NOT NULL PRIMARY KEY,
	mac_address MACADDR NOT NULL,
	reason VARCHAR(255) NOT NULL,
	blocked_at TIMESTAMP NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS security_event (
	id BIGSERIAL NOT NULL PRIMARY KEY,
	event_type VARCHAR(255) NOT NULL,
	security_status SECURITY_STATUS_ENUM NOT NULL,
	source_ip INET,
	mac_address MACADDR,
	details JSONB NOT NULL DEFAULT '{}',
	attempts INTEGER NOT NULL DEFAULT 1,
	created_at TIMESTAMP NOT NULL DEFAULT now(),
	is_read BOOLEAN NOT NULL DEFAULT FALSE,
	read_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS session_users (
    session_id VARCHAR(128) NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    last_seen TIMESTAMP NOT NULL DEFAULT now(),
    created_at TIMESTAMP NOT NULL DEFAULT now()
);

INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES
('communication_group', 'WISPr-Bandwidth-Max-Down', ':=', '20000000'),
('communication_group', 'WISPr-Bandwidth-Max-Up', ':=', '20000000'),
('daj_group', 'WISPr-Bandwidth-Max-Down', ':=', '20000000'),
('daj_group', 'WISPr-Bandwidth-Max-Up', ':=', '20000000'),
('finance_group', 'WISPr-Bandwidth-Max-Down', ':=', '30000000'),
('finance_group', 'WISPr-Bandwidth-Max-Up', ':=', '30000000'),
('rh_group', 'WISPr-Bandwidth-Max-Down', ':=', '20000000'),
('rh_group', 'WISPr-Bandwidth-Max-Up', ':=', '20000000'),
('sg_group', 'WISPr-Bandwidth-Max-Down', ':=', '50000000'),
('sg_group', 'WISPr-Bandwidth-Max-Up', ':=', '50000000');
-- =====================================================================
-- Correctifs de sécurité / fonctionnalités (revue du 13/08/2026)
-- À exécuter AUSSI sur une base existante, instruction par instruction.
-- ⚠️ Ne pas rejouer tout radius.sql sur une base existante (CREATE TYPE
--    échouerait) : exécuter uniquement les instructions ci-dessous.
-- =====================================================================

-- M4 : colonne department pour une base FreeRADIUS existante
ALTER TABLE radcheck ADD COLUMN IF NOT EXISTS department DEPARTMENT_ENUM;

-- M3 : index pour session_users (heartbeat des sessions)
-- (en cas de doublons de session_id, supprimer d'abord :
--  DELETE FROM session_users a USING session_users b
--  WHERE a.session_id = b.session_id AND a.created_at < b.created_at;)
ALTER TABLE session_users ADD PRIMARY KEY (session_id);
CREATE INDEX IF NOT EXISTS idx_session_users_last_seen ON session_users (last_seen);

-- M2 : groupe visiteur + limite de bande passante (10 Mbps = 10 000 000 bps)
-- ⚠️ PostgreSQL : exécuter l'ALTER TYPE ADD VALUE séparément des INSERT
--    (pas dans la même transaction) si exécuté en une seule session.
ALTER TYPE groupname_enum ADD VALUE IF NOT EXISTS 'visitor_group';

INSERT INTO radgroupreply (groupname, attribute, op, value)
SELECT 'visitor_group', 'WISPr-Bandwidth-Max-Down', ':=', '10000000'
WHERE NOT EXISTS (SELECT 1 FROM radgroupreply WHERE groupname = 'visitor_group' AND attribute = 'WISPr-Bandwidth-Max-Down');

INSERT INTO radgroupreply (groupname, attribute, op, value)
SELECT 'visitor_group', 'WISPr-Bandwidth-Max-Up', ':=', '10000000'
WHERE NOT EXISTS (SELECT 1 FROM radgroupreply WHERE groupname = 'visitor_group' AND attribute = 'WISPr-Bandwidth-Max-Up');
