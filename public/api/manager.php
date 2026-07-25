<?php
session_start();
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// API des opérations du gestionnaire de comptes.
if (empty($_SESSION['user'])) {
  json_response(false, 'Session expirée', null, 401);
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$pdo = $connexion;

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
  json_response(false, 'Aucune action spécifiée');
}

$action = $_POST['action'];

// La consultation est disponible aux utilisateurs connectés, mais seules les sessions
// administrateur peuvent créer un compte ou modifier son statut.
if (in_array($action, ['create_user', 'update_status'], true) && ($_SESSION['role_lib'] ?? '') !== 'Administrateur') {
  json_response(false, 'Accès réservé aux administrateurs');
}

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

      json_response(true, '', [
        'total_users' => (int) $row['total'],
        'active_users' => (int) $row['active'],
        'total_roles' => (int) $totalRoles,
      ]);
    } catch (Exception $e) {
      json_response(false, 'Erreur lors du chargement des statistiques');
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
      json_response(true, '', $departements);
    } catch (Exception $e) {
      json_response(false, 'Erreur lors du chargement des départements');
    }
    break;

  case 'get_roles':
    try {
      // Les rôles sont désormais une ENUM (ADMIN / USER)
      $values = getEnumValues($pdo, 'role_enum');
      $roles = array_map(function ($label) {
        return ['id' => $label, 'nom' => $label];
      }, $values);
      json_response(true, '', $roles);
    } catch (Exception $e) {
      json_response(false, 'Erreur lors du chargement des rôles');
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
      json_response(true, '', $users);
    } catch (Exception $e) {
      json_response(false, 'Erreur lors du chargement des utilisateurs');
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
      json_response(false, 'Tous les champs sont obligatoires');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      json_response(false, "Format d'email invalide");
    }

    if (strlen($password) < 6) {
      json_response(false, 'Le mot de passe doit contenir au moins 6 caractères');
    }

    try {
      // Vérifier l'unicité du nom d'utilisateur / email
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
      $stmt->execute([$username, $email]);
      if ($stmt->fetchColumn() > 0) {
        json_response(false, "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà");
      }

      // Valider le département et le rôle contre les valeurs de l'ENUM
      if (!in_array($department, getEnumValues($pdo, 'department_enum'), true)) {
        json_response(false, 'Département invalide');
      }
      if (!in_array($role, getEnumValues($pdo, 'role_enum'), true)) {
        json_response(false, 'Rôle invalide');
      }

      // Création (date_modification est NOT NULL sans valeur par défaut => now())
      $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users
              (username, email, password_hash, department, role, status, date_modification)
              VALUES (?, ?, ?, ?, ?, 'active', now())");
      $stmt->execute([$username, $email, $hashedPassword, $department, $role]);

      json_response(true, 'Utilisateur créé avec succès !');
    } catch (PDOException $e) {
      if ($e->getCode() == '23505') {
        // Violation de contrainte unique (username ou email)
        json_response(false, "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà");
      }
      json_response(false, "Erreur lors de la création de l'utilisateur");
    } catch (Exception $e) {
      json_response(false, "Erreur lors de la création de l'utilisateur");
    }
    break;

  case 'update_status':
    if (empty($_POST['user_id']) || empty($_POST['new_status'])) {
      json_response(false, 'Paramètres manquants');
    }

    // Statuts valides de l'ENUM users_status_enum
    $validStatuses = ['active', 'inactive', 'suspended'];
    if (!in_array($_POST['new_status'], $validStatuses, true)) {
      json_response(false, 'Statut invalide');
    }

    try {
      // Vérifier l'existence de l'utilisateur
      $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
      $stmt->execute([$_POST['user_id']]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        json_response(false, 'Utilisateur non trouvé');
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
      json_response(true, $message);
    } catch (Exception $e) {
      json_response(false, 'Erreur lors de la mise à jour du statut');
    }
    break;

  default:
    json_response(false, 'Action non reconnue');
    break;
}
