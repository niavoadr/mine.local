<?php
// Limitation de vitesse de connexion — source de vérité et helpers partagés.
//
// Principe : les débits sont STATIQUES et définis en base (table radgroupreply).
// Ce fichier ne sert pas à « régler » les débits depuis l'interface, mais à :
//   1. connaître les attributs RADIUS attendus pour chaque groupe ;
//   2. vérifier que la base correspond bien à ce qui est attendu (diagnostic) ;
//   3. formater correctement les valeurs pour l'affichage.

require_once __DIR__ . '/helpers.php';

if (!function_exists('bandwidthProfiles')) {
    /**
     * Profils de référence, en bits par seconde (identiques au seed database/radius.sql).
     * Toute modification se fait ici + migration SQL, jamais depuis l'interface web.
     */
    function bandwidthProfiles(): array
    {
        return [
            'communication_group' => ['down' => 20000000, 'up' => 20000000, 'label' => 'Communication'],
            'daj_group'           => ['down' => 20000000, 'up' => 20000000, 'label' => 'Affaires juridiques'],
            'finance_group'       => ['down' => 30000000, 'up' => 30000000, 'label' => 'Finance'],
            'rh_group'            => ['down' => 20000000, 'up' => 20000000, 'label' => 'Ressources humaines'],
            'sg_group'            => ['down' => 50000000, 'up' => 50000000, 'label' => 'Secrétariat général'],
            'visitor_group'       => ['down' => 10000000, 'up' => 10000000, 'label' => 'Visiteurs'],
        ];
    }
}

if (!function_exists('formatBitsPerSecond')) {
    /**
     * Affichage lisible d'un débit en bits/s.
     * Corrige l'ancien round($v / 1000000) qui affichait « 0 Mbps » sous 1 Mbps.
     */
    function formatBitsPerSecond($bits): string
    {
        $bits = (int) $bits;
        if ($bits <= 0) {
            return 'Illimité';
        }
        if ($bits >= 1000000) {
            $mbps = $bits / 1000000;
            $txt  = ($mbps == floor($mbps)) ? (string) (int) $mbps : number_format($mbps, 1, ',', ' ');
            return $txt . ' Mbps';
        }
        return round($bits / 1000) . ' kbps';
    }
}

if (!function_exists('bitsToMikrotikRate')) {
    /**
     * Mikrotik-Rate-Limit attend « rx-rate/tx-rate » du point de vue du routeur,
     * c'est-à-dire upload_client/download_client. Ex : « 20M/20M ».
     */
    function bitsToMikrotikRate($up, $down): string
    {
        return mikrotikUnit($up) . '/' . mikrotikUnit($down);
    }
}

if (!function_exists('mikrotikUnit')) {
    function mikrotikUnit($bits): string
    {
        $bits = (int) $bits;
        if ($bits <= 0) {
            return '0';
        }
        if ($bits % 1000000 === 0) {
            return (int) ($bits / 1000000) . 'M';
        }
        return (int) round($bits / 1000) . 'k';
    }
}

if (!function_exists('bandwidthAttributesFor')) {
    /**
     * Attributs de réponse RADIUS à publier pour un groupe donné.
     *
     * On envoie plusieurs dialectes en même temps : un NAS ignore simplement
     * les attributs vendor-specific qu'il ne connaît pas. C'est ce qui permet
     * à la limitation de fonctionner que le NAS soit Mikrotik, CoovaChilli,
     * pfSense/FreeBSD ou UniFi, sans rien changer côté portail.
     *
     * @return array<int, array{attribute:string, op:string, value:string}>
     */
    function bandwidthAttributesFor(array $profile): array
    {
        $down = (int) $profile['down'];
        $up   = (int) $profile['up'];

        return [
            // WISPr (CoovaChilli, ChilliSpot, la plupart des hotspots) — bits/s
            ['attribute' => 'WISPr-Bandwidth-Max-Down', 'op' => ':=', 'value' => (string) $down],
            ['attribute' => 'WISPr-Bandwidth-Max-Up',   'op' => ':=', 'value' => (string) $up],
            // Mikrotik RouterOS — « upload/download »
            ['attribute' => 'Mikrotik-Rate-Limit',      'op' => ':=', 'value' => bitsToMikrotikRate($up, $down)],
            // Générique RFC 4679 / ADSL-Forward (pfSense, certains BRAS) — bits/s
            ['attribute' => 'Ascend-Data-Rate',         'op' => ':=', 'value' => (string) $down],
            ['attribute' => 'Ascend-Xmit-Rate',         'op' => ':=', 'value' => (string) $up],
        ];
    }
}

