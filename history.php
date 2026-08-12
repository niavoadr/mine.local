<?php
require_once __DIR__ . '/connexion.php';

session_start();

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

header('Content-Type: application/json');

// Fonction utilitaire pour retourner une réponse JSON et arrêter le script
function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
  exit();
}

// Utiliser la connexion globale $connexion
$pdo = $connexion;

// Vérifier que l'action demandée est bien 'get_history'
if (!isset($_POST['action']) || $_POST['action'] !== 'get_history') {
  jsonResponse(false, 'Action non valide.');
}

try {
  // Récupérer et nettoyer les dates de début et de fin depuis la requête POST
  $startDate = isset($_POST['start_date']) && !empty($_POST['start_date']) ? $_POST['start_date'] : null;
  $endDate = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : null;

  // Requête SQL de base pour récupérer les données de la table radacct
  // CORRECTION : Le filtre 'WHERE 1=1' est utilisé pour afficher TOUS les utilisateurs, y compris 'bob'.
  $sql = "SELECT username, callingstationid, framedipaddress, acctstarttime, acctstoptime, acctsessiontime 
            FROM radacct 
            WHERE 1=1"; // Remplacé 'WHERE username LIKE 'v%'' par 'WHERE 1=1'

  $params = [];

  // Ajout des conditions de date si elles sont fournies
  if ($startDate) {
    $sql .= ' AND acctstarttime >= ?';
    $params[] = $startDate . ' 00:00:00';
  }

  if ($endDate) {
    $sql .= ' AND acctstarttime <= ?';
    $params[] = $endDate . ' 23:59:59';
  }

  // Ordonner les résultats du plus récent au plus ancien et limiter
  $sql .= ' ORDER BY acctstarttime DESC LIMIT 500';

  // Préparer et exécuter la requête SQL de manière sécurisée
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Renvoyer les données sous forme de réponse JSON
  jsonResponse(true, '', $history);
} catch (Exception $e) {
  // En cas d'erreur lors de l'exécution de la requête
  jsonResponse(false, 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage());
}
?>