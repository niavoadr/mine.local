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

if (!function_exists('get_pfsense_config')) {
  /**
   * Récupère la configuration de connexion à pfSense (déconnexion portail captif)
   * depuis le .env, avec valeurs par défaut sûres.
   *
   * @return array{host:string, port:int, user:string, pass:string, verify_ssl:bool, cp_zone:string, configured:bool}
   */
  function get_pfsense_config()
  {
    load_env(__DIR__ . '/.env');

    $host = trim((string) env('PFSENSE_HOST', ''));
    $port = (int) env('PFSENSE_PORT', 443);
    $user = trim((string) env('PFSENSE_USER', 'admin'));
    $pass = (string) env('PFSENSE_PASS', '');
    $verifySsl = filter_var(env('PFSENSE_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN);
    $cpZone = trim((string) env('PFSENSE_CP_ZONE', ''));

    return [
      'host'       => $host,
      'port'       => $port > 0 ? $port : 443,
      'user'       => $user !== '' ? $user : 'admin',
      'pass'       => $pass,
      'verify_ssl' => $verifySsl,
      'cp_zone'    => $cpZone,
      // Drapeau qui indique si la config minimale (host + mot de passe) est présente
      'configured' => $host !== '' && $pass !== '',
    ];
  }
}
