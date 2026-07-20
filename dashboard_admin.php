<?php
// PHP : Assurez-vous que la session est démarrée au tout début du script
session_start();

// Définition de l'ID de l'utilisateur connecté
$user_role_id = $_SESSION['role_lib'] ?? "";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RADIUS Dashboard - Tableau de Bord Ministére des Mines</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 50%, #D2691E 100%);
            min-height: 100vh;
            color: #1a1a1a;
            font-weight: 400;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 25px;
        }

        .header {
            background: #ffffff;
            border-radius: 8px;
            padding: 25px 35px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            border-bottom: 3px solid #8B4513;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .header-logo {
            width: 55px;
            height: 55px;
            object-fit: contain;
        }

        .header h1 {
            color: #1a1a1a;
            font-size: 1.75em;
            font-weight: 500;
            letter-spacing: -0.02em;
            text-align: center;
            margin-bottom: 0;
        }

        .header p {
            text-align: center;
            color: #666;
            font-size: 0.95em;
            font-weight: 400;
            margin-top: 6px;
        }

        .navigation {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .nav-tabs {
            display: flex;
            background: #fafafa;
            border-bottom: 1px solid #e5e5e5;
        }

        .nav-tab {
            flex: 1;
            padding: 16px 20px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: transparent;
            font-size: 0.95em;
            font-weight: 500;
            color: #666;
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
            letter-spacing: 0.01em;
        }

        .nav-tab:hover {
            background: #f5f5f5;
            color: #333;
        }

        .nav-tab.active {
            background: #ffffff;
            color: #8B4513;
            border-bottom-color: #8B4513;
        }

        .content {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 30px;
            min-height: 600px;
        }

        .content-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            font-size: 1.5em;
            font-weight: 500;
            color: #1a1a1a;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e5e5e5;
            letter-spacing: -0.01em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%);
            color: white;
            padding: 26px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(139, 69, 19, 0.12);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 69, 19, 0.2);
        }

        .stat-number {
            font-size: 2.6em;
            font-weight: 300;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 0.95em;
            font-weight: 400;
            opacity: 0.95;
            letter-spacing: 0.01em;
        }

        .device-list {
            background: #fafafa;
            border-radius: 8px;
            padding: 22px;
            margin-bottom: 18px;
            border: 1px solid #e5e5e5;
        }

        .device-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 3px solid #8B4513;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .device-mac {
            font-weight: 500;
            color: #1a1a1a;
            font-size: 0.95em;
        }

        .device-dept {
            color: #666;
            font-size: 0.9em;
            font-weight: 400;
        }

        .action-buttons {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 0.95em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.01em;
        }

        .btn-primary {
            background: #8B4513;
            color: white;
        }

        .btn-primary:hover {
            background: #6d3410;
            box-shadow: 0 2px 8px rgba(139, 69, 19, 0.2);
        }

        .btn-success {
            background: #A0522D;
            color: white;
        }

        .btn-success:hover {
            background: #884522;
            box-shadow: 0 2px 8px rgba(160, 82, 45, 0.2);
        }

        .btn-warning {
            background: #D2691E;
            color: white;
        }

        .btn-warning:hover {
            background: #b85a1a;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 500;
            color: #333;
            font-size: 0.9em;
            letter-spacing: 0.01em;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 13px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95em;
            transition: border-color 0.2s ease;
            color: #333;
            font-weight: 400;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.08);
        }

        .access-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .access-table th {
            background: #8B4513;
            color: white;
            padding: 13px 15px;
            text-align: left;
            font-weight: 500;
            font-size: 0.9em;
            letter-spacing: 0.02em;
        }

        .access-table td {
            padding: 11px 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
            font-size: 0.9em;
            font-weight: 400;
        }

        .access-table tr:hover {
            background: #fafafa;
        }

        .status-online {
            color: #28a745;
            font-weight: 500;
        }

        .status-offline {
            color: #dc3545;
            font-weight: 500;
        }

        .alert {
            padding: 13px 17px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 0.95em;
        }

        .alert-info {
            background: #fff8e6;
            border: 1px solid #f0dca8;
            color: #6d3410;
        }

        h3 {
            font-size: 1.15em;
            font-weight: 500;
            color: #1a1a1a;
            margin-bottom: 16px;
            letter-spacing: -0.01em;
        }
    </style>
</head>
<!-- Avant </body> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="manager.js"></script>
<body>
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <div class="header-content">
                <img src="images/logomine.jpg" alt="Logo Ministère des Mines" class="header-logo">
                <h1>Tableau de Bord Ministére des Mines</h1>
            </div>
            <p>Système de Gestion des Accès Réseau </p>
        </div>

        <!-- Barre de navigation -->
        <div class="navigation">
            <div class="nav-tabs">
                <button class="nav-tab active" onclick="switchTab('hosts')">
                    🏢 Hôtes de l'Entreprise
                </button>
                <button class="nav-tab" onclick="switchTab('strangers')">
                    👥 Étrangers
                </button>
                <button class="nav-tab" onclick="switchTab('manager')">
                    ⚙️ Gestionnaire de Compte
                </button>
                <button class="nav-tab" onclick="switchTab('alerts')">
    🚨 Alertes
