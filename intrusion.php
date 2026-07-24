<?php
require_once __DIR__ . '/connexion.php';

// intrusion.php
//
// Endpoints AJAX utilisés par l'onglet "Accès Étrangers / Intrusion" de
// dashboard_admin.php. Toutes les requêtes ciblent la table "security_event"
// du nouveau schéma (database/radius.sql) :
//   - id              BIGSERIAL
//   - event_type      VARCHAR(255)        (brute_force, unauthorized, spoofing, dos, scan, ...)
//   - security_status SECURITY_STATUS_ENUM (info / warning / critical)
//   - source_ip       INET
//   - mac_address     MACADDR
//   - details         JSONB               (ex: {"description": "...", "source": "Snort"})
//   - created_at      TIMESTAMP
//   - is_read         BOOLEAN
//   - read_at         TIMESTAMP
//
// Correspondance sévérité : l'ENUM de la base (critical / warning / info) est
// traduit vers l'échelle du front-end (critical / medium / low). Le filtre
// "high" du front-end n'a pas d'équivalent dans l'ENUM : il est ramené à
// "critical".

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
 * Traduit une sévérité demandée par le front-end (critical/high/medium/low)
 * vers une valeur du SECURITY_STATUS_ENUM de la base (critical/warning/info).
 *
 * @return string|null  null si la valeur ne correspond à aucun filtre exploitable
 */
function severityFilterToDb($severity)
{
  $map = [
    'critical' => 'critical',
    'high' => 'critical', // pas de niveau "high" dans l'ENUM -> rapproché de "critical"
    'medium' => 'warning',
    'low' => 'info',
  ];
  return $map[$severity] ?? null;
}

if (!isset($_POST['action'])) {
  jsonResponse(false, 'Aucune action spécifiée');
}

$action = $_POST['action'];

switch ($action) {
  case 'get_intrusions':
    try {
      $where = [];
      $params = [];

      // Filtre par sévérité
      $severity = trim((string) ($_POST['severity'] ?? ''));
      if ($severity !== '') {
        $dbSeverity = severityFilterToDb($severity);
        if ($dbSeverity !== null) {
          $where[] = 'security_status = ?';
          $params[] = $dbSeverity;
        }
      }

      // Filtre par type d'intrusion (colonne event_type)
      $type = trim((string) ($_POST['type'] ?? ''));
      if ($type !== '') {
        $where[] = 'event_type = ?';
        $params[] = $type;
      }

      // Filtre par date exacte
      $date = trim((string) ($_POST['date'] ?? ''));
      if ($date !== '') {
        $where[] = 'created_at::date = ?::date';
        $params[] = $date;
      }

      $sql = "
                SELECT
                    id,
                    event_type AS type,
                    security_status,
                    CASE security_status
                        WHEN 'critical' THEN 'critical'
                        WHEN 'warning'  THEN 'medium'
                        WHEN 'info'     THEN 'low'
                        ELSE 'low'
                    END AS severity,
                    source_ip::text   AS ip_address,
                    mac_address::text AS mac_address,
                    COALESCE(NULLIF(details->>'description', ''), event_type) AS description,
                    COALESCE(details->>'source', 'Manual') AS source_info,
                    to_char(created_at, 'DD/MM/YYYY HH24:MI:SS') AS timestamp,
                    is_read
                FROM security_event
            ";

      if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
      }

      $sql .= ' ORDER BY created_at DESC LIMIT 200';

      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

      jsonResponse(true, '', $records);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors de la récupération des intrusions : ' . $e->getMessage());
    }
    break;

  case 'get_stats':
    try {
      // critical  -> security_status = 'critical'
      // medium    -> security_status = 'warning'
      // suspicious-> security_status = 'info'
      $stmt = $pdo->query("
                SELECT
                    COUNT(*) FILTER (WHERE security_status = 'critical') AS critical,
                    COUNT(*) FILTER (WHERE security_status = 'warning')  AS medium,
                    COUNT(*) FILTER (WHERE security_status = 'info')     AS suspicious
                FROM security_event
            ");
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      jsonResponse(true, '', [
        'critical' => (int) $row['critical'],
        'medium' => (int) $row['medium'],
        'suspicious' => (int) $row['suspicious'],
      ]);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des statistiques');
    }
    break;

  default:
    jsonResponse(false, 'Action non reconnue');
    break;
}
