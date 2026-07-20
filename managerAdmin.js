// js/manager.js - Script AJAX pour la section Gestionnaire de Compte
$(document).ready(function() {
    // Initialisation uniquement quand on est sur l'onglet manager
    initializeManager();
    // Écouter les changements d'onglets
    $('.nav-tab').on('click', function() {
        if ($(this).text().includes('Gestionnaire de Compte')) {
            setTimeout(initializeManager, 100); // Petit délai pour laisser l'onglet s'activer
        }
    });
});

function initializeManager() {
    // Vérifier si on est sur l'onglet manager
    if (!$('#manager-content').hasClass('active')) {
        return;
    }
    // Modifier le HTML de la section manager pour AJAX
    updateManagerHTML();
    // Charger les données initiales
    loadManagerData();
    
    // Configurer l'événement du bouton Actualiser
    $('#ajax-btn-refresh').off('click').on('click', function() {
        loadUsers();
    });

    // Actualisation automatique
    setupAutoRefresh();
}

function updateManagerHTML() {
    const managerContent = $('#manager-content');
    // Remplacer le contenu par la version AJAX SANS le formulaire
    managerContent.html(`
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
    `);

    // Ajouter les styles CSS spécifiques pour AJAX
    if (!$('#ajax-styles').length) {
        $('head').append(`
            <style id="ajax-styles">
                .alert-error {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                    padding: 15px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }
                .ajax-spinner {
                    display: inline-block;
                    margin-right: 8px;
                }
                .ajax-users-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                }
                .ajax-users-table th {
                    background: #8B4513;
                    color: white;
                    padding: 15px;
                    text-align: left;
                    font-weight: 600;
                }
                .ajax-users-table td {
                    padding: 12px 15px;
                    border-bottom: 1px solid #e9ecef;
                }
                .ajax-users-table tr:hover {
                    background: #f8f9fa;
                }
                .ajax-fade-in {
                    animation: ajaxFadeIn 0.5s ease;
                }
                @keyframes ajaxFadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .btn:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }
            </style>
        `);
    }
}

function loadManagerData() {
    loadStats();
    // loadDepartements() et loadRoles() retirés
    loadUsers();
}

// setupManagerEvents() retiré, son unique événement (Actualiser) est dans initializeManager()

function setupAutoRefresh() {
    // Vérifier s'il n'y a pas déjà un timer en cours
    if (window.managerRefreshTimer) {
        clearInterval(window.managerRefreshTimer);
    }
    
    // Actualisation automatique toutes les 30 secondes
    window.managerRefreshTimer = setInterval(function() {
        if ($('#manager-content').hasClass('active')) {
            loadStats();
            loadUsers();
        }
    }, 30000);
}

// Fonctions AJAX

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

// loadDepartements() et loadRoles() sont retirés

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
                            <td>${user.nom_utilisateur}</td>
                            <td>${user.email}</td>
                            <td>${user.nom_departement || 'N/A'}</td>
                            <td>${user.nom_role || 'N/A'}</td>
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

// createUser() et updateUserStatus() sont retirés

// Fonctions utilitaires

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

// Nettoyer les timers quand on change d'onglet
$(document).on('click', '.nav-tab', function() {
    if (!$(this).text().includes('Gestionnaire de Compte')) {
        if (window.managerRefreshTimer) {
            clearInterval(window.managerRefreshTimer);
        }
    }
});