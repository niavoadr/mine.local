<?php
// Démarrer la capture de sortie pour éviter les sorties parasites
ob_start();

// Inclure le chargeur .env puis la connexion à la base
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/connexion.php';

// Récupération du secret MAC via la fonction centralisée (comme pour la DB)
try {
  $RADIUS_MAC_SECRET = get_radius_mac_secret();
} catch (Throwable $e) {
  $RADIUS_MAC_SECRET = '';
}

// Nettoyer toute sortie parasite avant d'envoyer les headers
ob_clean();

// Inclure le helper de déconnexion pfSense
require_once __DIR__ . '/pfsense_disconnect.php';

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

try {
  // Vérifier la connexion à la base de données
  if (!isset($connexion) || !$connexion) {
    throw new Exception('Connexion à la base de données échouée');
  }

  switch ($action) {
    case 'get_devices':
      getDevices($connexion);
      break;

    case 'add_device':
      addDevice($connexion);
      break;

    case 'delete_device':
      deleteDevice($connexion);
      break;

    case 'test':
      // Ajouter un test simple
      echo json_encode([
        'success' => true,
        'message' => 'API RADIUS fonctionnelle',
        'timestamp' => date('Y-m-d H:i:s'),
      ]);
      break;

    case 'diag_pfsense':
      diagPfSense($connexion);
      break;

    default:
      echo json_encode([
        'success' => false,
        'error' => 'Action non spécifiée. Actions disponibles: get_devices, add_device, delete_device, test',
      ]);
  }
} catch (Exception $e) {
  // S'assurer qu'on retourne du JSON même en cas d'erreur
  ob_clean();
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'line' => $e->getLine(),
    'file' => basename($e->getFile()),
  ]);
}

// Nettoyer et terminer
ob_end_flush();
exit();

/**
 * Normalise une adresse MAC au format attendu par FreeRADIUS et la base :
 *   xx:xx:xx:xx:xx:xx
 * Les saisies avec majuscules, tirets, points, espaces, etc. sont acceptées
 * tant qu'elles contiennent exactement 12 caractères hexadécimaux.
 */
function normalizeMacAddress($macRaw)
{
  $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));

  if (strlen($cleanMac) !== 12) {
    throw new Exception("Format d'adresse MAC invalide (12 caractères hexadécimaux requis)");
  }

  return implode(':', str_split($cleanMac, 2));
}

/**
 * Retourne la version sans séparateur d'une MAC normalisée, pour comparer
 * proprement avec d'anciennes valeurs stockées en base sous plusieurs formats.
 */
function compactMacAddress($macRaw)
{
  return str_replace(':', '', normalizeMacAddress($macRaw));
}

function normalizedMacSqlWhere()
{
  return "regexp_replace(lower(username), '[^0-9a-f]', '', 'g') = ?";
}

function getDevices(PDO $connexion)
{
  try {
    // Utilisation de GROUP BY pour éviter les doublons si plusieurs attributs radcheck existent pour une même MAC
    $sql = "SELECT 
                    MIN(rc.id) as id,
                    rc.username as mac_address,
                    MIN(rc.department) as department,
                    MAX(rg.groupname) as groupname,
                    MAX(rgr.value) as bandwidth_value
                FROM radcheck rc
                LEFT JOIN radusergroup rg ON LOWER(rc.username) = LOWER(rg.username)  
                LEFT JOIN radgroupreply rgr ON rg.groupname = rgr.groupname AND rgr.attribute = 'WISPr-Bandwidth-Max-Down'
                GROUP BY rc.username
                ORDER BY department, rc.username";

    $stmt = $connexion->query($sql);

    $devices = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      try {
        $displayMac = normalizeMacAddress($row['mac_address']);
      } catch (Exception $e) {
        // Sécurité : si d'anciens enregistrements non-MAC existent dans radcheck,
        // on ne casse pas l'affichage de la page.
        $displayMac = strtolower((string) $row['mac_address']);
      }

      $devices[] = [
        'id' => $row['id'],
        'mac_address' => $displayMac,
        'department' => enumToShortcode($row['department']),
        'bandwidth' => $row['bandwidth_value'] ? round($row['bandwidth_value'] / 1000000) . ' Mbps' : 'N/A',
        'group' => $row['groupname'] ?: 'N/A',
      ];
    }

    echo json_encode(['success' => true, 'data' => $devices, 'count' => count($devices)]);
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur getDevices: ' . $e->getMessage()]);
  }
}

