<?php
require_once __DIR__ . '/connexion.php';

// manager.php
//
// Toutes les requêtes de ce fichier ciblent la nouvelle base de données
// (schéma décrit dans database/radius.sql) :
//   - table  users : id, username, email, password_hash,
//                    department (DEPARTMENT_ENUM), role (ROLE_ENUM),
//                    status (USERS_STATUS_ENUM : active / inactive / suspended),
//                    date_creation, date_modification, last_login
//
// Le contrat JSON renvoyé au front-end (managerAdmin.js / managerUser.js)
// reste identique : les valeurs de statut de la base (active/inactive/suspended)
// sont traduites vers les valeurs attendues par le JS (actif/en_attente/suspendu).

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Utiliser la connexion globale $connexion
$pdo = $connexion;

// Fonction pour retourner une réponse JSON
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
 * Traduction statut base de données  ->  statut front-end.
 * USERS_STATUS_ENUM : active / inactive / suspended
 * JS attendu        : actif  / en_attente / suspendu
 */
function dbStatusToFront($dbStatus)
{
  $map = [
    'active' => 'actif',
    'inactive' => 'en_attente',
    'suspended' => 'suspendu',
  ];
  return $map[$dbStatus] ?? $dbStatus;
}

/**
 * Traduction statut front-end  ->  statut base de données.
 */
function frontStatusToDb($frontStatus)
{
  $map = [
    'actif' => 'active',
    'en_attente' => 'inactive',
    'suspendu' => 'suspended',
  ];
  return $map[$frontStatus] ?? null;
}

/**
 * Récupère la liste des valeurs d'un type ENUM PostgreSQL.
 */
function getEnumValues(PDO $pdo, $enumType)
{
  $stmt = $pdo->query("SELECT unnest(enum_range(NULL::{$enumType})) AS valeur");
  $values = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $values[] = $row['valeur'];
  }
  return $values;
}

// Vérifier si une action est fournie
if (!isset($_POST['action'])) {
  jsonResponse(false, 'Aucune action spécifiée');
}

$action = $_POST['action'];

