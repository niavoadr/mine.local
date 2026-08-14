<?php
session_start();
require_once __DIR__ . '/connexion.php';

if (empty($_SESSION['user']) && empty($_SESSION['nom_utilisateur'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

check_csrf();

header('Content-Type: application/json');
$pdo = $connexion;

$action = $_POST['action'] ?? '';

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
        // C2 : création de visiteurs réservée aux administrateurs
        $stmtRole = $pdo->prepare("SELECT role FROM users WHERE username = ?");
        $stmtRole->execute([$_SESSION['user'] ?? $_SESSION['nom_utilisateur']]);
        if ($stmtRole->fetchColumn() !== 'ADMIN') {
            http_response_code(403);
            jsonResponse(false, 'Accès réservé aux administrateurs');
        }

        $username = trim($_POST['username'] ?? '');
        $duration = (int) ($_POST['duration'] ?? 0);

        if (empty($username)) {
            jsonResponse(false, 'Le nom d\'utilisateur est requis.');
        }

        // M1 : durée limitée à 10 min, 30 min, 1 h ou 2 h
        if (!in_array($duration, [10, 30, 60, 120], true)) {
            jsonResponse(false, 'Durée invalide. Choisissez 10 min, 30 min, 1 h ou 2 h.');
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

            // M2 : limite de bande passante via le groupe visitor_group.
            // Le rattachement au groupe est OBLIGATOIRE : sans lui, le visiteur
            // s'authentifie mais le NAS ne reçoit aucune limite de débit.
            $stmtGroup = $pdo->prepare("SELECT 1 FROM pg_enum e JOIN pg_type t ON t.oid = e.enumtypid WHERE t.typname = 'groupname_enum' AND e.enumlabel = 'visitor_group'");
            $stmtGroup->execute();
            if (!$stmtGroup->fetchColumn()) {
                throw new Exception("Le groupe 'visitor_group' est absent de la base : impossible d'appliquer la limite de débit.");
            }

            $stmtProfile = $pdo->prepare("SELECT COUNT(*) FROM radgroupreply WHERE groupname = 'visitor_group' AND attribute = 'WISPr-Bandwidth-Max-Down'");
            $stmtProfile->execute();
            if ((int) $stmtProfile->fetchColumn() === 0) {
                throw new Exception("Aucun profil de débit défini pour 'visitor_group' (radgroupreply) : appliquez la migration database/migrations/2026_08_14_bandwidth_limits.sql.");
            }

            $stmt = $pdo->prepare("INSERT INTO radusergroup (username, groupname, priority) VALUES (?, 'visitor_group', 1)");
            $stmt->execute([$username]);

            $pdo->commit();

            jsonResponse(true, 'Visiteur créé avec succès !', [
                'username' => $username,
                'password' => $password,
                'expires_at' => date('d/m/Y H:i:s', strtotime($expiresAt))
            ]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Erreur création visiteur : ' . $e->getMessage());
            jsonResponse(false, 'Erreur lors de la création du visiteur');
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

            // C4 : l'expiration est désormais gérée par le cron expire_visitors.php
            foreach ($visitors as &$v) {
                $v['display_created_at'] = date('d/m/Y H:i:s', strtotime($v['date_creation']));
                $v['display_duration'] = $v['duration'] . ' min';
                
                $v['mac_address'] = $v['mac_address'] ?: 'N/A';
                $v['ip_address'] = $v['ip_address'] ?: 'N/A';
            }
            unset($v);

            jsonResponse(true, '', $visitors);
        } catch (Exception $e) {
            error_log('Erreur récupération des visiteurs : ' . $e->getMessage());
            jsonResponse(false, 'Erreur lors de la récupération des visiteurs');
        }
        break;

    default:
        jsonResponse(false, 'Action non reconnue');
        break;
}
