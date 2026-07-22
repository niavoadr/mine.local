<?php
require_once __DIR__ . '/database.php';

// manager.php

header('Content-Type: application/json');

header('Cache-Control: no-cache, must-revalidate');

try {
    $pdo = get_pdo_connection('DB');
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit;
}
// Fonction pour retourner une réponse JSON

function jsonResponse($success, $message = '', $data = null) {
    echo json_encode([

        'success' => $success,

        'message' => $message,
        'data' => $data
    ]);

    exit;
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
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateurs");
            $totalUsers = $stmt->fetch()['total'];
            // Utilisateurs actifs
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateurs WHERE statut = 'actif'");
            $activeUsers = $stmt->fetch()['total'];
            // Total des rôles
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM roles");
            $totalRoles = $stmt->fetch()['total'];
            jsonResponse(true, '', [
                'total_users' => (int)$totalUsers,
                'active_users' => (int)$activeUsers,
                'total_roles' => (int)$totalRoles
            ]);
        } catch(Exception $e) {
            jsonResponse(false, 'Erreur lors du chargement des statistiques');
        }
        break;
    case 'get_departements':
        try {
            $stmt = $pdo->query("SELECT id, nom FROM departements ORDER BY nom");

            $departements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(true, '', $departements);
        } catch(Exception $e) {
            jsonResponse(false, 'Erreur lors du chargement des départements');
        }
        break;
    case 'get_roles':
        try {

            $stmt = $pdo->query("SELECT id, nom FROM roles ORDER BY nom");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(true, '', $roles);
        } catch(Exception $e) {
            jsonResponse(false, 'Erreur lors du chargement des rôles');
        }
        break;
    case 'get_users':
        try {
            $stmt = $pdo->query("
                SELECT u.*, d.nom as nom_departement, r.nom as nom_role 
                FROM utilisateurs u 
                LEFT JOIN departements d ON u.id_departement = d.id 
                LEFT JOIN roles r ON u.id_role = r.id 
                ORDER BY u.date_creation DESC
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convertir les IDs en entiers pour une meilleure cohérence

            foreach ($users as &$user) {
                $user['id'] = (int)$user['id'];
                $user['id_departement'] = $user['id_departement'] ? (int)$user['id_departement'] : null;
                $user['id_role'] = $user['id_role'] ? (int)$user['id_role'] : null;
            }

            jsonResponse(true, '', $users);
        } catch(Exception $e) {
            jsonResponse(false, 'Erreur lors du chargement des utilisateurs');
        }
        break;

    case 'create_user':

        // Validation des données

        if (empty($_POST['nom_utilisateur']) || empty($_POST['email']) || empty($_POST['mot_de_passe']) || 

            empty($_POST['id_departement']) || empty($_POST['id_role'])) {

            jsonResponse(false, 'Tous les champs sont obligatoires');
        }

        // Validation de l'email

        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {

            jsonResponse(false, 'Format d\'email invalide');

        }
        // Validation de la force du mot de passe (minimum 6 caractères)


        if (strlen($_POST['mot_de_passe']) < 6) {


            jsonResponse(false, 'Le mot de passe doit contenir au moins 6 caractères');


        }





        try {


            // Vérifier si l'utilisateur ou l'email existe déjà


            $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE nom_utilisateur = ? OR email = ?");


            $stmt->execute([$_POST['nom_utilisateur'], $_POST['email']]);


            if ($stmt->fetch()[0] > 0) {


                jsonResponse(false, 'Un utilisateur avec ce nom d\'utilisateur ou cet email existe déjà');


            }





            // Vérifier si le département existe


            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departements WHERE id = ?");


            $stmt->execute([$_POST['id_departement']]);


            if ($stmt->fetch()[0] == 0) {


                jsonResponse(false, 'Département invalide');


            }





            // Vérifier si le rôle existe


            $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id = ?");


            $stmt->execute([$_POST['id_role']]);


            if ($stmt->fetch()[0] == 0) {


                jsonResponse(false, 'Rôle invalide');


            }





            // Créer l'utilisateur


            $stmt = $pdo->prepare("


                INSERT INTO utilisateurs (nom_utilisateur, email, mot_de_passe_hash, id_departement, id_role, statut) 


                VALUES (?, ?, ?, ?, ?, 'actif')


            ");


            


            $hashedPassword = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);


            


            $stmt->execute([


                $_POST['nom_utilisateur'],


                $_POST['email'],


                $hashedPassword,


                $_POST['id_departement'],


                $_POST['id_role']


            ]);





            jsonResponse(true, 'Utilisateur créé avec succès !');


        } catch(PDOException $e) {


            if ($e->getCode() == 23000) { // Violation de contrainte unique


                jsonResponse(false, 'Un utilisateur avec ce nom d\'utilisateur ou cet email existe déjà');


            } else {


                jsonResponse(false, 'Erreur lors de la création de l\'utilisateur');


            }


        } catch(Exception $e) {


            jsonResponse(false, 'Erreur lors de la création de l\'utilisateur');


        }


        break;





    case 'update_status':


        // Validation des données


        if (empty($_POST['user_id']) || empty($_POST['new_status'])) {


            jsonResponse(false, 'Paramètres manquants');


        }





        // Validation du statut


        $validStatuses = ['actif', 'suspendu', 'en_attente'];


        if (!in_array($_POST['new_status'], $validStatuses)) {


            jsonResponse(false, 'Statut invalide');


        }





        try {


            // Vérifier si l'utilisateur existe


            $stmt = $pdo->prepare("SELECT COUNT(*), nom_utilisateur FROM utilisateurs WHERE id = ?");


            $stmt->execute([$_POST['user_id']]);


            $result = $stmt->fetch();


            if ($result[0] == 0) {


                jsonResponse(false, 'Utilisateur non trouvé');


            }





            $username = $result[1];





            // Mettre à jour le statut


            $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = ? WHERE id = ?");


            $stmt->execute([$_POST['new_status'], $_POST['user_id']]);





            $statusMessages = [


                'actif' => 'activé',


                'suspendu' => 'suspendu', 


                'en_attente' => 'mis en attente'


            ];





            $message = "L'utilisateur {$username} a été {$statusMessages[$_POST['new_status']]} avec succès !";


            jsonResponse(true, $message);


        } catch(Exception $e) {


            jsonResponse(false, 'Erreur lors de la mise à jour du statut');


        }


        break;





    default:


        jsonResponse(false, 'Action non reconnue');


        break;


}


?>