Voici l'analyse complète et détaillée directement ici, sans PDF.

---

# Analyse complète de la base de données — Projet RADIUS

Le projet totalise **21 requêtes SQL** réparties sur **6 fichiers PHP**, toutes passant par un système de connexion à 3 couches.

---

## 1. La base de données `radius` (schéma cible de référence)

La base a été unifiée : il n'existe plus qu'**une seule base** nommée `radius` (structure dans `database/radius.sql`).

### Tables principales

| Table | Colonnes clés |
|---|---|
| **users** | `id`, `username` (UNIQUE), `email` (UNIQUE), `password_hash`, `department` (DEPARTMENT_ENUM), `role` (ROLE_ENUM), `status` (USERS_STATUS_ENUM), `date_creation`, `date_modification`, `last_login` |
| **visitor** | `id`, `username`, `password_hash`, `department`, `created_by` (FK→users), `date_creation`, `expires_at`, `duration`, `status`, `mac_address` (MACADDR), `nas_ip` (INET) |
| **radcheck** | `id`, `UserName`, `Attribute`, `op`, `Value`, `department` (DEPARTMENT_ENUM) |
| **radusergroup** | `id`, `UserName`, `GroupName` (GROUPNAME_ENUM), `priority` |
| **radgroupreply** | `id`, `GroupName` (GROUPNAME_ENUM), `Attribute`, `op`, `Value` |
| **radacct** | `RadAcctId`, `UserName`, `NASIPAddress`, `AcctStartTime`, `AcctStopTime`, `AcctSessionTime`, `CallingStationId`, `FramedIPAddress`, `AcctInputOctets`, `AcctOutputOctets`… |
| **blacklist** | `id`, `mac_address` (MACADDR), `reason`, `blocked_at`, `expires_at` |
| **security_event** | `id`, `event_type`, `security_status`, `source_ip`, `mac_address`, `details` (JSONB), `created_at`, `is_read`, `read_at` |
| **radreply / radpostauth / nas / nasreload** | Tables RADIUS annexes |

### Les 6 énumérations (TYPE ENUM)

| Énumération | Valeurs autorisées |
|---|---|
| ROLE_ENUM | `ADMIN`, `USER` |
| USERS_STATUS_ENUM | `active`, `inactive`, `suspended` |
| VISITOR_STATUS_ENUM | `active`, `expired` |
| SECURITY_STATUS_ENUM | `info`, `warning`, `critical` |
| DEPARTMENT_ENUM | `Communication`, `Directeur des Affaires Juridiques`, `Finance`, `Ressources Humaines`, `Secrétariat Général` |
| GROUPNAME_ENUM | `communication_group`, `daj_groupe`, `finance_group`, `rh_group`, `sg_group` |

> **Important :** ces énumérations remplacent les anciennes tables `departements` et `roles`. Le département et le rôle sont désormais des **colonnes** directement dans `users` (plus de jointures, plus de tables séparées).

---

## 2. Architecture de connexion à la base de données

La connexion est organisée en **3 couches** successives. **Aucun fichier n'ouvre une connexion PDO en direct** — tout passe par ce système.

### 2.1 Le fichier `.env` (source des identifiants)

Non versionné (dans `.gitignore`). Structure donnée par `.env.example` :
```
DB_HOST=localhost
DB_PORT=5432
DB_USER=admin
DB_PASS=********
DB_NAME=radius
```
C'est la seule source de vérité. Pour brancher le projet sur `radius`, il suffit que `DB_NAME=radius`.

### 2.2 `env.php` — chargement du `.env`

- Lit le `.env` et place chaque `CLE=Valeur` dans `$_ENV`, `$_SERVER` et via `putenv()`.
- Expose la fonction `env($cle, $defaut)` utilisée partout ensuite.
- Gère les guillemets autour des valeurs et les valeurs booléennes/null.

### 2.3 `database.php` — la « factory » centrale (cœur du système)

