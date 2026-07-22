<?php
require_once __DIR__ . '/database.php';

try {
    $conn = get_db_connection('DB');
    $connection = $conn;
    $conection = $conn; // Alias pour compatibilité ascendante si nécessaire
} catch (Throwable $e) {
    die("Erreur de connexion: " . $e->getMessage());
}
?>