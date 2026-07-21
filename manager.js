// js/manager.js - Script AJAX pour la section Gestionnaire de Compte (Mode Administrateur Actif)
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
    setupManagerEvents();
    setupAutoRefresh();
}

function updateManagerHTML() {
    const managerContent = $('#manager-content');
    managerContent.html(`
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h2 class="section-title mb-1"><i class="fa-solid fa-users-gear text-warning me-2"></i>Gestionnaire de Comptes & Habilitations</h2>
                <p class="text-muted mb-0">Création, suspension et affectation des rôles d'accès des agents du Ministère.</p>
            </div>
            <button type="button" class="btn btn-outline-warning fw-semibold px-4 py-2" id="ajax-btn-refresh" style="border-radius: 12px;">
                <i class="fa-solid fa-rotate-right me-1"></i> Actualiser la liste
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

        <div class="row g-4">
            <!-- Formulaire de création -->
            <div class="col-xl-4">
                <div class="card-custom h-100">
                    <div class="card-custom-header">
                        <i class="fa-solid fa-user-plus me-2"></i> Créer un nouveau compte agent
                    </div>
                    <div class="card-custom-body">
                        <form id="ajax-user-form">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Nom d'utilisateur</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-regular fa-user"></i></span>
                                    <input type="text" id="ajax_nom_utilisateur" name="nom_utilisateur" class="form-control border-start-0 ps-0" required placeholder="Ex: j.dupont">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Adresse Email professionnelle</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-regular fa-envelope"></i></span>
                                    <input type="email" id="ajax_email" name="email" class="form-control border-start-0 ps-0" required placeholder="agent@mines.gov.mg">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Mot de passe provisoire</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" id="ajax_mot_de_passe" name="mot_de_passe" class="form-control border-start-0 ps-0" required placeholder="Mot de passe sécurisé (min. 6 car.)">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Département d'affectation</label>
                                <select id="ajax_id_departement" name="id_departement" class="form-select" required>
                                    <option value="">Chargement...</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-muted small">Rôle d'administration</label>
                                <select id="ajax_id_role" name="id_role" class="form-select" required>
                                    <option value="">Chargement...</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 fw-bold py-3" id="ajax-btn-create" style="border-radius: 12px; background: var(--gold-primary); border: none; color: #000; box-shadow: 0 4px 15px rgba(218,165,32,0.3);">
                                <span class="ajax-spinner me-2" style="display: none;"><i class="fa-solid fa-spinner fa-spin"></i></span>
                                <i class="fa-solid fa-check me-1"></i> Créer et activer le compte
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="col-xl-8">
                <div class="card-custom h-100">
                    <div class="card-custom-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="fa-solid fa-table-list me-2"></i> Répertoire des comptes utilisateurs de l'organisation</span>
                        <span class="badge bg-warning text-dark">Temps Réel</span>
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
    loadDepartements();
    loadRoles();
    loadUsers();
}

function setupManagerEvents() {
    $('#ajax-user-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        createUser();
    });

    $('#ajax-btn-refresh').off('click').on('click', function() {
        loadUsers();
    });

    $(document).off('click', '.ajax-btn-status').on('click', '.ajax-btn-status', function() {
        const userId = $(this).data('user-id');
        const newStatus = $(this).data('new-status');
        updateUserStatus(userId, newStatus, $(this));
    });
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

function loadDepartements() {
    $.ajax({
        url: 'manager.php',
        method: 'POST',
        data: { action: 'get_departements' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const select = $('#ajax_id_departement');
                select.html('<option value="">Sélectionnez un département</option>');
                response.data.forEach(function(dept) {
                    select.append(`<option value="${dept.id}">${dept.nom}</option>`);
                });
            }
        },
        error: function() {
            showError('Erreur lors du chargement des départements');
        }
    });
}

function loadRoles() {
    $.ajax({
        url: 'manager.php',
        method: 'POST',
        data: { action: 'get_roles' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const select = $('#ajax_id_role');
                select.html('<option value="">Sélectionnez un rôle</option>');
                response.data.forEach(function(role) {
                    select.append(`<option value="${role.id}">${role.nom}</option>`);
                });
            }
        },
        error: function() {
            showError('Erreur lors du chargement des rôles');
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
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                response.data.forEach(function(user) {
                    const statusClass = `status-${user.statut}`;
                    const statusText = user.statut.charAt(0).toUpperCase() + user.statut.slice(1);
                    
                    let actionButton = '';
                    if (user.statut === 'actif') {
                        actionButton = `<button class="btn btn-sm btn-outline-warning fw-semibold ajax-btn-status" data-user-id="${user.id}" data-new-status="suspendu" style="border-radius: 8px;"><i class="fa-solid fa-pause me-1"></i>Suspendre</button>`;
                    } else if (user.statut === 'suspendu') {
                        actionButton = `<button class="btn btn-sm btn-outline-success fw-semibold ajax-btn-status" data-user-id="${user.id}" data-new-status="actif" style="border-radius: 8px;"><i class="fa-solid fa-play me-1"></i>Activer</button>`;
                    } else {
                        actionButton = `<button class="btn btn-sm btn-outline-info fw-semibold ajax-btn-status" data-user-id="${user.id}" data-new-status="actif" style="border-radius: 8px;"><i class="fa-solid fa-check me-1"></i>Approuver</button>`;
                    }
                    
                    tableHTML += `
                        <tr class="ajax-fade-in">
                            <td class="fw-semibold text-white">${user.nom_utilisateur}</td>
                            <td>${user.email}</td>
                            <td>${user.nom_departement || 'N/A'}</td>
                            <td><span class="badge bg-dark border border-secondary">${user.nom_role || 'N/A'}</span></td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                            <td class="text-end">${actionButton}</td>
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

function createUser() {
    const btn = $('#ajax-btn-create');
    const spinner = btn.find('.ajax-spinner');
    
    btn.prop('disabled', true);
    spinner.show();
    
    const formData = {
        action: 'create_user',
        nom_utilisateur: $('#ajax_nom_utilisateur').val(),
        email: $('#ajax_email').val(),
        mot_de_passe: $('#ajax_mot_de_passe').val(),
        id_departement: $('#ajax_id_departement').val(),
        id_role: $('#ajax_id_role').val()
    };

    $.ajax({
        url: 'manager.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                $('#ajax-user-form')[0].reset();
                loadStats();
                loadUsers();
            } else {
                showError(response.message);
            }
        },
        error: function() {
            showError('Erreur lors de la création de l\'utilisateur');
        },
        complete: function() {
            btn.prop('disabled', false);
            spinner.hide();
        }
    });
}

function updateUserStatus(userId, newStatus, button) {
    const originalText = button.html();
    button.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');
    
    $.ajax({
        url: 'manager.php',
        method: 'POST',
        data: {
            action: 'update_status',
            user_id: userId,
            new_status: newStatus
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showSuccess(response.message);
                loadStats();
                loadUsers();
            } else {
                showError(response.message);
                button.prop('disabled', false).html(originalText);
            }
        },
        error: function() {
            showError('Erreur lors de la mise à jour du statut');
            button.prop('disabled', false).html(originalText);
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
