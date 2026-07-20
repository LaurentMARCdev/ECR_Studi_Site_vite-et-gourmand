# API Back-end

API REST Symfony 7 + PostgreSQL pour l'application Vite & Gourmand.

## Stack technique

- **Framework** : Symfony 7 (PHP 8.2+)
- **BDD relationnelle** : PostgreSQL 15+
- **BDD NoSQL** : MongoDB (statistiques admin)
- **ORM** : Doctrine 3
- **Authentification** : Sessions PHP + cookies HttpOnly, hashing Argon2id
- **Sécurité** : Rate limiting, CSRF, CORS, protection contre l'énumération de comptes

## Structure du projet

```
src/
├── Controller/      # Endpoints HTTP (couche fine)
├── Service/         # Logique métier
├── Entity/          # Modèles Doctrine (mapping BDD)
├── Repository/      # Requêtes personnalisées Doctrine
├── DTO/             # Objets de transfert (validation)
├── Security/        # Authenticator custom
└── EventListener/   # Écouteurs d'événements

config/              # Configuration YAML
migrations/          # Migrations Doctrine (PHP + SQL brut)
templates/emails/    # Templates des e-mails transactionnels
```

## Installation

### 1. Prérequis

- PHP 8.2 ou supérieur avec extensions : `pdo_pgsql`, `mongodb`, `mbstring`, `intl`, `ctype`, `iconv`, `xml`
- Composer 2+
- PostgreSQL 15+
- MongoDB 6+ (pour les stats admin — fallback SQL automatique sinon)
- Un serveur SMTP local pour les tests (Mailhog, Mailtrap ou log)

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer l'environnement

Créer un fichier `.env.local` (non versionné) qui surcharge `.env` :

```env
APP_SECRET=<générer avec `openssl rand -hex 32`>
DATABASE_URL="postgresql://<user>:<password>@127.0.0.1:5432/vitegourmand?serverVersion=16"
MAILER_DSN=smtp://localhost:1025
FRONTEND_URL=http://localhost:5173
```

### 4. Créer la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

Alternativement, exécuter directement le script SQL fourni :

```bash
psql -U <user> -d vitegourmand -f migrations/sql/001_auth.sql
```

### 5. Lancer le serveur

```bash
symfony serve -d
# ou
php -S 127.0.0.1:8000 -t public/
```

L'API est accessible sur `http://127.0.0.1:8000/api/`.

## Endpoints — Domaine Auth

| Méthode | Route                            | Public | Description                                       |
|---------|----------------------------------|--------|---------------------------------------------------|
| POST    | `/api/auth/inscription`          | ✅     | Créer un compte utilisateur                       |
| POST    | `/api/auth/login`                | ✅     | Se connecter (JSON `{ email, motDePasse }`)       |
| POST    | `/api/auth/logout`               | ✅     | Se déconnecter (invalide la session)              |
| POST    | `/api/auth/mot-de-passe-oublie`  | ✅     | Envoyer un lien de réinitialisation par mail      |
| POST    | `/api/auth/reinitialiser`        | ✅     | Appliquer un nouveau mot de passe via un token    |
| GET     | `/api/auth/me`                   | ❌     | Récupérer les infos de l'utilisateur connecté     |

## Choix de sécurité

- **Argon2id** pour le hachage des mots de passe (recommandation ANSSI)
- **Cookie HttpOnly** de session (protégé contre le XSS)
- **SameSite=Lax** par défaut (protection CSRF)
- **Rate limiting** sur login (5/min), mot de passe oublié (3/15min), contact (5/15min)
- **Aucune énumération** de comptes : le mot de passe oublié renvoie toujours 200
- **Token de reset** : token cryptographiquement sûr (32 bytes), stocké en SHA-256, expire en 1 h, à usage unique
- **CORS strict** avec `allow_credentials: true`
- **Login throttling** géré par Symfony Security (compteur automatique après échecs)

## Comptes de test après seeding

| Rôle          | E-mail                   | Mot de passe    |
|---------------|--------------------------|-----------------|
| Administrateur| admin@vitegourmand.fr    | Admin@Vite2025  |

**Important** : changer le mot de passe admin dès la première connexion.

## Endpoints — Domaine Menus & Plats

### Publics (pas d'auth)

| Méthode | Route                                       | Description                                     |
|---------|---------------------------------------------|-------------------------------------------------|
| GET     | `/api/menus`                                | Liste des menus avec filtres dynamiques         |
| GET     | `/api/menus/{id}`                           | Détail d'un menu avec plats groupés             |
| GET     | `/api/menus/{id}/similaires`                | 3 autres menus (section "Vous aimerez aussi")   |
| GET     | `/api/referentiels/themes`                  | Liste des thèmes                                |
| GET     | `/api/referentiels/regimes`                 | Liste des régimes                               |
| GET     | `/api/referentiels/allergenes`              | Liste des allergènes                            |

