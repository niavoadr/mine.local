<?php
require_once __DIR__ . '/env.php';

$FAIL2BAN_LOG      = env('FAIL2BAN_LOG', '/var/log/fail2ban.log');
$CRON_API_TOKEN    = env('CRON_API_TOKEN', '');
$INTRUSION_PHP_URL = env('INTRUSION_PHP_URL', 'http://portail.cpanel/intrusion.php');
$LAST_SYNC_FILE    = __DIR__ . '/.fail2ban_last_sync';
$SEEN_IPS_FILE     = __DIR__ . '/.fail2ban_seen_ips';

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
    $chunks = [];

    if (file_exists($logPath) && is_readable($logPath)) {
        $content = file_get_contents($logPath);
        if ($content !== false && trim($content) !== '') {
            $chunks[] = $content;
        }
    }

    $journal = [];
    exec('journalctl -u fail2ban --no-pager -o short-iso --since "7 days ago" 2>/dev/null', $journal);
    if (!empty($journal)) {
        $chunks[] = implode("\n", $journal);
    }

    if (empty($chunks)) {
        if (!file_exists($logPath)) {
            return ['success' => false, 'missing' => true, 'error' => "Fichier introuvable: $logPath"];
        }
        return ['success' => true, 'log' => '', 'empty' => true];
    }

    return ['success' => true, 'log' => implode("\n", $chunks)];
}

function fail2banClient($args)
{
    foreach (['fail2ban-client ', 'sudo -n fail2ban-client '] as $prefix) {
        $out = [];
        $code = 0;
        exec($prefix . $args . ' 2>/dev/null', $out, $code);
        if ($code === 0 && !empty($out)) {
            return $out;
        }
    }
    return [];
}

function fetchCurrentlyBanned()
{
    $banned = [];
    $status = fail2banClient('status');
    $jails = [];
    foreach ($status as $line) {
        if (preg_match('/Jail list:\s*(.+)$/i', $line, $m)) {
            $jails = array_map('trim', explode(',', $m[1]));
        }
    }
    foreach ($jails as $jail) {
        if ($jail === '') {
            continue;
        }
        $out = fail2banClient('status ' . escapeshellarg($jail));
        foreach ($out as $line) {
            if (preg_match('/Banned IP list:\s*(.*)$/i', $line, $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $banned[] = ['jail' => $jail, 'ip' => $ip];
                    }
                }
            }
        }
    }
    return $banned;
}

function loadSeenIps($file)
{
    if (!file_exists($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return is_array($lines) ? $lines : [];
}

function saveSeenIps($file, array $keys)
{
    file_put_contents($file, implode("\n", array_values(array_unique($keys))) . "\n");
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

function parseLogTimestamp($raw)
{
    $raw = trim((string) $raw);
    $ts = strtotime($raw);
    if ($ts) {
        return $ts;
    }
    $ts = strtotime($raw . ' ' . date('Y'));
    return $ts ?: time();
}

function buildEvent($jail, $action, $ip, $attempts, $timestamp)
{
    $mapped = mapFail2banEvent($jail, $action);
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

    return [
        'event_type'    => $mapped['event_type'],
        'severity'      => $mapped['severity'],
        'source_ip'     => $ip,
        'mac_address'   => '',
        'description'   => $description,
        'source_info'   => 'Fail2ban',
        'attempts'      => $attempts,
        'raw_timestamp' => $timestamp,
        'seen_key'      => strtolower($jail) . '|' . $action . '|' . $ip,
    ];
}

function parseFail2banLog($logContent, $sinceTimestamp = null)
{
    $events = [];
    $foundCounts = [];
    $lines = explode("\n", $logContent);

    $actionPatterns = [
        '/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})(?:[.,]\d+)?(?:[+-]\d{2}:?\d{2})?\s+.*\[([^\]]+)\]\s+(Ban|Unban|Restore Ban)\s+(\S+)/i',
        '/^(\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+\S+\s+fail2ban(?:\[\d+\])?:\s+\w+\s+\[([^\]]+)\]\s+(Ban|Unban|Restore Ban)\s+(\S+)/i',
    ];
    $foundPattern = '/\[([^\]]+)\]\s+Found\s+(\S+)/i';

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (preg_match($foundPattern, $line, $m)) {
            $ip = $m[2];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $key = strtolower($m[1]) . '|' . $ip;
                $foundCounts[$key] = ($foundCounts[$key] ?? 0) + 1;
            }
            continue;
        }

        $matched = null;
        foreach ($actionPatterns as $pattern) {
            if (preg_match($pattern, $line, $m)) {
                $matched = $m;
                break;
            }
        }
        if ($matched === null || strcasecmp($matched[3], 'Restore Ban') === 0) {
            continue;
        }

        $timestamp = parseLogTimestamp($matched[1]);
        if ($sinceTimestamp && $timestamp <= $sinceTimestamp) {
            continue;
        }

        $jail   = $matched[2];
        $action = strtolower($matched[3]);
        $ip     = $matched[4];
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            continue;
        }

        $countKey = strtolower($jail) . '|' . $ip;
        $events[] = buildEvent($jail, $action, $ip, max(1, (int) ($foundCounts[$countKey] ?? 1)), $timestamp);
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
$seenIps  = loadSeenIps($SEEN_IPS_FILE);

echo "[fail2ban_sync] Lecture de $FAIL2BAN_LOG (+ journald)...\n";
$result = fetchFail2banLog($FAIL2BAN_LOG);

if (!$result['success']) {
    if (!empty($result['missing'])) {
        echo "[fail2ban_sync] Info: " . $result['error'] . " (Fail2ban pas encore installé ?)\n";
    } else {
        echo "[fail2ban_sync] Erreur: " . $result['error'] . "\n";
        exit(1);
    }
    $result = ['success' => true, 'log' => ''];
}

$lineCount = substr_count($result['log'], "\n");
echo "[fail2ban_sync] Journal: $lineCount ligne(s)\n";

$events = parseFail2banLog($result['log'] ?? '', $lastSync);

if (empty($events)) {
    $live = fetchCurrentlyBanned();
    echo "[fail2ban_sync] Repli fail2ban-client: " . count($live) . " IP bannie(s) en cours\n";
    foreach ($live as $row) {
        $key = strtolower($row['jail']) . '|ban|' . $row['ip'];
        if (in_array($key, $seenIps, true)) {
            continue;
        }
        $events[] = buildEvent($row['jail'], 'ban', $row['ip'], 5, time());
    }
}

echo "[fail2ban_sync] " . count($events) . " nouvel(le)(s) événement(s) Fail2ban\n";

$pdo = tryDbConnection();
$inserted = 0;
$blocked = 0;
$latestTimestamp = $lastSync ?: 0;

foreach ($events as $alert) {
    $seenKey = $alert['seen_key'] ?? '';
    if ($seenKey !== '' && in_array($seenKey, $seenIps, true)) {
        continue;
    }

    if ($alert['source_ip'] !== '') {
        $alert['mac_address'] = resolveMacFromIp($pdo, $alert['source_ip']);
    }

    $res = pushIntrusion($INTRUSION_PHP_URL, $CRON_API_TOKEN, $alert);
    if ($res && ($res['success'] ?? false)) {
        $inserted++;
        if ($seenKey !== '') {
            $seenIps[] = $seenKey;
        }
        if (strpos($res['message'] ?? '', 'appareil bloqué') !== false) {
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
saveSeenIps($SEEN_IPS_FILE, $seenIps);

echo "[fail2ban_sync] Terminé: $inserted insérée(s), $blocked MAC bloquée(s)\n";
