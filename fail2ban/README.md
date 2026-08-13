# Fail2ban — portail admin

Fail2ban tourne **sur le Debian**. Snort reste sur pfSense.

| URL | Jail | Blocage |
|---|---|---|
| `http://portail.cpanel/login.php` | `mine-login` | IP (iptables) |
| `http://192.168.0.99/login.php` | `mine-login` (même `login.php`) | IP (iptables) |

Un seul `logs/auth.log`. Pas de 2ᵉ jail.

| Gravité | Jail | Effet |
|---|---|---|
| warning | `mine-login` | IP |
| critical | `sshd`, `recidive` | IP + MAC si connue dans `radacct` |

## 1. `.env`

```
FAIL2BAN_LOG=/var/log/fail2ban.log
INTRUSION_PHP_URL=http://portail.cpanel/intrusion.php
```

`CRON_API_TOKEN` = celui de Snort.

## 2. Jail

```bash
sudo mkdir -p /usr/src/app/portail/admin/mine.local/logs
sudo touch /usr/src/app/portail/admin/mine.local/logs/auth.log
sudo chown www-data:www-data /usr/src/app/portail/admin/mine.local/logs /usr/src/app/portail/admin/mine.local/logs/auth.log
sudo chmod 750 /usr/src/app/portail/admin/mine.local/logs
sudo chmod 640 /usr/src/app/portail/admin/mine.local/logs/auth.log

sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/filter.d/mine-login.conf /etc/fail2ban/filter.d/
sudo cp /usr/src/app/portail/admin/mine.local/fail2ban/jail.d/mine-local.conf   /etc/fail2ban/jail.d/
sudo fail2ban-client reload
sudo fail2ban-client status
sudo fail2ban-client status mine-login
```

## 3. Cron

```
*/2 * * * * /usr/bin/php /usr/src/app/portail/admin/mine.local/fail2ban_sync.php >> /var/log/fail2ban_sync.log 2>&1
```
