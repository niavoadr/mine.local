<?php
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/env.php';

session_start();

$pdo = $connexion;

// Lire l'action tôt (nécessaire pour les vérifications d'authentification)
$action = $_POST['action'] ?? '';

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
 *
 * Les adresses MAC sont normalisées via normalizeMacAddress() (même
 * méthode que radius_devices.php et blacklist.php) pour éviter les
 * problèmes de casse et de format.
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

/**
 * Normalise une adresse MAC au format xx:xx:xx:xx:xx:xx (minuscules).
 * Même méthode que radius_devices.php et blacklist.php.
 */
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

// ============ AUTHENTIFICATION ============
// Deux modes d'authentification :
// 1. Session ADMIN (utilisateur connecté via le dashboard)
// 2. Jeton API CRON (pour snort_sync.php qui tourne sans session)
$is_admin_session = false;
$is_cron_api      = false;

// Vérifier le jeton API cron (prioritaire pour auto_block_intrusion)
$cron_api_token = env('CRON_API_TOKEN', '');
if ($cron_api_token !== '' && isset($_POST['cron_token']) && $_POST['cron_token'] === $cron_api_token) {
    $is_cron_api = true;
}

// Vérifier la session ADMIN
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

// L'action auto_block_intrusion est réservée au cron (jeton API)
// Les autres actions sont réservées à la session ADMIN
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

// ============ ACTIONS ============

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
      // Afficher la MAC normalisée
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

    // Normaliser la MAC si fournie (même méthode que radius_devices.php et blacklist.php)
    $macCompact = '';
    if ($mac_address !== '') {
      $macNormalized = normalizeMacAddress($mac_address);
      if ($macNormalized === false) {
        jsonResponse(false, "Format d'adresse MAC invalide");
      }
      $macCompact = compactMacAddress($mac_address);
      // Utiliser la MAC normalisée pour toutes les opérations
      $mac_address = $macNormalized;
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

        // Vérifier si déjà dans blacklist (MACADDR : insensible à la casse)
        $stmt = $pdo->prepare("SELECT id FROM blacklist WHERE mac_address = ?::macaddr");
        $stmt->execute([$mac_address]);
        $already_blocked = $stmt->fetchColumn();

        if (!$already_blocked) {
          // Ajouter dans blacklist
          $stmt = $pdo->prepare("INSERT INTO blacklist (mac_address, reason) VALUES (?::macaddr, ?)");
          $auto_reason = 'Auto-blocage: intrusion ' . $event_type . ' (' . $severity . ')';
          $stmt->execute([$mac_address, $auto_reason]);

          // Bloquer dans radcheck via comparaison insensible à la casse/format
          $normalizedMacWhere = normalizedMacSqlWhere();
          $stmt = $pdo->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Auth-Type'");
          $stmt->execute([$macCompact]);
          // Insérer avec la MAC normalisée
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
