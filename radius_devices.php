<?php
ob_start();

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/bandwidth.php';

session_start();

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    ob_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Session expirée']);
    ob_end_flush();
    exit();
}

try {
  $RADIUS_MAC_SECRET = get_radius_mac_secret();
} catch (Throwable $e) {
  $RADIUS_MAC_SECRET = '';
}

// C3 : actions en POST uniquement (plus d'action en GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ob_clean();
  http_response_code(405);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
  ob_end_flush();
  exit();
}

$action = $_POST['action'] ?? '';

// C2 : les actions d'écriture sont réservées aux administrateurs
if (in_array($action, ['add_device', 'delete_device'], true)) {
  $stmtRole = $connexion->prepare('SELECT role FROM users WHERE username = ?');
  $stmtRole->execute([$_SESSION['user'] ?? $_SESSION['nom_utilisateur']]);
  if ($stmtRole->fetchColumn() !== 'ADMIN') {
    ob_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Accès réservé aux administrateurs']);
    ob_end_flush();
    exit();
  }
}

// C3 : jeton CSRF
check_csrf();

ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
  if (!isset($connexion) || !$connexion) {
    throw new Exception('Connexion à la base de données échouée');
  }

  switch ($action) {
    case 'check_mac_status':
      checkMacStatus($connexion);
      break;

    case 'get_devices':
      getDevices($connexion);
      break;

    case 'bandwidth_status':
      getBandwidthStatus($connexion);
      break;

    case 'add_device':
      addDevice($connexion);
      break;

    case 'delete_device':
      deleteDevice($connexion);
      break;

    case 'test':
      echo json_encode([
        'success' => true,
        'message' => 'API RADIUS fonctionnelle',
        'timestamp' => date('Y-m-d H:i:s'),
      ]);
      break;

    default:
      echo json_encode([
        'success' => false,
        'error' => 'Action non spécifiée. Actions disponibles: get_devices, bandwidth_status, add_device, delete_device, test',
      ]);
  }
} catch (Exception $e) {
  ob_clean();
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
  ]);
}

ob_end_flush();
exit();

function checkMacStatus(PDO $connexion)
{
  $macRaw = trim($_POST['mac_address'] ?? '');
  if ($macRaw === '') {
    throw new Exception('Adresse MAC requise');
  }

  $mac = normalizeMacAddress($macRaw);
  if ($mac === false) {
    throw new Exception("Format d'adresse MAC invalide");
  }

  $macCompact = compactMacAddress($mac);
  $normalizedMacWhere = normalizedMacSqlWhere();

  $stmt = $connexion->prepare("SELECT attribute, value FROM radcheck WHERE $normalizedMacWhere");
  $stmt->execute([$macCompact]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $is_rejected = false;

  foreach ($rows as $row) {
    if ($row['attribute'] === 'Auth-Type' && $row['value'] === 'Reject') {
      $is_rejected = true;
    }
  }

  $stmt = $connexion->prepare("SELECT id FROM blacklist WHERE mac_address = ?::macaddr");
  $stmt->execute([$mac]);
  $in_blacklist = (bool) $stmt->fetchColumn();

  ob_clean();
  echo json_encode([
    'success' => true,
    'data' => [
      'exists'       => count($rows) > 0,
      'is_rejected'  => $is_rejected,
      'in_blacklist' => $in_blacklist,
    ],
  ]);
}

function getDevices(PDO $connexion)
{
  try {
    // La correspondance radcheck/radusergroup se fait sur la MAC normalisée :
    // un simple LOWER() ne rapproche pas « aabbccddeeff » de « aa:bb:cc:dd:ee:ff »
    // et l'appareil apparaissait alors sans groupe ni limite de débit.
    $sql = "SELECT 
                    MAX(rc.id) FILTER (WHERE rc.attribute = 'Cleartext-Password') AS id,
                    rc.username as mac_address,
                    MIN(rc.department) as department,
                    MAX(rg.groupname::text) as groupname,
                    MAX(rgr.value) FILTER (WHERE rgr.attribute = 'WISPr-Bandwidth-Max-Down') as bandwidth_down,
                    MAX(rgr.value) FILTER (WHERE rgr.attribute = 'WISPr-Bandwidth-Max-Up') as bandwidth_up
                FROM radcheck rc
                LEFT JOIN radusergroup rg
                       ON regexp_replace(lower(rc.username), '[^0-9a-z]', '', 'g')
                        = regexp_replace(lower(rg.username), '[^0-9a-z]', '', 'g')
                LEFT JOIN radgroupreply rgr
                       ON rg.groupname = rgr.groupname
                      AND rgr.attribute IN ('WISPr-Bandwidth-Max-Down', 'WISPr-Bandwidth-Max-Up')
                WHERE rc.department IS NOT NULL
                  AND (rc.username ~* '^([0-9a-f]{2}[:.-]?){5}[0-9a-f]{2}$' OR rc.username ~* '^[0-9a-f]{12}$')
                GROUP BY rc.username
                ORDER BY department, rc.username";

    $stmt = $connexion->query($sql);
    $devices = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $displayMac = normalizeMacAddress($row['mac_address']);
      if ($displayMac === false) {
        $displayMac = strtolower((string) $row['mac_address']);
      }

      // Un appareil sans groupe n'a AUCUNE limite appliquée par le NAS :
      // on le signale explicitement au lieu d'afficher un « N/A » ambigu.
      $hasGroup = !empty($row['groupname']);
      $down     = (int) ($row['bandwidth_down'] ?? 0);
      $up       = (int) ($row['bandwidth_up'] ?? 0);

      if (!$hasGroup) {
        $bandwidth = 'Non limité (sans groupe)';
      } elseif ($down <= 0) {
        $bandwidth = 'Non limité (profil absent)';
      } else {
        $bandwidth = formatBitsPerSecond($down);
      }

      $devices[] = [
        'id' => $row['id'],
        'mac_address' => $displayMac,
        'department' => enumToShortcode($row['department']),
        'bandwidth' => $bandwidth,
        'bandwidth_down' => $down,
        'bandwidth_up' => $up,
        'bandwidth_up_human' => $up > 0 ? formatBitsPerSecond($up) : '—',
        'bandwidth_ok' => $hasGroup && $down > 0,
        'group' => $row['groupname'] ?: 'Aucun',
      ];
    }

    echo json_encode(['success' => true, 'data' => $devices, 'count' => count($devices)]);
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur getDevices: ' . $e->getMessage()]);
  }
}

