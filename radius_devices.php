<?php
// Démarrer la capture de sortie pour éviter les sorties parasites
ob_start();

// Inclure le chargeur .env puis la connexion à la base
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/connexion.php';

// Vérification de session
session_start();

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    ob_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Session expirée']);
    ob_end_flush();
    exit();
}

// Récupération du secret MAC via la fonction centralisée (comme pour la DB)
try {
  $RADIUS_MAC_SECRET = get_radius_mac_secret();
} catch (Throwable $e) {
  $RADIUS_MAC_SECRET = '';
}

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

/**
 * Normalise une adresse MAC au format attendu par FreeRADIUS et la base :
 *   xx:xx:xx:xx:xx:xx
 * Les saisies avec majuscules, tirets, points, espaces, etc. sont acceptées
 * tant qu'elles contiennent exactement 12 caractères hexadécimaux.
 */
function normalizeMacAddress($macRaw)
{
  $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));

  if (strlen($cleanMac) !== 12) {
    throw new Exception("Format d'adresse MAC invalide (12 caractères hexadécimaux requis)");
  }

  return implode(':', str_split($cleanMac, 2));
}

/**
 * Retourne la version sans séparateur d'une MAC normalisée, pour comparer
 * proprement avec d'anciennes valeurs stockées en base sous plusieurs formats.
 */
function compactMacAddress($macRaw)
{
  return str_replace(':', '', normalizeMacAddress($macRaw));
}

function normalizedMacSqlWhere()
{
  return "regexp_replace(lower(username), '[^0-9a-f]', '', 'g') = ?";
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

  if (empty($macRaw) || empty($department)) {
    throw new Exception('Adresse MAC et département requis');
  }

  // Format requis en base : xx:xx:xx:xx:xx:xx (minuscules avec deux-points)
  $mac = normalizeMacAddress($macRaw);
  $macCompact = compactMacAddress($mac);

  $connexion->beginTransaction();

  try {
    // Correspondance département : shortcode (frontend) -> ENUM (DB) + groupname
    $map = getDepartmentMap();
    if (!isset($map[$department])) {
      throw new Exception('Département invalide');
    }
    $deptEnum = $map[$department]['enum'];
    $groupname = $map[$department]['group'];

    // Vérifier si l'appareil existe déjà, quel que soit le format précédent
    // en base : f8:a2..., F8-A2..., f8a2..., etc.
    $normalizedMacWhere = normalizedMacSqlWhere();
    $stmtCheck = $connexion->prepare("SELECT COUNT(*) FROM radcheck WHERE $normalizedMacWhere");
    $stmtCheck->execute([$macCompact]);
    if ($stmtCheck->fetchColumn() > 0) {
      // Nettoyer les doublons potentiels existants avant ré-insertion propre
      $stmtDel1 = $connexion->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere");
      $stmtDel1->execute([$macCompact]);
      $stmtDel2 = $connexion->prepare("DELETE FROM radusergroup WHERE $normalizedMacWhere");
      $stmtDel2->execute([$macCompact]);
    }

    // === NOUVELLE MÉTHODE : RADIUS MAC Authentication (MAB) ===
    // Une seule ligne dans radcheck avec le secret partagé
    // Le secret est relu ici via env.php : une variable globale n'est pas
    // visible dans la portée d'une fonction en PHP.
    $RADIUS_MAC_SECRET = get_radius_mac_secret();

    if ($RADIUS_MAC_SECRET === '') {
      throw new Exception('RADIUS_MAC_SECRET non configuré dans le fichier .env');
    }

    $sql = "INSERT INTO radcheck (username, attribute, op, value, department) 
               VALUES (?, 'Cleartext-Password', ':=', ?, ?)";
    $stmt = $connexion->prepare($sql);
    $stmt->execute([$mac, $RADIUS_MAC_SECRET, $deptEnum]);

    // Associer au groupe départemental dans radusergroup
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
    'daj'           => ['enum' => 'Directeur des Affaires Juridiques', 'group' => 'daj_group'],
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

  $mac = normalizeMacAddress($macRaw);
  $macCompact = compactMacAddress($mac);

  $connexion->beginTransaction();

  try {
    // 1. Supprimer de radusergroup
    // NB : sous PostgreSQL les littéraux de chaîne doivent être entre APOSTROPHES ('...').
    // Les guillemets doubles ("...") sont réservés aux identifiants (noms de colonnes) et
    // provoquaient l'erreur "column "-" does not exist" lors de la suppression.
    $normalizedMacWhere = normalizedMacSqlWhere();
    $sql1 = "DELETE FROM radusergroup WHERE $normalizedMacWhere";
    $stmt1 = $connexion->prepare($sql1);
    $stmt1->execute([$macCompact]);

    // 2. Supprimer de radcheck
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
