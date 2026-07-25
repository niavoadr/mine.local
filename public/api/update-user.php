<?php
/**
 * Endpoint legacy de mise à jour du nom d'utilisateur.
 */

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(false, 'Méthode non autorisée', null, 405);
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$username = trim((string) ($_POST['username'] ?? ''));

if ($id <= 0 || $username === '') {
  json_response(false, 'Identifiant et nom utilisateur requis', null, 422);
}

try {
  $stmt = $connexion->prepare('UPDATE users SET username = ?, date_modification = now() WHERE id = ?');
  $stmt->execute([$username, $id]);

  json_response(true, 'Utilisateur mis à jour avec succès');
} catch (PDOException $e) {
  json_response(false, 'Erreur lors de la mise à jour', null, 500);
}