/**
 * Diagnostic en lecture seule de la limitation de vitesse.
 * Les débits ne sont pas modifiables ici : ils viennent de radgroupreply.
 */
function getBandwidthStatus(PDO $connexion)
{
  try {
    $report = bandwidthDiagnose($connexion);
    echo json_encode(['success' => true, 'data' => $report]);
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur diagnostic débit: ' . $e->getMessage()]);
  }
}

function addDevice(PDO $connexion)
{
  $macRaw = $_POST['mac_address'] ?? '';
  $department = $_POST['department'] ?? '';
  $force = ($_POST['force'] ?? '0') === '1';

  if (empty($macRaw) || empty($department)) {
    throw new Exception('Adresse MAC et département requis');
  }

  $mac = normalizeMacAddress($macRaw);
  if ($mac === false) {
    throw new Exception("Format d'adresse MAC invalide");
  }
  $macCompact = compactMacAddress($mac);

  $connexion->beginTransaction();

  try {
    $map = getDepartmentMap();
    if (!isset($map[$department])) {
      throw new Exception('Département invalide');
    }
    $deptEnum = $map[$department]['enum'];
    $groupname = $map[$department]['group'];

    $normalizedMacWhere = normalizedMacSqlWhere();
    $stmtReject = $connexion->prepare("SELECT COUNT(*) FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Auth-Type' AND value = 'Reject'");
    $stmtReject->execute([$macCompact]);
    $is_rejected = ((int) $stmtReject->fetchColumn()) > 0;

    if ($is_rejected && !$force) {
      if ($connexion->inTransaction()) $connexion->rollBack();
      ob_clean();
      echo json_encode(['success' => false, 'error' => 'APPAREIL_DEJA_BLOQUE', 'data' => ['mac_address' => $mac]]);
      return;
    }

    $stmtCheck = $connexion->prepare("SELECT COUNT(*) FROM radcheck WHERE $normalizedMacWhere");
    $stmtCheck->execute([$macCompact]);
    if ($stmtCheck->fetchColumn() > 0) {
      $stmtDel1 = $connexion->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere");
      $stmtDel1->execute([$macCompact]);
      $stmtDel2 = $connexion->prepare("DELETE FROM radusergroup WHERE $normalizedMacWhere");
      $stmtDel2->execute([$macCompact]);
    }

    if ($is_rejected && $force) {
      $stmtBl = $connexion->prepare("DELETE FROM blacklist WHERE mac_address = ?::macaddr");
      $stmtBl->execute([$mac]);
    }

    $RADIUS_MAC_SECRET = get_radius_mac_secret();

    if ($RADIUS_MAC_SECRET === '') {
      throw new Exception('RADIUS_MAC_SECRET non configuré dans le fichier .env');
    }

    $sql = "INSERT INTO radcheck (username, attribute, op, value, department) 
               VALUES (?, 'Cleartext-Password', ':=', ?, ?)";
    $stmt = $connexion->prepare($sql);
    $stmt->execute([$mac, $RADIUS_MAC_SECRET, $deptEnum]);

    $sql2 = "INSERT INTO radusergroup (username, groupname, priority) 
                 VALUES (?, ?, 1)";
    $stmt2 = $connexion->prepare($sql2);
    $stmt2->execute([$mac, $groupname]);

    $connexion->commit();
    echo json_encode(['success' => true, 'message' => 'Appareil ajouté et autorisé avec succès']);
  } catch (Exception $e) {
    if ($connexion->inTransaction()) {
      $connexion->rollBack();
    }
    throw new Exception("Erreur lors de l'ajout: " . $e->getMessage());
  }
}

function deleteDevice(PDO $connexion)
{
  $macRaw = trim($_POST['mac_address'] ?? '');

  if (empty($macRaw)) {
    throw new Exception('Adresse MAC requise');
  }

  $mac = normalizeMacAddress($macRaw);
  if ($mac === false) {
    throw new Exception("Format d'adresse MAC invalide");
  }
  $macCompact = compactMacAddress($mac);

  $connexion->beginTransaction();

  try {
    $normalizedMacWhere = normalizedMacSqlWhere();
    $sql1 = "DELETE FROM radusergroup WHERE $normalizedMacWhere";
    $stmt1 = $connexion->prepare($sql1);
    $stmt1->execute([$macCompact]);

    $sql2 = "DELETE FROM radcheck WHERE $normalizedMacWhere";
    $stmt2 = $connexion->prepare($sql2);
    $stmt2->execute([$macCompact]);

    $connexion->commit();
    echo json_encode(['success' => true, 'message' => 'Appareil supprimé avec succès']);
  } catch (Exception $e) {
    if ($connexion->inTransaction()) {
      $connexion->rollBack();
    }
    throw new Exception('Erreur lors de la suppression: ' . $e->getMessage());
  }
}
?>