</button>
<button class="nav-tab logout-btn" onclick="logout()">
            ⏻ 
        </button>

            </div>
        </div>

        <!-- Contenu dynamique -->
         
<!-- Section Hôtes de l'Entreprise2 - Interface RADIUS Existante -->
            <div id="hosts-content" class="content-section active">
    <iframe
        src="radius_interface.php?role_lib=<?php echo $user_role_id; ?>" 
        width="100%" 
        height="800" 
        frameborder="0"
        style="border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
        Chargement de l'interface RADIUS...
    </iframe>
</div>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Section Étrangers - Ministère des Mines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
            --rouge-danger: #8B0000;
            --vert-succes: #006400;
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
        .container {
            max-width: 1400px;
            padding: 1.5rem;
        }

        .content-section {
            background: var(--noir-secondaire);
            border: 1px solid var(--marron-dore);
            padding: 0;
            box-shadow: 0 8px 32px rgba(184, 134, 11, 0.1);
        }

        /* ===== EN-TÊTE SECTION ===== */
        .section-title {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
            padding: 1rem 1.5rem;
            margin: 0;
            font-weight: 500;
            font-size: 1.3rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--or-accent);
        }

        .section-title i {
            margin-right: 0.75rem;
        }

        /* ===== NAVIGATION SOUS-SECTIONS ===== */
        .subsection-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0;
            background: var(--noir-tertiaire);
            border-bottom: 1px solid var(--marron-dore);
            position: relative;
        }
        
        .subsection-btn {
            flex: 1;
            padding: 0.85rem 1.5rem;
            background: transparent;
            color: var(--or-pale);
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(184, 134, 11, 0.2);
        }

        .subsection-btn:last-child {
            border-right: none;
        }
        
        .subsection-btn:hover {
            background: rgba(184, 134, 11, 0.1);
            color: var(--marron-clair);
        }
        
        .subsection-btn.active {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
            font-weight: 600;
        }
        
        .subsection-btn i {
            font-size: 1rem;
            margin-right: 0.5rem;
        }

        /* ===== CONTENU DES SECTIONS ===== */
        .subsection-content {
            display: none;
            padding: 1.5rem;
            animation: fadeIn 0.3s ease-in;
        }
        
        .subsection-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ===== TITRES DE SOUS-SECTION ===== */
        .subsection-content h4 {
            color: var(--marron-clair);
            font-weight: 500;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(184, 134, 11, 0.3);
        }

        /* ===== CARTES STATISTIQUES ===== */
        .stats-card {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--or-accent);
            transition: var(--transition);
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(184, 134, 11, 0.3);
        }

        .stats-card.danger {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
            color: var(--blanc);
            border-color: #DC143C;
        }

        .stats-card.warning {
            background: linear-gradient(135deg, var(--or-pale) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        .stats-card h5 {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-card h2 {
            font-size: 2.5rem;
            font-weight: 600;
            margin: 0;
            font-family: 'Courier New', monospace;
        }

        .stats-card i {
            margin-right: 0.5rem;
        }

        /* ===== CARTES ===== */
        .card {
            background: var(--noir-tertiaire);
            border: 1px solid var(--marron-dore);
            margin-bottom: 1.25rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--noir-principal) 0%, var(--noir-tertiaire) 100%);
            color: var(--marron-clair);
            font-weight: 500;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--marron-dore);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-header i {
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        /* ===== FORMULAIRES ===== */
        .form-label {
            color: var(--marron-clair);
            font-weight: 400;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .form-control, .form-select {
            background: var(--noir-secondaire);
            border: 1px solid var(--marron-dore);
            border-radius: 0;
            color: var(--blanc);
            padding: 0.6rem 0.9rem;
            font-weight: 300;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            background: var(--noir-principal);
            border-color: var(--marron-clair);
            box-shadow: 0 0 0 3px rgba(184, 134, 11, 0.15);
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

        /* ===== BOUTONS ===== */
        .btn {
            border-radius: 0;
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: var(--transition);
            border: none;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--marron-clair) 0%, var(--or-accent) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(184, 134, 11, 0.4);
            color: var(--noir-principal);
        }

        .btn-danger {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
            color: var(--blanc);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #DC143C 0%, #FF6347 100%);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #006400 0%, #228B22 100%);
            color: var(--blanc);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #228B22 0%, #32CD32 100%);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--or-pale) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        /* ===== TABLEAUX ===== */
        .table-responsive {
            overflow-x: auto;
            border: 1px solid var(--marron-dore);
        }

        .table {
            margin: 0;
            color: var(--blanc);
            font-size: 0.85rem;
        }

        .table thead {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        .table thead th {
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 0.65rem 0.9rem;
            border: none;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .table tbody {
            background: var(--noir-secondaire);
        }

        .table tbody td {
            padding: 0.6rem 0.9rem;
            border-bottom: 1px solid rgba(184, 134, 11, 0.1);
            vertical-align: middle;
            font-weight: 300;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: rgba(184, 134, 11, 0.1);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* ===== BADGES ===== */
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 0;
            font-weight: 500;
            letter-spacing: 0.3px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .bg-danger {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%) !important;
        }

        .bg-warning {
            background: linear-gradient(135deg, var(--or-pale) 0%, var(--marron-clair) 100%) !important;
            color: var(--noir-principal) !important;
        }

        .bg-success {
            background: linear-gradient(135deg, #006400 0%, #228B22 100%) !important;
        }

        .bg-info {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%) !important;
            color: var(--noir-principal) !important;
        }

        .bg-primary {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--or-accent) 100%) !important;
            color: var(--noir-principal) !important;
        }

        .bg-secondary {
            background: var(--noir-tertiaire) !important;
            color: var(--or-pale) !important;
            border: 1px solid var(--marron-dore);
        }

        .bg-dark {
            background: var(--noir-principal) !important;
            border: 1px solid var(--marron-dore);
        }

        /* ===== CODES ===== */
        code {
            background: var(--noir-principal);
            color: var(--or-accent);
            padding: 0.2rem 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            border: 1px solid var(--marron-dore);
        }

        /* ===== TEXTES DE STATUT ===== */
        .text-success {
            color: #32CD32 !important;
        }

        .text-danger {
            color: #FF6347 !important;
        }

        .text-warning {
            color: var(--or-pale) !important;
        }

        .text-muted {
            color: var(--or-pale) !important;
        }

        /* ===== SPINNER ===== */
        .fa-spinner {
            color: var(--marron-clair);
        }

        /* ===== SCROLLBAR ===== */
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .subsection-btn {
                font-size: 0.75rem;
                padding: 0.75rem 1rem;
            }

            .subsection-btn i {
                display: block;
                margin: 0 auto 0.25rem;
                font-size: 1.2rem;
            }

            .stats-card h2 {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

<!-- BARRE DE STATUT MINISTÉRIELLE -->
<div class="ministry-status-bar">
    <span class="status-indicator"></span>
    SYSTÈME SÉCURISÉ | GESTION DES ACCÈS ÉTRANGERS - MINISTÈRE DES MINES
</div>

<div class="container mt-4">
    <div id="strangers-content" class="content-section">
        <h2 class="section-title text-center">
            <i class="fas fa-user-shield"></i>Gestion des Accès Étrangers
        </h2>
        
        <!-- Navigation Bar for Sub-sections -->
        <div class="subsection-nav">
            <button class="subsection-btn active" data-section="visitor">
                <i class="fas fa-users"></i>Visiteurs
            </button>
            <button class="subsection-btn" data-section="blacklist">
                <i class="fas fa-ban"></i>Liste Noire
            </button>
            <button class="subsection-btn" data-section="intrusion">
                <i class="fas fa-exclamation-triangle"></i>Intrusions
            </button>
        </div>

        <!-- Content Sections -->
            
        <!-- VISITOR SECTION -->
        <div class="subsection-content active" id="visitor-section">
            <h4>
                <i class="fas fa-history me-2"></i>Historique des Accès Visiteurs
            </h4>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="startDate" class="form-label">Date de début</label>
                    <input type="date" class="form-control" id="startDate">
                </div>
                <div class="col-md-4">
                    <label for="endDate" class="form-label">Date de fin</label>
                    <input type="date" class="form-control" id="endDate">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary w-100" id="filter-btn">
                        <i class="fas fa-filter"></i> Filtrer
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nom d'utilisateur</th>
                            <th>Adresse MAC</th>
                            <th>Adresse IP</th>
                            <th>Début de session</th>
                            <th>Fin de session</th>
                            <th>Durée</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody id="history-table">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BLACKLIST SECTION -->
        <div class="subsection-content" id="blacklist-section">
            <h4>
                <i class="fas fa-shield-alt me-2"></i>Gestion de la Liste Noire
            </h4>
            
            <!-- Stats Cards -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="stats-card danger">
                        <h5><i class="fas fa-ban me-2"></i>Appareils Bloqués</h5>
                        <h2 id="blocked-count">0</h2>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card warning">
                        <h5><i class="fas fa-clock me-2"></i>Bloqués Aujourd'hui</h5>
                        <h2 id="blocked-today">0</h2>
                    </div>
                </div>
            </div>

            <!-- Add to Blacklist Form -->
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-plus-circle me-2"></i>Ajouter à la Liste Noire
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5">
                            <label for="blacklist-mac" class="form-label">Adresse MAC</label>
                            <input type="text" class="form-control" id="blacklist-mac" placeholder="XX:XX:XX:XX:XX:XX">
                        </div>
                        <div class="col-md-5">
                            <label for="blacklist-reason" class="form-label">Raison</label>
                            <select class="form-select" id="blacklist-reason">
                                <option value="">Sélectionner...</option>
                                <option value="abuse">Abus de connexion</option>
                                <option value="security">Menace de sécurité</option>
                                <option value="policy">Violation de politique</option>
                                <option value="malware">Activité malveillante</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-danger w-100" id="add-blacklist-btn">
                                <i class="fas fa-ban me-1"></i> Bloquer
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Blacklist Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Adresse MAC</th>
                            <th>Adresse IP</th>
                            <th>Raison</th>
                            <th>Date de blocage</th>
                            <th>Tentatives bloquées</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="blacklist-table">
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- INTRUSION SECTION -->
        <div class="subsection-content" id="intrusion-section">
            <h4>
                <i class="fas fa-bug me-2"></i>Détection et Surveillance des Intrusions
            </h4>
            
            <!-- Alert Stats -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="stats-card danger">
                        <h5><i class="fas fa-shield-alt me-2"></i>Alertes Critiques</h5>
                        <h2 id="critical-alerts">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card warning">
                        <h5><i class="fas fa-exclamation-circle me-2"></i>Alertes Moyennes</h5>
                        <h2 id="medium-alerts">0</h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h5><i class="fas fa-eye me-2"></i>Tentatives Suspectes</h5>
                        <h2 id="suspicious-attempts">0</h2>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fas fa-filter me-2"></i>Filtres de Recherche
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="intrusion-severity" class="form-label">Sévérité</label>
                            <select class="form-select" id="intrusion-severity">
                                <option value="">Tous</option>
                                <option value="critical">Critique</option>
                                <option value="high">Élevée</option>
                                <option value="medium">Moyenne</option>
                                <option value="low">Faible</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="intrusion-type" class="form-label">Type d'Intrusion</label>
                            <select class="form-select" id="intrusion-type">
                                <option value="">Tous</option>
                                <option value="brute_force">Force brute</option>
                                <option value="unauthorized">Accès non autorisé</option>
                                <option value="spoofing">Usurpation MAC/IP</option>
                                <option value="dos">Déni de service</option>
                                <option value="scan">Scan de réseau</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="intrusion-date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="intrusion-date">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary w-100" id="intrusion-filter-btn">
                                <i class="fas fa-search"></i> Rechercher
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Intrusion Alerts Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Type d'Intrusion</th>
                            <th>Sévérité</th>
                            <th>Source (IP/MAC)</th>
                            <th>Description</th>
                            <th>Source Info</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="intrusion-table">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Load initial data for all sections
        loadHistory();
        loadBlacklist();
        loadBlacklistStats();
        loadIntrusions();
        loadIntrusionStats();
        
        // Auto-refresh intrusions every 10 seconds
        setInterval(function() {
            if ($('#intrusion-section').hasClass('active')) {
                loadIntrusions();
                loadIntrusionStats();
            }
        }, 10000);
        
        // Event listeners
        $("#filter-btn").on('click', loadHistory);
        $("#add-blacklist-btn").on('click', addToBlacklist);
        $("#intrusion-filter-btn").on('click', loadIntrusions);
        
        // Section switching
        $('.subsection-btn').on('click', function() {
            const section = $(this).data('section');
            
            // Update button states
            $('.subsection-btn').removeClass('active');
            $(this).addClass('active');
            
            // Update section visibility
            $('.subsection-content').removeClass('active');
            $(`#${section}-section`).addClass('active');
            
            // Load data for the selected section if needed
            if (section === 'blacklist') {
                loadBlacklist();
                loadBlacklistStats();
            } else if (section === 'intrusion') {
                loadIntrusions();
                loadIntrusionStats();
            }
        });
    });

    // ==================== VISITOR FUNCTIONS ====================
    function loadHistory() {
        const startDate = $('#startDate').val();
        const endDate = $('#endDate').val();
        
        $('#history-table').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...</td></tr>');
        
        $.ajax({
            url: 'history.php',
            type: 'POST',
            data: {
                action: 'get_history',
                start_date: startDate,
                end_date: endDate
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayHistory(response.data);
                } else {
                    $('#history-table').html('<tr><td colspan="7" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Erreur AJAX: " + status + ", " + error);
                $('#history-table').html('<tr><td colspan="7" class="text-center text-danger py-4"><i class="fas fa-times-circle me-2"></i>Une erreur de communication est survenue.</td></tr>');
            }
        });
    }
    
    function displayHistory(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucune connexion trouvée pour la période sélectionnée.</td></tr>';
        } else {
            records.forEach(function(record) {
                const status = record.acctstoptime ? 'Déconnecté' : 'Actif';
                const statusClass = record.acctstoptime ? 'text-danger' : 'text-success';
                const timeLeft = calculateTimeLeft(record.acctstarttime, record.acctstoptime);
                
                html += `
                    <tr>
                        <td>${record.username}</td>
                        <td><code>${record.callingstationid}</code></td>
                        <td>${record.framedipaddress || 'N/A'}</td>
                        <td>${record.acctstarttime}</td>
                        <td>${record.acctstoptime || 'En cours'}</td>
                        <td>${formatDuration(record.acctsessiontime)}</td>
                        <td class="${statusClass}">
                            ${status}
                            ${status === 'Actif' ? `<br/><small>(${timeLeft} restants)</small>` : ''}
                        </td>
                    </tr>
                `;
            });
        }
        $('#history-table').html(html);
    }

    function calculateTimeLeft(startTime, stopTime) {
        if (stopTime) return '';
        const sessionDuration = 2 * 60;
        const now = new Date();
        const start = new Date(startTime);
        const elapsedSeconds = (now - start) / 1000;
        const remainingSeconds = sessionDuration - elapsedSeconds;
        if (remainingSeconds <= 0) {
            return 'Expiré';
        }
        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = Math.floor(remainingSeconds % 60);
        return `${minutes}min ${seconds}s`;
    }
    
    function formatDuration(seconds) {
        if (seconds === null || seconds === undefined || isNaN(seconds)) return 'N/A';
        const totalSeconds = parseInt(seconds);
        if (totalSeconds < 0) return 'N/A';
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const sec = totalSeconds % 60;
        let result = [];
        if (hours > 0) result.push(`${hours}h`);
        if (minutes > 0 || hours > 0) result.push(`${minutes}min`);
        result.push(`${sec}s`);
        return result.join(' ');
    }

    // ==================== BLACKLIST FUNCTIONS ====================
    function loadBlacklist() {
        $('#blacklist-table').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...</td></tr>');
        
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: { action: 'get_blacklist' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayBlacklist(response.data);
                } else {
                    $('#blacklist-table').html('<tr><td colspan="6" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function() {
                $('#blacklist-table').html('<tr><td colspan="6" class="text-center text-danger py-4"><i class="fas fa-times-circle me-2"></i>Une erreur de communication est survenue.</td></tr>');
            }
        });
    }

    function displayBlacklist(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucun appareil bloqué.</td></tr>';
        } else {
            records.forEach(function(record) {
                html += `
                    <tr>
                        <td><code>${record.mac_address}</code></td>
                        <td>${record.ip_address || 'N/A'}</td>
                        <td><span class="badge bg-danger">${record.reason}</span></td>
                        <td>${record.blocked_date}</td>
                        <td><span class="badge bg-warning">${record.blocked_attempts || 0}</span></td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="unblockDevice('${record.mac_address}')">
                                <i class="fas fa-unlock"></i> Débloquer
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#blacklist-table').html(html);
    }

    function loadBlacklistStats() {
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: { action: 'get_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#blocked-count').text(response.data.total || 0);
                    $('#blocked-today').text(response.data.today || 0);
                }
            }
        });
    }

    function addToBlacklist() {
        const mac = $('#blacklist-mac').val();
        const reason = $('#blacklist-reason').val();
        
        if (!mac || !reason) {
            alert('Veuillez remplir tous les champs');
            return;
        }
        
        $.ajax({
            url: 'blacklist.php',
            type: 'POST',
            data: {
                action: 'add_blacklist',
                mac_address: mac,
                reason: reason
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Appareil ajouté à la liste noire');
                    $('#blacklist-mac').val('');
                    $('#blacklist-reason').val('');
                    loadBlacklist();
                    loadBlacklistStats();
                } else {
                    alert('Erreur: ' + response.message);
                }
            }
        });
    }

    function unblockDevice(mac) {
        if (confirm('Voulez-vous vraiment débloquer cet appareil ?')) {
            $.ajax({
                url: 'blacklist.php',
                type: 'POST',
                data: {
                    action: 'remove_blacklist',
                    mac_address: mac
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Appareil débloqué avec succès');
                        loadBlacklist();
                        loadBlacklistStats();
                    } else {
                        alert('Erreur: ' + response.message);
                    }
                }
            });
        }
    }

    // ==================== INTRUSION FUNCTIONS ====================
    function loadIntrusions() {
        const severity = $('#intrusion-severity').val();
        const type = $('#intrusion-type').val();
        const date = $('#intrusion-date').val();
        
        $('#intrusion-table').html('<tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Chargement des données...</td></tr>');
        
        $.ajax({
            url: 'intrusion.php',
            type: 'POST',
            data: {
                action: 'get_intrusions',
                severity: severity,
                type: type,
                date: date
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayIntrusions(response.data);
                } else {
                    $('#intrusion-table').html('<tr><td colspan="7" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>Erreur: ' + response.message + '</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error("Erreur AJAX intrusions: " + status + ", " + error);
                console.log("Response:", xhr.responseText);
                $('#intrusion-table').html('<tr><td colspan="7" class="text-center text-danger py-4"><i class="fas fa-times-circle me-2"></i>Une erreur de communication est survenue. Vérifiez les permissions des fichiers de logs.</td></tr>');
            }
        });
    }

    function displayIntrusions(records) {
        let html = '';
        if (records.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Aucune intrusion détectée.</td></tr>';
        } else {
            records.forEach(function(record) {
                const severityBadge = getSeverityBadge(record.severity);
                const sourceInfoBadge = getSourceInfoBadge(record.source_info);
                
                html += `
                    <tr>
                        <td>${record.timestamp}</td>
                        <td><span class="badge bg-info">${record.type}</span></td>
                        <td>${severityBadge}</td>
                        <td>
                            <small><strong>IP:</strong> ${record.ip_address || 'N/A'}</small><br/>
                            <small><strong>MAC:</strong> <code>${record.mac_address || 'N/A'}</code></small>
                        </td>
                        <td><small>${record.description}</small></td>
                        <td>${sourceInfoBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="blockFromIntrusion('${record.mac_address}', '${record.type}')">
                                <i class="fas fa-ban"></i> Bloquer
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#intrusion-table').html(html);
    }

    function loadIntrusionStats() {
        $.ajax({
            url: 'intrusion.php',
            type: 'POST',
            data: { action: 'get_stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#critical-alerts').text(response.data.critical || 0);
                    $('#medium-alerts').text(response.data.medium || 0);
                    $('#suspicious-attempts').text(response.data.suspicious || 0);
                }
            },
            error: function(xhr, status, error) {
                console.error("Erreur stats: " + status);
            }
        });
    }

    function getSeverityBadge(severity) {
        const badges = {
            'critical': '<span class="badge bg-danger"><i class="fas fa-exclamation-circle"></i> Critique</span>',
            'high': '<span class="badge bg-danger">Élevée</span>',
            'medium': '<span class="badge bg-warning">Moyenne</span>',
            'low': '<span class="badge bg-info">Faible</span>'
        };
        return badges[severity] || '<span class="badge bg-secondary">Inconnue</span>';
    }

    function getSourceInfoBadge(source) {
        const badges = {
            'Snort': '<span class="badge bg-primary"><i class="fas fa-shield-alt"></i> Snort</span>',
            'Firewall': '<span class="badge bg-secondary"><i class="fas fa-fire"></i> Firewall</span>',
            'Fail2ban': '<span class="badge bg-danger"><i class="fas fa-ban"></i> Fail2ban</span>',
            'Manual': '<span class="badge bg-dark"><i class="fas fa-user"></i> Manuel</span>'
        };
        return badges[source] || '<span class="badge bg-info">Autre</span>';
    }

    function blockFromIntrusion(mac, type) {
        if (mac === 'N/A') {
            alert('Impossible de bloquer: adresse MAC non disponible');
            return;
        }
        
        if (confirm('Voulez-vous bloquer cet appareil suite à cette intrusion ?')) {
            $.ajax({
                url: 'blacklist.php',
                type: 'POST',
                data: {
                    action: 'add_blacklist',
                    mac_address: mac,
                    reason: 'Intrusion détectée: ' + type
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Appareil bloqué avec succès');
                        loadIntrusions();
                    } else {
                        alert('Erreur: ' + response.message);
                    }
                }
            });
        }
    }
</script>

</body>
</html>
                <!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de Compte - Ministère des Mines</title>
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
            --rouge-danger: #8B0000;
            --vert-succes: #006400;
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
        .container {
            max-width: 1400px;
            padding: 1.5rem;
        }

        .content-section {
            background: var(--noir-secondaire);
            border: 1px solid var(--marron-dore);
            padding: 0;
            box-shadow: 0 8px 32px rgba(184, 134, 11, 0.1);
        }

        /* ===== EN-TÊTE SECTION ===== */
        .section-title {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
            padding: 1rem 1.5rem;
            margin: 0;
            font-weight: 500;
            font-size: 1.3rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--or-accent);
        }

        /* ===== BOUTON DÉCONNEXION ===== */
        .logout-container {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
        }

        .btn-logout {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
            color: var(--blanc);
            border: 1px solid #DC143C;
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: var(--transition);
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #DC143C 0%, #FF6347 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 20, 60, 0.4);
            color: var(--blanc);
        }

        /* ===== ALERTES ===== */
        .alert {
            margin: 1.5rem;
            padding: 1rem 1.25rem;
            border: none;
            font-size: 0.9rem;
            display: none;
        }

        .alert-success {
            background: linear-gradient(135deg, #006400 0%, #228B22 100%);
            color: var(--blanc);
            border-left: 4px solid #32CD32;
        }

        .alert-error {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
            color: var(--blanc);
            border-left: 4px solid #FF6347;
        }

        /* ===== GRILLE STATISTIQUES ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            padding: 1.5rem;
            background: var(--noir-tertiaire);
            border-bottom: 1px solid var(--marron-dore);
        }

        .stat-card {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            padding: 1.25rem;
            text-align: center;
            border: 1px solid var(--or-accent);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(184, 134, 11, 0.4);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--noir-principal);
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--noir-principal);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== GRILLE PRINCIPALE ===== */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            padding: 1.5rem;
        }

        /* ===== SECTIONS ===== */
        .section-card {
            background: var(--noir-tertiaire);
            border: 1px solid var(--marron-dore);
            padding: 0;
        }

        .section-header {
            background: linear-gradient(135deg, var(--noir-principal) 0%, var(--noir-tertiaire) 100%);
            color: var(--marron-clair);
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--marron-dore);
            font-weight: 500;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-body {
            padding: 1.25rem;
        }

        /* ===== FORMULAIRES ===== */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            color: var(--marron-clair);
            font-weight: 400;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            background: var(--noir-secondaire);
            border: 1px solid var(--marron-dore);
            border-radius: 0;
            color: var(--blanc);
            padding: 0.6rem 0.9rem;
            font-weight: 300;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus {
            background: var(--noir-principal);
            border-color: var(--marron-clair);
            box-shadow: 0 0 0 3px rgba(184, 134, 11, 0.15);
            outline: none;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 300;
        }

        .form-group select option {
            background: var(--noir-tertiaire);
            color: var(--blanc);
        }

        /* ===== BOUTONS ===== */
        .btn {
            border-radius: 0;
            padding: 0.6rem 1.2rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: var(--transition);
            border: none;
            font-size: 0.85rem;
            text-transform: uppercase;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
            width: 100%;
            margin-top: 0.5rem;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--marron-clair) 0%, var(--or-accent) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(184, 134, 11, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-success {
            background: linear-gradient(135deg, #006400 0%, #228B22 100%);
            color: var(--blanc);
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #228B22 0%, #32CD32 100%);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
            color: var(--blanc);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #DC143C 0%, #FF6347 100%);
            transform: translateY(-2px);
        }

        /* ===== SPINNER ===== */
        .ajax-spinner {
            display: none;
            margin-right: 0.5rem;
        }

        .ajax-loading {
            display: none;
            text-align: center;
            padding: 2rem;
            color: var(--or-pale);
            font-size: 1rem;
        }

        /* ===== CONTAINER UTILISATEURS ===== */
        #ajax-users-container {
            background: var(--noir-secondaire);
        }

        /* ===== TABLEAU UTILISATEURS ===== */
        #ajax-users-container table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        #ajax-users-container table thead {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
        }

        #ajax-users-container table thead th {
            padding: 0.65rem 0.9rem;
            color: var(--noir-principal);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            text-align: left;
        }

        #ajax-users-container table tbody {
            background: var(--noir-secondaire);
        }

        #ajax-users-container table tbody td {
            padding: 0.6rem 0.9rem;
            color: var(--blanc);
            font-weight: 300;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(184, 134, 11, 0.1);
        }

        #ajax-users-container table tbody tr {
            transition: var(--transition);
        }

        #ajax-users-container table tbody tr:hover {
            background: rgba(184, 134, 11, 0.1);
        }

        #ajax-users-container table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Styles pour les badges dans le tableau */
        #ajax-users-container .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 0;
            font-weight: 500;
            letter-spacing: 0.3px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        #ajax-users-container .badge-success {
            background: linear-gradient(135deg, #006400 0%, #228B22 100%);
            color: var(--blanc);
        }

        #ajax-users-container .badge-warning {
            background: linear-gradient(135deg, var(--or-pale) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        #ajax-users-container .badge-danger {
            background: linear-gradient(135deg, #8B0000 0%, #DC143C 100%);
            color: var(--blanc);
        }

        #ajax-users-container .badge-info {
            background: linear-gradient(135deg, var(--marron-dore) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        /* Boutons dans le tableau */
        #ajax-users-container .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
        }

        #ajax-users-container .btn-warning {
            background: linear-gradient(135deg, var(--or-pale) 0%, var(--marron-clair) 100%);
            color: var(--noir-principal);
        }

        #ajax-users-container .btn-warning:hover {
            background: linear-gradient(135deg, var(--marron-clair) 0%, var(--or-accent) 100%);
            transform: translateY(-2px);
        }

        /* Messages vides */
        #ajax-users-container .text-center {
            text-align: center;
            color: var(--or-pale);
            padding: 2rem;
        }

        #ajax-users-container .text-muted {
            color: var(--or-pale) !important;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .logout-container {
                position: relative;
                top: 0;
                right: 0;
                padding: 1rem;
                text-align: center;
            }
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

        .section-card {
            animation: fadeIn 0.5s ease-out;
        }

        /* ===== SCROLLBAR ===== */
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
    </style>
