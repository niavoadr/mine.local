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

// ─── Méthode 1 : Utiliser ssh-keygen si disponible (le plus fiable) ───
$sshKeygen = trim(shell_exec('which ssh-keygen 2>/dev/null') ?: '');

if ($sshKeygen !== '') {
    echo "⏳ Génération en cours avec ssh-keygen (4096 bits RSA)...\n";

    $tmpDir = sys_get_temp_dir();
    $tmpKey = $tmpDir . '/pfsense_key_' . uniqid();
    $tmpPub = $tmpKey . '.pub';

    exec("ssh-keygen -t rsa -b 4096 -f " . escapeshellarg($tmpKey) . " -N '' -C 'mine.local-pfsense' 2>&1", $out, $code);

    if ($code === 0 && file_exists($tmpKey) && file_exists($tmpPub)) {
        $privateKey = file_get_contents($tmpKey);
        $publicKeyOpenSsh = trim(file_get_contents($tmpPub));

        // Nettoyer les fichiers temporaires
        unlink($tmpKey);
        unlink($tmpPub);

        echo "✅ Clés générées avec succès !\n\n";
        displayKeys($publicKeyOpenSsh, $privateKey);
        exit(0);
    }

    // Fallback si ssh-keygen a échoué
    if (file_exists($tmpKey)) unlink($tmpKey);
    if (file_exists($tmpPub)) unlink($tmpPub);
    echo "⚠️  ssh-keygen a échoué, fallback vers OpenSSL...\n\n";
}

// ─── Méthode 2 : Fallback avec openssl en ligne de commande ───
$opensslCmd = trim(shell_exec('which openssl 2>/dev/null') ?: '');

if ($opensslCmd !== '') {
    echo "⏳ Génération en cours avec openssl (4096 bits RSA)...\n";

    $tmpDir = sys_get_temp_dir();
    $tmpKey = $tmpDir . '/pfsense_key_' . uniqid() . '.pem';
    $tmpPub = $tmpDir . '/pfsense_key_' . uniqid() . '.pub';

    // Générer la clé privée
    exec("openssl genrsa -out " . escapeshellarg($tmpKey) . " 4096 2>&1", $out1, $code1);

    if ($code1 === 0 && file_exists($tmpKey)) {
        // Convertir en clé publique OpenSSH
        exec("ssh-keygen -y -f " . escapeshellarg($tmpKey) . " -C 'mine.local-pfsense' 2>&1", $pubOut, $code2);

        if ($code2 === 0 && !empty($pubOut)) {
            $privateKey = file_get_contents($tmpKey);
            $publicKeyOpenSsh = trim(implode("\n", $pubOut));

            unlink($tmpKey);

            echo "✅ Clés générées avec succès !\n\n";
            displayKeys($publicKeyOpenSsh, $privateKey);
            exit(0);
        }

        // Si ssh-keygen -y n'est pas dispo, fallback PHP pur
        $privateKey = file_get_contents($tmpKey);
        $publicKeyOpenSsh = phpRsaToOpenSsh($privateKey);

        unlink($tmpKey);

        if ($publicKeyOpenSsh !== '') {
            echo "✅ Clés générées avec succès !\n\n";
            displayKeys($publicKeyOpenSsh, $privateKey);
            exit(0);
        }
    }

    if (file_exists($tmpKey)) unlink($tmpKey);
    echo "⚠️  openssl a échoué, fallback vers PHP pur...\n\n";
}

// ─── Méthode 3 : PHP pur avec openssl_pkey_new ───
echo "⏳ Génération en cours avec PHP OpenSSL (4096 bits RSA)...\n";

$config = [
    "digest_alg"       => "sha512",
    "private_key_bits" => 4096,
    "private_key_type" => OPENSSL_KEYTYPERSA,
];

$keyPair = openssl_pkey_new($config);

if (!$keyPair) {
    echo "❌ Erreur: Impossible de générer la clé. Vérifiez que OpenSSL est installé.\n";
    exit(1);
}

openssl_pkey_export($keyPair, $privateKey);
$publicKeyOpenSsh = phpRsaToOpenSsh($privateKey);

if ($publicKeyOpenSsh === '') {
    echo "❌ Erreur: Impossible de convertir la clé publique en format OpenSSH.\n";
    exit(1);
}

echo "✅ Clés générées avec succès !\n\n";
displayKeys($publicKeyOpenSsh, $privateKey);


// ═══════════════════════════════════════════════════════════════
// FONCTIONS
// ═══════════════════════════════════════════════════════════════

/**
 * Affiche les clés générées avec les instructions.
 */
function displayKeys($publicKeyOpenSsh, $privateKey)
{
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
}

/**
 * Convertit une clé privée PEM en clé publique OpenSSH.
 *
 * Utilise openssl en ligne de commande si disponible,
 * sinon fallback en PHP pur.
 */
function phpRsaToOpenSsh($privateKeyPem)
{
    // Méthode A : ssh-keygen -y (le plus fiable)
    $sshKeygen = trim(shell_exec('which ssh-keygen 2>/dev/null') ?: '');
    if ($sshKeygen !== '') {
        $tmpDir = sys_get_temp_dir();
        $tmpKey = $tmpDir . '/pfsense_tmp_' . uniqid() . '.pem';
        file_put_contents($tmpKey, $privateKeyPem);
        chmod($tmpKey, 0600);

        exec("ssh-keygen -y -f " . escapeshellarg($tmpKey) . " 2>&1", $pubOut, $code);
        unlink($tmpKey);

        if ($code === 0 && !empty($pubOut)) {
            return trim(implode("\n", $pubOut));
        }
        if (file_exists($tmpKey)) unlink($tmpKey);
    }

    // Méthode B : PHP pur
    $res = openssl_pkey_get_private($privateKeyPem);
    if (!$res) return '';

    $details = openssl_pkey_get_details($res);
    if (!$details || !isset($details['ra'], $details['n'])) return '';

    $e = $details['ra']; // exposant (binaire)
    $n = $details['n'];  // module (binaire)

    // mpint : si le bit de poids fort est à 1, ajouter un octet 0x00 en tête
    $e = mpint($e);
    $n = mpint($n);

    $keyType = "ssh-rsa";
    $packed  = pack("N", strlen($keyType)) . $keyType;
    $packed .= $e;
    $packed .= $n;

    return $keyType . " " . base64_encode($packed);
}

/**
 * Encode une valeur binaire au format mpint SSH.
 *
 * mpint = uint32(longueur) + données
 * Si le bit de poids fort du premier octet est à 1, préfixer par 0x00.
 */
function mpint($data)
{
    if (empty($data)) {
        return pack("N", 0);
    }

    // Ajouter un octet 0x00 si le bit de poids fort est à 1
    if ((ord($data[0]) & 0x80) !== 0) {
        $data = "\x00" . $data;
    }

    return pack("N", strlen($data)) . $data;
}