C'est le fichier le plus important. Il définit **3 fonctions**, toutes basées sur un paramètre `$prefix` qui détermine quelles variables `.env` sont lues :

```php
function get_db_config($prefix = 'DB') {
    $host = env($prefix.'_HOST', 'localhost');
    $port = (int) env($prefix.'_PORT', 5432);
    $name = env($prefix.'_NAME', '');
    $user = env($prefix.'_USER', '');
    $pass = env($prefix.'_PASS', '');
    return ['host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'pass'=>$pass];
}

function get_db_connection($prefix = 'DB') {
    $config = get_db_config($prefix);
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s',
                   $config['host'],$config['port'],$config['name']);
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES 'utf8'");
    return $pdo;
}

function get_pdo_connection($prefix = 'DB') { /* code STRICTEMENT identique */ }
```

**Mécanisme du préfixe :** avec `get_db_connection('DB')` on lit `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (les bonnes). Avec `get_db_connection('DB_NAME')` on lirait `DB_NAME_HOST`, `DB_NAME_PORT`, `DB_NAME_NAME`… qui **n'existent pas** dans le `.env`.

> ⚠️ Conséquence du mauvais préfixe `'DB_NAME'` : host=localhost (défaut), port=5432 (défaut), dbname/user/pass **VIDES** → la connexion échoue ou tombe sur une base vide. **Bug réel présent dans 2 fichiers.**

### 2.4 Les 2 wrappers qui consomment la factory

```php
// connection.php          → préfixe CORRECT
require_once __DIR__.'/database.php';
$conn = get_db_connection('DB');   // lit DB_*
$connection = $conn; $conection = $conn; // alias

// radius_connection.php  → préfixe INCORRECT (bug)
require_once __DIR__.'/database.php';
$conn = get_db_connection('DB_NAME'); // lit DB_NAME_* (inexistant)
```

| Wrapper | Préfixe | Variables lues | Statut | Utilisé par |
|---|---|---|---|---|
| `connection.php` | `'DB'` | `DB_*` | Correct | login, update, stock |
| `radius_connection.php` | `'DB_NAME'` | `DB_NAME_*` | Bug | radius_devices |
| (direct, sans wrapper) | `'DB'` | `DB_*` | Correct | manager |
| (direct, sans wrapper) | `'DB_NAME'` | `DB_NAME_*` | Bug | history |

### 2.5 Diagramme du flux de connexion

```
  Fichier métier (login.php, manager.php, ...)
        │
        ▼  include / require_once
  connection.php  ──ou──  radius_connection.php   (wrappers)
        │                       │
        ▼  get_db_connection('DB')   get_db_connection('DB_NAME')
  ┌──────────────── database.php ────────────────┐
  │  get_db_config($prefix) → lit le .env         │
  │  get_db_connection()    → new PDO(pgsql:...)  │
  └───────────────────────────────────────────────┘
        │  env.php lit les variables
        ▼
  .env (DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME)
        │
        ▼
  Serveur PostgreSQL  →  base « radius »
```

---

## 3. Analyse requête par requête

Chaque requête est présentée avec : emplacement exact, type, SQL complet, paramètres liés, ce qu'elle fait, tables/colonnes concernées, traitement du résultat, et compatibilité `radius`.

### 3.1 `login.php` — authentification (1 requête)

- **Ligne(s) :** 15-20
- **Connexion :** `include("./connection.php")` → `$conn = get_db_connection('DB')` [préfixe correct]
- **Déclencheur :** formulaire POST (username + pass)

**Requête #1 — SELECT d'authentification**
```sql
SELECT U.*, r.nom AS lib_role
FROM utilisateurs U, roles r
WHERE nom_utilisateur = ?
  AND statut = 'actif'
  AND U.id_role = r.id
