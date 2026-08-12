<?php
/**
 * fail2ban_sync.php — Synchronisation des bans Fail2ban vers security_event.
 *
 * Même tuyau que snort_sync.php, source séparée :
 *   Fail2ban.log  →  parse Ban/Unban  →  POST intrusion.php (source_info=Fail2ban)
 *
 * Politique de blocage (Fail2ban seul, jamais Snort) :
 *   warning  → IP déjà bannie par iptables, pas de blacklist MAC
 *   critical → IP (iptables) + MAC (blacklist / radcheck) si résolue via radacct
 *
 * Utilisation cron (toutes les 2 minutes) :
 *   */2 * * * * /usr/bin/php /chemin/vers/mine.local/fail2ban_sync.php >> /var/log/fail2ban_sync.log 2>&1
 *
 * Prérequis :
 *   - Fail2ban installé sur le serveur web (ou joignable en SSH)
 *   - Filtre / jail fournis dans fail2ban/ copiés vers /etc/fail2ban/
 *   - CRON_API_TOKEN configuré dans le .env (le même que pour Snort)
 */

require_once __DIR__ . '/env.php';

// ============ CONFIGURATION ============
$FAIL2BAN_LOG         = env('FAIL2BAN_LOG', '/var/log/fail2ban.log');
$FAIL2BAN_SSH_HOST    = env('FAIL2BAN_SSH_HOST', '');
$FAIL2BAN_SSH_PORT    = (int) env('FAIL2BAN_SSH_PORT', '22');
$FAIL2BAN_SSH_USER    = env('FAIL2BAN_SSH_USER', '');
$FAIL2BAN_SSH_KEY     = env('FAIL2BAN_SSH_KEY', '');
$CRON_API_TOKEN       = env('CRON_API_TOKEN', '');
$INTRUSION_PHP_URL    = env('INTRUSION_PHP_URL', 'http://localhost/intrusion.php');
$LAST_SYNC_FILE       = __DIR__ . '/.fail2ban_last_sync';

// Si aucune clé dédiée n'est fournie, on réutilise celle de pfSense (même machine distante)
if ($FAIL2BAN_SSH_KEY === '') {
    $FAIL2BAN_SSH_KEY = env('PFSENSE_SSH_KEY', '');
}

// ============ FONCTIONS ============

/**
 * Normalise une adresse MAC au format xx:xx:xx:xx:xx:xx (minuscules).
 * Copie locale — on n'importe pas blacklist.php / intrusion.php.
 */
function normalizeMacAddress($macRaw)
{
    $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));
    if (strlen($cleanMac) !== 12) {
        return false;
    }
    return implode(':', str_split($cleanMac, 2));
}

/**
 * Connexion BDD optionnelle (uniquement pour résoudre IP → MAC).
 * Si la BDD est indisponible, le sync continue sans MAC : l'événement
 * est quand même enregistré, sans auto-blocage RADIUS.
 */
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

/**
 * Résout une IP vers la dernière MAC vue dans radacct (session active d'abord).
 */
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

/**
 * Lit le journal Fail2ban en local, ou via SSH si FAIL2BAN_SSH_HOST est défini.
 */
