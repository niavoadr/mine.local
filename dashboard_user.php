<?php
session_start();

// Informations de l'utilisateur connecté. Une session absente ne doit jamais afficher un nom par défaut.
$connected_username = trim((string) ($_SESSION['user'] ?? $_SESSION['nom_utilisateur'] ?? ''));
if ($connected_username === '') {
    header('Location: login.php');
    exit;
}
$user_role_id = $_SESSION['role_lib'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RADIUS Dashboard - Tableau de Bord Administration Restreinte</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark-1: #0a0a0c;
            --bg-dark-2: #141418;
            --bg-card: rgba(24, 24, 30, 0.8);
            --gold-primary: #DAA520;
            --gold-dark: #B8860B;
            --gold-glow: rgba(218, 165, 32, 0.25);
            --border-gold: rgba(218, 165, 32, 0.28);
            --text-muted: #9ca3af;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-dark-1);
            background-image: 
                radial-gradient(circle at 15% 10%, rgba(184, 134, 11, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(218, 165, 32, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(20, 20, 25, 1) 0%, var(--bg-dark-1) 100%);
            color: #e5e7eb;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* ===== BARRE DE STATUT MINISTÉRIELLE ===== */
        .ministry-status-bar {
            background: rgba(10, 10, 12, 0.95);
            border-bottom: 1px solid var(--border-gold);
            padding: 0.45rem 1.5rem;
            font-size: 0.75rem;
            color: var(--gold-primary);
            font-weight: 600;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #FFD700;
            border-radius: 50%;
            margin-right: 0.5rem;
            box-shadow: 0 0 8px #FFD700;
            animation: pulseGold 2s infinite;
        }

        @keyframes pulseGold {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.15); }
        }

        /* ===== EN-TÊTE DU DASHBOARD ===== */
        .dashboard-header {
            background: rgba(18, 18, 24, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-logo-box {
            width: 50px;
            height: 50px;
            background: #fff;
            border-radius: 12px;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--gold-primary);
            box-shadow: 0 4px 15px rgba(218, 165, 32, 0.25);
        }

        .header-logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }

        .header-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* ===== NAVIGATION BAR (ONGLETS) ===== */
        .nav-tabs-container {
            background: rgba(14, 14, 18, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 50px;
            padding: 6px;
            display: inline-flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .nav-tab {
            background: transparent;
            border: none;
            border-radius: 50px;
            color: var(--text-muted);
            padding: 0.55rem 1.25rem;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .nav-tab:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-tab.active {
            background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
            color: #000000 !important;
            box-shadow: 0 4px 15px rgba(218, 165, 32, 0.4);
        }

        .user-profile-pill {
            background: rgba(218, 165, 32, 0.12);
            border: 1px solid var(--border-gold);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
        }

        .btn-logout-modern {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #f87171;
            padding: 0.45rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-logout-modern:hover {
            background: #ef4444;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        /* ===== CONTENU PRINCIPAL ===== */
        .content-section {
            display: none;
            animation: fadeInTab 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glass-iframe {
            border-radius: 18px;
            background: transparent;
            width: 100%;
            height: 860px;
            border: 1px solid var(--border-gold);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }

        .card-custom {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-custom-header {
            background: linear-gradient(135deg, rgba(218, 165, 32, 0.15) 0%, rgba(184, 134, 11, 0.08) 100%);
            border-bottom: 1px solid var(--border-gold);
            padding: 1rem 1.5rem;
            color: #FFD700;
            font-weight: 600;
        }

        .card-custom-body {
            padding: 1.5rem;
        }

        .form-control {
            background: rgba(18, 18, 22, 0.9) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 12px !important;
            color: #ffffff !important;
            padding: 0.65rem 1rem !important;
        }

        .form-control:focus {
            border-color: var(--gold-primary) !important;
            box-shadow: 0 0 0 4px var(--gold-glow) !important;
        }

        .table-responsive {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            color: #e5e7eb;
        }

        .table thead th {
            background: rgba(218, 165, 32, 0.15);
            color: #FFD700;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.82rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 14px 18px;
        }

        .table tbody tr td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 14px 18px;
            vertical-align: middle;
            font-size: 0.92rem;
        }

        .table tbody tr:hover td {
            background: rgba(218, 165, 32, 0.08);
        }

        /* ===== SURCHARGES SPÉCIFIQUES POUR LE GESTIONNAIRE DE COMPTE (#manager-content) ===== */
        #manager-content {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 22px;
            padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.5);
        }

        #manager-content .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1.5rem;
        }

        #manager-content .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        #manager-content .stat-card {
            background: rgba(30, 30, 38, 0.8) !important;
            border: 1px solid var(--border-gold) !important;
            border-radius: 18px !important;
            padding: 1.5rem !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
            transition: transform 0.3s ease !important;
        }

        #manager-content .stat-card:hover {
            transform: translateY(-4px) !important;
            border-color: var(--gold-primary) !important;
        }

        #manager-content .stat-number {
            color: #FFD700 !important;
            font-size: 2.5rem !important;
            font-weight: 700 !important;
        }

        #manager-content .stat-label {
            color: var(--text-muted) !important;
            font-size: 0.85rem !important;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        #manager-content .ajax-users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 1.5rem;
        }

        #manager-content .ajax-users-table th {
            background: rgba(218, 165, 32, 0.15);
            color: #FFD700;
            padding: 14px 18px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.82rem;
            letter-spacing: 0.5px;
            border: none;
        }

        #manager-content .ajax-users-table td {
            background: rgba(30, 30, 38, 0.7);
            color: #e5e7eb;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        #manager-content .ajax-users-table tr:hover td {
            background: rgba(45, 45, 55, 0.85);
        }
    </style>
    <link rel="stylesheet" href="css/responsive.css?v=20260722">
    <link rel="stylesheet" href="css/animations.css?v=20260721">
