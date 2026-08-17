<?php
ob_start();
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once './connexion.php';

$msg = '';
$username_value = '';

function logFailedLogin($username)
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $username = preg_replace('/[\r\n\t]+/', ' ', (string) $username);
  $username = substr($username, 0, 128);
  $line = sprintf("%s [FAIL2BAN] Failed login from %s user=%s\n", date('Y-m-d H:i:s'), $ip, $username);
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
  }
  @file_put_contents($logDir . '/auth.log', $line, FILE_APPEND | LOCK_EX);
  @chmod($logDir . '/auth.log', 0640);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['pass'])) {
  $username_value = $_POST['username'];

  $sql = "SELECT * FROM users WHERE username = ? AND status = 'active'";

  try {
    $stmt = $connexion->prepare($sql);
    $stmt->execute([$_POST['username']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      if (password_verify($_POST['pass'], $row['password_hash'])) {
        $_SESSION['user'] = $row['username'];
        $_SESSION['nom_utilisateur'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['role_lib'] = $row['role'] === 'ADMIN' ? 'Administrateur' : 'Utilisateur';
        $_SESSION['user_id'] = $row['id'];

        try {
          $connexion->prepare('UPDATE users SET last_login = now() WHERE id = ?')->execute([$row['id']]);
        } catch (PDOException $e) {
        }

        if ($row['role'] === 'ADMIN') {
          $target_page = 'dashboard_admin.php';
        } else {
          $target_page = 'dashboard_user.php';
        }
        ob_end_clean();
        header('Location: ' . $target_page);
        die();
      } else {
        $msg = 'Login ou mot de passe incorrect.';
        logFailedLogin($username_value);
      }
    } else {
      $msg = 'Utilisateur non trouvé ou inactif.';
      logFailedLogin($username_value);
    }
  } catch (PDOException $e) {
    $msg = 'Erreur de connexion à la base de données.';
  }
}
?>
<!doctype html>
<html lang="fr" data-bs-theme="light">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Portail de Connexion - Administration RADIUS">
  <title>Connexion - Ministère des Mines</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css?v=20260817">
    <link rel="stylesheet" href="css/responsive.css?v=20260817">
    <link rel="stylesheet" href="css/animations.css?v=20260817">
</head>

<body class="login-page">
  <div class="bg-orb orb-1"></div>
  <div class="bg-orb orb-2"></div>

  <div class="login-card">
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

    <div class="form-panel">
      <div class="form-header">
        <h2>Authentification</h2>
        <p>Veuillez saisir vos identifiants de session</p>
      </div>

      <?php if (!empty($msg)): ?>
        <div class="custom-alert">
          <i class="fa-solid fa-circle-exclamation fs-5"></i>
          <span><?php echo htmlspecialchars($msg); ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" id="loginForm">
        <div class="form-floating">
          <input required type="text" class="form-control" id="floatingInput" placeholder="Nom d'utilisateur" name="username" value="<?php echo htmlspecialchars(
            $username_value,
          ); ?>" autocomplete="username">
          <label for="floatingInput">Nom d'utilisateur</label>
          <i class="fa-regular fa-user input-icon"></i>
        </div>

        <div class="form-floating">
          <input required type="password" class="form-control" id="floatingPassword" placeholder="Mot de passe" name="pass" autocomplete="current-password">
          <label for="floatingPassword">Mot de passe</label>
          <i class="fa-solid fa-lock input-icon"></i>
          <button type="button" class="password-toggle" onclick="togglePasswordVisibility()" title="Afficher/Masquer">
            <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
          </button>
        </div>

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