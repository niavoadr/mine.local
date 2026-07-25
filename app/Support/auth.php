<?php

declare(strict_types=1);

if (!function_exists('ensure_session_started')) {
  function ensure_session_started(): void
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_start();
    }
  }
}

if (!function_exists('current_username')) {
  function current_username(): string
  {
    return trim((string) ($_SESSION['user'] ?? ($_SESSION['nom_utilisateur'] ?? '')));
  }
}

if (!function_exists('require_authenticated_user')) {
  function require_authenticated_user(string $loginPath = 'login.php'): string
  {
    ensure_session_started();

    $username = current_username();
    if ($username === '') {
      header('Location: ' . $loginPath);
      exit();
    }

    return $username;
  }
}
