<?php
ob_start();
session_start();
require_once './connexion.php';

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
        $_SESSION['role'] = $row['role'];
        $_SESSION['user'] = $row['username'];

        // Enregistrer la dernière connexion (colonne last_login de la table users)
        try {
          $update = $connexion->prepare('UPDATE users SET last_login = now() WHERE id = ?');
          $update->execute([$row['id']]);
        } catch (PDOException $e) {
          // La mise à jour de last_login ne doit pas bloquer la connexion
        }

        // Déterminer la page de redirection
        if ($row['role'] == 'ADMIN') {
          $target_page = 'dashboard_admin.php';
        } else {
          $target_page = 'dashboard_user.php';
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

  <style>
  :root {
    --bg-dark-1: #0a0a0c;
    --bg-dark-2: #141418;
    --bg-card: rgba(24, 24, 30, 0.75);
    --gold-primary: #DAA520;
    --gold-dark: #B8860B;
    --gold-glow: rgba(218, 165, 32, 0.25);
    --border-gold: rgba(218, 165, 32, 0.3);
    --text-muted: #9ca3af;
  }

  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    padding: 0;
    min-height: 100vh;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    background-color: var(--bg-dark-1);
    background-image: 
      radial-gradient(circle at 15% 20%, rgba(184, 134, 11, 0.15) 0%, transparent 40%),
      radial-gradient(circle at 85% 80%, rgba(218, 165, 32, 0.12) 0%, transparent 40%),
      radial-gradient(circle at 50% 50%, rgba(20, 20, 25, 1) 0%, var(--bg-dark-1) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-x: hidden;
    padding: 1.5rem;
  }

  /* Orbes lumineux en arrière-plan */
  .bg-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    z-index: 0;
    pointer-events: none;
    animation: floatOrb 12s ease-in-out infinite alternate;
  }

  .orb-1 {
    width: 350px;
    height: 350px;
    background: rgba(218, 165, 32, 0.12);
    top: -50px;
    left: -50px;
  }

  .orb-2 {
    width: 450px;
    height: 450px;
    background: rgba(184, 134, 11, 0.1);
    bottom: -100px;
    right: -100px;
    animation-delay: -6s;
  }

  @keyframes floatOrb {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(40px, 30px) scale(1.1); }
  }

  /* Conteneur principal Glassmorphism */
  .login-card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 1000px;
    background: var(--bg-card);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid var(--border-gold);
    border-radius: 24px;
    box-shadow: 
      0 25px 50px -12px rgba(0, 0, 0, 0.8),
      0 0 40px rgba(184, 134, 11, 0.15);
    overflow: hidden;
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    animation: fadeInScale 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  }

  @keyframes fadeInScale {
    0% {
      opacity: 0;
      transform: scale(0.95) translateY(15px);
    }
    100% {
      opacity: 1;
      transform: scale(1) translateY(0);
    }
  }

  /* Section de marque à gauche */
  .brand-panel {
    background: linear-gradient(135deg, rgba(20, 20, 24, 0.8) 0%, rgba(28, 20, 12, 0.85) 100%);
    border-right: 1px solid rgba(218, 165, 32, 0.2);
    padding: 3rem 2.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
  }

  .brand-panel::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at top right, rgba(218, 165, 32, 0.1), transparent 60%);
    pointer-events: none;
  }

  .logo-wrapper {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .logo-img-box {
    width: 76px;
    height: 76px;
    background: #ffffff;
    border-radius: 18px;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5), 0 0 15px rgba(218, 165, 32, 0.3);
    border: 2px solid var(--gold-primary);
  }

  .logo-img-box img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
  }

  .brand-title h3 {
    font-weight: 700;
    font-size: 1.3rem;
    color: #ffffff;
    margin: 0;
    letter-spacing: -0.5px;
  }

  .brand-title span {
    font-size: 0.85rem;
    color: var(--gold-primary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .brand-hero {
    margin: 2rem 0;
  }

  .brand-hero h1 {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.25;
    color: #ffffff;
    margin-bottom: 1rem;
  }

  .brand-hero h1 span {
    background: linear-gradient(135deg, #FFF 0%, var(--gold-primary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .brand-hero p {
    color: var(--text-muted);
    font-size: 0.95rem;
    line-height: 1.6;
    margin: 0;
  }

  .feature-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.5rem;
  }

  .feature-pill {
    background: rgba(218, 165, 32, 0.1);
    border: 1px solid rgba(218, 165, 32, 0.25);
    color: #e5e7eb;
    padding: 0.45rem 0.9rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .feature-pill i {
    color: var(--gold-primary);
  }

  .brand-footer {
    font-size: 0.8rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 1.25rem;
  }

  /* Section de formulaire à droite */
  .form-panel {
    padding: 3.5rem 3rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .form-header {
    margin-bottom: 2.25rem;
  }

  .form-header h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 0.4rem;
    letter-spacing: -0.5px;
  }

  .form-header p {
    color: var(--text-muted);
    font-size: 0.92rem;
    margin: 0;
  }

  /* Alertes d'erreur */
  .custom-alert {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #f87171;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    font-size: 0.88rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: shake 0.4s ease-in-out;
  }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-5px); }
    40%, 80% { transform: translateX(5px); }
  }

  /* Floating labels & inputs modernes */
  .form-floating {
    margin-bottom: 1.25rem;
    position: relative;
  }

  .form-floating .form-control {
    background-color: rgba(30, 30, 36, 0.7) !important;
    border: 1.5px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 14px !important;
    color: #ffffff !important;
    font-size: 0.95rem;
    padding: 1.1rem 1rem 0.5rem 2.8rem !important;
    height: 58px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .form-floating .form-control:focus {
    border-color: var(--gold-primary) !important;
    box-shadow: 0 0 0 4px var(--gold-glow) !important;
    background-color: rgba(36, 36, 44, 0.85) !important;
  }

  .form-floating label {
    color: var(--text-muted);
    padding-left: 2.8rem;
    font-size: 0.9rem;
    transition: all 0.25s ease;
  }

  .form-floating .form-control:focus ~ label,
  .form-floating .form-control:not(:placeholder-shown) ~ label {
    color: var(--gold-primary);
    transform: scale(0.85) translateY(-0.6rem) translateX(-0.3rem);
  }

  .input-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
    font-size: 1rem;
    z-index: 5;
    transition: color 0.25s ease;
    pointer-events: none;
  }

  .form-floating .form-control:focus ~ .input-icon {
    color: var(--gold-primary);
  }

  .password-toggle {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
    background: none;
    border: none;
    padding: 0;
    font-size: 1rem;
    z-index: 5;
    cursor: pointer;
    transition: color 0.2s ease;
  }

  .password-toggle:hover {
    color: #ffffff;
  }

  /* Checkbox & Options */
  .form-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.75rem;
    font-size: 0.88rem;
  }

  .custom-checkbox {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    cursor: pointer;
    user-select: none;
    color: #d1d5db;
    margin: 0;
  }

  .custom-checkbox input[type="checkbox"] {
    appearance: none;
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    background: rgba(30, 30, 36, 0.8);
    border: 1.5px solid rgba(255, 255, 255, 0.2);
    border-radius: 5px;
    cursor: pointer;
    display: grid;
    place-content: center;
    transition: all 0.2s ease;
  }

  .custom-checkbox input[type="checkbox"]::before {
    content: "\f00c";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 10px;
    color: #000;
    transform: scale(0);
    transition: transform 0.15s ease-in-out;
  }

  .custom-checkbox input[type="checkbox"]:checked {
    background: var(--gold-primary);
    border-color: var(--gold-primary);
  }

  .custom-checkbox input[type="checkbox"]:checked::before {
    transform: scale(1);
  }

  /* Bouton principal */
  .btn-submit {
    width: 100%;
    height: 52px;
    background: linear-gradient(135deg, var(--gold-primary) 0%, var(--gold-dark) 100%);
    border: none;
    border-radius: 14px;
    color: #000000;
    font-weight: 700;
    font-size: 0.98rem;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(184, 134, 11, 0.35);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
  }

  .btn-submit::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
    transition: left 0.5s ease;
  }

  .btn-submit:hover::after {
    left: 100%;
  }

  .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px rgba(184, 134, 11, 0.55);
    background: linear-gradient(135deg, #e5b32e 0%, #c99312 100%);
    color: #000000;
  }

  .btn-submit:active {
    transform: translateY(0);
  }

  /* Bouton secondaire */
  .btn-ghost {
    width: 100%;
    height: 48px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 14px;
    color: #d1d5db;
    font-weight: 600;
    font-size: 0.9rem;
    margin-top: 0.85rem;
    cursor: pointer;
    transition: all 0.25s ease;
  }

  .btn-ghost:hover {
    background: rgba(218, 165, 32, 0.1);
    border-color: rgba(218, 165, 32, 0.4);
    color: var(--gold-primary);
  }

  /* Responsive Design */
  @media (max-width: 900px) {
    .login-card {
      grid-template-columns: 1fr;
      max-width: 480px;
    }

    .brand-panel {
      padding: 2rem;
      border-right: none;
      border-bottom: 1px solid rgba(218, 165, 32, 0.2);
    }

    .brand-hero, .feature-pills, .brand-footer {
      display: none;
    }

    .logo-wrapper {
      margin-bottom: 0;
    }

    .form-panel {
      padding: 2.5rem 2rem;
    }
  }

  @media (max-width: 480px) {
    body {
      padding: 1rem;
    }

    .form-panel {
      padding: 2rem 1.5rem;
    }

    .form-header h2 {
      font-size: 1.5rem;
    }
  }
  </style>
    <link rel="stylesheet" href="css/responsive.css?v=20260722">
    <link rel="stylesheet" href="css/animations.css?v=20260721">
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
            <img src="images/logomine.jpg" alt="Logo Ministère des Mines">
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

  <script>
    function togglePasswordVisibility() {
      const passwordInput = document.getElementById('floatingPassword');
      const toggleIcon = document.getElementById('togglePasswordIcon');
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }
  </script>
</body>

</html>