<?php
ob_start();
session_start();
require_once dirname(__DIR__) . '/app/bootstrap.php';

define('CHEF_DEPT', 5);

$msg = '';
$username_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['pass'])) {
  $username_value = $_POST['username'];

  $sql = "SELECT * FROM users WHERE username = ? AND status = 'active'";

  try {
    $stmt = $connexion->prepare($sql);
    $stmt->execute([$_POST['username']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      if (password_verify($_POST['pass'], $row['password_hash'])) {
        // Variables de session alignées sur le schéma `users` (consommées par les dashboards)
        $_SESSION['user'] = $row['username'];
        $_SESSION['nom_utilisateur'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['role_lib'] = $row['role'] === 'ADMIN' ? 'Administrateur' : 'Utilisateur';
        $_SESSION['user_id'] = $row['id'];

        // Mise à jour de la dernière connexion (non bloquant)
        try {
          $connexion->prepare('UPDATE users SET last_login = now() WHERE id = ?')->execute([$row['id']]);
        } catch (PDOException $e) {
          // On ignore : la connexion doit quand même aboutir
        }

        // Déterminer la page de redirection
        if ($row['role'] === 'ADMIN') {
          $target_page = 'dashboard-admin.php';
        } else {
          $target_page = 'dashboard-user.php';
        }
        // Vider le buffer et rediriger
        ob_end_clean();
        header('Location: ' . $target_page);
        die();
      } else {
        $msg = 'Login ou mot de passe incorrect.';
      }
    } else {
      $msg = 'Utilisateur non trouvé ou inactif.';
    }
  } catch (PDOException $e) {
    $msg = 'Erreur de connexion à la base de données.';
  }
}
?>
<!doctype html>
<html lang="fr" data-bs-theme="dark">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Portail de Connexion - Administration RADIUS">
  <title>Connexion - Ministère des Mines</title>
  
  <!-- Bootstrap 5 & FontAwesome & Google Fonts -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pages/login.css?v=20260725">
    <link rel="stylesheet" href="assets/css/app/responsive.css?v=20260722">
    <link rel="stylesheet" href="assets/css/app/animations.css?v=20260721">
</head>

<body>
  <!-- Orbes en arrière-plan -->
  <div class="bg-orb orb-1"></div>
  <div class="bg-orb orb-2"></div>

  <!-- Conteneur Glassmorphism -->
  <div class="login-card">
    
    <!-- Panneau Marque / Gauche -->
    <div class="brand-panel">
      <div>
        <div class="logo-wrapper">
          <div class="logo-img-box">
            <img src="assets/images/logomine.jpg" alt="Logo Ministère des Mines">
          </div>
          <div class="brand-title">
            <span>République de Madagascar</span>
            <h3>Ministère des Mines</h3>
          </div>
        </div>

        <div class="brand-hero">
          <h1>Portail Central <span>RADIUS & Gestion</span></h1>
          <p>Plateforme centralisée d'authentification, d'autorisation et de supervision du réseau et des comptes utilisateurs.</p>
          
          <div class="feature-pills">
            <div class="feature-pill">
              <i class="fa-solid fa-lock"></i> Sécurité Haute Précision
            </div>
            <div class="feature-pill">
              <i class="fa-solid fa-network-wired"></i> Contrôle d'Accès
            </div>
            <div class="feature-pill">
              <i class="fa-solid fa-shield-halved"></i> Audit en Temps Réel
            </div>
          </div>
        </div>
      </div>

      <div class="brand-footer">
        <span>© <?php echo date('Y'); ?> Ministère des Mines</span>
        <span>Version Sécurisée 2.4</span>
      </div>
    </div>

    <!-- Panneau Formulaire / Droite -->
    <div class="form-panel">
      <div class="form-header">
        <h2>Authentification</h2>
        <p>Veuillez saisir vos identifiants de session</p>
      </div>

      <!-- Message d'erreur -->
      <?php if (!empty($msg)): ?>
        <div class="custom-alert">
          <i class="fa-solid fa-circle-exclamation fs-5"></i>
          <span><?php echo htmlspecialchars($msg); ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" id="loginForm">
        
        <!-- Nom d'utilisateur -->
        <div class="form-floating">
          <input required type="text" class="form-control" id="floatingInput" placeholder="Nom d'utilisateur" name="username" value="<?php echo htmlspecialchars(
            $username_value,
          ); ?>" autocomplete="username">
          <label for="floatingInput">Nom d'utilisateur</label>
          <i class="fa-regular fa-user input-icon"></i>
        </div>

        <!-- Mot de passe -->
        <div class="form-floating">
          <input required type="password" class="form-control" id="floatingPassword" placeholder="Mot de passe" name="pass" autocomplete="current-password">
          <label for="floatingPassword">Mot de passe</label>
          <i class="fa-solid fa-lock input-icon"></i>
          <button type="button" class="password-toggle" onclick="togglePasswordVisibility()" title="Afficher/Masquer">
            <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
          </button>
        </div>

        <!-- Options -->
        <div class="form-options">
          <label class="custom-checkbox" for="flexCheckDefault">
            <input type="checkbox" value="remember-me" id="flexCheckDefault" name="forget">
            <span>Se souvenir de moi</span>
          </label>
        </div>

        <!-- Boutons -->
        <button class="btn-submit" type="submit" name="ok">
          <span>Se connecter</span>
          <i class="fa-solid fa-arrow-right-to-bracket"></i>
        </button>
        
        <button type="button" class="btn-ghost" onclick="alert('Veuillez contacter l\'administrateur système pour toute demande d\'accès ou de réinitialisation.');">
          <i class="fa-regular fa-circle-question me-1"></i> S'inscrire / Assistance
        </button>
      </form>
    </div>

  </div>
    <script src="assets/js/pages/login.js?v=20260725"></script>
</body>

</html>