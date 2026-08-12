<?php
/**
 * fail2ban_sync.php — Synchronisation des bans Fail2ban vers security_event.
 *
 * Fail2ban tourne en local sur le Debian (même machine que PHP / FreeRADIUS).
 * Pas de SSH : on lit /var/log/fail2ban.log, puis POST intrusion.php.
 *
 * Politique (Fail2ban seul, jamais Snort) :
 *   warning  → IP déjà bannie par iptables, pas de blacklist MAC
 *   critical → IP + MAC (blacklist / radcheck) si l'appareil est dans radacct
 *
 * Cron :
 *   */2 * * * * /usr/bin/php /usr/src/app/portail/admin/mine.local/fail2ban_sync.php >> /var/log/fail2ban_sync.log 2>&1
 */

require_once __DIR__ . '/env.php';

$FAIL2BAN_LOG      = env('FAIL2BAN_LOG', '/var/log/fail2ban.log');
$CRON_API_TOKEN    = env('CRON_API_TOKEN', '');
$INTRUSION_PHP_URL = env('INTRUSION_PHP_URL', 'http://portail.cpanel/intrusion.php');
$LAST_SYNC_FILE    = __DIR__ . '/.fail2ban_last_sync';

function normalizeMacAddress($macRaw)
{
    $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));
    if (strlen($cleanMac) !== 12) {
        return false;
    }
    return implode(':', str_split($cleanMac, 2));
}

function tryDbConnection()
{
    try {
        $host = env('DB_HOST', 'localhost');
        $port = (int) env('DB_PORT', 5432);
        $name = env('DB_NAME', '');
        $user = env('DB_USER', '');
        $pass = env('DB_PASS', '');
        if ($name === '' || $user === '') {
            return null;
        }
        return new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $name),
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (Throwable $e) {
        echo "[fail2ban_sync] Avertissement: BDD indisponible pour résolution IP→MAC\n";
        return null;
    }
}

function resolveMacFromIp($pdo, $ip)
{
    if (!$pdo || $ip === '') {
        return '';
    }
    try {
        $sql = "SELECT callingstationid
                FROM radacct
                WHERE framedipaddress = ?::inet
                  AND callingstationid IS NOT NULL
                  AND callingstationid <> ''
                ORDER BY (acctstoptime IS NULL) DESC,
                         COALESCE(acctupdatetime, acctstarttime) DESC NULLS LAST
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip]);
        $raw = $stmt->fetchColumn();
        if (!$raw) {
            return '';
        }
        $mac = normalizeMacAddress($raw);
        return $mac !== false ? $mac : '';
    } catch (Throwable $e) {
        return '';
    }
}

function fetchFail2banLog($logPath)
{
    if (!file_exists($logPath)) {
        return ['success' => false, 'missing' => true, 'error' => "Fichier introuvable: $logPath"];
    }
    if (!is_readable($logPath)) {
        return ['success' => false, 'error' => "Fichier illisible: $logPath"];
    }
    $content = file_get_contents($logPath);
    if ($content === false) {
        return ['success' => false, 'error' => "Impossible de lire: $logPath"];
    }
    return ['success' => true, 'log' => $content];
}

function mapFail2banEvent($jail, $action)
{
    $jail = strtolower((string) $jail);
    $action = strtolower((string) $action);

    if ($action === 'unban') {
        return ['event_type' => 'brute_force', 'severity' => 'info'];
    }

    $criticalJails = ['sshd', 'sshd-ddos', 'recidive'];
    if (in_array($jail, $criticalJails, true)) {
        return ['event_type' => 'brute_force', 'severity' => 'critical'];
    }

    return ['event_type' => 'brute_force', 'severity' => 'warning'];
}

