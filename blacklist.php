<?php
require_once __DIR__ . '/connexion.php';

session_start();

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

check_csrf();

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Méthode non autorisée');
}

$action = $_POST['action'] ?? '';

switch ($action) {

  case 'check_mac_status':
    $macRaw = trim($_POST['mac_address'] ?? '');
    if ($macRaw === '') {
      jsonResponse(false, 'Adresse MAC requise');
    }

    $mac = normalizeMacAddress($macRaw);
    if ($mac === false) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    $macCompact = compactMacAddress($macRaw);
    $normalizedMacWhere = normalizedMacSqlWhere();

    try {
      $stmt = $pdo->prepare("SELECT attribute, value FROM radcheck WHERE $normalizedMacWhere");
      $stmt->execute([$macCompact]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $is_authorized = false;
      $is_blocked    = false;
      $department    = null;

      foreach ($rows as $row) {
        if ($row['attribute'] === 'Cleartext-Password') {
          $is_authorized = true;
        }
        if ($row['attribute'] === 'Auth-Type' && $row['value'] === 'Reject') {
          $is_blocked = true;
        }
      }

      if ($is_authorized) {
        $stmt = $pdo->prepare("SELECT department FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Cleartext-Password'");
        $stmt->execute([$macCompact]);
        $department = $stmt->fetchColumn();
      }

      $stmt = $pdo->prepare("SELECT id FROM blacklist WHERE mac_address = ?::macaddr");
      $stmt->execute([$mac]);
      $in_blacklist = (bool) $stmt->fetchColumn();

      jsonResponse(true, '', [
        'exists'        => count($rows) > 0,
        'is_authorized' => $is_authorized,
        'is_blocked'    => $is_blocked,
        'in_blacklist'  => $in_blacklist,
        'department'    => $department,
      ]);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors de la vérification');
    }
    break;

  case 'get_blacklist':
    try {
      $sql = "SELECT
                b.id,
                b.mac_address::text AS mac_address,
                COALESCE((
                  SELECT se.source_ip::text
                  FROM security_event se
                  WHERE se.mac_address = b.mac_address
                    AND se.source_ip IS NOT NULL
                  ORDER BY se.created_at DESC
                  LIMIT 1
                ), 'N/A') AS ip_address,
                b.reason,
                b.blocked_at AS blocked_date,
                COALESCE((
                  SELECT SUM(se.attempts)
                  FROM security_event se
                  WHERE se.mac_address = b.mac_address
                ), 0) AS blocked_attempts
              FROM blacklist b
              ORDER BY b.blocked_at DESC";
      $stmt = $pdo->query($sql);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $data = array_map(function ($row) {
        $displayMac = normalizeMacAddress($row['mac_address']);
        if ($displayMac === false) {
          $displayMac = strtolower($row['mac_address']);
        }
        return [
          'mac_address'      => $displayMac,
          'ip_address'       => $row['ip_address'],
          'reason'           => $row['reason'],
          'blocked_date'     => $row['blocked_date'],
          'blocked_attempts' => (int) $row['blocked_attempts'],
        ];
      }, $rows);

      jsonResponse(true, '', $data);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement de la liste noire');
    }
    break;

  case 'get_stats':
    try {
      $sql = "SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE blocked_at::date = current_date) AS today
              FROM blacklist";
      $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
      jsonResponse(true, '', [
        'total' => (int) $row['total'],
        'today' => (int) $row['today'],
      ]);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des statistiques');
    }
    break;

  case 'add_blacklist':
    $macRaw  = trim($_POST['mac_address'] ?? '');
    $reason  = trim($_POST['reason'] ?? '');
    $force   = ($_POST['force'] ?? '0') === '1';

    if ($macRaw === '' || $reason === '') {
      jsonResponse(false, 'Adresse MAC et raison sont obligatoires');
    }

    $mac = normalizeMacAddress($macRaw);
    if ($mac === false) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    $macCompact = compactMacAddress($macRaw);
    $normalizedMacWhere = normalizedMacSqlWhere();

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("SELECT COUNT(*) FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Cleartext-Password'");
      $stmt->execute([$macCompact]);
      $is_authorized = ((int) $stmt->fetchColumn()) > 0;

      if ($is_authorized && !$force) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'APPAREIL_DEJA_AUTORISE', [
          'mac_address' => $mac,
        ]);
      }

      if ($is_authorized && $force) {
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere");
        $stmt->execute([$macCompact]);

        $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE $normalizedMacWhere");
        $stmt->execute([$macCompact]);
      }

      $stmt = $pdo->prepare("SELECT id FROM blacklist WHERE mac_address = ?::macaddr");
      $stmt->execute([$mac]);
      $existing = $stmt->fetchColumn();

      if ($existing) {
        $stmt = $pdo->prepare("UPDATE blacklist
                  SET reason = ?, blocked_at = now()
                  WHERE mac_address = ?::macaddr");
        $stmt->execute([$reason, $mac]);
      } else {
        $stmt = $pdo->prepare("INSERT INTO blacklist (mac_address, reason)
                  VALUES (?::macaddr, ?)");
        $stmt->execute([$mac, $reason]);
      }

      $stmt = $pdo->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Auth-Type'");
      $stmt->execute([$macCompact]);

      $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Auth-Type', ':=', 'Reject')");
      $stmt->execute([$mac]);

      $pdo->commit();
      jsonResponse(true, 'Appareil ajouté à la liste noire et bloqué sur le réseau');
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      jsonResponse(false, "Erreur lors de l'ajout à la liste noire");
    }
    break;

  case 'remove_blacklist':
    $macRaw = trim($_POST['mac_address'] ?? '');
    if ($macRaw === '') {
      jsonResponse(false, 'Adresse MAC requise');
    }

    $mac = normalizeMacAddress($macRaw);
    if ($mac === false) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    $macCompact = compactMacAddress($macRaw);
    $normalizedMacWhere = normalizedMacSqlWhere();

    try {
      $pdo->beginTransaction();

      $stmt = $pdo->prepare("DELETE FROM blacklist WHERE mac_address = ?::macaddr");
      $stmt->execute([$mac]);

      $stmt = $pdo->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Auth-Type' AND value = 'Reject'");
      $stmt->execute([$macCompact]);

      $pdo->commit();
      jsonResponse(true, 'Appareil débloqué avec succès sur le réseau');
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      jsonResponse(false, 'Erreur lors du déblocage');
    }
    break;

  default:
    jsonResponse(false, 'Action non reconnue');
    break;
}
