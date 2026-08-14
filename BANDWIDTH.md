# Limitation de vitesse de connexion

Les débits sont **statiques** : ils vivent en base, dans `radgroupreply`, et ne se
règlent **pas** depuis l'interface web. L'interface ne fait que les afficher et
vérifier qu'ils sont bien appliqués.

## Profils de référence

| Groupe | Département | Descendant | Montant | Mikrotik |
|---|---|---|---|---|
| `communication_group` | Communication | 20 Mbps | 20 Mbps | `20M/20M` |
| `daj_group` | Affaires juridiques | 20 Mbps | 20 Mbps | `20M/20M` |
| `finance_group` | Finance | 30 Mbps | 30 Mbps | `30M/30M` |
| `rh_group` | Ressources humaines | 20 Mbps | 20 Mbps | `20M/20M` |
| `sg_group` | Secrétariat général | 50 Mbps | 50 Mbps | `50M/50M` |
| `visitor_group` | Visiteurs | 10 Mbps | 10 Mbps | `10M/10M` |

Source unique : `bandwidth.php` → `bandwidthProfiles()`, reflété dans
`database/radius.sql` et la migration.

## Pourquoi la limite ne s'appliquait pas

1. **Un seul dialecte publié.** Seuls `WISPr-Bandwidth-Max-Down/Up` étaient
   envoyés. Un NAS qui ne parle pas WISPr (Mikrotik RouterOS, pfSense, plusieurs
   contrôleurs) ignore ces attributs sans erreur : l'utilisateur est accepté,
   mais sans aucune limite. On publie maintenant trois dialectes en parallèle
   (WISPr, `Mikrotik-Rate-Limit`, `Ascend-Data-Rate`/`Ascend-Xmit-Rate`) ; chaque
   NAS applique celui qu'il comprend et ignore les autres.
2. **Correspondance de MAC.** La liste des appareils rapprochait `radcheck` et
   `radusergroup` avec un simple `LOWER()`. Une MAC stockée `aabbccddeeff` d'un
   côté et `aa:bb:cc:dd:ee:ff` de l'autre n'était donc pas rapprochée : appareil
   sans groupe → sans limite. La jointure normalise désormais la MAC, et le
   diagnostic signale ces incohérences (elles cassent aussi la résolution de
   groupe côté FreeRADIUS, qui compare `User-Name` à l'identique).
3. **Comptes sans groupe.** Un compte présent dans `radcheck` mais absent de
   `radusergroup` s'authentifie sans profil. C'est maintenant détecté, et la
   création d'un visiteur échoue explicitement si `visitor_group` ou son profil
   de débit manque, au lieu de créer un compte non limité en silence.
4. **Affichage trompeur.** `round($v / 1000000)` affichait « 0 Mbps » sous
   1 Mbps et « N/A » aussi bien pour « pas de groupe » que pour « pas de
   profil ». L'affichage distingue désormais les deux cas.
5. **Doublons.** Deux lignes pour un même couple (groupe, attribut) font que
   FreeRADIUS renvoie deux valeurs et que le NAS en applique une au hasard. Un
   index unique l'empêche désormais.

## Application

```bash
psql -d radius -f database/migrations/2026_08_14_bandwidth_limits.sql
sudo systemctl restart freeradius
```

## Vérification

```bash
php bandwidth_check.php          # diagnostic
php bandwidth_check.php --fix    # réaligne radgroupreply sur les profils
```

Même diagnostic dans le dashboard admin : carte « Limitation de vitesse
(lecture seule) », bouton *Vérifier*.

Test direct du côté RADIUS, pour voir les attributs réellement renvoyés :

```bash
radtest aa:bb:cc:dd:ee:ff <RADIUS_MAC_SECRET> 127.0.0.1 0 <secret_client>
# La réponse Access-Accept doit contenir WISPr-Bandwidth-Max-Down,
# Mikrotik-Rate-Limit, etc.
```

Si `radtest` montre bien les attributs mais que le débit reste illimité, le
problème est côté NAS : il ne sait pas exploiter le dialecte reçu. Il faut alors
identifier le modèle exact de l'équipement pour ajouter le bon attribut
vendor-specific dans `bandwidthAttributesFor()`.

## Sessions déjà ouvertes

Un profil est appliqué **au moment de l'authentification**. Modifier
`radgroupreply` ne change rien à une session en cours : il faut une
reconnexion, ou un CoA/Disconnect envoyé au NAS sur le port 3799. Le panneau de
diagnostic rappelle le nombre de sessions actives concernées.
