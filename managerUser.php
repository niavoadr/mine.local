<?php
require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/manager_session.php';

try {
  register_app_session($connexion);
  $stats = $connexion->query(
    "SELECT COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'active') AS active
     FROM users"
  )->fetch();

  $roles = $connexion->query(
    "SELECT enumlabel
     FROM pg_enum e
     JOIN pg_type t ON t.oid = e.enumtypid
     WHERE t.typname = 'role_enum'
     ORDER BY e.enumsortorder"
  )->fetchAll(PDO::FETCH_COLUMN);

  $connectedUsers = get_connected_app_users($connexion);

  $users = $connexion->query(
    'SELECT id, username, email, department, role, status
     FROM users
     ORDER BY date_creation DESC'
  )->fetchAll();
} catch (Throwable $e) {
  $stats = ['total' => 0, 'active' => 0];
  $roles = [];
  $connectedUsers = 0;
  $users = [];
}

$statusLabels = [
  'active' => 'Actif',
  'suspended' => 'Suspendu',
  'inactive' => 'Inactif',
];
?>

<style>
    .manager-view .stat-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .manager-view .ajax-users-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .manager-view .ajax-users-table th {
        background: rgba(218, 165, 32, 0.15);
        color: #ffd700;
        padding: 14px 18px;
        text-align: left;
    }

    .manager-view .ajax-users-table td {
        padding: 14px 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        color: #e5e7eb;
    }

    .manager-view .user-identity {
        display: flex;
        align-items: center;
        gap: 0.7rem;
    }

    .manager-view .user-identity strong,
    .manager-view .user-identity small {
        display: block;
    }

    .manager-view .user-identity small {
        color: #8992a3;
        font-size: 0.7rem;
    }

    .manager-view .user-avatar {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        border-radius: 11px;
        background: #daa520;
        color: #16120a;
        font-weight: 800;
    }

    .manager-view .user-email {
        white-space: nowrap;
    }

    .manager-view .role-badge {
        display: inline-flex;
        gap: 0.4rem;
        padding: 0.4rem 0.65rem;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.04);
    }

    .manager-view .status-active,
    .manager-view .status-suspended,
    .manager-view .status-inactive {
        display: inline-block;
        padding: 0.35rem 0.8rem;
        border-radius: 50px;
        font-size: 0.78rem;
    }

    .manager-view .status-active {
        color: #10b981;
        background: rgba(16, 185, 129, 0.2);
    }

    .manager-view .status-suspended {
        color: #f87171;
        background: rgba(239, 68, 68, 0.2);
    }

    .manager-view .status-inactive {
        color: #fbbf24;
        background: rgba(245, 158, 11, 0.2);
    }

    .manager-view .directory-search {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.7rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 10px;
    }

    .manager-view .directory-search input {
        width: 130px;
        background: transparent;
        border: 0;
        outline: 0;
        color: #fff;
    }

</style>

<div class="manager-view">
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
      <h2 class="section-title mb-1">
        <i class="fa-solid fa-users-gear text-warning me-2"></i>
        Gestionnaire de Comptes & Habilitations
      </h2>
      <p class="text-muted mb-0">
        Consultation en lecture restreinte des comptes agents et rôles du Ministère.
      </p>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php
    $statCards = [
      ['Total Utilisateurs', (int) $stats['total'], 'fa-users', 'warning'],
      ['Utilisateurs Actifs', (int) $stats['active'], 'fa-user-check', 'success'],
      ['Utilisateurs connectés', $connectedUsers, 'fa-user-clock', 'info'],
    ];
    foreach ($statCards as $stat):
    ?>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="stat-label"><?= manager_escape($stat[0]) ?></div>
              <div class="stat-number"><?= $stat[1] ?></div>
            </div>
            <div class="stat-icon-box bg-<?= $stat[3] ?>-subtle text-<?= $stat[3] ?>">
              <i class="fa-solid <?= $stat[2] ?> fs-4"></i>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card-custom">
    <div class="card-custom-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <i class="fa-solid fa-table-list me-2"></i>
        Utilisateurs existants
      </div>
      <div class="d-flex align-items-center gap-2">
        <label class="directory-search">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="search" data-user-search placeholder="Rechercher…">
        </label>
        <button type="button" class="btn btn-sm btn-outline-light" data-manager-reload style="border-radius: 8px;">
          <i class="fas fa-sync-alt me-1"></i>
          Actualiser
        </button>
      </div>
    </div>
    <div class="card-custom-body p-0">
      <?php include __DIR__ . '/manager_users_table.php'; ?>
    </div>
  </div>
</div>

<script>
(function () {
  const root = document.querySelector('#manager-content .manager-view');

  if (!root || root.dataset.ready) {
    return;
  }

  root.dataset.ready = '1';
  root.querySelector('[data-manager-reload]').onclick = () => window.location.reload();

  root.querySelector('[data-user-search]').oninput = (event) => {
    const query = event.target.value.toLowerCase();

    root.querySelectorAll('tbody tr').forEach((row) => {
      row.hidden = !row.textContent.toLowerCase().includes(query);
    });
  };
})();

setInterval(() => {
  fetch('app_session.php', { credentials: 'same-origin' }).catch(() => {});
}, 30000);
</script>
