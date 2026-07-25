// js/managerUser.js - Script AJAX pour le Gestionnaire de Compte (Mode Lecture Restreinte)
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
                <div><span class="d-block"><i class="fa-solid fa-table-list me-2"></i> Utilisateurs existants</span><small class="directory-subtitle">Agents de l'organisation</small></div>
                <div class="directory-tools"><label class="directory-search" aria-label="Rechercher un utilisateur"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="ajax-user-search" placeholder="Rechercher…" autocomplete="off"></label><span class="live-badge"><span class="live-dot"></span>Lecture seule</span></div>
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
            .directory-subtitle { color:#8992a3; font-weight:400; margin-left:1.65rem; }
            .directory-tools { display:flex; align-items:center; gap:.75rem; }
            .directory-search { display:flex; align-items:center; gap:.5rem; background:#11151d; border:1px solid rgba(255,255,255,.12); border-radius:10px; padding:.4rem .7rem; color:#8992a3; }
            .directory-search input { width:130px; border:0; outline:0; background:transparent; color:#fff; font-size:.82rem; }
            .live-badge { color:#8be3bd; background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); border-radius:999px; padding:.35rem .7rem; font-size:.72rem; font-weight:700; white-space:nowrap; }
            .live-dot { display:inline-block; width:6px; height:6px; border-radius:50%; background:#22c55e; margin-right:6px; }
            .user-identity { display:flex; align-items:center; gap:.7rem; min-width:145px; }
            .user-avatar { display:grid; place-items:center; width:36px; height:36px; border-radius:11px; background:linear-gradient(135deg,#e4b83b,#9d6e09); color:#16120a; font-weight:800; }
            .user-identity strong { display:block; color:#f8fafc; font-size:.9rem; }
            .user-identity small { display:block; color:#737d8d; font-size:.68rem; }
            .user-email, .table-meta { display:flex; align-items:center; gap:.5rem; color:#b6bfce; font-size:.82rem; white-space:nowrap; }
            .user-email i, .table-meta i { color:#c99a21; width:14px; text-align:center; }
            .role-badge { display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .65rem; border:1px solid rgba(255,255,255,.1); border-radius:8px; background:rgba(255,255,255,.04); color:#d9dee8; font-size:.76rem; }
            .role-badge i { color:#8ec5ff; }
            /* Présentation en cartes : la liste des utilisateurs existants */
            .ajax-users-table { border-collapse:separate !important; border-spacing:0 10px !important; padding:0 14px !important; }
            .ajax-users-table thead th { background:#202838 !important; color:#f4c94e !important; border:0 !important; padding:13px 16px !important; }
            .ajax-users-table tbody tr { transition:all .2s ease; background:linear-gradient(100deg,#1b2230,#151b26) !important; box-shadow:0 5px 16px rgba(0,0,0,.22) !important; }
            .ajax-users-table tbody td { background:transparent !important; border-top:1px solid rgba(255,255,255,.07) !important; border-bottom:1px solid rgba(255,255,255,.07) !important; padding:16px !important; }
            .ajax-users-table tbody td:first-child { border-left:3px solid #d8a928 !important; border-radius:12px 0 0 12px; }
            .ajax-users-table tbody td:last-child { border-radius:0 12px 12px 0; }
            .ajax-users-table tbody tr:hover { transform:translateY(-3px) !important; background:linear-gradient(100deg,#252f40,#1b2432) !important; box-shadow:0 9px 24px rgba(0,0,0,.38) !important; }
            .ajax-users-table tbody tr:hover td { background:transparent !important; }
            .ajax-users-table .status-active, .ajax-users-table .status-suspended, .ajax-users-table .status-inactive { min-width:82px; text-align:center; }
            @media (max-width:700px) { .ajax-users-table { padding:0 8px !important; min-width:760px; } }

            @media (max-width:700px) { .directory-tools { width:100%; justify-content:space-between; } .directory-search { flex:1; } .directory-search input { width:100%; } }
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

        const statusLabels = { active: 'Actif', suspended: 'Suspendu', inactive: 'Inactif' };

        response.data.forEach(function (user) {
          const statusClass = `status-${user.status}`;
          const statusText = statusLabels[user.status] || user.status;

          tableHTML += `
                        <tr class="ajax-fade-in">
                            <td><div class="user-identity"><span class="user-avatar">${(user.username || '?').charAt(0).toUpperCase()}</span><div><strong>${user.username}</strong><small>ID #${user.id}</small></div></div></td>
                            <td><span class="user-email"><i class="fa-regular fa-envelope"></i>${user.email}</span></td>
                            <td><span class="table-meta"><i class="fa-solid fa-building"></i>${user.department || 'Non affecté'}</span></td>
                            <td><span class="role-badge"><i class="fa-solid fa-shield-halved"></i>${user.role || 'Non défini'}</span></td>
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
    error: function () {
      $('#ajax-users-loading').hide();
      $('#ajax-users-container').show();
      showError('Erreur lors du chargement des utilisateurs');
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
