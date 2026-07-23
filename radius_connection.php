<?php
require_once __DIR__ . '/database.php';

try {
    $conn = get_db_connection('DB_NAME');
} catch (Throwable $e) {
    die("Connexion à la base radius échouée: " . $e->getMessage());
}
?>