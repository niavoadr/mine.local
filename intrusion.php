<?php
header('Content-Type: application/json');

// Configuration
$syslogFile = '/var/log/syslog';
$firewallLogFile = '/var/log/pfsense/filterlog.log';
$maxLines = 1000; // Nombre de lignes à analyser

// Fonction pour parser les logs Snort
function parseSnortLogs($file, $maxLines)
{
  $alerts = [];

  if (!file_exists($file)) {
    return $alerts;
  }

  $lines = file($file);
  if (!$lines) {
    return $alerts;
  }

  // Prendre les dernières lignes
  $lines = array_slice($lines, -$maxLines);

  foreach ($lines as $line) {
    // Détecter les lignes Snort
    if (stripos($line, 'snort') !== false) {
      $alert = [];

      // Parser la date/heure
      if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $line, $matches)) {
        $alert['timestamp'] = date('d/m/Y H:i:s', strtotime($matches[1]));
      } else {
        $alert['timestamp'] = date('d/m/Y H:i:s');
      }

      // Parser l'IP source
      if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/', $line, $matches)) {
        $alert['ip_address'] = $matches[1];
      } else {
        $alert['ip_address'] = 'N/A';
      }

      // Déterminer le type et la sévérité
      $alert['type'] = 'Intrusion Snort';
      $alert['severity'] = 'medium';

      // Détection de mots-clés pour la sévérité
      if (stripos($line, 'attack') !== false || stripos($line, 'exploit') !== false) {
        $alert['severity'] = 'critical';
        $alert['type'] = 'Attaque détectée';
      } elseif (stripos($line, 'scan') !== false) {
        $alert['severity'] = 'high';
        $alert['type'] = 'Scan de réseau';
      } elseif (stripos($line, 'suspicious') !== false) {
        $alert['severity'] = 'medium';
        $alert['type'] = 'Activité suspecte';
      }

      $alert['mac_address'] = 'N/A';
      $alert['description'] = trim(substr($line, 50, 150));
      $alert['source_info'] = 'Snort';

      $alerts[] = $alert;
    }
  }

  return $alerts;
}

// Fonction pour parser les logs Firewall
function parseFirewallLogs($file, $maxLines)
{
  $alerts = [];

  if (!file_exists($file)) {
    return $alerts;
  }

  $lines = file($file);
  if (!$lines) {
    return $alerts;
  }

  $lines = array_slice($lines, -$maxLines);

  foreach ($lines as $line) {
    // Détecter les connexions bloquées
    if (stripos($line, 'block') !== false) {
      $alert = [];

      // Parser la date
      if (preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})/', $line, $matches)) {
        $alert['timestamp'] = date('d/m/Y H:i:s', strtotime($matches[1]));
      } else {
        $alert['timestamp'] = date('d/m/Y H:i:s');
      }

      // Parser les IPs (format filterlog)
      $parts = explode(',', $line);
      if (count($parts) > 15) {
        $alert['ip_address'] = $parts[11] ?? 'N/A'; // IP source
      } else {
        $alert['ip_address'] = 'N/A';
      }

      $alert['type'] = 'Connexion bloquée';
      $alert['severity'] = 'low';
      $alert['mac_address'] = 'N/A';
      $alert['description'] = 'Tentative de connexion bloquée par le firewall';
      $alert['source_info'] = 'Firewall';

      $alerts[] = $alert;
    }
  }

  return $alerts;
}

// Gestion des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'get_intrusions') {
    $severity = $_POST['severity'] ?? '';
    $type = $_POST['type'] ?? '';
    $date = $_POST['date'] ?? '';

    // Récupérer toutes les intrusions
    $snortAlerts = parseSnortLogs($syslogFile, $maxLines);
    $firewallAlerts = parseFirewallLogs($firewallLogFile, $maxLines);

    // Combiner
    $allIntrusions = array_merge($snortAlerts, $firewallAlerts);

    // Trier par date (plus récent en premier)
    usort($allIntrusions, function ($a, $b) {
      return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Filtrer selon les critères
    if (!empty($severity)) {
      $allIntrusions = array_filter($allIntrusions, function ($item) use ($severity) {
        return $item['severity'] === $severity;
      });
    }

    if (!empty($date)) {
      $allIntrusions = array_filter($allIntrusions, function ($item) use ($date) {
        return strpos($item['timestamp'], date('d/m/Y', strtotime($date))) === 0;
      });
    }

    echo json_encode([
      'success' => true,
      'data' => array_values($allIntrusions),
    ]);
  } elseif ($action === 'get_stats') {
    $snortAlerts = parseSnortLogs($syslogFile, $maxLines);
    $firewallAlerts = parseFirewallLogs($firewallLogFile, $maxLines);
    $allIntrusions = array_merge($snortAlerts, $firewallAlerts);

    $critical = count(array_filter($allIntrusions, fn($a) => $a['severity'] === 'critical'));
    $medium = count(array_filter($allIntrusions, fn($a) => $a['severity'] === 'medium'));
    $suspicious = count(array_filter($allIntrusions, fn($a) => $a['severity'] === 'low' || $a['severity'] === 'high'));

    echo json_encode([
      'success' => true,
      'data' => [
        'critical' => $critical,
        'medium' => $medium,
        'suspicious' => $suspicious,
      ],
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Action non reconnue',
    ]);
  }
} else {
  echo json_encode([
    'success' => false,
    'message' => 'Méthode non autorisée',
  ]);
}
?>