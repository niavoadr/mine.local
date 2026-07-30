<?php
/**
 * Authentification des visiteurs pour le portail captif pfSense.
 *
 * Flux attendu :
 *  1. pfSense envoie les identifiants du visiteur et la MAC du client à ce fichier.
 *  2. Le fichier vérifie username/password dans la table visitor uniquement.
 *  3. Si le compte est actif et non expiré, la MAC du client est ajoutée dans
 *     radcheck avec le RADIUS_MAC_SECRET comme valeur.
 *  4. Si le compte est expiré ou invalide, aucune autorisation MAC n'est ajoutée.
 *
 * Paramètres acceptés (GET, POST, JSON, variables d'environnement RADIUS ou CLI),
 * pour faciliter l'intégration pfSense/FreeRADIUS :
 *  - utilisateur : username, user, login, auth_user, User-Name, USER_NAME
 *  - mot de passe : password, pass, auth_pass, User-Password, USER_PASSWORD
 *  - MAC client : mac, mac_address, clientmac, client_mac, x_client_mac,
 *                 x_mac_address, callingstationid, calling_station_id
 *  - NAS/IP pfSense optionnel : nas_ip, nasip, nas_ip_address, nasipaddress,
 *                              NAS-IP-Address, x_forwarded_for
 *
 * Action de nettoyage optionnelle pour cron :
 *  - HTTP : check_visitor.php?action=cleanup_expired
 *  - CLI  : php check_visitor.php cleanup_expired
 *  - CLI  : php check_visitor.php <username> <password> <mac> [nas_ip]
 *  - CLI  : php check_visitor.php username=<username> password=<password> mac=<mac>
 */

ob_start();
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/visitor_radius_helpers.php';
ob_clean();

function captive_get_allowed_origins()
{
  // Origine du portail captif pfSense indiquée pour cette installation.
  $origins = ['http://192.168.0.1:8002'];

  // Optionnel : ajouter/modifier les origines autorisées sans changer le code.
  // Exemple .env : CHECK_VISITOR_ALLOWED_ORIGINS=http://192.168.0.1:8002,http://autre-portail
  $configuredOrigins = trim((string) env('CHECK_VISITOR_ALLOWED_ORIGINS', ''));
  if ($configuredOrigins !== '') {
    $origins = array_merge($origins, preg_split('/[\s,]+/', $configuredOrigins));
  }

  return array_values(array_unique(array_filter(array_map('trim', $origins))));
}

function captive_send_cors_headers()
{
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  $allowedOrigins = captive_get_allowed_origins();

  if ($origin !== '' && (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . (in_array('*', $allowedOrigins, true) ? '*' : $origin));
  }

  header('Vary: Origin');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, X-Client-Mac, X-Mac-Address, X-Forwarded-For');
  header('Access-Control-Max-Age: 600');
}

if (PHP_SAPI !== 'cli') {
  captive_send_cors_headers();
  header('Content-Type: application/json; charset=utf-8');

  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    echo json_encode(['success' => true]);
    exit();
  }
}

function captive_json_response($success, $message = '', $data = null, $httpCode = 200)
{
  if (PHP_SAPI !== 'cli') {
    http_response_code($httpCode);
  }

  echo json_encode([
    'success' => $success,
    'message' => $message,
    'data' => $data,
  ]) . PHP_EOL;

  if (PHP_SAPI === 'cli') {
    exit($success ? 0 : 1);
  }

  exit();
}

function captive_normalize_param_name($name)
{
  return strtolower(str_replace(['-', ' '], '_', (string) $name));
}

function captive_set_param_if_present(array &$params, $canonicalName, array $envNames)
{
  foreach ($envNames as $envName) {
    $value = getenv($envName);

    if ($value === false && isset($_ENV[$envName])) {
      $value = $_ENV[$envName];
    }

    if ($value === false && isset($_SERVER[$envName])) {
      $value = $_SERVER[$envName];
    }

    if ($value === false) {
      $normalizedEnvName = captive_normalize_param_name($envName);
      foreach ([$_ENV, $_SERVER] as $source) {
        foreach ($source as $key => $sourceValue) {
          if (captive_normalize_param_name($key) === $normalizedEnvName) {
            $value = $sourceValue;
            break 2;
          }
        }
      }
    }

    if ($value !== false && $value !== null && trim((string) $value) !== '') {
      $params[$canonicalName] = trim((string) $value);
      $params[captive_normalize_param_name($envName)] = trim((string) $value);
      return;
    }
  }
}

function captive_merge_params(array &$params, array $source)
{
  foreach ($source as $key => $value) {
    if (is_array($value)) {
      $value = reset($value);
    }

    if ($value === null) {
      continue;
    }

    $params[captive_normalize_param_name($key)] = trim((string) $value);
  }
}

