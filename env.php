<?php
if (!function_exists('load_env')) {
  function load_env($path)
  {
    if (!file_exists($path)) {
      return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $line = trim($line);

      if (empty($line) || strpos($line, '#') === 0) {
        continue;
      }

      if (strpos($line, 'export ') === 0) {
        $line = substr($line, 7);
      }

      if (strpos($line, '=') !== false) {
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (
          (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
          (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        ) {
          $value = substr($value, 1, -1);
        }

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

load_env(__DIR__ . '/.env');

date_default_timezone_set('Indian/Antananarivo');

if (!function_exists('get_radius_mac_secret')) {
  function get_radius_mac_secret()
  {
    load_env(__DIR__ . '/.env');

    $secret = env('RADIUS_MAC_SECRET', '');

    if ($secret === null || $secret === false || is_bool($secret)) {
      $secret = '';
    }

    return trim((string) $secret);
  }
}
