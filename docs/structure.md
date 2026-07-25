# Structure du projet

Le projet suit désormais une organisation PHP classique avec une séparation claire entre le code applicatif, les fichiers exposés au navigateur, les assets et la documentation.

```text
app/
  Config/              Configuration et connexion base de données
  Services/            Services applicatifs réutilisables
  Support/             Helpers communs HTTP, auth, sécurité
  Views/               Fragments de vues PHP non exposés directement
public/
  api/                 Endpoints HTTP/JSON
  assets/              CSS, JavaScript, images et librairies frontend
  radius/              Pages RADIUS intégrées au dashboard
  index.php            Point d'entrée public
  login.php            Authentification
  dashboard-*.php      Pages dashboard
  logout.php           Déconnexion
database/              Schémas SQL et migrations
docs/                  Documentation technique
```

## Convention de déploiement

La racine web du serveur doit pointer vers le dossier `public/`.

Exemple Apache/Nginx :

```text
DocumentRoot /chemin/du/projet/public
```

Ainsi, les fichiers sensibles (`app/`, `.env`, `database/`, `docs/`) ne sont pas servis directement par le navigateur.

## Endpoints principaux

- `public/api/visitor-manager.php` : gestion des visiteurs
- `public/api/blacklist.php` : gestion de la liste noire
- `public/api/intrusions.php` : détection/intrusions
- `public/api/alerts.php` : flux d'alertes sécurité
- `public/api/manager.php` : gestion des comptes
- `public/api/radius-devices.php` : appareils RADIUS
- `public/api/app-session.php` : heartbeat des sessions applicatives

## Assets

- `public/assets/css/pages/` : styles propres à une page
- `public/assets/css/components/` : styles de composants réutilisables
- `public/assets/css/app/` : styles globaux
- `public/assets/js/pages/` : scripts propres à une page
- `public/assets/js/components/` : scripts de composants
- `public/assets/js/vendor/` et `public/assets/css/vendor/` : librairies tierces locales
