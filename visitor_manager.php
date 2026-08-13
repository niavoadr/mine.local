<?php
session_start();
require_once __DIR__ . '/connexion.php';

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

header('Content-Type: application/json');
$pdo = $connexion;

$action = $_POST['action'] ?? '';

function jsonResponse($success, $message = '', $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomPassword = '';
    for ($i = 0; $i < $length; $i++) {
        $randomPassword .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomPassword;
}

switch ($action) {
    case 'create_visitor':
        $username = trim($_POST['username'] ?? '');
        $duration = intval($_POST['duration'] ?? 0);
        
        if (empty($username) || $duration <= 0) {
            jsonResponse(false, 'Le nom d\'utilisateur et une durée valide sont requis.');
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitor WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, 'Ce nom d\'utilisateur visiteur existe déjà.');
            }

            $password = generateRandomPassword(8);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $createdBy = $_SESSION['user_id'] ?? 1;
            $userDept = 'Communication';

            $connectedUser = $_SESSION['user'] ?? $_SESSION['nom_utilisateur'];
            $stmt = $pdo->prepare("SELECT id, department FROM users WHERE username = ?");
            $stmt->execute([$connectedUser]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow) {
                $createdBy = $userRow['id'];
                $userDept = $userRow['department'];
            }

            $expiresAt = date('Y-m-d H:i:s', strtotime("+$duration minutes"));

            $dummyMac = '00:00:00:00:00:00';
            $dummyNasIp = '0.0.0.0';

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO visitor (username, password_hash, department, created_by, expires_at, duration, status, mac_address, nas_ip) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)");
            $stmt->execute([$username, $hashedPassword, $userDept, $createdBy, $expiresAt, $duration, $dummyMac, $dummyNasIp]);

            $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, 'Cleartext-Password', ':=', $password]);

            $pdo->commit();

            jsonResponse(true, 'Visiteur créé avec succès !', [
                'username' => $username,
                'password' => $password,
                'expires_at' => date('d/m/Y H:i:s', strtotime($expiresAt))
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            jsonResponse(false, 'Erreur lors de la création du visiteur : ' . $e->getMessage());
        }
        break;

    case 'get_visitors':
        try {
            $sql = "SELECT 
                        v.username, 
                        v.status, 
                        v.expires_at, 
                        v.duration, 
                        v.date_creation,
                        u.username as creator_name,
                        a.callingstationid as mac_address,
                        a.framedipaddress as ip_address,
                        a.acctstarttime as last_session_start
                    FROM visitor v
                    JOIN users u ON v.created_by = u.id
                    LEFT JOIN (
                        SELECT DISTINCT ON (username) username, callingstationid, framedipaddress, acctstarttime
                        FROM radacct
                        ORDER BY username, acctstarttime DESC
                    ) a ON v.username = a.username
                    ORDER BY v.date_creation DESC";
            
            $stmt = $pdo->query($sql);
            $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $now = new DateTime();
            foreach ($visitors as &$v) {
                $expiry = new DateTime($v['expires_at']);
                if ($v['status'] === 'active' && $expiry < $now) {
                    try {
                        $pdo->beginTransaction();
                        $updateStmt = $pdo->prepare("UPDATE visitor SET status = 'expired' WHERE username = ?");
                        $updateStmt->execute([$v['username']]);
                        
                        $deleteStmt = $pdo->prepare("DELETE FROM radcheck WHERE username = ?");
                        $deleteStmt->execute([$v['username']]);
                        
                        $pdo->commit();
                        $v['status'] = 'expired';
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                    }
                }

                $v['display_created_at'] = date('d/m/Y H:i:s', strtotime($v['date_creation']));
                $v['display_duration'] = $v['duration'] . ' min';
                
                $v['mac_address'] = $v['mac_address'] ?: 'N/A';
                $v['ip_address'] = $v['ip_address'] ?: 'N/A';
            }

            jsonResponse(true, '', $visitors);
        } catch (Exception $e) {
            jsonResponse(false, 'Erreur lors de la récupération des visiteurs : ' . $e->getMessage());
        }
        break;

    default:
        jsonResponse(false, 'Action non reconnue');
        break;
}
