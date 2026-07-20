<?php
require_once __DIR__ . '/env.php';

$host     = env('RADIUS_DB_HOST', 'localhost');          // ← Changer de 192.168.1.99 à localhost
$username = env('RADIUS_DB_USER', 'rosa');       
$password = env('RADIUS_DB_PASS', '12345');    
$database = env('RADIUS_DB_NAME', 'radius');
$port     = (int) env('RADIUS_DB_PORT', 3306);

$conn = mysqli_connect($host, $username, $password, $database, $port);
if (!$conn) {
    die("Connexion à la base radius échouée: " . mysqli_connect_error());
}
?>