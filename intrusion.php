<?php
require_once __DIR__ . '/connexion.php';

session_start();

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

// Vérifier que l'utilisateur est ADMIN
$pdo_temp = $connexion;
$stmt_role = $pdo_temp->prepare("SELECT role FROM users WHERE username = ?");
$stmt_role->execute([$_SESSION['user'] ?? $_SESSION['nom_utilisateur']]);
$user_role = $stmt_role->fetchColumn();

if ($user_role !== 'ADMIN') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Accès réservé aux administrateurs']);
    exit();
}

header('Content-Type: application/json');

$pdo = $connexion;

/*
 * Lecture des détections d'intrusion depuis la table `security_event`.
 *
 * Mapping de sévérité (security_event.security_status -> libellé frontend) :
 *   critical -> 'critical' (Critique)
 *   warning  -> 'medium'   (Moyenne)
 *   info     -> 'low'      (Faible)
 *
 * L'action auto_block_intrusion permet au script snort_sync.php
 * d'insérer les alertes Snort et de bloquer automatiquement
 * les appareils en cas de sévérité critical ou warning.
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
  'medium'   => 'warning',
  'low'      => 'info',
];

function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
  exit();
}

function isValidMac($mac)
{
  // Accepte les formats AA:BB:CC:DD:EE:FF, AA-BB-CC-DD-EE-FF, AABB.CCDD.EEFF
  return (bool) preg_match('/^([0-9A-Fa-f]{2}[:\-\.]){5}[0-9A-Fa-f]{2}$/', $mac);
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
      'critical' => (int) ($counts['critical'] ?? 0),
      'medium'   => (int) ($counts['warning'] ?? 0),
      'low'      => (int) ($counts['info'] ?? 0),
    ]);
  } catch (Exception $e) {
    jsonResponse(false, 'Erreur lors de la récupération des statistiques');
  }
} elseif ($action === 'auto_block_intrusion') {
    // Cette action est appelée par le script cron qui lit les alertes Snort de pfSense
    // Elle insère dans security_event, puis bloque automatiquement dans blacklist + radcheck

    $event_type = trim($_POST['event_type'] ?? '');
    $severity   = trim($_POST['severity'] ?? '');     // critical, warning, info
    $source_ip  = trim($_POST['source_ip'] ?? '');
    $mac_address = trim($_POST['mac_address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $source_info = trim($_POST['source_info'] ?? 'Snort');
    $attempts    = max(1, (int) ($_POST['attempts'] ?? 1));

    // Validation
    if ($event_type === '' || $severity === '') {
      jsonResponse(false, 'event_type et severity sont obligatoires');
    }
    if (!in_array($severity, ['critical', 'warning', 'info'])) {
      jsonResponse(false, 'Sévérité invalide (critical, warning ou info)');
    }
    if ($mac_address !== '' && !isValidMac($mac_address)) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    try {
      $pdo->beginTransaction();

      // 1. Insérer dans security_event
      $stmt = $pdo->prepare("INSERT INTO security_event (event_type, security_status, source_ip, mac_address, details, attempts)
                VALUES (?, ?, ?::inet, ?::macaddr, ?, ?)");
      $details = json_encode(['description' => $description, 'source' => $source_info]);
      $stmt->execute([$event_type, $severity, $source_ip ?: null, $mac_address ?: null, $details, $attempts]);

      // 2. Auto-blocage : si la sévérité est critical ou warning, bloquer automatiquement
      if (($severity === 'critical' || $severity === 'warning') && $mac_address !== '') {

        // Vérifier si déjà dans blacklist
        $stmt = $pdo->prepare("SELECT id FROM blacklist WHERE mac_address = ?::macaddr");
        $stmt->execute([$mac_address]);
        $already_blocked = $stmt->fetchColumn();

        if (!$already_blocked) {
          // Ajouter dans blacklist
          $stmt = $pdo->prepare("INSERT INTO blacklist (mac_address, reason) VALUES (?::macaddr, ?)");
          $auto_reason = 'Auto-blocage: intrusion ' . $event_type . ' (' . $severity . ')';
          $stmt->execute([$mac_address, $auto_reason]);

          // Bloquer dans radcheck
          $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Auth-Type'");
          $stmt->execute([$mac_address]);
          $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')");
          $stmt->execute([$mac_address]);
        }
      }

      $pdo->commit();
      jsonResponse(true, 'Intrusion enregistrée' . (($severity === 'critical' || $severity === 'warning') && $mac_address !== '' ? ' et appareil bloqué' : ''));
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      jsonResponse(false, "Erreur lors de l'enregistrement de l'intrusion");
    }
} else {
  jsonResponse(false, 'Action non reconnue');
}
