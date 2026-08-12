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
 * Gestion de la liste noire (table `blacklist`).
 *
 * L'adresse IP et le nombre de tentatives proviennent directement de la
 * table `security_event` (colonne `source_ip` pour l'IP, colonne
 * `attempts` pour les tentatives), agrégées par adresse MAC.
 *
 * Le blocage est effectif : ajout/suppression dans radcheck avec
 * Auth-Type := Reject pour que FreeRADIUS rejette l'appareil.
 *
 * Avant de bloquer, on vérifie dans radcheck si l'appareil est déjà
 * autorisé (Cleartext-Password). Si oui, l'interface demande une
 * confirmation et supprime l'autorisation avant de bloquer.
 *
 * Les adresses MAC sont normalisées via normalizeMacAddress() (même
 * méthode que radius_devices.php) pour éviter les problèmes de casse
 * et de format.
 */

function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
  exit();
}

/**
 * Normalise une adresse MAC au format xx:xx:xx:xx:xx:xx (minuscules).
 * Identique à radius_devices.php.
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

$action = $_POST['action'] ?? '';

switch ($action) {

  // ============ Vérifier le statut d'une MAC dans radcheck ============
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
      // Vérifier si la MAC existe dans radcheck et quel attribut elle a
      $stmt = $pdo->prepare("SELECT attribute, value FROM radcheck WHERE $normalizedMacWhere");
      $stmt->execute([$macCompact]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $is_authorized = false;   // Cleartext-Password → appareil autorisé
      $is_blocked    = false;   // Auth-Type := Reject → déjà bloqué
      $department    = null;

      foreach ($rows as $row) {
        if ($row['attribute'] === 'Cleartext-Password') {
          $is_authorized = true;
        }
        if ($row['attribute'] === 'Auth-Type' && $row['value'] === 'Reject') {
          $is_blocked = true;
        }
      }

      // Récupérer le département si autorisé
      if ($is_authorized) {
        $stmt = $pdo->prepare("SELECT department FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Cleartext-Password'");
        $stmt->execute([$macCompact]);
        $department = $stmt->fetchColumn();
      }

      // Vérifier si déjà dans la table blacklist
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

  // ============ Liste noire ============
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

      // Vérifier si l'appareil est autorisé dans radcheck (Cleartext-Password)
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Cleartext-Password'");
      $stmt->execute([$macCompact]);
      $is_authorized = ((int) $stmt->fetchColumn()) > 0;

      if ($is_authorized && !$force) {
        // L'appareil est autorisé mais l'utilisateur n'a pas confirmé → refuser
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'APPAREIL_DEJA_AUTORISE', [
          'mac_address' => $mac,
        ]);
      }

      // Si force=1, supprimer TOUTES les entrées radcheck existantes
      // (Cleartext-Password + radusergroup) avant de bloquer
      if ($is_authorized && $force) {
        // Supprimer de radcheck (tous les attributs)
        $stmt = $pdo->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere");
        $stmt->execute([$macCompact]);

        // Supprimer de radusergroup aussi
        $stmt = $pdo->prepare("DELETE FROM radusergroup WHERE $normalizedMacWhere");
        $stmt->execute([$macCompact]);
      }

      // Si déjà bloquée dans blacklist, on renouvelle ; sinon on insère
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

      // Supprimer tout ancien Reject pour cette MAC
      $stmt = $pdo->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere AND attribute = 'Auth-Type'");
      $stmt->execute([$macCompact]);

      // Insérer le Reject avec la MAC normalisée
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

      // Supprimer de la blacklist
      $stmt = $pdo->prepare("DELETE FROM blacklist WHERE mac_address = ?::macaddr");
      $stmt->execute([$mac]);

      // Supprimer le blocage de radcheck
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