</head>
<body>

<!-- BARRE DE STATUT MINISTÉRIELLE -->
<div class="ministry-status-bar">
    <span class="status-indicator"></span>
    SYSTÈME SÉCURISÉ | GESTIONNAIRE DE COMPTES - MINISTÈRE DES MINES
</div>

<div class="container mt-4">
    <div id="manager-content" class="content-section active">
        <!-- EN-TÊTE -->
        <div style="position: relative;">
            <h2 class="section-title">Gestionnaire de Compte</h2>
            
            <!-- BOUTON DÉCONNEXION -->
            <div class="logout-container">
                <button onclick="confirmLogout()" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </button>
            </div>
        </div>

        <!-- ALERTES -->
        <div id="alert-success" class="alert alert-success">
            <strong><i class="fas fa-check-circle"></i> Succès :</strong> <span id="success-message"></span>
        </div>
        <div id="alert-error" class="alert alert-error">
            <strong><i class="fas fa-exclamation-circle"></i> Erreur :</strong> <span id="error-message"></span>
        </div>

        <!-- STATISTIQUES -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="stat-total-users">-</div>
                <div class="stat-label"><i class="fas fa-users"></i> Total Utilisateurs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-active-users">-</div>
                <div class="stat-label"><i class="fas fa-user-check"></i> Utilisateurs Actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="stat-total-roles">-</div>
                <div class="stat-label"><i class="fas fa-user-tag"></i> Rôles Disponibles</div>
            </div>
        </div>

        <!-- GRILLE PRINCIPALE -->
        <div class="main-grid">
            <!-- SECTION CRÉATION -->
            <div class="section-card">
                <div class="section-header">
                    <span><i class="fas fa-user-plus"></i> Créer un Nouveau Compte</span>
                </div>
                <div class="section-body">
                    <form id="ajax-user-form">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Nom d'utilisateur</label>
                            <input type="text" id="ajax_nom_utilisateur" name="nom_utilisateur" required placeholder="Entrez le nom d'utilisateur">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="ajax_email" name="email" required placeholder="utilisateur@entreprise.com">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Mot de passe</label>
                            <input type="password" id="ajax_mot_de_passe" name="mot_de_passe" required placeholder="Mot de passe sécurisé">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Département</label>
                            <select id="ajax_id_departement" name="id_departement" required>
                                <option value="">Chargement...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Rôle</label>
                            <select id="ajax_id_role" name="id_role" required>
                                <option value="">Chargement...</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="ajax-btn-create">
                            <span class="ajax-spinner"><i class="fas fa-spinner fa-spin"></i></span>
                            <i class="fas fa-plus-circle"></i> Créer le Compte
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- SECTION UTILISATEURS EXISTANTS -->
            <div class="section-card">
                <div class="section-header">
                    <span><i class="fas fa-users-cog"></i> Utilisateurs Existants</span>
                    <button type="button" class="btn btn-success" id="ajax-btn-refresh">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </button>
                </div>
                <div class="section-body">
                    <div id="ajax-users-loading" class="ajax-loading">
                        <i class="fas fa-spinner fa-spin"></i> Chargement des utilisateurs...
                    </div>
                    
                    <div id="ajax-users-container">
                        <!-- Les utilisateurs seront chargés ici via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="manager.js"></script>

