<?php
/**
 * Helper de déconnexion immédiate d'un client du portail captif pfSense via API XML-RPC native
 * Ne nécessite aucune extension PHP autre que curl (activé par défaut).
 *
 * La configuration pfSense est récupérée de manière centralisée via get_pfsense_config()
 * qui lit le .env (voir env.php).
 */

/**
 * Déconnecte immédiatement une adresse MAC du portail captif pfSense
 * @param string $mac Adresse MAC au format xx:xx:xx:xx:xx:xx
 * @return array ['success' => bool, 'message' => string]
 */
function pfsense_disconnect_mac(string $mac): array {
    // Vérifier que curl est disponible
    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'Extension PHP curl manquante, impossible de contacter pfSense'];
    }

    // Récupérer la configuration de façon centralisée (aucun appel direct à env() ici)
    $pf = get_pfsense_config();

    // Si la configuration pfSense n'est pas remplie, on s'arrête sans erreur bloquante
    if (!$pf['configured']) {
        return ['success' => false, 'message' => 'Configuration pfSense manquante dans .env (PFSENSE_HOST/PFSENSE_PASS), déconnexion distante ignorée'];
    }

    // Normaliser la MAC pour le code qui s'exécutera sur pfSense
    $mac = strtolower(trim($mac));
    if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $mac)) {
        return ['success' => false, 'message' => 'Format de MAC invalide pour la déconnexion pfSense'];
    }

    // Code PHP qui sera exécuté directement sur pfSense
    // Il utilise les fonctions natives du portail captif et la gestion des états pf
    $pfSensePhpCode = sprintf(
        '
        require_once("/etc/inc/captiveportal.inc");
        require_once("/etc/inc/util.inc");
        $search_mac = strtolower("%s");
        $target_zone = "%s";
        $disconnected = 0;
        $errors = [];
        // Récupérer toutes les sessions actives du portail captif
        $sessions = captiveportal_read_db();
        if (!is_array($sessions)) $sessions = [];
        foreach ($sessions as $session) {
            // Filtrer par zone si demandé
            if (!empty($target_zone) && $session["cpzone"] !== $target_zone) continue;
            // Ignorer les sessions dont la MAC ne correspond pas
            if (strtolower($session["mac"]) !== $search_mac) continue;
            // Déconnecter la session du portail captif
            $disconnect_result = captiveportal_disconnect($session, true, "Déconnecté par l\'administration (app RADIUS)");
            if (!$disconnect_result) {
                $errors[] = "Échec de déconnexion de la session IP " . $session["ip"];
                continue;
            }
            // Tuer TOUS les états de pare-feu associés à l\'IP du client (coupe immédiatement tous les flux)
            @mwexec("/sbin/pfctl -k " . escapeshellarg($session["ip"]) . " 2>/dev/null");
            @mwexec("/sbin/pfctl -k 0.0.0.0/0 -k " . escapeshellarg($session["ip"]) . " 2>/dev/null");
            $disconnected++;
        }
        if (!empty($errors)) {
            return "WARN: " . $disconnected . " session(s) déconnectée(s), erreurs: " . implode(", ", $errors);
        }
        return "OK: " . $disconnected . " session(s) du portail captif déconnectée(s) pour la MAC " . $search_mac;
        ',
        $mac,
        $pf['cp_zone']
    );

    // Construire la requête XML-RPC (pas besoin d'extension PHP xmlrpc, on génère le XML manuellement)
    $xmlPayload = sprintf(
        '<?xml version="1.0" encoding="UTF-8"?>
        <methodCall>
            <methodName>%s</methodName>
            <params>
                <param>
                    <value><string>%s</string></value>
                </param>
            </params>
        </methodCall>',
        'pfsense.exec_php',
        htmlspecialchars($pfSensePhpCode, ENT_XML1, 'UTF-8')
    );

    // Appel XML-RPC via cURL
    $url = sprintf('https://%s:%d/xmlrpc.php', $pf['host'], $pf['port']);
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Gérer les erreurs de connexion
    if ($curlError) {
        // Essayer l'ancien nom de méthode exec_php si pfsense.exec_php échoue (compatibilité pfSense < 2.5)
        $xmlPayload = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>
            <methodCall>
                <methodName>exec_php</methodName>
                <params>
                    <param>
                        <value><string>%s</string></value>
                    </param>
                </params>
            </methodCall>',
            htmlspecialchars($pfSensePhpCode, ENT_XML1, 'UTF-8')
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => 'Erreur de connexion à pfSense : ' . $curlError];
        }
    }

    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Erreur pfSense : code HTTP ' . $httpCode . ' (vérifiez identifiants et accès au port d\'administration)'];
    }

    // Parser la réponse XML-RPC
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($response);
    if (!$xml) {
        return ['success' => false, 'message' => 'Réponse invalide de pfSense'];
    }

    // Rechercher la valeur de retour
    $returnValue = (string)$xml->params->param->value->string ?? '';
    if (str_starts_with($returnValue, 'OK:')) {
        return ['success' => true, 'message' => $returnValue];
    }
    if (str_starts_with($returnValue, 'WARN:')) {
        return ['success' => true, 'message' => $returnValue];
    }
    return ['success' => false, 'message' => 'Erreur pfSense : ' . $returnValue];
}
