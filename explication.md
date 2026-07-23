Je vais examiner en détail chaque fichier PHP du projet pour comprendre toutes les requêtes à la base de données et les méthodes de connexion. Commençons par explorer tous les fichiers.

Laissez-moi lire en détail chaque fichier PHP pour bien comprendre les requêtes et connexions.

Je vais vérifier la partie PHP de `stock.php` et confirmer quelques détails avant de tout expliquer.

J'ai maintenant une vision complète du projet. Voici l'explication détaillée, fichier par fichier, de toutes les requêtes à la base de données et des méthodes de connexion. Je n'ai **rien modifié** pour le moment — je te présente d'abord l'état actuel, comme demandé.

---

## 1. Le système de connexion à la base de données (au cœur du projet)

La connexion est organisée en **3 couches**. Comprendre cette mécanique est essentiel, car c'est ce que tu voudras faire pointer vers la base `radius`.

### 🔹 `env.php` — le chargeur de `.env`
- Lit le fichier `.env` à la racine et place chaque `CLE=Valeur` dans `$_ENV`, `$_SERVER` et `getenv()`.
- Fournit la fonction `env('CLE', $defaut)` pour récupérer une valeur.
- Donc ton `.env` contiendra `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` (voir `.env.example`).

### 🔹 `database.php` — la « factory » centrale (le fichier le plus important)
Il définit **3 fonctions** :

1. `get_db_config($prefix = 'DB')` → construit le tableau de config en lisant :
   - `DB_HOST` (via `env($prefix.'_HOST')`)
   - `DB_PORT` (défaut `5432` → PostgreSQL)
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   
   ⚠️ **Important :** le `$prefix` détermine les variables lues. Avec `'DB'` on lit `DB_*`. Avec `'DB_NAME'` (cf. plus bas) on lirait `DB_NAME_HOST`, `DB_NAME_PORT`… qui **n'existent pas** dans le `.env`.

2. `get_db_connection($prefix = 'DB')` → crée un objet **PDO** (DSN `pgsql:host=...;port=...;dbname=...`), exécute `SET NAMES 'utf8'`, et renvoie la connexion.

3. `get_pdo_connection($prefix = 'DB')` → **strictement identique** à la précédente (code dupliqué).

### 🔹 Les 2 « wrappers » qui consomment la factory

| Fichier | Appel réel | Variables `.env` lues | Remarque |
|---|---|---|---|
| `connection.php` | `get_db_connection('DB')` | `DB_*` (les bonnes ✅) | Crée `$conn`, `$connection`, `$conection`. Utilisé par les pages d'authentification/gestion des comptes. |
| `radius_connection.php` | `get_db_connection('DB_NAME')` | `DB_NAME_HOST`, `DB_NAME_PORT`… ❌ **n'existent pas** | **Bug :** le préfixe `'DB_NAME'` est faux. Il fallait `'DB'`. Résultat : host=localhost, port=5432, name/user/pass vides → la connexion ne tombe jamais sur la bonne base. C'est ce fichier qu'utilise `radius_devices.php`. |

> 📌 C'est ce point-là (`radius_connection.php` + les `'DB_NAME'` dans `history.php`) qu'il faudra corriger pour brancher proprement tout le monde sur la base `radius`.

---

## 2. Les requêtes, fichier par fichier

### 📄 `login.php` — page de connexion
- **Connexion :** `include("./connection.php")` → préfixe `'DB'`.
- **Requête (1) — authentification :**
  ```sql
  SELECT U.*, r.nom AS lib_role
  FROM utilisateurs U, roles r
  WHERE nom_utilisateur = ? AND statut = 'actif' AND U.id_role = r.id
  ```
  Recherche l'utilisateur actif par son nom + récupère le libellé du rôle (jointure implicite).
- Ensuite : `password_verify()` en PHP (pas du SQL) sur la colonne `mot_de_passe_hash`.
- ⚠️ Tables `utilisateurs`/`roles` → **n'existent plus** dans le schéma unifié `radius` (remplacées par la table `users` avec colonnes `username`, `password_hash`, `role`, `status`).

