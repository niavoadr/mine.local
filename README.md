# RADIUS — Ministère des Mines

Application web de gestion RADIUS (authentification, appareils, visiteurs, sécurité).

## Structure du projet

```
mine_local/
├── index.php                 # Point d'entrée (redirige vers login)
├── login.php                 # Authentification
├── logout.php                # Déconnexion
├── dashboard_admin.php       # Tableau de bord administrateur
├── dashboard_user.php        # Tableau de bord utilisateur
├── radius_interface_admin.php
├── radius_interface_user.php
│
├── api/                      # Endpoints JSON / AJAX
│   ├── app_session.php       # Heartbeat de session applicative
│   ├── blacklist.php         # Liste noire MAC
│   ├── get_alerts.php        # Alertes sécurité
│   ├── history.php           # Historique RADIUS
│   ├── intrusion.php         # Détections d'intrusion
│   ├── manager.php           # CRUD utilisateurs
│   ├── radius_devices.php    # Gestion des appareils
│   ├── update.php            # Mise à jour utilisateur (legacy)
│   └── visitor_manager.php   # Comptes visiteurs
│
├── assets/                   # Ressources statiques (publiques)
│   ├── css/
│   ├── js/
│   └── images/
│
├── config/                   # Configuration (hors logique métier)
│   ├── env.php               # Chargeur .env
│   └── connexion.php         # Connexion PDO PostgreSQL
│
├── includes/                 # Partiels PHP réutilisables
│   ├── manager_admin.php
│   ├── manager_user.php
│   ├── manager_session.php
│   └── manager_users_table.php
│
├── database/                 # Schémas SQL
│   ├── app_sessions.sql
│   └── radius.sql
│
├── docs/                     # Documentation
│   └── postgres.md
│
├── .env.example              # Modèle de variables d'environnement
└── .gitignore
```

## Conventions respectées

| Dossier       | Rôle                                      |
|---------------|-------------------------------------------|
| `api/`        | Endpoints backend appelés en AJAX / fetch |
| `assets/`     | CSS, JS, images servis au navigateur      |
| `config/`     | Config & bootstrap (env, BDD)             |
| `includes/`   | Fragments PHP inclus par les pages        |
| `database/`   | Scripts SQL de schéma                     |
| `docs/`       | Documentation projet                      |
| Racine        | Pages web accessibles directement         |

## Installation rapide

1. Copier l'environnement :
   ```bash
   cp .env.example .env
   ```
2. Renseigner `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` dans `.env`.
3. Appliquer les schémas SQL si besoin (`database/*.sql`).
4. Servir la racine du projet via Apache/Nginx/PHP built-in :
   ```bash
   php -S localhost:8080
   ```
5. Ouvrir `http://localhost:8080/` → redirection vers `login.php`.

## Notes

- Le fichier `.env` n'est **jamais** versionné (voir `.gitignore`).
- Les pages racine (`dashboard_*.php`, `login.php`, …) restent à la racine pour des URLs simples et un déploiement sans rewrite.
- Les appels AJAX pointent vers `api/*.php`.
- Les assets sont référencés via `assets/css/`, `assets/js/`, `assets/images/`.
