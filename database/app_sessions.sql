-- À exécuter une seule fois dans PostgreSQL.
-- Cette table permet de compter les utilisateurs réellement connectés à l'application.

CREATE TABLE IF NOT EXISTS user_app_sessions (
    session_id VARCHAR(128) NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    last_seen TIMESTAMP NOT NULL DEFAULT now(),
    created_at TIMESTAMP NOT NULL DEFAULT now()
);
