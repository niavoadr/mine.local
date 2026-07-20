<?php
require_once __DIR__ . '/env.php';

/*$servername = "localhost";
$username = "rosa";
$password = "12345";
$db = "Admins";*/

/*
// Create connection avec gestion d'erreurs
$conection= new mysqli($servername, $username, $password, $db);

// Vérifier la connexion
if ($conection->connect_error) {
    die("Erreur de connexion: " . $con->connect_error);
}

// Définir le charset pour éviter les problèmes d'encodage
$conection->set_charset("utf8");
//echo "Connexion réussie à la base de données";

// Optionnel : afficher un message de succès pour le débogage*/
$servername = env('DB_HOST', 'localhost');
$username   = env('DB_USER', 'rosa');
$password   = env('DB_PASS', '12345');
$db         = env('DB_NAME', 'radius_ministere_mines'); // Changé pour correspondre au gestionnaire
$port       = (int) env('DB_PORT', 3306);

// Create connection avec gestion d'erreurs
$conection = new mysqli($servername, $username, $password, $db, $port);
$conn = $conection; // Alias pour compatibilité avec les scripts utilisant $conn

// Vérifier la connexion
if ($conection->connect_error) {
    die("Erreur de connexion: " . $conection->connect_error);
}

// Définir le charset pour éviter les problèmes d'encodage
$conection->set_charset("utf8");
//echo "Connexion réussie à la base de données";

// Optionnel : afficher un message de succès pour le débogage
?>