switch ($action) {
  case 'get_stats':
    try {
      // Total des utilisateurs
      $stmt = $pdo->query('SELECT COUNT(*) AS total FROM users');
      $totalUsers = $stmt->fetch()['total'];

      // Utilisateurs actifs
      $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users WHERE status = 'active'");
      $activeUsers = $stmt->fetch()['total'];

      // Nombre de rôles disponibles (valeurs du type ROLE_ENUM : ADMIN / USER)
      $stmt = $pdo->query('SELECT COUNT(*) AS total FROM unnest(enum_range(NULL::ROLE_ENUM)) AS r');
      $totalRoles = $stmt->fetch()['total'];

      jsonResponse(true, '', [
        'total_users' => (int) $totalUsers,
        'active_users' => (int) $activeUsers,
        'total_roles' => (int) $totalRoles,
      ]);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des statistiques');
    }
    break;

  case 'get_departements':
    try {
      // Les départements sont désormais les valeurs du type DEPARTMENT_ENUM.
      // On renvoie { id, nom } pour rester compatible avec le front-end,
      // où "id" correspond à la valeur exacte de l'ENUM.
      $departements = [];
      foreach (getEnumValues($pdo, 'DEPARTMENT_ENUM') as $valeur) {
        $departements[] = [
          'id' => $valeur,
          'nom' => $valeur,
        ];
      }

      jsonResponse(true, '', $departements);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des départements');
    }
    break;

  case 'get_roles':
    try {
      // Les rôles sont désormais les valeurs du type ROLE_ENUM (ADMIN / USER).
      $roles = [];
      foreach (getEnumValues($pdo, 'ROLE_ENUM') as $valeur) {
        $roles[] = [
          'id' => $valeur,
          'nom' => $valeur,
        ];
      }

      jsonResponse(true, '', $roles);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des rôles');
    }
    break;

  case 'get_users':
    try {
      // Les colonnes department et role sont directement dans la table users,
      // il n'y a plus de tables "departements" ni "roles" à joindre.
      $stmt = $pdo->query('
                SELECT
                    id,
                    username,
                    email,
                    department,
                    role,
                    status,
                    date_creation,
                    date_modification,
                    last_login
                FROM users
                ORDER BY date_creation DESC
            ');
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // On conserve les noms de champs attendus par le front-end :
      // nom_utilisateur, nom_departement, nom_role, statut.
      $users = [];
      foreach ($rows as $row) {
        $users[] = [
          'id' => (int) $row['id'],
          'nom_utilisateur' => $row['username'],
          'username' => $row['username'],
          'email' => $row['email'],
          'id_departement' => $row['department'],
          'nom_departement' => $row['department'],
          'id_role' => $row['role'],
          'nom_role' => $row['role'],
          'statut' => dbStatusToFront($row['status']),
          'status' => $row['status'],
          'date_creation' => $row['date_creation'],
        ];
      }

      jsonResponse(true, '', $users);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors du chargement des utilisateurs');
    }
    break;

  case 'create_user':
    // Validation des données
    if (
      empty($_POST['nom_utilisateur']) ||
      empty($_POST['email']) ||
      empty($_POST['mot_de_passe']) ||
      empty($_POST['id_departement']) ||
      empty($_POST['id_role'])
    ) {
      jsonResponse(false, 'Tous les champs sont obligatoires');
    }

    // Validation de l'email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
      jsonResponse(false, "Format d'email invalide");
    }

    // Validation de la force du mot de passe (minimum 6 caractères)
    if (strlen($_POST['mot_de_passe']) < 6) {
      jsonResponse(false, 'Le mot de passe doit contenir au moins 6 caractères');
    }

    try {
      // Vérifier si l'utilisateur ou l'email existe déjà
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
      $stmt->execute([$_POST['nom_utilisateur'], $_POST['email']]);
      if ($stmt->fetch()[0] > 0) {
        jsonResponse(false, "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà");
      }

      // Vérifier que le département est une valeur valide du DEPARTMENT_ENUM
      $validDepartments = getEnumValues($pdo, 'DEPARTMENT_ENUM');
      if (!in_array($_POST['id_departement'], $validDepartments, true)) {
        jsonResponse(false, 'Département invalide');
      }

      // Vérifier que le rôle est une valeur valide du ROLE_ENUM
      $validRoles = getEnumValues($pdo, 'ROLE_ENUM');
      if (!in_array($_POST['id_role'], $validRoles, true)) {
        jsonResponse(false, 'Rôle invalide');
      }

      // Créer l'utilisateur dans la table users
      $stmt = $pdo->prepare("
                INSERT INTO users
                    (username, email, password_hash, department, role, status, date_creation, date_modification)
                VALUES
                    (?, ?, ?, ?, ?, 'active', now(), now())
            ");

      $hashedPassword = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);

      $stmt->execute([
        $_POST['nom_utilisateur'],
        $_POST['email'],
        $hashedPassword,
        $_POST['id_departement'],
        $_POST['id_role'],
      ]);

      jsonResponse(true, 'Utilisateur créé avec succès !');
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) {
        // Violation de contrainte unique
        jsonResponse(false, "Un utilisateur avec ce nom d'utilisateur ou cet email existe déjà");
      } else {
        jsonResponse(false, "Erreur lors de la création de l'utilisateur");
      }
    } catch (Exception $e) {
      jsonResponse(false, "Erreur lors de la création de l'utilisateur");
    }
    break;

  case 'update_status':
    // Validation des données
    if (empty($_POST['user_id']) || empty($_POST['new_status'])) {
      jsonResponse(false, 'Paramètres manquants');
    }

    // Le front-end envoie les statuts "actif", "suspendu" ou "en_attente".
    // On les traduit vers le USERS_STATUS_ENUM de la base.
    $dbStatus = frontStatusToDb($_POST['new_status']);
    if ($dbStatus === null) {
      jsonResponse(false, 'Statut invalide');
    }

    try {
      // Vérifier si l'utilisateur existe
      $stmt = $pdo->prepare('SELECT COUNT(*) AS total, username FROM users WHERE id = ? GROUP BY username');
      $stmt->execute([$_POST['user_id']]);
      $result = $stmt->fetch();

      if (!$result || (int) $result['total'] === 0) {
        jsonResponse(false, 'Utilisateur non trouvé');
      }

      $username = $result['username'];

      // Mettre à jour le statut (et la date de modification)
      $stmt = $pdo->prepare('UPDATE users SET status = ?, date_modification = now() WHERE id = ?');
      $stmt->execute([$dbStatus, $_POST['user_id']]);

      $statusMessages = [
        'active' => 'activé',
        'suspended' => 'suspendu',
        'inactive' => 'mis en attente',
      ];

      $message = "L'utilisateur {$username} a été {$statusMessages[$dbStatus]} avec succès !";

      jsonResponse(true, $message);
    } catch (Exception $e) {
      jsonResponse(false, 'Erreur lors de la mise à jour du statut');
    }
    break;

  default:
    jsonResponse(false, 'Action non reconnue');
    break;
}