**Filtres supportés sur `/api/menus`** (query params, tous optionnels) :
- `prixMin`, `prixMax` : fourchette de prix (sur le prix total à partir de X personnes)
- `themeId` ou `themeLibelle` : filtre par thème
- `regimeId` ou `regimeLibelle` : filtre par régime
- `personnesMin` : menus commandables pour ≤ N personnes minimum

### Gestion (auth requise : ROLE_EMPLOYE)

| Méthode | Route                             | Description                                     |
|---------|-----------------------------------|-------------------------------------------------|
| POST    | `/api/employe/menus`              | Créer un menu                                   |
| PUT     | `/api/employe/menus/{id}`         | Modifier un menu                                |
| DELETE  | `/api/employe/menus/{id}`         | Désactiver un menu (soft delete)                |
| GET     | `/api/employe/plats`              | Lister les plats (filtre `?categorie=...`)      |
| POST    | `/api/employe/plats`              | Créer un plat                                   |
| PUT     | `/api/employe/plats/{id}`         | Modifier un plat                                |
| DELETE  | `/api/employe/plats/{id}`         | Supprimer un plat (409 si utilisé dans un menu) |

## Endpoints — Domaine Commandes

### Utilisateur (auth requise : ROLE_UTILISATEUR)

| Méthode | Route                                 | Description                                    |
|---------|---------------------------------------|------------------------------------------------|
| POST    | `/api/commandes`                      | Créer une commande                             |
| GET     | `/api/commandes/mes-commandes`        | Liste des commandes de l'utilisateur connecté  |
| GET     | `/api/commandes/{numero}`             | Détail d'une commande                          |
| PUT     | `/api/commandes/{numero}/modifier`    | Modifier (si statut = en_attente)              |
| DELETE  | `/api/commandes/{numero}`             | Annuler (si statut = en_attente)               |

### Employé / Admin (auth requise : ROLE_EMPLOYE)

| Méthode | Route                                        | Description                                     |
|---------|----------------------------------------------|-------------------------------------------------|
| GET     | `/api/employe/commandes`                     | Liste toutes les commandes avec filtres         |
| GET     | `/api/employe/commandes/{numero}`            | Détail complet (avec infos client)              |
| PUT     | `/api/employe/commandes/{numero}/statut`     | Transitionner le statut                         |
| DELETE  | `/api/employe/commandes/{numero}`            | Annuler (motif + mode contact obligatoires)     |

**Filtres supportés sur `/api/employe/commandes`** (query params, tous optionnels) :
- `statut` : filtre par statut (`en_attente`, `accepte`, ...)
- `clientQuery` : recherche libre sur nom / prénom / e-mail
- `menuId` : filtre par ID de menu
- `datePrestation` : filtre par date exacte (YYYY-MM-DD)

## Règles métier — Commandes

- **Prix menu** = `prix_par_personne × nombre_personnes` (minimum obligatoire)
- **Réduction 10%** automatique si `nb_personnes ≥ minimum + 5`
- **Livraison** : 5 € + 0,59 €/km hors Bordeaux (gratuite dans Bordeaux)
- **Numéro** au format `VG-YYYY-NNNN`, unique
- **Cycle de vie** : `en_attente → accepte → en_preparation → en_cours_livraison → livre → (attente_materiel) → terminee` — ou `annulee`
- **Verrou pessimiste** sur le menu lors de la création (protège contre la course sur le stock)
- **Notifications e-mail** aux étapes : confirmation, acceptation, retour matériel, annulation, invitation avis
- **Modification/annulation utilisateur** possible uniquement si statut = `en_attente`
- **Annulation employé** exige `modeContact` (gsm/mail) + `motif` (règle du sujet)

## Endpoints — Domaine Admin

Toutes les routes exigent `ROLE_ADMINISTRATEUR`.

### Gestion des employés

| Méthode | Route                                            | Description                                    |
|---------|--------------------------------------------------|------------------------------------------------|
| GET     | `/api/admin/employes`                            | Liste tous les employés                        |
| POST    | `/api/admin/employes`                            | Créer un compte employé (envoi mail sans mdp)  |
| PUT     | `/api/admin/employes/{id}/toggle-actif`          | Activer / désactiver un compte                 |

**Règle importante** : le mot de passe n'est **jamais** transmis par e-mail — l'admin doit le communiquer verbalement à l'employé (règle du sujet).

### Statistiques (source : MongoDB avec fallback SQL)

| Méthode | Route                                                | Description                                 |
|---------|------------------------------------------------------|---------------------------------------------|
| GET     | `/api/admin/stats/commandes-par-menu`                | Nombre de commandes par menu                |
| GET     | `/api/admin/stats/chiffre-affaires?debut=…&fin=…`    | CA par menu, filtrable par période          |

### Gestion des commandes (alias des routes employé)

| Méthode | Route                                              | Description                                |
|---------|----------------------------------------------------|--------------------------------------------|
| GET     | `/api/admin/commandes`                             | Liste toutes les commandes (mêmes filtres) |
| PUT     | `/api/admin/commandes/{numero}/statut`             | Transitionner le statut                    |
| DELETE  | `/api/admin/commandes/{numero}`                    | Annuler (motif + mode contact obligatoires)|

