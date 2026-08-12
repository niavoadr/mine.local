<?php
/**
 * generate_ssh_key.php — Génère une paire de clés SSH pour pfSense.
 *
 * Affiche la clé publique (à coller dans pfSense)
 * et la clé privée (à coller dans le fichier .env).
 *
 * Utilisation :
 *   php generate_ssh_key.php
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   Génération de clés SSH pour la connexion à pfSense        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Générer la paire de clés
$config = [
    "digest_alg"       => "sha512",
    "private_key_bits" => 4096,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
];

echo "⏳ Génération en cours (4096 bits RSA)...\n";
$keyPair = openssl_pkey_new($config);

if (!$keyPair) {
    echo "❌ Erreur: Impossible de générer la clé. Vérifiez que OpenSSL est installé.\n";
    exit(1);
}

// Extraire la clé privée
openssl_pkey_export($keyPair, $privateKey);

// Extraire la clé publique
$keyDetails = openssl_pkey_get_details($keyPair);
$publicKey = $keyDetails['key'];

echo "✅ Clés générées avec succès !\n";
echo "\n";

// Afficher la clé PUBLIQUE
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  CLÉ PUBLIQUE — À coller dans pfSense\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "  Sur pfSense :\n";
echo "    1. Va dans System → User Manager → Users\n";
echo "    2. Clique sur l'utilisateur root (ou admin)\n";
echo "    3. Descends jusqu'à « Authorized SSH Keys »\n";
echo "    4. Colle la clé ci-dessous dans le champ\n";
echo "    5. Clique Save\n";
echo "\n";
echo "┌──────────────────────────────────────────────────────────────┐\n";
$pubLines = explode("\n", trim($publicKey));
foreach ($pubLines as $line) {
    echo "│  " . $line . "\n";
}
echo "└──────────────────────────────────────────────────────────────┘\n";
echo "\n";

// Afficher la clé PRIVÉE
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  CLÉ PRIVÉE — À coller dans le fichier .env\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "  Ajoute cette variable dans ton fichier .env :\n";
echo "\n";
echo "┌──────────────────────────────────────────────────────────────┐\n";
echo "│  PFSENSE_SSH_KEY=\"\n";
$privLines = explode("\n", trim($privateKey));
foreach ($privLines as $line) {
    echo "│    " . $line . "\n";
}
echo "│  \"\n";
echo "└──────────────────────────────────────────────────────────────┘\n";
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  ⚠️  IMPORTANT\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "  • Ne partage JAMAIS la clé privée avec qui que ce soit\n";
echo "  • Le fichier .env ne doit PAS être commité dans Git\n";
echo "  • Après avoir collé les deux clés, teste la connexion :\n";
echo "        php snort_sync.php\n";
echo "\n";
