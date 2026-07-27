<?php
// Démarrer la capture de sortie pour éviter les sorties parasites
ob_start();

// Inclure la connexion à la base
require_once './connexion.php';

// Nettoyer toute sortie parasite avant d'envoyer les headers
ob_clean();

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
  // Vérifier la connexion à la base de données
  if (!isset($connexion) || !$connexion) {
    throw new Exception('Connexion à la base de données échouée');
  }

  switch ($action) {
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
      // Ajouter un test simple
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
  // S'assurer qu'on retourne du JSON même en cas d'erreur
  ob_clean();
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'line' => $e->getLine(),
    'file' => basename($e->getFile()),
  ]);
}

// Nettoyer et terminer
ob_end_flush();
exit();

function getDevices(PDO $connexion)
{
  try {
    // Utilisation de GROUP BY pour éviter les doublons si plusieurs attributs radcheck existent pour une même MAC
    $sql = "SELECT 
                    MIN(rc.id) as id,
                    rc.username as mac_address,
                    MIN(rc.department) as department,
                    MAX(rg.groupname) as groupname,
                    MAX(rgr.value) as bandwidth_value
                FROM radcheck rc
                LEFT JOIN radusergroup rg ON LOWER(rc.username) = LOWER(rg.username)  
                LEFT JOIN radgroupreply rgr ON rg.groupname = rgr.groupname AND rgr.attribute = 'WISPr-Bandwidth-Max-Down'
                GROUP BY rc.username
                ORDER BY department, rc.username";

    $stmt = $connexion->query($sql);

    $devices = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $devices[] = [
        'id' => $row['id'],
        'mac_address' => $row['mac_address'],
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

  if (empty($macRaw) || empty($department)) {
    throw new Exception('Adresse MAC et département requis');
  }

  // Nettoyer et valider les 12 caractères hexadécimaux
  $cleanMac = preg_replace('/[^a-fA-F0-9]/', '', $macRaw);
  if (strlen($cleanMac) !== 12) {
    throw new Exception("Format d'adresse MAC invalide (12 caractères hexadécimaux requis)");
  }

  // Format requis : XX:XX:XX:XX:XX:XX (majuscules avec deux-points)
  $mac = strtoupper(implode(':', str_split($cleanMac, 2)));

  $connexion->beginTransaction();

  try {
    // Correspondance département : shortcode (frontend) -> ENUM (DB) + groupname
    $map = getDepartmentMap();
    if (!isset($map[$department])) {
      throw new Exception('Département invalide');
    }
    $deptEnum = $map[$department]['enum'];
    $groupname = $map[$department]['group'];

    // Vérifier si l'appareil existe déjà (quel que soit le format précédent: tirets, points ou deux-points)
    $stmtCheck = $connexion->prepare("SELECT COUNT(*) FROM radcheck WHERE REPLACE(REPLACE(UPPER(username), '-', ':'), '.', ':') = ?");
    $stmtCheck->execute([$mac]);
    if ($stmtCheck->fetchColumn() > 0) {
      // Nettoyer les doublons potentiels existants avant ré-insertion propre
      $stmtDel1 = $connexion->prepare("DELETE FROM radcheck WHERE REPLACE(REPLACE(UPPER(username), '-', ':'), '.', ':') = ?");
      $stmtDel1->execute([$mac]);
      $stmtDel2 = $connexion->prepare("DELETE FROM radusergroup WHERE REPLACE(REPLACE(UPPER(username), '-', ':'), '.', ':') = ?");
      $stmtDel2->execute([$mac]);
    }

    // 1. Ajouter Auth-Type dans radcheck (pour acceptation par défaut FreeRADIUS)
    $sql1 = "INSERT INTO radcheck (username, attribute, op, value, department) 
                 VALUES (?, 'Auth-Type', ':=', 'Accept', ?)";
    $stmt1 = $connexion->prepare($sql1);
    $stmt1->execute([$mac, $deptEnum]);

    // 2. Ajouter Cleartext-Password dans radcheck (pour compatibilité PAP / pfSense MAB)
    $sql1_bis = "INSERT INTO radcheck (username, attribute, op, value, department) 
                 VALUES (?, 'Cleartext-Password', ':=', ?, ?)";
    $stmt1_bis = $connexion->prepare($sql1_bis);
    $stmt1_bis->execute([$mac, $mac, $deptEnum]);

    // 3. Associer au groupe départemental dans radusergroup
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

/**
 * Correspondance entre le shortcode envoyé par le frontend et :
 *   - la valeur de l'ENUM department_enum (colonne radcheck.department)
 *   - le groupname de l'ENUM groupname_enum (colonne radusergroup.groupname)
 */
function getDepartmentMap()
{
  return [
    'communication' => ['enum' => 'Communication', 'group' => 'communication_group'],
    'daj'           => ['enum' => 'Directeur des Affaires Juridiques', 'group' => 'daj_groupe'],
    'finance'       => ['enum' => 'Finance', 'group' => 'finance_group'],
    'rh'            => ['enum' => 'Ressources Humaines', 'group' => 'rh_group'],
    'sg'            => ['enum' => 'Secrétariat Général', 'group' => 'sg_group'],
  ];
}

/**
 * Convertit une valeur de l'ENUM department_enum vers le shortcode attendu par le frontend.
 */
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

  $cleanMac = preg_replace('/[^a-fA-F0-9]/', '', $macRaw);
  if (strlen($cleanMac) !== 12) {
    throw new Exception("Format d'adresse MAC invalide");
  }
  $mac = strtoupper(implode(':', str_split($cleanMac, 2)));

  $connexion->beginTransaction();

  try {
    // 1. Supprimer de radusergroup
    $sql1 = 'DELETE FROM radusergroup WHERE REPLACE(REPLACE(UPPER(username), "-", ":"), ".", ":") = ?';
    $stmt1 = $connexion->prepare($sql1);
    $stmt1->execute([$mac]);

    // 2. Supprimer de radcheck
    $sql2 = 'DELETE FROM radcheck WHERE REPLACE(REPLACE(UPPER(username), "-", ":"), ".", ":") = ?';
    $stmt2 = $connexion->prepare($sql2);
    $stmt2->execute([$mac]);

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