```
- **Paramètres liés :** `[ $_POST['username'] ]` (requête préparée → pas d'injection SQL).
- **Ce qu'elle fait :** cherche l'utilisateur dont le nom correspond, qui est `actif`, et récupère le libellé de son rôle via une jointure implicite (ancienne syntaxe `FROM a, b WHERE`).
- **Tables/colonnes :** `utilisateurs.*`, `utilisateurs.statut`, `utilisateurs.id_role`, `utilisateurs.mot_de_passe_hash`, `roles.nom`.
- **Traitement :** `$row = $stmt->fetch()`. Si trouvé → `password_verify($_POST['pass'], $row['mot_de_passe_hash'])`. Si OK → session `user`, `role_lib`, `id_role`, puis redirection vers `dashboard_user.php` si `id_role == 5`, sinon `dashboard_admin.php`.

> ⚠️ **INCOMPATIBLE radius** : tables `utilisateurs` et `roles` n'existent plus. La colonne `role` étant désormais dans `users`, la jointure disparaît.
> **Migration :** `SELECT * FROM users WHERE username = ? AND status = 'active'`, et le test devient `role == 'ADMIN'` / `'USER'`.

---

### 3.2 `manager.php` — API JSON de gestion des comptes (12 requêtes / 6 actions)

- **Lignes :** 45 → 366
- **Connexion :** `$pdo = get_pdo_connection('DB')` [préfixe correct, appel direct sans wrapper]
- **Rôle :** endpoint AJAX appelé par `managerAdmin.js`, retourne du JSON.

#### Action `get_stats` — 3 requêtes COUNT (lignes 45, 48, 51)
```sql
SELECT COUNT(*) AS total FROM utilisateurs;                       -- #2
SELECT COUNT(*) AS total FROM utilisateurs WHERE statut = 'actif'; -- #3
SELECT COUNT(*) AS total FROM roles;                              -- #4
```
- **#2** : total des comptes. **#3** : comptes actifs. **#4** : nombre de rôles. Aucun paramètre (`->query()`). Résultat envoyé dans `total_users`, `active_users`, `total_roles`.

> ⚠️ **Migration :** #2 et #3 → `users` (+ `status='active'`). #4 n'a plus de table (enum) → renvoyer 2 (ADMIN, USER).

#### Action `get_departements` — SELECT (ligne 64)
```sql
SELECT id, nom FROM departements ORDER BY nom      -- #5
```
- Remplit le `<select>` département du formulaire de création. Aucun paramètre.

> ⚠️ **Migration :** la table `departements` n'existe plus → liste en dur correspondant à DEPARTMENT_ENUM.

#### Action `get_roles` — SELECT (ligne 76)
```sql
SELECT id, nom FROM roles ORDER BY nom             -- #6
```
- Remplit le `<select>` rôle. Aucun paramètre.

> ⚠️ **Migration :** table `roles` supprimée → liste en dur : ADMIN / USER.

#### Action `get_users` — SELECT avec 2 jointures (lignes 85-91)
```sql
SELECT u.*, d.nom AS nom_departement, r.nom AS nom_role   -- #7
FROM utilisateurs u
LEFT JOIN departements d ON u.id_departement = d.id
LEFT JOIN roles r        ON u.id_role = r.id
ORDER BY u.date_creation DESC
```
- Liste tous les utilisateurs avec le nom de leur département et de leur rôle. Les `id` sont convertis en entiers avant l'envoi JSON.

> ⚠️ **Migration :** plus besoin de jointures. `SELECT id, username, email, department, role, status, date_creation FROM users ORDER BY date_creation DESC`.

#### Action `create_user` — 3 vérifs + 1 INSERT (lignes 147, 168, 189, 210)
```sql
SELECT COUNT(*) FROM utilisateurs                          -- #8 (unicité)
  WHERE nom_utilisateur = ? OR email = ?
SELECT COUNT(*) FROM departements WHERE id = ?            -- #9 (dept valide)
SELECT COUNT(*) FROM roles WHERE id = ?                   -- #10 (rôle valide)
INSERT INTO utilisateurs                                   -- #11 (création)
  (nom_utilisateur, email, mot_de_passe_hash, id_departement, id_role, statut)
