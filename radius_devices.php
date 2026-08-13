<?php
ob_start();

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/connexion.php';

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

ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

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
        'error' => 'Action non spécifiée. Actions disponibles: get_devices, add_device, delete_device, test',
      ]);
  }
} catch (Exception $e) {
  ob_clean();
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'line' => $e->getLine(),
    'file' => basename($e->getFile()),
  ]);
}

ob_end_flush();
exit();

function normalizeMacAddress($macRaw)
{
  $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));

  if (strlen($cleanMac) !== 12) {
    throw new Exception("Format d'adresse MAC invalide (12 caractères hexadécimaux requis)");
  }

  return implode(':', str_split($cleanMac, 2));
}

function compactMacAddress($macRaw)
{
  return str_replace(':', '', normalizeMacAddress($macRaw));
}

function normalizedMacSqlWhere()
{
  return "regexp_replace(lower(username), '[^0-9a-f]', '', 'g') = ?";
}

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
    $sql = "SELECT 
                    MIN(rc.id) as id,
                    rc.username as mac_address,
                    MIN(rc.department) as department,
                    MAX(rg.groupname) as groupname,
                    MAX(rgr.value) as bandwidth_value
                FROM radcheck rc
                LEFT JOIN radusergroup rg ON LOWER(rc.username) = LOWER(rg.username)  
                LEFT JOIN radgroupreply rgr ON rg.groupname = rgr.groupname AND rgr.attribute = 'WISPr-Bandwidth-Max-Down'
                WHERE rc.department IS NOT NULL
                  AND (rc.username ~* '^([0-9a-f]{2}[:.-]?){5}[0-9a-f]{2}$' OR rc.username ~* '^[0-9a-f]{12}$')
                GROUP BY rc.username
                ORDER BY department, rc.username";

    $stmt = $connexion->query($sql);
    $devices = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      try {
        $displayMac = normalizeMacAddress($row['mac_address']);
      } catch (Exception $e) {
        $displayMac = strtolower((string) $row['mac_address']);
      }

      $devices[] = [
        'id' => $row['id'],
        'mac_address' => $displayMac,
        'department' => enumToShortcode($row['department']),
        'bandwidth' => $row['bandwidth_value'] ? round($row['bandwidth_value'] / 1000000) . ' Mbps' : 'N/A',
        'group' => $row['groupname'] ?: 'N/A',
      ];
    }

    echo json_encode(['success' => true, 'data' => $devices, 'count' => count($devices)]);
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur getDevices: ' . $e->getMessage()]);
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

function getDepartmentMap()
{
  return [
    'communication' => ['enum' => 'Communication', 'group' => 'communication_group'],
    'daj'           => ['enum' => 'Directeur des Affaires Juridiques', 'group' => 'daj_group'],
    'finance'       => ['enum' => 'Finance', 'group' => 'finance_group'],
    'rh'            => ['enum' => 'Ressources Humaines', 'group' => 'rh_group'],
    'sg'            => ['enum' => 'Secrétariat Général', 'group' => 'sg_group'],
  ];
}

function enumToShortcode($enumValue)
{
  foreach (getDepartmentMap() as $shortcode => $info) {
    if ($info['enum'] === $enumValue) {
      return $shortcode;
    }
  }
  return strtolower((string) $enumValue);
}

function deleteDevice(PDO $connexion)
{
  $macRaw = trim($_POST['mac_address'] ?? '');

  if (empty($macRaw)) {
    throw new Exception('Adresse MAC requise');
  }

  $mac = normalizeMacAddress($macRaw);
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