function addDevice(PDO $connexion)
{
  $macRaw = $_POST['mac_address'] ?? '';
  $department = $_POST['department'] ?? '';

  if (empty($macRaw) || empty($department)) {
    throw new Exception('Adresse MAC et département requis');
  }

  // Format requis en base : xx:xx:xx:xx:xx:xx (minuscules avec deux-points)
  $mac = normalizeMacAddress($macRaw);
  $macCompact = compactMacAddress($mac);

  $connexion->beginTransaction();

  try {
    // Correspondance département : shortcode (frontend) -> ENUM (DB) + groupname
    $map = getDepartmentMap();
    if (!isset($map[$department])) {
      throw new Exception('Département invalide');
    }
    $deptEnum = $map[$department]['enum'];
    $groupname = $map[$department]['group'];

    // Vérifier si l'appareil existe déjà, quel que soit le format précédent
    // en base : f8:a2..., F8-A2..., f8a2..., etc.
    $normalizedMacWhere = normalizedMacSqlWhere();
    $stmtCheck = $connexion->prepare("SELECT COUNT(*) FROM radcheck WHERE $normalizedMacWhere");
    $stmtCheck->execute([$macCompact]);
    if ($stmtCheck->fetchColumn() > 0) {
      // Nettoyer les doublons potentiels existants avant ré-insertion propre
      $stmtDel1 = $connexion->prepare("DELETE FROM radcheck WHERE $normalizedMacWhere");
      $stmtDel1->execute([$macCompact]);
      $stmtDel2 = $connexion->prepare("DELETE FROM radusergroup WHERE $normalizedMacWhere");
      $stmtDel2->execute([$macCompact]);
    }    // === NOUVELLE MÉTHODE : RADIUS MAC Authentication (MAB) ===
    // Une seule ligne dans radcheck avec le secret partagé
    // Le secret est relu ici via env.php : une variable globale n'est pas
    // visible dans la portée d'une fonction en PHP.
    $RADIUS_MAC_SECRET = get_radius_mac_secret();

    if ($RADIUS_MAC_SECRET === '') {
      throw new Exception('RADIUS_MAC_SECRET non configuré dans le fichier .env');
    }

    $sql = "INSERT INTO radcheck (username, attribute, op, value, department) 
               VALUES (?, 'Cleartext-Password', ':=', ?, ?)";
    $stmt = $connexion->prepare($sql);
    $stmt->execute([$mac, $RADIUS_MAC_SECRET, $deptEnum]);

    // Associer au groupe départemental dans radusergroup
    $sql2 = "INSERT INTO radusergroup (username, groupname, priority) 
                 VALUES (?, ?, 1)";
    $stmt2 = $connexion->prepare($sql2);
    $stmt2->execute([$mac, $groupname]);

    $connexion->commit();
    echo json_encode(['success' => true, 'message' => 'Appareil ajouté et autorisé avec succès']);
  } catch (Exception $e) {
    if ($connexion->inTransaction()) {
      $connexion->rollBack();
    }
    throw new Exception("Erreur lors de l'ajout: " . $e->getMessage());
  }
}

/**
 * Correspondance entre le shortcode envoyé par le frontend et :
 *   - la valeur de l'ENUM department_enum (colonne radcheck.department)
 *   - le groupname de l'ENUM groupname_enum (colonne radusergroup.groupname)
 */
function getDepartmentMap()
{
  return [
    'communication' => ['enum' => 'Communication', 'group' => 'communication_group'],
    'daj'           => ['enum' => 'Directeur des Affaires Juridiques', 'group' => 'daj_groupe'],
    'finance'       => ['enum' => 'Finance', 'group' => 'finance_group'],
    'rh'            => ['enum' => 'Ressources Humaines', 'group' => 'rh_group'],
    'sg'            => ['enum' => 'Secrétariat Général', 'group' => 'sg_group'],
  ];
}

/**
 * Convertit une valeur de l'ENUM department_enum vers le shortcode attendu par le frontend.
 */
function enumToShortcode($enumValue)
{
  foreach (getDepartmentMap() as $shortcode => $info) {
    if ($info['enum'] === $enumValue) {
      return $shortcode;
    }
  }
  return strtolower((string) $enumValue);
}

