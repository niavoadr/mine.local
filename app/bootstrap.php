<?php
/**
 * Bootstrap applicatif minimal.
 *
 * Ce fichier centralise les dépendances communes pour éviter les `require`
 * dispersés dans les pages publiques et les endpoints API.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__));
}

if (!defined('APP_PATH')) {
  define('APP_PATH', __DIR__);
}

if (!defined('PUBLIC_PATH')) {
  define('PUBLIC_PATH', BASE_PATH . '/public');
}

require_once APP_PATH . '/Config/env.php';
require_once APP_PATH . '/Config/database.php';
require_once APP_PATH . '/Support/http.php';
require_once APP_PATH . '/Support/auth.php';
require_once APP_PATH . '/Support/security.php';
require_once APP_PATH . '/Services/AppSessionService.php';
