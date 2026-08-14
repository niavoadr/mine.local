<?php
// Vérification en ligne de commande de la limitation de vitesse.
//
//   php bandwidth_check.php          -> diagnostic
//   php bandwidth_check.php --fix    -> réécrit radgroupreply selon les profils de référence
//
// Utile en cron ou après une intervention sur la base.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/bandwidth.php';

$fix = in_array('--fix', $argv, true);
$pdo = $connexion;

if ($fix) {
    try {
        $pdo->beginTransaction();

        // Suppression des doublons (groupe, attribut)
        $pdo->exec("DELETE FROM radgroupreply a USING radgroupreply b
                    WHERE a.id > b.id AND a.groupname = b.groupname AND a.attribute = b.attribute");

        $stmtDel = $pdo->prepare("DELETE FROM radgroupreply WHERE groupname = ?::GROUPNAME_ENUM AND attribute = ?");
        $stmtIns = $pdo->prepare("INSERT INTO radgroupreply (groupname, attribute, op, value)
                                  VALUES (?::GROUPNAME_ENUM, ?, ?, ?)");

        foreach (bandwidthExpectedRows() as $row) {
            $stmtDel->execute([$row['groupname'], $row['attribute']]);
            $stmtIns->execute([$row['groupname'], $row['attribute'], $row['op'], $row['value']]);
        }

        $pdo->commit();
        echo "Profils de débit réappliqués depuis bandwidth.php\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, 'bandwidth_check --fix : ' . $e->getMessage() . "\n");
        exit(1);
    }
}

$report = bandwidthDiagnose($pdo);

echo "=== Limitation de vitesse — profils par groupe ===\n";
foreach ($report['groups'] as $g) {
    printf("%-22s %-10s down / %-10s up   %-10s  %s\n",
        $g['groupname'],
        $g['down_human'],
        $g['up_human'],
        $g['mikrotik'],
        $g['ok'] ? 'OK' : 'PROBLÈME');
}

echo "\nSessions actives : " . $report['active_sessions'] . "\n";

if ($report['issues']) {
    echo "\n=== Problèmes détectés ===\n";
    foreach ($report['issues'] as $issue) {
        echo ' - ' . $issue . "\n";
    }
    echo "\nCorrection possible : php bandwidth_check.php --fix\n";
    exit(2);
}

echo "\nAucun problème détecté : les limites sont publiées vers le NAS.\n";
exit(0);
