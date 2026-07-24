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
    // rc.department est un DEPARTMENT_ENUM (Finance, Ressources Humaines, ...).
    // Le front-end attend les codes courts (finance, rh, daj, communication, sg),
    // on traduit donc la valeur de l'ENUM vers le code court dans la requête.
    $sql = "SELECT 
                    rc.id,
                    rc.username as mac_address,
                    CASE rc.department
                        WHEN 'Finance' THEN 'finance'
                        WHEN 'Ressources Humaines' THEN 'rh'
                        WHEN 'Directeur des Affaires Juridiques' THEN 'daj'
                        WHEN 'Communication' THEN 'communication'
                        WHEN 'Secrétariat Général' THEN 'sg'
                        ELSE lower(rc.department::text)
                    END as department,
                    rg.groupname,
                    rgr.attribute,
                    rgr.value
                FROM radcheck rc
                LEFT JOIN radusergroup rg ON rc.username = rg.username  
                LEFT JOIN radgroupreply rgr ON rg.groupname = rgr.groupname
                WHERE rgr.attribute = 'WISPr-Bandwidth-Max-Down'
                ORDER BY rc.department, rc.username";

    $stmt = $connexion->query($sql);

    $devices = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $devices[] = [
        'id' => $row['id'],
        'mac_address' => $row['mac_address'],
        'department' => $row['department'],
        'bandwidth' => $row['value'] ? round($row['value'] / 1000000) . ' Mbps' : 'N/A',
        'group' => $row['groupname'],
      ];
    }

    echo json_encode(['success' => true, 'data' => $devices, 'count' => count($devices)]);
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur getDevices: ' . $e->getMessage()]);
  }
}

function addDevice(PDO $connexion)
{
  $mac = $_POST['mac_address'] ?? '';
  $department = $_POST['department'] ?? '';

  if (empty($mac) || empty($department)) {
    throw new Exception('Adresse MAC et département requis');
  }

  // Validation format MAC
  if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
    throw new Exception("Format d'adresse MAC invalide");
  }

  $connexion->beginTransaction();

  try {
    // Traduire le code court reçu du front-end vers les valeurs des ENUM
    // de la nouvelle base : radcheck.department (DEPARTMENT_ENUM) et
    // radusergroup.groupname (GROUPNAME_ENUM).
    $resolved = resolveDepartment($department);
    if ($resolved === null) {
      throw new Exception('Département invalide');
    }

    // 1. Ajouter dans radcheck
    $sql1 = "INSERT INTO radcheck (username, attribute, op, value, department) 
                 VALUES (?, 'Auth-Type', ':=', 'Accept', ?)";
    $stmt1 = $connexion->prepare($sql1);
    $stmt1->execute([$mac, $resolved['department']]);

    // 2. Associer au groupe départemental
    $groupname = $resolved['groupname'];
    $sql2 = "INSERT INTO radusergroup (username, groupname, priority) 
                 VALUES (?, ?, 1)";
    $stmt2 = $connexion->prepare($sql2);
    $stmt2->execute([$mac, $groupname]);

    $connexion->commit();
    echo json_encode(['success' => true, 'message' => 'Appareil ajouté avec succès']);
  } catch (Exception $e) {
    $connexion->rollBack();
    throw new Exception("Erreur lors de l'ajout: " . $e->getMessage());
  }
}

function deleteDevice(PDO $connexion)
{
  $mac = $_POST['mac_address'] ?? '';

  if (empty($mac)) {
    throw new Exception('Adresse MAC requise');
  }

  $connexion->beginTransaction();

  try {
    // 1. Supprimer de radusergroup
    $sql1 = 'DELETE FROM radusergroup WHERE username = ?';
    $stmt1 = $connexion->prepare($sql1);
    $stmt1->execute([$mac]);

    // 2. Supprimer de radcheck
    $sql2 = 'DELETE FROM radcheck WHERE username = ?';
    $stmt2 = $connexion->prepare($sql2);
    $stmt2->execute([$mac]);

    $connexion->commit();
    echo json_encode(['success' => true, 'message' => 'Appareil supprimé avec succès']);
  } catch (Exception $e) {
    $connexion->rollBack();
    throw new Exception('Erreur lors de la suppression: ' . $e->getMessage());
  }
}

/**
 * Fait correspondre le code court de département (envoyé par le front-end)
 * aux valeurs exactes des types ENUM de la nouvelle base de données :
 *   - DEPARTMENT_ENUM  -> colonne radcheck.department
 *   - GROUPNAME_ENUM   -> colonne radusergroup.groupname
 *
 * Accepte aussi directement une valeur complète du DEPARTMENT_ENUM.
 *
 * @return array{department:string,groupname:string}|null
 */
function resolveDepartment($input)
{
  $map = [
    'communication' => ['department' => 'Communication', 'groupname' => 'communication_group'],
    'daj' => ['department' => 'Directeur des Affaires Juridiques', 'groupname' => 'daj_groupe'],
    'finance' => ['department' => 'Finance', 'groupname' => 'finance_group'],
    'rh' => ['department' => 'Ressources Humaines', 'groupname' => 'rh_group'],
    'sg' => ['department' => 'Secrétariat Général', 'groupname' => 'sg_group'],
  ];

  $key = strtolower(trim((string) $input));
  if (isset($map[$key])) {
    return $map[$key];
  }

  // Tolérer la saisie directe d'une valeur du DEPARTMENT_ENUM
  foreach ($map as $entry) {
    if (strcasecmp($entry['department'], (string) $input) === 0) {
      return $entry;
    }
  }

  return null;
}
?>
