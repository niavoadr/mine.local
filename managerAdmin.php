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

  $departments = $connexion->query(
    "SELECT enumlabel
     FROM pg_enum e
     JOIN pg_type t ON t.oid = e.enumtypid
     WHERE t.typname = 'department_enum'
     ORDER BY e.enumsortorder"
  )->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  $stats = ['total' => 0, 'active' => 0];
  $roles = [];
  $connectedUsers = 0;
  $users = [];
  $departments = [];
}

$statusLabels = [
  'active' => 'Actif',
  'suspended' => 'Suspendu',
  'inactive' => 'Inactif',
];
?>

<div class="manager-view">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h2 class="section-title mb-1">
                <i class="fa-solid fa-users-gear text-warning me-2"></i>
                Gestionnaire de Comptes
            </h2>
            <p class="text-muted mb-0">
                Liste, création et suspension des agents du Ministère.
            </p>
        </div>
    </div>

    <div class="alert custom-alert-success d-none" data-manager-success role="alert"></div>
    <div class="alert custom-alert-error d-none" data-manager-error role="alert"></div>

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

    <div class="row g-4">
        <div class="col-xl-4 col-lg-5">
            <div class="user-form-card h-100">
                <div class="user-form-header">
                    <div class="icon-circle"><i class="fa-solid fa-user-plus"></i></div>
                    <h4 class="mb-0 text-white fw-bold fs-6">Créer un Nouveau Compte</h4>
                </div>
                <div class="user-form-body">
                    <form data-create-user>
                        <div class="form-field-group mb-3">
                            <label class="form-label text-light fw-semibold small">Nom d'utilisateur</label>
                            <input type="text" name="username" class="custom-form-input" required autocomplete="off">
                        </div>
                        <div class="form-field-group mb-3">
                            <label class="form-label text-light fw-semibold small">Adresse Email</label>
                            <input type="email" name="email" class="custom-form-input" required autocomplete="off">
                        </div>
                        <div class="form-field-group mb-3">
                            <label class="form-label text-light fw-semibold small">Mot de passe</label>
                            <input type="password" name="password" class="custom-form-input" required minlength="6">
                        </div>
                        <div class="form-field-group mb-3">
                            <label class="form-label text-light fw-semibold small">Département</label>
                            <select name="department" class="custom-form-select" required>
                                <option value="">Sélectionnez un département</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= manager_escape($department) ?>">
                                        <?= manager_escape($department) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field-group mb-4">
                            <label class="form-label text-light fw-semibold small">Rôle et Habilitation</label>
                            <select name="role" class="custom-form-select" required>
                                <option value="">Sélectionnez un rôle</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= manager_escape($role) ?>">
                                        <?= manager_escape($role) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold">
                            <i class="fa-solid fa-circle-check me-1"></i>
                            Créer et Activer le Compte
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-lg-7">
            <div class="card-custom h-100">
                <div class="card-custom-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="fa-solid fa-table-list me-2"></i> Liste des comptes</span>
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
                <div class="card-custom-body p-0" data-users-container>
                    <?php include __DIR__ . '/manager_users_table.php'; ?>
                </div>
            </div>
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

    const showMessage = (type, text) => {
        const element = root.querySelector('[data-manager-' + type + ']');
        element.textContent = text;
        element.classList.remove('d-none');
        setTimeout(() => element.classList.add('d-none'), 5000);
    };

    root.querySelector('[data-manager-reload]').onclick = () => window.location.reload();

    root.querySelector('[data-user-search]').oninput = (event) => {
        const query = event.target.value.toLowerCase();
        root.querySelectorAll('tbody tr').forEach((row) => {
            row.hidden = !row.textContent.toLowerCase().includes(query);
        });
    };

    root.querySelector('[data-create-user]').onsubmit = async (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
        formData.append('action', 'create_user');
        formData.append('csrf_token', window.CSRF_TOKEN);

        try {
            const response = await fetch('manager.php', {
                method: 'POST',
                body: formData,
            }).then((result) => result.json());

            if (!response.success) {
                showMessage('error', response.message);
                return;
            }

            showMessage('success', response.message);
            event.target.reset();
            setTimeout(() => window.location.reload(), 700);
        } catch (error) {
            showMessage('error', 'Erreur lors de la création de l’utilisateur');
        }
    };

    root.querySelectorAll('[data-user-status]').forEach((button) => {
        button.onclick = async () => {
            button.disabled = true;
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('user_id', button.dataset.userId);
            formData.append('new_status', button.dataset.userStatus);
            formData.append('csrf_token', window.CSRF_TOKEN);

            try {
                const response = await fetch('manager.php', {
                    method: 'POST',
                    body: formData,
                }).then((result) => result.json());

                if (!response.success) {
                    showMessage('error', response.message);
                    button.disabled = false;
                    return;
                }

                window.location.reload();
            } catch (error) {
                showMessage('error', 'Erreur lors de la mise à jour du statut');
                button.disabled = false;
            }
        };
    });
})();

setInterval(() => {
    fetch('app_session.php', { credentials: 'same-origin' }).catch(() => {});
}, 30000);
</script>
