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
    <title>RADIUS Dashboard - Tableau de Bord Administration Restreinte</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pages/dashboard-user.css?v=20260725">
    <link rel="stylesheet" href="assets/css/app/responsive.css?v=20260722">
    <link rel="stylesheet" href="assets/css/app/animations.css?v=20260721">
    <link rel="stylesheet" href="assets/css/components/account-manager-user.css?v=20260725">
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
                    <img src="assets/images/logomine.jpg" alt="Logo Ministère des Mines">
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
                src="radius/user.php"
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
            <?php include dirname(__DIR__) . '/app/Views/account-manager/user.php'; ?>
        </div>

    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/components/account-manager-user.js?v=20260725"></script>
    <script src="assets/js/pages/dashboard-user.js?v=20260725"></script>
</body>
</html>