VALUES (?, ?, ?, ?, ?, 'actif')
```
- **#8** params `[nom_utilisateur, email]` → bloque les doublons. **#9** param `[id_departement]`. **#10** param `[id_role]`.
- **#11** params `[nom_utilisateur, email, password_hash(mot_de_passe), id_departement, id_role]` ; le mot de passe est haché par `password_hash(..., PASSWORD_DEFAULT)` avant l'INSERT.

> ⚠️ **Migration :** #8 → `users` (username/email). #9 et #10 n'ont plus de table → valider contre les enums. #11 → `INSERT INTO users (username, email, password_hash, department, role, status) VALUES (?,?,?,?,?,'active')`.

#### Action `update_status` — vérif + UPDATE (lignes 333, 363)
```sql
SELECT COUNT(*), nom_utilisateur FROM utilisateurs WHERE id = ?   -- #12
UPDATE utilisateurs SET statut = ? WHERE id = ?                   -- #13
```
- **#12** param `[user_id]` → vérifie l'existence + récupère le nom pour le message.
- **#13** params `[new_status, user_id]` (valeurs : `actif`/`suspendu`/`en_attente`).

> ⚠️ **Migration :** `utilisateurs` → `users` ; colonne `statut` → `status`. Les valeurs autorisées deviennent `active`/`inactive`/`suspended` (`en_attente` n'existe plus dans l'enum).

---

### 3.3 `history.php` — historique des accès (1 requête dynamique)

- **Lignes :** 30-52
- **Connexion :** `$pdo = get_pdo_connection('DB_NAME')` [BUG de préfixe — comme radius_connection.php]
- **Déclencheur :** AJAX POST `action=get_history` (start_date, end_date optionnelles)

**Requête #14 — SELECT dynamique sur radacct**
```sql
SELECT username, callingstationid, framedipaddress,
       acctstarttime, acctstoptime, acctsessiontime
FROM radacct
WHERE 1=1
  [AND acctstarttime >= ?]   -- si start_date fournie
  [AND acctstarttime <= ?]   -- si end_date fournie
ORDER BY acctstarttime DESC
LIMIT 500
```
- **Paramètres :** construits dynamiquement dans `$params[]` selon les dates reçues. La clause `WHERE 1=1` permet d'ajouter facilement les filtres optionnels.
- **Ce qu'elle fait :** récupère l'historique des sessions RADIUS (utilisateur, MAC, IP, heure de début/fin, durée), limité à 500 lignes, trié du plus récent au plus ancien.

> ✅ La requête SQL est **DÉJÀ compatible** avec radius (table `radacct`). SEUL le préfixe de connexion est à corriger : `get_pdo_connection('DB_NAME')` → `get_pdo_connection('DB')`.

---

### 3.4 `radius_devices.php` — gestion des appareils MAC (5 requêtes / 3 fonctions)

- **Connexion :** `include("./radius_connection.php")` → `$conn = get_db_connection('DB_NAME')` [BUG de préfixe]
- **Actions :** `get_devices`, `add_device`, `delete_device` (dispatch sur `$_POST['action']`)

#### `get_devices` — SELECT multi-jointures (lignes 57-69) — #15
```sql
SELECT rc.id, rc.username AS mac_address, rc.department,
       rg.groupname, rgr.attribute, rgr.value
