CREATE TYPE department_enum AS ENUM (
	'Communication',
	'Directeur des Affaires Juridiques',
	'Finance',
	'Ressources Humaines',
	'Secrétariat Général'
);



CREATE TYPE groupname_enum AS ENUM (
	'communication_group',
	'daj_groupe',
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
    department        DEPARTMENT_ENUM NOT NULL
);



CREATE TABLE IF NOT EXISTS radgroupreply (
	id			serial PRIMARY KEY,
	GroupName		GROUPNAME_ENUM NOT NULL DEFAULT '',
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
	GroupName		GROUPNAME_ENUM NOT NULL DEFAULT '',
	priority		integer NOT NULL DEFAULT 0
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
