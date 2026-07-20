<?php
// Démarrer la capture de sortie pour éviter les sorties parasites
ob_start();

// Inclure la connexion à la base
require_once("./radius_connection.php");

// Nettoyer toute sortie parasite avant d'envoyer les headers
ob_clean();

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // Vérifier la connexion à la base de données
    if (!isset($conn) || !$conn) {
        throw new Exception('Connexion à la base de données échouée');
    }
    
    switch($action) {
        case 'get_devices':
            getDevices($conn);
            break;
            
        case 'add_device':
            addDevice($conn);
            break;
            
        case 'delete_device':
            deleteDevice($conn);
            break;
            
        case 'test':
            // Ajouter un test simple
            echo json_encode(['success' => true, 'message' => 'API RADIUS fonctionnelle', 'timestamp' => date('Y-m-d H:i:s')]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Action non spécifiée. Actions disponibles: get_devices, add_device, delete_device, test']);
    }
} catch(Exception $e) {
    // S'assurer qu'on retourne du JSON même en cas d'erreur
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => basename($e->getFile())]);
}

// Nettoyer et terminer
ob_end_flush();
exit;

function getDevices($conn) {
    try {
        $sql = "SELECT 
                    rc.id,
                    rc.username as mac_address,
                    rc.department,
                    rg.groupname,
                    rgr.attribute,
                    rgr.value
                FROM radcheck rc
                LEFT JOIN radusergroup rg ON rc.username = rg.username  
                LEFT JOIN radgroupreply rgr ON rg.groupname = rgr.groupname
                WHERE rgr.attribute = 'WISPr-Bandwidth-Max-Down'
                ORDER BY rc.department, rc.username";
        
        $result = mysqli_query($conn, $sql);
        
        if (!$result) {
            throw new Exception('Erreur SQL: ' . mysqli_error($conn));
        }
        
        $devices = [];
        
        while($row = mysqli_fetch_assoc($result)) {
            $devices[] = [
                'id' => $row['id'],
                'mac_address' => $row['mac_address'],
                'department' => $row['department'],
                'bandwidth' => $row['value'] ? round($row['value'] / 1000000) . ' Mbps' : 'N/A',
                'group' => $row['groupname']
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $devices, 'count' => count($devices)]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur getDevices: ' . $e->getMessage()]);
    }
}

function addDevice($conn) {
    $mac = $_POST['mac_address'] ?? '';
    $department = $_POST['department'] ?? '';
    
    if (empty($mac) || empty($department)) {
        throw new Exception("Adresse MAC et département requis");
    }
    
    // Validation format MAC
    if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
        throw new Exception("Format d'adresse MAC invalide");
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Ajouter dans radcheck
        $sql1 = "INSERT INTO radcheck (username, attribute, op, value, department) 
                 VALUES (?, 'Auth-Type', ':=', 'Accept', ?)";
        $stmt1 = mysqli_prepare($conn, $sql1);
        
        if (!$stmt1) {
            throw new Exception('Erreur préparation requête 1: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt1, 'ss', $mac, $department);
        
        if (!mysqli_stmt_execute($stmt1)) {
            throw new Exception('Erreur exécution requête 1: ' . mysqli_stmt_error($stmt1));
        }
        
        // 2. Associer au groupe départemental
        $groupname = $department . '_group';
        $sql2 = "INSERT INTO radusergroup (username, groupname, priority) 
                 VALUES (?, ?, 1)";
        $stmt2 = mysqli_prepare($conn, $sql2);
        
        if (!$stmt2) {
            throw new Exception('Erreur préparation requête 2: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt2, 'ss', $mac, $groupname);
        
        if (!mysqli_stmt_execute($stmt2)) {
            throw new Exception('Erreur exécution requête 2: ' . mysqli_stmt_error($stmt2));
        }
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Appareil ajouté avec succès']);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw new Exception("Erreur lors de l'ajout: " . $e->getMessage());
    }
}

function deleteDevice($conn) {
    $mac = $_POST['mac_address'] ?? '';
    
    if (empty($mac)) {
        throw new Exception("Adresse MAC requise");
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Supprimer de radusergroup
        $sql1 = "DELETE FROM radusergroup WHERE username = ?";
        $stmt1 = mysqli_prepare($conn, $sql1);
        
        if (!$stmt1) {
            throw new Exception('Erreur préparation suppression 1: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt1, 's', $mac);
        
        if (!mysqli_stmt_execute($stmt1)) {
            throw new Exception('Erreur exécution suppression 1: ' . mysqli_stmt_error($stmt1));
        }
        
        // 2. Supprimer de radcheck
        $sql2 = "DELETE FROM radcheck WHERE username = ?";
        $stmt2 = mysqli_prepare($conn, $sql2);
        
        if (!$stmt2) {
            throw new Exception('Erreur préparation suppression 2: ' . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt2, 's', $mac);
        
        if (!mysqli_stmt_execute($stmt2)) {
            throw new Exception('Erreur exécution suppression 2: ' . mysqli_stmt_error($stmt2));
        }
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Appareil supprimé avec succès']);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw new Exception("Erreur lors de la suppression: " . $e->getMessage());
    }
}
?>