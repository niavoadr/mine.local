# FreeRADIUS — Liste noire + Visiteur 1 identifiant = 1 appareil

## Ce que ça fait

### 1. Liste noire bloque TOUT, même avec des identifiants visiteur valides

Quand un appareil dont la MAC est dans la liste noire tente de se connecter :
- **Même avec un identifiant visiteur valide** → **REJECT**
- L'appareil ne peut JAMAIS accéder au réseau tant qu'il est bloqué

### 2. 1 identifiant visiteur = 1 appareil

- Un identifiant visiteur ne peut être utilisé que par **un seul appareil**
- Le premier appareil qui se connecte avec succès "réserve" l'identifiant
- Un autre appareil ne peut plus utiliser ce même identifiant
- **Mais** si la tentative échoue (MAC blacklistée, mauvais mot de passe...),
  l'identifiant n'est **pas** réservé et reste réutilisable
- Le même appareil peut se reconnecter avec le même identifiant

## Logique FreeRADIUS

```
authorize {
    preprocess

    # ÉTAPE 1 : MAC dans la liste noire ? → REJECT
    blacklist_check

    # ÉTAPE 2 : Autorisation SQL normale
    sql

    # ÉTAPE 3 : Visiteur déjà utilisé par un autre appareil ? → REJECT
    visitor_one_device

    ...
}
```

## Installation

### 1. Copier la politique

```bash
# Debian/Ubuntu
sudo cp policy.conf /etc/freeradius/3.0/policy.d/blacklist_visitor

# CentOS/RHEL
sudo cp policy.conf /etc/raddb/policy.d/blacklist_visitor
```

### 2. Modifier le virtual server

Éditer `/etc/freeradius/3.0/sites-available/default` (ou `/etc/raddb/sites-available/default`) :

Dans la section `authorize`, ajouter **avant** `sql` :

```
blacklist_check
```

Et **après** `sql` :

```
visitor_one_device
```

Exemple de section `authorize` modifiée :

```freeradius
authorize {
    preprocess
    auth_log

    # Vérification liste noire : MAC bloquée → reject immédiatement
    blacklist_check

    # Autorisation SQL standard
    sql

    # Vérification visiteur : 1 identifiant = 1 appareil
    visitor_one_device

    expiration
    logintime
    pap
}
```

### 3. Créer les index PostgreSQL

```bash
psql -U <utilisateur> -d <base> -f index.sql
```

Ou manuellement :

```sql
CREATE INDEX IF NOT EXISTS idx_radcheck_auth_reject
    ON radcheck (attribute, value)
    WHERE attribute = 'Auth-Type' AND value = 'Reject';

CREATE INDEX IF NOT EXISTS idx_radacct_visitor_mac
    ON radacct (username, CallingStationId)
    WHERE AcctStartTime IS NOT NULL;
```

### 4. Tester la configuration

```bash
# Vérifier la syntaxe
freeradius -XC

# Tester en mode debug
freeradius -X
```

### 5. Relancer FreeRADIUS

```bash
sudo systemctl restart freeradius
```

## Test manuel

### Test 1 : MAC blacklistée + identifiant visiteur valide

```bash
# 1. Ajouter une MAC à la liste noire via le dashboard
# 2. Créer un identifiant visiteur
# 3. Tester la connexion avec l'identifiant visiteur depuis l'appareil blacklisté
# → Doit être REJECT

radtest -x <visiteur> <mot_de_passe> <nas_ip> <nas_port> <secret> \
    -e "Calling-Station-Id=aa:bb:cc:dd:ee:ff"
```

### Test 2 : Visiteur utilisé par 2 appareils

```bash
# 1. Créer un identifiant visiteur
# 2. Se connecter avec l'appareil A → SUCCESS
# 3. Se connecter avec l'appareil B (même identifiant, MAC différente) → REJECT
```

### Test 3 : Tentative échouée ne réserve pas l'identifiant

```bash
# 1. Créer un identifiant visiteur
# 2. Appareil blacklisté tente de l'utiliser → REJECT
# 3. Appareil non-blacklisté utilise le même identifiant → SUCCESS
```
