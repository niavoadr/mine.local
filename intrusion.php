<?php
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/env.php';

session_start();

$pdo = $connexion;

$action = $_POST['action'] ?? '';

$severityDisplay = [
  'critical' => 'critical',
  'warning'  => 'medium',
  'info'     => 'low',
];

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

function normalizeMacAddress($macRaw)
{
  $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));
  if (strlen($cleanMac) !== 12) {
    return false;
  }
  return implode(':', str_split($cleanMac, 2));
}

function compactMacAddress($mac)
{
  return str_replace(':', '', normalizeMacAddress($mac));
}

function normalizedMacSqlWhere()
{
  return "regexp_replace(lower(username), '[^0-9a-f]', '', 'g') = ?";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Méthode non autorisée');
}

$is_admin_session = false;
$is_cron_api      = false;

$cron_api_token = env('CRON_API_TOKEN', '');
if ($cron_api_token !== '' && isset($_POST['cron_token']) && $_POST['cron_token'] === $cron_api_token) {
    $is_cron_api = true;
}

if (!$is_cron_api) {
    if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expirée']);
        exit();
    }

    $stmt_role = $pdo->prepare("SELECT role FROM users WHERE username = ?");
    $stmt_role->execute([$_SESSION['user'] ?? $_SESSION['nom_utilisateur']]);
    $user_role = $stmt_role->fetchColumn();

    if ($user_role !== 'ADMIN') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Accès réservé aux administrateurs']);
        exit();
    }

    $is_admin_session = true;
}

if ($is_cron_api && $action !== 'auto_block_intrusion') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Le jeton cron ne permet que auto_block_intrusion']);
    exit();
}
if ($is_admin_session && $action === 'auto_block_intrusion') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Action auto_block_intrusion réservée au cron']);
    exit();
}

header('Content-Type: application/json');


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
        $description = ucfirst(str_replace('_', ' ', $row['event_type']));
      }
      $displayMac = $row['mac_address'];
      if ($displayMac !== 'N/A') {
        $norm = normalizeMacAddress($displayMac);
        if ($norm !== false) {
          $displayMac = $norm;
        }
      }
      return [
        'timestamp'   => date('d/m/Y H:i:s', strtotime($row['created_at'])),
        'type'        => $row['event_type'],
        'severity'    => $severityDisplay[$row['security_status']] ?? 'low',
        'ip_address'  => $row['source_ip'],
        'mac_address' => $displayMac,
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

    $event_type = trim($_POST['event_type'] ?? '');
    $severity   = trim($_POST['severity'] ?? '');
    $source_ip  = trim($_POST['source_ip'] ?? '');
    $mac_address = trim($_POST['mac_address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $source_info = trim($_POST['source_info'] ?? 'Snort');
    $attempts    = max(1, (int) ($_POST['attempts'] ?? 1));
    $isFail2ban  = strcasecmp($source_info, 'Fail2ban') === 0;

    if ($event_type === '' || $severity === '') {
      jsonResponse(false, 'event_type et severity sont obligatoires');
    }
    if (!in_array($severity, ['critical', 'warning', 'info'])) {
      jsonResponse(false, 'Sévérité invalide (critical, warning ou info)');
    }

    $macCompact = '';
    if ($mac_address !== '') {
      $macNormalized = normalizeMacAddress($mac_address);
      if ($macNormalized === false) {
        jsonResponse(false, "Format d'adresse MAC invalide");
      }
      $macCompact = compactMacAddress($mac_address);
      $mac_address = $macNormalized;
    }

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("INSERT INTO security_event (event_type, security_status, source_ip, mac_address, details, attempts)
                VALUES (?, ?, ?::inet, ?::macaddr, ?, ?)");
      $details = json_encode(['description' => $description, 'source' => $source_info]);
      $stmt->execute([$event_type, $severity, $source_ip ?: null, $mac_address ?: null, $details, $attempts]);

      $macBlocked = false;
      if ($isFail2ban && $severity === 'critical' && $mac_address !== '') {

        $stmt = $pdo->prepare("SELECT id FROM blacklist WHERE mac_address = ?::macaddr");
        $stmt->execute([$mac_address]);
        $already_blocked = $stmt->fetchColumn();

        if (!$already_blocked) {
          $stmt = $pdo->prepare("INSERT INTO blacklist (mac_address, reason) VALUES (?::macaddr, ?)");
          $auto_reason = 'Fail2ban: ' . $event_type . ' (' . $severity . ')';
          $stmt->execute([$mac_address, $auto_reason]);

          $normalizedMacWhere = normalizedMacSqlWhere();
          $stmt = $pdo->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Auth-Type'");
          $stmt->execute([$macCompact]);
          $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')");
          $stmt->execute([$mac_address]);
        }
        $macBlocked = true;
      }

      $pdo->commit();

      if (!$isFail2ban) {
        $message = 'Intrusion enregistrée';
      } elseif ($macBlocked) {
        $message = 'Intrusion enregistrée et appareil bloqué';
      } elseif ($severity === 'warning' || $severity === 'critical') {
        $message = $severity === 'critical'
          ? 'Intrusion enregistrée (IP bloquée par Fail2ban, MAC inconnue)'
          : 'Intrusion enregistrée (IP bloquée par Fail2ban)';
      } else {
        $message = 'Intrusion enregistrée';
      }

      jsonResponse(true, $message);
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      jsonResponse(false, "Erreur lors de l'enregistrement de l'intrusion");
    }
} else {
  jsonResponse(false, 'Action non reconnue');
}
