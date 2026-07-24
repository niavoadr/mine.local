<?php
require_once __DIR__ . '/connexion.php';

// manager.php — Gestion des comptes utilisateurs (table `users`, schéma PostgreSQL)

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$pdo = $connexion;

function jsonResponse($success, $message = '', $data = null)
{
  echo json_encode([
    'success' => $success,
    'message' => $message,
    'data' => $data,
  ]);
  exit();
}

/**
 * Récupère les valeurs d'un type ENUM PostgreSQL.
 * Permet de s'adapter automatiquement au schéma (department_enum, role_enum, ...).
 */
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

switch ($action) {
  case 'get_stats':
    try {
      // Total des utilisateurs et utilisateurs actifs (status = 'active')
      $stmt = $pdo->query("SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'active') AS active
              FROM users");
      $row = $stmt->fetch();

      // Total des rôles = nombre de valeurs de l'enum role_enum
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
      // Les départements sont désormais une ENUM (plus de table dédiée)
      $values = getEnumValues($pdo, 'department_enum');
      $departements = array_map(function ($label) {
        // La valeur de l'option = le libellé de l'ENUM (utilisé tel quel dans la table users)
        return ['id' => $label, 'nom' => $label];
      }, $values);
      jsonResponse(true, '', $departements);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des départements');
    }
    break;

  case 'get_roles':
    try {
      // Les rôles sont désormais une ENUM (ADMIN / USER)
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
      // Plus de JOIN : department et role sont des colonnes ENUM de `users`
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
    // Champs alignés sur le schéma `users`
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
      // Vérifier l'unicité du nom d'utilisateur / email
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
      $stmt->execute([$username, $email]);
      if ($stmt->fetchColumn() > 0) {
        jsonResponse(false, "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà");
      }

      // Valider le département et le rôle contre les valeurs de l'ENUM
      if (!in_array($department, getEnumValues($pdo, 'department_enum'), true)) {
        jsonResponse(false, 'Département invalide');
      }
      if (!in_array($role, getEnumValues($pdo, 'role_enum'), true)) {
        jsonResponse(false, 'Rôle invalide');
      }

      // Création (date_modification est NOT NULL sans valeur par défaut => now())
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users
              (username, email, password_hash, department, role, status, date_modification)
              VALUES (?, ?, ?, ?, ?, 'active', now())");
      $stmt->execute([$username, $email, $hashedPassword, $department, $role]);

      jsonResponse(true, 'Utilisateur créé avec succès !');
    } catch (PDOException $e) {
      if ($e->getCode() == '23505') {
        // Violation de contrainte unique (username ou email)
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

    // Statuts valides de l'ENUM users_status_enum
    $validStatuses = ['active', 'inactive', 'suspended'];
    if (!in_array($_POST['new_status'], $validStatuses, true)) {
      jsonResponse(false, 'Statut invalide');
    }

    try {
      // Vérifier l'existence de l'utilisateur
      $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
      $stmt->execute([$_POST['user_id']]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        jsonResponse(false, 'Utilisateur non trouvé');
      }

      $username = $user['username'];

      // Mettre à jour le statut + date_modification
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
