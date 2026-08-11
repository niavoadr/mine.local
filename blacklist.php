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
 */

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

switch ($action) {
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
        return [
          'mac_address'      => $row['mac_address'],
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
    $mac = trim($_POST['mac_address'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($mac === '' || $reason === '') {
      jsonResponse(false, 'Adresse MAC et raison sont obligatoires');
    }
    if (!isValidMac($mac)) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    try {
      $pdo->beginTransaction();

      // Si déjà bloquée, on renouvelle le blocage ; sinon on insère
      $stmt = $pdo->prepare("SELECT id FROM blacklist WHERE mac_address = ?");
      $stmt->execute([$mac]);
      $existing = $stmt->fetchColumn();

      if ($existing) {
        $stmt = $pdo->prepare("UPDATE blacklist
                  SET reason = ?, blocked_at = now()
                  WHERE mac_address = ?");
        $stmt->execute([$reason, $mac]);
      } else {
        $stmt = $pdo->prepare("INSERT INTO blacklist (mac_address, reason)
                  VALUES (?, ?)");
        $stmt->execute([$mac, $reason]);
      }

      // Bloquer dans radcheck : Auth-Type := Reject pour FreeRADIUS
      // D'abord supprimer tout ancien Reject pour cette MAC
      $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Auth-Type'");
      $stmt->execute([$mac]);
      // Ensuite insérer le Reject
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
    $mac = trim($_POST['mac_address'] ?? '');
    if ($mac === '') {
      jsonResponse(false, 'Adresse MAC requise');
    }
    try {
      $pdo->beginTransaction();

      // Supprimer de la blacklist
      $stmt = $pdo->prepare("DELETE FROM blacklist WHERE mac_address = ?");
      $stmt->execute([$mac]);

      // Supprimer le blocage de radcheck
      $stmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ? AND attribute = 'Auth-Type' AND value = 'Reject'");
      $stmt->execute([$mac]);

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
