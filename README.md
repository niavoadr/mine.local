# Mine Local

Application PHP de gestion des accès RADIUS et de supervision des événements de sécurité.

## Organisation

```text
.
├── app/Support/              # Services PHP réutilisables (sessions, helpers)
├── config/                   # Configuration et connexion à la base de données
├── database/                 # Schémas et scripts PostgreSQL
├── public/assets/            # Ressources accessibles par le navigateur
│   ├── css/                  # Styles propres à l'application
│   ├── images/               # Images de l'interface
│   └── vendor/               # Dépendances front-end locales (Bootstrap)
├── resources/views/          # Fragments d'interface réutilisables
│   ├── dashboard/            # Blocs des tableaux de bord
│   └── partials/             # Composants partagés
└── *.php                    # Points d'entrée et endpoints historiques
```

Les fichiers PHP à la racine restent des points d'entrée publics afin de préserver les URLs existantes et les intégrations JavaScript. Les fichiers de configuration, services et vues sont maintenant isolés dans leurs répertoires dédiés. Les petits fichiers racine `connexion.php`, `env.php`, `manager_session.php` et les anciens noms de vues sont des adaptateurs de compatibilité : ils délèguent vers la nouvelle structure et peuvent être retirés lors d'une prochaine migration des URLs.

## Installation

1. Copier `.env.example` vers `.env` et renseigner les paramètres PostgreSQL.
2. Importer le schéma adapté depuis `database/`.
3. Configurer le serveur web avec ce dépôt comme racine (ou conserver les URLs PHP existantes).
4. Vérifier que PHP dispose de l'extension `pdo_pgsql`.

Ne jamais versionner `.env` ni les secrets de production.
