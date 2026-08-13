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

function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
  exit();
}

$pdo = $connexion;

if (!isset($_POST['action']) || $_POST['action'] !== 'get_history') {
  jsonResponse(false, 'Action non valide.');
}

try {
  $startDate = isset($_POST['start_date']) && !empty($_POST['start_date']) ? $_POST['start_date'] : null;
  $endDate = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : null;

  $sql = "SELECT username, callingstationid, framedipaddress, acctstarttime, acctstoptime, acctsessiontime 
            FROM radacct 
            WHERE 1=1";

  $params = [];

  if ($startDate) {
    $sql .= ' AND acctstarttime >= ?';
    $params[] = $startDate . ' 00:00:00';
  }

  if ($endDate) {
    $sql .= ' AND acctstarttime <= ?';
    $params[] = $endDate . ' 23:59:59';
  }

  $sql .= ' ORDER BY acctstarttime DESC LIMIT 500';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

  jsonResponse(true, '', $history);
} catch (Exception $e) {
  jsonResponse(false, 'Erreur lors de la récupération de l\'historique: ' . $e->getMessage());
}
?>