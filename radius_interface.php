<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ===== VARIABLES DE COULEUR NOIR & MARRON DORÉ ===== */
        :root {
            --noir-principal: #000000;
            --noir-secondaire: #1a1a1a;
            --noir-tertiaire: #2d2d2d;
            --marron-dore: #B8860B;
            --marron-clair: #DAA520;
            --or-accent: #FFD700;
            --or-pale: #F4A460;
            --blanc: #ffffff;
            --gris-clair: #f8f9fa;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ===== RÉINITIALISATION & TYPOGRAPHIE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 300;
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            color: var(--blanc);
            min-height: 100vh;
            letter-spacing: 0.3px;
        }

        /* ===== BARRE DE STATUT MINISTÉRIELLE ===== */
        .ministry-status-bar {
            background: var(--noir-principal);
            border-bottom: 2px solid var(--marron-dore);
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            color: var(--or-pale);
            font-weight: 300;
            letter-spacing: 1px;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--marron-clair);
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* ===== CONTENEUR PRINCIPAL ===== */
        .container-fluid {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ===== CARTES MODERNISÉES ===== */
        .card {
            background: var(--noir-secondaire);
            border: 1px solid var(--marron-dore);
            border-radius: 0;
            overflow: hidden;
            transition: var(--transition);
            box-shadow: 0 8px 32px rgba(184, 134, 11, 0.1);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 48px rgba(184, 134, 11, 0.2);
            border-color: var(--marron-clair);
        }

        .card-header {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
            font-weight: 500;
            padding: 0.85rem 1.25rem;
            border: none;
            font-size: 0.95rem;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 500;
            letter-spacing: 0.5px;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 1.25rem;
            background: var(--noir-secondaire);
        }

        /* ===== EMBLÈME MINISTÉRIEL ===== */
        .ministry-emblem {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--marron-dore), var(--or-accent));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            border: 2px solid var(--or-accent);
            position: relative;
        }

        .ministry-emblem::before {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            border: 2px solid var(--noir-principal);
            transform: rotate(45deg);
        }

        .ministry-header {
            border-left: 4px solid var(--marron-dore);
            padding-left: 1rem;
        }

        .ministry-subtitle {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--or-pale);
            font-weight: 300;
            margin-top: 0.25rem;
        }

        /* ===== EN-TÊTE PRINCIPAL ===== */
        .card-title {
            font-size: 1.5rem;
            font-weight: 500;
            background: linear-gradient(135deg, var(--marron-dore), var(--or-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .card-title i {
            background: linear-gradient(135deg, var(--marron-dore), var(--marron-clair));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 0.75rem;
        }

        .card-text {
            color: var(--or-pale);
            font-weight: 300;
            font-size: 0.95rem;
        }

        /* ===== FORMULAIRES ===== */
        .form-label {
            color: var(--marron-clair);
            font-weight: 400;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            letter-spacing: 0.3px;
        }

        .form-control, .form-select {
            background: var(--noir-tertiaire);
            border: 1px solid var(--marron-dore);
            border-radius: 0;
            color: var(--blanc);
            padding: 0.75rem 1rem;
            font-weight: 300;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            background: var(--noir-secondaire);
            border-color: var(--marron-clair);
            box-shadow: 0 0 0 4px rgba(184, 134, 11, 0.15);
            color: var(--blanc);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 300;
        }

        .form-select option {
            background: var(--noir-tertiaire);
            color: var(--blanc);
        }

        .form-text {
            color: var(--or-pale);
            font-size: 0.8rem;
            font-weight: 300;
        }

        /* ===== BOUTONS MODERNISÉS ===== */
        .btn {
            border-radius: 0;
            padding: 0.55rem 1.2rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: var(--transition);
            border: none;
            font-size: 0.85rem;
        }

        .btn-marron {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
            box-shadow: 0 4px 15px rgba(184, 134, 11, 0.3);
        }

        .btn-marron:hover {
            background: linear-gradient(135deg, var(--marron-clair) 0%, var(--or-accent) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(184, 134, 11, 0.4);
            color: var(--noir-principal);
        }

        .btn-marron:active {
            transform: translateY(0);
        }

        .btn-light {
            background: rgba(255, 255, 255, 0.1);
            color: var(--marron-clair);
            border: 1px solid var(--marron-dore);
            backdrop-filter: blur(10px);
        }

        .btn-light:hover {
            background: rgba(184, 134, 11, 0.2);
            color: var(--or-accent);
            border-color: var(--marron-clair);
        }

        .btn-danger {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
            color: var(--blanc);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #DC143C 0%, #FF6347 100%);
            transform: translateY(-2px);
        }

        /* ===== TABLE MODERNISÉE ===== */
        .table-responsive {
            border-radius: 0;
            overflow: hidden;
        }

        .table {
            margin: 0;
            color: var(--blanc);
        }

        .table thead {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        .table thead th {
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 0.65rem 1rem;
            border: none;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .table tbody {
            background: var(--noir-secondaire);
        }

        .table tbody td {
            padding: 0.6rem 1rem;
            border-bottom: 1px solid rgba(184, 134, 11, 0.1);
            vertical-align: middle;
            font-weight: 300;
            font-size: 0.9rem;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: rgba(184, 134, 11, 0.1);
            transform: scale(1.01);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ===== BADGES DÉPARTEMENTS ===== */
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 0;
            font-weight: 500;
            letter-spacing: 0.3px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .badge-finance {
            background: linear-gradient(135deg, #DAA520 0%, #FFD700 100%);
            color: var(--noir-principal);
        }

        .badge-rh {
            background: linear-gradient(135deg, #B8860B 0%, #DAA520 100%);
            color: var(--noir-principal);
        }

        .badge-daj {
            background: linear-gradient(135deg, #8B6914 0%, #B8860B 100%);
            color: var(--blanc);
        }

        .badge-communication {
            background: linear-gradient(135deg, #F4A460 0%, #DAA520 100%);
            color: var(--noir-principal);
        }

        .badge-sg {
            background: linear-gradient(135deg, #CD853F 0%, #DAA520 100%);
            color: var(--noir-principal);
        }

        /* ===== ADRESSE MAC ===== */
        .mac-address {
            font-family: 'Courier New', monospace;
            font-weight: 500;
            color: var(--or-accent);
            letter-spacing: 1px;
        }

        /* ===== STATISTIQUES - GRAPHIQUE CIRCULAIRE ===== */
        .chart-wrapper {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
        }

        .pie-chart-container {
            position: relative;
            width: 180px;
            height: 180px;
            flex-shrink: 0;
        }

        .center-label {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 10;
        }

        .total-number {
            font-size: 36px;
            font-weight: bold;
            color: var(--marron-clair);
            font-family: 'Courier New', monospace;
        }

        .total-text {
            font-size: 10px;
            color: var(--or-pale);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .legend {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            flex: 1;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background-color: var(--noir-tertiaire);
            border: 1px solid rgba(184, 134, 11, 0.2);
            transition: var(--transition);
            cursor: pointer;
        }

        .legend-item:hover {
            transform: translateY(-1px);
            background-color: rgba(184, 134, 11, 0.1);
            border-color: var(--marron-dore);
        }

        .legend-color {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .legend-info {
            flex: 1;
        }

        .legend-label {
            font-size: 10px;
            color: var(--or-pale);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 400;
        }

        .legend-value {
            font-size: 18px;
            font-weight: bold;
            color: var(--blanc);
            font-family: 'Courier New', monospace;
        }

        .legend-percentage {
            font-size: 10px;
            color: var(--marron-clair);
            margin-left: 4px;
        }

        /* Couleurs pour chaque département */
        .color-finance { background-color: #2196F3; }
        .color-rh { background-color: #FF9800; }
        .color-daj { background-color: #9C27B0; }
        .color-comm { background-color: #F44336; }
        .color-sg { background-color: #00BCD4; }
        .color-dsi { background-color: #4CAF50; }
        .color-dircab { background-color: #FFC107; }

        /* ===== ANIMATIONS DE CHARGEMENT ===== */
        .loading {
            display: none;
        }

        .spinner-border {
            color: var(--marron-clair);
            width: 2.5rem;
            height: 2.5rem;
        }

        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: fadeIn 0.5s ease-out;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .card-title {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 1.25rem;
            }
            
            .chart-wrapper {
                flex-direction: column;
            }

            .pie-chart-container {
                width: 160px;
                height: 160px;
            }

            .total-number {
                font-size: 30px;
            }

            .legend {
                grid-template-columns: 1fr;
                width: 100%;
            }
        }

        /* ===== SCROLLBAR PERSONNALISÉE ===== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--noir-tertiaire);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--marron-dore);
            border-radius: 0;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--marron-clair);
        }

        /* ===== ICÔNES ===== */
        .fa-network-wired,
        .fa-plus,
        .fa-chart-bar,
        .fa-list,
        .fa-sync-alt,
        .fa-trash {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    
    <!-- BARRE DE STATUT MINISTÉRIELLE -->
    <div class="ministry-status-bar">
        <span class="status-indicator"></span>
        SYSTÈME SÉCURISÉ | MINISTÈRE DES MINES - RÉPUBLIQUE DE MADAGASCAR
    </div>
    
    <div class="container-fluid py-4">
        <!-- EN-TÊTE PRINCIPAL -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body" style="padding: 1rem 1.5rem;">
                        <div class="d-flex align-items-center">
                            <div class="ministry-emblem">
                                <i class="fas fa-gem" style="color: var(--noir-principal); font-size: 1.2rem;"></i>
                            </div>
                            <div class="ministry-header flex-grow-1">
                                <h1 class="card-title mb-0">
                                    Ministère des Mines - Système d'Authentification Réseau
                                </h1>
                                <div class="ministry-subtitle">
                                    Direction des Systèmes d'Information | Gestion des Accès MAC
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION AJOUT & STATISTIQUES -->
        <div class="row mb-3">
            <!-- FORMULAIRE D'AJOUT -->
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus"></i> Ajouter un appareil</h5>
                    </div>
                    <div class="card-body">
                        <form id="addDeviceForm">
                            <div class="mb-2">
                                <label for="mac_address" class="form-label">Adresse MAC</label>
                                <input type="text" class="form-control" id="mac_address" 
                                       placeholder="AA-BB-CC-DD-EE-FF" required>
                                <div class="form-text">Format attendu par pfSense: AA-BB-CC-DD-EE-FF (IETF)</div>
                            </div>
                            <div class="mb-3">
                                <label for="department" class="form-label">Département</label>
                                <select class="form-select" id="department" required>
                                    <option value="">Sélectionner un département</option>
                                    <option value="finance">Direction Administratif (DAF)</option>
                                    <option value="rh">Ressources Humaines (RH)</option>
                                    <option value="daj">Direction des Affaires Juridiques (DAJ)</option>
                                    <option value="communication">Communication</option>
                                    <option value="sg">Secrétariat Général (SG)</option>
                                    <option value="dsi">Direction Service Info (DSI)</option>
                                    <option value="dircab">Direction Cabinet (DIRCAB)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-marron w-100">
                                <span class="loading"><i class="fas fa-spinner fa-spin"></i></span>
                                <i class="fas fa-plus"></i> Ajouter l'appareil
                            </button>
                        </form>                    
                    </div>
                </div>
            </div>

            <!-- STATISTIQUES AVEC GRAPHIQUE CIRCULAIRE -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistiques</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-wrapper">
                            <div class="pie-chart-container">
                                <canvas id="pieChart" width="180" height="180"></canvas>
                                <div class="center-label">
                                    <div class="total-number" id="totalDevices">0</div>
                                    <div class="total-text">Total</div>
                                </div>
                            </div>

                            <div class="legend" id="statsLegend">
                                <div class="text-center">
                                    <div class="spinner-border" role="status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLEAU DES APPAREILS -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Appareils configurés</h5>
                        <button class="btn btn-light btn-sm" onclick="loadDevices()">
                            <i class="fas fa-sync-alt"></i> Actualiser
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead>
                                    <tr>
                                        <th>Adresse MAC</th>
                                        <th>Département</th>
                                        <th>Groupe</th>
                                        <th>Bande passante</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="devicesTable">
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <div class="spinner-border" role="status"></div>
                                            <div class="mt-2">Chargement des appareils...</div>
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
        // --- FONCTION DE CONVERSION IETF ---
        function formatToIETF(mac) {
            let clean = mac.replace(/[^a-fA-F0-9]/g, '');
            if (clean.length === 12) {
                return clean.match(/.{1,2}/g).join('-').toUpperCase();
            }
            return mac; 
        }

        $(document).ready(function() {
            loadDevices();
            loadStats();
        });

        function loadDevices() {
            $.post('radius_devices.php', {action: 'get_devices'}, function(response) {
                if (response.success) {
                    displayDevices(response.data);
                } else {
                    $('#devicesTable').html('<tr><td colspan="5" class="text-center text-danger">Erreur: ' + response.error + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#devicesTable').html('<tr><td colspan="5" class="text-center text-danger">Erreur de communication</td></tr>');
            });
        }

        function displayDevices(devices) {
            let html = '';
            if (devices.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-4" style="color: var(--or-pale);">Aucun appareil configuré</td></tr>';
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
                            <td><span class="badge ${departmentColors[device.department]}">${device.department.toUpperCase()}</span></td>
                            <td>${device.group}</td>
                            <td><strong>${device.bandwidth}</strong></td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="deleteDevice('${device.mac_address}')">
                                    <i class="fas fa-trash"></i> Supprimer
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
            const macIETF = formatToIETF(macInput);
            const department = $('#department').val();
            
            if(macIETF.length !== 17) {
                alert("L'adresse MAC saisie est invalide. Veuillez saisir 12 caractères hexadécimaux.");
                return;
            }

            $('.loading').show();
            $('button[type="submit"]').prop('disabled', true);
            
            $.post('radius_devices.php', {
                action: 'add_device',
                mac_address: macIETF,
                department: department
            }, function(response) {
                if (response.success) {
                    alert('✅ Appareil ajouté avec succès (Format IETF : ' + macIETF + ')');
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
                    
                    // Mettre à jour le nombre total
                    $('#totalDevices').text(devices.length);
                    
                    // Créer les données pour le graphique
                    const chartData = [
                        { label: 'Finance', value: stats.finance, color: '#2196F3' },
                        { label: 'RH', value: stats.rh, color: '#FF9800' },
                        { label: 'DAJ', value: stats.daj, color: '#9C27B0' },
                        { label: 'Comm', value: stats.communication, color: '#F44336' },
                        { label: 'SG', value: stats.sg, color: '#00BCD4' },
                        { label: 'DSI', value: stats.dsi, color: '#4CAF50' },
                        { label: 'DirCab', value: stats.dircab, color: '#FFC107' }
                    ];
                    
                    // Dessiner le graphique
                    drawPieChart(chartData, devices.length);
                    
                    // Mettre à jour la légende
                    updateLegend(chartData, devices.length);
                }
            }, 'json');
        }

        function drawPieChart(data, total) {
            const canvas = document.getElementById('pieChart');
            const ctx = canvas.getContext('2d');
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = 80;

            // Effacer le canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (total === 0) {
                // Dessiner un cercle gris si aucune donnée
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                ctx.fillStyle = '#3a3a3a';
                ctx.fill();
                
                // Cercle intérieur
                ctx.beginPath();
                ctx.arc(centerX, centerY, 50, 0, 2 * Math.PI);
                ctx.fillStyle = '#1a1a1a';
                ctx.fill();
                return;
            }

            // Filtrer les valeurs à 0
            const filteredData = data.filter(item => item.value > 0);
            let currentAngle = -Math.PI / 2; // Commencer en haut

            filteredData.forEach(item => {
                const sliceAngle = (item.value / total) * 2 * Math.PI;

                // Dessiner le segment
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fillStyle = item.color;
                ctx.fill();

                // Bordure entre les segments
                ctx.strokeStyle = '#1a1a1a';
                ctx.lineWidth = 2;
                ctx.stroke();

                currentAngle += sliceAngle;
            });

            // Dessiner le cercle intérieur (effet donut)
            ctx.beginPath();
            ctx.arc(centerX, centerY, 50, 0, 2 * Math.PI);
            ctx.fillStyle = '#1a1a1a';
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
                        <div class="legend-info">
                            <div class="legend-label">${item.label}</div>
                            <div>
                                <span class="legend-value">${item.value}</span>
                                <span class="legend-percentage">(${percentage}%)</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#statsLegend').html(html);
        }
    </script>
</body>
</html>