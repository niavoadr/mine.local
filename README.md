# Mine Local — portail RADIUS

Application PHP de gestion RADIUS pour l'authentification des appareils, les visiteurs, les alertes et les comptes utilisateurs.

## Structure

Le projet est organisé selon une convention PHP standard :

- `app/` : code applicatif non exposé publiquement
- `public/` : racine web et fichiers accessibles par le navigateur
- `public/api/` : endpoints JSON/HTTP
- `public/assets/` : CSS, JavaScript, images et librairies frontend
- `database/` : scripts SQL
- `docs/` : documentation technique

Voir `docs/structure.md` pour le détail.

## Configuration

1. Copier `.env.example` vers `.env`.
2. Renseigner les paramètres PostgreSQL.
3. Configurer le serveur web pour pointer vers `public/`.

```bash
cp .env.example .env
```

Variables disponibles :

```env
DB_HOST=localhost
DB_PORT=5432
DB_USER=postgres
DB_PASS=change_me
DB_NAME=radius
```

## Point d'entrée

La racine web doit être `public/`, puis ouvrir :

```text
/login.php
```
