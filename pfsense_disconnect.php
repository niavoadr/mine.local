<?php
/**
 * Helper de déconnexion immédiate d'un client du portail captif pfSense via API XML-RPC native.
 * Ne nécessite aucune extension PHP autre que curl (activé par défaut).
 *
 * La configuration pfSense est récupérée de manière centralisée via get_pfsense_config() (env.php).
 *
 * Si l'appareil n'est pas trouvé dans les sessions du portail captif, on retombe
 * sur les IPs actives connues dans radacct et on tue directement les états pf correspondants
 * (pfctl -k), de façon à couper l'accès même si le portail captif n'est pas utilisé en mode
 * "session CP" (cas de MAB 802.1X où l'IP vient du DHCP/switch).
 */

/**
 * Déconnecte immédiatement une adresse MAC du portail captif pfSense et tue ses états pf.
 *
 * @param string $mac Adresse MAC au format xx:xx:xx:xx:xx:xx
 * @param PDO|null $pdo Connexion PDO optionnelle pour chercher les IPs actives dans radacct si le portail captif ne les connaît pas
 * @return array ['success' => bool, 'message' => string]
 */
function pfsense_disconnect_mac(string $mac, ?PDO $pdo = null): array {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'Extension PHP curl manquante'];
    }

    $pf = get_pfsense_config();
    if (!$pf['configured']) {
        return ['success' => false, 'message' => 'Configuration pfSense manquante (PFSENSE_HOST/PFSENSE_PASS) dans .env'];
    }

    $mac = strtolower(trim($mac));
    if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)) {
        return ['success' => false, 'message' => 'Format de MAC invalide'];
    }

    // Récupérer la liste des IPs actives connues pour cette MAC (depuis radacct)
    $knownIps = [];
    if ($pdo !== null) {
        try {
            $sql = "SELECT DISTINCT framedipaddress::text AS ip
                    FROM radacct
                    WHERE regexp_replace(lower(callingstationid), '[^0-9a-f]', '', 'g') = ?
                      AND acctstoptime IS NULL
                      AND framedipaddress IS NOT NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([str_replace(':', '', $mac)]);
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (filter_var($r['ip'], FILTER_VALIDATE_IP)) {
                    $knownIps[] = $r['ip'];
                }
            }
        } catch (Throwable $e) {
            // non bloquant
        }
    }

    // Vérifier quel schéma/port utiliser : on essaie d'abord la config telle quelle
    $targetZone = $pf['cp_zone'];
    $ipsJson = json_encode(array_values(array_unique($knownIps)));

    // Code qui s'exécute sur pfSense : tente une déconnexion du portail captif
    // ET purge les états pf pour les IP passées en paramètre.
    $pfSensePhpCode = sprintf(
        '
        require_once("/etc/inc/captiveportal.inc");
        require_once("/etc/inc/util.inc");
        $search_mac = strtolower("%s");
        $target_zone = "%s";
        $known_ips = json_decode(\'%s\', true);
        if (!is_array($known_ips)) $known_ips = [];

        $disconnected_cp = 0;
        $killed_states = 0;
        $ips_killed = [];
        $errors = [];

        function kill_states_for_ip($ip, &$killed_states, &$ips_killed, &$errors) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) return;
            $out1 = @mwexec("/sbin/pfctl -k " . escapeshellarg($ip) . " 2>&1", true);
            $out2 = @mwexec("/sbin/pfctl -k 0.0.0.0/0 -k " . escapeshellarg($ip) . " 2>&1", true);
            // Lister le nombre d\'états restants pour confirmer
            $remaining = trim(@shell_exec("/sbin/pfctl -s states 2>/dev/null | /usr/bin/grep " . escapeshellarg($ip) . " | /usr/bin/wc -l"));
            if (intval($remaining) === 0) {
                $killed_states++;
                $ips_killed[] = $ip;
            } else {
                $errors[] = "États restants pour $ip: " . $remaining;
            }
        }

        // 1) Parcourir les sessions du portail captif
        $sessions = captiveportal_read_db();
        if (is_array($sessions)) {
            foreach ($sessions as $session) {
                if (!empty($target_zone) && ($session["cpzone"] ?? "") !== $target_zone) continue;
                if (strtolower($session["mac"] ?? "") !== $search_mac) continue;
                $ip = $session["ip"] ?? "";
                $res = @captiveportal_disconnect($session, true, "Déconnecté par l\'administration (app RADIUS)");
                if ($res) {
                    $disconnected_cp++;
                    if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, $known_ips)) {
                        $known_ips[] = $ip;
                    }
                } else {
                    $errors[] = "Échec disconnect CP session IP " . $ip;
                }
            }
        }

        // 2) Tuer les états pf pour TOUTES les IPs connues (CP + radacct)
        foreach ($known_ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
            kill_states_for_ip($ip, $killed_states, $ips_killed, $errors);
        }

        return json_encode([
            "cp_disconnected" => $disconnected_cp,
            "states_killed" => $killed_states,
            "ips" => $ips_killed,
            "errors" => $errors
        ]);
        ',
        $mac,
        $targetZone,
        $ipsJson
    );

    // Tenter l'appel XML-RPC : d'abord le schéma configuré, puis les alternatives
    $attempts = [];
    $schema = $pf['use_https'] ?? true;
    if (is_string($schema) && strtolower($schema) === 'false') $schema = false;
    else $schema = true;

    $attempts[] = ['https' => $schema, 'port' => (int)$pf['port']];
    // alternatives automatiques
    if ($schema === true) {
        $attempts[] = ['https' => false, 'port' => ($pf['port'] === 443 ? 80 : $pf['port'])]; // fallback http
    } else {
        $attempts[] = ['https' => true, 'port' => ($pf['port'] === 80 ? 443 : $pf['port'])];
    }

    $lastError = '';
    foreach ($attempts as $i => $att) {
        $result = _pfsense_xmlrpc_call($att['https'], $att['port'], $pf, $pfSensePhpCode);
        if ($result['ok']) {
            // Parser la réponse JSON
            $data = @json_decode($result['value'], true);
            if (is_array($data)) {
                $cp = (int)($data['cp_disconnected'] ?? 0);
                $st = (int)($data['states_killed'] ?? 0);
                $ips = $data['ips'] ?? [];
                $errs = $data['errors'] ?? [];
                $msg = "pfSense: $cp session(s) CP déconnectée(s), $st IP(s) purgée(s) dans pf";
                if (!empty($ips)) $msg .= " (" . implode(', ', $ips) . ")";
                if (!empty($errs)) $msg .= " | warnings: " . implode(' ; ', $errs);
                if ($cp === 0 && $st === 0) {
                    return [
                        'success' => false,
                        'message' => "pfSense contacté mais aucune session/état trouvé pour la MAC $mac. Vérifiez que le client est bien connecté via le portail captif et que la MAC est dans le bon format. L'appareil conservera l'accès jusqu'à sa prochaine reconnexion."
                    ];
                }
                return ['success' => true, 'message' => $msg];
            }
            // Si la réponse n'est pas JSON (vieux pfSense), on la renvoie brute
            $v = trim((string)$result['value']);
            if ($v === '' || str_starts_with($v, 'OK:')) {
                return ['success' => true, 'message' => 'pfSense: déconnexion effectuée (' . $v . ')'];
            }
            return ['success' => false, 'message' => 'Réponse pfSense inattendue: ' . substr($v, 0, 200)];
        }
        $lastError = $result['error'];
    }

    return ['success' => false, 'message' => 'Impossible de contacter pfSense: ' . $lastError];
}

