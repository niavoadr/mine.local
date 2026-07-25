<?php
require_once __DIR__ . '/connexion.php';

/*
 * Affiche les derniers événements de sécurité (table `security_event`)
 * sous forme de lignes colorées par sévérité, consommées par l'onglet
 * "Alertes" du dashboard (conteneur .log-console).
 */

header('Content-Type: text/html; charset=utf-8');

// Couleurs par niveau de sévérité (info / warning / critical)
$severityColors = [
  'critical' => '#ef4444',
  'warning'  => '#f59e0b',
  'info'     => '#10b981',
];

try {
  $sql = "SELECT event_type, security_status,
                 COALESCE(source_ip::text, 'N/A')   AS source_ip,
                 COALESCE(mac_address::text, 'N/A') AS mac_address,
                 details->>'description'            AS description,
                 created_at
          FROM security_event
          ORDER BY created_at DESC
          LIMIT 50";
  $events = $connexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  echo 'Alertes de sécurité indisponibles : ' . htmlspecialchars($e->getMessage());
  return;
}

if (empty($events)) {
  echo 'Aucune alerte de sécurité récente.';
  return;
}

foreach ($events as $event) {
  $color = $severityColors[$event['security_status']] ?? '#9ca3af';
  $ts = date('d/m/Y H:i:s', strtotime($event['created_at']));
  $desc = $event['description'] !== null && $event['description'] !== ''
    ? $event['description']
    : ucfirst(str_replace('_', ' ', $event['event_type']));
  $line = sprintf('[%s] %s — %s | IP:%s | MAC:%s',
    strtoupper($event['security_status']),
    $ts,
    $desc,
    $event['source_ip'],
    $event['mac_address']
  );
  echo "<span style=\"color:$color\">" . htmlspecialchars($line) . '</span><br>';
}
