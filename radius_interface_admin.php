<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS</title>
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

        /* Formulaire */
        .form-control, .form-select {
            background: rgba(18, 18, 22, 0.9) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.12) !important;
            border-radius: 12px !important;
            color: #ffffff !important;
            padding: 0.75rem 1rem !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--gold-primary) !important;
            box-shadow: 0 0 0 4px rgba(218, 165, 32, 0.2) !important;
        }

        .btn-marron {
            background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
            border: none;
            color: #000;
            font-weight: 700;
            border-radius: 12px;
            padding: 0.75rem 1.25rem;
            box-shadow: 0 6px 16px rgba(184, 134, 11, 0.35);
            transition: all 0.3s ease;
        }

        .btn-marron:hover {
            background: linear-gradient(135deg, #e5b32e 0%, #c99312 100%);
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(184, 134, 11, 0.5);
        }

        /* Table */
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

        /* Badges */
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

        /* Graphique & Légende */
        .chart-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .pie-chart-container {
            position: relative;
            width: 180px;
            height: 180px;
        }

        .center-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .total-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: #FFD700;
            line-height: 1;
        }

        .total-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .legend {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 4px;
        }

        .color-finance { background-color: #2196F3; }
        .color-rh { background-color: #FF9800; }
        .color-daj { background-color: #9C27B0; }
        .color-comm { background-color: #F44336; }
        .color-sg { background-color: #00BCD4; }
        .color-dsi { background-color: #4CAF50; }
        .color-dircab { background-color: #FFC107; }

        .loading {
            display: none;
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
                            <p class="text-muted mb-0">Authentification par adresse MAC (MAB) — Auth-Type := Accept</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 g-4">
            <!-- Formulaire d'ajout -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Ajouter et autoriser un nouvel appareil</h5>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <form id="addDeviceForm">
                            <div class="mb-3">
                                <label for="mac_address" class="form-label text-muted">Adresse MAC de l'appareil</label>
                                <input type="text" class="form-control" id="mac_address" placeholder="Ex: 6c:3b:f5:14:bf:56" required>
                            </div>
                            <div class="mb-4">
                                <label for="department" class="form-label text-muted">Département</label>
                                <select class="form-select" id="department" required>
                                    <option value="">Sélectionner un département...</option>
                                    <option value="finance">Finance & Comptabilité</option>
                                    <option value="rh">Ressources Humaines</option>
                                    <option value="daj">Direction des Affaires Juridiques</option>
                                    <option value="communication">Communication</option>
                                    <option value="sg">Secrétariat Général</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-marron w-100">
                                <span class="loading me-2"><i class="fas fa-spinner fa-spin"></i></span>
                                <i class="fas fa-plus me-1"></i> Enregistrer l'appareil
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Graphique et statistiques -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Répartition par Département</h5>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="chart-wrapper w-100">
                            <div class="pie-chart-container">
                                <canvas id="pieChart" width="180" height="180"></canvas>
                                <div class="center-label">
                                    <div class="total-number" id="totalDevices">0</div>
                                    <div class="total-text">Total</div>
                                </div>
                            </div>
                            <div class="legend" id="statsLegend">
                                <div class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i> Chargement...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau des appareils -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="fas fa-list me-2"></i> Appareils enregistrés sur le réseau</span>
                        <button class="btn btn-sm btn-outline-light" onclick="loadDevices()" style="border-radius: 8px;">
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
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="devicesTable">
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <div class="spinner-border text-warning mb-2" role="status"></div>
                                            <div>Chargement des appareils...</div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        function formatToColon(mac) {
            let clean = mac.replace(/[^a-fA-F0-9]/g, '').toLowerCase();
            if (clean.length === 12) {
                return clean.match(/.{1,2}/g).join(':');
            }
            return mac.trim().toLowerCase();
        }

        $(document).ready(function() {
            loadDevices();
            loadStats();

            $('#mac_address').on('blur', function() {
                const formatted = formatToColon($(this).val());
                if (formatted.length === 17) {
                    $(this).val(formatted);
                }
            });
        });

        function loadDevices() {
            $.post('radius_devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    displayDevices(response.data);
                } else {
                    $('#devicesTable').html('<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur: ' + response.error + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#devicesTable').html('<tr><td colspan="5" class="text-center py-4 text-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Erreur de communication</td></tr>');
            });
        }

        function displayDevices(devices) {
            let html = '';
            if (devices.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-4 text-muted">Aucun appareil configuré dans RADIUS.</td></tr>';
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
                            <td class="text-end">
                                <button class="btn btn-danger btn-sm" onclick="deleteDevice('${device.mac_address}')" style="border-radius: 8px;">
                                    <i class="fas fa-trash me-1"></i> Supprimer
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#devicesTable').html(html);
        }

        $('#addDeviceForm').submit(function(e) {
            e.preventDefault();
            
            let macInput = $('#mac_address').val();
            const macColon = formatToColon(macInput);
            const department = $('#department').val();
            
            if(macColon.length !== 17) {
                alert("L'adresse MAC saisie est invalide. Veuillez saisir 12 caractères hexadécimaux.");
                return;
            }

            $('.loading').show();
            $('button[type="submit"]').prop('disabled', true);
            
            $.post('radius_devices.php', {
                action: 'add_device',
                mac_address: macColon,
                department: department
            }, function(response) {
                if (response.success) {
                    alert('✅ Appareil ajouté avec succès (Format : ' + macColon + ')');
                    $('#addDeviceForm')[0].reset();
                    loadDevices();
                    loadStats();
                } else {
                    alert('❌ Erreur: ' + response.error);
                }
            }, 'json').fail(function() {
                alert('❌ Erreur de communication avec le serveur');
            }).always(function() {
                $('.loading').hide();
                $('button[type="submit"]').prop('disabled', false);
            });
        });

        function deleteDevice(mac) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet appareil ?\n\nMAC: ' + mac)) {
                $.post('radius_devices.php', {
                    action: 'delete_device',
                    mac_address: mac
                }, function(response) {
                    if (response.success) {
                        alert('✅ Appareil supprimé !');
                        loadDevices();
                        loadStats();
                    } else {
                        alert('❌ Erreur: ' + response.error);
                    }
                }, 'json');
            }
        }

        function loadStats() {
            $.post('radius_devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    const devices = response.data;
                    const stats = {
                        finance: 0,
                        rh: 0,
                        daj: 0,
                        communication: 0,
                        sg: 0,
                        dsi: 0,
                        dircab: 0
                    };
                    
                    devices.forEach(function(device) {
                        if (stats.hasOwnProperty(device.department)) {
                            stats[device.department]++;
                        }
                    });
                    
                    $('#totalDevices').text(devices.length);
                    
                    const chartData = [
                        { label: 'Finance', value: stats.finance, color: '#2196F3' },
                        { label: 'RH', value: stats.rh, color: '#FF9800' },
                        { label: 'DAJ', value: stats.daj, color: '#9C27B0' },
                        { label: 'Comm', value: stats.communication, color: '#F44336' },
                        { label: 'SG', value: stats.sg, color: '#00BCD4' },
                        { label: 'DSI', value: stats.dsi, color: '#4CAF50' },
                        { label: 'DirCab', value: stats.dircab, color: '#FFC107' }
                    ];
                    
                    drawPieChart(chartData, devices.length);
                    updateLegend(chartData, devices.length);
                }
            }, 'json');
        }

        function drawPieChart(data, total) {
            const canvas = document.getElementById('pieChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = 80;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (total === 0) {
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                ctx.fillStyle = '#22222a';
                ctx.fill();
                
                ctx.beginPath();
                ctx.arc(centerX, centerY, 55, 0, 2 * Math.PI);
                ctx.fillStyle = '#18181e';
                ctx.fill();
                return;
            }

            const filteredData = data.filter(item => item.value > 0);
            let currentAngle = -Math.PI / 2;

            filteredData.forEach(item => {
                const sliceAngle = (item.value / total) * 2 * Math.PI;

                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fillStyle = item.color;
                ctx.fill();

                ctx.strokeStyle = '#18181e';
                ctx.lineWidth = 3;
                ctx.stroke();

                currentAngle += sliceAngle;
            });

            ctx.beginPath();
            ctx.arc(centerX, centerY, 55, 0, 2 * Math.PI);
            ctx.fillStyle = '#18181e';
            ctx.fill();
        }

        function updateLegend(data, total) {
            const departmentKeys = {
                'Finance': 'finance',
                'RH': 'rh',
                'DAJ': 'daj',
                'Comm': 'comm',
                'SG': 'sg',
                'DSI': 'dsi',
                'DirCab': 'dircab'
            };

            let html = '';
            data.forEach(item => {
                const percentage = total > 0 ? Math.round((item.value / total) * 100) : 0;
                const colorClass = 'color-' + departmentKeys[item.label];
                
                html += `
                    <div class="legend-item">
                        <div class="legend-color ${colorClass}"></div>
                        <div>
                            <strong>${item.label}:</strong> ${item.value} <span class="text-muted small">(${percentage}%)</span>
                        </div>
                    </div>
                `;
            });
            
            $('#statsLegend').html(html);
        }
    </script>
</body>
</html>