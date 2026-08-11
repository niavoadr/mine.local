<?php
/**
 * snort_sync.php — Synchronisation des alertes Snort (pfSense) vers security_event.
 *
 * Ce script doit être exécuté par un cron, par exemple :
 *   */2 * * * * /usr/bin/php /chemin/vers/mine.local/snort_sync.php >> /var/log/snort_sync.log 2>&1
 *
 * Il appelle l'API pfSense pour récupérer les alertes Snort récentes,
 * puis les insère dans security_event via intrusion.php (action auto_block_intrusion).
 */

// ============ CONFIGURATION ============
$PFSENSE_URL        = getenv('PFSENSE_URL')        ?: 'https://192.168.1.1';
$PFSENSE_API_KEY    = getenv('PFSENSE_API_KEY')    ?: '';
$PFSENSE_API_SECRET = getenv('PFSENSE_API_SECRET') ?: '';
$CRON_API_TOKEN     = getenv('CRON_API_TOKEN')     ?: '';

// Chemin vers intrusion.php (même dossier, accessible via le serveur web local)
$INTRUSION_PHP_URL = 'http://localhost/intrusion.php';

// Fichier de horodatage pour ne pas importer les mêmes alertes deux fois
$LAST_SYNC_FILE = __DIR__ . '/.snort_last_sync';

// ============ FONCTIONS ============

/**
 * Appelle l'API pfSense pour récupérer les alertes Snort récentes.
 *
 * NOTE : L'endpoint exact dépend de ta version de pfSense et du package Snort/Suricata.
 * PfSense 2.x avec le package Snort expose les alertes via :
 *   - L'API REST pfSense (si installée)
 *   - Ou directement en lisant les fichiers de log snort via SSH/SCP
 *
 * Adapté pour pfSense 2.7+ avec pfSense API v2 :
 *   GET /api/v2/services/snort/alerts
 */
function fetchSnortAlerts($baseUrl, $apiKey, $apiSecret, $sinceTimestamp = null)
{
    $url = rtrim($baseUrl, '/') . '/api/v2/services/snort/alerts';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // pfSense souvent en auto-signé
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($apiKey . ':' . $apiSecret),
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        return ['success' => false, 'error' => "HTTP $httpCode"];
    }

    $data = json_decode($response, true);
    if (!$data) {
        return ['success' => false, 'error' => 'Réponse JSON invalide'];
    }

    // Filtrer les alertes depuis le dernier timestamp
    $alerts = [];
    $items = $data['data'] ?? $data['alerts'] ?? [];

    foreach ($items as $item) {
        $alertTime = strtotime($item['timestamp'] ?? 'now');

        if ($sinceTimestamp && $alertTime <= $sinceTimestamp) {
            continue; // Déjà traité
        }

        // Mapper la sévérité Snort (priority 1=high, 2=medium, 3=low) vers notre enum
        $priority = (int) ($item['priority'] ?? 3);
        if ($priority <= 1) {
            $severity = 'critical';
        } elseif ($priority <= 2) {
            $severity = 'warning';
        } else {
            $severity = 'info';
        }

        $alerts[] = [
            'event_type'   => $item['classification'] ?? $item['rule_category'] ?? 'snort_alert',
            'severity'     => $severity,
            'source_ip'    => $item['src_ip']   ?? $item['source_ip']  ?? '',
            'mac_address'  => $item['src_mac']  ?? $item['mac_address'] ?? '',
            'description'  => $item['message']  ?? $item['description'] ?? '',
            'source_info'  => 'Snort',
            'attempts'     => 1,
            'timestamp'    => $item['timestamp'] ?? date('Y-m-d H:i:s'),
        ];
    }

    return ['success' => true, 'alerts' => $alerts];
}

/**
 * Pousse une alerte vers intrusion.php (action auto_block_intrusion)
 */
function pushIntrusion($intrusionUrl, $alert)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $intrusionUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'action'      => 'auto_block_intrusion',
            'cron_token'  => $CRON_API_TOKEN,
            'event_type'  => $alert['event_type'],
            'severity'    => $alert['severity'],
            'source_ip'   => $alert['source_ip'],
            'mac_address' => $alert['mac_address'],
            'description' => $alert['description'],
            'source_info' => $alert['source_info'],
            'attempts'    => $alert['attempts'],
        ]),
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// ============ EXÉCUTION PRINCIPALE ============

// Lire le timestamp de la dernière synchronisation
$lastSync = null;
if (file_exists($LAST_SYNC_FILE)) {
    $lastSync = (int) file_get_contents($LAST_SYNC_FILE);
}

// Récupérer les alertes Snort depuis pfSense
$result = fetchSnortAlerts($PFSENSE_URL, $PFSENSE_API_KEY, $PFSENSE_API_SECRET, $lastSync);

if (!$result['success']) {
    echo "[snort_sync] Erreur: " . $result['error'] . "\n";
    exit(1);
}

$alerts = $result['alerts'];
echo "[snort_sync] " . count($alerts) . " nouvelle(s) alerte(s) Snort\n";

// Pousser chaque alerte dans intrusion.php
$blocked = 0;
$inserted = 0;
foreach ($alerts as $alert) {
    $res = pushIntrusion($INTRUSION_PHP_URL, $alert);
    if ($res && ($res['success'] ?? false)) {
        $inserted++;
        if (strpos($res['message'] ?? '', 'bloqué') !== false) {
            $blocked++;
        }
    } else {
        echo "[snort_sync] Échec insertion: " . ($res['message'] ?? 'erreur inconnue') . "\n";
    }
}

// Mettre à jour le timestamp de dernière synchronisation
file_put_contents($LAST_SYNC_FILE, (string) time());

echo "[snort_sync] Terminé: $inserted insérée(s), $blocked bloquée(s)\n";