### 📄 `manager.php` — API JSON de gestion des comptes (appelé en AJAX par `managerAdmin.js`)
- **Connexion :** `get_pdo_connection('DB')`.
- **Action `get_stats` (3 requêtes COUNT) :**
  ```sql
  SELECT COUNT(*) AS total FROM utilisateurs;
  SELECT COUNT(*) AS total FROM utilisateurs WHERE statut = 'actif';
  SELECT COUNT(*) AS total FROM roles;
  ```
- **Action `get_departements` :** `SELECT id, nom FROM departements ORDER BY nom`
- **Action `get_roles` :** `SELECT id, nom FROM roles ORDER BY nom`
- **Action `get_users` :**
  ```sql
  SELECT u.*, d.nom AS nom_departement, r.nom AS nom_role
  FROM utilisateurs u
  LEFT JOIN departements d ON u.id_departement = d.id
  LEFT JOIN roles r ON u.id_role = r.id
  ORDER BY u.date_creation DESC
  ```
- **Action `create_user` (3 + 1 requêtes) :**
  ```sql
  SELECT COUNT(*) FROM utilisateurs WHERE nom_utilisateur = ? OR email = ?   -- unicité
  SELECT COUNT(*) FROM departements WHERE id = ?                             -- dept valide
  SELECT COUNT(*) FROM roles WHERE id = ?                                    -- rôle valide
  INSERT INTO utilisateurs (nom_utilisateur, email, mot_de_passe_hash, id_departement, id_role, statut)
  VALUES (?, ?, ?, ?, ?, 'actif')
  ```
- **Action `update_status` (2 requêtes) :**
  ```sql
  SELECT COUNT(*), nom_utilisateur FROM utilisateurs WHERE id = ?   -- existence
  UPDATE utilisateurs SET statut = ? WHERE id = ?
  ```
- ⚠️ **Toutes ces requêtes** visent `utilisateurs`, `roles`, `departements` → incompatibles avec le schéma `radius` (table unique `users` + énumérations `DEPARTMENT_ENUM`/`ROLE_ENUM`).

### 📄 `history.php` — historique des accès (onglet « Étrangers »)
- **Connexion :** `get_pdo_connection('DB_NAME')` → ⚠️ **même bug de préfixe** que `radius_connection.php`.
- **Requête (1) sur `radacct`** (table RADIUS, qui existe bien dans `radius`) :
  ```sql
  SELECT username, callingstationid, framedipaddress,
         acctstarttime, acctstoptime, acctsessiontime
  FROM radacct
  WHERE 1=1
    [AND acctstarttime >= ?]   -- si date de début fournie
    [AND acctstarttime <= ?]   -- si date de fin fournie
  ORDER BY acctstarttime DESC
  LIMIT 500
  ```
  ✅ Cette requête est déjà compatible avec la base `radius`.

### 📄 `radius_devices.php` — gestion des appareils (MAC par département)
- **Connexion :** `include("./radius_connection.php")` → préfixe `'DB_NAME'` (bug).
- **`get_devices` (1 SELECT multi-jointures) sur tables RADIUS :**
  ```sql
  SELECT rc.id, rc.username AS mac_address, rc.department, rg.groupname,
         rgr.attribute, rgr.value
  FROM radcheck rc
  LEFT JOIN radusergroup rg  ON rc.username = rg.username
  LEFT JOIN radgroupreply rgr ON rg.groupname = rgr.groupname
  WHERE rgr.attribute = 'WISPr-Bandwidth-Max-Down'
  ORDER BY rc.department, rc.username
  ```
