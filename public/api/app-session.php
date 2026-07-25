<?php
session_start();
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if (empty($_SESSION['user_id'])) {
  json_response(false, 'Session expirée', null, 401);
}

try {
  register_app_session($connexion);
  json_response(true);
} catch (Throwable $e) {
  json_response(false, 'Impossible de mettre à jour la session', null, 500);
}
