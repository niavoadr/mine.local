<?php
session_start();

// Informations de l'utilisateur connecté. Une session absente ne doit jamais afficher un nom par défaut.
$connected_username = trim((string) ($_SESSION['user'] ?? ($_SESSION['nom_utilisateur'] ?? '')));
if ($connected_username === '') {
  header('Location: login.php');
  exit();
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
                    <span class="fw-semibold"><?php echo htmlspecialchars(
                      $connected_username,
                      ENT_QUOTES,
                      'UTF-8',
                    ); ?></span>
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
                src="radius_interface_user.php" 
                class="glass-iframe">
                Chargement de l'interface RADIUS...
            </iframe>
        </div>

        <!-- 2. ONGLET ACCÈS ÉTRANGERS -->
        <div id="strangers-content" class="content-section">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-white"><i class="fas fa-user-shield text-warning me-2"></i>Gestion des Accès Visiteurs</h2>
                <p class="text-muted">Créez des accès temporaires pour les visiteurs et consultez l'historique.</p>
            </div>

            <div class="card-custom mb-4">
                <div class="card-custom-header">
                    <i class="fas fa-user-plus me-2"></i> Créer un Accès Visiteur
                </div>
                <div class="card-custom-body">
                    <form id="create-visitor-form">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="visitor-username" class="form-label text-muted small">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="visitor-username" placeholder="ex: visiteur_jean" required>
                            </div>
                            <div class="col-md-5">
                                <label for="visitor-duration" class="form-label text-muted small">Durée de la session (minutes)</label>
                                <input type="number" class="form-control" id="visitor-duration" placeholder="ex: 60" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-warning w-100 fw-bold" style="border-radius: 12px; height: 42px;">
                                    <i class="fas fa-plus me-1"></i> Créer
                                </button>
                            </div>
                        </div>
                    </form>
                    <div id="visitor-credentials" class="mt-3 d-none">
                        <div class="alert alert-success">
                            <strong>Visiteur créé !</strong><br>
                            Identifiant : <span id="res-username" class="fw-bold"></span><br>
                            Mot de passe : <span id="res-password" class="fw-bold text-danger"></span><br>
                            Expire le : <span id="res-expires" class="fw-bold"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-custom-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-history me-2"></i> Liste des Visiteurs</span>
                    <small class="text-muted">Données combinées visitor & radacct</small>
                </div>
                <div class="card-custom-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Visiteur</th>
                                    <th>MAC</th>
                                    <th>IP</th>
                                    <th>Créé par</th>
                                    <th>Début</th>
                                    <th>Fin</th>
                                    <th>Durée</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody id="visitor-table-body">
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement des visiteurs...
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
            <?php include __DIR__ . '/managerUser.php'; ?>
        </div>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        loadVisitors();
        
        setInterval(function() {
            if ($('#strangers-content').hasClass('active')) {
                loadVisitors();
            }
        }, 10000);
        
        $("#create-visitor-form").on('submit', function(e) {
            e.preventDefault();
            createVisitor();
        });
    });

    function confirmLogout() {
        if (confirm("⚠️ Êtes-vous sûr de vouloir vous déconnecter du tableau de bord ?")) {
            window.location.href = "logout.php";
        }
    }

    function switchTab(tabName) {
        // Masquer le résumé des identifiants visiteur lors du changement d'onglet
        $('#visitor-credentials').addClass('d-none');

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
        
        if (tabName === 'strangers') {
            loadVisitors();
        }
    }

    // ==================== VISITOR FUNCTIONS ====================
    function createVisitor() {
        const username = $('#visitor-username').val();
        const duration = $('#visitor-duration').val();
        
        $.ajax({
            url: 'visitor_manager.php',
            type: 'POST',
            data: {
                action: 'create_visitor',
                username: username,
                duration: duration
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#res-username').text(response.data.username);
                    $('#res-password').text(response.data.password);
                    $('#res-expires').text(response.data.expires_at);
                    $('#visitor-credentials').removeClass('d-none');
                    $('#create-visitor-form')[0].reset();
                    loadVisitors();
                } else {
                    alert('Erreur: ' + response.message);
                }
            },
            error: function() {
                alert('Une erreur est survenue lors de la création du visiteur.');
            }
        });
    }

    function loadVisitors() {
        $.ajax({
            url: 'visitor_manager.php',
            type: 'POST',
            data: { action: 'get_visitors' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayVisitors(response.data);
                } else {
                    $('#visitor-table-body').html('<tr><td colspan="8" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#visitor-table-body').html('<tr><td colspan="8" class="text-center text-danger py-4">Une erreur de communication est survenue.</td></tr>');
            }
        });
    }
    
    function displayVisitors(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucun visiteur enregistré.</td></tr>';
        } else {
            records.forEach(function(record) {
                const statusClass = record.status === 'active' ? 'badge bg-success text-dark fw-bold' : 'badge bg-danger';
                const statusLabel = record.status === 'active' ? 'Actif' : 'Expiré';
                
                html += `
                    <tr>
                        <td class="fw-semibold text-white">${record.username}</td>
                        <td><code>${record.mac_address}</code></td>
                        <td>${record.ip_address}</td>
                        <td><small class="text-muted">${record.creator_name}</small></td>
                        <td>${record.display_start}</td>
                        <td>${record.display_end}</td>
                        <td>${record.display_duration}</td>
                        <td><span class="${statusClass}">${statusLabel}</span></td>
                    </tr>
                `;
            });
        }
        $('#visitor-table-body').html(html);
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