function captive_collect_request_params()
{
  $params = [];

  // Variables d'environnement possibles quand le fichier est lancé par
  // FreeRADIUS/rlm_exec. On n'importe pas tout l'environnement pour éviter de
  // confondre USER (utilisateur système Linux) avec le visiteur.
  captive_set_param_if_present($params, 'username', [
    'User-Name',
    'USER_NAME',
    'RAD_USER_NAME',
    'RADIUS_USER_NAME',
    'AUTH_USER',
    'USERNAME',
  ]);
  captive_set_param_if_present($params, 'password', [
    'User-Password',
    'USER_PASSWORD',
    'RAD_USER_PASSWORD',
    'RADIUS_USER_PASSWORD',
    'AUTH_PASS',
    'PASSWORD',
  ]);
  captive_set_param_if_present($params, 'mac', [
    'Calling-Station-Id',
    'CALLING_STATION_ID',
    'RAD_CALLING_STATION_ID',
    'RADIUS_CALLING_STATION_ID',
    'CLIENT_MAC',
    'CLIENTMAC',
    'MAC_ADDRESS',
    'MAC',
  ]);
  captive_set_param_if_present($params, 'nas_ip', [
    'NAS-IP-Address',
    'NAS_IP_ADDRESS',
    'RAD_NAS_IP_ADDRESS',
    'RADIUS_NAS_IP_ADDRESS',
    'NASIPADDRESS',
    'NAS_IP',
    'NASIP',
    'REMOTE_ADDR',
  ]);

  // Quelques en-têtes possibles si la MAC/IP est transmise par proxy ou pfSense.
  foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') !== 0) {
      continue;
    }

    $headerName = substr($key, 5);
    $params[captive_normalize_param_name($headerName)] = trim((string) $value);
  }

  foreach ([$_GET, $_POST] as $source) {
    captive_merge_params($params, $source);
  }

  // Support des appels HTTP avec corps JSON ou corps urlencoded non parsé.
  if (PHP_SAPI !== 'cli') {
    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody) && trim($rawBody) !== '') {
      $decoded = json_decode($rawBody, true);
      if (is_array($decoded)) {
        captive_merge_params($params, $decoded);
      } elseif (empty($_POST)) {
        $parsed = [];
        parse_str($rawBody, $parsed);
        if (!empty($parsed)) {
          captive_merge_params($params, $parsed);
        }
      }
    }
  }

  // Support CLI pour cron ou FreeRADIUS :
  //   php check_visitor.php cleanup_expired
  //   php check_visitor.php action=cleanup_expired
  //   php check_visitor.php visiteur motdepasse aa:bb:cc:dd:ee:ff [nas_ip]
  //   php check_visitor.php username=visiteur password=motdepasse mac=aa:bb:cc:dd:ee:ff
  if (PHP_SAPI === 'cli') {
    global $argv;
    $args = array_slice($argv ?? [], 1);
    $positional = [];

    foreach ($args as $arg) {
      $arg = trim((string) $arg);
      if ($arg === '') {
        continue;
      }

      if (strpos($arg, '=') !== false) {
        [$key, $value] = explode('=', $arg, 2);
        $params[captive_normalize_param_name($key)] = trim((string) $value);
      } else {
        $positional[] = $arg;
      }
    }

    if (count($positional) === 1 && !isset($params['action'])) {
      $params['action'] = $positional[0];
    } elseif (count($positional) >= 3) {
      $params['username'] = $positional[0];
      $params['password'] = $positional[1];
      $params['mac'] = $positional[2];

      if (isset($positional[3])) {
        $params['nas_ip'] = $positional[3];
      }
    }
  }

  return $params;
}

function captive_get_param(array $params, array $names, $default = '')
{
  foreach ($names as $name) {
    $normalized = captive_normalize_param_name($name);
    if (array_key_exists($normalized, $params) && $params[$normalized] !== '') {
      return $params[$normalized];
    }
  }

  return $default;
}

function captive_normalize_ip($ipRaw)
{
  $ip = trim((string) $ipRaw);

  // X-Forwarded-For peut contenir plusieurs IP séparées par des virgules.
  if (strpos($ip, ',') !== false) {
    $parts = explode(',', $ip);
    $ip = trim($parts[0]);
  }

  if (filter_var($ip, FILTER_VALIDATE_IP)) {
    return $ip;
  }

  return '0.0.0.0';
}

function captive_bool_from_pg($value)
{
  return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
}