FROM radcheck rc
LEFT JOIN radusergroup  rg  ON rc.username  = rg.username
LEFT JOIN radgroupreply rgr ON rg.groupname = rgr.groupname
WHERE rgr.attribute = 'WISPr-Bandwidth-Max-Down'
ORDER BY rc.department, rc.username
```
- **Aucun paramètre** (`->query()`). Joint 3 tables RADIUS pour afficher chaque appareil MAC avec son département, son groupe et sa bande passante.
- **Traitement :** pour chaque ligne, la bande passante est calculée en PHP : `round(value / 1000000) . ' Mbps'` (octets → Mbit/s).

#### `add_device` — transaction : 2 INSERT (lignes 108, 115) — #16, #17
```sql
INSERT INTO radcheck (username, attribute, op, value, department)        -- #16
VALUES (?, 'Auth-Type', ':=', 'Accept', ?)
INSERT INTO radusergroup (username, groupname, priority)                 -- #17
VALUES (?, ?, 1)
```
- **#16** params `[mac, department]`. **#17** params `[mac, $groupname]` où `$groupname = $department . '_group'` (construit en PHP).
- Les 2 INSERT sont dans une transaction : `beginTransaction()` → si succès `commit()`, sinon `rollBack()` (intégrité assurée).

> ⚠️ **Piège :** `groupname` est une énumération (GROUPNAME_ENUM). Le formulaire envoie les codes courts `'finance'`, `'rh'`, `'daj'`, `'communication'`, `'sg'` → `$groupname` devient `finance_group`, `rh_group`, `daj_group`, etc. Or l'enum contient **`daj_groupe`** (sans le tiret, avec un `e`). L'INSERT échouera pour `daj`. De même `department` doit valoir une valeur exacte de DEPARTMENT_ENUM (`'Finance'`, pas `'finance'`).

#### `delete_device` — transaction : 2 DELETE (lignes 140, 145) — #18, #19
```sql
DELETE FROM radusergroup WHERE username = ?   -- #18
DELETE FROM radcheck       WHERE username = ? -- #19
```
- **#18** et **#19** param `[mac]`. Même logique transactionnelle que `add_device`.

> ✅ Ces 5 requêtes ciblent des tables **PRÉSENTES** dans radius (`radcheck`, `radusergroup`, `radgroupreply`). Seul le préfixe de connexion est à corriger.

---

### 3.5 `update.php` — mise à jour d'un utilisateur (1 requête)

- **Lignes :** 14-15
- **Connexion :** `require_once("./connection.php")` → `$conn = get_db_connection('DB')` [préfixe correct]

**Requête #20 — UPDATE sur la table users**
```sql
UPDATE users SET username = ? WHERE id = ?        -- #20
```
- **Paramètres :** `[nom, id]` (nom = `$_POST['username']`, id = `intval($_POST['id'])`).
- **Traitement :** met à jour le nom, puis redirige vers `front.php` (⚠️ ce fichier n'existe pas dans le dépôt).

> ✅ C'est la **SEULE requête déjà écrite pour la table `users`** du schéma radius. Elle est donc directement compatible. (À noter : `front.php` est absent du projet.)

---

### 3.6 `stock.php` — bloc d'authentification (1 requête)

- **Lignes :** 1569-1574
- **Connexion :** `include("./connection.php")` → `$conn = get_db_connection('DB')` [préfixe correct]
- **Remarque :** `stock.php` est un collage hétérogène (JS + HTML + bloc PHP). Le bloc SQL est en fin de fichier.

**Requête #21 — SELECT d'authentification (identique à login.php)**
```sql
SELECT U.*, r.nom AS lib_role
FROM utilisateurs U, roles r
WHERE nom_utilisateur = ? AND statut = 'actif' AND U.id_role = r.id
```
- **Paramètre :** `[ $_POST['username'] ]`. Même logique et même traitement que la requête #1 de `login.php` (password_verify, redirection selon le rôle).

> ⚠️ **INCOMPATIBLE radius** : mêmes tables `utilisateurs`/`roles` à migrer vers `users`.

---

## 4. Fichiers SANS accès direct à la base de données

Ces fichiers ne contiennent **aucune requête SQL** ni connexion PDO. Certains consomment des données via AJAX (ils appellent les fichiers analysés ci-dessus).

| Fichier | Type | Rôle exact |
|---|---|---|
| `index.php` | PHP | Simple `header('Location: login.php')`. |
| `logout.php` | PHP | Détruit la session (`session_unset` + `session_destroy`). |
| `get_alerts.php` | PHP | Lit `/var/log/syslog` via `shell_exec` (logs système, pas de BDD). |
| `intrusion.php` | PHP | Parse des logs Snort / pfSense / fail2ban (fichiers, pas de BDD). |
| `dashboard_admin.php` | HTML/JS | Tableau de bord admin. AJAX vers `history.php`, `blacklist.php` (**MANQUANT**), `intrusion.php`. |
| `dashboard_user.php` | HTML/JS | Tableau de bord utilisateur. AJAX vers `history.php`. |
| `radius_interface.php` | HTML/JS | Interface appareils (user). AJAX vers `radius_devices.php`. |
| `radius_interface_admin.php` | HTML/JS | Interface appareils (admin). AJAX vers `radius_devices.php`. |
| `managerAdmin.js` | JS | Front : appels AJAX vers `manager.php` (gestion des comptes). |
| `managerUser.js` | JS | Front utilisateur. |
| `env.php` | PHP | Charge le `.env` (utilité de connexion, mais n'exécute pas de requête). |

> ⚠️ Point d'attention : `dashboard_admin.php` appelle `blacklist.php` qui **n'existe pas** dans le dépôt. La fonctionnalité « liste noire » est donc cassée côté front.

---

## 5. Tableau maître de toutes les requêtes du projet

| # | Fichier | Ligne | Type | Table(s) | Préfixe | radius |
|---|---|---|---|---|---|---|
| 1 | login.php | 15 | SELECT | utilisateurs, roles | DB | ⚠ Non |
| 2 | manager.php | 45 | SELECT | utilisateurs | DB | ⚠ Non |
| 3 | manager.php | 48 | SELECT | utilisateurs | DB | ⚠ Non |
| 4 | manager.php | 51 | SELECT | roles | DB | ⚠ Non |
| 5 | manager.php | 64 | SELECT | departements | DB | ⚠ Non |
| 6 | manager.php | 76 | SELECT | roles | DB | ⚠ Non |
| 7 | manager.php | 85 | SELECT | utilisateurs+join | DB | ⚠ Non |
| 8 | manager.php | 147 | SELECT | utilisateurs | DB | ⚠ Non |
| 9 | manager.php | 168 | SELECT | departements | DB | ⚠ Non |
| 10 | manager.php | 189 | SELECT | roles | DB | ⚠ Non |
| 11 | manager.php | 210 | INSERT | utilisateurs | DB | ⚠ Non |
| 12 | manager.php | 333 | SELECT | utilisateurs | DB | ⚠ Non |
| 13 | manager.php | 363 | UPDATE | utilisateurs | DB | ⚠ Non |
| 14 | history.php | 30 | SELECT | radacct | DB_NAME | ✓ Oui\* |
| 15 | radius_devices.php | 57 | SELECT | radcheck+join | DB_NAME | ✓ Oui\* |
| 16 | radius_devices.php | 108 | INSERT | radcheck | DB_NAME | ⚠ Enum\* |
| 17 | radius_devices.php | 115 | INSERT | radusergroup | DB_NAME | ⚠ Enum\* |
| 18 | radius_devices.php | 140 | DELETE | radusergroup | DB_NAME | ✓ Oui\* |
| 19 | radius_devices.php | 145 | DELETE | radcheck | DB_NAME | ✓ Oui\* |
| 20 | update.php | 14 | UPDATE | users | DB | ✓ Oui |
| 21 | stock.php | 1569 | SELECT | utilisateurs, roles | DB | ⚠ Non |

**Légende :**
- `✓ Oui` = fonctionnera sans changement.
- `✓ Oui*` = SQL compatible, mais le préfixe de connexion `'DB_NAME'` est à corriger en `'DB'`.
- `⚠ Enum*` = tables OK mais les valeurs insérées (codes courts) ne correspondent pas aux énumérations.
- `⚠ Non` = tables inexistantes dans radius (migration du schéma nécessaire).

---

## 6. Synthèse et plan de migration détaillé

### 6.1 Bilan chiffré

| Catégorie | Détail | Nombre |
|---|---|---|
| Requêtes totales | 21 requêtes SQL sur 6 fichiers | 21 |
| Requêtes incompatibles (tables absentes) | utilisateurs / roles / departements | 13 |
| Requêtes compatibles (tables RADIUS/users) | radacct, radcheck, radusergroup, radgroupreply, users | 8 |
| Bugs de préfixe `'DB_NAME'` | radius_connection.php + history.php | 2 |
| Requêtes à risque énumération | add_device (groupname, department) | 2 |
| Fichiers sans requête SQL | HTML/JS/PHP utilitaires | 11 |

### 6.2 Plan d'action en 4 étapes

**Étape 1 — Corriger les 2 bugs de préfixe (connexion)**
```php
# radius_connection.php
$conn = get_db_connection('DB_NAME');  →  get_db_connection('DB');