if (!function_exists('bandwidthExpectedRows')) {
    /**
     * Toutes les lignes radgroupreply attendues, tous groupes confondus.
     * @return array<int, array{groupname:string, attribute:string, op:string, value:string}>
     */
    function bandwidthExpectedRows(): array
    {
        $rows = [];
        foreach (bandwidthProfiles() as $group => $profile) {
            foreach (bandwidthAttributesFor($profile) as $attr) {
                $rows[] = [
                    'groupname' => $group,
                    'attribute' => $attr['attribute'],
                    'op'        => $attr['op'],
                    'value'     => $attr['value'],
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('bandwidthDiagnose')) {
    /**
     * Diagnostic en lecture seule de la chaîne de limitation de débit.
     * Retourne la liste des problèmes qui font qu'une limite n'est PAS appliquée.
     */
    function bandwidthDiagnose(PDO $pdo): array
    {
        $issues  = [];
        $groups  = [];
        $profiles = bandwidthProfiles();

        // 1. Attributs présents en base, par groupe
        $stmt = $pdo->query("SELECT groupname::text AS groupname, attribute, op, value
                             FROM radgroupreply
                             ORDER BY groupname, attribute");
        $inDb = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $inDb[$row['groupname']][$row['attribute']][] = $row;
        }

        foreach ($profiles as $group => $profile) {
            $expected = bandwidthAttributesFor($profile);
            $missing  = [];
            $wrong    = [];
            $dupes    = [];

            foreach ($expected as $attr) {
                $found = $inDb[$group][$attr['attribute']] ?? [];
                if (count($found) === 0) {
                    $missing[] = $attr['attribute'];
                    continue;
                }
                if (count($found) > 1) {
                    $dupes[] = $attr['attribute'];
                }
                if ((string) $found[0]['value'] !== (string) $attr['value']) {
                    $wrong[] = sprintf('%s = %s (attendu %s)', $attr['attribute'], $found[0]['value'], $attr['value']);
                }
                if (trim((string) $found[0]['op']) !== $attr['op']) {
                    $wrong[] = sprintf('%s utilise l\'opérateur « %s » (attendu « %s »)', $attr['attribute'], trim((string) $found[0]['op']), $attr['op']);
                }
            }

            $groups[] = [
                'groupname'   => $group,
                'label'       => $profile['label'],
                'down'        => $profile['down'],
                'up'          => $profile['up'],
                'down_human'  => formatBitsPerSecond($profile['down']),
                'up_human'    => formatBitsPerSecond($profile['up']),
                'mikrotik'    => bitsToMikrotikRate($profile['up'], $profile['down']),
                'missing'     => $missing,
                'wrong'       => $wrong,
                'duplicates'  => $dupes,
                'ok'          => empty($missing) && empty($wrong) && empty($dupes),
            ];

            if ($missing) {
                $issues[] = sprintf('Groupe %s : attributs absents en base (%s) — aucune limite envoyée au NAS pour ce dialecte.', $group, implode(', ', $missing));
            }
            if ($wrong) {
                $issues[] = sprintf('Groupe %s : %s', $group, implode(' ; ', $wrong));
            }
            if ($dupes) {
                $issues[] = sprintf('Groupe %s : attributs en double (%s) — FreeRADIUS renvoie deux valeurs, le NAS en applique une au hasard.', $group, implode(', ', $dupes));
            }
        }

        // 2. Comptes RADIUS sans groupe : ils s'authentifient mais sans aucune limite
        $orphans = $pdo->query("SELECT rc.username
                                FROM radcheck rc
                                LEFT JOIN radusergroup rg
                                  ON regexp_replace(lower(rc.username), '[^0-9a-z]', '', 'g')
                                   = regexp_replace(lower(rg.username), '[^0-9a-z]', '', 'g')
                                WHERE rc.attribute = 'Cleartext-Password'
                                  AND rg.username IS NULL
                                ORDER BY rc.username")->fetchAll(PDO::FETCH_COLUMN);
        if ($orphans) {
            $issues[] = sprintf('%d compte(s) sans groupe RADIUS : %s — ils se connectent sans aucune limitation.',
                count($orphans), implode(', ', array_slice($orphans, 0, 10)) . (count($orphans) > 10 ? '…' : ''));
        }

        // 3. Incohérence de format d'identifiant entre radcheck et radusergroup.
        //    FreeRADIUS fait une correspondance EXACTE sur User-Name : si le NAS
        //    envoie « AABBCCDDEEFF » et que la base contient « aa:bb:cc:dd:ee:ff »,
        //    le groupe n'est pas trouvé et aucune limite n'est appliquée.
        $mixed = $pdo->query("SELECT DISTINCT rc.username
                              FROM radcheck rc
                              JOIN radusergroup rg
                                ON regexp_replace(lower(rc.username), '[^0-9a-z]', '', 'g')
                                 = regexp_replace(lower(rg.username), '[^0-9a-z]', '', 'g')
                              WHERE rc.username <> rg.username")->fetchAll(PDO::FETCH_COLUMN);
        if ($mixed) {
            $issues[] = sprintf('Format d\'identifiant différent entre radcheck et radusergroup pour : %s — le groupe ne sera pas trouvé à l\'authentification.',
                implode(', ', array_slice($mixed, 0, 10)));
        }

        // 4. Sessions actives récentes : leur limite date du moment de la connexion
        $active = (int) $pdo->query("SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL")->fetchColumn();

        return [
            'groups'          => $groups,
            'issues'          => $issues,
            'orphans'         => $orphans,
            'active_sessions' => $active,
            'healthy'         => empty($issues),
        ];
    }
}
