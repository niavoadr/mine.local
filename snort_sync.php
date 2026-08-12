<?php
/**
 * snort_sync.php — Synchronisation des alertes Snort (pfSense) vers security_event.
 *
 * Ce script se connecte à pfSense via SSH (avec mot de passe) pour lire les logs Snort,
 * puis insère les nouvelles alertes dans security_event via intrusion.php.
 *
 * Utilisation cron (toutes les 2 minutes) :
 *   */2 * * * * /usr/bin/php /chemin/vers/mine.local/snort_sync.php >> /var/log/snort_sync.log 2>&1
 *
 * Prérequis :
 *   - SSH activé sur pfSense (System → Advanced → Secure Shell)
 *   - sshpass installé sur le serveur web (apt install sshpass)
 *   - Le mot de passe SSH de pfSense dans le fichier .env
 */

require_once __DIR__ . '/env.php';

// ============ CONFIGURATION ============
$PFSENSE_SSH_HOST     = env('PFSENSE_SSH_HOST', '');
$PFSENSE_SSH_PORT     = (int) env('PFSENSE_SSH_PORT', '22');
$PFSENSE_SSH_USER     = env('PFSENSE_SSH_USER', 'root');
$PFSENSE_SSH_PASSWORD = env('PFSENSE_SSH_PASSWORD', '');
$CRON_API_TOKEN       = env('CRON_API_TOKEN', '');

// Chemin des logs Snort sur pfSense
$PFSENSE_SNORT_LOG    = env('PFSENSE_SNORT_LOG', '/var/log/snort/snort_alerts');

// URL vers intrusion.php (serveur web local)
$INTRUSION_PHP_URL    = env('INTRUSION_PHP_URL', 'http://localhost/intrusion.php');

// Fichier de horodatage pour ne pas importer les mêmes alertes deux fois
$LAST_SYNC_FILE       = __DIR__ . '/.snort_last_sync';

// ============ FONCTIONS ============

/**
 * Lit les logs Snort depuis pfSense via SSH avec mot de passe (sshpass).
 * Retourne le contenu texte du log.
 */
function fetchSnortLogSsh($host, $port, $user, $password, $snortLog)
{
    $sshCmd = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -p %d %s@%s "clog %s 2>/dev/null || cat %s 2>/dev/null"',
        escapeshellarg($password),
        $port,
        escapeshellarg($user),
        escapeshellarg($host),
        escapeshellarg($snortLog),
        escapeshellarg($snortLog)
    );

    $outputLines = [];
    $exitCode = 0;
    exec($sshCmd . ' 2>&1', $outputLines, $exitCode);

    if ($exitCode !== 0) {
        $error = implode("\n", $outputLines);
        return ['success' => false, 'error' => "SSH échoué (code $exitCode): $error"];
    }

    $output = implode("\n", $outputLines);
    return ['success' => true, 'log' => $output];
}

/**
 * Parse les lignes du log Snort de pfSense et retourne un tableau d'alertes.
 *
 * Format typique d'une ligne Snort dans les logs pfSense :
 *   Aug 12 10:30:00 hostname snort[12345]: [1:1001:1] ALERT MESSAGE [Classification: Attempted Admin] [Priority: 1] {TCP} 192.168.1.100:12345 -> 10.0.0.1:80
 */
