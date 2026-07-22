<?php
/**
 * Point d'entrée unique pour la connexion à la base de données.
 * Lit les informations de connexion depuis le fichier .env via env.php.
 */
require_once __DIR__ . '/env.php';

if (!function_exists('get_db_config')) {
    function get_db_config($prefix = 'DB')
    {
        $host = env($prefix . '_HOST', 'localhost');
        $port = (int) env($prefix . '_PORT', 3306);
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

        $conn = mysqli_connect(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['name'],
            $config['port']
        );

        if ($conn === false) {
            throw new RuntimeException('Connexion MySQLi impossible : ' . mysqli_connect_error());
        }

        if (!mysqli_set_charset($conn, 'utf8')) {
            throw new RuntimeException('Impossible de définir le charset UTF-8 : ' . mysqli_error($conn));
        }

        return $conn;
    }
}

if (!function_exists('get_pdo_connection')) {
    function get_pdo_connection($prefix = 'DB')
    {
        $config = get_db_config($prefix);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8',
            $config['host'],
            $config['port'],
            $config['name']
        );

        $pdo = new PDO(
            $dsn,
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    }
}
?>
