<?php
/**
 * generate_ssh_key.php — Génère une paire de clés SSH pour pfSense.
 *
 * Affiche la clé publique au format OpenSSH (à coller dans pfSense)
 * et la clé privée au format PEM (à coller dans le fichier .env).
 *
 * Utilisation :
 *   php generate_ssh_key.php
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   Génération de clés SSH pour la connexion à pfSense        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

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

openssl_pkey_export($keyPair, $privateKey);
$keyDetails = openssl_pkey_get_details($keyPair);

// Construire la clé publique au format OpenSSH : ssh-rsa AAAAB3...
$publicKeyOpenSsh = rsaToOpenSsh($keyDetails['ra'], $keyDetails['n']);

echo "✅ Clés générées avec succès !\n";
echo "\n";

// ─── CLÉ PUBLIQUE ───
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
echo "│  " . $publicKeyOpenSsh . "\n";
echo "└──────────────────────────────────────────────────────────────┘\n";
echo "\n";

// ─── CLÉ PRIVÉE ───
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
echo "  • Après avoir collé les deux clés, teste avec :\n";
echo "        php snort_sync.php\n";
echo "\n";


/**
 * Convertit l'exposant et le module RSA en clé publique OpenSSH.
 *
 * openssl_pkey_get_details retourne 'ra' (exposant) et 'n' (module)
 * sous forme de chaînes binaires brutes.
 *
 * Le format OpenSSH est :
 *   ssh-rsa BASE64( uint32(len("ssh-rsa")) || "ssh-rsa" || uint32(len(e)) || e || uint32(len(n)) || n )
 */
function rsaToOpenSsh($exponent, $modulus)
{
    $keyType = "ssh-rsa";

    // Encoder chaque champ : uint32 big-endian (longueur) + données brutes
    $packed  = pack("N", strlen($keyType)) . $keyType;
    $packed .= pack("N", strlen($exponent)) . $exponent;
    $packed .= pack("N", strlen($modulus)) . $modulus;

    return $keyType . " " . base64_encode($packed);
}
