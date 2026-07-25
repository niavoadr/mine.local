<?php
/** Suivi des sessions ouvertes dans l'application. */

function register_app_session(PDO $pdo): void
{
  if (empty($_SESSION['user_id']) || session_status() !== PHP_SESSION_ACTIVE) {
    return;
  }

  // La table ne possède volontairement aucun index : on remplace la ligne
  // de session manuellement à chaque heartbeat.
  $delete = $pdo->prepare('DELETE FROM session_users WHERE session_id = ?');
  $delete->execute([session_id()]);

  $insert = $pdo->prepare(
    "INSERT INTO session_users (session_id, user_id, last_seen)
     VALUES (?, ?, now())"
  );
  $insert->execute([session_id(), $_SESSION['user_id']]);
}

function get_connected_app_users(PDO $pdo): int
{
  $pdo->exec("DELETE FROM session_users WHERE last_seen < now() - interval '3 minutes'");

  return (int) $pdo->query(
    "SELECT COUNT(DISTINCT user_id)
     FROM session_users
     WHERE last_seen >= now() - interval '3 minutes'"
  )->fetchColumn();
}
