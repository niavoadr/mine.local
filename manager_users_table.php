<?php if (!$users): ?>
  <div class="text-center text-muted py-5"><i class="fa-solid fa-users-slash fs-2 d-block mb-2"></i>Aucun compte trouvé.</div>
<?php else: ?>
<table class="ajax-users-table"><thead><tr><th>Utilisateur</th><th>Email</th><th>Département</th><th>Rôle</th><th>Statut</th><?php if (basename($_SERVER['SCRIPT_NAME']) === 'dashboard_admin.php'): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($users as $user): $status = (string)$user['status']; $statusLabel = $statusLabels[$status] ?? $status; ?>
<tr><td><div class="user-identity"><span class="user-avatar"><?= manager_escape(strtoupper(substr((string)$user['username'], 0, 1))) ?></span><div><strong><?= manager_escape($user['username']) ?></strong><small>ID #<?= (int)$user['id'] ?></small></div></div></td><td><span class="user-email"><i class="fa-regular fa-envelope"></i><?= manager_escape($user['email']) ?></span></td><td><?= manager_escape($user['department'] ?: 'Non affecté') ?></td><td><span class="role-badge"><i class="fa-solid fa-shield-halved"></i><?= manager_escape($user['role'] ?: 'Non défini') ?></span></td><td><span class="status-<?= manager_escape($status) ?>"><?= manager_escape($statusLabel) ?></span></td>
<?php if (basename($_SERVER['SCRIPT_NAME']) === 'dashboard_admin.php'): ?><td class="text-end action-cell"><button type="button" class="btn btn-sm btn-outline-<?= $status === 'active' ? 'warning' : 'success' ?>" data-user-id="<?= (int)$user['id'] ?>" data-user-status="<?= $status === 'active' ? 'suspended' : 'active' ?>"><?= $status === 'active' ? 'Suspendre' : 'Activer' ?></button></td><?php endif; ?></tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
