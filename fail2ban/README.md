# Fail2ban — 3 adresses

Fail2ban tourne **sur le Debian**. Snort reste sur pfSense.

| URL | Jail | Où ça bloque |
|---|---|---|
| `http://portail.cpanel/login.php` | `mine-login` | iptables Debian |
| `http://192.168.0.99/login.php` | `mine-login` (même `login.php`) | iptables Debian |
| `http://192.168.0.1:8002/index.php?zone=debian` | `pfsense-portal` | iptables Debian **et** table pf `fail2ban` sur pfSense |

`portail.cpanel` et `192.168.0.99` = **un seul** fichier `logs/auth.log`. Pas de 2ᵉ jail.

| Gravité | Jail | Effet |
|---|---|---|
| warning | `mine-login`, `pfsense-portal` | IP |
| critical | `sshd`, `recidive` | IP + MAC si connue dans `radacct` |

## 1. `.env` (déjà en place)

```
FAIL2BAN_LOG=/var/log/fail2ban.log
INTRUSION_PHP_URL=http://portail.cpanel/intrusion.php
```

Pas de nouvelles clés. `CRON_API_TOKEN` = celui de Snort.

## 2. Jail portail admin (déjà OK chez toi)

```bash
sudo mkdir -p /usr/src/app/portail/admin/mine.local/logs
sudo touch /usr/src/app/portail/admin/mine.local/logs/auth.log
sudo chown www-data:www-data /usr/src/app/portail/admin/mine.local/logs /usr/src/app/portail/admin/mine.local/logs/auth.log
sudo chmod 750 /usr/src/app/portail/admin/mine.local/logs
sudo chmod 640 /usr/src/app/portail/admin/mine.local/logs/auth.log
```

## 3. Portail captif pfSense

### 3a. Fichier de log + rsyslog

```bash
sudo mkdir -p /var/log/pfsense
sudo touch /var/log/pfsense/portal.log
sudo chmod 640 /var/log/pfsense/portal.log

sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/rsyslog.d/pfsense-portal.conf \
        /etc/rsyslog.d/30-pfsense-portal.conf
sudo systemctl restart rsyslog
```

Sur **pfSense** : *Status → System Logs → Settings*

- Enable Remote Logging
- IP du Debian : `192.168.0.99`
- Port `514` UDP
- Remote log contents : *Everything* (ou Portal Auth + System)

### 3b. Table pf `fail2ban` sur pfSense

*Firewall → Aliases* (ou en SSH) :

```
pfctl -t fail2ban -T add 127.0.0.2
```

*Firewall → Rules → LAN* : règle **Block** source = table/alias `fail2ban`, tout en haut.

Sans cette règle, Fail2ban peut voir les échecs mais le `:8002` reste ouvert.

### 3c. Clé SSH pour l’action (la même que Snort)

```bash
sudo mkdir -p /etc/fail2ban/ssh
sudo nano /etc/fail2ban/ssh/pfsense_key
# coller le contenu de PFSENSE_SSH_KEY (déjà dans le .env)
sudo chmod 600 /etc/fail2ban/ssh/pfsense_key
```

## 4. Copier filtre / action / jail puis recharger

```bash
sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/filter.d/*.conf /etc/fail2ban/filter.d/
sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/action.d/*.conf /etc/fail2ban/action.d/
sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/jail.d/mine-local.conf /etc/fail2ban/jail.d/

sudo fail2ban-client reload
sudo fail2ban-client status
sudo fail2ban-client status mine-login
sudo fail2ban-client status pfsense-portal
```

## 5. Cron

```
*/2 * * * * /usr/bin/php /usr/src/app/portail/admin/mine.local/fail2ban_sync.php >> /var/log/fail2ban_sync.log 2>&1
```
