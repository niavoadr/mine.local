<?php


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
$servername = "localhost";
$username = "rosa";
$password = "12345";
$db = "radius_ministere_mines"; // Changé pour correspondre au gestionnaire

// Create connection avec gestion d'erreurs
$conection= new mysqli($servername, $username, $password, $db);

// Vérifier la connexion
if ($conection->connect_error) {
    die("Erreur de connexion: " . $conection->connect_error);
}

// Définir le charset pour éviter les problèmes d'encodage
$conection->set_charset("utf8");
//echo "Connexion réussie à la base de données";

// Optionnel : afficher un message de succès pour le débogage
?>