function deleteDevice(PDO $connexion)
{
  $macRaw = trim($_POST['mac_address'] ?? '');

  if (empty($macRaw)) {
    throw new Exception('Adresse MAC requise');
  }

  $mac = normalizeMacAddress($macRaw);
  $macCompact = compactMacAddress($mac);

  $connexion->beginTransaction();

  try {
    $normalizedMacWhere = normalizedMacSqlWhere();

    // 1. Supprimer de radusergroup
    $sql1 = "DELETE FROM radusergroup WHERE $normalizedMacWhere";
    $stmt1 = $connexion->prepare($sql1);
    $stmt1->execute([$macCompact]);

    // 2. Supprimer de radcheck
    $sql2 = "DELETE FROM radcheck WHERE $normalizedMacWhere";
    $stmt2 = $connexion->prepare($sql2);
    $stmt2->execute([$macCompact]);

    // 2.bis Ajouter une entrée de REJET explicite pour bloquer toute nouvelle authentification
    // même si la déconnexion pfSense échoue (FreeRADIUS renverra Access-Reject).
    // Cela garantit que l'appareil ne peut pas se ré-authentifier tant qu'un admin ne le réautorise pas.
    $sqlReject = "INSERT INTO radcheck (username, attribute, op, value)
                  VALUES (?, 'Auth-Type', ':=', 'Reject')";
    $stmtRej = $connexion->prepare($sqlReject);
    $stmtRej->execute([$mac]);

    // 3. Clôturer immédiatement toutes les sessions actives dans radacct
    $sqlCloseSessions = "UPDATE radacct
                          SET acctstoptime = NOW(),
                              acctterminatecause = 'Admin-Reset',
                              acctsessiontime = EXTRACT(EPOCH FROM (NOW() - acctstarttime))::bigint
                          WHERE regexp_replace(lower(callingstationid), '[^0-9a-f]', '', 'g') = ?
                            AND acctstoptime IS NULL";
    $stmtClose = $connexion->prepare($sqlCloseSessions);
    $stmtClose->execute([$macCompact]);
    $closedSessions = $stmtClose->rowCount();

    $connexion->commit();

    // 4. Déconnecter immédiatement le client sur pfSense (passe PDO pour lire radacct si besoin)
    $pfResult = pfsense_disconnect_mac($mac, $connexion);

    $message = 'Appareil supprimé. ';
    if ($closedSessions > 0) {
      $message .= "$closedSessions session(s) RADIUS clôturée(s). ";
    }
    if ($pfResult['success']) {
      $message .= '✅ ' . $pfResult['message'];
    } else {
      $message .= '⚠️ Déconnexion pfSense non effective : ' . $pfResult['message']
                . " Une règle de rejet a été ajoutée, l'appareil ne pourra pas se reconnecter,"
                . " mais les flux déjà établis peuvent persister quelques minutes le temps que le bail DHCP / la session expire,"
                . " ou que vous redemandiez une re-auth sur le switch/AP.";
    }

    echo json_encode(['success' => true, 'message' => $message]);
  } catch (Exception $e) {
    if ($connexion->inTransaction()) {
      $connexion->rollBack();
    }
    throw new Exception('Erreur lors de la suppression: ' . $e->getMessage());
  }
}
/**
 * Diagnostic pfSense intégré (pas besoin de fichiers supplémentaires).
 * Appel : radius_devices.php?action=diag_pfsense
 * Paramètres optionnels GET/POST : host, port, user, pass, https (0/1), mac
 * Si omis, utilise get_pfsense_config() depuis env.php.
 */
