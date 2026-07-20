<?php
$host = 'localhost';          // ← Changer de 192.168.1.99 à localhost
$username = 'rosa';       
$password = '12345';    
$database = 'radius';

$conn = mysqli_connect($host, $username, $password, $database);
if (!$conn) {
    die("Connexion à la base radius échouée: " . mysqli_connect_error());
}
?>