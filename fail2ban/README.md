# Fail2ban — mine.local

Fail2ban tourne **en local** sur le Debian (PHP + FreeRADIUS).  
Pas de SSH, pas de nouvelles clés. Snort reste sur pfSense.

Projet : `/usr/src/app/portail/admin/mine.local`  
Symlink web : `/var/www/mine.local` → `http://portail.cpanel/login.php`

| Source | Dashboard | Blocage |
|---|---|---|
| Snort | badge bleu | aucun |
| Fail2ban warning (`mine-login`) | badge rouge | **IP** (iptables) |
| Fail2ban critical (`sshd`, `recidive`) | badge rouge | **IP** + **MAC** si l'appareil est dans `radacct` |

## 1. Une ligne dans le `.env` existant

Ne pas recréer les variables Snort / SSH. Ajouter seulement :

```
FAIL2BAN_LOG=/var/log/fail2ban.log
```

Si `INTRUSION_PHP_URL` manque :

```
INTRUSION_PHP_URL=http://portail.cpanel/intrusion.php
```

`CRON_API_TOKEN` = le même que Snort.

## 2. Installer et activer

```bash
sudo apt-get install -y fail2ban

sudo mkdir -p /usr/src/app/portail/admin/mine.local/logs
sudo chown www-data:www-data /usr/src/app/portail/admin/mine.local/logs
sudo chmod 750 /usr/src/app/portail/admin/mine.local/logs

sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/filter.d/mine-login.conf /etc/fail2ban/filter.d/
sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/jail.d/mine-local.conf   /etc/fail2ban/jail.d/
sudo fail2ban-client reload
sudo fail2ban-client status mine-login
```

## 3. Cron (en plus de Snort)

```
*/2 * * * * /usr/bin/php /usr/src/app/portail/admin/mine.local/fail2ban_sync.php >> /var/log/fail2ban_sync.log 2>&1
```

## Flux

```
échec login.php  →  logs/auth.log
       ↓
Fail2ban (jail mine-login)  →  iptables BAN IP
       ↓
/var/log/fail2ban.log
       ↓
fail2ban_sync.php  →  intrusion.php (source_info=Fail2ban)
       ↓
dashboard badge rouge
       ↓  warning  = IP seulement
       ↓  critical + MAC dans radacct = blacklist RADIUS
```
