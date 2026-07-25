<?php

declare(strict_types=1);

if (!function_exists('is_valid_mac_address')) {
  function is_valid_mac_address(string $mac): bool
  {
    // Accepte AA:BB:CC:DD:EE:FF, AA-BB-CC-DD-EE-FF et AABB.CCDD.EEFF.
    return (bool) preg_match('/^([0-9A-Fa-f]{2}[:\-\.]){5}[0-9A-Fa-f]{2}$/', $mac);
  }
}

if (!function_exists('generate_random_password')) {
  function generate_random_password(int $length = 10): string
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxIndex = strlen($characters) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
      $password .= $characters[random_int(0, $maxIndex)];
    }

    return $password;
  }
}
