<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$connected_username = require_authenticated_user('login.php');
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
    <link rel="stylesheet" href="assets/css/pages/dashboard-admin.css?v=20260725">
    <link rel="stylesheet" href="assets/css/app/responsive.css?v=20260722">
    <link rel="stylesheet" href="assets/css/app/animations.css?v=20260721">
    <link rel="stylesheet" href="assets/css/components/account-manager-admin.css?v=20260725">
</head>
<body>

    <!-- BARRE DE STATUT MINISTÉRIELLE -->
    <div class="ministry-status-bar">
        <div class="d-flex align-items-center">
            <span class="status-indicator"></span>
            <span>SYSTÈME SÉCURISÉ | TABLEAU DE BORD ADMINISTRATION — MINISTÈRE DES MINES</span>
        </div>
    </div>

    <!-- EN-TÊTE ET NAVIGATION -->
    <header class="dashboard-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="header-logo-box">
                    <img src="assets/images/logomine.jpg" alt="Logo Ministère des Mines">
                </div>
                <div>
                    <h1 class="header-title">Tableau de Bord Global</h1>
                    <span class="header-subtitle">Plateforme Centrale d'Administration Réseau & RADIUS</span>
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
                <button class="nav-tab" onclick="switchTab('alerts')">
                    <i class="fa-solid fa-triangle-exclamation"></i> <span>Alertes</span>
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
                    <span class="badge bg-warning text-dark ms-1">Admin</span>
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

        <!-- 1. ONGLET HÔTES / RADIUS INTERFACE -->
        <div id="hosts-content" class="content-section active">
            <iframe
                src="radius/admin.php"
                class="glass-iframe">
                Chargement de l'interface RADIUS...
            </iframe>
        </div>

        <!-- 2. ONGLET ACCÈS ÉTRANGERS -->
        <div id="strangers-content" class="content-section">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-white"><i class="fas fa-user-shield text-warning me-2"></i>Gestion des Accès Étrangers & Surveillance</h2>
                <p class="text-muted">Historique des visiteurs, liste noire d'appareils bloqués et détection d'intrusions en temps réel.</p>
            </div>

            <!-- Navigation sous-sections -->
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

            <!-- VISITOR SUB-SECTION -->
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

            <!-- BLACKLIST SUB-SECTION -->
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

                <!-- Formulaire d'ajout à la liste noire -->
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

                <!-- Tableau Blacklist -->
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

            <!-- INTRUSION SUB-SECTION -->
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
                            <h5><i class="fas fa-eye me-2"></i> Tentatives Suspectes</h5>
                            <h2 id="suspicious-attempts">0</h2>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
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
                                    <option value="high">Élevée</option>
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

                <!-- Tableau Intrusions -->
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

        <!-- 3. ONGLET GESTIONNAIRE DE COMPTE -->
        <div id="manager-content" class="content-section">
            <?php include dirname(__DIR__) . '/app/Views/account-manager/admin.php'; ?>
        </div>

        <!-- 4. ONGLET ALERTES SÉCURITÉ -->
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
    <script src="assets/js/components/account-manager-admin.js?v=20260725"></script>
    <script src="assets/js/pages/dashboard-admin.js?v=20260725"></script>
</body>
</html>
