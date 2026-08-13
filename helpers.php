<?php
// Helpers partagés — portail RADIUS (M13 : mutualisation des fonctions dupliquées)

if (!function_exists('manager_escape')) {
    function manager_escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($success, $message = '', $data = null)
    {
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'error'   => $success ? '' : $message, // compatibilité avec les vues qui lisent 'error'
            'data'    => $data,
        ]);
        exit();
    }
}

if (!function_exists('normalizeMacAddress')) {
    function normalizeMacAddress($macRaw)
    {
        $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));
        if (strlen($cleanMac) !== 12) {
            return false; // comportement unique : retourne false (B4)
        }
        return implode(':', str_split($cleanMac, 2));
    }
}

if (!function_exists('compactMacAddress')) {
    function compactMacAddress($mac)
    {
        return str_replace(':', '', normalizeMacAddress($mac));
    }
}

if (!function_exists('normalizedMacSqlWhere')) {
    function normalizedMacSqlWhere()
    {
        return "regexp_replace(lower(username), '[^0-9a-f]', '', 'g') = ?";
    }
}

if (!function_exists('getDepartmentMap')) {
    function getDepartmentMap()
    {
        return [
            'communication' => ['enum' => 'Communication', 'group' => 'communication_group'],
            'daj'           => ['enum' => 'Directeur des Affaires Juridiques', 'group' => 'daj_group'],
            'finance'       => ['enum' => 'Finance', 'group' => 'finance_group'],
            'rh'            => ['enum' => 'Ressources Humaines', 'group' => 'rh_group'],
            'sg'            => ['enum' => 'Secrétariat Général', 'group' => 'sg_group'],
        ];
    }
}

if (!function_exists('enumToShortcode')) {
    function enumToShortcode($enumValue)
    {
        foreach (getDepartmentMap() as $shortcode => $info) {
            if ($info['enum'] === $enumValue) {
                return $shortcode;
            }
        }
        return strtolower((string) $enumValue);
    }
}

if (!function_exists('check_csrf')) {
    function check_csrf()
    {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide. Rechargez la page.']);
            exit();
        }
    }
}
