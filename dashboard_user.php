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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/theme.css?v=20260817">
    <link rel="stylesheet" href="css/responsive.css?v=20260817">
    <link rel="stylesheet" href="css/animations.css?v=20260817">
</head>
<body>

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

            <div class="d-flex align-items-center gap-3">
                <div class="user-profile-pill d-none d-lg-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-shield text-warning"></i>
                    <span class="fw-semibold"><?php echo htmlspecialchars(
                      $connected_username,
                      ENT_QUOTES,
                      'UTF-8',
                    ); ?></span>
                    <span class="badge bg-warning text-dark ms-1"><?php echo htmlspecialchars($_SESSION['role_lib'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></span>
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
                src="radius_interface_user.php" 
                class="glass-iframe">
                Chargement de l'interface RADIUS...
            </iframe>
        </div>

        <div id="strangers-content" class="content-section">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-white"><i class="fas fa-user-shield text-warning me-2"></i>Gestion des Accès Visiteurs</h2>
                <p class="text-muted">Créez des accès temporaires pour les visiteurs et consultez l'historique.</p>
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

        <div id="manager-content" class="content-section">
            <?php include __DIR__ . '/managerUser.php'; ?>
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
        
        setInterval(function() {
            if ($('#strangers-content').hasClass('active')) {
                loadVisitors();
            }
        }, 10000);
    });

    function confirmLogout() {
        if (confirm("Êtes-vous sûr de vouloir vous déconnecter du tableau de bord ?")) {
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
        
        if (tabName === 'strangers') {
            loadVisitors();
        }
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
                    $('#visitor-table-body').html('<tr><td colspan="7" class="text-center text-danger py-4">Erreur: ' + response.message + '</td></tr>');
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
    </script>
</body>
</html>