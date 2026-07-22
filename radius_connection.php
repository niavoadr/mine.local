<?php
require_once __DIR__ . '/database.php';

try {
    $conn = get_db_connection('RADIUS_DB');
} catch (Throwable $e) {
    die("Connexion à la base radius échouée: " . $e->getMessage());
}
?>