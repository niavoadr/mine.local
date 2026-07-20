<?php
// Fichier de test pour diagnostiquer les problèmes RADIUS
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test RADIUS - Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test { margin: 10px 0; padding: 10px; border: 1px solid #ccc; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .info { background: #d1ecf1; border-color: #b8daff; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
    </style>
</head>

<body>
    <h1>🔧 Test de Diagnostic RADIUS</h1>
    
    <?php
    echo "<div class='test info'>";
    echo "<h3>📋 Informations système</h3>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "Date/Heure: " . date('Y-m-d H:i:s') . "<br>";
    echo "Répertoire actuel: " . __DIR__ . "<br>";
    echo "</div>";
    ?>

    <!-- Test 1: Connexion à la base de données -->
    <div class="test">
        <h3>🔌 Test 1: Connexion à la base de données</h3>
        <?php
        try {
            $host = 'localhost';
            $username = 'root';
            $password = '123456';
            $database = 'radius';
            
            $conn = mysqli_connect($host, $username, $password, $database);
            
            if (!$conn) {
                echo "<div class='error'>❌ Erreur de connexion: " . mysqli_connect_error() . "</div>";
            } else {
                echo "<div class='success'>✅ Connexion réussie à la base 'radius'</div>";
                echo "Serveur MySQL: " . mysqli_get_server_info($conn) . "<br>";
                
                // Test des tables
                $tables = ['radcheck', 'radreply', 'radusergroup', 'radgroupreply'];
                foreach($tables as $table) {
                    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
                    if(mysqli_num_rows($result) > 0) {
                        echo "✅ Table '$table' existe<br>";
                    } else {
                        echo "❌ Table '$table' manquante<br>";
                    }
                }
            }
        } catch(Exception $e) {
            echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>

    <!-- Test 2: Test du fichier radius_connection.php -->
    <div class="test">
        <h3>📄 Test 2: Fichier radius_connection.php</h3>
        <?php
        if(file_exists('./radius_connection.php')) {
            echo "<div class='success'>✅ Fichier radius_connection.php existe</div>";
            
            // Test d'inclusion
            ob_start();
            $error = false;
            try {
                require_once('./radius_connection.php');
                if(isset($conn) && $conn) {
                    echo "✅ Inclusion réussie, connexion OK<br>";
                } else {
                    echo "❌ Inclusion réussie mais pas de connexion<br>";
                    $error = true;
                }
            } catch(Exception $e) {
                echo "❌ Erreur lors de l'inclusion: " . $e->getMessage() . "<br>";
                $error = true;
            }
            $output = ob_get_clean();
            
            if($error) {
                echo "<div class='error'>$output</div>";
            } else {
                echo "<div class='success'>$output</div>";
            }
            
        } else {
            echo "<div class='error'>❌ Fichier radius_connection.php introuvable</div>";
        }
        ?>
    </div>

    <!-- Test 3: Test des données -->
    <div class="test">
        <h3>📊 Test 3: Données dans les tables</h3>
        <?php
        if(isset($conn) && $conn) {
            // Compter les appareils dans radcheck
            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM radcheck");
            $row = mysqli_fetch_assoc($result);
            echo "Appareils dans radcheck: " . $row['count'] . "<br>";
            
            // Compter les groupes
            $result = mysqli_query($conn, "SELECT COUNT(DISTINCT groupname) as count FROM radgroupreply");
            $row = mysqli_fetch_assoc($result);
            echo "Groupes configurés: " . $row['count'] . "<br>";
            
            // Lister les départements
            $result = mysqli_query($conn, "SELECT DISTINCT department FROM radcheck WHERE department IS NOT NULL");
            $departments = [];
            while($row = mysqli_fetch_assoc($result)) {
                $departments[] = $row['department'];
            }
            echo "Départements: " . implode(', ', $departments) . "<br>";
        } else {
            echo "<div class='error'>❌ Pas de connexion pour tester les données</div>";
        }
        ?>
    </div>

    <!-- Test 4: Test AJAX -->
    <div class="test">
        <h3>🌐 Test 4: Test API AJAX</h3>
        <button onclick="testAjax()">Tester l'API</button>
        <div id="ajax_result"></div>
        
        <script>
        function testAjax() {
            fetch('radius_devices.php?action=get_devices')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('ajax_result').innerHTML = 
                        '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    document.getElementById('ajax_result').innerHTML = 
                        '<div class="error">Erreur AJAX: ' + error + '</div>';
                });
        }
        </script>
    </div>

    <!-- Test 5: Configuration PHP -->
    <div class="test">
        <h3>⚙️ Test 5: Configuration PHP</h3>
        <?php
        echo "Extension MySQLi: " . (extension_loaded('mysqli') ? '✅ Chargée' : '❌ Manquante') . "<br>";
        echo "Display Errors: " . (ini_get('display_errors') ? 'ON' : 'OFF') . "<br>";
        echo "Error Reporting: " . ini_get('error_reporting') . "<br>";
        ?>
    </div>

</body>
</html>