function fetchFail2banLog($logPath, $sshHost, $sshPort, $sshUser, $keyPath)
{
    if ($sshHost !== '') {
        $sshCmd = sprintf(
            'ssh -o StrictHostKeyChecking=no -o BatchMode=yes -o ConnectTimeout=10 -p %d -i %s %s@%s "cat %s 2>/dev/null"',
            $sshPort,
            escapeshellarg($keyPath),
            escapeshellarg($sshUser),
            escapeshellarg($sshHost),
            escapeshellarg($logPath)
        );
        $outputLines = [];
        $exitCode = 0;
        exec($sshCmd . ' 2>&1', $outputLines, $exitCode);
        if ($exitCode !== 0) {
            $error = implode("\n", $outputLines);
            return ['success' => false, 'error' => "SSH échoué (code $exitCode): $error"];
        }
        return ['success' => true, 'log' => implode("\n", $outputLines)];
    }

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

/**
 * Associe un jail + action Fail2ban à un event_type / sévérité du dashboard.
 *   Ban sshd / recidive → critical → blocage IP + MAC
 *   Autres Ban          → warning  → blocage IP seulement
 *   Unban               → info     → aucun blocage (on ne débloque pas la MAC)
 */
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

/**
 * Parse /var/log/fail2ban.log.
 *
 * Formats supportés :
 *   2026-08-12 14:30:00,123 fail2ban.actions [1234]: NOTICE  [sshd] Ban 192.168.1.50
 *   2026-08-12 14:35:00,123 fail2ban.actions [1234]: NOTICE  [sshd] Unban 192.168.1.50
 *   2026-08-12 14:29:50,001 fail2ban.filter  [1234]: INFO    [sshd] Found 192.168.1.50
 *
 * Les lignes "Restore Ban" (redémarrage de Fail2ban) sont ignorées pour éviter les doublons.
 */
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
            if (!$timestamp) {
                continue;
            }
            if ($sinceTimestamp && $timestamp <= $sinceTimestamp) {
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

        // Restore Ban = rechargement de Fail2ban, déjà connu
        if (strcasecmp($m[3], 'Restore Ban') === 0) {
            continue;
        }

        $timestamp = strtotime($m[1]);
        if (!$timestamp) {
            continue;
        }
        if ($sinceTimestamp && $timestamp <= $sinceTimestamp) {
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
            'timestamp'     => date('Y-m-d H:i:s', $timestamp),
            'raw_timestamp' => $timestamp,
        ];
    }

    usort($events, function ($a, $b) {
        return $a['raw_timestamp'] - $b['raw_timestamp'];
    });

    return $events;
}

/**
 * Pousse un événement vers intrusion.php (action auto_block_intrusion).
 * Identique à snort_sync.php — on ne touche pas à l'API existante.
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
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => $curlErr !== '' ? $curlErr : 'erreur curl'];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'réponse invalide'];
}

// ============ EXÉCUTION PRINCIPALE ============

if ($CRON_API_TOKEN === '') {
    echo "[fail2ban_sync] Erreur: CRON_API_TOKEN non configuré dans le .env\n";
    exit(1);
}

$useSsh = ($FAIL2BAN_SSH_HOST !== '');
$sshKeyTmpFile = null;

if ($useSsh) {
    if ($FAIL2BAN_SSH_USER === '') {
        echo "[fail2ban_sync] Erreur: FAIL2BAN_SSH_USER requis quand FAIL2BAN_SSH_HOST est défini\n";
        exit(1);
    }
    if ($FAIL2BAN_SSH_KEY === '') {
        echo "[fail2ban_sync] Erreur: FAIL2BAN_SSH_KEY (ou PFSENSE_SSH_KEY) requis pour le SSH\n";
        exit(1);
    }
    $sshKeyTmpFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
    file_put_contents($sshKeyTmpFile, $FAIL2BAN_SSH_KEY);
    chmod($sshKeyTmpFile, 0600);
}

$lastSync = null;
if (file_exists($LAST_SYNC_FILE)) {
    $lastSync = (int) file_get_contents($LAST_SYNC_FILE);
}

if ($useSsh) {
    echo "[fail2ban_sync] Connexion SSH à $FAIL2BAN_SSH_HOST...\n";
} else {
    echo "[fail2ban_sync] Lecture locale de $FAIL2BAN_LOG...\n";
}

$result = fetchFail2banLog(
    $FAIL2BAN_LOG,
    $FAIL2BAN_SSH_HOST,
    $FAIL2BAN_SSH_PORT,
    $FAIL2BAN_SSH_USER,
    $sshKeyTmpFile ?: ''
);

if (!$result['success']) {
    if (!empty($result['missing'])) {
        echo "[fail2ban_sync] Info: " . $result['error'] . " (Fail2ban pas encore installé ?)\n";
        if ($sshKeyTmpFile && file_exists($sshKeyTmpFile)) {
            unlink($sshKeyTmpFile);
        }
        exit(0);
    }
    echo "[fail2ban_sync] Erreur: " . $result['error'] . "\n";
    if ($sshKeyTmpFile && file_exists($sshKeyTmpFile)) {
        unlink($sshKeyTmpFile);
    }
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

if ($sshKeyTmpFile && file_exists($sshKeyTmpFile)) {
    unlink($sshKeyTmpFile);
}

echo "[fail2ban_sync] Terminé: $inserted insérée(s), $blocked bloquée(s)\n";