# history.php
$pdo = get_pdo_connection('DB_NAME');  →  get_pdo_connection('DB');
```
Effet immédiat : `radius_devices.php` et `history.php` se connectent enfin à la bonne base `radius`.

**Étape 2 — Adapter login.php et stock.php (authentification)**
```sql
AVANT : SELECT U.*, r.nom AS lib_role FROM utilisateurs U, roles r
        WHERE nom_utilisateur = ? AND statut = 'actif' AND U.id_role = r.id

APRÈS : SELECT * FROM users
        WHERE username = ? AND status = 'active'
```
Le rôle devient une colonne : remplacer `id_role == 5` par `role == 'ADMIN'` (ou `'USER'`). La colonne `mot_de_passe_hash` devient `password_hash`.

**Étape 3 — Réécrire manager.php (12 requêtes)**
- **Statistiques :** `utilisateurs` → `users`, `statut='actif'` → `status='active'` ; `COUNT roles` → valeur constante 2 ou liste enum.
- **Listes déroulantes :** `departements`/`roles` → listes en dur issues des enums DEPARTMENT_ENUM et ROLE_ENUM (plus de tables).
- **get_users :** supprimer les jointures (department et role sont des colonnes de `users`).
- **create_user :** `INSERT INTO users (username, email, password_hash, department, role, status) VALUES (?,?,?,?,?,'active')`. Valider department/role contre les enums.
- **update_status :** `UPDATE users SET status = ? WHERE id = ?` ; valeurs `active`/`inactive`/`suspended`.

**Étape 4 — Fiabiliser radius_devices.php (énumérations)**

Ajouter une fonction de mapping code court → valeur d'énumération :
```php
$dept_map = [
    'finance'       => ['dept'=>'Finance',            'group'=>'finance_group'],
    'rh'            => ['dept'=>'Ressources Humaines', 'group'=>'rh_group'],
    'daj'           => ['dept'=>'Directeur des Affaires Juridiques','group'=>'daj_groupe'],
    'communication' => ['dept'=>'Communication',       'group'=>'communication_group'],
    'sg'            => ['dept'=>'Secrétariat Général', 'group'=>'sg_group'],
];
```
Puis injecter les valeurs mappées dans les INSERT `radcheck` (department) et `radusergroup` (groupname).

### 6.3 Sécurité (point positif)

Toutes les requêtes utilisent des **requêtes préparées** avec bind de paramètres (`?`), ou `->query()` sur du SQL statique sans entrée utilisateur. Aucune concaténation directe de `$_POST` dans le SQL n'a été détectée : le projet est donc protégé contre l'injection SQL. **À conserver tel quel lors de la migration.**

---

Dis-moi si je peux enchaîner sur la **modification effective du code** (les 4 étapes du plan) pour brancher tout le projet sur la base `radius`.