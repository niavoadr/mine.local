<?php
session_start();
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/manager_session.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Session expirée']);
  exit();
}

try {
  register_app_session($connexion);
  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Impossible de mettre à jour la session']);
}
