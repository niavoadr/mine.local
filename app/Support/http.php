<?php

declare(strict_types=1);

if (!function_exists('json_response')) {
  /**
   * Retourne une réponse JSON standardisée puis arrête l'exécution.
   */
  function json_response(bool $success, string $message = '', $data = null, int $statusCode = 200): void
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'success' => $success,
      'message' => $message,
      'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit();
  }
}

if (!function_exists('e')) {
  /**
   * Échappement HTML court pour les vues.
   */
  function e($value): string
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}
