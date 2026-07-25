<?php
require_once __DIR__ . '/connexion.php';
header('Content-Type: application/json');

$pdo = $connexion;

/*
 * Lecture des détections d'intrusion depuis la table `security_event`.
 *
 * Mapping de sévérité (security_event.security_status -> libellé frontend) :
 *   critical -> 'critical' (Critique)
 *   warning  -> 'medium'   (Moyenne)
 *   info     -> 'low'      (Faible)
 */

// Affichage : valeur d'enum -> badge de sévérité du dashboard
$severityDisplay = [
  'critical' => 'critical',
  'warning'  => 'medium',
  'info'     => 'low',
];

// Filtre inverse : sévérité du dashboard -> valeur d'enum (3 niveaux en base)
$severityFilter = [
  'critical' => 'critical',
  'high'     => 'warning',
  'medium'   => 'warning',
  'low'      => 'info',
];

function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Méthode non autorisée');
}

$action = $_POST['action'] ?? '';

if ($action === 'get_intrusions') {
  $severity = $_POST['severity'] ?? '';
  $type = $_POST['type'] ?? '';
  $date = $_POST['date'] ?? '';

  $sql = "SELECT
            id,
            event_type,
            security_status,
            COALESCE(source_ip::text, 'N/A')   AS source_ip,
            COALESCE(mac_address::text, 'N/A') AS mac_address,
            details->>'description'            AS description,
            details->>'source'                 AS source_info,
            created_at
          FROM security_event
          WHERE 1=1";
  $params = [];

  if ($severity !== '' && array_key_exists($severity, $severityFilter)) {
    $sql .= ' AND security_status = ?';
    $params[] = $severityFilter[$severity];
  }
  if ($type !== '') {
    $sql .= ' AND event_type = ?';
    $params[] = $type;
  }
  if ($date !== '') {
    $sql .= ' AND created_at::date = ?';
    $params[] = $date;
  }

  $sql .= ' ORDER BY created_at DESC LIMIT 500';

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) use ($severityDisplay) {
      $description = $row['description'];
      if ($description === null || $description === '') {
        // Repli lisible si le JSON details ne contient pas de description
        $description = ucfirst(str_replace('_', ' ', $row['event_type']));
      }
      return [
        'timestamp'   => date('d/m/Y H:i:s', strtotime($row['created_at'])),
        'type'        => $row['event_type'],
        'severity'    => $severityDisplay[$row['security_status']] ?? 'low',
        'ip_address'  => $row['source_ip'],
        'mac_address' => $row['mac_address'],
        'description' => $description,
        'source_info' => $row['source_info'] !== null && $row['source_info'] !== ''
          ? $row['source_info']
          : 'Autre',
      ];
    }, $rows);

    jsonResponse(true, '', $data);
  } catch (Exception $e) {
    jsonResponse(false, 'Erreur lors de la récupération des intrusions');
  }
} elseif ($action === 'get_stats') {
  try {
    $sql = "SELECT security_status, COUNT(*) AS nb
            FROM security_event
            GROUP BY security_status";
    $counts = array_column(
      $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC),
      'nb',
      'security_status'
    );

    jsonResponse(true, '', [
      'critical'   => (int) ($counts['critical'] ?? 0),
      'medium'     => (int) ($counts['warning'] ?? 0),
      'suspicious' => (int) ($counts['info'] ?? 0),
    ]);
  } catch (Exception $e) {
    jsonResponse(false, 'Erreur lors de la récupération des statistiques');
  }
} else {
  jsonResponse(false, 'Action non reconnue');
}
