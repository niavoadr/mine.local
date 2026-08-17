<?php
require_once __DIR__ . '/env.php';

$PFSENSE_SSH_HOST     = env('PFSENSE_SSH_HOST', '');
$PFSENSE_SSH_PORT     = (int) env('PFSENSE_SSH_PORT', '22');
$PFSENSE_SSH_USER     = env('PFSENSE_SSH_USER', 'admin');
$PFSENSE_SSH_KEY      = env('PFSENSE_SSH_KEY', '');
$CRON_API_TOKEN       = env('CRON_API_TOKEN', '');

$PFSENSE_SNORT_LOG    = env('PFSENSE_SNORT_LOG', '/var/log/snort/snort_re032559/alert');

$INTRUSION_PHP_URL    = env('INTRUSION_PHP_URL', 'http://portail.cpanel/intrusion.php');

$LAST_SYNC_FILE       = __DIR__ . '/.snort_last_sync';

function fetchSnortLogSsh($host, $port, $user, $keyPath, $snortLog)
{
    $sshCmd = sprintf(
        'ssh -o StrictHostKeyChecking=no -o BatchMode=yes -o ConnectTimeout=10 -p %d -i %s %s@%s "cat %s 2>/dev/null || clog %s 2>/dev/null"',
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

function parseSnortLog($logContent, $sinceTimestamp = null)
{
    $alerts = [];
    $lines = explode("\n", $logContent);

    $pfsensePattern = '/^(\d{2}\/\d{2}\/\d{2}-\d{2}:\d{2}:\d{2}\.\d+)\s*,(\d+),(\d+),(\d+),"([^"]+)",(\w+),([0-9.]+),(\d+),([0-9.]+),(\d+),(\d+),([^,]*),(\d+),([^,]*),([^,]*)/';

    $standardPattern = '/^(\w{3}\s+\d+\s+\d+:\d+:\d+)\s+\S+\s+snort\[\d+\]:\s+\[(\d+):(\d+):(\d+)\]\s+(.+?)\s+\[Classification:\s*([^\]]+)\]\s+\[Priority:\s*(\d+)\]\s+\{(\w+)\}\s+([0-9.:]+)\s*->\s*([0-9.:]+)/';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if (preg_match($pfsensePattern, $line, $m)) {
            $tsStr = $m[1];
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})-(\d{2}:\d{2}:\d{2})/', $tsStr, $tm)) {
                $timestamp = strtotime('20' . $tm[3] . '-' . $tm[1] . '-' . $tm[2] . ' ' . $tm[4]);
            } else {
                $timestamp = strtotime($tsStr);
            }

            if (!$timestamp) continue;
            if ($sinceTimestamp && $timestamp <= $sinceTimestamp) continue;

            $priority = (int) $m[13];
            if ($priority <= 1) {
                $severity = 'critical';
            } elseif ($priority <= 2) {
                $severity = 'warning';
            } else {
                $severity = 'info';
            }

            $sourceIp    = $m[7];
            $description = trim($m[5]);
            $classification = trim($m[12]);

            $alerts[] = [
                'event_type'   => $classification ?: 'snort_alert',
                'severity'     => $severity,
                'source_ip'    => $sourceIp,
                'mac_address'  => '',
                'description'  => $description,
                'source_info'  => 'Snort',
                'attempts'     => 1,
                'timestamp'    => date('Y-m-d H:i:s', $timestamp),
                'raw_timestamp'=> $timestamp,
            ];
            continue;
        }

        if (preg_match($standardPattern, $line, $m)) {
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

$sshKeyTmpFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
file_put_contents($sshKeyTmpFile, $PFSENSE_SSH_KEY);
chmod($sshKeyTmpFile, 0600);

// C10 : suppression garantie de la clé privée, quel que soit le chemin de sortie
register_shutdown_function(function () use ($sshKeyTmpFile) {
    if (file_exists($sshKeyTmpFile)) {
        @unlink($sshKeyTmpFile);
    }
});

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

$inserted = 0;
$latestTimestamp = $lastSync;

foreach ($alerts as $alert) {
    $res = pushIntrusion($INTRUSION_PHP_URL, $CRON_API_TOKEN, $alert);
    if ($res && ($res['success'] ?? false)) {
        $inserted++;
        // B2 : le curseur n'avance que si l'insertion a réussi
        if ($alert['raw_timestamp'] > $latestTimestamp) {
            $latestTimestamp = $alert['raw_timestamp'];
        }
    } else {
        echo "[snort_sync] Échec insertion: " . ($res['message'] ?? 'erreur inconnue') . "\n";
    }
}

if ($latestTimestamp > $lastSync) {
    file_put_contents($LAST_SYNC_FILE, (string) $latestTimestamp);
}

if (file_exists($sshKeyTmpFile)) {
    unlink($sshKeyTmpFile);
}

echo "[snort_sync] Terminé: $inserted insérée(s) (affichage seul, blocage délégué à Fail2ban)\n";
