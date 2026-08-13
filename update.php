<?php
require_once './connexion.php';

session_start();

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    echo 'Session expirée';
    exit();
}

if (isset($_POST['id']) && isset($_POST['username'])) {
  $id = intval($_POST['id']);
  $nom = $_POST['username'];

  $sql = 'UPDATE users SET username = ?, date_modification = now() WHERE id = ?';

  try {
    $stmt = $connexion->prepare($sql);
    $stmt->execute([$nom, $id]);

    header('Location: front.php');
    exit();
  } catch (PDOException $e) {
    echo 'Erreur lors de la mise à jour : ' . $e->getMessage();
  }
}
?>


	
