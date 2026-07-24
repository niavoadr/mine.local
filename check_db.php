<?php
// check_db.php — Diagnostic de la connexion à la base de données.
// ⚠️  Fichier de diagnostic TEMPORAIRE : à SUPPRIMER après usage (il affiche
//     des informations sensibles sur la base). Ne pas laisser en production.
require_once __DIR__ . '/env.php';

header('Content-Type: text/plain; charset=utf-8');

function line($s = '')
{
  echo $s . "\n";
}

line('=== DIAGNOSTIC BASE DE DONNÉES (mine_local) ===');
line('Date: ' . date('Y-m-d H:i:s'));
line('');

// ---------------------------------------------------------------------------
// 1. Driver PDO PostgreSQL
// ---------------------------------------------------------------------------
line('--- 1. Extension PDO PostgreSQL ---');
$drivers = PDO::getAvailableDrivers();
line('Drivers PDO disponibles: ' . (empty($drivers) ? '(aucun)' : implode(', ', $drivers)));
if (in_array('pgsql', $drivers, true)) {
  line('OK: pdo_pgsql est installé.');
} else {
  line('ERREUR: pdo_pgsql est MANQUANT.');
  line("=> Installe-le (ex: 'sudo apt install php-pgsql') puis redémarre Apache/PHP-FPM.");
  exit;
}
line('');

// ---------------------------------------------------------------------------
// 2. Configuration lue depuis le .env
// ---------------------------------------------------------------------------
line('--- 2. Configuration lue (.env) ---');
$envExists = file_exists(__DIR__ . '/.env');
line('Fichier .env présent à la racine: ' . ($envExists ? 'OUI' : 'NON ⚠️  (créer le fichier .env)'));
$host = env('DB_HOST', '(non défini)');
$port = env('DB_PORT', '(non défini)');
$name = env('DB_NAME', '(non défini)');
$user = env('DB_USER', '(non défini)');
$pass = env('DB_PASS', null);
line("DB_HOST = $host");
line("DB_PORT = $port");
line("DB_NAME = $name");
line("DB_USER = $user");
line('DB_PASS = ' . ($pass !== null && $pass !== '' ? '(défini, masqué)' : '(VIDE ⚠️)'));
line('');

// ---------------------------------------------------------------------------
// 3. Connexion PDO
// ---------------------------------------------------------------------------
line('--- 3. Connexion PDO ---');
try {
  $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  line("OK: connexion réussie à la base \"$name\" sur $host:$port.");
} catch (Throwable $e) {
  line('ERREUR de connexion: ' . $e->getMessage());
  line('=> Vérifie host/port/dbname/user/pass, que PostgreSQL tourne, et que');
  line("   l'authentification est autorisée (pg_hba.conf) pour cet utilisateur.");
  exit;
}
line('');

// ---------------------------------------------------------------------------
// 4. Tables du schéma (database/radius.sql)
// ---------------------------------------------------------------------------
line('--- 4. Tables présentes dans la base ---');
$expected = [
  'users', 'radcheck', 'radusergroup', 'radgroupreply', 'radreply',
  'radacct', 'radpostauth', 'nas', 'visitor', 'blacklist', 'security_event',
];
$existing = [];
$stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $existing[] = $r['tablename'];
}
line('Tables trouvées: ' . (empty($existing) ? '(aucune ⚠️)' : implode(', ', $existing)));

if (in_array('users', $existing, true)) {
  line('OK: la table "users" existe dans cette base.');
} else {
  line('');
  line('ERREUR: la table "users" est ABSENTE de la base "' . $name . '".');
  line("=> Causes probables :");
  line("   - tu as inséré l'utilisateur dans une AUTRE base que DB_NAME ;");
  line("   - le script database/radius.sql n'a pas été exécuté sur cette base.");
  line("   Applique le schéma sur la bonne base :");
  line("   psql -U $user -d $name -h $host -f database/radius.sql");
}
$missing = array_values(array_diff($expected, $existing));
if (!empty($missing)) {
  line('Tables attendues mais manquantes: ' . implode(', ', $missing));
}
line('');

// ---------------------------------------------------------------------------
// 5. Contenu de users + test de la requête exacte de login.php
// ---------------------------------------------------------------------------
if (in_array('users', $existing, true)) {
  line('--- 5. Table users & test de la requête de login ---');
  try {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    line("Nombre d'utilisateurs: $count");

    $firstUsername = null;
    $stmt = $pdo->query('SELECT id, username, role, status FROM users ORDER BY id');
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
      if ($firstUsername === null) {
        $firstUsername = $r['username'];
      }
      line(sprintf('  #%d  username=%s  role=%s  status=%s', $r['id'], $r['username'], $r['role'], $r['status']));
    }

    if ($count === 0) {
      line('⚠️  La table users est vide : aucun utilisateur pour se connecter.');
    }

    line('');
    line('Test de la requête exacte de login.php (avec status = active) :');
    $testUser = $firstUsername !== null ? $firstUsername : 'admin';
    $stmt = $pdo->prepare("SELECT id, username, role, password_hash FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$testUser]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    line('OK: la requête SQL de login s\'exécute sans erreur.');
    if ($row) {
      line("Utilisateur actif trouvé: {$row['username']} (role={$row['role']}).");
      line('password_hash commence par: ' . substr($row['password_hash'], 0, 4) . '...');
      if (strpos($row['password_hash'], '$2y$') === 0 || strpos($row['password_hash'], '$6$') === 0) {
        line('Format du hash compatible avec password_verify().');
      } else {
        line('⚠️  Format de hash inhabituel — régénère-le avec password_hash().');
      }
    } else {
      line("Aucun utilisateur ACTIF trouvé pour username=\"$testUser\".");
      line("=> Vérifie que le status de l'utilisateur est bien 'active'.");
    }
  } catch (Throwable $e) {
    line('ERREUR SQL: ' . $e->getMessage());
  }
}

line('');
line('=== FIN DU DIAGNOSTIC ===');
line("N'oublie pas de SUPPRIMER check_db.php après usage.");
