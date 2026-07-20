<?php
// PHP : Assurez-vous que la session est démarrée au tout début du script
session_start();

// Définition de l'ID de l'utilisateur connecté
$user_role_id = $_SESSION['role_lib'] ?? "";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RADIUS Dashboard - Tableau de Bord Ministére des Mines</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 50%, #F4A460 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .header h1 {
            color: #8B4513;
            font-size: 2.5em;
            text-align: center;
            margin-bottom: 10px;
        }

        .header p {
            text-align: center;
            color: #A0522D;
            font-size: 1.1em;
        }

        .navigation {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .nav-tabs {
            display: flex;
            background: #f8f9fa;
        }

        .nav-tab {
            flex: 1;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
            font-size: 1.1em;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s ease;
            border-bottom: 4px solid transparent;
        }

        .nav-tab:hover {
            background: #e9ecef;
            color: #495057;
        }

        .nav-tab.active {
            background: white;
            color: #8B4513;
            border-bottom-color: #D2691E;
            transform: translateY(-2px);
        }

        .content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            min-height: 600px;
        }

        .content-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            font-size: 2em;
            color: #8B4513;
            margin-bottom: 20px;
            border-bottom: 3px solid #D2691E;
            padding-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #8B4513 0%, #D2691E 50%, #F4A460 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(139, 69, 19, 0.3);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .device-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .device-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #D2691E;
            box-shadow: 0 2px 5px rgba(139, 69, 19, 0.1);
        }

        .device-mac {
            font-weight: bold;
            color: #8B4513;
        }

        .device-dept {
            color: #A0522D;
            font-size: 0.9em;
        }

        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #8B4513;
            color: white;
        }

        .btn-success {
            background: #D2691E;
            color: white;
        }

        .btn-warning {
            background: #F4A460;
            color: #8B4513;
        }

        .btn-danger {
            background: #CD853F;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }

        .access-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .access-table th {
            background: #8B4513;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .access-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .access-table tr:hover {
            background: #f8f9fa;
        }

        .status-online {
            color: #D2691E;
            font-weight: bold;
        }

        .status-offline {
            color: #CD853F;
            font-weight: bold;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #F5DEB3;
            border: 1px solid #D2691E;
            color: #8B4513;
        }
    </style>
</head>
<!-- Avant </body> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="manager.js"></script>
<body>
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <div class="header-content">
                <img src="images/logomine.jpg" alt="Logo Ministère des Mines" class="header-logo">
                <h1>Tableau de Bord Ministére des Mines</h1>
            </div>
            <p>Système de Gestion des Accès Réseau </p>
        </div>

        <!-- Barre de navigation -->
        <div class="navigation">
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="switchTab('hosts')">
                    🏢 Hôtes de l'Entreprise
                </button>
                <button class="nav-tab" onclick="switchTab('strangers')">
                    👥 Étrangers
                </button>
                <button class="nav-tab" onclick="switchTab('manager')">
                    ⚙️ Gestionnaire de Compte
                </button>
            </div>
        </div>

        <!-- Contenu dynamique -->
         
<!-- Section Hôtes de l'Entreprise2 - Interface RADIUS Existante -->
            <div id="hosts-content" class="content-section active">
    <iframe
        src="radius_interface_admin.php?role_lib=<?php echo $user_role_id; ?>" 
        width="100%" 
        height="800" 
        frameborder="0"
        style="border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
        Chargement de l'interface RADIUS...
    </iframe>
</div>


            <!-- Section Étrangers -->
           
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Styles personnalisés pour correspondre à votre thème */
        body {
            background-color: #F5F5DC; /* Couleur de fond de votre thème */
        }
        .content-section {
            padding: 20px;
            background-color: #FFFFFF; /* Fond blanc pour la carte */
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #ced4da; /* Bordure légère */
        }
        .section-title {
            color: #5C4033; /* Marron foncé pour le titre */
            margin-bottom: 20px;
            border-bottom: 2px solid #8B4513; /* Marron moyen pour la ligne */
            padding-bottom: 10px;
        }
        .form-label {
            color: #5C4033; /* Couleur de texte des labels */
            font-weight: bold;
        }
        .form-control[type="date"] {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
            height: calc(1.5em + 0.75rem + 2px);
            font-size: 1rem;
            line-height: 1.5;
            color: #495057;
            background-color: #fff;
            background-clip: padding-box;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        .form-control[type="date"]:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .btn-primary {
            background-color: #8B4513; /* Marron moyen pour les boutons */
            border-color: #8B4513;
        }
        .btn-primary:hover {
            background-color: #5C4033; /* Marron foncé au survol */
            border-color: #5C4033;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table thead th {
            background-color: #5C4033; /* En-tête de tableau marron foncé */
            color: #fff;
        }
        .table-hover tbody tr:hover {
            background-color: #f0f0f0; /* Effet de survol */
        }
        .text-success { color: #28a745 !important; }
        .text-danger { color: #dc3545 !important; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">
    <div id="strangers-content" class="content-section">
        <h2 class="section-title">Historique des Accès Étrangers</h2>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <label for="startDate" class="form-label">Date de début</label>
                <input type="date" class="form-control" id="startDate">
            </div>
            <div class="col-md-4">
                <label for="endDate" class="form-label">Date de fin</label>
                <input type="date" class="form-control" id="endDate">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100" id="filter-btn">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr>
                        <th>Nom d'utilisateur</th>
                        <th>Adresse MAC</th>
                        <th>Adresse IP</th>
                        <th>Début de session</th>
                        <th>Fin de session</th>
                        <th>Durée</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody id="history-table">
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        loadHistory();
        $("#filter-btn").on('click', function() {
            loadHistory();
        });
    });

    function loadHistory() {
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        
        $('#history-table').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...</td></tr>');
        
        $.ajax({
            url: 'history.php',
            type: 'POST',
            data: {
                action: 'get_history',
                start_date: startDate,
                end_date: endDate
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayHistory(response.data);
                } else {
                    $('#history-table').html('<tr><td colspan="7" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Erreur AJAX: " + status + ", " + error);
                $('#history-table').html('<tr><td colspan="7" class="text-center text-danger py-4"><i class="fas fa-times-circle me-2"></i>Une erreur de communication est survenue.</td></tr>');
            }
        });
    }
    
    function displayHistory(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucune connexion trouvée pour la période sélectionnée.</td></tr>';
        } else {
            records.forEach(function(record) {
                const status = record.acctstoptime ? 'Déconnecté' : 'Actif';
                const statusClass = record.acctstoptime ? 'text-danger' : 'text-success';
                const timeLeft = calculateTimeLeft(record.acctstarttime, record.acctstoptime);
                
                html += `
                    <tr>
                        <td>${record.username}</td>
                        <td>${record.callingstationid}</td>
                        <td>${record.framedipaddress || 'N/A'}</td>
                        <td>${record.acctstarttime}</td>
                        <td>${record.acctstoptime || 'En cours'}</td>
                        <td>${formatDuration(record.acctsessiontime)}</td>
                        <td class="${statusClass}">
                            ${status}
                            ${status === 'Actif' ? `<br/><small>(${timeLeft} restants)</small>` : ''}
                        </td>
                    </tr>
                `;
            });
        }
        $('#history-table').html(html);
    }

    function calculateTimeLeft(startTime, stopTime) {
        if (stopTime) return '';
        const sessionDuration = 2 * 60;
        const now = new Date();
        const start = new Date(startTime);
        const elapsedSeconds = (now - start) / 1000;
        const remainingSeconds = sessionDuration - elapsedSeconds;
        if (remainingSeconds <= 0) {
            return 'Expiré';
        }
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = Math.floor(remainingSeconds % 60);
        return `${minutes}min ${seconds}s`;
    }
    
    function formatDuration(seconds) {
        if (seconds === null || seconds === undefined || isNaN(seconds)) return 'N/A';
        const totalSeconds = parseInt(seconds);
        if (totalSeconds < 0) return 'N/A';
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const sec = totalSeconds % 60;
        let result = [];
        if (hours > 0) result.push(`${hours}h`);
        if (minutes > 0 || hours > 0) result.push(`${minutes}min`);
        result.push(`${sec}s`);
        return result.join(' ');
    }
</script>

</body>
</html>

                <!-- Section Gestionnaire de Compte -->
    <button onclick="confirmLogout()" class="btn btn-danger">
    <i class="fas fa-sign-out-alt"></i> Déconnexion
</button>

<script>
function confirmLogout() {
    if (confirm("Êtes-vous sûr de vouloir vous déconnecter ?")) {
        window.location.href = "logout.php";
    }
}
</script>           
                    
<div id="manager-content" class="content-section active">
    <h2 class="section-title">Gestionnaire de Compte</h2>
    
    <div id="alert-success" class="alert alert-success" style="display: none;">
        <strong>Succès :</strong> <span id="success-message"></span>
    </div>
    <div id="alert-error" class="alert alert-error" style="display: none;">
        <strong>Erreur :</strong> <span id="error-message"></span>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number" id="stat-total-users">-</div>
            <div class="stat-label">Total Utilisateurs</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="stat-active-users">-</div>
            <div class="stat-label">Utilisateurs Actifs</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="stat-total-roles">-</div>
            <div class="stat-label">Rôles Disponibles</div>
        </div>
    </div>

    <div style="margin-top: 30px;">
        <div>
            <h3>Utilisateurs Existants 
                <button type="button" class="btn btn-success" id="ajax-btn-refresh" style="padding: 8px 15px; font-size: 0.9em; float: right;">
                    🔄 Actualiser
                </button>
            </h3>
            
            <div id="ajax-users-loading" class="ajax-loading" style="display: none; text-align: center; padding: 20px; color: #8B4513;">
                Chargement des utilisateurs...
            </div>
            
            <div id="ajax-users-container">
                </div>
        </div>
    </div>
</div>

<script>
    function switchTab(tabName) {
        // Masquer toutes les sections
        const sections = document.querySelectorAll('.content-section');
        sections.forEach(section => section.classList.remove('active'));
        
        // Désactiver tous les onglets
        const tabs = document.querySelectorAll('.nav-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        
        // Activer la section et l'onglet sélectionnés
        document.getElementById(tabName + '-content').classList.add('active');
        event.target.classList.add('active');
    }

    // Animation des statistiques au chargement (conservée pour l'esthétique)
    window.addEventListener('load', function() {
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(stat => {
            const finalNumber = stat.textContent;
            stat.textContent = '0';
            
            let current = 0;
            const increment = parseInt(finalNumber) / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= parseInt(finalNumber)) {
                    stat.textContent = finalNumber;
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(current);
                }
            }, 30);
        });
    });

    // Simulation de données en temps réel (conservée)
    setInterval(() => {
        const activeDevices = document.querySelector('.stat-number');
        if (activeDevices) {
            let current = parseInt(activeDevices.textContent);
            let variation = Math.floor(Math.random() * 5) - 2;
            let newValue = Math.max(140, Math.min(150, current + variation));
            activeDevices.textContent = newValue;
        }
    }, 10000);
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="managerAdmin.php"></script>

</body>
</html>