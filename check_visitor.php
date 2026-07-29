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
 * Paramètres acceptés (GET ou POST), pour faciliter l'intégration pfSense :
 *  - utilisateur : username, user, login, auth_user
 *  - mot de passe : password, pass, auth_pass
 *  - MAC client : mac, mac_address, clientmac, client_mac, x_client_mac,
 *                 x_mac_address, callingstationid, calling_station_id
 *  - NAS/IP pfSense optionnel : nas_ip, nasip, nas_ip_address, nasipaddress,
 *                              NAS-IP-Address, x_forwarded_for
 *
 * Action de nettoyage optionnelle pour cron :
 *  - HTTP : check_visitor.php?action=cleanup_expired
 *  - CLI  : php check_visitor.php cleanup_expired
 */

ob_start();
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/visitor_radius_helpers.php';
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Client-Mac, X-Mac-Address, X-Forwarded-For');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  echo json_encode(['success' => true]);
  exit();
}

function captive_json_response($success, $message = '', $data = null, $httpCode = 200)
{
  http_response_code($httpCode);
  echo json_encode([
    'success' => $success,
    'message' => $message,
    'data' => $data,
  ]);
  exit();
}

function captive_normalize_param_name($name)
{
  return strtolower(str_replace(['-', ' '], '_', (string) $name));
}

function captive_collect_request_params()
{
  $params = [];

  foreach ([$_GET, $_POST] as $source) {
    foreach ($source as $key => $value) {
      if (is_array($value)) {
        $value = reset($value);
      }
      $params[captive_normalize_param_name($key)] = trim((string) $value);
    }
  }

  // Support CLI pour un cron local :
  //   php check_visitor.php cleanup_expired
  //   php check_visitor.php action=cleanup_expired
  if (PHP_SAPI === 'cli') {
    global $argv;
    foreach (array_slice($argv ?? [], 1) as $arg) {
      if (strpos($arg, '=') !== false) {
        [$key, $value] = explode('=', $arg, 2);
        $params[captive_normalize_param_name($key)] = trim((string) $value);
      } elseif ($arg !== '') {
        $params['action'] = trim((string) $arg);
      }
    }
  }

  // Quelques en-têtes possibles si la MAC/IP est transmise par proxy ou pfSense.
  foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') !== 0) {
      continue;
    }

    $headerName = substr($key, 5);
    $params[captive_normalize_param_name($headerName)] = trim((string) $value);
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

  $username = captive_get_param($params, ['username', 'user', 'login', 'auth_user']);
  $password = captive_get_param($params, ['password', 'pass', 'auth_pass']);
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
