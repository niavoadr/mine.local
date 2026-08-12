# Fail2ban — mine.local

Fail2ban reste un service système. Ce dossier ne contient que le filtre / la jail du portail web. Les bans sont ensuite **lus** par `fail2ban_sync.php` et poussés dans `security_event` avec `source_info=Fail2ban`.

**Rôles :**

| Source | Affichage dashboard | Blocage |
|---|---|---|
| Snort | oui (badge bleu) | non |
| Fail2ban warning (ex. `mine-login`) | oui (badge rouge) | **IP** via iptables |
| Fail2ban critical (ex. `sshd`, `recidive`) | oui (badge rouge) | **IP** via iptables + **MAC** via blacklist / RADIUS si l'appareil est dans `radacct` |

## 1. Installer Fail2ban

```bash
sudo apt-get update
sudo apt-get install -y fail2ban
```

## 2. Copier le filtre et la jail

Ajuster `logpath` dans `jail.d/mine-local.conf` vers le chemin **absolu** de `logs/auth.log` (produit par `login.php`).

```bash
sudo cp fail2ban/filter.d/mine-login.conf /etc/fail2ban/filter.d/mine-login.conf
sudo cp fail2ban/jail.d/mine-local.conf   /etc/fail2ban/jail.d/mine-local.conf
sudo sed -i "s|/var/www/html/logs/auth.log|/chemin/absolu/vers/mine.local/logs/auth.log|" /etc/fail2ban/jail.d/mine-local.conf
sudo fail2ban-client reload
sudo fail2ban-client status mine-login
```

La jail `sshd` standard peut rester dans `/etc/fail2ban/jail.local` : `fail2ban_sync.php` ingère **tous** les Ban/Unban de `/var/log/fail2ban.log`.

## 3. Variables `.env`

```
CRON_API_TOKEN=<le même secret que pour Snort>
FAIL2BAN_LOG=/var/log/fail2ban.log
```

Laisser `FAIL2BAN_SSH_HOST` vide si Fail2ban tourne sur le serveur web (cas normal).

## 4. Cron (à côté de Snort, pas à la place)

```
*/2 * * * * /usr/bin/php /chemin/vers/mine.local/fail2ban_sync.php >> /var/log/fail2ban_sync.log 2>&1
```

Le compte cron doit pouvoir **lire** `/var/log/fail2ban.log`.

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
security_event  →  dashboard (badge rouge Fail2ban)
       ↓  si MAC trouvée dans radacct et sévérité warning/critical
blacklist + radcheck Reject
```
