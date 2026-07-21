// js/managerAdmin.js - Script AJAX pour le Gestionnaire de Compte (Mode Lecture Restreinte)
$(document).ready(function() {
    initializeManager();
    $('.nav-tab').on('click', function() {
        if ($(this).text().includes('Gestionnaire de Compte')) {
            setTimeout(initializeManager, 100);
        }
    });
});

function initializeManager() {
    if (!$('#manager-content').hasClass('active')) {
        return;
    }
    updateManagerHTML();
    loadManagerData();
    $('#ajax-btn-refresh').off('click').on('click', function() {
        loadUsers();
    });
    setupAutoRefresh();
}

function updateManagerHTML() {
    const managerContent = $('#manager-content');
    managerContent.html(`
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="section-title mb-1"><i class="fa-solid fa-users-gear text-warning me-2"></i>Gestionnaire de Comptes & Habilitations</h2>
                <p class="text-muted mb-0">Consultation en lecture restreinte des comptes agents et rôles du Ministère.</p>
            </div>
            <button type="button" class="btn btn-outline-warning fw-semibold px-4 py-2" id="ajax-btn-refresh" style="border-radius: 12px;">
                <i class="fa-solid fa-rotate-right me-1"></i> Actualiser le répertoire
            </button>
        </div>

        <!-- Alertes -->
        <div id="alert-success" class="alert custom-alert-success" style="display: none;">
            <i class="fa-solid fa-circle-check fs-5"></i>
            <div><strong>Succès :</strong> <span id="success-message"></span></div>
        </div>
        <div id="alert-error" class="alert custom-alert-error" style="display: none;">
            <i class="fa-solid fa-circle-exclamation fs-5"></i>
            <div><strong>Erreur :</strong> <span id="error-message"></span></div>
        </div>

        <!-- Statistiques -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total Utilisateurs</div>
                            <div class="stat-number" id="stat-total-users">-</div>
                        </div>
                        <div class="stat-icon-box bg-warning-subtle text-warning"><i class="fa-solid fa-users fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Utilisateurs Actifs</div>
                            <div class="stat-number" id="stat-active-users">-</div>
                        </div>
                        <div class="stat-icon-box bg-success-subtle text-success"><i class="fa-solid fa-user-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Rôles et Habilitations</div>
                            <div class="stat-number" id="stat-total-roles">-</div>
                        </div>
                        <div class="stat-icon-box bg-info-subtle text-info"><i class="fa-solid fa-shield-halved fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="card-custom">
            <div class="card-custom-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="fa-solid fa-table-list me-2"></i> Répertoire des comptes utilisateurs de l'organisation</span>
                <span class="badge bg-warning text-dark"><i class="fa-solid fa-lock me-1"></i> Lecture Seule</span>
            </div>
            <div class="card-custom-body p-0">
                <div id="ajax-users-loading" class="text-center py-5 text-warning">
                    <div class="spinner-border mb-2" role="status"></div>
                    <div>Chargement du répertoire des agents...</div>
                </div>
                <div id="ajax-users-container" class="table-responsive border-0 mb-0">
                    <!-- Table chargée par AJAX -->
                </div>
            </div>
        </div>
    `);

    // Ajouter les styles CSS modernes pour AJAX (remplace les anciens styles blancs)
    $('#ajax-styles').remove();
    $('head').append(`
        <style id="ajax-styles">
            .custom-alert-success {
                background: rgba(16, 185, 129, 0.15) !important;
                border: 1px solid rgba(16, 185, 129, 0.4) !important;
                color: #10b981 !important;
                padding: 1rem 1.25rem;
                border-radius: 14px;
                margin-bottom: 1.5rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            .custom-alert-error {
                background: rgba(239, 68, 68, 0.15) !important;
                border: 1px solid rgba(239, 68, 68, 0.4) !important;
                color: #f87171 !important;
                padding: 1rem 1.25rem;
                border-radius: 14px;
                margin-bottom: 1.5rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            .stat-icon-box {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .stat-card {
                background: rgba(24, 24, 30, 0.85) !important;
                border: 1px solid rgba(218, 165, 32, 0.25) !important;
                border-radius: 18px !important;
                padding: 1.5rem !important;
                box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important;
                transition: transform 0.3s ease, border-color 0.3s ease !important;
            }
            .stat-card:hover {
                transform: translateY(-3px) !important;
                border-color: #DAA520 !important;
            }
            .stat-number {
                color: #FFD700 !important;
                font-size: 2.2rem !important;
                font-weight: 700 !important;
            }
            .stat-label {
                color: #9ca3af !important;
                font-size: 0.82rem !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .ajax-users-table {
                width: 100%;
                border-collapse: separate !important;
                border-spacing: 0 !important;
                background: transparent !important;
                color: #e5e7eb !important;
                margin: 0 !important;
            }
            .ajax-users-table th {
                background: rgba(218, 165, 32, 0.15) !important;
                color: #FFD700 !important;
                padding: 15px 18px !important;
                text-align: left !important;
                font-weight: 600 !important;
                text-transform: uppercase;
                font-size: 0.8rem;
                letter-spacing: 0.5px;
                border-bottom: 1px solid rgba(218, 165, 32, 0.3) !important;
            }
            .ajax-users-table td {
                padding: 15px 18px !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
                vertical-align: middle !important;
                color: #e5e7eb !important;
            }
            .ajax-users-table tr:hover td {
                background: rgba(218, 165, 32, 0.08) !important;
            }
            .ajax-fade-in {
                animation: ajaxFadeIn 0.4s ease;
            }
            @keyframes ajaxFadeIn {
                from { opacity: 0; transform: translateY(8px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .status-actif {
                background: rgba(16, 185, 129, 0.2);
                color: #10b981;
                border: 1px solid rgba(16, 185, 129, 0.4);
                padding: 0.35rem 0.8rem;
                border-radius: 50px;
                font-weight: 600;
                font-size: 0.78rem;
                display: inline-block;
            }
            .status-suspendu {
                background: rgba(239, 68, 68, 0.2);
                color: #f87171;
                border: 1px solid rgba(239, 68, 68, 0.4);
                padding: 0.35rem 0.8rem;
                border-radius: 50px;
                font-weight: 600;
                font-size: 0.78rem;
                display: inline-block;
            }
            .status-en_attente {
                background: rgba(245, 158, 11, 0.2);
                color: #fbbf24;
                border: 1px solid rgba(245, 158, 11, 0.4);
                padding: 0.35rem 0.8rem;
                border-radius: 50px;
                font-weight: 600;
                font-size: 0.78rem;
                display: inline-block;
            }
        </style>
    `);
}