<script>
    function confirmLogout() {
        if (confirm("⚠️ Êtes-vous sûr de vouloir vous déconnecter ?")) {
            window.location.href = "logout.php";
        }
    }

    function switchTab(tabName) {
        // Masquer toutes les sections
        const sections = document.querySelectorAll('.content-section');
        sections.forEach(section => section.classList.remove('active'));
        
        // Désactiver tous les onglets
        const tabs = document.querySelectorAll('.nav-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        
        // Activer la section et l'onglet sélectionnés
        document.getElementById(tabName + '-content').classList.add('active');
        event.target.classList.add('active');
    }

    // Animation des statistiques au chargement
    window.addEventListener('load', function() {
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(stat => {
            const finalNumber = stat.textContent;
            if (finalNumber !== '-') {
                stat.textContent = '0';
                
                // Animation simple des nombres
                let current = 0;
                const increment = parseInt(finalNumber) / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= parseInt(finalNumber)) {
                        stat.textContent = finalNumber;
                        clearInterval(timer);
                    } else {
                        stat.textContent = Math.floor(current);
                    }
                }, 30);
            }
        });
    });

    // Simulation de données en temps réel (optionnel)
    setInterval(() => {
        const activeDevices = document.querySelector('.stat-number');
        if (activeDevices && activeDevices.textContent !== '-') {
            let current = parseInt(activeDevices.textContent);
            // Variation aléatoire de ±2
            let variation = Math.floor(Math.random() * 5) - 2;
            let newValue = Math.max(140, Math.min(150, current + variation));
            activeDevices.textContent = newValue;
        }
    }, 10000);

function logout() {
    if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
        window.location.href = 'logout.php';
    }
}
</script>

</body>
</html>