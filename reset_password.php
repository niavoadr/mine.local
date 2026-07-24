<?php
// reset_password.php — Réinitialise le mot de passe d'un utilisateur de la table
// "users" en générant le hash avec password_hash() de PHP (bcrypt $2y$), le SEUL
// format accepté par password_verify() dans login.php.
//
// ⚠️  Fichier utilitaire TEMPORAIRE : à SUPPRIMER après usage.
//
// Usage (Invite de commandes cmd) :
//     C:\xampp\php\php.exe reset_password.php <username> <nouveau_mot_de_passe>
//     ex: C:\xampp\php\php.exe reset_password.php admin admin123
//
// Usage (navigateur) :
//     reset_password.php?u=<username>&p=<nouveau_mot_de_passe>

require_once __DIR__ . '/env.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
  header('Content-Type: text/plain; charset=utf-8');
}

// Paramètres : arguments CLI ($argv) ou query string (web)
$username = $argv[1] ?? ($_GET['u'] ?? null);
$password = $argv[2] ?? ($_GET['p'] ?? null);

if (!$username || !$password) {
  echo "Usage (CLI): php reset_password.php <username> <nouveau_mot_de_passe>\n";
  echo "Usage (web): reset_password.php?u=<username>&p=<nouveau_mot_de_passe>\n";
  exit;
}

try {
  $host = env('DB_HOST', 'localhost');
  $port = (int) env('DB_PORT', 5432);
  $name = env('DB_NAME', '');
  $user = env('DB_USER', '');
  $pass = env('DB_PASS', '');

  $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $name);
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

  // 1. Vérifier que l'utilisateur existe
  $stmt = $pdo->prepare('SELECT id, username, status FROM users WHERE username = ?');
  $stmt->execute([$username]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    echo "ERREUR: utilisateur \"$username\" introuvable dans la table users.\n";
    exit;
  }

  // 2. Générer le hash natif PHP (bcrypt $2y$) — garanti compatible password_verify()
  $hash = password_hash($password, PASSWORD_DEFAULT);

  // 3. Mettre à jour la base (requête préparée : aucun risque d'échappement des $)
  $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, date_modification = now() WHERE id = ?');
  $stmt->execute([$hash, $row['id']]);

  // 4. Relecture + contrôle password_verify() pour confirmer que ça fonctionne
  $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
  $stmt->execute([$row['id']]);
  $stored = (string) $stmt->fetchColumn();

  echo "=== Réinitialisation du mot de passe ===\n";
  echo "Utilisateur     : {$row['username']} (id={$row['id']}, status={$row['status']})\n";
  echo "Hash enregistré : $hash\n";
  echo "Contrôle password_verify() : " . (password_verify($password, $stored) ? 'OK ✅' : 'ECHEC ❌') . "\n";

  if ($row['status'] !== 'active') {
    echo "\nATTENTION: le status est \"{$row['status']}\" or login.php exige status='active'.\n";
    echo "Corrige avec : UPDATE users SET status = 'active' WHERE username = '$username';\n";
  }

  echo "\n=> Connecte-toi avec : $username / $password\n";
  echo "\nN'oublie pas de SUPPRIMER reset_password.php après usage.\n";
} catch (Throwable $e) {
  echo 'ERREUR: ' . $e->getMessage() . "\n";
}