function diagPfSense(PDO $connexion)
{
  // Sortie texte lisible (mode navigateur ou CLI)
  $html = !(php_sapi_name() === 'cli');
  if ($html) {
    header('Content-Type: text/plain; charset=utf-8');
  }

  $out = function ($label, $val = '') {
    if (is_array($val) || is_object($val)) $val = print_r($val, true);
    echo "[*] " . $label . " : " . $val . "\n";
  };

  echo "=== DIAGNOSTIC DECONNEXION PFSENSE ===\n\n";

  // Récupérer les paramètres (override via requête, sinon via .env)
  $host = trim($_REQUEST['host'] ?? '');
  $port = (int)($_REQUEST['port'] ?? 0);
  $user = trim($_REQUEST['user'] ?? '');
  $pass = (string)($_REQUEST['pass'] ?? '');
  $useHttps = isset($_REQUEST['https']) ? filter_var($_REQUEST['https'], FILTER_VALIDATE_BOOLEAN) : null;
  $verifySsl = isset($_REQUEST['verify']) ? filter_var($_REQUEST['verify'], FILTER_VALIDATE_BOOLEAN) : false;
  $mac = trim($_REQUEST['mac'] ?? '');

  if ($host === '' || $pass === '') {
    if (function_exists('get_pfsense_config')) {
      $pf = get_pfsense_config();
      $host = $host ?: $pf['host'];
      $port = $port ?: $pf['port'];
      $user = $user ?: $pf['user'];
      $pass = $pass ?: $pf['pass'];
      if ($useHttps === null) $useHttps = $pf['use_https'];
      $verifySsl = $pf['verify_ssl'];
      $out("Source config", ".env (get_pfsense_config)");
    } else {
      echo "ERREUR : ni paramètres fournis dans l'URL ni fonction get_pfsense_config() disponible.\n";
      echo "Utilisation : radius_devices.php?action=diag_pfsense&host=IP&port=443&user=admin&pass=MDP&https=1&mac=xx:xx:xx:xx:xx:xx\n";
      exit;
    }
  }
  if ($useHttps === null) $useHttps = true;
  if ($port <= 0) $port = $useHttps ? 443 : 80;

  $out("curl extension", function_exists('curl_init') ? 'OK' : 'MANQUANTE');
  $out("simplexml", function_exists('simplexml_load_string') ? 'OK' : 'MANQUANTE');
  $out("pfSense host", $host);
  $out("pfSense port", $port);
  $out("pfSense user", $user);
  $out("pfSense pass", str_repeat('*', max(0, strlen($pass))) . ' (' . strlen($pass) . ' chars)');
  $out("use HTTPS", $useHttps ? 'true' : 'false');
  $out("verify SSL", $verifySsl ? 'true' : 'false');

  if (!function_exists('curl_init')) {
    echo "ERREUR FATALE : extension PHP curl manquante.\n";
    exit;
  }

  // Test XML-RPC simple : lister les zones CP et les sessions
  $testCode = '
    require_once "/etc/inc/captiveportal.inc";
    $zones = [];
    if (function_exists("captiveportal_zones")) {
      foreach (captiveportal_zones() as $z) { $zones[] = $z["zone"]; }
    }
    $sessions = captiveportal_read_db();
    $macs = [];
    if (is_array($sessions)) {
      foreach ($sessions as $s) { $macs[] = strtolower($s["mac"] ?? "") . " -> " . ($s["ip"] ?? "no-ip") . " (" . ($s["cpzone"] ?? "") . ")"; }
    }
    return json_encode([
      "version" => trim(@file_get_contents("/etc/version")),
      "zones" => $zones,
      "nb_sessions" => is_array($sessions) ? count($sessions) : -1,
      "sessions" => array_slice($macs, 0, 20)
    ]);
  ';

  function _diagXmlrpc($https, $port, $host, $user, $pass, $verify, $code)
  {
    $proto = $https ? 'https' : 'http';
    $url = "$proto://$host:$port/xmlrpc.php";
    foreach (['pfsense.exec_php', 'exec_php'] as $method) {
      $xml = '<?xml version="1.0"?><methodCall><methodName>' . $method . '</methodName><params><param><value><string>' . htmlspecialchars($code, ENT_XML1, 'UTF-8') . '</string></value></param></params></methodCall>';
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
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
      if ($err) return ['ok' => false, 'err' => "$proto:$port -> $err"];
      if ($http !== 200) {
        $msg = "$proto:$port -> HTTP $http";
        if ($http === 401) $msg .= ' (mauvais identifiants)';
        if ($http === 403) $msg .= ' (accès refusé / droits insuffisants)';
        if ($http === 0) $msg .= ' (pas de réponse - port fermé ou IP injoignable)';
        if ($http === 404) $msg .= ' (xmlrpc.php non trouvé - pfSense écoute-t-il bien sur ce port ?)';
        return ['ok' => false, 'err' => $msg];
      }
      libxml_use_internal_errors(true);
      $x = simplexml_load_string($resp);
      if (!$x) return ['ok' => false, 'err' => "$proto:$port -> réponse XML invalide"];
      return ['ok' => true, 'val' => (string)($x->params->param->value->string ?? '')];
    }
    return ['ok' => false, 'err' => "méthode XML-RPC non disponible"];
  }

  echo "\n[*] Test de connexion...\n";
  $res = _diagXmlrpc($useHttps, $port, $host, $user, $pass, $verifySsl, $testCode);
  if (!$res['ok']) {
    $out("  tentative 1 échouée", $res['err']);
    $fbHttps = !$useHttps;
    $fbPort = ($port === 443) ? 80 : (($port === 80) ? 443 : $port);
    $out("  tentative fallback", "$fbHttps:$fbPort");
    $res = _diagXmlrpc($fbHttps, $fbPort, $host, $user, $pass, $verifySsl, $testCode);
  }

  if ($res['ok']) {
    $data = json_decode($res['val'], true);
    echo "\n>>> CONNEXION A PFSENSE REUSSIE !\n";
    if (is_array($data)) {
      $out("Version pfSense", $data['version'] ?? 'inconnue');
      $out("Zones portail captif", $data['zones'] ? implode(', ', $data['zones']) : 'AUCUNE');
      $out("Nb sessions actives", $data['nb_sessions']);
      if (!empty($data['sessions'])) {
        echo "\n--- Liste des sessions CP actives (max 20) ---\n";
        foreach ($data['sessions'] as $s) echo "  - " . $s . "\n";
      }
      if ((int)$data['nb_sessions'] === 0) {
        echo "\n!!! IMPORTANT !!!\n";
        echo "Il n'y a AUCUNE session active dans le portail captif pfSense.\n";
        echo "Donc vos clients NE S'AUTHENTIFIENT PAS via le portail captif pfSense,\n";
        echo "mais tres probablement via 802.1X/MAB directement sur vos switchs/AP.\n";
        echo "Dans ce cas pfSense ne peut pas deconnecter ces clients : il faut envoyer\n";
        echo "un paquet RADIUS Disconnect-Request (CoA) directement aux switchs/AP.\n";
      }
    } else {
      $out("Réponse brute", $res['val']);
    }
  } else {
    echo "\n>>> ECHEC DE CONNEXION : " . $res['err'] . "\n\n";
    echo "Verifications a faire :\n";
    echo " 1. L'IP $host est-elle bien celle de pfSense (pas celle de l'appli) ?\n";
    echo " 2. Le port $port est-il bien le port du webgui pfSense ?\n";
    echo " 3. Les identifiants $user sont-ils corrects ?\n";
    echo " 4. Si pfSense est en HTTP seulement, ajoutez &https=0 dans l'URL\n";
    echo " 5. Ajoutez une règle de pare-feu sur pfSense qui autorise le serveur web\n";
    echo "    (" . ($_SERVER['SERVER_ADDR'] ?? 'IP du serveur') . ") a acceder au port $port de pfSense.\n";
    echo " 6. Dans System > Advanced > Admin Access, vérifiez que l'acces au webgui\n";
    echo "    n'est pas limité a certaines IPs (champ 'WebGUI redirect' / access lists).\n";
  }

  // Si MAC fournie, chercher dans radacct et essayer de déconnecter
  if ($mac !== '') {
    $macClean = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $mac));
    if (strlen($macClean) === 12) {
      $macFmt = implode(':', str_split($macClean, 2));
      echo "\n--- MAC cible: $macFmt ---\n";
      try {
        $stmt = $connexion->prepare("SELECT username, callingstationid, framedipaddress::text AS ip, nasipaddress::text AS nas_ip, acctstarttime, acctstoptime FROM radacct WHERE regexp_replace(lower(callingstationid), '[^0-9a-f]', '', 'g') = ? ORDER BY acctstarttime DESC LIMIT 10");
        $stmt->execute([$macClean]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
          echo "Sessions trouvees dans radacct:\n";
          foreach ($rows as $r) {
            echo "  - user={$r['username']} ip={$r['ip']} nas={$r['nas_ip']} start={$r['acctstarttime']} stop=" . ($r['acctstoptime'] ?: 'EN COURS') . "\n";
          }
        } else {
          echo "Aucune session dans radacct pour cette MAC.\n";
        }
      } catch (Throwable $e) {
        echo "Erreur lecture radacct: " . $e->getMessage() . "\n";
      }

      if (function_exists('pfsense_disconnect_mac')) {
        echo "\nTentative de deconnexion reelle...\n";
        $r = pfsense_disconnect_mac($macFmt, $connexion);
        echo "Resultat: " . ($r['success'] ? 'OK' : 'ECHEC') . " - " . $r['message'] . "\n";
      }
    } else {
      echo "\nMAC fournie invalide.\n";
    }
  } else {
    echo "\nAstuce : ajoutez &mac=aa:bb:cc:dd:ee:ff a l'URL pour tester la deconnexion d'une MAC precise.\n";
  }
  exit;
}


