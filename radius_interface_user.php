<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS - Vue Lecture Seule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/theme.css?v=20260817">
    <link rel="stylesheet" href="css/responsive.css?v=20260817">
    <link rel="stylesheet" href="css/animations.css?v=20260817">
</head>
<body>
    <script>window.CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>';</script>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h1 class="card-title fs-3 mb-1">
                                <i class="fas fa-network-wired"></i>
                                Gestion des Appareils RADIUS
                            </h1>
                            <p class="text-muted mb-0">Authentification MAC par département — Interface Administrateur restreinte (Lecture Seule)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-chart-bar me-2"></i> Répartition par Département</span>
                        <small class="text-muted">Mise à jour en temps réel</small>
                    </div>
                    <div class="card-body">
                        <div id="stats" class="row g-3 text-center">
                            <div class="col-12 py-3">
                                <div class="spinner-border text-warning" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="fas fa-list me-2"></i> Appareils configurés et autorisés</span>
                        <button class="btn btn-refresh btn-sm" onclick="loadDevices()">
                            <i class="fas fa-sync-alt me-1"></i> Actualiser
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Adresse MAC</th>
                                        <th>Département</th>
                                        <th>Groupe</th>
                                        <th>Bande passante</th>
                                    </tr>
                                </thead>
                                <tbody id="devicesTable">
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <div class="spinner-border text-warning mb-2" role="status"></div>
                                            <div>Chargement de la liste des appareils...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

            loadDevices();
            loadStats();
        });

        function loadDevices() {
            $.post('radius_devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    displayDevices(response.data);
                } else {
                    $('#devicesTable').html('<tr><td colspan="4" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur: ' + response.error + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#devicesTable').html('<tr><td colspan="4" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur de communication avec le serveur</td></tr>');
            });
        }

        function displayDevices(devices) {
            let html = '';
            if (devices.length === 0) {
                html = '<tr><td colspan="4" class="text-center py-4 text-muted">Aucun appareil configuré pour le moment.</td></tr>';
            } else {
                devices.forEach(function(device) {
                    const departmentColors = {
                        'finance': 'badge-finance',
                        'rh': 'badge-rh', 
                        'daj': 'badge-daj',
                        'communication': 'badge-communication',
                        'sg': 'badge-sg'
                    };
                    
                    html += `
                        <tr>
                            <td class="mac-address">${escapeHtml(device.mac_address)}</td>
                            <td><span class="badge ${departmentColors[device.department] || 'bg-secondary'} department-badge">${escapeHtml(device.department).toUpperCase()}</span></td>
                            <td>${escapeHtml(device.group)}</td>
                            <td><span class="badge bg-dark border border-secondary px-3 py-1">${escapeHtml(device.bandwidth)}</span></td>
                        </tr>
                    `;
                });
            }
            $('#devicesTable').html(html);
        }

        function loadStats() {
            $.post('radius_devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    const devices = response.data;
                    const stats = {};
                    
                    devices.forEach(function(device) {
                        stats[device.department] = (stats[device.department] || 0) + 1;
                    });
                    
                    let html = `
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>Total</h6><h4>${devices.length}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>Finance</h6><h4>${stats.finance || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>RH</h6><h4>${stats.rh || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>DAJ</h6><h4>${stats.daj || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>Comm</h6><h4>${stats.communication || 0}</h4></div></div>
                        <div class="col-6 col-md-2"><div class="stat-box"><h6>SG</h6><h4>${stats.sg || 0}</h4></div></div>
                    `;
                    
                    $('#stats').html(html);
                }
            }, 'json');
        }
    </script>
</body>
</html>