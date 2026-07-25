// js/managerAdmin.js - Script AJAX pour la section Gestionnaire de Compte (Mode Administrateur Actif)
$(document).ready(function () {
  initializeManager();
  $('.nav-tab').on('click', function () {
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
            <!-- Formulaire de création stylisé -->
            <div class="col-xl-4 col-lg-5">
                <div class="user-form-card h-100">
                    <div class="user-form-header">
                        <div class="icon-circle"><i class="fa-solid fa-user-plus"></i></div>
                        <div>
                            <h4 class="mb-0 text-white fw-bold fs-6">Créer un Nouveau Compte</h4>
                            <span class="text-warning small" style="font-size: 0.75rem;">Habilitation Agent Ministère</span>
                        </div>
                    </div>
                    <div class="user-form-body">
                        <div class="form-intro"><i class="fa-solid fa-circle-info"></i><span>Renseignez les informations professionnelles du nouvel agent.</span></div>
                        <form id="ajax-user-form">
                            <div class="form-field-group mb-3">
                                <label class="form-label text-light fw-semibold small mb-2"><i class="fa-regular fa-user text-warning me-2"></i>Nom d'utilisateur</label>
                                <input type="text" id="ajax_username" name="username" class="custom-form-input" required placeholder="Ex: j.dupont" autocomplete="off">
                            </div>
                            <div class="form-field-group mb-3">
                                <label class="form-label text-light fw-semibold small mb-2"><i class="fa-regular fa-envelope text-warning me-2"></i>Adresse Email professionnelle</label>
                                <input type="email" id="ajax_email" name="email" class="custom-form-input" required placeholder="agent@mines.gov.mg" autocomplete="off">
                            </div>
                            <div class="form-field-group mb-3">
                                <label class="form-label text-light fw-semibold small mb-2"><i class="fa-solid fa-lock text-warning me-2"></i>Mot de passe provisoire</label>
                                <input type="password" id="ajax_password" name="password" class="custom-form-input" required placeholder="Mot de passe sécurisé (min. 6 car.)">
                            </div>
                            <div class="form-field-group mb-3">
                                <label class="form-label text-light fw-semibold small mb-2"><i class="fa-solid fa-building text-warning me-2"></i>Département d'affectation</label>
                                <select id="ajax_department" name="department" class="custom-form-select" required>
                                    <option value="">Chargement des départements...</option>
                                </select>
                            </div>
                            <div class="form-field-group mb-4">
                                <label class="form-label text-light fw-semibold small mb-2"><i class="fa-solid fa-user-shield text-warning me-2"></i>Rôle et Habilitation</label>
                                <select id="ajax_role" name="role" class="custom-form-select" required>
                                    <option value="">Chargement des rôles...</option>
                                </select>
                            </div>
                            <button type="submit" id="ajax-btn-create">
                                <span class="ajax-spinner me-2" style="display: none;"><i class="fa-solid fa-spinner fa-spin"></i></span>
                                <i class="fa-solid fa-circle-check"></i> Créer et Activer le Compte
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Liste des utilisateurs -->
            <div class="col-xl-8 col-lg-7">
                <div class="card-custom h-100">
                    <div class="card-custom-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="d-block"><i class="fa-solid fa-table-list me-2"></i> Répertoire des comptes</span>
                            <small class="directory-subtitle">Agents de l'organisation</small>
                        </div>
                        <div class="directory-tools">
                            <label class="directory-search" aria-label="Rechercher un utilisateur">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="search" id="ajax-user-search" placeholder="Rechercher…" autocomplete="off">
                            </label>
                            <span class="live-badge"><span class="live-dot"></span>Temps réel</span>
                        </div>
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

  // Ajouter les styles CSS modernes pour AJAX et le formulaire
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
            /* Styling de la carte de création d'utilisateur */
            .user-form-card {
                background: linear-gradient(145deg, rgba(26, 26, 34, 0.95) 0%, rgba(16, 16, 22, 0.98) 100%) !important;
                border: 1.5px solid rgba(218, 165, 32, 0.35) !important;
                border-radius: 20px !important;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6), 0 0 30px rgba(218, 165, 32, 0.1) !important;
                overflow: hidden;
            }
            .user-form-header {
                background: linear-gradient(135deg, rgba(218, 165, 32, 0.22) 0%, rgba(184, 134, 11, 0.12) 100%) !important;
                border-bottom: 1px solid rgba(218, 165, 32, 0.3) !important;
                padding: 1.25rem 1.5rem !important;
                display: flex;
                align-items: center;
                gap: 0.85rem;
            }
            .user-form-header .icon-circle {
                width: 42px;
                height: 42px;
                background: var(--gold-primary, #DAA520);
                color: #000;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.15rem;
                box-shadow: 0 4px 12px rgba(218, 165, 32, 0.4);
            }
            .user-form-body {
                padding: 1.75rem 1.5rem !important;
            }
            .form-field-group label {
                color: #e5e7eb !important;
                font-size: 0.85rem !important;
                letter-spacing: 0.3px;
            }
            #ajax-user-form .custom-form-input,
            #ajax-user-form .custom-form-select {
                background-color: #101014 !important;
                border: 1.5px solid rgba(255, 255, 255, 0.16) !important;
                border-radius: 12px !important;
                color: #ffffff !important;
                font-size: 0.92rem !important;
                padding: 0.8rem 1rem !important;
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
                width: 100% !important;
                box-shadow: inset 0 2px 5px rgba(0,0,0,0.4) !important;
            }
            #ajax-user-form .custom-form-input:focus,
            #ajax-user-form .custom-form-select:focus {
                background-color: #16161e !important;
                border-color: #DAA520 !important;
                box-shadow: 0 0 0 4px rgba(218, 165, 32, 0.25), inset 0 1px 2px rgba(0,0,0,0.2) !important;
                outline: none !important;
            }
            #ajax-user-form .custom-form-input::placeholder {
                color: #6b7280 !important;
                opacity: 1 !important;
            }
            #ajax-user-form .custom-form-select option {
                background-color: #16161e !important;
                color: #ffffff !important;
                padding: 10px !important;
            }
            #ajax-btn-create {
                background: linear-gradient(135deg, #DAA520 0%, #B8860B 100%) !important;
                border: none !important;
                border-radius: 14px !important;
                color: #000000 !important;
                font-weight: 700 !important;
                font-size: 0.98rem !important;
                padding: 0.95rem 1.5rem !important;
                width: 100% !important;
                cursor: pointer !important;
                box-shadow: 0 8px 20px rgba(184, 134, 11, 0.4) !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 0.6rem !important;
                margin-top: 1.25rem !important;
            }
            #ajax-btn-create:hover {
                background: linear-gradient(135deg, #e5b32e 0%, #c99312 100%) !important;
                transform: translateY(-2px) !important;
                box-shadow: 0 12px 28px rgba(184, 134, 11, 0.6) !important;
                color: #000000 !important;
            }
            #ajax-btn-create:active {
                transform: translateY(0) !important;
            }
            #ajax-btn-create:disabled {
                opacity: 0.7 !important;
                cursor: not-allowed !important;
                transform: none !important;
            }
            /* Table CSS */
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
            .ajax-users-table { min-width: 920px; table-layout: auto !important; }
            .ajax-users-table td {
                padding: 15px 18px !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
                vertical-align: middle !important;
                color: #e5e7eb !important;
            }
            .ajax-users-table .action-cell { min-width: 165px; width: 165px; white-space: nowrap !important; padding-right: 22px !important; position: sticky; right: 0; z-index: 2; background: #181c24 !important; box-shadow: -8px 0 14px rgba(0,0,0,.22); }
            .ajax-users-table thead th:last-child { position: sticky; right: 0; z-index: 3; min-width: 165px; background: #272116 !important; }
            .ajax-users-table tbody tr:hover .action-cell { background: #272a22 !important; }
            #ajax-users-container { overflow-x: auto !important; overflow-y: visible; padding-bottom: 4px; }
            .ajax-users-table .action-cell .btn { white-space: nowrap !important; display: inline-flex; align-items: center; }
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
            .status-active {
                background: rgba(16, 185, 129, 0.2);
                color: #10b981;
                border: 1px solid rgba(16, 185, 129, 0.4);
                padding: 0.35rem 0.8rem;
                border-radius: 50px;
                font-weight: 600;
                font-size: 0.78rem;
                display: inline-block;
            }
            .status-suspended {
                background: rgba(239, 68, 68, 0.2);
                color: #f87171;
                border: 1px solid rgba(239, 68, 68, 0.4);
                padding: 0.35rem 0.8rem;
                border-radius: 50px;
                font-weight: 600;
                font-size: 0.78rem;
                display: inline-block;
            }
            .status-inactive {
                background: rgba(245, 158, 11, 0.2);
                color: #fbbf24;
                border: 1px solid rgba(245, 158, 11, 0.4);
                padding: 0.35rem 0.8rem;
                border-radius: 50px;
                font-weight: 600;
                font-size: 0.78rem;
                display: inline-block;
            }
            .form-intro { display:flex; gap:.6rem; align-items:flex-start; color:#9ca5b5; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:.7rem .75rem; margin-bottom:1.35rem; font-size:.75rem; line-height:1.45; }
            .form-intro i { color:#e4b83b; margin-top:2px; }
            .empty-users { min-height:240px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.45rem; color:#8d97a8; text-align:center; }
            .empty-users i { color:#c99a21; font-size:2rem; margin-bottom:.35rem; }
            .empty-users strong { color:#e5e7eb; font-size:.95rem; }
            .empty-users span { font-size:.8rem; }
            .directory-subtitle { color: #8992a3; font-weight: 400; margin-left: 1.65rem; }
            .directory-tools { display:flex; align-items:center; gap:.75rem; }
            .directory-search { display:flex; align-items:center; gap:.5rem; background:#11151d; border:1px solid rgba(255,255,255,.12); border-radius:10px; padding:.4rem .7rem; color:#8992a3; }
            .directory-search input { width:130px; border:0; outline:0; background:transparent; color:#fff; font-size:.82rem; }
            .live-badge { color:#8be3bd; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); border-radius:999px; padding:.35rem .7rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
            .live-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:#22c55e; margin-right:6px; box-shadow:0 0 0 3px rgba(34,197,94,.15); }
            .user-identity { display:flex; align-items:center; gap:.7rem; min-width:145px; }
            .user-avatar { display:grid; place-items:center; width:36px; height:36px; border-radius:11px; background:linear-gradient(135deg,#e4b83b,#9d6e09); color:#16120a; font-weight:800; }
            .user-identity strong { display:block; color:#f8fafc; font-size:.9rem; }
            .user-identity small { display:block; color:#737d8d; font-size:.68rem; margin-top:2px; }
            .user-email, .table-meta { display:flex; align-items:center; gap:.5rem; color:#b6bfce; font-size:.82rem; white-space:nowrap; }
            .user-email i, .table-meta i { color:#c99a21; width:14px; text-align:center; }
            .role-badge { display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .65rem; border:1px solid rgba(255,255,255,.1); border-radius:8px; background:rgba(255,255,255,.04); color:#d9dee8; font-size:.76rem; }
            .role-badge i { color:#8ec5ff; }
            .ajax-users-table tbody tr { transition:background .2s, transform .2s; }
            .ajax-users-table tbody tr:hover { transform:translateX(2px); }
            @media (max-width: 700px) { .directory-tools { width:100%; justify-content:space-between; } .directory-search { flex:1; } .directory-search input { width:100%; } .ajax-users-table th, .ajax-users-table td { padding:12px 14px !important; } }
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
  $('#ajax-user-form')
    .off('submit')
    .on('submit', function (e) {
      e.preventDefault();
      createUser();
    });

  $('#ajax-btn-refresh')
    .off('click')
    .on('click', function () {
      loadUsers();
    });

  $('#ajax-user-search')
    .off('input')
    .on('input', function () {
      const query = $(this).val().toLowerCase().trim();
      $('#ajax-users-container tbody tr').each(function () {
        $(this).toggle($(this).text().toLowerCase().includes(query));
      });
    });

  $(document)
    .off('click', '.ajax-btn-status')
    .on('click', '.ajax-btn-status', function () {
      const userId = $(this).data('user-id');
      const newStatus = $(this).data('new-status');
      updateUserStatus(userId, newStatus, $(this));
    });
}

function setupAutoRefresh() {
  if (window.managerRefreshTimer) {
    clearInterval(window.managerRefreshTimer);
  }
  window.managerRefreshTimer = setInterval(function () {
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
    success: function (response) {
      if (response.success) {
        animateNumber('#stat-total-users', response.data.total_users);
        animateNumber('#stat-active-users', response.data.active_users);
        animateNumber('#stat-total-roles', response.data.total_roles);
      }
    },
    error: function () {
      console.error('Erreur lors du chargement des statistiques');
    },
  });
}

function loadDepartements() {
  $.ajax({
    url: 'manager.php',
    method: 'POST',
    data: { action: 'get_departements' },
    dataType: 'json',
    success: function (response) {
      if (response.success) {
        const select = $('#ajax_department');
        select.html('<option value="">Sélectionnez un département</option>');
        response.data.forEach(function (dept) {
          select.append(`<option value="${dept.id}">${dept.nom}</option>`);
        });
      }
    },
    error: function () {
      showError('Erreur lors du chargement des départements');
    },
  });
}

function loadRoles() {
  $.ajax({
    url: 'manager.php',
    method: 'POST',
    data: { action: 'get_roles' },
    dataType: 'json',
    success: function (response) {
      if (response.success) {
        const select = $('#ajax_role');
        select.html('<option value="">Sélectionnez un rôle</option>');
        response.data.forEach(function (role) {
          select.append(`<option value="${role.id}">${role.nom}</option>`);
        });
      }
    },
    error: function () {
      showError('Erreur lors du chargement des rôles');
    },
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
    success: function (response) {
      if (response.success) {
        if (!response.data || response.data.length === 0) {
          $('#ajax-users-container').html(
            '<div class="empty-users"><i class="fa-solid fa-users-slash"></i><strong>Aucun compte trouvé</strong><span>Les comptes créés apparaîtront ici.</span></div>'
          );
          $('#ajax-users-loading').hide();
          $('#ajax-users-container').show();
          return;
        }
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

        const statusLabels = { active: 'Actif', suspended: 'Suspendu', inactive: 'Inactif' };

        response.data.forEach(function (user) {
          const statusClass = `status-${user.status}`;
          const statusText = statusLabels[user.status] || user.status;

          let actionButton = '';
          if (user.status === 'active') {
            actionButton = `<button class="btn btn-sm btn-outline-warning fw-semibold ajax-btn-status" data-user-id="${user.id}" data-new-status="suspended" style="border-radius: 8px;"><i class="fa-solid fa-pause me-1"></i>Suspendre</button>`;
          } else if (user.status === 'suspended') {
            actionButton = `<button class="btn btn-sm btn-outline-success fw-semibold ajax-btn-status" data-user-id="${user.id}" data-new-status="active" style="border-radius: 8px;"><i class="fa-solid fa-play me-1"></i>Activer</button>`;
          } else {
            actionButton = `<button class="btn btn-sm btn-outline-info fw-semibold ajax-btn-status" data-user-id="${user.id}" data-new-status="active" style="border-radius: 8px;"><i class="fa-solid fa-check me-1"></i>Activer</button>`;
          }

          tableHTML += `
                        <tr class="ajax-fade-in">
                            <td><div class="user-identity"><span class="user-avatar">${(user.username || '?').charAt(0).toUpperCase()}</span><div><strong>${user.username}</strong><small>ID #${user.id}</small></div></div></td>
                            <td><span class="user-email"><i class="fa-regular fa-envelope"></i>${user.email}</span></td>
                            <td><span class="table-meta"><i class="fa-solid fa-building"></i>${user.department || 'Non affecté'}</span></td>
                            <td><span class="role-badge"><i class="fa-solid fa-shield-halved"></i>${user.role || 'Non défini'}</span></td>
                            <td><span class="${statusClass}">${statusText}</span></td>
                            <td class="text-end action-cell">${actionButton}</td>
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
    error: function () {
      $('#ajax-users-loading').hide();
      $('#ajax-users-container').show();
      showError('Erreur lors du chargement des utilisateurs');
    },
  });
}

function createUser() {
  const btn = $('#ajax-btn-create');
  const spinner = btn.find('.ajax-spinner');

  btn.prop('disabled', true);
  spinner.show();

  const formData = {
    action: 'create_user',
    username: $('#ajax_username').val(),
    email: $('#ajax_email').val(),
    password: $('#ajax_password').val(),
    department: $('#ajax_department').val(),
    role: $('#ajax_role').val(),
  };

  $.ajax({
    url: 'manager.php',
    method: 'POST',
    data: formData,
    dataType: 'json',
    success: function (response) {
      if (response.success) {
        showSuccess(response.message);
        $('#ajax-user-form')[0].reset();
        loadStats();
        loadUsers();
      } else {
        showError(response.message);
      }
    },
    error: function () {
      showError("Erreur lors de la création de l'utilisateur");
    },
    complete: function () {
      btn.prop('disabled', false);
      spinner.hide();
    },
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
      new_status: newStatus,
    },
    dataType: 'json',
    success: function (response) {
      if (response.success) {
        showSuccess(response.message);
        loadStats();
        loadUsers();
      } else {
        showError(response.message);
        button.prop('disabled', false).html(originalText);
      }
    },
    error: function () {
      showError('Erreur lors de la mise à jour du statut');
      button.prop('disabled', false).html(originalText);
    },
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
    $({ counter: current }).animate(
      { counter: finalNumber },
      {
        duration: 1000,
        easing: 'swing',
        step: function () {
          element.text(Math.floor(this.counter));
        },
        complete: function () {
          element.text(finalNumber);
        },
      }
    );
  }
}