/**
 * Effectue UN appel XML-RPC à pfSense et retourne le résultat ou l'erreur.
 */
function _pfsense_xmlrpc_call(bool $https, int $port, array $pf, string $phpCode): array {
    $proto = $https ? 'https' : 'http';
    $url = sprintf('%s://%s:%d/xmlrpc.php', $proto, $pf['host'], $port);

    // Essayer d'abord pfsense.exec_php (versions récentes), puis exec_php (anciennes)
    foreach (['pfsense.exec_php', 'exec_php'] as $methodName) {
        $xmlPayload = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>
            <methodCall>
                <methodName>%s</methodName>
                <params>
                    <param><value><string>%s</string></value></param>
                </params>
            </methodCall>',
            $methodName,
            htmlspecialchars($phpCode, ENT_XML1, 'UTF-8')
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xmlPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
            CURLOPT_USERPWD => $pf['user'] . ':' . $pf['pass'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $pf['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $pf['verify_ssl'] ? 2 : 0,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['ok' => false, 'error' => "$proto:$port → $curlErr"];
        }
        if ($httpCode !== 200) {
            $errMsg = "$proto:$port → HTTP $httpCode";
            if ($httpCode === 401) $errMsg .= " (identifiants incorrects)";
            if ($httpCode === 403) $errMsg .= " (accès refusé - vérifiez les droits du compte et la règle de pare-feu)";
            if ($httpCode === 0)   $errMsg .= " (pas de réponse - port fermé ou IP injoignable)";
            return ['ok' => false, 'error' => $errMsg];
        }

        // Parser la réponse
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if (!$xml) {
            return ['ok' => false, 'error' => "$proto:$port → réponse XML invalide"];
        }
        $value = (string)($xml->params->param->value->string ?? '');
        if ($value === '') {
            // Quelques réponses ont la valeur dans <struct>
            $value = $response;
        }
        return ['ok' => true, 'value' => $value];
    }
    return ['ok' => false, 'error' => 'méthodes XML-RPC non disponibles'];
}
