
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS - Vue Lecture Seule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #0e0e12;
            --bg-card: rgba(24, 24, 30, 0.85);
            --border-gold: rgba(218, 165, 32, 0.25);
            --gold-primary: #DAA520;
            --gold-dark: #B8860B;
            --text-muted: #9ca3af;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-body);
            color: #e5e7eb;
            margin: 0;
            padding: 1.5rem;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-gold);
            border-radius: 18px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }

        .card:hover {
            border-color: var(--gold-primary);
            box-shadow: 0 15px 35px rgba(218, 165, 32, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, rgba(218, 165, 32, 0.18) 0%, rgba(184, 134, 11, 0.1) 100%);
            border-bottom: 1px solid var(--border-gold);
            padding: 1rem 1.5rem;
            color: #FFD700;
            font-weight: 600;
        }

        .card-title {
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-title i {
            color: var(--gold-primary);
        }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            color: #e5e7eb;
        }

        .table thead th {
            background: rgba(218, 165, 32, 0.15);
            color: #FFD700;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.82rem;
            letter-spacing: 0.5px;
            border: none;
            padding: 14px 18px;
        }

        .table tbody tr td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 14px 18px;
            vertical-align: middle;
            font-size: 0.92rem;
        }

        .table tbody tr:hover td {
            background: rgba(218, 165, 32, 0.08);
        }

        .mac-address {
            font-family: 'Plus Jakarta Sans', monospace;
            font-weight: 600;
            color: #6ea8fe;
        }

        .department-badge {
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-finance { background: rgba(33, 150, 243, 0.2); color: #64b5f6; border: 1px solid rgba(33, 150, 243, 0.4); }
        .badge-rh { background: rgba(255, 152, 0, 0.2); color: #ffb74d; border: 1px solid rgba(255, 152, 0, 0.4); }
        .badge-daj { background: rgba(156, 39, 176, 0.2); color: #ba68c8; border: 1px solid rgba(156, 39, 176, 0.4); }
        .badge-communication { background: rgba(244, 67, 54, 0.2); color: #e57373; border: 1px solid rgba(244, 67, 54, 0.4); }
        .badge-sg { background: rgba(0, 188, 212, 0.2); color: #4dd0e1; border: 1px solid rgba(0, 188, 212, 0.4); }
        
        .stat-box {
            padding: 1rem;
            background: rgba(18, 18, 22, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            margin: 0.5rem 0;
            transition: all 0.2s ease;
        }
        
        .stat-box:hover {
            border-color: var(--gold-primary);
        }

        .stat-box h6 {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
        }

        .stat-box h4 {
            color: #FFD700;
            font-weight: 700;
            margin: 0;
        }

        .btn-refresh {
            background: rgba(218, 165, 32, 0.15);
            border: 1px solid var(--border-gold);
            color: var(--gold-primary);
            font-weight: 600;
            border-radius: 10px;
            padding: 6px 14px;
            transition: all 0.2s ease;
        }

        .btn-refresh:hover {
            background: var(--gold-primary);
            color: #000;
        }
    </style>
    <link rel="stylesheet" href="css/responsive.css?v=20260722">
    <link rel="stylesheet" href="css/animations.css?v=20260721">
</head>
<body>
    <div class="container-fluid">
        <!-- En-tête -->
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

        <!-- Section Statistiques -->
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

        <!-- Liste des appareils -->
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
        $(document).ready(function() {
            loadDevices();
            loadStats();
        });

        // Charger la liste des appareils
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

        // Afficher les appareils
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
                            <td class="mac-address">${device.mac_address}</td>
                            <td><span class="badge ${departmentColors[device.department] || 'bg-secondary'} department-badge">${device.department.toUpperCase()}</span></td>
                            <td>${device.group}</td>
                            <td><span class="badge bg-dark border border-secondary px-3 py-1">${device.bandwidth}</span></td>
                        </tr>
                    `;
                });
            }
            $('#devicesTable').html(html);
        }

        // Charger les statistiques
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