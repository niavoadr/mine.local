<?php
require_once("./connection.php");
//update code//
// Vérifier si le formulaire a été soumis
if (isset($_POST['id']) && isset($_POST['username'])) {
    // Récupérer les données du formulaire
    $id = intval($_POST['id']);
    $nom = $conn->real_escape_string($_POST['username']);
  
    // Préparer et exécuter la requête SQL
    $sql = "UPDATE users  SET username = '$nom' WHERE id = $id";
  
    if ($conn->query($sql) === TRUE) {
        // Rediriger vers la page principale après la mise à jour
        //echo "Mise à jour réussie.";
        header('Location: front.php');
        exit();
    } else {
        echo "Erreur lors de la mise à jour : " . $conn->error;
    }
  }

?>


	
