<?php
/**
 * Fonctions utilitaires pour l'authentification des visiteurs via portail captif.
 *
 * Important : ces fonctions protègent la méthode d'authentification MAC existante
 * en ne supprimant jamais une entrée radcheck dont l'adresse MAC est rattachée à
 * radusergroup (appareils gérés par l'interface MAC/MAB).
 */

require_once __DIR__ . '/env.php';

if (!function_exists('visitor_normalize_mac_address')) {
  /**
   * Normalise une adresse MAC au format xx:xx:xx:xx:xx:xx.
   * Accepte les formats avec deux-points, tirets, points, espaces ou sans séparateur.
   */
  function visitor_normalize_mac_address($macRaw)
  {
    $cleanMac = strtolower(preg_replace('/[^a-fA-F0-9]/', '', (string) $macRaw));

    if (strlen($cleanMac) !== 12) {
      throw new InvalidArgumentException("Format d'adresse MAC invalide (12 caractères hexadécimaux requis)");
    }

    return implode(':', str_split($cleanMac, 2));
  }
}

if (!function_exists('visitor_compact_mac_address')) {
  /**
   * Retourne une MAC normalisée sans séparateur, utile pour les comparaisons SQL.
   */
  function visitor_compact_mac_address($macRaw)
  {
    return str_replace(':', '', visitor_normalize_mac_address($macRaw));
  }
}

if (!function_exists('visitor_looks_like_mac_address')) {
  /**
   * Indique si une chaîne ressemble à une adresse MAC complète.
   */
  function visitor_looks_like_mac_address($value)
  {
    $clean = preg_replace('/[^a-fA-F0-9]/', '', (string) $value);
    return strlen($clean) === 12;
  }
}

if (!function_exists('visitor_is_dummy_mac_address')) {
  /**
   * Les visiteurs sont créés avant de connaître la MAC ; l'ancienne valeur par
   * défaut 00:00:00:00:00:00 doit être ignorée pour les suppressions radcheck.
   */
  function visitor_is_dummy_mac_address($macRaw)
  {
    try {
      return visitor_compact_mac_address($macRaw) === '000000000000';
    } catch (Throwable $e) {
      return true;
    }
  }
}

if (!function_exists('visitor_normalized_mac_sql')) {
  /**
   * Expression PostgreSQL qui compare les MAC sans tenir compte du format stocké.
   */
  function visitor_normalized_mac_sql($column)
  {
    return "regexp_replace(lower(($column)::text), '[^0-9a-f]', '', 'g')";
  }
}

if (!function_exists('visitor_mac_has_static_group')) {
  /**
   * Une MAC présente dans radusergroup appartient à la méthode MAC/MAB existante.
   */
  function visitor_mac_has_static_group(PDO $pdo, $macRaw)
  {
    if (visitor_is_dummy_mac_address($macRaw)) {
      return false;
    }

    $macCompact = visitor_compact_mac_address($macRaw);
    $sql = 'SELECT COUNT(*) FROM radusergroup rg WHERE ' . visitor_normalized_mac_sql('rg.username') . ' = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$macCompact]);

    return ((int) $stmt->fetchColumn()) > 0;
  }
}

