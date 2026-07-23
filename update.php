<?php
require_once("./connection.php");
//update code//
// Vérifier si le formulaire a été soumis
if (isset($_POST['id']) && isset($_POST['username'])) {
    // Récupérer les données du formulaire
    $id = intval($_POST['id']);
    $nom = $_POST['username'];
  
    // Préparer et exécuter la requête SQL avec requête préparée PDO
    $sql = "UPDATE users SET username = ? WHERE id = ?";
  
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nom, $id]);
        
        // Rediriger vers la page principale après la mise à jour
        header('Location: front.php');
        exit();
    } catch (PDOException $e) {
        echo "Erreur lors de la mise à jour : " . $e->getMessage();
    }
  }

?>


	
