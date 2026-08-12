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
 * Les adresses MAC sont normalisées via normalizeMacAddress() (même
 * méthode que radius_devices.php) pour éviter les problèmes de casse
 * et de format (AA-BB-CC-DD-EE-FF vs aa:bb:cc:dd:ee:ff, etc.).
 */

function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
  exit();
}

/**
 * Normalise une adresse MAC au format attendu par FreeRADIUS et la base :
 *   xx:xx:xx:xx:xx:xx (minuscules, séparateur deux-points)
 * Les saisies avec majuscules, tirets, points, espaces, etc. sont acceptées
 * tant qu'elles contiennent exactement 12 caractères hexadécimaux.
 * Identique à la méthode utilisée dans radius_devices.php.
 */
function normalizeMacAddress($macRaw)
{
  $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));

  if (strlen($cleanMac) !== 12) {
    return false;
  }

  return implode(':', str_split($cleanMac, 2));
}

/**
 * Retourne la version sans séparateur d'une MAC normalisée, pour comparer
 * proprement avec d'anciennes valeurs stockées en base sous plusieurs formats.
 */
function compactMacAddress($mac)
{
  return str_replace(':', '', normalizeMacAddress($mac));
}

/**
 * Clause WHERE SQL insensible à la casse et au format pour radcheck.username.
 * Comparaison via regexp_replace : on retire tout séparateur et on passe
 * en minuscules avant de comparer avec la valeur compactée.
 */
function normalizedMacSqlWhere()
{
  return "regexp_replace(lower(username), '[^0-9a-f]', '', 'g') = ?";
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
        // Afficher la MAC normalisée (minuscules, deux-points)
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
    $macRaw = trim($_POST['mac_address'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($macRaw === '' || $reason === '') {
      jsonResponse(false, 'Adresse MAC et raison sont obligatoires');
    }

    // Normaliser la MAC : minuscules, format xx:xx:xx:xx:xx:xx
    $mac = normalizeMacAddress($macRaw);
    if ($mac === false) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    $macCompact = compactMacAddress($macRaw);

    try {
      $pdo->beginTransaction();

      // Si déjà bloquée, on renouvelle le blocage ; sinon on insère
      // blacklist.mac_address est de type MACADDR : la comparaison est insensible à la casse
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

      // Bloquer dans radcheck : Auth-Type := Reject pour FreeRADIUS
      // Utiliser la même méthode que radius_devices.php :
      // recherche insensible à la casse/format via normalizedMacSqlWhere()
      $normalizedMacWhere = normalizedMacSqlWhere();

      // Supprimer tout ancien Reject pour cette MAC (quel que soit le format stocké)
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

    // Normaliser la MAC
    $mac = normalizeMacAddress($macRaw);
    if ($mac === false) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    $macCompact = compactMacAddress($macRaw);

    try {
      $pdo->beginTransaction();

      // Supprimer de la blacklist (MACADDR : insensible à la casse)
      $stmt = $pdo->prepare("DELETE FROM blacklist WHERE mac_address = ?::macaddr");
      $stmt->execute([$mac]);

      // Supprimer le blocage de radcheck via comparaison insensible à la casse/format
      $normalizedMacWhere = normalizedMacSqlWhere();
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