function parseFail2banLog($logContent, $sinceTimestamp = null)
{
    $events = [];
    $foundCounts = [];
    $lines = explode("\n", $logContent);

    $actionPattern = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:,\d+)?\s+fail2ban\.actions(?:\s+\[[^\]]+\])?:\s+\w+\s+\[([^\]]+)\]\s+(Ban|Unban|Restore Ban)\s+(\S+)/i';
    $foundPattern  = '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:,\d+)?\s+fail2ban\.filter(?:\s+\[[^\]]+\])?:\s+\w+\s+\[([^\]]+)\]\s+Found\s+(\S+)/i';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match($foundPattern, $line, $m)) {
            $timestamp = strtotime($m[1]);
            if (!$timestamp || ($sinceTimestamp && $timestamp <= $sinceTimestamp)) {
                continue;
            }
            $ip = $m[3];
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $key = strtolower($m[2]) . '|' . $ip;
            $foundCounts[$key] = ($foundCounts[$key] ?? 0) + 1;
            continue;
        }

        if (!preg_match($actionPattern, $line, $m)) {
            continue;
        }
        if (strcasecmp($m[3], 'Restore Ban') === 0) {
            continue;
        }

        $timestamp = strtotime($m[1]);
        if (!$timestamp || ($sinceTimestamp && $timestamp <= $sinceTimestamp)) {
            continue;
        }

        $jail   = $m[2];
        $action = strtolower($m[3]);
        $ip     = $m[4];
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            continue;
        }

        $mapped   = mapFail2banEvent($jail, $action);
        $countKey = strtolower($jail) . '|' . $ip;
        $attempts = max(1, (int) ($foundCounts[$countKey] ?? 1));

        if ($action === 'ban') {
            $scope = ($mapped['severity'] === 'critical')
                ? 'IP + MAC si l\'appareil est connu'
                : 'IP uniquement';
            $description = sprintf(
                'Fail2ban a banni %s (%s, jail %s, %d tentative(s))',
                $ip,
                $scope,
                $jail,
                $attempts
            );
        } else {
            $description = sprintf('Fail2ban a débanni l\'IP %s (jail %s)', $ip, $jail);
        }

        $events[] = [
            'event_type'    => $mapped['event_type'],
            'severity'      => $mapped['severity'],
            'source_ip'     => $ip,
            'mac_address'   => '',
            'description'   => $description,
            'source_info'   => 'Fail2ban',
            'attempts'      => $attempts,
            'raw_timestamp' => $timestamp,
        ];
    }

    usort($events, function ($a, $b) {
        return $a['raw_timestamp'] - $b['raw_timestamp'];
    });

    return $events;
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
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => $curlErr !== '' ? $curlErr : 'erreur curl'];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'réponse invalide'];
}

if ($CRON_API_TOKEN === '') {
    echo "[fail2ban_sync] Erreur: CRON_API_TOKEN non configuré dans le .env\n";
    exit(1);
}

$lastSync = file_exists($LAST_SYNC_FILE) ? (int) file_get_contents($LAST_SYNC_FILE) : null;

echo "[fail2ban_sync] Lecture locale de $FAIL2BAN_LOG...\n";
$result = fetchFail2banLog($FAIL2BAN_LOG);

if (!$result['success']) {
    if (!empty($result['missing'])) {
        echo "[fail2ban_sync] Info: " . $result['error'] . " (Fail2ban pas encore installé ?)\n";
        exit(0);
    }
    echo "[fail2ban_sync] Erreur: " . $result['error'] . "\n";
    exit(1);
}

$events = parseFail2banLog($result['log'], $lastSync);
echo "[fail2ban_sync] " . count($events) . " nouvel(le)(s) événement(s) Fail2ban\n";

$pdo = tryDbConnection();
$inserted = 0;
$blocked = 0;
$latestTimestamp = $lastSync ?: 0;

foreach ($events as $alert) {
    if ($alert['source_ip'] !== '') {
        $alert['mac_address'] = resolveMacFromIp($pdo, $alert['source_ip']);
    }

    $res = pushIntrusion($INTRUSION_PHP_URL, $CRON_API_TOKEN, $alert);
    if ($res && ($res['success'] ?? false)) {
        $inserted++;
        if (strpos($res['message'] ?? '', 'bloqué') !== false) {
            $blocked++;
        }
    } else {
        echo "[fail2ban_sync] Échec insertion: " . ($res['message'] ?? 'erreur inconnue') . "\n";
    }

    if ($alert['raw_timestamp'] > $latestTimestamp) {
        $latestTimestamp = $alert['raw_timestamp'];
    }
}

if ($latestTimestamp > ($lastSync ?: 0)) {
    file_put_contents($LAST_SYNC_FILE, (string) $latestTimestamp);
}

echo "[fail2ban_sync] Terminé: $inserted insérée(s), $blocked MAC bloquée(s)\n";
