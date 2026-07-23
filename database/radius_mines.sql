CREATE TYPE department_enum AS ENUM (
	'Communication',
	'Directeur des Affaires Juridiques',
	'Finance',
	'Ressources Humaines',
	'Secrétariat Général'
);

CREATE TYPE role_enum AS ENUM (
	'ADMIN',
	'USER'
);

CREATE TYPE account_type_enum AS ENUM (
	'visitor',
	'permanent'
);

CREATE TYPE visitor_status_enum AS ENUM (
	'active',
	'expired'
);

CREATE TYPE user_status_enum AS ENUM (
	'active',
	'inactive',
	'suspended'
);

CREATE TYPE security_status_enum AS ENUM (
	'info',
	'warning',
	'critical'
);

CREATE TABLE IF NOT EXISTS users (
	id BIGSERIAL NOT NULL PRIMARY KEY,
	username VARCHAR(255) NOT NULL UNIQUE,
	email VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	department DEPARTMENT_ENUM NOT NULL,
	role ROLE_ENUM NOT NULL,
	status USER_STATUS_ENUM NOT NULL DEFAULT 'active',
	date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	date_modification TIMESTAMP NOT NULL,
	last_login TIMESTAMP,
	visitor_create INTEGER NOT NULL
);




CREATE TABLE IF NOT EXISTS visitor (
	id BIGSERIAL NOT NULL PRIMARY KEY,
	username VARCHAR(255) NOT NULL UNIQUE,
	password_hash VARCHAR(255) NOT NULL,
	department DEPARTMENT_ENUM NOT NULL,
	created_by BIGINT NOT NULL,
	date_creation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
	blocked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	expires_at TIMESTAMP NOT NULL
);




CREATE TABLE IF NOT EXISTS security_event (
	id BIGSERIAL NOT NULL PRIMARY KEY,
	event_type VARCHAR(255) NOT NULL,
	security_status SECURITY_STATUS_ENUM NOT NULL,
	source_ip INET,
	mac_address MACADDR,
	details JSON NOT NULL,
	create_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	read BOOLEAN NOT NULL,
	read_at TIMESTAMP
);



ALTER TABLE users
ADD FOREIGN KEY(visitor_create) REFERENCES visitor(id)
ON UPDATE NO ACTION ON DELETE NO ACTION;