- **`add_device` (transaction, 2 INSERT) :**
  ```sql
  INSERT INTO radcheck (username, attribute, op, value, department)
  VALUES (?, 'Auth-Type', ':=', 'Accept', ?)
  INSERT INTO radusergroup (username, groupname, priority)
  VALUES (?, ?, 1)
  ```
  ⚠️ Petit écueil : `$groupname = $department . '_group'` est construit en PHP ; mais dans `radius`, `groupname` est un **enum** (`finance_group`, `rh_group`, `daj_groupe`…). Le mapping département→groupe n'est pas 1:1 (`daj_groupe` ne finit pas par `_group`), donc cet INSERT peut échouer sur l'enum.
- **`delete_device` (transaction, 2 DELETE) :**
  ```sql
  DELETE FROM radusergroup WHERE username = ?
  DELETE FROM radcheck       WHERE username = ?
  ```
- ✅ Toutes ces requêtes ciblent des tables **présentes dans `radius`** (`radcheck`, `radusergroup`, `radgroupreply`).

### 📄 `update.php` — mise à jour d'un nom d'utilisateur
- **Connexion :** `require_once("./connection.php")` → préfixe `'DB'`.
- **Requête (1) :**
  ```sql
  UPDATE users SET username = ? WHERE id = ?
  ```
  ✅ C'est la seule requête qui vise déjà la table `users` du schéma `radius`. (Redirige ensuite vers `front.php`, qui n'existe pas dans le dépôt.)

### 📄 `stock.php` — fichier « fourre-tout » (attention)
- C'est un collage de plusieurs morceaux (JS `managerAdmin.js`, HTML, et un bloc PHP en fin de fichier).
- **Connexion :** `include("./connection.php")` → préfixe `'DB'`.
- **Requête (1)** (même logique que `login.php`) :
  ```sql
  SELECT U.*, r.nom AS lib_role
  FROM utilisateurs U, roles r
  WHERE nom_utilisateur = ? AND statut = 'actif' AND U.id_role = r.id
  ```
  ⚠️ Même problème de tables `utilisateurs`/`roles`.

---

## 3. Fichiers SANS accès direct à la base de données

Pour être complet, voici les fichiers qui **ne contiennent aucune requête SQL** :

| Fichier | Rôle réel |
|---|---|
| `index.php` | Simple redirection vers `login.php`. |
| `logout.php` | Détruit la session PHP. |
| `get_alerts.php` | Lit `/var/log/syslog` avec `shell_exec` (pas de BDD). |
| `intrusion.php` | Parse des fichiers de logs (Snort/pfSense/fail2ban) — pas de BDD. |
| `dashboard_admin.php` | Page HTML/JS uniquement ; elle **appelle en AJAX** `history.php`, `blacklist.php` (⚠️ fichier **manquant**) et `intrusion.php`. |
| `dashboard_user.php` | Page HTML/JS uniquement ; appelle `history.php` en AJAX. |
| `radius_interface.php` / `radius_interface_admin.php` | Interfaces HTML/JS ; appellent `radius_devices.php` en AJAX. |
| `managerAdmin.js` / `managerUser.js` | Code JS front (appels vers `manager.php`). |

---

## Résumé des points à retenir avant modification

1. **Toutes les connexions passent par `database.php`** via 2 wrappers (`connection.php` avec `'DB'` ✅, et `radius_connection.php` avec `'DB_NAME'` ❌).
2. **2 bugs de préfixe `'DB_NAME'`** à corriger : `radius_connection.php` et `history.php`.
3. **Incompatibilité de schéma** : `login.php`, `manager.php`, `stock.php` utilisent `utilisateurs`/`roles`/`departements` → doivent être réécrits pour la table `users` (+ enums `ROLE_ENUM`, `DEPARTMENT_ENUM`, `USERS_STATUS_ENUM`) de la base `radius`.
4. **Déjà compatibles avec `radius`** : `history.php` (radacct), `radius_devices.php` (radcheck/radusergroup/radgroupreply) et `update.php` (users) — il suffit de corriger leur méthode de connexion.

Veux-tu que je passe maintenant à la modification (faire pointer **toutes** les méthodes de connexion vers la base `radius` via le préfixe `'DB'`, en corrigeant les `'DB_NAME'`), ou veux-tu d'abord que je détaille un point précis ?