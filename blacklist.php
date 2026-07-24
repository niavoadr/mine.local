<?php
require_once __DIR__ . '/connexion.php';
header('Content-Type: application/json');

$pdo = $connexion;

/*
 * Gestion de la liste noire (table `blacklist`).
 *
 * L'adresse IP et le nombre de tentatives proviennent de la vue
 * `v_security_event_by_mac` (voir database/update_security_event.sql),
 * qui agrège les événements de `security_event` par adresse MAC.
 */

// Durée de blocage par défaut (jours) si aucune durée n'est fournie
define('BLACKLIST_DEFAULT_DAYS', 30);

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
                b.mac_address::text                       AS mac_address,
                COALESCE(se.last_source_ip::text, 'N/A')  AS ip_address,
                b.reason,
                b.blocked_at                              AS blocked_date,
                COALESCE(se.total_attempts, se.event_count, 0) AS blocked_attempts,
                b.expires_at
              FROM blacklist b
              LEFT JOIN v_security_event_by_mac se ON se.mac_address = b.mac_address
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
    $days = isset($_POST['duration_days']) && $_POST['duration_days'] !== ''
      ? max(1, (int) $_POST['duration_days'])
      : BLACKLIST_DEFAULT_DAYS;

    if ($mac === '' || $reason === '') {
      jsonResponse(false, 'Adresse MAC et raison sont obligatoires');
    }
    if (!isValidMac($mac)) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    try {
      // Calcul de l'expiration côté PHP (évite tout problème de binding d'intervalle)
      $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

      // Si déjà bloquée, on renouvelle le blocage ; sinon on insère
      $stmt = $pdo->prepare("SELECT id FROM blacklist WHERE mac_address = ?");
      $stmt->execute([$mac]);
      $existing = $stmt->fetchColumn();

      if ($existing) {
        $stmt = $pdo->prepare("UPDATE blacklist
                  SET reason = ?, blocked_at = now(), expires_at = ?
                  WHERE mac_address = ?");
        $stmt->execute([$reason, $expiresAt, $mac]);
      } else {
        $stmt = $pdo->prepare("INSERT INTO blacklist (mac_address, reason, expires_at)
                  VALUES (?, ?, ?)");
        $stmt->execute([$mac, $reason, $expiresAt]);
      }

      jsonResponse(true, 'Appareil ajouté à la liste noire');
    } catch (PDOException $e) {
      jsonResponse(false, "Erreur lors de l'ajout à la liste noire");
    }
    break;

  case 'remove_blacklist':
    $mac = trim($_POST['mac_address'] ?? '');
    if ($mac === '') {
      jsonResponse(false, 'Adresse MAC requise');
    }
    try {
      $stmt = $pdo->prepare("DELETE FROM blacklist WHERE mac_address = ?");
      $stmt->execute([$mac]);
      jsonResponse(true, 'Appareil débloqué avec succès');
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du déblocage');
    }
    break;

  default:
    jsonResponse(false, 'Action non reconnue');
    break;
}
