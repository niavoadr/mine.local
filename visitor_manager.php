<?php
session_start();
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/visitor_radius_helpers.php';

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
        $duration = intval($_POST['duration'] ?? 0); // in minutes

        if (empty($username) || $duration <= 0) {
            jsonResponse(false, 'Le nom d\'utilisateur et une durée valide sont requis.');
        }

        try {
            // Check if visitor already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM visitor WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(false, 'Ce nom d\'utilisateur visiteur existe déjà.');
            }

            $password = generateRandomPassword(8);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $createdBy = $_SESSION['user_id'] ?? 1;
            $userDept = 'Communication'; // Default

            // Get current user ID and department if not in session
            $connectedUser = $_SESSION['user'] ?? $_SESSION['nom_utilisateur'];
            $stmt = $pdo->prepare("SELECT id, department FROM users WHERE username = ?");
            $stmt->execute([$connectedUser]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow) {
                $createdBy = $userRow['id'];
                $userDept = $userRow['department'];
            }

            $expiresAt = date('Y-m-d H:i:s', strtotime("+$duration minutes"));

            // Valeurs temporaires : la vraie MAC/NAS sera renseignée par
            // check_visitor.php lors de la première connexion.
            $dummyMac = '00:00:00:00:00:00';
            $dummyNasIp = '0.0.0.0';

            $pdo->beginTransaction();

            // Le visiteur est enregistré uniquement dans visitor.
            // On n'ajoute plus username/password dans radcheck : l'autorisation
            // radcheck sera créée plus tard avec la MAC du client si le portail
            // captif valide les identifiants.
            $stmt = $pdo->prepare("INSERT INTO visitor (username, password_hash, department, created_by, expires_at, duration, status, mac_address, nas_ip)
                                   VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)");
            $stmt->execute([$username, $hashedPassword, $userDept, $createdBy, $expiresAt, $duration, $dummyMac, $dummyNasIp]);

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
            // Nettoie les comptes expirés avant affichage : la ligne visitor est
            // conservée, mais l'autorisation MAC dans radcheck est supprimée.
            visitor_cleanup_expired_visitors($pdo);

            // Join visitor with users (creator) and radacct (latest session by MAC).
            // Avec la nouvelle méthode, radacct peut être indexé par l'adresse MAC
            // plutôt que par le username du visiteur.
            $sql = "SELECT
                        v.username,
                        v.status,
                        v.expires_at,
                        v.duration,
                        v.date_creation,
                        v.mac_address::text AS visitor_mac_address,
                        u.username as creator_name,
                        COALESCE(NULLIF(v.mac_address::text, '00:00:00:00:00:00'), a.callingstationid) as mac_address,
                        a.framedipaddress::text as ip_address,
                        a.acctstarttime as last_session_start
                    FROM visitor v
                    JOIN users u ON v.created_by = u.id
                    LEFT JOIN (
                        SELECT DISTINCT ON (normalized_mac)
                               normalized_mac,
                               callingstationid,
                               framedipaddress,
                               acctstarttime
                          FROM (
                                SELECT regexp_replace(lower(COALESCE(NULLIF(callingstationid, ''), username)), '[^0-9a-f]', '', 'g') AS normalized_mac,
                                       callingstationid,
                                       framedipaddress,
                                       acctstarttime
                                  FROM radacct
                                 WHERE acctstarttime IS NOT NULL
                               ) latest_sessions
                         WHERE char_length(normalized_mac) = 12
                         ORDER BY normalized_mac, acctstarttime DESC
                    ) a ON a.normalized_mac = regexp_replace(lower(v.mac_address::text), '[^0-9a-f]', '', 'g')
                    ORDER BY v.date_creation DESC";

            $stmt = $pdo->query($sql);
            $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($visitors as &$v) {
                // Format dates for display
                // Début = Date de création du compte
                $v['display_start'] = date('d/m/Y H:i:s', strtotime($v['date_creation']));
                // Fin = Date d'expiration (Création + Durée)
                $v['display_end'] = date('d/m/Y H:i:s', strtotime($v['expires_at']));
                // Durée = Durée en minutes (formatée)
                $v['display_duration'] = $v['duration'] . ' min';

                // Affiche la MAC conservée dans visitor après validation portail,
                // sauf pour la valeur temporaire utilisée avant première connexion.
                if (!empty($v['visitor_mac_address']) && !visitor_is_dummy_mac_address($v['visitor_mac_address'])) {
                    try {
                        $v['mac_address'] = visitor_normalize_mac_address($v['visitor_mac_address']);
                    } catch (Exception $e) {
                        $v['mac_address'] = $v['visitor_mac_address'];
                    }
                } else {
                    $v['mac_address'] = $v['mac_address'] ?: 'N/A';
                }

                $v['ip_address'] = $v['ip_address'] ?: 'N/A';
                unset($v['visitor_mac_address']);
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
