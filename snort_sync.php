<?php
/**
 * snort_sync.php — Synchronisation des alertes Snort (pfSense) vers security_event.
 *
 * Ce script se connecte à pfSense via SSH (avec clé) pour lire les logs Snort,
 * puis insère les nouvelles alertes dans security_event via intrusion.php.
 *
 * Utilisation cron (toutes les 2 minutes) :
 *   *\/2 * * * * /usr/bin/php /chemin/vers/mine.local/snort_sync.php >> /var/log/snort_sync.log 2>&1
 *
 * Prérequis :
 *   - SSH activé sur pfSense (System → Advanced → Secure Shell)
 *   - Clé SSH configurée du serveur web vers pfSense (sans mot de passe)
 */

require_once __DIR__ . '/env.php';

// ============ CONFIGURATION ============
$PFSENSE_SSH_HOST     = env('PFSENSE_SSH_HOST', '');
$PFSENSE_SSH_PORT     = (int) env('PFSENSE_SSH_PORT', '22');
$PFSENSE_SSH_USER     = env('PFSENSE_SSH_USER', 'root');
$PFSENSE_SSH_KEY      = env('PFSENSE_SSH_KEY', '');       // Contenu de la clé privée SSH (collé directement)
$CRON_API_TOKEN       = env('CRON_API_TOKEN', '');

// Chemin des logs Snort sur pfSense
$PFSENSE_SNORT_LOG    = env('PFSENSE_SNORT_LOG', '/var/log/snort/snort_alerts');

// URL vers intrusion.php (serveur web local)
$INTRUSION_PHP_URL    = env('INTRUSION_PHP_URL', 'http://localhost/intrusion.php');

// Fichier de horodatage pour ne pas importer les mêmes alertes deux fois
$LAST_SYNC_FILE       = __DIR__ . '/.snort_last_sync';

// ============ FONCTIONS ============

/**
 * Lit les logs Snort depuis pfSense via SSH avec clé.
 * Retourne le contenu texte du log.
 */
function fetchSnortLogSsh($host, $port, $user, $keyPath, $snortLog)
{
    $sshCmd = sprintf(
        'ssh -o StrictHostKeyChecking=no -o BatchMode=yes -o ConnectTimeout=10 -p %d -i %s %s@%s "clog %s 2>/dev/null || cat %s 2>/dev/null"',
        $port,
        escapeshellarg($keyPath),
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

            $priority = (int) $m[7];
            if ($priority <= 1) {
                $severity = 'critical';
            } elseif ($priority <= 2) {
                $severity = 'warning';
            } else {
                $severity = 'info';
            }

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

if ($PFSENSE_SSH_HOST === '') {
    echo "[snort_sync] Erreur: PFSENSE_SSH_HOST non configuré dans le .env\n";
    exit(1);
}
if ($PFSENSE_SSH_KEY === '') {
    echo "[snort_sync] Erreur: PFSENSE_SSH_KEY non configuré dans le .env\n";
    exit(1);
}
if ($CRON_API_TOKEN === '') {
    echo "[snort_sync] Erreur: CRON_API_TOKEN non configuré dans le .env\n";
    exit(1);
}

// Écrire la clé SSH dans un fichier temporaire pour SSH
$sshKeyTmpFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
file_put_contents($sshKeyTmpFile, $PFSENSE_SSH_KEY);
chmod($sshKeyTmpFile, 0600);

$lastSync = null;
if (file_exists($LAST_SYNC_FILE)) {
    $lastSync = (int) file_get_contents($LAST_SYNC_FILE);
}

echo "[snort_sync] Connexion SSH à $PFSENSE_SSH_HOST...\n";
$result = fetchSnortLogSsh($PFSENSE_SSH_HOST, $PFSENSE_SSH_PORT, $PFSENSE_SSH_USER, $sshKeyTmpFile, $PFSENSE_SNORT_LOG);

if (!$result['success']) {
    echo "[snort_sync] Erreur: " . $result['error'] . "\n";
    exit(1);
}

$alerts = parseSnortLog($result['log'], $lastSync);
echo "[snort_sync] " . count($alerts) . " nouvelle(s) alerte(s) Snort\n";

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

if ($latestTimestamp > $lastSync) {
    file_put_contents($LAST_SYNC_FILE, (string) $latestTimestamp);
}

// Supprimer le fichier temporaire de la clé SSH
if (file_exists($sshKeyTmpFile)) {
    unlink($sshKeyTmpFile);
}

echo "[snort_sync] Terminé: $inserted insérée(s), $blocked bloquée(s)\n";