if (!function_exists('visitor_mac_has_other_active_visitor')) {
  /**
   * Vérifie si une autre validation visiteur encore active utilise déjà cette MAC.
   * Cela évite de couper l'accès d'un autre visiteur actif lors du nettoyage.
   */
  function visitor_mac_has_other_active_visitor(PDO $pdo, $macRaw, $excludeVisitorId = null)
  {
    if (visitor_is_dummy_mac_address($macRaw)) {
      return false;
    }

    $macCompact = visitor_compact_mac_address($macRaw);
    $sql = "SELECT COUNT(*)
              FROM visitor v
             WHERE " . visitor_normalized_mac_sql('v.mac_address') . " = ?
               AND v.status = 'active'
               AND v.expires_at > NOW()";
    $params = [$macCompact];

    if ($excludeVisitorId !== null) {
      $sql .= ' AND v.id <> ?';
      $params[] = (int) $excludeVisitorId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ((int) $stmt->fetchColumn()) > 0;
  }
}

if (!function_exists('visitor_delete_radcheck_mac')) {
  /**
   * Supprime les entrées radcheck d'une MAC ajoutée par le portail captif.
   * Protection : si la MAC est aussi dans radusergroup, on ne supprime rien afin
   * de ne pas toucher à la méthode de connexion MAC/MAB existante.
   */
  function visitor_delete_radcheck_mac(PDO $pdo, $macRaw)
  {
    if (visitor_is_dummy_mac_address($macRaw)) {
      return 0;
    }

    $macCompact = visitor_compact_mac_address($macRaw);

    if (visitor_mac_has_static_group($pdo, $macCompact)) {
      return 0;
    }

    $sql = 'DELETE FROM radcheck rc
             WHERE ' . visitor_normalized_mac_sql('rc.username') . ' = ?
               AND NOT EXISTS (
                 SELECT 1
                   FROM radusergroup rg
                  WHERE ' . visitor_normalized_mac_sql('rg.username') . ' = ?
               )';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$macCompact, $macCompact]);

    return $stmt->rowCount();
  }
}

if (!function_exists('visitor_delete_radcheck_mac_if_unused')) {
  /**
   * Supprime une MAC de radcheck uniquement si aucun autre visiteur actif ne
   * dépend encore de cette autorisation.
   */
  function visitor_delete_radcheck_mac_if_unused(PDO $pdo, $macRaw, $excludeVisitorId = null)
  {
    if (visitor_mac_has_other_active_visitor($pdo, $macRaw, $excludeVisitorId)) {
      return 0;
    }

    return visitor_delete_radcheck_mac($pdo, $macRaw);
  }
}

if (!function_exists('visitor_delete_legacy_radcheck_credentials')) {
  /**
   * Nettoie les anciennes lignes radcheck créées par l'ancienne méthode
   * (username visiteur + mot de passe). Les nouvelles créations n'en ajoutent
   * plus. On évite les usernames qui ressemblent à une MAC pour ne pas toucher
   * aux appareils MAC/MAB.
   */
  function visitor_delete_legacy_radcheck_credentials(PDO $pdo, $username)
  {
    $username = trim((string) $username);

    if ($username === '' || visitor_looks_like_mac_address($username)) {
      return 0;
    }

    $stmt = $pdo->prepare("DELETE FROM radcheck
                            WHERE username = ?
                              AND attribute = 'Cleartext-Password'
                              AND department IS NULL");
    $stmt->execute([$username]);

    return $stmt->rowCount();
  }
}

if (!function_exists('visitor_upsert_radcheck_mac')) {
  /**
   * Ajoute/remplace l'autorisation RADIUS MAC pour un visiteur valide.
   * Si la MAC appartient déjà à la méthode MAC/MAB (radusergroup), aucune
   * modification radcheck n'est faite pour respecter cette méthode.
   */
  function visitor_upsert_radcheck_mac(PDO $pdo, $macRaw, $radiusMacSecret, $department = null)
  {
    $mac = visitor_normalize_mac_address($macRaw);
    $secret = trim((string) $radiusMacSecret);

    if ($secret === '') {
      throw new RuntimeException('RADIUS_MAC_SECRET non configuré dans le fichier .env');
    }

    if (visitor_mac_has_static_group($pdo, $mac)) {
      return [
        'mac_address' => $mac,
        'inserted' => false,
        'skipped_static_mac' => true,
      ];
    }

    // Évite les doublons radcheck du portail captif pour cette MAC.
    visitor_delete_radcheck_mac($pdo, $mac);

    $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value, department)
                            VALUES (?, 'Cleartext-Password', ':=', ?, ?)");
    $stmt->execute([$mac, $secret, $department ?: null]);

    return [
      'mac_address' => $mac,
      'inserted' => true,
      'skipped_static_mac' => false,
    ];
  }
}

if (!function_exists('visitor_cleanup_expired_visitors')) {
  /**
   * Expire les validations visiteurs arrivées à échéance et supprime leur MAC
   * de radcheck, tout en conservant toutes les informations dans visitor.
   */
  function visitor_cleanup_expired_visitors(PDO $pdo)
  {
    $ownTransaction = !$pdo->inTransaction();
    $cleaned = 0;

    if ($ownTransaction) {
      $pdo->beginTransaction();
    }

    try {
      $stmt = $pdo->query("SELECT id, username, mac_address::text AS mac_address, status::text AS status
                             FROM visitor
                            WHERE expires_at <= NOW()
                            FOR UPDATE");
      $visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

      $updateStmt = $pdo->prepare("UPDATE visitor SET status = 'expired' WHERE id = ? AND status <> 'expired'");

      foreach ($visitors as $visitor) {
        visitor_delete_radcheck_mac_if_unused($pdo, $visitor['mac_address'], (int) $visitor['id']);
        visitor_delete_legacy_radcheck_credentials($pdo, $visitor['username']);
        $updateStmt->execute([(int) $visitor['id']]);
        $cleaned++;
      }

      if ($ownTransaction) {
        $pdo->commit();
      }

      return $cleaned;
    } catch (Throwable $e) {
      if ($ownTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }
}

if (!function_exists('visitor_expire_visitor_by_id')) {
  /**
   * Force l'expiration d'un visiteur précis et nettoie ses anciennes
   * autorisations radcheck sans supprimer la ligne visitor.
   */
  function visitor_expire_visitor_by_id(PDO $pdo, $visitorId)
  {
    $ownTransaction = !$pdo->inTransaction();

    if ($ownTransaction) {
      $pdo->beginTransaction();
    }

    try {
      $stmt = $pdo->prepare("SELECT id, username, mac_address::text AS mac_address
                               FROM visitor
                              WHERE id = ?
                              FOR UPDATE");
      $stmt->execute([(int) $visitorId]);
      $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$visitor) {
        if ($ownTransaction) {
          $pdo->commit();
        }
        return 0;
      }

      visitor_delete_radcheck_mac_if_unused($pdo, $visitor['mac_address'], (int) $visitor['id']);
      visitor_delete_legacy_radcheck_credentials($pdo, $visitor['username']);

      $updateStmt = $pdo->prepare("UPDATE visitor SET status = 'expired' WHERE id = ? AND status <> 'expired'");
      $updateStmt->execute([(int) $visitor['id']]);

      if ($ownTransaction) {
        $pdo->commit();
      }

      return 1;
    } catch (Throwable $e) {
      if ($ownTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
      throw $e;
    }
  }
}
