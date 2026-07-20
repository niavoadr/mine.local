<?php
// radius_interface_admin.php
// Version ADMIN (lecture seule - pas de formulaire d'ajout)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .department-badge {
            font-size: 0.8em;
            padding: 0.3rem 0.6rem;
        }
        .mac-address {
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        .loading {
            display: none;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body style="background-color: #F5F5DC;"> 
<style>
    /* Couleurs du thème marron et blanc */
    :root {
        --marron-fonce: #5C4033;
        --marron-moyen: #8B4513;
        --marron-clair: #A0522D;
        --blanc-creme: #FFFAF0;
        --blanc-casse: #F5F5DC;
        --couleur-texte: #333333;
    }

    .bg-marron-fonce { background-color: var(--marron-fonce) !important; color: var(--blanc-creme) !important; }
    .bg-marron-moyen { background-color: var(--marron-moyen) !important; color: var(--blanc-creme) !important; }
    .bg-marron-clair { background-color: var(--marron-clair) !important; color: var(--blanc-creme) !important; }
    .btn-marron {
        background-color: var(--marron-moyen);
        color: var(--blanc-creme);
        border: 1px solid var(--marron-fonce);
    }
    .btn-marron:hover {
        background-color: var(--marron-fonce);
        color: white;
    }
    .card-header { border-bottom: 2px solid var(--marron-fonce); }
    .table-marron thead { background-color: var(--marron-fonce); color: white; }
    .table-marron { border: 1px solid var(--marron-fonce); }
    .table-marron tr:hover { background-color: rgba(92, 64, 51, 0.1); }

    /* Couleurs des badges de département */
    .badge-finance { background-color: #A0522D; }
    .badge-rh { background-color: #8B4513; }
    .badge-daj { background-color: #5C4033; }
    .badge-communication { background-color: #D2B48C; color: var(--marron-fonce); }
    .badge-sg { background-color: #8B5A2B; }
    
    .card.shadow-sm {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }
</style>
    
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm" style="background-color: var(--blanc-creme); border: 1px solid var(--marron-fonce);">
                    <div class="card-body">
                        <h1 class="card-title mb-0" style="color: var(--marron-fonce);">
                            <i class="fas fa-network-wired" style="color: var(--marron-moyen);"></i>
                            Gestion des Appareils de l'entreprise
                        </h1>
                        <p class="card-text" style="color: var(--marron-moyen);">Authentification MAC par département avec priorisation</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADMIN VIEW - Pas de formulaire d'ajout, seulement les statistiques -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm" style="background-color: var(--blanc-creme); border: 1px solid var(--marron-fonce);">
                    <div class="card-header bg-marron-clair">
                        <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistiques</h5>
                    </div>
                    <div class="card-body">
                        <div id="stats" class="row text-center" style="color: var(--marron-fonce);">
                            <div class="col">
                                <div class="spinner-border" style="color: var(--marron-moyen);" role="status"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des appareils (lecture seule) -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm" style="background-color: var(--blanc-creme); border: 1px solid var(--marron-fonce);">
                    <div class="card-header bg-marron-fonce d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Appareils configurés</h5>
                        <button class="btn btn-light btn-sm" onclick="loadDevices()" style="background-color: var(--blanc-creme); color: var(--marron-fonce);">
                            <i class="fas fa-sync-alt"></i> Actualiser
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-marron">
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
                                        <td colspan="4" class="text-center py-4" style="color: var(--marron-moyen);">
                                            <div class="spinner-border" style="color: var(--marron-moyen);" role="status"></div>
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
                    $('#devicesTable').html('<tr><td colspan="4" class="text-center text-danger" style="color: var(--marron-fonce);">Erreur: ' + response.error + '</td></tr>');
                }
            }, 'json').fail(function() {
                $('#devicesTable').html('<tr><td colspan="4" class="text-center text-danger" style="color: var(--marron-fonce);">Erreur de communication avec le serveur</td></tr>');
            });
        }

        // Afficher les appareils (lecture seule, pas de bouton supprimer)
        function displayDevices(devices) {
            let html = '';
            if (devices.length === 0) {
                html = '<tr><td colspan="4" class="text-center" style="color: var(--marron-fonce); font-weight: bold;">Aucun appareil configuré</td></tr>';
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
                            <td><span class="badge ${departmentColors[device.department]} department-badge">${device.department.toUpperCase()}</span></td>
                            <td>${device.group}</td>
                            <td><strong>${device.bandwidth}</strong></td>
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
                        <div class="col"><h6 style="color: var(--marron-fonce);">Total</h6><h4 style="color: var(--marron-moyen);">${devices.length}</h4></div>
                        <div class="col"><h6 style="color: var(--marron-fonce);">Finance</h6><h4 style="color: var(--marron-fonce);">${stats.finance || 0}</h4></div>
                        <div class="col"><h6 style="color: var(--marron-fonce);">RH</h6><h4 style="color: var(--marron-fonce);">${stats.rh || 0}</h4></div>
                        <div class="col"><h6 style="color: var(--marron-fonce);">DAJ</h6><h4 style="color: var(--marron-fonce);">${stats.daj || 0}</h4></div>
                        <div class="col"><h6 style="color: var(--marron-fonce);">Comm</h6><h4 style="color: var(--marron-fonce);">${stats.communication || 0}</h4></div>
                        <div class="col"><h6 style="color: var(--marron-fonce);">SG</h6><h4 style="color: var(--marron-fonce);">${stats.sg || 0}</h4></div>
                    `;
                    
                    $('#stats').html(html);
                }
            }, 'json');
        }
    </script>
</body>
</html>