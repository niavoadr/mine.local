# Authentification par adresse MAC (MAB) — pfSense + FreeRADIUS + PostgreSQL

Ce document décrit la configuration attendue côté pfSense et côté serveur Debian
pour que l'ajout d'un appareil depuis l'interface web (`radius_devices.php`)
donne réellement accès à Internet.

---

## 1. Ce que fait l'application

Quand on ajoute une adresse MAC dans la liste des appareils autorisés :

```sql
INSERT INTO radcheck (username, attribute, op, value, department)
VALUES ('f8:a2:xx:xx:xx:xx', 'Auth-Type', ':=', 'Accept', 'Finance');

INSERT INTO radusergroup (username, groupname, priority)
VALUES ('f8:a2:xx:xx:xx:xx', 'finance_group', 1);
```

- `username` : adresse MAC normalisée en **minuscules, séparée par des deux-points**.
- `Auth-Type := Accept` : FreeRADIUS accepte la demande **sans vérifier le mot de passe**.
  C'est pour cette raison qu'aucun secret partagé n'est stocké côté application
  (la variable `RADIUS_MAC_SECRET` a été supprimée).
- `radusergroup` relie l'appareil au groupe de son département, qui porte les
  limitations de bande passante (`radgroupreply`).

---

## 2. Configuration pfSense (Services → Captive Portal → zone)

| Paramètre | Valeur attendue |
| :--- | :--- |
| Authentication Method | **Use RADIUS MAC Authentication** |
| Login Page Fallback | ✅ coché si on veut aussi le login/mot de passe visiteur |
| Authentication Server | le serveur RADIUS (Debian) |
| RADIUS MAC Secret | **non vide** (valeur libre, ignorée grâce à `Auth-Type := Accept`) |
| MAC address format | **Default** (`00:11:22:33:44:55`) |

### Pièges fréquents

1. **« Use an authentication backend » seul n'envoie JAMAIS de requête MAC.**
   Dans ce mode, pfSense n'envoie que le login/mot de passe du formulaire.
   Il faut activer « Use RADIUS MAC Authentication » (+ Login Page Fallback si
   on souhaite conserver la connexion par identifiant).

2. **Un captive portal n'est pas du 802.1X.**
   Aucune requête RADIUS n'est émise au moment de l'association Wi-Fi.
   Le client doit d'abord émettre une **requête HTTP** interceptée par le portail.
   Un terminal qui ne fait que du HTTPS / DoH peut ne rien déclencher.

3. **Deux secrets différents à ne pas confondre :**
   - le *shared secret NAS* entre pfSense et FreeRADIUS (`clients.conf`) — obligatoire ;
   - le *RADIUS MAC Secret* du portail — envoyé comme mot de passe, ignoré ici.

---

## 3. Configuration FreeRADIUS (Debian)

### 3.1. Déclarer pfSense comme client

`/etc/freeradius/3.0/clients.conf` :

```
client pfsense {
    ipaddr = 192.168.x.x       # IP de l'interface pfSense qui parle au RADIUS
    secret = <shared_secret_NAS>
    require_message_authenticator = no
    nas_type = other
}
```

### 3.2. Activer le module SQL PostgreSQL

`/etc/freeradius/3.0/mods-available/sql` :

```
sql {
    driver = "rlm_sql_postgresql"
    dialect = "postgresql"
    server   = "localhost"
    port     = 5432
    login    = "admin"
    password = "<mot_de_passe>"
    radius_db = "radius"
    read_clients = no
}
```

Puis :

```bash
sudo ln -s ../mods-available/sql /etc/freeradius/3.0/mods-enabled/sql
```

Et décommenter `sql` dans les sections **`authorize`** et **`post-auth`** de
`/etc/freeradius/3.0/sites-enabled/default` (et `inner-tunnel` si utilisé).

### 3.3. Normaliser la casse / le format de la MAC

Si pfSense envoie la MAC en majuscules ou avec un autre séparateur, la comparaison
SQL échoue. Deux options :

- côté pfSense : `MAC address format = Default` ;
- côté FreeRADIUS : appeler `rewrite_calling_station_id`
  (`policy.d/filter`) ou forcer `%{tolower:%{User-Name}}` dans la requête SQL.

### 3.4. Ouvrir le pare-feu

```bash
sudo ufw allow from <IP_pfSense> to any port 1812,1813 proto udp
```

---

## 4. Procédure de diagnostic

```bash
# 1. Un paquet RADIUS arrive-t-il seulement ?
sudo tcpdump -ni any udp port 1812 -vv

# 2. FreeRADIUS en mode debug (sortie la plus parlante)
sudo systemctl stop freeradius
sudo freeradius -X

# 3. Simuler la requête envoyée par pfSense (MAC en username)
radtest 'f8:a2:xx:xx:xx:xx' 'nimportequoi' 127.0.0.1 0 <shared_secret_NAS>

# 4. Vérifier le contenu de la base
psql -U admin -d radius -h localhost -c \
  "SELECT username, attribute, op, value, department FROM radcheck ORDER BY id DESC LIMIT 10;"
```

### Interprétation

| Symptôme | Cause probable |
| :--- | :--- |
| `tcpdump` ne voit rien | pfSense n'envoie pas : mauvais mode d'authentification, ou le client n'a pas émis de requête HTTP |
| `tcpdump` voit un paquet, pas de réponse | `clients.conf` incomplet ou mauvais shared secret NAS |
| `Access-Reject` dans `freeradius -X` | MAC absente de `radcheck`, ou format/casse différents |
| `radtest` KO en local | module SQL non activé, ou identifiants PostgreSQL erronés |
| Accès OK mais pas de limitation de débit | `radusergroup` / `radgroupreply` mal renseignés |

---

## 5. Hors périmètre

La connexion **par identifiant / mot de passe** (visiteurs, `visitor_manager.php`)
continue d'utiliser `Cleartext-Password` dans `radcheck` et n'est pas concernée
par ce document.