function parseSnortLog($logContent, $sinceTimestamp = null)
{
    $alerts = [];
    $lines = explode("\n", $logContent);

    // Regex pour parser une ligne d'alerte Snort
    $pattern = '/^(\w{3}\s+\d+\s+\d+:\d+:\d+)\s+\S+\s+snort\[\d+\]:\s+\[(\d+):(\d+):(\d+)\]\s+(.+?)\s+\[Classification:\s*([^\]]+)\]\s+\[Priority:\s*(\d+)\]\s+\{(\w+)\}\s+([0-9.:]+)\s*->\s*([0-9.:]+)/';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (preg_match($pattern, $line, $m)) {
            $timestamp = strtotime($m[1]);
            if (!$timestamp) {
                $timestamp = strtotime($m[1] . ' ' . date('Y'));
            }

            if ($sinceTimestamp && $timestamp <= $sinceTimestamp) {
                continue;
            }

            // Mapper la priorité Snort vers notre enum
            $priority = (int) $m[7];
            if ($priority <= 1) {
                $severity = 'critical';
            } elseif ($priority <= 2) {
                $severity = 'warning';
            } else {
                $severity = 'info';
            }

            // Extraire l'IP source (sans le port)
            $sourceIp = preg_replace('/:\d+$/', '', $m[9]);

            $alerts[] = [
                'event_type'   => trim($m[6]) ?: 'snort_alert',
                'severity'     => $severity,
                'source_ip'    => $sourceIp,
                'mac_address'  => '',
                'description'  => trim($m[5]),
                'source_info'  => 'Snort',
                'attempts'     => 1,
                'timestamp'    => date('Y-m-d H:i:s', $timestamp),
                'raw_timestamp'=> $timestamp,
            ];
        }
    }

    usort($alerts, function ($a, $b) {
        return $a['raw_timestamp'] - $b['raw_timestamp'];
    });

    return $alerts;
}

/**
 * Pousse une alerte vers intrusion.php (action auto_block_intrusion)
 */
function pushIntrusion($intrusionUrl, $cronToken, $alert)
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $intrusionUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'action'      => 'auto_block_intrusion',
            'cron_token'  => $cronToken,
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

// Vérifier la configuration
if ($PFSENSE_SSH_HOST === '') {
    echo "[snort_sync] Erreur: PFSENSE_SSH_HOST non configuré dans le .env\n";
    exit(1);
}
if ($PFSENSE_SSH_PASSWORD === '') {
    echo "[snort_sync] Erreur: PFSENSE_SSH_PASSWORD non configuré dans le .env\n";
    exit(1);
}
if ($CRON_API_TOKEN === '') {
    echo "[snort_sync] Erreur: CRON_API_TOKEN non configuré dans le .env\n";
    exit(1);
}

// Vérifier que sshpass est disponible
exec('which sshpass 2>/dev/null', $sshpassCheck, $sshpassCode);
if ($sshpassCode !== 0) {
    echo "[snort_sync] Erreur: sshpass n'est pas installé. Installez-le avec: apt install sshpass\n";
    exit(1);
}

// Lire le timestamp de la dernière synchronisation
$lastSync = null;
if (file_exists($LAST_SYNC_FILE)) {
    $lastSync = (int) file_get_contents($LAST_SYNC_FILE);
}

// Récupérer les logs Snort depuis pfSense via SSH
echo "[snort_sync] Connexion SSH à $PFSENSE_SSH_HOST...\n";
$result = fetchSnortLogSsh($PFSENSE_SSH_HOST, $PFSENSE_SSH_PORT, $PFSENSE_SSH_USER, $PFSENSE_SSH_PASSWORD, $PFSENSE_SNORT_LOG);

if (!$result['success']) {
    echo "[snort_sync] Erreur: " . $result['error'] . "\n";
    exit(1);
}

// Parser les alertes
$alerts = parseSnortLog($result['log'], $lastSync);
echo "[snort_sync] " . count($alerts) . " nouvelle(s) alerte(s) Snort\n";

// Pousser chaque alerte dans intrusion.php
$blocked = 0;
$inserted = 0;
$latestTimestamp = $lastSync;

foreach ($alerts as $alert) {
    $res = pushIntrusion($INTRUSION_PHP_URL, $CRON_API_TOKEN, $alert);
    if ($res && ($res['success'] ?? false)) {
        $inserted++;
        if (strpos($res['message'] ?? '', 'bloqué') !== false) {
            $blocked++;
        }
    } else {
        echo "[snort_sync] Échec insertion: " . ($res['message'] ?? 'erreur inconnue') . "\n";
    }

    if ($alert['raw_timestamp'] > $latestTimestamp) {
        $latestTimestamp = $alert['raw_timestamp'];
    }
}

// Mettre à jour le timestamp de dernière synchronisation
if ($latestTimestamp > $lastSync) {
    file_put_contents($LAST_SYNC_FILE, (string) $latestTimestamp);
}

echo "[snort_sync] Terminé: $inserted insérée(s), $blocked bloquée(s)\n";
