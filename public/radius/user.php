
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Appareils RADIUS - Vue Lecture Seule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/pages/radius-user.css?v=20260725">
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
    <script src="../assets/js/pages/radius-user.js?v=20260725"></script>
</body>
</html>