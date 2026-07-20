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
    // Configurer les événements
    setupManagerEvents();

    // Actualisation automatique
    setupAutoRefresh();
}
function updateManagerHTML() {
    const managerContent = $('#manager-content');
    // Remplacer le contenu par la version AJAX
    managerContent.html(`
        <h2 class="section-title">Gestionnaire de Compte</h2>
        <!-- Alertes -->
        <div id="alert-success" class="alert alert-success" style="display: none;">
            <strong>Succès :</strong> <span id="success-message"></span>
        </div>
        <div id="alert-error" class="alert alert-error" style="display: none;">
            <strong>Erreur :</strong> <span id="error-message"></span>
        </div>
        <!-- Statistiques -->
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

        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; margin-top: 30px;">
            <!-- Formulaire de création -->
            <div>
                <h3>Créer un Nouveau Compte</h3>
                <form id="ajax-user-form">
                    <div class="form-group">
                        <label>Nom d'utilisateur</label>
                        <input type="text" id="ajax_nom_utilisateur" name="nom_utilisateur" required placeholder="Entrez le nom d'utilisateur">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="ajax_email" name="email" required placeholder="utilisateur@entreprise.com">
                    </div>
                    <div class="form-group">
                        <label>Mot de passe</label>
                        <input type="password" id="ajax_mot_de_passe" name="mot_de_passe" required placeholder="Mot de passe sécurisé">
                    </div>
                    <div class="form-group">
                        <label>Département</label>
                        <select id="ajax_id_departement" name="id_departement" required>
                            <option value="">Chargement...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rôle</label>
                        <select id="ajax_id_role" name="id_role" required>
                            <option value="">Chargement...</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" id="ajax-btn-create">
                        <span class="ajax-spinner" style="display: none;">⏳</span>
                        Créer le Compte
                    </button>

                </form>

            </div>

            <!-- Liste des utilisateurs -->

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

                    <!-- Les utilisateurs seront chargés ici -->


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


    loadDepartements();


    loadRoles();


    loadUsers();


}





function setupManagerEvents() {


    // Gestionnaire du formulaire de création


    $('#ajax-user-form').off('submit').on('submit', function(e) {


        e.preventDefault();


        createUser();


    });





    // Bouton actualiser


    $('#ajax-btn-refresh').off('click').on('click', function() {


        loadUsers();


    });





    // Gestionnaire des actions sur les utilisateurs (délégation d'événements)


    $(document).off('click', '.ajax-btn-status').on('click', '.ajax-btn-status', function() {


        const userId = $(this).data('user-id');


        const newStatus = $(this).data('new-status');


        updateUserStatus(userId, newStatus, $(this));


    });


}





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


                                <th>Actions</th>


                            </tr>


                        </thead>


                        <tbody>


                `;


                


                response.data.forEach(function(user) {


                    const statusClass = `status-${user.statut}`;


                    const statusText = user.statut.charAt(0).toUpperCase() + user.statut.slice(1);


                    


                    let actionButton = '';


                    if (user.statut === 'actif') {


                        actionButton = `<button class="btn btn-warning ajax-btn-status" data-user-id="${user.id}" data-new-status="suspendu" style="padding: 5px 10px; font-size: 0.8em;">Suspendre</button>`;


                    } else if (user.statut === 'suspendu') {


                        actionButton = `<button class="btn btn-success ajax-btn-status" data-user-id="${user.id}" data-new-status="actif" style="padding: 5px 10px; font-size: 0.8em;">Activer</button>`;


                    } else {


                        actionButton = `<button class="btn btn-success ajax-btn-status" data-user-id="${user.id}" data-new-status="actif" style="padding: 5px 10px; font-size: 0.8em;">Approuver</button>`;


                    }


                    


                    tableHTML += `


                        <tr class="ajax-fade-in">


                            <td>${user.nom_utilisateur}</td>


                            <td>${user.email}</td>


                            <td>${user.nom_departement || 'N/A'}</td>


                            <td>${user.nom_role || 'N/A'}</td>


                            <td><span class="${statusClass}">${statusText}</span></td>


                            <td>${actionButton}</td>


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


    


    // Désactiver le bouton et afficher le spinner


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


            // Réactiver le bouton et masquer le spinner


            btn.prop('disabled', false);


            spinner.hide();


        }


    });


}





function updateUserStatus(userId, newStatus, button) {


    const originalText = button.text();


    button.prop('disabled', true).text('...');


    


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


                button.prop('disabled', false).text(originalText);


            }


        },


        error: function() {


            showError('Erreur lors de la mise à jour du statut');


            button.prop('disabled', false).text(originalText);


        }


    });


}





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