function loadManagerData() {
    loadStats();
    loadUsers();
}

function setupAutoRefresh() {
    if (window.managerRefreshTimer) {
        clearInterval(window.managerRefreshTimer);
    }
    window.managerRefreshTimer = setInterval(function() {
        if ($('#manager-content').hasClass('active')) {
            loadStats();
            loadUsers();
        }
    }, 30000);
}

function loadStats() {
    $.ajax({
        url: 'manager.php',
        method: 'POST',
        data: { action: 'get_stats' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                animateNumber('#stat-total-users', response.data.total_users);
                animateNumber('#stat-active-users', response.data.active_users);
                animateNumber('#stat-total-roles', response.data.total_roles);
            }
        },
        error: function() {
            console.error('Erreur lors du chargement des statistiques');
        }
    });
}

function loadUsers() {
    $('#ajax-users-loading').show();
    $('#ajax-users-container').hide();
    
    $.ajax({
        url: 'manager.php',
        method: 'POST',
        data: { action: 'get_users' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let tableHTML = `
                    <table class="ajax-users-table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Email</th>
                                <th>Département</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                response.data.forEach(function(user) {
                    const statusClass = `status-${user.statut}`;
                    const statusText = user.statut.charAt(0).toUpperCase() + user.statut.slice(1);
                    
                    tableHTML += `
                        <tr class="ajax-fade-in">
                            <td class="fw-semibold text-white">${user.nom_utilisateur}</td>
                            <td>${user.email}</td>
                            <td>${user.nom_departement || 'N/A'}</td>
                            <td><span class="badge bg-dark border border-secondary">${user.nom_role || 'N/A'}</span></td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                        </tr>
                    `;
                });
                
                tableHTML += `
                        </tbody>
                    </table>
                `;
                
                $('#ajax-users-container').html(tableHTML);
                $('#ajax-users-loading').hide();
                $('#ajax-users-container').show();
            }
        },
        error: function() {
            $('#ajax-users-loading').hide();
            $('#ajax-users-container').show();
            showError('Erreur lors du chargement des utilisateurs');
        }
    });
}

function showSuccess(message) {
    $('#success-message').text(message);
    $('#alert-success').fadeIn().delay(5000).fadeOut();
}

function showError(message) {
    $('#error-message').text(message);
    $('#alert-error').fadeIn().delay(5000).fadeOut();
}

function animateNumber(selector, finalNumber) {
    const element = $(selector);
    const current = parseInt(element.text()) || 0;
    
    if (current !== finalNumber) {
        $({ counter: current }).animate({ counter: finalNumber }, {
            duration: 1000,
            easing: 'swing',
            step: function() {
                element.text(Math.floor(this.counter));
            },
            complete: function() {
                element.text(finalNumber);
            }
        });
    }
}
