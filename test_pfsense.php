<?php
/**
 * Script de diagnostic pfSense.
 * Lancer depuis un navigateur ou en CLI : php test_pfsense.php [MAC]
 *
 * Usage navigateur : https://votre-app/test_pfsense.php?mac=aa:bb:cc:dd:ee:ff
 * Usage CLI        : php test_pfsense.php aa:bb:cc:dd:ee:ff
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/pfsense_disconnect.php';

function out($label, $val) {
    if (PHP_SAPI === 'cli') {
        echo "[*] $label : ";
        if (is_array($val) || is_object($val)) print_r($val);
        else echo $val . "\n";
    } else {
        echo "<p><b>" . htmlspecialchars($label) . " : </b><pre style='display:inline;background:#f4f4f4;padding:2px 6px;'>" . htmlspecialchars(print_r($val, true)) . "</pre></p>";
    }
}

if (PHP_SAPI !== 'cli') {
    echo "<h2>Diagnostic pfSense - Mine.local</h2><hr>";
    $mac = $_GET['mac'] ?? '';
} else {
    $mac = $argv[1] ?? '';
}

// 1. Vérifier curl
out("Extension curl", function_exists('curl_init') ? 'OK' : 'MANQUANTE');
out("Extension simplexml", function_exists('simplexml_load_string') ? 'OK' : 'MANQUANTE');

// 2. Afficher la config chargée
$pf = get_pfsense_config();
out("Configuration pfSense chargée", [
    'host' => $pf['host'] ?: '(vide)',
    'port' => $pf['port'],
    'user' => $pf['user'],
    'pass' => $pf['pass'] ? str_repeat('*', strlen($pf['pass'])) . ' (' . strlen($pf['pass']) . ' chars)' : '(vide)',
    'use_https' => $pf['use_https'] ? 'true' : 'false',
    'verify_ssl' => $pf['verify_ssl'] ? 'true' : 'false',
    'cp_zone' => $pf['cp_zone'] ?: '(vide = toutes les zones)',
    'configured' => $pf['configured'] ? 'OK' : 'NON (host ou pass manquant)',
]);

if (!$pf['configured']) {
    out("ERREUR", "Config pfSense incomplète dans .env");
    exit;
}

// 3. Test de connexion simple (appel qui liste juste les zones CP)
$testCode = '
    require_once("/etc/inc/captiveportal.inc");
    $zones = [];
    if (function_exists("captiveportal_zones")) {
        foreach (captiveportal_zones() as $z) { $zones[] = $z["zone"]; }
    }
    $cp_db = is_array(captiveportal_read_db()) ? count(captiveportal_read_db()) : 0;
    return json_encode(["zones" => $zones, "active_sessions" => $cp_db]);
';
out("Test de connexion à pfSense", "en cours...");
$result = _pfsense_xmlrpc_call($pf['use_https'], $pf['port'], $pf, $testCode);
if (!$result['ok']) {
    // essayer fallback
    $fallbackHttps = !$pf['use_https'];
    $fallbackPort = ($pf['port'] === 443) ? 80 : ($pf['port'] === 80 ? 443 : $pf['port']);
    out("  Tentative principale échouée", $result['error']);
    out("  Tentative fallback $fallbackHttps:$fallbackPort", "en cours...");
    $result = _pfsense_xmlrpc_call($fallbackHttps, $fallbackPort, $pf, $testCode);
}
if ($result['ok']) {
    $data = @json_decode($result['value'], true);
    out("✅ Connexion pfSense OK", $data ?: $result['value']);
} else {
    out("❌ Échec de connexion à pfSense", $result['error']);
    out("  Conseils", [
        "Vérifiez PFSENSE_HOST (IP pfSense accessible depuis le serveur web)",
        "Vérifiez PFSENSE_PORT (par défaut 443 pour HTTPS, 80 pour HTTP)",
        "Si votre pfSense n'écoute qu'en HTTP, mettez PFSENSE_USE_HTTPS=false dans .env",
        "Vérifiez PFSENSE_USER / PFSENSE_PASS",
        "Ajoutez une règle de pare-feu sur pfSense qui autorise l'IP de ce serveur à joindre l'interface d'admin de pfSense",
        "Vérifiez que 'Disable webConfigurator redirect rule' n'est PAS coché si le port d'admin est différent de 443",
        "Le compte utilisateur doit avoir le droit 'WebCfg - System: HA sync' ou être admin"
    ]);
}

// 4. Si une MAC est fournie, tester la déconnexion
if ($mac !== '') {
    try {
        $macClean = preg_replace('/[^a-fA-F0-9]/', '', $mac);
        if (strlen($macClean) !== 12) {
            out("ERREUR MAC", "Format invalide");
        } else {
            $macFmt = strtolower(implode(':', str_split($macClean, 2)));
            out("MAC testée", $macFmt);

            // Chercher dans radacct
            $stmt = $connexion->prepare("SELECT username, callingstationid, framedipaddress::text AS ip, acctstarttime, acctstoptime FROM radacct WHERE regexp_replace(lower(callingstationid), '[^0-9a-f]', '', 'g') = ? ORDER BY acctstarttime DESC LIMIT 5");
            $stmt->execute([$macClean]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            out("Sessions dans radacct (5 dernières)", $rows ?: "Aucune");

            out("Appel de déconnexion", "en cours...");
            $res = pfsense_disconnect_mac($macFmt, $connexion);
            out("Résultat déconnexion", $res);
        }
    } catch (Throwable $e) {
        out("ERREUR", $e->getMessage());
    }
} else {
    out("Info", "Pour tester la déconnexion d'une MAC réelle, appelez ce script avec ?mac=xx:xx:xx:xx:xx:xx");
}
