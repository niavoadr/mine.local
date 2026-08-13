<?php
session_start();
require_once __DIR__ . '/connexion.php';

if (empty($_SESSION['user'])) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Session expirée']);
  exit();
}

check_csrf();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$pdo = $connexion;

function getEnumValues(PDO $pdo, $typeName)
{
  $sql = "SELECT e.enumlabel AS label
          FROM pg_enum e
          JOIN pg_type t ON t.oid = e.enumtypid
          WHERE t.typname = ?
          ORDER BY e.enumsortorder";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$typeName]);
  return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (!isset($_POST['action'])) {
  jsonResponse(false, 'Aucune action spécifiée');
}

$action = $_POST['action'];

if (in_array($action, ['create_user', 'update_status'], true) && ($_SESSION['role_lib'] ?? '') !== 'Administrateur') {
  jsonResponse(false, 'Accès réservé aux administrateurs');
}

switch ($action) {
  case 'get_stats':
    try {
      $stmt = $pdo->query("SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'active') AS active
              FROM users");
      $row = $stmt->fetch();

      $totalRoles = count(getEnumValues($pdo, 'role_enum'));

      jsonResponse(true, '', [
        'total_users' => (int) $row['total'],
        'active_users' => (int) $row['active'],
        'total_roles' => (int) $totalRoles,
      ]);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des statistiques');
    }
    break;

  case 'get_departements':
    try {
      $values = getEnumValues($pdo, 'department_enum');
      $departements = array_map(function ($label) {
        return ['id' => $label, 'nom' => $label];
      }, $values);
      jsonResponse(true, '', $departements);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des départements');
    }
    break;

  case 'get_roles':
    try {
      $values = getEnumValues($pdo, 'role_enum');
      $roles = array_map(function ($label) {
        return ['id' => $label, 'nom' => $label];
      }, $values);
      jsonResponse(true, '', $roles);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des rôles');
    }
    break;

  case 'get_users':
    try {
      $stmt = $pdo->query("SELECT id, username, email, department, role, status, date_creation
              FROM users
              ORDER BY date_creation DESC");
      $users = [];
      foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
        $user['id'] = (int) $user['id'];
        $users[] = $user;
      }
      jsonResponse(true, '', $users);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des utilisateurs');
    }
    break;

  case 'create_user':
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department = $_POST['department'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($username === '' || $email === '' || $password === '' || $department === '' || $role === '') {
      jsonResponse(false, 'Tous les champs sont obligatoires');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      jsonResponse(false, "Format d'email invalide");
    }

    if (strlen($password) < 6) {
      jsonResponse(false, 'Le mot de passe doit contenir au moins 6 caractères');
    }

    try {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
      $stmt->execute([$username, $email]);
      if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà");
      }

      if (!in_array($department, getEnumValues($pdo, 'department_enum'), true)) {
        jsonResponse(false, 'Département invalide');
      }
      if (!in_array($role, getEnumValues($pdo, 'role_enum'), true)) {
        jsonResponse(false, 'Rôle invalide');
      }

      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users
              (username, email, password_hash, department, role, status, date_modification)
              VALUES (?, ?, ?, ?, ?, 'active', now())");
      $stmt->execute([$username, $email, $hashedPassword, $department, $role]);

      jsonResponse(true, 'Utilisateur créé avec succès !');
    } catch (PDOException $e) {
      if ($e->getCode() == '23505') {
        jsonResponse(false, "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà");
      }
      jsonResponse(false, "Erreur lors de la création de l'utilisateur");
    } catch (Exception $e) {
      jsonResponse(false, "Erreur lors de la création de l'utilisateur");
    }
    break;

  case 'update_status':
    if (empty($_POST['user_id']) || empty($_POST['new_status'])) {
      jsonResponse(false, 'Paramètres manquants');
    }

    $validStatuses = ['active', 'inactive', 'suspended'];
    if (!in_array($_POST['new_status'], $validStatuses, true)) {
      jsonResponse(false, 'Statut invalide');
    }

    try {
      $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
      $stmt->execute([$_POST['user_id']]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        jsonResponse(false, 'Utilisateur non trouvé');
      }

      $username = $user['username'];

      $stmt = $pdo->prepare("UPDATE users SET status = ?, date_modification = now() WHERE id = ?");
      $stmt->execute([$_POST['new_status'], $_POST['user_id']]);

      $statusMessages = [
        'active' => 'activé',
        'suspended' => 'suspendu',
        'inactive' => 'désactivé',
      ];

      $message = sprintf("L'utilisateur %s a été %s avec succès !", $username, $statusMessages[$_POST['new_status']]);
      jsonResponse(true, $message);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors de la mise à jour du statut');
    }
    break;

  default:
    jsonResponse(false, 'Action non reconnue');
    break;
}