## Architecture NoSQL (MongoDB)

Le sujet impose l'usage d'une base NoSQL pour les statistiques. L'architecture retenue :

- **PostgreSQL** = source de vérité transactionnelle (ACID, contraintes, référentielle)
- **MongoDB** = stockage dénormalisé pour lectures rapides (agrégations statistiques)

**Synchronisation** : un `CommandeStatisticsListener` Doctrine intercepte les événements `postPersist` et `postUpdate` sur les entités `Commande` et met automatiquement à jour la collection `statistiques_commandes`. Aucune ligne de code métier ne dépend directement de MongoDB.

**Fail-safe** : si MongoDB est indisponible, l'API :
1. Ne bloque JAMAIS les mutations SQL (les commandes continuent de passer)
2. Log un warning explicite
3. Retombe sur une agrégation SQL Doctrine pour les endpoints de stats

### Initialisation de MongoDB

```bash
# 1. Démarrer MongoDB (Docker recommandé)
docker run -d --name vitegourmand-mongo -p 27017:27017 mongo:7

# 2. Créer la collection et les index
mongosh mongodb://127.0.0.1:27017/vitegourmand_stats \
    < migrations/mongo/004_mongo_init.js

# 3. Synchroniser les commandes existantes depuis PostgreSQL
php bin/console app:sync-mongo
```

## Endpoints — Domaine Avis

### Public (pas d'auth)

| Méthode | Route                       | Description                                    |
|---------|-----------------------------|------------------------------------------------|
| GET     | `/api/avis?limite=6`        | Avis validés pour la page d'accueil            |

**Note RGPD** : les avis publics n'exposent que le prénom + initiale du nom.

### Utilisateur (auth requise : ROLE_UTILISATEUR)

| Méthode | Route                | Description                                              |
|---------|----------------------|----------------------------------------------------------|
| POST    | `/api/avis`          | Déposer un avis (commande TERMINÉE obligatoire)          |
| GET     | `/api/avis/mes-avis` | Liste des avis déposés par l'utilisateur                 |

### Modération (auth requise : ROLE_EMPLOYE, admin par héritage)

| Méthode | Route                                            | Description                     |
|---------|--------------------------------------------------|---------------------------------|
| GET     | `/api/employe/avis?statut=en_attente\|valide\|refuse` | File de modération       |
| PUT     | `/api/employe/avis/{id}/valider`                 | Publier l'avis                  |
| DELETE  | `/api/employe/avis/{id}/refuser`                 | Refuser (garde en base)         |

### Alias sous /api/admin/avis (pour le front admin)

Mêmes routes sous le préfixe `/api/admin/avis` — délégation transparente au même service.

## Règles métier — Avis

- **Note** entre 1 et 5 (contrainte CHECK en base)
- **Commentaire** obligatoire (10 à 2000 caractères)
- **Un seul avis par commande** (contrainte UNIQUE en base)
- **La commande doit être TERMINÉE** pour pouvoir déposer un avis
- **Modération obligatoire** avant publication (règle du sujet)
- **Traçabilité** : `moderateur_id` conservé pour audit
- **RGPD** : côté public, seul le prénom + initiale du nom sont affichés

## Endpoints — Domaine Contact

| Méthode | Route            | Description                                          |
|---------|------------------|------------------------------------------------------|
| POST    | `/api/contact`   | Envoi d'un message via le formulaire de contact      |

Body : `{ titre, email, description }`

**Sécurité** : rate limiting 5 requêtes / 15 minutes par IP (configuré dans `framework.yaml`). L'e-mail est envoyé à l'adresse `CONTACT_EMAIL` définie en environnement, avec `Reply-To` = e-mail du visiteur pour permettre une réponse directe. Aucune donnée n'est persistée en base — le service e-mail sert de stockage.

## Endpoints — Domaine Horaires

### Public (pas d'auth)

| Méthode | Route            | Description                                    |
|---------|------------------|------------------------------------------------|
| GET     | `/api/horaires`  | Horaires d'ouverture (footer, page contact)    |

### Employé / Admin (auth requise : ROLE_EMPLOYE)

| Méthode | Route                     | Description                                  |
|---------|---------------------------|----------------------------------------------|
| PUT     | `/api/employe/horaires`   | Mise à jour en masse des 7 jours (atomique)  |

Body : tableau de 7 objets `HoraireDTO` (un par jour).

## État global du back-end

- [x] **Auth** — Utilisateurs, rôles, session, mot de passe oublié
- [x] **Menus & Plats** — Menus, plats, allergènes, thèmes, régimes, images
- [x] **Commandes** — Cycle de vie complet, calcul prix + réduction, notifications
- [x] **Admin** — Gestion employés, statistiques MongoDB (avec fallback SQL)
- [x] **Avis** — Notation, modération, publication (RGPD)
- [x] **Contact** — Formulaire de contact avec rate limiting
- [x] **Horaires** — CRUD des horaires d'ouverture

**Back-end complet — prêt pour déploiement.**
