<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$connected_username = trim((string) ($_SESSION['user'] ?? ($_SESSION['nom_utilisateur'] ?? '')));
if ($connected_username === '') {
  header('Location: login.php');
  exit();
}

// C2 : accès réservé aux administrateurs
if (($_SESSION['role'] ?? '') !== 'ADMIN') {
  header('Location: dashboard_user.php');
  exit();
}

$user_role_id = $_SESSION['role_lib'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RADIUS Dashboard - Tableau de Bord Ministère des Mines</title>
    
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
            background: #10b981;
            border-radius: 50%;
            margin-right: 0.5rem;
            box-shadow: 0 0 8px #10b981;
            animation: pulseGreen 2s infinite;
        }

        @keyframes pulseGreen {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.15); }
        }

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

        .subsection-nav {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .subsection-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #d1d5db;
            padding: 0.6rem 1.4rem;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .subsection-btn:hover {
            border-color: var(--gold-primary);
            color: var(--gold-primary);
        }

        .subsection-btn.active {
            background: rgba(218, 165, 32, 0.2);
            border-color: var(--gold-primary);
            color: #FFD700;
            box-shadow: 0 4px 15px rgba(218, 165, 32, 0.2);
        }

        .subsection-content {
            display: none;
        }

        .subsection-content.active {
            display: block;
            animation: fadeInTab 0.3s ease-out;
        }

        .stats-card {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            margin-bottom: 1.5rem;
        }

        .stats-card.danger { border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.08); }
        .stats-card.warning { border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.08); }

        .stats-card h5 { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; }
        .stats-card h2 { font-size: 2.2rem; font-weight: 700; color: #fff; margin: 0; }

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

        .form-control, .form-select {
            background: rgba(18, 18, 22, 0.9) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 12px !important;
            color: #ffffff !important;
            padding: 0.65rem 1rem !important;
        }

        .form-control:focus, .form-select:focus {
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

        .log-console {
            background: #08080a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.25rem;
            font-family: 'Plus Jakarta Sans', monospace;
            font-size: 0.88rem;
            line-height: 1.7;
            max-height: 650px;
            overflow-y: auto;
            color: #10b981;
        }
    </style>
    <link rel="stylesheet" href="css/responsive.css?v=20260722">
    <link rel="stylesheet" href="css/animations.css?v=20260721">
    <link rel="stylesheet" href="css/brockmann.css?v=20260817">
</head>
<body>

    <header class="dashboard-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="header-logo-box">
                    <img src="images/logomine.jpg" alt="Logo Ministère des Mines">
                </div>
                <div>
                    <h1 class="header-title">Tableau de Bord Global</h1>
                    <span class="header-subtitle">Plateforme Centrale d'Administration Réseau & RADIUS</span>
                </div>
            </div>

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
                <button class="nav-tab" onclick="switchTab('alerts')">
                    <i class="fa-solid fa-triangle-exclamation"></i> <span>Alertes</span>
                </button>
            </nav>

            <div class="d-flex align-items-center gap-3">
                <div class="user-profile-pill d-none d-lg-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-shield text-warning"></i>
                    <span class="fw-semibold"><?php echo htmlspecialchars(
                      $connected_username,
                      ENT_QUOTES,
                      'UTF-8',
                    ); ?></span>
                    <span class="badge bg-warning text-dark ms-1"><?php echo htmlspecialchars($_SESSION['role_lib'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <button onclick="confirmLogout()" class="btn-logout-modern" title="Déconnexion">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span class="d-none d-sm-inline">Déconnexion</span>
                </button>
            </div>
        </div>
    </header>

    <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>';</script>

    <main class="container-fluid py-4 px-3 px-md-4">

        <div id="hosts-content" class="content-section active">
            <iframe
                src="radius_interface_admin.php" 
                class="glass-iframe">
                Chargement de l'interface RADIUS...
            </iframe>
        </div>

        <div id="strangers-content" class="content-section">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-white"><i class="fas fa-user-shield text-warning me-2"></i>Gestion des Accès Étrangers & Surveillance</h2>
                <p class="text-muted">Historique des visiteurs, liste noire d'appareils bloqués et détection d'intrusions en temps réel.</p>
            </div>

            <div class="subsection-nav">
                <button class="subsection-btn active" data-section="visitor">
                    <i class="fas fa-users"></i> Visiteurs
                </button>
                <button class="subsection-btn" data-section="blacklist">
                    <i class="fas fa-ban"></i> Liste Noire
                </button>
                <button class="subsection-btn" data-section="intrusion">
                    <i class="fas fa-exclamation-triangle"></i> Intrusions
                </button>
            </div>

            <div class="subsection-content active" id="visitor-section">
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
                                    <label for="visitor-duration" class="form-label text-muted small">Durée de la session</label>
                                    <select class="form-select" id="visitor-duration" required>
                                        <option value="">Choisir une durée...</option>
                                        <option value="10">10 minutes</option>
                                        <option value="30">30 minutes</option>
                                        <option value="60">1 heure</option>
                                        <option value="120">2 heures</option>
                                    </select>
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
                        <span><i class="fas fa-history me-2"></i> Liste des Visiteurs Enregistrés</span>
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
                                        <th>Date d'expiration</th>
                                        <th>Durée</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="visitor-table-body">
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement des visiteurs...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="subsection-content" id="blacklist-section">
                <div class="row mb-4 g-3">
                    <div class="col-md-6">
                        <div class="stats-card danger">
                            <h5><i class="fas fa-ban me-2"></i> Appareils Bloqués</h5>
                            <h2 id="blocked-count">0</h2>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card warning">
                            <h5><i class="fas fa-clock me-2"></i> Bloqués Aujourd'hui</h5>
                            <h2 id="blocked-today">0</h2>
                        </div>
                    </div>
                </div>

                <div class="card-custom mb-4">
                    <div class="card-custom-header">
                        <i class="fas fa-plus-circle me-2"></i> Bloquer manuellement une adresse MAC
                    </div>
                    <div class="card-custom-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="blacklist-mac" class="form-label text-muted small">Adresse MAC</label>
                                <input type="text" class="form-control" id="blacklist-mac" placeholder="Ex: XX-XX-XX-XX-XX-XX">
                            </div>
                            <div class="col-md-5">
                                <label for="blacklist-reason" class="form-label text-muted small">Raison du blocage</label>
                                <select class="form-select" id="blacklist-reason">
                                    <option value="">Sélectionner une raison...</option>
                                    <option value="abuse">Abus de connexion et bande passante</option>
                                    <option value="security">Menace ou tentative de sécurité</option>
                                    <option value="policy">Violation de la politique interne</option>
                                    <option value="malware">Activité suspecte / malveillante</option>
                                    <option value="other">Autre raison</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-danger w-100 fw-bold" id="add-blacklist-btn" style="border-radius: 12px; height: 42px;">
                                    <i class="fas fa-ban me-1"></i> Bloquer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Adresse MAC</th>
                                <th>Adresse IP</th>
                                <th>Raison</th>
                                <th>Date de blocage</th>
                                <th>Tentatives bloquées</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="blacklist-table">
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement de la liste noire...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="subsection-content" id="intrusion-section">
                <div class="row mb-4 g-3">
                    <div class="col-md-4">
                        <div class="stats-card danger">
                            <h5><i class="fas fa-shield-alt me-2"></i> Alertes Critiques</h5>
                            <h2 id="critical-alerts">0</h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card warning">
                            <h5><i class="fas fa-exclamation-circle me-2"></i> Alertes Moyennes</h5>
                            <h2 id="medium-alerts">0</h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5><i class="fas fa-eye me-2"></i> Alertes Faibles</h5>
                            <h2 id="low-alerts">0</h2>
                        </div>
                    </div>
                </div>

                <div class="card-custom mb-4">
                    <div class="card-custom-header">
                        <i class="fas fa-filter me-2"></i> Filtres et critères de détection d'intrusions
                    </div>
                    <div class="card-custom-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="intrusion-severity" class="form-label text-muted small">Sévérité</label>
                                <select class="form-select" id="intrusion-severity">
                                    <option value="">Toutes</option>
                                    <option value="critical">Critique</option>
                                    <option value="medium">Moyenne</option>
                                    <option value="low">Faible</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="intrusion-type" class="form-label text-muted small">Type d'Intrusion</label>
                                <select class="form-select" id="intrusion-type">
                                    <option value="">Tous</option>
                                    <option value="brute_force">Force brute</option>
                                    <option value="unauthorized">Accès non autorisé</option>
                                    <option value="spoofing">Usurpation MAC/IP</option>
                                    <option value="dos">Déni de service (DoS)</option>
                                    <option value="scan">Scan de réseau</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="intrusion-date" class="form-label text-muted small">Date exacte</label>
                                <input type="date" class="form-control" id="intrusion-date">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button class="btn btn-warning w-100 fw-bold" id="intrusion-filter-btn" style="border-radius: 12px; height: 42px; background: var(--gold-primary); border: none; color: #000;">
                                    <i class="fas fa-search me-1"></i> Rechercher
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date / Heure</th>
                                <th>Type d'Intrusion</th>
                                <th>Sévérité</th>
                                <th>Source (IP / MAC)</th>
                                <th>Description</th>
                                <th>Source Info</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="intrusion-table">
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement des détections...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="manager-content" class="content-section">
            <?php include __DIR__ . '/managerAdmin.php'; ?>
        </div>

        <div id="alerts-content" class="content-section">
            <div class="card-custom">
                <div class="card-custom-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-bell me-2"></i> Événements de Sécurité</span>
                    <button class="btn btn-sm btn-outline-light" onclick="loadSystemAlerts()" style="border-radius: 8px;">
                        <i class="fas fa-sync-alt me-1"></i> Actualiser
                    </button>
                </div>
                <div class="card-custom-body">
                    <div id="alerts-log-container">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-spinner fa-spin me-2 text-warning"></i> Chargement des alertes du serveur...
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $(document).ready(function() {
        $.ajaxSetup({ data: { csrf_token: window.CSRF_TOKEN } });

        loadVisitors();
        loadBlacklist();
        loadBlacklistStats();
        loadIntrusions();
        loadIntrusionStats();
        loadIntrusionTypes();

        $(document).on('click', '[data-unblock-mac]', function() {
            unblockDevice($(this).data('unblock-mac'));
        });
        $(document).on('click', '[data-block-mac]', function() {
            blockFromIntrusion($(this).data('block-mac'), $(this).data('block-type'));
        });

        setInterval(function() {
            if ($('#visitor-section').hasClass('active')) {
                loadVisitors();
            }
            if ($('#intrusion-section').hasClass('active')) {
                loadIntrusions();
                loadIntrusionStats();
            }
        }, 10000);
        
        $("#create-visitor-form").on('submit', function(e) {
            e.preventDefault();
            createVisitor();
        });
        $("#add-blacklist-btn").on('click', addToBlacklist);
        $("#intrusion-filter-btn").on('click', loadIntrusions);
        
        $('.subsection-btn').on('click', function() {
            $('#visitor-credentials').addClass('d-none');

            const section = $(this).data('section');
            $('.subsection-btn').removeClass('active');
            $(this).addClass('active');
            $('.subsection-content').removeClass('active');
            $(`#${section}-section`).addClass('active');
            
            if (section === 'visitor') {
                loadVisitors();
            } else if (section === 'blacklist') {
                loadBlacklist();
                loadBlacklistStats();
            } else if (section === 'intrusion') {
                loadIntrusions();
                loadIntrusionStats();
            }
        });
    });

    function confirmLogout() {
        if (confirm("Êtes-vous sûr de vouloir vous déconnecter du portail d'administration ?")) {
            window.location.href = "logout.php";
        }
    }

    function switchTab(tabName) {
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

        if (tabName === 'alerts') {
            loadSystemAlerts();
        }
        if (tabName === 'strangers') {
            loadVisitors();
        }
    }

    function loadSystemAlerts() {
        const container = document.getElementById('alerts-log-container');
        if (!container) return;
        container.innerHTML = '<div class="text-center py-4 text-warning"><i class="fa-solid fa-spinner fa-spin me-2"></i>Chargement des journaux de sécurité...</div>';
        fetch('get_alerts.php')
            .then(response => response.text())
            .then(data => {
                container.innerHTML = `<div class="log-console">${data || 'Aucune alerte récente.'}</div>`;
            })
            .catch(err => {
                container.innerHTML = `<div class="alert alert-danger">Erreur lors de la récupération des alertes: ${err}</div>`;
            });
    }

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
                $('#visitor-table-body').html('<tr><td colspan="7" class="text-center text-danger py-4">Une erreur de communication est survenue.</td></tr>');
            }
        });
    }
    
    function displayVisitors(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucun visiteur enregistré.</td></tr>';
        } else {
            records.forEach(function(record) {
                const statusClass = record.status === 'active' ? 'badge bg-success text-dark fw-bold' : 'badge bg-danger';
                const statusLabel = record.status === 'active' ? 'Actif' : 'Expiré';
                
                html += `
                    <tr>
                        <td class="fw-semibold text-white">${escapeHtml(record.username)}</td>
                        <td><code>${escapeHtml(record.mac_address)}</code></td>
                        <td>${escapeHtml(record.ip_address)}</td>
                        <td><small class="text-muted">${escapeHtml(record.creator_name)}</small></td>
                        <td>${escapeHtml(record.display_expires_at)}</td>
                        <td>${escapeHtml(record.display_duration)}</td>
                        <td><span class="${statusClass}">${statusLabel}</span></td>
                    </tr>
                `;
            });
        }
        $('#visitor-table-body').html(html);
    }

    function loadBlacklist() {
        $('#blacklist-table').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement de la liste noire...</td></tr>');
        
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: { action: 'get_blacklist' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayBlacklist(response.data);
                } else {
                    $('#blacklist-table').html('<tr><td colspan="6" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#blacklist-table').html('<tr><td colspan="6" class="text-center text-danger py-4">Une erreur de communication est survenue.</td></tr>');
            }
        });
    }

    function displayBlacklist(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucun appareil bloqué pour le moment.</td></tr>';
        } else {
            records.forEach(function(record) {
                html += `
                    <tr>
                        <td><code>${escapeHtml(record.mac_address)}</code></td>
                        <td>${escapeHtml(record.ip_address || 'N/A')}</td>
                        <td><span class="badge bg-danger px-3 py-2">${escapeHtml(record.reason)}</span></td>
                        <td>${escapeHtml(record.blocked_date)}</td>
                        <td><span class="badge bg-warning text-dark fw-bold">${escapeHtml(record.blocked_attempts || 0)}</span></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-success fw-semibold" data-unblock-mac="${escapeHtml(record.mac_address)}" style="border-radius: 8px;">
                                <i class="fas fa-unlock me-1"></i> Débloquer
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#blacklist-table').html(html);
    }

    function loadBlacklistStats() {
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: { action: 'get_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#blocked-count').text(response.data.total || 0);
                    $('#blocked-today').text(response.data.today || 0);
                }
            }
        });
    }

    function addToBlacklist(forceMac, forceReason) {
        const mac = (typeof forceMac === 'string' && forceMac) ? forceMac : $('#blacklist-mac').val();
        const reason = (typeof forceReason === 'string' && forceReason) ? forceReason : $('#blacklist-reason').val();
        
        if (!mac || !reason) {
            alert('Veuillez remplir tous les champs');
            return;
        }
        
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: {
                action: 'check_mac_status',
                mac_address: mac
            },
            dataType: 'json',
            success: function(check) {
                if (!check.success) {
                    alert('Erreur: ' + check.message);
                    return;
                }

                var status = check.data;

                if (status.is_blocked && status.in_blacklist) {
                    alert('Cet appareil est déjà dans la liste noire et bloqué sur le réseau.');
                    return;
                }

                if (status.is_authorized) {
                    var dept = status.department ? ' (' + status.department + ')' : '';
                    if (confirm('Cet appareil est déjà autorisé à accéder à internet' + dept + '.\n\nSi vous continuez, son accès sera supprimé et il sera bloqué sur le réseau.\n\nContinuer ?')) {
                        doAddBlacklist(mac, reason, true);
                    }
                    return;
                }

                doAddBlacklist(mac, reason, false);
            },
            error: function() {
                doAddBlacklist(mac, reason, false);
            }
        });
    }

    function doAddBlacklist(mac, reason, force) {
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: {
                action: 'add_blacklist',
                mac_address: mac,
                reason: reason,
                force: force ? '1' : '0'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Appareil ajouté à la liste noire et bloqué sur le réseau');
                    $('#blacklist-mac').val('');
                    $('#blacklist-reason').val('');
                    loadBlacklist();
                    loadBlacklistStats();
                } else if (response.message === 'APPAREIL_DEJA_AUTORISE') {
                    if (confirm('Cet appareil est déjà autorisé à accéder à internet.\n\nSi vous continuez, son accès sera supprimé et il sera bloqué sur le réseau.\n\nContinuer ?')) {
                        doAddBlacklist(mac, reason, true);
                    }
                } else {
                    alert('Erreur: ' + response.message);
                }
            }
        });
    }

    function unblockDevice(mac) {
        if (confirm('Voulez-vous vraiment débloquer cet appareil ?')) {
            $.ajax({
                url: 'blacklist.php',
                type: 'POST',
                data: {
                    action: 'remove_blacklist',
                    mac_address: mac
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Appareil débloqué avec succès');
                        loadBlacklist();
                        loadBlacklistStats();
                    } else {
                        alert('Erreur: ' + response.message);
                    }
                }
            });
        }
    }

    function loadIntrusions() {
        const severity = $('#intrusion-severity').val();
        const type = $('#intrusion-type').val();
        const date = $('#intrusion-date').val();
        
        $('#intrusion-table').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2 text-warning"></i>Chargement des intrusions...</td></tr>');
        
        $.ajax({
            url: 'intrusion.php',
            type: 'POST',
            data: {
                action: 'get_intrusions',
                severity: severity,
                type: type,
                date: date
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayIntrusions(response.data);
                } else {
                    $('#intrusion-table').html('<tr><td colspan="7" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#intrusion-table').html('<tr><td colspan="7" class="text-center text-danger py-4">Une erreur de communication est survenue. Vérifiez la configuration des logs.</td></tr>');
            }
        });
    }

    function displayIntrusions(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucune intrusion détectée.</td></tr>';
        } else {
            records.forEach(function(record) {
                const severityBadge = getSeverityBadge(record.severity);
                const sourceInfoBadge = getSourceInfoBadge(record.source_info);
                
                html += `
                    <tr>
                        <td>${escapeHtml(record.timestamp)}</td>
                        <td><span class="badge bg-info text-dark fw-semibold">${escapeHtml(record.type)}</span></td>
                        <td>${severityBadge}</td>
                        <td>
                            <small><strong>IP:</strong> ${escapeHtml(record.ip_address || 'N/A')}</small><br/>
                            <small><strong>MAC:</strong> <code>${escapeHtml(record.mac_address || 'N/A')}</code></small>
                        </td>
                        <td><small>${escapeHtml(record.description)}</small></td>
                        <td>${sourceInfoBadge}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-danger fw-semibold" data-block-mac="${escapeHtml(record.mac_address)}" data-block-type="${escapeHtml(record.type)}" style="border-radius: 8px;">
                                <i class="fas fa-ban me-1"></i> Bloquer
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#intrusion-table').html(html);
    }

    function loadIntrusionStats() {
        $.ajax({
            url: 'intrusion.php',
            type: 'POST',
            data: { action: 'get_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#critical-alerts').text(response.data.critical || 0);
                    $('#medium-alerts').text(response.data.medium || 0);
                    $('#low-alerts').text(response.data.low || 0);
                }
            }
        });
    }

    function loadIntrusionTypes() {
        $.ajax({
            url: 'intrusion.php',
            type: 'POST',
            data: { action: 'get_types' },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    const sel = $('#intrusion-type');
                    sel.find('option:not(:first)').remove();
                    response.data.forEach(function(t) {
                        sel.append($('<option>', { value: t, text: t }));
                    });
                }
            }
        });
    }

    function getSeverityBadge(severity) {
        const badges = {
            'critical': '<span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i> Critique</span>',
            'medium': '<span class="badge bg-warning text-dark fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Moyenne</span>',
            'low': '<span class="badge bg-info text-dark fw-bold"><i class="fas fa-info-circle me-1"></i> Faible</span>'
        };
        return badges[severity] || '<span class="badge bg-secondary">Inconnue</span>';
    }

    function getSourceInfoBadge(source) {
        const badges = {
            'Snort': '<span class="badge bg-primary"><i class="fas fa-shield-alt me-1"></i> Snort</span>',
            'Firewall': '<span class="badge bg-secondary"><i class="fas fa-fire me-1"></i> Firewall</span>',
            'Fail2ban': '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i> Fail2ban</span>',
            'Manual': '<span class="badge bg-dark border border-secondary"><i class="fas fa-user me-1"></i> Manuel</span>'
        };
        return badges[source] || '<span class="badge bg-info text-dark">Autre</span>';
    }

    function blockFromIntrusion(mac, type) {
        if (mac === 'N/A') {
            alert('Impossible de bloquer: adresse MAC non disponible');
            return;
        }

        var reason = 'Intrusion détectée: ' + type;

        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: {
                action: 'check_mac_status',
                mac_address: mac
            },
            dataType: 'json',
            success: function(check) {
                if (!check.success) {
                    alert('Erreur: ' + check.message);
                    return;
                }

                var status = check.data;

                if (status.is_blocked && status.in_blacklist) {
                    alert('Cet appareil est déjà dans la liste noire et bloqué sur le réseau.');
                    return;
                }

                if (status.is_authorized) {
                    var dept = status.department ? ' (' + status.department + ')' : '';
                    if (confirm('Cet appareil est déjà autorisé à accéder à internet' + dept + '.\n\nSi vous continuez, son accès sera supprimé et il sera bloqué suite à l\'intrusion.\n\nContinuer ?')) {
                        doBlockFromIntrusion(mac, reason, true);
                    }
                    return;
                }

                if (confirm('Voulez-vous bloquer cet appareil suite à cette intrusion ?')) {
                    doBlockFromIntrusion(mac, reason, false);
                }
            },
            error: function() {
                if (confirm('Voulez-vous bloquer cet appareil suite à cette intrusion ?')) {
                    doBlockFromIntrusion(mac, reason, false);
                }
            }
        });
    }

    function doBlockFromIntrusion(mac, reason, force) {
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: {
                action: 'add_blacklist',
                mac_address: mac,
                reason: reason,
                force: force ? '1' : '0'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Appareil bloqué avec succès');
                    loadIntrusions();
                    loadBlacklist();
                    loadBlacklistStats();
                } else if (response.message === 'APPAREIL_DEJA_AUTORISE') {
                    if (confirm('Cet appareil est déjà autorisé à accéder à internet.\n\nSi vous continuez, son accès sera supprimé et il sera bloqué.\n\nContinuer ?')) {
                        doBlockFromIntrusion(mac, reason, true);
                    }
                } else {
                    alert('Erreur: ' + response.message);
                }
            }
        });
    }
    </script>
</body>
</html>