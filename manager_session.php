<?php
/** Suivi des sessions ouvertes dans l'application. */

function register_app_session(PDO $pdo): void
{
  if (empty($_SESSION['user_id']) || session_status() !== PHP_SESSION_ACTIVE) {
    return;
  }

  $stmt = $pdo->prepare(
    "INSERT INTO user_app_sessions (session_id, user_id, last_seen)
     VALUES (?, ?, now())
     ON CONFLICT (session_id)
     DO UPDATE SET user_id = EXCLUDED.user_id, last_seen = now()"
  );
  $stmt->execute([session_id(), $_SESSION['user_id']]);
}

function get_connected_app_users(PDO $pdo): int
{
  $pdo->exec("DELETE FROM user_app_sessions WHERE last_seen < now() - interval '5 minutes'");

  return (int) $pdo->query(
    "SELECT COUNT(DISTINCT user_id)
     FROM user_app_sessions
     WHERE last_seen >= now() - interval '5 minutes'"
  )->fetchColumn();
}
