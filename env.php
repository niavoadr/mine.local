<?php
/**
 * Chargeur de variables d'environnement (.env) simple et sécurisé sans dépendance externe.
 */

if (!function_exists('load_env')) {
  function load_env($path)
  {
    if (!file_exists($path)) {
      return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $line = trim($line);

      // Ignorer les commentaires et les lignes vides
      if (empty($line) || strpos($line, '#') === 0) {
        continue;
      }

      // Supprimer 'export ' si présent
      if (strpos($line, 'export ') === 0) {
        $line = substr($line, 7);
      }

      // Séparer la clé et la valeur sur le premier caractère '='
      if (strpos($line, '=') !== false) {
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Supprimer les guillemets ou apostrophes autour de la valeur
        if (
          (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
          (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        ) {
          $value = substr($value, 1, -1);
        }

        // Définir la variable dans $_ENV, $_SERVER et getenv
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
          putenv(sprintf('%s=%s', $name, $value));
          $_ENV[$name] = $value;
          $_SERVER[$name] = $value;
        }
      }
    }
    return true;
  }
}

if (!function_exists('env')) {
  function env($key, $default = null)
  {
    if (array_key_exists($key, $_ENV)) {
      $value = $_ENV[$key];
    } elseif (array_key_exists($key, $_SERVER)) {
      $value = $_SERVER[$key];
    } else {
      $value = getenv($key);
      if ($value === false) {
        return $default;
      }
    }

    if ($value === 'true' || $value === '(true)') {
      return true;
    }
    if ($value === 'false' || $value === '(false)') {
      return false;
    }
    if ($value === 'null' || $value === '(null)') {
      return null;
    }
    if ($value === 'empty' || $value === '(empty)') {
      return '';
    }
    return $value;
  }
}

// Chargement automatique du fichier .env à la racine
load_env(__DIR__ . '/.env');

if (!function_exists('get_radius_mac_secret')) {
  /**
   * Récupère le secret partagé RADIUS (MAC Authentication Bypass) depuis le .env.
   * Retourne une chaîne vide si la variable n'est pas définie.
   */
  function get_radius_mac_secret()
  {
    // S'assurer que le .env est bien chargé (utile si l'ordre d'inclusion change)
    load_env(__DIR__ . '/.env');

    $secret = env('RADIUS_MAC_SECRET', '');

    if ($secret === null || $secret === false || is_bool($secret)) {
      $secret = '';
    }

    return trim((string) $secret);
  }
}

if (!function_exists('env_bool')) {
  /**
   * Récupère une variable booléenne depuis le .env.
   * Valeurs considérées comme true : 1, true, yes, on.
   */
  function env_bool($key, $default = false)
  {
    $value = env($key, $default ? 'true' : 'false');

    if (is_bool($value)) {
      return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
  }
}

if (!function_exists('get_captive_portal_ssh_config')) {
  /**
   * Récupère la configuration SSH du portail captif depuis le fichier .env.
   */
  function get_captive_portal_ssh_config()
  {
    // S'assurer que le .env est bien chargé (utile si l'ordre d'inclusion change)
    load_env(__DIR__ . '/.env');

    $port = (int) env('CAPTIVE_PORTAL_SSH_PORT', '22');
    if ($port <= 0 || $port > 65535) {
      $port = 22;
    }

    $timeout = (int) env('CAPTIVE_PORTAL_SSH_TIMEOUT', '10');
    if ($timeout <= 0) {
      $timeout = 10;
    }

    return [
      'enabled' => env_bool('CAPTIVE_PORTAL_SSH_ENABLED', false),
      'host' => trim((string) env('CAPTIVE_PORTAL_SSH_HOST', '')),
      'user' => trim((string) env('CAPTIVE_PORTAL_SSH_USER', '')),
      'port' => $port,
      'password' => (string) env('CAPTIVE_PORTAL_SSH_PASSWORD', ''),
      'timeout' => $timeout,
      'strict_host_key_checking' => trim((string) env('CAPTIVE_PORTAL_SSH_STRICT_HOST_KEY_CHECKING', 'accept-new')),
      'disconnect_command' => trim((string) env('CAPTIVE_PORTAL_DISCONNECT_COMMAND', '')),
    ];
  }
}
