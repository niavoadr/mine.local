<?php
require_once __DIR__ . '/env.php';

if (!function_exists('get_db_config')) {
  function get_db_config($prefix = 'DB')
  {
    $host = env($prefix . '_HOST', 'localhost');
    $port = (int) env($prefix . '_PORT', 5432);
    $name = env($prefix . '_NAME', '');
    $user = env($prefix . '_USER', '');
    $pass = env($prefix . '_PASS', '');

    return [
      'host' => $host,
      'port' => $port,
      'name' => $name,
      'user' => $user,
      'pass' => $pass,
    ];
  }
}

if (!function_exists('get_db_connection')) {
  function get_db_connection($prefix = 'DB')
  {
    $config = get_db_config($prefix);

    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['name']);

    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("SET NAMES 'utf8'");

    return $pdo;
  }
}

try {
  $connexion = get_db_connection('DB');
} catch (Throwable $e) {
  die('Erreur de connexion à la base de données radius : ' . $e->getMessage());
}
