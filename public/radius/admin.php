<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/pages/radius-admin.css?v=20260725">
    <link rel="stylesheet" href="../assets/css/app/responsive.css?v=20260722">
    <link rel="stylesheet" href="../assets/css/app/animations.css?v=20260721">
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
                            <p class="text-muted mb-0">Enregistrement et autorisation des appareils par adresse MAC (pfSense / FreeRADIUS)</p>
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
                                <input type="text" class="form-control" id="mac_address" placeholder="Ex: XX-XX-XX-XX-XX-XX" required>
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
    <script src="../assets/js/pages/radius-admin.js?v=20260725"></script>
</body>
</html>