</head>
<body>

    <!-- BARRE DE STATUT MINISTÉRIELLE -->
    <div class="ministry-status-bar">
        <div class="d-flex align-items-center">
            <span class="status-indicator"></span>
            <span>SYSTÈME SÉCURISÉ | TABLEAU DE BORD ADMINISTRATION RESTREINTE — MINISTÈRE DES MINES</span>
        </div>
    </div>

    <!-- EN-TÊTE ET NAVIGATION -->
    <header class="dashboard-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="header-logo-box">
                    <img src="images/logomine.jpg" alt="Logo Ministère des Mines">
                </div>
                <div>
                    <h1 class="header-title">Tableau de Bord Départemental</h1>
                    <span class="header-subtitle">Ministère des Mines — Vue Administrateur Restreint</span>
                </div>
            </div>

            <!-- ONGLETS DE NAVIGATION -->
            <nav class="nav-tabs-container">
                <button class="nav-tab active" onclick="switchTab('hosts')">
                    <i class="fa-solid fa-building-shield"></i> <span>Hôtes de l'Entreprise</span>
                </button>
                <button class="nav-tab" onclick="switchTab('strangers')">
                    <i class="fa-solid fa-users-viewfinder"></i> <span>Accès Étrangers</span>
                </button>
                <button class="nav-tab" onclick="switchTab('manager')">
                    <i class="fa-solid fa-users-gear"></i> <span>Gestionnaire de Compte</span>
                </button>
            </nav>

            <!-- UTILISATEUR & DECONNEXION -->
            <div class="d-flex align-items-center gap-3">
                <div class="user-profile-pill d-none d-lg-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-shield text-warning"></i>
                    <span class="fw-semibold"><?php echo htmlspecialchars($connected_username, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="badge bg-warning text-dark ms-1">User</span>
                </div>
                <button onclick="confirmLogout()" class="btn-logout-modern" title="Déconnexion">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span class="d-none d-sm-inline">Déconnexion</span>
                </button>
            </div>
        </div>
    </header>

    <!-- CONTENU PRINCIPAL DES ONGLETS -->
    <main class="container-fluid py-4 px-3 px-md-4">

        <!-- 1. ONGLET HÔTES / RADIUS INTERFACE (LECTURE SEULE) -->
        <div id="hosts-content" class="content-section active">
            <iframe
                src="radius_interface_admin.php?role_lib=<?php echo $user_role_id; ?>" 
                class="glass-iframe">
                Chargement de l'interface RADIUS...
            </iframe>
        </div>

        <!-- 2. ONGLET ACCÈS ÉTRANGERS (HISTORIQUE VISITEURS SEULEMENT) -->
        <div id="strangers-content" class="content-section">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-white"><i class="fas fa-history text-warning me-2"></i>Historique des Accès Étrangers</h2>
                <p class="text-muted">Consultation en lecture seule des connexions visiteurs enregistrées sur le réseau.</p>
            </div>

            <div class="card-custom">
                <div class="card-custom-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-search me-2"></i> Filtrer les connexions par date</span>
                    <small class="text-muted">Données radacct</small>
                </div>
                <div class="card-custom-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="startDate" class="form-label text-muted small">Date de début</label>
                            <input type="date" class="form-control" id="startDate">
                        </div>
                        <div class="col-md-4">
                            <label for="endDate" class="form-label text-muted small">Date de fin</label>
                            <input type="date" class="form-control" id="endDate">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-warning w-100 fw-bold" id="filter-btn" style="border-radius: 12px; height: 42px; background: var(--gold-primary); border: none; color: #000;">
                                <i class="fas fa-filter me-1"></i> Filtrer
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
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
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement de l'historique...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. ONGLET GESTIONNAIRE DE COMPTE (LECTURE RESTREINTE) -->
        <div id="manager-content" class="content-section">
            <div class="text-center py-5">
                <div class="spinner-border text-warning mb-3" role="status"></div>
                <h4>Chargement du Gestionnaire de Comptes...</h4>
            </div>
        </div>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="managerUser.js?v=20260721"></script>

    <script>
    $(document).ready(function() {
        loadHistory();
        $("#filter-btn").on('click', loadHistory);
    });

    function confirmLogout() {
        if (confirm("⚠️ Êtes-vous sûr de vouloir vous déconnecter du tableau de bord ?")) {
            window.location.href = "logout.php";
        }
    }

    function switchTab(tabName) {
        const sections = document.querySelectorAll('.content-section');
        sections.forEach(section => section.classList.remove('active'));
        
        const tabs = document.querySelectorAll('.nav-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        
        const targetSection = document.getElementById(tabName + '-content');
        if (targetSection) {
            targetSection.classList.add('active');
        }
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('active');
        }
    }

    function loadHistory() {
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        
        $('#history-table').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement des données...</td></tr>');
        
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
                    $('#history-table').html('<tr><td colspan="7" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#history-table').html('<tr><td colspan="7" class="text-center text-danger py-4">Une erreur de communication est survenue.</td></tr>');
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
                const statusClass = record.acctstoptime ? 'badge bg-secondary' : 'badge bg-success text-dark fw-bold';
                const timeLeft = calculateTimeLeft(record.acctstarttime, record.acctstoptime);
                
                html += `
                    <tr>
                        <td class="fw-semibold text-white">${record.username}</td>
                        <td><code>${record.callingstationid}</code></td>
                        <td>${record.framedipaddress || 'N/A'}</td>
                        <td>${record.acctstarttime}</td>
                        <td>${record.acctstoptime || 'En cours'}</td>
                        <td>${formatDuration(record.acctsessiontime)}</td>
                        <td>
                            <span class="${statusClass}">${status}</span>
                            ${status === 'Actif' ? `<br/><small class="text-warning">(${timeLeft} restants)</small>` : ''}
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
        if (remainingSeconds <= 0) return 'Expiré';
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