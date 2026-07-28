<?php
/**
 * Script de diagnostic ultra-minimal, aucune dépendance.
 * Place ce fichier DANS LE MEME DOSSIER que radius_devices.php sur ton serveur,
 * puis ouvre https://192.168.0.1/test_pf.php dans ton navigateur.
 */

// --- MODIFIE CES VALEURS DIRECTEMENT ICI POUR LE TEST (pas besoin du .env pour l'instant) ---
$pf_host = '192.168.0.254'; // ← METS L'IP DE TON PFSENSE ICI (souvent ...1 ou ...254)
$pf_port = 443;            // port admin pfSense
$pf_user = 'admin';        // login admin pfSense
$pf_pass = 'TON_MOT_DE_PASSE_ADMIN_PFSENSE'; // ← METS LE MOT DE PASSE PFSENSE ICI
$use_https = true;         // passe à false si tu accèdes à pfSense en http://
$verify_ssl = false;

echo "<h2>Test de connexion pfSense</h2><pre>";

// Vérifier curl
if (!function_exists('curl_init')) {
    echo "❌ ERREUR : Extension PHP curl manquante sur le serveur\n";
    exit;
}
echo "✅ Extension curl présente\n";

$test_code = '
    require_once("/etc/inc/captiveportal.inc");
    $zones = [];
    if (function_exists("captiveportal_zones")) {
        foreach (captiveportal_zones() as $z) { $zones[] = $z["zone"]; }
    }
    $sessions = captiveportal_read_db();
    return json_encode([
        "zones_cp" => $zones,
        "nb_sessions_cp_actives" => is_array($sessions) ? count($sessions) : -1,
        "pfsense_version" => trim(@file_get_contents("/etc/version")),
    ]);
';

function xmlrpc_call($https, $port, $host, $user, $pass, $verify, $code) {
    $proto = $https ? 'https' : 'http';
    $url = "$proto://$host:$port/xmlrpc.php";
    foreach (['pfsense.exec_php', 'exec_php'] as $method) {
        $xml = '<?xml version="1.0"?><methodCall><methodName>'.$method.'</methodName><params><param><value><string>'.htmlspecialchars($code, ENT_XML1).'</string></value></param></params></methodCall>';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
            CURLOPT_USERPWD => "$user:$pass",
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return ['ok' => false, 'err' => "$proto:$port → $err"];
        if ($http !== 200) {
            $msg = "$proto:$port → HTTP $http";
            if ($http === 401) $msg .= ' (mauvais identifiants)';
            if ($http === 403) $msg .= ' (accès refusé / droits manquants)';
            return ['ok' => false, 'err' => $msg];
        }
        libxml_use_internal_errors(true);
        $x = simplexml_load_string($resp);
        if (!$x) return ['ok' => false, 'err' => 'réponse XML invalide'];
        return ['ok' => true, 'val' => (string)$x->params->param->value->string];
    }
    return ['ok' => false, 'err' => 'méthode XML-RPC non disponible'];
}

echo "🔍 Tentative de connexion à pfSense $pf_host:$pf_port (".($use_https?'HTTPS':'HTTP').")...\n";
$res = xmlrpc_call($use_https, $pf_port, $pf_host, $pf_user, $pf_pass, $verify_ssl, $test_code);
if (!$res['ok']) {
    echo "❌ Échec tentative 1 : {$res['err']}\n";
    echo "🔍 Tentative de fallback (l'autre protocole/port)...\n";
    $fallback_https = !$use_https;
    $fallback_port = ($pf_port === 443) ? 80 : ($pf_port === 80 ? 443 : $pf_port);
    $res = xmlrpc_call($fallback_https, $fallback_port, $pf_host, $pf_user, $pf_pass, $verify_ssl, $test_code);
}
if ($res['ok']) {
    echo "✅ CONNEXION A PFSENSE RÉUSSIE !\n";
    $data = json_decode($res['val'], true);
    if (is_array($data)) {
        echo "Version pfSense : {$data['pfsense_version']}\n";
        echo "Zones de portail captif : " . (count($data['zones_cp']) ? implode(', ', $data['zones_cp']) : 'AUCUNE') . "\n";
        echo "Nombre de sessions actives dans le portail captif : {$data['nb_sessions_cp_actives']}\n";
        if ($data['nb_sessions_cp_actives'] === 0) {
            echo "\n⚠️ IMPORTANT : Il n'y a AUCUNE session active dans le portail captif pfSense !\n";
            echo "Cela signifie que vos clients NE S'AUTHENTIFIENT PAS via le portail captif pfSense,\n";
            echo "mais directement via 802.1X/MAB sur vos switchs/AP. La déconnexion ne peut donc pas\n";
            echo "se faire via pfSense : il faut envoyer des paquets RADIUS CoA/Disconnect aux switchs.\n";
        }
    } else {
        echo "Réponse pfSense : {$res['val']}\n";
    }
} else {
    echo "❌ ÉCHEC DE CONNEXION : {$res['err']}\n\n";
    echo "👉 Vérifie dans l'ordre :\n";
    echo "1. Que l'IP $pf_host est bien la bonne IP de pfSense\n";
    echo "2. Que le port $pf_port est bien le port de l'interface d'administration\n";
    echo "3. Que le serveur web (192.168.0.1) est autorisé par le pare-feu pfSense à accéder au port d'admin\n";
    echo "4. Que les identifiants $pf_user / mot de passe sont corrects\n";
    echo "5. Si pfSense est en HTTP seulement, change \$use_https = false\n";
}
echo "</pre>";
