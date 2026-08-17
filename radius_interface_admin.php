<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                            <p class="text-muted mb-0">Authentification MAC avec RADIUS MAC secret (MAB)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 g-4">
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

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span><i class="fas fa-list me-2"></i> Appareils autorisés</span>
                        <button class="btn btn-sm btn-outline-dark" onclick="loadDevices()" style="border-radius: 2px;">
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
        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatToColon(mac) {
            let clean = mac.replace(/[^a-fA-F0-9]/g, '').toLowerCase();
            if (clean.length === 12) {
                return clean.match(/.{1,2}/g).join(':');
            }
            return mac.trim().toLowerCase();
        }

        $(document).ready(function() {
            $.ajaxSetup({ data: { csrf_token: window.CSRF_TOKEN } });

            loadDevices();
            loadStats();

            $(document).on('click', '[data-delete-mac]', function() {
                deleteDevice($(this).data('delete-mac'));
            });

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
                            <td class="mac-address">${escapeHtml(device.mac_address)}</td>
                            <td><span class="badge ${departmentColors[device.department] || 'bg-secondary'} department-badge">${escapeHtml(device.department).toUpperCase()}</span></td>
                            <td>${escapeHtml(device.group)}</td>
                            <td><span class="badge bg-light border px-3 py-1">${escapeHtml(device.bandwidth)}</span></td>
                            <td class="text-end">
                                <button class="btn btn-danger btn-sm" data-delete-mac="${escapeHtml(device.mac_address)}" style="border-radius: 2px;">
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

            $.post('radius_devices.php', {
                action: 'check_mac_status',
                mac_address: macColon
            }, function(check) {
                if (check.success && check.data.is_rejected) {
                    if (confirm('Cet appareil est actuellement bloqué (liste noire).\n\nSi vous continuez, il sera retiré de la liste noire et autorisé à accéder à internet.\n\nContinuer ?')) {
                        doAddDevice(macColon, department, true);
                    }
                    return;
                }
                doAddDevice(macColon, department, false);
            }, 'json').fail(function() {
                doAddDevice(macColon, department, false);
            });
        });

        function doAddDevice(mac, department, force) {
            $('.loading').show();
            $('button[type="submit"]').prop('disabled', true);
            
            $.post('radius_devices.php', {
                action: 'add_device',
                mac_address: mac,
                department: department,
                force: force ? '1' : '0'
            }, function(response) {
                if (response.success) {
                    alert('Appareil ajouté avec succès');
                    $('#addDeviceForm')[0].reset();
                    loadDevices();
                    loadStats();
                } else if (response.error === 'APPAREIL_DEJA_BLOQUE') {
                    if (confirm('Cet appareil est actuellement bloqué (liste noire).\n\nSi vous continuez, il sera retiré de la liste noire et autorisé à accéder à internet.\n\nContinuer ?')) {
                        doAddDevice(mac, department, true);
                    }
                } else {
                    alert('Erreur: ' + response.error);
                }
            }, 'json').fail(function() {
                alert('Erreur de communication avec le serveur');
            }).always(function() {
                $('.loading').hide();
                $('button[type="submit"]').prop('disabled', false);
            });
        }

        function deleteDevice(mac) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet appareil ?\n\nMAC: ' + mac)) {
                $.post('radius_devices.php', {
                    action: 'delete_device',
                    mac_address: mac
                }, function(response) {
                    if (response.success) {
                        alert('Appareil supprimé !');
                        loadDevices();
                        loadStats();
                    } else {
                        alert('Erreur: ' + response.error);
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
                ctx.fillStyle = '#edebe6';
                ctx.fill();
                
                ctx.beginPath();
                ctx.arc(centerX, centerY, 55, 0, 2 * Math.PI);
                ctx.fillStyle = '#ffffff';
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

                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 3;
                ctx.stroke();

                currentAngle += sliceAngle;
            });

            ctx.beginPath();
            ctx.arc(centerX, centerY, 55, 0, 2 * Math.PI);
            ctx.fillStyle = '#ffffff';
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