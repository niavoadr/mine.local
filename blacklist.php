<?php
require_once __DIR__ . '/connexion.php';

// blacklist.php
//
// Endpoints AJAX utilisés par dashboard_admin.php pour la gestion de la
// liste noire. Toutes les requêtes ciblent la table "blacklist" du nouveau
// schéma (database/radius.sql) :
//   - id          BIGSERIAL
//   - mac_address MACADDR
//   - reason      VARCHAR(255)
//   - blocked_at  TIMESTAMP (défaut now())
//   - expires_at  TIMESTAMP (NOT NULL)

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$pdo = $connexion;

function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode([
    'success' => $success,
    'message' => $message,
    'data' => $data,
  ]);
  exit();
}

/**
 * Valide le format d'une adresse MAC (séparateurs ':', '-' ou aucun).
 */
function isValidMac($mac)
{
  return (bool) preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$|^([0-9A-Fa-f]{12})$/', trim((string) $mac));
}

if (!isset($_POST['action'])) {
  jsonResponse(false, 'Aucune action spécifiée');
}

$action = $_POST['action'];

switch ($action) {
  case 'get_blacklist':
    try {
      $stmt = $pdo->query("
                SELECT
                    mac_address::text AS mac_address,
                    reason,
                    to_char(blocked_at, 'DD/MM/YYYY HH24:MI') AS blocked_date
                FROM blacklist
                ORDER BY blocked_at DESC
            ");
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Champs complémentaires attendus par le front-end (absents de la table).
      $records = [];
      foreach ($rows as $row) {
        $records[] = [
          'mac_address' => $row['mac_address'],
          'ip_address' => null,
          'reason' => $row['reason'],
          'blocked_date' => $row['blocked_date'],
          'blocked_attempts' => 0,
        ];
      }

      jsonResponse(true, '', $records);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors de la récupération de la liste noire');
    }
    break;

  case 'get_stats':
    try {
      $total = (int) $pdo->query('SELECT COUNT(*) AS total FROM blacklist')->fetch()['total'];

      $stmt = $pdo->query('SELECT COUNT(*) AS total FROM blacklist WHERE blocked_at::date = CURRENT_DATE');
      $today = (int) $stmt->fetch()['total'];

      jsonResponse(true, '', [
        'total' => $total,
        'today' => $today,
      ]);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des statistiques');
    }
    break;

  case 'add_blacklist':
    $mac = trim((string) ($_POST['mac_address'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($mac === '' || $reason === '') {
      jsonResponse(false, "L'adresse MAC et la raison sont obligatoires");
    }

    if (!isValidMac($mac)) {
      jsonResponse(false, "Format d'adresse MAC invalide");
    }

    // La colonne reason est un VARCHAR(255)
    $reason = mb_substr($reason, 0, 255);

    try {
      // expires_at est NOT NULL : on bloque par défaut pour 30 jours.
      // Si l'adresse MAC est déjà présente, on rafraîchit le blocage.
      $stmt = $pdo->prepare("
                UPDATE blacklist
                SET reason = ?, blocked_at = now(), expires_at = now() + INTERVAL '30 days'
                WHERE mac_address = CAST(? AS macaddr)
            ");
      $stmt->execute([$reason, $mac]);

      if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("
                    INSERT INTO blacklist (mac_address, reason, blocked_at, expires_at)
                    VALUES (CAST(? AS macaddr), ?, now(), now() + INTERVAL '30 days')
                ");
        $stmt->execute([$mac, $reason]);
      }

      jsonResponse(true, 'Appareil ajouté à la liste noire');
    } catch (PDOException $e) {
      jsonResponse(false, "Erreur lors de l'ajout à la liste noire : " . $e->getMessage());
    } catch (Exception $e) {
      jsonResponse(false, "Erreur lors de l'ajout à la liste noire");
    }
    break;

  case 'remove_blacklist':
    $mac = trim((string) ($_POST['mac_address'] ?? ''));

    if ($mac === '') {
      jsonResponse(false, 'Adresse MAC requise');
    }

    try {
      $stmt = $pdo->prepare('DELETE FROM blacklist WHERE mac_address = CAST(? AS macaddr)');
      $stmt->execute([$mac]);

      jsonResponse(true, 'Appareil débloqué avec succès');
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors de la suppression de la liste noire');
    }
    break;

  default:
    jsonResponse(false, 'Action non reconnue');
    break;
}
