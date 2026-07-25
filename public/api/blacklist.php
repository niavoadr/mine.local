<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
header('Content-Type: application/json');

$pdo = $connexion;

/*
 * Gestion de la liste noire (table `blacklist`).
 *
 * L'adresse IP et le nombre de tentatives proviennent directement de la
 * table `security_event` (colonne `source_ip` pour l'IP, colonne
 * `attempts` pour les tentatives), agrégées par adresse MAC.
 */

// Durée de blocage par défaut (jours) si aucune durée n'est fournie
define('BLACKLIST_DEFAULT_DAYS', 30);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(false, 'Méthode non autorisée');
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
                ), 0) AS blocked_attempts,
                b.expires_at
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

      json_response(true, '', $data);
    } catch (Exception $e) {
      json_response(false, 'Erreur lors du chargement de la liste noire');
    }
    break;

  case 'get_stats':
    try {
      $sql = "SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE blocked_at::date = current_date) AS today
              FROM blacklist";
      $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
      json_response(true, '', [
        'total' => (int) $row['total'],
        'today' => (int) $row['today'],
      ]);
    } catch (Exception $e) {
      json_response(false, 'Erreur lors du chargement des statistiques');
    }
    break;

  case 'add_blacklist':
    $mac = trim($_POST['mac_address'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $days = isset($_POST['duration_days']) && $_POST['duration_days'] !== ''
      ? max(1, (int) $_POST['duration_days'])
      : BLACKLIST_DEFAULT_DAYS;

    if ($mac === '' || $reason === '') {
      json_response(false, 'Adresse MAC et raison sont obligatoires');
    }
    if (!is_valid_mac_address($mac)) {
      json_response(false, "Format d'adresse MAC invalide");
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

      json_response(true, 'Appareil ajouté à la liste noire');
    } catch (PDOException $e) {
      json_response(false, "Erreur lors de l'ajout à la liste noire");
    }
    break;

  case 'remove_blacklist':
    $mac = trim($_POST['mac_address'] ?? '');
    if ($mac === '') {
      json_response(false, 'Adresse MAC requise');
    }
    try {
      $stmt = $pdo->prepare("DELETE FROM blacklist WHERE mac_address = ?");
      $stmt->execute([$mac]);
      json_response(true, 'Appareil débloqué avec succès');
    } catch (Exception $e) {
      json_response(false, 'Erreur lors du déblocage');
    }
    break;

  default:
    json_response(false, 'Action non reconnue');
    break;
}
