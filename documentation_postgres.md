# Documentation PostgreSQL - Accès et Commandes de Base

Ce document répertorie les méthodes de connexion spécifiques pour les bases de données `radius` et `radius_mines`, les commandes de base pour naviguer dans l'interface `psql`, ainsi que la syntaxe des requêtes SQL essentielles (Sélection, Insertion, Mise à jour et Suppression).

---

## 1. Méthodes de connexion

Pour vous connecter aux différentes bases de données en tant qu'utilisateur `admin` sur l'hôte local (`localhost`), utilisez les commandes suivantes dans votre terminal :

*   **Pour accéder à la base de données `radius` :**
    ```bash
    psql -U admin -d radius -h localhost
    ```

*   **Pour accéder à la base de données `radius_mines` :**
    ```bash
    psql -U admin -d radius_mines -h localhost
    ```
*(Note : Il vous sera probablement demandé de saisir le mot de passe de l'utilisateur `admin` après avoir exécuté l'une de ces commandes).*

---

## 2. Afficher toutes les tables

Une fois connecté à une base de données via `psql`, vous pouvez lister toutes les tables présentes dans celle-ci avec la commande suivante :

```sql
\dt
```

Pour afficher la structure détaillée d'une table spécifique (colonnes, types de données, contraintes) :

```sql
\d nom_table
```

---

## 3. Commandes psql basiques

Voici une liste des commandes système les plus couramment utilisées une fois à l'intérieur de l'invite `psql` :

| Commande | Description |
| :--- | :--- |
| `\l` | Lister toutes les bases de données disponibles sur le serveur. |
| `\c nom_base` | Se connecter à une autre base de données (ex: `\c radius`). |
| `\d nom_table` | Décrire la structure d'une table spécifique (colonnes, types, index). |
| `\dt` | Lister toutes les tables du schéma courant. |
| `\dn` | Lister tous les schémas de la base de données. |
| `\du` | Lister tous les utilisateurs (rôles) de la base de données. |
| `\x` | Activer/Désactiver l'affichage étendu (très utile si une table a beaucoup de colonnes). |
| `\?` | Afficher l'aide sur les commandes internes de `psql` (qui commencent par `\`). |
| `\h` | Afficher l'aide sur les commandes SQL standard (ex: `\h SELECT`). |
| `\q` | Quitter l'interface `psql` et retourner au terminal système. |

---

## 4. Requêtes SQL fondamentales (CRUD)

Voici les commandes SQL principales pour manipuler les tables, les colonnes et les données :

### 4.1. Lecture de données (SELECT)
* **Lire toutes les colonnes et lignes d'une table :**
  ```sql
  SELECT * FROM nom_table;
  ```
* **Sélectionner des colonnes spécifiques avec limitation :**
  ```sql
  SELECT colonne1, colonne2 FROM nom_table LIMIT 10;
  ```
* **Compter le nombre de lignes dans une table :**
  ```sql
  SELECT COUNT(*) FROM nom_table;
  ```

---

### 4.2. Insertion de données (INSERT)
Pour ajouter une nouvelle ligne dans une table, spécifiez les nom des colonnes et leurs valeurs respectives.

* **Insérer une seule ligne :**
  ```sql
  INSERT INTO nom_table (colonne1, colonne2, colonne3) 
  VALUES ('valeur1', 'valeur2', 123);
  ```

* **Insérer plusieurs lignes d'un coup :**
  ```sql
  INSERT INTO nom_table (colonne1, colonne2, colonne3) 
  VALUES 
      ('valeur1', 'valeur2', 123),
      ('valeur3', 'valeur4', 456);
  ```

* **Insérer et retourner la ligne créee (ex: récupérer un ID auto-généré) :**
  ```sql
  INSERT INTO nom_table (colonne1, colonne2) 
  VALUES ('valeur1', 'valeur2') 
  RETURNING *;
  ```

---

### 4.3. Mise à jour de données (UPDATE)
Pour modifier des données existantes dans une table, utilisez `UPDATE`.

> ⚠️ **Attention :** Pensez toujours à inclure une clause `WHERE`, sinon toutes les lignes de la table seront modifiées !

* **Mettre à jour une colonne spécifique sous condition :**
  ```sql
  UPDATE nom_table 
  SET colonne1 = 'nouvelle_valeur' 
  WHERE id = 1;
  ```

* **Mettre à jour plusieurs colonnes à la fois :**
  ```sql
  UPDATE nom_table 
  SET colonne1 = 'nouvelle_valeur', colonne2 = 'autre_valeur' 
  WHERE condition_colonne = 'valeur';
  ```

---

### 4.4. Suppression de données (DELETE)
Pour supprimer une ou plusieurs lignes d'une table, utilisez `DELETE`.

> ⚠️ **Attention :** Sans la clause `WHERE`, l'intégralité du contenu de la table sera supprimée !

* **Supprimer des lignes spécifiques sous condition :**
  ```sql
  DELETE FROM nom_table 
  WHERE id = 5;
  ```

* **Supprimer les lignes selon une condition texte :**
  ```sql
  DELETE FROM nom_table 
  WHERE status = 'inactif';
  ```

* **Vider entièrement une table (méthode rapide) :**
  ```sql
  TRUNCATE TABLE nom_table;
  ```