try {
  if (!isset($connexion) || !$connexion) {
    captive_json_response(false, 'Connexion à la base de données indisponible.', null, 500);
  }

  $params = captive_collect_request_params();
  $action = strtolower((string) captive_get_param($params, ['action'], 'auth'));

  // Nettoie les visiteurs expirés à chaque appel afin de retirer les MAC de
  // radcheck dès qu'une nouvelle requête portail/cron passe par ce fichier.
  $cleanupCount = visitor_cleanup_expired_visitors($connexion);

  if ($action === 'cleanup' || $action === 'cleanup_expired') {
    captive_json_response(true, 'Nettoyage des validations visiteurs expirées effectué.', [
      'cleaned_visitors' => $cleanupCount,
    ]);
  }

  $username = captive_get_param($params, ['username', 'user', 'user_name', 'login', 'auth_user', 'User-Name']);
  $password = captive_get_param($params, ['password', 'pass', 'user_password', 'auth_pass', 'User-Password']);
  $macRaw = captive_get_param($params, [
    'mac',
    'mac_address',
    'clientmac',
    'client_mac',
    'x_client_mac',
    'x_mac_address',
    'callingstationid',
    'calling_station_id',
    'Calling-Station-Id',
  ]);
  $nasIpRaw = captive_get_param($params, [
    'nas_ip',
    'nasip',
    'nas_ip_address',
    'nasipaddress',
    'NAS-IP-Address',
    'x_forwarded_for',
  ], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

  if ($username === '' || $password === '' || $macRaw === '') {
    captive_json_response(false, 'Identifiant, mot de passe et adresse MAC client requis.', null, 400);
  }

  try {
    $mac = visitor_normalize_mac_address($macRaw);
  } catch (Throwable $e) {
    captive_json_response(false, $e->getMessage(), null, 400);
  }

  $stmt = $connexion->prepare("SELECT id,
                                      username,
                                      password_hash,
                                      department::text AS department,
                                      status::text AS status,
                                      expires_at,
                                      mac_address::text AS mac_address,
                                      nas_ip::text AS nas_ip,
                                      (expires_at <= NOW()) AS is_expired
                                 FROM visitor
                                WHERE username = ?
                                LIMIT 1");
  $stmt->execute([$username]);
  $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

  // Message volontairement générique pour ne pas aider les tentatives de brute force.
  if (!$visitor || !password_verify($password, $visitor['password_hash'])) {
    captive_json_response(false, 'Identifiants visiteur invalides ou expirés.', null, 401);
  }

  if ($visitor['status'] !== 'active' || captive_bool_from_pg($visitor['is_expired'])) {
    // Si le compte vient d'expirer entre le nettoyage global et cette requête,
    // on retire son ancienne MAC sans supprimer les informations visitor.
    visitor_expire_visitor_by_id($connexion, (int) $visitor['id']);
    captive_json_response(false, 'Identifiants visiteur invalides ou expirés.', [
      'expired' => true,
    ], 403);
  }

  $radiusMacSecret = get_radius_mac_secret();
  $nasIp = captive_normalize_ip($nasIpRaw);
  $oldMac = $visitor['mac_address'] ?? '';
  $oldMacChanged = !visitor_is_dummy_mac_address($oldMac)
    && visitor_compact_mac_address($oldMac) !== visitor_compact_mac_address($mac);

  $connexion->beginTransaction();

  try {
    if ($oldMacChanged) {
      visitor_delete_radcheck_mac_if_unused($connexion, $oldMac, (int) $visitor['id']);
    }

    // Sécurité de migration : supprime l'ancienne méthode username/password
    // radcheck si elle existe encore pour ce visiteur.
    visitor_delete_legacy_radcheck_credentials($connexion, $visitor['username']);

    $radcheckResult = visitor_upsert_radcheck_mac(
      $connexion,
      $mac,
      $radiusMacSecret,
      $visitor['department']
    );

    $updateStmt = $connexion->prepare("UPDATE visitor
                                          SET mac_address = ?,
                                              nas_ip = ?,
                                              status = 'active'
                                        WHERE id = ?");
    $updateStmt->execute([$mac, $nasIp, (int) $visitor['id']]);

    $connexion->commit();
  } catch (Throwable $e) {
    if ($connexion->inTransaction()) {
      $connexion->rollBack();
    }
    throw $e;
  }

  $message = $radcheckResult['skipped_static_mac']
    ? 'Identifiants valides. MAC déjà gérée par la méthode MAC, aucune modification de cette méthode.'
    : 'Identifiants valides. Adresse MAC autorisée dans radcheck.';

  captive_json_response(true, $message, [
    'username' => $visitor['username'],
    'mac_address' => $mac,
    'nas_ip' => $nasIp,
    'expires_at' => $visitor['expires_at'],
    'radcheck_inserted' => $radcheckResult['inserted'],
    'static_mac_method_preserved' => $radcheckResult['skipped_static_mac'],
  ]);
} catch (Throwable $e) {
  if (isset($connexion) && $connexion instanceof PDO && $connexion->inTransaction()) {
    $connexion->rollBack();
  }

  captive_json_response(false, 'Erreur portail captif : ' . $e->getMessage(), null, 500);
}
