<?php
// C4 : Cron — expire les comptes visiteurs et supprime leur accès RADIUS.
// Crontab : * * * * * /usr/bin/php /chemin/vers/expire_visitors.php >> /var/log/expire_visitors.log 2>&1

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/connexion.php';

try {
    $pdo = $connexion;
    $pdo->beginTransaction();

    $pdo->exec("UPDATE visitor SET status = 'expired'
                WHERE status = 'active' AND expires_at < now()");

    $pdo->exec("DELETE FROM radcheck r USING visitor v
                WHERE v.status = 'expired' AND v.username = r.username");

    // M2 : suppression du groupe bande passante du visiteur expiré
    $pdo->exec("DELETE FROM radusergroup r USING visitor v
                WHERE v.status = 'expired' AND v.username = r.username");

    $pdo->commit();
    echo date('Y-m-d H:i:s') . " expire_visitors OK\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'expire_visitors: ' . $e->getMessage() . "\n");
    exit(1);
}
