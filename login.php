<?php
ob_start();
session_start();
include("./connection.php");

define('CHEF_DEPT', 5);

$msg = ''; 
$username_value = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['pass'])) {
  
  $username_value = $_POST['username'];
  
  $sql = "SELECT U.*, r.nom as lib_role FROM utilisateurs U, roles r 
          WHERE nom_utilisateur = ? AND statut = 'actif' AND U.id_role=r.id";
  $stmt = $conn->prepare($sql);
  
  if ($stmt === false) {
      $msg = "Erreur de préparation de la requête.";
  } else if (!$stmt->bind_param("s", $_POST['username'])) {
      $msg = "Erreur de liaison des paramètres.";
  } else if (!$stmt->execute()) {
      $msg = "Erreur d'exécution de la requête.";
  } else {
      $result = $stmt->get_result();
      
      if ($result->num_rows > 0) {
        
        $row = $result->fetch_assoc();
        
        if (password_verify($_POST['pass'], $row['mot_de_passe_hash'])) {
          
          $_SESSION["user"] = $row['nom_utilisateur'];
          $_SESSION['role_lib'] = $row['lib_role'];
          $_SESSION['id_role'] = $row['id_role'];
          
          // Déterminer la page de redirection
          if ($row['id_role'] == 5) {
              $target_page = "dashboard_user.php"; 
          } else {
              $target_page = "dashboard_admin.php";
          }
          
          // Vider le buffer et rediriger
          ob_end_clean();
          header("Location: " . $target_page);
          die();
          
        } else {
          $msg = "Login ou mot de passe incorrect";
        }
      } else {
        $msg = "Utilisateur non trouvé ou inactif";
      }
      
      $stmt->close();
  }
}
?>
<!doctype html>
<html lang="fr" data-bs-theme="auto">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Connexion">
  <meta name="author" content="">
  <title>Connexion - Portail Mines</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
  integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <style>
  :root {
    --noir-principal: #000000;
    --noir-secondaire: #1a1a1a;
    --marron-dore: #B8860B;
    --marron-clair: #DAA520;
    --gris-fonce: #2d2d2d;
    --blanc: #ffffff;
  }

  * {
    border-radius: 0 !important;
  }

  @keyframes slideBackground {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }

  @keyframes fadeInDown {
    from {
      opacity: 0;
      transform: translateY(-30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes slideInLeft {
    from {
      opacity: 0;
      transform: translateX(-100px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  @keyframes slideInRight {
    from {
      opacity: 0;
      transform: translateX(100px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  @keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
  }

  @keyframes pulse {
    0%, 100% { box-shadow: 0 8px 24px rgba(184, 134, 11, 0.3); }
    50% { box-shadow: 0 12px 32px rgba(184, 134, 11, 0.5); }
  }

  body {
    margin: 0;
    padding: 0;
    height: 100vh;
    background: linear-gradient(135deg, var(--noir-principal) 0%, var(--noir-secondaire) 30%, #3d2a1a 70%, var(--noir-principal) 100%);
    background-size: 300% 300%;
    animation: slideBackground 15s ease infinite;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    overflow: hidden;
  }

  .login-container {
    display: flex;
    height: 100%;
    position: relative;
  }

  .login-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--marron-dore), var(--marron-clair), var(--marron-dore));
    overflow: hidden;
  }

  .login-container::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, transparent, var(--blanc), transparent);
    animation: shimmer 2s infinite;
  }

  .login-left {
    flex: 1;
    background: url('images/logomine.jpg') center center no-repeat;
    background-size: contain;
    background-color: var(--noir-secondaire);
    border-right: 3px solid var(--marron-dore);
    animation: slideInLeft 0.8s ease-out;
    position: relative;
    overflow: hidden;
  }

  .login-left::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(184, 134, 11, 0.1) 0%, transparent 70%);
    animation: pulse 4s ease-in-out infinite;
  }

  .login-right {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    background-color: var(--noir-principal);
    animation: slideInRight 0.8s ease-out;
  }

  .login-form {
    background-color: var(--gris-fonce);
    padding: 3rem 2.5rem;
    border: 2px solid var(--marron-dore);
    box-shadow: 0 8px 24px rgba(184, 134, 11, 0.3);
    max-width: 400px;
    width: 100%;
    margin-top: 100px;
    animation: fadeInDown 1s ease-out;
    position: relative;
    overflow: hidden;
  }

  .login-form::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(184, 134, 11, 0.1), transparent);
    animation: shimmer 3s infinite;
  }

  .login-form h1 {
    font-size: 1.5rem;
    margin-bottom: 2rem;
    color: var(--marron-dore);
    text-transform: uppercase;
    letter-spacing: 2px;
    font-weight: 700;
    border-bottom: 2px solid var(--marron-dore);
    padding-bottom: 1rem;
    animation: fadeInUp 1.2s ease-out;
  }

  .form-floating {
    animation: fadeInUp 1.4s ease-out;
  }

  .form-floating:nth-child(3) {
    animation-delay: 0.1s;
  }

  .form-floating > .form-control,
  .form-floating > .form-control:focus {
    background-color: var(--noir-secondaire);
    border: 2px solid var(--marron-dore);
    color: var(--blanc);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .form-floating > .form-control:focus {
    box-shadow: 0 0 0 0.25rem rgba(184, 134, 11, 0.25);
    transform: translateY(-2px);
    border-color: var(--marron-clair);
  }

  .form-floating > label {
    color: var(--marron-clair);
    transition: all 0.3s ease;
  }

  .form-floating > .form-control:focus ~ label {
    color: var(--marron-dore);
  }

  .form-check {
    animation: fadeInUp 1.6s ease-out;
  }

  .form-check-input {
    background-color: var(--noir-secondaire);
    border: 2px solid var(--marron-dore);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
  }

  .form-check-input:checked {
    background-color: var(--marron-dore);
    border-color: var(--marron-dore);
    transform: scale(1.1);
  }

  .form-check-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(184, 134, 11, 0.25);
  }

  .form-check-input:hover {
    transform: scale(1.05);
  }

  .form-check-label {
    color: var(--blanc);
    font-size: 0.9rem;
    cursor: pointer;
    transition: color 0.2s ease;
  }

  .form-check-label:hover {
    color: var(--marron-clair);
  }

  .btn-primary {
    background-color: var(--marron-dore);
    border: 2px solid var(--marron-dore);
    color: var(--noir-principal);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    animation: fadeInUp 1.8s ease-out;
  }

  .btn-primary::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
  }

  .btn-primary:hover::before {
    width: 300px;
    height: 300px;
  }

  .btn-primary:hover {
    background-color: var(--marron-clair);
    border-color: var(--marron-clair);
    color: var(--noir-principal);
    box-shadow: 0 8px 20px rgba(184, 134, 11, 0.5);
    transform: translateY(-3px);
  }

  .btn-primary:active {
    transform: translateY(-1px);
  }

  .btn-outline-secondary {
    background-color: transparent;
    border: 2px solid var(--marron-dore);
    color: var(--marron-dore);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0.75rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    animation: fadeInUp 2s ease-out;
  }

  .btn-outline-secondary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: var(--noir-secondaire);
    transition: left 0.4s ease;
    z-index: -1;
  }

  .btn-outline-secondary:hover::before {
    left: 0;
  }

  .btn-outline-secondary:hover {
    border-color: var(--marron-clair);
    color: var(--marron-clair);
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(184, 134, 11, 0.3);
  }

  .btn-outline-secondary:active {
    transform: translateY(-1px);
  }

  .text-danger {
    font-size: 0.875rem;
    color: #ff6b6b !important;
    background-color: rgba(255, 107, 107, 0.1);
    padding: 0.5rem;
    border-left: 3px solid #ff6b6b;
    margin-bottom: 1rem;
    animation: fadeInDown 0.5s ease-out;
  }

  @media (max-width: 768px) {
    .login-container {
      flex-direction: column;
    }

    .login-left {
      height: 200px;
      border-right: none;
      border-bottom: 3px solid var(--marron-dore);
    }

    .login-form {
      margin-top: 2rem;
    }
  }

  @keyframes float {
    0%, 100% { transform: translateY(0) translateX(0); }
    25% { transform: translateY(-20px) translateX(10px); }
    50% { transform: translateY(-10px) translateX(-10px); }
    75% { transform: translateY(-30px) translateX(5px); }
  }

  .login-right::before {
    content: '';
    position: absolute;
    width: 2px;
    height: 2px;
    background: var(--marron-dore);
    top: 20%;
    left: 30%;
    animation: float 6s ease-in-out infinite;
    opacity: 0.6;
  }

  .login-right::after {
    content: '';
    position: absolute;
    width: 2px;
    height: 2px;
    background: var(--marron-clair);
    top: 60%;
    right: 25%;
    animation: float 8s ease-in-out infinite;
    opacity: 0.4;
  }
  </style>
</head>

<body>
<div class="login-container">
  <div class="login-left"></div>
  <div class="login-right">
    <form method="POST" action="login.php" class="login-form">
      <h1 class="h4 mb-3 fw-bold text-center">Portail gestion</h1>
      <?php if (!empty($msg)): ?>
        <p class="text-danger text-center"><?php echo htmlspecialchars($msg); ?></p>
      <?php endif; ?>

      <div class="form-floating mb-3">
        <input required type="text" class="form-control" id="floatingInput" placeholder="Username" name="username" value="<?php echo htmlspecialchars($username_value); ?>">
        <label for="floatingInput">Nom d'utilisateur</label>
      </div>

      <div class="form-floating mb-3">
        <input required type="password" class="form-control" id="floatingPassword" placeholder="Password" name="pass">
        <label for="floatingPassword">Mot de passe</label>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="remember-me" id="flexCheckDefault" name="forget">
        <label class="form-check-label" for="flexCheckDefault">
          Se souvenir de moi
        </label>
      </div>

      <button class="btn btn-primary w-100 mb-2" type="submit" name="ok">Connexion</button>
      <button type="button" class="btn btn-outline-secondary w-100">S'inscrire</button>
    </form>
  </div>
</div>
</body>

</html>